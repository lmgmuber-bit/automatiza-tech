<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script de prueba para la validación de disponibilidad
 */

require_once('wp-load.php');
require_once('wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php');

global $wpdb;

echo "=== Prueba de Validación de Disponibilidad ===\n\n";

// Obtener una fecha con DEMO existente
$leads_table = $wpdb->prefix . 'automatiza_leads';
$demo = $wpdb->get_row("SELECT scheduled_date, scheduled_time, name, email FROM $leads_table WHERE scheduled_date IS NOT NULL LIMIT 1");

if ($demo) {
    echo "📋 DEMO encontrada:\n";
    echo "   - Fecha: {$demo->scheduled_date}\n";
    echo "   - Hora: {$demo->scheduled_time}\n";
    echo "   - Cliente: {$demo->name} ({$demo->email})\n\n";
    
    // Probar con ese horario
    echo "🔍 Verificando disponibilidad para {$demo->scheduled_date} {$demo->scheduled_time}...\n";
    $result = automatiza_tech_check_slot_availability($demo->scheduled_date, $demo->scheduled_time);
    
    echo "   Resultado: " . ($result['available'] ? '✅ Disponible' : '❌ No disponible') . "\n";
    if (!$result['available']) {
        echo "   Tipo conflicto: {$result['conflict_type']}\n";
        echo "   Detalles: {$result['conflict_details']}\n";
    }
} else {
    echo "⚠️ No hay DEMOs en la base de datos\n";
}

echo "\n";

// Obtener una fecha con Seguimiento existente
$followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
$followup = $wpdb->get_row("SELECT id, meeting_date, meeting_time, client_name, client_email FROM $followup_table WHERE status != 'cancelled' LIMIT 1");

if ($followup) {
    echo "📋 Seguimiento encontrado:\n";
    echo "   - ID: {$followup->id}\n";
    echo "   - Fecha: {$followup->meeting_date}\n";
    echo "   - Hora: {$followup->meeting_time}\n";
    echo "   - Cliente: {$followup->client_name} ({$followup->client_email})\n\n";
    
    // Probar con ese horario (sin excluir)
    echo "🔍 Verificando disponibilidad para {$followup->meeting_date} {$followup->meeting_time} (sin excluir)...\n";
    $result = automatiza_tech_check_slot_availability($followup->meeting_date, $followup->meeting_time);
    
    echo "   Resultado: " . ($result['available'] ? '✅ Disponible' : '❌ No disponible') . "\n";
    if (!$result['available']) {
        echo "   Tipo conflicto: {$result['conflict_type']}\n";
        echo "   Detalles: {$result['conflict_details']}\n";
    }
    
    // Probar con ese horario (excluyendo el mismo ID - caso de edición)
    echo "\n🔍 Verificando para edición (excluyendo ID {$followup->id})...\n";
    $result2 = automatiza_tech_check_slot_availability($followup->meeting_date, $followup->meeting_time, $followup->id);
    
    echo "   Resultado: " . ($result2['available'] ? '✅ Disponible' : '❌ No disponible') . "\n";
    if (!$result2['available']) {
        echo "   Tipo conflicto: {$result2['conflict_type']}\n";
        echo "   Detalles: {$result2['conflict_details']}\n";
    }
} else {
    echo "⚠️ No hay reuniones de seguimiento en la base de datos\n";
}

echo "\n";

// Probar con horario libre
echo "🔍 Verificando horario libre (2030-12-25 09:00)...\n";
$result3 = automatiza_tech_check_slot_availability('2030-12-25', '09:00');
echo "   Resultado: " . ($result3['available'] ? '✅ Disponible' : '❌ No disponible') . "\n";

echo "\n✅ Pruebas completadas.\n";
