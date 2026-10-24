<?php
// Pruebas locales de las respuestas automáticas a comentarios con palabra clave (Instagram).
// La Graph API de Instagram está simulada: ninguna llamada sale a Meta. Nunca contra producción.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/ig-comments-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
require_once __DIR__ . '/../../omnichannel-controller.php';
require_once __DIR__ . '/../../omnichannel-ig-comments-controller.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$TOKEN = 'tok-prueba-' . bin2hex(random_bytes(12));
$ACC = '17841400000000001';   // id de la cuenta de AT en la simulación
$H = HOUR_IN_SECONDS;

// --- Canal y configuración de bot de prueba (los reales del sitio local no se leen ni se imprimen)
$wpdb->insert($P . 'channels', [
	'client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG comentarios',
	'is_active' => 1, 'webhook_secret' => 'secreto-webhook-prueba', 'bot_token' => $TOKEN,
	'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
]);
$CID = (int) $wpdb->insert_id;
ok($CID > 0, 'canal de prueba creado');
$wpdb->insert($P . 'bot_configs', ['client_id' => 0, 'channel_id' => $CID, 'bot_name' => '[PRUEBA]', 'is_active' => 1,
	'custom_functions_json' => wp_json_encode(['derivacion' => ['agente_id' => 7, 'correos' => ['x@ejemplo.cl']]])]);
$BCID = (int) $wpdb->insert_id;

register_shutdown_function(static function () use ($wpdb, $P, $CID, $BCID) {
	$conv = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$P}conversations WHERE channel_id = %d", $CID));
	if ($conv) { $wpdb->query("DELETE FROM {$P}messages WHERE conversation_id IN (" . implode(',', array_map('intval', $conv)) . ')'); }
	$wpdb->delete($P . 'conversations', ['channel_id' => $CID]);
	$wpdb->delete($P . 'ig_comment_replies', ['channel_id' => $CID]);
	$wpdb->query($wpdb->prepare("DELETE FROM {$P}audit_log WHERE action = 'ig_comment_reply' AND new_values_json LIKE %s", '%"channel_id":' . $CID . '%'));
	$wpdb->delete($P . 'bot_configs', ['id' => $BCID]);
	$wpdb->delete($P . 'channels', ['id' => $CID]);
	foreach (['account', 'poll_lock', 'poll_seen'] as $k) { delete_option('omni_ig_comments_' . $k . '_' . $CID); }
});

// --- Simulación de graph.instagram.com
$now = time();
$iso = static function ($ts) { return gmdate('Y-m-d\TH:i:s', $ts) . '+0000'; };
$GLOBALS['ig'] = [
	'calls' => [], 'auth_ok' => true, 'token_in_url' => false, 'token' => $TOKEN, 'acc' => $ACC,
	'fail_public' => false, 'fail_dm' => false, 'dm_by_id' => false,
	'media' => [], 'comments' => [],
];
function ig_resp($code, $body) {
	return ['headers' => [], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
}
function ig_err($code, $msg) {
	return ig_resp(400, ['error' => ['message' => $msg, 'type' => 'OAuthException', 'code' => $code, 'fbtrace_id' => 'Aprueba']]);
}
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://graph.instagram.com/') !== 0) { return $pre; }
	$g = &$GLOBALS['ig'];
	if (($args['headers']['Authorization'] ?? '') !== 'Bearer ' . $g['token']) { $g['auth_ok'] = false; }
	if (strpos($url, $g['token']) !== false) { $g['token_in_url'] = true; }
	$method = $args['method'] ?? 'GET';
	$p = wp_parse_url($url);
	$path = preg_replace('#^/v[0-9.]+/#', '', $p['path']);
	$body = isset($args['body']) ? json_decode($args['body'], true) : null;
	$g['calls'][] = ['method' => $method, 'path' => $path, 'body' => $body];
	if ($method === 'GET' && $path === 'me') {
		return ig_resp(200, ['user_id' => $g['acc'], 'id' => '9001', 'username' => 'automatizatech.cl']);
	}
	if ($method === 'GET' && $path === 'me/media') {
		return ig_resp(200, ['data' => $g['media']]);
	}
	if ($method === 'GET' && preg_match('#^([^/]+)/comments$#', $path, $m)) {
		return ig_resp(200, ['data' => $g['comments'][$m[1]] ?? []]);
	}
	if ($method === 'POST' && preg_match('#^([^/]+)/replies$#', $path, $m)) {
		if ($g['fail_public']) { return ig_err(100, 'No se pudo responder el comentario'); }
		return ig_resp(200, ['id' => 'r-' . $m[1]]);
	}
	if ($method === 'POST' && $path === 'me/messages') {
		if (isset($body['recipient']['id'])) { $g['dm_by_id'] = true; }
		if ($g['fail_dm']) { return ig_err(10, 'No se pudo enviar el mensaje privado'); }
		$cmt = (string) ($body['recipient']['comment_id'] ?? '');
		return ig_resp(200, ['recipient_id' => 'igsid-' . preg_replace('/^c-/', '', $cmt), 'message_id' => 'mid-' . $cmt]);
	}
	return ig_err(1, 'Ruta no simulada: ' . $method . ' ' . $path);
}, PHP_INT_MAX, 3);

