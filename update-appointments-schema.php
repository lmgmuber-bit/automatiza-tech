<?php
/**
 * Script para actualizar el esquema de la tabla de citas
 * Ejecutar una sola vez visitando: http://localhost/automatiza-tech/update-appointments-schema.php
 */

require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_leads';

// Agregar columnas nuevas si no existen
$columns_to_add = array(
    'event_id' => "ALTER TABLE {$table_name} ADD COLUMN event_id VARCHAR(255) NULL AFTER meet_link",
    'source' => "ALTER TABLE {$table_name} ADD COLUMN source VARCHAR(50) DEFAULT 'web' AFTER event_id",
    'status' => "ALTER TABLE {$table_name} ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER source",
    'updated_at' => "ALTER TABLE {$table_name} ADD COLUMN updated_at DATETIME NULL AFTER created_at",
    'cancelled_at' => "ALTER TABLE {$table_name} ADD COLUMN cancelled_at DATETIME NULL AFTER updated_at"
);

echo "<h1>Actualizando esquema de tabla de citas...</h1>";

foreach ($columns_to_add as $column => $query) {
    // Verificar si la columna ya existe
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM {$table_name} LIKE %s",
        $column
    ));
    
    if (empty($column_exists)) {
        $result = $wpdb->query($query);
        if ($result === false) {
            echo "<p style='color: red;'>❌ Error al agregar columna '{$column}': {$wpdb->last_error}</p>";
        } else {
            echo "<p style='color: green;'>✅ Columna '{$column}' agregada exitosamente</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Columna '{$column}' ya existe</p>";
    }
}

// Crear índices para mejorar performance
$indexes = array(
    "CREATE INDEX idx_email ON {$table_name}(email)",
    "CREATE INDEX idx_phone ON {$table_name}(phone)",
    "CREATE INDEX idx_event_id ON {$table_name}(event_id)",
    "CREATE INDEX idx_scheduled_date ON {$table_name}(scheduled_date)",
    "CREATE INDEX idx_status ON {$table_name}(status)"
);

echo "<h2>Creando índices...</h2>";

foreach ($indexes as $index_query) {
    $result = $wpdb->query($index_query);
    if ($result === false && strpos($wpdb->last_error, 'Duplicate key name') === false) {
        echo "<p style='color: orange;'>⚠️ {$wpdb->last_error}</p>";
    } else {
        echo "<p style='color: green;'>✅ Índice creado</p>";
    }
}

echo "<h2>✅ Actualización completada</h2>";
echo "<p><strong>Estructura actual de la tabla:</strong></p>";

$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>{$col->Field}</td>";
    echo "<td>{$col->Type}</td>";
    echo "<td>{$col->Null}</td>";
    echo "<td>{$col->Key}</td>";
    echo "<td>{$col->Default}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='/wp-admin/admin.php?page=automatiza-appointments'>Ir a ver citas en el admin</a></p>";
