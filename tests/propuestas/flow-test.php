<?php
// Correr: php tests/propuestas/flow-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/proposals-flow.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Transiciones
ok(at_propuesta_transicion_valida('borrador', 'ajustando'), 'borrador -> ajustando');
ok(at_propuesta_transicion_valida('borrador', 'generando'), 'borrador -> generando');
ok(!at_propuesta_transicion_valida('borrador', 'sent'), 'borrador no se envía');
ok(!at_propuesta_transicion_valida('generando', 'generando'), 'doble clic en aprobar no repite');
ok(at_propuesta_transicion_valida('lista', 'sent'), 'lista -> sent');
ok(at_propuesta_transicion_valida('error', 'generando'), 'error permite reintentar el final');
ok(!at_propuesta_transicion_valida('desconocido', 'borrador'), 'estado desconocido no transita');

// Candado de envío
ok(at_propuesta_puede_enviarse(null, 'pending'), 'propuesta vieja se envía como siempre');
ok(at_propuesta_puede_enviarse('', 'draft'), 'flujo vacío = vieja');
ok(!at_propuesta_puede_enviarse('v3', 'borrador'), 'v3 en borrador no se envía');
ok(at_propuesta_puede_enviarse('v3', 'lista'), 'v3 lista se envía');

// La API REST no puede mandar 'sent'; eso lo hace solo el panel
ok(!at_propuesta_estado_permitido_por_api('sent'), 'la API no puede pedir sent');
ok(at_propuesta_estado_permitido_por_api('lista'), 'la API sí puede pedir lista');
ok(at_propuesta_estado_permitido_por_api('ajustando'), 'la API sí puede pedir ajustando');

// Precios desde el panel
$p = ['pricing_rows' => [['service' => 'X', 'price_usd' => 0, 'price_label' => 'Por confirmar']], 'pricing_note' => ''];
$p2 = at_propuesta_aplicar_precios($p, [
    ['service' => 'Fase 1', 'price_label' => '$250.000 en 2 pagos'],
    ['service' => '', 'price_label' => '$1'],
    ['service' => 'Soporte', 'price_label' => 'Incluido', 'emphasis' => '1'],
], ' Valores en pesos. ');
ok(count($p2['pricing_rows']) === 2, 'filas vacías se descartan');
ok($p2['pricing_rows'][0] === ['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$250.000 en 2 pagos'], 'fila normal');
ok(($p2['pricing_rows'][1]['emphasis'] ?? false) === true, 'fila destacada');
ok($p2['pricing_note'] === 'Valores en pesos.', 'nota recortada');
ok(at_propuesta_aplicar_precios($p, [], 'n')['pricing_rows'] === $p['pricing_rows'], 'sin filas se conservan las anteriores');

// No se aprueba con precios pendientes
ok(at_propuesta_precios_pendientes([]), 'sin pricing_rows, precios pendientes');
ok(at_propuesta_precios_pendientes(['pricing_rows' => []]), 'pricing_rows vacío, precios pendientes');
ok(at_propuesta_precios_pendientes(['pricing_rows' => [['service' => 'X', 'price_label' => '']]]), 'price_label vacío, pendiente');
ok(at_propuesta_precios_pendientes(['pricing_rows' => [['service' => 'X', 'price_label' => 'Por confirmar']]]), '"Por confirmar" literal, pendiente');
ok(at_propuesta_precios_pendientes(['pricing_rows' => [['service' => 'X', 'price_label' => ' por CONFIRMAR ']]]), '"Por confirmar" sin mayúsculas ni espacios, pendiente');
ok(!at_propuesta_precios_pendientes(['pricing_rows' => [['service' => 'X', 'price_label' => '$250.000 en 2 pagos']]]), 'precio real escrito, no pendiente');
ok(at_propuesta_precios_pendientes(['pricing_rows' => [
    ['service' => 'X', 'price_label' => '$250.000'],
    ['service' => 'Y', 'price_label' => ''],
]]), 'una sola fila sin precio entre varias también bloquea');

// Nadie más cambia precios
$guardado = ['unique_id' => 'abc', 'pricing_rows' => [['service' => 'A', 'price_usd' => 0, 'price_label' => '$1']], 'pricing_note' => 'n', 'challenge_text' => 'viejo'];
$entrante = ['unique_id' => 'otro', 'pricing_rows' => [['service' => 'A', 'price_usd' => 999]], 'pricing_note' => 'inventada', 'challenge_text' => 'nuevo'];
$r = at_propuesta_conservar_precios($guardado, $entrante);
ok($r['pricing_rows'] === $guardado['pricing_rows'], 'precios guardados ganan');
ok($r['pricing_note'] === 'n', 'nota guardada gana');
ok($r['unique_id'] === 'abc', 'unique_id no cambia');
ok($r['challenge_text'] === 'nuevo', 'el resto sí se actualiza');

// Historial de comentarios
$log = at_propuesta_agregar_comentario(null, 'Fase 2 en 2 pagos', '2026-09-23 18:00');
$log = at_propuesta_agregar_comentario($log, 'Ads a $120.000', '2026-09-23 18:05');
$d = json_decode($log, true);
ok(count($d) === 2 && $d[1]['comentario'] === 'Ads a $120.000', 'historial acumula');
ok(count(json_decode(at_propuesta_agregar_comentario('no-json', 'x', 'f'), true)) === 1, 'historial corrupto se reinicia');

// Costo de fotos
$c = at_propuesta_costo_fotos(['image_briefs' => [['slide' => 'cover', 'prompt' => 'a'], ['slide' => 'x'], ['slide' => 'pricing', 'prompt' => 'b']]]);
ok($c === ['fotos' => 2, 'usd_lista' => 0.0064], 'costo = fotos válidas x 0,0032');

// Validación del payload (mismos campos obligatorios que renderer/src/schema.js)
$valido = ['unique_id' => 'a', 'client_name' => 'b', 'company_name' => 'c', 'challenge_title' => 'd', 'challenge_text' => 'e',
    'solution_title' => 'f', 'solution_text' => 'g', 'benefits' => [1], 'how_it_works' => [1], 'pricing_rows' => [1], 'next_steps' => [1]];
ok(at_propuesta_errores_payload($valido) === [], 'payload completo es válido');
$sin = $valido; unset($sin['benefits']); $sin['challenge_text'] = '  ';
ok(count(at_propuesta_errores_payload($sin)) === 2, 'detecta array faltante y texto vacío');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
