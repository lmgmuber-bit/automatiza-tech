<?php
// PHP sin WordPress. Datos ficticios; no toca base ni red.
$archivo = __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/finanzas/puras.php';
if (is_file($archivo)) { require_once $archivo; }
$total = 0;
$fallas = 0;
function ok($valor, string $mensaje): void {
	global $total, $fallas;
	$total++;
	if (!$valor) { $fallas++; }
	echo ($valor ? 'ok ' : 'FALLA ') . $mensaje . "\n";
}
$funciones = ['tipos', 'monto', 'validar', 'referencia', 'tc_de_serie', 'resumen_mes', 'saldo', 'mismos_datos'];
foreach ($funciones as $f) { ok(function_exists('at_fin_' . $f), 'interfaz at_fin_' . $f); }
if ($fallas) { echo "$total pruebas, $fallas fallas\n"; exit(1); }

// tipos
ok(at_fin_tipos() === ['pago', 'recarga', 'consumo', 'ajuste'], 'tipos válidos');

// monto
ok(at_fin_monto('1,26') === 1.26, 'monto con coma');
ok(at_fin_monto('1.26') === 1.26, 'monto con punto');
ok(at_fin_monto(' 50 ') === 50.0, 'monto con espacios');
ok(at_fin_monto('abc') === null, 'monto texto es null');
ok(at_fin_monto('') === null && at_fin_monto(null) === null && at_fin_monto([]) === null, 'monto vacío, null y arreglo');
ok(at_fin_monto(0) === 0.0, 'monto cero es 0.0');
ok(at_fin_monto(7) === 7.0 && at_fin_monto(-5.5) === -5.5, 'monto número');
ok(at_fin_monto('-5') === -5.0, 'monto negativo en texto');

