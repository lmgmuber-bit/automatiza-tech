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
 * Excluir REST API de LiteSpeed Cache
 * Especialmente las rutas de recordatorios que N8N consulta frecuentemente
 */
add_action('init', function() {
    // Si es una petición a la REST API, desactivar caché
    if (isset($_SERVER['REQUEST_URI']) && 
        (strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false || 
         strpos($_SERVER['REQUEST_URI'], 'rest_route=') !== false)) {
        
        // Headers para prevenir caché
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('X-LiteSpeed-Cache-Control: no-cache, private');
            header('X-Accel-Expires: 0');
        }
        
        // Constante para LiteSpeed Cache plugin
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!defined('LSCACHE_NO_CACHE')) {
            define('LSCACHE_NO_CACHE', true);
        }
    }
}, 1); // Prioridad muy alta (1)

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
 *
 * LOCAL  → guarda en BD, retorna éxito (sin SMTP disponible)
 * PROD   → guarda en BD + envía email, retorna éxito solo si el mail se envió
 */
function handle_contact_form() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'automatiza_tech_nonce')) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    // Sanitizar datos
    $name    = sanitize_text_field($_POST['name']        ?? '');
    $email   = sanitize_email($_POST['email']            ?? '');
    $company = sanitize_text_field($_POST['company']     ?? '');
    $phone   = sanitize_text_field($_POST['phone']       ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    // Validar email
    if (!is_email($email)) {
        wp_send_json_error('Email no válido');
        return;
    }

    // Detectar entorno: local = localhost / 127.0.0.1 / .local / wp_get_environment_type()
    $env  = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = in_array($env, array('local', 'development'))
             || $host === 'localhost'
             || strpos($host, '127.0.0.1') !== false
             || substr($host, -6) === '.local';

    // Guardar siempre en BD (no se pierde ningún lead)
    wp_insert_post(array(
        'post_type'   => 'at_demo_request',
        'post_title'  => $name . ' — ' . $email,
        'post_status' => 'private',
        'meta_input'  => array(
            '_at_name'    => $name,
            '_at_email'   => $email,
            '_at_company' => $company,
            '_at_phone'   => $phone,
            '_at_message' => $message,
            '_at_date'    => current_time('mysql'),
            '_at_env'     => $is_local ? 'local' : 'production',
            '_at_source'  => 'contact_form',
        ),
    ));

    // Extraer fecha y hora del mensaje si vienen del modal de agendamiento
    $fecha_demo = '';
    $hora_demo  = '';
    if ( preg_match('/Fecha:\s*([^\s]+)/', $message, $mf) ) $fecha_demo = $mf[1];
    if ( preg_match('/Hora:\s*(.+)$/',    $message, $mh) ) $hora_demo  = trim($mh[1]);

    $fecha_hora_row = '';
    if ( $fecha_demo || $hora_demo ) {
        $fecha_hora_row = '
            <tr>
                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                    <strong style="color:#1e3a8a;">📅 Demo agendada:</strong>
                </td>
                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                    <span style="background:#06d6a0;color:#0a1628;font-weight:700;padding:3px 10px;border-radius:20px;font-size:13px;">'
                    . esc_html($fecha_demo) . ' — ' . esc_html($hora_demo) .
                    '</span>
                </td>
            </tr>';
    }

    // Preparar email con plantilla HTML branded AT
    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $to         = 'automatizatech.bots@gmail.com';
    $subject    = '🗓️ Nueva solicitud de demo — ' . $name;
    $body       = '
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
    <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;background:#f0f4ff;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:24px;">
        <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(30,58,138,0.12);">

            <!-- Header AT -->
            <tr>
                <td style="background:linear-gradient(135deg,#1e3a8a 0%,#0d2d45 60%,#062a2a 100%);padding:32px 30px;text-align:center;">
                    <h1 style="color:#ffffff;margin:0;font-size:22px;letter-spacing:0.5px;">🗓️ Nueva Solicitud de Demo</h1>
                    <p style="color:#06d6a0;margin:8px 0 0 0;font-size:14px;font-weight:600;">AutomatizaTech · Plataforma Omnicanal con IA</p>
                </td>
            </tr>

            <!-- Body -->
            <tr>
                <td style="padding:30px;">

                    <!-- Info del contacto -->
                    <div style="background:#f4f7ff;padding:20px;border-radius:8px;border-left:4px solid #1e3a8a;margin-bottom:20px;">
                        <h2 style="color:#1e3a8a;margin:0 0 14px 0;font-size:16px;">👤 Datos del prospecto</h2>
                        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
                            <tr>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;width:32%;">
                                    <strong style="color:#1e3a8a;">Nombre:</strong>
                                </td>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">' . esc_html($name) . '</td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                                    <strong style="color:#1e3a8a;">Email:</strong>
                                </td>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                                    <a href="mailto:' . esc_attr($email) . '" style="color:#1e3a8a;text-decoration:none;">' . esc_html($email) . '</a>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                                    <strong style="color:#1e3a8a;">Teléfono:</strong>
                                </td>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                                    ' . ( empty($phone) ? '<em style="color:#999;">No especificado</em>' : '<a href="tel:' . esc_attr($phone) . '" style="color:#1e3a8a;text-decoration:none;">' . esc_html($phone) . '</a>' ) . '
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                                    <strong style="color:#1e3a8a;">Empresa:</strong>
                                </td>
                                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                                    ' . ( empty($company) ? '<em style="color:#999;">No especificada</em>' : esc_html($company) ) . '
                                </td>
                            </tr>
                            ' . $fecha_hora_row . '
                            <tr>
                                <td style="padding:8px 0;">
                                    <strong style="color:#1e3a8a;">Recibido:</strong>
                                </td>
                                <td style="padding:8px 0;">' . current_time('d/m/Y H:i:s') . '</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Mensaje -->
                    <div style="background:#f0fff8;padding:18px;border-radius:8px;border-left:4px solid #06d6a0;margin-bottom:24px;">
                        <h3 style="color:#06b084;margin:0 0 8px 0;font-size:15px;">📝 Notas:</h3>
                        <p style="color:#333;margin:0;line-height:1.6;white-space:pre-wrap;">' . esc_html($message) . '</p>
                    </div>

                    <!-- CTA al admin -->
                    <div style="text-align:center;margin-top:10px;">
                        <a href="' . admin_url('edit.php?post_type=at_demo_request') . '"
                           style="display:inline-block;background:linear-gradient(135deg,#1e3a8a,#06d6a0);color:#fff;padding:13px 32px;text-decoration:none;border-radius:30px;font-weight:700;font-size:14px;box-shadow:0 4px 14px rgba(6,214,160,0.35);">
                            📋 Ver solicitud en el Panel
                        </a>
                    </div>

                </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td style="background:#f4f7ff;padding:18px 30px;text-align:center;border-top:1px solid #e0e8f0;">
                    <p style="color:#555;margin:0;font-size:12px;">
                        🌐 Enviado desde: <a href="' . home_url() . '" style="color:#1e3a8a;text-decoration:none;">' . home_url() . '</a>
                    </p>
                    <p style="color:#999;margin:6px 0 0 0;font-size:11px;">
                        Mensaje automático del sistema de agendamiento — AutomatizaTech
                    </p>
                </td>
            </tr>

        </table>
        </td></tr>
    </table>
    </body></html>';

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <' . $from_email . '>',
        'Reply-To: ' . $name . ' <' . $email . '>',
    );

    if ($is_local) {
        // LOCAL: no depende del email — ya quedó guardado en BD
        wp_mail($to, $subject, $body, $headers); // intento silencioso
        wp_send_json_success('¡Solicitud recibida! Te contactaremos en menos de 24 horas.');
    } else {
        // PROD: el éxito depende de que el email se envíe
        $sent = wp_mail($to, $subject, $body, $headers);
        if ($sent) {
            wp_send_json_success('¡Solicitud recibida! Te contactaremos en menos de 24 horas.');
        } else {
            wp_send_json_error('Error al enviar el mensaje. Por favor intenta de nuevo.');
        }
    }
}
add_action('wp_ajax_contact_form', 'handle_contact_form');
add_action('wp_ajax_nopriv_contact_form', 'handle_contact_form');

