<?php
// Correr: AT_WP_LOAD=<sitio>/wp-load.php php tests/retomar/globales-wp-test.php
// Cargar el módulo Retomar no puede pisar variables globales. Pasó con un foreach suelto en panel.php: dejaba
// $accion = 'revertir_baja' y rompía las acciones del plan de trabajo y de entregables (revisión B, 18-oct).
$accion = 'centinela-retomar';
$tabla = 'centinela-retomar';
$id = 'centinela-retomar';
require __DIR__ . '/wp-bootstrap.php';
ok(function_exists('at_rt_panel'), 'el módulo Retomar está cargado');
ok($accion === 'centinela-retomar', 'cargar el módulo no cambia la global $accion');
ok($tabla === 'centinela-retomar', 'cargar el módulo no cambia la global $tabla');
ok($id === 'centinela-retomar', 'cargar el módulo no cambia la global $id');
ok(has_action('admin_post_at_rt_revertir_baja') && has_action('admin_post_at_rt_guardar'), 'las acciones del panel siguen registradas');
fin();
