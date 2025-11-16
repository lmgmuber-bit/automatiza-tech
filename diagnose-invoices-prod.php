<?php
/**
 * Diagnóstico de Facturas en Producción
 * Verifica el estado de las facturas y la sincronización con clientes
 */

// Cargar WordPress
require_once('wp-load.php');

// Verificar que sea admin
if (!current_user_can('administrator')) {
    die('Solo administradores pueden ejecutar este diagnóstico');
}

global $wpdb;

echo "<h1>🔍 Diagnóstico de Sistema de Facturas</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .ok { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ffc107; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #007bff; color: white; }
    .code { background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0; font-family: monospace; }
</style>";

// 1. Verificar tablas
echo "<div class='section'>";
echo "<h2>📊 1. Verificación de Tablas</h2>";

$tables_to_check = [
    'automatiza_tech_clients' => 'Tabla de Clientes',
    'automatiza_tech_invoices' => 'Tabla de Facturas',
    'automatiza_services' => 'Tabla de Servicios'
];

foreach ($tables_to_check as $table_suffix => $table_name) {
    $table = $wpdb->prefix . $table_suffix;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "<p class='ok'>✅ {$table_name} ({$table}): Existe con {$count} registros</p>";
    } else {
        echo "<p class='error'>❌ {$table_name} ({$table}): NO EXISTE</p>";
    }
}
echo "</div>";

// 2. Verificar clientes contratados
echo "<div class='section'>";
echo "<h2>👥 2. Clientes Contratados</h2>";

$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
$clients = $wpdb->get_results("SELECT * FROM {$clients_table} ORDER BY id ASC");

