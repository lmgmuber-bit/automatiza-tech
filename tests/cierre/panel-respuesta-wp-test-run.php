<?php
// Uso interno de panel-respuesta-wp-test.php: corre at_cc_accion_registrar_aceptacion() en un proceso
// PHP aparte, porque esa acción siempre termina con wp_safe_redirect()+exit; y no se puede llamar
// dentro del mismo proceso que sigue evaluando la prueba (mismo patrón que
// panel-timeline-wp-test-render.php).
// Argumentos: <propuesta_id> <admin_id>
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$propuesta_id = (int) ($argv[1] ?? 0);
$admin_id = (int) ($argv[2] ?? 0);
if ($propuesta_id <= 0 || $admin_id <= 0) {
	fwrite(STDERR, "Uso: panel-respuesta-wp-test-run.php <propuesta_id> <admin_id>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_cc_wp_load;
if (!function_exists('at_cc_accion_registrar_aceptacion')) {
	fwrite(STDERR, "panel.php no está cargado en ese sitio.\n");
	exit(2);
}
wp_set_current_user($admin_id);
// Sin esto, wp_mail() intenta un envío real (sin SMTP configurado en el sitio local de prueba) y la
// bienvenida "falla", lo que agrega un aviso de 'cierre_incompleto' distinto al que prueba este caso.
add_filter('pre_wp_mail', '__return_true');
$nonce = wp_create_nonce('at_cc_aceptar_' . $propuesta_id);
$_POST = [
	'action'       => 'at_cc_registrar_aceptacion',
	'proposal_id'  => (string) $propuesta_id,
	'canal'        => 'whatsapp',
	'nota'         => 'Dijo que sí por WhatsApp (prueba, ronda 1 de revisión).',
	'nombre'       => 'Cliente Prueba',
	'rut'          => '',
	'fecha'        => current_time('Y-m-d'),
	'filas'        => ['0'],
	'bienvenida'   => '1',
	'_wpnonce'     => $nonce,
];
$_REQUEST['_wpnonce'] = $nonce;
$_FILES = [];
at_cc_accion_registrar_aceptacion();
// No debería llegar aquí: la acción real siempre termina con exit.
fwrite(STDERR, "ADVERTENCIA: at_cc_accion_registrar_aceptacion no llamó exit.\n");