function calls_of($method, $suffix) {
	return array_values(array_filter($GLOBALS['ig']['calls'], static function ($c) use ($method, $suffix) {
		return $c['method'] === $method && substr($c['path'], -strlen($suffix)) === $suffix;
	}));
}
function reset_calls() { $GLOBALS['ig']['calls'] = []; }
function webhook($from_id, $username, $text, $comment_id, $time = null, $media = 'media-1') {
	global $ACC;
	return ['object' => 'instagram', 'entry' => [[
		'id' => $ACC, 'time' => $time ?? time(),
		'changes' => [['field' => 'comments', 'value' => [
			'id' => $comment_id, 'from' => ['id' => $from_id, 'username' => $username], 'text' => $text,
			'media' => ['id' => $media, 'media_product_type' => 'REELS'],
		]]],
	]]];
}
function row($comment_id) {
	global $wpdb, $P;
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}ig_comment_replies WHERE comment_id = %s", $comment_id));
}

$omni = new OmnichannelController();
$igc = new OmniIgCommentsController($omni);
$igc->maybe_create_tables();
ok((bool) $wpdb->get_var("SHOW TABLES LIKE '{$P}ig_comment_replies'"), 'tabla de registro creada');

// --- Normalización de la palabra
ok(OmniIgCommentsController::normalize_text('Método') === 'METODO', 'tilde y minúsculas: Método → METODO');
ok(OmniIgCommentsController::normalize_text('metodo 🙌') === 'METODO', 'con emoji al final');
ok(OmniIgCommentsController::normalize_text('🔥¡MÉTODO!🔥') === 'METODO', 'con signos y emojis alrededor');
ok(OmniIgCommentsController::normalize_text('  clínica ') === 'CLINICA', 'espacios y tilde: clínica');
ok(OmniIgCommentsController::normalize_text('#leads') === 'LEADS', 'con almohadilla');
ok(OmniIgCommentsController::normalize_text('quiero el método') === 'QUIERO EL METODO', 'una frase queda como frase');

// --- Sin configuración: no hace nada
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-1', 'ana', 'MÉTODO', 'c-sin-config'));
ok(($r['results'][0]['status'] ?? '') === 'disabled', 'sin palabras configuradas: no responde');
ok(count($GLOBALS['ig']['calls']) === 0 && !row('c-sin-config'), 'sin configuración no llama a Meta ni registra');

// --- Configuración (se agrega sin tocar la clave derivacion del asistente)
$CONFIG = [
	'activo' => true,
	'activo_desde' => gmdate('c', $now - 2 * $H),
	'max_por_persona_24h' => 3,
	'palabras' => [
		['palabra' => 'MÉTODO', 'publica' => '¡Te lo enviamos por mensaje! 📩', 'privada' => 'Hola, así trabajamos en AutomatizaTech.'],
		['palabra' => 'CLÍNICA', 'publica' => '¡Te escribimos por mensaje! 📩', 'privada' => 'Hola. En consultas automatizamos la cita.'],
	],
];
ok($igc->save_config($CID, $CONFIG) === true, 'configuración guardada');
$cf = json_decode($wpdb->get_var($wpdb->prepare("SELECT custom_functions_json FROM {$P}bot_configs WHERE id = %d", $BCID)), true);
ok(($cf['derivacion']['agente_id'] ?? 0) === 7, 'guardar palabras no borra la clave derivacion');
ok(count($cf['palabras_clave']['palabras'] ?? []) === 2, 'quedan las 2 palabras guardadas');

