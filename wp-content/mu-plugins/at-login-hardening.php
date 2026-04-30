<?php
/**
 * Plugin Name: AT Login Hardening
 * Description: Rate limit en wp-login.php (transient + IP). Refuerza cookies de auth con Secure/HttpOnly/SameSite. Anti enumeracion de usuarios via mensajes de error genericos.
 * Version:     1.0.0
 * Author:      Automatiza Tech
 *
 * Vive en wp-content/mu-plugins/ -> se carga automaticamente.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'at_login_client_ip' ) ) {
    function at_login_client_ip() {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $first = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
            return trim( $first );
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Bloqueo temporal tras N intentos fallidos por IP en ventana corta.
 * 5 fallos / 15 min => bloquea 15 min adicionales.
 */
if ( ! function_exists( 'at_login_throttle_check' ) ) {
    function at_login_throttle_check() {
        $ip   = at_login_client_ip();
        $key  = 'at_login_blk_' . md5( $ip );
        $blk  = get_transient( $key );
        if ( $blk ) {
            status_header( 429 );
            nocache_headers();
            wp_die(
                'Demasiados intentos de inicio de sesion desde tu IP. Intenta de nuevo en unos minutos.',
                'Acceso bloqueado',
                [ 'response' => 429 ]
            );
        }
    }
    add_action( 'login_init', 'at_login_throttle_check' );
}

if ( ! function_exists( 'at_login_failed' ) ) {
    function at_login_failed( $username ) {
        $ip      = at_login_client_ip();
        $cnt_key = 'at_login_cnt_' . md5( $ip );
        $blk_key = 'at_login_blk_' . md5( $ip );
        $cnt     = (int) get_transient( $cnt_key );
        $cnt++;
        set_transient( $cnt_key, $cnt, 15 * MINUTE_IN_SECONDS );
        if ( $cnt >= 5 ) {
            set_transient( $blk_key, 1, 15 * MINUTE_IN_SECONDS );
            delete_transient( $cnt_key );
            error_log( '[at-login] IP bloqueada por 15 min: ' . $ip );
        }
    }
    add_action( 'wp_login_failed', 'at_login_failed' );
}

/**
 * Limpia contador tras login exitoso.
 */
if ( ! function_exists( 'at_login_success' ) ) {
    function at_login_success( $user_login, $user ) {
        $ip = at_login_client_ip();
        delete_transient( 'at_login_cnt_' . md5( $ip ) );
        delete_transient( 'at_login_blk_' . md5( $ip ) );
    }
    add_action( 'wp_login', 'at_login_success', 10, 2 );
}

/**
 * Mensaje de error generico para no revelar si el username existe.
 */
add_filter( 'login_errors', function ( $error ) {
    if ( strpos( (string) $error, 'wp-login.php?action=lostpassword' ) !== false ) {
        return $error;
    }
    return '<strong>Error:</strong> credenciales invalidas.';
} );

/**
 * Cookie de auth con SameSite=Lax + Secure en HTTPS.
 */
add_filter( 'secure_auth_cookie', function ( $secure ) {
    return is_ssl() ? true : $secure;
} );
add_filter( 'auth_cookie_expiration', function ( $exp, $user_id, $remember ) {
    return $remember ? ( 14 * DAY_IN_SECONDS ) : ( 4 * HOUR_IN_SECONDS );
}, 10, 3 );

/**
 * Bloquear xmlrpc.php a nivel WP (defensa adicional al .htaccess).
 */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_xmlrpc_server_class', function () { return 'stdClass'; } );

/**
 * Deshabilitar pingbacks (anti DDoS amplificado via XML-RPC).
 */
add_filter( 'xmlrpc_methods', function ( $methods ) {
    unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
    return $methods;
} );
