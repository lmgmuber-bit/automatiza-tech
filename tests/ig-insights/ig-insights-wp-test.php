<?php
// Pruebas locales de Métricas IG (tomas, estados de permiso y token, tachado del token de los canales).
// La Graph API de Instagram está simulada: ninguna llamada sale a Meta. Nunca contra producción.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/ig-insights/ig-insights-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
require_once __DIR__ . '/../../omnichannel-controller.php';
require_once __DIR__ . '/../../omnichannel-ig-insights-controller.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$TOKEN = 'tok-prueba-' . bin2hex(random_bytes(12));
$H = HOUR_IN_SECONDS;

// --- Canal de prueba (los canales reales del sitio local no se leen ni se imprimen)
$wpdb->insert($P . 'channels', [
	'client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG métricas',
	'is_active' => 1, 'webhook_secret' => 'secreto-webhook-prueba', 'bot_token' => $TOKEN,
	'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
]);
$CID = (int) $wpdb->insert_id;
ok($CID > 0, 'canal de prueba creado');

register_shutdown_function(static function () use ($wpdb, $P, $CID) {
	$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$P}ig_posts WHERE channel_id = %d", $CID));
	if ($ids) { $wpdb->query("DELETE FROM {$P}ig_post_metrics WHERE post_id IN (" . implode(',', array_map('intval', $ids)) . ')'); }
	$wpdb->delete($P . 'ig_posts', ['channel_id' => $CID]);
	$wpdb->delete($P . 'ig_account_daily', ['channel_id' => $CID]);
	$wpdb->delete($P . 'audit_log', ['entity_type' => 'channel', 'entity_id' => $CID]);
	$wpdb->delete($P . 'channels', ['id' => $CID]);
	delete_option('omni_ig_insights_state_' . $CID);
	delete_option('omni_ig_insights_profile_' . $CID);
	delete_option('omni_ig_insights_lock_' . $CID);
});

// --- Simulación de graph.instagram.com
$now = time();
$iso = static function ($ts) { return gmdate('Y-m-d\TH:i:s', $ts) . '+0000'; };
$GLOBALS['ig'] = [
	'mode'  => 'ok',     // ok | no_perm | token_expired
	'calls' => [],
	'auth_ok' => true,
	'token' => $TOKEN,
	'media' => [
		['id' => 'm-reel-25h', 'caption' => 'Reel de 25 h', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'timestamp' => $iso($now - 25 * $H), 'permalink' => 'https://www.instagram.com/reel/a/', 'like_count' => 11, 'comments_count' => 2],
		['id' => 'm-img-49h', 'caption' => 'Imagen de 49 h', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'timestamp' => $iso($now - 49 * $H), 'permalink' => 'https://www.instagram.com/p/b/', 'like_count' => 7, 'comments_count' => 0],
		['id' => 'm-vid-200h', 'caption' => 'Video sin tipo de 200 h', 'media_type' => 'VIDEO', 'timestamp' => $iso($now - 200 * $H), 'permalink' => 'https://www.instagram.com/reel/c/', 'like_count' => 3, 'comments_count' => 1],
		['id' => 'm-reel-40d', 'caption' => 'Reel de 40 días', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'timestamp' => $iso($now - 960 * $H), 'permalink' => 'https://www.instagram.com/reel/d/', 'like_count' => 30, 'comments_count' => 4],
	],
	'stories' => [
		['id' => 'm-story-2h', 'media_type' => 'VIDEO', 'timestamp' => $iso($now - 2 * $H), 'permalink' => 'https://www.instagram.com/stories/x/'],
	],
	'reject_metric' => [],   // media_id que rechaza ig_reels_avg_watch_time
	'gone' => [],            // media_id que Meta da por inexistente
];
$VALS = ['views' => 1000, 'reach' => 800, 'likes' => 50, 'comments' => 5, 'saved' => 7, 'shares' => 3, 'total_interactions' => 65,
	'ig_reels_avg_watch_time' => 4321, 'ig_reels_video_view_total_time' => 99999, 'follows' => 2, 'profile_visits' => 9, 'replies' => 1];
