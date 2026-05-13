<?php
/**
 * Handlers AJAX para descarga y validación de facturas
 * Automatiza Tech - Sistema de Facturación
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/../../at-path-safe.php';
require_once get_template_directory() . '/lib/at-auth-middleware.php';

/**
 * Handler AJAX para descargar facturas (solo administradores).
 * Registrado en wp_ajax_download_invoice (usuarios autenticados).
 */
function automatiza_download_invoice() {
    global $wpdb;

    // E1: requiere usuario autenticado con manage_options
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para descargar facturas', 'Error de autenticación', [ 'response' => 403 ] );
    }

    // Obtener número de factura y sanitizar estrictamente (E2: anti path-traversal)
    $invoice_number = isset( $_GET['invoice_number'] ) ? sanitize_text_field( $_GET['invoice_number'] ) : '';
    if ( empty( $invoice_number ) ) {
        wp_die( 'Número de factura no especificado', 'Error', [ 'response' => 400 ] );
    }
    // Aplicar at_safe_basename para evitar traversal en el glob() posterior
    $safe_invoice_number = at_safe_basename( $invoice_number );

    // Buscar factura en la base de datos
    $invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
    $invoice = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$invoices_table} WHERE invoice_number = %s AND status = 'active'",
        $safe_invoice_number
    ) );

    if ( ! $invoice ) {
        wp_die( 'Factura no encontrada', 'Error', [ 'response' => 404 ] );
    }

    // E1: verificar que el usuario puede acceder a esta factura
    if ( ! at_require_own_invoice( $invoice ) ) {
        wp_die( 'No tienes permiso para descargar esta factura.', 'Acceso denegado', [ 'response' => 403 ] );
    }

    // Construir ruta del archivo PDF
    $upload_dir   = wp_upload_dir();
    $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';

    // E2: buscar PDF usando basename seguro + at_path_inside para anti path-traversal
    $pdf_files = glob( $invoices_dir . $safe_invoice_number . '*.pdf' );

    if ( empty( $pdf_files ) ) {
        wp_die( 'Archivo PDF no encontrado para esta factura', 'Error', [ 'response' => 404 ] );
    }

    // Incrementar contador de descargas
    $wpdb->update(
        $invoices_table,
        [ 'download_count' => $invoice->download_count + 1 ],
        [ 'id' => $invoice->id ],
        [ '%d' ],
        [ '%d' ]
    );

    // E2: servir con helper seguro (valida path inside del directorio, streams el PDF)
    at_serve_protected_pdf( $pdf_files[0], $invoices_dir );
}

// Registrar handler para usuarios autenticados (admin y otros roles)
add_action( 'wp_ajax_download_invoice', 'automatiza_download_invoice' );


/**
 * Handler AJAX para validar facturas (acceso público)
 * Permite a clientes verificar la autenticidad de una factura sin login
 */
function automatiza_validate_invoice() {
    global $wpdb;
    
    // Obtener número de factura
    $invoice_number = isset($_GET['invoice_number']) ? sanitize_text_field($_GET['invoice_number']) : '';
    
    if (empty($invoice_number)) {
        wp_send_json_error(array(
            'message' => 'Número de factura no especificado'
        ));
    }
    
    // Buscar factura en la base de datos
    $invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            id, 
            invoice_number, 
            client_name, 
            client_email, 
            subtotal, 
            iva, 
            total, 
            created_at,
            validated_at
        FROM {$invoices_table} 
        WHERE invoice_number = %s AND status = 'active'",
        $invoice_number
    ));
    
    if (!$invoice) {
        wp_send_json_error(array(
            'message' => 'Factura no encontrada o inválida',
            'valid' => false
        ));
    }
    
    // Actualizar fecha de validación si es la primera vez
    if (empty($invoice->validated_at)) {
        $wpdb->update(
            $invoices_table,
            array('validated_at' => current_time('mysql')),
            array('id' => $invoice->id),
            array('%s'),
            array('%d')
        );
    }
    
    // Registrar validación en log
    error_log("Validación de factura: {$invoice_number} desde IP " . $_SERVER['REMOTE_ADDR']);
    
    // Formatear números para display
    $subtotal = !empty($invoice->subtotal) ? floatval($invoice->subtotal) : 0;
    $iva = !empty($invoice->iva) ? floatval($invoice->iva) : 0;
    $total = !empty($invoice->total) ? floatval($invoice->total) : 0;
    
    // Responder con datos de la factura
    wp_send_json_success(array(
        'valid' => true,
        'message' => 'Factura válida y verificada',
        'invoice' => array(
            'number' => $invoice->invoice_number,
            'client_name' => $invoice->client_name,
            'client_email' => $invoice->client_email,
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'date' => date('d/m/Y', strtotime($invoice->created_at)),
            'validated_at' => $invoice->validated_at ? date('d/m/Y H:i', strtotime($invoice->validated_at)) : 'Primera validación'
        )
    ));
}

