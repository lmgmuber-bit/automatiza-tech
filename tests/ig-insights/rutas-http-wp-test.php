<?php
// Pruebas por HTTP de las rutas de Métricas IG (ig-metrics/* para agentes y admin/ig-insights).
// Levanta `php -S` sobre una copia temporal de api-omnichannel.php que carga el WordPress local
// (wp-load-shim.php), con un secret y un canal de prueba y la Graph API simulada. Nunca contra producción.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/ig-insights/rutas-http-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$ROOT = dirname(__DIR__, 2);
$SECRET = 'igm-prueba-' . bin2hex(random_bytes(16));

$wpdb->insert($P . 'channels', [
	'client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG rutas HTTP',
	'is_active' => 1, 'webhook_secret' => 'secreto-webhook-prueba', 'bot_token' => 'tok-prueba-' . bin2hex(random_bytes(10)),
	'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
]);
$CID = (int) $wpdb->insert_id;

// Carpeta temporal con la API y sus dependencias; wp-load.php es el puente de prueba.
$DIR = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ig-rutas-' . bin2hex(random_bytes(4));
mkdir($DIR);
foreach (['api-omnichannel.php', 'omnichannel-controller.php', 'omnichannel-atfinance-controller.php', 'omnichannel-ig-insights-controller.php', 'omnichannel-ig-comments-controller.php'] as $f) {
	copy($ROOT . '/' . $f, $DIR . '/' . $f);
}
// Los helpers at-*.php apuntan a los del sitio local: el tema ya los carga desde ahí y
// require_once solo evita la doble declaración si la ruta es la misma (como en PROD).
foreach (['at-rate-limit.php', 'at-mime-check.php', 'at-cors.php'] as $f) {
	$real = str_replace('\\', '/', dirname(getenv('AT_WP_LOAD')) . '/' . $f);
	file_put_contents($DIR . '/' . $f, "<?php\nrequire_once " . var_export($real, true) . ";\n");
}
copy(__DIR__ . '/wp-load-shim.php', $DIR . '/wp-load.php');

$PORT = 18000 + random_int(0, 900);
$env = array_merge(getenv(), ['IGT_SECRET' => $SECRET, 'IGT_CHANNEL' => (string) $CID, 'AT_WP_LOAD' => getenv('AT_WP_LOAD')]);
$server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $PORT, '-t', $DIR], [0 => ['pipe', 'r'], 1 => ['file', $DIR . '/server.log', 'a'], 2 => ['file', $DIR . '/server.log', 'a']], $pipes, $DIR, $env);

register_shutdown_function(static function () use ($server, $DIR, $wpdb, $P, $CID) {
	proc_terminate($server);
	proc_close($server);
	array_map('unlink', glob($DIR . '/*'));
	rmdir($DIR);
	$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$P}ig_posts WHERE channel_id = %d", $CID));
	if ($ids) { $wpdb->query("DELETE FROM {$P}ig_post_metrics WHERE post_id IN (" . implode(',', array_map('intval', $ids)) . ')'); }
	$wpdb->delete($P . 'ig_posts', ['channel_id' => $CID]);
	$wpdb->delete($P . 'ig_account_daily', ['channel_id' => $CID]);
	$wpdb->delete($P . 'channels', ['id' => $CID]);
	delete_option('omni_ig_insights_state_' . $CID);
	delete_option('omni_ig_insights_profile_' . $CID);
	delete_option('omni_ig_insights_lock_' . $CID);
	// El limitador de tasa usa transients por IP: no dejar el contador de la prueba.
	delete_transient('at_rl_' . md5('omni_ig_metrics|127.0.0.1|'));
});

/** @return array{code:int, headers:string, json:mixed} */
function http($method, $route, $secret = null, $body = null, $extra_qs = '') {
	global $PORT;
	$ch = curl_init('http://127.0.0.1:' . $PORT . '/api-omnichannel.php?route=' . rawurlencode($route) . $extra_qs);
	$headers = ['Content-Type: application/json'];
	if ($secret !== null) { $headers[] = 'X-IG-Metrics-Secret: ' . $secret; }
	curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
		CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 60]);
	if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($body)); }
	$raw = (string) curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	curl_close($ch);
	return ['code' => $code, 'headers' => substr($raw, 0, $hsize), 'body' => substr($raw, $hsize), 'json' => json_decode(substr($raw, $hsize), true)];
}

