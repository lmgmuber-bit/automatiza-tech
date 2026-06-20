<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar columna no_show_email_sent a la tabla de citas
 * Ejecutar una sola vez: https://automatizatech.cl/update-leads-table-noshow.php
 */

require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Sin permisos');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

echo "<h1>Actualizando tabla de citas...</h1>";

// Verificar si la columna ya existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'no_show_email_sent'");

if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN no_show_email_sent DATETIME NULL DEFAULT NULL AFTER confirmed_attendance");
    
    if ($result !== false) {
        echo "<p style='color:green;'>✅ Columna 'no_show_email_sent' agregada correctamente.</p>";
    } else {
        echo "<p style='color:red;'>❌ Error al agregar columna: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ La columna 'no_show_email_sent' ya existe.</p>";
}

echo "<p><strong>Puedes eliminar este archivo después de ejecutarlo.</strong></p>";
echo "<p><a href='" . admin_url('admin.php?page=automatiza-leads-manager') . "'>← Volver a Gestión de Citas</a></p>";
?>
