<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Debug script para verificar recordatorios
 * Acceder via: https://automatizatech.cl/debug-reminders-prod.php
 */
require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

header('Content-Type: text/plain; charset=utf-8');

// Hora actual del servidor
$now = current_time('mysql');
$timezone = wp_timezone_string();

echo "=== DIAGNÓSTICO DE RECORDATORIOS ===\n\n";
echo "Fecha/Hora actual servidor: $now\n";
echo "Zona horaria configurada: $timezone\n";
echo "Timestamp Unix: " . current_time('timestamp') . "\n\n";

// Todos los leads pendientes
echo "=== TODOS LOS LEADS CON CITAS FUTURAS ===\n\n";
$all_leads = $wpdb->get_results("SELECT id, name, email, scheduled_date, scheduled_time, recordatorio72h, recordatorio24h, recordatorio1h, CONCAT(scheduled_date, ' ', scheduled_time) as full_datetime FROM $table_name WHERE scheduled_date >= CURDATE() ORDER BY scheduled_date, scheduled_time");

if (empty($all_leads)) {
    echo "No hay leads con citas futuras.\n";
} else {
    foreach($all_leads as $lead) {
        $cita_timestamp = strtotime($lead->full_datetime);
        $now_timestamp = strtotime($now);
        $diff_hours = round(($cita_timestamp - $now_timestamp) / 3600, 1);
        
        echo "ID: {$lead->id} | {$lead->name} | {$lead->email}\n";
        echo "   Cita: {$lead->full_datetime} (en {$diff_hours} horas)\n";
        echo "   Recordatorios enviados: 72h={$lead->recordatorio72h}, 24h={$lead->recordatorio24h}, 1h={$lead->recordatorio1h}\n";
        
        // Verificar en qué rango debería estar
        if ($diff_hours > 0 && $diff_hours <= 2) {
            echo "   >>> Rango: RECORDATORIO 1H (30min - 2h)\n";
        } elseif ($diff_hours > 2 && $diff_hours <= 24) {
            echo "   >>> Rango: RECORDATORIO 24H (2h - 24h)\n";
        } elseif ($diff_hours > 49 && $diff_hours <= 72) {
            echo "   >>> Rango: RECORDATORIO 72H (49h - 72h)\n";
        } elseif ($diff_hours > 24 && $diff_hours <= 49) {
            echo "   >>> Rango: ENTRE 24H Y 72H (sin recordatorio programado)\n";
        } else {
            echo "   >>> Rango: FUERA DE RANGO (más de 72h o ya pasó)\n";
        }
        echo "\n";
    }
}

echo "\n=== RANGOS ACTUALES DE BÚSQUEDA ===\n\n";

// 72h
$start_72 = date('Y-m-d H:i:s', strtotime($now . ' + 49 hours'));
$end_72 = date('Y-m-d H:i:s', strtotime($now . ' + 72 hours'));
echo "72H: $start_72  a  $end_72\n";

// 24h
$start_24 = date('Y-m-d H:i:s', strtotime($now . ' + 2 hours'));
$end_24 = date('Y-m-d H:i:s', strtotime($now . ' + 24 hours'));
echo "24H: $start_24  a  $end_24\n";

// 1h
$start_1 = date('Y-m-d H:i:s', strtotime($now . ' + 30 minutes'));
$end_1 = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour 59 minutes'));
echo "1H:  $start_1  a  $end_1\n";

echo "\n=== RESULTADOS POR ENDPOINT ===\n\n";

// Query 72h
$leads_72h = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, scheduled_date, scheduled_time FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND recordatorio72h = 0",
    $start_72, $end_72
));
echo "Endpoint /reminders/72h: " . count($leads_72h) . " leads\n";
foreach($leads_72h as $l) echo "   - ID {$l->id}: {$l->name} ({$l->scheduled_date} {$l->scheduled_time})\n";

// Query 24h
$leads_24h = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, scheduled_date, scheduled_time FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND recordatorio24h = 0",
    $start_24, $end_24
));
echo "\nEndpoint /reminders/24h: " . count($leads_24h) . " leads\n";
foreach($leads_24h as $l) echo "   - ID {$l->id}: {$l->name} ({$l->scheduled_date} {$l->scheduled_time})\n";

// Query 1h
$leads_1h = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, scheduled_date, scheduled_time FROM $table_name 
     WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
     AND recordatorio1h = 0",
    $start_1, $end_1
));
echo "\nEndpoint /reminders/1h: " . count($leads_1h) . " leads\n";
foreach($leads_1h as $l) echo "   - ID {$l->id}: {$l->name} ({$l->scheduled_date} {$l->scheduled_time})\n";

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
