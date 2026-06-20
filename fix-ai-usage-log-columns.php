<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para actualizar la tabla ai_usage_log con las columnas correctas
 * Ejecutar en PROD después de setup-maxtech-tables.php
 */
require_once 'wp-load.php';
global $wpdb;

$table_name = $wpdb->prefix . 'ai_usage_log';

echo "=== Actualizando tabla ai_usage_log ===" . PHP_EOL . PHP_EOL;

// Verificar si la tabla existe
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$exists) {
    echo "❌ La tabla no existe. Ejecuta primero setup-maxtech-tables.php" . PHP_EOL;
    exit;
}

// Columnas que necesita el código
$columns_needed = array(
    'client_identifier' => "ALTER TABLE $table_name ADD COLUMN client_identifier VARCHAR(100) DEFAULT 'interno_aria' AFTER user_email",
    'total_tokens' => "ALTER TABLE $table_name ADD COLUMN total_tokens INT(11) DEFAULT 0 AFTER tokens_total",
    'model_used' => "ALTER TABLE $table_name ADD COLUMN model_used VARCHAR(50) DEFAULT NULL AFTER model",
    'cost_estimated' => "ALTER TABLE $table_name ADD COLUMN cost_estimated DECIMAL(10,6) DEFAULT 0.000000 AFTER cost_usd",
    'prompt_tokens' => "ALTER TABLE $table_name ADD COLUMN prompt_tokens INT(11) DEFAULT 0 AFTER tokens_input",
    'completion_tokens' => "ALTER TABLE $table_name ADD COLUMN completion_tokens INT(11) DEFAULT 0 AFTER tokens_output",
    'request_endpoint' => "ALTER TABLE $table_name ADD COLUMN request_endpoint VARCHAR(100) DEFAULT NULL AFTER endpoint"
);

foreach ($columns_needed as $column => $alter_sql) {
    $col_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE '$column'");
    
    if ($col_exists) {
        echo "✅ Columna '$column' ya existe" . PHP_EOL;
    } else {
        echo "⏳ Agregando columna '$column'..." . PHP_EOL;
        $result = $wpdb->query($alter_sql);
        if ($result !== false) {
            echo "   ✅ Agregada correctamente" . PHP_EOL;
        } else {
            echo "   ❌ Error: " . $wpdb->last_error . PHP_EOL;
        }
    }
}

// Sincronizar valores entre columnas nuevas y antiguas
echo PHP_EOL . "=== Sincronizando valores ===" . PHP_EOL;

// total_tokens = tokens_total
$wpdb->query("UPDATE $table_name SET total_tokens = tokens_total WHERE total_tokens = 0 AND tokens_total > 0");
echo "✅ total_tokens sincronizado" . PHP_EOL;

// model_used = model
$wpdb->query("UPDATE $table_name SET model_used = model WHERE model_used IS NULL AND model IS NOT NULL");
echo "✅ model_used sincronizado" . PHP_EOL;

// cost_estimated = cost_usd
$wpdb->query("UPDATE $table_name SET cost_estimated = cost_usd WHERE cost_estimated = 0 AND cost_usd > 0");
echo "✅ cost_estimated sincronizado" . PHP_EOL;

// request_endpoint = endpoint
$wpdb->query("UPDATE $table_name SET request_endpoint = endpoint WHERE request_endpoint IS NULL AND endpoint IS NOT NULL");
echo "✅ request_endpoint sincronizado" . PHP_EOL;

// Mostrar estructura final
echo PHP_EOL . "=== Estructura final de la tabla ===" . PHP_EOL;
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
foreach ($columns as $col) {
    echo $col->Field . " | " . $col->Type . PHP_EOL;
}

echo PHP_EOL . "=== Completado ===" . PHP_EOL;
