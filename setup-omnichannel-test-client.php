<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para crear un cliente de prueba en el portal omnicanal.
 * 
 * Uso: Visita en el navegador (requiere estar logueado como admin en WordPress):
 *   http://localhost/automatiza-tech/setup-omnichannel-test-client.php
 * 
 * Crea un cliente de prueba con API key que puedes usar para acceder al portal.
 * IMPORTANTE: Elimina este archivo después de usarlo en un entorno de producción.
 */

require_once __DIR__ . '/wp-load.php';

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Acceso denegado. Debes ser administrador de WordPress.');
}

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';

// Verificar que la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$prefix}clients'");

if (!$table_exists) {
    echo '<div style="font-family:sans-serif; max-width:600px; margin:50px auto; padding:30px; background:#fef2f2; border:2px solid #ef4444; border-radius:16px;">';
    echo '<h2 style="color:red;">⚠️ La tabla ' . esc_html($prefix) . 'clients no existe.</h2>';
    echo '<p>Ejecuta primero: <a href="/automatiza-tech/setup-omnichannel-db.php">setup-omnichannel-db.php</a></p>';
    echo '</div>';
    exit;
}

// Verificar si ya existe un cliente de prueba
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$prefix}clients WHERE email = %s",
    'admin@automatizatech.com'
));

if ($existing) {
    echo '<div style="font-family:sans-serif; max-width:600px; margin:50px auto; padding:30px; background:#f0fdf4; border:2px solid #22c55e; border-radius:16px;">';
    echo '<h2>✅ Cliente de prueba ya existe</h2>';
    echo '<table style="margin:20px 0; border-collapse:collapse;">';
    echo '<tr><td style="padding:8px; font-weight:bold;">Empresa:</td><td style="padding:8px;">' . esc_html($existing->company_name) . '</td></tr>';
    echo '<tr><td style="padding:8px; font-weight:bold;">Email:</td><td style="padding:8px;">' . esc_html($existing->email) . '</td></tr>';
    echo '<tr><td style="padding:8px; font-weight:bold;">Plan:</td><td style="padding:8px;">' . esc_html($existing->plan_type) . '</td></tr>';
    echo '<tr><td style="padding:8px; font-weight:bold;">Estado:</td><td style="padding:8px;">' . esc_html($existing->status) . '</td></tr>';
    echo '<tr style="background:#ecfdf5;"><td style="padding:12px; font-weight:bold; font-size:1.1em;">🔑 API Key:</td><td style="padding:12px; font-family:monospace; font-size:1.1em; color:#1e40af; word-break:break-all;">' . esc_html($existing->api_key) . '</td></tr>';
    echo '</table>';
    echo '<p style="color:#666;">Usa esta API Key en el login del portal: <a href="http://localhost:5173" target="_blank">http://localhost:5173</a></p>';
    echo '</div>';
    exit;
}

// Crear cliente de prueba
$api_key = wp_generate_password(48, false);

$result = $wpdb->insert($prefix . 'clients', [
    'company_name' => 'AutomatizaTech (Admin Test)',
    'contact_name' => 'Administrador',
    'email'        => 'admin@automatizatech.com',
    'phone'        => '+57 300 000 0000',
    'plan_type'    => 'enterprise',
    'status'       => 'active',
    'max_channels' => 10,
    'max_agents'   => 20,
    'api_key'      => $api_key,
    'activated_at' => current_time('mysql'),
]);

if ($result) {
    echo '<div style="font-family:sans-serif; max-width:600px; margin:50px auto; padding:30px; background:#f0fdf4; border:2px solid #22c55e; border-radius:16px;">';
    echo '<h2>🎉 Cliente de prueba creado exitosamente</h2>';
    echo '<table style="margin:20px 0; border-collapse:collapse;">';
    echo '<tr><td style="padding:8px; font-weight:bold;">Empresa:</td><td style="padding:8px;">AutomatizaTech (Admin Test)</td></tr>';
    echo '<tr><td style="padding:8px; font-weight:bold;">Plan:</td><td style="padding:8px;">Enterprise</td></tr>';
    echo '<tr><td style="padding:8px; font-weight:bold;">Max Canales:</td><td style="padding:8px;">10</td></tr>';
    echo '<tr><td style="padding:8px; font-weight:bold;">Max Agentes:</td><td style="padding:8px;">20</td></tr>';
    echo '<tr style="background:#ecfdf5;"><td style="padding:12px; font-weight:bold; font-size:1.1em;">🔑 API Key:</td><td style="padding:12px; font-family:monospace; font-size:1.1em; color:#1e40af; word-break:break-all;">' . esc_html($api_key) . '</td></tr>';
    echo '</table>';
    echo '<p style="color:#666;">Usa esta API Key en el login del portal: <a href="http://localhost:5173" target="_blank">http://localhost:5173</a></p>';
    echo '<p style="color:#ef4444; font-size:0.85em;">⚠️ Guarda la API Key. Elimina este archivo en producción.</p>';
    echo '</div>';
} else {
    echo '<div style="font-family:sans-serif; max-width:600px; margin:50px auto; padding:30px; background:#fef2f2; border:2px solid #ef4444; border-radius:16px;">';
    echo '<h2>❌ Error al crear el cliente</h2>';
    echo '<p>' . esc_html($wpdb->last_error) . '</p>';
    echo '</div>';
}
