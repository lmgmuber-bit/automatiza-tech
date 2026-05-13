<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * =============================================================
 * SETUP COMPLETO OMNICHANNEL - PRODUCCIÓN
 * =============================================================
 * 
 * Ejecuta todas las migraciones necesarias para el portal OmniCliente.
 * Crea tablas, agrega columnas y datos por defecto en una sola ejecución.
 * 
 * SEGURO DE RE-EJECUTAR: usa CREATE TABLE IF NOT EXISTS y verifica
 * columnas antes de agregar.
 * 
 * Ejecutar UNA VEZ en PROD:  https://automatizatech.cl/setup-omnichannel-prod.php
 * (Requiere estar logueado como admin de WordPress)
 * =============================================================
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Solo administradores pueden ejecutar este script.');
}

global $wpdb;
$charset = $wpdb->get_charset_collate();
$prefix  = $wpdb->prefix . 'omnichannel_';
$results = [];

header('Content-Type: text/html; charset=utf-8');

function check_table($wpdb, $table) {
    return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
}

function col_exists($wpdb, $table, $col) {
    return !empty($wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `{$table}` LIKE %s", $col
    )));
}

function add_col($wpdb, $table, $col, $definition, &$results) {
    if (!col_exists($wpdb, $table, $col)) {
        $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
        $results[] = "  ✅ Columna {$col} agregada a {$table}";
    } else {
        $results[] = "  ⏭ Columna {$col} ya existe en {$table}";
    }
}

// =====================================================
// PASO 1: TABLAS PRINCIPALES (setup-omnichannel-db.php)
// =====================================================
$results[] = "<h3>1. Tablas principales</h3>";

// 1.1 Clientes
$t = $prefix . 'clients';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.2 Canales
$t = $prefix . 'channels';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp',
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.3 Conversaciones
$t = $prefix . 'conversations';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NOT NULL,
    external_contact_id VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    contact_avatar_url VARCHAR(500) DEFAULT NULL,
    channel_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp',
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.4 Mensajes
$t = $prefix . 'messages';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    channel_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp',
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.5 Bot configs
$t = $prefix . 'bot_configs';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.6 Audit log
$t = $prefix . 'audit_log';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.7 Takeovers
$t = $prefix . 'takeovers';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// 1.8 Agentes
$t = $prefix . 'agents';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    wp_user_id BIGINT UNSIGNED DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    password_reset_code VARCHAR(255) DEFAULT NULL,
    password_reset_expires DATETIME DEFAULT NULL,
    access_token VARCHAR(191) DEFAULT NULL,
    token_expires DATETIME DEFAULT NULL,
    reset_token VARCHAR(191) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    role ENUM('admin','supervisor','agent') DEFAULT 'agent',
    skills TEXT DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
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
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// =====================================================
// PASO 2: TIPOS DE CANAL (setup-channel-types.php)
// =====================================================
$results[] = "<h3>2. Tipos de canal</h3>";

