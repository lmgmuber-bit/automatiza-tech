<?php
// Correr: php tests/plan/cierre-puras-test.php   (sin WordPress)
// Estado del proyecto, Etapa 3 — Task 1: resumen del cierre, correo, WhatsApp y bloqueo D8. Datos inventados.
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/avance-puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/cierre-puras.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

$act = fn(string $n, string $d, string $h) => ['nombre' => $n, 'responsable' => 'at', 'dias_habiles' => 2, 'desde' => $d, 'hasta' => $h];
$plan = ['proyecto' => '[PRUEBA] Sitio de la panadería', 'fases' => [
	['clave' => 'diseno_desarrollo', 'bloques' => [
		['nombre' => 'Diseño', 'entregable' => 'Maqueta', 'actividades' => [$act('Maqueta', '2026-10-05', '2026-10-09')]],
		['nombre' => 'Desarrollo', 'entregable' => 'Sitio', 'actividades' => [$act('Construcción', '2026-10-12', '2026-10-21')]],
	]],
	['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'entregable' => 'Publicado', 'actividades' => [$act('Publicación', '2026-10-22', '2026-11-05')]]]],
], 'cronograma' => ['inicio' => '2026-10-05', 'fin' => '2026-11-05'], 'soporte' => ['mensuales' => []]];
$av = at_av_vacio();
$av['cierres'] = [[
	'id' => 'c111111', 'titulo' => 'Sitio publicado', 'bloques' => ['Desarrollo'], 'fecha_real' => '2026-10-10', 'estado' => 'publicado', 'publicado_at' => '2026-10-13 10:00:00', 'entregable_id' => 0,
	'antes' => ['texto' => 'Lento.', 'puntos' => ['Tardaba 17 s', 'Sucursales cerradas', 'Fotos viejas', 'Cuarto punto'], 'evidencias' => ['c111111-lh']],
	'durante' => ['texto' => 'Rehicimos el sitio.', 'actividades' => [['bloque' => 'Desarrollo', 'nombre' => 'Construcción', 'plan_desde' => '2026-10-12', 'plan_hasta' => '2026-10-21', 'real' => '2026-10-10', 'nota' => '']]],
	'despues' => ['texto' => 'Rápido y claro.', 'puntos' => ['Carga en 2,1 s'], 'evidencias' => ['c111111-lh2', 'c111111-video', 'c111111-sitio']],
]];
$av['evidencias'] = [
	'c111111-lh' => ['tipo' => 'cifra', 'titulo' => 'Velocidad', 'valor' => '56', 'unidad' => 'de 100', 'fuente' => 'Lighthouse', 'fecha' => '2026-10-03', 'url' => '', 'poster' => ''],
	'c111111-lh2' => ['tipo' => 'cifra', 'titulo' => 'Velocidad', 'valor' => '98', 'unidad' => 'de 100', 'fuente' => 'Lighthouse', 'fecha' => '2026-10-14', 'url' => '', 'poster' => ''],
	'c111111-video' => ['tipo' => 'video', 'titulo' => 'Recorrido', 'url' => 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.mp4', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
	'c111111-sitio' => ['tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/', 'poster' => '', 'valor' => '', 'unidad' => '', 'fuente' => '', 'fecha' => ''],
];
$av['necesitamos'] = [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-01'], ['texto' => 'Dominio', 'estado' => 'resuelto', 'fecha' => '2026-10-01']];
$av['bloques'] = ['Diseño' => ['estado' => 'listo', 'inicio_real' => '2026-10-05', 'fin_real' => '2026-10-09']];

ok(at_av_cierre_de_avance($av, 'c111111')['titulo'] === 'Sitio publicado' && at_av_cierre_de_avance($av, 'cnoexis') === null, 'busca un cierre por id');
ok(at_av_frase_plan_real('2026-10-21', '2026-10-10') === 'Lo teníamos planificado para el 21 de octubre de 2026 y quedó listo el 10 de octubre de 2026 (11 días antes).', 'frase: antes de lo planificado');
ok(at_av_frase_plan_real('2026-10-21', '2026-10-21') === 'Quedó listo el 21 de octubre de 2026, el día planificado.', 'frase: el día planificado');
ok(at_av_frase_plan_real('2026-10-21', '2026-10-23') === 'Lo teníamos planificado para el 21 de octubre de 2026 y quedó listo el 23 de octubre de 2026 (2 días después).', 'frase: después');
ok(at_av_frase_plan_real('', '2026-10-10') === 'Quedó listo el 10 de octubre de 2026.', 'frase: sin fecha planificada');

$r = at_av_resumen_cierre($plan, $av, 'c111111');
ok($r['antes'] === ['Tardaba 17 s', 'Sucursales cerradas', 'Fotos viejas'], 'antes: hasta 3 puntos');
ok($r['durante'] === ['Construcción'] && $r['despues'] === ['Carga en 2,1 s'], 'durante: las actividades; después: sus puntos');
ok($r['cifras'][0]['parte'] === 'antes' && $r['cifras'][1]['parte'] === 'despues', 'cada cifra dice si es del antes o del después');
ok(count($r['cifras']) === 2 && $r['cifras'][1]['valor'] === '98' && $r['cifras'][1]['fuente'] === 'Lighthouse', 'cifras del antes y del después con fuente y fecha');
ok($r['linea_despues'] === 'Carga en 2,1 s', 'una línea del después: su primer punto');
ok(str_contains($r['frase'], '21 de octubre') && str_contains($r['frase'], '11 días antes'), 'la frase usa el fin planificado del bloque del cierre');
ok(array_column($r['sigue'], 'nombre') === ['Puesta en marcha'] && $r['necesitamos'] === ['Logo en alta'], 'lo que sigue: bloques no listos; necesitamos: solo lo abierto');
ok($r['videos'] === [['titulo' => 'Recorrido', 'url' => 'https://automatizatech.cl/wp-content/uploads/at-estado/7/aaaaaaaaaaaaaaaaaaaa.mp4']], 'videos del cierre');
ok(in_array('https://example.com/', $r['urls'], true) && in_array($r['videos'][0]['url'], $r['urls'], true), 'urls: enlaces y archivos del cierre');
$sin = $av; $sin['cierres'][0]['despues']['puntos'] = [];
ok(at_av_resumen_cierre($plan, $sin, 'c111111')['linea_despues'] === 'Rápido y claro.', 'sin puntos: la primera frase del texto del después');

ok(at_av_urls_internas(['https://automatizatech.cl/x', 'https://n8n-x.kchiba.easypanel.host/p/a', 'https://N8N-X.KCHIBA.EASYPANEL.HOST/p/a']) === ['https://n8n-x.kchiba.easypanel.host/p/a', 'https://N8N-X.KCHIBA.EASYPANEL.HOST/p/a'], 'D8: detecta el servidor interno sin importar mayúsculas');

$html = at_av_correo_cierre_html(['nombre' => 'Ana', 'proyecto' => '[PRUEBA] Panadería', 'logo' => 'https://automatizatech.cl/logo.png', 'resumen' => $r,
	'mensaje' => "Gracias por tu paciencia.\n<b>hola</b>", 'url_estado' => 'https://automatizatech.cl/ver-estado.php?id=AbCdEf123456', 'con_pdf' => true]);
ok(str_contains($html, 'Terminamos: Sitio publicado') && str_contains($html, 'Hola <strong>Ana</strong>'), 'correo: título y saludo');
ok(str_contains($html, 'Ver el estado del proyecto') && str_contains($html, 'ver-estado.php?id=AbCdEf123456'), 'correo: botón al estado');
ok(str_contains($html, '98 de 100') && str_contains($html, 'Lighthouse') && str_contains($html, '14 de octubre de 2026'), 'correo: cifra con fuente y fecha');
ok(str_contains($html, 'Antes · <strong>Velocidad:</strong> 56 de 100') && str_contains($html, 'Después · <strong>Velocidad:</strong> 98 de 100') && strpos($html, 'Antes · <strong>') < strpos($html, 'Después · <strong>'), 'correo: cada cifra dice Antes o Después, en ese orden');
$rs = $r; $rs['cifras'] = [['titulo' => 'Velocidad', 'valor' => '98', 'unidad' => 'de 100', 'fuente' => 'Lighthouse', 'fecha' => '', 'parte' => 'despues']];
$h2 = at_av_correo_cierre_html(['resumen' => $rs]);
ok(str_contains($h2, '(Lighthouse)') && !str_contains($h2, 'Lighthouse,') && !str_contains($h2, '(, '), 'correo: cifra sin fecha, solo la fuente entre paréntesis');
$rs['cifras'][0]['fuente'] = ''; $rs['cifras'][0]['fecha'] = '2026-10-14';
ok(str_contains(at_av_correo_cierre_html(['resumen' => $rs]), '(14 de octubre de 2026)'), 'correo: cifra sin fuente, solo la fecha');
$rs['cifras'][0]['fecha'] = '';
ok(!str_contains(at_av_correo_cierre_html(['resumen' => $rs]), '<span style="color:#64748b">'), 'correo: cifra sin fuente ni fecha, sin paréntesis');
ok(str_contains($html, 'Gracias por tu paciencia.<br>') && str_contains($html, '&lt;b&gt;hola&lt;/b&gt;') && !str_contains($html, '<b>hola</b>'), 'correo: el mensaje de Luis va escapado y con saltos de línea');
ok(str_contains($html, 'Lo que sigue') && str_contains($html, 'Puesta en marcha') && str_contains($html, 'Lo que necesitamos de ti') && str_contains($html, 'Logo en alta'), 'correo: lo que sigue y lo que necesitamos');
ok(str_contains($html, 'adjunto en PDF'), 'correo: avisa el PDF adjunto');
ok(!str_contains($html, 'easypanel') && !str_contains(at_av_correo_cierre_html(['resumen' => $r, 'url_estado' => 'https://x.easypanel.host/p']), 'easypanel.host/p'), 'correo: nunca dibuja un enlace del servidor interno');
ok(!str_contains(at_av_correo_cierre_html(['resumen' => $r, 'url_estado' => 'https://automatizatech.cl/e', 'mensaje' => '']), 'Un mensaje de'), 'correo: sin mensaje no hay recuadro vacío');

$wa = at_av_texto_whatsapp_cierre('Ana', $r, 'https://automatizatech.cl/ver-estado.php?id=AbCdEf123456');
ok($wa === "Hola Ana, terminamos Sitio publicado 🎉 Carga en 2,1 s. Te dejamos el detalle y el video aquí: https://automatizatech.cl/ver-estado.php?id=AbCdEf123456", 'WhatsApp: con video');
$rv = $r; $rv['videos'] = [];
ok(at_av_texto_whatsapp_cierre('', $rv, 'https://automatizatech.cl/e') === 'Hola, terminamos Sitio publicado 🎉 Carga en 2,1 s. Te dejamos el detalle aquí: https://automatizatech.cl/e', 'WhatsApp: sin video y sin nombre');
$ev = at_av_envio_de_cierre(['id' => 'x']);
ok($ev === ['correo' => ['enviado_at' => null, 'prueba_at' => null, 'mensaje' => '', 'enviando' => null, 'a' => ''], 'whatsapp' => ['abierto_at' => null]], 'envío: un cierre sin esas claves da los valores por defecto');
$ev = at_av_envio_de_cierre(['correo' => ['enviado_at' => '2026-10-14 11:00:00', 'a' => 'x@example.com']]);
ok($ev['correo']['enviado_at'] === '2026-10-14 11:00:00' && $ev['correo']['a'] === 'x@example.com' && $ev['correo']['prueba_at'] === null && $ev['correo']['mensaje'] === '' && $ev['whatsapp']['abierto_at'] === null, 'envío: conserva lo que trae el cierre y completa el resto');
ok(at_av_envio_de_cierre(['correo' => ['enviado_at' => '', 'prueba_at' => 5], 'whatsapp' => ['abierto_at' => '']])['correo']['enviado_at'] === null && at_av_envio_de_cierre(['correo' => ['prueba_at' => 5]])['correo']['prueba_at'] === null && at_av_envio_de_cierre(['whatsapp' => ['abierto_at' => '']])['whatsapp']['abierto_at'] === null, 'envío: vacío o inválido pasa a null');
fin();
