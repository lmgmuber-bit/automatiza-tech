<?php
// Correr: php tests/cierre/plantilla-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/cierre-cliente/puras.php';
$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

$md = @file_get_contents(__DIR__ . '/../../Docs/CONTRATO_SERVICIO_DESARROLLO.md');
ok(is_string($md) && $md !== '', 'la plantilla existe');
$md = (string) $md;
ok(strpos($md, "\n## ACEPTACIÓN") !== false, 'tiene la sección ACEPTACIÓN (ahí corta el PDF y dibuja las firmas)');
ok(preg_match('/^##\s+Comparecientes/m', $md) === 1, 'el cuerpo empieza en un título ##');
ok(strpos($md, 'BORRADOR') !== false, 'marcada como borrador para abogado');
$cuerpo = preg_split('/^##\s+ACEPTACI/mu', $md)[0];
preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $cuerpo, $m);
$usadas = array_values(array_unique($m[1]));
$empresa = ['ciudad_firma', 'fecha_firma_larga', 'rut_at', 'representante_at_nombre', 'representante_at_rut', 'domicilio_at', 'email_at', 'ciudad_jurisdiccion'];
// Task 5b: marcadores que no se guardan; ContractService los calcula al generar el PDF.
$derivados = ['comparecencia_cliente'];
$sin_fuente = array_values(array_diff($usadas, at_cc_claves_contrato_servicios(), $empresa, $derivados));
ok($sin_fuente === [], 'todos los marcadores tienen de dónde salir' . ($sin_fuente ? ': ' . implode(', ', $sin_fuente) : ''));
$obligatorios = ['servicios_contratados', 'monto_total', 'forma_pago', 'alcance', 'entregables', 'plazo', 'comparecencia_cliente', 'fases_siguientes', 'propuesta_codigo', 'fecha_aceptacion', 'canal_aceptacion'];
$faltan = array_values(array_diff($obligatorios, $usadas));
ok($faltan === [], 'usa los datos del acuerdo' . ($faltan ? ': faltan ' . implode(', ', $faltan) : ''));

// Task 5b: el párrafo del cliente depende de si es persona natural o empresa.
ok(strpos($cuerpo, '{{comparecencia_cliente}}') !== false, 'el cliente comparece con el párrafo según su tipo');
ok(strpos($md, 'representada por **{{') === false, 'la plantilla ya no fija «representada por» para todo cliente');
ok(strpos($md, "\n{{comparecencia_cliente}}\n") !== false && strpos($md, "**Y POR LA OTRA:**\n{{comparecencia_cliente}}") !== false, 'el párrafo del cliente va solo en su línea, después de «Y POR LA OTRA»');
ok(strpos($md, '15.1. Fases siguientes de la propuesta (precios referenciales que se confirman al iniciar cada una):') !== false, 'cláusula de fases siguientes (15.1 desde la cláusula de IA) con el texto aprobado');
// 27-sep: cláusula de uso de inteligencia artificial (pedido de Luis), antes de la garantía; las siguientes se corren en uno.
ok(strpos($md, "## CLÁUSULA DUODÉCIMA — Uso de inteligencia artificial

12.1. EL PROVEEDOR ejecuta el Proyecto con su equipo de profesionales, que se apoya en herramientas de inteligencia artificial") !== false, 'cláusula duodécima: el equipo de profesionales se apoya en inteligencia artificial');
ok(strpos($md, 'Una persona del equipo de EL PROVEEDOR revisa todo lo que se produce con apoyo de esas herramientas antes de entregarlo') !== false, 'la IA no entrega sola: una persona revisa');
$titulos = preg_match_all('/^## CLÁUSULA ([A-ZÁÉÍÓÚ]+) —/mu', $md, $tt) ? $tt[1] : [];
ok($titulos === ['PRIMERA', 'SEGUNDA', 'TERCERA', 'CUARTA', 'QUINTA', 'SEXTA', 'SÉPTIMA', 'OCTAVA', 'NOVENA', 'DÉCIMA', 'UNDÉCIMA', 'DUODÉCIMA', 'DECIMOTERCERA', 'DECIMOCUARTA', 'DECIMOQUINTA', 'DECIMOSEXTA', 'DECIMOSÉPTIMA'], 'las cláusulas van en orden y sin saltos: ' . implode(', ', $titulos));
foreach ([12 => 'DUODÉCIMA', 13 => 'DECIMOTERCERA', 14 => 'DECIMOCUARTA', 15 => 'DECIMOQUINTA', 16 => 'DECIMOSEXTA', 17 => 'DECIMOSÉPTIMA'] as $n => $t) {
	ok(preg_match('/^## CLÁUSULA ' . $t . ' —[^
]*

' . $n . '\.1\. /mu', $md) === 1, "la cláusula $t numera sus párrafos como $n.x");
}
ok(strpos($md, 'La propuesta incluye además las siguientes fases') === false, 'la 14.1 vieja ya no está');
$nota = '> Nota para la revisión legal: si EL CLIENTE es persona natural, revisar la aplicación de la Ley 19.496 (y de la Ley 20.416 para micro y pequeñas empresas) sobre las cláusulas de responsabilidad, término anticipado, domicilio y jurisdicción.';
$pos_nota = strpos($md, $nota);
$pos_primer_titulo = preg_match('/^##\s/m', $md, $t, PREG_OFFSET_CAPTURE) ? $t[0][1] : false;
ok($pos_nota !== false && $pos_primer_titulo !== false && $pos_nota < $pos_primer_titulo, 'la nota para el abogado va antes del primer ## (no sale en el PDF)');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