/**
 * Registrar CPT at_demo_request para guardar solicitudes del formulario
 */
function at_register_demo_request_cpt() {
    register_post_type('at_demo_request', array(
        'labels'       => array(
            'name'          => 'Solicitudes Demo',
            'singular_name' => 'Solicitud Demo',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'supports'     => array('title', 'custom-fields'),
        'menu_icon'    => 'dashicons-calendar-alt',
        'capability_type' => 'post',
    ));
}
add_action('init', 'at_register_demo_request_cpt');

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
        'default'           => '+56 9 2700 2984',
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
    $number = get_theme_mod('whatsapp_number', '+56 9 2700 2984');
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
 * Banner visual de ambiente LOCAL (solo se muestra en localhost)
 * Para diferenciar visualmente del entorno de producción.
 */
function at_local_environment_banner() {
    if (strpos(home_url(), 'localhost') === false && strpos(home_url(), '.local') === false) {
        return; // Solo en local
    }
    ?>
    <style>
        #at-local-banner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 999999;
            background: repeating-linear-gradient(
                45deg,
                #ff6b00,
                #ff6b00 10px,
                #e05500 10px,
                #e05500 20px
            );
            color: #fff;
            text-align: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            padding: 6px 0;
            text-transform: uppercase;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            pointer-events: none;
        }
        #at-local-banner span {
            background: rgba(0,0,0,0.35);
            padding: 2px 18px;
            border-radius: 3px;
        }
        /* Desplazar la página para que no tape contenido */
        html { margin-top: 32px !important; }
        /* Si el admin bar está activo, sumar al margen */
        html.wp-toolbar { margin-top: 32px !important; }
        #wpadminbar { top: 32px !important; }
    </style>
    <div id="at-local-banner">
        <span>⚠ AMBIENTE LOCAL — NO ES PRODUCCIÓN ⚠</span>
    </div>
    <?php
}
add_action('wp_head', 'at_local_environment_banner', 1);
add_action('admin_head', 'at_local_environment_banner', 1);
add_action('login_head', 'at_local_environment_banner', 1);

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
 * Incluir sistema de gestión de categorías de servicios
 */
