<?php
define('WP_USE_THEMES', false);
require('wp-load.php');

global $wpdb;

echo "=== VERIFICACIÓN DE PLANES EN BASE DE DATOS ===\n\n";

$plans = $wpdb->get_results("SELECT * FROM wp_automatiza_services WHERE category = 'pricing' ORDER BY display_order");

if (empty($plans)) {
    echo "❌ No se encontraron planes en la base de datos\n";
} else {
    echo "✓ Se encontraron " . count($plans) . " planes:\n\n";
    
    foreach ($plans as $plan) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: " . $plan->id . "\n";
        echo "Nombre: " . $plan->name . "\n";
        echo "Precio USD: $" . $plan->price_usd . "\n";
        echo "Destacado (highlight): " . ($plan->highlight ? '✓ SÍ' : '✗ NO') . "\n";
        echo "Activo: " . ($plan->active ? '✓ SÍ' : '✗ NO') . "\n";
        echo "Orden: " . $plan->display_order . "\n";
        echo "Texto del botón: " . $plan->button_text . "\n";
        echo "\n";
    }
}

echo "\n=== RESUMEN ===\n";
$highlighted = $wpdb->get_var("SELECT COUNT(*) FROM wp_automatiza_services WHERE category = 'pricing' AND highlight = 1");
echo "Planes con highlight activado: " . $highlighted . "\n";

if ($highlighted == 0) {
    echo "\n⚠️  PROBLEMA ENCONTRADO: Ningún plan tiene el campo 'highlight' activado\n";
    echo "El badge 'OFERTA ESPECIAL' solo se muestra cuando highlight = 1\n";
}
