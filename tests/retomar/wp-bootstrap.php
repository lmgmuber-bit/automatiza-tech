<?php
// Pruebas locales de Retomar. Nunca ejecutar contra una instalación de producción.
$at_rt_wp_load = getenv('AT_WP_LOAD');
if (!$at_rt_wp_load || !is_file($at_rt_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (!defined('WP_USE_THEMES')) {
	define('WP_USE_THEMES', false);
}
require $at_rt_wp_load;
// Bloquea correo y HTTP: las pruebas nunca contactan servicios externos.
add_filter('pre_wp_mail', static fn() => true);
add_filter('pre_http_request', static fn() => new WP_Error('prueba_local', 'HTTP externo bloqueado en pruebas.'), PHP_INT_MAX);
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) {
			echo "FALLA falta la función {$f}()\n\n1 FALLAS\n";
			exit(1);
		}
	}
}
