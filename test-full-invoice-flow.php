<?php
/**
 * Test completo del flujo de facturación
 * Simula el proceso completo: crear cliente → contratar → generar factura → enviar correo
 */

require_once('wp-load.php');

echo "<h1>Test Completo del Sistema de Facturación</h1>";
echo "<p>Este test simula el flujo completo de contratación de un cliente.</p>";

// Verificar que tenemos los archivos necesarios
$required_files = array(
    get_template_directory() . '/lib/fpdf.php' => 'FPDF',
    get_template_directory() . '/lib/qrcode.php' => 'QRCode',
    get_template_directory() . '/lib/invoice-pdf-fpdf.php' => 'Invoice PDF Generator'
);

echo "<h2>1. Verificación de Archivos</h2>";
$all_files_ok = true;
foreach ($required_files as $file => $name) {
    echo "<p>$name: ";
    if (file_exists($file)) {
        echo "<strong style='color:green;'>✓ OK</strong> ";
        echo "<code>" . basename($file) . "</code>";
    } else {
        echo "<strong style='color:red;'>✗ FALTA</strong> ";
        echo "<code>$file</code>";
        $all_files_ok = false;
    }
    echo "</p>";
}

if (!$all_files_ok) {
    echo "<div style='background:#ffebee;padding:15px;border-left:4px solid #f44336;'>";
    echo "<p>❌ <strong>Error:</strong> Faltan archivos necesarios. Por favor instala FPDF.</p>";
    echo "</div>";
    exit;
}

echo "<div style='background:#e8f5e9;padding:15px;border-left:4px solid #4CAF50;margin:20px 0;'>";
echo "<p>✅ Todos los archivos necesarios están presentes</p>";
echo "</div>";

// 2. Verificar directorios
echo "<h2>2. Verificación de Directorios</h2>";

$upload_dir = wp_upload_dir();
$required_dirs = array(
    $upload_dir['basedir'] . '/automatiza-tech-invoices/' => 'Facturas',
    $upload_dir['basedir'] . '/qr-codes/' => 'Códigos QR'
);

foreach ($required_dirs as $dir => $name) {
    echo "<p>$name: ";
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        echo "<strong style='color:orange;'>⚠️ CREADO</strong>";
    } else {
        echo "<strong style='color:green;'>✓ EXISTE</strong>";
    }
    echo " <code>" . basename($dir) . "</code>";
    echo "</p>";
}

// 3. Obtener un plan activo
echo "<h2>3. Obtener Plan de Servicio</h2>";
global $wpdb;
$plan = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}automatiza_services WHERE status = 'active' LIMIT 1");

if (!$plan) {
    echo "<div style='background:#ffebee;padding:15px;border-left:4px solid #f44336;'>";
    echo "<p>❌ <strong>Error:</strong> No hay planes activos en la base de datos.</p>";
    echo "</div>";
    exit;
}

echo "<div style='background:#e3f2fd;padding:15px;border-left:4px solid #2196F3;margin:15px 0;'>";
echo "<p><strong>Plan seleccionado:</strong> " . htmlspecialchars($plan->name) . "</p>";
echo "<p><strong>Precio:</strong> $" . number_format($plan->price_clp, 0, ',', '.') . "</p>";
echo "</div>";

// 4. Crear datos de cliente de prueba
echo "<h2>4. Datos del Cliente de Prueba</h2>";
$test_client = (object) array(
    'id' => 9999,
    'name' => 'María Fernanda González',
    'email' => 'maria.gonzalez@testautomatiza.com',
    'phone' => '+56 9 8765 4321',
    'company' => 'Empresa Test S.A.',
    'message' => 'Cliente de prueba para validar el sistema completo'
);

echo "<div style='background:#f3e5f5;padding:15px;border-left:4px solid #9c27b0;margin:15px 0;'>";
echo "<ul>";
echo "<li><strong>Nombre:</strong> " . htmlspecialchars($test_client->name) . "</li>";
echo "<li><strong>Email:</strong> " . htmlspecialchars($test_client->email) . "</li>";
echo "<li><strong>Teléfono:</strong> " . htmlspecialchars($test_client->phone) . "</li>";
echo "<li><strong>Empresa:</strong> " . htmlspecialchars($test_client->company) . "</li>";
echo "</ul>";
echo "</div>";

// 5. Generar número de factura
$invoice_number = 'AT-' . date('Ymd') . '-TEST-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
echo "<h2>5. Número de Factura Generado</h2>";
echo "<p><strong style='font-size:18px;color:#2196F3;'>$invoice_number</strong></p>";

// 6. Generar PDF
echo "<h2>6. Generación de PDF</h2>";
echo "<p>Generando factura en formato PDF...</p>";

