<?php
// Pruebas por HTTP de los comentarios con palabra clave: el webhook de Meta (campo comments) en
// webhook-omnichannel.php y las rutas ig-comments/poll y admin/ig-comments de api-omnichannel.php.
// Levanta `php -S` sobre una copia temporal que carga el WordPress local (wp-load-shim.php), con un
// canal y un secret de prueba y la Graph API simulada. Nunca contra producción.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/rutas-http-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
require_once __DIR__ . '/../../omnichannel-controller.php';
require_once __DIR__ . '/../../omnichannel-ig-comments-controller.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$ROOT = dirname(__DIR__, 2);
$SECRET = 'igc-prueba-' . bin2hex(random_bytes(16));
$WH_SECRET = 'wh-prueba-' . bin2hex(random_bytes(8));
$ACC = '17841400000000002';

$wpdb->insert($P . 'channels', [
	'client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG comentarios HTTP',
	'is_active' => 1, 'webhook_secret' => $WH_SECRET, 'bot_token' => 'tok-prueba-' . bin2hex(random_bytes(10)),
	'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
]);
$CID = (int) $wpdb->insert_id;
$wpdb->insert($P . 'bot_configs', ['client_id' => 0, 'channel_id' => $CID, 'bot_name' => '[PRUEBA]', 'is_active' => 1]);
$BCID = (int) $wpdb->insert_id;
$igc = new OmniIgCommentsController(new OmnichannelController());
$igc->maybe_create_tables();
$igc->save_config($CID, ['activo' => true, 'activo_desde' => gmdate('c', time() - 3600), 'max_por_persona_24h' => 3, 'palabras' => [
	['palabra' => 'MÉTODO', 'publica' => '¡Te lo enviamos por mensaje! 📩', 'privada' => 'Hola, así trabajamos.'],
	['palabra' => 'SPA', 'publica' => '¡Te escribimos por mensaje! 📩', 'privada' => 'Hola. Para spas armamos agenda.'],
]]);

$DIR = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'igc-rutas-' . bin2hex(random_bytes(4));
mkdir($DIR);
foreach (['webhook-omnichannel.php', 'api-omnichannel.php', 'omnichannel-controller.php', 'omnichannel-atfinance-controller.php',
	'omnichannel-ig-insights-controller.php', 'omnichannel-ig-comments-controller.php'] as $f) {
	copy($ROOT . '/' . $f, $DIR . '/' . $f);
}
// Los helpers at-*.php apuntan a los del sitio local (require_once evita la doble declaración).
foreach (['at-rate-limit.php', 'at-mime-check.php', 'at-cors.php', 'at-webhook-verify.php'] as $f) {
	$real = str_replace('\\', '/', dirname(getenv('AT_WP_LOAD')) . '/' . $f);
	file_put_contents($DIR . '/' . $f, "<?php\nrequire_once " . var_export($real, true) . ";\n");
}
copy(__DIR__ . '/wp-load-shim.php', $DIR . '/wp-load.php');

$PORT = 18000 + random_int(0, 900);
$env = array_merge(getenv(), ['IGC_SECRET' => $SECRET, 'IGC_CHANNEL' => (string) $CID, 'AT_WP_LOAD' => getenv('AT_WP_LOAD')]);
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $PORT, '-t', $DIR], [0 => ['pipe', 'r'], 1 => ['file', $DIR . '/server.log', 'a'], 2 => ['file', $DIR . '/server.log', 'a']], $pipes, $DIR, $env);

register_shutdown_function(static function () use ($server, $DIR, $wpdb, $P, $CID, $BCID) {
	proc_terminate($server);
	proc_close($server);
	array_map('unlink', glob($DIR . '/*'));
	rmdir($DIR);
	$conv = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$P}conversations WHERE channel_id = %d", $CID));
	if ($conv) { $wpdb->query("DELETE FROM {$P}messages WHERE conversation_id IN (" . implode(',', array_map('intval', $conv)) . ')'); }
	$wpdb->delete($P . 'conversations', ['channel_id' => $CID]);
	$wpdb->delete($P . 'ig_comment_replies', ['channel_id' => $CID]);
	$wpdb->query($wpdb->prepare("DELETE FROM {$P}audit_log WHERE action = 'ig_comment_reply' AND new_values_json LIKE %s", '%"channel_id":' . $CID . '%'));
	$wpdb->delete($P . 'bot_configs', ['id' => $BCID]);
	$wpdb->delete($P . 'channels', ['id' => $CID]);
	foreach (['account', 'poll_lock', 'poll_seen'] as $k) { delete_option('omni_ig_comments_' . $k . '_' . $CID); }
	// El limitador de tasa usa transients por IP: no dejar los contadores de la prueba.
	delete_transient('at_rl_' . md5('omni_ig_comments|127.0.0.1|'));
	delete_transient('at_rl_' . md5('webhook_omni_' . $CID . '|127.0.0.1|'));
});

