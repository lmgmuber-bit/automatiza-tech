<?php
/**
 * Script de Setup para Producción — Proxy OpenAI + Tracking
 * 
 * Ejecutar UNA VEZ en producción:
 * https://automatizatech.cl/setup-proxy-production.php
 * 
 * Requiere estar logueado como admin de WordPress.
 */
require_once(__DIR__ . '/wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('⛔ Acceso denegado. Requiere login como administrador.');
}

global $wpdb;
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Setup Proxy OpenAI — Producción</h1><pre style='font-family:monospace;font-size:14px;'>";

// ═══════════════════════════════════════
// 1. Verificar OPENAI_API_KEY
// ═══════════════════════════════════════
echo "\n<b>1. OPENAI_API_KEY</b>\n";
if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
    echo "   ✅ Definida: " . substr(OPENAI_API_KEY, 0, 20) . "...\n";
} else {
    echo "   ❌ NO DEFINIDA\n";
    echo "   ⚠️  Agregar a wp-config.php:\n";
    echo "      define('OPENAI_API_KEY', 'sk-proj-...');\n";
}

// ═══════════════════════════════════════
// 2. Crear tabla ai_usage_log
// ═══════════════════════════════════════
echo "\n<b>2. Tabla ai_usage_log</b>\n";

$table_exists = $wpdb->get_var("SHOW TABLES LIKE 'ai_usage_log'");
if ($table_exists) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM ai_usage_log");
    echo "   ✅ Ya existe ($count registros)\n";
} else {
    echo "   ⏳ Creando tabla...\n";
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS ai_usage_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL DEFAULT 0,
        client_identifier VARCHAR(100) NOT NULL DEFAULT '',
        prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
        completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
        total_tokens INT UNSIGNED NOT NULL DEFAULT 0,
        model_used VARCHAR(50) NOT NULL DEFAULT '',
        cost_estimated DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
        request_endpoint VARCHAR(100) DEFAULT 'chat/completions',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_identifier),
        INDEX idx_created (created_at),
        INDEX idx_user_date (user_id, created_at)
    ) ENGINE=InnoDB $charset_collate";
    
    $result = $wpdb->query($sql);
    if ($result !== false) {
        echo "   ✅ Tabla creada exitosamente\n";
    } else {
        echo "   ❌ Error: " . $wpdb->last_error . "\n";
    }
}

// También verificar/crear con prefijo wp_
$wp_table = $wpdb->prefix . 'ai_usage_log';
$wp_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$wp_table'");
echo "\n   Tabla {$wp_table}: " . ($wp_table_exists ? "✅ Existe" : "ℹ️ No existe (ok, se usa sin prefijo)") . "\n";

// ═══════════════════════════════════════
// 3. Verificar archivos del proxy
// ═══════════════════════════════════════
echo "\n<b>3. Archivos del proxy</b>\n";
$files = [
    'api-chat-proxy.php' => 'Proxy principal',
    'openai-controller.php' => 'Controller OpenAI',
    'admin-ai-dashboard.php' => 'Dashboard de consumo',
    'reporte-consumo-ai.php' => 'Reporte por categoría',
];
foreach ($files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    $bom = $exists && (substr(file_get_contents($path), 0, 3) === "\xEF\xBB\xBF");
    echo "   " . ($exists ? "✅" : "❌") . " $file ($desc)";
    if ($bom) echo " ⚠️ TIENE BOM";
    echo "\n";
}

// ═══════════════════════════════════════
// 4. Verificar WP_DEBUG_DISPLAY
// ═══════════════════════════════════════
echo "\n<b>4. Configuración de errores</b>\n";
echo "   WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? "⚠️ ACTIVADO" : "✅ Desactivado") . "\n";
echo "   WP_DEBUG_DISPLAY: " . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? "⚠️ ACTIVADO (puede emitir HTML en JSON)" : "✅ Desactivado") . "\n";
echo "   display_errors: " . (ini_get('display_errors') ? "⚠️ ACTIVADO" : "✅ Desactivado") . "\n";

if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
    echo "\n   ⚠️  RECOMENDACIÓN: Agregar a wp-config.php en producción:\n";
    echo "      define('WP_DEBUG', false);\n";
    echo "      define('WP_DEBUG_DISPLAY', false);\n";
    echo "      @ini_set('display_errors', 0);\n";
}

