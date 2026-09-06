<?php
/**
 * sala.php — API de las salas de ayudantes del juego 3D (fase 3).
 * GET  ?op=estado|acciones   (lecturas por polling)
 * POST ?op=crear|unirse|accion|resumen|cerrar   (JSON, ≤ 4 KB)
 * Contrato: app/design/sala-api.md en el repo del juego. Sin dependencias externas.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';
require __DIR__ . '/lib.sala.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

function cc_sala_responder(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Devuelve el resultado de lib.sala.php con el HTTP que trae; sin filtrar internos. */
function cc_sala_resultado(array $resultado): void
{
    if ($resultado['ok']) {
        unset($resultado['http']);
        cc_sala_responder(200, $resultado);
    }
    $http = (int) ($resultado['http'] ?? 400);
    unset($resultado['http']);
    if ($http === 429 && isset($resultado['espera'])) {
        header('Retry-After: ' . max(1, (int) $resultado['espera']));
    }
    cc_sala_responder($http, $resultado);
}

$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
$op = isset($_GET['op']) && is_string($_GET['op']) ? $_GET['op'] : '';
$lecturas = ['estado', 'acciones'];
$escrituras = ['crear', 'unirse', 'accion', 'resumen', 'cerrar'];

if (!in_array($op, $lecturas, true) && !in_array($op, $escrituras, true)) {
    cc_sala_responder(422, ['ok' => false, 'error' => 'op_invalida']);
}

// Los celulares están en la misma red que la tablet: mismo origen. Un navegador
// que no manda Sec-Fetch-Site (viejo) también pasa; uno de otro sitio, no.
$fetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
    cc_sala_responder(403, ['ok' => false, 'error' => 'origen']);
}

$input = [];
if (in_array($op, $lecturas, true)) {
    if ($metodo !== 'GET') {
        header('Allow: GET');
        cc_sala_responder(405, ['ok' => false, 'error' => 'metodo']);
    }
    $input = $_GET;
} else {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        cc_sala_responder(405, ['ok' => false, 'error' => 'metodo']);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
        cc_sala_responder(413, ['ok' => false, 'error' => 'demasiado_grande']);
    }
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== 0) {
        cc_sala_responder(415, ['ok' => false, 'error' => 'tipo_contenido']);
    }
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || strlen($raw) > 4096) {
        cc_sala_responder(413, ['ok' => false, 'error' => 'demasiado_grande']);
    }
    try {
        $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        cc_sala_responder(400, ['ok' => false, 'error' => 'json_invalido']);
    }
    if (!is_array($input)) {
        cc_sala_responder(400, ['ok' => false, 'error' => 'json_invalido']);
    }
}

try {
    $identidad = cb_request_identity();
    $limites = ['crear' => [10, 600, 600], 'unirse' => [30, 600, 300], 'accion' => [120, 60, 60], 'resumen' => [120, 60, 30], 'cerrar' => [20, 60, 60]];
    if (isset($limites[$op])) {
        [$max, $ventana, $bloqueo] = $limites[$op];
        $limite = cb_rate_limit('sala-' . $op, $identidad, $max, $ventana, $bloqueo);
        if (!$limite['allowed']) {
            header('Retry-After: ' . max(1, (int) $limite['retry_after']));
            cc_sala_responder(429, ['ok' => false, 'error' => $op === 'crear' ? 'demasiadas_salas' : 'demasiadas_solicitudes', 'espera' => (int) $limite['retry_after']]);
        }
    }

    switch ($op) {
        case 'crear':
            cc_sala_resultado(cb_sala_crear(is_string($input['nombre'] ?? null) ? $input['nombre'] : '', $identidad));
            break;
        case 'unirse':
            cc_sala_resultado(cb_sala_unirse($input['codigo'] ?? '', $input['nombre'] ?? '', $identidad));
            break;
        case 'accion':
            cc_sala_resultado(cb_sala_accion($input['codigo'] ?? '', $input['ayudante'] ?? '', $input['tipo'] ?? ''));
            break;
        case 'estado':
            $token = is_string($input['ayudante'] ?? null) ? $input['ayudante'] : '';
            cc_sala_resultado(cb_sala_estado($input['codigo'] ?? '', $token));
            break;
        case 'resumen':
            cc_sala_resultado(cb_sala_resumen($input['codigo'] ?? '', $input['anfitrion'] ?? '', $input['resumen'] ?? null));
            break;
        case 'acciones':
            cc_sala_resultado(cb_sala_acciones($input['codigo'] ?? '', $input['anfitrion'] ?? '', is_numeric($input['desde'] ?? null) ? (int) $input['desde'] : 0));
            break;
        case 'cerrar':
            cc_sala_resultado(cb_sala_cerrar($input['codigo'] ?? '', $input['anfitrion'] ?? ''));
            break;
        default:
            cc_sala_responder(422, ['ok' => false, 'error' => 'op_invalida']);
    }
} catch (Throwable $e) {
    error_log('CumpleClick sala: ' . $e->getMessage());
    cc_sala_responder(503, ['ok' => false, 'error' => 'servicio']);
}
