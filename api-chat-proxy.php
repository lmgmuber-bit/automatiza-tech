<?php
/**
 * API Proxy para Consumo de OpenAI
 * Endpoint centralizado para N8N, PetsGo y otros servicios/clientes
 * Registra automáticamente el consumo en ai_usage_log
 * 
 * IMPORTANTE: Este archivo DEBE devolver JSON limpio siempre.
 * Se usa output buffering para capturar y descartar cualquier output 
 * accidental de WordPress (errores HTML, BOM, notices, etc.)
 */

// Iniciar output buffering ANTES de cargar WordPress para capturar cualquier output
ob_start();

// Suprimir display errors para evitar HTML en la respuesta JSON
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once('wp-load.php');
require_once('openai-controller.php');

// Descartar TODO el output que haya generado WordPress al cargar
ob_end_clean();

// Ahora sí, enviar headers limpios
header('Content-Type: application/json; charset=utf-8');
header('X-Proxy-Version: 2.1');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Recibir datos
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$messages = isset($input['messages']) ? $input['messages'] : [];
$model = isset($input['model']) ? $input['model'] : 'gpt-4o';
$userId = isset($input['user_id']) ? intval($input['user_id']) : 1; 
$clientIdentifier = isset($input['client_identifier']) ? sanitize_text_field($input['client_identifier']) : null;

if (empty($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'Messages array is required']);
    exit;
}

try {
    $controller = new OpenAIController();
    
    // Verificar que la API Key esté configurada
    if (empty($controller->getApiKey())) {
        http_response_code(500);
        echo json_encode([
            'error' => 'OPENAI_API_KEY no está configurada en el servidor.',
            'reply' => 'Error: API Key no configurada en el servidor. Por favor configura OPENAI_API_KEY en wp-config.php'
        ]);
        exit;
    }
    
    // Capturar cualquier output accidental durante la llamada (errores de BD, notices, etc.)
    ob_start();
    $response = $controller->chatCompletion($userId, $messages, $model, $clientIdentifier);
    $accidental_output = ob_get_clean();
    
    // Loguear output accidental si hubo (para debugging, sin romper el JSON)
    if (!empty(trim($accidental_output))) {
        error_log('api-chat-proxy: Output accidental capturado (' . strlen($accidental_output) . ' bytes): ' . substr(strip_tags($accidental_output), 0, 500));
    }
    
    // Agregar metadata de tracking
    $trackingReady = $controller->isTrackingReady();
    
    if (isset($response['error'])) {
        $code = isset($response['code']) ? $response['code'] : 500;
        http_response_code($code > 0 ? $code : 500);
        if (!isset($response['reply'])) {
            $response['reply'] = 'Error: ' . $response['error'];
        }
    }
    
    // Agregar info de tracking a la respuesta (no invasivo)
    $response['_tracking'] = [
        'client_identifier' => $clientIdentifier,
        'tracked' => $trackingReady,
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Limpiar cualquier output buffering pendiente
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'reply' => 'Error interno del servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>