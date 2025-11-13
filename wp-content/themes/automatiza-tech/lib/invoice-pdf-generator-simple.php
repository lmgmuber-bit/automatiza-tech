<?php
/**
 * Generador Simple de PDFs usando HTML optimizado
 * Compatible con navegadores modernos que pueden "Imprimir a PDF"
 */

class SimplePDFInvoice {
    
    public function generate($client_data, $plan_data) {
        // Generar HTML optimizado para PDF
        $html = $this->generatePrintableHTML($client_data, $plan_data);
        
        // Intentar usar wkhtmltopdf si está disponible en el sistema
        if ($this->hasWKHTMLTOPDF()) {
            return $this->convertWithWKHTML($html);
        }
        
        // Si no hay wkhtmltopdf, retornar HTML con instrucciones
        return $this->wrapHTMLForBrowserPrint($html);
    }
    
    private function hasWKHTMLTOPDF() {
        // Verificar si wkhtmltopdf está instalado
        $paths = [
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'C:\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            '/usr/local/bin/wkhtmltopdf',
            '/usr/bin/wkhtmltopdf'
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    private function convertWithWKHTML($html) {
        $wk_path = $this->hasWKHTMLTOPDF();
        
        $temp_html = sys_get_temp_dir() . '/invoice_' . uniqid() . '.html';
        $temp_pdf = sys_get_temp_dir() . '/invoice_' . uniqid() . '.pdf';
        
        file_put_contents($temp_html, $html);
        
        $cmd = '"' . $wk_path . '" -q -s A4 -T 10mm -B 10mm -L 10mm -R 10mm ' . 
               '"' . $temp_html . '" "' . $temp_pdf . '"';
        
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0 && file_exists($temp_pdf)) {
            $pdf_content = file_get_contents($temp_pdf);
            @unlink($temp_html);
            @unlink($temp_pdf);
            return $pdf_content;
        }
        
        @unlink($temp_html);
        return false;
    }
    
    private function wrapHTMLForBrowserPrint($html) {
        // Retornar HTML pero informar que debe usar "Guardar como PDF" del navegador
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Factura - Guardar como PDF</title>
    <style>
        .print-instructions {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1e3a8a;
            color: white;
            padding: 15px;
            text-align: center;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .print-instructions button {
            background: #06d6a0;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            margin: 0 5px;
        }
        .print-instructions button:hover {
            background: #05c090;
        }
        .invoice-content {
            margin-top: 80px;
        }
        @media print {
            .print-instructions { display: none !important; }
            .invoice-content { margin-top: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="print-instructions">
        <p style="margin: 0 0 10px 0; font-size: 16px; font-weight: bold;">
            📄 Para guardar como PDF: Presiona Ctrl+P o haz clic en el botón abajo
        </p>
        <button onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>
        <button onclick="document.querySelector(\'.print-instructions\').style.display=\'none\'">✖️ Cerrar mensaje</button>
        <p style="margin: 10px 0 0 0; font-size: 12px;">
            En la ventana de impresión, selecciona "Guardar como PDF" como destino
        </p>
    </div>
    <div class="invoice-content">' . $html . '</div>
</body>
</html>';
    }
    
    public function generatePrintableHTML($client_data, $plan_data) {
        $invoice_number = 'AT-' . date('Ymd', strtotime($client_data->contracted_at)) . '-' . str_pad($client_data->id, 4, '0', STR_PAD_LEFT);
        $invoice_date = date('d/m/Y', strtotime($client_data->contracted_at));
        $site_url = get_site_url();
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        
        // Calcular totales
        $subtotal = floatval($plan_data->price_clp);
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        // Generar QR Code
        require_once(get_template_directory() . '/lib/qrcode.php');
        $validation_url = $site_url . '/validar-factura.php?id=' . urlencode($invoice_number);
        $qr_base64 = SimpleQRCode::generateBase64($validation_url, 120);
        
        $primary_color = '#1e3a8a';
        $secondary_color = '#06d6a0';
        
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura ' . $invoice_number . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        @page { size: A4; margin: 10mm; }
        
        @media print {
            body { background: white !important; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            background: white;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 0;
        }
        .header {
            background: linear-gradient(135deg, ' . $primary_color . ', ' . $secondary_color . ');
            color: white;
            padding: 15px 25px;
            text-align: center;
            margin-bottom: 15px;
        }
        .header img {
            max-width: 100px;
            height: auto;
            margin-bottom: 5px;
        }
        .header h1 {
            font-size: 20pt;
            margin: 5px 0;
        }
        .header p {
            font-size: 9pt;
            margin: 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 15px;
        }
        .info-box {
            background: #f9fafb;
            padding: 12px;
            border-left: 3px solid ' . $secondary_color . ';
        }
        .info-box h3 {
            color: ' . $primary_color . ';
            font-size: 11pt;
            margin-bottom: 8px;
        }
        .info-box p {
            font-size: 9pt;
            margin: 4px 0;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }
        .details h2 {
            color: ' . $primary_color . ';
            font-size: 13pt;
            border-bottom: 2px solid ' . $secondary_color . ';
            padding-bottom: 5px;
            margin: 15px 0 12px 0;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table.items thead {
            background: ' . $primary_color . ';
            color: white;
        }
        table.items th {
            padding: 8px;
            text-align: left;
            font-size: 9pt;
        }
        table.items td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }
        .service-name {
            font-weight: bold;
            color: ' . $primary_color . ';
            font-size: 10pt;
        }
        .service-description {
            color: #666;
            font-size: 8pt;
            margin-top: 3px;
        }
        .features {
            font-size: 8pt;
            margin: 5px 0 0 15px;
            padding: 0;
            list-style: none;
        }
        .features li {
            margin: 2px 0;
            color: #555;
        }
        .features li:before {
            content: "✓ ";
            color: ' . $secondary_color . ';
            font-weight: bold;
        }
        .totals-wrapper {
            margin-top: 15px;
            overflow: hidden;
        }
        .totals-box {
            float: right;
            width: 45%;
            background: #f9fafb;
            padding: 12px;
            border-radius: 5px;
        }
        .totals-box table {
            width: 100%;
        }
        .totals-box td {
            padding: 5px;
            font-size: 9pt;
        }
        .totals-box .label {
            text-align: right;
            font-weight: bold;
            color: #555;
        }
        .totals-box .amount {
            text-align: right;
            font-weight: bold;
        }
        .total-row {
            border-top: 2px solid ' . $secondary_color . ';
        }
        .total-row .label {
            color: ' . $primary_color . ';
            font-size: 11pt;
        }
        .total-row .amount {
            color: ' . $secondary_color . ';
            font-size: 13pt;
        }
        .qr-section {
            text-align: center;
            padding: 12px;
            background: #f9fafb;
            border-top: 2px solid ' . $secondary_color . ';
            margin-top: 15px;
            page-break-inside: avoid;
        }
        .qr-section h3 {
            font-size: 10pt;
            color: ' . $primary_color . ';
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
            margin: 5px 0;
        }
        .footer {
            background: linear-gradient(135deg, ' . $primary_color . ', ' . $secondary_color . ');
            color: white;
            padding: 10px 20px;
            margin-top: 15px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }
        .footer-col h4 {
            font-size: 9pt;
            margin: 0 0 5px 0;
        }
        .footer-col p {
            font-size: 7pt;
            margin: 2px 0;
        }
        .thank-you {
            background: white;
            color: ' . $primary_color . ';
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 8pt;
            display: inline-block;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header">
            <img src="' . $logo_url . '" alt="AutomatizaTech Logo">
            <h1>🧾 FACTURA</h1>
            <p>AutomatizaTech - Soluciones Digitales Profesionales</p>
        </div>
        
        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-box">
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
                        <th style="width: 15%; text-align: center;">Cant.</th>
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
                    $html .= '<li>' . esc_html(trim($feature)) . '</li>';
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
            <div class="totals-wrapper">
                <div class="totals-box">
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
            <p>Escanea el código QR para validar la autenticidad</p>
            <img src="' . $qr_base64 . '" alt="QR Code">
            <p><strong>Código:</strong> ' . $invoice_number . '</p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
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
