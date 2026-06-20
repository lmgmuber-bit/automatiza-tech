<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

// Hora actual del servidor
$now = current_time('mysql');
echo "=== DIAGNÓSTICO RECORDATORIO 1H ===\n\n";
echo "Hora actual servidor: $now\n";

// Rangos para 1h
$start_range = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour'));
$end_range = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour 59 minutes'));
echo "Buscando citas entre: $start_range y $end_range\n\n";

// Todos los leads pendientes
echo "=== TODOS LOS LEADS CON CITAS FUTURAS ===\n";
$all_leads = $wpdb->get_results("SELECT id, name, email, scheduled_date, scheduled_time, recordatorio72h, recordatorio24h, recordatorio1h, CONCAT(scheduled_date, ' ', scheduled_time) as full_datetime FROM $table_name WHERE scheduled_date >= CURDATE() ORDER BY scheduled_date, scheduled_time");

foreach($all_leads as $lead) {
    echo "ID: {$lead->id} | {$lead->name} | {$lead->email}\n";
    echo "   Cita: {$lead->full_datetime}\n";
    echo "   Recordatorios: 72h={$lead->recordatorio72h}, 24h={$lead->recordatorio24h}, 1h={$lead->recordatorio1h}\n";
    
    // Verificar si debería aparecer en el rango
    if ($lead->full_datetime >= $start_range && $lead->full_datetime <= $end_range) {
        echo "   >>> DEBERÍA APARECER EN RECORDATORIO 1H\n";
        if ($lead->recordatorio1h == 1) {
            echo "   >>> PERO YA FUE MARCADO COMO ENVIADO\n";
        }
    }
    echo "\n";
}

echo "\n=== QUERY EXACTA DEL ENDPOINT 1H ===\n";
$query = $wpdb->prepare(
    "SELECT * FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND recordatorio1h = 0",
    $start_range, $end_range
);
echo "Query: $query\n\n";

$leads_1h = $wpdb->get_results($query);
echo "Resultados: " . count($leads_1h) . " leads\n";
print_r($leads_1h);
