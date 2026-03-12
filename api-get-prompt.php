<?php
/**
 * API Endpoint para obtener el System Prompt de una propuesta
 * 
 * Uso: GET /api-get-prompt.php?id=UNIQUE_LINK_ID
 * 
 * Devuelve el system_prompt_text guardado en wp_automatiza_propuestas
 * para que n8n pueda cargarlo dinámicamente en el Agente IA
 */

// Headers CORS para permitir llamadas desde n8n
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar WordPress
require_once('wp-load.php');

// Obtener el ID de la propuesta (puede venir como 'id', 'proposal_id', o 'unique_id')
$unique_id = '';

// Primero intentar GET
if (isset($_GET['id'])) {
    $unique_id = sanitize_text_field($_GET['id']);
} elseif (isset($_GET['proposal_id'])) {
    $unique_id = sanitize_text_field($_GET['proposal_id']);
} elseif (isset($_GET['unique_id'])) {
    $unique_id = sanitize_text_field($_GET['unique_id']);
}

// Si no hay en GET, intentar POST/JSON body
if (empty($unique_id)) {
    $json_input = file_get_contents('php://input');
    if ($json_input) {
        $data = json_decode($json_input, true);
        if ($data) {
            $unique_id = sanitize_text_field($data['id'] ?? $data['proposal_id'] ?? $data['unique_id'] ?? $data['sessionId'] ?? '');
        }
    }
}

// Validar que tenemos un ID
if (empty($unique_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID de propuesta no proporcionado',
        'usage' => 'GET /api-get-prompt.php?id=YOUR_UNIQUE_LINK_ID'
    ]);
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';

// Buscar la propuesta por unique_link_id
$proposal = $wpdb->get_row($wpdb->prepare(
    "SELECT id, unique_link_id, client_name, client_email, company_name, system_prompt_text, n8n_chat_url, status, created_at 
     FROM $table_name 
     WHERE unique_link_id = %s",
    $unique_id
));

// Si no se encuentra por unique_link_id, intentar por ID numérico
if (!$proposal && is_numeric($unique_id)) {
    $proposal = $wpdb->get_row($wpdb->prepare(
        "SELECT id, unique_link_id, client_name, client_email, company_name, system_prompt_text, n8n_chat_url, status, created_at 
         FROM $table_name 
         WHERE id = %d",
        intval($unique_id)
    ));
}

if (!$proposal) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Propuesta no encontrada',
        'id_buscado' => $unique_id
    ]);
    exit;
}

// Verificar que hay un prompt guardado
if (empty($proposal->system_prompt_text)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'La propuesta existe pero no tiene un System Prompt configurado',
        'proposal_id' => $proposal->id,
        'company_name' => $proposal->company_name
    ]);
    exit;
}

// Devolver los datos exitosamente
http_response_code(200);
echo json_encode([
    'success' => true,
    'data' => [
        'id' => $proposal->id,
        'unique_link_id' => $proposal->unique_link_id,
        'client_name' => $proposal->client_name,
        'client_email' => $proposal->client_email,
        'company_name' => $proposal->company_name,
        'system_prompt' => $proposal->system_prompt_text,
        'status' => $proposal->status,
        'created_at' => $proposal->created_at
    ]
]);
