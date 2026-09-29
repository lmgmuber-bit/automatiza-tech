<?php
// Correr: php tests/plan/validacion-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Enumeraciones
ok(at_pt_fases_validas() === ['diseno_desarrollo' => 'Diseño y desarrollo', 'implementacion' => 'Implementación', 'soporte' => 'Soporte y mejora continua'], 'fases en su orden fijo y con su título fijo');
ok(at_pt_responsables() === ['at' => 'AutomatizaTech', 'cliente' => 'Tú', 'ambos' => 'Ambos'], 'responsables');
ok(array_keys(at_pt_etapas()) === ['arranque', 'diseno', 'desarrollo', 'pruebas', 'implementacion', 'soporte', ''], 'etapas, incluida la vacía');
ok(array_keys(at_pt_origenes()) === ['tabla', 'ia', 'luis'], 'orígenes de la duración');
ok(at_pt_slides_foto() === ['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre'], 'láminas que llevan foto');
ok(at_pt_etapas_tabla() === ['diseno', 'desarrollo', 'pruebas', 'implementacion'], 'columnas de la tabla de tiempos');

// Entero exacto
ok(at_pt_entero(5) === 5 && at_pt_entero('5') === 5 && at_pt_entero(' 12 ') === 12 && at_pt_entero(3.0) === 3 && at_pt_entero('-2') === -2, 'entero desde número o texto de dígitos');
ok(at_pt_entero(3.5) === null && at_pt_entero('3,5') === null && at_pt_entero('') === null && at_pt_entero(null) === null && at_pt_entero(true) === null && at_pt_entero([]) === null && at_pt_entero('diez') === null, 'lo que no es un entero exacto es null');

