<?php
/**
 * comprobante-aceptacion.php — entrega al cliente su comprobante firmado.
 * Autenticación por token de comprobante (GET ?r=<32 hex>), distinto del enlace
 * de aceptación (que se rota al firmar). Solo sirve aceptaciones `accepted`.
 * ?d=1 fuerza descarga; por defecto se muestra en el navegador.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/lib.acceptance.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

function cb_receipt_error(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>CumpleClick</title></head>'
        . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:3rem 1.5rem;background:#FFF8EC;color:#4C2882">'
        . '<h1 style="font-size:1.4rem">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
    exit;
}

$token = (string) ($_GET['r'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_receipt_error(400, 'Enlace de comprobante inválido.');
}

try {
    $limit = cb_rate_limit('accept-receipt', cb_request_identity(), 20, 600, 900);
    if (!$limit['allowed']) {
        header('Retry-After: ' . max(1, (int) $limit['retry_after']));
        cb_receipt_error(429, 'Demasiados intentos. Espera unos minutos.');
    }
    $row = cb_load_acceptance_by_receipt_hash(cb_hash_token($token));
} catch (Throwable $e) {
    error_log('CumpleClick comprobante-aceptacion.php: ' . $e->getMessage());
    cb_receipt_error(503, 'Servicio no disponible por el momento.');
}

if (!$row) {
    cb_receipt_error(404, 'Comprobante no encontrado.');
}
$path = cb_acceptance_file_path((string) ($row['evidence_storage_key'] ?? ''));
if ($path === null || !is_file($path)) {
    error_log('CumpleClick comprobante-aceptacion.php: archivo faltante para aceptación ' . (int) $row['id']);
    cb_receipt_error(404, 'El archivo del comprobante no está disponible. Escríbenos a CumpleClick.');
}
// Integridad: si el archivo no coincide con el hash registrado, no se entrega.
if (!hash_equals((string) $row['evidence_sha256'], (string) hash_file('sha256', $path))) {
    error_log('CumpleClick comprobante-aceptacion.php: hash no coincide para aceptación ' . (int) $row['id']);
    cb_receipt_error(409, 'El comprobante no pasó la verificación de integridad. Escríbenos a CumpleClick.');
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Length: ' . (string) filesize($path));
$disposition = (($_GET['d'] ?? '') === '1') ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="comprobante-cumpleclick-' . (int) $row['id'] . '.html"');
readfile($path);
