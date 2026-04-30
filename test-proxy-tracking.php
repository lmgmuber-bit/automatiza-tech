<?php
/**
 * Prueba del sistema de tracking
 * Hace una llamada real a OpenAI y registra el consumo
 */
require_once('wp-load.php');
require_once('openai-controller.php');

echo "=== Prueba de Tracking OpenAI ===\n\n";

$controller = new OpenAIController();

// Simular 3 llamadas de diferentes "clientes"
$pruebas = [
    [
        'client' => 'cliente_kells_capilar',
        'mensaje' => 'Hola, ¿cuál es el horario de atención?'
    ],
    [
        'client' => 'interno_automatizatech',
        'mensaje' => '¿Qué es la automatización de procesos?'
    ],
    [
        'client' => 'demo_mg_muebles',
        'mensaje' => 'Dime un saludo corto para una mueblería'
    ]
];

foreach ($pruebas as $i => $prueba) {
    echo "📤 Prueba " . ($i + 1) . ": {$prueba['client']}\n";
    echo "   Mensaje: {$prueba['mensaje']}\n";
    
    $messages = [
        ['role' => 'system', 'content' => 'Responde en máximo 20 palabras.'],
        ['role' => 'user', 'content' => $prueba['mensaje']]
    ];
    
    $response = $controller->chatCompletion(1, $messages, 'gpt-4o-mini', $prueba['client']);
    
    if (isset($response['error'])) {
        echo "   ❌ Error: {$response['error']}\n\n";
    } else {
        $reply = $response['choices'][0]['message']['content'];
        $tokens = $response['usage']['total_tokens'];
        echo "   ✅ Respuesta: " . substr($reply, 0, 50) . "...\n";
        echo "   📊 Tokens: $tokens\n\n";
    }
    
    // Pausa para no saturar
    sleep(1);
}

echo "=== Verificando registros en BD ===\n\n";

global $wpdb;
$registros = $wpdb->get_results("
    SELECT client_identifier, model_used, total_tokens, cost_estimated, created_at 
    FROM ai_usage_log 
    ORDER BY id DESC 
    LIMIT 5
", ARRAY_A);

if ($registros) {
    echo str_pad("Cliente", 25) . str_pad("Modelo", 15) . str_pad("Tokens", 10) . "Costo USD\n";
    echo str_repeat("-", 60) . "\n";
    
    foreach ($registros as $r) {
        echo str_pad($r['client_identifier'], 25);
        echo str_pad($r['model_used'], 15);
        echo str_pad($r['total_tokens'], 10);
        echo "$" . $r['cost_estimated'] . "\n";
    }
}

echo "\n✅ ¡Listo! Ahora puedes ver el dashboard en:\n";
echo "   http://localhost/automatiza-tech/admin-ai-dashboard.php\n";