if (empty($clients)) {
    echo "<p class='warning'>⚠️ No hay clientes contratados en la base de datos</p>";
} else {
    echo "<p class='ok'>✅ Total de clientes: " . count($clients) . "</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Fecha Contrato</th><th>Número Factura Esperado</th></tr>";
    
    foreach ($clients as $client) {
        $expected_invoice = 'AT-' . date('Ymd', strtotime($client->contracted_at)) . '-' . str_pad($client->id, 4, '0', STR_PAD_LEFT);
        echo "<tr>";
        echo "<td>{$client->id}</td>";
        echo "<td>{$client->name}</td>";
        echo "<td>{$client->email}</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($client->contracted_at)) . "</td>";
        echo "<td><strong>{$expected_invoice}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 3. Verificar facturas generadas
echo "<div class='section'>";
echo "<h2>📄 3. Facturas en Base de Datos</h2>";

$invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$invoices_table}'");

if (!$table_exists) {
    echo "<p class='error'>❌ La tabla de facturas NO EXISTE. Debe crearla primero.</p>";
    echo "<div class='code'>";
    echo "Para crear la tabla, ejecuta este script SQL en phpMyAdmin:<br><br>";
    echo "CREATE TABLE IF NOT EXISTS {$invoices_table} (<br>";
    echo "&nbsp;&nbsp;id int(11) NOT NULL AUTO_INCREMENT,<br>";
    echo "&nbsp;&nbsp;invoice_number varchar(50) NOT NULL,<br>";
    echo "&nbsp;&nbsp;client_name varchar(255) NOT NULL,<br>";
    echo "&nbsp;&nbsp;client_email varchar(255) NOT NULL,<br>";
    echo "&nbsp;&nbsp;client_rut varchar(50) DEFAULT NULL,<br>";
    echo "&nbsp;&nbsp;client_phone varchar(50) DEFAULT NULL,<br>";
    echo "&nbsp;&nbsp;client_address varchar(255) DEFAULT NULL,<br>";
    echo "&nbsp;&nbsp;subtotal decimal(10,2) DEFAULT 0.00,<br>";
    echo "&nbsp;&nbsp;iva decimal(10,2) DEFAULT 0.00,<br>";
    echo "&nbsp;&nbsp;total decimal(10,2) DEFAULT 0.00,<br>";
    echo "&nbsp;&nbsp;status varchar(20) DEFAULT 'active',<br>";
    echo "&nbsp;&nbsp;download_count int(11) DEFAULT 0,<br>";
    echo "&nbsp;&nbsp;validated_at datetime DEFAULT NULL,<br>";
    echo "&nbsp;&nbsp;created_at timestamp DEFAULT CURRENT_TIMESTAMP,<br>";
    echo "&nbsp;&nbsp;PRIMARY KEY (id),<br>";
    echo "&nbsp;&nbsp;UNIQUE KEY invoice_number (invoice_number)<br>";
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    echo "</div>";
} else {
    $invoices = $wpdb->get_results("SELECT * FROM {$invoices_table} ORDER BY id ASC");
    
    if (empty($invoices)) {
        echo "<p class='warning'>⚠️ La tabla existe pero NO HAY FACTURAS registradas</p>";
        echo "<p>Esto significa que las facturas nunca se guardaron en la base de datos.</p>";
    } else {
        echo "<p class='ok'>✅ Total de facturas: " . count($invoices) . "</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Número Factura</th><th>Cliente</th><th>Email</th><th>RUT</th><th>Total</th><th>Estado</th><th>Descargas</th><th>Creada</th></tr>";
        
        foreach ($invoices as $invoice) {
            echo "<tr>";
            echo "<td>{$invoice->id}</td>";
            echo "<td><strong>{$invoice->invoice_number}</strong></td>";
            echo "<td>{$invoice->client_name}</td>";
            echo "<td>{$invoice->client_email}</td>";
            echo "<td>" . ($invoice->client_rut ?: 'N/A') . "</td>";
            echo "<td>\$" . number_format($invoice->total, 2) . "</td>";
            echo "<td>{$invoice->status}</td>";
            echo "<td>{$invoice->download_count}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($invoice->created_at)) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
echo "</div>";

// 4. Verificar archivos PDF
echo "<div class='section'>";
echo "<h2>📁 4. Archivos PDF de Facturas</h2>";

$upload_dir = wp_upload_dir();
$invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';

if (!file_exists($invoices_dir)) {
    echo "<p class='error'>❌ El directorio de facturas NO EXISTE: {$invoices_dir}</p>";
    echo "<p>Crear el directorio con permisos 755</p>";
} else {
    echo "<p class='ok'>✅ Directorio existe: {$invoices_dir}</p>";
    
    $pdf_files = glob($invoices_dir . '*.pdf');
    
    if (empty($pdf_files)) {
        echo "<p class='warning'>⚠️ No hay archivos PDF en el directorio</p>";
    } else {
        echo "<p class='ok'>✅ Total de archivos PDF: " . count($pdf_files) . "</p>";
        echo "<table>";
        echo "<tr><th>Archivo</th><th>Tamaño</th><th>Fecha Modificación</th></tr>";
        
        foreach ($pdf_files as $pdf) {
            $filename = basename($pdf);
            $size = filesize($pdf);
            $size_kb = round($size / 1024, 2);
            $modified = date('d/m/Y H:i', filemtime($pdf));
            
            echo "<tr>";
            echo "<td>{$filename}</td>";
            echo "<td>{$size_kb} KB</td>";
            echo "<td>{$modified}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
echo "</div>";

// 5. Cruce de datos
echo "<div class='section'>";
echo "<h2>🔗 5. Sincronización Cliente-Factura</h2>";

if (!empty($clients) && $table_exists) {
    echo "<table>";
    echo "<tr><th>Cliente</th><th>Factura Esperada</th><th>¿Existe en BD?</th><th>¿Archivo PDF?</th></tr>";
    
    foreach ($clients as $client) {
        $expected_invoice = 'AT-' . date('Ymd', strtotime($client->contracted_at)) . '-' . str_pad($client->id, 4, '0', STR_PAD_LEFT);
        
        // Verificar en BD
        $invoice_in_db = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$invoices_table} WHERE invoice_number = %s",
            $expected_invoice
        ));
        
        // Verificar archivo PDF
        $pdf_files_found = glob($invoices_dir . $expected_invoice . '*.pdf');
        
        echo "<tr>";
        echo "<td>{$client->name}</td>";
        echo "<td><strong>{$expected_invoice}</strong></td>";
        echo "<td>" . ($invoice_in_db ? "<span class='ok'>✅ SÍ</span>" : "<span class='error'>❌ NO</span>") . "</td>";
        echo "<td>" . (!empty($pdf_files_found) ? "<span class='ok'>✅ SÍ (" . basename($pdf_files_found[0]) . ")</span>" : "<span class='error'>❌ NO</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No se puede hacer el cruce de datos (faltan clientes o tabla de facturas)</p>";
}
echo "</div>";

// 6. Verificar estructura de tabla de facturas
echo "<div class='section'>";
echo "<h2>🏗️ 6. Estructura de Tabla de Facturas</h2>";

if ($table_exists) {
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$invoices_table}");
    
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col->Field}</strong></td>";
        echo "<td>{$col->Type}</td>";
        echo "<td>{$col->Null}</td>";
        echo "<td>{$col->Key}</td>";
        echo "<td>" . ($col->Default ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar si falta la columna client_rut
    $has_rut = false;
    foreach ($columns as $col) {
        if ($col->Field === 'client_rut') {
            $has_rut = true;
            break;
        }
    }
    
    if (!$has_rut) {
        echo "<p class='error'>❌ FALTA LA COLUMNA 'client_rut'</p>";
        echo "<div class='code'>";
        echo "Para agregar la columna RUT, ejecuta este SQL:<br><br>";
        echo "ALTER TABLE {$invoices_table} ADD COLUMN client_rut varchar(50) DEFAULT NULL AFTER client_email;";
        echo "</div>";
    } else {
        echo "<p class='ok'>✅ La columna 'client_rut' existe</p>";
    }
}
echo "</div>";

// 7. Recomendaciones
echo "<div class='section'>";
echo "<h2>💡 7. Recomendaciones</h2>";

$issues = [];

if (!$table_exists) {
    $issues[] = "Crear la tabla de facturas usando el script SQL proporcionado arriba";
}

if ($table_exists && empty($invoices)) {
    $issues[] = "Regenerar las facturas para los clientes existentes";
}

if (!file_exists($invoices_dir)) {
    $issues[] = "Crear el directorio de facturas: mkdir -p {$invoices_dir} && chmod 755 {$invoices_dir}";
}

if (empty($issues)) {
    echo "<p class='ok'>✅ No se detectaron problemas graves</p>";
} else {
    echo "<p class='error'>⚠️ Problemas detectados:</p>";
    echo "<ol>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ol>";
}

echo "<h3>Pasos para Solucionar:</h3>";
echo "<ol>";
echo "<li>Si falta la tabla de facturas, créala con el script SQL proporcionado</li>";
echo "<li>Si falta la columna client_rut, agrégala con el script ALTER TABLE</li>";
echo "<li>Crea el directorio de facturas si no existe</li>";
echo "<li>Regenera las facturas ejecutando el script de regeneración</li>";
echo "</ol>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 8. URLs Importantes</h2>";
echo "<ul>";
echo "<li><a href='" . admin_url('admin.php?page=automatiza-tech-clients') . "'>Panel de Clientes</a></li>";
echo "<li><a href='" . admin_url('admin.php?page=automatiza-invoice-settings') . "'>Configuración de Facturas</a></li>";
echo "<li><a href='" . site_url('/regenerate-invoices-fpdf.php') . "'>Regenerar Facturas (si existe el script)</a></li>";
echo "</ul>";
echo "</div>";
