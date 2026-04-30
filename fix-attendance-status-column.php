<?php
/**
 * Script para agregar columna attendance_status a la tabla de leads
 * Ejecutar en PROD para arreglar el problema de actualización de estado
 */
require_once 'wp-load.php';
global $wpdb;

$table_name = $wpdb->prefix . 'automatiza_leads';

echo "=== Verificando columna attendance_status ===" . PHP_EOL;

// Verificar si existe la columna
$column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'attendance_status'");

if ($column_exists) {
    echo "✅ La columna attendance_status ya existe." . PHP_EOL;
} else {
    echo "⚠️ La columna attendance_status NO existe. Agregando..." . PHP_EOL;
    
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN attendance_status VARCHAR(20) DEFAULT NULL AFTER confirmed_attendance");
    
    if ($result !== false) {
        echo "✅ Columna attendance_status agregada correctamente." . PHP_EOL;
        
        // Sincronizar valores existentes de confirmed_attendance
        echo PHP_EOL . "Sincronizando valores existentes..." . PHP_EOL;
        
        // Los que tienen confirmed_attendance = 0 -> attendance_status = 'no_show'
        $updated_noshow = $wpdb->query("UPDATE $table_name SET attendance_status = 'no_show' WHERE confirmed_attendance = 0 AND attendance_status IS NULL");
        echo "- Actualizados a 'no_show': $updated_noshow" . PHP_EOL;
        
        // Los que tienen confirmed_attendance = 1 -> attendance_status = 'attended'
        $updated_attended = $wpdb->query("UPDATE $table_name SET attendance_status = 'attended' WHERE confirmed_attendance = 1 AND attendance_status IS NULL");
        echo "- Actualizados a 'attended': $updated_attended" . PHP_EOL;
        
    } else {
        echo "❌ Error al agregar columna: " . $wpdb->last_error . PHP_EOL;
    }
}

// Mostrar estructura actual
echo PHP_EOL . "=== Estructura actual de la tabla ===" . PHP_EOL;
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
foreach ($columns as $col) {
    echo $col->Field . " | " . $col->Type . " | " . ($col->Null === 'YES' ? 'NULL' : 'NOT NULL') . PHP_EOL;
}
