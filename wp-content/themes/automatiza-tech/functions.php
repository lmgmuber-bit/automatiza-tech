<?php
/**
 * Automatiza Tech Theme Functions
 * 
 * @package AutomatizaTech
 * @version 1.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración del tema
 */
function automatiza_tech_setup() {
    // Soporte para título automático
    add_theme_support('title-tag');
    
    // Soporte para imágenes destacadas
    add_theme_support('post-thumbnails');
    
    // Soporte para logos personalizados
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 300,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    
    // Soporte para HTML5
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ));
    
    // Soporte para Feed Links
    add_theme_support('automatic-feed-links');
    
    // Menús de navegación
    register_nav_menus(array(
        'primary' => __('Menú Principal', 'automatiza-tech'),
        'footer'  => __('Menú Footer', 'automatiza-tech'),
    ));
    
    // Tamaños de imagen personalizados
    add_image_size('hero-image', 1200, 600, true);
    add_image_size('feature-image', 400, 300, true);
}
add_action('after_setup_theme', 'automatiza_tech_setup');

/**
 * Encolar estilos y scripts
 */
function automatiza_tech_scripts() {
    // Bootstrap CSS (CDN para mejor performance)
    wp_enqueue_style(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        array(),
        '5.3.0'
    );
    
    // Google Fonts
    wp_enqueue_style(
        'google-fonts',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Open+Sans:wght@400;600&display=swap',
        array(),
        null
    );
    
    // Estilo principal del tema
    wp_enqueue_style(
        'automatiza-tech-style',
        get_stylesheet_uri(),
        array('bootstrap'),
        wp_get_theme()->get('Version')
    );
    
    // Bootstrap JS
    wp_enqueue_script(
        'bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        array(),
        '5.3.0',
        true
    );
    
    // Script personalizado del tema
    wp_enqueue_script(
        'automatiza-tech-script',
        get_template_directory_uri() . '/assets/js/main.js',
        array('jquery', 'bootstrap'),
        wp_get_theme()->get('Version'),
        true
    );
    
    // Localizar script para AJAX
    wp_localize_script('automatiza-tech-script', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('automatiza_tech_nonce')
    ));
    
    // También localizar para jQuery (usado por el formulario de contacto)
    wp_localize_script('jquery', 'automatiza_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('automatiza_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'automatiza_tech_scripts');

/**
 * Registrar widgets
 */
function automatiza_tech_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar Principal', 'automatiza-tech'),
        'id'            => 'sidebar-1',
        'description'   => __('Widgets para la barra lateral principal.', 'automatiza-tech'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 1', 'automatiza-tech'),
        'id'            => 'footer-1',
        'description'   => __('Primera columna del footer.', 'automatiza-tech'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 2', 'automatiza-tech'),
        'id'            => 'footer-2',
        'description'   => __('Segunda columna del footer.', 'automatiza-tech'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ));
    
    register_sidebar(array(
        'name'          => __('Footer 3', 'automatiza-tech'),
        'id'            => 'footer-3',
        'description'   => __('Tercera columna del footer.', 'automatiza-tech'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ));
}
add_action('widgets_init', 'automatiza_tech_widgets_init');

/**
 * Optimizaciones de rendimiento
 */
function automatiza_tech_performance_optimizations() {
    // Preload de recursos críticos
    add_action('wp_head', function() {
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Open+Sans:wght@400;600&display=swap" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        echo '<link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style">';
    });
}
add_action('init', 'automatiza_tech_performance_optimizations');

/**
 * Reemplazar jQuery en el FRONT usando el gancho correcto.
 * Evita avisos al no ejecutar deregister en admin o en hooks incorrectos.
 */
function automatiza_tech_override_jquery() {
    // Solo en el frontend
    if (is_admin()) {
        return;
    }
    // Reemplazar jQuery core por CDN en el hook recomendado
    wp_deregister_script('jquery');
    wp_register_script('jquery', 'https://code.jquery.com/jquery-3.6.0.min.js', array(), '3.6.0', true);
    // No forzamos wp_enqueue_script('jquery') aquí; se cargará por dependencia
}
add_action('wp_enqueue_scripts', 'automatiza_tech_override_jquery', 0);

/**
 * Manejar formulario de contacto
 */
function handle_contact_form() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'automatiza_tech_nonce')) {
        wp_die('Error de seguridad');
    }
    
    // Sanitizar datos
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $company = sanitize_text_field($_POST['company']);
    $phone = sanitize_text_field($_POST['phone']);
    $message = sanitize_textarea_field($_POST['message']);
    
    // Validar email
    if (!is_email($email)) {
        wp_send_json_error('Email no válido');
    }
    
    // Configurar email
    $to = get_option('admin_email');
    $subject = 'Nuevo contacto desde Automatiza Tech - ' . $name;
    $body = "
    Nuevo mensaje de contacto:

    Nombre: $name
    Email: $email
    Empresa: $company
    Teléfono: $phone

    Mensaje:
    $message
    ";    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $name . ' <' . $email . '>'
    );
    
    // Enviar email
    $sent = wp_mail($to, $subject, $body, $headers);
    
    if ($sent) {
        wp_send_json_success('Mensaje enviado correctamente');
    } else {
        wp_send_json_error('Error al enviar el mensaje');
    }
}
add_action('wp_ajax_contact_form', 'handle_contact_form');
add_action('wp_ajax_nopriv_contact_form', 'handle_contact_form');

