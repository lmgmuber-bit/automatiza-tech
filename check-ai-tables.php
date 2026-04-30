<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once 'wp-load.php';
global $wpdb;

echo "=== Verificando tablas ai_usage_log ===" . PHP_EOL . PHP_EOL;

// Tabla sin prefijo
echo "1. Tabla 'ai_usage_log' (sin prefijo):" . PHP_EOL;
$exists1 = $wpdb->get_var("SHOW TABLES LIKE 'ai_usage_log'");
if ($exists1) {
    $count1 = $wpdb->get_var("SELECT COUNT(*) FROM ai_usage_log");
    echo "   ✅ Existe - $count1 registros" . PHP_EOL;
    
    if ($count1 > 0) {
        echo "   Muestra de datos:" . PHP_EOL;
        $sample = $wpdb->get_results("SELECT id, client_identifier, total_tokens, cost_estimated, created_at FROM ai_usage_log ORDER BY id DESC LIMIT 5");
        foreach ($sample as $s) {
            echo "   - ID:{$s->id} | {$s->client_identifier} | {$s->total_tokens} tokens | \${$s->cost_estimated} | {$s->created_at}" . PHP_EOL;
        }
    }
} else {
    echo "   ❌ No existe" . PHP_EOL;
}

// Tabla con prefijo
echo PHP_EOL . "2. Tabla 'wp_ai_usage_log' (con prefijo):" . PHP_EOL;
$exists2 = $wpdb->get_var("SHOW TABLES LIKE 'wp_ai_usage_log'");
if ($exists2) {
    $count2 = $wpdb->get_var("SELECT COUNT(*) FROM wp_ai_usage_log");
    echo "   ✅ Existe - $count2 registros" . PHP_EOL;
    
    if ($count2 > 0) {
        echo "   Muestra de datos:" . PHP_EOL;
        $sample = $wpdb->get_results("SELECT * FROM wp_ai_usage_log ORDER BY id DESC LIMIT 5");
        foreach ($sample as $s) {
            $tokens = $s->total_tokens ?: $s->tokens_total ?: 0;
            $cost = $s->cost_estimated ?: $s->cost_usd ?: 0;
            echo "   - ID:{$s->id} | {$s->client_identifier} | {$tokens} tokens | \${$cost} | {$s->created_at}" . PHP_EOL;
        }
    }
} else {
    echo "   ❌ No existe" . PHP_EOL;
}

echo PHP_EOL . "=== FIN ===" . PHP_EOL;
