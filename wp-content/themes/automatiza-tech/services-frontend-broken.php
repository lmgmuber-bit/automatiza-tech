<?php
/**
 * Funciones para mostrar servicios en el frontend
 */

// Función para renderizar sección de features/beneficios
function render_features_section() {
    $features = get_active_automatiza_services('features');
    
    if (empty($features)) {
        // Fallback a contenido estático si no hay servicios en BD
        return get_default_features_content();
    }
    
    ob_start();
    ?>
    <section class="features-section" id="beneficios">
        <div class="container">
            <h2 class="section-title">¿Por qué elegir Automatiza Tech?</h2>
            <p class="text-center text-muted mb-5">Automatiza tu atención, ahorra tiempo, escala tu negocio</p>
            
            <div class="features-grid">
                <?php foreach ($features as $feature): ?>
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="<?php echo esc_attr($feature->icon); ?>"></i>
                    </div>
                    <h3><?php echo esc_html($feature->name); ?></h3>
                    <p><?php echo esc_html($feature->description); ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($features) < 6): ?>
                <!-- Agregar beneficios estáticos adicionales si hay menos de 6 -->
                <?php if (!in_array('Ahorra Tiempo', array_column($features, 'name'))): ?>
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Ahorra Tiempo</h3>
                    <p>Automatiza respuestas frecuentes y libera tiempo para enfocarte en hacer crecer tu negocio.</p>
                </div>
                <?php endif; ?>
                
                <?php if (!in_array('Mejor Experiencia', array_column($features, 'name'))): ?>
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Mejor Experiencia</h3>
                    <p>Respuestas instantáneas y personalizadas que mejoran la satisfacción de tus clientes.</p>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

// Función para renderizar sección de servicios especiales
function render_special_services_section() {
    $services = get_active_automatiza_services('special');
    
    if (empty($services)) {
        // Fallback al contenido estático
        return get_default_special_services_content();
    }
    
    ob_start();
    ?>
    <section class="services-section py-5" id="servicios">
        <div class="container">
            <h2 class="section-title text-center mb-5">Nuestros Servicios Especializados</h2>
            <div class="row justify-content-center">
                <?php foreach ($services as $service): ?>
                <div class="col-lg-8 col-md-10 mb-4">
                    <div class="service-card shadow-lg border-0 rounded-lg overflow-hidden">
                        <div class="service-header bg-gradient-primary text-white p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h3 class="mb-1"><?php echo esc_html($service->name); ?></h3>
                                    <p class="mb-0 opacity-90">Para Emprendimientos</p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <div class="service-icon">
                                        <i class="<?php echo esc_attr($service->icon); ?> fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="service-body p-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="service-description mb-4">
                                        <?php echo esc_html($service->description); ?>
                                    </p>
                                    <?php if (!empty($service->features)): 
                                        $features = json_decode($service->features, true);
                                        if (is_array($features)):
                                    ?>
                                    <div class="features-list">
                                        <h5 class="mb-3">¿Qué incluye?</h5>
                                        <div class="row">
                                            <?php 
                                            $half = ceil(count($features) / 2);
                                            $left_features = array_slice($features, 0, $half);
                                            $right_features = array_slice($features, $half);
                                            ?>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <?php foreach ($left_features as $feature): ?>
                                                    <li><i class="fas fa-check text-success me-2"></i><?php echo esc_html($feature); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <?php foreach ($right_features as $feature): ?>
                                                    <li><i class="fas fa-check text-success me-2"></i><?php echo esc_html($feature); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="pricing-card bg-light rounded p-4 text-center">
                                        <h4 class="text-primary mb-3">Precio Especial</h4>
                                        <div class="price-display mb-3">
                                            <span class="currency">$</span>
                                            <span class="amount"><?php echo esc_html(number_format($service->price_usd, 0)); ?></span>
                                            <span class="period"><?php echo $service->price_usd > 0 ? ' USD/mes' : ''; ?></span>
                                        </div>
                                        <?php if (!empty($service->setup_fee)): ?>
                                        <p class="small text-muted mb-3">Setup inicial: $<?php echo esc_html($service->setup_fee); ?></p>
                                        <?php endif; ?>
                                        <div class="robot-container d-inline-block position-relative">
                                            <?php 
                                            $whatsapp_message = !empty($service->whatsapp_message) ? $service->whatsapp_message : 'Hola! Me interesa el servicio: ' . $service->name;
                                            $button_text = !empty($service->button_text) ? $service->button_text : '¡Quiero este servicio!';
                                            ?>
                                            <a href="<?php echo esc_url(get_whatsapp_url($whatsapp_message)); ?>" 
                                               target="_blank" class="btn btn-success btn-lg w-100 cta-button">
                                                <?php echo esc_html($button_text); ?>
                                            </a>
                                            <div class="robot-peek">
                                                <i class="fas fa-robot"></i>
                                            </div>
                                        </div>
                                        <p class="small text-muted mt-2">Sin permanencia</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
    
    return ob_get_clean();
}

// Shortcode para mostrar servicios de pricing
function pricing_services_shortcode($atts) {
    return render_pricing_section();
}
add_shortcode('pricing_services', 'pricing_services_shortcode');

