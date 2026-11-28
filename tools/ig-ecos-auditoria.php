<?php
/**
 * Conversaciones de Instagram a nombre de la propia cuenta (Portal OmniCliente).
 *
 * Hasta el arreglo de los ecos (record_instagram_echo, 24-oct-2026), los mensajes que enviaba la
 * cuenta volvían por el webhook con is_echo y se guardaban como ENTRANTES en una conversación cuyo
 * contacto era la propia cuenta. El destinatario nunca se guardó, así que esos mensajes no se pueden
 * reubicar: la propuesta es archivar la conversación (no se borra nada y se puede deshacer).
 *
 * Solo por línea de comandos y fuera del webroot. Sin --aplicar solo cuenta: no muestra contenido.
 *
 *   php ig-ecos-auditoria.php --wp=<wp-load.php> --canal=2 [--host=automatizatech.cl]
 *   php ig-ecos-auditoria.php --wp=<wp-load.php> --canal=2 --aplicar --respaldo=<archivo.json>
 *       Respalda las filas (archivo nuevo, 0600, fuera del sitio) y deja esas conversaciones en
 *       status='archived'. Para deshacer: volver a poner el status que guarda el respaldo.
 *   --ids=<id1,id2>  ids de la cuenta, si no se quiere preguntarlos a Meta (GET me) o en pruebas.
 *   --controlador=<carpeta>  carpeta con omnichannel-controller.php (por defecto, la del sitio de --wp).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de comandos');
}

$opts = getopt('', ['wp:', 'canal:', 'aplicar', 'respaldo:', 'host:', 'ids:', 'controlador:']);
$wp_load = $opts['wp'] ?? '';
$channel_id = (int) ($opts['canal'] ?? 0);
if ($wp_load === '' || !is_file($wp_load) || $channel_id <= 0) {
    fwrite(STDERR, "Uso: --wp=<ruta a wp-load.php> --canal=<id> [--aplicar --respaldo=<archivo.json>] [--ids=...]\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = $opts['host'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define('WP_USE_THEMES', false);
require $wp_load;
require_once rtrim($opts['controlador'] ?? dirname($wp_load), '/\\') . '/omnichannel-controller.php';

global $wpdb;
$p = $wpdb->prefix . 'omnichannel_';
$channel = $wpdb->get_row($wpdb->prepare("SELECT id, channel_type FROM {$p}channels WHERE id = %d", $channel_id));
if (!$channel || $channel->channel_type !== 'instagram') {
    fwrite(STDERR, "El canal {$channel_id} no existe o no es de Instagram.\n");
    exit(2);
}

if (isset($opts['ids'])) {
    $ids = array_values(array_filter(array_map('trim', explode(',', (string) $opts['ids']))));
} else {
    $me = (new OmnichannelController())->ig_graph_get($channel_id, 'me?fields=user_id,id');
    if (empty($me['success'])) {
        fwrite(STDERR, 'No se pudo preguntar a Meta por la cuenta: ' . ($me['error'] ?? 'error') . "\n");
        exit(1);
    }
    $ids = array_values(array_filter([(string) ($me['data']['user_id'] ?? ''), (string) ($me['data']['id'] ?? '')]));
}
if (!$ids) {
    fwrite(STDERR, "Sin ids de la cuenta.\n");
    exit(1);
}
$in = implode(',', array_fill(0, count($ids), '%s'));
$convs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$p}conversations WHERE channel_id = %d AND external_contact_id IN ($in) AND status != 'archived'",
    array_merge([$channel_id], $ids)
), ARRAY_A);

echo 'conversaciones a nombre de la propia cuenta: ' . count($convs) . "\n";
foreach ($convs as $c) {
    $n = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) total, SUM(direction = 'inbound') entrantes, SUM(direction = 'outbound') salientes,
                MIN(created_at) desde, MAX(created_at) hasta
         FROM {$p}messages WHERE conversation_id = %d", $c['id']
    ));
    echo "  conversación {$c['id']}: status={$c['status']}, intervention_mode=" . ($c['intervention_mode'] ?? '-')
        . ", mensajes={$n->total} (entrantes {$n->entrantes}, salientes {$n->salientes}), del {$n->desde} al {$n->hasta}\n";
}
if (!$convs) exit(0);
if (!array_key_exists('aplicar', $opts)) {
    echo "Sin --aplicar: no se cambió nada. Propuesta: archivar (status='archived'), con respaldo.\n";
    exit(0);
}

$backup_path = $opts['respaldo'] ?? '';
$dir = $backup_path !== '' ? realpath(dirname($backup_path)) : false;
$webroot = realpath(ABSPATH);
if ($dir === false || ($webroot !== false && strpos($dir . DIRECTORY_SEPARATOR, $webroot . DIRECTORY_SEPARATOR) === 0)) {
    fwrite(STDERR, "--aplicar exige --respaldo=<archivo.json> en una carpeta existente fuera del sitio web.\n");
    exit(2);
}
if (file_exists($backup_path)) {
    fwrite(STDERR, "El respaldo ya existe: no se pisa.\n");
    exit(2);
}
// El respaldo trae la conversación entera y sus mensajes (contenido incluido): borrarlo al verificar.
$dump = [];
foreach ($convs as $c) {
    $dump[] = ['conversation' => $c, 'messages' => $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}messages WHERE conversation_id = %d ORDER BY id", $c['id']), ARRAY_A)];
}
file_put_contents($backup_path, wp_json_encode($dump, JSON_UNESCAPED_UNICODE));
@chmod($backup_path, 0600);
foreach ($convs as $c) {
    $wpdb->update($p . 'conversations', ['status' => 'archived'], ['id' => $c['id']]);
    echo "  conversación {$c['id']} archivada (antes: {$c['status']})\n";
}
exit(0);
