<?php
// Validación de los informes semanales que se envían por el SMTP del servidor. Uso: php tests/informes-correo/puras-test.php
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/informes-correo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

$destinos = ['luis@prueba.cl', 'adriana@prueba.cl'];
$pdf = base64_encode("%PDF-1.4\n% informe de prueba\n");
$base = ['para' => ' Adriana@Prueba.CL ', 'asunto' => 'Informe semanal · Marketing y diseño · 02-11-2026', 'html' => '<p>Hola</p>', 'texto' => 'Hola'];

$v = at_inf_validar($base, $destinos, '');
ok($v['ok'] && $v['datos']['para'] === 'adriana@prueba.cl', 'válido: destinatario de la lista, normalizado');
ok($v['datos']['cc'] === [] && $v['datos']['pdf'] === null && $v['datos']['responder_a'] === '', 'válido: sin copia, sin PDF ni responder a');

ok(!at_inf_validar(['para' => 'otro@prueba.cl'] + $base, $destinos, '')['ok'], 'rechaza un destinatario fuera de la lista');
ok(!at_inf_validar($base, [], '')['ok'], 'sin lista de destinatarios configurada no envía a nadie');
ok(at_inf_validar(['cc' => 'LUIS@prueba.cl'] + $base, $destinos, '')['datos']['cc'] === ['luis@prueba.cl'], 'copia como texto, normalizada');
ok(at_inf_validar(['cc' => ['luis@prueba.cl']] + $base, $destinos, '')['ok'], 'copia como lista');
ok(!at_inf_validar(['cc' => ['luis@prueba.cl', 'extra@prueba.cl']] + $base, $destinos, '')['ok'], 'rechaza una copia fuera de la lista');

ok(!at_inf_validar(['asunto' => "Hola\r\nBcc: x@y.cl"] + $base, $destinos, '')['ok'], 'rechaza un asunto con salto de línea (inyección de cabeceras)');
ok(!at_inf_validar(['asunto' => str_repeat('a', 201)] + $base, $destinos, '')['ok'], 'rechaza un asunto de más de 200 caracteres');
ok(!at_inf_validar(['asunto' => '  '] + $base, $destinos, '')['ok'], 'rechaza un asunto vacío');
ok(!at_inf_validar(['html' => ''] + $base, $destinos, '')['ok'], 'rechaza un correo sin HTML');
ok(!at_inf_validar(['html' => str_repeat('a', 300001)] + $base, $destinos, '')['ok'], 'rechaza un HTML de más de 300 KB');

$conPdf = at_inf_validar(['pdf_base64' => $pdf, 'pdf_nombre' => 'Informe semanal · 02-11-2026.pdf'] + $base, $destinos, '');
ok($conPdf['ok'] && strncmp($conPdf['datos']['pdf'], '%PDF-', 5) === 0, 'PDF válido: se decodifica');
ok($conPdf['datos']['pdf_nombre'] === 'Informe-semanal-02-11-2026.pdf', 'nombre del PDF limpio: ' . $conPdf['datos']['pdf_nombre']);
ok(at_inf_validar(['pdf_base64' => $pdf, 'pdf_nombre' => '../../etc/passwd'] + $base, $destinos, '')['datos']['pdf_nombre'] === 'passwd.pdf', 'nombre del PDF sin rutas y con .pdf');
ok(!at_inf_validar(['pdf_base64' => '***no es base64***'] + $base, $destinos, '')['ok'], 'rechaza un PDF que no es base64');
ok(!at_inf_validar(['pdf_base64' => base64_encode('<html>no soy pdf</html>')] + $base, $destinos, '')['ok'], 'rechaza un adjunto que no empieza con %PDF-');
ok(!at_inf_validar(['pdf_base64' => base64_encode('%PDF-' . str_repeat('a', 3 * 1024 * 1024))] + $base, $destinos, '')['ok'], 'rechaza un PDF de más de 3 MB');

ok(at_inf_validar(['responder_a' => 'Buzon@Prueba.cl'] + $base, $destinos, 'buzon@prueba.cl')['datos']['responder_a'] === 'buzon@prueba.cl', 'responder a: la dirección configurada');
ok(!at_inf_validar(['responder_a' => 'otro@prueba.cl'] + $base, $destinos, 'buzon@prueba.cl')['ok'], 'responder a: rechaza otra dirección');
ok(!at_inf_validar(['responder_a' => 'buzon@prueba.cl'] + $base, $destinos, '')['ok'], 'responder a: sin dirección configurada no se acepta');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
