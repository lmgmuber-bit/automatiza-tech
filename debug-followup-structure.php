<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Debug: Ver estructura de tabla followup_meetings
 */
require_once(__DIR__ . '/wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_followup_meetings';

echo "<h2>Estructura de $table_name</h2>";

$columns = $wpdb->get_results("DESCRIBE $table_name");
echo "<pre>";
foreach ($columns as $col) {
    echo $col->Field . " (" . $col->Type . ") " . ($col->Null === 'YES' ? 'NULL' : 'NOT NULL') . " " . $col->Default . "\n";
}
echo "</pre>";

// Ver un registro de ejemplo
echo "<h2>Último registro:</h2>";
$sample = $wpdb->get_row("SELECT * FROM $table_name ORDER BY id DESC LIMIT 1");
echo "<pre>" . print_r($sample, true) . "</pre>";
