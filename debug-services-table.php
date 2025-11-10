<?php
define('WP_USE_THEMES', false);
require('wp-load.php');

global $wpdb;

echo "=== VERIFICACIÓN DE TABLAS Y DATOS ===\n\n";

// Verificar tablas que existen
echo "1. Verificando tablas existentes:\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_automatiza%'");
foreach ($tables as $table) {
    $table_name = array_values((array)$table)[0];
    echo "   ✓ " . $table_name . "\n";
}

echo "\n2. Verificando estructura de wp_automatiza_services:\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM wp_automatiza_services");
foreach ($columns as $col) {
    echo "   - " . $col->Field . " (" . $col->Type . ")\n";
}

echo "\n3. Verificando TODOS los servicios:\n";
$all_services = $wpdb->get_results("SELECT id, name, category, highlight, active FROM wp_automatiza_services");
echo "   Total de servicios: " . count($all_services) . "\n\n";

foreach ($all_services as $service) {
    echo "   ID: " . $service->id;
    echo " | Nombre: " . $service->name;
    echo " | Categoría: " . $service->category;
    echo " | Highlight: " . $service->highlight;
    echo " | Activo: " . $service->active . "\n";
}

echo "\n4. Verificando específicamente categoría 'pricing':\n";
$pricing = $wpdb->get_results("SELECT * FROM wp_automatiza_services WHERE category = 'pricing'");
echo "   Planes encontrados: " . count($pricing) . "\n";

if (!empty($pricing)) {
    foreach ($pricing as $plan) {
        echo "\n   ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "   Nombre: " . $plan->name . "\n";
        echo "   Highlight: " . ($plan->highlight ? 'SÍ (1)' : 'NO (0)') . "\n";
        echo "   Activo: " . ($plan->active ? 'SÍ (1)' : 'NO (0)') . "\n";
    }
}
