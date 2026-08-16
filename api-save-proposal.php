<?php
// api-save-proposal.php
// Endpoint para n8n: Recibe datos y guarda en la BD.

require_once('wp-load.php');
require_once __DIR__ . '/at-rate-limit.php';
require_once __DIR__ . '/at-webhook-verify.php';

header('Content-Type: application/json');

// Rate limit: max 20 peticiones/min por IP (anti abuso)
if ( ! at_rate_limit_check( 'save_proposal', 20, 60 ) ) {
    at_rate_limit_reject( 60 );
}

// HMAC verification.
// Define AT_N8N_WEBHOOK_SECRET en wp-config-secrets.php para activar.
// Si hay secreto: verifica firma HMAC; si headers ausentes, cae a whitelist IP.
// Si no hay secreto: permite (dev) con warning en log.
$raw_body    = file_get_contents('php://input');
$n8n_secret  = defined('AT_N8N_WEBHOOK_SECRET') ? AT_N8N_WEBHOOK_SECRET : '';

if ( $n8n_secret !== '' ) {
    $hmac_result = at_webhook_verify_hmac( $raw_body, $n8n_secret );
    if ( $hmac_result === false ) {
        http_response_code( 403 );
        echo json_encode( [ 'error' => 'Firma HMAC invalida.' ] );
        exit;
    }
    if ( $hmac_result === null ) {
        // Sin headers HMAC: fallback a whitelist de IPs (N8N legacy sin firma)
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
            error_log( 'api-save-proposal: AT_N8N_WEBHOOK_SECRET configurado pero sin headers HMAC y sin AT_N8N_ALLOWED_IPS — solicitud aceptada en modo permisivo.' );
        }
    }
} else {
    error_log( 'api-save-proposal: AT_N8N_WEBHOOK_SECRET no configurado — ejecutando sin verificacion HMAC.' );
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode( $raw_body, true );

if (!$data) {
    echo json_encode(['error' => 'No data received']);
    exit;
}

$client_email = sanitize_email($data['client_email']);
$transcript = sanitize_textarea_field($data['transcript']);
$gamma_prompt = $data['gamma_prompt']; // Mantener formato original
$system_prompt = $data['system_prompt']; // Mantener formato original

// Generar ID único para enlaces (más seguro que el ID autoincremental para URLs públicas)
$unique_id = wp_generate_password(12, false);

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';

$inserted = $wpdb->insert(
    $table_name,
    array(
        'client_email' => $client_email,
        'unique_link_id' => $unique_id,
        'transcript_text' => $transcript,
        'gamma_prompt_text' => $gamma_prompt,
        'system_prompt_text' => $system_prompt,
        'created_at' => current_time('mysql')
    )
);

if ($inserted) {
    $base_url = get_site_url();
    echo json_encode([
        'success' => true,
        'id' => $wpdb->insert_id,
        'unique_id' => $unique_id,
        'demo_url' => $base_url . '/ver-demo.php?id=' . $unique_id,
        'presentation_url' => $base_url . '/ver-presentacion.php?id=' . $unique_id
    ]);
} else {
    echo json_encode(['error' => 'Database insert failed', 'db_error' => $wpdb->last_error]);
}