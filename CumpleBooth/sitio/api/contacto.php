<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/public/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

function cc_contact_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    cc_contact_response(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > 16384) {
    cc_contact_response(413, ['ok' => false, 'error' => 'Solicitud demasiado grande.']);
}
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) {
    cc_contact_response(415, ['ok' => false, 'error' => 'El contenido debe enviarse como JSON.']);
}

$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site'], true)) {
    cc_contact_response(403, ['ok' => false, 'error' => 'Origen no permitido.']);
}

try {
    $limit = cb_rate_limit('public-lead', cb_request_identity(), 5, 600, 900);
    if (!$limit['allowed']) {
        header('Retry-After: ' . max(1, (int) $limit['retry_after']));
        cc_contact_response(429, ['ok' => false, 'error' => 'Recibimos varios intentos. Espera unos minutos y vuelve a probar.']);
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw)) { throw new RuntimeException('No se pudo leer la solicitud.'); }
    $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($input)) { throw new RuntimeException('Formato inválido.'); }

    // Honeypot: respuesta neutra para no enseñar al bot cómo sortearlo.
    if (cb_lead_text($input['website'] ?? '', 200) !== '') {
        cc_contact_response(201, ['ok' => true, 'reference' => 'CC-RECIBIDO']);
    }

    $result = cb_create_lead($input, cb_request_identity(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!$result['ok']) {
        cc_contact_response(422, ['ok' => false, 'error' => 'Revisa los campos indicados.', 'fields' => $result['errors']]);
    }
    cc_contact_response(201, ['ok' => true, 'reference' => $result['reference']]);
} catch (JsonException $e) {
    cc_contact_response(400, ['ok' => false, 'error' => 'La solicitud no tiene un formato válido.']);
} catch (Throwable $e) {
    error_log('CumpleClick contacto: ' . $e->getMessage());
    cc_contact_response(503, ['ok' => false, 'error' => 'No pudimos registrar tu solicitud. Intenta nuevamente en unos minutos.']);
}
