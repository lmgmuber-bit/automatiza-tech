<?php
// Uso: php tests/prospeccion/puras-test.php (sin WordPress)
define('ABSPATH', __DIR__);
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/prospeccion/puras.php';
$f = 0;
function ok($c, $m) { global $f; if ($c) { echo "ok   $m\n"; } else { $f++; echo "FALLA $m\n"; } }

ok(at_pro_estados() === ['por_contactar', 'contactado', 'respondio', 'formulario', 'descartado', 'baja'], 'estados en orden');
ok(at_pro_fuentes() === ['lead_scout', 'sellbird', 'manual', 'instagram'], 'fuentes');
ok(at_pro_dominio('https://www.Ejemplo.cl/contacto?x=1') === 'ejemplo.cl', 'dominio con esquema, www y ruta');
ok(at_pro_dominio('ejemplo.cl:8080') === 'ejemplo.cl', 'dominio con puerto');
ok(at_pro_dominio('') === '', 'dominio vacío');
ok(at_pro_dominio('  WWW.Café.cl/x ') === 'café.cl', 'dominio con espacios y mayúsculas');
ok(at_pro_dominio('no es una url') === '', 'dominio inválido con espacios');
ok(at_pro_telefono('+56 9 1234 5678') === '56912345678', 'teléfono con +56');
ok(at_pro_telefono('912345678') === '56912345678', 'teléfono de 9 dígitos');
ok(at_pro_telefono('123') === '', 'teléfono corto vacío');
$t = at_pro_token();
ok(at_pro_token_valido($t) && strlen($t) === 16, 'token generado es válido');
ok($t !== at_pro_token(), 'dos tokens distintos');
ok(!at_pro_token_valido('abc'), 'token corto inválido');
ok(!at_pro_token_valido('aaaaaaaaaaaaaaa!'), 'token con símbolo inválido');
ok(!at_pro_token_valido('AAAAAAAAAAAAAAAA'), 'token en mayúscula inválido (collation insensible)');
foreach ([['por_contactar', 'contactado', true], ['contactado', 'respondio', true], ['respondio', 'formulario', true],
	['por_contactar', 'formulario', true], ['contactado', 'descartado', true], ['formulario', 'baja', true], ['por_contactar', 'baja', true],
	['baja', 'contactado', false], ['baja', 'descartado', false], ['formulario', 'por_contactar', false], ['descartado', 'contactado', true],
	['contactado', 'contactado', false], ['contactado', 'inventado', false], ['inventado', 'baja', false]] as $x) {
	ok(at_pro_transicion_valida($x[0], $x[1]) === $x[2], "transición {$x[0]} -> {$x[1]} = " . ($x[2] ? 'sí' : 'no'));
}
ok(at_pro_columna_csv('Teléfono') === 'telefono', 'cabecera Teléfono');
ok(at_pro_columna_csv("\xEF\xBB\xBFnombre") === 'negocio', 'cabecera con BOM');
ok(at_pro_columna_csv('xyz') === '', 'cabecera desconocida');
ok(at_pro_columna_csv(' E-Mail ') === 'correo', 'cabecera E-Mail con espacios');
ok(at_pro_columna_csv('Señales') === 'senales', 'cabecera Señales');
ok(at_pro_columna_csv('Website') === 'web' && at_pro_columna_csv('Category') === 'rubro' && at_pro_columna_csv('City') === 'comuna', 'alias en inglés');
$m = at_pro_mensaje(['negocio' => 'Café Sur', 'senales' => 'no tienen reservas en línea'], 'https://x/?r=T', 'https://x/?baja=T');
ok(strpos($m, 'Café Sur') !== false && strpos($m, 'no tienen reservas en línea') !== false, 'mensaje trae negocio y señal');
ok(strpos($m, 'https://x/?r=T') !== false && strpos($m, 'https://x/?baja=T') !== false, 'mensaje trae enlace al formulario y de baja');
ok(strpos($m, '$') === false && stripos($m, 'garantiza') === false, 'mensaje sin precios ni promesas');
$m2 = at_pro_mensaje(['negocio' => 'Café Sur', 'senales' => ''], 'https://x/?r=T', 'https://x/?baja=T');
ok(strpos($m2, 'Café Sur') !== false && strpos($m2, 'https://x/?baja=T') !== false, 'mensaje sin señal igual sale completo');
echo $f ? "\n$f FALLAS\n" : "\nTODO OK\n"; exit($f ? 1 : 0);