// validar
$hoy = '2026-10-21';
$base = ['tipo' => 'pago', 'fecha' => '2026-10-20', 'proveedor' => 'X', 'monto' => '10', 'moneda' => 'USD'];
$r = at_fin_validar($base, $hoy);
ok($r['ok'] === true && $r['errores'] === [], 'pago válido');
ok($r['datos']['monto'] === 10.0 && $r['datos']['moneda'] === 'USD' && $r['datos']['fecha'] === '2026-10-20', 'datos normalizados');
ok($r['datos']['cuenta'] === '' && $r['datos']['tipo_cambio'] === null && $r['datos']['gasto_id'] === null, 'opcionales vacíos');
foreach (['tipo', 'fecha', 'cuenta', 'proveedor', 'categoria', 'descripcion', 'proyecto', 'monto', 'moneda', 'tipo_cambio', 'gasto_id', 'referencia', 'registrado_por', 'notas'] as $k) { ok(array_key_exists($k, $r['datos']), 'datos trae ' . $k); }
ok(!at_fin_validar(array_merge($base, ['fecha' => '2026-10-22']), $hoy)['ok'], 'fecha futura');
ok(at_fin_validar(array_merge($base, ['fecha' => '2026-10-21']), $hoy)['ok'], 'fecha de hoy');
ok(!at_fin_validar(array_merge($base, ['fecha' => '2026-02-30']), $hoy)['ok'], 'fecha inexistente');
ok(!at_fin_validar(array_merge($base, ['fecha' => 'basura']), $hoy)['ok'], 'fecha basura');
ok(!at_fin_validar(array_merge($base, ['tipo' => 'regalo']), $hoy)['ok'], 'tipo inválido');
ok(!at_fin_validar(array_merge($base, ['tipo' => 'consumo']), $hoy)['ok'], 'consumo sin cuenta');
ok(at_fin_validar(array_merge($base, ['tipo' => 'consumo', 'cuenta' => 'higgsfield']), $hoy)['ok'], 'consumo con cuenta');
ok(!at_fin_validar(array_merge($base, ['tipo' => 'recarga']), $hoy)['ok'], 'recarga sin cuenta');
ok(!at_fin_validar(array_merge($base, ['tipo' => 'ajuste', 'monto' => '-5']), $hoy)['ok'], 'ajuste sin cuenta');
ok(at_fin_validar(array_merge($base, ['tipo' => 'ajuste', 'monto' => '-5', 'cuenta' => 'higgsfield']), $hoy)['ok'], 'ajuste negativo con cuenta');
ok(!at_fin_validar(array_merge($base, ['tipo' => 'ajuste', 'monto' => '0', 'cuenta' => 'higgsfield']), $hoy)['ok'], 'ajuste en cero');
ok(!at_fin_validar(array_merge($base, ['monto' => '-5']), $hoy)['ok'], 'pago negativo');
ok(!at_fin_validar(array_merge($base, ['monto' => 0]), $hoy)['ok'], 'pago en cero');
ok(!at_fin_validar(array_merge($base, ['monto' => 'abc']), $hoy)['ok'], 'monto no numérico');
ok(!at_fin_validar(array_merge($base, ['moneda' => 'EUR']), $hoy)['ok'], 'moneda EUR');
ok(at_fin_validar(array_merge($base, ['moneda' => 'clp']), $hoy)['datos']['moneda'] === 'CLP', 'moneda en minúsculas se normaliza');
ok(at_fin_validar(array_merge($base, ['tipo' => ' PAGO ']), $hoy)['datos']['tipo'] === 'pago', 'tipo se normaliza');
ok(at_fin_validar(array_merge($base, ['monto' => '1,26']), $hoy)['datos']['monto'] === 1.26, 'monto con coma en validar');
ok(!at_fin_validar([], $hoy)['ok'], 'vacío no es válido');
ok(count(at_fin_validar(['tipo' => 'regalo', 'monto' => '0', 'moneda' => 'EUR', 'fecha' => 'x'], $hoy)['errores']) >= 4, 'acumula errores');
$largo = at_fin_validar(array_merge($base, [
	'proveedor' => str_repeat('a', 200), 'categoria' => str_repeat('b', 100), 'descripcion' => str_repeat('c', 400),
	'proyecto' => str_repeat('d', 120), 'referencia' => str_repeat('e', 200), 'registrado_por' => str_repeat('f', 100),
]), $hoy)['datos'];
ok(strlen($largo['proveedor']) === 120 && strlen($largo['categoria']) === 60 && strlen($largo['descripcion']) === 255 && strlen($largo['proyecto']) === 80 && strlen($largo['referencia']) === 120 && strlen($largo['registrado_por']) === 60, 'recorta largos');
ok(mb_strlen(at_fin_validar(array_merge($base, ['proveedor' => str_repeat('ñ', 200)]), $hoy)['datos']['proveedor']) === 120, 'recorta por caracteres, no bytes');
$opc = at_fin_validar(array_merge($base, ['tipo_cambio' => '981,5', 'gasto_id' => '7', 'cuenta' => ' Higgsfield ']), $hoy)['datos'];
ok($opc['tipo_cambio'] === 981.5 && $opc['gasto_id'] === 7 && $opc['cuenta'] === 'higgsfield', 'tipo de cambio, gasto_id y cuenta normalizados');
ok(!at_fin_validar(array_merge($base, ['tipo_cambio' => 'abc']), $hoy)['ok'] && !at_fin_validar(array_merge($base, ['tipo_cambio' => '0']), $hoy)['ok'], 'tipo de cambio dado pero inválido');
ok(at_fin_validar(array_merge($base, ['tipo_cambio' => '']), $hoy)['datos']['tipo_cambio'] === null, 'tipo de cambio vacío es null');
ok(!at_fin_validar(array_merge($base, ['tipo' => 'recarga', 'cuenta' => 'Mi cuenta!']), $hoy)['ok'], 'cuenta con caracteres no válidos');

