<?php
require_once __DIR__ . '/at-maintenance-guard.php';

/**
 * Script para agregar columna google_event_id a las tablas de seguimientos y demos
 * Ejecutar UNA vez: https://automatizatech.cl/add-google-event-id-column.php
 */

require_once('wp-load.php');

global $wpdb;

// ========== TABLA DE SEGUIMIENTOS ==========
$followup_table = $wpdb->prefix . 'automatiza_followup_meetings';

echo "<h2>1. Tabla de Seguimientos (followup_meetings)</h2>";

$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $followup_table LIKE 'google_event_id'");

if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $followup_table ADD COLUMN google_event_id VARCHAR(255) DEFAULT '' AFTER meet_link");
    
    if ($result !== false) {
        echo "<p style='color:green;'>✅ Columna google_event_id agregada a seguimientos</p>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ La columna ya existe en seguimientos</p>";
}

// ========== TABLA DE DEMOS/LEADS ==========
$leads_table = $wpdb->prefix . 'automatiza_leads';

echo "<h2>2. Tabla de DEMOs (automatiza_leads)</h2>";

$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $leads_table LIKE 'google_event_id'");

if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE $leads_table ADD COLUMN google_event_id VARCHAR(255) DEFAULT '' AFTER token");
    
    if ($result !== false) {
        echo "<p style='color:green;'>✅ Columna google_event_id agregada a DEMOs</p>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color:blue;'>ℹ️ La columna ya existe en DEMOs</p>";
}

// ========== MOSTRAR ESTRUCTURAS ==========
echo "<h3>Estructura tabla seguimientos:</h3>";
echo "<pre>";
$columns = $wpdb->get_results("DESCRIBE $followup_table");
foreach ($columns as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}
echo "</pre>";

echo "<h3>Estructura tabla DEMOs:</h3>";
echo "<pre>";
$columns = $wpdb->get_results("DESCRIBE $leads_table");
foreach ($columns as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}
echo "</pre>";

echo "<p><strong>Ahora puedes eliminar este archivo.</strong></p>";
?>
