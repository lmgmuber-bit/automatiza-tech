<?php
// Correr: php tests/plan/render-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Estados y transiciones
ok(array_keys(at_pt_transiciones()) === ['generando', 'borrador', 'cambios', 'aprobando', 'listo', 'error', 'enviado'], 'los siete estados del plan');
ok(at_pt_transicion_valida('generando', 'borrador') && at_pt_transicion_valida('borrador', 'borrador') && at_pt_transicion_valida('borrador', 'aprobando') && at_pt_transicion_valida('aprobando', 'listo'), 'camino normal: generando -> borrador -> (guardar y recalcular) borrador -> aprobando -> listo');
ok(at_pt_transicion_valida('borrador', 'cambios') && at_pt_transicion_valida('cambios', 'borrador') && at_pt_transicion_valida('listo', 'cambios') && at_pt_transicion_valida('listo', 'borrador'), 'pedir cambios desde borrador o listo, y volver al borrador');
ok(at_pt_transicion_valida('generando', 'error') && at_pt_transicion_valida('cambios', 'error') && at_pt_transicion_valida('aprobando', 'error'), 'destrabar: generando, cambios y aprobando pasan a error');
ok(at_pt_transicion_valida('error', 'generando') && at_pt_transicion_valida('error', 'borrador'), 'Review Focus 5: desde error, «Reintentar borrador» (-> generando) o «Volver al borrador»');
ok(!at_pt_transicion_valida('generando', 'listo') && !at_pt_transicion_valida('borrador', 'listo') && !at_pt_transicion_valida('generando', 'generando'), 'no se salta la aprobación ni se genera dos veces a la vez');
ok(!at_pt_transicion_valida('borrador', 'enviado') && at_pt_transicion_valida('listo', 'enviado'), 'solo un plan listo se envía');
ok(at_pt_transiciones()['enviado'] === [] && !at_pt_transicion_valida('enviado', 'borrador') && !at_pt_transicion_valida('enviado', 'error'), 'enviado no cambia en la Etapa 1');
ok(!at_pt_transicion_valida('raro', 'borrador') && !at_pt_transicion_valida('', 'error') && !at_pt_transicion_valida('borrador', 'raro'), 'estados desconocidos: no');

// Costo de fotos nuevas (US$0,0032 por foto) + revisión de texto con GPT-4o (≈ US$0,026 por consulta, D18); máximo: x2
$todas = array_map(fn($s) => ['slide' => $s, 'prompt' => "escena del rubro para {$s}"], at_pt_slides_foto());
ok(at_pt_costo_fotos($todas, true) === ['fotos' => 8, 'usd_lista' => 0.0516, 'usd_revision' => 0.026, 'usd_max' => 0.1032], 'con propuesta: 10 láminas, portada y cierre se reutilizan -> 8 fotos + revisión = US$0,0516 (máximo 0,1032)');
ok(at_pt_costo_fotos($todas, false) === ['fotos' => 10, 'usd_lista' => 0.058, 'usd_revision' => 0.026, 'usd_max' => 0.116], 'Review Focus 4: sin propuesta, portada y cierre también son nuevas -> 10 fotos + revisión = US$0,058 (máximo 0,116)');
$sucias = [
	['slide' => 'metodo', 'prompt' => 'a'], ['slide' => 'metodo', 'prompt' => 'b'], ['slide' => 'inventada', 'prompt' => 'c'],
	['slide' => 'gantt', 'prompt' => '  '], ['slide' => 'cover', 'prompt' => 'd'], 'no es brief', ['prompt' => 'sin lámina'],
];
ok(at_pt_costo_fotos($sucias, true) === ['fotos' => 1, 'usd_lista' => 0.0292, 'usd_revision' => 0.026, 'usd_max' => 0.0584], 'cuenta una por lámina válida con descripción (sin repetidas, inventadas ni vacías); con una sola foto la revisión igual se cobra');
ok(at_pt_costo_fotos($sucias, false)['fotos'] === 2 && at_pt_costo_fotos([], false) === ['fotos' => 0, 'usd_lista' => 0.0, 'usd_revision' => 0.0, 'usd_max' => 0.0], 'sin propuesta la portada cuenta; sin briefs no hay fotos ni revisión: costo 0');
ok(AT_PT_USD_POR_FOTO === 0.0032 && AT_PT_USD_REVISION === 0.026, 'tarifas iguales a las de at_propuesta_costo_fotos (inc/proposals-flow.php): US$0,0032 por foto y US$0,026 por revisión');

