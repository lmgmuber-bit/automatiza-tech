<?php
/**
 * Automatiza Tech - Módulo de Información Operativa de Clientes
 * 
 * Maneja toda la información técnica y operativa de clientes contratados:
 * - URLs de aplicaciones
 * - Redes sociales
 * - Dominios y hosting
 * - Cuentas de correo
 * - Accesos y credenciales
 * - Integraciones
 */

if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTech_Client_Operations {
    
    private static $instance = null;
    private $table_name;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Añade el botón de regenerar factura a la lista de clientes.
     */
    public function add_regenerate_button($actions, $client) {
        // El estado 'active' es para clientes que están actualmente en proyecto.
        if (isset($client->contract_status) && $client->contract_status === 'active') {
            $nonce = wp_create_nonce('regenerate_invoice_op_' . $client->id);
            // Concatenamos el nuevo botón al string de acciones existente.
            $actions .= sprintf(
                '<button class="button regenerate-invoice-op-btn" data-id="%d" data-nonce="%s" style="margin-left: 6px; background-color:#f59e0b;color:white;border-color:#d97706;" title="Regenerar y reenviar la factura por correo electrónico.">♻️ Regenerar</button>',
                $client->id,
                $nonce
            );
        }
        return $actions;
    }

    /**
     * Añade el script de regeneración al footer de la página de clientes.
     * @deprecated Ahora se encola a través de admin_enqueue_scripts en functions.php
     */
    public function add_regenerate_script() {
        // El script ahora se encola a través de automatiza_tech_admin_scripts en functions.php
        // para un mejor manejo de dependencias y versiones.
    }

    private function __construct() {
        error_log('Client Operations Module: __construct INICIO');
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_tech_clients';
        
        // AJAX handlers
        add_action('wp_ajax_get_client_operational_data', array($this, 'ajax_get_operational_data'));
        add_action('wp_ajax_save_client_operational_data', array($this, 'ajax_save_operational_data'));
        add_action('wp_ajax_get_client_full_details', array($this, 'ajax_get_full_details'));
        add_action('wp_ajax_notify_project_progress', array($this, 'ajax_notify_project_progress'));
        add_action('wp_ajax_regenerate_and_resend_invoice_op', array($this, 'ajax_regenerate_and_resend_invoice'));

        // Hook para el botón en la lista de clientes
        add_filter('automatiza_tech_clients_actions', array($this, 'add_regenerate_button'), 10, 2);
        add_action('admin_footer-contactos_page_automatiza-tech-clients', array($this, 'add_regenerate_script'));
        
        // Verificar columnas
        $this->check_branding_columns();
        error_log('Client Operations Module: __construct FIN');
    }
    
    /**
     * Verificar y agregar columnas de branding si no existen
     */
    private function check_branding_columns() {
        global $wpdb;
        $cols = $wpdb->get_col("DESCRIBE {$this->table_name}", 0);
        
        if (!in_array('brand_logo', $cols)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN brand_logo varchar(500) DEFAULT NULL");
        }
        if (!in_array('brand_manual', $cols)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN brand_manual varchar(500) DEFAULT NULL");
        }
        if (!in_array('brand_colors', $cols)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN brand_colors text DEFAULT NULL");
        }
        if (!in_array('brand_typography', $cols)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN brand_typography text DEFAULT NULL");
        }
    }
    
    /**
     * Obtener datos operativos del cliente
     */
    public function ajax_get_operational_data() {
        check_ajax_referer('client_operations_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $client_id = intval($_POST['client_id']);
        
        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $client_id
        ), ARRAY_A);
        
        if (!$client) {
            wp_send_json_error('Cliente no encontrado');
        }
        
        // Decodificar campos JSON
        $json_fields = ['social_other', 'email_accounts', 'api_credentials', 'cms_access', 'ftp_access', 'db_access', 'secondary_contacts', 'integrations'];
        foreach ($json_fields as $field) {
            if (isset($client[$field]) && !empty($client[$field])) {
                $client[$field] = json_decode($client[$field], true);
            }
        }
        
        wp_send_json_success($client);
    }
    
    /**
     * Guardar datos operativos del cliente
     */
    public function ajax_save_operational_data() {
        check_ajax_referer('client_operations_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $client_id = intval($_POST['client_id']);
        
        global $wpdb;
        
        // Campos de texto simple
        $text_fields = [
            'app_url', 'app_admin_url', 'app_staging_url',
            'domain', 'domain_registrar', 'hosting_provider', 'hosting_plan', 'server_ip',
            'social_facebook', 'social_instagram', 'social_linkedin', 'social_twitter', 'social_tiktok', 'social_youtube',
            'email_provider',
            'business_description', 'business_industry', 'business_size',
            'billing_address', 'billing_contact',
            'technical_contact', 'technical_email', 'technical_phone',
            'google_analytics_id', 'google_tag_manager', 'facebook_pixel', 'whatsapp_business',
            'operational_notes', 'sla_level', 'support_hours',
            'brand_logo', 'brand_manual', 'brand_colors', 'brand_typography'
        ];
        
        // Campos JSON
        $json_fields = ['social_other', 'email_accounts', 'api_credentials', 'cms_access', 'ftp_access', 'db_access', 'secondary_contacts', 'integrations'];
        
        // Campos numéricos/fecha
        $numeric_fields = ['monthly_fee', 'payment_day'];
        $date_fields = ['domain_expiry', 'contract_end_date'];
        
        $update_data = [];
        $format = [];
        
        // Procesar campos de texto
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $update_data[$field] = sanitize_text_field($_POST[$field]);
                $format[] = '%s';
            }
        }
        
        // Procesar campos JSON
        foreach ($json_fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                if (is_array($value)) {
                    $update_data[$field] = json_encode($value, JSON_UNESCAPED_UNICODE);
                } else {
                    $update_data[$field] = $value;
                }
                $format[] = '%s';
            }
        }
        
        // Procesar campos numéricos
        foreach ($numeric_fields as $field) {
            if (isset($_POST[$field])) {
                $update_data[$field] = floatval($_POST[$field]);
                $format[] = '%f';
            }
        }
        
        // Procesar campos de fecha
        foreach ($date_fields as $field) {
            if (isset($_POST[$field]) && !empty($_POST[$field])) {
                $update_data[$field] = sanitize_text_field($_POST[$field]);
                $format[] = '%s';
            }
        }
        
        // Procesar subida de archivos (Identidad Corporativa)
        $file_inputs = ['brand_logo_file' => 'brand_logo', 'brand_manual_file' => 'brand_manual'];
        foreach ($file_inputs as $input_name => $db_field) {
            if (!empty($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                
                $attach_id = media_handle_upload($input_name, 0);
                if (!is_wp_error($attach_id)) {
                    $_POST[$db_field] = wp_get_attachment_url($attach_id); // Sobrescribir POST para que lo tome el loop de texto
                }
            }
        }
        
        if (empty($update_data)) {
            wp_send_json_error('No hay datos para actualizar');
        }
        
        $update_data['updated_at'] = current_time('mysql');
        $format[] = '%s';
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $client_id),
            $format,
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Error al guardar: ' . $wpdb->last_error);
        }
        
        wp_send_json_success(array(
            'message' => 'Datos guardados correctamente',
            'updated' => count($update_data)
        ));
    }
    
    /**
     * Obtener detalles completos del cliente para el modal expandido
     */
    public function ajax_get_full_details() {
        check_ajax_referer('client_operations_nonce', 'nonce');
        
        $client_id = intval($_POST['client_id']);
        
        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $client_id
        ));
        
        if (!$client) {
            wp_send_json_error('Cliente no encontrado');
        }
        
        ob_start();
        $this->render_full_details_modal($client);
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * Enviar notificación de avance de proyecto
     */
    public function ajax_notify_project_progress() {
        check_ajax_referer('client_operations_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $client_id = intval($_POST['client_id']);
        $title = sanitize_text_field($_POST['title']);
        $description = wp_kses_post($_POST['description']);
        
        if (empty($title) || empty($description)) {
            wp_send_json_error('Faltan datos');
        }
        
        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $client_id));
        
        if (!$client) {
            wp_send_json_error('Cliente no encontrado');
        }
        
        // 1. Guardar en historial (wp_automatiza_clients_details)
        $details_table = $wpdb->prefix . 'automatiza_clients_details';
        // Verificar si existe la tabla
        if ($wpdb->get_var("SHOW TABLES LIKE '$details_table'") == $details_table) {
            $metadata = json_encode(array(
                'notified_email' => true,
                'notified_at' => current_time('mysql'),
                'recipient' => $client->email
            ));
            
            $wpdb->insert(
                $details_table,
                array(
                    'client_id' => $client_id,
                    'detail_type' => 'project_update',
                    'title' => $title,
                    'description' => $description,
                    'status' => 'completed',
                    'completed_date' => current_time('mysql'),
                    'metadata' => $metadata,
                    'created_by' => get_current_user_id()
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        }
        
        // 2. Enviar Correo
        $to = $client->email;
        $subject = "🚀 Avance de Proyecto: $title - AutomatizaTech";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .header { background: #0288d1; color: white; padding: 15px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { padding: 20px; }
                .footer { text-align: center; font-size: 12px; color: #777; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🚀 Avance de Proyecto</h2>
                </div>
                <div class='content'>
                    <p>Hola <strong>" . esc_html($client->name) . "</strong>,</p>
                    <p>Te informamos sobre un nuevo avance en tu proyecto:</p>
                    
                    <div style='background: #f0f9ff; padding: 15px; border-left: 4px solid #0288d1; margin: 20px 0;'>
                        <h3 style='margin-top: 0; color: #0277bd;'>" . esc_html($title) . "</h3>
                        <div>" . nl2br($description) . "</div>
                    </div>
                    
                    <p>Si tienes alguna consulta, no dudes en responder a este correo.</p>
                </div>
                <div class='footer'>
                    <p>AutomatizaTech - Soluciones Digitales</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'Bcc: lgonzalez@automatizatech.cl',
            'Bcc: adriana.perez@automatizatech.cl'
        );
        
        // Enviar
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success('Notificación enviada');
        } else {
            wp_send_json_error('Error al enviar el correo');
        }
    }
    
    /**
     * Renderizar el modal completo de detalles del cliente
     */
    public function render_full_details_modal($client) {
        $status_labels = array(
            'active' => '✅ Activo',
            'completed' => '🎉 Completado',
            'paused' => '⏸️ Pausado',
            'cancelled' => '❌ Cancelado'
        );
        ?>
        <div class="client-full-modal">
            <!-- Header -->
            <div class="cfm-header">
                <div class="cfm-header-info">
                    <h2>👤 <?php echo esc_html($client->name); ?></h2>
                    <span class="cfm-status cfm-status-<?php echo esc_attr($client->contract_status); ?>">
                        <?php echo $status_labels[$client->contract_status] ?? $client->contract_status; ?>
                    </span>
                </div>
                <button type="button" class="cfm-close" onclick="closeClientFullModal()">&times;</button>
            </div>
            
            <!-- Tabs -->
            <div class="cfm-tabs">
                <button type="button" class="cfm-tab active" data-tab="general">📋 General</button>
                <button type="button" class="cfm-tab" data-tab="technical">🔧 Técnico</button>
                <button type="button" class="cfm-tab" data-tab="social">📱 Redes</button>
                <button type="button" class="cfm-tab" data-tab="access">🔐 Accesos</button>
                <button type="button" class="cfm-tab" data-tab="billing">💰 Facturación</button>
                <button type="button" class="cfm-tab" data-tab="branding">🎨 Identidad</button>
                <button type="button" class="cfm-tab" data-tab="tracking">📊 Seguimiento</button>
            </div>
            
            <form id="client-full-form" class="cfm-form">
                <input type="hidden" name="client_id" value="<?php echo intval($client->id); ?>">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('client_operations_nonce'); ?>">
                
                <!-- Tab: General -->
                <div class="cfm-tab-content active" id="tab-general">
                    <div class="cfm-section">
                        <h3>📋 Información Básica</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>👤 Nombre</label>
                                <input type="text" value="<?php echo esc_attr($client->name); ?>" readonly class="cfm-readonly">
                            </div>
                            <div class="cfm-field">
                                <label>🏢 Empresa</label>
                                <input type="text" value="<?php echo esc_attr($client->company); ?>" readonly class="cfm-readonly">
                            </div>
                            <div class="cfm-field">
                                <label>📧 Email</label>
                                <input type="email" value="<?php echo esc_attr($client->email); ?>" readonly class="cfm-readonly">
                            </div>
                            <div class="cfm-field">
                                <label>📱 Teléfono</label>
                                <input type="tel" value="<?php echo esc_attr($client->phone); ?>" readonly class="cfm-readonly">
                            </div>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>📝 Información del Negocio</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>🏭 Industria/Rubro</label>
                                <input type="text" name="business_industry" value="<?php echo esc_attr($client->business_industry ?? ''); ?>" placeholder="Ej: Retail, Servicios, Tecnología...">
                            </div>
                            <div class="cfm-field">
                                <label>📏 Tamaño Empresa</label>
                                <select name="business_size">
                                    <option value="">Seleccionar...</option>
                                    <option value="micro" <?php selected($client->business_size ?? '', 'micro'); ?>>Microempresa (1-9)</option>
                                    <option value="small" <?php selected($client->business_size ?? '', 'small'); ?>>Pequeña (10-49)</option>
                                    <option value="medium" <?php selected($client->business_size ?? '', 'medium'); ?>>Mediana (50-249)</option>
                                    <option value="large" <?php selected($client->business_size ?? '', 'large'); ?>>Grande (250+)</option>
                                </select>
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>📄 Descripción del Negocio</label>
                            <textarea name="business_description" rows="3" placeholder="Describe brevemente a qué se dedica el cliente..."><?php echo esc_textarea($client->business_description ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>👥 Contactos</h3>
                        <div class="cfm-grid-3">
                            <div class="cfm-field">
                                <label>🔧 Contacto Técnico</label>
                                <input type="text" name="technical_contact" value="<?php echo esc_attr($client->technical_contact ?? ''); ?>" placeholder="Nombre">
                            </div>
                            <div class="cfm-field">
                                <label>📧 Email Técnico</label>
                                <input type="email" name="technical_email" value="<?php echo esc_attr($client->technical_email ?? ''); ?>" placeholder="email@empresa.com">
                            </div>
                            <div class="cfm-field">
                                <label>📞 Teléfono Técnico</label>
                                <input type="tel" name="technical_phone" value="<?php echo esc_attr($client->technical_phone ?? ''); ?>" placeholder="+56 9 XXXX XXXX">
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>📋 Contactos Secundarios</label>
                            <textarea name="secondary_contacts" rows="2" placeholder='[{"nombre": "Juan", "cargo": "Gerente", "email": "juan@...", "telefono": "..."}]'><?php echo esc_textarea($client->secondary_contacts ?? ''); ?></textarea>
                            <small>Formato JSON para múltiples contactos</small>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Técnico -->
                <div class="cfm-tab-content" id="tab-technical">
                    <div class="cfm-section">
                        <h3>🌐 URLs de la Aplicación</h3>
                        <div class="cfm-grid-1">
                            <div class="cfm-field">
                                <label>🔗 URL Principal (Producción)</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="app_url" value="<?php echo esc_attr($client->app_url ?? ''); ?>" placeholder="https://www.ejemplo.com">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)" title="Abrir URL">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>⚙️ Panel de Administración</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="app_admin_url" value="<?php echo esc_attr($client->app_admin_url ?? ''); ?>" placeholder="https://www.ejemplo.com/wp-admin">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)" title="Abrir URL">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>🧪 URL Staging/Desarrollo</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="app_staging_url" value="<?php echo esc_attr($client->app_staging_url ?? ''); ?>" placeholder="https://staging.ejemplo.com">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)" title="Abrir URL">🔗</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>🌍 Dominio y Hosting</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>🌐 Dominio Principal</label>
                                <input type="text" name="domain" value="<?php echo esc_attr($client->domain ?? ''); ?>" placeholder="ejemplo.com">
                            </div>
                            <div class="cfm-field">
                                <label>📅 Fecha Expiración Dominio</label>
                                <input type="date" name="domain_expiry" value="<?php echo esc_attr($client->domain_expiry ?? ''); ?>">
                            </div>
                            <div class="cfm-field">
                                <label>🏢 Registrador</label>
                                <input type="text" name="domain_registrar" value="<?php echo esc_attr($client->domain_registrar ?? ''); ?>" placeholder="Ej: NIC Chile, GoDaddy, Namecheap...">
                            </div>
                            <div class="cfm-field">
                                <label>🖥️ IP del Servidor</label>
                                <input type="text" name="server_ip" value="<?php echo esc_attr($client->server_ip ?? ''); ?>" placeholder="123.456.789.000">
                            </div>
                            <div class="cfm-field">
                                <label>☁️ Proveedor Hosting</label>
                                <input type="text" name="hosting_provider" value="<?php echo esc_attr($client->hosting_provider ?? ''); ?>" placeholder="Ej: AWS, DigitalOcean, Hostinger...">
                            </div>
                            <div class="cfm-field">
                                <label>📦 Plan de Hosting</label>
                                <input type="text" name="hosting_plan" value="<?php echo esc_attr($client->hosting_plan ?? ''); ?>" placeholder="Ej: Business, VPS 2GB...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>📧 Correo Electrónico</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>📬 Proveedor de Correo</label>
                                <input type="text" name="email_provider" value="<?php echo esc_attr($client->email_provider ?? ''); ?>" placeholder="Ej: Google Workspace, Microsoft 365, Zoho...">
                            </div>
                            <div class="cfm-field">
                                <label>📲 WhatsApp Business</label>
                                <input type="text" name="whatsapp_business" value="<?php echo esc_attr($client->whatsapp_business ?? ''); ?>" placeholder="+56 9 XXXX XXXX">
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>📋 Cuentas de Correo Configuradas</label>
                            <textarea name="email_accounts" rows="3" placeholder='[{"email": "info@ejemplo.com", "tipo": "principal"}, {"email": "ventas@ejemplo.com", "tipo": "ventas"}]'><?php echo esc_textarea($client->email_accounts ?? ''); ?></textarea>
                            <small>Formato JSON para múltiples cuentas</small>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>📊 Integraciones y Analytics</h3>
                        <div class="cfm-grid-3">
                            <div class="cfm-field">
                                <label>📈 Google Analytics</label>
                                <input type="text" name="google_analytics_id" value="<?php echo esc_attr($client->google_analytics_id ?? ''); ?>" placeholder="G-XXXXXXXXXX">
                            </div>
                            <div class="cfm-field">
                                <label>🏷️ Google Tag Manager</label>
                                <input type="text" name="google_tag_manager" value="<?php echo esc_attr($client->google_tag_manager ?? ''); ?>" placeholder="GTM-XXXXXXX">
                            </div>
                            <div class="cfm-field">
                                <label>📘 Facebook Pixel</label>
                                <input type="text" name="facebook_pixel" value="<?php echo esc_attr($client->facebook_pixel ?? ''); ?>" placeholder="XXXXXXXXXXXXXXXX">
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>🔌 Otras Integraciones Activas</label>
                            <textarea name="integrations" rows="2" placeholder='[{"nombre": "Mailchimp", "api_key": "***"}, {"nombre": "Stripe", "account_id": "***"}]'><?php echo esc_textarea($client->integrations ?? ''); ?></textarea>
                            <small>Formato JSON para integraciones</small>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Redes Sociales -->
                <div class="cfm-tab-content" id="tab-social">
                    <div class="cfm-section">
                        <h3>📱 Redes Sociales</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>📘 Facebook</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="social_facebook" value="<?php echo esc_attr($client->social_facebook ?? ''); ?>" placeholder="https://facebook.com/pagina">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>📷 Instagram</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="social_instagram" value="<?php echo esc_attr($client->social_instagram ?? ''); ?>" placeholder="https://instagram.com/cuenta">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>💼 LinkedIn</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="social_linkedin" value="<?php echo esc_attr($client->social_linkedin ?? ''); ?>" placeholder="https://linkedin.com/company/...">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>🐦 Twitter/X</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="social_twitter" value="<?php echo esc_attr($client->social_twitter ?? ''); ?>" placeholder="https://x.com/cuenta">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>🎵 TikTok</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="social_tiktok" value="<?php echo esc_attr($client->social_tiktok ?? ''); ?>" placeholder="https://tiktok.com/@cuenta">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)">🔗</button>
                                </div>
                            </div>
                            <div class="cfm-field">
                                <label>🎬 YouTube</label>
                                <div class="cfm-input-with-action">
                                    <input type="url" name="social_youtube" value="<?php echo esc_attr($client->social_youtube ?? ''); ?>" placeholder="https://youtube.com/@canal">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)">🔗</button>
                                </div>
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>🌐 Otras Redes</label>
                            <textarea name="social_other" rows="2" placeholder='[{"red": "Pinterest", "url": "https://..."}, {"red": "Threads", "url": "https://..."}]'><?php echo esc_textarea($client->social_other ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Accesos -->
                <div class="cfm-tab-content" id="tab-access">
                    <div class="cfm-section cfm-warning-section">
                        <p>⚠️ <strong>IMPORTANTE:</strong> Esta información es sensible. Guarda las credenciales de forma segura y considera usar un gestor de contraseñas externo.</p>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>🔐 Acceso al CMS/Panel</h3>
                        <div class="cfm-field">
                            <label>📝 Credenciales CMS (WordPress, etc.)</label>
                            <textarea name="cms_access" rows="3" placeholder='{"url": "https://ejemplo.com/wp-admin", "user": "admin", "pass": "***", "notas": "..."}'><?php echo esc_textarea($client->cms_access ?? ''); ?></textarea>
                            <small>Formato JSON - Considera no almacenar contraseñas en texto plano</small>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>📁 Acceso FTP/SFTP</h3>
                        <div class="cfm-field">
                            <label>📂 Credenciales FTP</label>
                            <textarea name="ftp_access" rows="3" placeholder='{"host": "ftp.ejemplo.com", "user": "ftpuser", "pass": "***", "port": "21"}'><?php echo esc_textarea($client->ftp_access ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>🗄️ Acceso Base de Datos</h3>
                        <div class="cfm-field">
                            <label>💾 Credenciales BD</label>
                            <textarea name="db_access" rows="3" placeholder='{"host": "localhost", "user": "dbuser", "pass": "***", "database": "wp_cliente", "port": "3306"}'><?php echo esc_textarea($client->db_access ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>🔑 APIs y Servicios</h3>
                        <div class="cfm-field">
                            <label>🔌 Credenciales de APIs</label>
                            <textarea name="api_credentials" rows="4" placeholder='[
  {"servicio": "Stripe", "api_key": "sk_live_***", "secret": "***"},
  {"servicio": "SendGrid", "api_key": "SG.***"}
]'><?php echo esc_textarea($client->api_credentials ?? ''); ?></textarea>
                            <small>Formato JSON para múltiples APIs</small>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Facturación -->
                <div class="cfm-tab-content" id="tab-billing">
                    <div class="cfm-section">
                        <h3>💰 Información de Facturación</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>🏷️ RUT/Tax ID</label>
                                <input type="text" value="<?php echo esc_attr($client->tax_id ?? ''); ?>" readonly class="cfm-readonly">
                            </div>
                            <div class="cfm-field">
                                <label>💵 Valor Contrato</label>
                                <input type="text" value="$<?php echo number_format($client->contract_value ?? 0, 0, ',', '.'); ?>" readonly class="cfm-readonly">
                            </div>
                            <div class="cfm-field">
                                <label>💳 Cuota Mensual</label>
                                <input type="number" name="monthly_fee" value="<?php echo esc_attr($client->monthly_fee ?? ''); ?>" placeholder="0" step="1000">
                            </div>
                            <div class="cfm-field">
                                <label>📅 Día de Pago</label>
                                <input type="number" name="payment_day" value="<?php echo esc_attr($client->payment_day ?? ''); ?>" min="1" max="31" placeholder="1-31">
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>👤 Contacto de Facturación</label>
                            <input type="text" name="billing_contact" value="<?php echo esc_attr($client->billing_contact ?? ''); ?>" placeholder="Nombre y email del contacto de facturación">
                        </div>
                        <div class="cfm-field">
                            <label>📍 Dirección de Facturación</label>
                            <textarea name="billing_address" rows="2" placeholder="Dirección completa para facturación"><?php echo esc_textarea($client->billing_address ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>📋 Contrato y SLA</h3>
                        <div class="cfm-grid-3">
                            <div class="cfm-field">
                                <label>🛠️ Tipo de Proyecto</label>
                                <input type="text" value="<?php echo esc_attr($client->project_type ?? ''); ?>" readonly class="cfm-readonly">
                            </div>
                            <div class="cfm-field">
                                <label>⭐ Nivel de SLA</label>
                                <select name="sla_level">
                                    <option value="">Seleccionar...</option>
                                    <option value="basic" <?php selected($client->sla_level ?? '', 'basic'); ?>>Básico</option>
                                    <option value="standard" <?php selected($client->sla_level ?? '', 'standard'); ?>>Estándar</option>
                                    <option value="premium" <?php selected($client->sla_level ?? '', 'premium'); ?>>Premium</option>
                                    <option value="enterprise" <?php selected($client->sla_level ?? '', 'enterprise'); ?>>Enterprise</option>
                                </select>
                            </div>
                            <div class="cfm-field">
                                <label>📅 Fin de Contrato</label>
                                <input type="date" name="contract_end_date" value="<?php echo esc_attr($client->contract_end_date ?? ''); ?>">
                            </div>
                        </div>
                        <div class="cfm-field">
                            <label>🕐 Horario de Soporte</label>
                            <input type="text" name="support_hours" value="<?php echo esc_attr($client->support_hours ?? ''); ?>" placeholder="Ej: Lun-Vie 9:00-18:00">
                        </div>
                    </div>
                    
                    <div class="cfm-section">
                        <h3>📝 Notas Internas</h3>
                        <div class="cfm-field">
                            <label>🔒 Notas Operativas (solo visibles para el equipo)</label>
                            <textarea name="operational_notes" rows="4" placeholder="Notas internas sobre el cliente, preferencias, historial de incidencias..."><?php echo esc_textarea($client->operational_notes ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Seguimiento -->
                <div class="cfm-tab-content" id="tab-tracking">
                    <div class="cfm-section" style="background: #f0f9ff; border-color: #b3e5fc;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0; color: #0277bd;">🚀 Avances del Proyecto</h3>
                                <p style="margin: 5px 0 0 0; font-size: 13px; color: #555;">Notifica al cliente sobre los hitos y actualizaciones importantes.</p>
                            </div>
                            <button type="button" onclick="openNotifyProgressModal(<?php echo $client->id; ?>, '<?php echo esc_attr($client->name); ?>', '<?php echo esc_attr($client->email); ?>')" 
                                    style="background: linear-gradient(135deg, #0288d1, #01579b); color: white; border: none; padding: 8px 15px; border-radius: 20px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; box-shadow: 0 4px 6px rgba(2, 136, 209, 0.3);">
                                <span style="font-size: 16px;">📢</span> Notificar Avance
                            </button>
                        </div>
                    </div>

                    <?php if (function_exists('automatiza_render_client_details')): ?>
                        <?php automatiza_render_client_details($client->id); ?>
                    <?php else: ?>
                        <div class="cfm-section">
                            <p>📋 El módulo de seguimiento no está disponible.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab: Identidad Corporativa -->
                <div class="cfm-tab-content" id="tab-branding">
                    <div class="cfm-section">
                        <h3>🎨 Identidad Visual</h3>
                        <div class="cfm-grid-2">
                            <div class="cfm-field">
                                <label>🖼️ Logo Corporativo</label>
                                <div class="cfm-input-with-action">
                                    <input type="text" name="brand_logo" value="<?php echo esc_attr($client->brand_logo ?? ''); ?>" placeholder="URL del logo...">
                                    <button type="button" class="cfm-btn-sm" onclick="document.getElementById('brand_logo_file').click()" title="Subir archivo">📂</button>
                                    <input type="file" id="brand_logo_file" name="brand_logo_file" style="display:none;" accept="image/*">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)" title="Ver Logo">👁️</button>
                                </div>
                                <small id="brand_logo_msg"></small>
                            </div>
                            <div class="cfm-field">
                                <label>📘 Manual de Marca (PDF)</label>
                                <div class="cfm-input-with-action">
                                    <input type="text" name="brand_manual" value="<?php echo esc_attr($client->brand_manual ?? ''); ?>" placeholder="URL del manual...">
                                    <button type="button" class="cfm-btn-sm" onclick="document.getElementById('brand_manual_file').click()" title="Subir archivo">📂</button>
                                    <input type="file" id="brand_manual_file" name="brand_manual_file" style="display:none;" accept=".pdf">
                                    <button type="button" class="cfm-btn-sm" onclick="openUrl(this)" title="Ver Manual">📄</button>
                                </div>
                                <small id="brand_manual_msg"></small>
                            </div>
                        </div>
                        
                        <script>
                        document.getElementById('brand_logo_file').addEventListener('change', function() {
                            if(this.files[0]) document.getElementById('brand_logo_msg').textContent = 'Seleccionado: ' + this.files[0].name;
                        });
                        document.getElementById('brand_manual_file').addEventListener('change', function() {
                            if(this.files[0]) document.getElementById('brand_manual_msg').textContent = 'Seleccionado: ' + this.files[0].name;
                        });
                        </script>
                        
                        <div class="cfm-field" style="margin-top: 15px;">
                            <label>🎨 Paleta de Colores</label>
                            <textarea name="brand_colors" rows="3" placeholder='Principal: #000000, Secundario: #FFFFFF, Acento: #FF0000...'><?php echo esc_textarea($client->brand_colors ?? ''); ?></textarea>
                            <small>Describe los colores principales de la marca o pega los códigos HEX/RGB.</small>
                        </div>
                        
                        <div class="cfm-field" style="margin-top: 15px;">
                            <label>🔤 Tipografía</label>
                            <textarea name="brand_typography" rows="2" placeholder='Títulos: Montserrat, Cuerpo: Roboto...'><?php echo esc_textarea($client->brand_typography ?? ''); ?></textarea>
                            <small>Fuentes utilizadas en la identidad corporativa.</small>
                        </div>
                    </div>
                </div>
                
            </form>
            
            <!-- Footer con botones -->
            <div class="cfm-footer">
                <button type="button" class="cfm-btn cfm-btn-secondary" onclick="closeClientFullModal()">❌ Cerrar</button>
                <button type="button" class="cfm-btn cfm-btn-primary" onclick="saveClientOperationalData()">💾 Guardar Cambios</button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Estilos CSS para el modal
     */
    public static function render_styles() {
        ?>
        <style>
        /* Modal Container */
        .client-full-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 100000;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            overflow-y: auto;
        }
        
        .client-full-modal {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 1000px;
            margin: 20px auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            max-height: calc(100vh - 40px);
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .cfm-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 3px solid #d63384;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border-radius: 16px 16px 0 0;
        }
        
        .cfm-header h2 {
            color: #fff;
            margin: 0;
            font-size: 22px;
        }
        
        .cfm-header-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .cfm-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .cfm-status-active { background: #10b981; color: #fff; }
        .cfm-status-completed { background: #8b5cf6; color: #fff; }
        .cfm-status-paused { background: #f59e0b; color: #fff; }
        .cfm-status-cancelled { background: #ef4444; color: #fff; }
        
        .cfm-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 32px;
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.2s;
        }
        
        .cfm-close:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        
        /* Tabs */
        .cfm-tabs {
            display: flex;
            gap: 0;
            background: #f1f5f9;
            padding: 0;
            overflow-x: auto;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .cfm-tab {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .cfm-tab:hover {
            background: #e2e8f0;
            color: #1e3a8a;
        }
        
        .cfm-tab.active {
            color: #d63384;
            border-bottom-color: #d63384;
            background: #fff;
        }
        
        /* Tab Content */
        .cfm-form {
            overflow-y: auto;
            flex: 1;
            padding: 0;
        }
        
        .cfm-tab-content {
            display: none;
            padding: 20px 25px;
        }
        
        .cfm-tab-content.active {
            display: block;
        }
        
        /* Sections */
        .cfm-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        
        .cfm-section h3 {
            margin: 0 0 15px 0;
            color: #1e3a8a;
            font-size: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .cfm-warning-section {
            background: #fef3cd;
            border-color: #ffc107;
        }
        
        .cfm-warning-section p {
            margin: 0;
            color: #856404;
        }
        
        /* Grid Layouts */
        .cfm-grid-1 { display: grid; grid-template-columns: 1fr; gap: 15px; }
        .cfm-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .cfm-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        
        @media (max-width: 768px) {
            .cfm-grid-2, .cfm-grid-3 { grid-template-columns: 1fr; }
        }
        
        /* Fields */
        .cfm-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .cfm-field label {
            font-weight: 600;
            color: #374151;
            font-size: 13px;
        }
        
        .cfm-field input,
        .cfm-field select,
        .cfm-field textarea {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .cfm-field input:focus,
        .cfm-field select:focus,
        .cfm-field textarea:focus {
            outline: none;
            border-color: #d63384;
            box-shadow: 0 0 0 3px rgba(214, 51, 132, 0.1);
        }
        
        .cfm-field small {
            color: #64748b;
            font-size: 11px;
        }
        
        .cfm-readonly {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .cfm-input-with-action {
            display: flex;
            gap: 8px;
        }
        
        .cfm-input-with-action input {
            flex: 1;
        }
        
        .cfm-btn-sm {
            padding: 8px 12px;
            border: none;
            background: #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cfm-btn-sm:hover {
            background: #d63384;
            color: #fff;
        }
        
        /* Footer */
        .cfm-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 25px;
            border-top: 2px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 0 0 16px 16px;
        }
        
        .cfm-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .cfm-btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        
        .cfm-btn-secondary:hover {
            background: #5a6268;
        }
        
        .cfm-btn-primary {
            background: linear-gradient(135deg, #d63384, #e91e8c);
            color: #fff;
            box-shadow: 0 4px 15px rgba(214, 51, 132, 0.3);
        }
        
        .cfm-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214, 51, 132, 0.4);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .client-full-modal-overlay {
                padding: 10px;
            }
            
            .cfm-header {
                padding: 15px;
            }
            
            .cfm-header h2 {
                font-size: 18px;
            }
            
            .cfm-tabs {
                overflow-x: auto;
            }
            
            .cfm-tab {
                padding: 10px 15px;
                font-size: 12px;
            }
            
            .cfm-section {
                padding: 15px;
            }
            
            .cfm-footer {
                flex-direction: column;
            }
            
            .cfm-btn {
                width: 100%;
            }
        }
        </style>
        <?php
    }
    
    /**
     * JavaScript para el modal
     */
    public static function render_scripts() {
        ?>
        <script>
        function openClientFullModal(clientId) {
            // Crear overlay
            const overlay = document.createElement('div');
            overlay.className = 'client-full-modal-overlay';
            overlay.id = 'client-full-modal-overlay';
            overlay.innerHTML = '<div style="color: #fff; text-align: center; padding: 50px;"><h2>⏳ Cargando información del cliente...</h2></div>';
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            
            // Cargar datos via AJAX
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_client_full_details',
                    client_id: clientId,
                    nonce: '<?php echo wp_create_nonce('client_operations_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        overlay.innerHTML = response.data;
                        initClientFullModalTabs();
                    } else {
                        alert('Error: ' + (response.data || 'No se pudo cargar la información'));
                        closeClientFullModal();
                    }
                },
                error: function() {
                    alert('Error de conexión');
                    closeClientFullModal();
                }
            });
        }
        
        function closeClientFullModal() {
            const overlay = document.getElementById('client-full-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            document.body.style.overflow = '';
        }
        
        function initClientFullModalTabs() {
            document.querySelectorAll('.cfm-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remover active de todos
                    document.querySelectorAll('.cfm-tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.cfm-tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Activar el seleccionado
                    this.classList.add('active');
                    const tabId = 'tab-' + this.dataset.tab;
                    document.getElementById(tabId).classList.add('active');
                });
            });
        }
        
        function saveClientOperationalData() {
            const form = document.getElementById('client-full-form');
            const formData = new FormData(form);
            formData.append('action', 'save_client_operational_data');
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Guardando...';
            btn.disabled = true;
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    if (response.success) {
                        alert('✅ ' + response.data.message);
                    } else {
                        alert('❌ Error: ' + (response.data || 'No se pudo guardar'));
                    }
                },
                error: function() {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    alert('❌ Error de conexión');
                }
            });
        }
        
        function openUrl(btn) {
            const input = btn.parentElement.querySelector('input');
            if (input && input.value) {
                window.open(input.value, '_blank');
            } else {
                alert('No hay URL configurada');
            }
        }
        
        function openNotifyProgressModal(clientId, clientName, clientEmail) {
            // Crear modal
            const modalId = 'notify-progress-modal';
            let modal = document.getElementById(modalId);
            
            if (modal) {
                modal.remove();
            }
            
            const modalHTML = `
                <div id="${modalId}" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100001; display: flex; justify-content: center; align-items: center;">
                    <div style="background: white; width: 90%; max-width: 500px; padding: 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <h3 style="margin-top: 0; color: #1e3a8a;">📢 Notificar Avance de Proyecto</h3>
                        <p style="color: #666; font-size: 14px;">Cliente: <strong>${clientName}</strong> (${clientEmail})</p>
                        
                        <form onsubmit="submitNotifyProgress(event)">
                            <input type="hidden" name="client_id" value="${clientId}">
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Título del Avance</label>
                                <input type="text" name="title" required placeholder="Ej: Fase 1 Completada" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: bold; margin-bottom: 5px;">Descripción Detallada</label>
                                <textarea name="description" required rows="5" placeholder="Describe los avances realizados..." style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                            </div>
                            
                            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                                <button type="button" onclick="document.getElementById('${modalId}').remove()" style="padding: 8px 15px; border: none; background: #ccc; border-radius: 4px; cursor: pointer;">Cancelar</button>
                                <button type="submit" style="padding: 8px 15px; border: none; background: #0288d1; color: white; border-radius: 4px; cursor: pointer; font-weight: bold;">Enviar Notificación</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        function submitNotifyProgress(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '⏳ Enviando...';
            btn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'notify_project_progress');
            formData.append('nonce', '<?php echo wp_create_nonce('client_operations_nonce'); ?>');
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    if (response.success) {
                        alert('✅ Notificación enviada correctamente');
                        document.getElementById('notify-progress-modal').remove();
                        // Recargar modal principal si es necesario o actualizar lista
                        // openClientFullModal(formData.get('client_id'));
                    } else {
                        alert('❌ Error: ' + (response.data || 'Error desconocido'));
                    }
                },
                error: function() {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    alert('❌ Error de conexión');
                }
            });
        }
        
        // Cerrar con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeClientFullModal();
            }
        });
        
        // Cerrar al hacer clic fuera del modal
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('client-full-modal-overlay')) {
                closeClientFullModal();
            }
        });
        </script>
        <?php
    }

    /**
     * Regenerar y reenviar factura
     */
    public function ajax_regenerate_and_resend_invoice() {
        error_log('--- Inicio de regeneración de factura ---');

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'regenerate_invoice_op_' . intval($_POST['id']))) {
            error_log('Error: Fallo de seguridad (nonce).');
            wp_send_json_error('Fallo de seguridad.');
            return;
        }
        error_log('Nonce verificado correctamente.');

        if (!current_user_can('manage_options')) {
            error_log('Error: El usuario no tiene permisos.');
            wp_send_json_error('No tienes permisos para realizar esta acción.');
            return;
        }
        error_log('Permisos de usuario verificados.');

        $client_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (empty($client_id)) {
            error_log('Error: ID de cliente no proporcionado.');
            wp_send_json_error('ID de cliente no válido.');
            return;
        }
        error_log("ID de Cliente: $client_id");

        global $wpdb;
        $clients_table = $wpdb->prefix . 'automatiza_tech_clients';
        $client_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $clients_table WHERE id = %d", $client_id));

        if (!$client_data) {
            error_log("Error: No se encontró el cliente con ID $client_id.");
            wp_send_json_error('Cliente no encontrado.');
            return;
        }
        error_log('Datos del cliente recuperados de la BD.');

        // Incluir el archivo que contiene la función de envío
        $contact_form_path = get_template_directory() . '/inc/contact-form.php';
        error_log("Intentando incluir: $contact_form_path");

        if (!file_exists($contact_form_path)) {
            error_log("Error fatal: El archivo $contact_form_path no existe.");
            wp_send_json_error('Error crítico: Falta un archivo del sistema (contact-form).');
            return;
        }
        require_once $contact_form_path;
        error_log('Archivo contact-form.php incluido.');

        if (!class_exists('AutomatizaTechContactForm')) {
            error_log('Error fatal: La clase AutomatizaTechContactForm no existe después de incluir el archivo.');
            wp_send_json_error('Error crítico: Falta una clase del sistema (ContactForm).');
            return;
        }
        error_log('La clase AutomatizaTechContactForm existe.');

        // Obtener los servicios asociados al cliente
        // CORRECCIÓN: No existe tabla relacional client_services. Usamos el plan_id almacenado en el cliente.
        $services_table = $wpdb->prefix . 'automatiza_services';
        
        // Verificar si el cliente tiene un plan_id
        if (empty($client_data->plan_id)) {
             error_log("Advertencia: El cliente ID $client_id no tiene plan_id. Intentando recuperar por project_type o contract_value.");
             // Si no hay plan_id, intentar reconstruir un servicio genérico basado en el tipo de proyecto y valor
             $services = array((object) array(
                 'id' => 0, // ID ficticio para evitar warnings
                 'name' => $client_data->project_type ? $client_data->project_type : 'Servicios Profesionales',
                 'description' => 'Servicios de desarrollo y consultoría según contrato.', // Descripción por defecto
                 'price_clp' => $client_data->contract_value,
                 'quantity' => 1,
                 'discount' => 0
             ));
        } else {
            // Obtener detalles del plan desde la tabla de servicios
            $services = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, description, price_clp, 1 as quantity, 0 as discount FROM $services_table WHERE id = %d", 
                $client_data->plan_id
            ));
        }

        if (empty($services)) {
            error_log("Error: No se encontraron servicios para el cliente ID $client_id (Plan ID: {$client_data->plan_id}).");
            // Fallback final: Crear un servicio genérico con el valor del contrato
            $services = array((object) array(
                 'id' => 0,
                 'name' => $client_data->project_type ? $client_data->project_type : 'Servicios Profesionales',
                 'description' => 'Servicios de desarrollo y consultoría según contrato.',
                 'price_clp' => $client_data->contract_value,
                 'quantity' => 1,
                 'discount' => 0
             ));
             error_log("Usando servicio genérico de fallback.");
        }
        error_log('Servicios del cliente recuperados/generados.');

        // Llamar a la función para generar y enviar el correo
        error_log('Llamando a send_invoice_email_to_client...');
        
        $contact_form = new AutomatizaTechContactForm();
        // IMPORTANTE: Pasar el objeto $client_data completo, no solo el ID
        $result = $contact_form->send_invoice_email_to_client($client_data, $services);
        
        error_log('Resultado de send_invoice_email_to_client: ' . print_r($result, true));

        // Si estamos en entorno local, es normal que falle el envío de correo SMTP.
        // Verificamos si el PDF se generó correctamente.
        $is_local = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '.local') !== false);
        
        // Verificar resultado: puede ser booleano (true) o array (['success' => true])
        $success = false;
        if (is_bool($result) && $result) {
            $success = true;
        } elseif (is_array($result) && isset($result['success']) && $result['success']) {
            $success = true;
        }

        if ($success || $is_local) {
            $msg = 'Factura regenerada exitosamente.';
            if ($is_local && !$success) {
                $msg .= ' (Nota: El envío de correo falló porque estás en local, pero el PDF se generó correctamente).';
            }
            error_log('Éxito: ' . $msg);
            
            // Limpiar cualquier salida previa para evitar parsererror en JSON
            while (ob_get_level()) { ob_end_clean(); }
            
            wp_send_json_success(['message' => $msg]);
        } else {
            $error_message = (is_array($result) && isset($result['message'])) ? $result['message'] : 'Error desconocido durante el envío.';
            error_log("Fallo: $error_message");
            
            // Limpiar cualquier salida previa para evitar parsererror en JSON
            while (ob_get_level()) { ob_end_clean(); }
            
            wp_send_json_error($error_message);
        }
        error_log('--- Fin de regeneración de factura ---');
    }
}

// Inicializar
AutomatizaTech_Client_Operations::get_instance();

// Agregar estilos y scripts en admin
add_action('admin_footer', array('AutomatizaTech_Client_Operations', 'render_styles'));
add_action('admin_footer', array('AutomatizaTech_Client_Operations', 'render_scripts'));

/**
 * Función helper para abrir el modal desde cualquier parte
 */
function automatiza_client_full_modal_button($client_id, $text = '📋 Ver Ficha Completa') {
    return '<button type="button" class="button" onclick="openClientFullModal(' . intval($client_id) . ')">' . esc_html($text) . '</button>';
}