require_once get_template_directory() . '/inc/service-categories-manager.php';

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

/**
 * Incluir módulo de generación de Boletas (Receipts)
 */
require_once get_template_directory() . '/inc/receipts-module.php';

/**
 * Incluir módulo de Detalles de Clientes (Seguimiento y Proyectos)
 */
require_once get_template_directory() . '/inc/client-details-module.php';

/**
 * Incluir módulo de Información Operativa de Clientes
 */
require_once get_template_directory() . '/inc/client-operations-module.php';

/**
 * Incluir Bóveda de Credenciales (accesos técnicos encriptados)
 */
require_once get_template_directory() . '/inc/credentials-vault-module.php';

/**
 * Incluir módulo de Chat IA
 */
require_once get_template_directory() . '/inc/chat-widget.php';

/**
 * Incluir Endpoints API personalizados
 */
require_once get_template_directory() . '/inc/api-endpoints.php';

/**
 * Incluir panel de recordatorios manuales
 */
require_once get_template_directory() . '/inc/admin-reminders.php';

/**
 * Incluir gestor de citas (CRUD)
 */
require_once get_template_directory() . '/inc/admin-leads-manager.php';

/**
 * Incluir panel de aprobación de propuestas
 */
require_once get_template_directory() . '/inc/admin-proposals.php';

/**
 * Incluir panel de reuniones de seguimiento
 */
require_once get_template_directory() . '/inc/admin-followup-meetings.php';
require_once get_template_directory() . '/inc/client-pdf-report.php';

/**
 * Incluir panel de monitoreo de errores N8N - ARGOS
 */
require_once get_template_directory() . '/inc/admin-n8n-errors.php';

/**
 * Panel de QA — Gestión de pruebas por proyecto/cliente
 * Casos de prueba, evidencias, aprobaciones y comentarios
 */
require_once get_template_directory() . '/inc/admin-qa-module.php';

/**
 * Encolar scripts específicos para el área de administración.
 */
function automatiza_tech_admin_scripts($hook) {
    // Solo cargar en la página de clientes
    if ('contactos_page_automatiza-tech-clients' !== $hook) {
        return;
    }

    // Usar la última hora de modificación del archivo como versión para evitar caché
    $script_path = get_template_directory() . '/assets/js/client-operations.js';
    $script_url = get_template_directory_uri() . '/assets/js/client-operations.js';
    $version = file_exists($script_path) ? filemtime($script_path) : '1.0';

    wp_enqueue_script(
        'automatiza-tech-client-operations',
        $script_url,
        array('jquery'),
        $version,
        true
    );

    wp_localize_script('automatiza-tech-client-operations', 'automatiza_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('regenerate_invoice_op_nonce') // Nonce específico
    ));
}
add_action('admin_enqueue_scripts', 'automatiza_tech_admin_scripts');