$ACC = ['reach' => 300, 'views' => 900, 'accounts_engaged' => 40, 'total_interactions' => 70, 'likes' => 50, 'comments' => 6,
	'shares' => 4, 'saves' => 8, 'replies' => 2, 'profile_links_taps' => 3];

function ig_resp($code, $body) {
	return ['headers' => [], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
}
function ig_err($code, $msg, $sub = 0) {
	$e = ['message' => $msg, 'type' => 'OAuthException', 'code' => $code, 'fbtrace_id' => 'Aprueba'];
	if ($sub) { $e['error_subcode'] = $sub; }
	return ig_resp(400, ['error' => $e]);
}
add_filter('pre_http_request', static function ($pre, $args, $url) use ($VALS, $ACC) {
	if (strpos($url, 'https://graph.instagram.com/') !== 0) { return $pre; }
	$g = &$GLOBALS['ig'];
	$g['calls'][] = $url;
	if (($args['headers']['Authorization'] ?? '') !== 'Bearer ' . $g['token']) { $g['auth_ok'] = false; }
	if (($args['method'] ?? 'GET') !== 'GET') { return ig_err(1, 'Métricas IG solo debe leer'); }
	$p = wp_parse_url($url);
	$path = preg_replace('#^/v[0-9.]+/#', '', $p['path']);
	parse_str($p['query'] ?? '', $q);
	if ($g['mode'] === 'token_expired') { return ig_err(190, 'Error validating access token: Session has expired.'); }
	if ($path === 'me') {
		return ig_resp(200, ['id' => '9001', 'user_id' => '17841400000000000', 'username' => 'automatizatech.cl', 'followers_count' => 120, 'follows_count' => 10, 'media_count' => 4]);
	}
	if ($path === 'me/media') {
		$fields = explode(',', $q['fields'] ?? '');
		return ig_resp(200, ['data' => array_map(static function ($m) use ($fields) {
			return array_intersect_key($m, array_flip($fields));
		}, $g['media']), 'paging' => ['cursors' => ['before' => 'x', 'after' => 'y']]]);
	}
	if ($path === 'me/stories') { return ig_resp(200, ['data' => $g['stories']]); }
	if ($path === 'me/insights') {
		if ($g['mode'] === 'no_perm') { return ig_err(10, '(#10) Application does not have permission for this action'); }
		$metrics = explode(',', $q['metric'] ?? '');
		if ($metrics === ['follower_count']) {
			$vals = [];
			for ($t = (int) $q['since'] + DAY_IN_SECONDS; $t <= (int) $q['until']; $t += DAY_IN_SECONDS) {
				$vals[] = ['value' => 4, 'end_time' => gmdate('Y-m-d\TH:i:s', $t) . '+0000'];
			}
			return ig_resp(200, ['data' => [['name' => 'follower_count', 'period' => 'day', 'values' => $vals]]]);
		}
		if (($q['metric_type'] ?? '') !== 'total_value') { return ig_err(100, '(#100) metric_type=total_value requerido'); }
		return ig_resp(200, ['data' => array_map(static function ($m) use ($ACC) {
			return ['name' => $m, 'period' => 'day', 'total_value' => ['value' => $ACC[$m] ?? 0]];
		}, $metrics)]);
	}
	if (preg_match('#^([^/]+)/insights$#', $path, $mm)) {
		$id = rawurldecode($mm[1]);
		if ($g['mode'] === 'no_perm') { return ig_err(10, '(#10) Application does not have permission for this action'); }
		if (in_array($id, $g['gone'], true)) { return ig_err(100, 'Unsupported get request. Object with ID does not exist', 33); }
		$metrics = explode(',', $q['metric'] ?? '');
		if (in_array($id, $g['reject_metric'], true) && in_array('ig_reels_avg_watch_time', $metrics, true)) {
			return ig_err(100, '(#100) The value must be a valid insights metric: ig_reels_avg_watch_time');
		}
		return ig_resp(200, ['data' => array_map(static function ($m) use ($VALS) {
			return ['name' => $m, 'period' => 'lifetime', 'values' => [['value' => $VALS[$m] ?? 0]], 'id' => 'x/insights/' . $m . '/lifetime'];
		}, $metrics)]);
	}
	return ig_err(100, 'Ruta no simulada: ' . $path);
}, PHP_INT_MAX, 3);

$omni = new OmnichannelController();
$igc = new OmniIgInsightsController($omni);
$igc->maybe_create_tables();
foreach (['ig_posts', 'ig_post_metrics', 'ig_account_daily'] as $t) {
	ok((bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $P . $t)), "tabla {$t} creada");
}
$reset_throttle = static function () use ($CID) {
	$s = get_option('omni_ig_insights_state_' . $CID, []);
	if (is_array($s)) { $s['last_sync_at'] = gmdate('Y-m-d H:i:s', time() - 3600); update_option('omni_ig_insights_state_' . $CID, $s, false); }
};
$metric = static function ($media_id, $cp) use ($wpdb, $P, $CID) {
	return $wpdb->get_row($wpdb->prepare(
		"SELECT m.* FROM {$P}ig_post_metrics m JOIN {$P}ig_posts p ON p.id = m.post_id WHERE p.channel_id = %d AND p.media_id = %s AND m.checkpoint = %s",
		$CID, $media_id, $cp
	));
};
// Envejece las tomas «latest» para que un force vuelva a leer (force no relee lo de menos de 30 min).
$age_latest = static function () use ($wpdb, $P, $CID) {
	$wpdb->query($wpdb->prepare(
		"UPDATE {$P}ig_post_metrics m JOIN {$P}ig_posts p ON p.id = m.post_id SET m.taken_at = DATE_SUB(m.taken_at, INTERVAL 2 HOUR) WHERE p.channel_id = %d AND m.checkpoint = 'latest'",
		$CID
	));
};
$post = static function ($media_id) use ($wpdb, $P, $CID) {
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}ig_posts WHERE channel_id = %d AND media_id = %s", $CID, $media_id));
};

