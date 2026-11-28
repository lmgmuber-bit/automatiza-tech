<?php
// Carga el WordPress local de prueba que arma el controlador (Task 0).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/prospeccion/<prueba>-wp-test.php
$at_pro_wp_load = getenv('AT_WP_LOAD');
if (!$at_pro_wp_load || !is_file($at_pro_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba (la entrega el controlador).\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8089';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
require $at_pro_wp_load;
if (!function_exists('at_pro_migrar_esquema')) {
	fwrite(STDERR, "El módulo prospeccion no está cargado en ese sitio.\n");
	exit(2);
}
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
