<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para probar conexión a N8N
 */
require_once 'wp-load.php';

$api_key = get_option('maxtech_n8n_api_key');
$base_url = get_option('maxtech_n8n_url', 'https://n8n-n8n.kchiba.easypanel.host');

echo "=== Probando conexión a N8N ===" . PHP_EOL;
echo "URL: " . $base_url . PHP_EOL;
echo "API Key: " . substr($api_key, 0, 30) . "..." . PHP_EOL . PHP_EOL;

// Llamar a la API de workflows
$response = wp_remote_get($base_url . '/api/v1/workflows', array(
    'headers' => array(
        'X-N8N-API-KEY' => $api_key,
        'Accept' => 'application/json'
    ),
    'timeout' => 15
));

if (is_wp_error($response)) {
    echo "❌ ERROR: " . $response->get_error_message() . PHP_EOL;
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "HTTP Code: " . $code . PHP_EOL;
    
    if ($code == 200) {
        $data = json_decode($body, true);
        if (isset($data['data'])) {
            echo "✅ CONEXIÓN EXITOSA!" . PHP_EOL;
            echo "Workflows encontrados: " . count($data['data']) . PHP_EOL . PHP_EOL;
            
            echo "=== Lista de Workflows ===" . PHP_EOL;
            $activos = 0;
            $inactivos = 0;
            foreach ($data['data'] as $wf) {
                $estado = !empty($wf['active']) ? '🟢 ACTIVO' : '⚪ Inactivo';
                if (!empty($wf['active'])) $activos++; else $inactivos++;
                echo $estado . " - " . $wf['name'] . " (ID: " . $wf['id'] . ")" . PHP_EOL;
            }
            echo PHP_EOL . "Resumen: {$activos} activos, {$inactivos} inactivos" . PHP_EOL;
        } else {
            echo "⚠️ Respuesta inesperada: " . substr($body, 0, 200) . PHP_EOL;
        }
    } else {
        echo "❌ Error HTTP " . $code . PHP_EOL;
        echo "Respuesta: " . substr($body, 0, 300) . PHP_EOL;
    }
}
