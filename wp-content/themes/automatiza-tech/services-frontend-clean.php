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
        'category' => 'pricing',
        'columns' => 3,
        'show_highlight' => true
    ), $atts);
    
    $services = get_automatiza_services($atts['category']);
    
    if (empty($services)) {
        return '<p>No hay servicios disponibles.</p>';
    }
    
    ob_start();
    ?>
    <div class="automatiza-services-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
        <?php foreach ($services as $service): ?>
            <div class="service-card <?php echo $service->is_highlighted ? 'highlighted' : ''; ?>">
                
                <?php if ($service->is_highlighted && $atts['show_highlight']): ?>
                    <div class="offer-badge">OFERTA ESPECIAL</div>
                <?php endif; ?>
                
                <div class="service-header">
                    <i class="<?php echo esc_attr($service->service_icon); ?>"></i>
                    <h3><?php echo esc_html($service->service_name); ?></h3>
                </div>
                
                <div class="service-price">
                    <?php if ($service->service_price > 0): ?>
                        <span class="price-amount">$<?php echo number_format($service->service_price, 0, ',', '.'); ?></span>
                        <span class="price-period">USD/mes</span>
                    <?php else: ?>
                        <span class="price-amount">Gratis</span>
                    <?php endif; ?>
                </div>
                
                <div class="service-description">
                    <p><?php echo esc_html($service->service_description); ?></p>
                </div>
                
                <?php if (!empty($service->service_features)): ?>
                    <ul class="service-features">
                        <?php foreach ($service->service_features as $feature): ?>
                            <li><i class="fas fa-check"></i> <?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <div class="service-action">
                    <?php 
                    $whatsapp_message = !empty($service->whatsapp_message) 
                        ? $service->whatsapp_message 
                        : "Hola, estoy interesado en el servicio: " . $service->service_name;
                    $whatsapp_url = "https://wa.me/56912345678?text=" . urlencode($whatsapp_message);
                    ?>
                    <a href="<?php echo esc_url($whatsapp_url); ?>" class="btn-primary" target="_blank">
                        <?php echo !empty($service->button_text) ? esc_html($service->button_text) : 'Comenzar'; ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <style>
    .automatiza-services-grid {
        display: grid;
        gap: 30px;
        margin: 40px 0;
    }
    
    .automatiza-services-grid[data-columns="2"] {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }
    
    .automatiza-services-grid[data-columns="3"] {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
    
    .automatiza-services-grid[data-columns="4"] {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .service-card {
        background: #fff;
        border-radius: 15px;
        padding: 30px;
        text-align: center;
        box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        position: relative;
        border: 2px solid transparent;
    }
    
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.15);
    }
    
    .service-card.highlighted {
        border-color: #ff6b35;
        background: linear-gradient(135deg, #fff 0%, #fff9f7 100%);
    }
    
    .offer-badge {
        position: absolute;
        top: -10px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(45deg, #ff6b35, #f7931e);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4); }
        50% { box-shadow: 0 4px 25px rgba(255, 107, 53, 0.6); }
        100% { box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4); }
    }
    
    .service-header {
        margin-bottom: 20px;
    }
    
    .service-header i {
        font-size: 3em;
        color: #007cba;
        margin-bottom: 15px;
        display: block;
    }
    
    .service-header h3 {
        font-size: 1.4em;
        color: #333;
        margin: 0;
        font-weight: 600;
    }
    
    .service-price {
        margin-bottom: 20px;
        padding: 15px 0;
    }
    
    .price-amount {
        font-size: 2.5em;
        font-weight: bold;
        color: #007cba;
        display: block;
        line-height: 1;
    }
    
    .price-period {
        color: #666;
        font-size: 0.9em;
        margin-top: 5px;
        display: block;
    }
    
    .service-description {
        margin-bottom: 25px;
        color: #666;
        line-height: 1.6;
    }
    
    .service-features {
        list-style: none;
        padding: 0;
        margin: 25px 0;
        text-align: left;
    }
    
    .service-features li {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        color: #555;
        display: flex;
        align-items: center;
    }
    
    .service-features li:last-child {
        border-bottom: none;
    }
    
    .service-features i {
        color: #28a745;
        margin-right: 10px;
        font-size: 0.9em;
    }
    
    .service-action {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #f0f0f0;
    }
    
    .btn-primary {
        background: linear-gradient(45deg, #007cba, #005a87);
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        transition: all 0.3s ease;
        border: none;
        font-size: 1em;
    }
    
    .btn-primary:hover {
        background: linear-gradient(45deg, #005a87, #004666);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 124, 186, 0.4);
        color: white;
        text-decoration: none;
    }
    
    @media (max-width: 768px) {
        .automatiza-services-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .service-card {
            padding: 25px 20px;
        }
        
        .service-header i {
            font-size: 2.5em;
        }
        
        .price-amount {
            font-size: 2em;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('pricing_services', 'pricing_services_shortcode');

// Renderizar sección de precios
function render_pricing_section() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $services = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE service_category = 'pricing' AND is_active = 1 ORDER BY service_order ASC, service_name ASC"
    );
    
    if (empty($services)) {
        return;
    }
    
    ob_start();
    ?>
    <section class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="section-title">Nuestros Planes</h2>
                    <p class="section-subtitle">Elige el plan que mejor se adapte a tus necesidades</p>
                </div>
            </div>
            
            <div class="row pricing-cards">
                <?php foreach ($services as $service): 
                    $features = json_decode($service->service_features, true) ?: [];
                ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="pricing-card <?php echo $service->is_highlighted ? 'featured' : ''; ?>">
                            
                            <?php if ($service->is_highlighted): ?>
                                <div class="featured-badge">OFERTA ESPECIAL</div>
                            <?php endif; ?>
                            
                            <div class="pricing-header">
                                <i class="<?php echo esc_attr($service->service_icon); ?>"></i>
                                <h3><?php echo esc_html($service->service_name); ?></h3>
                                <div class="price">
                                    <span class="amount">$<?php echo number_format($service->service_price, 0); ?></span>
                                    <span class="period">USD/mes</span>
                                </div>
                            </div>
                            
                            <div class="pricing-body">
                                <p class="description"><?php echo esc_html($service->service_description); ?></p>
                                
                                <?php if (!empty($features)): ?>
                                    <ul class="features-list">
                                        <?php foreach ($features as $feature): ?>
                                            <li><i class="fas fa-check"></i> <?php echo esc_html($feature); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                            
                            <div class="pricing-footer">
                                <?php 
                                $message = !empty($service->whatsapp_message) 
                                    ? $service->whatsapp_message 
                                    : "Hola, me interesa el plan: " . $service->service_name;
                                $whatsapp_url = "https://wa.me/56912345678?text=" . urlencode($message);
                                ?>
                                <a href="<?php echo esc_url($whatsapp_url); ?>" class="btn-plan" target="_blank">
                                    <?php echo !empty($service->button_text) ? esc_html($service->button_text) : 'Comenzar'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <style>
    .pricing-section {
        padding: 80px 0;
        background: #f8f9fa;
    }
    
    .section-title {
        font-size: 2.5em;
        color: #333;
        margin-bottom: 15px;
        font-weight: 700;
    }
    
    .section-subtitle {
        font-size: 1.2em;
        color: #666;
        margin-bottom: 50px;
    }
    
    .pricing-cards {
        margin-top: 30px;
    }
    
    .pricing-card {
        background: white;
        border-radius: 15px;
        padding: 40px 30px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        position: relative;
        border: 2px solid transparent;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .pricing-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }
    
    .pricing-card.featured {
        border-color: #007cba;
        transform: scale(1.05);
    }
    
    .pricing-card.featured:hover {
        transform: scale(1.05) translateY(-10px);
    }
    
    .featured-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(45deg, #007cba, #005a87);
        color: white;
        padding: 10px 25px;
        border-radius: 25px;
        font-size: 0.85em;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 5px 20px rgba(0, 124, 186, 0.4);
        animation: pulse 2s infinite;
    }
    
    .pricing-header {
        margin-bottom: 30px;
    }
    
    .pricing-header i {
        font-size: 3.5em;
        color: #007cba;
        margin-bottom: 20px;
        display: block;
    }
    
    .pricing-header h3 {
        font-size: 1.5em;
        color: #333;
        margin-bottom: 20px;
        font-weight: 600;
    }
    
    .price {
        margin-bottom: 20px;
    }
    
    .price .amount {
        font-size: 3em;
        font-weight: bold;
        color: #007cba;
        display: block;
        line-height: 1;
    }
    
    .price .period {
        color: #666;
        font-size: 1em;
        margin-top: 5px;
        display: block;
    }
    
    .pricing-body {
        flex: 1;
        margin-bottom: 30px;
    }
    
    .description {
        color: #666;
        margin-bottom: 25px;
        line-height: 1.6;
    }
    
    .features-list {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: left;
    }
    
    .features-list li {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
        color: #555;
        display: flex;
        align-items: center;
    }
    
    .features-list li:last-child {
        border-bottom: none;
    }
    
    .features-list i {
        color: #28a745;
        margin-right: 12px;
        font-size: 1em;
    }
    
    .pricing-footer {
        padding-top: 20px;
        border-top: 1px solid #f0f0f0;
    }
    
    .btn-plan {
        background: linear-gradient(45deg, #007cba, #005a87);
        color: white;
        padding: 15px 35px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: bold;
        display: inline-block;
        transition: all 0.3s ease;
        border: none;
        font-size: 1.1em;
        width: 100%;
    }
    
    .btn-plan:hover {
        background: linear-gradient(45deg, #005a87, #004666);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 124, 186, 0.4);
        color: white;
        text-decoration: none;
    }
    
    @media (max-width: 768px) {
        .pricing-section {
            padding: 60px 0;
        }
        
        .section-title {
            font-size: 2em;
        }
        
        .pricing-card {
            padding: 30px 25px;
        }
        
        .pricing-card.featured {
            transform: none;
            margin-top: 0;
        }
        
        .pricing-header i {
            font-size: 3em;
        }
        
        .price .amount {
            font-size: 2.5em;
        }
        
        .btn-plan {
            padding: 12px 25px;
            font-size: 1em;
        }
    }
    </style>
    <?php
    echo ob_get_clean();
}

// Renderizar servicios especiales
function render_special_services_section() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $services = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE service_category = 'features' AND is_active = 1 ORDER BY service_order ASC, service_name ASC"
    );
    
    if (empty($services)) {
        return;
    }
    
    ob_start();
    ?>
    <section class="special-services-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="section-title">¿Por qué elegir Automatiza Tech?</h2>
                    <p class="section-subtitle">Nuestros servicios especializados te brindan todo lo que necesitas</p>
                </div>
            </div>
            
            <div class="row services-grid">
                <?php foreach ($services as $service): 
                    $features = json_decode($service->service_features, true) ?: [];
                ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="service-item">
                            <div class="service-icon">
                                <i class="<?php echo esc_attr($service->service_icon); ?>"></i>
                            </div>
                            <h4><?php echo esc_html($service->service_name); ?></h4>
                            <p><?php echo esc_html($service->service_description); ?></p>
                            
                            <?php if (!empty($features)): ?>
                                <ul class="service-features">
                                    <?php foreach ($features as $feature): ?>
                                        <li><?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <style>
    .special-services-section {
        padding: 80px 0;
        background: white;
    }
    
    .services-grid {
        margin-top: 50px;
    }
    
    .service-item {
        text-align: center;
        padding: 40px 20px;
        height: 100%;
        transition: all 0.3s ease;
    }
    
    .service-item:hover {
        transform: translateY(-5px);
    }
    
    .service-icon {
        margin-bottom: 25px;
    }
    
    .service-icon i {
        font-size: 3.5em;
        color: #007cba;
        background: linear-gradient(45deg, #007cba, #005a87);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .service-item h4 {
        font-size: 1.4em;
        color: #333;
        margin-bottom: 20px;
        font-weight: 600;
    }
    
    .service-item p {
        color: #666;
        line-height: 1.6;
        margin-bottom: 20px;
    }
    
    .service-features {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: left;
    }
    
    .service-features li {
        color: #555;
        padding: 5px 0;
        position: relative;
        padding-left: 20px;
    }
    
    .service-features li:before {
        content: "✓";
        color: #28a745;
        font-weight: bold;
        position: absolute;
        left: 0;
    }
    
    @media (max-width: 768px) {
        .special-services-section {
            padding: 60px 0;
        }
        
        .service-item {
            padding: 30px 15px;
        }
        
        .service-icon i {
            font-size: 3em;
        }
    }
    </style>
    <?php
    echo ob_get_clean();
}

// Widget de tipo de cambio
function exchange_rate_widget() {
    $usd_to_clp = get_option('usd_to_clp_rate', 800);
    
    ob_start();
    ?>
    <div class="exchange-rate-widget">
        <strong>Tipo de cambio:</strong> 1 USD = $<?php echo number_format($usd_to_clp, 0, ',', '.'); ?> CLP
    </div>
    
    <style>
    .exchange-rate-widget {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
        text-align: center;
        margin: 10px 0;
        font-size: 0.85em;
        color: #666;
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('exchange_rate', 'exchange_rate_widget');