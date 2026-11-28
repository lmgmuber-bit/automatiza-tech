<?php
// Correr: php tests/plan/cronograma-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Feriados de prueba: lunes 12-oct, sábado 31-oct y domingo 1-nov de 2026, martes 8-dic, viernes 25-dic y viernes 1-ene-2027.
$fer = ['2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08', '2026-12-25', '2027-01-01'];
$a = fn(string $n, string $r, int $d, bool $paralela = false) => ['nombre' => $n, 'responsable' => $r, 'dias_habiles' => $d, 'en_paralelo' => $paralela];
function fechas(array $plan, string $nombre): array {
	foreach ($plan['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			foreach ($b['actividades'] as $x) {
				if ($x['nombre'] === $nombre) {
					return [$x['desde'], $x['hasta']];
				}
			}
		}
	}
	return [];
}

// Semanas de calendario (lunes a domingo)
ok(at_pt_semanas('2026-10-05', '2026-10-09') === 1 && at_pt_semanas('2026-10-05', '2026-10-05') === 1, 'lunes a viernes de la misma semana: 1');
ok(at_pt_semanas('2026-10-05', '2026-10-12') === 2 && at_pt_semanas('2026-10-09', '2026-10-12') === 2, 'de un viernes al lunes siguiente: 2 semanas');
ok(at_pt_semanas('2026-10-04', '2026-10-05') === 2, 'domingo 4 y lunes 5-oct son semanas distintas');
ok(at_pt_semanas('2026-12-28', '2027-01-04') === 2 && at_pt_semanas('2026-10-05', '2026-11-27') === 8, 'cambio de año; 5-oct a 27-nov: 8 semanas');
ok(at_pt_semanas('2026-10-09', '2026-10-05') === 0 && at_pt_semanas('basura', '2026-10-05') === 0, 'rango al revés o fecha inválida: 0');

