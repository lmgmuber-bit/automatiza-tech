<?php
/**
 * api.php — API pública de solo lectura. GET ?p=<slug> → party+theme resueltos.
 * Sin dependencias externas. Compatible PHP 8.0+ (baseline 8.2).
 *
 * Modo feria (2026-09-26): si la fiesta es una feria, acepta además `tema` y `modo`, responde
 * con la temática que eligió el visitante y suma `feria` (contrato 4.1 del handoff del modo
 * feria). Una fiesta sin feria responde exactamente lo mismo que antes, con o sin esos parámetros.
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/lib.ferias.php';

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

$feria = cb_feria_de_fiesta_segura($slugRaw);
if ($feria !== null) {
    if (!cb_feria_abierta($feria)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'inactive']);
        exit;
    }
    $tema = isset($_GET['tema']) ? (string) $_GET['tema'] : '';
    $modo = isset($_GET['modo']) ? (string) $_GET['modo'] : '';
    try {
        $result = cb_feria_resolver($feria, $result, $tema, $modo);
    } catch (Throwable $e) {
        error_log('CumpleClick API feria: ' . $e->getMessage());
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
        'feria' => $result['feria'],
    ]);
    exit;
}

echo json_encode([
    'ok'    => true,
    'party' => $result['party'],
    'theme' => $result['theme'],
]);
