<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once 'wp-load.php';
global $wpdb;
echo "Prefijo de tablas en DEV: " . $wpdb->prefix . PHP_EOL;
echo "Tablas existentes:" . PHP_EOL;
$tables = $wpdb->get_col("SHOW TABLES");
foreach ($tables as $t) {
    echo "  - $t" . PHP_EOL;
}
