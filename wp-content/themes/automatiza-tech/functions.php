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
 * Obtener IP real del request (considera proxies comunes).
 */
function automatiza_tech_get_request_ip() {
    $candidates = array(
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    );

    foreach ($candidates as $raw_ip) {
        if ($raw_ip === '') {
            continue;
        }

        $ip = trim(explode(',', (string) $raw_ip)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

/**
 * Parsear correos opcionales en copia.
 *
 * Formatos soportados:
 * - correo1@dominio.com, correo2@dominio.com
 * - correo1@dominio.com, correo2@dominio.com / Nombre 1, Nombre 2
 */
function automatiza_tech_parse_optional_copy_emails($raw_input) {
    $raw = trim((string) $raw_input);
    if ($raw === '') {
        return array(
            'valid' => array(),
            'invalid' => array(),
            'names' => array(),
            'names_by_email' => array(),
        );
    }

    $emails_part = $raw;
    $names_part = '';
    if (strpos($raw, '/') !== false) {
        $split = explode('/', $raw, 2);
        $emails_part = trim($split[0]);
        $names_part = trim($split[1]);
    }

    $parts = preg_split('/[\s,;]+/', $emails_part, -1, PREG_SPLIT_NO_EMPTY);
    $name_parts = array();
    if ($names_part !== '') {
        $name_parts = preg_split('/\s*[;,]\s*|\r\n|\r|\n/', $names_part, -1, PREG_SPLIT_NO_EMPTY);
        $name_parts = array_map('trim', $name_parts);
    }

    $valid = array();
    $invalid = array();
    $names_by_email = array();

    foreach ($parts as $index => $part) {
        $email = sanitize_email(trim($part));
        $display_name = isset($name_parts[$index]) ? sanitize_text_field($name_parts[$index]) : '';

        if ($email !== '' && is_email($email)) {
            $email_key = strtolower($email);
            if (!in_array($email_key, $valid, true)) {
                $valid[] = $email_key;
            }
            if ($display_name !== '' && !isset($names_by_email[$email_key])) {
                $names_by_email[$email_key] = $display_name;
            }
        } else {
            $invalid[] = trim($part);
        }
    }

    $ordered_names = array();
    foreach ($valid as $email_key) {
        $ordered_names[] = isset($names_by_email[$email_key]) ? $names_by_email[$email_key] : '';
    }

    return array(
        'valid' => array_values(array_unique($valid)),
        'invalid' => array_values(array_unique($invalid)),
        'names' => $ordered_names,
        'names_by_email' => $names_by_email,
    );
}

/**
 * Formatear lista de invitados para despliegue humano.
 */
function automatiza_tech_format_invitees_for_humans($invitees, $invitees_names_by_email = array()) {
    $items = array();
    foreach ((array) $invitees as $invitee_email) {
        $email = strtolower((string) $invitee_email);
        $name = trim((string) ($invitees_names_by_email[$email] ?? ''));
        if ($name !== '') {
            $items[] = $name . ' <' . $email . '>';
        } else {
            $items[] = $email;
        }
    }
    return implode(', ', $items);
}

/**
 * Notificar al titular cuando se agregan correos en copia en un agendamiento demo.
 */
function automatiza_tech_send_demo_copy_added_notification($primary_email, $primary_name, $invitees, $scheduled_date = '', $scheduled_time = '', $invitees_names_by_email = array()) {
    $to = sanitize_email($primary_email);
    if ($to === '' || !is_email($to) || empty($invitees)) {
        return false;
    }

    $site_title = get_bloginfo('name');
    $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $website_url = home_url('/');
    $whatsapp_url = 'https://wa.me/56927002984';
    $contact_email = 'contacto@automatizatech.cl';
    $contact_phone = '+56 9 2700 2984';

    $recipient_name = $primary_name !== '' ? $primary_name : 'Cliente';

    $invitees_lines = array();
    foreach ($invitees as $invitee_email) {
        $normalized_email = strtolower(sanitize_email($invitee_email));
        if ($normalized_email === '' || !is_email($normalized_email)) {
            continue;
        }

        $display_name = trim((string) ($invitees_names_by_email[$normalized_email] ?? ''));
        if ($display_name !== '') {
            $invitees_lines[] = $display_name . ' <' . $normalized_email . '>';
        } else {
            $invitees_lines[] = $normalized_email;
        }
    }

    if (empty($invitees_lines)) {
        $invitees_lines[] = automatiza_tech_format_invitees_for_humans($invitees, $invitees_names_by_email);
    }

    $invitees_list_html = '';
    foreach ($invitees_lines as $line) {
        $invitees_list_html .= '<li style="margin:0 0 8px 0; color:#334155;">' . esc_html($line) . '</li>';
    }

    $scheduled_date_text = '';
    if ($scheduled_date !== '') {
        $date_ts = strtotime($scheduled_date);
        if ($date_ts !== false) {
            $scheduled_date_text = date('d/m/Y', $date_ts);
        }
    }

    $scheduled_time_text = '';
    if ($scheduled_time !== '') {
        $scheduled_time_text = substr($scheduled_time, 0, 5) . ' hrs';
    }

    $date_time_details = '';
    if ($scheduled_date_text !== '' || $scheduled_time_text !== '') {
        $date_time_details = '
            <div style="background:linear-gradient(135deg,#f0fdfa,#ccfbf1); border:1px solid #99f6e4; border-radius:10px; padding:14px 16px; margin:18px 0 0 0;">
                <p style="margin:0 0 6px 0; font-size:14px; color:#0f766e;"><strong>📅 Fecha:</strong> ' . esc_html($scheduled_date_text !== '' ? $scheduled_date_text : 'Por confirmar') . '</p>
                <p style="margin:0; font-size:14px; color:#0f766e;"><strong>🕐 Hora:</strong> ' . esc_html($scheduled_time_text !== '' ? $scheduled_time_text : 'Por confirmar') . '</p>
            </div>';
    }

    $subject = '👥 Actualización de tu demo: se agregaron participantes | ' . $site_title;
    $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; background-color:#f0fdfa; margin:0; padding:0; color:#333;">
    <div style="max-width:600px; margin:20px auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 40px rgba(13,148,136,0.15);">
        <div style="background:linear-gradient(135deg,#0d9488,#14b8a6,#2dd4bf); padding:40px 20px; text-align:center;">
            <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '" style="max-height:60px; width:auto; margin-bottom:12px; filter:brightness(0) invert(1);">
            <h1 style="margin:0; color:#ffffff; font-size:24px; letter-spacing:0.5px;">Actualización de tu Demo</h1>
            <p style="margin:10px 0 0 0; color:rgba(255,255,255,0.92); font-size:14px;">Se agregaron nuevos participantes a tu reunión</p>
        </div>

        <div style="padding:32px 30px;">
            <p style="font-size:16px; margin:0 0 14px 0; color:#0f766e;">Hola <strong>' . esc_html($recipient_name) . '</strong>,</p>
            <p style="font-size:15px; margin:0 0 16px 0; color:#334155;">Confirmamos que se agregaron los siguientes correos en copia para tu demo:</p>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #14b8a6; border-radius:10px; padding:16px;">
                <ul style="margin:0; padding-left:18px; font-size:14px;">
                    ' . $invitees_list_html . '
                </ul>
            </div>

            ' . $date_time_details . '

            <div style="margin:20px 0 0 0; background:#fffbeb; border-left:4px solid #f59e0b; border-radius:8px; padding:12px 14px;">
                <p style="margin:0; font-size:13px; color:#92400e;">Este aviso es informativo. Si necesitas cambiar participantes o reagendar, escríbenos por WhatsApp.</p>
            </div>

            <div style="text-align:center; margin:26px 0 6px 0;">
                <a href="' . esc_url($whatsapp_url) . '" style="display:inline-block; background:linear-gradient(135deg,#25D366,#20bd5a); color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:999px; font-size:14px; font-weight:700;">💬 Contactar por WhatsApp</a>
            </div>
        </div>

        <div style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:20px; text-align:center;">
            <p style="margin:0 0 8px 0; font-size:12px; color:#64748b;">' . esc_html($site_title) . ' · ' . esc_html($contact_phone) . '</p>
            <p style="margin:0 0 8px 0; font-size:12px;"><a href="mailto:' . esc_attr($contact_email) . '" style="color:#0d9488; text-decoration:none;">' . esc_html($contact_email) . '</a></p>
            <p style="margin:0; font-size:12px;"><a href="' . esc_url($website_url) . '" style="color:#0d9488; text-decoration:none;">' . esc_html($website_url) . '</a></p>
        </div>
    </div>
</body>
</html>';

    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <' . $from_email . '>',
        'Reply-To: ' . $contact_email,
    );

    return wp_mail($to, $subject, $html, $headers);
}

/**
 * Enviar invitación por correo a participantes agregados en copia para demos.
 */
function automatiza_tech_send_demo_participant_invitations($primary_name, $primary_email, $invitees, $scheduled_date = '', $scheduled_time = '', $invitees_names_by_email = array()) {
    if (empty($invitees)) {
        return false;
    }

    $site_title = get_bloginfo('name');
    $logo_url = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Automatiza Tech <' . $from_email . '>',
    );

    $date_text = $scheduled_date !== '' ? date('d/m/Y', strtotime($scheduled_date)) : 'Por confirmar';
    $time_text = $scheduled_time !== '' ? substr($scheduled_time, 0, 5) . ' hrs' : 'Por confirmar';
    $owner_name = $primary_name !== '' ? $primary_name : 'Cliente';
    $owner_email = sanitize_email($primary_email);

    $sent_any = false;
    foreach ($invitees as $invitee_email) {
        $to = sanitize_email($invitee_email);
        if ($to === '' || !is_email($to)) {
            continue;
        }

        $recipient_name = trim((string) ($invitees_names_by_email[strtolower($to)] ?? ''));
        if ($recipient_name === '') {
            $recipient_name = 'Participante';
        }

        $subject = '📅 Invitación a demo | ' . $site_title;
        $html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="font-family: Arial, sans-serif; background:#f0f4ff; margin:0; padding:20px; color:#1e293b;">
    <div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 8px 25px rgba(30,58,138,0.12);">
        <div style="background:linear-gradient(135deg,#1e3a8a,#0d2044); padding:26px 22px; text-align:center;">
            <img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_title) . '" style="max-height:56px; width:auto; margin-bottom:10px;">
            <h1 style="margin:0; color:#fff; font-size:21px;">Invitación a Demo</h1>
        </div>
        <div style="padding:24px;">
            <p style="font-size:15px; margin:0 0 12px 0;">Hola <strong>' . esc_html($recipient_name) . '</strong>,</p>
            <p style="font-size:15px; margin:0 0 12px 0;">Fuiste agregado como participante a una demo en Automatiza Tech.</p>
            <p style="font-size:14px; color:#334155; margin:0 0 8px 0;"><strong>Fecha:</strong> ' . esc_html($date_text) . '</p>
            <p style="font-size:14px; color:#334155; margin:0 0 8px 0;"><strong>Hora:</strong> ' . esc_html($time_text) . '</p>
            <p style="font-size:14px; color:#334155; margin:0 0 8px 0;"><strong>Titular:</strong> ' . esc_html($owner_name) . '</p>
            ' . ($owner_email !== '' ? '<p style="font-size:14px; color:#334155; margin:0 0 12px 0;"><strong>Email titular:</strong> ' . esc_html($owner_email) . '</p>' : '') . '
            <p style="font-size:13px; color:#64748b; margin:0;">Si necesitas coordinación adicional, puedes responder este correo.</p>
        </div>
    </div>
</body>
</html>';

        if (wp_mail($to, $subject, $html, $headers)) {
            $sent_any = true;
        }
    }

    return $sent_any;
}

