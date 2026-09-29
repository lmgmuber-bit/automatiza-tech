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

fin();
