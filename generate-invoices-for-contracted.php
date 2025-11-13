<?php
/**
 * Generar Facturas para Clientes Contratados
 * 
 * Este script genera facturas para todos los clientes que están actualmente contratados
 * y que aún no tienen una factura generada.
 */

// Cargar WordPress
require_once(__DIR__ . '/wp-load.php');

// Verificar si estamos en CLI o navegador
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Generar Facturas - AutomatizaTech</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #1e3a8a 0%, #06d6a0 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                padding: 40px;
                max-width: 900px;
                width: 100%;
            }
            h1 {
                color: #1e3a8a;
                margin-bottom: 20px;
                font-size: 2em;
                text-align: center;
            }
            .subtitle {
                color: #666;
                text-align: center;
                margin-bottom: 30px;
                font-size: 1.1em;
            }
            .result-box {
                background: #f9fafb;
                border-left: 4px solid #06d6a0;
                padding: 20px;
                margin: 15px 0;
                border-radius: 8px;
            }
            .success {
                border-left-color: #06d6a0;
                background: #f0fdf4;
            }
            .error {
                border-left-color: #dc3232;
                background: #fef2f2;
            }
            .warning {
                border-left-color: #f59e0b;
                background: #fffbeb;
            }
            .info {
                border-left-color: #1e3a8a;
                background: #eff6ff;
            }
            .result-box h3 {
                margin-bottom: 10px;
                font-size: 1.2em;
            }
            .result-box p {
                margin: 5px 0;
                line-height: 1.6;
            }
            .invoice-item {
                background: white;
                padding: 15px;
                margin: 10px 0;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
            }
            .invoice-item strong {
                color: #1e3a8a;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin: 30px 0;
            }
            .stat-card {
                background: linear-gradient(135deg, #1e3a8a, #1e40af);
                color: white;
                padding: 20px;
                border-radius: 10px;
                text-align: center;
            }
            .stat-card .number {
                font-size: 2.5em;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .stat-card .label {
                font-size: 0.9em;
                opacity: 0.9;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #06d6a0, #00a978);
                color: white;
                padding: 12px 30px;
                border-radius: 25px;
                text-decoration: none;
                font-weight: 600;
                margin: 10px 5px;
                transition: all 0.3s ease;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(6, 214, 160, 0.4);
            }
            .btn-secondary {
                background: linear-gradient(135deg, #1e3a8a, #1e40af);
            }
            .actions {
                text-align: center;
                margin-top: 30px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>📄 Generador de Facturas Masivo</h1>
            <p class='subtitle'>Procesando clientes contratados...</p>\n";
}

global $wpdb;

// Nombres de las tablas
$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
$invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
$services_table = $wpdb->prefix . 'automatiza_services';

// Contadores
$total_clients = 0;
$invoices_created = 0;
$invoices_existed = 0;
$errors = 0;
$results = [];

echo_message("🔍 Buscando clientes contratados...", 'info');

// Obtener todos los clientes contratados
$contracted_clients = $wpdb->get_results("
    SELECT * FROM {$clients_table} 
    WHERE contract_status = 'contracted'
    ORDER BY contracted_at DESC
");

$total_clients = count($contracted_clients);

if ($total_clients === 0) {
    echo_message("⚠️ No se encontraron clientes contratados", 'warning');
    finish_output();
    exit;
}

echo_message("✅ Se encontraron {$total_clients} clientes contratados", 'success');

// Cargar librería de QR Code
require_once(get_template_directory() . '/lib/qrcode.php');

// Procesar cada cliente
foreach ($contracted_clients as $client) {
    $client_name = esc_html($client->name);
    $client_id = $client->id;
    
    // Generar número de factura
    $invoice_number = 'AT-' . date('Ymd', strtotime($client->contracted_at)) . '-' . str_pad($client_id, 4, '0', STR_PAD_LEFT);
    
    // Verificar si ya existe la factura
    $existing_invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT id, invoice_number FROM {$invoices_table} WHERE invoice_number = %s",
        $invoice_number
    ));
    
    if ($existing_invoice) {
        $invoices_existed++;
        $results[] = [
            'status' => 'existed',
            'client' => $client_name,
            'invoice' => $invoice_number,
            'message' => 'Ya existe'
        ];
        continue;
    }
    
    // Obtener datos del plan
    if (!$client->plan_id) {
        $errors++;
        $results[] = [
            'status' => 'error',
            'client' => $client_name,
            'invoice' => $invoice_number,
            'message' => 'No tiene plan asignado'
        ];
        continue;
    }
    
    $plan_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$services_table} WHERE id = %d AND status = 'active'",
        $client->plan_id
    ));
    
    if (!$plan_data) {
        $errors++;
        $results[] = [
            'status' => 'error',
            'client' => $client_name,
            'invoice' => $invoice_number,
            'message' => 'Plan no encontrado o inactivo'
        ];
        continue;
    }
    
    // Generar factura HTML
    try {
        $invoice_html = generate_invoice_html_standalone($client, $plan_data);
        
        // Guardar en base de datos
        $subtotal = floatval($plan_data->price_clp);
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        $validation_url = site_url('/validar-factura.php?id=' . urlencode($invoice_number));
        
        $insert_result = $wpdb->insert(
            $invoices_table,
            [
                'invoice_number' => $invoice_number,
                'client_id' => $client->id,
                'client_name' => $client->name,
                'client_email' => $client->email,
                'plan_id' => $plan_data->id,
                'plan_name' => $plan_data->name,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'invoice_html' => $invoice_html,
                'invoice_file_path' => '',
                'qr_code_data' => $validation_url,
                'created_at' => current_time('mysql'),
                'status' => 'active'
            ],
            ['%s', '%d', '%s', '%s', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($insert_result) {
            $invoices_created++;
            $results[] = [
                'status' => 'created',
                'client' => $client_name,
                'invoice' => $invoice_number,
                'message' => 'Factura generada exitosamente',
                'total' => '$' . number_format($total, 0, ',', '.')
            ];
        } else {
            $errors++;
            $results[] = [
                'status' => 'error',
                'client' => $client_name,
                'invoice' => $invoice_number,
                'message' => 'Error al guardar en BD: ' . $wpdb->last_error
            ];
        }
        
    } catch (Exception $e) {
        $errors++;
        $results[] = [
            'status' => 'error',
            'client' => $client_name,
            'invoice' => $invoice_number,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Mostrar estadísticas
if (!$is_cli) {
    echo "<div class='stats'>
        <div class='stat-card'>
            <div class='number'>{$total_clients}</div>
            <div class='label'>Clientes Procesados</div>
        </div>
        <div class='stat-card' style='background: linear-gradient(135deg, #06d6a0, #00a978);'>
            <div class='number'>{$invoices_created}</div>
            <div class='label'>Facturas Creadas</div>
        </div>
        <div class='stat-card' style='background: linear-gradient(135deg, #f59e0b, #d97706);'>
            <div class='number'>{$invoices_existed}</div>
            <div class='label'>Ya Existían</div>
        </div>
        <div class='stat-card' style='background: linear-gradient(135deg, #dc3232, #c32d2d);'>
            <div class='number'>{$errors}</div>
            <div class='label'>Errores</div>
        </div>
    </div>";
}

// Mostrar resultados detallados
echo_message("📋 Resultados Detallados:", 'info');

foreach ($results as $result) {
    $status_icon = [
        'created' => '✅',
        'existed' => '📄',
        'error' => '❌'
    ][$result['status']];
    
    $status_class = [
        'created' => 'success',
        'existed' => 'warning',
        'error' => 'error'
    ][$result['status']];
    
    if (!$is_cli) {
        echo "<div class='invoice-item'>";
        echo "<strong>{$status_icon} {$result['client']}</strong><br>";
        echo "Factura: <code>{$result['invoice']}</code><br>";
        echo "Estado: {$result['message']}";
        if (isset($result['total'])) {
            echo "<br>Total: <strong>{$result['total']}</strong>";
        }
        echo "</div>";
    } else {
        echo "{$status_icon} {$result['client']} - {$result['invoice']} - {$result['message']}\n";
    }
}

// Mensaje final
if ($invoices_created > 0) {
    echo_message(
        "🎉 Proceso completado: {$invoices_created} factura(s) generada(s), {$invoices_existed} ya existían, {$errors} error(es)",
        'success'
    );
} else {
    echo_message(
        "ℹ️ No se generaron nuevas facturas. {$invoices_existed} ya existían, {$errors} error(es)",
        'warning'
    );
}

finish_output();

/**
 * Función auxiliar para generar HTML de factura
 */
function generate_invoice_html_standalone($client_data, $plan_data) {
    $invoice_number = 'AT-' . date('Ymd', strtotime($client_data->contracted_at)) . '-' . str_pad($client_data->id, 4, '0', STR_PAD_LEFT);
    $invoice_date = date('d/m/Y', strtotime($client_data->contracted_at));
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
        @page { size: A4; margin: 0; }
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
        .invoice-header img { max-width: 110px !important; margin-bottom: 8px !important; }
        .invoice-header h1 { font-size: 1.8em; margin-bottom: 5px; text-shadow: 2px 2px 4px rgba(0,0,0,0.2); }
        .invoice-header p { font-size: 1em; opacity: 0.9; }
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            padding: 20px 30px;
            background: #f9fafb;
        }
        .info-block {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid {$secondary_color};
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .info-block h3 { color: {$primary_color}; margin-bottom: 15px; font-size: 1.1em; }
        .info-block p { margin: 8px 0; font-size: 0.95em; }
        .info-label { font-weight: 600; color: #555; display: inline-block; width: 120px; }
        .invoice-details { padding: 25px 30px; }
        .invoice-details h2 {
            color: {$primary_color};
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 3px solid {$secondary_color};
            font-size: 1.3em;
        }
        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        .service-table thead { background: {$primary_color}; color: white; }
        .service-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        .service-table td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        .service-table tbody tr:hover { background: #f9fafb; }
        .service-description { color: #666; font-size: 0.9em; margin-top: 5px; }
        .totals {
            margin-top: 20px;
            text-align: right;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .totals .row { display: flex; justify-content: flex-end; margin: 8px 0; font-size: 1em; }
        .totals .label { margin-right: 40px; color: #555; font-weight: 600; min-width: 150px; text-align: right; }
        .totals .amount { min-width: 150px; text-align: right; font-weight: bold; }
        .total-row { border-top: 3px solid {$secondary_color}; padding-top: 15px; margin-top: 15px; }
        .total-row .label { color: {$primary_color}; font-size: 1.3em; }
        .total-row .amount { color: {$secondary_color}; font-size: 1.5em; }
        .invoice-footer {
            background: linear-gradient(135deg, {$primary_color}, {$secondary_color});
            color: white;
            padding: 15px 30px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            align-items: center;
        }
        .footer-column { text-align: left; }
        .footer-column h3 { font-size: 0.95em; margin-bottom: 8px; opacity: 0.95; }
        .footer-column p { margin: 4px 0; font-size: 0.85em; opacity: 0.9; }
        .thank-you {
            background: white;
            color: {$primary_color};
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 600;
            text-align: center;
        }
        .features-list { list-style: none; padding: 8px 0; }
        .features-list li { padding: 4px 0; padding-left: 20px; position: relative; font-size: 0.9em; }
        .features-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: {$secondary_color};
            font-weight: bold;
            font-size: 1.2em;
        }
        .qr-validation { page-break-inside: avoid; padding: 12px 30px !important; }
        .qr-validation h3 { font-size: 0.95em !important; margin-bottom: 6px !important; }
        .qr-validation p { margin-bottom: 8px !important; font-size: 0.85em !important; }
        .qr-validation img { max-width: 120px !important; height: auto !important; }
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
            <img src='{$logo_url}' alt='AutomatizaTech Logo'>
            <h1>🧾 FACTURA</h1>
            <p>AutomatizaTech - Soluciones Digitales Profesionales</p>
        </div>
        
        <div class='invoice-info'>
            <div class='info-block'>
                <h3>📋 Datos de la Factura</h3>
                <p><span class='info-label'>N° Factura:</span> <strong>{$invoice_number}</strong></p>
                <p><span class='info-label'>Fecha:</span> {$invoice_date}</p>
                <p><span class='info-label'>Válido hasta:</span> " . date('d/m/Y', strtotime($client_data->contracted_at . ' +30 days')) . "</p>
            </div>
            
            <div class='info-block'>
                <h3>👤 Datos del Cliente</h3>
                <p><span class='info-label'>Nombre:</span> <strong>" . esc_html($client_data->name) . "</strong></p>
                <p><span class='info-label'>Email:</span> " . esc_html($client_data->email) . "</p>
                " . ($client_data->company ? "<p><span class='info-label'>Empresa:</span> " . esc_html($client_data->company) . "</p>" : "") . "
                " . ($client_data->phone ? "<p><span class='info-label'>Teléfono:</span> " . esc_html($client_data->phone) . "</p>" : "") . "
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
                            <strong style='color: {$primary_color}; font-size: 1.1em;'>" . esc_html($plan_data->name) . "</strong>
                            <div class='service-description'>" . esc_html($plan_data->description) . "</div>
                            " . (!empty($plan_data->features) ? "
                            <ul class='features-list'>
                                " . implode('', array_map(function($feature) {
                                    return "<li>" . esc_html(trim($feature)) . "</li>";
                                }, explode("\n", $plan_data->features))) . "
                            </ul>
                            " : "") . "
                        </td>
                        <td style='text-align: center; font-size: 1.1em; font-weight: 600;'>1</td>
                        <td style='text-align: right; font-size: 1.1em; font-weight: 600;'>$" . number_format($subtotal, 0, ',', '.') . "</td>
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
            <h3 style='color: {$primary_color}; margin-bottom: 6px;'>🔒 Validación de Factura</h3>
            <p style='margin-bottom: 8px; color: #666;'>Escanea el QR para validar la autenticidad</p>";
    
    $validation_url = $site_url . '/validar-factura.php?id=' . urlencode($invoice_number);
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

/**
 * Función auxiliar para mostrar mensajes
 */
function echo_message($message, $type = 'info') {
    global $is_cli;
    
    if ($is_cli) {
        echo $message . "\n";
    } else {
        echo "<div class='result-box {$type}'><p>{$message}</p></div>";
    }
}

/**
 * Finalizar output
 */
function finish_output() {
    global $is_cli;
    
    if (!$is_cli) {
        echo "<div class='actions'>
            <a href='" . admin_url('admin.php?page=automatiza-tech-clients') . "' class='btn btn-secondary'>👥 Ver Clientes</a>
            <a href='" . site_url('/test-invoice-preview.php') . "' class='btn'>📄 Previsualizar Facturas</a>
        </div>
        </div>
    </body>
    </html>";
    }
}
