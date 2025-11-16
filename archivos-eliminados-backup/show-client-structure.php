<?php
require 'wp-load.php';
global $wpdb;
$table = $wpdb->prefix . 'automatiza_tech_clients';
$columns = $wpdb->get_results("DESCRIBE {$table}");
echo "=== ESTRUCTURA TABLA CLIENTES ===\n\n";
foreach($columns as $col) {
    echo "{$col->Field} | {$col->Type} | Null: {$col->Null}\n";
}
