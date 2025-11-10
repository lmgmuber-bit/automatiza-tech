<?php
/**
 * Funciones helper para renderizar servicios dinámicamente
 * Mantiene el diseño exacto del frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderizar sección de beneficios/características
 */
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
            <h2 class="section-title">¿Por qu&eacute; elegir Automatiza Tech?</h2>
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

/**
 * Renderizar sección de precios/planes
 */
function render_pricing_section() {
    $plans = get_active_automatiza_services('pricing');
    
    if (empty($plans)) {
        // Fallback a contenido estático si no hay planes en BD
        return get_default_pricing_content();
    }
    
    ob_start();
    ?>
    <section class="pricing-section" id="planes">
        <div class="container">
            <h2 class="section-title">Planes y Precios</h2>
            <p class="text-center text-muted mb-5">Elige el plan que mejor se adapte a tu negocio</p>
            
            <div class="row justify-content-center">
                <?php foreach ($plans as $index => $plan): 
                    $features_array = json_decode($plan->features, true);
                    if (!is_array($features_array)) {
                        $features_array = explode(',', $plan->features);
                    }
                    
                    // Determinar clase de columna según cantidad de planes
                    $col_class = count($plans) <= 3 ? 'col-md-4' : 'col-md-3';
                    
                    // Determinar estilos según si es destacado
                    $card_class = $plan->highlight ? 'border-primary pricing-card-special' : '';
                    $header_class = $plan->highlight ? 'bg-primary text-white' : 'bg-light';
                    $button_class = $plan->highlight ? 'btn-primary' : 'btn-outline-primary';
                    
                    // Obtener precio a mostrar (siempre en USD)
                    $price = number_format($plan->price_usd, 0);
                    $currency = '$';
                    $period = $plan->price_usd > 0 ? ' USD/mes' : '';
                ?>
                <div class="<?php echo $col_class; ?> mb-4">
                    <div class="pricing-card card h-100 <?php echo $card_class; ?>">
                        <?php if ($plan->highlight): ?>
                        <div class="special-offer-badge">
                            🔥 OFERTA ESPECIAL
                        </div>
                        <?php endif; ?>
                        <div class="card-header text-center <?php echo $header_class; ?>">
                            <h5 class="card-title"><?php echo esc_html($plan->name); ?></h5>
                            <?php if ($plan->price_usd > 0): ?>
                            <div class="price">
                                <span class="currency"><?php echo $currency; ?></span>
                                <span class="amount"><?php echo $price; ?></span>
                                <span class="period"><?php echo $period; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($plan->highlight && !empty($plan->button_text)): ?>
                            <span class="badge badge-light"><?php echo esc_html($plan->button_text); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($plan->description)): ?>
                            <p class="text-muted mb-3"><?php echo esc_html($plan->description); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($features_array)): ?>
                            <ul class="list-unstyled">
                                <?php foreach ($features_array as $feature): ?>
                                <li><i class="fas fa-check text-success"></i> <?php echo esc_html(trim($feature, '"')); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-center">
                            <?php 
                            $button_text = !empty($plan->button_text) ? $plan->button_text : 'Comenzar';
                            $whatsapp_message = !empty($plan->whatsapp_message) ? $plan->whatsapp_message : 'Hola! Me interesa el ' . $plan->name . '. ¿Podrías darme más información?';
                            ?>
                            <a href="<?php echo esc_url(get_whatsapp_url($whatsapp_message)); ?>" 
                               class="btn <?php echo $button_class; ?>" 
                               target="_blank" rel="noopener">
                                <?php echo esc_html($button_text); ?>
                            </a>
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

/**
 * Contenido por defecto de características (fallback)
 */
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

/**
 * Contenido por defecto de precios (fallback)
 */
