<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Debug de recordatorios - Subir a producción temporalmente
 * ELIMINAR DESPUÉS DE DIAGNOSTICAR
 */
require_once('wp-load.php');

header('Content-Type: application/json');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

$now = current_time('mysql');
$timezone = wp_timezone_string();

// Rangos para cada tipo de recordatorio
$ranges = [
    '1h' => [
        'start' => date('Y-m-d H:i:s', strtotime($now . ' + 1 hour')),
        'end' => date('Y-m-d H:i:s', strtotime($now . ' + 1 hour 59 minutes'))
    ],
    '24h' => [
        'start' => date('Y-m-d H:i:s', strtotime($now . ' + 2 hours')),
        'end' => date('Y-m-d H:i:s', strtotime($now . ' + 24 hours'))
    ],
    '72h' => [
        'start' => date('Y-m-d H:i:s', strtotime($now . ' + 49 hours')),
        'end' => date('Y-m-d H:i:s', strtotime($now . ' + 72 hours'))
    ]
];

// Obtener todos los leads con citas futuras
$all_leads = $wpdb->get_results("
    SELECT id, name, email, scheduled_date, scheduled_time, 
           recordatorio72h, recordatorio24h, recordatorio1h,
           CONCAT(scheduled_date, ' ', scheduled_time) as full_datetime
    FROM $table_name 
    WHERE scheduled_date >= CURDATE() 
    ORDER BY scheduled_date, scheduled_time
");

// Analizar cada lead
$leads_analysis = [];
foreach ($all_leads as $lead) {
    $analysis = [
        'id' => $lead->id,
        'name' => $lead->name,
        'email' => $lead->email,
        'cita_datetime' => $lead->full_datetime,
        'recordatorios' => [
            '72h' => (bool)$lead->recordatorio72h,
            '24h' => (bool)$lead->recordatorio24h,
            '1h' => (bool)$lead->recordatorio1h
        ],
        'deberia_aparecer_en' => []
    ];
    
    // Verificar en qué rangos debería aparecer
    foreach ($ranges as $type => $range) {
        $column = 'recordatorio' . $type;
        if ($lead->full_datetime >= $range['start'] && $lead->full_datetime <= $range['end']) {
            if ($lead->$column == 0) {
                $analysis['deberia_aparecer_en'][] = $type;
            } else {
                $analysis['ya_enviado'][] = $type;
            }
        }
    }
    
    $leads_analysis[] = $analysis;
}

// Resultado del endpoint actual
$endpoint_1h = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, email FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND recordatorio1h = 0",
    $ranges['1h']['start'], $ranges['1h']['end']
));

$response = [
    'server_info' => [
        'current_time' => $now,
        'timezone' => $timezone,
        'php_timezone' => date_default_timezone_get()
    ],
    'ranges' => $ranges,
    'leads_futuros' => $leads_analysis,
    'endpoint_1h_result' => $endpoint_1h,
    'total_leads_futuros' => count($all_leads)
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
