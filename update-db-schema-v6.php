<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar columnas de confirmación por tipo de recordatorio
 * Ejecutar una vez en producción
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

echo "<h2>Actualizando esquema de base de datos a v6</h2>";

// Agregar confirmed_attendance72h si no existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_attendance72h'");
if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_attendance72h tinyint(1) DEFAULT 0");
    echo "<p>✅ Columna confirmed_attendance72h agregada: " . ($result !== false ? 'OK' : 'ERROR') . "</p>";
} else {
    echo "<p>ℹ️ Columna confirmed_attendance72h ya existe</p>";
}

// Agregar confirmed_attendance24h si no existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_attendance24h'");
if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_attendance24h tinyint(1) DEFAULT 0");
    echo "<p>✅ Columna confirmed_attendance24h agregada: " . ($result !== false ? 'OK' : 'ERROR') . "</p>";
} else {
    echo "<p>ℹ️ Columna confirmed_attendance24h ya existe</p>";
}

// Agregar confirmed_attendance1h si no existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'confirmed_attendance1h'");
if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN confirmed_attendance1h tinyint(1) DEFAULT 0");
    echo "<p>✅ Columna confirmed_attendance1h agregada: " . ($result !== false ? 'OK' : 'ERROR') . "</p>";
} else {
    echo "<p>ℹ️ Columna confirmed_attendance1h ya existe</p>";
}

// Actualizar opción de versión
update_option('automatiza_leads_table_created_v6', true);
echo "<p>✅ Opción automatiza_leads_table_created_v6 actualizada</p>";

// Mostrar estructura actual de la tabla
echo "<h3>Estructura actual de la tabla:</h3>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
echo "<table border='1' cellpadding='5'><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td>{$col->Field}</td><td>{$col->Type}</td><td>{$col->Null}</td><td>{$col->Default}</td></tr>";
}
echo "</table>";

echo "<br><p><strong>⚠️ Recuerda eliminar este archivo después de ejecutarlo en producción.</strong></p>";
