<?php
// Test del shortcode específicamente
require_once 'wp-config.php';
require_once 'wp-load.php';
require_once 'wp-content/themes/automatiza-tech/services-frontend.php';

echo "=== TEST SHORTCODE PRICING ===\n";

// Test directo del shortcode
$output = do_shortcode('[pricing_services]');

echo "Longitud del output: " . strlen($output) . " caracteres\n";

// Buscar colores en el output
if (strpos($output, '#3b82f6') !== false) {
    echo "✓ Color azul Plan Básico encontrado\n";
} else {
    echo "✗ Color azul Plan Básico NO encontrado\n";
}

if (strpos($output, '#4774f0') !== false) {
    echo "✓ Color azul Plan Profesional encontrado\n";
} else {
    echo "✗ Color azul Plan Profesional NO encontrado\n";
}

if (strpos($output, '#ffae00') !== false) {
    echo "✓ Color amarillo Plan Enterprise encontrado\n";
} else {
    echo "✗ Color amarillo Plan Enterprise NO encontrado\n";
}

// Mostrar una muestra del HTML generado
echo "\nPrimeros 500 caracteres del HTML generado:\n";
echo substr($output, 0, 500) . "...\n";
?>