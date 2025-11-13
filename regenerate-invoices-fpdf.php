<?php
/**
 * Regenerar todas las facturas usando FPDF
 * Este script convierte todas las facturas existentes al formato PDF real
 */

// Cargar WordPress
require_once('wp-load.php');

echo "<h1>Regenerar Facturas con FPDF</h1>";
echo "<p>Este script regenerará todas las facturas existentes usando el nuevo sistema FPDF.</p>";

global $wpdb;

// Obtener todos los clientes con facturas
$clients = $wpdb->get_results("
    SELECT c.*, i.invoice_number, i.invoice_path
    FROM {$wpdb->prefix}automatiza_tech_clients c
    INNER JOIN {$wpdb->prefix}automatiza_tech_invoices i ON c.id = i.client_id
    WHERE c.contract_status = 'contratado'
    AND i.invoice_number != ''
    ORDER BY i.id DESC
");

if (empty($clients)) {
    echo "<div style='background:#fff3e0;padding:15px;border-left:4px solid #ff9800;'>";
    echo "<p>No se encontraron clientes con facturas para regenerar.</p>";
    echo "</div>";
    exit;
}

echo "<div style='background:#e3f2fd;padding:15px;border-left:4px solid #2196F3;margin:20px 0;'>";
echo "<h3>Clientes encontrados: " . count($clients) . "</h3>";
echo "</div>";

require_once(get_template_directory() . '/lib/invoice-pdf-fpdf.php');

$success_count = 0;
$error_count = 0;
$errors = array();

echo "<table style='width:100%;border-collapse:collapse;margin:20px 0;'>";
echo "<thead style='background:#2196F3;color:white;'>";
echo "<tr>";
echo "<th style='padding:10px;border:1px solid #ddd;'>Cliente</th>";
echo "<th style='padding:10px;border:1px solid #ddd;'>Número Factura</th>";
echo "<th style='padding:10px;border:1px solid #ddd;'>Estado</th>";
echo "<th style='padding:10px;border:1px solid #ddd;'>Tamaño</th>";
echo "<th style='padding:10px;border:1px solid #ddd;'>Acciones</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

foreach ($clients as $client) {
    echo "<tr style='border-bottom:1px solid #ddd;'>";
    echo "<td style='padding:10px;'>" . htmlspecialchars($client->name) . "</td>";
    echo "<td style='padding:10px;'>" . htmlspecialchars($client->invoice_number) . "</td>";
    
    try {
        // Obtener datos del plan
        $plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}automatiza_services WHERE id = %d",
            $client->plan_id
        ));
        
        if (!$plan) {
            throw new Exception("Plan no encontrado: ID {$client->plan_id}");
        }
        
        // Generar PDF
        $upload_dir = wp_upload_dir();
        $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
        
        if (!file_exists($invoices_dir)) {
            wp_mkdir_p($invoices_dir);
        }
        
        $pdf_generator = new InvoicePDFFPDF($client, $plan, $client->invoice_number);
        $pdf_path = $invoices_dir . $client->invoice_number . '-' . sanitize_file_name($client->name) . '.pdf';
        
        $result = $pdf_generator->save($pdf_path);
        
        if ($result && file_exists($pdf_path) && filesize($pdf_path) > 0) {
            $pdf_size = filesize($pdf_path);
            $pdf_url = $upload_dir['baseurl'] . '/automatiza-tech-invoices/' . basename($pdf_path);
            
            // Actualizar ruta en base de datos
            $wpdb->update(
                $wpdb->prefix . 'automatiza_tech_invoices',
                array('invoice_path' => $pdf_path),
                array('client_id' => $client->id),
                array('%s'),
                array('%d')
            );
            
            echo "<td style='padding:10px;color:#4CAF50;font-weight:bold;'>✓ Éxito</td>";
            echo "<td style='padding:10px;'>" . number_format($pdf_size/1024, 2) . " KB</td>";
            echo "<td style='padding:10px;'>";
            echo "<a href='" . esc_url($pdf_url) . "' target='_blank' style='background:#2196F3;color:white;padding:5px 10px;text-decoration:none;border-radius:3px;font-size:12px;margin-right:5px;'>Ver PDF</a>";
            echo "</td>";
            
            $success_count++;
        } else {
            throw new Exception("PDF generado pero archivo vacío o no existe");
        }
        
    } catch (Exception $e) {
        echo "<td style='padding:10px;color:#f44336;font-weight:bold;'>✗ Error</td>";
        echo "<td style='padding:10px;'>-</td>";
        echo "<td style='padding:10px;color:#f44336;font-size:12px;'>" . htmlspecialchars($e->getMessage()) . "</td>";
        $error_count++;
        $errors[] = array(
            'client' => $client->name,
            'invoice' => $client->invoice_number,
            'error' => $e->getMessage()
        );
    }
    
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";

// Resumen
echo "<div style='background:#e8f5e9;padding:20px;border-left:4px solid #4CAF50;margin:20px 0;'>";
echo "<h2 style='margin-top:0;'>Resumen de Regeneración</h2>";
echo "<ul>";
echo "<li><strong>Total de facturas:</strong> " . count($clients) . "</li>";
echo "<li><strong style='color:#4CAF50;'>Exitosas:</strong> {$success_count}</li>";
echo "<li><strong style='color:#f44336;'>Con errores:</strong> {$error_count}</li>";
echo "</ul>";
echo "</div>";

if (!empty($errors)) {
    echo "<div style='background:#ffebee;padding:20px;border-left:4px solid #f44336;margin:20px 0;'>";
    echo "<h3 style='margin-top:0;'>Detalles de Errores:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>";
        echo "<strong>" . htmlspecialchars($error['client']) . "</strong> ";
        echo "(" . htmlspecialchars($error['invoice']) . "): ";
        echo "<em>" . htmlspecialchars($error['error']) . "</em>";
        echo "</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<hr style='margin:30px 0;'>";
echo "<p>";
echo "<a href='wp-admin/admin.php?page=automatiza-tech-contacts' style='background:#2196F3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-right:10px;'>← Panel de Contactos</a>";
echo "<a href='test-fpdf-invoice.php' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Generar PDF de Prueba</a>";
echo "</p>";