// Esperar a que el servidor responda.
$listo = false;
for ($i = 0; $i < 50 && !$listo; $i++) {
	usleep(200000);
	$listo = @fsockopen('127.0.0.1', $PORT) !== false;
}
ok($listo, 'servidor de prueba arriba');

$r = http('GET', 'ig-metrics');
ok($r['code'] === 403, 'GET ig-metrics sin cabecera → 403');
if ($r['code'] !== 403) {
	echo '     respuesta: ' . $r['code'] . ' ' . substr(strip_tags($r['body']), 0, 300) . "
";
	echo '     log: ' . substr((string) @file_get_contents($DIR . '/server.log'), -600) . "
";
}
$r = http('GET', 'ig-metrics', 'clave-mala');
ok($r['code'] === 403, 'GET ig-metrics con cabecera mala → 403');
$r = http('GET', 'ig-metrics', $SECRET, null, '&sin_secreto=1');
ok($r['code'] === 503, 'sin AT_IG_METRICS_SECRET definido → 503 (no queda abierta)');
$r = http('POST', 'ig-metrics/sync', 'reel-diario-no-sirve');
ok($r['code'] === 403, 'otro secreto no sirve para pedir una toma');

$r = http('GET', 'ig-metrics', $SECRET);
ok($r['code'] === 200 && ($r['json']['ok'] ?? false) === true, 'GET ig-metrics → 200 ok');
ok(($r['json']['insights_status']['status'] ?? '') === 'never', 'antes de la primera toma: estado never');
ok(stripos($r['headers'], 'Cache-Control: no-store') !== false, 'respuesta con Cache-Control: no-store');
foreach (['generated_at', 'channel_id', 'account', 'insights_status', 'period', 'daily', 'posts'] as $k) {
	ok(array_key_exists($k, (array) $r['json']), "resumen trae «{$k}»");
}

$r = http('POST', 'ig-metrics/sync', $SECRET, ['force' => true]);
ok($r['code'] === 200 && ($r['json']['status'] ?? '') === 'no_permission', 'POST ig-metrics/sync con token sin permiso → 200 no_permission');
$r = http('POST', 'ig-metrics/sync', $SECRET);
ok($r['code'] === 200 && !empty($r['json']['skipped']), 'segunda toma inmediata → se salta');

$r = http('GET', 'ig-metrics', $SECRET, null, '&days=7');
ok(($r['json']['account']['followers_count'] ?? null) === 42, 'resumen: seguidores de la toma');
ok(($r['json']['period']['days'] ?? null) === 7, 'resumen: respeta days=7');
ok(($r['json']['posts'][0]['media_id'] ?? '') === 'm-http-1' && ($r['json']['posts'][0]['like_count'] ?? null) === 4, 'resumen: la publicación con sus likes');
ok(array_key_exists('latest', $r['json']['posts'][0] ?? []) && $r['json']['posts'][0]['latest'] === null, 'resumen: sin permiso no hay métricas inventadas');

$r = http('GET', 'ig-metrics/posts', $SECRET, null, '&limit=5');
ok($r['code'] === 200 && count($r['json']['posts'] ?? []) === 1 && isset($r['json']['insights_status']), 'GET ig-metrics/posts → lista con estado');
$r = http('GET', 'ig-metrics/posts/m-http-1', $SECRET);
ok($r['code'] === 200 && ($r['json']['post']['permalink'] ?? '') === 'https://www.instagram.com/reel/z/', 'GET ig-metrics/posts/{media_id} → la publicación');
$r = http('GET', 'ig-metrics/posts/no-existe', $SECRET);
ok($r['code'] === 404, 'publicación no registrada → 404');
$r = http('GET', 'ig-metrics/otra', $SECRET);
ok($r['code'] === 404, 'subruta desconocida → 404');

$r = http('GET', 'admin/ig-insights', $SECRET);
ok($r['code'] === 403, 'admin/ig-insights exige sesión de admin (el secret de agentes no sirve)');
$r = http('POST', 'admin/ig-insights/sync');
ok($r['code'] === 403, 'admin/ig-insights/sync sin sesión → 403');

$r = http('GET', 'reel-diario/token-check', $SECRET);
ok($r['code'] === 403, 'el secret de métricas no abre las rutas del publicador');

fin();
