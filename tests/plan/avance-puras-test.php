<?php
// Correr: php tests/plan/avance-puras-test.php   (sin WordPress)
// Estado del proyecto, Etapa 2 — Task 1: forma del avance, importar cierre (at-cierre-v1), estado de los bloques y
// textos del panel. Datos inventados: el repositorio es público.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

const COD = 'AbCdEf123456';

/** Plan firmado mínimo con fechas ya calculadas: dos fases, tres bloques. */
function plan_prueba(): array {
	$act = fn(string $n, string $d, string $h) => ['nombre' => $n, 'responsable' => 'at', 'dias_habiles' => 2, 'desde' => $d, 'hasta' => $h];
	return [
		'proyecto' => '[PRUEBA] Sitio de la panadería',
		'fases' => [
			['clave' => 'diseno_desarrollo', 'bloques' => [
				['nombre' => 'Diseño', 'entregable' => 'Maqueta aprobada', 'actividades' => [$act('Maqueta de la portada', '2026-10-05', '2026-10-06'), $act('Páginas internas', '2026-10-07', '2026-10-09')]],
				['nombre' => 'Desarrollo', 'entregable' => 'Sitio en pruebas', 'actividades' => [$act('Construcción del sitio', '2026-10-12', '2026-10-21')]],
			]],
			['clave' => 'implementacion', 'bloques' => [
				['nombre' => 'Puesta en marcha', 'entregable' => 'Sitio publicado', 'actividades' => [$act('Publicación', '2026-10-22', '2026-10-23')]],
			]],
		],
		'cronograma' => ['inicio' => '2026-10-05', 'fin' => '2026-10-23', 'semanas' => 3, 'barras' => [], 'hitos' => []],
		'soporte' => ['garantia_meses' => 3, 'mensuales' => ['Mantención mensual del sitio']],
	];
}

