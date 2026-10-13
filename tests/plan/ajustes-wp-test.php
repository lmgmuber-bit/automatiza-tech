<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ajustes-wp-test.php
// Task 8: CRM › Ajustes del plan (tabla de tiempos de referencia editable, opción at_pt_duraciones en JSON).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';

if (!function_exists('at_pt_render_ajustes') || !function_exists('at_pt_duraciones_de_filas')) {
	fwrite(STDERR, "ajustes.php no está cargado: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
// La base local es compartida: se guarda la opción tal como estaba y se deja igual al terminar, también si la
// prueba se cae a mitad de camino (register_shutdown_function corre igual tras un error fatal o un exit).
$opcion_antes = get_option('at_pt_duraciones', null);
register_shutdown_function(function () use ($opcion_antes) {
	if ($opcion_antes === null) {
		delete_option('at_pt_duraciones');
	} else {
		update_option('at_pt_duraciones', $opcion_antes, false);
	}
});
$admin = pt_admin_id();
wp_set_current_user($admin);

// ---------- Filas del formulario → tabla ----------
$fila = function (string $clave, string $nombre, array $dias, bool $quitar = false): array {
	$f = ['clave' => $clave, 'nombre' => $nombre, 'diseno' => $dias[0], 'desarrollo' => $dias[1], 'pruebas' => $dias[2], 'implementacion' => $dias[3]];
	return $quitar ? $f + ['quitar' => '1'] : $f;
};
$r = at_pt_duraciones_de_filas([
	$fila('sitio_una_pagina', 'Sitio de una página', ['4', '6', '2', '1']),
	$fila('', 'Automatización móvil', ['2', '', '1', '1']),
	$fila('', '', ['', '', '', '']),
	$fila('muchos_dias', 'Demasiados días', ['61', '1', '1', '1']),
	$fila('letras', 'Con letras', ['dos', '1', '1', '1']),
	$fila('sitio_una_pagina', 'Clave repetida', ['1', '1', '1', '1']),
	$fila('X-Mala', 'Clave con mayúsculas', ['1', '1', '1', '1']),
	$fila('quitada', 'Marcada para quitar', ['1', '1', '1', '1'], true),
	$fila('sin_nombre', '', ['1', '1', '1', '1']),
]);
ok(array_keys($r['tabla']) === ['sitio_una_pagina', 'automatizacion_movil'], 'quedan solo las dos filas válidas, en su orden: ' . implode(', ', array_keys($r['tabla'])));
$s = $r['tabla']['sitio_una_pagina'] ?? [];
ok(($s['nombre'] ?? '') === 'Sitio de una página' && ($s['diseno'] ?? null) === 4 && ($s['desarrollo'] ?? null) === 6 && ($s['pruebas'] ?? null) === 2 && ($s['implementacion'] ?? null) === 1, 'la fila editada queda con sus días como enteros');
$a = $r['tabla']['automatizacion_movil'] ?? [];
ok(($a['desarrollo'] ?? null) === 0, 'un día en blanco vale 0');
ok($r['descartadas'] === 5, 'se cuentan 5 filas descartadas (61 días, letras, clave repetida, clave inválida, sin nombre): ' . $r['descartadas']);
ok(at_pt_clave_de_nombre('Automatización móvil') === 'automatizacion_movil' && at_pt_clave_de_nombre('¡Sitio  web / tienda!') === 'sitio_web_tienda', 'la clave se arma del nombre sin tildes ni signos');

// ---------- Guardar por admin-post ----------
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'at_pt_guardar_duraciones', ['filas' => [
	$fila('sitio_una_pagina', 'Sitio de una página', ['4', '6', '2', '1']),
	$fila('plataforma', 'Plataforma o sistema a medida', ['8', '22', '5', '3']),
	$fila('mala', 'Fila mala', ['99', '1', '1', '1']),
]]);
$q = pt_query($r['redirect']);
ok(($q['page'] ?? '') === 'at-pt-ajustes' && ($q['pt_msg'] ?? '') === 'guardada' && ($q['pt_descartadas'] ?? '') === '1', 'Guardar: vuelve a los ajustes con «guardada» y 1 fila descartada: ' . $r['redirect']);
$crudo = get_option('at_pt_duraciones');
ok(is_string($crudo) && is_array(json_decode($crudo, true)), 'la opción at_pt_duraciones queda guardada como JSON');
$t = at_pt_duraciones();
ok(array_keys($t) === ['sitio_una_pagina', 'plataforma'] && (int) $t['plataforma']['desarrollo'] === 22, 'at_pt_duraciones() devuelve la tabla guardada');

// Sin filas válidas: no se guarda nada.
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'at_pt_guardar_duraciones', ['filas' => [$fila('mala', 'Fila mala', ['99', '1', '1', '1'])]]);
ok((pt_query($r['redirect'])['pt_msg'] ?? '') === 'vacia', 'solo filas inválidas: avisa «vacia»');
ok(get_option('at_pt_duraciones') === $crudo, 'solo filas inválidas: la tabla guardada no cambia');

