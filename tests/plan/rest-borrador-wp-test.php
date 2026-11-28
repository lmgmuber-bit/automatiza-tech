<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/rest-borrador-wp-test.php
// Task 7: POST /plan/{id}/borrador — valida, tabla, fechas en días hábiles, días de Luis, planes rotos y tope.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_rest_borrador');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
// Tabla de referencia y dos feriados fijos: el lunes 12 y el martes 20 de octubre de 2026.
add_filter('pre_option_at_pt_duraciones', function () { return ''; });
add_filter('pre_option_automatiza_chat_schedule', function () { return ['holidays' => "2026-10-12\n2026-10-20\n"]; });
$GLOBALS['pt_http_respuesta'] = 200;

// 1) Borrador válido: contrato firmado el viernes 9 de octubre de 2026.
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], pt_propuesta($m)));
$GLOBALS['pt_http'] = [];
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$f = at_pt_plan($pid);
$p = at_pt_payload($f);
ok($r->get_status() === 200 && ($r->get_data()['ok'] ?? null) === true && ($r->get_data()['errores'] ?? null) === [], '1) borrador válido: 200 ok');
ok($f->estado === 'borrador' && !empty($p['fases']) && ($p['fecha_firma'] ?? '') === '2026-10-09', '1) queda en «borrador» con su contenido y la fecha de firma');
ok($f->fecha_inicio === '2026-10-13' && ($p['cronograma']['inicio'] ?? '') === '2026-10-13' && ($p['fecha_inicio'] ?? '') === '2026-10-13', '1) firmado el viernes 9 con el lunes 12 feriado: parte el martes 13 (Review Focus 1)');
$malas = [];
foreach (pt_actividades($p) as $a) {
	foreach (['desde', 'hasta'] as $k) {
		$dia = (string) ($a[$k] ?? '');
		$fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) ? new DateTimeImmutable($dia, new DateTimeZone('UTC')) : null;
		if (!$fecha || (int) $fecha->format('N') >= 6 || in_array($dia, ['2026-10-12', '2026-10-20'], true)) {
			$malas[] = ($a['nombre'] ?? '?') . " {$k}={$dia}";
		}
	}
}
ok($malas === [], '1) ninguna actividad empieza ni termina en fin de semana ni en un feriado' . ($malas ? ': ' . implode(', ', $malas) : ''));
ok((pt_actividad($p, 'Maqueta de la portada')['desde'] ?? '') === '2026-10-19' && (pt_actividad($p, 'Maqueta de la portada')['hasta'] ?? '') === '2026-10-22', '1) la secuencia salta el feriado del martes 20: la maqueta (3 días hábiles) va del lunes 19 al jueves 22');
ok((pt_actividad($p, 'Maqueta de la portada')['dias_habiles'] ?? 0) === 3 && (pt_actividad($p, 'Maqueta de la portada')['origen'] ?? '') === 'tabla', '1) días de la tabla donde calza (diseño de un sitio de una página: 3)');
ok((pt_actividad($p, 'Capacitación')['origen'] ?? '') === 'ia', '1) sin par en la tabla: origen «ia»');
$render = pt_llamadas('render');
ok(count($render) === 1 && $render[0]['cuerpo'] === ['id' => $pid, 'codigo' => (string) $f->codigo, 'modo' => 'draft', 'aviso' => true], '1) pide la vista previa con aviso a Luis');

// 2) Luis eligió un sábado como fecha de inicio (Review Focus 1).
$m2 = pt_marca();
$cli2 = pt_cliente($m2);
$pid2 = at_pt_crear_plan(pt_contrato($cli2['tech'], null));
at_pt_guardar($pid2, ['fecha_inicio' => '2026-10-17']);
pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$f2 = at_pt_plan($pid2);
ok($f2->estado === 'borrador' && $f2->fecha_inicio === '2026-10-19' && (at_pt_payload($f2)['cronograma']['inicio'] ?? '') === '2026-10-19', '2) inicio un sábado: parte el lunes 19');

// 3) Borradores que no se aplican.
at_pt_guardar($pid2, ['estado' => 'listo']);
$antes = at_pt_plan($pid2)->payload;
$r = pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
ok($r->get_status() === 409 && at_pt_plan($pid2)->payload === $antes && at_pt_plan($pid2)->estado === 'listo', '3) plan «listo»: un borrador tardío no se aplica (409)');
ok(pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'otro'])->get_status() === 400, '3) origen desconocido: 400');
ok(pt_pedir('POST', '/plan/' . pt_plan_inexistente() . '/borrador', ['plan' => pt_plan_ia(), 'origen' => 'borrador'])->get_status() === 404, '3) plan que no existe: 404');

