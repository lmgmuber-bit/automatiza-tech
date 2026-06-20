<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Agrega columnas YCloud a la tabla omnichannel_channels.
 * Ejecutar UNA VEZ en PROD: https://tudominio.cl/add-ycloud-columns.php
 * Eliminar después de ejecutar.
 */
define('ABSPATH', dirname(__FILE__) . '/');
require_once(dirname(__FILE__) . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Acceso denegado');
}

global $wpdb;
$table = $wpdb->prefix . 'omnichannel_channels';

$columns = [
    'ycloud_api_key'    => "VARCHAR(255) DEFAULT '' AFTER `webhook_secret`",
    'ycloud_waba_id'    => "VARCHAR(100) DEFAULT '' AFTER `ycloud_api_key`",
    'ycloud_phone_id'   => "VARCHAR(100) DEFAULT '' AFTER `ycloud_waba_id`",
    'ycloud_webhook_id' => "VARCHAR(100) DEFAULT '' AFTER `ycloud_phone_id`",
    'provider'          => "VARCHAR(50) DEFAULT 'ycloud' AFTER `ycloud_webhook_id`",
];

echo "<pre>\n";
echo "=== Migración de columnas YCloud (channels) ===\n\n";

foreach ($columns as $col => $def) {
    $exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
    if ($exists) {
        echo "✓ {$col}: ya existe, se omite\n";
    } else {
        $result = $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
        if ($result !== false) {
            echo "✅ {$col}: columna agregada correctamente\n";
        } else {
            echo "❌ {$col}: ERROR - " . $wpdb->last_error . "\n";
        }
    }
}

// --- Messages table: add ycloud_message_id, whatsapp_message_id ---
$msg_table = $wpdb->prefix . 'omnichannel_messages';
$msg_columns = [
    'ycloud_message_id'   => "VARCHAR(100) DEFAULT '' AFTER `media_url`",
    'whatsapp_message_id' => "VARCHAR(100) DEFAULT '' AFTER `ycloud_message_id`",
    'delivery_status'     => "VARCHAR(30) DEFAULT '' AFTER `whatsapp_message_id`",
    'error_code'          => "VARCHAR(50) DEFAULT '' AFTER `delivery_status`",
    'error_message'       => "TEXT NULL AFTER `error_code`",
];

echo "\n=== Migración de columnas YCloud (messages) ===\n\n";

foreach ($msg_columns as $col => $def) {
    $exists = $wpdb->get_results("SHOW COLUMNS FROM `{$msg_table}` LIKE '{$col}'");
    if ($exists) {
        echo "✓ {$col}: ya existe, se omite\n";
    } else {
        $result = $wpdb->query("ALTER TABLE `{$msg_table}` ADD COLUMN `{$col}` {$def}");
        if ($result !== false) {
            echo "✅ {$col}: columna agregada correctamente\n";
        } else {
            echo "❌ {$col}: ERROR - " . $wpdb->last_error . "\n";
        }
    }
}

echo "\n=== Listo. Elimina este archivo de PROD. ===\n";
echo "</pre>\n";
