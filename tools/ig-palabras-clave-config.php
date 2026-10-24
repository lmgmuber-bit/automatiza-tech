<?php
/**
 * Guarda (o apaga) las palabras clave de los comentarios de Instagram de un canal del Portal
 * OmniCliente, en bot_configs.custom_functions_json, clave `palabras_clave`. No toca las demás
 * claves (p. ej. `derivacion` del asistente de mensajes directos).
 *
 * Solo por línea de comandos y fuera del webroot. Los textos son los aprobados en la bóveda
 * (10-Projects/2026-10-20-Redes-AT-Claqueta-Decisiones.md, «Borrador: respuestas a palabras clave»).
 *
 *   php ig-palabras-clave-config.php --wp=<wp-load.php> --canal=2
 *       Muestra lo que hay y lo que escribiría, sin escribir.
 *   php ig-palabras-clave-config.php --wp=<wp-load.php> --canal=2 --aplicar --respaldo=<archivo.json>
 *       Respalda la fila (archivo nuevo, permisos 0600, fuera del sitio) y guarda las palabras activas
 *       desde --desde (por defecto, ahora): los comentarios anteriores nunca se responden.
 *   --apagar  con --aplicar, deja la configuración con "activo": false (vuelta atrás inmediata).
 *   --host=automatizatech.cl  HTTP_HOST con que se carga WordPress (en PROD wp-config.php elige la base por el host).
 *   --controlador=<carpeta>  carpeta con omnichannel-*-controller.php (por defecto, la del sitio de --wp).
 *
 * Diseño: Docs/superpowers/specs/2026-10-24-ig-comentarios-palabra-clave-design.md
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por línea de comandos');
}

$opts = getopt('', ['wp:', 'canal:', 'aplicar', 'respaldo:', 'apagar', 'desde:', 'host:', 'controlador:']);
$wp_load = $opts['wp'] ?? '';
$channel_id = (int) ($opts['canal'] ?? 0);
if ($wp_load === '' || !is_file($wp_load) || $channel_id <= 0) {
    fwrite(STDERR, "Uso: --wp=<ruta a wp-load.php> --canal=<id> [--aplicar --respaldo=<archivo.json>] [--apagar] [--desde=<fecha ISO>]\n");
    exit(2);
}
$apply = array_key_exists('aplicar', $opts);

$_SERVER['HTTP_HOST'] = $opts['host'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define('WP_USE_THEMES', false);
require $wp_load;
$ctrl_dir = rtrim($opts['controlador'] ?? dirname($wp_load), '/\\');
require_once $ctrl_dir . '/omnichannel-controller.php';
require_once $ctrl_dir . '/omnichannel-ig-comments-controller.php';

$PALABRAS = [
    ['palabra' => 'MÉTODO', 'publica' => '¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩',
     'privada' => 'Hola, gracias por escribirnos. Así trabajamos en AutomatizaTech: diagnóstico, priorización, propuesta por fases, desarrollo con avances, entrega probada y soporte. ¿Qué proceso de tu negocio te gustaría ordenar? Si quieres, agenda tu diagnóstico gratis en automatizatech.cl'],
    ['palabra' => 'SPA', 'publica' => '¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩',
     'privada' => 'Hola. Para spas y centros de estética armamos agenda, recordatorios y respuesta por WhatsApp. ¿Hoy cómo agendan tus clientas? Agenda tu diagnóstico gratis en automatizatech.cl'],
    ['palabra' => 'CLÍNICA', 'publica' => '¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩',
     'privada' => 'Hola. En consultas automatizamos la cita y los recordatorios, nunca el criterio clínico. ¿Cuántas citas manejan a la semana? Agenda tu diagnóstico gratis en automatizatech.cl'],
    ['palabra' => 'TIENDA', 'publica' => '¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩',
     'privada' => 'Hola. Para tiendas que venden por Instagram armamos catálogo, pedidos y estado de despacho en un solo lugar. ¿Por dónde te llegan hoy los pedidos? Agenda tu diagnóstico gratis en automatizatech.cl'],
    ['palabra' => 'LEADS', 'publica' => '¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩',
     'privada' => 'Hola. Hacemos que cada contacto nuevo tenga dueño y seguimiento automático. ¿Quién responde hoy los mensajes en tu equipo? Agenda tu diagnóstico gratis en automatizatech.cl'],
    ['palabra' => 'SISTEMA', 'publica' => '¡Hola! 👋 Te lo enviamos por mensaje, revisa tu bandeja 📩',
     'privada' => 'Hola. Te mostramos cómo se ve un sistema comercial por dentro: registro, asignación y seguimiento de cada cliente. ¿Qué rubro tienes? Agenda tu diagnóstico gratis en automatizatech.cl'],
];

global $wpdb;
$p = $wpdb->prefix . 'omnichannel_';
$channel = $wpdb->get_row($wpdb->prepare("SELECT id, channel_type FROM {$p}channels WHERE id = %d", $channel_id));
if (!$channel || $channel->channel_type !== 'instagram') {
    fwrite(STDERR, "El canal {$channel_id} no existe o no es de Instagram.\n");
    exit(2);
}
$row = $wpdb->get_row($wpdb->prepare("SELECT id, custom_functions_json FROM {$p}bot_configs WHERE channel_id = %d ORDER BY id LIMIT 1", $channel_id));
if (!$row) {
    fwrite(STDERR, "El canal {$channel_id} no tiene fila en bot_configs.\n");
    exit(2);
}
$current = $row->custom_functions_json ? json_decode($row->custom_functions_json, true) : [];
echo "bot_configs id {$row->id}; claves actuales: " . (is_array($current) && $current ? implode(', ', array_keys($current)) : '(ninguna)') . "\n";
$prev = is_array($current) ? ($current['palabras_clave'] ?? null) : null;
echo 'palabras_clave actual: ' . ($prev ? ('activo=' . (!empty($prev['activo']) ? 'sí' : 'no') . ', ' . count($prev['palabras'] ?? []) . ' palabras, desde ' . ($prev['activo_desde'] ?? '?')) : '(no hay)') . "\n";

if (array_key_exists('apagar', $opts)) {
    if (!$prev) {
        fwrite(STDERR, "No hay palabras_clave que apagar.\n");
        exit(2);
    }
    $config = $prev;
    $config['activo'] = false;
} else {
    $since = isset($opts['desde']) ? strtotime((string) $opts['desde']) : time();
    if (!$since) {
        fwrite(STDERR, "--desde no es una fecha válida.\n");
        exit(2);
    }
    $config = [
        'activo'              => true,
        'activo_desde'        => wp_date('c', $since, new DateTimeZone('America/Santiago')),
        'max_por_persona_24h' => OmniIgCommentsController::DEFAULT_MAX_PER_PERSON,
        'palabras'            => $PALABRAS,
    ];
}
echo 'escribiría: activo=' . ($config['activo'] ? 'sí' : 'no') . ', desde ' . $config['activo_desde'] . ', '
    . count($config['palabras']) . ' palabras (' . implode(', ', array_column($config['palabras'], 'palabra')) . ")\n";

if (!$apply) {
    echo "Sin --aplicar: no se escribió nada.\n";
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
file_put_contents($backup_path, wp_json_encode(['bot_config_id' => (int) $row->id, 'channel_id' => $channel_id,
    'custom_functions_json' => $row->custom_functions_json], JSON_UNESCAPED_UNICODE));
@chmod($backup_path, 0600);

$igc = new OmniIgCommentsController(new OmnichannelController());
$igc->maybe_create_tables();
$res = $igc->save_config($channel_id, $config);
if ($res !== true) {
    fwrite(STDERR, 'No se guardó: ' . ($res['error'] ?? 'error') . "\n");
    exit(1);
}
$check = $igc->get_config($channel_id);
echo 'guardado. Leído de vuelta: ' . ($check ? (count($check['palabras']) . ' palabras, activo=' . ($check['activo'] ? 'sí' : 'no')) : 'NO SE LEE') . "\n";
exit($check ? 0 : 1);
