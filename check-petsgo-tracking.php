<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once(__DIR__ . '/wp-load.php');
global $wpdb;
$r = $wpdb->get_results("SELECT id, client_identifier, model_used, total_tokens, cost_estimated, created_at FROM ai_usage_log WHERE client_identifier LIKE 'cliente_petsgo%' ORDER BY created_at DESC LIMIT 5", ARRAY_A);
echo "Registros PetsGo en ai_usage_log:\n";
if (empty($r)) {
    echo "  (ninguno)\n";
} else {
    foreach ($r as $row) {
        echo "  ID:{$row['id']} | {$row['client_identifier']} | {$row['model_used']} | tokens:{$row['total_tokens']} | \${$row['cost_estimated']} | {$row['created_at']}\n";
    }
}
