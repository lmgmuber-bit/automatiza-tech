<?php
// Pruebas locales de Anuncios de Meta (lectura, caché, estados de token y permiso, freno por límite).
// La Marketing API está simulada: ninguna llamada sale a Meta. Nunca contra producción.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/meta-ads/meta-ads-wp-test.php
// Sin AT_WP_LOAD usa un WordPress mínimo en memoria (stub-wp.php): no necesita base de datos.
if (PHP_SAPI !== 'cli') { exit; }
if (getenv('AT_WP_LOAD')) {
	require __DIR__ . '/../retomar/wp-bootstrap.php';
} else {
	require __DIR__ . '/stub-wp.php';
}
require_once __DIR__ . '/../../omnichannel-meta-ads-controller.php';

$TOKEN = 'tok-ads-prueba-' . bin2hex(random_bytes(12));
$OPTS = [OmniMetaAdsController::CACHE_OPTION, OmniMetaAdsController::COOLDOWN_OPTION, OmniMetaAdsController::LOCK_OPTION];
$backup = [];
foreach ($OPTS as $o) { $backup[$o] = get_option($o, null); delete_option($o); }
register_shutdown_function(static function () use ($OPTS, $backup) {
	foreach ($OPTS as $o) {
		delete_option($o);
		if ($backup[$o] !== null) { update_option($o, $backup[$o], 'no'); }
	}
});
$reset = static function () use ($OPTS) { foreach ($OPTS as $o) { delete_option($o); } };

