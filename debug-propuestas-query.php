<?php
require_once __DIR__ . '/at-maintenance-guard.php';

require_once('wp-load.php');
global $wpdb;

$table = $wpdb->prefix . 'automatiza_propuestas';

echo "=== DEBUG: Consulta de propuestas ===\n\n";

// La misma consulta que usa el módulo
$query = "SELECT id, client_name, client_email, company_name, phone,
          unique_link_id as proposal_number, created_at, status
          FROM $table 
          WHERE client_email IS NOT NULL AND client_email != ''
          ORDER BY created_at DESC
          LIMIT 50";

echo "Query: $query\n\n";

$results = $wpdb->get_results($query);

if ($wpdb->last_error) {
    echo "❌ ERROR: " . $wpdb->last_error . "\n";
} else {
    echo "✅ Total resultados: " . count($results) . "\n\n";
    
    if (count($results) > 0) {
        foreach($results as $r) {
            echo "ID: {$r->id}\n";
            echo "  Email: {$r->client_email}\n";
            echo "  Nombre: " . ($r->client_name ?: '(vacío)') . "\n";
            echo "  Empresa: " . ($r->company_name ?: '(vacío)') . "\n";
            echo "  Status: {$r->status}\n";
            echo "---\n";
        }
    } else {
        echo "⚠️ No hay resultados\n";
    }
}

// También verificar el prefijo correcto
echo "\n=== Info adicional ===\n";
echo "Prefijo DB: " . $wpdb->prefix . "\n";
echo "Tabla completa: " . $table . "\n";

// Verificar si la tabla existe
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
echo "Tabla existe: " . ($exists ? "SÍ" : "NO") . "\n";
?>