// Renderizar sección de precios
function render_pricing_section() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $services = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC"
    );
    
    // Debug temporal - eliminar después
    // error_log('Services data: ' . print_r($services, true));
    
    if (empty($services)) {
        return;
    }
    
    ob_start();
    ?>
    <section class="pricing-section">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="section-title">Planes y Precios</h2>
                    <p class="section-subtitle">Elige el plan que mejor se adapte a tu negocio</p>
                </div>
            </div>
            
            <div class="row pricing-cards">
                <?php foreach ($services as $service): 
                    $features = json_decode($service->features, true) ?: [];
                    $card_color = $service->card_color ?: '#007cba';
                    $button_color = $service->button_color ?: '#28a745';
                    $text_color = $service->text_color ?: '#ffffff';
                ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="pricing-card <?php echo $service->highlight ? 'featured' : ''; ?>" 
                             style="background: linear-gradient(135deg, <?php echo esc_attr($card_color); ?>, <?php echo esc_attr($card_color); ?>dd); color: <?php echo esc_attr($text_color); ?>;">
                            
                            <?php if ($service->highlight): ?>
                                <div class="featured-badge">OFERTA ESPECIAL</div>
                            <?php endif; ?>
                            
                            <div class="pricing-header">
                                <i class="<?php echo esc_attr($service->icon); ?>" style="color: <?php echo esc_attr($text_color); ?>;"></i>
                                <h3 style="color: <?php echo esc_attr($text_color); ?>;"><?php echo esc_html($service->name); ?></h3>
                                <div class="price">
                                    <span class="amount" style="color: <?php echo esc_attr($text_color); ?>;">$<?php echo number_format($service->price_usd, 0); ?></span>
                                    <span class="period" style="color: <?php echo esc_attr($text_color); ?>99;">USD/mes</span>
                                </div>
                            </div>
                            
                            <div class="pricing-body">
                                <p class="description" style="color: <?php echo esc_attr($text_color); ?>cc;"><?php echo esc_html($service->description); ?></p>
                                
                                <?php if (!empty($features)): ?>
                                    <ul class="features-list" style="color: <?php echo esc_attr($text_color); ?>;">
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
                                    : "Hola, me interesa el plan: " . $service->name;
                                $whatsapp_url = "https://wa.me/56940331127?text=" . urlencode($message);
                                ?>
                                <a href="<?php echo esc_url($whatsapp_url); ?>" class="btn-plan" target="_blank" 
                                   style="background: <?php echo esc_attr($button_color); ?>; color: white;">
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
                    <h2 class="section-title">Nuestros Servicios Especializados</h2>
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

// Funciones auxiliares de fallback
function get_default_features_content() {
    ob_start();
    ?>
    <section class="features-section" id="beneficios">
        <div class="container">
            <h2 class="section-title">¿Por qué elegir Automatiza Tech?</h2>
            <p class="text-center text-muted mb-5">Automatiza tu atención, ahorra tiempo, escala tu negocio</p>
            
            <div class="features-grid">
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Atención 24/7</h3>
                    <p>Chatbots inteligentes que nunca descansan. Atiende a tus clientes las 24 horas del día, los 7 días de la semana.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Aumenta tus Ventas</h3>
                    <p>Convierte más leads en clientes con respuestas automáticas inteligentes y seguimiento personalizado.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Fácil Integración</h3>
                    <p>Se integra perfectamente con WhatsApp, Instagram, tu sitio web y tu CRM existente.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Ahorra Tiempo</h3>
                    <p>Automatiza respuestas frecuentes y libera tiempo para enfocarte en hacer crecer tu negocio.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Mejor Experiencia</h3>
                    <p>Respuestas instantáneas y personalizadas que mejoran la satisfacción de tus clientes.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Seguridad Garantizada</h3>
                    <p>Protección de datos y comunicaciones seguras con los más altos estándares de la industria.</p>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function get_default_special_services_content() {
    ob_start();
    ?>
    <section class="services-section py-5" id="servicios">
        <div class="container">
            <h2 class="section-title text-center mb-5">Nuestros Servicios Especializados</h2>
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    <div class="service-card shadow-lg border-0 rounded-lg overflow-hidden">
                        <div class="service-header bg-gradient-primary text-white p-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h3 class="mb-1">Web + WhatsApp Business</h3>
                                    <p class="mb-0 opacity-90">Para Emprendimientos</p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <div class="service-icon">
                                        <i class="fas fa-store fa-3x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="service-body p-4">
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="service-description mb-4">
                                        Impulsa tu emprendimiento con una solución completa que incluye sitio web profesional 
                                        y automatización de WhatsApp Business para generar más ventas y mejorar la atención al cliente.
                                    </p>
                                    <div class="features-list">
                                        <h5 class="mb-3">¿Qué incluye?</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <li><i class="fas fa-check text-success me-2"></i>Sitio web responsivo</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Catálogo de productos</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>WhatsApp Business API</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Chat automatizado</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <li><i class="fas fa-check text-success me-2"></i>Botón de WhatsApp integrado</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Respuestas automáticas</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Horarios de atención</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Soporte técnico</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="pricing-card bg-light rounded p-4 text-center">
                                        <h4 class="text-primary mb-3">Precio Especial</h4>
                                        <div class="price-display mb-3">
                                            <span class="currency">$</span>
                                            <span class="amount">299</span>
                                            <span class="period"> USD/mes</span>
                                        </div>
                                        <p class="small text-muted mb-3">Setup inicial: $500 USD</p>
                                        <div class="robot-container d-inline-block position-relative">
                                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa el servicio Web + WhatsApp Business')); ?>" 
                                               target="_blank" class="btn btn-success btn-lg w-100 cta-button">
                                                ¡Quiero mi Web + WhatsApp!
                                            </a>
                                            <div class="robot-peek">
                                                <i class="fas fa-robot"></i>
                                            </div>
                                        </div>
                                        <p class="small text-muted mt-2">Sin permanencia</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    
    return ob_get_clean();
}