// Registrar handler para usuarios autenticados
add_action('wp_ajax_validate_invoice', 'automatiza_validate_invoice');

// Registrar handler para acceso público (sin login)
add_action('wp_ajax_nopriv_validate_invoice', 'automatiza_validate_invoice');


/**
 * Shortcode para mostrar formulario de validación de facturas
 * Uso: [validar_factura]
 */
function automatiza_invoice_validation_form_shortcode() {
    ob_start();
    ?>
    <div class="invoice-validation-form" id="invoice-validation-container">
        <div class="validation-form-card">
            <h3>🔍 Validar Factura</h3>
            <p>Ingresa el número de factura para verificar su autenticidad</p>
            
            <form id="invoice-validation-form">
                <div class="form-group">
                    <label for="invoice_number">Número de Factura:</label>
                    <input 
                        type="text" 
                        id="invoice_number" 
                        name="invoice_number" 
                        class="form-control"
                        placeholder="Ej: AT-20241114-0001"
                        required
                    >
                </div>
                
                <button type="submit" class="btn btn-primary">Validar Factura</button>
            </form>
            
            <div id="validation-result" style="display: none; margin-top: 20px;"></div>
        </div>
    </div>
    
    <style>
        .invoice-validation-form {
            max-width: 500px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .validation-form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .validation-form-card h3 {
            color: #0073aa;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
        }
        
        .btn-primary {
            background: #0073aa;
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a87;
        }
        
        #validation-result {
            padding: 20px;
            border-radius: 5px;
        }
        
        #validation-result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        #validation-result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .invoice-details {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .invoice-details strong {
            display: inline-block;
            width: 120px;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#invoice-validation-form').on('submit', function(e) {
            e.preventDefault();
            
            var invoiceNumber = $('#invoice_number').val().trim();
            var resultDiv = $('#validation-result');
            
            if (!invoiceNumber) {
                resultDiv.removeClass('success').addClass('error');
                resultDiv.html('<strong>Error:</strong> Por favor ingresa un número de factura').show();
                return;
            }
            
            // Mostrar loading
            resultDiv.removeClass('success error').html('Validando...').show();
            
            // Hacer llamada AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'GET',
                data: {
                    action: 'validate_invoice',
                    invoice_number: invoiceNumber
                },
                success: function(response) {
                    if (response.success && response.data.valid) {
                        var invoice = response.data.invoice;
                        
                        // Función para formatear números con separador de miles
                        function formatMoney(amount) {
                            var num = parseFloat(amount) || 0;
                            return '$' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                        }
                        
                        var html = '<h4>✅ Factura Válida</h4>';
                        html += '<div class="invoice-details">';
                        html += '<p><strong>Número:</strong> ' + invoice.number + '</p>';
                        html += '<p><strong>Cliente:</strong> ' + invoice.client_name + '</p>';
                        html += '<p><strong>Email:</strong> ' + invoice.client_email + '</p>';
                        html += '<p><strong>Subtotal:</strong> ' + formatMoney(invoice.subtotal) + '</p>';
                        html += '<p><strong>IVA:</strong> ' + formatMoney(invoice.iva) + '</p>';
                        html += '<p><strong>Total:</strong> ' + formatMoney(invoice.total) + '</p>';
                        html += '<p><strong>Fecha emisión:</strong> ' + invoice.date + '</p>';
                        html += '</div>';
                        
                        resultDiv.removeClass('error').addClass('success').html(html);
                    } else {
                        resultDiv.removeClass('success').addClass('error');
                        resultDiv.html('<strong>❌ ' + response.data.message + '</strong>');
                    }
                },
                error: function() {
                    resultDiv.removeClass('success').addClass('error');
                    resultDiv.html('<strong>Error:</strong> No se pudo conectar con el servidor');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Registrar shortcode
add_shortcode('validar_factura', 'automatiza_invoice_validation_form_shortcode');