// Cuerpo del render: plan completo (validar -> tabla -> fechas), firma martes 29-sep-2026.
$fer = ['2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08', '2026-12-25', '2027-01-01'];
$v = at_pt_validar_plan([
	'proyecto'     => '[PRUEBA] Sitio web de Cliente Prueba',
	'fecha_firma'  => '2026-09-29',
	'fases'        => [
		['clave' => 'diseno_desarrollo', 'descripcion' => 'Diseñamos y construimos tu sitio.', 'bloques' => [
			['nombre' => 'Diseño', 'entrega' => true, 'entregable' => 'Maqueta aprobada', 'actividades' => [
				['nombre' => 'Propuesta de diseño', 'responsable' => 'at', 'dias_habiles' => 3, 'servicio' => 'sitio_una_pagina', 'etapa' => 'diseno'],
			]],
			['nombre' => 'Desarrollo', 'actividades' => [
				['nombre' => 'Maquetación', 'responsable' => 'at', 'dias_habiles' => 5, 'servicio' => 'sitio_una_pagina', 'etapa' => 'desarrollo'],
			]],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación del sitio', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_una_pagina', 'etapa' => 'implementacion']]],
		]],
	],
	'hitos'        => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño']],
	'reuniones'    => [['nombre' => 'Reunión de inicio', 'detalle' => 'Por videollamada']],
	'image_briefs' => array_map(fn($s) => ['slide' => $s, 'prompt' => "equipo de una pyme trabajando, lámina {$s}"], at_pt_slides_foto()),
]);
$plan = at_pt_calcular_fechas(at_pt_aplicar_tabla($v['plan'], at_pt_duraciones_defecto()), at_pt_inicio_por_defecto('2026-09-29', $fer), $fer);
$datos = [
	'codigo'       => 'Ab3dE5fG7hJ9',
	'company_name' => '[PRUEBA] Empresa de prueba',
	'client_name'  => 'Cliente Prueba',
	'portal_url'   => 'https://automatizatech.cl/?crm_view=timeline&cid=7&token=abc123',
	'whatsapp'     => '56927002984',
	'fecha_firma'  => '2026-09-29 18:40:12',
];
$r = at_pt_armar_render($plan, $datos, false);
ok($v['ok'] && array_keys($r) === ['document_type', 'unique_id', 'draft', 'company_name', 'client_name', 'proyecto', 'fecha_firma_larga', 'fecha_inicio', 'fecha_fin', 'semanas', 'metodo', 'fases', 'cronograma', 'necesitamos_de_ti', 'reuniones', 'soporte', 'portal_url', 'agenda', 'image_briefs', 'images'], 'las claves del cuerpo del render, en el orden del esqueleto');
ok($r['document_type'] === 'plan' && $r['unique_id'] === 'Ab3dE5fG7hJ9' && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $r['unique_id']) === 1, 'tipo plan y unique_id = código (calza con la regla del renderer)');
ok($r['draft'] === true && $r['image_briefs'] === [], 'vista previa: draft true y sin fotos');
ok(strpos(json_encode($r), '"images":{}') !== false && strpos(json_encode($r), '"web_url":""') !== false, 'images sale como objeto JSON vacío {} y web_url vacío');
ok($r['company_name'] === '[PRUEBA] Empresa de prueba' && $r['client_name'] === 'Cliente Prueba' && $r['proyecto'] === '[PRUEBA] Sitio web de Cliente Prueba', 'empresa, cliente y proyecto');
ok($r['fecha_firma_larga'] === '29 de septiembre de 2026', 'fecha de firma larga (la firma trae hora)');
ok($r['fecha_inicio'] === '2026-10-05' && $r['fecha_fin'] === $plan['cronograma']['fin'] && $r['semanas'] === $plan['cronograma']['semanas'] && $r['semanas'] > 0, 'inicio lunes 5-oct, fin y semanas del cronograma');
ok($r['metodo'] === ['hechas' => ['diagnostico', 'priorizacion'], 'actual' => 'propuesta', 'proximas' => ['diseno_desarrollo', 'implementacion', 'soporte']], 'Método AT: diagnóstico y priorización hechas, estás en la propuesta');
ok($r['fases'] === $plan['fases'] && $r['cronograma'] === $plan['cronograma'] && $r['necesitamos_de_ti'] === $plan['necesitamos_de_ti'] && $r['reuniones'] === $plan['reuniones'] && $r['soporte'] === $plan['soporte'], 'fases, cronograma, insumos, reuniones y soporte pasan intactos');
ok($r['portal_url'] === 'https://automatizatech.cl/?crm_view=timeline&cid=7&token=abc123', 'enlace al portal del cliente');
ok($r['agenda']['whatsapp_url'] === 'https://wa.me/56927002984?text=Hola%20Tech%2C%20quiero%20agendar%20la%20llamada%20de%20seguimiento%20de%20mi%20plan%20de%20trabajo%20%28c%C3%B3digo%20Ab3dE5fG7hJ9%29', 'enlace de WhatsApp con Tech con el mensaje y el código del plan');
ok($r['agenda']['web_url'] === '', 'agenda web vacía en la Etapa 1');
$f = at_pt_armar_render($plan, $datos, true);
ok($f['draft'] === false && $f['image_briefs'] === $plan['image_briefs'] && count($f['image_briefs']) === 10, 'versión final: draft false y los 10 briefs del plan');
ok(is_array(json_decode(json_encode($f), true)) && json_decode(json_encode($f))->images == new stdClass(), 'el cuerpo se codifica y decodifica como JSON');

