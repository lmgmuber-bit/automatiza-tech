<?php
// Proceso hijo de firma-cliente-visor-wp-test.php (las dos páginas terminan con exit).
//   php firma-cliente-visor-wp-test-run.php pagina <sign_token>   -> HTML de contracts/sign-contract.php
//   php firma-cliente-visor-wp-test-run.php descarga '<query json>' -> lo que entrega admin-ajax (el PDF o el error)
$at_cc_wp_load = getenv('AT_WP_LOAD');
if (!$at_cc_wp_load || !is_file($at_cc_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD.\n");
	exit(2);
}
$modo = $argv[1] ?? '';
$_SERVER['HTTP_HOST'] = 'localhost:8089';
$_SERVER['REQUEST_METHOD'] = 'GET';
// Una IP distinta por corrida: sign-contract.php limita a 10 visitas por hora e IP.
$_SERVER['REMOTE_ADDR'] = '10.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(1, 254);
if ($modo === 'pagina') {
	$_SERVER['REQUEST_URI'] = '/contracts/sign-contract.php';
	$_GET = ['token' => (string) ($argv[2] ?? '')];
	define('WP_USE_THEMES', false);
	require $at_cc_wp_load;
	require dirname($at_cc_wp_load) . '/contracts/sign-contract.php';
	exit;
}
if ($modo === 'descarga') {
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
	$_GET = json_decode((string) ($argv[2] ?? '{}'), true) ?: [];
	define('WP_USE_THEMES', false);
	require $at_cc_wp_load;
	require_once ABSPATH . 'contracts/contract-service.php';
	add_filter('wp_die_handler', function () {
		return function ($mensaje) {
			echo 'WP_DIE: ' . (is_string($mensaje) ? $mensaje : 'error');
			exit;
		};
	});
	at_download_contract_ajax_handler();
	exit;
}
fwrite(STDERR, "Modo desconocido.\n");
exit(2);
