<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Setup Omnichannel Portal Database Tables
 * 
 * Crea las tablas necesarias para el sistema omnicanal:
 * - Clientes con acceso al portal
 * - Canales configurados (WhatsApp, Instagram, Telegram, Messenger)
 * - Conversaciones unificadas
 * - Mensajes de todos los canales
 * - Configuración de bots por canal
 * - Registro de auditoría
 * - Toma de control por ejecutivo
 *
 * Ejecutar: /setup-omnichannel-db.php (requiere admin WordPress)
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Solo administradores pueden ejecutar este script.');
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// =====================================================
// 1. CLIENTES CON ACCESO AL PORTAL OMNICANAL
// =====================================================
$sql_clients = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(191) NOT NULL,
    contact_name VARCHAR(191) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    wp_user_id BIGINT UNSIGNED DEFAULT NULL,
    plan_type VARCHAR(20) NOT NULL DEFAULT 'basic',
    status VARCHAR(20) NOT NULL DEFAULT 'trial',
    max_channels INT UNSIGNED DEFAULT 2,
    max_agents INT UNSIGNED DEFAULT 3,
    api_key VARCHAR(64) DEFAULT NULL,
    trial_ends_at DATETIME DEFAULT NULL,
    activated_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_email (email),
    UNIQUE KEY idx_api_key (api_key),
    KEY idx_status (status),
    KEY idx_wp_user (wp_user_id)
) $charset_collate;";

// =====================================================
// 2. CANALES CONFIGURADOS POR CLIENTE
// =====================================================
$sql_channels = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_channels (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_type ENUM('whatsapp','instagram','telegram','messenger') NOT NULL,
    channel_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    credentials_json TEXT DEFAULT NULL,
    webhook_url VARCHAR(500) DEFAULT NULL,
    webhook_secret VARCHAR(128) DEFAULT NULL,
    phone_number VARCHAR(50) DEFAULT NULL,
    page_id VARCHAR(100) DEFAULT NULL,
    bot_token VARCHAR(255) DEFAULT NULL,
    config_json TEXT DEFAULT NULL,
    last_synced_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_channel (client_id, channel_type),
    KEY idx_active (is_active)
) $charset_collate;";

// =====================================================
// 3. CONVERSACIONES UNIFICADAS
// =====================================================
$sql_conversations = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    external_contact_id VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    contact_avatar_url VARCHAR(500) DEFAULT NULL,
    channel_type ENUM('whatsapp','instagram','telegram','messenger') NOT NULL,
    status ENUM('open','assigned','bot','closed','archived') DEFAULT 'bot',
    assigned_agent_id BIGINT UNSIGNED DEFAULT NULL,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    tags VARCHAR(500) DEFAULT NULL,
    last_message_at DATETIME DEFAULT NULL,
    last_message_preview VARCHAR(500) DEFAULT NULL,
    unread_count INT UNSIGNED DEFAULT 0,
    metadata_json TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client_status (client_id, status),
    KEY idx_channel (channel_id),
    KEY idx_external_contact (external_contact_id),
    KEY idx_assigned_agent (assigned_agent_id),
    KEY idx_last_message (last_message_at),
    KEY idx_channel_type (channel_type)
) $charset_collate;";

// =====================================================
// 4. MENSAJES UNIFICADOS
// =====================================================
$sql_messages = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    channel_type ENUM('whatsapp','instagram','telegram','messenger') NOT NULL,
    direction ENUM('inbound','outbound') NOT NULL,
    sender_type ENUM('contact','agent','bot','system') NOT NULL,
    sender_id VARCHAR(255) DEFAULT NULL,
    sender_name VARCHAR(255) DEFAULT NULL,
    message_type ENUM('text','image','video','audio','document','location','sticker','template','interactive') DEFAULT 'text',
    content TEXT DEFAULT NULL,
    media_url VARCHAR(500) DEFAULT NULL,
    media_mime_type VARCHAR(100) DEFAULT NULL,
    external_message_id VARCHAR(255) DEFAULT NULL,
    reply_to_message_id BIGINT UNSIGNED DEFAULT NULL,
    delivery_status ENUM('sent','delivered','read','failed','pending') DEFAULT 'pending',
    metadata_json TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversation (conversation_id),
    KEY idx_direction (direction),
    KEY idx_created (created_at),
    KEY idx_external_msg (external_message_id),
    KEY idx_sender_type (sender_type)
) $charset_collate;";

