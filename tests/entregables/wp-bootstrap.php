<?php
// Carga el WordPress local de prueba (sitio wp-local-plan del scratchpad, uniones a este worktree).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/entregables/<prueba>-wp-test.php
$at_en_wp_load = getenv('AT_WP_LOAD');
if (!$at_en_wp_load || !is_file($at_en_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
// En CLI no hay subida HTTP real: con esta constante (solo aquí y en accion-run.php) un archivo local cuenta como subido.
define('AT_EN_PRUEBAS', true);
require $at_en_wp_load;
// Las pruebas nunca salen a la red ni mandan correos reales.
add_filter('pre_http_request', function ($pre, $args, $url) {
	return new WP_Error('prueba', 'HTTP bloqueado en la prueba: ' . $url);
}, 1, 3);
$GLOBALS['en_correos'] = [];
add_filter('pre_wp_mail', function ($nulo, $atts) {
	if ($nulo !== null) { // otra prueba ya decidió (p. ej. simular una falla con prioridad 0)
		return $nulo;
	}
	$GLOBALS['en_correos'][] = $atts;
	return true;
}, 1, 2);
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
/** Corta con una falla legible si falta una función (paso rojo). */
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) {
			echo "FALLA falta la función {$f}()\n\n1 FALLAS\n";
			exit(1);
		}
	}
}
/** Primer administrador del sitio local. */
function en_admin_id(): int {
	$a = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
	$id = (int) ($a[0] ?? 0);
	if ($id <= 0) {
		fwrite(STDERR, "No hay administrador en el WordPress local de prueba.\n");
		exit(2);
	}
	return $id;
}
