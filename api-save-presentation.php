<?php
// api-save-presentation.php
// Endpoint para el microservicio propuesta-renderer: guarda el link de la
// presentacion renderizada y el PDF en la propuesta correspondiente.

require_once('wp-load.php');
require_once __DIR__ . '/at-rate-limit.php';
require_once __DIR__ . '/at-webhook-verify.php';

header('Content-Type: application/json');

if ( ! at_rate_limit_check( 'save_presentation', 20, 60 ) ) {
    at_rate_limit_reject( 60, 'save_presentation' );
}

$raw_body   = file_get_contents('php://input');
$n8n_secret = defined('AT_N8N_WEBHOOK_SECRET') ? AT_N8N_WEBHOOK_SECRET : '';

if ( $n8n_secret !== '' ) {
    $hmac_result = at_webhook_verify_hmac( $raw_body, $n8n_secret );
    if ( $hmac_result === false ) {
        http_response_code( 403 );
        echo json_encode( [ 'error' => 'Firma HMAC invalida.' ] );
        exit;
    }
    if ( $hmac_result === null ) {
        $allowed_ips = defined('AT_N8N_ALLOWED_IPS')
            ? array_map( 'trim', explode( ',', AT_N8N_ALLOWED_IPS ) )
            : [];
        if ( ! empty( $allowed_ips ) ) {
            $client_ip = at_rate_limit_client_ip();
            if ( ! in_array( $client_ip, $allowed_ips, true ) ) {
                http_response_code( 403 );
                echo json_encode( [ 'error' => 'Acceso no autorizado.' ] );
                exit;
            }
        } else {
            error_log( 'api-save-presentation: AT_N8N_WEBHOOK_SECRET configurado pero sin headers HMAC y sin AT_N8N_ALLOWED_IPS — solicitud aceptada en modo permisivo.' );
        }
    }
} else {
    error_log( 'api-save-presentation: AT_N8N_WEBHOOK_SECRET no configurado — ejecutando sin verificacion HMAC.' );
}

$data = json_decode( $raw_body, true );

if ( ! $data || empty( $data['unique_id'] ) ) {
    echo json_encode( [ 'error' => 'unique_id requerido' ] );
    exit;
}

$unique_id = sanitize_text_field( $data['unique_id'] );
$view_url  = isset( $data['view_url'] ) ? esc_url_raw( $data['view_url'] ) : '';
$pdf_url   = isset( $data['pdf_url'] ) ? esc_url_raw( $data['pdf_url'] ) : '';

if ( ! $view_url && ! $pdf_url ) {
    echo json_encode( [ 'error' => 'view_url o pdf_url requerido' ] );
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';

$update = [];
if ( $view_url ) {
    $update['gamma_iframe_url'] = $view_url;
}
if ( $pdf_url ) {
    $update['pdf_path'] = $pdf_url;
}

$updated = $wpdb->update( $table_name, $update, [ 'unique_link_id' => $unique_id ] );

if ( $updated === false ) {
    echo json_encode( [ 'error' => 'Database update failed', 'db_error' => $wpdb->last_error ] );
    exit;
}

echo json_encode( [ 'success' => true, 'unique_id' => $unique_id ] );
