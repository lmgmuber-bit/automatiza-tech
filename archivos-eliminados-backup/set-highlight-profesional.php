<?php
define('WP_USE_THEMES', false);
require('wp-load.php');

global $wpdb;

echo "=== CONFIGURANDO HIGHLIGHT SOLO PARA PLAN PROFESIONAL ===\n\n";

// Desactivar highlight de todos los planes
$wpdb->query("UPDATE wp_automatiza_services SET highlight = 0 WHERE category = 'pricing'");
echo "✓ Highlight desactivado para todos los planes\n";

// Activar highlight solo para Plan Profesional
$result = $wpdb->query("UPDATE wp_automatiza_services SET highlight = 1 WHERE category = 'pricing' AND name = 'Plan Profesional'");

if ($result) {
    echo "✓ Highlight activado solo para Plan Profesional\n\n";
} else {
    echo "❌ Error: " . $wpdb->last_error . "\n\n";
}

// Verificar los cambios
echo "Estado final de los planes:\n";
$plans = $wpdb->get_results("SELECT name, highlight, status FROM wp_automatiza_services WHERE category = 'pricing' ORDER BY price_usd");

foreach ($plans as $plan) {
    $badge = $plan->highlight ? '🔥 CON BADGE NARANJA' : '   Sin badge';
    echo "   $badge | {$plan->name} (Status: {$plan->status})\n";
}

// Limpiar cache
wp_cache_flush();
delete_transient('automatiza_services_pricing');
delete_transient('automatiza_all_services');

echo "\n✓ Cache limpiado\n";
echo "\n¡Listo! Ahora solo el Plan Profesional tendrá el badge naranja 'OFERTA ESPECIAL'\n";
echo "Recarga la página con Ctrl+Shift+R\n";
