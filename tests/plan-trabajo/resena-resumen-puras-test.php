<?php
// Sin WordPress, credenciales ni red. Datos ficticios.
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/resena-puras.php';
$fallas = 0; $n = 0;
function ok($v, $m) { global $fallas, $n; $n++; if (!$v) { $fallas++; echo "FAIL $m\n"; } }
$tz = new DateTimeZone('America/Santiago');
$ahora = new DateTimeImmutable('2026-11-24 10:15:00', $tz);

// at_rg_resumen_desde
ok(at_rg_resumen_desde('2026-11-24T10:00:00-03:00', $ahora) instanceof DateTimeImmutable, 'desde válido');
foreach (['', 'ayer', '2026-11-24T11:00:00-03:00', '2026-11-22T10:00:00-03:00'] as $v) {
    ok(at_rg_resumen_desde($v, $ahora) === null, 'desde inválido: ' . $v);
}
ok(at_rg_resumen_desde('2026-11-24T10:19:00-03:00', $ahora) !== null, 'futuro dentro de 5 min');

// at_rg_resumen_canal
$r = fn($c, $e, $det = '') => at_rg_resumen_canal($c, ['estado' => $e, 'detalle' => $det], 'sin correo');
foreach ([
    ['correo','aceptado','✓','Aceptado por el servidor de correo'], ['correo','fallido','✗','No salió'],
    ['correo','en_curso','⏳','Sin confirmar'], ['correo','desconocido','⏳','Sin confirmar'],
    ['whatsapp','entregado','✓','Entregado'], ['whatsapp','leido','✓','Leído'],
    ['whatsapp','aceptado','⏳','Meta lo aceptó; sin confirmación de entrega'],
    ['whatsapp','en_curso','⏳','Meta lo aceptó; sin confirmación de entrega'],
    ['whatsapp','desconocido','⏳','Meta lo aceptó; sin confirmación de entrega'],
    ['whatsapp','fallido','✗','No se entregó'],
    ['correo','no_intentado','—','sin correo'], ['whatsapp','no_intentado','—','sin correo'],
] as [$c, $e, $s, $t]) {
    $x = $r($c, $e);
    ok($x['simbolo'] === $s && $x['texto'] === $t, "canal $c/$e");
}
ok($r('whatsapp', 'fallido', 'Meta: código 131049')['texto'] === 'Meta: código 131049', 'detalle ✗');
ok($r('correo', 'en_curso', 'esperando')['texto'] === 'esperando', 'detalle ⏳');
ok($r('correo', 'aceptado', 'x')['texto'] === 'Aceptado por el servidor de correo', 'detalle no pisa ✓');

// at_rg_motivo_sin_canal
$perm = ['autorizado' => true, 'origen' => 'formulario', 'fecha' => '2026-11-01', 'telefono' => '+56900000001'];
$cli = ['email' => 'persona@ejemplo.cl', 'telefono' => '+56900000001'];
$ctx = ['permiso' => $perm, 'bajas' => []];
ok(at_rg_motivo_sin_canal('correo', ['email' => '', 'telefono' => '+56900000001'], $ctx) === 'sin correo', 'sin correo');
ok(at_rg_motivo_sin_canal('whatsapp', ['email' => 'persona@ejemplo.cl', 'telefono' => ''], $ctx) === 'sin teléfono', 'sin teléfono');
ok(at_rg_motivo_sin_canal('whatsapp', $cli, ['permiso' => ['autorizado' => false], 'bajas' => []]) === 'sin permiso de WhatsApp', 'sin permiso');
$baja = ['alcance' => 'resenas', 'canal' => 'whatsapp', 'telefono' => '+56900000001'];
ok(at_rg_motivo_sin_canal('whatsapp', $cli, ['permiso' => $perm, 'bajas' => [$baja]]) === 'pidió la baja', 'baja whatsapp');
ok(at_rg_motivo_sin_canal('correo', $cli, ['permiso' => $perm, 'bajas' => [$baja]]) === 'no correspondía', 'baja de otro canal');
ok(at_rg_motivo_sin_canal('correo', $cli, $ctx) === 'no correspondía', 'sin motivo');

