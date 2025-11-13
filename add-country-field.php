<?php
/**
 * Script para agregar campo de país a la tabla de clientes
 * Ejecutar una sola vez para actualizar la estructura de la base de datos
 */

require_once('wp-load.php');

global $wpdb;

// Nombre de la tabla
$table_name = $wpdb->prefix . 'automatiza_tech_clients';

// Verificar si la columna ya existe
$column_exists = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = 'country'",
        DB_NAME,
        $table_name
    )
);

if (empty($column_exists)) {
    // Agregar columna country después de phone
    $sql = "ALTER TABLE `{$table_name}` 
            ADD COLUMN `country` varchar(2) COLLATE utf8mb4_unicode_520_ci DEFAULT 'CL' 
            COMMENT 'Código ISO de 2 letras del país' 
            AFTER `phone`";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ Columna 'country' agregada exitosamente a la tabla {$table_name}\n";
        echo "Por defecto, todos los clientes existentes se marcaron como Chile (CL)\n";
        
        // Actualizar clientes existentes según su código de WhatsApp
        $updated = $wpdb->query("
            UPDATE `{$table_name}` 
            SET country = CASE 
                WHEN phone LIKE '+56%' THEN 'CL'
                WHEN phone LIKE '+1%' THEN 'US'
                WHEN phone LIKE '+54%' THEN 'AR'
                WHEN phone LIKE '+57%' THEN 'CO'
                WHEN phone LIKE '+52%' THEN 'MX'
                WHEN phone LIKE '+51%' THEN 'PE'
                WHEN phone LIKE '+34%' THEN 'ES'
                WHEN phone LIKE '+55%' THEN 'BR'
                ELSE 'CL'
            END
        ");
        
        echo "✅ Actualizado país de {$updated} cliente(s) basado en código telefónico\n\n";
        
        // Mostrar resumen
        $summary = $wpdb->get_results("
            SELECT country, COUNT(*) as total 
            FROM `{$table_name}` 
            GROUP BY country
        ");
        
        echo "📊 Resumen de clientes por país:\n";
        foreach ($summary as $row) {
            $country_names = array(
                'CL' => 'Chile',
                'US' => 'Estados Unidos',
                'AR' => 'Argentina',
                'CO' => 'Colombia',
                'MX' => 'México',
                'PE' => 'Perú',
                'ES' => 'España',
                'BR' => 'Brasil'
            );
            $country_name = isset($country_names[$row->country]) ? $country_names[$row->country] : 'Otro';
            echo "   {$row->country} ({$country_name}): {$row->total} cliente(s)\n";
        }
        
    } else {
        echo "❌ Error al agregar la columna: " . $wpdb->last_error . "\n";
    }
} else {
    echo "ℹ️ La columna 'country' ya existe en la tabla {$table_name}\n";
    
    // Mostrar resumen actual
    $summary = $wpdb->get_results("
        SELECT country, COUNT(*) as total 
        FROM `{$table_name}` 
        GROUP BY country
    ");
    
    echo "\n📊 Resumen actual de clientes por país:\n";
    foreach ($summary as $row) {
        $country_names = array(
            'CL' => 'Chile',
            'US' => 'Estados Unidos',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'MX' => 'México',
            'PE' => 'Perú',
            'ES' => 'España',
            'BR' => 'Brasil'
        );
        $country_name = isset($country_names[$row->country]) ? $country_names[$row->country] : 'Otro';
        echo "   {$row->country} ({$country_name}): {$row->total} cliente(s)\n";
    }
}

echo "\n✨ Proceso completado.\n";
echo "Ahora el sistema detectará automáticamente el país del cliente para generar facturas con la moneda correcta:\n";
echo "   🇨🇱 Chile (CL) → Pesos Chilenos (CLP) con IVA 19%\n";
echo "   🌎 Otros países → Dólares Americanos (USD) sin IVA\n";