function req($method, $url, $body = null, $headers = []) {
	$ch = curl_init($url);
	curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
		CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers)]);
	if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($body)); }
	$raw = (string) curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	return ['code' => $code, 'body' => $raw, 'json' => json_decode($raw, true)];
}
$BASE = 'http://127.0.0.1:' . $PORT;
$WH = $BASE . '/webhook-omnichannel.php?channel_id=' . $CID . '&secret=' . rawurlencode($WH_SECRET);
$api = static function ($method, $route, $secret = null, $qs = '') use ($BASE) {
	return req($method, $BASE . '/api-omnichannel.php?route=' . rawurlencode($route) . $qs, null,
		$secret === null ? [] : ['X-IG-Comments-Secret: ' . $secret]);
};
$aviso = static function ($comment_id, $text, $from = 'u-http-1', $user = 'cliente.http') use ($ACC) {
	return ['object' => 'instagram', 'entry' => [['id' => $ACC, 'time' => time(), 'changes' => [['field' => 'comments',
		'value' => ['id' => $comment_id, 'from' => ['id' => $from, 'username' => $user], 'text' => $text,
			'media' => ['id' => 'm-http', 'media_product_type' => 'REELS']]]]]]];
};

$listo = false;
for ($i = 0; $i < 50 && !$listo; $i++) { usleep(200000); $listo = @fsockopen('127.0.0.1', $PORT) !== false; }
ok($listo, 'servidor de prueba arriba');

// --- Webhook
$r = req('POST', $WH, $aviso('c-http-1', 'método'));
ok($r['code'] === 200 && ($r['json']['results'][0]['status'] ?? '') === 'replied', 'webhook comments con palabra → 200 replied');
if ($r['code'] !== 200) { echo '     ' . substr($r['body'], 0, 300) . "\n     log: " . substr((string) @file_get_contents($DIR . '/server.log'), -600) . "\n"; }
$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}ig_comment_replies WHERE comment_id = %s", 'c-http-1'));
ok($row && $row->status === 'replied' && $row->dm_message_id === 'mid-c-http-1', 'webhook: registrado con el privado enviado');
ok($row && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}messages WHERE conversation_id = %d", $row->conversation_id)) === 2, 'webhook: conversación con nota y privado');
$r = req('POST', $WH, $aviso('c-http-1', 'método'));
ok(($r['json']['results'][0]['status'] ?? '') === 'duplicate', 'webhook repetido → duplicate');
$r = req('POST', $WH, $aviso('c-http-2', 'MÉTODO', $ACC, 'automatizatech.cl'));
ok(($r['json']['results'][0]['status'] ?? '') === 'own_account', 'webhook de la propia cuenta → se ignora');
$r = req('POST', $BASE . '/webhook-omnichannel.php?channel_id=' . $CID . '&secret=malo', $aviso('c-http-3', 'MÉTODO'));
ok($r['code'] === 403 && !$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}ig_comment_replies WHERE comment_id = %s", 'c-http-3')), 'webhook con secret malo → 403 y nada registrado');
// La persona contesta por directo: entra por el camino de siempre a la misma conversación.
$dm = ['object' => 'instagram', 'entry' => [['id' => $ACC, 'time' => time(), 'messaging' => [[
	'sender' => ['id' => 'igsid-c-http-1'], 'recipient' => ['id' => $ACC], 'message' => ['mid' => 'mid-respuesta-1', 'text' => 'Hola, me interesa']]]]]];
$r = req('POST', $WH, $dm);
ok($r['code'] === 200 && $row && (int) ($r['json']['conversation_id'] ?? 0) === (int) $row->conversation_id, 'la respuesta por directo entra a la misma conversación');

// --- Rutas del plan B
$r = $api('POST', 'ig-comments/poll');
ok($r['code'] === 403, 'POST ig-comments/poll sin cabecera → 403');
$r = $api('POST', 'ig-comments/poll', 'clave-mala');
ok($r['code'] === 403, 'POST ig-comments/poll con cabecera mala → 403');
$r = $api('POST', 'ig-comments/poll', $SECRET, '&sin_secreto=1');
ok($r['code'] === 503, 'sin AT_IG_COMMENTS_SECRET definido → 503 (no queda abierta)');
$r = $api('POST', 'ig-comments/poll', $SECRET);
ok($r['code'] === 200 && ($r['json']['replied'] ?? 0) === 1, 'POST ig-comments/poll → responde el comentario nuevo');
if ($r['code'] !== 200) { echo '     ' . substr($r['body'], 0, 300) . "\n"; }
ok(($wpdb->get_var($wpdb->prepare("SELECT source FROM {$P}ig_comment_replies WHERE comment_id = %s", 'c-http-poll')) ?? '') === 'poll', 'consulta: registrado con origen poll');
$r = $api('GET', 'ig-comments/poll', $SECRET);
ok($r['code'] === 404, 'GET ig-comments/poll → 404');
$r = req('GET', $BASE . '/api-omnichannel.php?route=ig-metrics', null, ['X-IG-Metrics-Secret: ' . $SECRET]);
ok(in_array($r['code'], [403, 503], true), 'el secret de comentarios no abre Métricas IG');
$r = $api('GET', 'admin/ig-comments', $SECRET);
ok($r['code'] === 403, 'admin/ig-comments exige sesión de admin (el secret no sirve)');

fin();
