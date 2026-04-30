<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');
global $wpdb;

echo "=== Estado del Sistema de Tracking OpenAI ===\n\n";

// 1. Verificar si existe la tabla
$tableExists = $wpdb->get_var("SHOW TABLES LIKE 'ai_usage_log'");

if (!$tableExists) {
    echo "❌ La tabla 'ai_usage_log' NO existe.\n";
    echo "   Ejecutando creación automática...\n\n";
    
    // Crear la tabla
    $sql = "CREATE TABLE IF NOT EXISTS ai_usage_log (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        client_identifier VARCHAR(100) DEFAULT NULL,
        prompt_tokens INT DEFAULT 0,
        completion_tokens INT DEFAULT 0,
        total_tokens INT DEFAULT 0,
        model_used VARCHAR(50) NOT NULL,
        cost_estimated DECIMAL(10, 6) DEFAULT 0.000000,
        request_endpoint VARCHAR(100) DEFAULT 'chat/completions',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, created_at),
        INDEX idx_client (client_identifier)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $wpdb->query($sql);
    
    $tableExists = $wpdb->get_var("SHOW TABLES LIKE 'ai_usage_log'");
    if ($tableExists) {
        echo "✅ Tabla creada exitosamente.\n\n";
    } else {
        echo "❌ Error al crear la tabla: " . $wpdb->last_error . "\n";
        exit;
    }
} else {
    echo "✅ La tabla 'ai_usage_log' existe.\n\n";
}

// 2. Contar registros
$count = $wpdb->get_var("SELECT COUNT(*) FROM ai_usage_log");
echo "📊 Registros totales: $count\n\n";

if ($count > 0) {
    // Mostrar resumen
    $stats = $wpdb->get_results("
        SELECT 
            COALESCE(client_identifier, 'Sin identificar') as cliente,
            COUNT(*) as peticiones,
            SUM(total_tokens) as tokens,
            ROUND(SUM(cost_estimated), 4) as costo_usd
        FROM ai_usage_log
        GROUP BY client_identifier
        ORDER BY costo_usd DESC
        LIMIT 10
    ", ARRAY_A);
    
    echo "=== Consumo por Cliente ===\n";
    echo str_pad("Cliente", 30) . str_pad("Peticiones", 12) . str_pad("Tokens", 12) . "Costo USD\n";
    echo str_repeat("-", 70) . "\n";
    
    foreach ($stats as $row) {
        echo str_pad($row['cliente'], 30);
        echo str_pad($row['peticiones'], 12);
        echo str_pad(number_format($row['tokens']), 12);
        echo "$" . $row['costo_usd'] . "\n";
    }
    
    // Total
    $total = $wpdb->get_row("SELECT SUM(total_tokens) as tokens, SUM(cost_estimated) as costo FROM ai_usage_log", ARRAY_A);
    echo str_repeat("-", 70) . "\n";
    echo str_pad("TOTAL", 30) . str_pad("", 12) . str_pad(number_format($total['tokens']), 12) . "$" . round($total['costo'], 4) . "\n";
    
} else {
    echo "ℹ️  La tabla está vacía.\n";
    echo "   Los flujos actuales de N8N van directo a OpenAI.\n";
    echo "   Solo los NUEVOS flujos que usen el proxy registrarán datos aquí.\n\n";
    echo "   ¿Quieres hacer una prueba? Ejecuta:\n";
    echo "   php test-proxy-tracking.php\n";
}
