<?php
require_once __DIR__ . '/at-maintenance-guard.php';

// update-db-schema-v2.php
require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';

$columns = [
    'client_name' => 'VARCHAR(255)',
    'company_name' => 'VARCHAR(255)',
    'status' => "VARCHAR(50) DEFAULT 'draft'",
    'pdf_path' => 'TEXT'
];

echo "Actualizando tabla $table_name...\n";

foreach ($columns as $col => $def) {
    // Check if column exists
    $exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE '$col'");
    if (empty($exists)) {
        $sql = "ALTER TABLE $table_name ADD COLUMN $col $def";
        $result = $wpdb->query($sql);
        if ($result === false) {
            echo "Error añadiendo $col: " . $wpdb->last_error . "\n";
        } else {
            echo "Columna $col añadida.\n";
        }
    } else {
        echo "Columna $col ya existe.\n";
    }
}

// También asegurarnos de que gamma_iframe_url y n8n_chat_url sean TEXT para que quepan URLs largas o iframes
$wpdb->query("ALTER TABLE $table_name MODIFY COLUMN gamma_iframe_url TEXT");
$wpdb->query("ALTER TABLE $table_name MODIFY COLUMN n8n_chat_url TEXT");

echo "Proceso finalizado.\n";
?>