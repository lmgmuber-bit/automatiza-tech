<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Verificar registro de consumo y hacer prueba
 */
require_once 'wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'ai_usage_log';

echo "=== Verificación de registro de consumo ===" . PHP_EOL . PHP_EOL;

// 1. Verificar registros recientes
echo "1. REGISTROS EN ai_usage_log:" . PHP_EOL;
$total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "   Total: $total" . PHP_EOL;

if ($total > 0) {
    $ultimos = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 5");
    echo "   Últimos registros:" . PHP_EOL;
    foreach ($ultimos as $u) {
        print_r($u);
    }
}

// 2. Hacer INSERT de prueba
echo PHP_EOL . "2. PRUEBA DE INSERT:" . PHP_EOL;

$user = wp_get_current_user();
$test_data = array(
    'user_id' => get_current_user_id() ?: 1,
    'user_email' => $user->user_email ?: 'test@test.com',
    'client_identifier' => 'interno_aria',
    'model' => 'gpt-4o-mini',
    'model_used' => 'gpt-4o-mini',
    'endpoint' => 'aria_agente',
    'request_endpoint' => 'aria_agente',
    'tokens_total' => 100,
    'total_tokens' => 100,
    'cost_usd' => 0.0001,
    'cost_estimated' => 0.0001,
    'request_type' => 'test'
);

$result = $wpdb->insert($table, $test_data);

if ($result) {
    echo "   ✅ INSERT exitoso - ID: " . $wpdb->insert_id . PHP_EOL;
} else {
    echo "   ❌ INSERT falló" . PHP_EOL;
    echo "   Error: " . $wpdb->last_error . PHP_EOL;
    echo "   Query: " . $wpdb->last_query . PHP_EOL;
}

// 3. Verificar estructura de la tabla
echo PHP_EOL . "3. ESTRUCTURA DE LA TABLA:" . PHP_EOL;
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach ($columns as $col) {
    echo "   " . $col->Field . " | " . $col->Type . " | " . ($col->Null === 'YES' ? 'NULL' : 'NOT NULL') . " | Default: " . ($col->Default ?? 'none') . PHP_EOL;
}

// 4. Verificar nuevo total
echo PHP_EOL . "4. NUEVO TOTAL:" . PHP_EOL;
$nuevo_total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "   Total ahora: $nuevo_total" . PHP_EOL;

// 5. Verificar si hay registros de hoy
echo PHP_EOL . "5. REGISTROS DE HOY:" . PHP_EOL;
$hoy = $wpdb->get_results("SELECT * FROM $table WHERE DATE(created_at) = CURDATE()");
echo "   Registros hoy: " . count($hoy) . PHP_EOL;

echo PHP_EOL . "=== FIN ===" . PHP_EOL;
