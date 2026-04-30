<?php
/**
 * Script para corregir el source de registros que vienen de WhatsApp pero tienen 'web'
 * 
 * Criterio: Si tiene teléfono y NO tiene session_id, vino de WhatsApp
 * 
 * Uso: https://automatizatech.cl/fix-whatsapp-source.php
 * 
 * ELIMINAR DESPUÉS DE USAR
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CORRIGIENDO SOURCE DE REGISTROS WHATSAPP ===\n\n";

// Buscar registros que tienen teléfono, no tienen session_id, y source='web'
$leads_to_fix = $wpdb->get_results(
    "SELECT id, name, phone, session_id, source 
     FROM $table_name 
     WHERE source = 'web' 
     AND phone IS NOT NULL 
     AND phone != ''
     AND (session_id IS NULL OR session_id = '')"
);

echo "Registros encontrados con teléfono, sin session_id, source='web': " . count($leads_to_fix) . "\n\n";

if (empty($leads_to_fix)) {
    echo "✅ No hay registros que corregir.\n";
    exit;
}

echo "REGISTROS A CORREGIR:\n";
echo str_repeat('-', 60) . "\n";

foreach ($leads_to_fix as $lead) {
    echo "ID: {$lead->id} | {$lead->name} | Tel: {$lead->phone}\n";
}

echo str_repeat('-', 60) . "\n\n";

// Preguntar si desea actualizar
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "⚠️  Para confirmar la actualización, añade ?confirm=yes a la URL\n";
    echo "URL: " . home_url('/fix-whatsapp-source.php?confirm=yes') . "\n";
    exit;
}

// Actualizar los registros
$updated = $wpdb->query(
    "UPDATE $table_name 
     SET source = 'whatsapp' 
     WHERE source = 'web' 
     AND phone IS NOT NULL 
     AND phone != ''
     AND (session_id IS NULL OR session_id = '')"
);

echo "✅ ACTUALIZACIÓN COMPLETADA\n";
echo "Registros actualizados a source='whatsapp': $updated\n";

echo "\n=== SCRIPT COMPLETADO ===\n";
