<?php
/**
 * manual.php — el manual de la fiesta, para verlo o descargarlo en PDF.
 *
 *   ?p=<slug>&f=<firma>          la página
 *   ?p=<slug>&f=<firma>&pdf=1    el PDF, el mismo que va adjunto al correo
 *
 * Va firmado con HMAC, igual que el comprobante de pago: el manual trae el nombre del papá y
 * el PIN de la galería, así que no debe abrirse solo con adivinar el slug de una fiesta.
 * La firma se comprueba ANTES de mirar si la fiesta existe, para no revelar qué fiestas hay.
 *
 * El PIN y el enlace de la invitación no están en la ficha; llegan por parámetro cuando el
 * admin genera el enlace. Si no vienen, se usa el PIN por defecto y la invitación se omite.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib.manual.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$slug = (string) ($_GET['p'] ?? '');
$firma = (string) ($_GET['f'] ?? '');

if (!cb_valid_public_slug($slug) || !cb_manual_firma_valida($slug, $firma)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Este enlace no es válido.\n");
}

// Mismo freno que el comprobante: un enlace firmado no debe poder rastrillarse a golpes.
$limite = cb_rate_limit('manual', cb_request_identity(), 60, 600, 600);
if (empty($limite['allowed'])) {
    http_response_code(429);
    header('Retry-After: ' . max(1, (int) $limite['retry_after']));
    exit("Demasiadas solicitudes. Espera unos minutos.\n");
}

$extra = [];
if (isset($_GET['pin'])) { $extra['pin'] = (string) $_GET['pin']; }
if (isset($_GET['i'])) { $extra['invitacion'] = (string) $_GET['i']; }

$d = cb_manual_datos($slug, $extra);
if ($d === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("La fiesta no existe.\n");
}

if (($_GET['pdf'] ?? '') === '1') {
    $pdf = cb_manual_pdf($d);
    $nombre = 'manual-fiesta-' . preg_replace('/[^a-z0-9-]/', '', strtolower($slug)) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdf));
    header('Content-Disposition: inline; filename="' . $nombre . '"');
    echo $pdf;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo cb_manual_html($d);
