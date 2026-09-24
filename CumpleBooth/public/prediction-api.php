<?php
/** Endpoint público del kiosco: guarda una predicción para el evento resuelto. */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 8192) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'error' => 'payload_too_large']);
    exit;
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$slug = (string) ($input['party'] ?? '');
try {
    $resolved = cb_resolve_party($slug);
    if (!$resolved['ok'] || cb_event_type((string) ($resolved['party']['event_type'] ?? '')) !== 'baby_shower') {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }
    $partyId = cb_party_db_id($slug);
    if ($partyId === null) {
        throw new RuntimeException('No se pudo resolver la fiesta en base de datos.');
    }
    $rate = cb_rate_limit('prediction:' . $partyId, cb_request_identity(), 10, 600, 600);
    if (!$rate['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . max(1, (int) $rate['retry_after']));
        echo json_encode(['ok' => false, 'error' => 'rate_limited']);
        exit;
    }
    $result = cb_prediction_create_for_party($partyId, $input);
    if (!$result['ok']) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'validation_error', 'message' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(!empty($result['duplicate']) ? 200 : 201);
    echo json_encode(['ok' => true, 'id' => $result['id'], 'duplicate' => !empty($result['duplicate'])]);
} catch (Throwable $e) {
    error_log('CumpleClick prediction API: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);
}