// Sitio web: firma martes 29-sep-2026 -> inicio lunes 5-oct-2026, con el feriado del 12-oct dentro del bloque Diseño.
$plan = at_pt_validar_plan([
	'proyecto' => '[PRUEBA] Sitio web de Cliente Prueba',
	'fases'    => [
		['clave' => 'diseno_desarrollo', 'bloques' => [
			['nombre' => 'Diseño', 'entrega' => true, 'actividades' => [$a('Propuesta de diseño', 'at', 3), $a('Ajustes de diseño', 'at', 2)]],
			['nombre' => 'Desarrollo', 'actividades' => [$a('Maquetación', 'at', 5), $a('Formulario de contacto', 'at', 2, true), $a('Carga de textos', 'cliente', 1)]],
			['nombre' => 'Pruebas y revisión', 'entrega' => true, 'actividades' => [$a('Pruebas en celular y escritorio', 'at', 2)]],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [$a('Publicación del sitio', 'at', 1), $a('Capacitación', 'ambos', 1)]],
		]],
		['clave' => 'soporte', 'bloques' => [
			['nombre' => 'Garantía', 'actividades' => [$a('Ajustes menores', 'at', 10)]],
		]],
	],
	'hitos' => [
		['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño'],
		['nombre' => 'Sitio aprobado', 'despues_de' => 'Pruebas y revisión'],
		['nombre' => 'Insumos recibidos', 'despues_de' => 'Arranque'],
	],
])['plan'];
$inicio = at_pt_inicio_por_defecto('2026-09-29', $fer);
$c = at_pt_calcular_fechas($plan, $inicio, $fer);
ok($inicio === '2026-10-05' && $c['fecha_inicio'] === '2026-10-05' && $c['cronograma']['inicio'] === '2026-10-05', 'firma martes 29-sep -> el plan parte el lunes 5-oct');
ok(fechas($c, 'Reunión de inicio') === ['2026-10-05', '2026-10-05'] && fechas($c, 'Entrega de logo, textos y accesos') === ['2026-10-06', '2026-10-08'], 'Arranque: reunión el 5-oct; insumos del 6 al 8-oct');
ok(fechas($c, 'Propuesta de diseño') === ['2026-10-09', '2026-10-14'], '3 días desde el viernes 9-oct saltan el feriado del lunes 12: 9, 13 y 14-oct');
ok(fechas($c, 'Ajustes de diseño') === ['2026-10-15', '2026-10-16'], 'la siguiente actividad parte el hábil siguiente: 15 y 16-oct');
ok(fechas($c, 'Maquetación') === ['2026-10-26', '2026-10-30'], 'después de la revisión de Diseño (19 al 23-oct) sigue Desarrollo el lunes 26-oct');
ok(fechas($c, 'Formulario de contacto') === ['2026-10-26', '2026-10-27'], 'en paralelo: parte el mismo día que la anterior (26-oct)');
ok(fechas($c, 'Carga de textos') === ['2026-11-02', '2026-11-02'], 'la siguiente parte después del mayor «hasta» del bloque (30-oct), saltando el sábado 31 y el domingo 1-nov feriados');
ok(fechas($c, 'Pruebas en celular y escritorio') === ['2026-11-03', '2026-11-04'] && fechas($c, 'Publicación del sitio') === ['2026-11-12', '2026-11-12'], 'Pruebas 3 y 4-nov; revisión del 5 al 11-nov; Implementación parte el 12-nov');
ok(fechas($c, 'Capacitación') === ['2026-11-13', '2026-11-13'] && fechas($c, 'Ajustes menores') === ['2026-11-16', '2026-11-27'], 'Capacitación el 13-nov; Soporte del 16 al 27-nov (10 días hábiles)');
$barras = array_map(fn($b) => [$b['etiqueta'], $b['tipo'], $b['responsable'], $b['desde'], $b['hasta']], $c['cronograma']['barras']);
ok($barras === [
	['Arranque', 'trabajo', 'ambos', '2026-10-05', '2026-10-08'],
	['Diseño', 'trabajo', 'at', '2026-10-09', '2026-10-16'],
	['Tu revisión', 'revision', 'cliente', '2026-10-19', '2026-10-23'],
	['Desarrollo', 'trabajo', 'ambos', '2026-10-26', '2026-11-02'],
	['Pruebas y revisión', 'trabajo', 'at', '2026-11-03', '2026-11-04'],
	['Tu revisión', 'revision', 'cliente', '2026-11-05', '2026-11-11'],
	['Puesta en marcha', 'trabajo', 'ambos', '2026-11-12', '2026-11-13'],
	['Garantía', 'trabajo', 'at', '2026-11-16', '2026-11-27'],
], 'barras: una por bloque (responsable común o ambos) y «Tu revisión» de 5 días hábiles tras cada bloque con entrega');
ok(array_column($c['cronograma']['barras'], 'fase') === ['diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'diseno_desarrollo', 'implementacion', 'soporte'], 'cada barra dice su fase');
ok(array_keys($c['cronograma']['barras'][0]) === ['fase', 'etiqueta', 'tipo', 'responsable', 'desde', 'hasta'], 'claves de una barra, en orden');
ok($c['cronograma']['hitos'] === [
	['nombre' => 'Insumos recibidos', 'fecha' => '2026-10-08'],
	['nombre' => 'Diseño aprobado', 'fecha' => '2026-10-23'],
	['nombre' => 'Sitio aprobado', 'fecha' => '2026-11-11'],
	['nombre' => 'Entrega estimada', 'fecha' => '2026-11-13'],
], 'hitos en orden de fecha: fin de la revisión si el bloque tiene entrega, su fin si no; «Entrega estimada» = fin del último bloque de Implementación');
ok($c['hitos'] === [
	['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño', 'fecha' => '2026-10-23'],
	['nombre' => 'Sitio aprobado', 'despues_de' => 'Pruebas y revisión', 'fecha' => '2026-11-11'],
	['nombre' => 'Insumos recibidos', 'despues_de' => 'Arranque', 'fecha' => '2026-10-08'],
], 'los hitos del plan reciben su fecha');
ok($c['cronograma']['fin'] === '2026-11-27' && $c['cronograma']['semanas'] === 8, 'cronograma del 5-oct al 27-nov: 8 semanas de calendario');
ok(array_keys($c['cronograma']) === ['inicio', 'fin', 'semanas', 'barras', 'hitos'], 'claves del cronograma, en orden');
ok(at_pt_calcular_fechas($c, $inicio, $fer) === $c, 'recalcular da lo mismo');

// Review Focus 1: inicio en fin de semana o feriado -> el plan parte el hábil siguiente.
$sabado = at_pt_calcular_fechas($plan, '2026-10-10', $fer);
ok($sabado['fecha_inicio'] === '2026-10-13' && fechas($sabado, 'Reunión de inicio') === ['2026-10-13', '2026-10-13'], 'inicio sábado 10-oct (y lunes 12 feriado) -> parte el martes 13-oct');
ok(at_pt_calcular_fechas($plan, '2026-10-12', $fer)['cronograma']['inicio'] === '2026-10-13' && at_pt_calcular_fechas($plan, '2026-10-12', [])['cronograma']['inicio'] === '2026-10-12', 'inicio en feriado -> el hábil siguiente; sin ese feriado, ese mismo día');
$firma_sabado = at_pt_calcular_fechas($plan, at_pt_inicio_por_defecto('2026-10-03', $fer), $fer);
ok($firma_sabado['fecha_inicio'] === '2026-10-05', 'firma un sábado -> inicio el lunes siguiente');
ok(at_pt_calcular_fechas($plan, 'basura', $fer) === $plan && at_pt_calcular_fechas(['proyecto' => 'X'], '2026-10-05', $fer) === ['proyecto' => 'X'], 'inicio inválido o plan sin fases: el plan vuelve sin tocar');

// Paralela más larga que la anterior: el bloque termina con la más larga; la primera nunca es paralela.
$mini = ['fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Bloque', 'entrega' => false, 'actividades' => [
	$a('A', 'at', 2, true), $a('B', 'cliente', 5, true), $a('C', 'at', 1),
]]]]]];
$m = at_pt_calcular_fechas($mini, '2026-10-05', []);
ok(fechas($m, 'A') === ['2026-10-05', '2026-10-06'] && fechas($m, 'B') === ['2026-10-05', '2026-10-09'] && fechas($m, 'C') === ['2026-10-12', '2026-10-12'], 'B en paralelo con A y más larga: C parte después de B');
ok($m['cronograma']['barras'] === [['fase' => 'diseno_desarrollo', 'etiqueta' => 'Bloque', 'tipo' => 'trabajo', 'responsable' => 'ambos', 'desde' => '2026-10-05', 'hasta' => '2026-10-12']], 'una barra, responsable ambos (AT y cliente)');
ok($m['cronograma']['hitos'] === [['nombre' => 'Entrega estimada', 'fecha' => '2026-10-12']], 'sin fase de Implementación, «Entrega estimada» = fin del cronograma');