// 4) Planes rotos (Review Focus 2): «error» con motivo legible y nada guardado.
$m3 = pt_marca();
$cli3 = pt_cliente($m3);
$pid3 = at_pt_crear_plan(pt_contrato($cli3['tech'], null));
$r = pt_pedir('POST', "/plan/{$pid3}/borrador", 'esto no es json');
ok($r->get_status() === 400 && at_pt_plan($pid3)->estado === 'generando' && at_pt_plan($pid3)->payload === null, '4) cuerpo que no es JSON: WordPress lo rechaza (400) sin tocar el plan');
$con = function (callable $cambio): array { $p = pt_plan_ia(); $cambio($p); return $p; };
$malos = [
	'plan en texto roto'      => '{"fases": [',
	'fase desconocida'        => $con(function (&$p) { $p['fases'][] = ['clave' => 'marketing', 'descripcion' => '', 'bloques' => [['nombre' => 'Campaña', 'entrega' => false, 'entregable' => '', 'actividades' => [['nombre' => 'Anuncios', 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 3, 'servicio' => '', 'etapa' => '']]]]]; }),
	'días 0'                  => $con(function (&$p) { $p['fases'][0]['bloques'][0]['actividades'][0]['dias_habiles'] = 0; }),
	'días 200'                => $con(function (&$p) { $p['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 200; }),
	'sin fases'               => $con(function (&$p) { $p['fases'] = []; }),
	'bloques sin actividades' => $con(function (&$p) { foreach ($p['fases'] as $i => $f) { foreach ($f['bloques'] as $j => $b) { $p['fases'][$i]['bloques'][$j]['actividades'] = []; } } }),
];
foreach ($malos as $nombre => $plan) {
	at_pt_guardar($pid3, ['estado' => 'generando', 'nota' => '']);
	if (is_array($plan)) {
		$v = at_pt_validar_plan($plan);
		ok(empty($v['ok']), "4) {$nombre}: at_pt_validar_plan() lo rechaza");
	}
	$r = pt_pedir('POST', "/plan/{$pid3}/borrador", ['plan' => $plan, 'origen' => 'borrador']);
	$f3 = at_pt_plan($pid3);
	ok($r->get_status() === 422 && ($r->get_data()['ok'] ?? null) === false && !empty($r->get_data()['errores']), "4) {$nombre}: 422 con los errores");
	ok($f3->estado === 'error' && strpos((string) $f3->nota, 'La IA devolvió un plan que no sirve: ') === 0 && mb_strlen((string) $f3->nota) > 40, "4) {$nombre}: el plan queda en «error» con un motivo legible");
	ok($f3->payload === null, "4) {$nombre}: no se guarda nada");
}

// 5) Textos (Review Focus 2): uno enorme se rechaza; uno largo se acorta con aviso (en la nota y en la respuesta).
at_pt_guardar($pid3, ['estado' => 'generando', 'nota' => '']);
$enorme = pt_plan_ia();
$enorme['fases'][0]['descripcion'] = str_repeat('Texto muy largo. ', 6000);
$r = pt_pedir('POST', "/plan/{$pid3}/borrador", ['plan' => $enorme, 'origen' => 'borrador']);
$f3 = at_pt_plan($pid3);
ok($r->get_status() === 422 && $f3->estado === 'error' && $f3->payload === null && strpos((string) $f3->nota, 'demasiado largo') !== false, '5) texto enorme: 422, «error» con el motivo y nada guardado');
at_pt_guardar($pid3, ['estado' => 'generando', 'nota' => '']);
$largo = pt_plan_ia();
$largo['fases'][0]['descripcion'] = str_repeat('x', 1500);
$r = pt_pedir('POST', "/plan/{$pid3}/borrador", ['plan' => $largo, 'origen' => 'borrador']);
$f3 = at_pt_plan($pid3);
ok($r->get_status() === 200 && $f3->estado === 'borrador' && mb_strlen(at_pt_payload($f3)['fases'][0]['descripcion'] ?? '') === 600 && strpos((string) $f3->nota, 'Avisos del borrador: ') === 0 && in_array('Diseño y desarrollo: descripción: se acortó a 600 caracteres.', $r->get_data()['avisos'] ?? [], true), '5) texto largo: se acorta a 600 y el aviso queda en la nota y en la respuesta');

// 6) «Pedir cambios» no pisa los días de Luis (Review Focus 3).
$anterior = pt_editar_actividad(at_pt_payload(at_pt_plan($pid)), 'Construcción del sitio', ['dias_habiles' => 12, 'origen' => 'luis']);
at_pt_guardar($pid, ['payload' => $anterior, 'estado' => 'cambios', 'comentarios' => 'Alarga el diseño un día']);
$ia = pt_editar_actividad($anterior, 'Construcción del sitio', ['dias_habiles' => 2]);
$ia = pt_editar_actividad($ia, 'Maqueta de la portada', ['dias_habiles' => 4]);
$ia['fases'][0]['descripcion'] = 'Diseñamos y construimos tu sitio (revisado).';
$GLOBALS['pt_http'] = [];
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => $ia, 'origen' => 'cambios']);
$p6 = at_pt_payload(at_pt_plan($pid));
ok($r->get_status() === 200 && at_pt_plan($pid)->estado === 'borrador', '6) cambios aplicados: vuelve a «borrador»');
ok((pt_actividad($p6, 'Construcción del sitio')['dias_habiles'] ?? 0) === 12 && (pt_actividad($p6, 'Construcción del sitio')['origen'] ?? '') === 'luis', '6) los días que Luis editó no se pisan aunque la IA los cambie');
ok((pt_actividad($p6, 'Maqueta de la portada')['dias_habiles'] ?? 0) === 4 && (pt_actividad($p6, 'Maqueta de la portada')['origen'] ?? '') === 'ia', '6) en «cambios» no se reaplica la tabla: queda el día que pidió la IA, marcado «ia» (IA · revisar, no «luis»)');
ok(($p6['fases'][0]['descripcion'] ?? '') === 'Diseñamos y construimos tu sitio (revisado).' && count(pt_llamadas('render')) === 1, '6) el resto del cambio se guarda y se pide una vista previa nueva');

// 7) Un borrador tardío sobre un plan que ya tiene contenido no se aplica: nada de Luis se pisa (Review Focus 3 y 5).
at_pt_guardar($pid, ['estado' => 'error']);
$antes7 = at_pt_plan($pid)->payload;
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$p7 = at_pt_payload(at_pt_plan($pid));
ok($r->get_status() === 409 && at_pt_plan($pid)->payload === $antes7 && (pt_actividad($p7, 'Construcción del sitio')['dias_habiles'] ?? 0) === 12 && (pt_actividad($p7, 'Construcción del sitio')['origen'] ?? '') === 'luis', '7) borrador tardío sobre un plan con contenido: 409 y nada de Luis se pisa');

// 8) Plataforma grande: con los días de la IA cabe, con la tabla pasa el tope de 130 días hábiles (Review Focus 6).
$m8 = pt_marca();
$cli8 = pt_cliente($m8);
$pid8 = at_pt_crear_plan(pt_contrato($cli8['tech'], null));
$b8 = function (string $n, string $e): array {
	$a = [];
	foreach (array_keys(at_pt_duraciones_defecto()) as $s) {
		$a[] = ['nombre' => "{$n} {$s}", 'detalle' => '', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => $s, 'etapa' => $e];
	}
	return ['nombre' => $n, 'entrega' => true, 'entregable' => '', 'actividades' => $a];
};
$grande = ['proyecto' => '[PRUEBA] Proyecto grande', 'fases' => [
	['clave' => 'diseno_desarrollo', 'descripcion' => '', 'bloques' => [$b8('Diseño', 'diseno'), $b8('Desarrollo', 'desarrollo'), $b8('Pruebas', 'pruebas')]],
	['clave' => 'implementacion', 'descripcion' => '', 'bloques' => [$b8('Puesta en marcha', 'implementacion')]],
]];
ok(at_pt_validar_plan($grande)['ok'] === true, '8) con los días de la IA el plan cabe');
$r = pt_pedir('POST', "/plan/{$pid8}/borrador", ['plan' => $grande, 'origen' => 'borrador']);
$f8 = at_pt_plan($pid8);
ok($r->get_status() === 422 && $f8->estado === 'error' && $f8->payload === null && strpos((string) $f8->nota, 'El plan no cabe con la tabla de tiempos y los días que fijó Luis: ') === 0 && strpos((string) $f8->nota, '130') !== false, '8) la tabla lo lleva a 133 días hábiles: 422, «error» con el tope y nada guardado');

// 9) La IA manda su propio «Arranque»: se usa el fijo, con la entrega de insumos de la cláusula 4.2.
$m9 = pt_marca();
$cli9 = pt_cliente($m9);
$pid9 = at_pt_crear_plan(pt_contrato($cli9['tech'], null));
$con_arranque = pt_plan_ia();
array_unshift($con_arranque['fases'][0]['bloques'], ['nombre' => 'Arranque', 'entrega' => false, 'entregable' => '', 'actividades' => [
	['nombre' => 'Kickoff', 'detalle' => '', 'responsable' => 'ambos', 'dias_habiles' => 1, 'servicio' => '', 'etapa' => 'arranque'],
]]);
$r = pt_pedir('POST', "/plan/{$pid9}/borrador", ['plan' => $con_arranque, 'origen' => 'borrador']);
$f9 = at_pt_plan($pid9);
$p9 = at_pt_payload($f9);
$nombres9 = array_column($p9['fases'][0]['bloques'] ?? [], 'nombre');
ok($r->get_status() === 200 && array_column($p9['fases'][0]['bloques'][0]['actividades'] ?? [], 'nombre') === ['Reunión de inicio', 'Entrega de logo, textos y accesos'] && count(array_keys($nombres9, 'Arranque', true)) === 1 && strpos((string) $f9->nota, 'Avisos del borrador: La IA mandó su propio bloque «Arranque»') === 0, '9) «Arranque» de la IA: queda uno solo, el fijo (reunión de inicio y entrega de logo, textos y accesos), con el aviso');

// 10) «Pedir cambios» en que la IA renombra una actividad de Luis: sus días no vuelven solos, pero queda el aviso (Review Focus 3).
at_pt_guardar($pid, ['estado' => 'cambios']);
$ia10 = pt_editar_actividad(at_pt_payload(at_pt_plan($pid)), 'Construcción del sitio', ['nombre' => 'Construcción y pruebas del sitio', 'dias_habiles' => 6, 'origen' => 'ia']);
$r = pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => $ia10, 'origen' => 'cambios']);
$aviso10 = 'La IA renombró o quitó «Construcción del sitio», que tenía 12 días hábiles puestos por ti: revísala en el panel.';
ok($r->get_status() === 200 && in_array($aviso10, $r->get_data()['avisos'] ?? [], true) && strpos((string) at_pt_plan($pid)->nota, $aviso10) !== false && (pt_actividad(at_pt_payload(at_pt_plan($pid)), 'Construcción y pruebas del sitio')['origen'] ?? '') === 'ia', '10) la IA renombró una actividad de Luis: aviso en la respuesta y en la nota; la nueva queda «ia»');

