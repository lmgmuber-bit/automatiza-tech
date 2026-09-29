<?php
// Carga el WordPress local de prueba del plan de trabajo (Task 0: sitio wp-local-plan).
// Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/plan/<prueba>-wp-test.php
$at_pt_wp_load = getenv('AT_WP_LOAD');
if (!$at_pt_wp_load || !is_file($at_pt_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba (<scratchpad>/wp-local-plan/wp-load.php, Task 0).\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8093';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
// Defensa: en las pruebas los flujos del plan nunca apuntan al n8n de PROD (router.php solo cubre el
// servidor web, no la línea de comandos): un puerto local cerrado. Además datos-prueba.php intercepta todo.
foreach (['AT_N8N_PLAN_BORRADOR' => 'plan-v1-borrador', 'AT_N8N_PLAN_CAMBIOS' => 'plan-v1-cambios', 'AT_N8N_PLAN_RENDER' => 'plan-v1-render'] as $at_pt_c => $at_pt_p) {
	if (!defined($at_pt_c)) {
		define($at_pt_c, 'http://127.0.0.1:9/' . $at_pt_p);
	}
}
define('WP_USE_THEMES', false);
require $at_pt_wp_load;
if (!function_exists('at_pt_crear_plan')) {
	fwrite(STDERR, "El módulo plan-trabajo no está cargado en ese sitio (falta inc/plan-trabajo/cargar.php o su línea en inc/admin-proposals.php).\n");
	exit(2);
}
$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
/** Corta la prueba con una falla legible si falta una función (paso rojo del ciclo TDD). */
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) {
			echo "FALLA falta la función {$f}()\n\n1 FALLAS\n";
			exit(1);
		}
	}
}
