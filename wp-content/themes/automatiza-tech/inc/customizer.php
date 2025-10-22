<?php
/**
 * Automatiza Tech Customizer functionality
 *
 * @package AutomatizaTech
 */

/**
 * Customizer additions
 */
function automatiza_tech_customize_register_extended($wp_customize) {
    
    // Hero Section
    $wp_customize->add_section('hero_section', array(
        'title'    => __('Sección Hero', 'automatiza-tech'),
        'priority' => 30,
    ));
    
    // Hero Background Image
    $wp_customize->add_setting('hero_background_image', array(
        'default'           => '',
        'sanitize_callback' => 'absint',
    ));
    
    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'hero_background_image', array(
        'label'     => __('Imagen de Fondo Hero', 'automatiza-tech'),
        'section'   => 'hero_section',
        'mime_type' => 'image',
    )));
    
    // Colors Section
    $wp_customize->add_section('colors_section', array(
        'title'    => __('Colores del Tema', 'automatiza-tech'),
        'priority' => 40,
    ));
    
    // Primary Color
    $wp_customize->add_setting('primary_color', array(
        'default'           => '#1e40af',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'primary_color', array(
        'label'   => __('Color Primario', 'automatiza-tech'),
        'section' => 'colors_section',
    )));
    
    // Secondary Color
    $wp_customize->add_setting('secondary_color', array(
        'default'           => '#84cc16',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'secondary_color', array(
        'label'   => __('Color Secundario', 'automatiza-tech'),
        'section' => 'colors_section',
    )));
    
    // Contact Section
    $wp_customize->add_section('contact_section', array(
        'title'    => __('Información de Contacto', 'automatiza-tech'),
        'priority' => 50,
    ));
    
    // Email
    $wp_customize->add_setting('contact_email', array(
        'default'           => 'info@automatizatech.com',
        'sanitize_callback' => 'sanitize_email',
    ));
    
    $wp_customize->add_control('contact_email', array(
        'label'   => __('Email de Contacto', 'automatiza-tech'),
        'section' => 'contact_section',
        'type'    => 'email',
    ));
    
    // Address
    $wp_customize->add_setting('contact_address', array(
        'default'           => 'Disponible en toda Latinoamérica',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('contact_address', array(
        'label'   => __('Dirección', 'automatiza-tech'),
        'section' => 'contact_section',
        'type'    => 'text',
    ));
    
    // Social Media Section
    $wp_customize->add_section('social_media', array(
        'title'    => __('Redes Sociales', 'automatiza-tech'),
        'priority' => 60,
    ));
    
    $social_networks = array(
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin'  => 'LinkedIn',
        'twitter'   => 'Twitter',
        'youtube'   => 'YouTube'
    );
    
    foreach ($social_networks as $network => $label) {
        $wp_customize->add_setting("social_{$network}", array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ));
        
        $wp_customize->add_control("social_{$network}", array(
            'label'   => $label,
            'section' => 'social_media',
            'type'    => 'url',
        ));
    }
    
    // Footer Section
    $wp_customize->add_section('footer_section', array(
        'title'    => __('Configuración Footer', 'automatiza-tech'),
        'priority' => 70,
    ));
    
    // Footer Text
    $wp_customize->add_setting('footer_text', array(
        'default'           => 'Conectamos tus ventas, web y CRM con bots inteligentes para negocios que no se detienen.',
        'sanitize_callback' => 'sanitize_textarea_field',
    ));
    
    $wp_customize->add_control('footer_text', array(
        'label'   => __('Texto del Footer', 'automatiza-tech'),
        'section' => 'footer_section',
        'type'    => 'textarea',
    ));
    
    // Copyright Text
    $wp_customize->add_setting('copyright_text', array(
        'default'           => 'Automatiza Tech. Todos los derechos reservados.',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('copyright_text', array(
        'label'   => __('Texto de Copyright', 'automatiza-tech'),
        'section' => 'footer_section',
        'type'    => 'text',
    ));
}
add_action('customize_register', 'automatiza_tech_customize_register_extended');

/**
 * Output custom CSS based on customizer settings
 */
function automatiza_tech_customizer_css() {
    $primary_color = get_theme_mod('primary_color', '#1e40af');
    $secondary_color = get_theme_mod('secondary_color', '#84cc16');
    
    ?>
    <style type="text/css">
        :root {
            --color-primary: <?php echo esc_attr($primary_color); ?>;
            --color-secondary: <?php echo esc_attr($secondary_color); ?>;
        }
        
        .text-primary,
        .section-title,
        .main-navigation a:hover {
            color: <?php echo esc_attr($primary_color); ?> !important;
        }
        
        .bg-primary,
        .btn-primary,
        .hero-section {
            background-color: <?php echo esc_attr($primary_color); ?> !important;
        }
        
        .text-secondary,
        .hero-subtitle,
        .footer-section h3 {
            color: <?php echo esc_attr($secondary_color); ?> !important;
        }
        
        .bg-secondary,
        .btn-secondary {
            background-color: <?php echo esc_attr($secondary_color); ?> !important;
        }
        
        .btn-outline {
            border-color: <?php echo esc_attr($primary_color); ?> !important;
            color: <?php echo esc_attr($primary_color); ?> !important;
        }
        
        .btn-outline:hover {
            background-color: <?php echo esc_attr($primary_color); ?> !important;
        }
        
        .feature-icon {
            background: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?>, <?php echo esc_attr($secondary_color); ?>) !important;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: <?php echo esc_attr($primary_color); ?> !important;
            box-shadow: 0 0 0 3px <?php echo esc_attr($primary_color); ?>26 !important;
        }
        
        .integration-item:hover {
            border-color: <?php echo esc_attr($primary_color); ?> !important;
        }
        
        .pricing-card.border-primary {
            border-color: <?php echo esc_attr($primary_color); ?> !important;
        }
        
        .whatsapp-float:hover {
            transform: scale(1.1);
        }
        
        <?php
        // Hero background image
        $hero_bg = get_theme_mod('hero_background_image');
        if ($hero_bg) {
            $hero_bg_url = wp_get_attachment_image_url($hero_bg, 'full');
            if ($hero_bg_url) {
                ?>
                .hero-section {
                    background-image: linear-gradient(135deg, <?php echo esc_attr($primary_color); ?>CC 0%, #1d4ed8CC 100%), url('<?php echo esc_url($hero_bg_url); ?>');
                    background-size: cover;
                    background-position: center;
                    background-attachment: fixed;
                }
                <?php
            }
        }
        ?>
    </style>
    <?php
}
add_action('wp_head', 'automatiza_tech_customizer_css');

/**
 * Live preview JavaScript
 */
function automatiza_tech_customize_preview_js() {
    wp_enqueue_script(
        'automatiza-tech-customizer-preview',
        get_template_directory_uri() . '/assets/js/customizer-preview.js',
        array('customize-preview'),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action('customize_preview_init', 'automatiza_tech_customize_preview_js');

/**
 * Customizer controls JavaScript
 */
function automatiza_tech_customize_controls_js() {
    wp_enqueue_script(
        'automatiza-tech-customizer-controls',
        get_template_directory_uri() . '/assets/js/customizer-controls.js',
        array('customize-controls'),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action('customize_controls_enqueue_scripts', 'automatiza_tech_customize_controls_js');