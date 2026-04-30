<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');
global $wpdb;

echo "=== CLASIFICACIÓN DE CONSUMO POR TIPO ===\n\n";

// Internos
echo "🏢 INTERNO (AutomatizaTech)\n";
$internos = $wpdb->get_results("SELECT client_identifier, SUM(total_tokens) as tokens, SUM(cost_estimated) as costo FROM ai_usage_log WHERE client_identifier LIKE 'interno_%' GROUP BY client_identifier", ARRAY_A);
foreach($internos as $r) { 
    echo "   " . str_pad($r['client_identifier'], 30) . number_format($r['tokens']) . " tokens, $" . round($r['costo'],4) . " USD\n"; 
}
if(empty($internos)) echo "   (sin registros)\n";

echo "\n👥 CLIENTES (Facturables)\n";
$clientes = $wpdb->get_results("SELECT client_identifier, SUM(total_tokens) as tokens, SUM(cost_estimated) as costo FROM ai_usage_log WHERE client_identifier LIKE 'cliente_%' GROUP BY client_identifier ORDER BY costo DESC", ARRAY_A);
foreach($clientes as $r) { 
    echo "   " . str_pad($r['client_identifier'], 30) . number_format($r['tokens']) . " tokens, $" . round($r['costo'],4) . " USD\n"; 
}
if(empty($clientes)) echo "   (sin registros)\n";

echo "\n🧪 DEMOS (Prospectos)\n";
$demos = $wpdb->get_results("SELECT client_identifier, SUM(total_tokens) as tokens, SUM(cost_estimated) as costo FROM ai_usage_log WHERE client_identifier LIKE 'demo_%' GROUP BY client_identifier", ARRAY_A);
foreach($demos as $r) { 
    echo "   " . str_pad($r['client_identifier'], 30) . number_format($r['tokens']) . " tokens, $" . round($r['costo'],4) . " USD\n"; 
}
if(empty($demos)) echo "   (sin registros)\n";

echo "\n❓ SIN CLASIFICAR\n";
$otros = $wpdb->get_results("SELECT COALESCE(client_identifier, 'NULL') as client_identifier, SUM(total_tokens) as tokens, SUM(cost_estimated) as costo FROM ai_usage_log WHERE (client_identifier NOT LIKE 'interno_%' AND client_identifier NOT LIKE 'cliente_%' AND client_identifier NOT LIKE 'demo_%') OR client_identifier IS NULL GROUP BY client_identifier", ARRAY_A);
foreach($otros as $r) { 
    echo "   " . str_pad($r['client_identifier'], 30) . number_format($r['tokens']) . " tokens, $" . round($r['costo'],4) . " USD\n"; 
}
if(empty($otros)) echo "   (sin registros)\n";

// Totales
echo "\n" . str_repeat("═", 50) . "\n";
echo "📊 RESUMEN POR CATEGORÍA (Este Mes)\n";
echo str_repeat("═", 50) . "\n\n";

$totales = $wpdb->get_row("SELECT 
    SUM(CASE WHEN client_identifier LIKE 'interno_%' THEN total_tokens ELSE 0 END) as tokens_interno,
    SUM(CASE WHEN client_identifier LIKE 'interno_%' THEN cost_estimated ELSE 0 END) as costo_interno,
    SUM(CASE WHEN client_identifier LIKE 'cliente_%' THEN total_tokens ELSE 0 END) as tokens_clientes,
    SUM(CASE WHEN client_identifier LIKE 'cliente_%' THEN cost_estimated ELSE 0 END) as costo_clientes,
    SUM(CASE WHEN client_identifier LIKE 'demo_%' THEN total_tokens ELSE 0 END) as tokens_demos,
    SUM(CASE WHEN client_identifier LIKE 'demo_%' THEN cost_estimated ELSE 0 END) as costo_demos,
    SUM(total_tokens) as tokens_total,
    SUM(cost_estimated) as costo_total
FROM ai_usage_log
WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())", ARRAY_A);

echo "Categoría               Tokens          Costo OpenAI    Facturar (+30%)\n";
echo str_repeat("-", 70) . "\n";
printf("🏢 Interno              %-15s \$%-14s (no facturar)\n", number_format($totales['tokens_interno']), round($totales['costo_interno'], 4));
printf("👥 Clientes             %-15s \$%-14s \$%s\n", number_format($totales['tokens_clientes']), round($totales['costo_clientes'], 4), round($totales['costo_clientes'] * 1.3, 2));
printf("🧪 Demos                %-15s \$%-14s (absorber)\n", number_format($totales['tokens_demos']), round($totales['costo_demos'], 4));
echo str_repeat("-", 70) . "\n";
printf("TOTAL                   %-15s \$%-14s\n", number_format($totales['tokens_total']), round($totales['costo_total'], 4));

echo "\n💡 Leyenda:\n";
echo "   - interno_*  → Uso propio de AutomatizaTech (no se cobra)\n";
echo "   - cliente_*  → Clientes activos (FACTURAR con margen)\n";
echo "   - demo_*     → Demos a prospectos (costo de adquisición)\n";
