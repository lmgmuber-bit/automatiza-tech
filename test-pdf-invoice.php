<?php
/**
 * Test de Generación de PDF para Facturas
 */

require_once(__DIR__ . '/wp-load.php');

global $wpdb;

$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
$services_table = $wpdb->prefix . 'automatiza_services';

// Obtener primer cliente contratado
$client = $wpdb->get_row("
    SELECT * FROM {$clients_table} 
    WHERE contract_status = 'contracted' 
    LIMIT 1
");

if (!$client) {
    die('No hay clientes contratados para generar PDF');
}

// Obtener plan
$plan = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$services_table} WHERE id = %d",
    $client->plan_id
));

if (!$plan) {
    die('Plan no encontrado');
}

// Generar número de factura
$invoice_number = 'AT-' . date('Ymd', strtotime($client->contracted_at)) . '-' . str_pad($client->id, 4, '0', STR_PAD_LEFT);

// Cargar generador
require_once(get_template_directory() . '/lib/invoice-pdf-generator-simple.php');

$generator = new SimplePDFInvoice();
$pdf_content = $generator->generate($client, $plan);

// Descargar PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Factura_' . $invoice_number . '.pdf"');
header('Content-Length: ' . strlen($pdf_content));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdf_content;
exit;