// ═══════════════════════════════════════
// 5. Test de conexión a OpenAI
// ═══════════════════════════════════════
echo "\n<b>5. Test de conexión a OpenAI</b>\n";
if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . OPENAI_API_KEY]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "   ❌ Error de conexión: $curlError\n";
    } elseif ($httpCode === 200) {
        echo "   ✅ API Key válida — conexión exitosa (HTTP 200)\n";
    } elseif ($httpCode === 401) {
        echo "   ❌ API Key INVÁLIDA o EXPIRADA (HTTP 401)\n";
    } else {
        echo "   ⚠️ Respuesta inesperada: HTTP $httpCode\n";
    }
} else {
    echo "   ⏭️  Omitido (sin API Key)\n";
}

// ═══════════════════════════════════════
// 6. Test completo del proxy
// ═══════════════════════════════════════
echo "\n<b>6. Test del proxy (simulando llamada de PetsGo)</b>\n";
if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
    require_once(__DIR__ . '/openai-controller.php');
    $controller = new OpenAIController();
    
    echo "   Controller creado: ✅\n";
    echo "   Tracking ready: " . ($controller->isTrackingReady() ? "✅" : "⚠️ No (tabla puede no existir)") . "\n";
    echo "   API Key: " . (empty($controller->getApiKey()) ? "❌ Vacía" : "✅ " . substr($controller->getApiKey(), 0, 15) . "...") . "\n";
    
    // Mini test con OpenAI
    echo "\n   Enviando test a OpenAI (gpt-4o-mini)...\n";
    $testResponse = $controller->chatCompletion(
        1, // user_id
        [
            ['role' => 'system', 'content' => 'Responde solo "OK PROXY TEST" sin nada más.'],
            ['role' => 'user', 'content' => 'test']
        ],
        'gpt-4o-mini',
        'test_setup_script'
    );
    
    if (isset($testResponse['error'])) {
        echo "   ❌ Error: " . $testResponse['error'] . "\n";
    } elseif (isset($testResponse['choices'][0]['message']['content'])) {
        echo "   ✅ Respuesta: " . $testResponse['choices'][0]['message']['content'] . "\n";
        echo "   Tokens: " . ($testResponse['usage']['total_tokens'] ?? 'N/A') . "\n";
        echo "   Model: " . ($testResponse['model'] ?? 'N/A') . "\n";
        
        // Verificar si se registró en BD
        $lastRecord = $wpdb->get_row("SELECT * FROM ai_usage_log WHERE client_identifier = 'test_setup_script' ORDER BY created_at DESC LIMIT 1", ARRAY_A);
        if ($lastRecord) {
            echo "   ✅ Tracking registrado: ID #{$lastRecord['id']}, costo \${$lastRecord['cost_estimated']}\n";
        } else {
            echo "   ⚠️ Tracking NO registrado (tabla puede tener problemas)\n";
        }
    } else {
        echo "   ⚠️ Respuesta inesperada: " . json_encode($testResponse) . "\n";
    }
} else {
    echo "   ⏭️  Omitido (sin API Key)\n";
}

// ═══════════════════════════════════════
// Resumen
// ═══════════════════════════════════════
echo "\n" . str_repeat("═", 50) . "\n";
echo "<b>📋 RESUMEN</b>\n";
echo str_repeat("═", 50) . "\n\n";

$all_ok = true;
$checks = [
    ['OPENAI_API_KEY definida', defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)],
    ['Tabla ai_usage_log existe', !empty($wpdb->get_var("SHOW TABLES LIKE 'ai_usage_log'"))],
    ['api-chat-proxy.php presente', file_exists(__DIR__ . '/api-chat-proxy.php')],
    ['openai-controller.php presente', file_exists(__DIR__ . '/openai-controller.php')],
];

foreach ($checks as [$label, $ok]) {
    echo ($ok ? "✅" : "❌") . " $label\n";
    if (!$ok) $all_ok = false;
}

echo "\n";
if ($all_ok) {
    echo "🎉 <b>Todo configurado correctamente.</b> PetsGo debería funcionar.\n";
    echo "   URL del proxy: " . site_url('/api-chat-proxy.php') . "\n";
} else {
    echo "⚠️ <b>Hay items pendientes.</b> Revisar los errores arriba.\n";
}

echo "</pre>";
