<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-ajustes-wp-test.php
// Task 5: feriados, tabla de tiempos y contratos firmados que aún no tienen plan.
// Las opciones se simulan con pre_option_*: la base local no se toca.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_feriados', 'at_pt_duraciones', 'at_pt_contratos_sin_plan');
require __DIR__ . '/datos-prueba.php';

// 1) Feriados de «Ajustes del chat».
$horario = null;
add_filter('pre_option_automatiza_chat_schedule', function () use (&$horario) { return $horario; });
$horario = ['monday' => ['start' => '09:00', 'end' => '18:00'], 'holidays' => "2026-10-12\r\n2026-12-25\n\nno-es-fecha\n"];
$feriados = at_pt_feriados();
ok(in_array('2026-10-12', $feriados, true) && in_array('2026-12-25', $feriados, true) && !in_array('no-es-fecha', $feriados, true), '1) lee los feriados del ajuste existente (una fecha por línea, con \\r\\n o \\n)');
$horario = ['monday' => ['start' => '09:00', 'end' => '18:00']];
ok(at_pt_feriados() === [], '1) sin feriados cargados: lista vacía');
$horario = 'texto';
ok(at_pt_feriados() === [], '1) ajuste con otra forma: lista vacía');

// 2) Tabla de tiempos de referencia.
$tabla = '';
add_filter('pre_option_at_pt_duraciones', function () use (&$tabla) { return $tabla; });
ok(at_pt_duraciones() === at_pt_duraciones_defecto(), '2) sin tabla guardada: la de referencia');
$tabla = '{roto';
ok(at_pt_duraciones() === at_pt_duraciones_defecto(), '2) tabla ilegible: la de referencia');
$tabla = wp_json_encode([
	'sitio_una_pagina' => ['nombre' => 'Sitio de una página', 'diseno' => 4, 'desarrollo' => 6, 'pruebas' => 2, 'implementacion' => 1],
	'MAL CLAVE'        => ['nombre' => 'Fila inválida', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
]);
$t = at_pt_duraciones();
ok(($t['sitio_una_pagina']['diseno'] ?? null) === 4 && !isset($t['MAL CLAVE']), '2) la tabla de Luis, normalizada (sin las filas inválidas)');
$tabla = ['google_ads' => ['nombre' => 'Google Ads', 'diseno' => 2, 'desarrollo' => 3, 'pruebas' => 1, 'implementacion' => 1]];
ok(isset(at_pt_duraciones()['google_ads']), '2) también acepta la tabla guardada como arreglo');

// 3) Contratos de servicios firmados sin plan, de todas las fichas del cliente.
$m = pt_marca();
$cli = pt_cliente($m);
$ficha2 = pt_ficha($cli['crm'], $m . '-2');
$c1 = pt_contrato($cli['tech'], null);
$c2 = pt_contrato($ficha2, null);
pt_contrato($cli['tech'], null, ['type' => 'soporte']);
pt_contrato($cli['tech'], null, ['status' => 'sent']);
$c5 = pt_contrato($cli['tech'], null);
at_pt_crear_plan($c5);
$ids = array_map(function ($c) { return (int) $c->id; }, at_pt_contratos_sin_plan($cli['crm']));
ok($ids === [$c2, $c1], '3) servicios firmados sin plan de las dos fichas, del más nuevo al más viejo (sin soporte, sin firmar ni con plan)');
ok(at_pt_contratos_sin_plan(0) === [], '3) sin cliente: lista vacía');
$otro = pt_cliente(pt_marca());
ok(at_pt_contratos_sin_plan($otro['crm']) === [], '3) otro cliente no ve estos contratos');

fin();