// Tabla de tiempos
$def = at_pt_duraciones_defecto();
ok(array_keys($def) === ['sitio_una_pagina', 'sitio_web_tienda', 'asistente_basico', 'asistente_avanzado', 'plataforma', 'automatizacion_n8n', 'google_ads'], 'tabla por defecto: siete servicios');
ok($def['plataforma'] === ['nombre' => 'Plataforma o sistema a medida', 'diseno' => 8, 'desarrollo' => 20, 'pruebas' => 5, 'implementacion' => 3], 'plataforma: 8 + 20 + 5 + 3 días hábiles');
ok(at_pt_normalizar_duraciones($def) === $def, 'la tabla por defecto ya viene normalizada');
$sucia = [
	'sitio_una_pagina' => ['nombre' => ' Sitio de una página ', 'diseno' => '3', 'desarrollo' => 5, 'pruebas' => 2, 'implementacion' => 1, 'extra' => 'x'],
	'Con Mayúsculas'   => ['nombre' => 'X', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'a'                => ['nombre' => 'Clave de una letra', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'sin_nombre'       => ['nombre' => '', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'nombre_largo'     => ['nombre' => str_repeat('n', 61), 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'dias_61'          => ['nombre' => 'Muchos días', 'diseno' => 61, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'dias_negativos'   => ['nombre' => 'Negativo', 'diseno' => -1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'dias_decimales'   => ['nombre' => 'Decimal', 'diseno' => 2.5, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
	'falta_pruebas'    => ['nombre' => 'Incompleta', 'diseno' => 1, 'desarrollo' => 1, 'implementacion' => 1],
	'no_es_fila'       => 'texto',
	'google_ads'       => ['nombre' => 'Google Ads', 'diseno' => 0, 'desarrollo' => 3, 'pruebas' => 0, 'implementacion' => 60],
];
ok(at_pt_normalizar_duraciones($sucia) === [
	'sitio_una_pagina' => ['nombre' => 'Sitio de una página', 'diseno' => 3, 'desarrollo' => 5, 'pruebas' => 2, 'implementacion' => 1],
	'google_ads'       => ['nombre' => 'Google Ads', 'diseno' => 0, 'desarrollo' => 3, 'pruebas' => 0, 'implementacion' => 60],
], 'normalizar: descarta claves, nombres y días inválidos; acepta 0 y 60; quita los campos de más');
$lista = [
	['clave' => 'nuevo_servicio', 'nombre' => 'Nuevo servicio', 'diseno' => 1, 'desarrollo' => 2, 'pruebas' => 1, 'implementacion' => 1],
	['clave' => 'nuevo_servicio', 'nombre' => 'Repetido', 'diseno' => 9, 'desarrollo' => 9, 'pruebas' => 9, 'implementacion' => 9],
	['nombre' => 'Sin clave', 'diseno' => 1, 'desarrollo' => 1, 'pruebas' => 1, 'implementacion' => 1],
];
ok(at_pt_normalizar_duraciones($lista) === ['nuevo_servicio' => ['nombre' => 'Nuevo servicio', 'diseno' => 1, 'desarrollo' => 2, 'pruebas' => 1, 'implementacion' => 1]], 'normalizar una lista del formulario: gana la primera clave repetida y sin clave no entra');
ok(at_pt_normalizar_duraciones([]) === [], 'tabla vacía');

// Arranque fijo
$arr = at_pt_arranque();
ok($arr['nombre'] === 'Arranque' && $arr['entrega'] === false && $arr['entregable'] === '' && count($arr['actividades']) === 2, 'arranque: un bloque sin entrega con dos actividades');
ok($arr['actividades'][0]['nombre'] === 'Reunión de inicio' && $arr['actividades'][0]['responsable'] === 'ambos' && $arr['actividades'][0]['dias_habiles'] === 1, 'arranque: reunión de inicio, ambos, 1 día hábil');
ok($arr['actividades'][1]['nombre'] === 'Entrega de logo, textos y accesos' && $arr['actividades'][1]['responsable'] === 'cliente' && $arr['actividades'][1]['dias_habiles'] === 3, 'arranque: insumos del cliente, 3 días hábiles');
ok($arr['actividades'][0]['etapa'] === 'arranque' && $arr['actividades'][1]['origen'] === 'tabla' && $arr['actividades'][1]['en_paralelo'] === false, 'arranque: etapa arranque, origen tabla, sin paralelo');
ok(array_keys($arr['actividades'][0]) === ['nombre', 'detalle', 'responsable', 'dias_habiles', 'servicio', 'etapa', 'origen', 'en_paralelo', 'desde', 'hasta'], 'actividad con las diez claves del contrato, en orden');

// Review Focus 2: del texto de la IA solo sale un plan si es un objeto JSON; lo demás es null y no llega a validarse.
ok(at_pt_plan_de_json('esto no es JSON') === null && at_pt_plan_de_json('{"fases": [') === null && at_pt_plan_de_json('[1, 2]') === null && at_pt_plan_de_json('[{"proyecto": "X"}]') === null && at_pt_plan_de_json('{}') === null && at_pt_plan_de_json('') === null, 'JSON inválido, una lista (aunque envuelva un objeto) u objeto vacío: null');
ok(at_pt_plan_de_json("```json\n{\"proyecto\": \"X\", \"fases\": []}\n```") === ['proyecto' => 'X', 'fases' => []], 'JSON con cerco ```json');
ok(at_pt_plan_de_json("Aquí va el plan:\n{\"proyecto\": \"X\"}\nSaludos") === ['proyecto' => 'X'] && at_pt_plan_de_json("\xEF\xBB\xBF{\"proyecto\": \"Y\"}") === ['proyecto' => 'Y'], 'JSON con texto alrededor o con BOM');

// Ayudas para comparar nombres y leer sí o no
ok(at_pt_clave_nombre("  Diseño \t Web ") === 'diseño web' && at_pt_clave_nombre('ARRANQUE') === 'arranque', 'nombres para comparar: minúsculas y espacios simples');
ok(at_pt_booleano(true) && at_pt_booleano(1) && at_pt_booleano('Sí') && at_pt_booleano(' on ') && at_pt_booleano('true'), 'sí: true, 1, «Sí», «on» y «true»');
ok(!at_pt_booleano(false) && !at_pt_booleano(0) && !at_pt_booleano(2) && !at_pt_booleano('no') && !at_pt_booleano(null) && !at_pt_booleano([1]), 'no: false, 0, 2, «no», null o una lista');

// Valores mostrados en un mensaje de error
ok(at_pt_mostrar(null) === 'vacío' && at_pt_mostrar('') === 'vacío' && at_pt_mostrar(false) === 'false' && at_pt_mostrar([1]) === 'una lista' && at_pt_mostrar(2.5) === '2.5' && at_pt_mostrar("dos\n  líneas") === 'dos líneas', 'valores de la IA mostrados cortos y en una línea');
ok(at_pt_mostrar(str_repeat('x', 50)) === str_repeat('x', 39) . '…' && at_pt_mostrar("\xFF") === 'texto ilegible', 'lo largo se corta a 40 caracteres y lo que no es UTF-8 no se muestra');

// Texto limpio con tope: se acorta con aviso hasta 4 veces el tope; más largo es error
$e = [];
$w = [];
ok(at_pt_texto("  Hola\t mundo \x07 ", 20, 'Campo', $e, $w) === 'Hola mundo' && $e === [] && $w === [], 'una línea: sin caracteres de control ni espacios de más');
ok(at_pt_texto("Uno\r\n\r\n\r\nDos ", 20, 'Campo', $e, $w, true) === "Uno\n\nDos" && at_pt_texto(42, 20, 'Campo', $e, $w) === '42' && at_pt_texto(null, 20, 'Campo', $e, $w) === '' && $e === [], 'multilínea: conserva el párrafo; un número pasa a texto; null queda vacío');
ok(at_pt_texto(str_repeat('a', 25), 20, 'Campo', $e, $w) === str_repeat('a', 19) . '…' && $w === ['Campo: se acortó a 20 caracteres.'], 'hasta 4 veces el tope: se acorta con «…» y aviso');
ok(at_pt_texto(str_repeat('a', 81), 20, 'Campo', $e, $w) === '' && $e === ['Campo: el texto es demasiado largo (81 caracteres; máximo 20).'], 'más de 4 veces el tope: error legible');
$e = [];
ok(at_pt_texto(['x'], 20, 'Campo', $e, $w) === '' && at_pt_texto("\xFF", 20, 'Campo', $e, $w) === '' && $e === ['Campo: no es texto.', 'Campo: el texto no es UTF-8 válido.'], 'una lista o bytes que no son UTF-8: error');

// Listas de siempre y topes de la carta Gantt
ok(count(at_pt_necesitamos_defecto()) === 3 && array_column(at_pt_reuniones_defecto(), 'nombre') === ['Reunión de inicio', 'Llamada de seguimiento del plan', 'Entrega y capacitación'], 'listas de siempre para lo que la IA deje vacío');
ok(AT_PT_MAX_BLOQUES === 14 && AT_PT_MAX_ACTIVIDADES_BLOQUE === 10 && AT_PT_MAX_ACTIVIDADES === 60 && AT_PT_MAX_DIAS_PLAN === 130 && AT_PT_DIAS_REVISION === 5, 'topes de la carta Gantt y 5 días hábiles de revisión (cláusula 6.1)');

// Días hábiles de un bloque en secuencia (sin su revisión)
$paralelas = at_pt_dias_bloque(['actividades' => [
	['dias_habiles' => 5], ['dias_habiles' => 2, 'en_paralelo' => true], ['dias_habiles' => 1], ['dias_habiles' => 3, 'en_paralelo' => true],
]]);
ok($paralelas === 8, 'días de un bloque con paralelas: 5 (con 2 en paralelo) + 3 (con 1 en paralelo) = 8');
ok(at_pt_dias_bloque(['actividades' => [['dias_habiles' => 1, 'en_paralelo' => true], ['dias_habiles' => 2]]]) === 3 && at_pt_dias_bloque(['actividades' => []]) === 0, 'la primera nunca es paralela; bloque vacío: 0');

// Validación del plan: un plan como lo devuelve la IA, con desorden y detalles que hay que normalizar.
$ia = [
	'proyecto'    => '  [PRUEBA] Sitio web de Cliente Prueba ',
	'fecha_firma' => '2026-09-29 18:40:12',
	'fases'       => [
		['clave' => 'soporte', 'titulo' => 'Otro título', 'bloques' => [
			['nombre' => 'Garantía', 'actividades' => [
				['nombre' => 'Ajustes menores', 'responsable' => 'at', 'dias_habiles' => 10, 'etapa' => 'soporte'],
			]],
		]],
		['clave' => 'diseno_desarrollo', 'descripcion' => "Diseñamos y construimos tu sitio.\r\n\r\n\r\nTú apruebas cada avance.\t", 'bloques' => [
			['nombre' => 'Diseño', 'entregable' => 'Maqueta aprobada', 'entrega' => true, 'actividades' => [
				['nombre' => 'Propuesta de diseño', 'responsable' => 'AT', 'dias_habiles' => '3', 'servicio' => 'sitio_web_tienda', 'etapa' => 'diseno', 'en_paralelo' => true],
				['nombre' => 'Ajustes de diseño', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'Sitio_Web_Tienda', 'etapa' => 'DISENO', 'origen' => 'inventado', 'en_paralelo' => 'sí'],
			]],
			['nombre' => 'Bloque vacío', 'actividades' => []],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [
				['nombre' => 'Publicación del sitio', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_web_tienda', 'etapa' => 'implementacion', 'origen' => 'tabla'],
			]],
		]],
	],
	'hitos' => [
		['nombre' => 'Diseño aprobado', 'despues_de' => ' diseño '],
		['nombre' => 'Hito fantasma', 'despues_de' => 'No existe'],
		['nombre' => 'Entrega estimada', 'despues_de' => 'Puesta en marcha'],
	],
	'necesitamos_de_ti' => [],
	'reuniones'         => ['Reunión de inicio', ['nombre' => 'Entrega y capacitación', 'detalle' => 'Una hora por videollamada']],
	'soporte'           => ['garantia_meses' => '3', 'mensuales' => ['Google Ads: gestión mensual']],
	'image_briefs'      => [
		['slide' => 'metodo', 'prompt' => "team of a small bakery  kneading dough together,\n warm light"],
		['slide' => 'metodo', 'prompt' => 'otra foto para la misma lámina'],
		['slide' => 'inventada', 'prompt' => 'x'],
		['slide' => 'gantt', 'prompt' => ''],
		['slide' => 'fase_1', 'prompt' => str_repeat('a', 1201)],
	],
	'cronograma' => ['basura' => true],
];
$v = at_pt_validar_plan($ia);
$p = $v['plan'];
ok($v['ok'] === true && $v['errores'] === [], 'plan de la IA con desorden: válido y sin errores');
ok(array_column($p['fases'], 'clave') === ['diseno_desarrollo', 'implementacion', 'soporte'], 'fases en su orden fijo aunque vengan desordenadas');
ok(array_column($p['fases'], 'titulo') === ['Diseño y desarrollo', 'Implementación', 'Soporte y mejora continua'], 'títulos fijos por clave (se ignora el de la IA)');
ok(array_column($p['fases'][0]['bloques'], 'nombre') === ['Arranque', 'Diseño'], 'el Arranque va al inicio de la primera fase y el bloque vacío se quita');
ok($p['fases'][0]['bloques'][0] === at_pt_arranque(), 'el bloque Arranque es el fijo');
ok($p['proyecto'] === '[PRUEBA] Sitio web de Cliente Prueba' && $p['version'] === 1, 'proyecto recortado y versión 1');
ok($p['fecha_firma'] === '2026-09-29' && $p['fecha_inicio'] === '', 'fecha de firma sin hora; fecha de inicio vacía hasta calcular');
ok($p['fases'][0]['descripcion'] === "Diseñamos y construimos tu sitio.\n\nTú apruebas cada avance.", 'descripción: conserva el párrafo, sin \\r, sin tabulador y sin líneas vacías de más');
$d1 = $p['fases'][0]['bloques'][1]['actividades'][0];
$d2 = $p['fases'][0]['bloques'][1]['actividades'][1];
ok($d1['responsable'] === 'at' && $d1['dias_habiles'] === 3 && $d1['origen'] === 'ia', 'responsable «AT» -> at; días «3» -> 3; sin origen -> ia');
ok($d1['en_paralelo'] === false && $d2['en_paralelo'] === true, 'la primera actividad de un bloque nunca es paralela; «sí» es paralela');
ok($d2['servicio'] === 'sitio_web_tienda' && $d2['etapa'] === 'diseno' && $d2['origen'] === 'ia', 'servicio y etapa en minúsculas; origen inventado -> ia');
ok($p['fases'][1]['bloques'][0]['actividades'][0]['origen'] === 'tabla', 'un origen válido se conserva');
ok($p['fases'][0]['bloques'][1]['entrega'] === true && $p['fases'][1]['bloques'][0]['entrega'] === false && $p['fases'][0]['bloques'][1]['entregable'] === 'Maqueta aprobada', 'entrega y entregable');
ok($d1['desde'] === '' && $d1['hasta'] === '' && $p['cronograma'] === [], 'desde, hasta y cronograma vacíos hasta calcular (se ignora el cronograma que mande la IA)');
ok($p['hitos'] === [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño', 'fecha' => '']], 'hitos: el nombre exacto del bloque; fuera el que apunta a un bloque que no existe y la «Entrega estimada» (la pone el cronograma)');
ok(in_array('El hito «Hito fantasma» se descartó: no existe el bloque «No existe».', $v['avisos'], true), 'aviso legible del hito descartado');
ok($p['necesitamos_de_ti'] === at_pt_necesitamos_defecto() && in_array('«Qué necesitamos de ti» venía vacío: se usó la lista de siempre (logo, textos y accesos).', $v['avisos'], true), 'qué necesitamos de ti vacío: lista de siempre, con aviso');
ok($p['reuniones'] === [['nombre' => 'Reunión de inicio', 'detalle' => ''], ['nombre' => 'Entrega y capacitación', 'detalle' => 'Una hora por videollamada']], 'reuniones como texto o como objeto');
ok($p['soporte'] === ['garantia_meses' => 3, 'mensuales' => ['Google Ads: gestión mensual']], 'soporte: garantía entera y mensuales como texto');
ok($p['image_briefs'] === [['slide' => 'metodo', 'prompt' => 'team of a small bakery kneading dough together, warm light']], 'fotos: una por lámina válida, sin vacías ni de más de 1.200 caracteres');
ok(count(array_filter($v['avisos'], fn($a) => strpos($a, 'Se descartó') === 0)) === 4, 'cuatro avisos de fotos descartadas (repetida, lámina inventada, sin descripción y demasiado larga)');
ok(array_keys($p) === ['version', 'proyecto', 'fecha_firma', 'fecha_inicio', 'fases', 'hitos', 'necesitamos_de_ti', 'reuniones', 'soporte', 'image_briefs', 'cronograma'], 'claves del plan normalizado, en orden');
ok(at_pt_validar_plan($p)['plan'] === $p, 'validar dos veces da lo mismo (el panel vuelve a guardar sin duplicar el Arranque)');
$con_arranque = $ia;
$con_arranque['fases'][1]['bloques'] = array_merge([['nombre' => ' arranque ', 'actividades' => [['nombre' => 'Kickoff', 'responsable' => 'ambos', 'dias_habiles' => 2]]]], $ia['fases'][1]['bloques']);
ok(array_column(at_pt_validar_plan($con_arranque)['plan']['fases'][0]['bloques'], 'nombre') === ['arranque', 'Diseño'], 'si la primera fase ya trae un bloque «Arranque», no se agrega otro');
$por_clave = at_pt_validar_plan(['proyecto' => 'X', 'fases' => ['implementacion' => ['bloques' => [['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación', 'responsable' => 'at', 'dias_habiles' => 1]]]]]]]);
ok($por_clave['ok'] && $por_clave['plan']['fases'][0]['clave'] === 'implementacion' && $por_clave['plan']['fases'][0]['bloques'][0]['nombre'] === 'Arranque', 'fases como objeto con la clave por nombre; el Arranque va a la primera fase que exista');
$repetida = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [
	['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 5]]]]],
	['clave' => 'soporte', 'bloques' => [['nombre' => 'Mejora continua', 'actividades' => [['nombre' => 'Reunión mensual', 'responsable' => 'ambos', 'dias_habiles' => 1]]]]],
]]);
ok($repetida['ok'] && array_column($repetida['plan']['fases'][0]['bloques'], 'nombre') === ['Arranque', 'Garantía', 'Mejora continua'] && in_array('La fase «Soporte y mejora continua» venía repetida: se juntaron sus bloques.', $repetida['avisos'], true), 'una fase repetida se junta con aviso');
$etiquetas = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'Implementación', 'bloques' => [['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación', 'responsable' => 'Tú', 'dias_habiles' => 1], ['nombre' => 'Capacitación', 'responsable' => 'AutomatizaTech', 'dias_habiles' => 1]]]]]]]);
ok($etiquetas['ok'] && $etiquetas['plan']['fases'][0]['clave'] === 'implementacion' && array_column($etiquetas['plan']['fases'][0]['bloques'][1]['actividades'], 'responsable') === ['cliente', 'at'], 'la IA escribe la etiqueta en vez de la clave («Implementación», «Tú», «AutomatizaTech»): se entiende sin error');
ok(at_pt_clave_de(' SOPORTE Y MEJORA CONTINUA ', at_pt_fases_validas()) === 'soporte' && at_pt_clave_de('ambos', at_pt_responsables()) === 'ambos' && at_pt_clave_de('el equipo', at_pt_responsables()) === '' && at_pt_clave_de(3, at_pt_responsables()) === '', 'clave desde la clave o desde la etiqueta; lo demás no calza');

