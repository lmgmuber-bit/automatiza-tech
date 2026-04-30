<?php
require_once('wp-load.php');
global $wpdb;

$table = $wpdb->prefix . 'automatiza_propuestas';

echo "=== COLUMNAS DE $table ===\n";
$cols = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach($cols as $c) {
    echo $c->Field . " - " . $c->Type . "\n";
}

echo "\n=== DATOS (últimos 5) ===\n";
$rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 5");
if (empty($rows)) {
    echo "No hay datos en la tabla\n";
} else {
    foreach($rows as $r) {
        print_r($r);
    }
}
?>