// --- Lógica pura
$mk = static function ($hours, $pt = 'REELS') use ($now) {
	return (object) ['posted_at' => gmdate('Y-m-d H:i:s', $now - (int) round($hours * HOUR_IN_SECONDS)), 'media_product_type' => $pt, 'media_type' => 'VIDEO'];
};
ok($igc->due_checkpoints($mk(10), [], false, $now) === ['latest'], 'a las 10 h solo toca la última');
ok($igc->due_checkpoints($mk(25), [], false, $now) === ['24h', 'latest'], 'a las 25 h toca 24h');
ok($igc->due_checkpoints($mk(37), [], false, $now) === ['latest'], 'a las 37 h la de 24h ya no se rotula (pasó 1,5 veces)');
ok($igc->due_checkpoints($mk(49), ['24h' => gmdate('Y-m-d H:i:s', $now - 24 * $H)], false, $now) === ['48h', 'latest'], 'a las 49 h toca 48h');
ok($igc->due_checkpoints($mk(170), [], false, $now) === ['7d', 'latest'], 'a los 7 días toca 7d');
ok($igc->due_checkpoints($mk(721), [], false, $now) === ['30d', 'latest'], 'a los 30 días toca 30d');
ok($igc->due_checkpoints($mk(100), ['latest' => gmdate('Y-m-d H:i:s', $now - 2 * $H)], false, $now) === [], 'última de hace 2 h: nada pendiente');
ok($igc->due_checkpoints($mk(100), ['latest' => gmdate('Y-m-d H:i:s', $now - 2 * $H)], true, $now) === ['latest'], 'con force se refresca la última');
ok($igc->due_checkpoints($mk(100), ['latest' => gmdate('Y-m-d H:i:s', $now - 600)], true, $now) === [], 'con force no se relee una toma de hace 10 min');
ok($igc->due_checkpoints($mk(3, 'STORY'), ['latest' => gmdate('Y-m-d H:i:s', $now - 1800)], false, $now) === ['latest'], 'historia: se refresca en cada pasada');
ok($igc->post_kind((object) ['media_product_type' => '', 'media_type' => 'VIDEO']) === 'REELS', 'VIDEO sin tipo → Reel');
ok($igc->post_kind((object) ['media_product_type' => '', 'media_type' => 'CAROUSEL_ALBUM']) === 'FEED', 'carrusel → feed');
$parsed = OmniIgInsightsController::parse_insights(['data' => [
	['name' => 'reach', 'values' => [['value' => 12]]],
	['name' => 'views', 'total_value' => ['value' => 34]],
	['name' => 'raro', 'values' => [['value' => ['a' => 1]]]],
]]);
ok($parsed === ['reach' => 12, 'views' => 34], 'parse_insights lee values[0] y total_value, ignora no numéricos');
ok(OmniIgInsightsController::classify_error(['error' => 'x', 'fb_error' => ['code' => 190]])['status'] === 'token_invalid', '190 → token_invalid');
ok(OmniIgInsightsController::classify_error(['error' => '(#10) Application does not have permission for this action', 'fb_error' => ['code' => 10]])['status'] === 'no_permission', '10 → no_permission');
ok(OmniIgInsightsController::classify_error(['error' => '(#10) Not enough viewers for the media to show insights', 'fb_error' => ['code' => 10]])['status'] === 'error', '10 por pocos espectadores no es falta de permiso');
ok(strpos(OmniIgInsightsController::classify_error(['error' => 'algo ' . str_repeat('A', 60), 'fb_error' => ['code' => 1]])['message'], str_repeat('A', 60)) === false, 'una cadena larga tipo token se tacha del mensaje');