/**
 * =====================================================
 * PERSONALIZACIÓN DEL LOGIN DE WORDPRESS
 * Usa hooks oficiales de WP — no modifica ningún archivo core
 * =====================================================
 */

// 1. Estilos personalizados en la página de login
add_action('login_enqueue_scripts', function() {
    $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
    ?>
    <style>
    /* Fondo con gradiente corporativo AT */
    body.login {
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 40%, #134e4a 100%) !important;
    }
    body.login::before {
        content: '';
        position: fixed;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
        z-index: 0;
    }

    /* Logo personalizado */
    #login h1 a, .login h1 a {
        background-image: url('<?php echo esc_url($logo_url); ?>') !important;
        background-size: contain !important;
        background-position: center !important;
        background-repeat: no-repeat !important;
        width: 260px !important;
        height: 80px !important;
        display: block;
        text-indent: -9999px;
    }

    /* Caja del formulario */
    #loginform, #lostpasswordform, #registerform {
        background: rgba(255,255,255,0.97) !important;
        border-radius: 16px !important;
        border: none !important;
        box-shadow: 0 20px 60px rgba(0,0,0,0.25) !important;
        padding: 30px 30px 24px !important;
    }

    /* Labels */
    #loginform label, #lostpasswordform label {
        color: #374151 !important;
        font-weight: 600 !important;
        font-size: 13px !important;
    }

    /* Inputs */
    #loginform input[type=text],
    #loginform input[type=password],
    #lostpasswordform input[type=text] {
        border: 1.5px solid #d1d5db !important;
        border-radius: 8px !important;
        padding: 10px 12px !important;
        font-size: 14px !important;
        box-shadow: none !important;
        transition: border-color .2s !important;
    }
    #loginform input[type=text]:focus,
    #loginform input[type=password]:focus,
    #lostpasswordform input[type=text]:focus {
        border-color: #0d9488 !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(13,148,136,.15) !important;
    }

    /* Botón principal */
    .wp-core-ui .button-primary,
    #loginform .button-primary {
        background: linear-gradient(135deg, #0d9488, #14b8a6) !important;
        border: none !important;
        border-radius: 8px !important;
        padding: 10px 20px !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        letter-spacing: 0.3px !important;
        box-shadow: 0 4px 14px rgba(13,148,136,.35) !important;
        transition: all .2s !important;
        text-shadow: none !important;
    }
    .wp-core-ui .button-primary:hover {
        background: linear-gradient(135deg, #0f766e, #0d9488) !important;
        box-shadow: 0 6px 18px rgba(13,148,136,.45) !important;
        transform: translateY(-1px) !important;
    }

    /* Links */
    #nav a, #backtoblog a, .login #nav a, .login #backtoblog a {
        color: rgba(255,255,255,0.85) !important;
        text-decoration: none !important;
        font-size: 13px !important;
    }
    #nav a:hover, #backtoblog a:hover { color: #fff !important; text-decoration: underline !important; }

    /* Checkbox rememberme */
    #loginform .forgetmenot label { font-weight: 400 !important; color: #6b7280 !important; }

    /* Mensajes de error */
    #login_error {
        border-left-color: #ef4444 !important;
        color: #dc2626 !important;
        border-radius: 8px !important;
    }

    /* Contenedor centrado */
    #login { padding-top: 80px !important; }

    /* Footer login */
    .login #backtoblog { text-align: center; }

    /* Tag "Powered by" oculto */
    #login #backtoblog { margin-top: 12px; }
    </style>
    <?php
});

// 2. Redirigir el logo al sitio (no a wordpress.org)
add_filter('login_headerurl', function() {
    return home_url();
});

// 3. Texto alternativo del logo
add_filter('login_headertext', function() {
    return get_bloginfo('name') . ' — Panel de Administración';
});

// 4. Título de la página
add_filter('login_title', function($title) {
    return get_bloginfo('name') . ' — Acceso al Panel';
});

// 5. JS: bloquear botón al enviar el formulario
add_action('login_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('loginform');
        if (!form) return;
        form.addEventListener('submit', function() {
            var btn = document.getElementById('wp-submit');
            if (!btn) return;
            btn.disabled = true;
            btn.value = '⏳ Iniciando sesión...';
            btn.style.opacity = '0.75';
            btn.style.cursor = 'not-allowed';
        });
    });
    </script>
    <?php
});