// Review Focus 4: contrato sin propuesta y cliente sin correo (sin portal).
$sin = at_pt_armar_render($plan, array_merge($datos, ['portal_url' => '']), true);
ok($sin['portal_url'] === '' && $sin['fases'] !== [] && $sin['cronograma']['barras'] !== [], 'sin portal: portal_url vacío y el documento se arma igual');
ok(at_pt_costo_fotos($sin['image_briefs'], false)['fotos'] === 10 && in_array('cover', array_column($sin['image_briefs'], 'slide'), true) && in_array('cierre', array_column($sin['image_briefs'], 'slide'), true), 'sin propuesta: portada y cierre van con foto nueva (10 fotos)');
ok(at_pt_armar_render($plan, array_merge($datos, ['portal_url' => 'javascript:alert(1)']), false)['portal_url'] === '' && at_pt_armar_render($plan, array_merge($datos, ['portal_url' => 'https://x.cl/a b']), false)['portal_url'] === '', 'un portal que no es http(s) o trae espacios se descarta');
ok(at_pt_armar_render($plan, array_merge($datos, ['whatsapp' => '+56 9 2700 2984']), false)['agenda']['whatsapp_url'] === $r['agenda']['whatsapp_url'] && at_pt_armar_render($plan, array_merge($datos, ['whatsapp' => '9 2700 2984']), false)['agenda']['whatsapp_url'] === $r['agenda']['whatsapp_url'], 'el número de WhatsApp se normaliza (con +56, espacios o 9 dígitos)');
ok(at_pt_armar_render($plan, array_merge($datos, ['whatsapp' => '']), false)['agenda']['whatsapp_url'] === '', 'sin número de WhatsApp no hay enlace');
$sin_nombre = $plan;
$sin_nombre['proyecto'] = '';
ok(at_pt_armar_render($sin_nombre, $datos, false)['proyecto'] === '[PRUEBA] Empresa de prueba' && at_pt_armar_render($sin_nombre, array_merge($datos, ['company_name' => '']), false)['proyecto'] === 'Tu proyecto', 'sin nombre de proyecto: el de la empresa, o «Tu proyecto»');
ok(at_pt_armar_render($plan, array_merge($datos, ['company_name' => '']), false)['company_name'] === 'Cliente Prueba' && at_pt_armar_render($sin_nombre, array_merge($datos, ['company_name' => '', 'client_name' => '']), false)['company_name'] === 'Tu proyecto', 'persona natural sin empresa: company_name = el cliente o el proyecto (el renderer lo exige no vacío)');
// Decisión D8: la garantía del contrato firmado manda sobre la que trae el plan.
$con_garantia = at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => 6]), true);
ok($plan['soporte']['garantia_meses'] === 3 && $con_garantia['soporte']['garantia_meses'] === 6 && $con_garantia['soporte']['mensuales'] === $plan['soporte']['mensuales'], 'garantía del contrato (6 meses) en vez de la del plan (3); los mensuales no cambian');
ok(at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => '12']), false)['soporte']['garantia_meses'] === 12, 'la garantía del contrato también llega como texto de dígitos (placeholder del contrato)');
$cero = at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => 0]), true)['soporte'];
ok($plan['soporte']['garantia_meses'] === 3 && $cero === ['garantia_meses' => 0, 'mensuales' => $plan['soporte']['mensuales']] && at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => '0']), false)['soporte']['garantia_meses'] === 0, 'contrato sin garantía (0, número o texto): el documento dice 0 aunque el plan diga 3');
$sin_garantia = array_map(fn($g) => at_pt_armar_render($plan, array_merge($datos, ['garantia_meses' => $g]), false)['soporte'], [-2, 'tres', '', null, 2.5]);
ok($sin_garantia === array_fill(0, 5, $plan['soporte']), 'garantía del contrato negativa, no entera o vacía: queda la del plan');
ok(!array_key_exists('garantia_meses', $datos) && $r['soporte'] === $plan['soporte'], 'sin la clave garantia_meses en los datos: queda la del plan');
ok(at_pt_armar_render([], ['garantia_meses' => 6], false)['soporte'] === ['garantia_meses' => 6, 'mensuales' => []], 'plan sin soporte: la garantía del contrato igual llega al documento');
ok(at_pt_armar_render($plan, array_merge($datos, ['fecha_firma' => '']), false)['fecha_firma_larga'] === '29 de septiembre de 2026', 'sin fecha de firma en los datos: la del plan');
ok(at_pt_armar_render([], ['codigo' => 'Ab3dE5fG7hJ9'], false)['fases'] === [] && at_pt_armar_render([], [], false)['semanas'] === 0, 'un plan vacío no rompe el armado (el renderer lo rechaza por su esquema)');

fin();