// --- Comentario con palabra clave: pública, luego privada, conversación y registro
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-ana', 'ana.prueba', 'Método 🙌', 'c-100'));
ok(($r['results'][0]['status'] ?? '') === 'replied', 'comentario con palabra: respondido');
$pub = calls_of('POST', 'c-100/replies');
$dm = calls_of('POST', 'me/messages');
ok(count($pub) === 1 && ($pub[0]['body']['message'] ?? '') === '¡Te lo enviamos por mensaje! 📩', 'respuesta pública con el texto de MÉTODO');
ok(count($dm) === 1 && ($dm[0]['body']['recipient'] ?? null) === ['comment_id' => 'c-100'], 'privada solo con recipient.comment_id');
ok(($dm[0]['body']['message']['text'] ?? '') === 'Hola, así trabajamos en AutomatizaTech.', 'privada con el texto de MÉTODO');
$order = array_map(static function ($c) { return $c['method'] . ' ' . $c['path']; }, $GLOBALS['ig']['calls']);
ok(array_search('POST c-100/replies', $order, true) < array_search('POST me/messages', $order, true), 'primero la pública y después la privada');
ok($GLOBALS['ig']['dm_by_id'] === false, 'nunca se escribe por IGSID');
$row = row('c-100');
ok($row && $row->keyword === 'METODO' && $row->from_username === 'ana.prueba' && $row->from_igsid === 'u-ana', 'registro con quién y qué palabra');
ok($row && $row->public_status === 'sent' && $row->dm_status === 'sent' && $row->status === 'replied', 'registro con las dos respuestas enviadas');
ok($row && $row->public_reply_id === 'r-c-100' && $row->dm_message_id === 'mid-c-100' && $row->source === 'webhook', 'registro con los ids de Meta y el origen');
$conv = $row ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}conversations WHERE id = %d", $row->conversation_id)) : null;
ok($conv && $conv->external_contact_id === 'igsid-100' && $conv->status === 'bot' && $conv->channel_type === 'instagram', 'conversación creada en modo bot con el IGSID que devuelve Meta');
ok($conv && $conv->contact_name === '@ana.prueba', 'la conversación lleva el usuario de Instagram');
$msgs = $conv ? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$P}messages WHERE conversation_id = %d ORDER BY id", $conv->id)) : [];
ok(count($msgs) === 2 && $msgs[0]->sender_type === 'system' && strpos($msgs[0]->content, 'MÉTODO') !== false, 'nota de sistema con la palabra comentada');
ok(isset($msgs[1]) && $msgs[1]->sender_type === 'bot' && $msgs[1]->direction === 'outbound' && $msgs[1]->external_message_id === 'mid-c-100', 'el mensaje privado queda en la conversación');
ok((bool) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}audit_log WHERE action = 'ig_comment_reply' AND new_values_json LIKE %s", '%c-100%')), 'queda en la auditoría');

// --- El mismo comentario otra vez (Meta repite el aviso): no se responde dos veces
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-ana', 'ana.prueba', 'Método 🙌', 'c-100'));
ok(($r['results'][0]['status'] ?? '') === 'duplicate' && count($GLOBALS['ig']['calls']) === 0, 'aviso repetido: no vuelve a responder');

// --- La propia cuenta (sus respuestas también llegan como comentarios)
reset_calls();
$r = $igc->handle_webhook($CID, webhook($ACC, 'otra', 'MÉTODO', 'c-propio-1'));
ok(($r['results'][0]['status'] ?? '') === 'own_account' && count($GLOBALS['ig']['calls']) === 0, 'comentario de la propia cuenta (id): se ignora');
$r = $igc->handle_webhook($CID, webhook('u-x', 'automatizatech.cl', 'METODO', 'c-propio-2'));
ok(($r['results'][0]['status'] ?? '') === 'own_account', 'comentario de la propia cuenta (usuario): se ignora');
ok(!row('c-propio-1') && !row('c-propio-2'), 'la propia cuenta no queda registrada');

// --- Sin palabra, palabra que no está y comentario viejo
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-b', 'beto', 'quiero el método', 'c-frase'));
ok(($r['results'][0]['status'] ?? '') === 'no_keyword', 'una frase con la palabra no cuenta');
$r = $igc->handle_webhook($CID, webhook('u-b', 'beto', 'TIENDA', 'c-otra'));
ok(($r['results'][0]['status'] ?? '') === 'no_keyword', 'una palabra que no está configurada no cuenta');
$r = $igc->handle_webhook($CID, webhook('u-b', 'beto', 'MÉTODO', 'c-viejo', $now - 3 * $H));
ok(($r['results'][0]['status'] ?? '') === 'too_old', 'anterior a activo_desde: no responde');
ok(count($GLOBALS['ig']['calls']) === 0 && !row('c-frase') && !row('c-viejo'), 'nada de eso llama a Meta ni se registra');

