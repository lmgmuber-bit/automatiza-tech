<?php
/**
 * Test directo de la función get_service_details
 */

// Cargar WordPress
require_once __DIR__ . '/wp-load.php';

// Verificar que estemos logueados
if (!is_user_logged_in()) {
    echo "Error: Debes estar logueado\n";
    exit;
}

// Simular la llamada POST
$_POST['action'] = 'get_service_details';
$_POST['service_id'] = '4';
$_POST['nonce'] = wp_create_nonce('automatiza_services_nonce');

echo "=== TEST DE get_service_details ===\n";
echo "Simulando llamada AJAX...\n";

// Capturar la salida
ob_start();

try {
    // Crear instancia del manager
    require_once __DIR__ . '/wp-content/themes/automatiza-tech/inc/services-manager.php';
    $manager = new AutomatizaServicesManager();
    
    // Ejecutar el método
    $manager->get_service_details();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();

echo "Salida capturada:\n";
echo "Longitud: " . strlen($output) . " bytes\n";

// Mostrar los primeros caracteres en hex
echo "Primeros 20 bytes (hex): ";
for ($i = 0; $i < min(20, strlen($output)); $i++) {
    echo sprintf('%02X ', ord($output[$i]));
}
echo "\n";

// Mostrar contenido
echo "Contenido:\n";
echo $output;
echo "\n";

// Intentar decodificar como JSON
echo "\n=== ANÁLISIS JSON ===\n";
$json_data = json_decode($output, true);
$json_error = json_last_error();

if ($json_error === JSON_ERROR_NONE) {
    echo "✅ JSON válido\n";
    print_r($json_data);
} else {
    echo "❌ Error JSON: " . json_last_error_msg() . "\n";
    
    // Limpiar posibles caracteres problemáticos
    $cleaned_output = trim($output);
    $cleaned_output = preg_replace('/^[\x00-\x1F\x80-\xFF]/', '', $cleaned_output); // Remover caracteres de control
    
    echo "Intentando con output limpio:\n";
    echo "Output limpio: '$cleaned_output'\n";
    
    $json_data = json_decode($cleaned_output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON válido después de limpieza\n";
        print_r($json_data);
    } else {
        echo "❌ Aún hay error JSON: " . json_last_error_msg() . "\n";
    }
}
?>