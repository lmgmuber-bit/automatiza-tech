<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar campo phone a tabla propuestas
 * Ejecutar en: https://automatizatech.cl/add-phone-propuestas-prod.php
 */
require_once('wp-load.php');
global $wpdb;

header('Content-Type: text/plain; charset=utf-8');

$table = $wpdb->prefix . 'automatiza_propuestas';

echo "=== AGREGAR CAMPO PHONE A PROPUESTAS ===\n";
echo "Tabla: $table\n";
echo "Prefijo: " . $wpdb->prefix . "\n\n";

// Verificar si la tabla existe
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
if (!$exists) {
    echo "❌ ERROR: La tabla $table no existe\n";
    exit;
}

// Verificar columnas actuales
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table");
echo "Columnas actuales: " . implode(', ', $columns) . "\n\n";

if (in_array('phone', $columns)) {
    echo "✅ El campo 'phone' ya existe en la tabla.\n";
} else {
    echo "Agregando campo 'phone'...\n";
    $result = $wpdb->query("ALTER TABLE $table ADD COLUMN phone varchar(50) DEFAULT '' AFTER company_name");
    if ($result !== false) {
        echo "✅ Campo 'phone' agregado correctamente.\n";
    } else {
        echo "❌ Error al agregar campo: " . $wpdb->last_error . "\n";
    }
}

// Mostrar estructura actualizada
echo "\n=== ESTRUCTURA FINAL ===\n";
$cols = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach($cols as $c) {
    echo "  - " . $c->Field . " (" . $c->Type . ")\n";
}

// Mostrar algunos datos
echo "\n=== DATOS DE PRUEBA (últimos 5) ===\n";
$rows = $wpdb->get_results("SELECT id, client_email, client_name, company_name, phone FROM $table ORDER BY id DESC LIMIT 5");
foreach($rows as $r) {
    echo "ID {$r->id}: {$r->client_email} | {$r->client_name} | {$r->company_name} | Phone: " . ($r->phone ?: '(vacío)') . "\n";
}

echo "\n✅ LISTO - Ahora recarga la página de Seguimientos\n";
?>
