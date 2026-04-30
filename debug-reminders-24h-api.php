<?php
/**
 * Debug script para verificar el endpoint de recordatorios 24h
 * Acceder via: https://automatizatech.cl/debug-reminders-24h-api.php
 */
require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

header('Content-Type: application/json; charset=utf-8');

$now = current_time('mysql');

// Rangos exactos que usa el API
$start_range = date('Y-m-d H:i:s', strtotime($now . ' + 2 hours'));
$end_range = date('Y-m-d H:i:s', strtotime($now . ' + 48 hours'));

// Query exacta que usa el endpoint
$leads = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
     AND recordatorio24h = 0
     AND (confirmed_attendance24h IS NULL OR confirmed_attendance24h = 0)
     AND (status IS NULL OR status != 'cancelled')",
    $start_range, $end_range, $now
));

$debug = [
    'timestamp' => $now,
    'timezone' => wp_timezone_string(),
    'search_range' => [
        'start' => $start_range,
        'end' => $end_range
    ],
    'leads_count' => count($leads),
    'leads_raw' => $leads,
    'leads_formatted' => []
];

// Formatear igual que el endpoint real
if (!empty($leads)) {
    foreach ($leads as $lead) {
        $formatted = clone $lead;
        $formatted->scheduled_date = date('d-m-Y', strtotime($lead->scheduled_date));
        $formatted->scheduled_time = substr($lead->scheduled_time, 0, 5);
        if (!isset($formatted->meet_link)) {
            $formatted->meet_link = '';
        }
        $debug['leads_formatted'][] = $formatted;
    }
}

// Verificar si hay leads que deberían estar pero no están
$all_future = $wpdb->get_results(
    "SELECT id, name, phone, scheduled_date, scheduled_time, recordatorio24h, confirmed_attendance24h, status,
     CONCAT(scheduled_date, ' ', scheduled_time) as datetime_full,
     TIMESTAMPDIFF(HOUR, NOW(), CONCAT(scheduled_date, ' ', scheduled_time)) as hours_until
     FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) > NOW()
     ORDER BY scheduled_date, scheduled_time"
);

$debug['all_future_leads'] = [];
foreach ($all_future as $lead) {
    $debug['all_future_leads'][] = [
        'id' => $lead->id,
        'name' => $lead->name,
        'phone' => $lead->phone,
        'datetime' => $lead->datetime_full,
        'hours_until' => $lead->hours_until,
        'recordatorio24h' => $lead->recordatorio24h,
        'confirmed_attendance24h' => $lead->confirmed_attendance24h,
        'status' => $lead->status,
        'in_24h_range' => ($lead->hours_until >= 2 && $lead->hours_until <= 48),
        'should_receive' => (
            $lead->hours_until >= 2 && 
            $lead->hours_until <= 48 && 
            $lead->recordatorio24h == 0 &&
            ($lead->confirmed_attendance24h === null || $lead->confirmed_attendance24h == 0) &&
            ($lead->status === null || $lead->status != 'cancelled')
        )
    ];
}

// SQL debug
$debug['sql_query'] = $wpdb->prepare(
    "SELECT * FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND CONCAT(scheduled_date, ' ', scheduled_time) > %s
     AND recordatorio24h = 0
     AND (confirmed_attendance24h IS NULL OR confirmed_attendance24h = 0)
     AND (status IS NULL OR status != 'cancelled')",
    $start_range, $end_range, $now
);

// Posibles problemas detectados
$debug['diagnostics'] = [];

if (empty($leads)) {
    $debug['diagnostics'][] = 'No hay leads para enviar recordatorio 24h';
    
    // Verificar si hay leads que ya tienen recordatorio enviado
    $already_sent = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
         AND recordatorio24h = 1",
        $start_range, $end_range
    ));
    if ($already_sent > 0) {
        $debug['diagnostics'][] = "Hay $already_sent leads en el rango pero ya tienen recordatorio24h = 1";
    }
    
    // Verificar si hay leads cancelados
    $cancelled = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
         AND status = 'cancelled'",
        $start_range, $end_range
    ));
    if ($cancelled > 0) {
        $debug['diagnostics'][] = "Hay $cancelled leads en el rango pero están cancelados";
    }
    
    // Verificar si hay leads ya confirmados
    $confirmed = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
         AND confirmed_attendance24h = 1",
        $start_range, $end_range
    ));
    if ($confirmed > 0) {
        $debug['diagnostics'][] = "Hay $confirmed leads en el rango pero ya confirmaron asistencia (confirmed_attendance24h = 1)";
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
