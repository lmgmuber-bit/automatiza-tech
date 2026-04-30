<?php
/**
 * Verificar todos los servicios en la base de datos
 */
require_once(__DIR__ . '/wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'automatiza_services';

echo "<h2>🔍 Verificación de Servicios en Base de Datos</h2>";
echo "<pre style='background: #1a1a2e; color: #eee; padding: 20px; border-radius: 10px;'>";

// TODOS los servicios
$all = $wpdb->get_results("SELECT id, name, category, price_clp, price_usd, status FROM $table ORDER BY id ASC");
echo "📦 TOTAL SERVICIOS EN BD: " . count($all) . "\n\n";

// Servicios activos con precio > 0
$active_with_price = $wpdb->get_results("SELECT id, name, category, price_clp, price_usd, status FROM $table WHERE status = 'active' AND (price_clp > 0 OR price_usd > 0) ORDER BY id ASC");
echo "✅ SERVICIOS ACTIVOS CON PRECIO (los que aparecen en cotización): " . count($active_with_price) . "\n\n";

echo str_repeat("=", 80) . "\n";
echo "LISTADO DE TODOS LOS SERVICIOS:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($all as $s) {
    $icon = $s->status === 'active' ? '✅' : '❌';
    $price_ok = ($s->price_clp > 0 || $s->price_usd > 0) ? '💲' : '⚠️ SIN PRECIO';
    echo sprintf("%s ID: %2d | %-45s | Cat: %-10s | USD: %8.2f | CLP: %10s | %s\n", 
        $icon, 
        $s->id, 
        substr($s->name, 0, 45),
        $s->category,
        $s->price_usd,
        number_format($s->price_clp, 0, ',', '.'),
        $price_ok
    );
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "SERVICIOS QUE APARECEN EN EL MODAL DE COTIZACIÓN:\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($active_with_price as $p) {
    echo "📋 ID: {$p->id} | {$p->name} - \${$p->price_usd} USD / \$" . number_format($p->price_clp, 0, ',', '.') . " CLP\n";
}

echo "</pre>";

echo "<p><a href='add-marketplace-mascotas-services.php'>🐾 Ejecutar script para agregar servicios de Marketplace Mascotas</a></p>";
?>
