<?php
/**
 * API Endpoint para guardar y obtener historial de conversaciones
 * 
 * Endpoints:
 * - POST /api-chat-history.php?action=save - Guardar un mensaje
 * - GET /api-chat-history.php?action=get&session_id=XXX - Obtener historial completo
 * - DELETE /api-chat-history.php?action=clear&session_id=XXX - Limpiar historial
 */

// Headers CORS para permitir llamadas desde n8n
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar WordPress
require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'chat_history';

// Crear tabla si no existe
$wpdb->query("CREATE TABLE IF NOT EXISTS $table_name (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Obtener acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Obtener datos del body JSON si es POST
$json_input = file_get_contents('php://input');
$body_data = [];
if ($json_input) {
    $body_data = json_decode($json_input, true) ?: [];
}

switch ($action) {
    case 'save':
        // Guardar un mensaje en el historial
        $session_id = $body_data['session_id'] ?? $_POST['session_id'] ?? '';
        $role = $body_data['role'] ?? $_POST['role'] ?? ''; // 'user' o 'assistant'
        $message = $body_data['message'] ?? $_POST['message'] ?? '';
        
        if (empty($session_id) || empty($role) || empty($message)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Faltan parámetros: session_id, role, message'
            ]);
            exit;
        }
        
        // Validar role
        if (!in_array($role, ['user', 'assistant'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Role debe ser "user" o "assistant"'
            ]);
            exit;
        }
        
        // Limpiar el mensaje del marcador si existe
        $message = str_replace('[CONVERSACION_FINALIZADA]', '', $message);
        $message = trim($message);
        
        // Insertar mensaje
        $result = $wpdb->insert(
            $table_name,
            [
                'session_id' => sanitize_text_field($session_id),
                'role' => $role,
                'message' => $message
            ],
            ['%s', '%s', '%s']
        );
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message_id' => $wpdb->insert_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al guardar mensaje'
            ]);
        }
        break;
        
    case 'get':
        // Obtener historial completo de una sesión
        $session_id = $body_data['session_id'] ?? $_GET['session_id'] ?? '';
        
        if (empty($session_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Falta session_id'
            ]);
            exit;
        }
        
        // Obtener todos los mensajes de la sesión ordenados por fecha
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT role, message, created_at FROM $table_name WHERE session_id = %s ORDER BY created_at ASC",
            $session_id
        ));
        
        // Construir historial HTML formateado
        $historial_html = '';
        $historial_text = '';
        
        foreach ($messages as $msg) {
            if ($msg->role === 'user') {
                $historial_html .= '
<div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 12px 16px; border-radius: 12px; margin: 10px 0 10px 20%; text-align: right;">
  <strong>👤 Usuario:</strong> ' . esc_html($msg->message) . '
</div>';
                $historial_text .= "👤 Usuario: " . $msg->message . "\n\n";
            } else {
                $historial_html .= '
<div style="background: white; border: 1px solid #e0e0e0; padding: 12px 16px; border-radius: 12px; margin: 10px 20% 10px 0;">
  <strong style="color: #06d6a0;">🤖 Asistente:</strong> ' . esc_html($msg->message) . '
</div>';
                $historial_text .= "🤖 Asistente: " . $msg->message . "\n\n";
            }
        }
        
        echo json_encode([
            'success' => true,
            'session_id' => $session_id,
            'message_count' => count($messages),
            'messages' => $messages,
            'historial_html' => $historial_html,
            'historial_text' => $historial_text
        ]);
        break;
        
    case 'clear':
        // Limpiar historial de una sesión
        $session_id = $body_data['session_id'] ?? $_GET['session_id'] ?? '';
        
        if (empty($session_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Falta session_id'
            ]);
            exit;
        }
        
        $deleted = $wpdb->delete($table_name, ['session_id' => $session_id], ['%s']);
        
        echo json_encode([
            'success' => true,
            'deleted_count' => $deleted
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Acción no válida',
            'available_actions' => ['save', 'get', 'clear'],
            'usage' => [
                'save' => 'POST con body JSON: {session_id, role, message}',
                'get' => 'GET ?action=get&session_id=XXX',
                'clear' => 'DELETE ?action=clear&session_id=XXX'
            ]
        ]);
}
