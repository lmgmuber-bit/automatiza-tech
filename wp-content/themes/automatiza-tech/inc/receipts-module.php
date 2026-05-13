<?php
/**
 * Automatiza Tech - Receipts Module
 * Módulo para generar boletas con items parametrizables
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/lib/receipt-pdf-fpdf.php';

class AutomatizaTechReceipts {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_tech_receipts';
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_generate_receipt', array($this, 'handle_generation'));
        add_action('wp_ajax_nopriv_generate_receipt', array($this, 'handle_generation')); // Permitir si se necesita desde frontend sin login, pero mejor restringir
        
        // Crear tabla al iniciar si no existe
        add_action('init', array($this, 'check_table'));
    }
    
    public function check_table() {
        global $wpdb;
        $table_name = $this->table_name;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                receipt_number varchar(50) NOT NULL,
                client_name varchar(100) NOT NULL,
                client_email varchar(100),
                client_phone varchar(20),
                client_tax_id varchar(50),
                items_json text NOT NULL,
                subtotal decimal(10,2),
                iva decimal(10,2),
                total decimal(10,2),
                pdf_path varchar(255),
                status varchar(20) DEFAULT 'active',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                validated_at datetime,
                PRIMARY KEY (id),
                UNIQUE KEY receipt_number (receipt_number)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Generador de Boletas',
            'Boletas',
            'manage_options',
            'automatiza-tech-receipts',
            array($this, 'render_admin_page'),
            'dashicons-tickets-alt',
            56
        );
    }
    
    public function render_admin_page() {

        // Obtener historial de boletas
        global $wpdb;
        $receipts = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY created_at DESC LIMIT 50");
        ?>
        <div class="wrap">
            <h1>Generador de Boletas Electrónicas</h1>
            <p>Genera boletas con items parametrizables, valida con QR y guarda un registro.</p>
            
            <div class="receipts-container">
                <h2>Nueva Boleta</h2>
                <form id="receipt-generator-form">
                    
                    <h3>Datos del Cliente</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="client_name">Nombre Cliente</label></th>
                            <td><input type="text" name="client_name" id="client_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="client_email">Email</label></th>
                            <td><input type="email" name="client_email" id="client_email" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="client_phone">Teléfono</label></th>
                            <td><input type="text" name="client_phone" id="client_phone" class="regular-text" placeholder="+569..."></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="client_tax_id">RUT / DNI</label></th>
                            <td><input type="text" name="client_tax_id" id="client_tax_id" class="regular-text" required></td>
                        </tr>
                    </table>
                    
                    <h3>Items de la Boleta</h3>
                    <table class="widefat striped" id="items-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th style="width: 80px;">Cantidad</th>
                                <th style="width: 120px;">Precio Unit. (CLP)</th>
                                <th style="width: 120px;">Subtotal</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body">
                            <!-- Items dinámicos aquí -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5">
                                    <button type="button" class="button" id="add-item-btn">+ Agregar Item</button>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="3" style="text-align: right;">Total CLP:</th>
                                <th style="text-align: right;" id="total-display">$0</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <input type="hidden" name="action" value="generate_receipt">
                        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('generate_receipt_nonce'); ?>">
                        <button type="submit" class="button button-primary button-large" id="generate-btn">Generar Boleta</button>
                    </div>
                </form>
                
                <div id="result-area" style="margin-top: 20px; display: none;">
                    <div class="notice notice-success inline">
                        <p><strong>¡Boleta generada con éxito!</strong></p>
                        <p>
                            <a href="#" id="download-link" class="button button-primary" target="_blank">📥 Descargar PDF</a>
                            <a href="#" id="validate-link" class="button" target="_blank">🔍 Ver Validación</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="receipts-history">
                <h2>Historial de Boletas (Últimas 50)</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>N° Boleta</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)): ?>
                            <tr>
                                <td colspan="6">No hay boletas generadas aún.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receipts as $receipt): 
                                $download_url = admin_url('admin-ajax.php?action=download_receipt_admin&id=' . $receipt->id);
                                $validation_url = site_url('validar-boleta.php?id=' . $receipt->receipt_number);
                            ?>
                            <tr>
                                <td data-label="N° Boleta"><strong><?php echo esc_html($receipt->receipt_number); ?></strong></td>
                                <td data-label="Fecha"><?php echo date('d/m/Y H:i', strtotime($receipt->created_at)); ?></td>
                                <td data-label="Cliente">
                                    <?php echo esc_html($receipt->client_name); ?><br>
                                    <small><?php echo esc_html($receipt->client_tax_id); ?></small>
                                </td>
                                <td data-label="Total">$<?php echo number_format($receipt->total, 0, ',', '.'); ?></td>
                                <td data-label="Estado">
                                    <span class="badge badge-success" style="background:#d4edda; color:#155724; padding:2px 6px; border-radius:4px;">Generada</span>
                                    <?php if ($receipt->validated_at): ?>
                                        <span title="Validada el <?php echo $receipt->validated_at; ?>">✅</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="">
                                    <a href="<?php echo $validation_url . '&action=download'; ?>" class="button button-small" target="_blank">📥 PDF</a>
                                    <a href="<?php echo $validation_url; ?>" class="button button-small" target="_blank">🔍 Ver</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        /* ========== BASE STYLES - BOLETAS ========== */
        .wrap {
            box-sizing: border-box;
        }
        .wrap *, .wrap *:before, .wrap *:after {
            box-sizing: inherit;
        }
        
        .receipts-container {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            max-width: 800px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        .receipts-history {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            border-radius: 8px;
            overflow: hidden;
        }
        
        /* ========== RESPONSIVE STYLES - BOLETAS ========== */
        
        /* Desktop & Large tablets */
        @media screen and (min-width: 1025px) {
            .receipts-container {
                max-width: 800px;
            }
        }
        
        /* Tablet (768px - 1024px) */
        @media screen and (max-width: 1024px) {
            .receipts-container {
                max-width: 100%;
            }
            #items-table th,
            #items-table td {
                padding: 8px 6px;
                font-size: 13px;
            }
        }
        
        /* Mobile (hasta 767px) */
        @media screen and (max-width: 767px) {
            .wrap {
                padding: 10px !important;
                margin-left: 0 !important;
            }
            .wrap h1 {
                font-size: 20px;
                margin-bottom: 10px;
            }
            .wrap > p {
                font-size: 13px;
            }
            
            /* Form container */
            .receipts-container {
                padding: 15px !important;
                margin-bottom: 15px !important;
            }
            
            .wrap h2 {
                font-size: 16px;
            }
            .wrap h3 {
                font-size: 14px;
            }
            
            /* Form table responsive */
            .form-table {
                display: block;
            }
            .form-table tbody {
                display: block;
            }
            .form-table tr {
                display: block;
                margin-bottom: 12px;
            }
            .form-table th {
                display: block;
                width: 100% !important;
                padding: 0 0 5px 0 !important;
                font-size: 13px;
            }
            .form-table td {
                display: block;
                width: 100%;
                padding: 0 !important;
            }
            .form-table input.regular-text {
                width: 100% !important;
                font-size: 16px !important;
                padding: 10px !important;
                box-sizing: border-box;
            }
            
            /* Items table responsive - card layout */
            #items-table {
                display: block;
                border: none;
            }
            #items-table thead {
                display: none;
            }
            #items-table tbody {
                display: block;
            }
            #items-table tbody tr {
                display: block;
                background: #f8f9fa;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 12px;
            }
            #items-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border: none;
            }
            #items-table tbody td:before {
                content: attr(data-label);
                font-weight: 600;
                color: #64748b;
                font-size: 12px;
                min-width: 100px;
            }
            #items-table tbody td:last-child {
                justify-content: flex-end;
            }
            #items-table tbody td:last-child:before {
                display: none;
            }
            #items-table input.widefat {
                width: 60% !important;
                font-size: 14px !important;
                padding: 8px !important;
                text-align: right;
            }
            #items-table tbody td:first-child input.widefat {
                width: 100% !important;
                text-align: left;
            }
            #items-table tbody td:first-child {
                flex-direction: column;
                align-items: flex-start;
            }
            #items-table tfoot {
                display: block;
            }
            #items-table tfoot tr {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
            }
            #items-table tfoot td,
            #items-table tfoot th {
                border: none;
                padding: 5px;
            }
            #items-table tfoot tr:first-child td {
                width: 100%;
            }
            
            /* History table - card layout for mobile */
            .receipts-history {
                padding: 15px !important;
            }
            .receipts-history .widefat {
                display: block;
                border: none;
            }
            .receipts-history .widefat thead {
                display: none;
            }
            .receipts-history .widefat tbody {
                display: block;
            }
            .receipts-history .widefat tbody tr {
                display: block;
                background: #f8f9fa;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 12px;
            }
            .receipts-history .widefat tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 6px 0;
                border: none;
            }
            .receipts-history .widefat tbody td:before {
                content: attr(data-label);
                font-weight: 600;
                color: #64748b;
                font-size: 12px;
            }
            .receipts-history .widefat tbody td:last-child {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding-top: 12px;
                border-top: 1px solid #e2e8f0;
                margin-top: 8px;
            }
            .receipts-history .widefat tbody td:last-child:before {
                display: none;
            }
            .receipts-history .widefat .button-small {
                width: 100%;
                text-align: center;
                padding: 10px;
                font-size: 13px;
            }
            
            /* Buttons */
            #generate-btn {
                width: 100%;
                padding: 12px 20px !important;
            }
            #add-item-btn {
                width: 100%;
                padding: 10px !important;
            }
            
            /* Result area */
            #result-area {
                margin-top: 15px;
            }
            #result-area .button {
                display: block;
                width: 100%;
                margin: 5px 0 !important;
                text-align: center;
                padding: 12px !important;
            }
        }
        
        /* Mobile Small (hasta 480px) */
        @media screen and (max-width: 480px) {
            .wrap {
                padding: 5px !important;
            }
            .wrap h1 {
                font-size: 18px;
            }
            
            .receipts-container,
            .receipts-history {
                padding: 10px !important;
                border-radius: 6px;
            }
            
            #items-table tbody tr,
            .receipts-history .widefat tbody tr {
                padding: 12px;
            }
            
            #items-table input.widefat {
                font-size: 16px !important; /* Prevent iOS zoom */
            }
            
            .form-table input.regular-text {
                font-size: 16px !important; /* Prevent iOS zoom */
            }
        }
        
        /* Touch improvements */
        @media (hover: none) and (pointer: coarse) {
            .form-table input.regular-text,
            #items-table input.widefat,
            #generate-btn,
            #add-item-btn,
            .button-small {
                min-height: 44px;
            }
            .remove-row {
                min-width: 44px;
                min-height: 44px;
            }
        }
        
        /* Fix WordPress admin bar on mobile */
        @media screen and (max-width: 782px) {
            .wrap {
                margin-top: 10px;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            
            // Función para agregar fila
            function addItemRow() {
                var rowId = Date.now();
                var html = `
                    <tr data-id="${rowId}">
                        <td data-label="Descripción"><input type="text" name="items[${rowId}][name]" class="widefat item-name" required placeholder="Descripción del servicio/producto"></td>
                        <td data-label="Cantidad"><input type="number" name="items[${rowId}][quantity]" class="widefat item-qty" value="1" min="1" required></td>
                        <td data-label="Precio Unit."><input type="number" name="items[${rowId}][price]" class="widefat item-price" value="0" min="0" required></td>
                        <td data-label="Subtotal" style="text-align: right; vertical-align: middle;" class="item-subtotal">$0</td>
                        <td data-label=""><button type="button" class="button remove-row"><span class="dashicons dashicons-trash" style="margin-top: 4px;"></span></button></td>
                    </tr>
                `;
                $('#items-body').append(html);
                updateTotal();
            }
            
            // Agregar primera fila
            addItemRow();
            
            // Eventos
            $('#add-item-btn').on('click', addItemRow);
            
            $(document).on('click', '.remove-row', function() {
                $(this).closest('tr').remove();
                updateTotal();
            });
            
            $(document).on('input', '.item-qty, .item-price', function() {
                var row = $(this).closest('tr');
                var qty = parseFloat(row.find('.item-qty').val()) || 0;
                var price = parseFloat(row.find('.item-price').val()) || 0;
                var subtotal = qty * price;
                row.find('.item-subtotal').text('$' + subtotal.toLocaleString('es-CL'));
                updateTotal();
            });
            
            function updateTotal() {
                var total = 0;
                $('#items-body tr').each(function() {
                    var qty = parseFloat($(this).find('.item-qty').val()) || 0;
                    var price = parseFloat($(this).find('.item-price').val()) || 0;
                    total += qty * price;
                });
                $('#total-display').text('$' + total.toLocaleString('es-CL'));
            }
            
            // Submit form
            $('#receipt-generator-form').on('submit', function(e) {
                e.preventDefault();
                var btn = $('#generate-btn');
                btn.prop('disabled', true).text('Generando...');
                $('#result-area').slideUp();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        btn.prop('disabled', false).text('Generar Boleta');
                        if (response.success) {
                            $('#download-link').attr('href', response.data.pdf_url);
                            $('#validate-link').attr('href', response.data.validation_url);
                            $('#result-area').slideDown();
                            alert('Boleta generada correctamente.');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text('Generar Boleta');
                        console.error('AJAX Error:', xhr);
                        
                        var msg = 'Error de conexión.';
                        if (xhr.status === 200 && xhr.responseText) {
                            // A veces WP devuelve 200 pero con error en el body si hay warnings PHP
                             msg = 'Error procesando respuesta (posible error PHP).';
                        } else if (xhr.status !== 200) {
                             msg = 'Error del servidor: ' + xhr.status + ' ' + xhr.statusText;
                        }
                        
                        console.log('Response Body:', xhr.responseText);
                        alert(msg + '\n\nRespuesta del servidor:\n' + xhr.responseText.substring(0, 500));
                    }
                });
            });
            
        });
        </script>
        <?php
    }
    
    public function handle_generation() {
        // Log start
        error_log('Receipt Generation Started');
        
        try {
            if (!check_ajax_referer('generate_receipt_nonce', 'nonce', false)) {
                throw new Exception('Error de seguridad (Nonce invÃ¡lido)');
            }
            
            if (!current_user_can('manage_options')) {
                throw new Exception('No tienes permisos.');
            }
            
            $client_name = sanitize_text_field($_POST['client_name']);
            $client_email = sanitize_email($_POST['client_email']);
            $client_phone = sanitize_text_field($_POST['client_phone']);
            $client_tax_id = sanitize_text_field($_POST['client_tax_id']);
            
            if (empty($client_name) || empty($client_tax_id)) {
                throw new Exception('Nombre y RUT son obligatorios.');
            }
            
            $raw_items = isset($_POST['items']) ? $_POST['items'] : array();
            if (empty($raw_items)) {
                throw new Exception('Debes agregar al menos un item.');
            }
            
            $items = array();
            $total = 0;
            
            foreach ($raw_items as $item) {
                $name = sanitize_text_field($item['name']);
                $qty = intval($item['quantity']);
                $price = floatval($item['price']);
                
                if ($qty > 0) { // Permitir precio 0 si es bonus
                    $items[] = array(
                        'name' => $name,
                        'quantity' => $qty,
                        'price' => $price, // Precio unitario en CLP
                        'price_clp' => $price, // Identificador para la clase PDF
                        'price_usd' => 0 // No manejamos USD en este modulo simple
                    );
                    $total += ($qty * $price);
                }
            }
            
            if (empty($items)) {
                throw new Exception('Items invÃ¡lidos.');
            }
            
            // Generar nÃºmero de boleta secuencial o basado en fecha
            // Formato: BOL-YYYYMMDD-XXXX
            global $wpdb;
            $today = date('Ymd');
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()") + 1;
            $receipt_number = 'BOL-' . $today . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            
            // Preparar datos para PDF
            $client_data = (object) array(
                'name' => $client_name,
                'email' => $client_email,
                'phone' => $client_phone,
                'tax_id' => $client_tax_id,
                'country' => 'CL' // Asumimos CL para boletas
            );
            
            // Items a objetos para compatibilidad con clase PDF
            $items_objects = array();
            foreach ($items as $it) {
                $obj = (object) $it;
                $items_objects[] = $obj;
            }
            
            // Generar PDF
            $upload_dir = wp_upload_dir();
            $receipts_dir = $upload_dir['basedir'] . '/automatiza-tech-receipts/';
            if (!file_exists($receipts_dir)) {
                wp_mkdir_p($receipts_dir);
            }
            
            $pdf_filename = $receipt_number . '.pdf';
            $pdf_path = $receipts_dir . $pdf_filename;
            $pdf_url = $upload_dir['baseurl'] . '/automatiza-tech-receipts/' . $pdf_filename;
            
            if (!class_exists('ReceiptPDFFPDF')) {
                require_once get_template_directory() . '/lib/receipt-pdf-fpdf.php';
            }

            $pdf = new ReceiptPDFFPDF($client_data, $items_objects, $receipt_number);
            $pdf->build();
            $pdf->Output('F', $pdf_path);
            
            // Guardar en BD
            // Calculo inverso de IVA (asumiendo total con IVA)
            $total_con_iva = $total;
            $neto = round($total_con_iva / 1.19);
            $iva = $total - $neto;
            
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'receipt_number' => $receipt_number,
                    'client_name' => $client_name,
                    'client_email' => $client_email,
                    'client_phone' => $client_phone,
                    'client_tax_id' => $client_tax_id,
                    'items_json' => json_encode($items),
                    'subtotal' => $neto,
                    'iva' => $iva,
                    'total' => $total_con_iva,
                    'pdf_path' => $pdf_path,
                    'status' => 'active',
                    'download_token' => bin2hex( random_bytes( 24 ) ),
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Error guardando en BD: ' . $wpdb->last_error);
            }
            
            // Enviar Correo con la boleta adjunta
            // Envolvemos en try/catch para evitar falla total si falla el mail (común en local)
            $email_sent = false;
            try {
                $email_sent = $this->send_receipt_email($client_data, $items, $receipt_number, $total_con_iva, $pdf_path);
            } catch (Throwable $e) {
                error_log('Receipt Module Warning: Failed to send email: ' . $e->getMessage());
            }
            
            // URL validacion
            $validation_url = site_url('validar-boleta.php?id=' . $receipt_number);
            
            wp_send_json_success(array(
                'receipt_number' => $receipt_number,
                'pdf_url' => $pdf_url,
                'validation_url' => $validation_url,
                'email_sent' => $email_sent
            ));

        } catch (Throwable $e) {
            error_log('Receipt Gen Critical Error: ' . $e->getMessage());
            wp_send_json_error('Error del Servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Enviar correo con boleta adjunta
     */
    private function send_receipt_email($client_data, $items, $receipt_number, $total, $pdf_path) {
        $to = $client_data->email;
        if (!is_email($to)) {
            return false;
        }

        $subject = "Tu Boleta Electrónica {$receipt_number} - AutomatizaTech";
        
        // Estilos y Colores
        $bg_color = "#f5f8fa";
        $card_bg = "#ffffff";
        $primary_color = "#0096C7";
        $text_color = "#333333";
        
        // Construir cuerpo del correo HTML
        $message = '<!DOCTYPE html><html><body style="margin:0;padding:0;font-family:Helvetica,Arial,sans-serif;background-color:'.$bg_color.';">';
        $message .= '<div style="max-width:600px;margin:0 auto;padding:40px 20px;">';
        
        // Card Container
        $message .= '<div style="background-color:'.$card_bg.';border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.05);overflow:hidden;">';
        
        // Header con Logo (si existe URL pública) o Título
        $message .= '<div style="background-color:'.$primary_color.';padding:30px;text-align:center;">';
        $message .= '<h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:bold;">¡Gracias por tu compra!</h1>';
        $message .= '</div>';
        
        // Contenido Principal
        $message .= '<div style="padding:40px 30px;">';
        $message .= '<p style="color:'.$text_color.';font-size:16px;line-height:1.5;margin-top:0;">Hola <strong>' . $client_data->name . '</strong>,</p>';
        $message .= '<p style="color:'.$text_color.';font-size:16px;line-height:1.5;">Adjunto encontrarás tu <strong>Boleta Electrónica</strong> correspondiente a los servicios contratados.</p>';
        
        // Resumen de la Boleta
        $message .= '<div style="background-color:#f8f9fa;border-radius:8px;padding:20px;margin:25px 0;">';
        $message .= '<table style="width:100%;border-collapse:collapse;">';
        $message .= '<tr><td style="padding:5px 0;color:#666;font-size:14px;">N° Boleta:</td><td style="padding:5px 0;text-align:right;font-weight:bold;color:#333;">' . $receipt_number . '</td></tr>';
        $message .= '<tr><td style="padding:5px 0;color:#666;font-size:14px;">Fecha:</td><td style="padding:5px 0;text-align:right;font-weight:bold;color:#333;">' . date('d/m/Y') . '</td></tr>';
        $message .= '<tr><td style="padding:10px 0 0 0;border-top:1px solid #eee;color:#333;font-weight:bold;">Total:</td><td style="padding:10px 0 0 0;border-top:1px solid #eee;text-align:right;font-weight:bold;color:'.$primary_color.';font-size:18px;">$' . number_format($total, 0, ',', '.') . '</td></tr>';
        $message .= '</table>';
        $message .= '</div>';
        
        // Botón de Acción
        $validation_url = site_url('validar-boleta.php?id=' . $receipt_number);
        $message .= '<div style="text-align:center;margin-top:30px;">';
        $message .= '<a href="' . $validation_url . '" style="display:inline-block;background-color:'.$primary_color.';color:#ffffff;text-decoration:none;padding:12px 30px;border-radius:50px;font-weight:bold;box-shadow:0 4px 6px rgba(0,150,199,0.2);">Ver Boleta en Línea</a>';
        $message .= '</div>';
        
        $message .= '</div>'; // Fin padding content
        
        // Footer del Card
        $company_name = get_option('company_name', 'AutomatizaTech SpA');
        $message .= '<div style="background-color:#fafafa;padding:20px;text-align:center;border-top:1px solid #eeeeee;">';
        $message .= '<p style="color:#999;font-size:12px;margin:0;">' . $company_name . ' - Transformación Digital</p>';
        $message .= '</div>';
        
        $message .= '</div>'; // Fin Card
        
        // Footer General
        $message .= '<p style="text-align:center;color:#999;font-size:12px;margin-top:20px;">Este correo fue generado automáticamente. Por favor no responder a esta dirección.</p>';
        $message .= '</div></body></html>';
        
        // Headers
        $from_email = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <' . $from_email . '>'
        );
        
        // Adjuntos
        $attachments = array();
        if (file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }
        
        return wp_mail($to, $subject, $message, $headers, $attachments);
    }
}

new AutomatizaTechReceipts();