$t = $prefix . 'channel_types';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    emoji VARCHAR(10) DEFAULT '📡',
    color VARCHAR(50) DEFAULT 'gray-500',
    fields_json TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_slug (slug)
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// Seed defaults
$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$t}`");
if ($count === 0) {
    $defaults = [
        ['whatsapp', 'WhatsApp', '📱', 'green-500', json_encode([['key'=>'phone_number','label'=>'Número de teléfono','placeholder'=>'+521234567890']]), 1],
        ['instagram', 'Instagram', '📸', 'pink-500', json_encode([['key'=>'page_id','label'=>'ID de página/cuenta','placeholder'=>'ID de Instagram Business']]), 2],
        ['telegram', 'Telegram', '✈️', 'blue-400', json_encode([['key'=>'bot_token','label'=>'Bot Token','placeholder'=>'12345:ABC-DEF...']]), 3],
        ['messenger', 'Messenger', '💬', 'blue-600', json_encode([['key'=>'page_id','label'=>'Page ID','placeholder'=>'Facebook Page ID']]), 4],
        ['email', 'Email', '📧', 'gray-500', json_encode([['key'=>'email_address','label'=>'Dirección de correo','placeholder'=>'soporte@empresa.com']]), 5],
        ['webchat', 'Web Chat', '🌐', 'indigo-500', json_encode([['key'=>'widget_url','label'=>'URL del Widget','placeholder'=>'https://...']]), 6],
    ];
    foreach ($defaults as $d) {
        $wpdb->insert($t, [
            'slug' => $d[0], 'label' => $d[1], 'emoji' => $d[2],
            'color' => $d[3], 'fields_json' => $d[4], 'sort_order' => $d[5], 'is_active' => 1,
        ]);
    }
    $results[] = "  ✅ " . count($defaults) . " tipos de canal insertados por defecto";
} else {
    $results[] = "  ⏭ Tipos de canal ya tienen {$count} registros";
}

// =====================================================
// PASO 3: BOT TEMPLATES (setup-omnichannel-v2.php)
// =====================================================
$results[] = "<h3>3. Bot templates + N8N workflows</h3>";

$t = $prefix . 'bot_templates';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED DEFAULT NULL,
    template_name VARCHAR(255) NOT NULL,
    nombre_negocio VARCHAR(255) DEFAULT NULL,
    nombre_asistente VARCHAR(100) DEFAULT NULL,
    emoji_principal VARCHAR(10) DEFAULT NULL,
    saludo TEXT DEFAULT NULL,
    max_parrafos INT UNSIGNED DEFAULT 2,
    emojis VARCHAR(100) DEFAULT NULL,
    funcion_asistente TEXT DEFAULT NULL,
    tono VARCHAR(100) DEFAULT NULL,
    horario TEXT DEFAULT NULL,
    duracion_servicios TEXT DEFAULT NULL,
    requerimientos TEXT DEFAULT NULL,
    respuesta_agendar TEXT DEFAULT NULL,
    respuesta_cancelar TEXT DEFAULT NULL,
    respuesta_escalacion TEXT DEFAULT NULL,
    negocio_email VARCHAR(255) DEFAULT NULL,
    categorias_servicios TEXT DEFAULT NULL,
    info_servicios TEXT DEFAULT NULL,
    catalogo_servicios_detallado LONGTEXT DEFAULT NULL,
    estrategia_conversacional TEXT DEFAULT NULL,
    info_tecnica TEXT DEFAULT NULL,
    restricciones TEXT DEFAULT NULL,
    capacidades TEXT DEFAULT NULL,
    ejemplo_conversacion TEXT DEFAULT NULL,
    custom_fields_json LONGTEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client (client_id),
    KEY idx_channel (channel_id)
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

$t = $prefix . 'n8n_workflows';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    workflow_name VARCHAR(255) NOT NULL,
    n8n_workflow_id VARCHAR(100) DEFAULT NULL,
    webhook_url VARCHAR(500) DEFAULT NULL,
    trigger_type VARCHAR(50) DEFAULT 'webhook',
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_triggered_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client (client_id)
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// =====================================================
// PASO 4: SUPPORT TICKETS + MESSAGES
// =====================================================
$results[] = "<h3>4. Soporte (tickets + mensajes)</h3>";

$t = $prefix . 'support_tickets';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_number VARCHAR(30) NOT NULL,
    client_id BIGINT UNSIGNED DEFAULT NULL,
    agent_id BIGINT UNSIGNED DEFAULT NULL,
    agent_name VARCHAR(255) DEFAULT NULL,
    agent_email VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(500) NOT NULL,
    description TEXT DEFAULT NULL,
    category VARCHAR(100) DEFAULT 'general',
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status ENUM('open','in-progress','resolved','closed') DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ticket_number (ticket_number),
    KEY idx_agent (agent_id),
    KEY idx_status (status),
    KEY idx_created (created_at)
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// Add columns that may not exist in older installations
add_col($wpdb, $t, 'resolved_at', "`resolved_at` DATETIME DEFAULT NULL AFTER `updated_at`", $results);
add_col($wpdb, $t, 'admin_notes', "`admin_notes` TEXT DEFAULT NULL AFTER `resolved_at`", $results);

$t = $prefix . 'ticket_messages';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$t}` (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    sender_type ENUM('agent','admin','system') NOT NULL,
    sender_id BIGINT UNSIGNED DEFAULT NULL,
    sender_name VARCHAR(255) DEFAULT NULL,
    sender_email VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    attachments TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ticket (ticket_id),
    KEY idx_created (created_at)
) {$charset};");
$results[] = check_table($wpdb, $t) ? "✅ {$t}" : "❌ {$t}: {$wpdb->last_error}";

// Add sender_email column if missing (needed for public ticket creation)
add_col($wpdb, $t, 'sender_email', "`sender_email` VARCHAR(255) DEFAULT NULL AFTER `sender_name`", $results);

// Add resolved_at column to support_tickets if missing
$st = $prefix . 'support_tickets';
add_col($wpdb, $st, 'resolved_at', "`resolved_at` DATETIME DEFAULT NULL AFTER `updated_at`", $results);
add_col($wpdb, $st, 'admin_notes', "`admin_notes` TEXT DEFAULT NULL AFTER `resolved_at`", $results);

