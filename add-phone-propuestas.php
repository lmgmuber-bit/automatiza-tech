<?php
/**
 * Agregar campo phone a la tabla propuestas
 * Ejecutar una sola vez
 */
require_once('wp-load.php');
global $wpdb;

$table = $wpdb->prefix . 'automatiza_propuestas';

echo "=== Agregando campo phone a $table ===\n\n";

// Verificar si ya existe
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table");

if (in_array('phone', $columns)) {
    echo "✅ El campo 'phone' ya existe en la tabla.\n";
} else {
    $result = $wpdb->query("ALTER TABLE $table ADD COLUMN phone varchar(50) DEFAULT '' AFTER company_name");
    if ($result !== false) {
        echo "✅ Campo 'phone' agregado correctamente.\n";
    } else {
        echo "❌ Error al agregar campo: " . $wpdb->last_error . "\n";
    }
}

// Mostrar estructura actualizada
echo "\n=== Estructura actual de la tabla ===\n";
$cols = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach($cols as $c) {
    echo "  - " . $c->Field . " (" . $c->Type . ")\n";
}

echo "\n✅ Listo! Ahora puedes actualizar los teléfonos de los clientes en phpMyAdmin.\n";
?>