// Review Focus 2: lo que la IA devuelve mal deja el plan en error con un motivo legible y nunca un plan a medias.
ok(at_pt_validar_entrada('esto no es JSON') === ['ok' => false, 'errores' => ['La IA no devolvió un plan en JSON válido (un objeto con fases).'], 'avisos' => [], 'plan' => []] && at_pt_validar_entrada(null)['plan'] === [] && at_pt_validar_entrada(42)['ok'] === false, 'JSON inválido, ausente o de otro tipo: error legible y sin plan');
ok(at_pt_validar_entrada("```json\n" . json_encode($ia) . "\n```")['plan'] === $v['plan'] && at_pt_validar_entrada($ia) === $v, 'el plan en texto (con cerco) o como objeto: lo mismo que validar el objeto');
$arranque_ia = at_pt_validar_entrada($con_arranque, true);
ok(array_column($arranque_ia['plan']['fases'][0]['bloques'], 'nombre') === ['Arranque', 'Diseño'] && $arranque_ia['plan']['fases'][0]['bloques'][0] === at_pt_arranque() && $arranque_ia['avisos'][0] === 'La IA mandó su propio bloque «Arranque»: se usó el fijo (reunión de inicio y entrega de logo, textos y accesos).', 'borrador nuevo: el «Arranque» de la IA se cambia por el fijo, que trae la entrega de insumos de la cláusula 4.2');
$arranque_tarde = $ia;
$arranque_tarde['fases'][2]['bloques'][] = ['nombre' => 'Arranque', 'actividades' => [['nombre' => 'Kickoff', 'responsable' => 'ambos', 'dias_habiles' => 1]]];
$bloques_arranque = 0;
foreach (at_pt_validar_entrada($arranque_tarde, true)['plan']['fases'] as $f) {
	foreach ($f['bloques'] as $b) {
		$bloques_arranque += at_pt_clave_nombre($b['nombre']) === 'arranque' ? 1 : 0;
	}
}
ok($bloques_arranque === 1, 'un «Arranque» de la IA en otra fase tampoco queda: un solo Arranque, al inicio');
$mal = at_pt_validar_plan([]);
ok($mal['ok'] === false && $mal['errores'] === ['El plan no trae fases.'] && $mal['plan'] === [], 'plan vacío: error «El plan no trae fases.» y sin plan');
$mal = at_pt_validar_plan([['clave' => 'diseno_desarrollo']]);
ok($mal['ok'] === false && strpos($mal['errores'][0], 'llegó una lista') !== false && $mal['plan'] === [], 'una lista en vez de un objeto: error legible');
$mal = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'marketing', 'bloques' => []], ['clave' => 'implementacion', 'bloques' => [['nombre' => 'Puesta en marcha', 'actividades' => [['nombre' => 'Publicación', 'responsable' => 'at', 'dias_habiles' => 1]]]]]]]);
ok($mal['ok'] === false && $mal['errores'] === ['Fase desconocida: «marketing» (las válidas son diseno_desarrollo, implementacion y soporte).'] && $mal['plan'] === [], 'fase desconocida: error aunque las demás estén bien');
$dias = function ($d) {
	return at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [['nombre' => 'Maqueta', 'responsable' => 'at', 'dias_habiles' => $d]]]]]]]);
};
ok($dias(0)['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: dice 0 días hábiles (deben ser de 1 a 60).'] && $dias(0)['plan'] === [], 'días 0: error legible y sin plan');
ok($dias(200)['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: dice 200 días hábiles (deben ser de 1 a 60).'], 'días 200: error legible');
ok($dias(2.5)['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: los días hábiles no son un número entero («2.5»; deben ser de 1 a 60).'] && $dias('tres')['ok'] === false && $dias(null)['ok'] === false, 'días con decimales, en palabras o ausentes: error');
ok($dias(1)['ok'] && $dias(60)['ok'] && $dias('60')['ok'], 'días 1 y 60 son válidos');
$resp = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [['nombre' => 'Maqueta', 'responsable' => 'el equipo', 'dias_habiles' => 2]]]]]]]);
ok($resp['errores'] === ['Diseño y desarrollo › Diseño › «Maqueta»: responsable desconocido «el equipo» (debe ser at, cliente o ambos).'], 'responsable desconocido: error legible');
$texto = function ($nombre) {
	return at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [['nombre' => $nombre, 'responsable' => 'at', 'dias_habiles' => 2]]]]]]]);
};
$enorme = $texto(str_repeat('texto roto ', 500));
ok($enorme['ok'] === false && $enorme['errores'] === ['Diseño y desarrollo › Diseño › actividad 1: el texto es demasiado largo (5.499 caracteres; máximo 120).'] && $enorme['plan'] === [], 'texto enorme (más de 4 veces el tope): error legible y sin plan');
$largo = $texto(str_repeat('a', 130));
$nombre_largo = $largo['plan']['fases'][0]['bloques'][1]['actividades'][0]['nombre'];
ok($largo['ok'] && mb_strlen($nombre_largo) === 120 && substr($nombre_largo, -3) === '…' && in_array('Diseño y desarrollo › Diseño › actividad 1: se acortó a 120 caracteres.', $largo['avisos'], true), 'texto algo largo: se acorta a 120 con «…» y aviso');
ok($texto(['una', 'lista'])['errores'] === ['Diseño y desarrollo › Diseño › actividad 1: no es texto.'] && $texto('')['errores'] === ['Diseño y desarrollo › Diseño: la actividad 1 no tiene nombre.'], 'nombre que no es texto o vacío: error');
ok($texto("con\x00control y\ttab")['plan']['fases'][0]['bloques'][1]['actividades'][0]['nombre'] === 'con control y tab', 'caracteres de control fuera');
$vacio = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => []]]]]]);
ok($vacio['ok'] === false && $vacio['errores'] === ['El plan no trae actividades (aparte del arranque).'] && $vacio['plan'] === [], 'ninguna actividad: error legible');
$solo_arranque = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [at_pt_arranque()]]]]);
ok($solo_arranque['ok'] === false && $solo_arranque['errores'] === ['El plan no trae actividades (aparte del arranque).'], 'solo el arranque tampoco es un plan');
$sin_proyecto = at_pt_validar_plan(['fases' => [['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 1]]]]]]]);
ok($sin_proyecto['ok'] && $sin_proyecto['plan']['proyecto'] === '' && $sin_proyecto['avisos'][0] === 'El plan no trae el nombre del proyecto.', 'sin nombre de proyecto: aviso, no error (el render usa el nombre de la empresa)');

// Topes (Review Focus 6): más no cabe legible en la carta Gantt.
$bloque = fn(string $n, int $acts = 1, int $dias = 1, bool $entrega = false) => ['nombre' => $n, 'entrega' => $entrega, 'actividades' => array_map(fn($i) => ['nombre' => "{$n} {$i}", 'responsable' => 'at', 'dias_habiles' => $dias], range(1, $acts))];
$muchos = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("B{$i}"), range(1, 14))]]]);
ok($muchos['ok'] === false && $muchos['errores'] === ['El plan trae 15 bloques contando el arranque (máximo 14): la carta Gantt no cabe legible. Junta bloques o divide el proyecto.'], '15 bloques con el arranque: error');
ok(at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("B{$i}"), range(1, 13))]]])['ok'], '14 bloques con el arranque: válido');
$once = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [$bloque('Grande', 11)]]]]);
ok($once['ok'] === false && $once['errores'] === ['Diseño y desarrollo › Grande: trae 11 actividades (máximo 10 por bloque).'], '11 actividades en un bloque: error');
$sesenta = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("B{$i}", 9), range(1, 7))]]]);
ok($sesenta['ok'] === false && $sesenta['errores'] === ['El plan trae 65 actividades (máximo 60).'], '65 actividades con el arranque: error');
$lento = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [$bloque('Largo', 3, 40, true)]]]]);
ok($lento['ok'] === true, '4 de arranque + 3 × 40 + 5 de revisión = 129 días hábiles: válido');
$muy_lento = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [$bloque('Largo', 3, 40, true), $bloque('Extra', 1, 2)]]]]);
ok($muy_lento['errores'] === ['El plan suma 131 días hábiles con las revisiones (máximo 130, unas 26 semanas): acórtalo o divide el proyecto.'], '131 días hábiles: error');

