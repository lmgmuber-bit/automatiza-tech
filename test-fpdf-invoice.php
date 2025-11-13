<?php
/**
 * Test del generador de PDF con FPDF
 * Genera una factura de prueba usando FPDF
 */

// Cargar WordPress
require_once('wp-load.php');
require_once(get_template_directory() . '/lib/invoice-pdf-fpdf.php');

echo "<h1>Test de Generación de PDF con FPDF</h1>";
echo "<p>Generando factura de prueba...</p>";

// Detectar país del query string (para pruebas)
$test_country = isset($_GET['country']) ? strtoupper($_GET['country']) : 'CL';

// Datos de cliente de prueba según país
if ($test_country === 'CL') {
    // Cliente de Chile
    $client_data = (object) array(
        'id' => 999,
        'name' => 'Juan Pérez García',
        'email' => 'juan.perez@example.com',
        'phone' => '+56 9 8765 4321',
        'company' => 'Empresa Demo S.A.',
        'country' => 'CL',
        'message' => 'Prueba de factura PDF - Cliente Chile'
    );
} else {
    // Cliente internacional (USA)
    $client_data = (object) array(
        'id' => 998,
        'name' => 'John Smith',
        'email' => 'john.smith@example.com',
        'phone' => '+1 305 555 1234',
        'company' => 'Demo Corp LLC',
        'country' => 'US',
        'message' => 'Test invoice PDF - International client'
    );
}

// Datos de planes de prueba (múltiples items con ambos precios)
$plan_data = array(
    (object) array(
        'id' => 1,
        'name' => 'Plan Profesional - Desarrollo Web Completo',
        'price_clp' => 350000,
        'price_usd' => 400,
        'description' => 'Sitio web profesional con diseño responsive'
    ),
    (object) array(
        'id' => 2,
        'name' => 'Hosting Premium Anual',
        'price_clp' => 120000,
        'price_usd' => 140,
        'description' => 'Hosting con SSL y soporte 24/7'
    ),
    (object) array(
        'id' => 3,
        'name' => 'Mantenimiento Mensual',
        'price_clp' => 80000,
        'price_usd' => 90,
        'description' => 'Actualizaciones y soporte técnico'
    )
);

$invoice_number = 'AT-' . date('Ymd') . '-TEST-001';

// Determinar moneda para mostrar
$currency = ($test_country === 'CL') ? 'CLP' : 'USD';
$currency_field = ($test_country === 'CL') ? 'price_clp' : 'price_usd';
$total_items = array_sum(array_map(function($item) use ($currency_field) { 
    return $item->$currency_field; 
}, $plan_data));

echo "<div style='background:#e3f2fd;padding:15px;border-left:4px solid #2196F3;margin:20px 0;'>";
echo "<h3>Datos de la Factura:</h3>";
echo "<ul>";
echo "<li><strong>País:</strong> " . htmlspecialchars($test_country) . " (" . ($test_country === 'CL' ? '🇨🇱 Chile' : '🌎 Internacional') . ")</li>";
echo "<li><strong>Moneda:</strong> " . $currency . "</li>";
echo "<li><strong>Cliente:</strong> " . htmlspecialchars($client_data->name) . "</li>";
echo "<li><strong>Email:</strong> " . htmlspecialchars($client_data->email) . "</li>";
echo "<li><strong>Teléfono:</strong> " . htmlspecialchars($client_data->phone) . "</li>";
echo "<li><strong>Items:</strong> " . count($plan_data) . " servicios</li>";
if ($currency === 'CLP') {
    echo "<li><strong>Total Items:</strong> $" . number_format($total_items, 0, ',', '.') . " CLP</li>";
} else {
    echo "<li><strong>Total Items:</strong> USD $" . number_format($total_items, 2, '.', ',') . "</li>";
}
echo "<li><strong>IVA:</strong> " . ($test_country === 'CL' ? 'Aplica (19%)' : 'No aplica') . "</li>";
echo "<li><strong>Número Factura:</strong> " . htmlspecialchars($invoice_number) . "</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background:#fff3cd;padding:15px;border-left:4px solid #ffc107;margin:20px 0;'>";
echo "<h3>🔄 Cambiar País de Prueba:</h3>";
echo "<p>";
echo "<a href='?country=CL' style='padding:10px 20px;background:#0096C7;color:white;text-decoration:none;border-radius:5px;margin-right:10px;'>🇨🇱 Chile (CLP)</a>";
echo "<a href='?country=US' style='padding:10px 20px;background:#00BFB3;color:white;text-decoration:none;border-radius:5px;'>🌎 Internacional (USD)</a>";
echo "</p>";
echo "</div>";

