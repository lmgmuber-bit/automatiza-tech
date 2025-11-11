<?php
/**
 * Configuración SMTP para Hostinger
 * Asegura que los correos se envíen correctamente en producción
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configurar SMTP para envío de correos en producción
 */
function automatiza_tech_smtp_config($phpmailer) {
    // Solo aplicar en producción (Hostinger)
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
        return; // No aplicar en local
    }
    
    $phpmailer->isSMTP();
    $phpmailer->Host       = 'smtp.hostinger.com';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = 587; // Puerto TLS
    $phpmailer->SMTPSecure = 'tls';
    
    // Credenciales SMTP - DEBE estar definido en wp-config.php
    if (defined('SMTP_USER') && defined('SMTP_PASS')) {
        $phpmailer->Username = SMTP_USER;
        $phpmailer->Password = SMTP_PASS;
        $phpmailer->From     = SMTP_USER; // Usar el mismo correo SMTP
        $phpmailer->FromName = 'Automatiza Tech';
    } else {
        // Si no hay credenciales, registrar error
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log('SMTP ERROR: SMTP_USER y SMTP_PASS no están definidos en wp-config.php');
        }
        return; // No continuar sin credenciales
    }
    
    // Reply-To
    $phpmailer->addReplyTo(SMTP_USER, 'Automatiza Tech');
    
    // Configuración adicional
    $phpmailer->CharSet = 'UTF-8';
    $phpmailer->Encoding = 'base64';
    
    // Debug en desarrollo (comentar en producción)
    // $phpmailer->SMTPDebug = 2;
    // $phpmailer->Debugoutput = 'html';
}

// Hook para configurar SMTP
add_action('phpmailer_init', 'automatiza_tech_smtp_config');

/**
 * Configurar el remitente por defecto
 */
function automatiza_tech_mail_from($email) {
    // Solo cambiar si no es de WordPress
    if (strpos($email, 'wordpress@') === 0) {
        return get_option('admin_email');
    }
    return $email;
}
add_filter('wp_mail_from', 'automatiza_tech_mail_from');

/**
 * Configurar el nombre del remitente por defecto
 */
function automatiza_tech_mail_from_name($name) {
    // Solo cambiar si es el nombre por defecto de WordPress
    if ($name === 'WordPress') {
        return 'Automatiza Tech';
    }
    return $name;
}
add_filter('wp_mail_from_name', 'automatiza_tech_mail_from_name');

/**
 * Forzar HTML en correos
 */
function automatiza_tech_mail_content_type() {
    return 'text/html';
}
add_filter('wp_mail_content_type', 'automatiza_tech_mail_content_type');

/**
 * Test de envío de correo (función auxiliar para administradores)
 */
function automatiza_tech_test_email() {
    // Solo accesible para administradores
    if (!current_user_can('administrator')) {
        return;
    }
    
    // Verificar si se solicitó test de email
    if (isset($_GET['test_email']) && $_GET['test_email'] === 'send' && 
        isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'test_email')) {
        
        $to = get_option('admin_email');
        $subject = '✅ Test de correo - Automatiza Tech';
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5;">
            <div style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="color: #667eea; margin-top: 0;">🎉 ¡Test de Correo Exitoso!</h2>
                <p>Este es un correo de prueba del sistema de Automatiza Tech.</p>
                <p><strong>Fecha:</strong> ' . current_time('d/m/Y H:i:s') . '</p>
                <p><strong>Servidor:</strong> ' . $_SERVER['HTTP_HOST'] . '</p>
                <p><strong>IP:</strong> ' . $_SERVER['SERVER_ADDR'] . '</p>
                <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                <p style="color: #666; font-size: 14px;">Si recibes este correo, significa que el sistema de envío de correos está funcionando correctamente. ✅</p>
            </div>
        </body>
        </html>
        ';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Correo de prueba enviado exitosamente!</strong> Revisa tu bandeja de entrada.</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p><strong>❌ Error al enviar correo de prueba.</strong> Verifica la configuración SMTP.</p></div>';
            });
        }
    }
}
add_action('admin_init', 'automatiza_tech_test_email');

/**
 * Agregar botón de test de email en el panel de contactos
 */
function automatiza_tech_add_test_email_button() {
    $screen = get_current_screen();
    
    if ($screen && $screen->id === 'automatiza-tech_page_automatiza-tech-contacts') {
        $nonce = wp_create_nonce('test_email');
        $test_url = admin_url('admin.php?page=automatiza-tech-contacts&test_email=send&_wpnonce=' . $nonce);
        
        echo '<style>
            .test-email-button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                color: white !important;
                border: none !important;
                padding: 8px 16px !important;
                border-radius: 6px !important;
                text-decoration: none !important;
                display: inline-block !important;
                margin-left: 10px !important;
                font-weight: 600 !important;
                box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3) !important;
                transition: all 0.3s ease !important;
            }
            .test-email-button:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5) !important;
            }
        </style>
        <script>
            jQuery(document).ready(function($) {
                if ($(".send-email-new-contacts").length) {
                    $(".send-email-new-contacts").after(
                        \'<a href="' . $test_url . '" class="button test-email-button">📧 Test de Correo</a>\'
                    );
                }
            });
        </script>';
    }
}
add_action('admin_footer', 'automatiza_tech_add_test_email_button');

/**
 * Logging de errores de email
 */
function automatiza_tech_log_email_errors($wp_error) {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log('Automatiza Tech - Error de email: ' . print_r($wp_error, true));
    }
}
add_action('wp_mail_failed', 'automatiza_tech_log_email_errors');
