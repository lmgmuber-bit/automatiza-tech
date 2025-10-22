<?php
/**
 * Funciones para mostrar servicios en el frontend
 */

// Función para obtener servicios por categoría
function get_automatiza_services($category = null, $active_only = true) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $where_conditions = array();
    $where_values = array();
    
    if ($active_only) {
        $where_conditions[] = "is_active = %d";
        $where_values[] = 1;
    }
    
    if ($category) {
        $where_conditions[] = "service_category = %s";
        $where_values[] = $category;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $query = "SELECT * FROM $table_name $where_clause ORDER BY service_order ASC, service_name ASC";
    
    if (!empty($where_values)) {
        $query = $wpdb->prepare($query, $where_values);
    }
    
    $services = $wpdb->get_results($query);
    
    // Decodificar features JSON
    foreach ($services as $service) {
        if ($service->service_features) {
            $service->service_features = json_decode($service->service_features, true);
        } else {
            $service->service_features = array();
        }
    }
    
    return $services;
}

// Shortcode para mostrar servicios de pricing
function pricing_services_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_currency' => 'both', // usd, clp, both
        'columns' => 3,
        'show_popular' => true
    ), $atts);
    
    $services = get_automatiza_services('pricing');
    
    if (empty($services)) {
        return '<p>No hay servicios de pricing disponibles.</p>';
    }
    
    ob_start();
    ?>
    <div class="pricing-services-container">
        <div class="row justify-content-center">
            <?php foreach ($services as $service): ?>
                <div class="col-md-<?php echo 12 / intval($atts['columns']); ?> mb-4">
                    <div class="pricing-card card h-100 <?php echo $service->is_popular ? 'border-primary' : ''; ?>">
                        <div class="card-header text-center <?php echo $service->is_popular ? 'bg-primary text-white' : 'bg-light'; ?>">
                            <h5 class="card-title">
                                <?php if ($service->service_icon): ?>
                                    <i class="<?php echo esc_attr($service->service_icon); ?>"></i>
                                <?php endif; ?>
                                <?php echo esc_html($service->service_name); ?>
                            </h5>
                            <div class="price">
                                <?php if ($service->price_usd > 0): ?>
                                    <?php if ($atts['show_currency'] === 'both' || $atts['show_currency'] === 'usd'): ?>
                                        <div class="price-usd">
                                            <span class="currency">$</span>
                                            <span class="amount"><?php echo number_format($service->price_usd, 0); ?></span>
                                            <span class="period">USD/mes</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($atts['show_currency'] === 'both' || $atts['show_currency'] === 'clp'): ?>
                                        <div class="price-clp">
                                            <span class="currency">$</span>
                                            <span class="amount"><?php echo number_format($service->price_clp, 0, ',', '.'); ?></span>
                                            <span class="period">CLP/mes</span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="price-free">
                                        <span class="amount">Gratis</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($service->is_popular && $atts['show_popular']): ?>
                                <span class="badge <?php echo $service->is_popular ? 'badge-light' : ''; ?>">Más Popular</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body">
                            <p class="service-description"><?php echo esc_html($service->service_description); ?></p>
                            
                            <?php if (!empty($service->service_features)): ?>
                                <ul class="list-unstyled">
                                    <?php foreach ($service->service_features as $feature): ?>
                                        <li><i class="fas fa-check text-success"></i> <?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer text-center">
                            <a href="#contact" class="btn <?php echo $service->is_popular ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <?php echo $service->price_usd > 0 ? 'Comenzar' : 'Más Información'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .pricing-services-container .price-usd {
        font-size: 1.2em;
        margin-bottom: 5px;
    }
    
    .pricing-services-container .price-clp {
        font-size: 0.9em;
        opacity: 0.8;
    }
    
    .pricing-services-container .price-free .amount {
        font-size: 1.5em;
        color: #28a745;
    }
    
    .pricing-services-container .service-description {
        font-size: 0.9em;
        color: #666;
        margin-bottom: 15px;
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('pricing_services', 'pricing_services_shortcode');

// Shortcode para mostrar servicios de características
function features_services_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => 3,
        'show_icons' => true
    ), $atts);
    
    $services = get_automatiza_services('features');
    
    if (empty($services)) {
        return '<p>No hay servicios de características disponibles.</p>';
    }
    
    ob_start();
    ?>
    <div class="features-services-container">
        <div class="features-grid">
            <?php foreach ($services as $service): ?>
                <div class="feature-card fade-in-up">
                    <?php if ($atts['show_icons'] && $service->service_icon): ?>
                        <div class="feature-icon">
                            <i class="<?php echo esc_attr($service->service_icon); ?>"></i>
                        </div>
                    <?php endif; ?>
                    <h3><?php echo esc_html($service->service_name); ?></h3>
                    <p><?php echo esc_html($service->service_description); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('features_services', 'features_services_shortcode');