function get_default_pricing_content() {
    ob_start();
    ?>
    <section class="pricing-section" id="planes">
        <div class="container">
            <h2 class="section-title">Planes y Precios</h2>
            <p class="text-center text-muted mb-5">Elige el plan que mejor se adapte a tu negocio</p>
            
            <div class="row justify-content-center">
                <div class="col-md-4 mb-4">
                    <div class="pricing-card card h-100">
                        <div class="card-header text-center bg-light">
                            <h5 class="card-title">Básico</h5>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">99</span>
                                <span class="period"> USD/mes</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Hasta 1,000 conversaciones/mes</li>
                                <li><i class="fas fa-check text-success"></i> WhatsApp y Web Chat</li>
                                <li><i class="fas fa-check text-success"></i> Respuestas automáticas básicas</li>
                                <li><i class="fas fa-check text-success"></i> Soporte por email</li>
                                <li><i class="fas fa-check text-success"></i> Analíticas básicas</li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa el Plan Básico de Automatiza Tech. ¿Podrías darme más información?')); ?>" 
                               class="btn btn-outline-primary" 
                               target="_blank" rel="noopener">Comenzar</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="pricing-card card h-100 border-primary">
                        <div class="card-header text-center bg-primary text-white">
                            <h5 class="card-title">Profesional</h5>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">199</span>
                                <span class="period"> USD/mes</span>
                            </div>
                            <span class="badge badge-light">Más Popular</span>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Hasta 5,000 conversaciones/mes</li>
                                <li><i class="fas fa-check text-success"></i> Todas las integraciones</li>
                                <li><i class="fas fa-check text-success"></i> IA avanzada</li>
                                <li><i class="fas fa-check text-success"></i> Soporte prioritario</li>
                                <li><i class="fas fa-check text-success"></i> Analíticas avanzadas</li>
                                <li><i class="fas fa-check text-success"></i> API personalizada</li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa el Plan Profesional de Automatiza Tech. ¿Podrías darme más información?')); ?>" 
                               class="btn btn-primary" 
                               target="_blank" rel="noopener">Comenzar</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="pricing-card card h-100">
                        <div class="card-header text-center bg-secondary text-white">
                            <h5 class="card-title">Enterprise</h5>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">399</span>
                                <span class="period"> USD/mes</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Conversaciones ilimitadas</li>
                                <li><i class="fas fa-check text-success"></i> Integraciones personalizadas</li>
                                <li><i class="fas fa-check text-success"></i> IA ultra avanzada</li>
                                <li><i class="fas fa-check text-success"></i> Soporte 24/7</li>
                                <li><i class="fas fa-check text-success"></i> Gerente de cuenta dedicado</li>
                                <li><i class="fas fa-check text-success"></i> Implementación personalizada</li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa el Plan Enterprise de Automatiza Tech. ¿Podrías darme más información?')); ?>" 
                               class="btn btn-secondary" 
                               target="_blank" rel="noopener">Contactar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Renderizar sección de servicios especiales dinámicamente
 */
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

/**
 * Contenido por defecto de servicios especiales (fallback)
 */
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

/**
 * Shortcode para renderizar servicios dinámicamente
 */
function automatiza_services_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'type' => 'grid', // grid, list, cards, features, pricing, special
        'limit' => -1,
        'show_price' => 'true',
        'show_features' => 'true'
    ), $atts);
    
    // Renderizar tipos específicos de secciones
    if ($atts['type'] === 'features') {
        return render_features_section();
    } elseif ($atts['type'] === 'pricing') {
        return render_pricing_section();
    } elseif ($atts['type'] === 'special') {
        return render_special_services_section();
    }
    
    $services = get_active_automatiza_services($atts['category']);
    
    if (empty($services)) {
        return '';
    }
    
    // Limitar cantidad si se especifica
    if ($atts['limit'] > 0) {
        $services = array_slice($services, 0, $atts['limit']);
    }
    
    ob_start();
    ?>
    <div class="automatiza-services-container">
        <?php if ($atts['type'] === 'grid'): ?>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
            <div class="service-item">
                <div class="service-icon">
                    <i class="<?php echo esc_attr($service->icon); ?>"></i>
                </div>
                <h3><?php echo esc_html($service->name); ?></h3>
                <p><?php echo esc_html($service->description); ?></p>
                
                <?php if ($atts['show_price'] === 'true' && $service->price_usd > 0): ?>
                <div class="service-price">
                    $<?php echo number_format($service->price_usd, 0); ?> USD
                </div>
                <?php endif; ?>
                
                <?php if ($atts['show_features'] === 'true' && !empty($service->features)): ?>
                <div class="service-features">
                    <?php 
                    $features = json_decode($service->features, true);
                    if (is_array($features)):
                    ?>
                    <ul>
                        <?php foreach ($features as $feature): ?>
                        <li><?php echo esc_html(trim($feature, '"')); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($service->whatsapp_message)): ?>
                <a href="<?php echo esc_url(get_whatsapp_url($service->whatsapp_message)); ?>" 
                   class="btn btn-primary" target="_blank">
                    <?php echo esc_html(!empty($service->button_text) ? $service->button_text : 'Más Información'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('automatiza_services', 'automatiza_services_shortcode');