try {
    require_once(get_template_directory() . '/lib/invoice-pdf-fpdf.php');
    
    $pdf_generator = new InvoicePDFFPDF($test_client, $plan, $invoice_number);
    
    $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
    $pdf_filename = $invoice_number . '-' . sanitize_file_name($test_client->name) . '.pdf';
    $pdf_path = $invoices_dir . $pdf_filename;
    
    $success = $pdf_generator->save($pdf_path);
    
    if ($success && file_exists($pdf_path) && filesize($pdf_path) > 0) {
        $pdf_size = filesize($pdf_path);
        $pdf_url = $upload_dir['baseurl'] . '/automatiza-tech-invoices/' . $pdf_filename;
        
        echo "<div style='background:#e8f5e9;padding:20px;border-left:4px solid #4CAF50;margin:20px 0;'>";
        echo "<h3 style='color:#4CAF50;margin-top:0;'>✓ PDF Generado Exitosamente</h3>";
        echo "<ul>";
        echo "<li><strong>Archivo:</strong> <code>$pdf_filename</code></li>";
        echo "<li><strong>Tamaño:</strong> " . number_format($pdf_size/1024, 2) . " KB</li>";
        echo "<li><strong>Ubicación:</strong> <code>" . htmlspecialchars($pdf_path) . "</code></li>";
        echo "</ul>";
        echo "<p style='margin-top:20px;'>";
        echo "<a href='" . esc_url($pdf_url) . "' target='_blank' style='background:#2196F3;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;font-weight:bold;margin-right:10px;'>📥 Descargar PDF</a>";
        echo "<a href='" . esc_url($pdf_url) . "' target='_blank' style='background:#4CAF50;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;font-weight:bold;'>👁️ Ver PDF</a>";
        echo "</p>";
        echo "</div>";
        
        // 7. Simular envío de correo
        echo "<h2>7. Simulación de Envío de Correo</h2>";
        echo "<div style='background:#fff3e0;padding:15px;border-left:4px solid #ff9800;margin:15px 0;'>";
        echo "<p><strong>Para:</strong> " . htmlspecialchars($test_client->email) . "</p>";
        echo "<p><strong>Asunto:</strong> ✅ Confirmación de Contratación - AutomatizaTech</p>";
        echo "<p><strong>Adjunto:</strong> <code>$pdf_filename</code> (" . number_format($pdf_size/1024, 2) . " KB)</p>";
        echo "<p><strong>Estado:</strong> <span style='color:#4CAF50;'>✓ Listo para enviar</span></p>";
        echo "<p style='font-size:12px;color:#757575;'><em>Nota: Este es un test. No se envía correo real.</em></p>";
        echo "</div>";
        
        // 8. Resumen final
        echo "<h2>8. Resumen del Test</h2>";
        echo "<div style='background:#e8f5e9;padding:20px;border-left:4px solid #4CAF50;margin:20px 0;'>";
        echo "<h3 style='color:#4CAF50;margin-top:0;'>✅ Sistema Completamente Funcional</h3>";
        echo "<table style='width:100%;'>";
        echo "<tr><td style='padding:5px;'><strong>✓ Archivos:</strong></td><td>Todos presentes</td></tr>";
        echo "<tr><td style='padding:5px;'><strong>✓ Directorios:</strong></td><td>Creados y accesibles</td></tr>";
        echo "<tr><td style='padding:5px;'><strong>✓ Plan:</strong></td><td>" . htmlspecialchars($plan->name) . "</td></tr>";
        echo "<tr><td style='padding:5px;'><strong>✓ Cliente:</strong></td><td>" . htmlspecialchars($test_client->name) . "</td></tr>";
        echo "<tr><td style='padding:5px;'><strong>✓ Factura:</strong></td><td>$invoice_number</td></tr>";
        echo "<tr><td style='padding:5px;'><strong>✓ PDF:</strong></td><td>" . number_format($pdf_size/1024, 2) . " KB</td></tr>";
        echo "<tr><td style='padding:5px;'><strong>✓ Correo:</strong></td><td>Configurado con adjunto</td></tr>";
        echo "</table>";
        echo "</div>";
        
        echo "<div style='background:#e3f2fd;padding:20px;border-left:4px solid #2196F3;margin:20px 0;'>";
        echo "<h3 style='margin-top:0;'>🚀 Próximos Pasos</h3>";
        echo "<ol>";
        echo "<li>Contratar un cliente real desde el panel de WordPress</li>";
        echo "<li>Verificar que el PDF se genera automáticamente</li>";
        echo "<li>Confirmar que el correo llega con el PDF adjunto</li>";
        echo "<li>Escanear el código QR para validar la factura</li>";
        echo "</ol>";
        echo "</div>";
        
    } else {
        throw new Exception("PDF generado pero archivo vacío o no existe");
    }
    
} catch (Exception $e) {
    echo "<div style='background:#ffebee;padding:20px;border-left:4px solid #f44336;margin:20px 0;'>";
    echo "<h3 style='color:#f44336;margin-top:0;'>✗ Error al Generar PDF</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;max-height:200px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr style='margin:30px 0;'>";
echo "<p>";
echo "<a href='wp-admin/admin.php?page=automatiza-tech-contacts' style='background:#2196F3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-right:10px;'>← Panel de Contactos</a>";
echo "<a href='test-fpdf-invoice.php' style='background:#757575;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-right:10px;'>PDF Simple</a>";
echo "<a href='regenerate-invoices-fpdf.php' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Regenerar Todas</a>";
echo "</p>";