// Shortcode para mostrar servicios especiales
function special_services_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_currency' => 'both',
        'show_cta' => true
    ), $atts);
    
    $services = get_automatiza_services('special');
    
    if (empty($services)) {
        return '<p>No hay servicios especiales disponibles.</p>';
    }
    
    ob_start();
    ?>
    <div class="special-services-container">
        <?php foreach ($services as $service): ?>
            <div class="special-service-card">
                <div class="service-header">
                    <?php if ($service->service_icon): ?>
                        <div class="service-icon">
                            <i class="<?php echo esc_attr($service->service_icon); ?>"></i>
                        </div>
                    <?php endif; ?>
                    <h3><?php echo esc_html($service->service_name); ?></h3>
                </div>
                
                <div class="service-content">
                    <p class="service-description"><?php echo esc_html($service->service_description); ?></p>
                    
                    <?php if ($service->price_usd > 0): ?>
                        <div class="service-price">
                            <?php if ($atts['show_currency'] === 'both' || $atts['show_currency'] === 'usd'): ?>
                                <div class="price-usd">$<?php echo number_format($service->price_usd, 0); ?> USD</div>
                            <?php endif; ?>
                            
                            <?php if ($atts['show_currency'] === 'both' || $atts['show_currency'] === 'clp'): ?>
                                <div class="price-clp">$<?php echo number_format($service->price_clp, 0, ',', '.'); ?> CLP</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($service->service_features)): ?>
                        <ul class="service-features">
                            <?php foreach ($service->service_features as $feature): ?>
                                <li><i class="fas fa-check"></i> <?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_cta']): ?>
                        <div class="service-cta">
                            <button class="cta-button btn btn-primary" onclick="contactForService('<?php echo esc_js($service->service_name); ?>')">
                                Solicitar Información
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    function contactForService(serviceName) {
        const message = encodeURIComponent(`Hola! Me interesa el servicio "${serviceName}". Me gustaría recibir más información sobre este paquete completo.`);
        const phoneNumber = '56936800925';
        const whatsappUrl = `https://wa.me/${phoneNumber}?text=${message}`;
        window.open(whatsappUrl, '_blank');
    }
    </script>
    
    <style>
    .special-service-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .service-header {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .service-icon i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #ffd700;
    }
    
    .service-price {
        text-align: center;
        margin: 1.5rem 0;
    }
    
    .price-usd {
        font-size: 1.8rem;
        font-weight: bold;
        color: #ffd700;
    }
    
    .price-clp {
        font-size: 1.2rem;
        opacity: 0.9;
    }
    
    .service-features {
        list-style: none;
        padding: 0;
        margin: 1.5rem 0;
    }
    
    .service-features li {
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .service-features i {
        color: #00ff88;
        margin-right: 0.5rem;
    }
    
    .service-cta {
        text-align: center;
        margin-top: 2rem;
    }
    
    .cta-button {
        background: #00ff88;
        border: none;
        color: #333;
        padding: 15px 30px;
        border-radius: 50px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
    }
    
    .cta-button:hover {
        background: #00cc6a;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,255,136,0.4);
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('special_services', 'special_services_shortcode');

// Función para obtener un servicio específico
function get_service_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    
    if ($service && $service->service_features) {
        $service->service_features = json_decode($service->service_features, true);
    }
    
    return $service;
}

// Widget para mostrar el tipo de cambio actual
function exchange_rate_widget() {
    $current_rate = get_current_exchange_rate();
    ?>
    <div class="exchange-rate-widget">
        <small>Tipo de cambio actual: 1 USD = <?php echo number_format($current_rate, 0, ',', '.'); ?> CLP</small>
    </div>
    <style>
    .exchange-rate-widget {
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        margin: 10px 0;
        font-size: 0.85em;
        color: #666;
    }
    </style>
    <?php
}
add_shortcode('exchange_rate', 'exchange_rate_widget');
?>