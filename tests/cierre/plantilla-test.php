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
$sin_fuente = array_values(array_diff($usadas, at_cc_claves_contrato_servicios(), $empresa));
ok($sin_fuente === [], 'todos los marcadores tienen de dónde salir' . ($sin_fuente ? ': ' . implode(', ', $sin_fuente) : ''));
$obligatorios = ['servicios_contratados', 'monto_total', 'forma_pago', 'alcance', 'entregables', 'plazo', 'razon_social_cliente', 'representante_cliente_nombre', 'representante_cliente_rut', 'fases_siguientes', 'propuesta_codigo', 'fecha_aceptacion', 'canal_aceptacion'];
$faltan = array_values(array_diff($obligatorios, $usadas));
ok($faltan === [], 'usa los datos del acuerdo' . ($faltan ? ': faltan ' . implode(', ', $faltan) : ''));

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