// Sin permiso (visitante sin sesión, id 0) y con un nonce de otra acción: no cambia nada.
$r = pt_correr_accion('at_pt_guardar_duraciones', 0, 'at_pt_guardar_duraciones', ['restaurar' => '1']);
ok($r['redirect'] === '' && strpos($r['salida'], 'Sin permiso') !== false, 'sin manage_options: se corta con «Sin permiso»');
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'otra_accion', ['restaurar' => '1']);
ok($r['redirect'] === '', 'con un nonce de otra acción: no redirige ni guarda');
ok(get_option('at_pt_duraciones') === $crudo, 'ni el visitante ni el nonce ajeno cambiaron la tabla');

// Restaurar la propuesta.
$r = pt_correr_accion('at_pt_guardar_duraciones', $admin, 'at_pt_guardar_duraciones', ['restaurar' => '1']);
ok((pt_query($r['redirect'])['pt_msg'] ?? '') === 'restaurada', 'Restaurar: avisa «restaurada»');
ok(get_option('at_pt_duraciones', 'NO') === 'NO' && at_pt_duraciones() === at_pt_duraciones_defecto(), 'Restaurar: se borra la opción y vuelve la tabla propuesta');

// ---------- La página ----------
$_GET = ['page' => 'at-pt-ajustes', 'pt_msg' => 'guardada', 'pt_descartadas' => '2'];
ob_start();
at_pt_render_ajustes();
$h = (string) ob_get_clean();
ok(strpos($h, 'name="action" value="at_pt_guardar_duraciones"') !== false && strpos($h, 'name="_wpnonce"') !== false, 'el formulario va a admin-post con su nonce');
ok(strpos($h, 'name="filas[0][nombre]" value="Sitio de una página"') !== false && strpos($h, 'name="filas[0][diseno]" value="3"') !== false, 'muestra la tabla vigente (Sitio de una página, diseño 3)');
$n = count(at_pt_duraciones_defecto());
ok(strpos($h, 'name="filas[' . ($n + 1) . '][nombre]" value=""') !== false && strpos($h, 'name="filas[' . ($n + 2) . ']') === false, 'agrega dos filas vacías para sumar tipos de servicio');
ok(strpos($h, 'Tabla guardada.') !== false && strpos($h, '2 fila(s) no se guardaron') !== false, 'muestra el aviso y cuántas filas se descartaron');
ok(strpos($h, 'Restaurar la tabla propuesta') !== false && strpos($h, 'return confirm(') !== false, 'Restaurar pide confirmación');
ok(has_action('admin_menu', 'at_pt_menu_ajustes') === 20, 'el submenú se registra en admin_menu con prioridad 20 (después del menú del CRM)');

$_GET = [];
fin(); // la opción at_pt_duraciones vuelve a como estaba en la función de cierre de arriba
