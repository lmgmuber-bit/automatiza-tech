<?php
// Uso interno de revision-final-wp-test.php: corre at_cc_accion_completar_cierre() o
// at_cc_accion_crear_ficha_operativa() en un proceso PHP aparte, porque las dos terminan con
// wp_safe_redirect()+exit; (mismo patrón que panel-ronda2-wp-test-run.php).
// Argumentos: <completar|crear_ficha> <usuario_id> <post_json>
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$accion = (string) ($argv[1] ?? '');
$usuario_id = (int) ($argv[2] ?? 0);
$post = json_decode((string) ($argv[3] ?? '{}'), true);
if (!in_array($accion, ['completar', 'crear_ficha'], true) || $usuario_id <= 0 || !is_array($post)) {
	fwrite(STDERR, "Uso: revision-final-wp-test-run.php <completar|crear_ficha> <usuario_id> <post_json>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_cc_wp_load;
if (!function_exists('at_cc_accion_completar_cierre') || !function_exists('at_cc_accion_crear_ficha_operativa')) {
	fwrite(STDERR, "El módulo cierre-cliente no está cargado en ese sitio.\n");
	exit(2);
}
wp_set_current_user($usuario_id);
// Sin esto, wp_mail() intenta un envío real (sin SMTP configurado en el sitio local de prueba). Cada
// correo que se habría mandado se anota en STDOUT para que la prueba los cuente.
add_filter('pre_wp_mail', function ($nulo, $atts) { echo 'CORREO ' . wp_json_encode(['to' => $atts['to'], 'subject' => $atts['subject']]) . "\n"; return true; }, 10, 2);
add_filter('wp_redirect', function ($url) { echo 'REDIRIGE ' . $url . "\n"; return $url; });

$_POST = $post;
if ($accion === 'completar') {
	$nonce = wp_create_nonce('at_cc_completar_' . (int) ($post['proposal_id'] ?? 0));
} else {
	$nonce = wp_create_nonce('at_cc_crear_ficha_operativa_' . (int) ($post['crm_id'] ?? 0));
}
$_POST['_wpnonce'] = $nonce;
$_REQUEST['_wpnonce'] = $nonce;
$_FILES = [];
if ($accion === 'completar') {
	at_cc_accion_completar_cierre();
} else {
	at_cc_accion_crear_ficha_operativa();
}
// No debería llegar aquí: las dos acciones reales siempre terminan con exit.
fwrite(STDERR, "ADVERTENCIA: la acción {$accion} no llamó exit.\n");
