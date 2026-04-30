<?php
/**
 * Verificar facturas en la base de datos
 */

// Cargar WordPress
require_once('wp-load.php');

global $wpdb;

echo "<h2>🔍 Verificación de Facturas en Base de Datos</h2>";
echo "<hr>";

// Nombre de la tabla
$invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';

echo "<h3>📋 Tabla: {$invoices_table}</h3>";

// Verificar si la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$invoices_table}'");

if ($table_exists) {
    echo "<p style='color: green;'>✅ La tabla existe</p>";
    
    // Contar facturas
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$invoices_table}");
    echo "<p><strong>Total de facturas:</strong> {$count}</p>";
    
    // Mostrar todas las facturas
    $invoices = $wpdb->get_results("SELECT * FROM {$invoices_table} ORDER BY created_at DESC");
    
    if ($invoices) {
        echo "<h3>📄 Facturas encontradas:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>ID</th>
                <th>Número Factura</th>
                <th>Cliente</th>
                <th>Plan</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Creada</th>
              </tr>";
        
        foreach ($invoices as $invoice) {
            $status_color = $invoice->status === 'active' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$invoice->id}</td>";
            echo "<td><strong>{$invoice->invoice_number}</strong></td>";
            echo "<td>{$invoice->client_name}</td>";
            echo "<td>{$invoice->plan_name}</td>";
            echo "<td>\${$invoice->total}</td>";
            echo "<td style='color: {$status_color};'>{$invoice->status}</td>";
            echo "<td>{$invoice->created_at}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Crear enlaces de prueba
        echo "<hr>";
        echo "<h3>🔗 Enlaces de Prueba:</h3>";
        foreach ($invoices as $invoice) {
            $validate_url = "validar-factura.php?id=" . urlencode($invoice->invoice_number);
            $download_url = "validar-factura.php?id=" . urlencode($invoice->invoice_number) . "&action=download";
            
            echo "<div style='margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 4px solid #06d6a0;'>";
            echo "<strong>{$invoice->invoice_number}</strong><br>";
            echo "<a href='{$validate_url}' target='_blank'>✅ Validar</a> | ";
            echo "<a href='{$download_url}' target='_blank'>📥 Descargar</a>";
            echo "</div>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ No hay facturas en la tabla</p>";
    }
    
    // Mostrar estructura de la tabla
    echo "<hr>";
    echo "<h3>🏗️ Estructura de la tabla:</h3>";
    $columns = $wpdb->get_results("DESCRIBE {$invoices_table}");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr style='background: #f0f0f0;'><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    echo "<p style='color: red;'>❌ La tabla NO existe</p>";
    echo "<p>Necesitas crear la tabla de facturas. Ejecuta el archivo SQL de migración.</p>";
}

// Verificar también la tabla de clientes
echo "<hr>";
$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
echo "<h3>👥 Tabla de Clientes: {$clients_table}</h3>";

$clients_exists = $wpdb->get_var("SHOW TABLES LIKE '{$clients_table}'");
if ($clients_exists) {
    $clients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$clients_table}");
    echo "<p style='color: green;'>✅ Tabla existe - Total clientes: {$clients_count}</p>";
    
    // Mostrar clientes con estado 'contracted'
    $contracted = $wpdb->get_results("SELECT id, name, email, status, created_at FROM {$clients_table} WHERE status = 'contracted' ORDER BY created_at DESC LIMIT 10");
    if ($contracted) {
        echo "<h4>Clientes contratados recientes:</h4>";
        echo "<ul>";
        foreach ($contracted as $client) {
            echo "<li><strong>{$client->name}</strong> ({$client->email}) - ID: {$client->id} - {$client->created_at}</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>❌ Tabla NO existe</p>";
}