// Add client_id to support_tickets if missing
add_col($wpdb, $st, 'client_id', "`client_id` BIGINT UNSIGNED DEFAULT NULL AFTER `ticket_number`", $results);

// =====================================================
// PASO 5: COLUMNAS EXTRA EN CLIENTES (period management)
// =====================================================
$results[] = "<h3>5. Period management (columnas en clients)</h3>";

$t = $prefix . 'clients';
add_col($wpdb, $t, 'period_start', "`period_start` DATE DEFAULT NULL AFTER `trial_ends_at`", $results);
add_col($wpdb, $t, 'period_end', "`period_end` DATE DEFAULT NULL AFTER `period_start`", $results);
add_col($wpdb, $t, 'is_free', "`is_free` TINYINT(1) NOT NULL DEFAULT 0 AFTER `period_end`", $results);
add_col($wpdb, $t, 'expiry_notified_days', "`expiry_notified_days` VARCHAR(50) DEFAULT '' AFTER `is_free`", $results);

// Index
$idx = $wpdb->get_results("SHOW INDEX FROM `{$t}` WHERE Key_name = 'idx_period_end'");
if (empty($idx)) {
    $wpdb->query("ALTER TABLE `{$t}` ADD INDEX idx_period_end (period_end)");
    $results[] = "  ✅ Índice idx_period_end agregado";
} else {
    $results[] = "  ⏭ Índice idx_period_end ya existe";
}

// =====================================================
// PASO 6: COLUMNAS EXTRA EN AGENTES (v3 migration)  
// =====================================================
$results[] = "<h3>6. Agent login columns</h3>";

$t = $prefix . 'agents';
add_col($wpdb, $t, 'password_hash', "`password_hash` VARCHAR(255) DEFAULT NULL AFTER `email`", $results);
add_col($wpdb, $t, 'password_reset_code', "`password_reset_code` VARCHAR(255) DEFAULT NULL AFTER `password_hash`", $results);
add_col($wpdb, $t, 'password_reset_expires', "`password_reset_expires` DATETIME DEFAULT NULL AFTER `password_reset_code`", $results);
add_col($wpdb, $t, 'access_token', "`access_token` VARCHAR(191) DEFAULT NULL AFTER `password_reset_expires`", $results);
add_col($wpdb, $t, 'token_expires', "`token_expires` DATETIME DEFAULT NULL AFTER `access_token`", $results);
add_col($wpdb, $t, 'reset_token', "`reset_token` VARCHAR(191) DEFAULT NULL AFTER `token_expires`", $results);
add_col($wpdb, $t, 'reset_token_expires', "`reset_token_expires` DATETIME DEFAULT NULL AFTER `reset_token`", $results);
add_col($wpdb, $t, 'skills', "`skills` TEXT DEFAULT NULL AFTER `role`", $results);
add_col($wpdb, $t, 'department', "`department` VARCHAR(100) DEFAULT NULL AFTER `skills`", $results);

// =====================================================
// PASO 7: WP-CRON para expiración
// =====================================================
$results[] = "<h3>7. WP-Cron</h3>";
if (!wp_next_scheduled('omnichannel_check_expiry')) {
    wp_schedule_event(time(), 'twicedaily', 'omnichannel_check_expiry');
    $results[] = "✅ WP-Cron 'omnichannel_check_expiry' programado (twicedaily)";
} else {
    $results[] = "⏭ WP-Cron 'omnichannel_check_expiry' ya existe";
}

// =====================================================
// RESUMEN
// =====================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Setup OmniCliente PROD</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; border-radius: 12px; padding: 24px; margin: 16px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { color: #1a1a2e; }
        h3 { color: #4F46E5; margin-top: 24px; }
        .line { padding: 4px 0; font-family: 'Courier New', monospace; font-size: 14px; }
        .ok { color: #00c851; }
        .err { color: #ff3547; }
        .skip { color: #888; }
        .warn { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px 16px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Setup OmniCliente — Producción</h1>
        <p>Resultados de la configuración de base de datos:</p>
        <?php foreach ($results as $r): ?>
            <?php if (str_starts_with($r, '<h3>')): ?>
                <?php echo $r; ?>
            <?php elseif (str_contains($r, '✅')): ?>
                <div class="line ok"><?php echo esc_html($r); ?></div>
            <?php elseif (str_contains($r, '❌')): ?>
                <div class="line err"><?php echo esc_html($r); ?></div>
            <?php else: ?>
                <div class="line skip"><?php echo esc_html($r); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <div class="warn">
            ⚠️ <strong>Importante:</strong> Elimina o protege este archivo después de ejecutarlo.<br>
            No lo dejes accesible en producción.
        </div>
    </div>
</body>
</html>