// --- Simulación de graph.facebook.com (Marketing API)
$GLOBALS['fb'] = ['mode' => 'ok', 'calls' => [], 'auth_ok' => true, 'token_in_url' => false, 'non_get' => false, 'token' => $TOKEN, 'reject_field' => false];
function fb_resp($code, $body) {
	return ['headers' => [], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
}
function fb_err($code, $msg) {
	return fb_resp(400, ['error' => ['message' => $msg, 'type' => 'OAuthException', 'code' => $code, 'fbtrace_id' => 'Aprueba']]);
}
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://graph.facebook.com/') !== 0) { return new WP_Error('prueba_local', 'HTTP externo bloqueado en pruebas.'); }
	$g = &$GLOBALS['fb'];
	$g['calls'][] = $url;
	if (($args['headers']['Authorization'] ?? '') !== 'Bearer ' . $g['token']) { $g['auth_ok'] = false; }
	if (strpos($url, $g['token']) !== false) { $g['token_in_url'] = true; }
	if (($args['method'] ?? 'GET') !== 'GET') { $g['non_get'] = true; return fb_err(1, 'Anuncios solo debe leer'); }
	$p = wp_parse_url($url);
	$path = preg_replace('#^/v[0-9.]+/#', '', $p['path']);
	parse_str($p['query'] ?? '', $q);
	if ($g['mode'] === 'token_expired') { return fb_err(190, 'Error validating access token: Session has expired.'); }
	if ($g['mode'] === 'no_perm') { return fb_err(200, '(#200) Ad account owner has NOT grant ads_management or ads_read permission'); }
	if ($g['mode'] === 'rate') { return fb_err(80004, 'There have been too many calls to this ad-account.'); }
	if ($path === 'act_1234567890123456') {
		return fb_resp(200, ['name' => 'AutomatizaTech Ads', 'currency' => 'CLP', 'account_status' => 1, 'timezone_name' => 'America/Santiago', 'id' => 'act_1234567890123456']);
	}
	if ($path === 'act_1234567890123456/ads') {
		return fb_resp(200, ['data' => [
			['id' => 'ad-1', 'name' => 'Mensajes', 'effective_status' => 'ACTIVE', 'created_time' => '2026-11-04T15:45:00-0300',
				'adset' => ['start_time' => '2026-11-04T15:45:00-0300', 'end_time' => '2026-11-07T15:45:00-0300'],
				'creative' => ['title' => 'AutomatizaTech', 'body' => 'Somos AutomatizaTech…', 'thumbnail_url' => 'https://scontent.example/a.jpg']],
			['id' => 'ad-2', 'name' => 'Mensajes 4 preguntas', 'effective_status' => 'ACTIVE', 'created_time' => '2026-11-04T17:45:00-0300',
				'adset' => ['start_time' => '2026-11-04T17:45:00-0300', 'end_time' => '2026-11-07T17:45:00-0300'],
				'creative' => ['title' => 'Diagnóstico gratis', 'body' => 'Antes de recomendarte…']],
		]]);
	}
	if ($path === 'act_1234567890123456/insights') {
		if (isset($q['time_increment'])) {
			return fb_resp(200, ['data' => [
				['date_start' => '2026-11-04', 'spend' => '1500', 'impressions' => '400', 'reach' => '350', 'actions' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '1']]],
				['date_start' => '2026-11-05', 'spend' => '2944', 'impressions' => '900', 'reach' => '700'],
			]]);
		}
		if ($g['reject_field'] && strpos($q['fields'] ?? '', 'video_thruplay_watched_actions') !== false) {
			return fb_err(100, '(#100) video_thruplay_watched_actions is not valid for fields param');
		}
		return fb_resp(200, ['data' => [
			// ad-1 con conversaciones; ad-2 sin «actions» (todavía sin eventos).
			['ad_id' => 'ad-1', 'spend' => '4444', 'impressions' => '1300', 'reach' => '1050', 'frequency' => '1.238', 'cpm' => '3418.46', 'clicks' => '20',
				'actions' => [['action_type' => 'video_view', 'value' => '600'], ['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '2'], ['action_type' => 'post_engagement', 'value' => '640']],
				'cost_per_action_type' => [['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '2222']],
				'video_thruplay_watched_actions' => [['action_type' => 'video_view', 'value' => '120']],
				'date_start' => '2026-11-04', 'date_stop' => '2026-11-05'],
			['ad_id' => 'ad-2', 'spend' => '0', 'impressions' => '0', 'reach' => '0', 'date_start' => '2026-11-04', 'date_stop' => '2026-11-05'],
		]]);
	}
	return fb_err(100, 'Ruta no simulada: ' . $path);
}, PHP_INT_MAX, 3);

$c = new OmniMetaAdsController();

// 1. Sin configuración: no llama a Meta y lo dice.
$r = $c->get_overview(false);
ok($r['ads_status']['status'] === 'no_config', 'sin constantes → no_config');
ok(count($GLOBALS['fb']['calls']) === 0, 'sin constantes no se llama a Meta');
ok($r['ads'] === [] && $r['totals'] === null, 'sin constantes no hay cifras inventadas');

define('AT_META_ADS_TOKEN', $TOKEN);
define('AT_META_AD_ACCOUNT_ID', 'act_1234567890123456');
ok(OmniMetaAdsController::account_id() === '1234567890123456', 'el id acepta el prefijo act_');

// 2. Lectura normal.
$r = $c->get_overview(false);
ok($r['ads_status']['status'] === 'ok', 'lectura ok');
ok(count($GLOBALS['fb']['calls']) === 4, 'una lectura = 4 llamadas (cuenta, anuncios, resultados, días)');
ok($GLOBALS['fb']['auth_ok'], 'el token va en la cabecera Authorization');
ok(!$GLOBALS['fb']['token_in_url'], 'el token nunca va en la URL');
ok(!$GLOBALS['fb']['non_get'], 'solo GET');
ok(strpos($GLOBALS['fb']['calls'][0], 'graph.facebook.com/v') !== false, 'usa una versión fija de la Graph API');
ok($r['account']['currency'] === 'CLP' && $r['account']['name'] === 'AutomatizaTech Ads', 'cuenta y moneda');
ok(count($r['ads']) === 2 && $r['ads'][0]['id'] === 'ad-2', 'dos anuncios, el más nuevo primero');
$a1 = $r['ads'][1]['metrics'];
ok($a1['spend'] === 4444.0 && $a1['impressions'] === 1300 && $a1['reach'] === 1050, 'gasto, impresiones y alcance del anuncio 1');
ok($a1['conversations'] === 2 && $a1['cost_per_conversation'] === 2222.0, 'conversaciones y costo por conversación');
ok($a1['video_views'] === 600 && $a1['thruplays'] === 120, 'reproducciones y ThruPlays');
$a2 = $r['ads'][0]['metrics'];
ok($a2['conversations'] === null && $a2['video_views'] === null, 'sin «actions» las acciones quedan null, no 0');
ok($r['ads'][1]['end_at'] === '2026-11-07T18:45:00Z', 'fecha de término en UTC');
ok($r['totals']['spend'] === 4444.0 && $r['totals']['conversations'] === 2 && $r['totals']['cost_per_conversation'] === 2222.0, 'totales');
ok(count($r['daily']) === 2 && $r['daily'][0]['conversations'] === 1 && $r['daily'][1]['conversations'] === null, 'gasto por día');

// 3. Caché: la segunda lectura no llama a Meta.
$n = count($GLOBALS['fb']['calls']);
$r = $c->get_overview(false);
ok(count($GLOBALS['fb']['calls']) === $n && $r['ads_status']['status'] === 'ok', 'caché de 30 minutos');
// 4. Forzar antes de 2 minutos no relee.
$r = $c->get_overview(true);
ok(count($GLOBALS['fb']['calls']) === $n && strpos((string) $r['note'], '2 minutos') !== false, 'forzar antes de 2 minutos no relee');

// 5. Token vencido: conserva lo último bueno y avisa.
$cache = get_option(OmniMetaAdsController::CACHE_OPTION);
$cache['fetched_ts'] -= 3600; update_option(OmniMetaAdsController::CACHE_OPTION, $cache, 'no');
$GLOBALS['fb']['mode'] = 'token_expired';
$r = $c->get_overview(false);
ok($r['ads_status']['status'] === 'token_invalid', 'token vencido → token_invalid');
ok(count($r['ads']) === 2 && $r['ads_status']['data_at'] !== null, 'con error se muestran los últimos datos buenos con su fecha');

// 6. Sin permiso.
$reset(); $GLOBALS['fb']['mode'] = 'no_perm';
$r = $c->get_overview(false);
ok($r['ads_status']['status'] === 'no_permission' && $r['ads'] === [], 'sin ads_read → no_permission, sin cifras');
$n = count($GLOBALS['fb']['calls']);
$c->get_overview(false);
ok(count($GLOBALS['fb']['calls']) === $n, 'un fallo también queda en caché: abrir la pestaña no reintenta');

// 7. Límite de Meta: pausa de una hora sin llamadas.
$reset(); $GLOBALS['fb']['mode'] = 'rate';
$r = $c->get_overview(false);
ok($r['ads_status']['status'] === 'rate_limited', 'límite → rate_limited');
ok((int) get_option(OmniMetaAdsController::COOLDOWN_OPTION) > time() + 3000, 'queda una pausa de una hora');
$GLOBALS['fb']['mode'] = 'ok';
$cache = get_option(OmniMetaAdsController::CACHE_OPTION);
$cache['fetched_ts'] -= 3600; update_option(OmniMetaAdsController::CACHE_OPTION, $cache, 'no');
$n = count($GLOBALS['fb']['calls']);
$r = $c->get_overview(true);
ok(count($GLOBALS['fb']['calls']) === $n && strpos((string) $r['note'], 'Pausa') !== false, 'durante la pausa no se llama a Meta, ni forzando');

// 8. Campo rechazado: reintenta con el juego chico.
$reset(); $GLOBALS['fb']['reject_field'] = true;
$r = $c->get_overview(false);
ok($r['ads_status']['status'] === 'ok' && $r['ads'][1]['metrics']['conversations'] === 2, 'campo rechazado → reintento con menos campos');

// 9. Clasificación y tachado.
$e = OmniMetaAdsController::classify_error(['error' => 'Invalid token EAAG' . str_repeat('x', 60), 'fb_error' => ['code' => 190]]);
ok($e['status'] === 'token_invalid' && strpos($e['message'], str_repeat('x', 40)) === false, 'un token en el mensaje de Meta se tacha');
ok(OmniMetaAdsController::classify_error(['error' => 'x', 'fb_error' => ['code' => 17]])['status'] === 'rate_limited', 'código 17 = límite');
ok(OmniMetaAdsController::classify_error(['error' => 'cURL error 28', 'fb_error' => ['code' => 0]])['status'] === 'error', 'error de red = error');

// 10. La caché no guarda el token.
ok(strpos(wp_json_encode(get_option(OmniMetaAdsController::CACHE_OPTION)), $TOKEN) === false, 'el token no queda en la caché');

fin();
