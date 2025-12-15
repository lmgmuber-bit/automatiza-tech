<?php
// api-save-proposal.php
// Endpoint para n8n: Recibe datos y guarda en la BD.

require_once('wp-load.php');

header('Content-Type: application/json');

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

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
?>