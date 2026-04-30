<?php
/**
 * Consulta DIRECTA del consumo OpenAI - API v1/organization/usage
 * NOTA: Esta API requiere una API Key de Administrador de la organización
 */

// Cargar WP para usar la constante OPENAI_API_KEY definida en wp-config.
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    status_header(403);
    exit('Acceso denegado');
}

if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) {
    exit('OPENAI_API_KEY no configurada');
}
$apiKey = OPENAI_API_KEY;

// Timestamps Unix para el rango
$startTime = strtotime('-7 days');
$endTime = time();

echo "=== API de Uso OpenAI (Organization Usage) ===\n";
echo "Rango: " . date('Y-m-d', $startTime) . " a " . date('Y-m-d', $endTime) . "\n\n";

// Endpoint oficial de Usage (requiere Admin Key)
$url = "https://api.openai.com/v1/organization/usage/completions?start_time={$startTime}&end_time={$endTime}&bucket_width=1d";

echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "cURL Error: $error\n";

$data = json_decode($response, true);
echo "\nRespuesta:\n";
print_r($data);

// Si funciona, calcular totales
if ($httpCode === 200 && isset($data['data'])) {
    $totalInputTokens = 0;
    $totalOutputTokens = 0;
    
    foreach ($data['data'] as $bucket) {
        foreach ($bucket['results'] as $result) {
            $totalInputTokens += $result['input_tokens'] ?? 0;
            $totalOutputTokens += $result['output_tokens'] ?? 0;
        }
    }
    
    echo "\n=== TOTALES ===\n";
    echo "Input Tokens: " . number_format($totalInputTokens) . "\n";
    echo "Output Tokens: " . number_format($totalOutputTokens) . "\n";
    echo "Total: " . number_format($totalInputTokens + $totalOutputTokens) . "\n";
}
