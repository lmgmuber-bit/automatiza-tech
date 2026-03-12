<?php
/**
 * Script para actualizar el esquema de la tabla de reuniones de seguimiento
 * Agrega los campos: confirmed_at, whatsapp_sent
 * 
 * Ejecutar una sola vez: php update-followup-schema.php
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_followup_meetings';

echo "=== Actualizando esquema de $table_name ===\n\n";

// Verificar si la tabla existe
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    echo "❌ Error: La tabla $table_name no existe.\n";
    exit(1);
}

// Agregar columna confirmed_at si no existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_at'");
if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_at datetime DEFAULT NULL AFTER status");
    if ($result !== false) {
        echo "✅ Columna 'confirmed_at' agregada correctamente.\n";
    } else {
        echo "❌ Error al agregar columna 'confirmed_at': " . $wpdb->last_error . "\n";
    }
} else {
    echo "ℹ️ Columna 'confirmed_at' ya existe.\n";
}

// Agregar columna whatsapp_sent si no existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'whatsapp_sent'");
if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN whatsapp_sent tinyint(1) DEFAULT 0 AFTER email_sent");
    if ($result !== false) {
        echo "✅ Columna 'whatsapp_sent' agregada correctamente.\n";
    } else {
        echo "❌ Error al agregar columna 'whatsapp_sent': " . $wpdb->last_error . "\n";
    }
} else {
    echo "ℹ️ Columna 'whatsapp_sent' ya existe.\n";
}

echo "\n=== Estructura actual de la tabla ===\n";
$columns = $wpdb->get_results("DESCRIBE $table_name");
foreach ($columns as $col) {
    echo "  - {$col->Field}: {$col->Type}" . ($col->Null === 'NO' ? ' NOT NULL' : '') . "\n";
}

echo "\n✅ Actualización completada.\n";