/** Archivo at-cierre-v1 válido; $cambios reemplaza claves de primer nivel o de «cierre» (con 'cierre.x'). */
function archivo(array $cambios = []): array {
	$d = [
		'formato' => 'at-cierre-v1', 'plan' => COD,
		'cierre' => [
			'titulo' => 'Sitio web', 'bloques' => ['diseño', 'Desarrollo '], 'fecha_real' => '2026-10-10', 'entregable_id' => 0,
			'antes' => ['texto' => 'El sitio usaba una plantilla genérica.', 'puntos' => ['Plantilla genérica', 'Páginas de demostración indexadas'], 'evidencias' => ['lh-antes']],
			'durante' => ['texto' => 'Rediseñamos y publicamos.', 'actividades' => [
				['bloque' => 'Diseño', 'nombre' => 'Maqueta de la portada', 'real' => '2026-10-03', 'nota' => ''],
				['bloque' => 'Desarrollo', 'nombre' => 'Construcción del sitio', 'real' => '2026-10-10', 'nota' => 'Adelantado'],
			]],
			'despues' => ['texto' => 'Sitio nuevo publicado.', 'puntos' => ['Publicado el 10-oct'], 'evidencias' => ['lh-despues', 'portada', 'reel', 'sitio']],
		],
		'evidencias' => [
			['ref' => 'lh-antes', 'tipo' => 'cifra', 'titulo' => 'Lighthouse celular', 'valor' => '56', 'unidad' => 'puntos', 'fuente' => 'PageSpeed Insights', 'fecha' => '2026-10-06'],
			['ref' => 'lh-despues', 'tipo' => 'cifra', 'titulo' => 'Lighthouse celular', 'valor' => '98', 'unidad' => 'puntos', 'fuente' => 'medición local', 'fecha' => '2026-10-13'],
			['ref' => 'portada', 'tipo' => 'captura', 'titulo' => 'Portada nueva', 'archivo' => 'portada-nueva.png'],
			['ref' => 'reel', 'tipo' => 'video', 'titulo' => 'Recorrido del sitio', 'archivo' => 'reel-sitio.mp4', 'poster' => 'reel-poster.jpg'],
			['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'El sitio publicado', 'url' => 'https://example.com/'],
		],
		'bloques' => ['Puesta en marcha' => ['estado' => 'en_curso', 'inicio_real' => '2026-10-13', 'fin_real' => '']],
		'cambios_plan' => [['fecha' => '2026-10-13', 'titulo' => 'Publicidad pasa al servicio mensual', 'detalle' => 'Acordado con el cliente.', 'bloques' => ['Puesta en marcha']]],
		'necesitamos' => [['texto' => 'Fecha de inicio del servicio mensual', 'estado' => 'abierto', 'fecha' => '2026-10-13']],
		'alcance' => ['fase1' => ['Sitio web publicado'], 'mensual' => ['Publicidad y visibilidad']],
	];
	foreach ($cambios as $k => $v) {
		if (str_starts_with($k, 'cierre.')) {
			$d['cierre'][substr($k, 7)] = $v;
		} elseif ($v === null) {
			unset($d[$k]);
		} else {
			$d[$k] = $v;
		}
	}
	return $d;
}

$plan = plan_prueba();
$vacio = at_av_vacio();

// --- forma
ok(at_av_normalizar(null) === $vacio && at_av_normalizar('') === $vacio && at_av_normalizar('{roto') === $vacio, 'avance vacío, sin guardar o ilegible: forma vacía completa');
$n = at_av_normalizar('{"cierres":[{"id":"c1"},"basura"],"alcance":{"fase1":["A",3]},"render":{"estado":"listo","otra":1}}');
ok(count($n['cierres']) === 1 && $n['alcance']['fase1'] === ['A'] && $n['render']['estado'] === 'listo' && !isset($n['render']['otra']) && $n['render_previa'] === at_av_registro_vacio(), 'normalizar descarta lo que no tiene la forma esperada');

// --- ayudas
ok(at_av_url_publica('https://example.com/a?b=1') === 'https://example.com/a?b=1', 'enlace https: sirve');
ok(at_av_url_publica('http://example.com/') === '' && at_av_url_publica('https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x') === '' && at_av_url_publica('https://ex ample.com') === '' && at_av_url_publica('javascript:alert(1)') === '', 'http, easypanel, espacios o javascript: no sirven (D8)');
ok(at_av_limpiar("Hola\n  mundo\x07") === 'Hola mundo' && at_av_limpiar("a\n\n\n\nb", true) === "a\n\nb", 'limpiar quita caracteres de control y junta espacios');
ok(at_av_limpiar('Lighthouse >=90 y <90 en celular') === 'Lighthouse >=90 y <90 en celular', 'limpiar no corta un «<» suelto (F3)');
$e = [];
ok(at_av_texto('Lighthouse >=90 y <90 en celular', 200, 'El texto', $e) === 'Lighthouse >=90 y <90 en celular' && $e === [], 'un texto con «<90» se guarda completo (F3)');
$e = [];
ok(at_av_texto('menos de <3 segundos', 200, 'El texto', $e) === 'menos de <3 segundos' && $e === [], 'un texto con «<3 segundos» se guarda completo (F3)');
$e = [];
at_av_texto('"><script>alert(1)</script>', 200, 'El título', $e);
ok(count($e) === 1 && str_contains($e[0], 'no puede llevar etiquetas HTML'), 'un texto con etiquetas HTML es error y no se guarda (F3)');
ok(at_av_fecha_valida('2026-02-28') && !at_av_fecha_valida('2026-02-30') && !at_av_fecha_valida('10-10-2026') && !at_av_fecha_valida(null), 'fechas AAAA-MM-DD reales');
ok(at_av_fecha_valida('2026-10-14') && !at_av_fecha_valida('2019-12-31') && !at_av_fecha_valida('2101-01-01') && at_av_fecha_valida('2020-01-01') && at_av_fecha_valida('2100-12-31'), 'fechas entre 2020 y 2100 (F4)');
$bp = at_av_bloques_plan($plan);
ok(array_column($bp, 'nombre') === ['Diseño', 'Desarrollo', 'Puesta en marcha'] && $bp[0]['plan_desde'] === '2026-10-05' && $bp[0]['plan_hasta'] === '2026-10-09' && $bp[2]['fase'] === 'implementacion', 'bloques del plan con su fase y sus fechas planificadas');
ok(at_av_bloque_del_plan($bp, '  DISEÑO ') === 'Diseño' && at_av_bloque_del_plan($bp, 'Otro') === '', 'los bloques se reconocen sin importar mayúsculas ni espacios');

// --- importar: el archivo bueno
$v = at_av_validar_import(archivo(), COD, $plan, $vacio);
ok($v['ok'] === true && $v['errores'] === [], 'archivo válido: sin errores', implode(' | ', $v['errores']));
ok($v['cierre']['bloques'] === ['Diseño', 'Desarrollo'], 'los bloques del cierre quedan con el nombre del plan');
$acts = $v['cierre']['durante']['actividades'];
ok(count($acts) === 2 && $acts[1]['plan_desde'] === '2026-10-12' && $acts[1]['plan_hasta'] === '2026-10-21' && $acts[1]['real'] === '2026-10-10', 'cada actividad trae sus fechas planificadas del plan');
ok(array_keys($v['evidencias']) === ['lh-antes', 'lh-despues', 'portada', 'reel', 'sitio'] && $v['evidencias']['reel']['poster_archivo'] === 'reel-poster.jpg', 'evidencias por ref, con el póster del video');
ok($v['cambios_plan'][0]['bloques'] === ['Puesta en marcha'] && $v['necesitamos'][0]['estado'] === 'abierto' && $v['alcance']['mensual'] === ['Publicidad y visibilidad'], 'cambios, necesitamos y alcance validados');
$sin_act = archivo(['cierre.durante' => ['texto' => 'x']]);
$v2 = at_av_validar_import($sin_act, COD, $plan, $vacio);
ok($v2['ok'] && count($v2['cierre']['durante']['actividades']) === 3 && $v2['cierre']['durante']['actividades'][0]['nombre'] === 'Maqueta de la portada', 'sin actividades: se precargan las del plan de esos bloques');
$v3 = at_av_validar_import(archivo(['cambios_plan' => null, 'necesitamos' => null, 'alcance' => null, 'bloques' => null]), COD, $plan, $vacio);
ok($v3['ok'] && $v3['cambios_plan'] === null && $v3['necesitamos'] === null && $v3['alcance'] === null && $v3['bloques'] === [], 'listas que no vienen: null (no se tocan al guardar)');

// --- importar: errores (Review Focus 4). Cada uno rechaza el archivo entero con un motivo simple.
$casos = [
	'otro formato' => [['formato' => 'otro'], 'no es un cierre'],
	'plan equivocado' => [['plan' => 'ZZZZZZ999999'], 'es de otro plan'],
	'cifra sin fuente' => [['evidencias' => [['ref' => 'x', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fecha' => '2026-10-01']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'no tiene fuente'],
	'cifra sin fecha' => [['evidencias' => [['ref' => 'x', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'no tiene fecha'],
	'enlace http' => [['evidencias' => [['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'http://example.com']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'https'],
	'enlace easypanel' => [['evidencias' => [['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'Vista', 'url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'servidor interno'],
	'fecha real inválida' => [['cierre.fecha_real' => '2026-13-01'], 'fecha real del cierre'],
	'bloque inexistente' => [['cierre.bloques' => ['Diseño', 'Inventado']], 'no está en el plan'],
	'sin bloques' => [['cierre.bloques' => []], 'al menos un bloque'],
	'evidencia no listada' => [['cierre.despues' => ['evidencias' => ['no-existe']]], 'no está en la lista'],
	'captura sin archivo' => [['evidencias' => [['ref' => 'x', 'tipo' => 'captura', 'titulo' => 'Portada']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'necesita «archivo»'],
	'video .exe' => [['evidencias' => [['ref' => 'x', 'tipo' => 'video', 'titulo' => 'Reel', 'archivo' => 'reel.exe']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'video (.mp4 o .webm)'],
	'texto muy largo' => [['cierre.antes' => ['texto' => str_repeat('a', 2001)]], 'máximo 2000'],
	'ref repetida' => [['evidencias' => [['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'A', 'url' => 'https://a.cl'], ['ref' => 'x', 'tipo' => 'enlace', 'titulo' => 'B', 'url' => 'https://b.cl']], 'cierre.antes' => ['evidencias' => ['x']], 'cierre.despues' => []], 'misma «ref»'],
	'muchas evidencias' => [['evidencias' => array_map(fn($i) => ['ref' => "e$i", 'tipo' => 'enlace', 'titulo' => "E$i", 'url' => 'https://a.cl'], range(1, 61)), 'cierre.antes' => [], 'cierre.despues' => []], 'máximo 60'],
	'ref solo numeros' => [['evidencias' => [['ref' => '1', 'tipo' => 'enlace', 'titulo' => 'A', 'url' => 'https://a.cl']], 'cierre.antes' => ['evidencias' => ['1']], 'cierre.despues' => []], 'no tiene «ref»'],
	'titulo con etiqueta' => [['cierre.titulo' => '"><script>alert(1)</script>'], 'no puede llevar etiquetas HTML'],
	'estado de bloque raro' => [['bloques' => ['Diseño' => ['estado' => 'casi']]], 'estado desconocido'],
	'cambio sin fecha' => [['cambios_plan' => [['titulo' => 'Algo']]], 'fecha válida'],
];
foreach ($casos as $nombre => [$cambios, $esperado]) {
	$r = at_av_validar_import(archivo($cambios), COD, $plan, $vacio);
	$texto = implode(' | ', $r['errores']);
	ok($r['ok'] === false && $r['cierre'] === [] && stripos($texto, $esperado) !== false, "rechaza: {$nombre} («{$esperado}»)" . ($r['ok'] ? '' : " — {$texto}"));
}
$ra1 = at_av_validar_import(archivo(['evidencias' => [['ref' => 'a1', 'tipo' => 'enlace', 'titulo' => 'A', 'url' => 'https://a.cl']], 'cierre.antes' => ['evidencias' => ['a1']], 'cierre.despues' => []]), COD, $plan, $vacio);
ok($ra1['ok'] === true, 'una ref con al menos una letra («a1») vale (F6)');
ok(at_av_validar_import('no es arreglo', COD, $plan, $vacio)['ok'] === false, 'algo que no es un objeto: rechazado sin lanzar');

// --- aplicar
$a1 = at_av_aplicar_import($vacio, $v, 'cabc123', '2026-10-13 18:00:00');
$av = $a1['avance'];
$c = $av['cierres'][0];
ok($a1['cierre_id'] === 'cabc123' && $c['estado'] === 'borrador' && $c['publicado_at'] === '' && $c['despues']['evidencias'] === ['cabc123-lh-despues', 'cabc123-portada', 'cabc123-reel', 'cabc123-sitio'], 'importar crea un cierre en borrador con ids de evidencia propios');
ok($av['evidencias']['cabc123-sitio']['url'] === 'https://example.com/' && $av['evidencias']['cabc123-portada']['url'] === '' && $av['bloques']['Puesta en marcha']['estado'] === 'en_curso' && count($av['cambios_plan']) === 1, 'enlaces con su url, archivos pendientes, bloques y listas guardados');
ok(at_av_faltan_archivos($av, 'cabc123') === ['cabc123-portada' => 'Portada nueva', 'cabc123-reel' => 'Recorrido del sitio'], 'faltan los archivos de la captura y del video (el póster es opcional)');
ok(at_av_cambiar_estado_cierre($av, 'cabc123', 'publicado', '2026-10-13 18:05:00') === 'Falta subir el archivo de: Portada nueva, Recorrido del sitio.', 'no se publica con archivos pendientes');
// Simular la subida de los archivos (Task 3 lo hace de verdad).
$av['evidencias']['cabc123-portada']['url'] = 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.png';
$av['evidencias']['cabc123-reel']['url'] = 'https://automatizatech.cl/wp-content/uploads/at-estado/7/bbbbbbbbbbbbbbbbbbbb.mp4';
// Review Focus 1: reimportar el mismo título (borrador) conserva los archivos de las evidencias con la misma ref y archivo.
$corr = archivo(['cierre.titulo' => 'SITIO WEB', 'cierre.antes' => ['texto' => 'Texto corregido.', 'evidencias' => ['lh-antes']]]);
$corr['evidencias'][3]['archivo'] = 'reel-sitio-v2.mp4'; // el video cambió: hay que volver a subirlo
$vc = at_av_validar_import($corr, COD, $plan, $av);
$a2 = at_av_aplicar_import($av, $vc, 'cotro99', '2026-10-13 19:00:00');
ok($vc['ok'] && $a2['cierre_id'] === 'cabc123' && count($a2['avance']['cierres']) === 1 && $a2['avance']['cierres'][0]['antes']['texto'] === 'Texto corregido.', 'reimportar el mismo título reemplaza el borrador (mismo id)');
ok($a2['avance']['evidencias']['cabc123-portada']['url'] !== '' && $a2['avance']['evidencias']['cabc123-reel']['url'] === '' && $a2['archivos'] === ['bbbbbbbbbbbbbbbbbbbb.mp4'], 'la captura conserva su archivo; el video cambiado queda pendiente y su archivo viejo se puede borrar');
$pub = at_av_cambiar_estado_cierre($av, 'cabc123', 'publicado', '2026-10-13 18:05:00');
ok(is_array($pub) && $pub['cierres'][0]['estado'] === 'publicado' && $pub['cierres'][0]['publicado_at'] === '2026-10-13 18:05:00', 'con los archivos subidos se publica');
$r = at_av_validar_import(archivo(), COD, $plan, $pub);
ok($r['ok'] === false && stripos(implode(' ', $r['errores']), 'ya está publicado') !== false, 'un cierre publicado no se pisa al importar');
ok(is_string(at_av_quitar_cierre($pub, 'cabc123')), 'un cierre publicado no se quita (primero a borrador)');
$bor = at_av_cambiar_estado_cierre($pub, 'cabc123', 'borrador', '2026-10-13 18:10:00');
$q = at_av_quitar_cierre($bor, 'cabc123');
ok(is_array($q) && $q['avance']['cierres'] === [] && $q['avance']['evidencias'] === [] && $q['archivos'] === ['aaaaaaaaaaaaaaaaaaaa.png', 'bbbbbbbbbbbbbbbbbbbb.mp4'], 'quitar un borrador saca sus evidencias y devuelve sus archivos');
ok(at_av_cambiar_estado_cierre($av, 'nada', 'publicado', 'x') === 'Ese cierre no existe.', 'cierre inexistente: motivo');

// --- solo se guardan las evidencias que «Antes» o «Después» nombran
$suelta = archivo();
$suelta['evidencias'][] = ['ref' => 'suelta', 'tipo' => 'enlace', 'titulo' => 'Nadie la nombra', 'url' => 'https://example.com/otra'];
$vs = at_av_validar_import($suelta, COD, $plan, $vacio);
$as = at_av_aplicar_import($vacio, $vs, 'cabc123', '2026-10-13 18:00:00');
ok($vs['ok'] && $vs['avisos'] !== [] && !isset($as['avance']['evidencias']['cabc123-suelta']) && count($as['avance']['evidencias']) === 5, 'una evidencia que nadie nombra no se guarda (el validador ya avisó)');

// --- tope de evidencias al reemplazar un borrador
$tres = fn(string $titulo) => archivo(['cierre.titulo' => $titulo, 'evidencias' => [
	['ref' => 'a', 'tipo' => 'enlace', 'titulo' => 'A', 'url' => 'https://a.cl'], ['ref' => 'b', 'tipo' => 'enlace', 'titulo' => 'B', 'url' => 'https://b.cl'], ['ref' => 'c', 'tipo' => 'enlace', 'titulo' => 'C', 'url' => 'https://c.cl']],
	'cierre.antes' => ['evidencias' => ['a']], 'cierre.despues' => ['evidencias' => ['b', 'c']]]);
$lleno = $vacio;
for ($i = 1; $i <= 58; $i++) {
	$lleno['evidencias']["z{$i}"] = ['tipo' => 'enlace', 'titulo' => "Z{$i}", 'url' => 'https://z.cl'];
}
$tope = at_av_validar_import($tres('Otro cierre'), COD, $plan, $lleno);
ok($tope['ok'] === false && stripos(implode(' ', $tope['errores']), 'Con este cierre el plan pasaría las 60 evidencias') !== false, 'con 58 evidencias y un cierre nuevo de 3, pasa el tope de 60');
// Un borrador existente cuya misma evidencia está en «Antes» y en «Después» cuenta una sola vez: 59 - 1 + 3 = 61.
$dup = $vacio;
for ($i = 1; $i <= 59; $i++) {
	$dup['evidencias']["z{$i}"] = ['tipo' => 'enlace', 'titulo' => "Z{$i}", 'url' => 'https://z.cl'];
}
$dup['cierres'][] = ['id' => 'cdup001', 'titulo' => 'Sitio web', 'estado' => 'borrador', 'antes' => ['evidencias' => ['z1']], 'despues' => ['evidencias' => ['z1']]];
$tope2 = at_av_validar_import($tres('Sitio web'), COD, $plan, $dup);
ok($tope2['ok'] === false && stripos(implode(' ', $tope2['errores']), 'pasaría las 60 evidencias') !== false, 'reemplazar un borrador con una evidencia en Antes y Después no la cuenta dos veces (59 - 1 + 3 = 61)');

// --- estado de los bloques
$eb =at_av_estado_bloques($plan, $pub);
ok($eb[0]['estado'] === 'listo' && $eb[0]['fin_real'] === '2026-10-10' && $eb[1]['estado'] === 'listo' && $eb[2]['estado'] === 'en_curso' && $eb[2]['inicio_real'] === '2026-10-13', 'bloques cubiertos por un cierre publicado: listos con la fecha del cierre');
$eb2 = at_av_estado_bloques($plan, $bor);
ok($eb2[0]['estado'] === 'por_venir' && $eb2[0]['fin_real'] === '', 'un cierre en borrador no marca nada como listo (Review Focus 2)');
ok(count(at_av_publicados($pub)) === 1 && at_av_publicados($bor) === [], 'publicados: solo los publicados');

// --- exportar para «Editar» (R2): el archivo vuelve a validar igual
$exp = at_av_exportar_cierre($pub, 'cabc123', COD);
$ve = at_av_validar_import($exp, COD, $plan, $bor);
ok($exp['formato'] === 'at-cierre-v1' && $exp['plan'] === COD && $ve['ok'] && $ve['cierre']['titulo'] === 'Sitio web' && array_keys($ve['evidencias']) === ['lh-antes', 'lh-despues', 'portada', 'reel', 'sitio'], 'exportar un cierre da un archivo at-cierre-v1 que vuelve a validar', implode(' | ', $ve['errores']));

// --- textos del panel
$e = [];
$cam = at_av_cambios_de_texto("2026-10-13 | Publicidad al mensual | Acordado por correo\n\n2026-10-20 | Otro cambio", $bp, [], $e);
ok($e === [] && count($cam) === 2 && $cam[0] === ['fecha' => '2026-10-13', 'titulo' => 'Publicidad al mensual', 'detalle' => 'Acordado por correo', 'bloques' => []], 'cambios de plan desde el panel: «fecha | título | detalle»');
ok(at_av_texto_de_cambios($cam) === "2026-10-13 | Publicidad al mensual | Acordado por correo\n2026-10-20 | Otro cambio |", 'y de vuelta al cuadro de texto');
$e = [];
at_av_cambios_de_texto('13-10-2026 | Mal', $bp, [], $e);
ok(count($e) === 1 && str_contains($e[0], 'línea 1'), 'cambio con fecha mal escrita: error que dice la línea');
$e = [];
at_av_cambios_de_texto("2026-10-13 | Uno\n\n2026-10-13 |", $bp, [], $e);
ok(count($e) === 1 && str_contains($e[0], 'línea 3'), 'cambio sin título: el error dice la línea del cuadro de texto, no la de la lista filtrada');
$e = [];
$prev = [['fecha' => '2026-10-13', 'titulo' => 'Publicidad al mensual', 'detalle' => 'x', 'bloques' => ['Puesta en marcha']]];
$cam2 = at_av_cambios_de_texto("2026-10-13 |  publicidad al   mensual | Acordado por correo\n2026-10-14 | Publicidad al mensual", $bp, $prev, $e);
ok($e === [] && $cam2[0]['bloques'] === ['Puesta en marcha'] && $cam2[0]['detalle'] === 'Acordado por correo' && $cam2[1]['bloques'] === [], 'editar el cuadro conserva los bloques del cambio con la misma fecha y título (y no de otro)');
$e = [];
$nec = at_av_necesitamos_de_texto("Logo en alta\n✓ Acceso al dominio", [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-01']], '2026-10-14', $e);
ok($e === [] && $nec === [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-01'], ['texto' => 'Acceso al dominio', 'estado' => 'resuelto', 'fecha' => '2026-10-14']], 'necesitamos: «✓ » marca resuelto y se conserva la fecha de lo que ya estaba');
ok(at_av_texto_de_necesitamos($nec) === "Logo en alta\n✓ Acceso al dominio", 'y de vuelta al cuadro de texto');
$e = [];
ok(at_av_lista_de_texto("Uno\n\nDos ", 'Fase 1', $e) === ['Uno', 'Dos'] && $e === [], 'listas de alcance: una por línea');
$e = [];
$b = at_av_bloques_de_post([['nombre' => 'Diseño', 'estado' => 'listo', 'inicio_real' => '2026-10-03', 'fin_real' => '2026-10-09'], ['nombre' => 'Desarrollo', 'estado' => 'por_venir', 'inicio_real' => '', 'fin_real' => ''], ['nombre' => 'Inventado', 'estado' => 'listo']], $plan, $e);
ok(array_keys($b) === ['Diseño', 'Desarrollo'] && $b['Diseño']['fin_real'] === '2026-10-09' && count($e) === 1, 'bloques del panel: se guardan los del plan; uno desconocido es error');
$e = [];
at_av_bloques_de_post([['nombre' => 'Diseño', 'estado' => 'listo', 'inicio_real' => '2026-10-09', 'fin_real' => '2026-10-03']], $plan, $e);
ok(count($e) === 1 && str_contains($e[0], 'antes'), 'fin real antes del inicio real: error');

// --- Formulario «Nuevo cierre»: arma el mismo archivo at-cierre-v1 y pasa la misma validación.
$fila_ev = fn(array $x) => $x + ['ref' => '', 'tipo' => 'captura', 'donde' => 'despues', 'titulo' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => '', 'url' => '', 'archivo_actual' => '', 'poster_actual' => ''];
$form = [
	'titulo' => ' Fotos antiguas retiradas ', 'fecha_real' => '2026-10-08', 'bloques' => ['diseño', ''],
	'antes_texto' => 'Se veían fotos de personal antiguo.', 'antes_puntos' => "- Fotos viejas\n\n• Sin permiso de uso ",
	'durante_texto' => 'Las retiramos.', 'despues_texto' => 'Ya no aparecen.', 'despues_puntos' => '',
	'ev' => [
		$fila_ev(['tipo' => 'cifra', 'donde' => 'antes', 'titulo' => 'Fotos de personal antiguo', 'valor' => '7', 'unidad' => 'fotos', 'fuente' => 'Revisión del sitio', 'fecha' => '2026-10-07']),
		$fila_ev(['tipo' => 'captura', 'titulo' => 'Portada sin las fotos']),
		$fila_ev([]),
		$fila_ev(['tipo' => 'video', 'titulo' => 'Recorrido']),
		$fila_ev(['tipo' => 'enlace', 'titulo' => 'El sitio', 'url' => 'https://example.com/']),
	],
];
$subidos = [1 => ['archivo' => 'Captura de pantalla 2026-10-08 a las 10.30.JPEG'], 3 => ['archivo' => 'grabacion final.MP4', 'poster' => 'portada video.png']];
$f = at_av_cierre_de_formulario($form, COD, $subidos);
$v = at_av_validar_import($f['datos'], COD, $plan, at_av_vacio());
ok($f['errores'] === [] && $v['ok'] && $v['cierre']['titulo'] === 'Fotos antiguas retiradas' && $v['cierre']['bloques'] === ['Diseño'], 'formulario: el cierre pasa la misma validación del archivo (bloque sin distinguir mayúsculas)');
ok($v['cierre']['antes']['puntos'] === ['Fotos viejas', 'Sin permiso de uso'] && $v['cierre']['antes']['evidencias'] === ['evidencia-1'] && $v['cierre']['despues']['evidencias'] === ['evidencia-2', 'evidencia-3', 'evidencia-4'], 'formulario: puntos sin viñetas y cada evidencia en su parte; la fila vacía no cuenta');
ok($f['nombres'] === [1 => ['archivo' => 'evidencia-2.jpg'], 3 => ['archivo' => 'evidencia-3.mp4', 'poster' => 'evidencia-3-portada.png']] && $v['evidencias']['evidencia-3']['poster_archivo'] === 'evidencia-3-portada.png', 'formulario: los archivos se renombran a la ref (los nombres del celular traen espacios)');
ok($v['cierre']['durante']['actividades'] !== [] && $v['cierre']['entregable_id'] === 0, 'formulario: las actividades del bloque se precargan del plan');

$sin_titulo = $form;
$sin_titulo['ev'][0]['titulo'] = '';
ok(at_av_cierre_de_formulario($sin_titulo, COD, [])['errores'] === ['A la evidencia 1 le falta el título.'], 'formulario: evidencia con datos pero sin título = error claro');
$heic = at_av_cierre_de_formulario($form, COD, [1 => ['archivo' => 'IMG_1234.HEIC']]);
ok($heic['errores'] === ['La imagen de «Portada sin las fotos» debe ser JPG, PNG o WebP.'], 'formulario: una foto HEIC del iPhone se rechaza con un mensaje claro');
$sin_archivo = at_av_cierre_de_formulario($form, COD, []);
$v2 = at_av_validar_import($sin_archivo['datos'], COD, $plan, at_av_vacio());
ok($v2['ok'] && $v2['evidencias']['evidencia-2']['archivo'] === 'evidencia-2.jpg' && $v2['evidencias']['evidencia-3']['archivo'] === 'evidencia-3.mp4', 'formulario: sin archivo se guarda igual y queda «falta» (se sube después)');
$dup = $form;
$dup['ev'][0]['ref'] = 'velocidad';
$dup['ev'][1]['ref'] = 'velocidad';
$rd = at_av_cierre_de_formulario($dup, COD, []);
ok(array_column($rd['datos']['evidencias'], 'ref') === ['velocidad', 'evidencia-1', 'evidencia-2', 'evidencia-3'], 'formulario: una ref repetida recibe otra nueva');

// Editar: del cierre guardado al formulario y de vuelta, conservando refs, archivos y actividades.
$ap = at_av_aplicar_import(at_av_vacio(), $v, 'cabc123', '2026-10-08 10:00:00');
$exp = at_av_exportar_cierre($ap['avance'], 'cabc123', COD);
$pf = at_av_formulario_de_cierre($exp);
ok($pf['titulo'] === 'Fotos antiguas retiradas' && $pf['antes_puntos'] === "Fotos viejas\nSin permiso de uso" && $pf['ev'][1]['archivo_actual'] === 'evidencia-2.jpg' && $pf['ev'][2]['poster_actual'] === 'evidencia-3-portada.png' && $pf['ev'][0]['donde'] === 'antes', 'editar: el formulario se llena con el cierre guardado');
$vuelta = at_av_cierre_de_formulario($pf, COD, [], $exp);
ok($vuelta['datos']['evidencias'] == $exp['evidencias'] && $vuelta['datos']['cierre']['durante']['actividades'] === $exp['cierre']['durante']['actividades'] && $vuelta['nombres'] === [], 'editar sin tocar nada: mismas evidencias, archivos y actividades');
$pf2 = $pf;
$pf2['bloques'] = ['Diseño', 'Desarrollo'];
ok(!array_key_exists('actividades', at_av_cierre_de_formulario($pf2, COD, [], $exp)['datos']['cierre']['durante']), 'editar con otros bloques: las actividades se vuelven a tomar del plan');
$nueva = at_av_cierre_de_formulario($pf, COD, [1 => ['archivo' => 'otra.png']], $exp);
ok($nueva['datos']['evidencias'][1]['archivo'] === 'evidencia-2.png' && $nueva['nombres'] === [1 => ['archivo' => 'evidencia-2.png']], 'editar con una imagen nueva: reemplaza la anterior');
fin();
