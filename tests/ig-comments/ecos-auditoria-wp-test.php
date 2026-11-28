<?php
// Prueba local de tools/ig-ecos-auditoria.php sobre un canal de prueba: sin --aplicar solo cuenta y
// no muestra contenido; con --aplicar respalda y archiva solo la conversación de la propia cuenta.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/ig-comments/ecos-auditoria-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$ROOT = dirname(__DIR__, 2);
$ACC = '17841400000000009';
$wpdb->insert($P . 'channels', ['client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG ecos',
	'is_active' => 1, 'webhook_secret' => 'x', 'bot_token' => 'tok-prueba', 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
$CID = (int) $wpdb->insert_id;
$mk = static function ($contact, $status) use ($wpdb, $P, $CID) {
	$wpdb->insert($P . 'conversations', ['client_id' => 0, 'channel_id' => $CID, 'external_contact_id' => $contact,
		'contact_name' => 'x', 'channel_type' => 'instagram', 'status' => $status, 'last_message_at' => current_time('mysql', 1)]);
	return (int) $wpdb->insert_id;
};
$OWN = $mk($ACC, 'assigned');
$REAL = $mk('igsid-persona', 'bot');
foreach ([$OWN, $OWN, $REAL] as $i => $cv) {
	$wpdb->insert($P . 'messages', ['conversation_id' => $cv, 'channel_type' => 'instagram', 'direction' => 'inbound', 'sender_type' => 'contact',
		'message_type' => 'text', 'content' => 'texto-privado-' . $i, 'delivery_status' => 'delivered', 'created_at' => current_time('mysql', 1)]);
}
$TMP = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'igc-ecos-' . bin2hex(random_bytes(4));
mkdir($TMP);
register_shutdown_function(static function () use ($wpdb, $P, $CID, $OWN, $REAL, $TMP) {
	$wpdb->query("DELETE FROM {$P}messages WHERE conversation_id IN (" . (int) $OWN . ',' . (int) $REAL . ')');
	$wpdb->delete($P . 'conversations', ['channel_id' => $CID]);
	$wpdb->delete($P . 'channels', ['id' => $CID]);
	array_map('unlink', glob($TMP . '/*'));
	rmdir($TMP);
});
$run = static function (array $args) use ($ROOT, $CID, $ACC) {
	$cmd = array_merge([PHP_BINARY, $ROOT . '/tools/ig-ecos-auditoria.php', '--wp=' . getenv('AT_WP_LOAD'), '--canal=' . $CID,
		'--ids=' . $ACC, '--controlador=' . $ROOT], $args);
	$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	return ['code' => proc_close($proc), 'out' => $out];
};
$status = static function ($id) use ($wpdb, $P) {
	return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$P}conversations WHERE id = %d", $id));
};

$r = $run([]);
ok($r['code'] === 0 && strpos($r['out'], 'propia cuenta: 1') !== false && strpos($r['out'], 'mensajes=2') !== false, 'sin --aplicar: cuenta la conversación de la propia cuenta y sus mensajes');
ok(strpos($r['out'], 'texto-privado') === false, 'no muestra el contenido de los mensajes');
ok($status($OWN) === 'assigned', 'sin --aplicar: no cambia nada');
$r = $run(['--aplicar']);
ok($r['code'] === 2 && $status($OWN) === 'assigned', '--aplicar sin respaldo: no cambia nada');
$r = $run(['--aplicar', '--respaldo=' . $TMP . '/ecos.json']);
ok($r['code'] === 0 && $status($OWN) === 'archived', '--aplicar: archiva la conversación de la propia cuenta');
ok($status($REAL) === 'bot', 'la conversación de una persona real no se toca');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}messages WHERE conversation_id = %d", $OWN)) === 2, 'no borra mensajes');
$bk = json_decode((string) @file_get_contents($TMP . '/ecos.json'), true);
ok((int) ($bk[0]['conversation']['id'] ?? 0) === $OWN && ($bk[0]['conversation']['status'] ?? '') === 'assigned' && count($bk[0]['messages'] ?? []) === 2, 'respaldo con la fila y el status anterior');
$r = $run([]);
ok(strpos($r['out'], 'propia cuenta: 0') !== false, 'después ya no quedan');

fin();
