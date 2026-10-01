<?php
// Uso interno de las pruebas del plan (accion-wp-helpers.php): ejecuta una acción admin-post en un proceso PHP
// aparte, porque esas acciones siempre terminan con wp_safe_redirect() + exit.
// Imprime una línea «REDIRECT <url>» por la redirección y una «N8N <url> <cuerpo>» por cada llamada HTTP saliente
// (que nunca sale: se contesta aquí con 200, o con un error de conexión si el modo es «caido»). Se escribe con
// fwrite(STDOUT) para no marcar la salida como enviada (header() de wp_redirect no avisa nada).
// Argumentos: <accion> <user_id> <accion_del_nonce> <archivo_json_con_el_post> <n8n: ok|caido>
$at_pt_wp_load = getenv('AT_WP_LOAD');
if (!$at_pt_wp_load || !is_file($at_pt_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$accion = (string) ($argv[1] ?? '');
$usuario = (int) ($argv[2] ?? 0);
$accion_nonce = (string) ($argv[3] ?? '');
$post = json_decode((string) @file_get_contents((string) ($argv[4] ?? '')), true);
$modo_n8n = (string) ($argv[5] ?? 'ok');
if ($accion === '' || $accion_nonce === '' || !is_array($post)) {
	fwrite(STDERR, "Uso: accion-wp-test-run.php <accion> <user_id> <accion_del_nonce> <archivo_json> <ok|caido>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/wp-admin/admin-post.php';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
// Defensa en profundidad (como wp-bootstrap.php de la Task 5): los flujos del plan nunca apuntan al n8n de PROD.
foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', 'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $at_pt_c => $at_pt_p) {
	if (!defined($at_pt_c)) {
		define($at_pt_c, 'http://127.0.0.1:9/' . $at_pt_p);
	}
}
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_pt_wp_load;
if (!has_action('admin_post_' . $accion)) {
	fwrite(STDERR, "La acción admin_post_{$accion} no está registrada: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
wp_set_current_user($usuario);
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', function ($pre, $args, $url) use ($modo_n8n) {
	if (strpos($url, 'wp-cron.php') !== false) { // el cron que WordPress lanza al terminar: ni sale ni se anota
		return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
	}
	$cuerpo = $args['body'] ?? '';
	fwrite(STDOUT, 'N8N ' . $url . ' ' . (is_string($cuerpo) ? $cuerpo : (string) wp_json_encode($cuerpo)) . "\n");
	if ($modo_n8n === 'caido') {
		return new WP_Error('http_request_failed', 'cURL error 7: Failed to connect (prueba)');
	}
	return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);
add_filter('wp_redirect', function ($ubicacion) {
	fwrite(STDOUT, 'REDIRECT ' . $ubicacion . "\n");
	return $ubicacion;
}, PHP_INT_MAX);
$nonce = wp_create_nonce($accion_nonce);
// WordPress entrega $_POST con barras agregadas (wp_magic_quotes) y las acciones hacen wp_unslash(): se imita.
$_POST = wp_slash($post + ['action' => $accion, '_wpnonce' => $nonce]);
$_REQUEST = $_POST;
$_SERVER['REQUEST_METHOD'] = 'POST';
do_action('admin_post_' . $accion);
fwrite(STDOUT, "SIN_EXIT\n");
