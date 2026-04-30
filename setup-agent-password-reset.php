<?php
/**
 * Add password_reset_code and password_reset_expires columns to omnichannel_agents table.
 * Run once via browser: /automatiza-tech/setup-agent-password-reset.php
 */
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

global $wpdb;
$table = $wpdb->prefix . 'omnichannel_agents';
$charset = $wpdb->get_charset_collate();

$cols = $wpdb->get_col("DESCRIBE {$table}");

$added = [];

if (!in_array('password_reset_code', $cols)) {
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN password_reset_code VARCHAR(255) DEFAULT NULL AFTER password_hash");
    $added[] = 'password_reset_code';
}

if (!in_array('password_reset_expires', $cols)) {
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN password_reset_expires DATETIME DEFAULT NULL AFTER password_reset_code");
    $added[] = 'password_reset_expires';
}

if (empty($added)) {
    echo '<p>✅ Las columnas ya existen. No se realizaron cambios.</p>';
} else {
    echo '<p>✅ Columnas agregadas: <strong>' . implode(', ', $added) . '</strong></p>';
}

echo '<p>Tabla: ' . esc_html($table) . '</p>';
echo '<h3>Estructura actual:</h3><pre>';
$structure = $wpdb->get_results("DESCRIBE {$table}");
foreach ($structure as $col) {
    echo esc_html("{$col->Field} — {$col->Type} — {$col->Null} — {$col->Default}") . "\n";
}
echo '</pre>';