// Una respuesta desbordada de la IA no llena la nota del plan: hasta 25 mensajes de cada tipo.
$desborde = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 1]]]]]], 'image_briefs' => array_fill(0, 1500, ['slide' => 'inventada', 'prompt' => 'x'])]);
ok($desborde['ok'] && count($desborde['avisos']) === 26 && end($desborde['avisos']) === '… y 1.477 avisos más.', '1.502 avisos: quedan los 25 primeros y «… y 1.477 avisos más.»');
$ceros = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => array_map(fn($i) => $bloque("C{$i}", 10, 0), range(1, 3))]]]);
ok(!$ceros['ok'] && count($ceros['errores']) === 26 && end($ceros['errores']) === '… y 5 errores más.' && $ceros['plan'] === [], '30 errores: quedan los 25 primeros y «… y 5 errores más.»');

// Reparto proporcional de la tabla de tiempos, con resto
ok(at_pt_repartir(5, [2, 2, 2]) === [2, 2, 1], '5 días entre tres actividades de 2: cuotas 1,67; los 2 días que sobran van a las primeras -> 2, 2 y 1');
ok(at_pt_repartir(10, [3, 1]) === [8, 2], '10 días con pesos 3 y 1: cuotas 7,5 y 2,5; el empate de fracciones lo gana la primera -> 8 y 2');
ok(at_pt_repartir(10, [4, 4, 1]) === [5, 4, 1], '10 días con pesos 4, 4 y 1: cuotas 4,44, 4,44 y 1,11 -> 5, 4 y 1');
ok(at_pt_repartir(20, [5, 10, 5]) === [5, 10, 5], 'si los pesos ya suman el total, quedan iguales');
ok(at_pt_repartir(8, [1, 1, 1]) === [3, 3, 2], '8 días entre tres iguales: 3, 3 y 2');
ok(at_pt_repartir(4, [1, 1, 100]) === [1, 1, 2], 'mínimo 1: a las chicas les toca 1 y a la grande el resto');
ok(at_pt_repartir(2, [5, 5, 5]) === [1, 1, 1] && at_pt_repartir(0, [3]) === [1], 'total menor que la cantidad (o cero): 1 a cada una');
ok(at_pt_repartir(7, []) === [], 'sin actividades, nada');
ok(at_pt_repartir(5, [3, 2, 1]) === [2, 2, 1], 'diseño = 5 con la IA en 3, 2 y 1: la que subió al mínimo de 1 no se lleva el día que sobra -> 2, 2 y 1');
$suma_ok = true;
foreach ([[13, [1, 2, 3, 4]], [60, [60, 1, 1]], [9, [7, 7, 7, 7, 7, 7, 7, 7]], [31, [2, 9, 4]], [59, [1, 1, 1, 60]]] as [$t, $w]) {
	$r = at_pt_repartir($t, $w);
	$suma_ok = $suma_ok && array_sum($r) === $t && min($r) >= 1;
}
ok($suma_ok, 'el reparto siempre suma el total de la tabla y ninguna actividad queda en 0');
$monotono = true;
foreach ([[5, [3, 2, 1]], [5, [28, 17, 11]], [71, [2, 16, 1, 42, 39]], [18, [58, 56, 52, 13, 27, 33, 34, 23, 59, 36]]] as [$t, $w]) {
	$r = at_pt_repartir($t, $w);
	foreach ($w as $i => $wi) {
		foreach ($w as $j => $wj) {
			$monotono = $monotono && !($wi > $wj && $r[$i] < $r[$j]);
		}
	}
}
ok($monotono, 'proporcional: a la que la IA le estimó más días nunca le tocan menos que a otra');

