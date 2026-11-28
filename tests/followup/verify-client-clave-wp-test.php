<?php
// Correr: AT_WP_LOAD=<ruta> php tests/followup/verify-client-clave-wp-test.php
// 28-oct-2026: verify-client exige la clave X-AT-Secret de n8n (at_cc_rest_auth). La prueba toma el permiso tal
// como está escrito en el admin-followup-meetings.php de ESTA copia del repo (no el del sitio local, que puede ir
// atrasado), lo registra en una ruta de prueba con el callback real y la llama con y sin clave.
require __DIR__ . '/../cierre/wp-bootstrap.php';
foreach (['automatiza_tech_verify_client_by_phone', 'at_cc_rest_auth'] as $f) {
	if (!function_exists($f)) {
		fwrite(STDERR, "$f() no está definida.\n");
		exit(2);
	}
}
if (!defined('AT_REST_SECRET')) {
	define('AT_REST_SECRET', 'clave-de-prueba-verify-client');
}

$fuente = (string) file_get_contents(__DIR__ . '/../../wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php');
$i = strpos($fuente, "register_rest_route('automatiza-tech/v1', '/verify-client'");
ok($i !== false, 'el archivo registra /verify-client');
ok(!preg_match("/'permission_callback'\s*=>\s*'__return_true'/", substr($fuente, (int) $i, 600)), 'verify-client ya no es pública');
$ok_permiso = preg_match("/'permission_callback'\s*=>\s*(function\s*\(WP_REST_Request \\\$r\)\s*\{.*?\n\s*\}),/s", substr($fuente, (int) $i, 900), $m);
ok($ok_permiso === 1, 'se encuentra el permiso de verify-client en el archivo');
$permiso = eval('return ' . $m[1] . ';');

add_action('rest_api_init', function () use ($permiso) {
	register_rest_route('automatiza-tech/v1', '/verify-client-prueba-clave', [
		'methods' => 'GET', 'callback' => 'automatiza_tech_verify_client_by_phone', 'permission_callback' => $permiso,
	]);
});
rest_get_server();
do_action('rest_api_init', rest_get_server());

$pedir = function (?string $clave): WP_REST_Response {
	$r = new WP_REST_Request('GET', '/automatiza-tech/v1/verify-client-prueba-clave');
	$r->set_query_params(['phone' => '56900000000']);
	if ($clave !== null) {
		$r->set_header('X-AT-Secret', $clave);
	}
	ob_start();
	$res = rest_do_request($r);
	ob_end_clean();
	return $res;
};
foreach (['sin clave' => null, 'clave vacía' => '', 'clave equivocada' => 'otra-clave'] as $caso => $clave) {
	$res = $pedir($clave);
	$d = (array) $res->get_data();
	ok($res->get_status() === 401 && !isset($d['client_name']) && !isset($d['client_email']) && !isset($d['proposal_id']),
		"$caso: 401, sin nombre, correo ni propuesta");
}
$res = $pedir(AT_REST_SECRET);
$d = (array) $res->get_data();
ok($res->get_status() === 200 && array_key_exists('is_client', $d), 'con la clave correcta responde como siempre (200 y is_client)');

fin();
