<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Migration: Add attachments column to ticket_messages table
 * Run once: /automatiza-tech/setup-ticket-attachments.php
 */
require_once __DIR__ . '/wp-load.php';

global $wpdb;
$prefix = $wpdb->prefix . 'omnichannel_';
$table  = $prefix . 'ticket_messages';

// Add attachments JSON column if not exists
$col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'attachments'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN attachments TEXT DEFAULT NULL AFTER message");
    echo "✅ Column 'attachments' added to {$table}\n";
} else {
    echo "ℹ️ Column 'attachments' already exists in {$table}\n";
}

echo "\nDone.\n";
