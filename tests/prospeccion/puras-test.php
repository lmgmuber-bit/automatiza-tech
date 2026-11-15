<?php
// Uso: php tests/prospeccion/puras-test.php (sin WordPress)
define('ABSPATH', __DIR__);
if (!function_exists('esc_html')) { function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($u) { return htmlspecialchars((string) $u, ENT_QUOTES, 'UTF-8'); } }
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/prospeccion/puras.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/prospeccion/correo.php';
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
// texto simple (WhatsApp) con el modelo de Toga
ok(strpos($m, 'Hola, soy Luis de AutomatizaTech SpA (automatizatech.cl). Vi Café Sur en tu sitio web y noté que no tienen reservas en línea.') === 0, 'mensaje: apertura, fuente manual y señal');
ok(strpos($m, "agenda un diagnóstico sin costo: https://x/?r=T

Es un mensaje comercial. Si no quieres que te escribamos más, responde NO o entra aquí: https://x/?baja=T") !== false, 'mensaje: enlace y línea de baja aparte');
ok(strpos($m2, 'Vi Café Sur en tu sitio web.') !== false && strpos($m2, 'noté') === false, 'mensaje sin señal termina en punto');
ok(strpos(at_pro_mensaje(['negocio' => 'X', 'fuente' => 'sellbird'], 'a', 'b'), 'Vi X en Google Maps') !== false, 'mensaje: sellbird = Google Maps');

// correo con diseño
$pc = ['negocio' => 'Café Aurora', 'senales' => 'su web no tiene reservas en línea', 'fuente' => 'lead_scout'];
ok(at_pro_correo_asunto($pc) === 'Propuesta comercial para Café Aurora · AutomatizaTech', 'asunto del correo');
ok(at_pro_correo_asunto([]) === 'Propuesta comercial para tu negocio · AutomatizaTech', 'asunto sin negocio');
$h = at_pro_correo_html($pc, 'https://x/?r=T', 'https://x/?baja=T');
ok(strpos($h, 'Hola, equipo de <strong>Café Aurora</strong>:') !== false, 'correo: saludo con el negocio');
ok(strpos($h, 'Encontré tu negocio en Google Maps y vi que su web no tiene reservas en línea.') !== false, 'correo: fuente Google Maps y señal');
ok(substr_count($h, 'https://x/?r=T') === 3, 'correo: enlace del formulario en el botón (href), en el enlace de respaldo y como texto');
ok(strpos($h, 'href="https://x/?baja=T"') !== false && strpos($h, 'https://automatizatech.cl/privacidad/') !== false, 'correo: enlace de baja y de privacidad');
ok(strpos($h, 'publicidad') !== false && strpos($h, 'Agendar diagnóstico sin costo') !== false, 'correo: aviso de publicidad y botón');
ok(strpos($h, 'AutomatizaTech SpA · Providencia, Santiago · ') !== false && strpos($h, 'contacto@automatizatech.cl') !== false, 'correo: pie');
ok(stripos($h, '<style') === false && stripos($h, 'class=') === false && strpos($h, 'max-width:600px') !== false, 'correo: estilos en línea, sin clases ni <style>');
ok(strpos($h, '#1e40af') !== false && strpos($h, '#06d6a0') !== false && strpos($h, '#1f2937') !== false && strpos($h, '#f9fafb') !== false, 'correo: colores AT');
$hx = at_pro_correo_html(['negocio' => '<script>alert(1)</script>', 'senales' => '<b>x</b>'], 'https://x/?r=T', 'https://x/?baja=T');
ok(strpos($hx, '<script>') === false && strpos($hx, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && strpos($hx, '<b>x</b>') === false, 'correo: negocio y señal escapados');
ok(strpos(at_pro_correo_html(['negocio' => 'A', 'fuente' => 'manual'], 'f', 'b'), 'en tu sitio web y quise escribirte.') !== false, 'correo: fuente manual y sin señal');
ok(strpos(at_pro_correo_html(['negocio' => 'A', 'fuente' => 'sellbird', 'senales' => 's'], 'f', 'b'), 'en Google Maps y vi que s.') !== false, 'correo: sellbird = Google Maps');
echo $f ? "\n$f FALLAS\n" : "\nTODO OK\n"; exit($f ? 1 : 0);