// --- Estado inicial
ok($igc->get_status($CID)['status'] === 'never', 'sin tomas: estado never');

// --- 1) Token sin permiso de insights: datos básicos sí, métricas no, aviso claro
$GLOBALS['ig']['mode'] = 'no_perm';
$r = $igc->sync($CID);
ok(($r['status'] ?? '') === 'no_permission', 'sin permiso: estado no_permission');
ok(!empty($r['ok']), 'sin permiso: la toma igual responde ok (lo básico se guardó)');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}ig_posts WHERE channel_id = %d", $CID)) === 5, 'sin permiso: 4 publicaciones y 1 historia registradas');
ok((int) $post('m-reel-25h')->like_count === 11, 'sin permiso: likes del reel guardados desde /me/media');
ok(!$metric('m-reel-25h', 'latest'), 'sin permiso: ninguna toma de métricas inventada');
$ov = $igc->get_overview($CID, 7);
ok($ov['insights_status']['status'] === 'no_permission' && strpos($ov['insights_status']['message'], 'instagram_business_manage_insights') !== false, 'resumen: avisa qué permiso falta');
ok($ov['account']['followers_count'] === 120 && $ov['account']['username'] === 'automatizatech.cl', 'resumen: seguidores reales del perfil');
ok($ov['period']['totals']['reach'] === null, 'resumen: alcance sin dato queda null, no 0');
$calls_no_perm = count($GLOBALS['ig']['calls']);
ok($calls_no_perm <= 6, "sin permiso: corta al primer error de permiso ({$calls_no_perm} llamadas)");

