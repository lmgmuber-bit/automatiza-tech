<?php
/**
 * Generador de HTML de Factura para Previsualización
 */

// Cargar librería de QR Code
require_once(get_template_directory() . '/lib/qrcode.php');

function generate_invoice_preview($client_data, $plan_data) {
    $invoice_number = 'AT-' . date('Ymd') . '-TEST';
    $invoice_date = date('d/m/Y');
    $site_url = get_site_url();
    $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
    
    $primary_color = '#1e3a8a';
    $secondary_color = '#06d6a0';
    $accent_color = '#f59e0b';
    
    $subtotal = floatval($plan_data->price_clp);
    $iva = $subtotal * 0.19;
    $total = $subtotal + $iva;
    
    $html = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Factura {$invoice_number}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        @page {
            size: A4;
            margin: 0;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.4; 
            color: #333;
            background: #f5f5f5;
            padding: 0;
            margin: 0;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
            page-break-after: avoid;
        }
        .invoice-header {
            background: linear-gradient(135deg, {$primary_color}, {$secondary_color});
            color: white;
            padding: 20px 30px;
            text-align: center;
        }
        .invoice-header img {
            max-width: 110px !important;
            margin-bottom: 8px !important;
        }
        .invoice-header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .invoice-header p {
            font-size: 1em;
            opacity: 0.9;
        }
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px 30px;
            background: #f9fafb;
        }
        .info-block {
            background: white;
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 3px solid {$secondary_color};
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .info-block h3 {
            color: {$primary_color};
            margin-bottom: 8px;
            font-size: 0.95em;
        }
        .info-block p {
            margin: 5px 0;
            font-size: 0.85em;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            display: inline-block;
            width: 120px;
        }
        .invoice-details {
            padding: 15px 30px;
        }
        .invoice-details h2 {
            color: {$primary_color};
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid {$secondary_color};
            font-size: 1.2em;
        }
        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            border-radius: 6px;
            overflow: hidden;
        }
        .service-table thead {
            background: {$primary_color};
            color: white;
        }
        .service-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8em;
        }
        .service-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .service-description {
            color: #666;
            font-size: 0.8em;
            margin-top: 3px;
        }
        .totals {
            margin-top: 15px;
            text-align: right;
            padding: 12px 15px;
            background: #f9fafb;
            border-radius: 6px;
        }
        .totals .row {
            display: flex;
            justify-content: flex-end;
            margin: 6px 0;
            font-size: 0.95em;
        }
        .totals .label {
            margin-right: 30px;
            color: #555;
            font-weight: 600;
            min-width: 120px;
            text-align: right;
        }
        .totals .amount {
            min-width: 120px;
            text-align: right;
            font-weight: bold;
        }
        .total-row {
            border-top: 2px solid {$secondary_color};
            padding-top: 8px;
            margin-top: 8px;
        }
        .total-row .label {
            color: {$primary_color};
            font-size: 1.1em;
        }
        .total-row .amount {
            color: {$secondary_color};
            font-size: 1.2em;
        }
        .qr-validation {
            page-break-inside: avoid;
            padding: 15px 30px !important;
        }
        .qr-validation h3 {
            font-size: 1em !important;
            margin-bottom: 8px !important;
        }
        .qr-validation p {
            font-size: 0.85em !important;
            margin-bottom: 8px !important;
        }
        .qr-validation {
            page-break-inside: avoid;
            padding: 12px 30px !important;
        }
        .qr-validation h3 {
            font-size: 0.95em !important;
            margin-bottom: 6px !important;
        }
        .qr-validation p {
            margin-bottom: 8px !important;
            font-size: 0.85em !important;
        }
        .qr-validation img {
            max-width: 120px !important;
            height: auto !important;
        }
        .invoice-footer {
            background: linear-gradient(135deg, {$primary_color}, {$secondary_color});
            color: white;
            padding: 15px 30px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            align-items: center;
        }
        .footer-column {
            text-align: left;
        }
        .footer-column h3 {
            font-size: 0.95em;
            margin-bottom: 8px;
            opacity: 0.95;
        }
        .footer-column p {
            margin: 4px 0;
            font-size: 0.85em;
            opacity: 0.9;
        }
        .thank-you {
            background: white;
            color: {$primary_color};
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 600;
            text-align: center;
        }
        .features-list {
            list-style: none;
            padding: 8px 0;
        }
        .features-list li {
            padding: 4px 0;
            padding-left: 20px;
            position: relative;
            font-size: 0.85em;
        }
        .features-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: {$secondary_color};
            font-weight: bold;
            font-size: 1.2em;
        }
        @media print {
            body { background: white; padding: 0; margin: 0; }
            .invoice-container { box-shadow: none; border-radius: 0; }
            .invoice-header { padding: 15px 25px; }
            .invoice-info { padding: 12px 25px; gap: 8px; }
            .info-block { padding: 8px 10px; }
            .invoice-details { padding: 20px 25px; }
            .invoice-footer { padding: 10px 25px; gap: 15px; }
            .qr-validation { padding: 10px 25px !important; }
            .footer-column p { font-size: 0.8em; }
        }
    </style>
