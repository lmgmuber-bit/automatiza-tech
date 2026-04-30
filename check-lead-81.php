<?php
/**
 * Verificar estado de cita #81
 */
require_once 'wp-load.php';
global $wpdb;

$lead_id = 81;

$lead = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}automatiza_leads WHERE id = %d", 
    $lead_id
));

if ($lead) {
    echo "=== Estado de Cita #{$lead_id} ===" . PHP_EOL;
    echo "Nombre: " . $lead->name . PHP_EOL;
    echo "Email: " . $lead->email . PHP_EOL;
    echo "Fecha: " . $lead->scheduled_date . PHP_EOL;
    echo "Hora: " . $lead->scheduled_time . PHP_EOL;
    echo PHP_EOL;
    echo "=== Campos de Estado ===" . PHP_EOL;
    echo "status: " . var_export($lead->status, true) . PHP_EOL;
    echo "confirmed_attendance: " . var_export($lead->confirmed_attendance, true) . PHP_EOL;
    echo "attendance_status: " . var_export($lead->attendance_status ?? 'NO EXISTE', true) . PHP_EOL;
} else {
    echo "Cita #81 no encontrada" . PHP_EOL;
}

// Mostrar estructura de la tabla
echo PHP_EOL . "=== Estructura de campos relevantes ===" . PHP_EOL;
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}automatiza_leads WHERE Field IN ('status', 'confirmed_attendance', 'attendance_status')");
foreach ($columns as $col) {
    echo $col->Field . ": " . $col->Type . " (Default: " . var_export($col->Default, true) . ")" . PHP_EOL;
}
