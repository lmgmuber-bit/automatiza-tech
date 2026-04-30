<?php
require_once __DIR__ . '/wp-load.php';
global $wpdb;
$rows = $wpdb->get_results("SELECT id, client_name, company, meeting_date, meeting_time, status, google_event_id, meet_link FROM {$wpdb->prefix}automatiza_followup_meetings ORDER BY id DESC LIMIT 10");
echo "=== FOLLOWUP MEETINGS ===\n";
foreach ($rows as $r) {
    echo "ID: {$r->id} | {$r->client_name} | {$r->company} | {$r->meeting_date} {$r->meeting_time} | status: {$r->status} | event_id: [{$r->google_event_id}] | meet: {$r->meet_link}\n";
}