</head>
<body>
    <div class='invoice-container'>
        <div class='invoice-header'>
            <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 110px; height: auto; margin-bottom: 8px;'>
            <h1>🧾 FACTURA</h1>
            <p>AutomatizaTech - Soluciones Digitales Profesionales</p>
        </div>
        
        <div class='invoice-info'>
            <div class='info-block'>
                <h3>📋 Datos de la Factura</h3>
                <p><span class='info-label'>N° Factura:</span> <strong>{$invoice_number}</strong></p>
                <p><span class='info-label'>Fecha:</span> {$invoice_date}</p>
                <p><span class='info-label'>Válido hasta:</span> " . date('d/m/Y', strtotime('+30 days')) . "</p>
            </div>
            
            <div class='info-block'>
                <h3>👤 Datos del Cliente</h3>
                <p><span class='info-label'>Nombre:</span> <strong>" . esc_html($client_data->name) . "</strong></p>
                <p><span class='info-label'>Email:</span> " . esc_html($client_data->email) . "</p>
                <p><span class='info-label'>Empresa:</span> " . esc_html($client_data->company) . "</p>
                <p><span class='info-label'>Teléfono:</span> " . esc_html($client_data->phone) . "</p>
            </div>
        </div>
        
        <div class='invoice-details'>
            <h2>💼 Detalle del Servicio Contratado</h2>
            
            <table class='service-table'>
                <thead>
                    <tr>
                        <th style='width: 60%'>Descripción</th>
                        <th style='width: 20%; text-align: center;'>Cantidad</th>
                        <th style='width: 20%; text-align: right;'>Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong style='color: {$primary_color}; font-size: 0.95em;'>" . esc_html($plan_data->name) . "</strong>
                            <div class='service-description'>" . esc_html($plan_data->description) . "</div>
                        </td>
                        <td style='text-align: center; font-size: 0.9em; font-weight: 600;'>1</td>
                        <td style='text-align: right; font-size: 0.95em; font-weight: 600;'>$" . number_format($subtotal, 0, ',', '.') . "</td>
                    </tr>
                </tbody>
            </table>
            
            <div class='totals'>
                <div class='row'>
                    <span class='label'>Subtotal:</span>
                    <span class='amount'>$" . number_format($subtotal, 0, ',', '.') . "</span>
                </div>
                <div class='row'>
                    <span class='label'>IVA (19%):</span>
                    <span class='amount'>$" . number_format($iva, 0, ',', '.') . "</span>
                </div>
                <div class='row total-row'>
                    <span class='label'>TOTAL:</span>
                    <span class='amount'>$" . number_format($total, 0, ',', '.') . "</span>
                </div>
            </div>
        </div>
        
        <div class='qr-validation' style='text-align: center; padding: 12px 30px; background: #f9fafb; border-top: 2px solid {$secondary_color};'>
            <h3 style='color: {$primary_color}; margin-bottom: 6px; font-size: 0.95em;'>🔒 Validación de Factura</h3>
            <p style='margin-bottom: 8px; color: #666; font-size: 0.85em;'>Escanea el QR para validar la autenticidad</p>";
    
    // Generar URL de validación para el QR (apunta directamente a la página de validación)
    $validation_url = $site_url . '/validar-factura.php?id=' . urlencode($invoice_number);
    
    // Generar QR Code en base64 con la URL de validación
    $qr_base64 = SimpleQRCode::generateBase64($validation_url, 120);
    
    $html .= "
            <img src='{$qr_base64}' alt='Código QR de Validación' style='width: 120px; height: 120px; border: 2px solid {$secondary_color}; border-radius: 6px; padding: 6px; background: white;'>
            <p style='margin-top: 4px; font-size: 0.7em; color: #888;'>
                Código: <strong style='color: {$primary_color};'>{$invoice_number}</strong>
            </p>
        </div>
        
        <div class='invoice-footer'>
            <div class='footer-column'>
                <div class='thank-you'>
                    ¡Gracias por confiar en AutomatizaTech! 🎉
                </div>
                <p style='margin-top: 8px; font-size: 0.75em; opacity: 0.85;'>
                    Generada: " . date('d/m/Y H:i') . "
                </p>
            </div>
            
            <div class='footer-column'>
                <h3>📞 Contacto</h3>
                <p>📧 info@automatizatech.shop</p>
                <p>📱 +56 9 6432 4169</p>
            </div>
            
            <div class='footer-column'>
                <h3>🌐 Web</h3>
                <p>{$site_url}</p>
                <p>Soluciones Digitales</p>
            </div>
        </div>
    </div>
</body>
</html>";
    
    return $html;
}
