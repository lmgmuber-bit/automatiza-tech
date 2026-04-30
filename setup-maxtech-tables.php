<?php
/**
 * Script para verificar y crear las tablas necesarias para MAXTECH
 * Ejecutar en PROD
 */
require_once 'wp-load.php';
global $wpdb;

echo "=== Diagnóstico de Tablas MAXTECH ===" . PHP_EOL . PHP_EOL;

// Tablas necesarias
$tables_needed = array(
    'ai_usage_log' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_usage_log (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) DEFAULT NULL,
        user_email varchar(100) DEFAULT NULL,
        model varchar(50) DEFAULT NULL,
        endpoint varchar(100) DEFAULT NULL,
        tokens_input int(11) DEFAULT 0,
        tokens_output int(11) DEFAULT 0,
        tokens_total int(11) DEFAULT 0,
        cost_usd decimal(10,6) DEFAULT 0.000000,
        request_type varchar(50) DEFAULT 'chat',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_id (user_id),
        KEY idx_created_at (created_at),
        KEY idx_model (model)
    ) {$wpdb->get_charset_collate()};",
    
    'crm_chat_historial' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}crm_chat_historial (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        role enum('user','assistant','system') NOT NULL,
        content longtext NOT NULL,
        tokens_used int(11) DEFAULT 0,
        model varchar(50) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_session_id (session_id),
        KEY idx_user_id (user_id),
        KEY idx_created_at (created_at)
    ) {$wpdb->get_charset_collate()};"
);

// Verificar cada tabla
foreach ($tables_needed as $table_short => $create_sql) {
    $table_name = $wpdb->prefix . $table_short;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "✅ $table_name - EXISTE ($count registros)" . PHP_EOL;
    } else {
        echo "❌ $table_name - NO EXISTE" . PHP_EOL;
        echo "   Creando tabla..." . PHP_EOL;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($create_sql);
        
        // Verificar si se creó
        $now_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if ($now_exists) {
            echo "   ✅ Tabla creada correctamente!" . PHP_EOL;
        } else {
            echo "   ❌ Error al crear tabla: " . $wpdb->last_error . PHP_EOL;
        }
    }
}

// Verificar otras tablas relacionadas
echo PHP_EOL . "=== Otras Tablas del Sistema ===" . PHP_EOL;

$other_tables = array(
    'automatiza_leads',
    'automatiza_propuestas', 
    'automatiza_followup_meetings',
    'automatiza_n8n_errors'
);

foreach ($other_tables as $table_short) {
    $table_name = $wpdb->prefix . $table_short;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "✅ $table_name ($count registros)" . PHP_EOL;
    } else {
        echo "❌ $table_name - NO EXISTE" . PHP_EOL;
    }
}

// Verificar configuración de MAXTECH
echo PHP_EOL . "=== Configuración MAXTECH ===" . PHP_EOL;

$n8n_url = get_option('maxtech_n8n_url', 'No configurado');
$n8n_key = get_option('maxtech_n8n_api_key', '');
$openai_key = get_option('openai_api_key', '');

echo "N8N URL: " . $n8n_url . PHP_EOL;
echo "N8N API Key: " . ($n8n_key ? substr($n8n_key, 0, 20) . '...' : 'No configurada') . PHP_EOL;
echo "OpenAI API Key: " . ($openai_key ? substr($openai_key, 0, 10) . '...' : 'No configurada') . PHP_EOL;

echo PHP_EOL . "=== Completado ===" . PHP_EOL;
