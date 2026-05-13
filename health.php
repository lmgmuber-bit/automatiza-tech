<?php
/**
 * AT Health — Endpoint de salud para monitoreo externo (N8N, UptimeRobot, etc.)
 *
 * GET /health.php
 * Respuesta: {"status":"ok|degraded","db":"ok|error","ts":"ISO8601"}
 *
 * - Sin datos sensibles (no config, no versiones, no errores de detalle).
 * - Rate limit: 120 req/min por IP vía transients.
 * - Solo GET permitido.
 */
declare(strict_types=1);

// Cargar WordPress minimal (plugins + temas no son necesarios pero wp-load es lo más seguro)
require_once __DIR__ . '/wp-load.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');
header('X-Robots-Tag: noindex');

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo wp_json_encode(['status' => 'method_not_allowed']);
    exit;
}

// Rate limit: 120 req/min por IP
$ip  = preg_replace('/[^0-9a-fA-F:.,]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
$tk  = 'at_hlth_rl_' . substr(md5($ip), 0, 12);
$hits = (int) get_transient($tk);
if ($hits >= 120) {
    http_response_code(429);
    header('Retry-After: 60');
    echo wp_json_encode(['status' => 'rate_limited']);
    exit;
}
set_transient($tk, $hits + 1, 60);

// Verificación de base de datos — sin exponer detalles de error
global $wpdb;
$db_ok = false;
try {
    $db_ok = ($wpdb->get_var('SELECT 1') === '1');
} catch (\Throwable $e) {
    // silenciar — solo reportamos ok/error, sin mensaje interno
}

http_response_code($db_ok ? 200 : 503);
echo wp_json_encode([
    'status' => $db_ok ? 'ok' : 'degraded',
    'db'     => $db_ok ? 'ok' : 'error',
    'ts'     => gmdate('c'),
]);
exit;
