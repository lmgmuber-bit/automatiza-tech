<?php
/**
 * Script para agregar campo de descuento a la tabla de servicios
 * Ejecutar una vez: http://localhost/automatiza-tech/add-discount-field.php
 */

require_once(__DIR__ . '/wp-load.php');

// Verificar que solo admin pueda ejecutar
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Acceso denegado. Debes iniciar sesión como administrador.');
}

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

echo "<h1>🏷️ Agregando Campo de Descuento</h1>";
echo "<pre>";

// Verificar si la columna ya existe
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'discount_percent'");

if (empty($column_exists)) {
    // Agregar columna discount_percent
    $result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN discount_percent decimal(5,2) DEFAULT 0.00 AFTER price_clp");
    
    if ($result !== false) {
        echo "✅ Columna 'discount_percent' agregada exitosamente\n";
    } else {
        echo "❌ Error al agregar columna: " . $wpdb->last_error . "\n";
    }
} else {
    echo "ℹ️ La columna 'discount_percent' ya existe\n";
}

// Mostrar estructura actual de la tabla
echo "\n📋 Estructura actual de la tabla:\n";
echo str_repeat("-", 60) . "\n";

$columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
foreach ($columns as $column) {
    echo sprintf("  %-20s %-20s %s\n", 
        $column->Field, 
        $column->Type, 
        $column->Null === 'YES' ? 'NULL' : 'NOT NULL'
    );
}

echo str_repeat("-", 60) . "\n";

// Limpiar cache
wp_cache_flush();

echo "\n✅ Campo de descuento listo para usar\n";
echo "</pre>";

echo '<p><a href="' . admin_url('admin.php?page=automatiza-services') . '" class="button button-primary" style="padding: 10px 20px; font-size: 16px;">📋 Ir a Gestión de Servicios</a></p>';
?>