// 11) El plan llega como el texto de la IA, con cerco ```json: la ruta se lo pasa tal cual a at_pt_validar_entrada().
$m11 = pt_marca();
$cli11 = pt_cliente($m11);
$pid11 = at_pt_crear_plan(pt_contrato($cli11['tech'], null));
$r = pt_pedir('POST', "/plan/{$pid11}/borrador", ['plan' => "```json\n" . wp_json_encode(pt_plan_ia()) . "\n```", 'origen' => 'borrador']);
ok($r->get_status() === 200 && at_pt_plan($pid11)->estado === 'borrador' && (pt_actividad(at_pt_payload(at_pt_plan($pid11)), 'Maqueta de la portada')['origen'] ?? '') === 'tabla', '11) plan en texto con cerco ```json: se acepta y se le aplica la tabla');

// 12) La IA no manda el nombre del proyecto: se usa el del contrato, sin aviso que ya no aplica.
$m12 = pt_marca();
$cli12 = pt_cliente($m12);
$pid12 = at_pt_crear_plan(pt_contrato($cli12['tech'], null));
$sin_nombre = pt_plan_ia();
unset($sin_nombre['proyecto']);
$r = pt_pedir('POST', "/plan/{$pid12}/borrador", ['plan' => $sin_nombre, 'origen' => 'borrador']);
ok($r->get_status() === 200 && (at_pt_payload(at_pt_plan($pid12))['proyecto'] ?? '') === '[PRUEBA] Sitio de una página' && !in_array('El plan no trae el nombre del proyecto.', $r->get_data()['avisos'] ?? [], true), '12) sin nombre de proyecto: el del contrato y sin el aviso de la validación');

fin();
