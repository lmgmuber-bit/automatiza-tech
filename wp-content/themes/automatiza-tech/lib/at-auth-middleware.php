<?php
/**
 * AT Auth Middleware — E1
 *
 * Helpers reutilizables de autenticación y autorización para handlers
 * AJAX, páginas admin y endpoints de descarga. Centraliza las comprobaciones
 * que anteriormente estaban duplicadas en cada handler.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'at_require_admin' ) ) {
    /**
     * Exige que el usuario actual sea un administrador WP (manage_options).
     * Para uso en páginas o acciones que no son AJAX.
     * Si falla: redirige al login con auth_redirect().
     */
    function at_require_admin(): void {
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( function_exists( 'at_secmon_log_event' ) ) {
            at_secmon_log_event( 'api_auth_failed', [ 'endpoint' => $_SERVER['REQUEST_URI'] ?? '' ] );
        }
        auth_redirect();
        exit;
    }
}

if ( ! function_exists( 'at_require_admin_ajax' ) ) {
    /**
     * Exige admin WP + nonce válido para un handler AJAX.
     *
     * Uso:
     *   at_require_admin_ajax( 'mi_accion_nonce' );
     *
     * @param string $nonce_action  Acción usada en wp_create_nonce().
     * @param string $nonce_key     Nombre del campo/header donde viene el nonce (default '_wpnonce').
     */
    function at_require_admin_ajax( string $nonce_action, string $nonce_key = '_wpnonce' ): void {
        $nonce = sanitize_text_field( $_REQUEST[ $nonce_key ] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? '' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            if ( function_exists( 'at_secmon_log_event' ) ) {
                at_secmon_log_event( 'ajax_nonce_invalid', [ 'action' => $nonce_action ] );
            }
            wp_send_json_error( [ 'message' => 'Nonce inválido o expirado.' ], 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( function_exists( 'at_secmon_log_event' ) ) {
                at_secmon_log_event( 'api_auth_failed', [ 'action' => $nonce_action ] );
            }
            wp_send_json_error( [ 'message' => 'Sin permisos de administrador.' ], 403 );
        }
    }
}

if ( ! function_exists( 'at_require_own_invoice' ) ) {
    /**
     * Verifica que el usuario actual pueda acceder a una factura.
     * Admins ven todo; usuarios regulares solo sus propias facturas.
     *
     * @param object $invoice  Fila de la tabla de facturas (debe tener ->client_id o ->wp_user_id).
     * @return bool
     */
    function at_require_own_invoice( object $invoice ): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true; // Administradores ven todo
        }
        // Para usuarios no-admin: verificar que la factura pertenece al cliente asociado
        $user_id = get_current_user_id();
        if ( ! empty( $invoice->wp_user_id ) && (int) $invoice->wp_user_id === $user_id ) {
            return true;
        }
        // Fallback: buscar el cliente por wp_user_id y comparar con client_id de la factura
        if ( ! empty( $invoice->client_id ) ) {
            global $wpdb;
            $clients_table = $wpdb->prefix . 'automatiza_tech_clients';
            $client = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$clients_table} WHERE wp_user_id = %d LIMIT 1",
                $user_id
            ) );
            if ( $client && (int) $client->id === (int) $invoice->client_id ) {
                return true;
            }
        }
        if ( function_exists( 'at_secmon_log_event' ) ) {
            at_secmon_log_event( 'idor_attempt', [
                'resource'    => 'invoice',
                'resource_id' => $invoice->id ?? '?',
                'user_id'     => $user_id,
            ] );
        }
        return false;
    }
}
