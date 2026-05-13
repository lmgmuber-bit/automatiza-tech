<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Purgar caché de LiteSpeed/Hostinger
 * Ejecutar: https://automatizatech.cl/purge-cache.php
 * Para N8N: https://automatizatech.cl/purge-cache.php?format=json
 */

// Detectar si es llamada desde N8N (formato JSON)
$isJson = isset($_GET['format']) && $_GET['format'] === 'json';

if ($isJson) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

// Método 1: Headers de purga LiteSpeed
header('X-LiteSpeed-Purge: *');

// Método 2: Llamar función de WordPress si existe
require_once('wp-load.php');

$results = [];

// Purgar LiteSpeed Cache si está activo
if (function_exists('litespeed_purge_all')) {
    litespeed_purge_all();
    $results[] = "LiteSpeed Cache purgado";
    if (!$isJson) echo "LiteSpeed Cache purgado via función.<br>";
}

// Intentar purgar via clase de LiteSpeed
if (class_exists('LiteSpeed_Cache_API')) {
    LiteSpeed_Cache_API::purge_all();
    $results[] = "LiteSpeed API purge_all";
}

// Purgar WP Super Cache si está activo
if (function_exists('wp_cache_clear_cache')) {
    wp_cache_clear_cache();
    $results[] = "WP Super Cache purgado";
    if (!$isJson) echo "WP Super Cache purgado.<br>";
}

// Purgar W3 Total Cache si está activo
if (function_exists('w3tc_flush_all')) {
    w3tc_flush_all();
    $results[] = "W3 Total Cache purgado";
    if (!$isJson) echo "W3 Total Cache purgado.<br>";
}

// Purgar OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    $results[] = "OPcache reseteado";
    if (!$isJson) echo "OPcache reseteado.<br>";
}

// Limpiar transients de WordPress relacionados con API
global $wpdb;
$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_rest_%'");
$results[] = "Transients REST eliminados: $deleted";
if (!$isJson) echo "Transients de REST API eliminados.<br>";

// Respuesta
if ($isJson) {
    echo json_encode([
        'success' => true,
        'message' => 'Cache purgado correctamente',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'actions' => $results
    ]);
    exit;
}

echo "<br><strong>✓ Caché purgado!</strong><br>";
echo "<br>Prueba el endpoint: <a href='/wp-json/automatiza-tech/v1/leads/reminders-wa/1h'>/leads/reminders-wa/1h</a>";
