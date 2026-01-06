<?php
/**
 * Script para resetear confirmación de 1h para pruebas
 * Ejecutar: https://automatizatech.cl/reset-confirmation-1h.php
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

// Resetear confirmación de 1h para leads 71 y 74
$result = $wpdb->query("
    UPDATE $table_name 
    SET confirmed_attendance1h = 0,
        confirmed_attendance1h_wa = 0
    WHERE id IN (71, 74)
");

echo "<h2>Reset de confirmación 1h</h2>";
echo "<p>Filas actualizadas: " . $result . "</p>";

// Mostrar estado actual
$leads = $wpdb->get_results("
    SELECT id, name, scheduled_date, scheduled_time, 
           confirmed_attendance1h, confirmed_attendance1h_wa,
           recordatorio1h, recordatorio1h_wa
    FROM $table_name 
    WHERE id IN (71, 74)
");

echo "<h3>Estado actual de los leads:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Fecha</th><th>Hora</th><th>Conf 1h Email</th><th>Conf 1h WA</th><th>Rec 1h Email</th><th>Rec 1h WA</th></tr>";

foreach ($leads as $lead) {
    echo "<tr>";
    echo "<td>{$lead->id}</td>";
    echo "<td>{$lead->name}</td>";
    echo "<td>{$lead->scheduled_date}</td>";
    echo "<td>{$lead->scheduled_time}</td>";
    echo "<td>{$lead->confirmed_attendance1h}</td>";
    echo "<td>{$lead->confirmed_attendance1h_wa}</td>";
    echo "<td>{$lead->recordatorio1h}</td>";
    echo "<td>{$lead->recordatorio1h_wa}</td>";
    echo "</tr>";
}
echo "</table>";

// Mostrar hora actual
echo "<p><strong>Hora actual servidor:</strong> " . current_time('mysql') . "</p>";

// Calcular rangos
$now = current_time('mysql');
$start_range = date('Y-m-d H:i:s', strtotime($now . ' + 30 minutes'));
$end_range = date('Y-m-d H:i:s', strtotime($now . ' + 2 hours'));
echo "<p><strong>Rango 1h WA:</strong> $start_range a $end_range</p>";

echo "<p style='color:green;'><strong>✓ Listo! Ahora prueba el endpoint:</strong></p>";
echo "<a href='/wp-json/automatiza-tech/v1/leads/reminders-wa/1h' target='_blank'>/wp-json/automatiza-tech/v1/leads/reminders-wa/1h</a>";
