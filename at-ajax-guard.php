<?php
/**
 * AT AJAX Guard Helper
 *
 * Helpers para endurecer handlers wp_ajax_* y wp_ajax_nopriv_*.
 * Patron de uso recomendado:
 *
 *   add_action( 'wp_ajax_my_action', function () {
 *       at_ajax_require_nonce( 'my_action_nonce' );      // muere con 403 si falla
 *       at_ajax_require_cap( 'manage_options' );          // opcional
 *       // ... handler ...
 *   } );
 *
 *   // Para handlers nopriv con secreto compartido (token/API key):
 *   add_action( 'wp_ajax_nopriv_my_action', function () {
 *       at_ajax_require_token( 'my_action_token', $expected_token );
 *       // ... handler ...
 *   } );
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'at_ajax_require_nonce' ) ) {
    /**
     * Verifica nonce. Si falla, responde 403 JSON y termina.
     *
     * @param string $action Nombre del nonce (mismo usado en wp_create_nonce()).
     * @param string $field  Nombre del campo POST/GET. Default '_ajax_nonce'.
     */
    function at_ajax_require_nonce( $action, $field = '_ajax_nonce' ) {
        $nonce = $_REQUEST[ $field ] ?? '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
            status_header( 403 );
            wp_send_json_error( [ 'error' => 'Nonce invalido o ausente' ], 403 );
        }
    }
}

if ( ! function_exists( 'at_ajax_require_cap' ) ) {
    function at_ajax_require_cap( $capability = 'manage_options' ) {
        if ( ! current_user_can( $capability ) ) {
            status_header( 403 );
            wp_send_json_error( [ 'error' => 'Permisos insuficientes' ], 403 );
        }
    }
}

if ( ! function_exists( 'at_ajax_require_token' ) ) {
    /**
     * Verifica un token compartido (HMAC-friendly). Compara con hash_equals.
     *
     * @param string $field    Nombre del campo (POST o header).
     * @param string $expected Valor esperado.
     */
    function at_ajax_require_token( $field, $expected ) {
        $received = $_REQUEST[ $field ] ?? '';
        if ( '' === $received ) {
            $hdr_field = 'HTTP_X_' . strtoupper( str_replace( '-', '_', $field ) );
            $received  = $_SERVER[ $hdr_field ] ?? '';
        }
        if ( ! is_string( $received ) || ! is_string( $expected ) || $expected === '' ) {
            status_header( 403 );
            wp_send_json_error( [ 'error' => 'Token requerido' ], 403 );
        }
        if ( ! hash_equals( $expected, $received ) ) {
            status_header( 403 );
            wp_send_json_error( [ 'error' => 'Token invalido' ], 403 );
        }
    }
}
