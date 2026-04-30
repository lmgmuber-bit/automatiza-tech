<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar columnas de recordatorios diarios 8PM y 8AM
 * Para la tabla de SEGUIMIENTOS (followup_meetings)
 * Ejecutar una sola vez en PROD
 * 
 * Columnas:
 * - recordatorio_8pm: Email recordatorio noche anterior
 * - recordatorio_8am: Email recordatorio mañana del día
 * - recordatorio_8pm_wa: WhatsApp recordatorio noche anterior
 * - recordatorio_8am_wa: WhatsApp recordatorio mañana del día
 * - confirmed_attendance_8am: Confirmación desde el recordatorio 8AM
 * - confirmed_attendance_8am_wa: Confirmación desde WhatsApp 8AM
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_followup_meetings';

$columns_to_add = array(
    'recordatorio_8pm' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio email 8PM (citas día siguiente)'",
    'recordatorio_8am' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio email 8AM (citas mismo día)'",
    'recordatorio_8pm_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio WhatsApp 8PM (citas día siguiente)'",
    'recordatorio_8am_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio WhatsApp 8AM (citas mismo día)'",
    'confirmed_attendance_8am' => "TINYINT(1) DEFAULT 0 COMMENT 'Confirmación desde email 8AM'",
    'confirmed_attendance_8am_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Confirmación desde WhatsApp 8AM'"
);

echo "<h2>Agregando columnas de recordatorios diarios a tabla de SEGUIMIENTOS: $table_name</h2>";

foreach ($columns_to_add as $column_name => $column_def) {
    // Verificar si la columna ya existe
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $table_name LIKE %s",
        $column_name
    ));
    
    if (empty($column_exists)) {
        $sql = "ALTER TABLE $table_name ADD COLUMN $column_name $column_def";
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Columna <strong>$column_name</strong> agregada correctamente</p>";
        } else {
            echo "<p style='color: red;'>❌ Error agregando columna <strong>$column_name</strong>: " . $wpdb->last_error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Columna <strong>$column_name</strong> ya existe</p>";
    }
}

echo "<h3>Estructura actual de la tabla:</h3>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
echo "<pre>";
foreach ($columns as $col) {
    if (strpos($col->Field, 'recordatorio') !== false || strpos($col->Field, 'confirmed') !== false) {
        echo "  - " . $col->Field . " (" . $col->Type . ")\n";
    }
}
echo "</pre>";

echo "<p><strong>Listo!</strong> Ahora puedes subir los archivos de workflows a N8N.</p>";
?>
