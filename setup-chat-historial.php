<?php
/**
 * Setup tabla historial de chat del agente IA
 */
require_once __DIR__ . '/wp-load.php';
global $wpdb;

$tabla = $wpdb->prefix . 'crm_chat_historial';

$sql = "CREATE TABLE IF NOT EXISTS {$tabla} (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    user_id BIGINT(20) UNSIGNED,
    rol ENUM('user', 'assistant') NOT NULL,
    mensaje TEXT NOT NULL,
    archivos JSON,
    audio_url VARCHAR(500),
    tokens_usado INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$result = $wpdb->query($sql);

if ($result !== false) {
    echo "✅ Tabla {$tabla} creada correctamente\n";
} else {
    echo "❌ Error: " . $wpdb->last_error . "\n";
}