// referencia
$d1 = at_fin_validar($base, $hoy)['datos'];
$d2 = at_fin_validar($base, $hoy)['datos'];
$d3 = at_fin_validar(array_merge($base, ['monto' => '11']), $hoy)['datos'];
ok(at_fin_referencia($d1) === at_fin_referencia($d2), 'referencia estable');
ok(at_fin_referencia($d1) !== at_fin_referencia($d3), 'referencia cambia con el monto');
ok(preg_match('/^auto-[0-9a-f]{40}$/D', at_fin_referencia($d1)) === 1, 'referencia auto- + sha1');
ok(at_fin_referencia($d1) === 'auto-' . sha1('pago|2026-10-20||X|10.0000|USD|'), 'referencia exacta');
ok(strlen(at_fin_referencia($d1)) <= 120, 'referencia cabe en 120');

// tc de serie
$serie = ['2026-10-16' => 980.5, '2026-10-17' => 981.0];
ok(at_fin_tc_de_serie('2026-10-17', $serie) === 981.0, 'tc del mismo día');
ok(at_fin_tc_de_serie('2026-10-18', $serie) === 981.0, 'tc sábado usa el viernes');
ok(at_fin_tc_de_serie('2026-10-16', $serie) === 980.5, 'tc ignora fechas posteriores');
ok(at_fin_tc_de_serie('2026-10-24', $serie) === 981.0, 'tc 7 días atrás cuenta');
ok(at_fin_tc_de_serie('2026-10-25', $serie) === null, 'tc 8 días atrás no cuenta');
ok(at_fin_tc_de_serie('2026-10-30', $serie) === null, 'tc fuera de ventana');
ok(at_fin_tc_de_serie('2026-10-15', $serie) === null, 'tc antes de la serie');
ok(at_fin_tc_de_serie('2026-10-18', []) === null, 'tc con serie vacía');
ok(at_fin_tc_de_serie('basura', $serie) === null, 'tc con fecha inválida');

// resumen del mes
$movs = [
	['tipo' => 'pago', 'categoria' => 'IA', 'proveedor' => 'OpenAI', 'monto_clp' => 1000, 'tc_estado' => 'ok'],
	['tipo' => 'recarga', 'categoria' => 'Video', 'proveedor' => 'Higgsfield', 'monto_clp' => 2000, 'tc_estado' => 'ok'],
	['tipo' => 'consumo', 'categoria' => 'Video', 'proveedor' => 'Higgsfield', 'monto_clp' => 500, 'tc_estado' => 'ok'],
	['tipo' => 'ajuste', 'categoria' => 'Video', 'proveedor' => 'Higgsfield', 'monto_clp' => 300, 'tc_estado' => 'ok'],
];
$res = at_fin_resumen_mes($movs);
ok($res['total_clp'] === 3000, 'total solo pago y recarga');
ok($res['por_categoria'] === ['IA' => 1000, 'Video' => 2000], 'por categoría suma igual');
ok($res['por_proveedor'] === ['OpenAI' => 1000, 'Higgsfield' => 2000], 'por proveedor suma igual');
ok($res['pendientes'] === 0, 'sin pendientes');
$movs[] = ['tipo' => 'pago', 'categoria' => 'IA', 'proveedor' => 'OpenAI', 'monto_clp' => null, 'tc_estado' => 'pendiente'];
$res = at_fin_resumen_mes($movs);
ok($res['pendientes'] === 1 && $res['total_clp'] === 3000, 'pendiente se cuenta y no suma');
$vacio = at_fin_resumen_mes([]);
ok($vacio === ['total_clp' => 0, 'por_categoria' => [], 'por_proveedor' => [], 'pendientes' => 0], 'resumen vacío');
$res = at_fin_resumen_mes([['tipo' => 'pago', 'monto_clp' => '1500', 'tc_estado' => 'manual'], ['tipo' => 'pago', 'categoria' => '', 'proveedor' => '', 'monto_clp' => 100]]);
ok($res['total_clp'] === 1600 && $res['por_categoria'] === ['Sin categoría' => 1600] && $res['por_proveedor'] === ['Sin proveedor' => 1600], 'montos en texto y sin categoría ni proveedor');

