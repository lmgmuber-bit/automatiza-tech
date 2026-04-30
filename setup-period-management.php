<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Migration: Add period management fields to omnichannel_clients
 * 
 * Adds: period_start, period_end, is_free, expiry_notified_days
 * Registers WP-Cron for expiry reminder emails
 * 
 * Run once: http://localhost/automatiza-tech/setup-period-management.php
 */
require_once __DIR__ . '/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'omnichannel_clients';
$results = [];

// 1. Add period_start
$col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'period_start'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN period_start DATE DEFAULT NULL AFTER trial_ends_at");
    $results[] = "✅ period_start added";
} else {
    $results[] = "⏭ period_start already exists";
}

// 2. Add period_end
$col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'period_end'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN period_end DATE DEFAULT NULL AFTER period_start");
    $results[] = "✅ period_end added";
} else {
    $results[] = "⏭ period_end already exists";
}

// 3. Add is_free (0 = paid, 1 = free/grace)
$col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_free'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN is_free TINYINT(1) NOT NULL DEFAULT 0 AFTER period_end");
    $results[] = "✅ is_free added";
} else {
    $results[] = "⏭ is_free already exists";
}

// 4. Add expiry_notified_days — tracks which reminder was last sent (10,7,5,3,0)
$col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'expiry_notified_days'");
if (empty($col)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN expiry_notified_days VARCHAR(50) DEFAULT '' AFTER is_free");
    $results[] = "✅ expiry_notified_days added";
} else {
    $results[] = "⏭ expiry_notified_days already exists";
}

// 5. Add index on period_end for cron efficiency
$idx = $wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'idx_period_end'");
if (empty($idx)) {
    $wpdb->query("ALTER TABLE $table ADD INDEX idx_period_end (period_end)");
    $results[] = "✅ idx_period_end index added";
} else {
    $results[] = "⏭ idx_period_end index exists";
}

// 6. Backfill existing clients: set period_start=created_at, period_end=trial_ends_at
$updated = $wpdb->query("
    UPDATE $table 
    SET period_start = DATE(created_at),
        period_end = DATE(trial_ends_at)
    WHERE period_start IS NULL 
      AND trial_ends_at IS NOT NULL
");
$results[] = "✅ Backfilled $updated existing clients with trial dates";

// 7. Register WP-Cron event (twice daily)
if (!wp_next_scheduled('omnichannel_check_expiry')) {
    wp_schedule_event(time(), 'twicedaily', 'omnichannel_check_expiry');
    $results[] = "✅ WP-Cron 'omnichannel_check_expiry' scheduled (twicedaily)";
} else {
    $results[] = "⏭ WP-Cron 'omnichannel_check_expiry' already scheduled";
}

echo "<h2>Period Management Migration</h2><pre>";
foreach ($results as $r) echo "$r\n";
echo "</pre>";
