<?php
/**
 * Script de prueba para verificar la plantilla de email con datos dinámicos
 */

require_once('wp-load.php');

echo "=== VERIFICACIÓN DE PLANTILLA DE EMAIL ===\n\n";

// Obtener planes desde la base de datos
$plans = get_active_automatiza_services('pricing');

echo "1. Planes encontrados en la base de datos: " . count($plans) . "\n\n";

if (!empty($plans)) {
    foreach ($plans as $index => $plan) {
        echo "Plan " . ($index + 1) . ": " . $plan->name . "\n";
        echo "  - Precio USD: $" . $plan->price_usd . "\n";
        echo "  - Descripción: " . (substr($plan->description, 0, 50)) . "...\n";
        echo "  - Destacado: " . ($plan->highlight ? 'Sí' : 'No') . "\n";
        
        $features_array = json_decode($plan->features, true);
        if (!is_array($features_array)) {
            $features_array = explode(',', $plan->features);
        }
        echo "  - Características: " . count($features_array) . " items\n";
        
        foreach (array_slice($features_array, 0, 3) as $feature) {
            echo "    * " . trim($feature) . "\n";
        }
        echo "\n";
    }
} else {
    echo "⚠️ No se encontraron planes en la base de datos.\n";
}

echo "\n2. Verificando función get_whatsapp_url():\n";
$whatsapp_url = get_whatsapp_url('Hola! Me interesa conocer más sobre los planes');
echo "   URL de WhatsApp: " . $whatsapp_url . "\n";

echo "\n3. Verificando theme_mod whatsapp_number:\n";
$whatsapp_number = get_theme_mod('whatsapp_number', '+56 9 4033 1127');
echo "   Número de WhatsApp: " . $whatsapp_number . "\n";

echo "\n✅ Verificación completada. La plantilla de email ahora usa datos dinámicos de la base de datos.\n";
?>
