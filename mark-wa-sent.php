<?php
/**
 * Script rápido para marcar recordatorios WhatsApp como enviados
 * Uso: https://automatizatech.cl/mark-wa-sent.php?id=71&type=24h
 * 
 * ELIMINAR DESPUÉS DE USAR
 */

require_once('wp-load.php');

$lead_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

header('Content-Type: application/json; charset=utf-8');

if (!$lead_id || !in_array($type, ['72h', '24h', '1h'])) {
    echo json_encode(['error' => 'Parámetros inválidos. Uso: ?id=71&type=24h']);
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';
$column = 'recordatorio' . $type . '_wa';

$result = $wpdb->update(
    $table_name,
    array($column => 1),
    array('id' => $lead_id),
    array('%d'),
    array('%d')
);

if ($result !== false) {
    echo json_encode([
        'success' => true,
        'message' => "Lead ID $lead_id marcado con $column = 1",
        'rows_affected' => $result
    ]);
} else {
    echo json_encode([
        'error' => 'Error al actualizar: ' . $wpdb->last_error
    ]);
}
