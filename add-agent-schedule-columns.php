<?php
/**
 * Migration: Add schedule columns to wp_omnichannel_agents
 * Columns: schedule_start (TIME), schedule_end (TIME), available_days (VARCHAR - comma-separated day numbers)
 * 
 * Run via: GET /add-agent-schedule-columns.php?token=<wp_hash>
 */
require_once __DIR__ . '/wp-load.php';

$expected = wp_hash('add_agent_schedule_cols_2025');
$token = sanitize_text_field($_GET['token'] ?? '');
if (empty($token) || !hash_equals($expected, $token)) {
    wp_die('Token inválido. Genera con: echo wp_hash("add_agent_schedule_cols_2025");');
}

global $wpdb;
$table = $wpdb->prefix . 'omnichannel_agents';

$results = [];

// schedule_start
$col = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'schedule_start'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `schedule_start` TIME DEFAULT NULL AFTER `department`");
    $results[] = 'schedule_start added';
} else {
    $results[] = 'schedule_start already exists';
}

// schedule_end
$col = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'schedule_end'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `schedule_end` TIME DEFAULT NULL AFTER `schedule_start`");
    $results[] = 'schedule_end added';
} else {
    $results[] = 'schedule_end already exists';
}

// available_days (comma-separated day numbers: 1=Mon,2=Tue,...,7=Sun)
$col = $wpdb->get_results("SHOW COLUMNS FROM `{$table}` LIKE 'available_days'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `available_days` VARCHAR(50) DEFAULT '1,2,3,4,5' AFTER `schedule_end`");
    $results[] = 'available_days added (default Mon-Fri)';
} else {
    $results[] = 'available_days already exists';
}

echo '<h2>Migration: Agent Schedule Columns</h2>';
echo '<pre>' . implode("\n", $results) . '</pre>';
echo '<p style="color:green;">Done ✅</p>';
