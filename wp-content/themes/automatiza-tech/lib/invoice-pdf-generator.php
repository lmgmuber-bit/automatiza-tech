<?php
/**
 * Clase para Generar Facturas en PDF
 * Usa TCPDF (incluida en WordPress)
 */

// Cargar TCPDF si está disponible
if (!class_exists('TCPDF')) {
    // Intentar cargar desde WordPress
    $tcpdf_paths = [
        ABSPATH . 'wp-includes/certificates/tcpdf/tcpdf.php',
        ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php',
        __DIR__ . '/../tcpdf/tcpdf.php'
    ];
    
    $tcpdf_loaded = false;
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $tcpdf_loaded = true;
            break;
        }
    }
    
    if (!$tcpdf_loaded) {
        // Usar mPDF como alternativa
        require_once(__DIR__ . '/mpdf-simple.php');
    }
}

class InvoicePDFGenerator {
    
    private $primary_color;
    private $secondary_color;
    private $accent_color;
    
    public function __construct() {
        $this->primary_color = '#1e3a8a';
        $this->secondary_color = '#06d6a0';
        $this->accent_color = '#f59e0b';
    }
    
    /**
     * Generar PDF de factura
     */
    public function generate($client_data, $plan_data, $output_mode = 'S') {
        // S = String, F = File, D = Download, I = Inline
        
        $invoice_number = 'AT-' . date('Ymd', strtotime($client_data->contracted_at)) . '-' . str_pad($client_data->id, 4, '0', STR_PAD_LEFT);
        
        // Crear instancia de mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_header' => 5,
            'margin_footer' => 5,
            'tempDir' => sys_get_temp_dir()
        ]);
        
        // Metadata
        $mpdf->SetTitle('Factura ' . $invoice_number);
        $mpdf->SetAuthor('AutomatizaTech');
        $mpdf->SetCreator('AutomatizaTech Invoice System');
        $mpdf->SetSubject('Factura de Servicios');
        
        // Generar HTML
        $html = $this->generateHTML($client_data, $plan_data);
        
        // Escribir HTML al PDF
        $mpdf->WriteHTML($html);
        
        // Retornar según modo
        return $mpdf->Output('Factura_' . $invoice_number . '.pdf', $output_mode);
    }
    
    /**
     * Generar HTML optimizado para PDF
     */
    private function generateHTML($client_data, $plan_data) {
        $invoice_number = 'AT-' . date('Ymd', strtotime($client_data->contracted_at)) . '-' . str_pad($client_data->id, 4, '0', STR_PAD_LEFT);
        $invoice_date = date('d/m/Y', strtotime($client_data->contracted_at));
        $site_url = get_site_url();
        $logo_path = get_template_directory() . '/assets/images/logo-automatiza-tech.png';
        
        // Convertir logo a base64
        $logo_base64 = '';
        if (file_exists($logo_path)) {
            $logo_data = file_get_contents($logo_path);
            $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
        }
        
        // Calcular totales
        $subtotal = floatval($plan_data->price_clp);
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        // Generar QR Code
        require_once(__DIR__ . '/qrcode.php');
        $validation_url = $site_url . '/validar-factura.php?id=' . urlencode($invoice_number);
        $qr_base64 = SimpleQRCode::generateBase64($validation_url, 120);
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #333;
        }
        .header {
            text-align: center;
            padding: 10px 0;
            background: linear-gradient(135deg, ' . $this->primary_color . ', ' . $this->secondary_color . ');
            color: white;
            margin-bottom: 15px;
        }
        .header img {
            max-width: 100px;
            margin-bottom: 5px;
        }
        .header h1 {
            font-size: 18pt;
            margin: 5px 0;
        }
        .header p {
            font-size: 9pt;
            margin: 0;
        }
        .info-section {
            width: 100%;
            margin-bottom: 15px;
        }
        .info-box {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            padding: 10px;
            background: #f9fafb;
            border-left: 3px solid ' . $this->secondary_color . ';
            margin-bottom: 10px;
        }
        .info-box h3 {
            font-size: 11pt;
            color: ' . $this->primary_color . ';
            margin: 0 0 8px 0;
        }
        .info-box p {
            font-size: 9pt;
            margin: 3px 0;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        .details h2 {
            font-size: 13pt;
            color: ' . $this->primary_color . ';
            border-bottom: 2px solid ' . $this->secondary_color . ';
            padding-bottom: 5px;
            margin: 15px 0 10px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table.items thead {
            background: ' . $this->primary_color . ';
            color: white;
        }
        table.items th {
            padding: 8px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
        }
        table.items td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }
        .service-name {
            font-weight: bold;
            color: ' . $this->primary_color . ';
            font-size: 10pt;
        }
        .service-description {
            font-size: 8pt;
            color: #666;
            margin-top: 3px;
        }
        .features {
            font-size: 8pt;
            margin-top: 5px;
            padding-left: 15px;
        }
        .features li {
            margin: 2px 0;
            color: #555;
        }
        .totals {
            width: 100%;
            margin-top: 15px;
        }
        .totals-table {
            float: right;
            width: 45%;
            background: #f9fafb;
            padding: 10px;
            border-radius: 5px;
        }
        .totals-table table {
            width: 100%;
        }
        .totals-table td {
            padding: 5px;
            font-size: 9pt;
        }
        .totals-table .label {
            text-align: right;
            font-weight: bold;
            color: #555;
        }
        .totals-table .amount {
            text-align: right;
            font-weight: bold;
        }
        .total-row {
            border-top: 2px solid ' . $this->secondary_color . ';
            padding-top: 8px !important;
            margin-top: 5px;
        }
        .total-row .label {
            color: ' . $this->primary_color . ';
            font-size: 11pt;
        }
        .total-row .amount {
            color: ' . $this->secondary_color . ';
            font-size: 12pt;
        }
        .qr-section {
            text-align: center;
            padding: 10px;
            background: #f9fafb;
            border-top: 2px solid ' . $this->secondary_color . ';
            margin-top: 15px;
        }
        .qr-section h3 {
            font-size: 10pt;
            color: ' . $this->primary_color . ';
            margin: 0 0 5px 0;
        }
        .qr-section p {
            font-size: 8pt;
            color: #666;
            margin: 3px 0;
        }
        .qr-section img {
            width: 100px;
            height: 100px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, ' . $this->primary_color . ', ' . $this->secondary_color . ');
            color: white;
            padding: 10px 15px;
            font-size: 8pt;
        }
        .footer-cols {
            width: 100%;
        }
        .footer-col {
            display: inline-block;
            width: 32%;
            vertical-align: top;
        }
        .footer-col h4 {
            font-size: 9pt;
            margin: 0 0 5px 0;
        }
        .footer-col p {
            margin: 2px 0;
            font-size: 7pt;
        }
        .thank-you {
            background: white;
            color: ' . $this->primary_color . ';
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">';
    
    if ($logo_base64) {
        $html .= '<img src="' . $logo_base64 . '" alt="Logo">';
    }
    
    $html .= '
        <h1>🧾 FACTURA</h1>
        <p>AutomatizaTech - Soluciones Digitales Profesionales</p>
    </div>
    
    <!-- Info Section -->
    <div class="info-section">
        <div class="info-box" style="margin-right: 2%;">
            <h3>📋 Datos de la Factura</h3>
            <p><span class="info-label">N° Factura:</span> <strong>' . $invoice_number . '</strong></p>
            <p><span class="info-label">Fecha:</span> ' . $invoice_date . '</p>
            <p><span class="info-label">Válido hasta:</span> ' . date('d/m/Y', strtotime($client_data->contracted_at . ' +30 days')) . '</p>
        </div>
        
        <div class="info-box">
            <h3>👤 Datos del Cliente</h3>
            <p><span class="info-label">Nombre:</span> <strong>' . esc_html($client_data->name) . '</strong></p>
            <p><span class="info-label">Email:</span> ' . esc_html($client_data->email) . '</p>';
            
    if (!empty($client_data->company)) {
        $html .= '<p><span class="info-label">Empresa:</span> ' . esc_html($client_data->company) . '</p>';
    }
    if (!empty($client_data->phone)) {
        $html .= '<p><span class="info-label">Teléfono:</span> ' . esc_html($client_data->phone) . '</p>';
    }
    
    $html .= '
        </div>
    </div>
    
    <!-- Details -->
    <div class="details">
        <h2>💼 Detalle del Servicio Contratado</h2>
        
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 60%;">Descripción</th>
                    <th style="width: 15%; text-align: center;">Cantidad</th>
                    <th style="width: 25%; text-align: right;">Precio</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="service-name">' . esc_html($plan_data->name) . '</div>
                        <div class="service-description">' . esc_html($plan_data->description) . '</div>';
    
    if (!empty($plan_data->features)) {
        $features = explode("\n", $plan_data->features);
        $html .= '<ul class="features">';
        foreach ($features as $feature) {
            if (trim($feature)) {
                $html .= '<li>✓ ' . esc_html(trim($feature)) . '</li>';
            }
        }
        $html .= '</ul>';
    }
    
    $html .= '
                    </td>
                    <td style="text-align: center; font-weight: bold;">1</td>
                    <td style="text-align: right; font-weight: bold;">$' . number_format($subtotal, 0, ',', '.') . '</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals">
            <div class="totals-table">
                <table>
                    <tr>
                        <td class="label">Subtotal:</td>
                        <td class="amount">$' . number_format($subtotal, 0, ',', '.') . '</td>
                    </tr>
                    <tr>
                        <td class="label">IVA (19%):</td>
                        <td class="amount">$' . number_format($iva, 0, ',', '.') . '</td>
                    </tr>
                    <tr class="total-row">
                        <td class="label">TOTAL:</td>
                        <td class="amount">$' . number_format($total, 0, ',', '.') . '</td>
                    </tr>
                </table>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>
    
    <!-- QR Section -->
    <div class="qr-section">
        <h3>🔒 Validación de Factura</h3>
        <p>Escanea el código QR para validar la autenticidad de esta factura</p>
        <img src="' . $qr_base64 . '" alt="QR Code">
        <p><strong>Código:</strong> ' . $invoice_number . '</p>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <div class="footer-cols">
            <div class="footer-col">
                <div class="thank-you">¡Gracias por confiar en AutomatizaTech! 🎉</div>
                <p>Generada: ' . date('d/m/Y H:i') . '</p>
            </div>
            <div class="footer-col">
                <h4>📞 Contacto</h4>
                <p>📧 info@automatizatech.shop</p>
                <p>📱 +56 9 6432 4169</p>
            </div>
            <div class="footer-col">
                <h4>🌐 Web</h4>
                <p>' . $site_url . '</p>
                <p>Soluciones Digitales</p>
            </div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
}
