<?php
/** Pruebas del comprobante de pago: el PDF, su enlace firmado y el correo con adjunto. */
if (PHP_SAPI !== 'cli') { exit(2); }
$tmp = sys_get_temp_dir() . '/cumpleclick-comprobante-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
register_shutdown_function(static function () use ($tmp): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
    @rmdir($tmp);
});
putenv('CC_STORAGE_MODE=db');
putenv('CC_PDO_DSN=sqlite:' . $tmp . '/comprobante.sqlite');
putenv('CC_APP_HMAC_KEY=' . str_repeat('e', 64));
putenv('CC_PUBLIC_BASE_URL=https://example.test/app');
putenv('CC_PHOTO_DIR=' . $tmp . '/photos');
putenv('CC_STATE_DIR=' . $tmp . '/state');
putenv('CUMPLECLICK_CONFIG_FILE=' . $tmp . '/no-config.php');
require dirname(__DIR__, 2) . '/public/lib.php';
require dirname(__DIR__, 2) . '/public/lib.comprobante.php';
require dirname(__DIR__, 2) . '/public/lib.mail.php';

$tests = 0;
function cmp_check(bool $cond, string $msg): void {
    global $tests;
    $tests++;
    if (!$cond) { throw new RuntimeException('FAIL: ' . $msg); }
}

// ── El escritor de PDF ──────────────────────────────────────────────────────
$pdf = new CcPdf(215.9, 279.4);
$pdf->texto(20, 20, 'Ñandú á é í ó ú ü ¿? ¡!', 12, true);
$pdf->parrafo(20, 30, 80, str_repeat('palabra ', 40), 10);
$pdf->linea(20, 100, 190, 100);
$pdf->rectangulo(20, 110, 60, 10);
$salida = $pdf->salida();
cmp_check(str_starts_with($salida, '%PDF-1.4'), 'el PDF parte con su cabecera');
cmp_check(str_ends_with(trim($salida), '%%EOF'), 'el PDF termina en EOF');
cmp_check(substr_count($salida, '/Type /Page') >= 1, 'tiene al menos una página');
cmp_check(str_contains($salida, '/BaseFont /Helvetica'), 'declara la fuente base');
cmp_check(str_contains($salida, 'startxref'), 'tiene tabla de referencias');

// El ancho del texto tiene que crecer con el tamaño y con la cantidad de letras: de ahí
// depende que los montos queden alineados a la derecha.
$corto = $pdf->anchoTextoMm('AB', 10);
$largo = $pdf->anchoTextoMm('ABCD', 10);
cmp_check($largo > $corto, 'más letras miden más');
cmp_check($pdf->anchoTextoMm('AB', 20) > $corto, 'más tamaño mide más');
cmp_check($pdf->anchoTextoMm('', 10) === 0.0, 'el texto vacío no mide nada');

// Varias páginas: el documento tiene que declararlas todas.
$multi = new CcPdf();
$multi->texto(10, 10, 'uno');
$multi->nuevaPagina();
$multi->texto(10, 10, 'dos');
cmp_check(substr_count($multi->salida(), '/Type /Page') === 3, 'dos páginas más el nodo Pages');

// ── El enlace firmado ───────────────────────────────────────────────────────
$firma = cb_comprobante_firma('fiesta-a');
cmp_check(strlen($firma) === 24, 'la firma tiene largo fijo');
cmp_check($firma === cb_comprobante_firma('fiesta-a'), 'la firma es estable para la misma fiesta');
cmp_check($firma !== cb_comprobante_firma('fiesta-b'), 'otra fiesta firma distinto');
cmp_check(str_contains(cb_comprobante_url('fiesta-a'), 'comprobante.php?p=fiesta-a&f=' . $firma),
    'la URL trae la fiesta y su firma');

// ── El folio ────────────────────────────────────────────────────────────────
cmp_check(cb_comprobante_folio(36, '2026-09-07') === 'CC-0036-20260907', 'el folio junta fiesta y fecha');
cmp_check(cb_comprobante_fecha_larga('2026-09-13') === '13 de septiembre de 2026', 'fecha en español');
cmp_check(cb_comprobante_fecha_larga('sin fecha') === 'sin fecha', 'una fecha inválida se devuelve tal cual');

// ── El correo con el PDF adjunto ────────────────────────────────────────────
$cfg = ['from' => 'no-reply@example.test', 'from_name' => 'CumpleClick', 'reply_to' => 'hola@example.test'];
$sinAdjunto = cc_mail_build(['to' => 'a@b.cl', 'subject' => 'Hola', 'text' => 'hola', 'html' => '<p>hola</p>'], $cfg);
cmp_check(str_contains($sinAdjunto, 'Content-Type: multipart/alternative'), 'sin adjuntos el correo no cambia');
cmp_check(!str_contains($sinAdjunto, 'multipart/mixed'), 'sin adjuntos no hay envoltura de más');

$conAdjunto = cc_mail_build([
    'to' => 'a@b.cl', 'subject' => 'Comprobante', 'text' => 't', 'html' => '<p>t</p>',
    'attachments' => [['filename' => 'comprobante.pdf', 'type' => 'application/pdf', 'data' => $salida]],
], $cfg);
cmp_check(str_contains($conAdjunto, 'Content-Type: multipart/mixed'), 'con adjunto el correo es mixed');
cmp_check(str_contains($conAdjunto, 'Content-Type: multipart/alternative'), 'el cuerpo sigue siendo alternativo');
cmp_check(str_contains($conAdjunto, 'filename="comprobante.pdf"'), 'el adjunto lleva su nombre');
cmp_check(str_contains($conAdjunto, 'Content-Transfer-Encoding: base64'), 'el adjunto va en base64');
$mayor = 0;
foreach (explode("\r\n", $conAdjunto) as $linea) { $mayor = max($mayor, strlen($linea)); }
cmp_check($mayor <= 998, 'ninguna línea pasa el largo que aceptan los servidores de correo');

echo "OK comprobante: $tests comprobaciones\n";
