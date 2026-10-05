<?php
/**
 * feria-logo.php?f=<slug> — el logo del cliente de un evento, para el muro de prensa de la temática Empresa (04-10-2026).
 *
 * El archivo vive fuera del webroot (directorio de estado) y es un PNG que CumpleClick ya volvió a codificar al subirlo
 * (cb_feria_logo_procesar): nunca se sirve tal cual lo mandó el navegador. Es el logo que sale en las fotos del evento, así
 * que no es un dato privado; un evento anonimizado ya no lo entrega.
 */
require __DIR__ . '/lib.ferias.php';

header('X-Content-Type-Options: nosniff');
$slug = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';
$ruta = '';
try {
    $feria = cb_feria_de_fiesta_segura($slug);
    if ($feria !== null && !$feria['anonimizada']) {
        $ruta = cb_feria_logo_ruta((int) $feria['id']);
    }
} catch (Throwable $e) {
    error_log('CumpleClick feria-logo: ' . $e->getMessage());
}
if ($ruta === '' || !is_file($ruta)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Este evento no tiene logo.';
    exit;
}
header('Content-Type: image/png');
// no-transform: el CDN de Hostinger re-codifica los PNG y a las tablets Android se los entrega achicados y en WebP.
header('Cache-Control: public, max-age=300, no-transform');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
