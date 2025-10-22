<?php
// Cargar WordPress
require_once('wp-config.php');
require_once('wp-load.php');

echo "=== VERIFICANDO SERVICIOS EN LA BD ===\n\n";

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

// Obtener todos los servicios
$services = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id");

if ($services) {
    echo "SERVICIOS ENCONTRADOS:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($services as $service) {
        echo "ID: {$service->id}\n";
        echo "Nombre: {$service->name}\n";
        echo "Categoría: {$service->category}\n";
        echo "Status: {$service->status}\n";
        echo "Precio USD: $" . number_format($service->price_usd, 2) . "\n";
        echo "Precio CLP: $" . number_format($service->price_clp, 0) . "\n";
        echo "Icono: {$service->icon}\n";
        echo "Destacado: " . ($service->highlight ? 'Sí' : 'No') . "\n";
        echo "Descripción: " . substr($service->description, 0, 50) . "...\n";
        echo "Creado: {$service->created_at}\n";
        echo str_repeat("-", 40) . "\n";
    }
    
    // Buscar específicamente el servicio "AT" o similares
    echo "\nBUSCANDO SERVICIO 'AT' o 'Atención':\n";
    $at_services = $wpdb->get_results("SELECT * FROM {$table_name} WHERE name LIKE '%Atención%' OR name LIKE '%AT%'");
    
    if ($at_services) {
        foreach ($at_services as $service) {
            echo "ENCONTRADO: ID {$service->id} - {$service->name} ({$service->category})\n";
            echo "Status: {$service->status}\n";
            echo "¿Puede editarse? " . ($service->status === 'active' ? 'SÍ' : 'NO') . "\n";
        }
    } else {
        echo "No se encontró servicio específico con 'AT' o 'Atención'\n";
    }
    
} else {
    echo "No se encontraron servicios\n";
}

echo "\n=== FIN VERIFICACIÓN ===\n";
?>