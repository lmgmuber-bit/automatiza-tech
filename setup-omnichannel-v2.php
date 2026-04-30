<?php
/**
 * Omnichannel Portal v2 - Database Migrations
 * 
 * Adds:
 * - Bot templates table (from CSV configs like KellsCapilar)
 * - YCloud integration fields on channels
 * - Human intervention tracking fields
 * - Client import from WP admin
 * 
 * Ejecutar: /setup-omnichannel-v2.php (requiere admin WordPress)
 */

require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado.');
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix;

$results = [];

// =====================================================
// 1. BOT TEMPLATES - Plantillas reutilizables por cliente
//    Basado en la estructura del CSV de KellsCapilar
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_bot_templates (
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
) $charset_collate;";

$wpdb->query($sql);
$results[] = "omnichannel_bot_templates: " . ($wpdb->last_error ?: 'OK');

// =====================================================
// 2. N8N WORKFLOW CONFIGS - Plantillas de workflows N8N
// =====================================================
$sql = "CREATE TABLE IF NOT EXISTS {$prefix}omnichannel_n8n_workflows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED DEFAULT NULL,
    workflow_type VARCHAR(50) NOT NULL DEFAULT 'whatsapp_bot',
    workflow_name VARCHAR(255) NOT NULL,
    n8n_workflow_id VARCHAR(100) DEFAULT NULL,
    n8n_webhook_url VARCHAR(500) DEFAULT NULL,
    workflow_json LONGTEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_triggered_at DATETIME DEFAULT NULL,
    trigger_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_client (client_id),
    KEY idx_channel (channel_id),
    KEY idx_type (workflow_type)
) $charset_collate;";

$wpdb->query($sql);
$results[] = "omnichannel_n8n_workflows: " . ($wpdb->last_error ?: 'OK');

// =====================================================
// 3. ALTER channels - add YCloud fields
// =====================================================
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}omnichannel_channels");

$alter_cols = [
    'ycloud_api_key'    => "VARCHAR(255) DEFAULT NULL COMMENT 'YCloud API Key for WhatsApp'",
    'ycloud_waba_id'    => "VARCHAR(100) DEFAULT NULL COMMENT 'YCloud WABA ID'",
    'ycloud_phone_id'   => "VARCHAR(100) DEFAULT NULL COMMENT 'YCloud Phone Number ID'",
    'ycloud_webhook_id' => "VARCHAR(100) DEFAULT NULL COMMENT 'YCloud Webhook ID'",
    'provider'          => "VARCHAR(50) DEFAULT 'ycloud' COMMENT 'Channel provider: ycloud, meta, telegram_api'",
];

foreach ($alter_cols as $col => $def) {
    if (!in_array($col, $cols)) {
        $wpdb->query("ALTER TABLE {$prefix}omnichannel_channels ADD COLUMN {$col} {$def}");
        $results[] = "channels.{$col}: " . ($wpdb->last_error ?: 'ADDED');
    } else {
        $results[] = "channels.{$col}: EXISTS";
    }
}

// =====================================================
// 4. ALTER conversations - add intervention fields
// =====================================================
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}omnichannel_conversations");

$conv_cols = [
    'intervention_mode'    => "VARCHAR(20) DEFAULT 'bot' COMMENT 'bot|human|paused'",
    'intervention_started_at' => "DATETIME DEFAULT NULL",
    'intervention_agent_id'   => "BIGINT UNSIGNED DEFAULT NULL",
    'bot_memory_json'         => "LONGTEXT DEFAULT NULL COMMENT 'Bot conversation memory/context'",
    'contact_metadata_json'   => "LONGTEXT DEFAULT NULL COMMENT 'Contact profile data from YCloud'",
    'ycloud_contact_id'       => "VARCHAR(100) DEFAULT NULL COMMENT 'YCloud contact ID'",
];

foreach ($conv_cols as $col => $def) {
    if (!in_array($col, $cols)) {
        $wpdb->query("ALTER TABLE {$prefix}omnichannel_conversations ADD COLUMN {$col} {$def}");
        $results[] = "conversations.{$col}: " . ($wpdb->last_error ?: 'ADDED');
    } else {
        $results[] = "conversations.{$col}: EXISTS";
    }
}

// =====================================================
// 5. ALTER messages - add YCloud message fields
// =====================================================
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}omnichannel_messages");

$msg_cols = [
    'ycloud_message_id'   => "VARCHAR(100) DEFAULT NULL",
    'whatsapp_message_id' => "VARCHAR(100) DEFAULT NULL",
    'error_code'          => "VARCHAR(50) DEFAULT NULL",
    'error_message'       => "TEXT DEFAULT NULL",
];

foreach ($msg_cols as $col => $def) {
    if (!in_array($col, $cols)) {
        $wpdb->query("ALTER TABLE {$prefix}omnichannel_messages ADD COLUMN {$col} {$def}");
        $results[] = "messages.{$col}: " . ($wpdb->last_error ?: 'ADDED');
    } else {
        $results[] = "messages.{$col}: EXISTS";
    }
}

// =====================================================
// 6. ALTER clients - add extra business fields
// =====================================================
$cols = $wpdb->get_col("SHOW COLUMNS FROM {$prefix}omnichannel_clients");

$client_cols = [
    'business_type'     => "VARCHAR(100) DEFAULT NULL COMMENT 'Tipo de negocio'",
    'website'           => "VARCHAR(500) DEFAULT NULL",
    'address'           => "TEXT DEFAULT NULL",
    'logo_url'          => "VARCHAR(500) DEFAULT NULL",
    'timezone'          => "VARCHAR(50) DEFAULT 'America/Santiago'",
    'country_code'      => "VARCHAR(5) DEFAULT 'CL'",
    'currency'          => "VARCHAR(10) DEFAULT 'CLP'",
    'notes'             => "TEXT DEFAULT NULL COMMENT 'Internal admin notes'",
];

foreach ($client_cols as $col => $def) {
    if (!in_array($col, $cols)) {
        $wpdb->query("ALTER TABLE {$prefix}omnichannel_clients ADD COLUMN {$col} {$def}");
        $results[] = "clients.{$col}: " . ($wpdb->last_error ?: 'ADDED');
    } else {
        $results[] = "clients.{$col}: EXISTS";
    }
}

// =====================================================
// OUTPUT
// =====================================================
header('Content-Type: text/plain; charset=utf-8');
echo "=== Omnichannel v2 Migration Results ===\n\n";
foreach ($results as $r) {
    echo "  {$r}\n";
}
echo "\nDone.\n";
