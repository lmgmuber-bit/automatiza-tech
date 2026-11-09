<?php
// Regla nuevo, prospecto o cliente del asistente de WhatsApp. Uso: php tests/wa-contexto/puras-test.php
define('AT_WA_PURAS_TEST', true);
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

const AHORA = '2026-11-10 12:00';
$agenda = function (string $correo) { return ['nombre_completo' => 'Cliente Prueba', 'correo' => $correo, 'empresa' => 'Empresa Prueba']; };
$ficha = function (array $extra = []) use ($agenda) {
	return array_merge(['clave' => 'crm:1', 'nombre' => 'julio pérez', 'agenda' => $agenda('a@prueba.cl'), 'contratos' => [], 'propuestas' => [], 'diagnosticos' => []], $extra);
};
$contrato = function (int $id, string $tipo, string $estado, bool $firmado = true, string $codigo = '') {
	return ['id' => $id, 'tipo' => $tipo, 'estado' => $estado, 'firmado' => $firmado, 'nombre' => "Contrato $id", 'plan_codigo' => $codigo];
};
$propuesta = function (int $id, string $estado) { return ['id' => $id, 'estado' => $estado, 'nombre' => "Propuesta $id"]; };

// Teléfono: últimos 9 dígitos
ok(at_wa_ultimos9('+56 9 1234 5678') === '912345678', 'teléfono +56 9 con espacios');
ok(at_wa_ultimos9('56912345678') === '912345678' && at_wa_ultimos9('912345678') === '912345678', 'teléfono 569… y 9…');
ok(at_wa_ultimos9('+58 414-123-4567') === '141234567', 'teléfono de Venezuela con guiones');
ok(at_wa_ultimos9('+1 (804) 298-0236') === '042980236', 'teléfono de EE. UU. con paréntesis');
ok(at_wa_ultimos9('1234') === '' && at_wa_ultimos9('') === '', 'menos de 9 dígitos no se busca');

// Primer nombre
ok(at_wa_primer_nombre('  maría josé González ') === 'María', 'primer nombre con mayúscula inicial');
ok(at_wa_primer_nombre('') === '', 'primer nombre vacío');

// Código del plan en el texto del botón de WhatsApp
ok(at_wa_codigo_de_texto('Hola, quiero agendar (código Ab3dE6gH9jK1)') === 'Ab3dE6gH9jK1', 'código con tilde');
ok(at_wa_codigo_de_texto('Seguimiento (Codigo Ab3dE6gH9jK1) gracias') === 'Ab3dE6gH9jK1', 'código sin tilde y con mayúscula');
ok(at_wa_codigo_de_texto('quiero agendar') === '' && at_wa_codigo_de_texto('(código corto)') === '', 'sin código o código inválido');

// Nombre de un contrato
ok(at_wa_nombre_contrato('servicios', ['servicios_contratados' => "- Sitio web\n- Bot"], 'Configurador 3D', '12') === 'Configurador 3D', 'contrato: manda el proyecto del plan');
ok(at_wa_nombre_contrato('servicios', ['servicios_contratados' => "- Sitio web con agenda\n- Bot"], '', '12') === 'Sitio web con agenda', 'contrato de servicios sin plan: primer servicio');
ok(at_wa_nombre_contrato('soporte', [], '', '9') === 'Soporte técnico' && at_wa_nombre_contrato('sla', [], '', '9') === 'Soporte técnico', 'contrato de soporte o sla');
ok(at_wa_nombre_contrato('servicios', [], '', '14') === 'Contrato 14', 'contrato sin datos: su número');

// Nombre de una propuesta
ok(at_wa_nombre_propuesta('Agenda y recordatorios', 'Spa Prueba', '2026-09-24 10:00:00') === 'Agenda y recordatorios', 'propuesta: servicio');
ok(at_wa_nombre_propuesta('', 'Spa Prueba', '2026-09-24 10:00:00') === 'Spa Prueba', 'propuesta: empresa');
ok(at_wa_nombre_propuesta('', '', '2026-09-24 10:00:00') === 'Propuesta del 24-09', 'propuesta: fecha');