// Review Focus 6: carta Gantt larga (plataforma a medida + soporte): 15 barras en 14 semanas, fechas en orden.
$d = fn(string $n, string $r, int $dias, string $s = '', string $e = '', bool $par = false) => ['nombre' => $n, 'responsable' => $r, 'dias_habiles' => $dias, 'servicio' => $s, 'etapa' => $e, 'en_paralelo' => $par];
$larga = at_pt_validar_plan(['proyecto' => '[PRUEBA] Plataforma de Cliente Prueba', 'fases' => [
	['clave' => 'diseno_desarrollo', 'bloques' => [
		['nombre' => 'Diseño', 'entrega' => true, 'actividades' => [$d('Mapa de pantallas', 'at', 3, 'plataforma', 'diseno'), $d('Diseño de pantallas', 'at', 5, 'plataforma', 'diseno')]],
		['nombre' => 'Desarrollo · etapa 1', 'entrega' => true, 'actividades' => [$d('Base de datos y usuarios', 'at', 5, 'plataforma', 'desarrollo'), $d('Panel de administración', 'at', 5, 'plataforma', 'desarrollo')]],
		['nombre' => 'Desarrollo · etapa 2', 'entrega' => true, 'actividades' => [$d('Reportes', 'at', 5, 'plataforma', 'desarrollo'), $d('Integración con pagos', 'at', 5, 'plataforma', 'desarrollo', true)]],
		['nombre' => 'Migración de datos', 'actividades' => [$d('Planilla con tus datos', 'cliente', 2), $d('Carga de datos', 'at', 2, '', '', true)]],
		['nombre' => 'Pruebas', 'entrega' => true, 'actividades' => [$d('Pruebas con usuarios reales', 'ambos', 5, 'plataforma', 'pruebas')]],
	]],
	['clave' => 'implementacion', 'bloques' => [
		['nombre' => 'Puesta en marcha', 'actividades' => [$d('Publicación', 'at', 2, 'plataforma', 'implementacion')]],
		['nombre' => 'Capacitación', 'actividades' => [$d('Capacitación del equipo', 'ambos', 1, 'plataforma', 'implementacion')]],
		['nombre' => 'Marcha blanca', 'actividades' => [$d('Acompañamiento', 'at', 2)]],
	]],
	['clave' => 'soporte', 'bloques' => [
		['nombre' => 'Garantía', 'actividades' => [$d('Corrección de errores', 'at', 2)]],
		['nombre' => 'Mejora continua', 'actividades' => [$d('Reunión mensual de mejoras', 'ambos', 1)]],
	]],
], 'hitos' => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño'], ['nombre' => 'Plataforma aprobada', 'despues_de' => 'Pruebas']]]);
$g = at_pt_calcular_fechas(at_pt_aplicar_tabla($larga['plan'], at_pt_duraciones_defecto()), '2026-10-05', $fer)['cronograma'];
ok($larga['ok'] && count($g['barras']) === 15 && $g['semanas'] === 14, 'plataforma + soporte: 10 bloques y 5 revisiones = 15 barras en 14 semanas (5-oct-2026 al 4-ene-2027)');
ok($g['inicio'] === '2026-10-05' && $g['fin'] === '2027-01-04', 'del 5-oct-2026 al 4-ene-2027 (salta los feriados del 12-oct, 8-dic, 25-dic y 1-ene)');
$en_orden = true;
$previo = '';
foreach ($g['barras'] as $b) {
	$en_orden = $en_orden && at_pt_es_habil($b['desde'], $fer) && at_pt_es_habil($b['hasta'], $fer) && $b['desde'] <= $b['hasta'] && $b['desde'] > $previo;
	$previo = $b['hasta'];
}
ok($en_orden, 'cada barra empieza y termina en día hábil, después de la anterior y sin traslaparse');
ok($g['barras'][1] === ['fase' => 'diseno_desarrollo', 'etiqueta' => 'Diseño', 'tipo' => 'trabajo', 'responsable' => 'at', 'desde' => '2026-10-09', 'hasta' => '2026-10-21'], 'Diseño con la tabla (8 días hábiles) salta el feriado del 12-oct: 9 al 21-oct');
ok($g['hitos'] === [['nombre' => 'Diseño aprobado', 'fecha' => '2026-10-28'], ['nombre' => 'Plataforma aprobada', 'fecha' => '2026-12-21'], ['nombre' => 'Entrega estimada', 'fecha' => '2026-12-29']], 'hitos de la carta larga; la entrega estimada es el fin de la Marcha blanca');
$tope = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => ['nombre' => "Etapa {$i}", 'entrega' => true, 'actividades' => [$a("Trabajo {$i}", 'at', 3)]], range(1, 13))]]]);
$gt = at_pt_calcular_fechas($tope['plan'], '2026-10-05', $fer)['cronograma'];
ok($tope['ok'] && count($gt['barras']) === 27 && $gt['semanas'] === 23 && $gt['fin'] === '2027-03-09', 'el tope de 14 bloques da 27 barras (el Arranque no tiene revisión) en 23 semanas, hasta el 9-mar-2027');
// Peor caso que el renderer debe dejar legible (fixture de la Task 12): 14 bloques, 27 barras, 130 días hábiles.
$peor = at_pt_validar_plan(['proyecto' => '[PRUEBA] Peor caso', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => ['nombre' => "Etapa {$i}", 'entrega' => true, 'actividades' => [$a("Trabajo {$i}", 'at', $i <= 9 ? 5 : 4)]], range(1, 13))]]]);
$gp = at_pt_calcular_fechas($peor['plan'], '2026-10-05', $fer)['cronograma'];
$habiles = 0;
for ($dia = $gp['inicio']; $dia <= $gp['fin']; $dia = at_pt_siguiente_habil($dia, $fer)) {
	$habiles++;
}
ok($peor['ok'] && count($gp['barras']) === 27 && $habiles === 130 && $gp['semanas'] === 27 && $gp['fin'] === '2027-04-08', 'peor caso: 27 barras y 130 días hábiles en 27 semanas (5-oct-2026 al 8-abr-2027)');
$peor_mas_uno = $peor['plan'];
$peor_mas_uno['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 6;
ok(at_pt_validar_plan($peor_mas_uno)['errores'] === ['El plan suma 131 días hábiles con las revisiones (máximo 130, unas 26 semanas): acórtalo o divide el proyecto.'], 'un día hábil más que el peor caso ya no se acepta');

// «Entrega estimada» cuando el último bloque de Implementación tiene entrega: el fin del bloque (cuando entregamos),
// antes de la revisión del cliente; «Tu revisión» sigue apareciendo como barra.
$impl = at_pt_calcular_fechas(at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'entrega' => true, 'actividades' => [$a('Publicación', 'at', 2)]]]]]])['plan'], '2026-10-05', []);
ok($impl['cronograma']['hitos'] === [['nombre' => 'Entrega estimada', 'fecha' => '2026-10-12']] && end($impl['cronograma']['barras'])['desde'] === '2026-10-13' && $impl['cronograma']['fin'] === '2026-10-19', 'Implementación con entrega: «Entrega estimada» el 12-oct (fin del bloque); tu revisión del 13 al 19-oct');

fin();
