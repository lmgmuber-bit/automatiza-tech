<?php
require_once __DIR__ . '/wp-load.php';
global $wpdb;
$cols = $wpdb->get_results('DESCRIBE wp_automatiza_leads');
foreach ($cols as $c) {
    echo $c->Field . ' (' . $c->Type . ")\n";
}
