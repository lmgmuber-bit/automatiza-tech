<?php
/**
 * Funciones para mostrar servicios en el frontend
 */

// FunciÃ³n para renderizar secciÃ³n de features/beneficios
function render_features_section() {
    $features = get_active_automatiza_services('features');
    
    if (empty($features)) {
        // Fallback a contenido estÃ¡tico si no hay servicios en BD
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
                <!-- Agregar beneficios estÃ¡ticos adicionales si hay menos de 6 -->
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

// FunciÃ³n para renderizar secciÃ³n de servicios especiales
function render_special_services_section() {
    $services = get_active_automatiza_services('special');
    
    if (empty($services)) {
        // Fallback al contenido estÃ¡tico
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
                                    <div class="special-price-card-compact">
                                        <div class="special-badge">OFERTA ESPECIAL</div>
                                        <h4 class="price-title">Precio Especial</h4>
                                        <div class="price-display">
                                            <span class="currency">$</span>
                                            <span class="amount"><?php echo esc_html(number_format($service->price_usd, 0)); ?></span>
                                            <span class="period">/mes</span>
                                        </div>
                                        <?php if (!empty($service->setup_fee)): ?>
                                        <p class="setup-fee">Setup inicial: $<?php echo esc_html($service->setup_fee); ?></p>
                                        <?php endif; ?>
                                        <div class="button-container">
                                            <?php 
                                            $whatsapp_message = !empty($service->whatsapp_message) ? $service->whatsapp_message : 'Hola! Me interesa el servicio: ' . $service->name;
                                            $button_text = !empty($service->button_text) ? $service->button_text : '¡Quiero este servicio!';
                                            ?>
                                            <a href="<?php echo esc_url(get_whatsapp_url($whatsapp_message)); ?>" 
                                               target="_blank" class="btn-special-compact">
                                                <?php echo esc_html($button_text); ?>
                                            </a>
                                        </div>
                                        <p class="no-permanence">Sin permanencia</p>
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
    
    <style>
    .special-price-card-compact {
        background: white;
        border: 2px solid #86d647;
        border-radius: 15px;
        padding: 25px 20px;
        text-align: center;
        position: relative;
        margin: 20px 0;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .special-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: #86d647;
        color: white;
        padding: 6px 18px;
        border-radius: 20px;
        font-size: 0.75em;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .special-price-card-compact .price-title {
        color: #4472c4;
        font-size: 1.2em;
        font-weight: 600;
        margin: 20px 0 15px 0;
    }
    
    .special-price-card-compact .price-display {
        margin-bottom: 15px;
    }
    
    .special-price-card-compact .currency {
        font-size: 1.1em;
        color: #4472c4;
        vertical-align: top;
    }
    
    .special-price-card-compact .amount {
        font-size: 2.5em;
        font-weight: bold;
        color: #4472c4;
        line-height: 1;
    }
    
    .special-price-card-compact .period {
        font-size: 0.9em;
        color: #666;
        margin-left: 2px;
    }
    
    .special-price-card-compact .setup-fee {
        font-size: 0.85em;
        color: #666;
        margin-bottom: 15px;
    }
    
    .btn-special-compact {
        background: #86d647;
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        transition: all 0.3s ease;
        width: 100%;
        font-size: 0.9em;
        margin-bottom: 10px;
    }
    
    .btn-special-compact:hover {
        background: #75c139;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(134, 214, 71, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .special-price-card-compact .no-permanence {
        font-size: 0.8em;
        color: #666;
        margin: 8px 0 0 0;
    }
    
    @media (max-width: 768px) {
        .special-price-card-compact {
            margin: 25px 10px 10px 10px;
            padding: 20px 15px;
        }
        
        .special-price-card-compact .amount {
            font-size: 2.2em;
        }
        
        .btn-special-compact {
            font-size: 0.85em;
            padding: 10px 15px;
        }
    }
    </style>
    <?php
    
    return ob_get_clean();
}

// Shortcode para mostrar servicios de pricing
function pricing_services_shortcode($atts) {
    return render_pricing_section();
}
add_shortcode('pricing_services', 'pricing_services_shortcode');

// Renderizar secciÃ³n de precios
function render_pricing_section() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_services';
    
    $services = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE category = 'pricing' AND status = 'active' ORDER BY service_order ASC, name ASC"
    );
    
    // Debug temporal - eliminar despuÃ©s
    // error_log('Services data: ' . print_r($services, true));
    
    if (empty($services)) {
        return;
    }
    
    ob_start();
    ?>
    <section class="pricing-section" id="planes">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h2 class="section-title">Planes y Precios</h2>
                    <p class="section-subtitle">Elige el plan que mejor se adapte a tu negocio</p>
                </div>
            </div>
            
            <div class="pricing-carousel-wrapper">
                <div class="row pricing-cards pricing-carousel">
                <?php foreach ($services as $service): 
                    $features = json_decode($service->features, true) ?: [];
                    $card_color = $service->card_color ?: '#007cba';
                    $button_color = $service->button_color ?: '#28a745';
                    $text_color = $service->text_color ?: '#ffffff';
                    $description = esc_html($service->description);
                ?>
                    <div class="col-lg-4 col-md-6 mb-4 pricing-card-col">
                        <div class="pricing-card <?php echo $service->highlight ? 'featured' : ''; ?>">
                            
                            <?php if ($service->highlight): ?>
                                <div class="featured-badge">OFERTA ESPECIAL</div>
                            <?php endif; ?>
                            
                            <div class="pricing-header" style="background: linear-gradient(135deg, <?php echo esc_attr($card_color); ?>, <?php echo esc_attr($card_color); ?>dd); color: <?php echo esc_attr($text_color); ?>;">
                                <i class="<?php echo esc_attr($service->icon); ?>" style="color: <?php echo esc_attr($text_color); ?>;"></i>
                                <h3 style="color: <?php echo esc_attr($text_color); ?>;"><?php echo esc_html($service->name); ?></h3>
                                <div class="price">
                                    <span class="amount" style="color: <?php echo esc_attr($text_color); ?>;">$<?php echo number_format($service->price_usd, 0); ?></span>
                                    <span class="period" style="color: <?php echo esc_attr($text_color); ?>99;">/mes</span>
                                </div>
                            </div>
                            
                            <div class="pricing-body">
                                <div class="description-wrapper">
                                    <p class="description"><?php echo $description; ?></p>
                                </div>
                                
                                <?php if (!empty($features)): ?>
                                    <ul class="features-list">
                                        <?php foreach ($features as $feature): ?>
                                            <li><i class="fas fa-check"></i><span><?php echo esc_html($feature); ?></span></li>
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
        </div>
    </section>
    
    <script>
    // Efecto de rombo desactivado - tarjetas estáticas
    </script>
    
    <style>
    .pricing-section {
        padding: 30px 0;
        background: #f8f9fa;
        scroll-margin-top: 110px; /* Compensar header fijo al usar #planes */
    }
    
    .section-title {
        font-size: 2.2em;
        color: #333;
        margin-bottom: 10px;
        font-weight: 700;
    }
    
    .section-subtitle {
        font-size: 1.1em;
        color: #666;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .pricing-section {
            padding: 0;
            margin: 0;
            background: none;
            scroll-margin-top: 80px; /* Menor offset en mobile */
        }
        
        .section-title {
            font-size: 1.3em;
            margin: 0;
            padding: 5px 0 2px;
            line-height: 1.1;
        }
        
        .section-subtitle {
            font-size: 0.85em;
            margin: 0;
            padding: 0 0 5px 0;
            line-height: 1.1;
        }

        .description {
            font-size: 0.85em;
            line-height: 1.2;
            margin: 0;
            padding: 3px 5px;
            color: #666;
            font-weight: 700;
            display: block !important;
            visibility: visible !important;
        }

        .pricing-body {
            padding: 0;
            margin: 0;
        }

        .container {
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .row {
            margin: 0;
            padding: 0;
        }

        .col-12 {
            padding: 0;
        }

        .pricing-card {
            margin: 0 0 5px 0;
            border: none;
            box-shadow: none;
        }

        .pricing-header {
            padding: 5px;
            margin: 0;
        }

        .price {
            margin: 2px 0;
            padding: 0;
        }
        
        .container {
            padding: 0;
            margin: 0 auto;
            max-width: 100%;
        }
        
        .col-md-6, .col-lg-4 {
            padding: 0 2px;
        }
        
        .mb-4 {
            margin-bottom: 3px !important;
        }
        
        .row {
            margin: 0;
        }
        
        .pricing-card-col {
            padding: 0 1px;
        }
        
        .pricing-header {
            padding: 10px 5px;
        }
        
        .pricing-body {
            padding: 10px 5px;
        }
        
        .features-list {
            margin: 5px 0;
        }
        
        .features-list li {
            padding: 3px 0;
            font-size: 0.9em;
        }
    }
    
    .pricing-carousel-wrapper {
        margin: 0;
        padding: 0;
        position: relative;
    }
    
    .pricing-cards {
        display: flex;
        justify-content: center;
        align-items: stretch;
        gap: 5px;
        flex-wrap: wrap;
    }

    .description-wrapper {
        padding: 0;
        margin: 0;
    }

    @media (max-width: 768px) {
        .pricing-carousel-wrapper {
            margin: 0;
            padding: 0;
        }

        .pricing-cards {
            gap: 2px;
        }

        .pricing-card-col {
            padding: 0 1px;
            margin: 0 0 2px 0;
        }

        .description-wrapper {
            background-color: #f8f9fa;
            padding: 3px 5px;
            margin: 0;
        }

        .description {
            color: #555;
            font-size: 0.8em;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            font-weight: 700;
        }
    }

    @media (max-width: 768px) {
        .pricing-carousel-wrapper {
            margin: 0;
            padding: 0;
        }
        
        .pricing-cards {
            gap: 3px;
        }
        
        .pricing-card {
            margin: 0 0 3px 0;
            border-radius: 5px;
        }
        
        .pricing-header {
            padding: 5px;
            border-radius: 5px 5px 0 0;
        }
        
        .pricing-header h3 {
            margin: 0;
            padding: 2px 0;
            font-size: 1.2em;
            line-height: 1.2;
        }
        
        .price {
            margin: 2px 0;
        }
        
        .pricing-body {
            padding: 3px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .pricing-body p {
            margin: 0;
            padding: 0;
            line-height: 1.2;
            font-size: 0.85em;
        }
        
    .features-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    
    .features-list li {
        padding: 2px 3px;
        font-size: 0.8em;
        line-height: 1.1;
        display: flex;
        align-items: center;
        margin: 0;
    }

    .features-list i {
        font-size: 0.9em;
        margin-right: 3px;
        color: #28a745;
    }

    @media (max-width: 768px) {
        .features-list {
            margin: 0;
            padding: 0 2px;
        }
        
        .features-list li {
            padding: 1px 0;
            font-size: 0.75em;
            line-height: 1.1;
        }

        .features-list i {
            font-size: 0.8em;
            margin-right: 2px;
        }
    }        .mb-4 {
            margin-bottom: 10px !important;
        }

        .row {
            margin-left: -5px;
            margin-right: -5px;
        }
    }
    
    .pricing-card-col {
        flex: 0 0 auto;
        max-width: 360px;
        width: 100%;
    }
    
    .pricing-card {
        background: white;
        border-radius: 16px;
        padding: 0;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        transition: all 0.3s ease;
        position: relative;
        border: 1px solid #e9ecef;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: visible;
        width: 100%;
        margin: 0 auto;
    }
    
    .pricing-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.12);
    }
    
    .pricing-card.featured {
        border-color: #ff8c42 !important;
        border-width: 2px !important;
        transform: scale(1.02) !important;
        margin-top: 0 !important;
        box-shadow: 0 8px 30px rgba(255, 140, 66, 0.25) !important;
        position: relative !important;
    }
    
    .pricing-card.featured:hover {
        transform: scale(1.02) translateY(-5px);
    }
    
    .featured-badge {
        position: absolute !important;
        top: -22px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        background: #ff6200 !important;
        background: linear-gradient(135deg, #ff6200 0%, #ff8800 100%) !important;
        color: #ffffff !important;
        padding: 6px 18px !important;
        border-radius: 20px !important;
        font-size: 0.7rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.8px !important;
        box-shadow: 0 4px 12px rgba(255, 98, 0, 0.5) !important;
        z-index: 1000 !important;
        white-space: nowrap !important;
        animation: pulse-badge 2s ease-in-out infinite !important;
        min-width: auto !important;
        width: auto !important;
        display: inline-block !important;
    }
    
    @keyframes pulse-badge {
        0%, 100% {
            transform: translateX(-50%) scale(1);
            box-shadow: 0 4px 12px rgba(255, 98, 0, 0.5);
        }
        50% {
            transform: translateX(-50%) scale(1.04);
            box-shadow: 0 6px 18px rgba(255, 98, 0, 0.7);
        }
    }
    
    .pricing-header {
        padding: 15px 15px 12px 15px;
        border-radius: 16px 16px 0 0;
        margin-bottom: 0;
    }
    
    .pricing-header i {
        font-size: 1.8em;
        margin-bottom: 6px;
        display: block;
    }
    
    .pricing-header h3 {
        font-size: 1.2em;
        margin-bottom: 6px;
        font-weight: 700;
    }
    
    .price {
        margin-bottom: 0;
    }
    
    .price .amount {
        font-size: 1.8em;
        font-weight: bold;
        display: block;
        line-height: 1;
    }
    
    .price .period {
        color: rgba(255,255,255,0.8);
        font-size: 0.75em;
        margin-top: 2px;
        display: block;
    }
    
    .pricing-body {
        /* Evita que el body crezca y genere espacio en blanco */
        flex: 0 0 auto;
        padding: 12px 15px;
        background: white;
        color: #333;
    }
    
    .description {
        color: #666;
        margin-bottom: 10px;
        line-height: 1.3;
        font-size: 0.9em;
        font-weight: 700;
        display: block; /* Mostrar descripción por defecto */
    }
    
    .features-list {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: left;
    }
    
    .features-list li {
        padding: 3px 0;
        color: #555;
        display: flex;
        align-items: flex-start;
        font-size: 0.78em;
        line-height: 1.3;
    }
    
    .features-list li:last-child {
        border-bottom: none;
    }
    
    .features-list i {
        color: #28a745;
        margin-right: 6px;
        font-size: 0.7em;
        margin-top: 2px;
        flex-shrink: 0;
    }
    
    .pricing-footer {
        padding: 10px 15px 15px 15px;
        background: white;
        border-top: 1px solid #f0f0f0;
        border-radius: 0 0 16px 16px;
    }
    
    .btn-plan {
        color: white;
        padding: 10px 25px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        transition: all 0.3s ease;
        border: none;
        font-size: 0.9em;
        width: 100%;
        cursor: pointer;
    }
    
    .btn-plan:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        color: white;
        text-decoration: none;
        opacity: 0.9;
    }
    
    @media (max-width: 992px) {
        /* Desactivar efecto rombo en tablets y móviles */
        .pricing-carousel-wrapper {
            min-height: auto;
            perspective: none;
            overflow: visible;
        }
        
        .pricing-cards {
            display: block;
            flex-direction: column;
        }
        
        .pricing-card-col {
            transform: none !important;
            opacity: 1 !important;
            z-index: auto !important;
            max-width: 100%;
            flex: 0 0 100%;
            margin-bottom: 25px;
        }
        
        .pricing-cards.rotate-1 .pricing-card-col,
        .pricing-cards.rotate-2 .pricing-card-col {
            transform: none !important;
            opacity: 1 !important;
            z-index: auto !important;
        }
        
        .pricing-card {
            box-shadow: 0 5px 20px rgba(0,0,0,0.08) !important;
        }
    }
    
    @media (max-width: 768px) {
        .pricing-section {
            padding: 5px 0 5px 0;
            margin: 0;
        }
        
        .section-title {
            font-size: 1.5em;
            margin-bottom: 3px;
        }
        
        .section-subtitle {
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        
        .pricing-carousel-wrapper {
            margin-top: 8px;
            padding-top: 3px;
        }
        
        .pricing-cards {
            gap: 10px;
        }
        
        .pricing-card {
            margin-bottom: 10px;
        }
        
        .pricing-card.featured {
            transform: none !important;
            margin-top: 0 !important;
        }
        
        .pricing-header {
            padding: 8px 8px 6px 8px;
        }
        
        .pricing-header i {
            font-size: 1.3em;
            margin-bottom: 2px;
        }
        
        .pricing-header h3 {
            font-size: 0.95em;
            margin-bottom: 2px;
        }
        
        .price {
            margin-bottom: 0;
        }
        
        .price .amount {
            font-size: 1.4em;
            line-height: 1;
        }
        
        .price .period {
            font-size: 0.6em;
            margin-top: 0;
        }
        
        .pricing-body {
            padding: 6px 8px;
            flex: initial; /* Evitar crecimiento que genera huecos */
        }
        
        .description {
            display: block !important; /* Asegurar visibilidad en mobile */
            height: auto;
            margin: 4px 0 6px 0;
            padding: 0;
            overflow: visible;
            font-weight: 700;
        }
        
        .pricing-footer {
            padding: 5px 8px 8px 8px;
        }
        
        .btn-plan {
            padding: 6px 12px;
            font-size: 0.75em;
            border-radius: 15px;
        }
        
        .features-list {
            margin: 0;
            padding: 0;
        }
        
        .features-list li {
            font-size: 0.68em;
            padding: 1px 0;
            line-height: 1.15;
        }
        
        .features-list i {
            font-size: 0.55em;
            margin-right: 3px;
            margin-top: 0;
        }
        
        .featured-badge {
            top: -15px !important;
            padding: 3px 10px !important;
            font-size: 0.55rem !important;
            border-radius: 12px !important;
            letter-spacing: 0.5px !important;
        }
        
        .pricing-card-col {
            padding-left: 8px;
            padding-right: 8px;
            margin-bottom: 0;
        }

        /* Overrides fuertes para mobile: eliminar espacios y forzar altura automática */
        .pricing-card {
            display: block;
            height: auto;
            box-shadow: none;
        }

        .pricing-body {
            flex: initial;
            padding: 8px 10px;
        }

        .pricing-footer {
            margin-top: 6px;
            padding-bottom: 8px;
        }

        /* Quitar separador global en mobile */
        .section-spacer, .section-space {
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
    </style>
    <?php
    echo ob_get_clean();
}

// Renderizar sección de precio especial
function render_special_price_section() {
    ob_start();
    ?>
    <section class="special-price-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="special-price-card">
                        <div class="special-header">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="special-content">
                                        <h2 class="special-title">Web + WhatsApp Business.</h2>
                                        <p class="special-subtitle">Para Emprendimientos</p>
                                        
                                        <p class="special-description">
                                            Impulsa tu emprendimiento con una solución completa que incluye sitio web profesional y automatización de WhatsApp Business para generar más ventas y mejorar la atención al cliente.
                                        </p>
                                        
                                        <div class="features-section">
                                            <h3 class="features-title">¿Qué incluye?</h3>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <ul class="features-list">
                                                        <li><i class="fas fa-check"></i> Sitio web responsivo</li>
                                                        <li><i class="fas fa-check"></i> Catálogo de productos</li>
                                                        <li><i class="fas fa-check"></i> WhatsApp Business API</li>
                                                        <li><i class="fas fa-check"></i> Chat automatizado</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <ul class="features-list">
                                                        <li><i class="fas fa-check"></i> Botón de WhatsApp integrado</li>
                                                        <li><i class="fas fa-check"></i> Respuestas automáticas</li>
                                                        <li><i class="fas fa-check"></i> Horarios de atención</li>
                                                        <li><i class="fas fa-check"></i> Soporte técnico</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="price-card">
                                        <h3 class="price-title">Precio Especial</h3>
                                        <div class="price-amount">
                                            <span class="currency">$</span>
                                            <span class="amount">299</span>
                                            <span class="period">USD/mes</span>
                                        </div>
                                        <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa el servicio Web + WhatsApp Business')); ?>" 
                                           target="_blank" class="btn-special">
                                            ¡Quiero mi Web + WhatsApp!
                                        </a>
                                        <p class="no-contract">Sin permanencia</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <style>
    .special-price-section {
        padding: 60px 0;
        background: #f8f9fa;
    }
    
    .special-price-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .special-header {
        background: linear-gradient(135deg, #4472c4, #5882d4);
        color: white;
        padding: 40px;
    }
    
    .special-title {
        font-size: 2.2em;
        font-weight: 700;
        margin-bottom: 8px;
        color: white;
    }
    
    .special-subtitle {
        font-size: 1.1em;
        margin-bottom: 25px;
        opacity: 0.9;
        color: white;
    }
    
    .special-description {
        font-size: 1em;
        line-height: 1.6;
        margin-bottom: 30px;
        opacity: 0.95;
        color: white;
    }
    
    .features-title {
        font-size: 1.3em;
        font-weight: 600;
        margin-bottom: 20px;
        color: white;
    }
    
    .special-content .features-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .special-content .features-list li {
        padding: 6px 0;
        font-size: 0.95em;
        display: flex;
        align-items: center;
        color: white;
    }
    
    .special-content .features-list i {
        color: #86d647;
        margin-right: 10px;
        font-size: 0.9em;
    }
    
    .price-card {
        background: white;
        border: 2px solid #86d647;
        border-radius: 15px;
        padding: 30px 25px;
        text-align: center;
        margin: 20px 0;
    }
    
    .price-title {
        color: #4472c4;
        font-size: 1.3em;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .price-amount {
        margin-bottom: 25px;
    }
    
    .price-amount .currency {
        font-size: 1.2em;
        color: #4472c4;
        vertical-align: top;
    }
    
    .price-amount .amount {
        font-size: 3em;
        font-weight: bold;
        color: #4472c4;
        line-height: 1;
    }
    
    .price-amount .period {
        font-size: 0.9em;
        color: #666;
        display: block;
        margin-top: 5px;
    }
    
    .btn-special {
        background: #86d647;
        color: white;
        padding: 12px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        display: inline-block;
        transition: all 0.3s ease;
        width: 100%;
        font-size: 0.95em;
    }
    
    .btn-special:hover {
        background: #75c139;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(134, 214, 71, 0.4);
        color: white;
        text-decoration: none;
    }
    
    .no-contract {
        margin-top: 15px;
        font-size: 0.85em;
        color: #666;
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .special-price-section {
            padding: 40px 0;
        }
        
        .special-header {
            padding: 30px 20px;
        }
        
        .special-title {
            font-size: 1.8em;
            text-align: center;
        }
        
        .special-subtitle {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .special-description {
            font-size: 0.95em;
            margin-bottom: 25px;
        }
        
        .features-title {
            font-size: 1.2em;
            margin-bottom: 15px;
        }
        
        .special-content .features-list li {
            font-size: 0.9em;
            padding: 4px 0;
        }
        
        .price-card {
            margin: 25px 10px 10px 10px;
            padding: 25px 20px;
        }
        
        .price-title {
            font-size: 1.2em;
        }
        
        .price-amount .amount {
            font-size: 2.5em;
        }
        
        .btn-special {
            font-size: 0.9em;
            padding: 12px 15px;
        }
    }
    </style>
    <?php
    return ob_get_clean();
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
                                        Impulsa tu emprendimiento con una soluciÃ³n completa que incluye sitio web profesional 
                                        y automatizaciÃ³n de WhatsApp Business para generar mÃ¡s ventas y mejorar la atenciÃ³n al cliente.
                                    </p>
                                    <div class="features-list">
                                        <h5 class="mb-3">¿Qué incluye?</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <li><i class="fas fa-check text-success me-2"></i>Sitio web responsivo</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>CatÃ¡logo de productos</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>WhatsApp Business API</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Chat automatizado</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled">
                                                    <li><i class="fas fa-check text-success me-2"></i>BotÃ³n de WhatsApp integrado</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Respuestas automÃ¡ticas</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Horarios de atenciÃ³n</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Soporte tÃ©cnico</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="special-price-card-compact">
                                        <div class="special-badge">OFERTA ESPECIAL</div>
                                        <h4 class="price-title">Precio Especial</h4>
                                        <div class="price-display">
                                            <span class="currency">$</span>
                                            <span class="amount">299</span>
                                            <span class="period">/mes</span>
                                        </div>
                                        <p class="setup-fee">Setup inicial: $500 USD</p>
                                        <div class="button-container">
                                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa el servicio Web + WhatsApp Business')); ?>" 
                                               target="_blank" class="btn-special-compact">
                                                ¡Quiero mi Web + WhatsApp!
                                            </a>
                                        </div>
                                        <p class="no-permanence">Sin permanencia</p>
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
