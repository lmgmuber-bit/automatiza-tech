<?php
define('WP_USE_THEMES', false);
require('wp-load.php');

global $wpdb;

echo "=== ACTIVANDO PLANES DE PRECIOS ===\n\n";

// Activar todos los planes de pricing
$result = $wpdb->query("UPDATE wp_automatiza_services SET status = 'active' WHERE category = 'pricing'");

if ($result === false) {
    echo "❌ Error al actualizar: " . $wpdb->last_error . "\n";
} else {
    echo "✓ Se actualizaron $result planes\n\n";
    
    // Verificar los cambios
    echo "Verificando cambios:\n";
    $plans = $wpdb->get_results("SELECT name, status, highlight FROM wp_automatiza_services WHERE category = 'pricing'");
    
    foreach ($plans as $plan) {
        echo "   - {$plan->name}: Status = {$plan->status}, Highlight = " . ($plan->highlight ? 'SÍ' : 'NO') . "\n";
    }
}

// Limpiar cache
wp_cache_flush();
echo "\n✓ Cache limpiado\n";
echo "\n¡Listo! Recarga la página con Ctrl+Shift+R\n";