// at_rg_resumen
function canal_(string $e, string $det = '', string $at = '2026-11-24T10:00:00-03:00'): array { return ['estado' => $e, 'at' => $at, 'detalle' => $det, 'wamid' => '']; }
function intento_(string $id, string $at, string $co, string $wa, string $modo = 'real'): array {
    return ['id' => $id, 'modo' => $modo, 'origen' => 'manual', 'tipo' => 'invitacion', 'creado_at' => $at, 'correo' => canal_($co), 'whatsapp' => canal_($wa)];
}
$desde = new DateTimeImmutable('2026-11-24T10:00:00-03:00');
$dA = at_rg_vacio(); $dA['intentos'] = [
    intento_('a1', '2026-11-23T09:00:00-03:00', 'aceptado', 'leido'),
    intento_('a2', '2026-11-24T10:05:00-03:00', 'aceptado', 'leido'),
];
$dB = at_rg_vacio(); $dB['intentos'] = [
    intento_('b2', '2026-11-24T10:10:00-03:00', 'aceptado', 'aceptado'),
    intento_('b1', '2026-11-24T10:02:00-03:00', 'en_curso', 'no_intentado', 'prueba'),
];
$proy = [
    ['id' => 1, 'cliente' => ['nombre' => 'Ana Prueba', 'empresa' => 'Ejemplo SpA', 'email' => 'ana@ejemplo.cl', 'telefono' => '+56900000001'], 'ctx' => $ctx, 'datos' => $dA],
    ['id' => 2, 'cliente' => ['nombre' => '', 'empresa' => 'Otra Ltda', 'email' => 'otra@ejemplo.cl', 'telefono' => ''], 'ctx' => ['permiso' => [], 'bajas' => []], 'datos' => $dB],
];
$res = at_rg_resumen($proy, $desde);
ok($res['total'] === 3, 'total 3');
ok($res['ok'] === 1, 'ok 1: ' . $res['ok']);
ok(array_column($res['filas'], 'creado_at') === ['2026-11-24T10:02:00-03:00', '2026-11-24T10:05:00-03:00', '2026-11-24T10:10:00-03:00'], 'orden por creado_at');
[$f1, $f2, $f3] = $res['filas'];
ok($f1['modo'] === 'prueba' && $f1['cliente'] === 'Otra Ltda' && $f1['proyecto'] === 'Otra Ltda', 'fila prueba, cliente=empresa');
ok($f1['whatsapp']['simbolo'] === '—' && $f1['whatsapp']['texto'] === 'sin teléfono', 'whatsapp no intentado con motivo');
ok($f2['cliente'] === 'Ana Prueba' && $f2['proyecto'] === 'Ejemplo SpA' && $f2['proyecto_id'] === 1, 'fila 2 datos');
ok($f2['correo']['simbolo'] === '✓' && $f2['whatsapp']['simbolo'] === '✓', 'fila 2 ✓/✓');
ok($f3['whatsapp']['simbolo'] === '⏳', 'fila 3 ⏳');
ok(isset($f1['tipo']), 'tipo presente');
// tope de 50
$dC = at_rg_vacio();
for ($k = 0; $k < 60; $k++) { $dC['intentos'][] = intento_('c' . $k, sprintf('2026-11-24T10:%02d:00-03:00', $k % 15 + 1), 'aceptado', 'leido'); }
$cz = ['nombre' => 'Zeta', 'empresa' => '', 'email' => 'z@ejemplo.cl', 'telefono' => '+56900000001'];
$res2 = at_rg_resumen([['id' => 3, 'cliente' => $cz, 'ctx' => $ctx, 'datos' => $dC]], $desde);
ok(count($res2['filas']) === 50, 'tope 50 filas');
ok($res2['total'] === 60, 'total cuenta todos los intentos (60), solo filas se corta en 50');
ok($res2['filas'][0]['proyecto'] === '—', 'proyecto vacío = —');

// OK: ✗ no cuenta; ✓ con — sí cuenta
$dD = at_rg_vacio(); $dD['intentos'] = [
    intento_('d1', '2026-11-24T10:03:00-03:00', 'aceptado', 'fallido'),
    intento_('d2', '2026-11-24T10:04:00-03:00', 'aceptado', 'no_intentado'),
];
$res3 = at_rg_resumen([['id' => 4, 'cliente' => $cz, 'ctx' => ['permiso' => [], 'bajas' => []], 'datos' => $dD]], $desde);
ok($res3['total'] === 2 && $res3['ok'] === 1, 'fila con ✗ no cuenta como OK; ✓ y — sí: ' . $res3['ok']);

echo "$n comprobaciones, $fallas fallas
"; exit($fallas ? 1 : 0);
