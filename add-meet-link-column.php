<?php
require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

// Check if column exists
$row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_name' AND column_name = 'meet_link'");

if (empty($row)) {
    $sql = "ALTER TABLE $table_name ADD COLUMN meet_link varchar(255) DEFAULT '' AFTER session_id";
    $wpdb->query($sql);
    echo "Column 'meet_link' added successfully to $table_name.";
} else {
    echo "Column 'meet_link' already exists in $table_name.";
}
?>