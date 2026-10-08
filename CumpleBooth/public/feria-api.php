<?php
/**
 * feria-api.php — el selector de la feria (AT-CUMPLECLICK-020, contrato 4.2 y 4.3 del handoff).
 *   GET  ?f=<slug>                                   → datos de la feria y sus mundos por modo.
 *   POST {accion:"numero", f, modo, tema, nombre}   → reserva el número F-### de un visitante.
 */
require __DIR__ . '/lib.ferias.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function cb_feria_api_responder(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function cb_feria_api_error(int $status, string $error, array $extra = []): void
{
    cb_feria_api_responder($status, array_merge(['ok' => false, 'error' => $error], $extra));
}

/** La feria del slug, solo si está abierta. Cualquier otro caso responde 404 y termina. */
function cb_feria_api_cargar(string $slug): array
{
    try {
        $feria = cb_feria_de_fiesta($slug);
    } catch (Throwable $e) {
        error_log('CumpleClick feria-api: ' . $e->getMessage());
        cb_feria_api_error(503, 'service_unavailable');
    }
    if ($feria === null || !cb_feria_abierta($feria)) {
        cb_feria_api_error(404, 'feria_no_disponible');
    }
    return $feria;
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo === 'GET') {
    $feria = cb_feria_api_cargar((string) ($_GET['f'] ?? ''));
    $video = is_file(__DIR__ . '/feria/pantalla-espera.mp4') ? 'feria/pantalla-espera.mp4' : '';
    $publica = cb_feria_publica($feria);
    unset($publica['modos']);
    cb_feria_api_responder(200, [
        'ok' => true,
        'feria' => $publica,
        'mundos' => cb_feria_mundos_publicos($feria),
        'video_espera' => $video,
    ]);
}

if ($metodo !== 'POST') {
    header('Allow: GET, POST');
    cb_feria_api_error(405, 'method_not_allowed');
}

$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || strlen($raw) > 4096) {
    cb_feria_api_error(413, 'too_big');
}
$data = json_decode($raw, true);
if (!is_array($data) || ($data['accion'] ?? '') !== 'numero') {
    cb_feria_api_error(400, 'accion_invalida');
}
$feria = cb_feria_api_cargar((string) ($data['f'] ?? ''));

// Todas las tablets de un stand suelen salir por la misma IP: el límite es por feria y por IP,
// holgado para una fila real (una persona cada 20 s por dos horas no llega) y corto para un abuso.
$limit = cb_rate_limit('feria-numero:' . $feria['slug'], cb_request_identity(), 120, 600, 300);
if (!$limit['allowed']) {
    header('Retry-After: ' . max(1, (int) $limit['retry_after']));
    cb_feria_api_error(429, 'rate_limited', ['retry_after' => (int) $limit['retry_after']]);
}

try {
    $r = cb_feria_reservar_numero($feria, (string) ($data['modo'] ?? ''), (string) ($data['tema'] ?? ''), (string) ($data['nombre'] ?? ''));
} catch (Throwable $e) {
    error_log('CumpleClick feria-api numero: ' . $e->getMessage());
    cb_feria_api_error(503, 'service_unavailable');
}
if (!$r['ok']) {
    cb_feria_api_error((int) $r['code'], (string) $r['error']);
}
unset($r['code']);
cb_feria_api_responder(200, $r);
