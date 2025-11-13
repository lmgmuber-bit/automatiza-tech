<?php
/**
 * Crear Clientes de Prueba Contratados
 * 
 * Este script crea clientes de ejemplo en estado "contratado" 
 * para poder probar el generador de facturas masivo.
 */

require_once(__DIR__ . '/wp-load.php');

global $wpdb;

$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
$services_table = $wpdb->prefix . 'automatiza_services';

echo "=== CREAR CLIENTES DE PRUEBA ===\n\n";

// Verificar que hay planes activos
$plans = $wpdb->get_results("SELECT id, name FROM {$services_table} WHERE status = 'active' ORDER BY id");

if (empty($plans)) {
    echo "❌ ERROR: No hay planes activos en el sistema\n";
    echo "Ejecuta primero: php activate-plans.php\n";
    exit;
}

echo "📋 Planes disponibles:\n";
foreach ($plans as $plan) {
    echo "   - ID {$plan->id}: {$plan->name}\n";
}
echo "\n";

// Clientes de prueba
$test_clients = [
    [
        'name' => 'María González',
        'email' => 'maria.gonzalez@example.com',
        'phone' => '+56 9 8765 4321',
        'company' => 'Comercial González SpA',
        'message' => 'Necesito una tienda online profesional',
        'plan_index' => 0
    ],
    [
        'name' => 'Juan Pérez',
        'email' => 'juan.perez@example.com',
        'phone' => '+56 9 7654 3210',
        'company' => 'Servicios Pérez Ltda',
        'message' => 'Quiero digitalizar mi negocio',
        'plan_index' => 1
    ],
    [
        'name' => 'Carlos López',
        'email' => 'carlos.lopez@example.com',
        'phone' => '+56 9 6543 2109',
        'company' => 'Constructora López y Asociados',
        'message' => 'Necesito presencia web corporativa',
        'plan_index' => 0
    ],
    [
        'name' => 'Ana Martínez',
        'email' => 'ana.martinez@example.com',
        'phone' => '+56 9 5432 1098',
        'company' => 'Estética Ana',
        'message' => 'Quiero sistema de reservas online',
        'plan_index' => 2
    ],
    [
        'name' => 'Luis Rodríguez',
        'email' => 'luis.rodriguez@example.com',
        'phone' => '+56 9 4321 0987',
        'company' => 'Tech Solutions SRL',
        'message' => 'Necesito plataforma empresarial completa',
        'plan_index' => 2
    ]
];

$created = 0;
$skipped = 0;

foreach ($test_clients as $client_data) {
    // Verificar si ya existe
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$clients_table} WHERE email = %s",
        $client_data['email']
    ));
    
    if ($existing) {
        echo "⚠️  Cliente ya existe: {$client_data['name']} ({$client_data['email']})\n";
        $skipped++;
        continue;
    }
    
    // Seleccionar plan
    $plan_id = $plans[$client_data['plan_index']]->id ?? $plans[0]->id;
    $plan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$services_table} WHERE id = %d",
        $plan_id
    ));
    
    // Calcular valor del contrato
    $contract_value = floatval($plan->price_clp) * 1.19; // Con IVA
    
    // Insertar cliente
    $result = $wpdb->insert(
        $clients_table,
        [
            'name' => $client_data['name'],
            'email' => $client_data['email'],
            'phone' => $client_data['phone'],
            'company' => $client_data['company'],
            'original_message' => $client_data['message'],
            'contract_status' => 'contracted',
            'plan_id' => $plan_id,
            'contract_value' => $contract_value,
            'project_type' => 'web_development',
            'contracted_at' => current_time('mysql'),
            'contacted_at' => current_time('mysql')
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s']
    );
    
    if ($result) {
        $created++;
        echo "✅ Cliente creado: {$client_data['name']}\n";
        echo "   Email: {$client_data['email']}\n";
        echo "   Plan: {$plan->name} (ID: {$plan_id})\n";
        echo "   Valor: $" . number_format($contract_value, 0, ',', '.') . "\n";
        echo "   ---\n";
    } else {
        echo "❌ Error al crear: {$client_data['name']}\n";
        echo "   Error: {$wpdb->last_error}\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "✅ Clientes creados: {$created}\n";
echo "⚠️  Ya existían: {$skipped}\n";

if ($created > 0) {
    echo "\n🎉 ¡Listo! Ahora puedes generar facturas con:\n";
    echo "   php generate-invoices-for-contracted.php\n";
    echo "\nO desde el navegador:\n";
    echo "   http://localhost/automatiza-tech/generate-invoices-for-contracted.php\n";
}

echo "\n=================================\n";
