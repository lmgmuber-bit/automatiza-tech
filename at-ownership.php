<?php
/**
 * AT Ownership Helper
 *
 * Helpers para validar que un recurso pertenece al usuario autenticado.
 * Defiende contra IDOR (Insecure Direct Object Reference).
 *
 *   if ( ! at_owns_resource( 'propuestas', $proposal_id, get_current_user_id(), 'created_by' ) ) {
 *       wp_send_json_error( 'No autorizado', 403 );
 *   }
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'at_owns_resource' ) ) {
    /**
     * Verifica ownership de un registro en BD.
     *
     * @param string $table_suffix Tabla (sin prefijo). Ej: 'propuestas', 'omnichannel_messages'.
     * @param int    $resource_id  ID del recurso.
     * @param int    $user_id      ID de WP_User esperado.
     * @param string $owner_col    Columna que contiene el owner. Default 'user_id'.
     * @return bool
     */
    function at_owns_resource( $table_suffix, $resource_id, $user_id, $owner_col = 'user_id' ) {
        global $wpdb;
        $resource_id = (int) $resource_id;
        $user_id     = (int) $user_id;
        if ( $resource_id <= 0 || $user_id <= 0 ) {
            return false;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        $table     = $wpdb->prefix . preg_replace( '/[^a-z0-9_]/i', '', $table_suffix );
        $owner_col = preg_replace( '/[^a-z0-9_]/i', '', $owner_col );
        if ( $owner_col === '' ) {
            return false;
        }
        $owner = $wpdb->get_var( $wpdb->prepare(
            "SELECT {$owner_col} FROM {$table} WHERE id = %d",
            $resource_id
        ) );
        return $owner !== null && (int) $owner === $user_id;
    }
}

if ( ! function_exists( 'at_require_ownership' ) ) {
    /**
     * Aborta con 403 JSON si el usuario no es owner.
     */
    function at_require_ownership( $table_suffix, $resource_id, $owner_col = 'user_id' ) {
        if ( ! at_owns_resource( $table_suffix, $resource_id, get_current_user_id(), $owner_col ) ) {
            status_header( 403 );
            wp_send_json_error( [ 'error' => 'No tienes permiso para acceder a este recurso' ], 403 );
        }
    }
}
