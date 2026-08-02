<?php
/**
 * api.php — API pública de solo lectura. GET ?p=<slug> → party+theme resueltos.
 * Sin dependencias externas. Compatible PHP 8.0+ (baseline 8.2).
 */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$slugRaw = isset($_GET['p']) ? (string) $_GET['p'] : '';

try {
    $result = cb_resolve_party($slugRaw);
} catch (Throwable $e) {
    error_log('CumpleClick API: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);
    exit;
}

if (!$result['ok']) {
    http_response_code($result['code']);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
    exit;
}

echo json_encode([
    'ok'    => true,
    'party' => $result['party'],
    'theme' => $result['theme'],
]);
