<?php
require_once __DIR__ . '/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'automatiza_leads';

// Add 'status' column if missing
$col_status = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'status'");
if (!$col_status) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN `status` varchar(20) DEFAULT NULL AFTER `scheduled_time`");
    echo "Added 'status' column.\n";
} else {
    echo "'status' column already exists.\n";
}

// Add 'source' column if missing
$col_source = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'source'");
if (!$col_source) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN `source` varchar(30) DEFAULT 'web' AFTER `status`");
    echo "Added 'source' column.\n";
} else {
    echo "'source' column already exists.\n";
}

// Add columns for WhatsApp reminders if missing
$wa_cols = [
    'wa_reminder_72h' => "tinyint(1) DEFAULT 0",
    'wa_reminder_24h' => "tinyint(1) DEFAULT 0",  
    'wa_reminder_1h'  => "tinyint(1) DEFAULT 0",
    'meet_link'       => "varchar(255) DEFAULT NULL",
];

foreach ($wa_cols as $col => $def) {
    $exists = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE '$col'");
    if (!$exists) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN `$col` $def");
        echo "Added '$col' column.\n";
    } else {
        echo "'$col' column already exists.\n";
    }
}

echo "\nDone. Current columns:\n";
$cols = $wpdb->get_results("DESCRIBE $table");
foreach ($cols as $c) {
    echo "  - {$c->Field} ({$c->Type})\n";
}
