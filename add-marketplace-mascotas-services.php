<?php
/**
 * Script para agregar los servicios del Marketplace de Mascotas
 * Basado en la propuesta enviada al cliente
 * 
 * Ejecutar una sola vez: http://localhost/automatiza-tech/add-marketplace-mascotas-services.php
 */

// Cargar WordPress
require_once(__DIR__ . '/wp-load.php');

// Verificar que solo admin pueda ejecutar
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Acceso denegado. Debes iniciar sesión como administrador.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

echo "<h1>🐾 Agregando Servicios - Marketplace de Mascotas</h1>";
echo "<pre>";

// Definir los servicios del proyecto
$servicios_marketplace = [
    [
        'name' => 'Desarrollo Plataforma Multi-Tienda',
        'category' => 'custom',
        'price_usd' => 1200,
        'price_clp' => 1200000,
        'discount_percent' => 70.00,
        'description' => 'Sitio Web + Panel Administrador completo para marketplace de mascotas',
        'features' => json_encode([
            'Sitio web responsive completo',
            'Panel de administración multi-tienda',
            'Gestión de productos y categorías',
            'Sistema de pagos integrado',
            'Dashboard de ventas y estadísticas',
            'Gestión de usuarios y roles',
            'Sistema de inventario',
            'Notificaciones automáticas'
        ]),
        'icon' => 'fas fa-store',
        'highlight' => 0,
        'button_text' => 'Cotizar',
        'whatsapp_message' => 'Hola! Me interesa el desarrollo de la Plataforma Multi-Tienda para marketplace de mascotas',
        'status' => 'active',
        'service_order' => 1
    ],
    [
        'name' => 'Integración App Móvil Híbrida',
        'category' => 'custom',
        'price_usd' => 500,
        'price_clp' => 500000,
        'discount_percent' => 70.00,
        'description' => 'Configuración para Android y iOS',
        'features' => json_encode([
            'App híbrida multiplataforma',
            'Compatibilidad Android e iOS',
            'Push notifications',
            'Integración con el marketplace web',
            'Diseño UI/UX optimizado',
            'Geolocalización de tiendas',
            'Carrito de compras móvil',
            'Historial de pedidos'
        ]),
        'icon' => 'fas fa-mobile-alt',
        'highlight' => 0,
        'button_text' => 'Cotizar',
        'whatsapp_message' => 'Hola! Me interesa la Integración App Móvil Híbrida',
        'status' => 'active',
        'service_order' => 2
    ],
    [
        'name' => 'Módulo Chatbot con IA',
        'category' => 'custom',
        'price_usd' => 450,
        'price_clp' => 450000,
        'discount_percent' => 70.00,
        'description' => 'Asistente inteligente y sistema de reportes',
        'features' => json_encode([
            'Chatbot con inteligencia artificial',
            'Atención automatizada 24/7',
            'Sistema de reportes automáticos',
            'Integración con WhatsApp Business',
            'Respuestas personalizadas',
            'Análisis de conversaciones',
            'Escalamiento a agentes humanos',
            'Métricas y dashboard de rendimiento'
        ]),
        'icon' => 'fas fa-robot',
        'highlight' => 1, // Destacado
        'button_text' => 'Cotizar',
        'whatsapp_message' => 'Hola! Me interesa el Módulo Chatbot con IA',
        'status' => 'active',
        'service_order' => 3
    ],
    [
        'name' => 'Hosting + Dominio (Primer Año)',
        'category' => 'custom',
        'price_usd' => 180,
        'price_clp' => 180000,
        'discount_percent' => 70.00,
        'description' => 'Incluye servidor premium, certificado SSL y dominio personalizado',
        'features' => json_encode([
            'Servidor premium de alto rendimiento',
            'Certificado SSL incluido',
            'Dominio personalizado (.cl o .com)',
            'Backups automáticos diarios',
            'CDN para velocidad global',
            'Soporte técnico incluido',
            'Email corporativo',
            'Protección DDoS'
        ]),
        'icon' => 'fas fa-server',
        'highlight' => 0,
        'button_text' => 'Cotizar',
        'whatsapp_message' => 'Hola! Me interesa el Hosting + Dominio',
        'status' => 'active',
        'service_order' => 4
    ],
    [
        'name' => 'Despliegue en Producción iOS y Android',
        'category' => 'custom',
        'price_usd' => 200,
        'price_clp' => 200000,
        'discount_percent' => 70.00,
        'description' => 'Incluye publicación en App Store y Google Play, configuración de certificados, testing en producción y soporte post-lanzamiento',
        'features' => json_encode([
            'Publicación en App Store (iOS)',
            'Publicación en Google Play (Android)',
            'Configuración de certificados',
            'Testing completo en producción',
            'Optimización ASO básica',
            'Soporte post-lanzamiento (30 días)',
            'Monitoreo de errores',
            'Actualizaciones críticas incluidas'
        ]),
        'icon' => 'fas fa-rocket',
        'highlight' => 0,
        'button_text' => 'Cotizar',
        'whatsapp_message' => 'Hola! Me interesa el Despliegue en Producción iOS y Android',
        'status' => 'active',
        'service_order' => 5
    ]
];

// Contar servicios existentes en categoría custom
$existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE category = 'custom'");
echo "Servicios existentes en categoría 'custom': $existing\n\n";

$added = 0;
$skipped = 0;

foreach ($servicios_marketplace as $servicio) {
    // Verificar si ya existe un servicio con el mismo nombre
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE name = %s",
        $servicio['name']
    ));
    
    if ($exists > 0) {
        echo "⏭️ SALTADO: '{$servicio['name']}' ya existe\n";
        $skipped++;
        continue;
    }
    
    $result = $wpdb->insert($table_name, $servicio);
    
    if ($result !== false) {
        $id = $wpdb->insert_id;
        $discount = isset($servicio['discount_percent']) ? $servicio['discount_percent'] : 0;
        $price_final_usd = $servicio['price_usd'] * (1 - $discount/100);
        $price_final_clp = $servicio['price_clp'] * (1 - $discount/100);
        
        echo "✅ AGREGADO: '{$servicio['name']}' (ID: $id)\n";
        echo "   - Categoría: {$servicio['category']}\n";
        echo "   - Precio Original: \${$servicio['price_usd']} USD / \$" . number_format($servicio['price_clp'], 0, ',', '.') . " CLP\n";
        if ($discount > 0) {
            echo "   - Descuento: {$discount}%\n";
            echo "   - Precio Final: \$" . number_format($price_final_usd, 0) . " USD / \$" . number_format($price_final_clp, 0, ',', '.') . " CLP\n";
        }
        echo "\n";
        $added++;
    } else {
        echo "❌ ERROR al agregar: '{$servicio['name']}'\n";
        echo "   Error: " . $wpdb->last_error . "\n\n";
    }
}

// Limpiar cache
wp_cache_flush();
delete_transient('automatiza_services_custom');
delete_transient('automatiza_all_services');

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RESUMEN:\n";
echo "   - Servicios agregados: $added\n";
echo "   - Servicios saltados (ya existían): $skipped\n";
echo "   - Total servicios custom: " . $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE category = 'custom'") . "\n";
echo str_repeat("=", 50) . "\n";

echo "\n🎉 ¡Listo! Ahora ve a WP Admin > Servicios AT para ver los nuevos servicios\n";
echo "También puedes crear cotizaciones personalizadas con estos items.\n";
echo "</pre>";

echo '<p><a href="' . admin_url('admin.php?page=automatiza-services') . '" class="button button-primary" style="padding: 10px 20px; font-size: 16px;">📋 Ver Gestión de Servicios</a></p>';
?>
