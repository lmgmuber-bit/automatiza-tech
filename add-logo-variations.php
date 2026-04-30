<?php
/**
 * Agregar campos de variaciones de logo a la tabla de clientes
 */

require_once __DIR__ . '/wp-load.php';

// if (!current_user_can('manage_options')) {
//    die('Requiere admin');
// }

global $wpdb;
$table = $wpdb->prefix . 'crm_clientes';

echo "<h1>Actualizando tabla para Logos Variantes</h1><pre>";

$new_cols = [
    'logo_nombre' => 'VARCHAR(255) DEFAULT NULL',
    'logo_isotipo' => 'VARCHAR(255) DEFAULT NULL',
    'logo_tagline' => 'VARCHAR(255) DEFAULT NULL'
];

foreach ($new_cols as $col => $def) {
    if (!$wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '$col'")) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN $col $def AFTER logo_url");
        echo "✅ Columna '$col' agregada.\n";
    } else {
        echo "ℹ️ Columna '$col' ya existe.\n";
    }
}

echo "Done.";
