<?php
/**
 * Verificar planes en la base de datos
 */

// Cargar WordPress
require_once('wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'automatiza_services';

echo "<h2>🔍 Verificación de Planes en Base de Datos</h2>";

// Verificar si la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

if (!$table_exists) {
    echo "<p style='color: red;'>❌ La tabla $table_name NO existe</p>";
    echo "<p>Necesitas crear la tabla primero.</p>";
    exit;
}

echo "<p style='color: green;'>✅ La tabla $table_name existe</p><br>";

// Obtener todos los servicios
$all_services = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");

echo "<h3>📊 Total de servicios en la tabla: " . count($all_services) . "</h3>";

if (empty($all_services)) {
    echo "<p style='color: orange;'>⚠️ No hay ningún servicio en la tabla</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #667eea; color: white;'>
            <th>ID</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Precio</th>
            <th>Activo</th>
            <th>Destacado</th>
          </tr>";
    
    foreach ($all_services as $service) {
        $active_status = $service->active == 1 ? '✅ Sí' : '❌ No';
        $featured_status = $service->is_featured == 1 ? '⭐ Sí' : '';
        $bg_color = $service->active == 1 ? '#e8f5e9' : '#ffebee';
        
        echo "<tr style='background: $bg_color;'>
                <td>{$service->id}</td>
                <td><strong>{$service->name}</strong></td>
                <td>{$service->category}</td>
                <td>\${$service->price} {$service->currency}</td>
                <td>{$active_status}</td>
                <td>{$featured_status}</td>
              </tr>";
    }
    echo "</table><br>";
}

// Verificar planes específicos de pricing activos
$pricing_plans = $wpdb->get_results("SELECT * FROM $table_name WHERE category = 'pricing' AND active = 1");

echo "<h3>💼 Planes de Pricing Activos: " . count($pricing_plans) . "</h3>";

if (empty($pricing_plans)) {
    echo "<p style='color: red;'>❌ No hay planes con category='pricing' y active=1</p>";
    echo "<p><strong>Solución:</strong> Necesitas activar los planes o cambiar su categoría a 'pricing'</p>";
    
    // Mostrar planes que podrían ser de pricing
    $potential_plans = $wpdb->get_results("SELECT * FROM $table_name WHERE active = 1");
    
    if (!empty($potential_plans)) {
        echo "<h4>🔧 Planes activos que podrías usar (cambiar category a 'pricing'):</h4>";
        echo "<ul>";
        foreach ($potential_plans as $plan) {
            echo "<li><strong>{$plan->name}</strong> - Categoría actual: '{$plan->category}' (ID: {$plan->id})</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: green;'>✅ Planes encontrados y listos para usar en correos:</p>";
    echo "<ul>";
    foreach ($pricing_plans as $plan) {
        echo "<li><strong>{$plan->name}</strong> - \${$plan->price} {$plan->currency}/mes</li>";
    }
    echo "</ul>";
}

echo "<hr><h3>🛠️ Scripts de Corrección</h3>";

// Script para activar todos los planes de pricing
echo "<h4>Opción 1: Activar todos los planes de pricing</h4>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
echo "UPDATE {$table_name} SET active = 1 WHERE category = 'pricing';";
echo "</pre>";

// Script para cambiar categoría de planes activos
echo "<h4>Opción 2: Cambiar categoría de planes activos a 'pricing'</h4>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";
echo "UPDATE {$table_name} SET category = 'pricing' WHERE active = 1;";
echo "</pre>";

echo "<hr>";
echo "<p><a href='wp-admin/admin.php?page=automatiza-tech-services' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📊 Ir al Panel de Servicios</a></p>";
echo "<p><a href='verify-email-setup.php' style='background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Volver a Verificación</a></p>";
?>
