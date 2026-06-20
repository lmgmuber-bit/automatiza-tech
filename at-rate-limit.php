<?php
/**
 * AT Rate Limit Helper
 *
 * Limitador de tasa basado en transients de WordPress + IP cliente.
 * Ligero, sin dependencias externas. Apto para endpoints publicos
 * (login, webhooks, formularios, validar-factura, etc).
 *
 * Uso:
 *   require_once __DIR__ . '/at-rate-limit.php';
 *   if ( ! at_rate_limit_check( 'contact_form', 10, 60 ) ) {
 *       http_response_code( 429 );
 *       header( 'Retry-After: 60' );
 *       exit( wp_json_encode( [ 'error' => 'Demasiadas solicitudes' ] ) );
 *   }
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'at_rate_limit_client_ip' ) ) {
    function at_rate_limit_client_ip() {
        $candidates = [];
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            foreach ( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) as $ip ) {
                $candidates[] = trim( $ip );
            }
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $candidates[] = $_SERVER['REMOTE_ADDR'];
        }
        foreach ( $candidates as $ip ) {
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
        return '0.0.0.0';
    }
}

if ( ! function_exists( 'at_rate_limit_check' ) ) {
    /**
     * Devuelve true si la peticion esta dentro del limite.
     * Devuelve false si se debe rechazar.
     *
     * @param string $bucket    Nombre del endpoint (slug corto).
     * @param int    $max       Maximo de hits permitidos en la ventana.
     * @param int    $window    Ventana en segundos.
     * @param string $extra_key Identificador opcional adicional (ej: email).
     */
    function at_rate_limit_check( $bucket, $max = 30, $window = 60, $extra_key = '' ) {
        $ip   = at_rate_limit_client_ip();
        $key  = 'at_rl_' . md5( $bucket . '|' . $ip . '|' . $extra_key );
        $hits = (int) get_transient( $key );
        if ( $hits >= $max ) {
            return false;
        }
        set_transient( $key, $hits + 1, $window );
        return true;
    }
}

if ( ! function_exists( 'at_rate_limit_reject' ) ) {
    /**
     * @param int    $retry_after  Segundos para Retry-After header.
     * @param string $bucket       Nombre del bucket (para logging de seguridad).
     */
    function at_rate_limit_reject( $retry_after = 60, $bucket = '' ) {
        // C4: log rate-limit rejections for security monitoring
        if ( function_exists( 'at_secmon_log_event' ) ) {
            at_secmon_log_event( 'rate_limit_reject', [ 'bucket' => $bucket ] );
        }
        if ( ! headers_sent() ) {
            http_response_code( 429 );
            header( 'Retry-After: ' . (int) $retry_after );
            header( 'Content-Type: application/json; charset=utf-8' );
        }
        echo wp_json_encode( [ 'error' => 'Demasiadas solicitudes. Intenta de nuevo en unos segundos.' ] );
        exit;
    }
}
