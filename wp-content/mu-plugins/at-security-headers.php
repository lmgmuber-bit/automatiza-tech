<?php
/**
 * Plugin Name: AT Security Headers
 * Description: Headers de seguridad globales y endurecimiento basico de cabeceras (HSTS, XFO, nosniff, Referrer-Policy, Permissions-Policy, X-XSS, CSP en modo Report-Only).
 * Version:     1.0.0
 * Author:      Automatiza Tech
 *
 * Vive en wp-content/mu-plugins/ -> se carga automaticamente, no se puede desactivar
 * desde el panel WP. Defensa adicional en caso de que el bloque AT_HARDENING en
 * .htaccess no este aplicado (por ejemplo si el .htaccess es sobrescrito).
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('at_security_send_headers')) {
    function at_security_send_headers() {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        header('X-XSS-Protection: 0');

        // Quitar fingerprinting de PHP/servidor.
        header_remove('X-Powered-By');
        @ini_set('expose_php', '0');

        $env = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production';

        // HSTS: solo en produccion HTTPS. 6 meses con includeSubDomains.
        // Cuando estes 100% seguro y quieras preload list, sube max-age a 31536000 y agrega "preload".
        if ($env === 'production' && is_ssl()) {
            header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
        }

        // CSP en enforcement. Las directivas permiten unsafe-inline/unsafe-eval para
        // compatibilidad con widgets y código inline existente. Suficiente para bloquear
        // XSS externo sin romper funcionalidad. Revisar en Phase 2 para eliminar unsafe-*.
        $csp = "default-src 'self' https: data: blob:; "
             . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; "
             . "style-src  'self' 'unsafe-inline' https:; "
             . "img-src    'self' data: blob: https:; "
             . "font-src   'self' data: https:; "
             . "connect-src 'self' https: wss:; "
             . "frame-src  'self' https:; "
             . "object-src 'none'; "
             . "base-uri   'self'; "
             . "form-action 'self' https:;";
        header('Content-Security-Policy: ' . $csp);
    }
    // Prioridad temprana para asegurar el envio antes que cualquier output.
    add_action('send_headers', 'at_security_send_headers', 1);
}

/**
 * Cookies endurecidas: Secure + HttpOnly + SameSite=Lax para sesiones.
 * Aplicado solo en produccion sobre HTTPS para no romper desarrollo local.
 */
if (!function_exists('at_security_init_cookies')) {
    function at_security_init_cookies() {
        $env = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production';
        if ($env !== 'production' || !is_ssl()) {
            return;
        }
        $params = session_get_cookie_params();
        @session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    add_action('init', 'at_security_init_cookies', 0);
}

/**
 * Quitar version de WP del HTML (reduce fingerprinting).
 */
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

/**
 * Deshabilitar listado de usuarios via REST API publica (/wp-json/wp/v2/users).
 * Solo usuarios autenticados pueden listar.
 */
add_filter('rest_authentication_errors', function ($result) {
    if (!empty($result)) {
        return $result;
    }
    if (!is_user_logged_in()) {
        $route = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (strpos($route, '/wp/v2/users') !== false) {
            return new WP_Error(
                'rest_forbidden',
                'Listado de usuarios no permitido sin autenticacion.',
                ['status' => 401]
            );
        }
    }
    return $result;
});
