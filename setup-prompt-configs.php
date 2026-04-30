<?php
/**
 * Migración: Crear tabla wp_omnichannel_prompt_configs
 * Almacena la configuración parametrizada de prompts del bot por canal.
 * 
 * Ejecutar una sola vez: https://automatizatech.cl/setup-prompt-configs.php
 * ELIMINAR después de ejecutar.
 */
require_once __DIR__ . '/wp-load.php';

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';
$charset = $wpdb->get_charset_collate();

$table = $prefix . 'prompt_configs';

$sql = "CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel_id BIGINT UNSIGNED NOT NULL,
    config_name VARCHAR(255) NOT NULL DEFAULT 'Configuración Principal',
    prompt_data LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by VARCHAR(255) DEFAULT '',
    updated_by VARCHAR(255) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_channel (channel_id),
    KEY idx_active (is_active)
) {$charset};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);

// Verify
$cols = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
if ($cols) {
    echo "<h2>✅ Tabla {$table} creada correctamente</h2>";
    echo "<table border='1' cellpadding='4'><tr><th>Column</th><th>Type</th><th>Key</th></tr>";
    foreach ($cols as $c) {
        echo "<tr><td>{$c->Field}</td><td>{$c->Type}</td><td>{$c->Key}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<h2>❌ Error creando tabla</h2>";
}

echo "<br><p style='color:red;font-weight:bold;'>⚠️ ELIMINAR este archivo después de ejecutar.</p>";
