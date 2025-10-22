<?php
/**
 * Template functions for Automatiza Tech theme
 *
 * @package AutomatizaTech
 */

/**
 * Get social media links
 */
function automatiza_tech_get_social_links() {
    $social_networks = array(
        'facebook'  => array('icon' => 'fab fa-facebook', 'name' => 'Facebook'),
        'instagram' => array('icon' => 'fab fa-instagram', 'name' => 'Instagram'),
        'linkedin'  => array('icon' => 'fab fa-linkedin', 'name' => 'LinkedIn'),
        'twitter'   => array('icon' => 'fab fa-twitter', 'name' => 'Twitter'),
        'youtube'   => array('icon' => 'fab fa-youtube', 'name' => 'YouTube')
    );
    
    $links = array();
    
    foreach ($social_networks as $network => $data) {
        $url = get_theme_mod("social_{$network}");
        if ($url) {
            $links[$network] = array(
                'url' => esc_url($url),
                'icon' => $data['icon'],
                'name' => $data['name']
            );
        }
    }
    
    return $links;
}

/**
 * Display social media links
 */
function automatiza_tech_social_links($echo = true) {
    $links = automatiza_tech_get_social_links();
    $output = '';
    
    if (!empty($links)) {
        $output .= '<div class="social-links">';
        foreach ($links as $network => $data) {
            $output .= sprintf(
                '<a href="%s" target="_blank" rel="noopener" title="%s" class="social-link social-link-%s">
                    <i class="%s"></i>
                </a>',
                $data['url'],
                $data['name'],
                $network,
                $data['icon']
            );
        }
        $output .= '</div>';
    }
    
    if ($echo) {
        echo $output;
    }
    
    return $output;
}

/**
 * Get featured services
 */
function automatiza_tech_get_featured_services() {
    return array(
        array(
            'icon' => 'fas fa-robot',
            'title' => 'Chatbots Inteligentes',
            'description' => 'Bots avanzados con IA que entienden y responden como humanos.',
            'features' => array('Procesamiento de lenguaje natural', 'Aprendizaje automático', 'Respuestas contextuales')
        ),
        array(
            'icon' => 'fab fa-whatsapp',
            'title' => 'Integración WhatsApp',
            'description' => 'Conecta tu negocio con WhatsApp Business API.',
            'features' => array('WhatsApp Business API', 'Mensajes masivos', 'Automatización completa')
        ),
        array(
            'icon' => 'fas fa-cogs',
            'title' => 'Automatización CRM',
            'description' => 'Sincroniza leads y clientes automáticamente con tu CRM.',
            'features' => array('Integración con CRMs populares', 'Sincronización en tiempo real', 'Gestión de leads')
        ),
        array(
            'icon' => 'fas fa-chart-line',
            'title' => 'Analíticas Avanzadas',
            'description' => 'Métricas detalladas para optimizar tu estrategia.',
            'features' => array('Reportes en tiempo real', 'Métricas de conversión', 'Análisis de comportamiento')
        )
    );
}

/**
 * Get pricing plans
 */
function automatiza_tech_get_pricing_plans() {
    return array(
        'basic' => array(
            'name' => 'Básico',
            'price' => 99,
            'period' => 'mes',
            'description' => 'Perfecto para pequeños negocios que inician su automatización',
            'features' => array(
                'Hasta 1,000 conversaciones/mes',
                'WhatsApp y Web Chat',
                'Respuestas automáticas básicas',
                'Soporte por email',
                'Analíticas básicas',
                'Integración con 1 CRM'
            ),
            'cta' => 'Comenzar',
            'popular' => false
        ),
        'professional' => array(
            'name' => 'Profesional',
            'price' => 199,
            'period' => 'mes',
            'description' => 'Ideal para empresas en crecimiento con necesidades avanzadas',
            'features' => array(
                'Hasta 5,000 conversaciones/mes',
                'Todas las integraciones',
                'IA avanzada y personalizada',
                'Soporte prioritario',
                'Analíticas avanzadas',
                'API personalizada',
                'Múltiples agentes',
                'Automatización de workflows'
            ),
            'cta' => 'Más Popular',
            'popular' => true
        ),
        'enterprise' => array(
            'name' => 'Enterprise',
            'price' => 399,
            'period' => 'mes',
            'description' => 'Solución completa para grandes empresas y corporaciones',
            'features' => array(
                'Conversaciones ilimitadas',
                'Integraciones personalizadas',
                'IA ultra avanzada',
                'Soporte 24/7 dedicado',
                'Gerente de cuenta dedicado',
                'Implementación personalizada',
                'SLA garantizado',
                'Personalización completa'
            ),
            'cta' => 'Contactar',
            'popular' => false
        )
    );
}