/**
 * Customizer options
 */
function automatiza_tech_customize_register($wp_customize) {
    // Sección de configuración
    $wp_customize->add_section('automatiza_tech_options', array(
        'title'    => __('Opciones Automatiza Tech', 'automatiza-tech'),
        'priority' => 120,
    ));
    
    // WhatsApp número
    $wp_customize->add_setting('whatsapp_number', array(
        'default'           => '+56 9 4033 1127',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('whatsapp_number', array(
        'label'   => __('Número de WhatsApp', 'automatiza-tech'),
        'section' => 'automatiza_tech_options',
        'type'    => 'text',
    ));
    
    // Hero title
    $wp_customize->add_setting('hero_title', array(
        'default'           => 'Automatiza Tech',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('hero_title', array(
        'label'   => __('Título Principal', 'automatiza-tech'),
        'section' => 'automatiza_tech_options',
        'type'    => 'text',
    ));
    
    // Hero subtitle
    $wp_customize->add_setting('hero_subtitle', array(
        'default'           => 'Conectamos tus ventas, web y CRM.',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('hero_subtitle', array(
        'label'   => __('Subtítulo', 'automatiza-tech'),
        'section' => 'automatiza_tech_options',
        'type'    => 'text',
    ));
    
    // Hero tagline
    $wp_customize->add_setting('hero_tagline', array(
        'default'           => 'Bots inteligentes para negocios que no se detienen.',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    
    $wp_customize->add_control('hero_tagline', array(
        'label'   => __('Tagline', 'automatiza-tech'),
        'section' => 'automatiza_tech_options',
        'type'    => 'text',
    ));
}
add_action('customize_register', 'automatiza_tech_customize_register');

/**
 * Obtener URL de WhatsApp
 */
function get_whatsapp_url($message = '') {
    $number = get_theme_mod('whatsapp_number', '+56 9 4033 1127');
    // Limpiar el número: remover espacios, guiones, paréntesis pero mantener el +
    $number = preg_replace('/[^0-9+]/', '', $number);
    
    if ($message) {
        $message = urlencode($message);
        return "https://wa.me/{$number}?text={$message}";
    }
    
    return "https://wa.me/{$number}";
}



/**
 * Optimizaciones SEO básicas
 */
function automatiza_tech_seo_optimizations() {
    // Meta tags básicos
    add_action('wp_head', function() {
        if (is_front_page()) {
            echo '<meta name="description" content="Automatiza Tech - Conectamos tus ventas, web y CRM. Bots inteligentes para negocios que no se detienen. Mejora tu atención al cliente 24/7.">';
            echo '<meta name="keywords" content="automatización, chatbots, CRM, ventas, WhatsApp, Instagram, atención al cliente">';
            echo '<meta property="og:title" content="Automatiza Tech - Conectamos tus ventas, web y CRM">';
            echo '<meta property="og:description" content="Bots inteligentes para negocios que no se detienen. Automatiza tu atención, ahorra tiempo, escala tu negocio.">';
            echo '<meta property="og:type" content="website">';
            echo '<meta property="og:url" content="' . home_url() . '">';
        }
    });
}
add_action('init', 'automatiza_tech_seo_optimizations');

/**
 * Incluir archivos adicionales
 */
require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/template-functions.php';

// Incluir configuración de desarrollo si estamos en localhost
if (defined('WP_DEBUG') && WP_DEBUG && (strpos(home_url(), 'localhost') !== false || strpos(home_url(), '.local') !== false)) {
    require get_template_directory() . '/inc/development-config.php';
}

/**
 * Límite de revisiones de posts para mejor rendimiento
 */
if (!defined('WP_POST_REVISIONS')) {
    define('WP_POST_REVISIONS', 3);
}

/**
 * Incluir sistema de formulario de contacto
 */
require_once get_template_directory() . '/inc/contact-form.php';

/**
 * Incluir configuración SMTP para correos
 */
require_once get_template_directory() . '/inc/smtp-config.php';

/**
 * Incluir shortcode del formulario de contacto
 */
require_once get_template_directory() . '/inc/contact-shortcode.php';

/**
 * Incluir configuración de datos de facturación
 */
require_once get_template_directory() . '/inc/invoice-settings.php';

/**
 * Incluir actualizador automático de precios CLP
 */
require_once get_template_directory() . '/inc/currency-updater.php';

/**
 * Incluir panel de administración de precios CLP
 */
require_once get_template_directory() . '/inc/currency-admin.php';

/**
 * Incluir sistema de gestión de servicios
 */
require_once get_template_directory() . '/inc/services-manager.php';

/**
 * Incluir funciones de frontend para servicios
 */
// require_once get_template_directory() . '/inc/services-frontend.php'; // Comentado para evitar conflictos
require_once get_template_directory() . '/services-frontend.php';

/**
 * Incluir handlers AJAX para facturas (descarga y validación)
 */
require_once get_template_directory() . '/inc/invoice-handlers.php';


