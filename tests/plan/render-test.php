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

fin();
