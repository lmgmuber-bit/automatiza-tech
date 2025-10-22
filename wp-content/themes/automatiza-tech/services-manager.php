<?php
/**
 * Sistema de Servicios y Precios - Automatiza Tech
 * Crear tabla para gestión de servicios con conversión de moneda
 */

// Función para crear la tabla de servicios
function create_services_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        service_name varchar(255) NOT NULL,
        service_description text NOT NULL,
        service_icon varchar(100) NOT NULL DEFAULT 'fas fa-cogs',
        price_usd decimal(10,2) NOT NULL,
        price_clp decimal(15,2) NOT NULL,
        exchange_rate decimal(10,4) NOT NULL DEFAULT 800.0000,
        service_features text,
        service_category varchar(100) DEFAULT 'general',
        is_active tinyint(1) DEFAULT 1,
        is_popular tinyint(1) DEFAULT 0,
        service_order int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Insertar servicios de ejemplo
    $wpdb->insert(
        $table_name,
        array(
            'service_name' => 'Básico',
            'service_description' => 'Perfecto para pequeños negocios que comienzan',
            'service_icon' => 'fas fa-rocket',
            'price_usd' => 99.00,
            'price_clp' => 79200.00,
            'exchange_rate' => 800.0000,
            'service_features' => json_encode([
                'Hasta 1,000 conversaciones/mes',
                'WhatsApp y Web Chat',
                'Respuestas automáticas básicas',
                'Soporte por email',
                'Analíticas básicas'
            ]),
            'service_category' => 'pricing',
            'is_active' => 1,
            'service_order' => 1
        )
    );
    
    $wpdb->insert(
        $table_name,
        array(
            'service_name' => 'Profesional',
            'service_description' => 'Para negocios en crecimiento que necesitan más',
            'service_icon' => 'fas fa-star',
            'price_usd' => 199.00,
            'price_clp' => 159200.00,
            'exchange_rate' => 800.0000,
            'service_features' => json_encode([
                'Hasta 5,000 conversaciones/mes',
                'Todas las integraciones',
                'IA avanzada',
                'Soporte prioritario',
                'Analíticas avanzadas',
                'API personalizada'
            ]),
            'service_category' => 'pricing',
            'is_active' => 1,
            'is_popular' => 1,
            'service_order' => 2
        )
    );
    
    $wpdb->insert(
        $table_name,
        array(
            'service_name' => 'Enterprise',
            'service_description' => 'Solución completa para grandes empresas',
            'service_icon' => 'fas fa-crown',
            'price_usd' => 399.00,
            'price_clp' => 319200.00,
            'exchange_rate' => 800.0000,
            'service_features' => json_encode([
                'Conversaciones ilimitadas',
                'Integraciones personalizadas',
                'IA ultra avanzada',
                'Soporte 24/7',
                'Gerente de cuenta dedicado',
                'Implementación personalizada'
            ]),
            'service_category' => 'pricing',
            'is_active' => 1,
            'service_order' => 3
        )
    );
    
    // Servicios de características
    $wpdb->insert(
        $table_name,
        array(
            'service_name' => 'Atención 24/7',
            'service_description' => 'Chatbots inteligentes que nunca descansan. Atiende a tus clientes las 24 horas del día, los 7 días de la semana.',
            'service_icon' => 'fas fa-robot',
            'price_usd' => 0.00,
            'price_clp' => 0.00,
            'service_category' => 'features',
            'is_active' => 1,
            'service_order' => 1
        )
    );
    
    $wpdb->insert(
        $table_name,
        array(
            'service_name' => 'Aumenta tus Ventas',
            'service_description' => 'Convierte más leads en clientes con respuestas automáticas inteligentes y seguimiento personalizado.',
            'service_icon' => 'fas fa-chart-line',
            'price_usd' => 0.00,
            'price_clp' => 0.00,
            'service_category' => 'features',
            'is_active' => 1,
            'service_order' => 2
        )
    );
    
    $wpdb->insert(
        $table_name,
        array(
            'service_name' => 'Web + WhatsApp Business para Emprendimientos',
            'service_description' => 'Paquete completo: Sitio web profesional + Bot de WhatsApp + Integración CRM para emprendedores.',
            'service_icon' => 'fas fa-rocket',
            'price_usd' => 299.00,
            'price_clp' => 239200.00,
            'exchange_rate' => 800.0000,
            'service_features' => json_encode([
                'Sitio web responsive',
                'Bot WhatsApp personalizado',
                'Integración CRM básica',
                'Formularios de contacto',
                'Analíticas web',
                'Soporte por 3 meses'
            ]),
            'service_category' => 'special',
            'is_active' => 1,
            'service_order' => 1
        )
    );
}

// Activar al activar el tema o plugin
add_action('after_switch_theme', 'create_services_table');

// Función para obtener tipo de cambio actualizado (API externa)
function get_current_exchange_rate() {
    $api_url = 'https://api.exchangerate-api.com/v4/latest/USD';
    
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        return 800.0000; // Valor por defecto si falla la API
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['rates']['CLP'])) {
        return floatval($data['rates']['CLP']);
    }
    
    return 800.0000; // Valor por defecto
}

// Función para actualizar precios automáticamente
function update_service_prices() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'automatiza_services';
    $current_rate = get_current_exchange_rate();
    
    $services = $wpdb->get_results("SELECT * FROM $table_name WHERE price_usd > 0");
    
    foreach ($services as $service) {
        $new_price_clp = $service->price_usd * $current_rate;
        
        $wpdb->update(
            $table_name,
            array(
                'price_clp' => $new_price_clp,
                'exchange_rate' => $current_rate
            ),
            array('id' => $service->id)
        );
    }
}

// Actualizar precios diariamente
if (!wp_next_scheduled('update_service_prices_daily')) {
    wp_schedule_event(time(), 'daily', 'update_service_prices_daily');
}
add_action('update_service_prices_daily', 'update_service_prices');
?>