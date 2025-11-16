<?php
// Test directo del frontend
require_once 'wp-config.php';
require_once 'wp-load.php';

echo "=== TEST DIRECTO FRONTEND ===\n";

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

$services = $wpdb->get_results(
    "SELECT * FROM $table_name WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC"
);

echo "Servicios encontrados: " . count($services) . "\n\n";

foreach ($services as $service) {
    echo "Plan: " . $service->name . "\n";
    echo "Card color desde DB: " . ($service->card_color ?: 'NULL') . "\n";
    echo "Button color desde DB: " . ($service->button_color ?: 'NULL') . "\n";
    echo "Text color desde DB: " . ($service->text_color ?: 'NULL') . "\n";
    
    // Test variables como en el frontend
    $card_color = $service->card_color ?: '#007cba';
    $button_color = $service->button_color ?: '#28a745';
    $text_color = $service->text_color ?: '#ffffff';
    
    echo "Card color final: " . $card_color . "\n";
    echo "Button color final: " . $button_color . "\n";
    echo "Text color final: " . $text_color . "\n";
    echo "---\n";
}
?>