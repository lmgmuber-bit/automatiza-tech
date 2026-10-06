<?php
// Uso interno: corre admin_post_<accion> en un proceso aparte (las acciones terminan con redirect + exit).
// Argumentos: <accion> <user_id> <accion_del_nonce|-> <json_post> <json_files>
// Entorno opcional: EN_IP, EN_MAIL_FALLA, EN_SIN_PRUEBAS, y para simular un POST que superó post_max_size (PHP vacía $_POST y $_FILES):
// EN_CONTENT_LENGTH (bytes) y EN_GET_CODIGO (el código que viaja en la URL de la acción).
$en_wp_load = getenv('AT_WP_LOAD');
[$_, $accion, $usuario, $nonce_accion, $f_post, $f_files] = $argv + [null, '', '0', '-', '', ''];
$_SERVER['HTTP_HOST'] = 'localhost:8093';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-post.php';
$_SERVER['REMOTE_ADDR'] = getenv('EN_IP') ?: '127.0.0.1';
define('WP_USE_THEMES', false);
define('WP_ADMIN', true);
// Como en wp-bootstrap.php; con EN_SIN_PRUEBAS=1 no se define y rige is_uploaded_file() como en PROD.
if (!getenv('EN_SIN_PRUEBAS')) {
	define('AT_EN_PRUEBAS', true);
}
require $en_wp_load;
wp_set_current_user((int) $usuario);
add_filter('pre_http_request', function () { return new WP_Error('prueba', 'sin red'); }, 1);
add_filter('pre_wp_mail', function ($n, $a) {
	fwrite(STDOUT, 'MAIL ' . $a['to'] . ' | ' . $a['subject'] . "\n");
	if (getenv('EN_MAIL_FALLA')) {
		// Como PHPMailer al fallar: avisa por wp_mail_failed y wp_mail devuelve false.
		do_action('wp_mail_failed', new WP_Error('wp_mail_failed', 'SMTP simulado: no se pudo autenticar <b>x</b>'));
		return false;
	}
	return true;
}, 1, 2);
add_filter('wp_redirect', function ($u) { fwrite(STDOUT, 'REDIRECT ' . $u . "\n"); return $u; }, PHP_INT_MAX);
$post = json_decode((string) file_get_contents($f_post), true) ?: [];
if ($nonce_accion !== '-') {
	$post['_wpnonce'] = wp_create_nonce($nonce_accion);
}
$_SERVER['REQUEST_METHOD'] = 'POST';
if (getenv('EN_CONTENT_LENGTH') !== false && getenv('EN_CONTENT_LENGTH') !== '') {
	// POST demasiado grande: PHP deja $_POST y $_FILES vacíos y solo queda lo que viaja en la URL.
	$_SERVER['CONTENT_LENGTH'] = getenv('EN_CONTENT_LENGTH');
	$_POST = [];
	$_FILES = [];
	$_GET = ['action' => $accion] + (getenv('EN_GET_CODIGO') ? ['codigo' => getenv('EN_GET_CODIGO')] : []);
	$_REQUEST = $_GET;
} else {
	$_POST = wp_slash($post + ['action' => $accion]);
	$_REQUEST = $_POST;
	$_FILES = json_decode((string) file_get_contents($f_files), true) ?: [];
}
do_action(((int) $usuario > 0 ? 'admin_post_' : 'admin_post_nopriv_') . $accion);
fwrite(STDOUT, "SIN_EXIT\n");