// saldo
$sal = at_fin_saldo([
	['tipo' => 'recarga', 'fecha' => '2026-10-01', 'monto' => 50],
	['tipo' => 'consumo', 'fecha' => '2026-10-05', 'monto' => 12],
	['tipo' => 'ajuste', 'fecha' => '2026-10-06', 'monto' => -3],
]);
ok($sal['recargado'] === 50.0 && $sal['consumido'] === 12.0 && $sal['ajustes'] === -3.0, 'saldo suma por tipo');
ok($sal['saldo'] === 35.0, 'saldo 50 + (-3) - 12');
ok($sal['ultima_recarga'] === ['fecha' => '2026-10-01', 'monto' => 50.0], 'última recarga');
ok($sal['usado_desde_recarga'] === 12.0 && $sal['pct_usado'] === 24.0, 'uso de la recarga 24 %');
$sal = at_fin_saldo([['tipo' => 'consumo', 'fecha' => '2026-10-05', 'monto' => 12], ['tipo' => 'consumo', 'fecha' => '2026-10-06', 'monto' => 8]]);
ok($sal['saldo'] === -20.0 && $sal['ultima_recarga'] === null && $sal['pct_usado'] === null && $sal['usado_desde_recarga'] === 0.0, 'solo consumos: saldo negativo, sin recarga');
$sal = at_fin_saldo([]);
ok($sal['saldo'] === 0.0 && $sal['ultima_recarga'] === null && $sal['pct_usado'] === null, 'saldo vacío');
$sal = at_fin_saldo([
	['tipo' => 'consumo', 'fecha' => '2026-09-20', 'monto' => 30],
	['tipo' => 'recarga', 'fecha' => '2026-09-01', 'monto' => 100],
	['tipo' => 'recarga', 'fecha' => '2026-10-01', 'monto' => 40],
	['tipo' => 'consumo', 'fecha' => '2026-10-01', 'monto' => 10],
	['tipo' => 'consumo', 'fecha' => '2026-10-10', 'monto' => 10],
]);
ok($sal['ultima_recarga'] === ['fecha' => '2026-10-01', 'monto' => 40.0], 'última recarga por fecha aunque venga desordenada');
ok($sal['usado_desde_recarga'] === 20.0 && $sal['pct_usado'] === 50.0, 'solo cuenta consumos desde la última recarga');
ok($sal['saldo'] === 90.0 && $sal['recargado'] === 140.0 && $sal['consumido'] === 50.0, 'saldo con dos recargas');
$sal = at_fin_saldo([['tipo' => 'recarga', 'fecha' => '2026-10-01', 'monto' => 10.1], ['tipo' => 'consumo', 'fecha' => '2026-10-02', 'monto' => 3.3]]);
ok($sal['saldo'] === 6.8, 'saldo sin ruido de coma flotante');

// mismos datos
ok(at_fin_mismos_datos($d1, $d2), 'mismos datos iguales');
ok(!at_fin_mismos_datos($d1, $d3), 'distinto monto');
ok(!at_fin_mismos_datos($d1, array_merge($d1, ['fecha' => '2026-10-19'])), 'distinta fecha');
ok(!at_fin_mismos_datos($d1, array_merge($d1, ['tipo' => 'recarga'])), 'distinto tipo');
ok(!at_fin_mismos_datos($d1, array_merge($d1, ['moneda' => 'CLP'])), 'distinta moneda');
ok(!at_fin_mismos_datos($d1, array_merge($d1, ['descripcion' => 'otra'])), 'distinta descripción');
ok(!at_fin_mismos_datos($d1, array_merge($d1, ['cuenta' => 'otra'])), 'distinta cuenta');
ok(!at_fin_mismos_datos($d1, array_merge($d1, ['proveedor' => 'Y'])), 'distinto proveedor');
ok(at_fin_mismos_datos($d1, array_merge($d1, ['notas' => 'otra', 'registrado_por' => 'panel', 'referencia' => 'x'])), 'las notas y quién registra no cuentan');
ok(at_fin_mismos_datos(['monto' => '10'] + $d1, $d1), 'monto en texto igual al número');

echo "$total pruebas, $fallas fallas\n";
exit($fallas ? 1 : 0);
