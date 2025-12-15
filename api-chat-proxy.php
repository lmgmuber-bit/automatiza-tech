<?php
// api-chat-proxy.php
// Proxy simple para ocultar la API Key de OpenAI
require_once('wp-load.php');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$messages = $input['messages'] ?? [];

if (empty($messages)) {
    echo json_encode(['error' => 'No messages']);
    exit;
}

// IMPORTANTE: Configura tu API Key aquí o en wp-config.php
// define('OPENAI_API_KEY', 'sk-...'); 
// Por seguridad, intentaré leerla de una constante o variable de entorno si existe.
$api_key = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : getenv('OPENAI_API_KEY');

// Fallback para demo (NO RECOMENDADO EN PRODUCCIÓN REAL SIN KEY)
if (!$api_key) {
    // Intentar buscar en n8n credentials? No, no puedo acceder a eso desde PHP.
    // Para que esto funcione, el usuario debe configurar la KEY en wp-config.php
    echo json_encode(['reply' => 'Error: API Key no configurada en el servidor (api-chat-proxy.php). Por favor configura OPENAI_API_KEY en wp-config.php']);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-3.5-turbo', // O gpt-4 si prefieres
    'messages' => $messages,
    'temperature' => 0.7
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(['reply' => 'Error de conexión: ' . curl_error($ch)]);
} else {
    $response = json_decode($result, true);
    $reply = $response['choices'][0]['message']['content'] ?? 'No pude generar una respuesta.';
    echo json_encode(['reply' => $reply]);
}
curl_close($ch);
?>