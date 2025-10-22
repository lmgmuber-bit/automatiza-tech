<?php
// Script para forzar limpieza de cache
require_once 'wp-config.php';
require_once 'wp-load.php';

echo "=== LIMPIANDO CACHE ===\n";

// Limpiar cache de WordPress
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
    echo "✓ Cache de WordPress limpiado\n";
}

// Limpiar cache de objetos
if (function_exists('wp_cache_delete')) {
    wp_cache_delete('automatiza_services_pricing', 'automatiza_services');
    echo "✓ Cache de servicios limpiado\n";
}

// Limpiar cualquier transient relacionado
delete_transient('automatiza_services_pricing');
delete_transient('pricing_services_cache');

echo "✓ Transients limpiados\n";

echo "\n¡Cache limpiado! Intenta recargar la página ahora.\n";
?>