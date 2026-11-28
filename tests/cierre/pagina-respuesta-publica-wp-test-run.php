<?php
// Uso interno de pagina-respuesta-publica-wp-test.php: corre at_cc_procesar_respuesta_publica() o
// at_cc_procesar_datos_contrato_publica() en un proceso PHP aparte, porque las dos siempre terminan
// con wp_safe_redirect()+exit (mismo patrón que panel-ronda2-wp-test-run.php). El destino del
// redirect se imprime en stdout como "REDIRECT:<url>" (capturado con un filtro 'wp_redirect', que
// corre ANTES del exit real) para poder revisarlo sin necesitar una respuesta HTTP real.
// Argumentos: <responder|datos_contrato> <GET|POST> <post_json> <get_json>
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$accion = (string) ($argv[1] ?? '');
$metodo = (string) ($argv[2] ?? 'POST');
$post = json_decode((string) ($argv[3] ?? '{}'), true);
$get = json_decode((string) ($argv[4] ?? '{}'), true);
if (!in_array($accion, ['responder', 'datos_contrato'], true) || !is_array($post) || !is_array($get)) {
	fwrite(STDERR, "Uso: pagina-respuesta-publica-wp-test-run.php <responder|datos_contrato> <GET|POST> <post_json> <get_json>\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '198.51.100.77';
$_SERVER['REQUEST_METHOD'] = $metodo;
define('WP_USE_THEMES', false);
define('WP_ADMIN', true); // admin-post.php real corre en contexto de admin.
require $at_cc_wp_load;
if (!function_exists('at_cc_procesar_respuesta_publica') || !function_exists('at_cc_procesar_datos_contrato_publica')) {
	fwrite(STDERR, "pagina.php no está cargado en ese sitio.\n");
	exit(2);
}
$_POST = $post;
$_GET = $get;
add_filter('wp_redirect', function ($location) {
	fwrite(STDOUT, "REDIRECT:" . $location . "\n");
	return $location;
});
// El correo (a Luis o al cliente) se manda en este mismo proceso hijo: pre_wp_mail() registrado en el
// padre no alcanza a correr aquí. Se imprime a stdout (base64 para no chocar con los saltos de línea
// del propio mensaje) y el padre lo decodifica.
add_filter('pre_wp_mail', function ($nulo, $atts) {
	fwrite(STDOUT, "MAIL:" . base64_encode((string) wp_json_encode($atts)) . "\n");
	return true;
}, 10, 2);
if ($accion === 'responder') {
	at_cc_procesar_respuesta_publica();
} else {
	at_cc_procesar_datos_contrato_publica();
}
// No debería llegar aquí: las dos acciones reales siempre terminan con exit.
fwrite(STDERR, "ADVERTENCIA: la acción {$accion} no llamó exit.\n");