// --- Varios comentarios en un mismo aviso
reset_calls();
$multi = webhook('u-c', 'carla', 'clinica', 'c-200');
$multi['entry'][0]['changes'][] = webhook('u-d', 'dani', 'MÉTODO!!', 'c-201')['entry'][0]['changes'][0];
$r = $igc->handle_webhook($CID, $multi);
ok(count($r['results'] ?? []) === 2 && $r['results'][0]['status'] === 'replied' && $r['results'][1]['status'] === 'replied', 'un aviso con dos comentarios responde los dos');
ok((calls_of('POST', 'c-200/replies')[0]['body']['message'] ?? '') === '¡Te escribimos por mensaje! 📩', 'cada palabra con su texto (CLÍNICA)');

// --- Tope por persona: hasta 3 en 24 h
reset_calls();
foreach (['c-300', 'c-301', 'c-302', 'c-303'] as $cmt) {
	$r = $igc->handle_webhook($CID, webhook('u-e', 'eva', 'METODO', $cmt));
}
ok(($r['results'][0]['status'] ?? '') === 'limit', 'la cuarta en 24 h no se responde');
ok(count(calls_of('POST', 'me/messages')) === 3, 'solo 3 privados a la misma persona');
ok(row('c-303') && row('c-303')->status === 'limit', 'la cuarta queda registrada como tope');

// --- Falla la pública: igual sale la privada
reset_calls();
$GLOBALS['ig']['fail_public'] = true;
$r = $igc->handle_webhook($CID, webhook('u-f', 'fran', 'METODO', 'c-400'));
$GLOBALS['ig']['fail_public'] = false;
ok(($r['results'][0]['status'] ?? '') === 'partial' && count(calls_of('POST', 'me/messages')) === 1, 'si falla la pública, la privada igual sale');
ok(row('c-400')->public_status === 'failed' && row('c-400')->dm_status === 'sent' && row('c-400')->error !== '', 'registro con la pública fallida y el error');

// --- Falla la privada: reintento en el siguiente aviso, hasta 3 intentos
reset_calls();
$GLOBALS['ig']['fail_dm'] = true;
$r = $igc->handle_webhook($CID, webhook('u-g', 'gabo', 'METODO', 'c-500'));
ok(($r['results'][0]['status'] ?? '') === 'failed' && row('c-500')->dm_status === 'failed', 'privada fallida queda registrada');
$GLOBALS['ig']['fail_dm'] = false;
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-g', 'gabo', 'METODO', 'c-500'));
ok(($r['results'][0]['status'] ?? '') === 'replied' && count(calls_of('POST', 'c-500/replies')) === 0 && count(calls_of('POST', 'me/messages')) === 1, 'el reintento manda solo la privada (la pública ya salió)');
ok((int) row('c-500')->attempts === 2, 'segundo intento contado');
$GLOBALS['ig']['fail_dm'] = true;
$igc->handle_webhook($CID, webhook('u-h', 'hugo', 'METODO', 'c-501'));
$igc->handle_webhook($CID, webhook('u-h', 'hugo', 'METODO', 'c-501'));
$igc->handle_webhook($CID, webhook('u-h', 'hugo', 'METODO', 'c-501'));
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-h', 'hugo', 'METODO', 'c-501'));
$GLOBALS['ig']['fail_dm'] = false;
ok(($r['results'][0]['status'] ?? '') === 'duplicate' && count($GLOBALS['ig']['calls']) === 0, 'después de 3 intentos no se insiste');

// --- Conversación que ya atiende una persona: se agregan los mensajes, el modo no cambia
$wpdb->insert($P . 'conversations', ['client_id' => 0, 'channel_id' => $CID, 'external_contact_id' => 'igsid-600',
	'contact_name' => 'Isa Real', 'channel_type' => 'instagram', 'status' => 'assigned', 'last_message_at' => current_time('mysql', 1)]);
$HCONV = (int) $wpdb->insert_id;
$wpdb->query($wpdb->prepare("UPDATE {$P}conversations SET metadata_json = NULL WHERE id = %d", $HCONV));
$r = $igc->handle_webhook($CID, webhook('u-isa', 'isa', 'METODO', 'c-600'));
$hc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}conversations WHERE id = %d", $HCONV));
ok((int) row('c-600')->conversation_id === $HCONV && $hc->status === 'assigned' && $hc->contact_name === 'Isa Real', 'conversación existente: se usa, sin cambiar el modo ni el nombre');

