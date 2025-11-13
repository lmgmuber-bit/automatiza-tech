<?php
/**
 * Verificar Clientes en la Base de Datos
 */

require_once(__DIR__ . '/wp-load.php');

global $wpdb;

$clients_table = $wpdb->prefix . 'automatiza_tech_clients';

echo "=== VERIFICACIÓN DE CLIENTES ===\n\n";

// Total de clientes
$total = $wpdb->get_var("SELECT COUNT(*) FROM {$clients_table}");
echo "📊 Total de clientes: {$total}\n\n";

if ($total > 0) {
    // Por estado
    $statuses = $wpdb->get_results("
        SELECT contract_status, COUNT(*) as count 
        FROM {$clients_table} 
        GROUP BY contract_status
    ");
    
    echo "📈 Clientes por estado:\n";
    foreach ($statuses as $stat) {
        $status_name = [
            'contacted' => '📞 Contactados',
            'contracted' => '✅ Contratados',
            'active' => '🟢 Activos',
            'inactive' => '🔴 Inactivos'
        ][$stat->contract_status] ?? ($stat->contract_status ?: 'Sin estado');
        
        echo "   {$status_name}: {$stat->count}\n";
    }
    
    echo "\n📋 Últimos 10 clientes:\n";
    $clients = $wpdb->get_results("
        SELECT id, name, email, contract_status, contracted_at, plan_id 
        FROM {$clients_table} 
        ORDER BY id DESC 
        LIMIT 10
    ");
    
    foreach ($clients as $client) {
        echo "\n   ID: {$client->id}\n";
        echo "   Nombre: {$client->name}\n";
        echo "   Email: {$client->email}\n";
        echo "   Estado: " . ($client->contract_status ?: 'Sin estado') . "\n";
        echo "   Plan ID: " . ($client->plan_id ?: 'Sin plan') . "\n";
        echo "   Contratado: " . ($client->contracted_at ?: 'No') . "\n";
        echo "   ---\n";
    }
    
    // Verificar clientes contratados sin factura
    $invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
    
    $without_invoice = $wpdb->get_results("
        SELECT c.id, c.name, c.email, c.plan_id
        FROM {$clients_table} c
        LEFT JOIN {$invoices_table} i ON CONCAT('AT-', DATE_FORMAT(c.contracted_at, '%Y%m%d'), '-', LPAD(c.id, 4, '0')) = i.invoice_number
        WHERE c.contract_status = 'contracted' AND i.id IS NULL
    ");
    
    if (!empty($without_invoice)) {
        echo "\n⚠️  Clientes contratados SIN factura: " . count($without_invoice) . "\n";
        foreach ($without_invoice as $client) {
            echo "   - {$client->name} (ID: {$client->id}, Plan: " . ($client->plan_id ?: 'Sin plan') . ")\n";
        }
    } else {
        echo "\n✅ Todos los clientes contratados tienen factura\n";
    }
} else {
    echo "⚠️  No hay clientes registrados en la base de datos\n";
    echo "\nPara crear clientes de prueba:\n";
    echo "1. Ve al panel de WordPress → Contactos\n";
    echo "2. Agrega un nuevo contacto\n";
    echo "3. Muévelo a 'Contratado' y selecciona un plan\n";
}

echo "\n=================================\n";
