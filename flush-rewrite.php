<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');
flush_rewrite_rules(true); // Hard flush
echo "Rewrite rules flushed successfully (Hard Flush).";
