<?php
/**
 * Descarga del comprobante de pago por enlace firmado.
 *
 * Vive fuera del admin para que quien contrató pueda abrirlo desde el correo o desde el
 * mensaje de WhatsApp, sin cuenta. La firma va en la URL: sin ella, cambiar el slug dejaría
 * ver el cobro de otra fiesta. El PDF se arma en el momento, así que siempre muestra lo que
 * está cargado hoy en la ficha.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/lib.comprobante.php';

function comprobante_error(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Comprobante · CumpleClick</title>'
        . '<body style="font-family:system-ui,sans-serif;background:#ECE7F2;color:#241436;margin:0;'
        . 'display:grid;place-items:center;min-height:100dvh"><main style="background:#fff;border-radius:16px;'
        . 'padding:26px 28px;max-width:420px;text-align:center;line-height:1.6"><p>'
        . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')
        . '</p><p style="color:#6B6280;font-size:.9rem">Escríbenos a contacto@cumpleclick.com y te lo reenviamos.</p>'
        . '</main></body>';
    exit;
}

$slug = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : '';
$firma = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';
if (!cb_valid_public_slug($slug) || $firma === '') {
    comprobante_error(400, 'El enlace del comprobante no es válido.');
}
// hash_equals y no ==: comparar firmas con == filtra información por el tiempo de respuesta.
if (!hash_equals(cb_comprobante_firma($slug), $firma)) {
    comprobante_error(403, 'El enlace del comprobante no es válido.');
}

$datos = cb_comprobante_datos($slug);
if ($datos === null) {
    comprobante_error(404, 'Todavía no hay un comprobante para esta fiesta.');
}

$pdf = cb_comprobante_pdf($datos);
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
// `inline`: en el celular se abre en el visor y desde ahí se guarda o se reenvía, que es lo
// que hace la gente. Con `attachment` algunos navegadores móviles lo descargan a ciegas.
header('Content-Disposition: inline; filename="' . cb_comprobante_nombre_archivo($datos) . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $pdf;
