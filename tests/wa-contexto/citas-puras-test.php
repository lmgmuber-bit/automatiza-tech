<?php
// Citas futuras del número para cancelar o reprogramar con botones. Uso: php tests/wa-contexto/citas-puras-test.php
define('AT_WA_PURAS_TEST', true);
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/citas-puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

const AHORA = '2026-11-03 12:00';
$diag = function (int $id, string $fecha, string $hora, string $estado = 'active') {
	return ['id' => $id, 'scheduled_date' => $fecha, 'scheduled_time' => $hora . ':00', 'status' => $estado,
		'name' => 'Ana Pérez', 'email' => 'ana@prueba.cl', 'empresa' => 'Empresa Prueba'];
};
$seg = function (int $id, string $fecha, string $hora, string $estado = 'scheduled', string $vinculo = '') {
	return ['id' => $id, 'meeting_date' => $fecha, 'meeting_time' => $hora . ':00', 'status' => $estado,
		'client_name' => 'Ana Pérez', 'client_email' => 'ana.seg@prueba.cl', 'company' => 'Empresa Prueba',
		'contrato_id' => '5', 'propuesta_id' => null, 'vinculo' => $vinculo,
		'notes' => 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.', 'meeting_subject' => 'Seguimiento: Agenda'];
};
$ids = function (array $citas) { return array_map(function ($c) { return $c['tipo'][0] . $c['id']; }, $citas); };

// Etiqueta
ok(at_wa_cita_etiqueta('diagnostico', '2026-11-03 17:00', '') === 'Diagnóstico gratis · mar 03-nov · 17:00', 'etiqueta diagnóstico');
ok(at_wa_cita_etiqueta('seguimiento', '2026-11-03 17:00', 'Propuesta: Sitio web') === 'Seguimiento: Propuesta: Sitio web · mar 03-nov · 17:00', 'etiqueta seguimiento con vínculo');
ok(at_wa_cita_etiqueta('seguimiento', '2026-11-03 17:00', '') === 'Seguimiento · mar 03-nov · 17:00', 'etiqueta seguimiento sin vínculo');
ok(at_wa_cita_etiqueta('diagnostico', '2026-12-07 09:30', '') === 'Diagnóstico gratis · lun 07-dic · 09:30', 'etiqueta otro día y mes');

// Solo futuras: hoy temprano fuera, hoy tarde dentro
$r = at_wa_citas_armar([$diag(1, '2026-11-03', '10:00'), $diag(2, '2026-11-03', '17:00')], [], AHORA);
ok($ids($r) === ['d2'], 'hoy temprano fuera, hoy tarde dentro');
ok($ids(at_wa_citas_armar([$diag(3, '2026-11-03', '12:00')], [], AHORA)) === [], 'una cita a esta misma hora ya no se ofrece');

// Inactivas fuera
$r = at_wa_citas_armar([$diag(4, '2026-11-05', '10:00', 'cancelled'), $diag(5, '2026-11-05', '11:00', 'no_show'), $diag(6, '2026-11-05', '12:00', '')],
	[$seg(7, '2026-11-05', '10:00', 'cancelled'), $seg(8, '2026-11-05', '11:00', 'completed'), $seg(9, '2026-11-05', '13:00', 'confirmed')], AHORA);
ok($ids($r) === ['d6', 's9'], 'canceladas, completadas y no_show fuera');

// Orden mezclando tipos
$r = at_wa_citas_armar([$diag(10, '2026-11-10', '10:00'), $diag(11, '2026-11-04', '09:00')], [$seg(12, '2026-11-06', '15:00'), $seg(13, '2026-11-04', '08:00')], AHORA);
ok($ids($r) === ['s13', 'd11', 's12', 'd10'], 'orden de la más próxima');

// Tope 10
$muchas = [];
for ($i = 1; $i <= 12; $i++) {
	$muchas[] = $diag(100 + $i, '2026-11-' . sprintf('%02d', 4 + $i), '10:00');
}
$r = at_wa_citas_armar($muchas, [], AHORA);
ok(count($r) === 10 && $r[0]['id'] === 101 && $r[9]['id'] === 110, 'tope 10, las más próximas');

// Datos de la cita vieja
$r = at_wa_citas_armar([$diag(20, '2026-11-08', '10:00')], [$seg(21, '2026-11-09', '10:00', 'scheduled', 'Proyecto: Agenda')], AHORA);
$d = $r[0];
$s = $r[1];
ok($d['tipo'] === 'diagnostico' && $d['id'] === 20 && $d['cuando'] === '2026-11-08 10:00' && $d['correo'] === 'ana@prueba.cl'
	&& $d['nombre'] === 'Ana Pérez' && $d['empresa'] === 'Empresa Prueba' && $d['contrato_id'] === 0 && $d['propuesta_id'] === 0, 'datos de la cita vieja (diagnóstico)');
ok($s['tipo'] === 'seguimiento' && $s['correo'] === 'ana.seg@prueba.cl' && $s['contrato_id'] === 5 && $s['propuesta_id'] === 0
	&& $s['etiqueta'] === 'Seguimiento: Proyecto: Agenda · lun 09-nov · 10:00', 'datos de la cita vieja (seguimiento, ids enteros)');

// B-CITAS-06: el seguimiento lleva la nota y el asunto de la reunión vieja (la agenda del plan busca «Plan de trabajo <código>»)
ok($s['notas'] === 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.' && $s['asunto'] === 'Seguimiento: Agenda', 'el seguimiento conserva nota y asunto');
ok($d['notas'] === '' && $d['asunto'] === '', 'el diagnóstico no trae nota ni asunto');

// Fecha rota no rompe
ok(at_wa_citas_armar([$diag(30, '', '')], [], AHORA) === [], 'una fila sin fecha no se ofrece');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
