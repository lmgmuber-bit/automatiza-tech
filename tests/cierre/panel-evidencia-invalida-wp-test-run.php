<?php
// Uso interno de panel-evidencia-invalida-wp-test.php: corre at_cc_accion_registrar_aceptacion() con
// UNA evidencia que no es una imagen válida (texto plano con extensión .png), en un proceso PHP
// aparte porque la acción siempre termina con wp_safe_redirect()+exit (mismo patrón que
// panel-ronda2-wp-test-run.php). at_cc_error_evidencia() descarta el archivo por getimagesize() antes
// de move_uploaded_file(), así que un $_FILES armado a mano (sin subida HTTP real) es representativo.
// Argumentos: <admin_id> <post_json>
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$admin_id = (int) ($argv[1] ?? 0);
$post = json_decode((string) ($argv[2] ?? '{}'), true);
if ($admin_id <= 0 || !is_array($post)) {
	fwrite(STDERR, "Uso: panel-evidencia-invalida-wp-test-run.php <admin_id> <post_json>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
require $at_cc_wp_load;
if (!function_exists('at_cc_accion_registrar_aceptacion')) {
	fwrite(STDERR, "panel.php no está cargado en ese sitio.\n");
	exit(2);
}
wp_set_current_user($admin_id);
add_filter('pre_wp_mail', function () { return true; });

$id = (int) ($post['proposal_id'] ?? 0);
$nonce = wp_create_nonce('at_cc_aceptar_' . $id);
$_POST = $post;
$_POST['_wpnonce'] = $nonce;
$_REQUEST['_wpnonce'] = $nonce;

// Archivo falso: texto plano con extensión .png (el caso exacto del hallazgo).
$tmp = tempnam(sys_get_temp_dir(), 'at_cc_ev_falsa');
file_put_contents($tmp, 'esto no es una imagen');
$_FILES['evidencia'] = [
	'name' => ['falsa.png'], 'type' => ['image/png'], 'tmp_name' => [$tmp],
	'error' => [UPLOAD_ERR_OK], 'size' => [filesize($tmp)],
];

at_cc_accion_registrar_aceptacion();
// No debería llegar aquí: la acción real siempre termina con exit.
fwrite(STDERR, "ADVERTENCIA: at_cc_accion_registrar_aceptacion() no llamó exit.\n");
