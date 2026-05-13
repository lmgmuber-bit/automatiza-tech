<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar campos de recordatorios por WhatsApp separados de los de correo
 * 
 * Campos existentes (para correo):
 * - recordatorio72h, recordatorio24h, recordatorio1h
 * 
 * Nuevos campos (para WhatsApp):
 * - recordatorio72h_wa, recordatorio24h_wa, recordatorio1h_wa
 * 
 * Ejecutar una sola vez: https://automatizatech.cl/add-whatsapp-reminder-fields.php
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

header('Content-Type: text/plain; charset=utf-8');

echo "=== AGREGANDO CAMPOS DE RECORDATORIOS POR WHATSAPP ===\n\n";

// Lista de columnas a agregar
$new_columns = [
    'recordatorio72h_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio 72h por WhatsApp enviado'",
    'recordatorio24h_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio 24h por WhatsApp enviado'",
    'recordatorio1h_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Recordatorio 1h por WhatsApp enviado'",
    'confirmed_attendance72h_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Confirmó asistencia desde WhatsApp 72h'",
    'confirmed_attendance24h_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Confirmó asistencia desde WhatsApp 24h'",
    'confirmed_attendance1h_wa' => "TINYINT(1) DEFAULT 0 COMMENT 'Confirmó asistencia desde WhatsApp 1h'",
];

$added = 0;
$skipped = 0;

foreach ($new_columns as $column_name => $column_definition) {
    // Verificar si la columna ya existe
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
        $table_name, $column_name
    ));
    
    if (empty($column_exists)) {
        $sql = "ALTER TABLE $table_name ADD COLUMN $column_name $column_definition";
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo "✅ Columna '$column_name' agregada correctamente.\n";
            $added++;
        } else {
            echo "❌ Error al agregar columna '$column_name': " . $wpdb->last_error . "\n";
        }
    } else {
        echo "⏭️  Columna '$column_name' ya existe, saltando.\n";
        $skipped++;
    }
}

echo "\n=== RESUMEN ===\n";
echo "Columnas agregadas: $added\n";
echo "Columnas existentes (saltadas): $skipped\n";

// Mostrar estructura actual de la tabla
echo "\n=== ESTRUCTURA ACTUAL DE CAMPOS DE RECORDATORIOS ===\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name WHERE Field LIKE 'recordatorio%' OR Field LIKE 'confirmed_attendance%'");

echo "\nCAMPOS DE CORREO (existentes):\n";
foreach ($columns as $col) {
    if (strpos($col->Field, '_wa') === false) {
        echo "  - {$col->Field} ({$col->Type})\n";
    }
}

echo "\nCAMPOS DE WHATSAPP (nuevos):\n";
foreach ($columns as $col) {
    if (strpos($col->Field, '_wa') !== false) {
        echo "  - {$col->Field} ({$col->Type})\n";
    }
}

echo "\n=== SCRIPT COMPLETADO ===\n";
echo "Ahora debes actualizar el endpoint de la API para usar los nuevos campos.\n";
