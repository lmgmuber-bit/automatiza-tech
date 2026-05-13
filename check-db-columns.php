<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_propuestas';
$cols = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
foreach ($cols as $col) {
    echo $col->Field . " (" . $col->Type . ")\n";
}
?>