// --- 2) Con permiso
$GLOBALS['ig']['mode'] = 'ok';
$GLOBALS['ig']['calls'] = [];
$reset_throttle();
$r = $igc->sync($CID);
ok(($r['status'] ?? '') === 'ok' && !empty($r['ok']), 'con permiso: estado ok');
$m24 = $metric('m-reel-25h', '24h');
ok($m24 && (int) $m24->views === 1000 && (int) $m24->reach === 800 && (int) $m24->avg_watch_time_ms === 4321, 'reel de 25 h: toma 24h con views, reach y tiempo medio');
ok($m24 && $m24->follows === null, 'reel: follows no se pide (Meta no lo da en Reels) y queda NULL');
ok((bool) $metric('m-reel-25h', 'latest'), 'reel de 25 h: también la última');
ok(!$metric('m-reel-25h', '48h'), 'reel de 25 h: sin toma 48h todavía');
$m48 = $metric('m-img-49h', '48h');
ok($m48 && (int) $m48->follows === 2 && (int) $m48->profile_visits === 9 && $m48->avg_watch_time_ms === null, 'imagen de 49 h: toma 48h con follows y visitas al perfil');
ok((bool) $metric('m-vid-200h', '7d'), 'video sin tipo de 200 h: toma 7d');
ok(!$metric('m-reel-40d', 'latest'), 'publicación de 40 días: no se gasta una llamada');
ok((bool) $metric('m-story-2h', 'latest'), 'historia vigente: toma latest');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}ig_account_daily WHERE channel_id = %d AND insights_at IS NOT NULL", $CID)) === 3, 'cuenta: 3 días completos guardados');
$ayer = (new DateTimeImmutable('yesterday', new DateTimeZone('America/Santiago')))->format('Y-m-d');
$d = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}ig_account_daily WHERE channel_id = %d AND day = %s", $CID, $ayer));
ok($d && (int) $d->reach === 300 && (int) $d->views === 900 && (int) $d->profile_links_taps === 3, 'cuenta: ayer con alcance, vistas y toques de enlaces');
ok($d && (int) $d->followers_gained === 4, 'cuenta: seguidores nuevos del día (cuenta con ≥100)');
ok($GLOBALS['ig']['auth_ok'], 'el token viaja solo en la cabecera Authorization');
$en_url = false;
foreach ($GLOBALS['ig']['calls'] as $u) { if (strpos($u, $TOKEN) !== false || strpos($u, 'access_token') !== false) { $en_url = true; } }
ok(!$en_url, 'el token nunca va en la URL');
ok((int) $r['api_calls'] <= 14, "con permiso: pocas llamadas a Meta ({$r['api_calls']})");

$ov = $igc->get_overview($CID, 7);
ok($ov['period']['totals']['reach'] === 900 && $ov['period']['days_with_data'] === 3, 'resumen: total de alcance de 3 días');
$reel = null;
foreach ($ov['posts'] as $p) { if ($p['media_id'] === 'm-reel-25h') { $reel = $p; } }
ok($reel && $reel['checkpoints']['24h']['views'] === 1000 && $reel['checkpoints']['48h'] === null, 'resumen: el reel trae su toma 24h y la 48h vacía');
ok($reel && $reel['kind'] === 'REELS' && substr($reel['posted_at'], -1) === 'Z', 'resumen: tipo y fecha ISO en UTC');
ok(strpos(wp_json_encode($ov), $TOKEN) === false, 'el resumen nunca contiene el token');

// --- 3) Freno: dos tomas seguidas
$r2 = $igc->sync($CID, true);
ok(!empty($r2['skipped']), 'una segunda toma antes de 2 min se salta, aun con force');

// --- 4) Sin nada pendiente: no se llama a /insights de nuevo
$GLOBALS['ig']['calls'] = [];
$reset_throttle();
$igc->sync($CID);
$ins = array_filter($GLOBALS['ig']['calls'], static function ($u) { return strpos($u, '/insights') !== false && strpos($u, 'm-story') === false; });
ok(count($ins) === 0, 'pasada sin pendientes: solo la historia vuelve a /insights');

// --- 5) Métrica rechazada: baja de nivel sin perder la toma
$GLOBALS['ig']['reject_metric'] = ['m-reel-25h'];
$reset_throttle();
$age_latest();
$r = $igc->sync($CID, true);
$lat = $metric('m-reel-25h', 'latest');
ok($lat && (int) $lat->views === 1000 && $lat->avg_watch_time_ms === null, 'métrica rechazada: se reintenta sin ella y se guarda el resto');
$m24b = $metric('m-reel-25h', '24h');
ok($m24b && (int) $m24b->avg_watch_time_ms === 4321, 'la toma fija de 24h no se pisa después');
$GLOBALS['ig']['reject_metric'] = [];

