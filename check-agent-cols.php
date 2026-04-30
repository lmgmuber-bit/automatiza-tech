<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once __DIR__ . '/wp-load.php';
global $wpdb;
$cols = $wpdb->get_results('SHOW COLUMNS FROM ' . $wpdb->prefix . 'omnichannel_agents');
header('Content-Type: text/plain');
foreach ($cols as $c) {
    echo $c->Field . ' | ' . $c->Type . "\n";
}
