<?php
// Uso interno de archivo-wp-test.php: corre at_cc_accion_archivar() o at_cc_accion_desarchivar() en
// un proceso PHP aparte, porque las dos terminan con wp_safe_redirect()+exit; (mismo patrón que
// revision-final-wp-test-run.php).
// Argumentos: <archivar|desarchivar> <usuario_id> <propuesta_id> <valido|malo|cruzado>
//   valido  = nonce de esta acción y esta propuesta
//   malo    = nonce inventado
//   cruzado = nonce de la OTRA acción (archivar <-> desarchivar) para la misma propuesta
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$accion = (string) ($argv[1] ?? '');
$usuario_id = (int) ($argv[2] ?? 0);
$propuesta_id = (int) ($argv[3] ?? 0);
$modo = (string) ($argv[4] ?? '');
if (!in_array($accion, ['archivar', 'desarchivar'], true) || $usuario_id <= 0 || $propuesta_id <= 0 || !in_array($modo, ['valido', 'malo', 'cruzado'], true)) {
	fwrite(STDERR, "Uso: archivo-wp-test-run.php <archivar|desarchivar> <usuario_id> <propuesta_id> <valido|malo|cruzado>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_cc_wp_load;
if (!function_exists('at_cc_accion_archivar') || !function_exists('at_cc_accion_desarchivar')) {
	fwrite(STDERR, "archivo.php no está cargado en ese sitio.\n");
	exit(2);
}
wp_set_current_user($usuario_id);
add_filter('pre_wp_mail', function ($nulo, $atts) { echo 'CORREO ' . wp_json_encode(['to' => $atts['to'], 'subject' => $atts['subject']]) . "\n"; return true; }, 10, 2);
add_filter('wp_redirect', function ($url) { echo 'REDIRIGE ' . $url . "\n"; return $url; });

$otra = $accion === 'archivar' ? 'desarchivar' : 'archivar';
$nonce = $modo === 'valido' ? wp_create_nonce('at_cc_' . $accion . '_' . $propuesta_id)
	: ($modo === 'cruzado' ? wp_create_nonce('at_cc_' . $otra . '_' . $propuesta_id) : 'nonce-inventado');
$_POST = ['action' => 'at_cc_' . $accion, 'proposal_id' => (string) $propuesta_id, '_wpnonce' => $nonce];
$_REQUEST = $_POST;
$_FILES = [];
if ($accion === 'archivar') {
	at_cc_accion_archivar();
} else {
	at_cc_accion_desarchivar();
}
// No debería llegar aquí: las dos acciones reales siempre terminan con exit (o wp_die()).
fwrite(STDERR, "ADVERTENCIA: la acción {$accion} no llamó exit.\n");