/**
 * Manejar formulario de contacto
 *
 * LOCAL  → guarda en BD, retorna éxito (sin SMTP disponible)
 * PROD   → guarda en BD + envía email, retorna éxito solo si el mail se envió
 */
function handle_contact_form() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error('Método no permitido');
        return;
    }

    if (empty($_POST['nonce'])) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'automatiza_tech_nonce')) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    $request_data = wp_unslash($_POST);

    // Honeypot simple anti-bot (debe llegar vacío).
    $honeypot = sanitize_text_field($request_data['website'] ?? '');
    if ($honeypot !== '') {
        wp_send_json_error('Solicitud inválida');
        return;
    }

    // Rate limiting por IP para proteger endpoint público.
    $ip = automatiza_tech_get_request_ip();
    $rate_key = 'at_contact_form_rl_' . md5($ip);
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 8) {
        wp_send_json_error('Demasiadas solicitudes. Intenta nuevamente en unos minutos.');
        return;
    }
    set_transient($rate_key, $attempts + 1, 10 * MINUTE_IN_SECONDS);

    // Sanitizar datos
    $name    = sanitize_text_field($request_data['name'] ?? '');
    $email   = sanitize_email($request_data['email'] ?? '');
    $company = sanitize_text_field($request_data['company'] ?? '');
    $phone   = sanitize_text_field($request_data['phone'] ?? '');
    $message = sanitize_textarea_field($request_data['message'] ?? '');
    $invitees_raw = sanitize_text_field($request_data['invitees_emails'] ?? '');
    $invitees_parsed = automatiza_tech_parse_optional_copy_emails($invitees_raw);
    $invitees_valid = $invitees_parsed['valid'];
    $invitees_names = $invitees_parsed['names'] ?? array();
    $invitees_names_by_email = $invitees_parsed['names_by_email'] ?? array();

    if (!empty($invitees_parsed['invalid'])) {
        wp_send_json_error('Correos en copia inválidos: ' . implode(', ', $invitees_parsed['invalid']));
        return;
    }

    // Límites de longitud para endurecer el endpoint.
    if ($name === '' || strlen($name) > 80) {
        wp_send_json_error('Nombre inválido.');
        return;
    }
    if ($email === '' || strlen($email) > 100) {
        wp_send_json_error('Email inválido.');
        return;
    }
    if (strlen($company) > 120) {
        wp_send_json_error('Empresa inválida.');
        return;
    }
    if (strlen($message) > 2000) {
        wp_send_json_error('Mensaje demasiado largo.');
        return;
    }
    if (strlen($invitees_raw) > 500) {
        wp_send_json_error('El campo de correos en copia es demasiado largo.');
        return;
    }

    // Validar teléfono chileno: exactamente 9 dígitos y comenzando con 9
    $phone_digits = preg_replace('/\D+/', '', $phone);
    $phone_local = (strpos($phone_digits, '56') === 0) ? substr($phone_digits, 2) : $phone_digits;
    if (!preg_match('/^9\d{8}$/', $phone_local)) {
        wp_send_json_error('Teléfono no válido. Debe tener 9 dígitos y comenzar con 9.');
        return;
    }
    $phone = '+56' . $phone_local;

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
            '_at_invitees_emails' => !empty($invitees_valid) ? implode(',', $invitees_valid) : '',
            '_at_invitees_names' => !empty($invitees_names) ? implode(',', $invitees_names) : '',
            '_at_company' => $company,
            '_at_phone'   => $phone,
            '_at_message' => $message,
            '_at_date'    => current_time('mysql'),
            '_at_env'     => $is_local ? 'local' : 'production',
            '_at_source'  => 'contact_form',
        ),
    ));

		$fecha_demo = sanitize_text_field($request_data['date'] ?? '');
		$hora_demo  = sanitize_text_field($request_data['time'] ?? '');
    if (empty($fecha_demo) && preg_match('/Fecha:\s*([^\s]+)/', $message, $mf)) {
        $fecha_demo = sanitize_text_field($mf[1]);
    }
    if (empty($hora_demo) && preg_match('/Hora:\s*([^\s]+)/', $message, $mh)) {
        $hora_demo = sanitize_text_field($mh[1]);
    }

    // Normalizar fecha a Y-m-d si llega como d/m/Y
    $fecha_db = '';
    if ($fecha_demo !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_demo)) {
            $fecha_db = $fecha_demo;
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_demo, $mdate)) {
            $fecha_db = $mdate[3] . '-' . $mdate[2] . '-' . $mdate[1];
        }
    }

    if ($fecha_demo !== '' && $fecha_db === '') {
        wp_send_json_error('Formato de fecha inválido.');
        return;
    }

    // Normalizar hora a HH:MM:SS
    $hora_db = '';
    if ($hora_demo !== '') {
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora_demo)) {
            $hora_db = $hora_demo;
        } elseif (preg_match('/^\d{2}:\d{2}$/', $hora_demo)) {
            $hora_db = $hora_demo . ':00';
        }
    }

    if ($hora_demo !== '' && $hora_db === '') {
        wp_send_json_error('Formato de hora inválido.');
        return;
    }

    if ($fecha_db !== '' && strtotime($fecha_db) === false) {
        wp_send_json_error('Fecha inválida.');
        return;
    }

    if ($hora_db !== '' && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora_db)) {
        wp_send_json_error('Hora inválida.');
        return;
    }

    // Validación de disponibilidad real (misma regla del bot/check-availability).
    if ($fecha_db !== '' && $hora_db !== '') {
        $slot_hhmm = substr($hora_db, 0, 5);

        if (!preg_match('/^\d{2}:00$/', $slot_hhmm)) {
            wp_send_json_error('Hora inválida. Debe ser una hora en punto.');
            return;
        }

        if (!function_exists('automatiza_tech_check_availability')) {
            error_log('handle_contact_form: automatiza_tech_check_availability no está disponible');
            wp_send_json_error('No se pudo validar disponibilidad. Intenta nuevamente.');
            return;
        }

        $availability_req = new WP_REST_Request('POST', '/automatiza-tech/v1/check-availability');
        $availability_req->set_header('content-type', 'application/json');
        $availability_req->set_body(wp_json_encode(array('date' => $fecha_db)));
        $availability = automatiza_tech_check_availability($availability_req);

        if (is_wp_error($availability)) {
            wp_send_json_error($availability->get_error_message());
            return;
        }

        $working_hours = is_array($availability['workingHours'] ?? null) ? $availability['workingHours'] : array();
        $busy_slots = is_array($availability['busySlots'] ?? null) ? $availability['busySlots'] : array();

        $start = sanitize_text_field($working_hours['start'] ?? '');
        $end = sanitize_text_field($working_hours['end'] ?? '');

        if ($start === '' || $end === '') {
            wp_send_json_error('No hay horario de atención disponible para la fecha seleccionada.');
            return;
        }

        if ($slot_hhmm < $start || $slot_hhmm >= $end) {
            wp_send_json_error('La hora seleccionada está fuera del horario de atención.');
            return;
        }

        if (in_array($slot_hhmm, $busy_slots, true)) {
            wp_send_json_error('El horario seleccionado ya no está disponible. Elige otro horario.');
            return;
        }

        if ($fecha_db === current_time('Y-m-d')) {
            $now_hhmm = current_time('H:i');
            if ($slot_hhmm <= $now_hhmm) {
                wp_send_json_error('La hora seleccionada ya pasó. Elige un horario futuro.');
                return;
            }
        }
    }

    // Persistir también en wp_automatiza_leads para que aparezca en Admin > Citas Activas
    global $wpdb;
    $leads_table = $wpdb->prefix . 'automatiza_leads';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $leads_table));
    if ($table_exists === $leads_table) {
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$leads_table}", 0);
        $lead_data = array();

        if (in_array('created_at', $columns, true)) {
            $lead_data['created_at'] = current_time('mysql');
        }
        if (in_array('name', $columns, true)) {
            $lead_data['name'] = $name;
        }
        if (in_array('email', $columns, true)) {
            $lead_data['email'] = $email;
        }
        if (in_array('phone', $columns, true)) {
            $lead_data['phone'] = $phone;
        }
        if (in_array('session_id', $columns, true)) {
            $lead_data['session_id'] = 'modal_promo';
        }
        if (in_array('source', $columns, true)) {
            $lead_data['source'] = 'web';
        }
        if (in_array('status', $columns, true)) {
            $lead_data['status'] = 'scheduled';
        }
        if (in_array('invitees_emails', $columns, true) && !empty($invitees_valid)) {
            $lead_data['invitees_emails'] = implode(',', $invitees_valid);
        }
        if (in_array('invitees_names', $columns, true) && !empty($invitees_names)) {
            $lead_data['invitees_names'] = implode(',', $invitees_names);
        }
        if (in_array('copied_emails', $columns, true) && !empty($invitees_valid)) {
            $lead_data['copied_emails'] = implode(',', $invitees_valid);
        }
        if (in_array('token', $columns, true)) {
            $lead_data['token'] = bin2hex(random_bytes(16));
        }
        if (in_array('scheduled_date', $columns, true) && $fecha_db !== '') {
            $lead_data['scheduled_date'] = $fecha_db;
        }
        if (in_array('scheduled_time', $columns, true) && $hora_db !== '') {
            $lead_data['scheduled_time'] = $hora_db;
        }
        if (in_array('notes', $columns, true) && $message !== '') {
            $lead_data['notes'] = $message;
        }

        if (!empty($lead_data)) {
            $inserted = $wpdb->insert($leads_table, $lead_data);
            if ($inserted === false) {
                error_log('handle_contact_form: error al insertar en automatiza_leads -> ' . $wpdb->last_error);
            }
        }
    }

    $fecha_hora_row = '';
    if ($fecha_demo || $hora_demo) {
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

    $invitees_row = '';
    if (!empty($invitees_valid)) {
        $invitees_pretty = automatiza_tech_format_invitees_for_humans($invitees_valid, $invitees_names_by_email);
        $invitees_row = '
            <tr>
                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">
                    <strong style="color:#1e3a8a;">👥 Correos en copia:</strong>
                </td>
                <td style="padding:8px 0;border-bottom:1px solid #e0e8f0;">' . esc_html($invitees_pretty) . '</td>
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
                            ' . $invitees_row . '
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

    if (!empty($invitees_valid)) {
        automatiza_tech_send_demo_copy_added_notification($email, $name, $invitees_valid, $fecha_db, $hora_db, $invitees_names_by_email);
        automatiza_tech_send_demo_participant_invitations($name, $email, $invitees_valid, $fecha_db, $hora_db, $invitees_names_by_email);
    }

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
 * Notificación corporativa liviana para demos agendadas desde flujos externos (chatbot/N8N).
 * No guarda en BD; solo envía correo interno al equipo.
 */
function automatiza_tech_send_corporate_demo_notification() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error('Método no permitido');
        return;
    }

    if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'automatiza_tech_nonce')) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    $request_data = wp_unslash($_POST);

    $name = sanitize_text_field($request_data['name'] ?? '');
    $email = sanitize_email($request_data['email'] ?? '');
    $phone = sanitize_text_field($request_data['phone'] ?? '');
    $company = sanitize_text_field($request_data['company'] ?? 'Demo — Modal Promocional');
    $date = sanitize_text_field($request_data['date'] ?? '');
    $time = sanitize_text_field($request_data['time'] ?? '');

    if ($name === '' || !is_email($email)) {
        wp_send_json_error('Datos inválidos para enviar notificación corporativa.');
        return;
    }

    $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
    $to = 'automatizatech.bots@gmail.com';
    $subject = '🗓️ Nueva solicitud de demo — ' . $name;

    $date_text = $date !== '' ? $date : 'No especificada';
    $time_text = $time !== '' ? $time : 'No especificada';
    $phone_text = $phone !== '' ? $phone : 'No especificado';
    $company_text = $company !== '' ? $company : 'No especificada';

    $body = '
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
    <body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;background:#f0f4ff;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4ff;padding:24px;">
        <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(30,58,138,0.12);">
            <tr>
                <td style="background:linear-gradient(135deg,#1e3a8a 0%,#0d2d45 60%,#062a2a 100%);padding:28px 30px;text-align:center;">
                    <h1 style="color:#ffffff;margin:0;font-size:22px;letter-spacing:0.5px;">🗓️ Nueva Solicitud de Demo</h1>
                    <p style="color:#06d6a0;margin:8px 0 0 0;font-size:14px;font-weight:600;">AutomatizaTech · Notificación Corporativa</p>
                </td>
            </tr>
            <tr>
                <td style="padding:28px 30px;">
                    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;">
                        <tr><td style="border-bottom:1px solid #e0e8f0;width:34%;"><strong>Nombre:</strong></td><td style="border-bottom:1px solid #e0e8f0;">' . esc_html($name) . '</td></tr>
                        <tr><td style="border-bottom:1px solid #e0e8f0;"><strong>Email:</strong></td><td style="border-bottom:1px solid #e0e8f0;">' . esc_html($email) . '</td></tr>
                        <tr><td style="border-bottom:1px solid #e0e8f0;"><strong>Teléfono:</strong></td><td style="border-bottom:1px solid #e0e8f0;">' . esc_html($phone_text) . '</td></tr>
                        <tr><td style="border-bottom:1px solid #e0e8f0;"><strong>Empresa:</strong></td><td style="border-bottom:1px solid #e0e8f0;">' . esc_html($company_text) . '</td></tr>
                        <tr><td style="border-bottom:1px solid #e0e8f0;"><strong>Fecha demo:</strong></td><td style="border-bottom:1px solid #e0e8f0;">' . esc_html($date_text) . '</td></tr>
                        <tr><td style="border-bottom:1px solid #e0e8f0;"><strong>Hora demo:</strong></td><td style="border-bottom:1px solid #e0e8f0;">' . esc_html($time_text) . '</td></tr>
                        <tr><td><strong>Origen:</strong></td><td>Modal promo + flujo chatbot/N8N</td></tr>
                    </table>
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

    $sent = wp_mail($to, $subject, $body, $headers);

    if (!$sent) {
        wp_send_json_error('No se pudo enviar la notificación corporativa.');
        return;
    }

    wp_send_json_success('Notificación corporativa enviada.');
}
add_action('wp_ajax_send_corporate_demo_notification', 'automatiza_tech_send_corporate_demo_notification');
add_action('wp_ajax_nopriv_send_corporate_demo_notification', 'automatiza_tech_send_corporate_demo_notification');

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
 * Home premium AT: SEO tecnico + atribucion de marketing por lead_id.
 * El agendamiento usa el pipeline canonico REST /leads.
 */
require_once get_template_directory() . '/inc/home-premium-backend.php';

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
 * Incluir endpoint REST de prompts de propuestas (skill at-proposal-refiner)
 */
require_once get_template_directory() . '/inc/rest-proposals.php';

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
 * Módulo de Contratos con Doble Firma (AT + Cliente)
 * - Admin page: Contactos > Contratos
 * - Widget en ficha del cliente (pestaña "📜 Contratos")
 */
if (file_exists(ABSPATH . 'contracts/admin-contracts.php')) {
    require_once ABSPATH . 'contracts/admin-contracts.php';
}
if (file_exists(ABSPATH . 'contracts/client-contracts-widget.php')) {
    require_once ABSPATH . 'contracts/client-contracts-widget.php';
}

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