try {
    // Crear directorio si no existe
    $upload_dir = wp_upload_dir();
    $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
    
    if (!file_exists($invoices_dir)) {
        wp_mkdir_p($invoices_dir);
        echo "<p>✓ Directorio creado: <code>" . htmlspecialchars($invoices_dir) . "</code></p>";
    }
    
    // Generar PDF
    echo "<p>Generando PDF con FPDF...</p>";
    
    $pdf_generator = new InvoicePDFFPDF($client_data, $plan_data, $invoice_number);
    
    $pdf_path = $invoices_dir . $invoice_number . '-' . sanitize_file_name($client_data->name) . '.pdf';
    
    echo "<p>Guardando en: <code>" . htmlspecialchars($pdf_path) . "</code></p>";
    
    $success = $pdf_generator->save($pdf_path);
    
    if ($success && file_exists($pdf_path)) {
        $pdf_size = filesize($pdf_path);
        
        echo "<div style='background:#e8f5e9;padding:20px;border-left:4px solid #4CAF50;margin:20px 0;'>";
        echo "<h2 style='color:#4CAF50;margin-top:0;'>✓ ¡PDF Generado Exitosamente!</h2>";
        echo "<p><strong>Archivo:</strong> <code>" . basename($pdf_path) . "</code></p>";
        echo "<p><strong>Tamaño:</strong> " . number_format($pdf_size) . " bytes (" . number_format($pdf_size/1024, 2) . " KB)</p>";
        echo "<p><strong>Ubicación:</strong> <code>" . htmlspecialchars($pdf_path) . "</code></p>";
        
        // Botón para descargar
        $pdf_url = $upload_dir['baseurl'] . '/automatiza-tech-invoices/' . basename($pdf_path);
        echo "<p style='margin-top:20px;'>";
        echo "<a href='" . esc_url($pdf_url) . "' target='_blank' style='background:#2196F3;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;font-weight:bold;'>📥 Descargar PDF de Prueba</a>";
        echo "</p>";
        
        echo "<p style='margin-top:15px;'>";
        echo "<a href='" . esc_url($pdf_url) . "' target='_blank' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>👁️ Ver PDF en Nueva Pestaña</a>";
        echo "</p>";
        
        echo "</div>";
        
        // Información técnica
        echo "<div style='background:#fff3e0;padding:15px;border-left:4px solid #ff9800;margin:20px 0;'>";
        echo "<h3 style='margin-top:0;'>📋 Información Técnica</h3>";
        echo "<ul>";
        echo "<li><strong>Librería:</strong> FPDF 1.86</li>";
        echo "<li><strong>Formato:</strong> PDF/A4</li>";
        echo "<li><strong>Orientación:</strong> Vertical (Portrait)</li>";
        echo "<li><strong>Dependencias:</strong> Ninguna (100% PHP)</li>";
        echo "<li><strong>Compatible con:</strong> Local + Producción</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        throw new Exception("No se pudo guardar el PDF o el archivo está vacío");
    }
    
} catch (Exception $e) {
    echo "<div style='background:#ffebee;padding:20px;border-left:4px solid #f44336;margin:20px 0;'>";
    echo "<h2 style='color:#f44336;margin-top:0;'>✗ Error al Generar PDF</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr style='margin:30px 0;'>";
echo "<p><a href='wp-admin/admin.php?page=automatiza-tech-contacts' style='background:#757575;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>← Volver al Panel de Contactos</a></p>";