/**
 * Display pricing plans
 */
function automatiza_tech_display_pricing_plans() {
    $plans = automatiza_tech_get_pricing_plans();
    
    foreach ($plans as $plan_id => $plan) {
        $card_class = 'pricing-card card h-100';
        $header_class = 'card-header text-center';
        $button_class = 'btn';
        
        if ($plan['popular']) {
            $card_class .= ' border-primary featured-plan';
            $header_class .= ' bg-primary text-white';
            $button_class .= ' btn-primary';
        } else {
            $header_class .= ' bg-light';
            $button_class .= ' btn-outline-primary';
        }
        
        ?>
        <div class="col-md-4 mb-4">
            <div class="<?php echo esc_attr($card_class); ?>">
                <div class="<?php echo esc_attr($header_class); ?>">
                    <h5 class="card-title"><?php echo esc_html($plan['name']); ?></h5>
                    <div class="price">
                        <span class="currency">$</span>
                        <span class="amount"><?php echo esc_html($plan['price']); ?></span>
                        <span class="period">/<?php echo esc_html($plan['period']); ?></span>
                    </div>
                    <?php if ($plan['popular']): ?>
                        <span class="badge badge-light">Más Popular</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="card-text text-muted"><?php echo esc_html($plan['description']); ?></p>
                    <ul class="list-unstyled">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li><i class="fas fa-check text-success"></i> <?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-footer text-center">
                    <a href="#contact" class="<?php echo esc_attr($button_class); ?>">
                        <?php echo esc_html($plan['cta']); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * Get industries data
 */
function automatiza_tech_get_industries() {
    return array(
        'ecommerce' => array(
            'name' => 'E-commerce',
            'icon' => 'fas fa-store',
            'description' => 'Automatiza consultas de productos, seguimiento de pedidos y soporte post-venta.',
            'use_cases' => array(
                'Consultas de productos',
                'Estado de pedidos',
                'Recomendaciones personalizadas',
                'Soporte post-venta'
            )
        ),
        'health' => array(
            'name' => 'Salud',
            'icon' => 'fas fa-heartbeat',
            'description' => 'Gestiona citas médicas, recordatorios y consultas básicas de pacientes.',
            'use_cases' => array(
                'Agenda de citas',
                'Recordatorios de medicina',
                'Consultas básicas',
                'Seguimiento de pacientes'
            )
        ),
        'education' => array(
            'name' => 'Educación',
            'icon' => 'fas fa-graduation-cap',
            'description' => 'Atiende consultas de estudiantes, información de cursos y procesos de inscripción.',
            'use_cases' => array(
                'Información de cursos',
                'Proceso de inscripción',
                'Soporte académico',
                'Calendario escolar'
            )
        ),
        'restaurants' => array(
            'name' => 'Restaurantes',
            'icon' => 'fas fa-utensils',
            'description' => 'Toma pedidos automáticamente, gestiona reservas y ofrece menús interactivos.',
            'use_cases' => array(
                'Toma de pedidos',
                'Reservas de mesa',
                'Menú interactivo',
                'Delivery tracking'
            )
        ),
        'real_estate' => array(
            'name' => 'Inmobiliaria',
            'icon' => 'fas fa-home',
            'description' => 'Califica leads, agenda visitas y proporciona información de propiedades.',
            'use_cases' => array(
                'Calificación de leads',
                'Agenda de visitas',
                'Info de propiedades',
                'Cotizaciones automáticas'
            )
        ),
        'services' => array(
            'name' => 'Servicios',
            'icon' => 'fas fa-briefcase',
            'description' => 'Gestiona cotizaciones, agenda citas y brinda soporte técnico automatizado.',
            'use_cases' => array(
                'Cotizaciones automáticas',
                'Agenda de servicios',
                'Soporte técnico',
                'Follow-up de clientes'
            )
        )
    );
}

/**
 * Display industry cards
 */
function automatiza_tech_display_industries() {
    $industries = automatiza_tech_get_industries();
    
    foreach ($industries as $industry_id => $industry) {
        ?>
        <div class="col-md-4 mb-4">
            <div class="industry-card card h-100">
                <div class="card-body text-center">
                    <div class="industry-icon text-primary mb-3">
                        <i class="<?php echo esc_attr($industry['icon']); ?> fa-3x"></i>
                    </div>
                    <h5 class="card-title"><?php echo esc_html($industry['name']); ?></h5>
                    <p class="card-text"><?php echo esc_html($industry['description']); ?></p>
                    <div class="use-cases">
                        <h6>Casos de uso:</h6>
                        <ul class="list-unstyled">
                            <?php foreach ($industry['use_cases'] as $use_case): ?>
                                <li><i class="fas fa-check text-success"></i> <?php echo esc_html($use_case); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

/**
 * Get testimonials
 */
function automatiza_tech_get_testimonials() {
    return array(
        array(
            'name' => 'María González',
            'company' => 'TiendaOnline.com',
            'industry' => 'E-commerce',
            'testimonial' => 'Automatiza Tech revolucionó nuestra atención al cliente. Ahora atendemos 10 veces más consultas con la misma calidad.',
            'rating' => 5,
            'image' => ''
        ),
        array(
            'name' => 'Carlos Rodríguez',
            'company' => 'Clínica San Rafael',
            'industry' => 'Salud',
            'testimonial' => 'La automatización de citas médicas nos ahorró 15 horas semanales de trabajo administrativo.',
            'rating' => 5,
            'image' => ''
        ),
        array(
            'name' => 'Ana Martínez',
            'company' => 'RestaurantePremium',
            'industry' => 'Restaurantes',
            'testimonial' => 'Nuestros pedidos por WhatsApp aumentaron 300% desde que implementamos el bot de Automatiza Tech.',
            'rating' => 5,
            'image' => ''
        )
    );
}

/**
 * Get FAQ data
 */
function automatiza_tech_get_faq() {
    return array(
        array(
            'question' => '¿Qué tan rápido puedo implementar el chatbot?',
            'answer' => 'La implementación básica toma entre 24-48 horas. Para configuraciones personalizadas, puede tomar hasta 1 semana.'
        ),
        array(
            'question' => '¿Se integra con mi CRM actual?',
            'answer' => 'Sí, nos integramos con los CRMs más populares como Salesforce, HubSpot, Pipedrive, Zoho, y muchos más.'
        ),
        array(
            'question' => '¿Qué idiomas soporta el bot?',
            'answer' => 'Actualmente soportamos español, inglés, portugués y francés. Podemos agregar idiomas adicionales según necesidad.'
        ),
        array(
            'question' => '¿Hay límite en el número de conversaciones?',
            'answer' => 'Depende del plan elegido. El plan básico incluye 1,000 conversaciones/mes, mientras que Enterprise es ilimitado.'
        ),
        array(
            'question' => '¿Qué tipo de soporte ofrecen?',
            'answer' => 'Ofrecemos soporte por email, chat en vivo, y llamadas telefónicas. Los planes superiores incluyen soporte prioritario 24/7.'
        )
    );
}

/**
 * Format price with currency
 */
function automatiza_tech_format_price($price, $currency = '$') {
    return $currency . number_format($price, 0, '.', ',');
}

/**
 * Get contact information
 */
function automatiza_tech_get_contact_info() {
    return array(
        'email' => get_theme_mod('contact_email', 'info@automatizatech.com'),
        'phone' => get_theme_mod('whatsapp_number', '+1234567890'),
        'address' => get_theme_mod('contact_address', 'Disponible en toda Latinoamérica'),
        'hours' => 'Atención 24/7 con nuestros bots'
    );
}

/**
 * Check if development mode
 */
function automatiza_tech_is_dev_mode() {
    return defined('WP_DEBUG') && WP_DEBUG;
}

/**
 * Get optimized image URL
 */
function automatiza_tech_get_optimized_image($attachment_id, $size = 'medium') {
    if (!$attachment_id) {
        return '';
    }
    
    $image_url = wp_get_attachment_image_url($attachment_id, $size);
    
    // Add WebP support if available
    if (function_exists('wp_get_webp_info')) {
        $webp_url = str_replace(array('.jpg', '.jpeg', '.png'), '.webp', $image_url);
        if (file_exists(str_replace(home_url(), ABSPATH, $webp_url))) {
            return $webp_url;
        }
    }
    
    return $image_url;
}