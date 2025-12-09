<?php
/**
 * Sistema de Validación y Descarga de Facturas
 * URL: /validar-factura.php
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require('wp-load.php');

global $wpdb;

// Obtener ID de la factura desde la URL
$invoice_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

if (empty($invoice_id)) {
    wp_die('❌ Error: No se proporcionó un número de factura válido.', 'Error de Validación', ['response' => 400]);
}

// Buscar la factura en la base de datos
$invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
$clients_table = $wpdb->prefix . 'automatiza_tech_clients';
$plans_table = $wpdb->prefix . 'automatiza_services';

// Intentar obtener datos completos, incluyendo joins por si faltan datos en la tabla de facturas
$invoice = $wpdb->get_row($wpdb->prepare(
    "SELECT i.*, c.name as client_name_joined, p.name as plan_name_joined 
     FROM {$invoices_table} i
     LEFT JOIN {$clients_table} c ON i.client_id = c.id
     LEFT JOIN {$plans_table} p ON i.plan_id = p.id
     WHERE i.invoice_number = %s AND i.status = 'active'",
    $invoice_id
));

if (!$invoice) {
    wp_die('❌ Error: Factura no encontrada o inválida.', 'Factura No Encontrada', ['response' => 404]);
}

// Rellenar datos faltantes si es necesario
if (empty($invoice->client_name) && !empty($invoice->client_name_joined)) {
    $invoice->client_name = $invoice->client_name_joined;
}
if (empty($invoice->plan_name) && !empty($invoice->plan_name_joined)) {
    $invoice->plan_name = $invoice->plan_name_joined;
}

// Determinar la acción (validar o descargar)
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'validate';

if ($action === 'download') {
    // DESCARGAR FACTURA EN PDF
    
    // Construir ruta del archivo PDF
    $upload_dir = wp_upload_dir();
    $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
    
    // Buscar el archivo PDF
    $pdf_files = glob($invoices_dir . $invoice->invoice_number . '*.pdf');
    
    if (empty($pdf_files)) {
        wp_die('❌ Archivo PDF no encontrado para esta factura. Contacta al administrador.', 'Error', ['response' => 404]);
    }
    
    $pdf_file = $pdf_files[0];
    
    // Verificar que el archivo existe y es legible
    if (!file_exists($pdf_file) || !is_readable($pdf_file)) {
        wp_die('❌ No se puede acceder al archivo PDF. Contacta al administrador.', 'Error', ['response' => 500]);
    }
    
    // Actualizar contador de descargas
    $wpdb->update(
        $invoices_table,
        [
            'download_count' => $invoice->download_count + 1,
            'validated_at' => current_time('mysql')
        ],
        ['id' => $invoice->id],
        ['%d', '%s'],
        ['%d']
    );
    
    // Enviar headers para descarga
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdf_file) . '"');
    header('Content-Length: ' . filesize($pdf_file));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Limpiar cualquier salida anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    flush();
    
    // Enviar el archivo
    readfile($pdf_file);
    exit;
    
} else {
    // VALIDAR FACTURA (Página de validación)
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>✅ Factura Validada - <?php echo esc_html($invoice->invoice_number); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .validation-container {
                max-width: 600px;
                background: white;
                border-radius: 15px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
                animation: slideIn 0.5s ease-out;
            }
            @keyframes slideIn {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .header {
                background: linear-gradient(135deg, #1e3a8a, #06d6a0);
                color: white;
                padding: 40px;
                text-align: center;
            }
            .header h1 {
                font-size: 2.5em;
                margin-bottom: 10px;
            }
            .header p {
                font-size: 1.2em;
                opacity: 0.9;
            }
            .content {
                padding: 40px;
            }
            .success-badge {
                background: #d1fae5;
                color: #065f46;
                padding: 20px;
                border-radius: 10px;
                text-align: center;
                margin-bottom: 30px;
                border-left: 5px solid #10b981;
            }
            .success-badge h2 {
                font-size: 1.5em;
                margin-bottom: 10px;
            }
            .info-grid {
                display: grid;
                gap: 15px;
                margin: 20px 0;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 15px;
                background: #f9fafb;
                border-radius: 8px;
                border-left: 3px solid #06d6a0;
            }
            .info-label {
                font-weight: 600;
                color: #1e3a8a;
            }
            .info-value {
                color: #333;
                text-align: right;
            }
            .download-btn {
                display: block;
                width: 100%;
                padding: 18px;
                background: linear-gradient(135deg, #1e3a8a, #06d6a0);
                color: white;
                text-align: center;
                text-decoration: none;
                border-radius: 10px;
                font-size: 1.2em;
                font-weight: 600;
                margin-top: 30px;
                transition: all 0.3s;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }
            .download-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }
            .footer {
                text-align: center;
                padding: 20px;
                background: #f9fafb;
                color: #666;
                font-size: 0.9em;
            }
            .security-icon {
                font-size: 3em;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="validation-container">
            <div class="header">
                <div class="security-icon">🔒</div>
                <h1>Factura Validada</h1>
                <p>AutomatizaTech</p>
            </div>
            
            <div class="content">
                <div class="success-badge">
                    <h2>✅ Esta factura es auténtica y válida</h2>
                    <p>Emitida por AutomatizaTech</p>
                </div>
                
                <div class="info-grid">
                    <div class="info-row">
                        <span class="info-label">📄 Número de Factura:</span>
                        <span class="info-value"><strong><?php echo esc_html($invoice->invoice_number); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">👤 Cliente:</span>
                        <span class="info-value"><?php echo esc_html($invoice->client_name); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">💼 Plan:</span>
                        <span class="info-value"><?php echo esc_html($invoice->plan_name); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">💰 Total:</span>
                        <span class="info-value"><strong>$<?php echo number_format($invoice->total, 0, ',', '.'); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📅 Fecha de Emisión:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($invoice->created_at)); ?></span>
                    </div>
                    <?php if ($invoice->validated_at): ?>
                    <div class="info-row">
                        <span class="info-label">✓ Última Validación:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($invoice->validated_at)); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">📥 Descargas:</span>
                        <span class="info-value"><?php echo intval($invoice->download_count); ?> veces</span>
                    </div>
                </div>
                
                <a href="?id=<?php echo urlencode($invoice->invoice_number); ?>&action=download" class="download-btn">
                    💾 Descargar Factura Completa
                </a>
            </div>
            
            <div class="footer">
                <p>🔐 Sistema de validación seguro de AutomatizaTech</p>
                <p style="margin-top: 5px;">Este documento ha sido verificado en nuestra base de datos</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
    // Actualizar fecha de validación
    if (!$invoice->validated_at) {
        $wpdb->update(
            $invoices_table,
            ['validated_at' => current_time('mysql')],
            ['id' => $invoice->id],
            ['%s'],
            ['%d']
        );
    }
    
    exit;
}
