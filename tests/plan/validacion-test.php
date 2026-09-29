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

fin();