// --- Configuración apagada
$off = $CONFIG; $off['activo'] = false;
$igc->save_config($CID, $off);
reset_calls();
$r = $igc->handle_webhook($CID, webhook('u-j', 'juan', 'METODO', 'c-700'));
ok(($r['results'][0]['status'] ?? '') === 'disabled' && count($GLOBALS['ig']['calls']) === 0, 'con activo=false no responde');
$igc->save_config($CID, $CONFIG);

// --- Plan B: consulta periódica
reset_calls();
$GLOBALS['ig']['media'] = [
	['id' => 'm-1', 'timestamp' => $iso($now - 5 * $H), 'comments_count' => 3],
	['id' => 'm-2', 'timestamp' => $iso($now - 20 * 24 * $H), 'comments_count' => 1],
	['id' => 'm-0', 'timestamp' => $iso($now - 1 * $H), 'comments_count' => 0],
];
$GLOBALS['ig']['comments'] = [
	'm-1' => [
		['id' => 'c-800', 'text' => 'Método', 'timestamp' => $iso($now - 10 * 60), 'from' => ['id' => 'u-k', 'username' => 'kari'], 'username' => 'kari'],
		['id' => 'c-801', 'text' => 'qué bueno', 'timestamp' => $iso($now - 9 * 60), 'from' => ['id' => 'u-l', 'username' => 'leo'], 'username' => 'leo'],
		['id' => 'c-100', 'text' => 'Método 🙌', 'timestamp' => $iso($now - 30 * 60), 'from' => ['id' => 'u-ana', 'username' => 'ana.prueba'], 'username' => 'ana.prueba'],
	],
	'm-2' => [
		['id' => 'c-802', 'text' => 'METODO', 'timestamp' => $iso($now - 8 * 24 * $H), 'from' => ['id' => 'u-m', 'username' => 'meli'], 'username' => 'meli'],
	],
];
$r = $igc->poll($CID);
ok(!empty($r['ok']) && ($r['replied'] ?? 0) === 1, 'consulta: responde el comentario nuevo con palabra');
ok(row('c-800') && row('c-800')->source === 'poll' && row('c-800')->status === 'replied', 'consulta: registrado con origen poll');
ok(count(calls_of('POST', 'c-100/replies')) === 0, 'consulta: lo que ya respondió el webhook no se repite');
ok(!row('c-802'), 'consulta: comentario de más de 7 días no se responde');
ok(count(calls_of('GET', 'm-0/comments')) === 0, 'consulta: no lee posts sin comentarios');
reset_calls();
$r = $igc->poll($CID);
ok(!empty($r['ok']) && count(calls_of('GET', '/comments')) === 0, 'consulta repetida: sin comentarios nuevos no relee');

// --- Consulta con privada fallida: ese post se relee en la siguiente y se reintenta
$GLOBALS['ig']['media'][] = ['id' => 'm-3', 'timestamp' => $iso($now - 2 * $H), 'comments_count' => 1];
$GLOBALS['ig']['comments']['m-3'] = [
	['id' => 'c-900', 'text' => 'CLÍNICA', 'timestamp' => $iso($now - 5 * 60), 'from' => ['id' => 'u-n', 'username' => 'nico'], 'username' => 'nico'],
];
$GLOBALS['ig']['fail_dm'] = true;
$igc->poll($CID);
$GLOBALS['ig']['fail_dm'] = false;
ok(row('c-900') && row('c-900')->dm_status === 'failed', 'consulta: privada fallida registrada');
reset_calls();
$r = $igc->poll($CID);
ok(count(calls_of('GET', 'm-3/comments')) === 1 && row('c-900')->status === 'replied', 'consulta siguiente: relee ese post y la privada sale');
reset_calls();
$igc->poll($CID);
ok(count(calls_of('GET', 'm-3/comments')) === 0, 'después ya no lo relee');

// --- Token
ok($GLOBALS['ig']['auth_ok'] === true, 'el token va siempre en la cabecera Authorization');
ok($GLOBALS['ig']['token_in_url'] === false, 'el token nunca va en la URL');
$log = $igc->get_log($CID, 50);
ok(is_array($log) && count($log) >= 5 && strpos(wp_json_encode($log), $TOKEN) === false, 'el registro para el portal no trae el token');

fin();