// Clasificación
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(5, 'servicios', 'signed', true, 'Ab3dE6gH9jK1')]])], AHORA);
ok($r['situacion'] === 'cliente' && count($r['opciones']) === 1 && $r['opciones'][0]['tipo'] === 'proyecto' && $r['opciones'][0]['id'] === 5, 'contrato de servicios firmado: cliente con un proyecto');
ok($r['nombre'] === 'Julio' && $r['opciones'][0]['plan_codigo'] === 'Ab3dE6gH9jK1' && $r['opciones'][0]['agenda']['correo'] === 'a@prueba.cl', 'cliente: primer nombre, código y agenda de su ficha');
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(5, 'servicios', 'expired')]])], AHORA);
ok($r['situacion'] === 'cliente' && $r['opciones'] === [], 'contrato vencido: sigue cliente, sin proyecto para agendar');
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(6, 'nda', 'signed')]])], AHORA);
ok($r['situacion'] === 'nuevo', 'un NDA firmado solo no hace cliente');
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(7, 'servicios', 'cancelled')]])], AHORA);
ok($r['situacion'] === 'nuevo', 'contrato cancelado no hace cliente');
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(8, 'servicios', 'sent', false)]])], AHORA);
ok($r['situacion'] === 'nuevo', 'contrato enviado sin firmar no hace cliente');
$r = at_wa_clasificar([$ficha(['propuestas' => [$propuesta(20, 'pending')]])], AHORA);
ok($r['situacion'] === 'prospecto' && $r['opciones'] === [], 'propuesta pending: prospecto sin opciones');
$r = at_wa_clasificar([$ficha(['propuestas' => [$propuesta(21, 'sent')]])], AHORA);
ok($r['situacion'] === 'prospecto' && count($r['opciones']) === 1 && $r['opciones'][0]['tipo'] === 'propuesta', 'propuesta enviada: prospecto con una opción');
$r = at_wa_clasificar([$ficha(['propuestas' => [$propuesta(22, 'evaluando')]])], AHORA);
ok(count($r['opciones']) === 1, 'propuesta en evaluación es abierta');
$r = at_wa_clasificar([$ficha(['propuestas' => [$propuesta(23, 'aceptada')]])], AHORA);
ok($r['situacion'] === 'prospecto' && $r['opciones'] === [], 'propuesta aceptada: prospecto sin opciones');
$r = at_wa_clasificar([$ficha(['propuestas' => [$propuesta(24, 'borrador'), $propuesta(25, 'error')]])], AHORA);
ok($r['situacion'] === 'nuevo', 'propuesta en borrador o con error no cuenta');
$r = at_wa_clasificar([$ficha(['diagnosticos' => [['cuando' => '2026-11-01 10:00', 'estado' => 'active', 'asistencia' => '']]])], AHORA);
ok($r['situacion'] === 'prospecto', 'diagnóstico ya realizado: prospecto');
$r = at_wa_clasificar([$ficha(['diagnosticos' => [['cuando' => '2026-11-01 10:00', 'estado' => 'active', 'asistencia' => 'no_show']]])], AHORA);
ok($r['situacion'] === 'nuevo', 'diagnóstico al que no llegó: nuevo');
$r = at_wa_clasificar([$ficha(['diagnosticos' => [['cuando' => '2026-11-01 10:00', 'estado' => 'cancelled', 'asistencia' => '']]])], AHORA);
ok($r['situacion'] === 'nuevo', 'diagnóstico cancelado: nuevo');
$r = at_wa_clasificar([$ficha(['diagnosticos' => [['cuando' => '2026-11-14 16:00', 'estado' => 'active', 'asistencia' => ''], ['cuando' => '2026-11-12 10:00', 'estado' => 'active', 'asistencia' => ''], ['cuando' => '2026-11-11 09:00', 'estado' => 'cancelled', 'asistencia' => '']]])], AHORA);
ok($r['situacion'] === 'nuevo' && $r['diagnostico_proximo'] === '2026-11-12 10:00', 'diagnóstico futuro: nuevo con el más cercano no cancelado');
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(5, 'servicios', 'signed'), $contrato(9, 'soporte', 'signed')], 'propuestas' => [$propuesta(21, 'sent'), $propuesta(26, 'evaluando')]])], AHORA);
ok($r['situacion'] === 'cliente' && array_map(function ($o) { return $o['tipo'] . $o['id']; }, $r['opciones']) === ['proyecto9', 'proyecto5', 'propuesta26', 'propuesta21'], 'cliente con propuestas abiertas: proyectos primero y por id descendente');
$otra = $ficha(['clave' => 'crm:2', 'nombre' => 'Ana', 'agenda' => $agenda('b@prueba.cl'), 'propuestas' => [$propuesta(30, 'sent')]]);
$r = at_wa_clasificar([$ficha(['contratos' => [$contrato(5, 'servicios', 'signed')]]), $otra], AHORA);
ok($r['situacion'] === 'cliente' && $r['nombre'] === '' && count($r['opciones']) === 2, 'número en dos fichas: la situación más alta, sin nombre, opciones de las dos');
ok($r['opciones'][0]['agenda']['correo'] === 'a@prueba.cl' && $r['opciones'][1]['agenda']['correo'] === 'b@prueba.cl', 'número en dos fichas: cada opción con la agenda de su ficha');
$r = at_wa_clasificar([], AHORA);
ok($r === ['situacion' => 'nuevo', 'nombre' => '', 'opciones' => [], 'diagnostico_proximo' => ''], 'sin fichas: nuevo vacío');

// Elegida por el código del plan
$ops = at_wa_clasificar([$ficha(['contratos' => [$contrato(5, 'servicios', 'signed', true, 'Ab3dE6gH9jK1')]])], AHORA)['opciones'];
ok((at_wa_elegida($ops, 'Ab3dE6gH9jK1')['id'] ?? 0) === 5, 'código exacto: elige ese proyecto');
ok(at_wa_elegida($ops, 'ab3de6gh9jk1') === null && at_wa_elegida($ops, 'Zz9zZ9zZ9zZ9') === null && at_wa_elegida($ops, '') === null, 'código con otras mayúsculas, ajeno o vacío: nada');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