// =====================================================
// 5. CONFIGURACIÓN DE BOTS POR CANAL
// =====================================================
$sql_bot_configs = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_bot_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    bot_name VARCHAR(255) DEFAULT 'Asistente',
    is_active TINYINT(1) DEFAULT 1,
    ai_model VARCHAR(50) DEFAULT 'gpt-4o-mini',
    system_prompt TEXT DEFAULT NULL,
    welcome_message TEXT DEFAULT NULL,
    fallback_message TEXT DEFAULT NULL,
    escalation_keywords TEXT DEFAULT NULL,
    max_response_tokens INT UNSIGNED DEFAULT 500,
    temperature DECIMAL(3,2) DEFAULT 0.70,
    business_hours_json TEXT DEFAULT NULL,
    auto_reply_outside_hours TINYINT(1) DEFAULT 1,
    outside_hours_message TEXT DEFAULT NULL,
    n8n_webhook_url VARCHAR(500) DEFAULT NULL,
    custom_functions_json TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client (client_id),
    KEY idx_channel (channel_id),
    KEY idx_active (is_active)
) $charset_collate;";

// =====================================================
// 6. REGISTRO DE AUDITORÍA COMPLETO
// =====================================================
$sql_audit_log = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED DEFAULT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    user_email VARCHAR(255) DEFAULT NULL,
    user_role VARCHAR(50) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    old_values_json TEXT DEFAULT NULL,
    new_values_json TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client (client_id),
    KEY idx_user (user_id),
    KEY idx_action (action),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_created (created_at)
) $charset_collate;";

// =====================================================
// 7. TOMA DE CONTROL POR EJECUTIVO
// =====================================================
$sql_takeovers = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_takeovers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    agent_name VARCHAR(255) DEFAULT NULL,
    agent_email VARCHAR(255) DEFAULT NULL,
    reason VARCHAR(500) DEFAULT NULL,
    status ENUM('active','released','transferred') DEFAULT 'active',
    taken_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME DEFAULT NULL,
    transferred_to_agent_id BIGINT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_conversation (conversation_id),
    KEY idx_agent (agent_id),
    KEY idx_client (client_id),
    KEY idx_status (status),
    KEY idx_taken_at (taken_at)
) $charset_collate;";

// =====================================================
// 8. AGENTES / USUARIOS DEL PORTAL POR CLIENTE
// =====================================================
$sql_agents = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_agents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    wp_user_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin','supervisor','agent') DEFAULT 'agent',
    status ENUM('active','inactive','away') DEFAULT 'active',
    avatar_url VARCHAR(500) DEFAULT NULL,
    max_concurrent_chats INT UNSIGNED DEFAULT 5,
    active_chats INT UNSIGNED DEFAULT 0,
    last_active_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client (client_id),
    KEY idx_email (email),
    KEY idx_status (status),
    KEY idx_role (role)
) $charset_collate;";

// Ejecutar todas las creaciones
$tables = [
    'omnichannel_clients'       => $sql_clients,
    'omnichannel_channels'      => $sql_channels,
    'omnichannel_conversations' => $sql_conversations,
    'omnichannel_messages'      => $sql_messages,
    'omnichannel_bot_configs'   => $sql_bot_configs,
    'omnichannel_audit_log'     => $sql_audit_log,
    'omnichannel_takeovers'     => $sql_takeovers,
    'omnichannel_agents'        => $sql_agents,
];

$results = [];
foreach ($tables as $name => $sql) {
    $full_name = $prefix . $name;
    // Intentar con dbDelta primero
    dbDelta($sql);
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_name));
    if (!$exists) {
        // Fallback: ejecutar CREATE TABLE directamente
        $wpdb->query($sql);
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_name));
    }
    $results[$name] = $exists ? 'OK' : 'ERROR: ' . $wpdb->last_error;
}

// Mostrar resultados
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Setup Omnichannel DB</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; border-radius: 12px; padding: 24px; margin: 16px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { color: #1a1a2e; }
        .ok { color: #00c851; font-weight: bold; }
        .error { color: #ff4444; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 10px 16px; text-align: left; border-bottom: 1px solid #eee; }
        th { color: #666; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔧 Setup Omnichannel Database</h1>
        <p>Resultado de la creación de tablas:</p>
        <table>
            <tr><th>Tabla</th><th>Estado</th></tr>
            <?php foreach ($results as $name => $status): ?>
            <tr>
                <td><code><?php echo esc_html($prefix . $name); ?></code></td>
                <td class="<?php echo $status === 'OK' ? 'ok' : 'error'; ?>">
                    <?php echo str_starts_with($status, 'OK') ? '✅ Creada' : '❌ ' . esc_html($status); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
<?php
