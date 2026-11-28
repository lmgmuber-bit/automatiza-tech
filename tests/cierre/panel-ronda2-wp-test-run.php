<?php
// Uso interno de panel-ronda2-wp-test.php: corre at_cc_accion_pedir_respuesta() o
// at_cc_accion_registrar_aceptacion() en un proceso PHP aparte, porque las dos siempre terminan con
// wp_safe_redirect()+exit; (mismo patrón que panel-respuesta-wp-test-run.php).
// Argumentos: <pedir|aceptar> <admin_id> <post_json>
// El JSON de <post_json> es el $_POST de la petición real; puede traer además la clave interna
// '__mail_ok' (bool) para decidir qué devuelve pre_wp_mail en esta llamada (por defecto, true).
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$accion = (string) ($argv[1] ?? '');
$admin_id = (int) ($argv[2] ?? 0);
$post = json_decode((string) ($argv[3] ?? '{}'), true);
if (!in_array($accion, ['pedir', 'aceptar'], true) || $admin_id <= 0 || !is_array($post)) {
	fwrite(STDERR, "Uso: panel-ronda2-wp-test-run.php <pedir|aceptar> <admin_id> <post_json>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_cc_wp_load;
if (!function_exists('at_cc_accion_pedir_respuesta') || !function_exists('at_cc_accion_registrar_aceptacion')) {
	fwrite(STDERR, "panel.php no está cargado en ese sitio.\n");
	exit(2);
}
wp_set_current_user($admin_id);
$mail_ok = array_key_exists('__mail_ok', $post) ? (bool) $post['__mail_ok'] : true;
unset($post['__mail_ok']);
// Sin esto, wp_mail() intenta un envío real (sin SMTP configurado en el sitio local de prueba).
add_filter('pre_wp_mail', function () use ($mail_ok) { return $mail_ok; });

$id = (int) ($post['proposal_id'] ?? 0);
$nonce_prefijo = $accion === 'pedir' ? 'at_cc_pedir_' : 'at_cc_aceptar_';
$nonce = wp_create_nonce($nonce_prefijo . $id);
$_POST = $post;
$_POST['_wpnonce'] = $nonce;
$_REQUEST['_wpnonce'] = $nonce;
$_FILES = [];
if ($accion === 'pedir') {
	at_cc_accion_pedir_respuesta();
} else {
	at_cc_accion_registrar_aceptacion();
}
// No debería llegar aquí: las dos acciones reales siempre terminan con exit.
fwrite(STDERR, "ADVERTENCIA: la acción {$accion} no llamó exit.\n");
