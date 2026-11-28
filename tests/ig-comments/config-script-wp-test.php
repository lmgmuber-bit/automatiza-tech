<?php
// Prueba local de tools/ig-palabras-clave-config.php sobre un canal de prueba: sin --aplicar no escribe,
// con --aplicar respalda la fila y conserva las demás claves, y --apagar deja activo=false.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/config-script-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$ROOT = dirname(__DIR__, 2);
$wpdb->insert($P . 'channels', ['client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG script',
	'is_active' => 1, 'webhook_secret' => 'x', 'bot_token' => 'tok-prueba', 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
$CID = (int) $wpdb->insert_id;
$wpdb->insert($P . 'bot_configs', ['client_id' => 0, 'channel_id' => $CID, 'bot_name' => '[PRUEBA]', 'is_active' => 1,
	'custom_functions_json' => wp_json_encode(['derivacion' => ['agente_id' => 3]])]);
$BCID = (int) $wpdb->insert_id;
$TMP = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'igc-script-' . bin2hex(random_bytes(4));
mkdir($TMP);
register_shutdown_function(static function () use ($wpdb, $P, $CID, $BCID, $TMP) {
	$wpdb->delete($P . 'bot_configs', ['id' => $BCID]);
	$wpdb->delete($P . 'channels', ['id' => $CID]);
	array_map('unlink', glob($TMP . '/*'));
	rmdir($TMP);
});

$run = static function (array $args) use ($ROOT, $CID) {
	$cmd = array_merge([PHP_BINARY, $ROOT . '/tools/ig-palabras-clave-config.php', '--wp=' . getenv('AT_WP_LOAD'),
		'--canal=' . $CID, '--controlador=' . $ROOT], $args);
	$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	return ['code' => proc_close($proc), 'out' => $out];
};
$cf = static function () use ($wpdb, $P, $BCID) {
	return json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT custom_functions_json FROM {$P}bot_configs WHERE id = %d", $BCID)), true);
};

$r = $run([]);
ok($r['code'] === 0 && strpos($r['out'], 'no se escribió nada') !== false, 'sin --aplicar: solo muestra');
ok(!isset($cf()['palabras_clave']), 'sin --aplicar: la fila no cambia');
$r = $run(['--aplicar']);
ok($r['code'] === 2 && !isset($cf()['palabras_clave']), '--aplicar sin respaldo: no escribe');
$r = $run(['--aplicar', '--respaldo=' . $TMP . '/fila.json']);
ok($r['code'] === 0 && strpos($r['out'], '6 palabras, activo=sí') !== false, '--aplicar: guarda las 6 palabras activas');
$c = $cf();
ok(($c['derivacion']['agente_id'] ?? 0) === 3, 'conserva la clave derivacion');
ok(array_column($c['palabras_clave']['palabras'] ?? [], 'palabra') === ['MÉTODO', 'SPA', 'CLÍNICA', 'TIENDA', 'LEADS', 'SISTEMA'], 'las seis palabras de la bóveda, con tildes');
$bk = json_decode((string) @file_get_contents($TMP . '/fila.json'), true);
ok(($bk['bot_config_id'] ?? 0) === $BCID && strpos((string) ($bk['custom_functions_json'] ?? ''), 'derivacion') !== false, 'respaldo con la fila anterior');
$r = $run(['--aplicar', '--respaldo=' . $TMP . '/fila.json']);
ok($r['code'] === 2, 'no pisa un respaldo que ya existe');
$r = $run(['--aplicar', '--apagar', '--respaldo=' . $TMP . '/fila2.json']);
ok($r['code'] === 0 && strpos($r['out'], 'activo=no') !== false, '--apagar: guardado y leído de vuelta como apagado');
ok(($cf()['palabras_clave']['activo'] ?? true) === false && count($cf()['palabras_clave']['palabras'] ?? []) === 6, 'apagado conserva las palabras');

fin();
