<?php
/**
 * get-migration-token.php
 * Genera tokens para scripts de migración.
 * Uso: /get-migration-token.php?key=NOMBRE_DEL_SEED
 * 
 * Ejemplo: /get-migration-token.php?key=add_agent_schedule_cols_2025
 * 
 * ⚠️  Requiere estar logueado como admin de WordPress.
 */

require_once __DIR__ . '/wp-load.php';

// Solo admins pueden ver tokens
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Debes estar logueado como administrador de WordPress.');
}

$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

if (empty($key)) {
    echo '<h2>Generador de Tokens de Migración</h2>';
    echo '<p>Uso: <code>?key=NOMBRE_DEL_SEED</code></p>';
    echo '<p>Ejemplo: <code>?key=add_agent_schedule_cols_2025</code></p>';
    exit;
}

$token = wp_hash($key);

echo '<h2>Token generado</h2>';
echo '<p><strong>Key:</strong> ' . esc_html($key) . '</p>';
echo '<p><strong>Token:</strong> <code style="background:#eee;padding:4px 8px;font-size:16px;user-select:all">' . esc_html($token) . '</code></p>';
echo '<p>Copia el token y úsalo en la URL de migración.</p>';