// --- 6) Publicación borrada en Instagram
$GLOBALS['ig']['media'] = array_values(array_filter($GLOBALS['ig']['media'], static function ($m) { return $m['id'] !== 'm-img-49h'; }));
$reset_throttle();
$igc->sync($CID, true);
ok($post('m-img-49h')->status === 'deleted', 'publicación que desapareció de /me/media → deleted');
$GLOBALS['ig']['gone'] = ['m-vid-200h'];
$reset_throttle();
$age_latest();
$igc->sync($CID, true);
ok($post('m-vid-200h')->status === 'deleted', 'Meta dice que el objeto no existe → deleted');
$GLOBALS['ig']['gone'] = [];

// --- 6b) Candado: una toma en curso frena a la otra; un candado viejo no traba para siempre
$reset_throttle();
add_option('omni_ig_insights_lock_' . $CID, time(), '', 'no');
$r = $igc->sync($CID);
ok(!empty($r['skipped']) && strpos($r['reason'], 'en curso') !== false, 'con otra toma en curso, se salta');
update_option('omni_ig_insights_lock_' . $CID, time() - 600, false);
$r = $igc->sync($CID);
ok(empty($r['skipped']), 'un candado de hace 10 min se considera muerto y la toma sigue');
ok(get_option('omni_ig_insights_lock_' . $CID, 'no') === 'no', 'al terminar la toma suelta el candado');

// --- 7) Token vencido
$GLOBALS['ig']['mode'] = 'token_expired';
$reset_throttle();
$r = $igc->sync($CID);
ok(($r['status'] ?? '') === 'token_invalid' && empty($r['ok']), 'token vencido: token_invalid y ok=false (el cron avisa)');
ok($igc->get_status($CID)['status'] === 'token_invalid', 'el estado guardado queda en token_invalid');
ok(strpos(wp_json_encode(get_option('omni_ig_insights_state_' . $CID)), $TOKEN) === false, 'el estado guardado no contiene el token');

// --- 8) Canal sin token
$wpdb->update($P . 'channels', ['bot_token' => ''], ['id' => $CID]);
$reset_throttle();
$r = $igc->sync($CID);
ok(($r['status'] ?? '') === 'no_token', 'canal sin token: no_token sin llamar a Meta');
$wpdb->update($P . 'channels', ['bot_token' => $TOKEN], ['id' => $CID]);

// --- 9) El token de los canales ya no sale al navegador ni a la auditoría
$todos = $omni->get_all_channels();
$filtra = false;
foreach ($todos as $ch) { if ($ch->bot_token !== '') { $filtra = true; } }
ok(!$filtra, 'get_all_channels: ningún canal trae bot_token');
$mio = null;
foreach ($todos as $ch) { if ((int) $ch->id === $CID) { $mio = $ch; } }
ok($mio && $mio->has_bot_token === true, 'get_all_channels: has_bot_token=true en el canal con token');
$del_cliente = $omni->get_channels(0);
$filtra = false;
foreach ($del_cliente as $ch) { if ($ch->bot_token !== '') { $filtra = true; } }
ok(!$filtra, 'get_channels (clientes y agentes): ningún canal trae bot_token');
$token_igual = static function ($t) use ($wpdb, $P, $CID) {
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}channels WHERE id = %d AND bot_token = %s", $CID, $t)) === 1;
};
ok($omni->update_channel($CID, ['channel_name' => '[PRUEBA] IG métricas 2', 'bot_token' => '']) === true, 'editar con el token vacío responde bien');
ok($token_igual($TOKEN), 'editar con el token vacío conserva el token guardado');
$NUEVO = 'tok-prueba-nuevo-' . bin2hex(random_bytes(10));
$omni->update_channel($CID, ['bot_token' => $NUEVO]);
ok($token_igual($NUEVO), 'pegar un token nuevo lo reemplaza');
$audit = $wpdb->get_col($wpdb->prepare("SELECT CONCAT(IFNULL(old_values_json,''), IFNULL(new_values_json,'')) FROM {$P}audit_log WHERE entity_type = 'channel' AND entity_id = %d", $CID));
$en_audit = false;
foreach ($audit as $a) { if (strpos($a, $TOKEN) !== false || strpos($a, $NUEVO) !== false) { $en_audit = true; } }
ok($audit && !$en_audit, 'la auditoría del canal guarda [oculto] en vez del token');

fin();