// Tabla -> días en un plan: por par (servicio, etapa) en todo el plan
$tab = at_pt_duraciones_defecto();
$base = at_pt_validar_plan([
	'proyecto' => '[PRUEBA] Tienda de Cliente Prueba',
	'fases' => [
		['clave' => 'diseno_desarrollo', 'bloques' => [
			['nombre' => 'Diseño', 'entrega' => true, 'actividades' => [
				['nombre' => 'Propuesta de diseño', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'sitio_web_tienda', 'etapa' => 'diseno'],
				['nombre' => 'Ajustes de diseño', 'responsable' => 'at', 'dias_habiles' => 2, 'servicio' => 'sitio_web_tienda', 'etapa' => 'diseno'],
			]],
			['nombre' => 'Desarrollo', 'actividades' => [
				['nombre' => 'Maquetación', 'responsable' => 'at', 'dias_habiles' => 4, 'servicio' => 'sitio_web_tienda', 'etapa' => 'desarrollo'],
				['nombre' => 'Carrito de compras', 'responsable' => 'at', 'dias_habiles' => 4, 'servicio' => 'sitio_web_tienda', 'etapa' => 'desarrollo'],
				['nombre' => 'Integración con WhatsApp', 'responsable' => 'at', 'dias_habiles' => 3, 'servicio' => 'asistente_basico', 'etapa' => 'desarrollo', 'en_paralelo' => true],
				['nombre' => 'Carga de productos', 'responsable' => 'cliente', 'dias_habiles' => 3, 'servicio' => 'sitio_web_tienda'],
			]],
			['nombre' => 'Pagos', 'actividades' => [
				['nombre' => 'Pasarela de pago', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_web_tienda', 'etapa' => 'desarrollo'],
				['nombre' => 'Diseño de la app', 'responsable' => 'at', 'dias_habiles' => 6, 'servicio' => 'app_movil', 'etapa' => 'diseno', 'origen' => 'tabla'],
			]],
			['nombre' => 'Pruebas', 'entrega' => true, 'actividades' => [
				['nombre' => 'Pruebas en celular', 'responsable' => 'at', 'dias_habiles' => 7, 'servicio' => 'sitio_web_tienda', 'etapa' => 'pruebas', 'origen' => 'luis'],
				['nombre' => 'Pruebas de pago', 'responsable' => 'ambos', 'dias_habiles' => 2, 'servicio' => 'sitio_web_tienda', 'etapa' => 'pruebas'],
			]],
		]],
		['clave' => 'implementacion', 'bloques' => [
			['nombre' => 'Puesta en marcha', 'actividades' => [
				['nombre' => 'Publicación', 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => 'sitio_web_tienda', 'etapa' => 'implementacion'],
				['nombre' => 'Capacitación', 'responsable' => 'ambos', 'dias_habiles' => 1, 'etapa' => 'implementacion'],
			]],
		]],
		['clave' => 'soporte', 'bloques' => [
			['nombre' => 'Garantía', 'actividades' => [
				['nombre' => 'Ajustes menores', 'responsable' => 'at', 'dias_habiles' => 10, 'servicio' => 'sitio_web_tienda', 'etapa' => 'soporte'],
			]],
		]],
	],
])['plan'];
function act(array $plan, string $nombre): ?array {
	foreach ($plan['fases'] as $f) {
		foreach ($f['bloques'] as $b) {
			foreach ($b['actividades'] as $a) {
				if ($a['nombre'] === $nombre) {
					return $a;
				}
			}
		}
	}
	return null;
}
$t = at_pt_aplicar_tabla($base, $tab);
$dias_origen = fn(string $n) => [act($t, $n)['dias_habiles'], act($t, $n)['origen']];
ok($dias_origen('Propuesta de diseño') === [3, 'tabla'] && $dias_origen('Ajustes de diseño') === [2, 'tabla'], 'sitio web o tienda, diseño = 5: la IA dijo 2 y 2 -> 3 y 2, origen tabla');
ok($dias_origen('Maquetación') === [5, 'tabla'] && $dias_origen('Carrito de compras') === [4, 'tabla'] && $dias_origen('Pasarela de pago') === [1, 'tabla'], 'desarrollo = 10 repartido entre bloques distintos: 4, 4 y 1 -> 5, 4 y 1');
ok($dias_origen('Integración con WhatsApp') === [4, 'tabla'], 'asistente básico, desarrollo = 4 aunque la IA dijo 3');
ok($dias_origen('Publicación') === [2, 'tabla'], 'sitio web o tienda, implementación = 2');
ok($dias_origen('Carga de productos') === [3, 'ia'] && $dias_origen('Capacitación') === [1, 'ia'] && $dias_origen('Ajustes menores') === [10, 'ia'], 'sin etapa, sin servicio o etapa de soporte: días de la IA, origen ia');
ok($dias_origen('Diseño de la app') === [6, 'ia'], 'servicio que no está en la tabla: origen ia aunque la IA diga tabla');
ok($dias_origen('Pruebas en celular') === [7, 'luis'] && $dias_origen('Pruebas de pago') === [2, 'ia'], 'Review Focus 3: el par con días de Luis no se toca (7 de Luis y 2 de la IA quedan)');
ok($dias_origen('Reunión de inicio') === [1, 'tabla'] && $dias_origen('Entrega de logo, textos y accesos') === [3, 'tabla'], 'el Arranque fijo no cambia');
ok(at_pt_aplicar_tabla($t, $tab) === $t, 'aplicar la tabla dos veces da lo mismo');
$tab_luis = $tab;
$tab_luis['sitio_web_tienda']['diseno'] = 8;
$t2 = at_pt_aplicar_tabla($base, $tab_luis);
ok(act($t2, 'Propuesta de diseño')['dias_habiles'] === 4 && act($t2, 'Ajustes de diseño')['dias_habiles'] === 4, 'con la tabla corregida por Luis (diseño = 8): 4 y 4');
unset($tab_luis['asistente_basico']);
ok(act(at_pt_aplicar_tabla($base, $tab_luis), 'Integración con WhatsApp') === array_merge(act($base, 'Integración con WhatsApp'), ['origen' => 'ia']), 'si Luis quita un servicio de la tabla, sus actividades quedan con los días de la IA y origen ia');
ok(act(at_pt_aplicar_tabla($base, []), 'Maquetación')['origen'] === 'ia' && act(at_pt_aplicar_tabla($base, []), 'Maquetación')['dias_habiles'] === 4, 'tabla vacía: todo queda de la IA');

// Review Focus 6: la tabla puede pasar el tope de días, así que el plan se valida otra vez después de aplicarla.
$por_servicio = fn(string $bloque, string $etapa, bool $entrega) => ['nombre' => $bloque, 'entrega' => $entrega, 'actividades' => array_map(fn($s) => ['nombre' => "{$bloque} · {$s}", 'responsable' => 'at', 'dias_habiles' => 1, 'servicio' => $s, 'etapa' => $etapa], array_keys($tab))];
$combinado = at_pt_validar_plan(['proyecto' => '[PRUEBA] Proyecto combinado', 'fases' => [
	['clave' => 'diseno_desarrollo', 'bloques' => [$por_servicio('Diseño', 'diseno', true), $por_servicio('Desarrollo', 'desarrollo', true), $por_servicio('Pruebas', 'pruebas', true)]],
	['clave' => 'implementacion', 'bloques' => [$por_servicio('Puesta en marcha', 'implementacion', false)]],
	['clave' => 'soporte', 'bloques' => [['nombre' => 'Garantía', 'actividades' => [['nombre' => 'Ajustes', 'responsable' => 'at', 'dias_habiles' => 10]]]]],
]]);
$tras_tabla = at_pt_validar_plan(at_pt_aplicar_tabla($combinado['plan'], $tab));
ok($combinado['ok'] && !$tras_tabla['ok'] && $tras_tabla['errores'] === ['El plan suma 138 días hábiles con las revisiones (máximo 130, unas 26 semanas): acórtalo o divide el proyecto.'] && $tras_tabla['plan'] === [], 'los siete servicios juntos: 57 días hábiles de la IA pasan a 138 con la tabla; validar otra vez lo deja en error');
ok(at_pt_validar_plan($t)['ok'] && at_pt_validar_plan($t)['plan'] === $t, 'validar después de aplicar la tabla conserva días y orígenes (es idempotente)');

// Review Focus 3: Luis edita días a mano y después se reaplica la tabla o pide cambios a la IA: sus días no se pisan.
function cambiar(array $plan, string $nombre, array $campos): array {
	foreach ($plan['fases'] as $fi => $f) {
		foreach ($f['bloques'] as $bi => $b) {
			foreach ($b['actividades'] as $ai => $a) {
				if ($a['nombre'] === $nombre) {
					$plan['fases'][$fi]['bloques'][$bi]['actividades'][$ai] = array_merge($a, $campos);
				}
			}
		}
	}
	return $plan;
}
$guardado = $t; // plan con la tabla aplicada (bloque anterior); bloques de la fase 1: Arranque, Diseño, Desarrollo, Pagos, Pruebas
ok(at_pt_marcar_ediciones($guardado, $guardado) === $guardado, 'guardar sin cambios no cambia ningún origen');

// 1) En el panel: Maquetación de 5 a 7 días, una actividad nueva y un origen manipulado en el formulario.
$form = cambiar($guardado, 'Maquetación', ['dias_habiles' => 7]);
$form = cambiar($form, 'Propuesta de diseño', ['origen' => 'ia']);
$form['fases'][0]['bloques'][2]['actividades'][] = ['nombre' => 'Sesión de fotos', 'responsable' => 'cliente', 'dias_habiles' => 2, 'origen' => 'tabla'];
$editado = at_pt_marcar_ediciones($guardado, at_pt_validar_plan($form)['plan']);
ok([act($editado, 'Maquetación')['dias_habiles'], act($editado, 'Maquetación')['origen']] === [7, 'luis'], 'días cambiados en el panel: origen luis');
ok(act($editado, 'Sesión de fotos')['origen'] === 'luis', 'actividad nueva en el panel: origen luis');
ok([act($editado, 'Propuesta de diseño')['dias_habiles'], act($editado, 'Propuesta de diseño')['origen']] === [3, 'tabla'], 'días iguales: conserva el origen guardado aunque el formulario diga otro');
ok(act($editado, 'Carrito de compras')['origen'] === 'tabla' && act($editado, 'Reunión de inicio')['origen'] === 'tabla', 'lo que no cambió conserva su origen');

// 2) Se reaplica la tabla: el par (sitio_web_tienda, desarrollo) tiene días de Luis y no se toca.
$retabla = at_pt_aplicar_tabla($editado, $tab);
ok(act($retabla, 'Maquetación')['dias_habiles'] === 7 && act($retabla, 'Carrito de compras')['dias_habiles'] === 4 && act($retabla, 'Pasarela de pago')['dias_habiles'] === 1, 'reaplicar la tabla no pisa los 7 días de Luis ni reparte su grupo');
ok(act($retabla, 'Sesión de fotos')['origen'] === 'luis' && act($retabla, 'Propuesta de diseño')['dias_habiles'] === 3, 'la actividad de Luis sin par sigue siendo de Luis; los demás grupos, igual');

// 3) «Pedir cambios»: la IA cambia los días de Luis, mueve su actividad de bloque, cambia otra e inventa una marca 'luis'.
//    Orden de la Task 7: validar -> respetar días de Luis -> marcar con 'ia' -> validar -> calcular.
$ia2 = cambiar($editado, 'Maquetación', ['dias_habiles' => 3, 'origen' => 'ia']);
$ia2 = cambiar($ia2, 'Carrito de compras', ['dias_habiles' => 6]);
$ia2['fases'][0]['bloques'][2]['actividades'] = array_values(array_filter($ia2['fases'][0]['bloques'][2]['actividades'], fn($a) => $a['nombre'] !== 'Sesión de fotos'));
$ia2['fases'][0]['bloques'][1]['actividades'][] = ['nombre' => 'Sesión de fotos', 'responsable' => 'cliente', 'dias_habiles' => 4, 'origen' => 'ia'];
$ia2['fases'][0]['bloques'][4]['actividades'][] = ['nombre' => 'Revisión de textos', 'responsable' => 'ambos', 'dias_habiles' => 2, 'origen' => 'luis'];
$respetado = at_pt_respetar_dias_luis($editado, at_pt_validar_plan($ia2)['plan']);
ok([act($respetado, 'Maquetación')['dias_habiles'], act($respetado, 'Maquetación')['origen']] === [7, 'luis'], 'la IA cambió los días de Luis: vuelven a 7 y a luis');
ok([act($respetado, 'Sesión de fotos')['dias_habiles'], act($respetado, 'Sesión de fotos')['origen']] === [2, 'luis'], 'la actividad de Luis movida de bloque conserva sus 2 días');
ok(act($respetado, 'Revisión de textos')['origen'] === 'ia', 'la IA no puede crear una marca luis');
ok(act($respetado, 'Carrito de compras')['dias_habiles'] === 6 && act($respetado, 'Pruebas en celular')['dias_habiles'] === 7, 'los demás cambios de la IA se aplican; los días de Luis de antes siguen');
$tras_cambios = at_pt_marcar_ediciones($editado, $respetado, 'ia');
ok(act($tras_cambios, 'Maquetación')['origen'] === 'luis' && act($tras_cambios, 'Maquetación')['dias_habiles'] === 7 && act($tras_cambios, 'Sesión de fotos')['origen'] === 'luis', 'después de marcar, los días de Luis siguen siendo de Luis');
ok(act($tras_cambios, 'Carrito de compras')['origen'] === 'ia' && act($tras_cambios, 'Revisión de textos')['origen'] === 'ia', 'lo que la IA cambió o agregó al pedir cambios queda ia («revisar»), nunca luis');
ok(act($tras_cambios, 'Propuesta de diseño')['origen'] === 'tabla', 'lo que la IA no tocó conserva su origen');
$ronda2 = at_pt_marcar_ediciones($tras_cambios, at_pt_respetar_dias_luis($tras_cambios, at_pt_validar_plan(cambiar($tras_cambios, 'Carrito de compras', ['dias_habiles' => 3]))['plan']), 'ia');
ok(act($ronda2, 'Carrito de compras')['dias_habiles'] === 3 && act($ronda2, 'Carrito de compras')['origen'] === 'ia' && act($ronda2, 'Maquetación')['dias_habiles'] === 7, 'segunda ronda: la IA puede volver a cambiar lo que estimó ella; los 7 días de Luis siguen');
ok(at_pt_marcar_ediciones($editado, $respetado, 'otra') === at_pt_marcar_ediciones($editado, $respetado), 'una marca desconocida vale luis (la del panel)');
$renombrada = cambiar($editado, 'Maquetación', ['nombre' => 'Maquetación responsive', 'dias_habiles' => 3]);
ok(at_pt_luis_perdidas($editado, at_pt_validar_plan($renombrada)['plan']) === ['La IA renombró o quitó «Maquetación», que tenía 7 días hábiles puestos por ti: revísala en el panel.'] && at_pt_luis_perdidas($editado, $respetado) === [], 'la IA renombra o quita una actividad de Luis: aviso con sus días; si siguen todas, sin avisos');

// 4) Nombres repetidos: se reconocen por orden de aparición.
$dup = at_pt_validar_plan(['proyecto' => 'X', 'fases' => [['clave' => 'diseno_desarrollo', 'bloques' => [['nombre' => 'Diseño', 'actividades' => [
	['nombre' => 'Revisión interna', 'responsable' => 'at', 'dias_habiles' => 1],
	['nombre' => 'Revisión interna', 'responsable' => 'at', 'dias_habiles' => 4, 'origen' => 'luis'],
]]]]]])['plan'];
$dup_ia = $dup;
$dup_ia['fases'][0]['bloques'][1]['actividades'][0]['dias_habiles'] = 9;
$dup_ia['fases'][0]['bloques'][1]['actividades'][1]['dias_habiles'] = 9;
$dup_r = at_pt_respetar_dias_luis($dup, $dup_ia)['fases'][0]['bloques'][1]['actividades'];
ok([$dup_r[0]['dias_habiles'], $dup_r[0]['origen'], $dup_r[1]['dias_habiles'], $dup_r[1]['origen']] === [9, 'ia', 4, 'luis'], 'dos «Revisión interna»: solo la segunda (la de Luis) recupera sus días');
ok(array_column(at_pt_recorrer_actividades($dup), 3) === ['reunión de inicio#1', 'entrega de logo, textos y accesos#1', 'revisión interna#1', 'revisión interna#2'], 'claves de actividad: nombre normalizado y número de aparición');

// 5) Borrador nuevo: sin versión anterior, la IA no puede traer marcas luis.
ok(act(at_pt_respetar_dias_luis([], $base), 'Pruebas en celular')['origen'] === 'ia' && act(at_pt_respetar_dias_luis([], $base), 'Pruebas en celular')['dias_habiles'] === 7, 'borrador nuevo: la marca luis de la IA baja a ia y conserva los días');
$borrador = at_pt_aplicar_tabla(at_pt_respetar_dias_luis([], $base), $tab);
ok([act($borrador, 'Pruebas en celular')['dias_habiles'], act($borrador, 'Pruebas en celular')['origen'], act($borrador, 'Pruebas de pago')['dias_habiles'], act($borrador, 'Pruebas de pago')['origen']] === [2, 'tabla', 1, 'tabla'], 'borrador: una marca luis inventada por la IA no bloquea la tabla; el par (sitio web, pruebas) recibe sus 3 días: 2 y 1');

fin();
