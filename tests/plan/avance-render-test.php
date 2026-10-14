<?php
// Correr: php tests/plan/avance-render-test.php   (sin WordPress)
// Estado del proyecto, Etapa 2 — Task 5: cuerpo para el renderer (document_type estado). Datos inventados.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';
$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

$act = fn(string $n, string $d, string $h) => ['nombre' => $n, 'responsable' => 'at', 'dias_habiles' => 2, 'desde' => $d, 'hasta' => $h];
$plan = [
	'proyecto' => '[PRUEBA] Sitio de la panadería',
	'fases' => [
		['clave' => 'diseno_desarrollo', 'bloques' => [
			['nombre' => 'Diseño', 'entregable' => 'Maqueta aprobada', 'actividades' => [$act('Maqueta', '2026-10-05', '2026-10-09')]],
			['nombre' => 'Desarrollo', 'entregable' => 'Sitio en pruebas', 'actividades' => [$act('Construcción', '2026-10-12', '2026-10-21')]],
		]],
		['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'entregable' => 'Sitio publicado', 'actividades' => [$act('Publicación', '2026-10-22', '2026-10-23')]]]],
	],
	'cronograma' => ['inicio' => '2026-10-05', 'fin' => '2026-10-23', 'semanas' => 3, 'barras' => [], 'hitos' => []],
	'soporte' => ['garantia_meses' => 3, 'mensuales' => ['Mantención mensual del sitio']],
];
$datos = ['codigo' => 'AbCdEf123456', 'company_name' => '[PRUEBA] Panadería', 'client_name' => 'Cliente Prueba', 'portal_url' => '', 'whatsapp' => '56900000000',
	'fecha_firma' => '2026-10-01', 'garantia_meses' => 3, 'sitio' => 'https://automatizatech.cl', 'previa_token' => 'abc123def4',
	'entregables' => [3 => 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789']];
$av = at_av_vacio();
$av['cierres'] = [
	['id' => 'c111111', 'titulo' => 'Sitio web', 'bloques' => ['Diseño', 'Desarrollo'], 'fecha_real' => '2026-10-10', 'estado' => 'publicado', 'publicado_at' => '2026-10-13 10:00:00', 'entregable_id' => 3,
		'antes' => ['texto' => 'Antes.', 'puntos' => ['a'], 'evidencias' => ['c111111-lh']],
		'durante' => ['texto' => 'Durante.', 'actividades' => [['bloque' => 'Diseño', 'nombre' => 'Maqueta', 'plan_desde' => '2026-10-05', 'plan_hasta' => '2026-10-09', 'real' => '2026-10-08', 'nota' => '']]],
		'despues' => ['texto' => 'Después.', 'puntos' => [], 'evidencias' => ['c111111-portada', 'c111111-pendiente', 'c111111-interno', 'c111111-sitio']]],
	['id' => 'c222222', 'titulo' => 'Borrador secreto', 'bloques' => ['Puesta en marcha'], 'fecha_real' => '2026-10-05', 'estado' => 'borrador', 'publicado_at' => '', 'entregable_id' => 0,
		'antes' => ['texto' => 'x', 'puntos' => [], 'evidencias' => []], 'durante' => ['texto' => '', 'actividades' => []], 'despues' => ['texto' => '', 'puntos' => [], 'evidencias' => []]],
];
$av['evidencias'] = [
	'c111111-lh' => ['tipo' => 'cifra', 'titulo' => 'Lighthouse', 'url' => '', 'poster' => '', 'valor' => '98', 'unidad' => 'puntos', 'fuente' => 'PageSpeed', 'fecha' => '2026-10-13'],
	'c111111-portada' => ['tipo' => 'captura', 'titulo' => 'Portada', 'url' => 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.png', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-pendiente' => ['tipo' => 'video', 'titulo' => 'Sin subir', 'url' => '', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-interno' => ['tipo' => 'enlace', 'titulo' => 'Interno', 'url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-sitio' => ['tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
];
$av['necesitamos'] = [['texto' => 'Logo', 'estado' => 'abierto', 'fecha' => '2026-10-01'], ['texto' => 'Dominio', 'estado' => 'resuelto', 'fecha' => '2026-10-01']];

$r = at_av_armar_render_estado($plan, $av, $datos, false, '2026-10-14');
$json = (string) json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
ok($r['document_type'] === 'estado' && $r['unique_id'] === 'AbCdEf123456-estado' && $r['draft'] === false && $r['actualizado'] === '2026-10-14', 'tipo estado, código del plan + «-estado», sin borradores');
ok(array_column($r['cierres'], 'titulo') === ['Sitio web'] && !str_contains($json, 'Borrador secreto'), 'solo cierres publicados: el borrador no llega (Review Focus 2)');
$c = $r['cierres'][0];
ok($c['borrador'] === false && $c['entregable_url'] === 'https://automatizatech.cl/ver-entregable.php?id=XyZ123456789', 'cierre con el enlace de su entrega');
ok(array_column($c['despues']['evidencias'], 'titulo') === ['Portada', 'Sitio'] && $c['antes']['evidencias'][0]['valor'] === '98', 'evidencias sin archivo o con enlace interno no salen (D8); la cifra sí');
ok(!str_contains($json, 'easypanel'), 'ningún enlace al servidor interno en el cuerpo');
ok(array_column($r['bloques'], 'estado') === ['listo', 'listo', 'por_venir'] && $r['bloques'][0]['fin_real'] === '2026-10-10', 'bloques con su estado (el borrador no marca nada)');
ok($r['necesitamos'] === ['Logo'], 'necesitamos: solo lo abierto, como texto');
ok($r['alcance'] === ['fase1' => ['Maqueta aprobada', 'Sitio en pruebas', 'Sitio publicado'], 'mensual' => ['Mantención mensual del sitio']], 'alcance vacío: entregables del plan y servicios mensuales del plan');
$av2 = $av;
$av2['alcance'] = ['fase1' => ['Sitio web'], 'mensual' => ['Publicidad']];
ok(at_av_armar_render_estado($plan, $av2, $datos, false, '2026-10-14')['alcance'] === ['fase1' => ['Sitio web'], 'mensual' => ['Publicidad']], 'alcance cargado: manda el del avance');
ok($r['plan_url'] === 'https://automatizatech.cl/ver-plan.php?id=AbCdEf123456' && str_contains($r['agenda']['web_url'], 'agendar=1') && $r['company_name'] === '[PRUEBA] Panadería', 'enlaces al plan y a la agenda por automatizatech.cl');
ok($r['image_briefs'] === [] && $r['images'] instanceof stdClass && str_contains($json, '"images":{}'), 'sin fotos nuevas (D9); images sale como objeto JSON');
ok($r['cronograma'] === ['inicio' => '2026-10-05', 'fin' => '2026-10-23', 'semanas' => 3], 'cronograma: inicio, fin y semanas del plan');
$p = at_av_armar_render_estado($plan, $av, $datos, true, '2026-10-14');
ok($p['unique_id'] === 'AbCdEf123456-previa-abc123def4' && $p['draft'] === true && array_column($p['cierres'], 'titulo') === ['Borrador secreto', 'Sitio web'] && $p['cierres'][0]['borrador'] === true, 'vista previa: también borradores, marcados, en orden de fecha y en su propia ruta (R3)');
fin();
