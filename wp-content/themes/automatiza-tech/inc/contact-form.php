<?php
/**
 * Automatiza Tech - Contact Form Handler
 * Maneja el formulario de contacto y administración de datos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTechContactForm {
    
    private $table_name;
    private $clients_table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_tech_contacts';
        $this->clients_table_name = $wpdb->prefix . 'automatiza_tech_clients';
        
        // Hooks de WordPress
        add_action('wp_ajax_submit_contact_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_submit_contact_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_check_phone_exists', array($this, 'check_phone_exists'));
        add_action('wp_ajax_nopriv_check_phone_exists', array($this, 'check_phone_exists'));
        add_action('wp_ajax_get_contact_details', array($this, 'get_contact_details'));
        add_action('wp_ajax_nopriv_get_contact_details', array($this, 'get_contact_details'));
        add_action('wp_ajax_get_client_details', array($this, 'get_client_details'));
        add_action('wp_ajax_nopriv_get_client_details', array($this, 'get_client_details'));
        add_action('wp_ajax_search_contacts', array($this, 'search_contacts'));
        add_action('wp_ajax_nopriv_search_contacts', array($this, 'search_contacts'));
        add_action('wp_ajax_search_clients', array($this, 'search_clients'));
        add_action('wp_ajax_nopriv_search_clients', array($this, 'search_clients'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        add_action('admin_init', array($this, 'handle_export_action'));
        
        // Crear tabla al activar
        register_activation_hook(__FILE__, array($this, 'create_table'));
        
        // Verificar y actualizar estructura de tabla
        add_action('init', array($this, 'check_table_structure'));
    }
    
    /**
     * Verificar y actualizar estructura de tabla
     */
    public function check_table_structure() {
        global $wpdb;
        
        // Verificar si existe la columna updated_at
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$this->clients_table_name} LIKE %s",
            'updated_at'
        ));
        
        if (empty($column_exists)) {
            // Agregar la columna updated_at si no existe
            $wpdb->query("ALTER TABLE {$this->clients_table_name} ADD COLUMN updated_at datetime AFTER notes");
        }
    }
    
    /**
     * Crear tablas en la base de datos
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de contactos
        $sql_contacts = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            company varchar(100),
            phone varchar(20),
            message text NOT NULL,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'new',
            notes text,
            PRIMARY KEY (id),
            KEY email (email),
            KEY submitted_at (submitted_at)
        ) $charset_collate;";
        
        // Tabla de clientes
        $sql_clients = "CREATE TABLE {$this->clients_table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            contact_id mediumint(9),
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            company varchar(100),
            phone varchar(20),
            original_message text,
            contacted_at datetime DEFAULT CURRENT_TIMESTAMP,
            contracted_at datetime DEFAULT CURRENT_TIMESTAMP,
            contract_value decimal(10,2),
            project_type varchar(100),
            contract_status varchar(20) DEFAULT 'active',
            notes text,
            updated_at datetime,
            PRIMARY KEY (id),
            KEY email (email),
            KEY contracted_at (contracted_at),
            KEY contact_id (contact_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_contacts);
        dbDelta($sql_clients);
    }
    
    /**
     * Manejar envío del formulario
     */
    public function handle_form_submission() {
        global $wpdb;
        
        // Limpiar cualquier salida anterior
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Log para depuración
        error_log('=== CONTACT FORM SUBMISSION ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar que sea una petición POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error('Método no permitido');
            wp_die();
        }
        
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'automatiza_ajax_nonce')) {
            error_log('Nonce verification failed. Expected: automatiza_ajax_nonce, Received: ' . ($_POST['nonce'] ?? 'none'));
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        // Validar y sanitizar datos con múltiples capas de seguridad
        $name = $this->validate_and_sanitize_name($_POST['name'] ?? '');
        $email = $this->validate_and_sanitize_email($_POST['email'] ?? '');
        $company = $this->validate_and_sanitize_company($_POST['company'] ?? '');
        $phone = $this->validate_and_sanitize_phone($_POST['phone'] ?? '');
        $message = $this->validate_and_sanitize_message($_POST['message'] ?? '');
        
        // Validaciones obligatorias
        if (empty($name)) {
            wp_send_json_error('El nombre es obligatorio y debe tener entre 2 y 100 caracteres.');
            wp_die();
        }
        
        if (empty($email)) {
            wp_send_json_error('El email es obligatorio y debe ser válido.');
            wp_die();
        }
        
        if (empty($message)) {
            wp_send_json_error('El mensaje es obligatorio y debe tener entre 10 y 2000 caracteres.');
            wp_die();
        }
        
        // Verificar si el teléfono ya existe (si se proporcionó)
        if (!empty($phone)) {
            $phone_exists_contacts = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s",
                $phone
            ));
            
            $phone_exists_clients = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->clients_table_name} WHERE phone = %s",
                $phone
            ));
            
            if ($phone_exists_contacts > 0 || $phone_exists_clients > 0) {
                wp_send_json_error('El número de teléfono ' . $phone . ' ya se encuentra registrado en nuestro sistema. Si eres el propietario de este número, contáctanos por WhatsApp para actualizar tu información.');
                wp_die();
            }
        }
        
        // Verificar límites de rate limiting (máximo 5 envíos por IP por hora)
        if (!$this->check_rate_limit()) {
            wp_send_json_error('Has excedido el límite de envíos. Intenta de nuevo en una hora.');
            wp_die();
        }
        
        // Verificar contenido spam
        if ($this->is_spam_content($name, $email, $message)) {
            wp_send_json_error('Tu mensaje ha sido marcado como spam. Contacta al administrador.');
            wp_die();
        }
        
        // Guardar en base de datos
        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'name' => $name,
                'email' => $email,
                'company' => $company,
                'phone' => $phone,
                'message' => $message,
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Database insert failed: ' . $wpdb->last_error);
            wp_send_json_error('Error al guardar el mensaje. Por favor intenta de nuevo.');
            wp_die();
        }
        
        error_log('Contact form saved successfully with ID: ' . $wpdb->insert_id);
        
        // Enviar email de notificación (opcional)
        $this->send_notification_email($name, $email, $company, $phone, $message);
        
        wp_send_json_success('¡Gracias! Tu mensaje ha sido enviado correctamente. Te contactaremos pronto.');
        
        // Terminar la ejecución para evitar salida adicional
        wp_die();
    }
    
    /**
     * Validar y sanitizar nombre
     */
    private function validate_and_sanitize_name($name) {
        // Remover espacios al inicio y final
        $name = trim($name);
        
        // Sanitizar
        $name = sanitize_text_field($name);
        
        // Remover caracteres especiales peligrosos
        $name = preg_replace('/[<>"\']/', '', $name);
        
        // Validar longitud
        if (strlen($name) < 2 || strlen($name) > 100) {
            return '';
        }
        
        // Validar que solo contenga letras, espacios y algunos caracteres especiales seguros
        if (!preg_match('/^[a-zA-ZáéíóúñüÁÉÍÓÚÑÜ\s\-\.]+$/', $name)) {
            return '';
        }
        
        return $name;
    }
    
    /**
     * Validar y sanitizar email
     */
    private function validate_and_sanitize_email($email) {
        // Remover espacios
        $email = trim($email);
        
        // Sanitizar email
        $email = sanitize_email($email);
        
        // Validar formato
        if (!is_email($email)) {
            return '';
        }
        
        // Validar longitud
        if (strlen($email) > 100) {
            return '';
        }
        
        // Lista negra de dominios spam (opcional)
        $spam_domains = array('10minutemail.com', 'guerrillamail.com', 'tempmail.org');
        $domain = substr(strrchr($email, "@"), 1);
        if (in_array($domain, $spam_domains)) {
            return '';
        }
        
        return $email;
    }
    
    /**
     * Validar y sanitizar empresa
     */
    private function validate_and_sanitize_company($company) {
        // Remover espacios
        $company = trim($company);
        
        // Sanitizar
        $company = sanitize_text_field($company);
        
        // Remover caracteres peligrosos
        $company = preg_replace('/[<>"\']/', '', $company);
        
        // Validar longitud máxima
        if (strlen($company) > 100) {
            $company = substr($company, 0, 100);
        }
        
        return $company;
    }
    
    /**
     * Validar y sanitizar teléfono
     */
    private function validate_and_sanitize_phone($phone) {
        // Remover espacios
        $phone = trim($phone);
        
        // Sanitizar
        $phone = sanitize_text_field($phone);
        
        // Si está vacío, retornar vacío
        if (empty($phone)) {
            return '';
        }
        
        // Validar formato con código de país
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            return ''; // Formato inválido, retornar vacío
        }
        
        // Validar códigos de país permitidos y longitud específica
        $country_validations = array(
            '+56' => array('length' => 12, 'digits' => 9), // Chile: +56 + 9 dígitos = 12 total
            '+54' => array('length' => array(12, 13), 'digits' => array(9, 10)), // Argentina: variable
            '+57' => array('length' => 13, 'digits' => 10), // Colombia: +57 + 10 dígitos
            '+51' => array('length' => 12, 'digits' => 9), // Perú: +51 + 9 dígitos  
            '+52' => array('length' => 13, 'digits' => 10), // México: +52 + 10 dígitos
            '+34' => array('length' => 12, 'digits' => 9), // España: +34 + 9 dígitos
            '+1' => array('length' => 12, 'digits' => 10) // USA: +1 + 10 dígitos
        );
        
        $country_code_found = false;
        
        foreach ($country_validations as $code => $validation) {
            if (strpos($phone, $code) === 0) {
                $country_code_found = true;
                
                // Validación específica para Chile (+56)
                if ($code === '+56') {
                    // Chile debe tener exactamente 12 caracteres totales (+56 + 9 dígitos)
                    if (strlen($phone) !== 12) {
                        return ''; // Longitud incorrecta para Chile
                    }
                    // Verificar que después del +56 haya exactamente 9 dígitos
                    $number_part = substr($phone, 3); // Remover +56
                    if (!preg_match('/^[0-9]{9}$/', $number_part)) {
                        return ''; // Debe tener exactamente 9 dígitos después de +56
                    }
                } else {
                    // Para otros países, validación más flexible
                    $expected_lengths = is_array($validation['length']) ? $validation['length'] : array($validation['length']);
                    if (!in_array(strlen($phone), $expected_lengths)) {
                        return ''; // Longitud incorrecta para este país
                    }
                }
                break;
            }
        }
        
        if (!$country_code_found) {
            return ''; // Código de país no permitido
        }
        
        return $phone;
    }
    
    /**
     * Validar y sanitizar mensaje
     */
    private function validate_and_sanitize_message($message) {
        // Remover espacios al inicio y final
        $message = trim($message);
        
        // Sanitizar textarea
        $message = sanitize_textarea_field($message);
        
        // Validar longitud
        if (strlen($message) < 10 || strlen($message) > 2000) {
            return '';
        }
        
        // Remover scripts y etiquetas HTML peligrosas
        $message = wp_strip_all_tags($message, true);
        
        return $message;
    }
    
    /**
     * Verificar límite de envíos por IP
     */
    private function check_rate_limit() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $transient_key = 'contact_form_' . md5($ip);
        
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            // Primera vez, crear transient por 1 hora
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($attempts >= 5) {
            return false; // Límite excedido
        }
        
        // Incrementar contador
        set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
        return true;
    }
    
    /**
     * Detectar contenido spam
     */
    private function is_spam_content($name, $email, $message) {
        // Palabras clave de spam
        $spam_keywords = array(
            'viagra', 'casino', 'lottery', 'winner', 'congratulations',
            'free money', 'click here', 'buy now', 'limited time',
            'guaranteed', 'no risk', 'urgent', 'act now'
        );
        
        $content = strtolower($name . ' ' . $email . ' ' . $message);
        
        foreach ($spam_keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                error_log('Spam detected: keyword "' . $keyword . '" found in content');
                return true;
            }
        }
        
        // Verificar demasiados enlaces
        if (preg_match_all('/https?:\/\//', $message, $matches) > 2) {
            error_log('Spam detected: too many links in message');
            return true;
        }
        
        // Verificar caracteres repetitivos
        if (preg_match('/(.)\1{10,}/', $message)) {
            error_log('Spam detected: repetitive characters');
            return true;
        }
        
        return false;
    }
    
    /**
     * Enviar email de notificación
     */
    private function send_notification_email($name, $email, $company, $phone, $message) {
        $to = get_option('admin_email');
        $subject = 'Nuevo contacto desde Automatiza Tech - ' . $name;
        $body = "
        <h2>Nuevo mensaje de contacto</h2>
        <p><strong>Nombre:</strong> {$name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Empresa:</strong> {$company}</p>
        <p><strong>Teléfono:</strong> {$phone}</p>
        <p><strong>Mensaje:</strong></p>
        <p>{$message}</p>
        <p><small>Enviado desde: " . home_url() . "</small></p>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . $name . ' <' . $email . '>'
        );
        
        wp_mail($to, $subject, $body, $headers);
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            'Contactos Automatiza Tech',
            'Contactos',
            'manage_options',
            'automatiza-tech-contacts',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            25
        );
        
        add_submenu_page(
            'automatiza-tech-contacts',
            'Clientes Contratados',
            'Clientes',
            'manage_options',
            'automatiza-tech-clients',
            array($this, 'clients_page')
        );
    }
    
    /**
     * Scripts para el frontend
     */
    public function frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'automatiza_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('contact_form_nonce')
        ));
    }
    
    /**
     * Verificar si el usuario actual es administrador principal
     */
    private function is_main_admin() {
        return current_user_can('administrator') && is_super_admin();
    }
    
    /**
     * Mover contacto a tabla de clientes
     */
    private function move_to_clients($contact_id) {
        global $wpdb;
        
        // Obtener datos del contacto
        $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $contact_id));
        
        if (!$contact) {
            return false;
        }
        
        // Insertar en tabla de clientes
        $result = $wpdb->insert(
            $this->clients_table_name,
            array(
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'company' => $contact->company,
                'phone' => $contact->phone,
                'original_message' => $contact->message,
                'contacted_at' => $contact->submitted_at,
                'contracted_at' => current_time('mysql'),
                'contract_value' => 0.00,
                'project_type' => '',
                'contract_status' => 'active',
                'notes' => $contact->notes
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Eliminar de tabla de contactos
            $wpdb->delete($this->table_name, array('id' => $contact_id), array('%d'));
            
            // Log de la conversión
            error_log("CLIENTE CONVERTIDO: {$contact->name} ({$contact->email}) movido de contactos a clientes.");
            
            // Enviar correo de notificación para cliente contratado
            $this->send_contracted_client_email($contact);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Enviar correo de notificación cuando un cliente es contratado
     */
    private function send_contracted_client_email($contact) {
        // Configurar SMTP para desarrollo local
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        
        // Email de destino
        $to = 'automatizatech.bots@gmail.com';
        
        // Asunto del correo
        $subject = '🎉 ¡Nuevo Cliente Contratado! - ' . $contact->name;
        
        // Obtener URL del sitio para el encabezado
        $site_url = get_site_url();
        $admin_url = admin_url('admin.php?page=automatiza-tech-clients');
        
        // Construir el mensaje HTML
        $message = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 25px; border-radius: 0 0 8px 8px; }
                .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .label { font-weight: bold; color: #1e3a8a; display: inline-block; width: 120px; }
                .value { color: #495057; }
                .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 0.9em; }
                .cta { background: #06d6a0; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; display: inline-block; margin: 10px 0; }
                .message-box { background: #e3f2fd; padding: 15px; border-left: 4px solid #1976d2; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🎉 ¡Nuevo Cliente Contratado!</h1>
                <p>Se ha convertido un contacto a cliente en AutomatizaTech</p>
            </div>
            
            <div class='content'>
                <div class='info-box'>
                    <h3 style='color: #1e3a8a; margin-top: 0;'>📋 Información del Cliente</h3>
                    <p><span class='label'>Nombre:</span> <span class='value'>" . esc_html($contact->name) . "</span></p>
                    <p><span class='label'>Email:</span> <span class='value'>" . esc_html($contact->email) . "</span></p>
                    <p><span class='label'>Empresa:</span> <span class='value'>" . esc_html($contact->company ?: 'No especificada') . "</span></p>
                    <p><span class='label'>Teléfono:</span> <span class='value'>" . esc_html($contact->phone ?: 'No especificado') . "</span></p>
                    <p><span class='label'>Contactado:</span> <span class='value'>" . date('d/m/Y H:i', strtotime($contact->submitted_at)) . "</span></p>
                    <p><span class='label'>Contratado:</span> <span class='value'>" . date('d/m/Y H:i') . "</span></p>
                </div>
                
                " . (!empty($contact->message) ? "
                <div class='message-box'>
                    <h4 style='color: #1976d2; margin-top: 0;'>💬 Mensaje Original</h4>
                    <p>" . nl2br(esc_html($contact->message)) . "</p>
                </div>
                " : "") . "
                
                " . (!empty($contact->notes) ? "
                <div class='info-box'>
                    <h4 style='color: #1e3a8a; margin-top: 0;'>📝 Notas</h4>
                    <p>" . nl2br(esc_html($contact->notes)) . "</p>
                </div>
                " : "") . "
                
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='{$admin_url}' class='cta'>👥 Ver Panel de Clientes</a>
                </div>
                
                <div class='footer'>
                    <p>📧 Correo enviado automáticamente desde <strong>AutomatizaTech</strong></p>
                    <p>🌐 <a href='{$site_url}'>{$site_url}</a></p>
                    <p>📅 " . date('d/m/Y H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <noreply@automatizatech.com>',
            'Reply-To: automatizatech.bots@gmail.com'
        );
        
        // Enviar el correo
        $sent = wp_mail($to, $subject, $message, $headers);
        
        // Log del resultado
        if ($sent) {
            error_log("CORREO ENVIADO: Notificación de cliente contratado enviada a {$to} para {$contact->name} ({$contact->email})");
        } else {
            error_log("ERROR CORREO: No se pudo enviar notificación de cliente contratado para {$contact->name} ({$contact->email})");
            
            // Crear backup del correo en archivo para revisión manual
            $this->save_email_to_file($to, $subject, $message, $contact);
        }
        
        return $sent;
    }
    
    /**
     * Configurar SMTP para correo electrónico
     */
    public function configure_smtp($phpmailer) {
        $phpmailer->isSMTP();
        
        // Configuración para Gmail SMTP (recomendado para producción)
        if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
            $phpmailer->Host       = SMTP_HOST;
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Port       = SMTP_PORT ?? 587;
            $phpmailer->Username   = SMTP_USER;
            $phpmailer->Password   = SMTP_PASS;
            $phpmailer->SMTPSecure = 'tls';
        } else {
            // Configuración para desarrollo local con MailHog o similar
            $phpmailer->Host       = 'localhost';
            $phpmailer->SMTPAuth   = false;
            $phpmailer->Port       = 1025; // Puerto de MailHog
            $phpmailer->SMTPSecure = false;
        }
        
        $phpmailer->From     = 'noreply@automatizatech.com';
        $phpmailer->FromName = 'AutomatizaTech';
        
        // Log de configuración
        error_log("SMTP CONFIGURADO: Host={$phpmailer->Host}, Port={$phpmailer->Port}, Auth=" . ($phpmailer->SMTPAuth ? 'true' : 'false'));
    }
    
    /**
     * Guardar correo en archivo cuando falla el envío
     */
    private function save_email_to_file($to, $subject, $message, $contact) {
        $upload_dir = wp_upload_dir();
        $emails_dir = $upload_dir['basedir'] . '/automatiza-tech-emails/';
        
        // Crear directorio si no existe
        if (!file_exists($emails_dir)) {
            wp_mkdir_p($emails_dir);
        }
        
        // Nombre del archivo con timestamp
        $filename = 'cliente-contratado-' . date('Y-m-d_H-i-s') . '-' . sanitize_file_name($contact->name) . '.html';
        $filepath = $emails_dir . $filename;
        
        // Contenido del archivo
        $file_content = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$subject}</title>
</head>
<body>
    <div style='background: #f0f0f0; padding: 20px;'>
        <div style='background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <div style='background: #dc3545; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                <h2>⚠️ Correo No Enviado - Guardado para Revisión</h2>
                <p><strong>Para:</strong> {$to}</p>
                <p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <div style='border: 2px solid #1e3a8a; padding: 15px; border-radius: 8px;'>
                <h3>📧 Contenido del Correo Original:</h3>
                {$message}
            </div>
            
            <div style='margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px;'>
                <h3>🔧 Instrucciones:</h3>
                <p>Este correo no pudo ser enviado automáticamente. Para enviarlo manualmente:</p>
                <ol>
                    <li>Copia este contenido</li>
                    <li>Envía manualmente a: <strong>{$to}</strong></li>
                    <li>Asunto: <strong>{$subject}</strong></li>
                    <li>O configura SMTP en WordPress</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>";
        
        // Guardar archivo
        $saved = file_put_contents($filepath, $file_content);
        
        if ($saved) {
            error_log("EMAIL BACKUP: Correo guardado en archivo: {$filepath}");
            
            // Crear archivo de índice para fácil acceso
            $index_file = $emails_dir . 'index.html';
            $index_content = $this->generate_email_index($emails_dir);
            file_put_contents($index_file, $index_content);
            
            return $filepath;
        } else {
            error_log("ERROR: No se pudo guardar el correo en archivo");
            return false;
        }
    }
    
    /**
     * Generar índice de correos guardados
     */
    private function generate_email_index($emails_dir) {
        $files = glob($emails_dir . 'cliente-contratado-*.html');
        $site_url = get_site_url();
        $upload_url = wp_upload_dir()['baseurl'] . '/automatiza-tech-emails/';
        
        $index = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>📧 Correos de Clientes Contratados - AutomatizaTech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
        .email-item { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #1e3a8a; }
        .email-item a { color: #1e3a8a; text-decoration: none; font-weight: bold; }
        .email-item a:hover { text-decoration: underline; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📧 Correos de Clientes Contratados</h1>
            <p>AutomatizaTech - Correos No Enviados</p>
        </div>
        
        <div class='info'>
            <h3>ℹ️ Información</h3>
            <p>Estos correos no pudieron ser enviados automáticamente debido a la configuración de SMTP. Puedes:</p>
            <ul>
                <li>Revisar el contenido y enviar manualmente</li>
                <li>Configurar SMTP en WordPress</li>
                <li>Usar un plugin como WP Mail SMTP</li>
            </ul>
        </div>
        
        <h2>📋 Lista de Correos (" . count($files) . " total)</h2>";
        
        if (empty($files)) {
            $index .= "<p>No hay correos guardados.</p>";
        } else {
            foreach ($files as $file) {
                $filename = basename($file);
                $file_url = $upload_url . $filename;
                $file_time = filemtime($file);
                $formatted_time = date('Y-m-d H:i:s', $file_time);
                
                $index .= "
                <div class='email-item'>
                    <p><strong>📧 <a href='{$file_url}' target='_blank'>{$filename}</a></strong></p>
                    <p>📅 Guardado: {$formatted_time}</p>
                </div>";
            }
        }
        
        $index .= "
        <div class='info'>
            <p><strong>🔗 URL de acceso:</strong> <a href='{$upload_url}index.html' target='_blank'>{$upload_url}index.html</a></p>
            <p><strong>📁 Ubicación física:</strong> {$emails_dir}</p>
        </div>
    </div>
</body>
</html>";
        
        return $index;
    }
    
    /**
     * Verificar si un número de teléfono ya existe
     */
    public function check_phone_exists() {
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'automatiza_ajax_nonce')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        if (!isset($_POST['phone']) || empty($_POST['phone'])) {
            wp_send_json_error('Teléfono no proporcionado');
            wp_die();
        }
        
        global $wpdb;
        $phone = sanitize_text_field($_POST['phone']);
        
        // Verificar en tabla de contactos
        $contact_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s",
            $phone
        ));
        
        // Verificar en tabla de clientes
        $client_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->clients_table_name} WHERE phone = %s",
            $phone
        ));
        
        $exists = ($contact_exists > 0 || $client_exists > 0);
        
        wp_send_json_success(array('exists' => $exists));
        wp_die();
    }
    
    /**
     * Obtener detalles de un contacto para el modal
     */
    public function get_contact_details() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_contact_details')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            wp_send_json_error('ID de contacto no proporcionado');
            wp_die();
        }
        
        global $wpdb;
        $contact_id = intval($_POST['id']);
        
        $contact = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $contact_id
        ));
        
        if (!$contact) {
            wp_send_json_error('Contacto no encontrado');
            wp_die();
        }
        
        // Crear HTML de respuesta
        $html = '<h2>Detalles del Contacto</h2>';
        $html .= '<p><strong>Nombre:</strong> ' . esc_html($contact->name) . '</p>';
        $html .= '<p><strong>Email:</strong> ' . esc_html($contact->email) . '</p>';
        $html .= '<p><strong>Empresa:</strong> ' . esc_html($contact->company) . '</p>';
        $html .= '<p><strong>Teléfono:</strong> ' . esc_html($contact->phone) . '</p>';
        $html .= '<p><strong>Estado:</strong> ' . esc_html($contact->status) . '</p>';
        $html .= '<p><strong>Mensaje:</strong></p>';
        $html .= '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">' . nl2br(esc_html($contact->message)) . '</div>';
        
        wp_send_json_success($html);
        wp_die();
    }
    /**
     * Obtener detalles de un cliente para el modal
     */
    public function get_client_details() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_client_details')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            wp_send_json_error('ID de cliente no proporcionado');
            wp_die();
        }
        
        global $wpdb;
        $client_id = intval($_POST['id']);
        
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->clients_table_name} WHERE id = %d",
            $client_id
        ));
        
        if (!$client) {
            wp_send_json_error('Cliente no encontrado');
            wp_die();
        }
        
        // Formatear los datos para mostrar
        $status_labels = array(
            'active' => '✅ Activo',
            'completed' => '🎉 Completado',
            'paused' => '⏸️ Pausado',
            'cancelled' => '❌ Cancelado'
        );
        
        $html = '<div class="client-details-content">';
        $html .= '<div class="detail-header">';
        $html .= '<h3 style="color: #d63384; margin: 0 0 15px 0; font-size: 20px;">👤 ' . esc_html($client->name) . '</h3>';
        $html .= '<span class="status-badge status-' . esc_attr($client->contract_status) . '">' . $status_labels[$client->contract_status] . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="detail-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;">';
        $html .= '<div class="detail-item">';
        $html .= '<strong style="color: #d63384;">📧 Email:</strong><br>';
        $html .= '<a href="mailto:' . esc_attr($client->email) . '" style="color: #0073aa;">' . esc_html($client->email) . '</a>';
        $html .= '</div>';
        
        $html .= '<div class="detail-item">';
        $html .= '<strong style="color: #d63384;">📱 Teléfono:</strong><br>';
        if ($client->phone) {
            $html .= '<a href="tel:' . esc_attr($client->phone) . '" style="color: #0073aa;">' . esc_html($client->phone) . '</a>';
        } else {
            $html .= '<span style="color: #999;">No especificado</span>';
        }
        $html .= '</div>';
        
        $html .= '<div class="detail-item">';
        $html .= '<strong style="color: #d63384;">🏢 Empresa:</strong><br>';
        $html .= $client->company ? esc_html($client->company) : '<span style="color: #999;">No especificada</span>';
        $html .= '</div>';
        
        $html .= '<div class="detail-item">';
        $html .= '<strong style="color: #d63384;">💰 Valor del Contrato:</strong><br>';
        if ($client->contract_value > 0) {
            $html .= '$' . number_format($client->contract_value, 0, ',', '.') . ' CLP';
        } else {
            $html .= '<span style="color: #999;">No definido</span>';
        }
        $html .= '</div>';
        
        $html .= '<div class="detail-item">';
        $html .= '<strong style="color: #d63384;">🛠️ Tipo de Proyecto:</strong><br>';
        $html .= $client->project_type ? esc_html($client->project_type) : '<span style="color: #999;">No especificado</span>';
        $html .= '</div>';
        
        $html .= '<div class="detail-item">';
        $html .= '<strong style="color: #d63384;">📅 Fecha de Contrato:</strong><br>';
        $html .= date('d/m/Y H:i:s', strtotime($client->contracted_at));
        $html .= '</div>';
        $html .= '</div>';
        
        if ($client->original_message) {
            $html .= '<div class="detail-message" style="margin-top: 20px;">';
            $html .= '<strong style="color: #d63384;">💬 Mensaje Original:</strong>';
            $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; border-left: 4px solid #d63384;">';
            $html .= nl2br(esc_html($client->original_message));
            $html .= '</div>';
            $html .= '</div>';
        }
        
        if ($client->notes) {
            $html .= '<div class="detail-notes" style="margin-top: 20px;">';
            $html .= '<strong style="color: #d63384;">📝 Notas del Proyecto:</strong>';
            $html .= '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 10px; border-left: 4px solid #ffc107;">';
            $html .= nl2br(esc_html($client->notes));
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success($html);
        wp_die();
    }
    
    /**
     * Búsqueda asíncrona de contactos
     */
    public function search_contacts() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'search_contacts')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        global $wpdb;
        
        if (empty($search_term)) {
            // Si no hay término de búsqueda, devolver todos los contactos
            $contacts = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY submitted_at DESC");
        } else {
            // Búsqueda en múltiples columnas
            $contacts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE name LIKE %s 
                 OR email LIKE %s 
                 OR company LIKE %s 
                 OR phone LIKE %s 
                 OR message LIKE %s 
                 ORDER BY submitted_at DESC",
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%'
            ));
        }
        
        wp_send_json_success($contacts);
        wp_die();
    }
    
    /**
     * Búsqueda asíncrona de clientes
     */
    public function search_clients() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'search_clients')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        global $wpdb;
        
        if (empty($search_term)) {
            // Si no hay término de búsqueda, devolver todos los clientes
            $clients = $wpdb->get_results("SELECT * FROM {$this->clients_table_name} ORDER BY contracted_at DESC");
        } else {
            // Búsqueda en múltiples columnas
            $clients = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->clients_table_name} 
                 WHERE name LIKE %s 
                 OR email LIKE %s 
                 OR company LIKE %s 
                 OR phone LIKE %s 
                 OR project_type LIKE %s
                 OR notes LIKE %s
                 ORDER BY contracted_at DESC",
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%'
            ));
        }
        
        wp_send_json_success($clients);
        wp_die();
    }
    
    /**
     * Manejar acción de exportación antes de cargar la página
     */
    public function handle_export_action() {
        if (isset($_GET['page']) && $_GET['page'] === 'automatiza-tech-contacts' && 
            isset($_GET['action']) && $_GET['action'] === 'export') {
            $this->export_to_csv();
        }
    }
    
    /**
     * Scripts para el admin
     */
    public function admin_scripts($hook) {
        if ($hook != 'toplevel_page_automatiza-tech-contacts' && $hook != 'contactos_page_automatiza-tech-clients') {
            return;
        }
        wp_enqueue_script('jquery');
        // Localizar script para AJAX
        wp_localize_script('jquery', 'automatizaTechAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('automatiza_tech_nonce')
        ));
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        global $wpdb;
        
        // Manejar acciones
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'delete':
                    if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_contact')) {
                        $wpdb->delete($this->table_name, array('id' => intval($_GET['id'])), array('%d'));
                        echo '<div class="notice notice-success"><p>Contacto eliminado correctamente.</p></div>';
                    }
                    break;
                case 'update_status':
                    if (isset($_GET['id']) && isset($_GET['status']) && wp_verify_nonce($_GET['_wpnonce'], 'update_status')) {
                        $contact_id = intval($_GET['id']);
                        $new_status = sanitize_text_field($_GET['status']);
                        
                        // Si el estado es "contratado", mover a tabla de clientes
                        if ($new_status === 'contracted') {
                            $result = $this->move_to_clients($contact_id);
                            if ($result) {
                                echo '<div class="notice notice-success"><p>🎉 ¡Contacto movido a Clientes exitosamente! El cliente ahora aparece en la sección de Clientes.</p></div>';
                            } else {
                                echo '<div class="notice notice-error"><p>❌ Error al mover el contacto a Clientes.</p></div>';
                            }
                        } else {
                            // Actualización normal de estado
                            $wpdb->update(
                                $this->table_name,
                                array('status' => $new_status),
                                array('id' => $contact_id),
                                array('%s'),
                                array('%d')
                            );
                            echo '<div class="notice notice-success"><p>Estado actualizado correctamente.</p></div>';
                        }
                    }
                    break;
                
                case 'edit_contact':
                    // Solo administradores pueden editar contactos
                    if (!current_user_can('administrator')) {
                        echo '<div class="notice notice-error"><p>❌ Acceso denegado: Solo administradores pueden editar contactos.</p></div>';
                        break;
                    }
                    
                    if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'edit_contact')) {
                        $contact_id = intval($_GET['id']);
                        $name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';
                        $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
                        $company = isset($_GET['company']) ? sanitize_text_field($_GET['company']) : '';
                        $phone = isset($_GET['phone']) ? sanitize_text_field($_GET['phone']) : '';
                        $message = isset($_GET['message']) ? sanitize_textarea_field($_GET['message']) : '';
                        
                        // Validar datos requeridos
                        if (empty($name) || empty($email)) {
                            echo '<div class="notice notice-error"><p>❌ Error: Nombre y email son obligatorios.</p></div>';
                            break;
                        }
                        
                        // Validar email
                        if (!is_email($email)) {
                            echo '<div class="notice notice-error"><p>❌ Error: Email no válido.</p></div>';
                            break;
                        }
                        
                        $result = $wpdb->update(
                            $this->table_name,
                            array(
                                'name' => $name,
                                'email' => $email,
                                'company' => $company,
                                'phone' => $phone,
                                'message' => $message
                            ),
                            array('id' => $contact_id),
                            array('%s', '%s', '%s', '%s', '%s'),
                            array('%d')
                        );
                        
                        if ($result !== false) {
                            echo '<div class="notice notice-success"><p>✅ Contacto actualizado exitosamente.</p></div>';
                        } else {
                            echo '<div class="notice notice-error"><p>❌ Error al actualizar el contacto.</p></div>';
                        }
                    }
                    break;
                
                case 'delete_all':
                    // Verificación múltiple de permisos para máxima seguridad
                    if (!current_user_can('administrator')) {
                        echo '<div class="notice notice-error"><p>❌ Acceso denegado: Solo administradores pueden realizar esta acción.</p></div>';
                        break;
                    }
                    
                    if (!is_super_admin()) {
                        echo '<div class="notice notice-error"><p>❌ Acceso denegado: Solo el administrador principal puede eliminar todos los contactos.</p></div>';
                        break;
                    }
                    
                    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_all_contacts')) {
                        echo '<div class="notice notice-error"><p>❌ Error de seguridad: Token de verificación inválido.</p></div>';
                        break;
                    }
                    
                    // Log de seguridad
                    $current_user = wp_get_current_user();
                    error_log("SECURITY LOG: Usuario {$current_user->user_login} (ID: {$current_user->ID}) intentó eliminar todos los contactos.");
                    
                    // Obtener count antes de eliminar para el log
                    $contact_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
                    
                    // Ejecutar eliminación
                    $deleted = $wpdb->query("DELETE FROM {$this->table_name}");
                    
                    if ($deleted !== false) {
                        error_log("SECURITY LOG: Eliminación exitosa. {$deleted} contactos eliminados por {$current_user->user_login}");
                        echo '<div class="notice notice-success"><p>✅ Todos los contactos han sido eliminados correctamente. (' . $deleted . ' registros eliminados)</p></div>';
                    } else {
                        error_log("SECURITY LOG: Error al eliminar contactos. Usuario: {$current_user->user_login}");
                        echo '<div class="notice notice-error"><p>❌ Error al eliminar los contactos.</p></div>';
                    }
                    break;
            }
        }
        
        // Obtener contactos
        $contacts = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY submitted_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Contactos Automatiza Tech</h1>
            
            <?php
            $current_user = wp_get_current_user();
            $is_main_admin = $this->is_main_admin();
            ?>
            
            <div class="notice notice-info" style="margin-top: 15px;">
                <p>
                    <strong>👤 Usuario actual:</strong> <?php echo esc_html($current_user->display_name); ?> (<?php echo esc_html($current_user->user_login); ?>)
                    <span style="margin-left: 15px;">
                        <strong>🔑 Permisos:</strong> 
                        <?php if ($is_main_admin): ?>
                            <span style="color: #46b450;">✅ Administrador Principal</span> - Acceso completo a todas las funciones
                        <?php elseif (current_user_can('manage_options')): ?>
                            <span style="color: #ffb900;">⚠️ Administrador</span> - Acceso limitado (no puede eliminar todos los contactos)
                        <?php else: ?>
                            <span style="color: #dc3232;">❌ Sin permisos</span> - Solo lectura
                        <?php endif; ?>
                    </span>
                </p>
            </div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=export'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span> Exportar CSV
                    </a>
                    <?php if (!empty($contacts) && current_user_can('administrator') && is_super_admin()): ?>
                    <a href="#" 
                       class="button button-secondary delete-all-contacts" 
                       onclick="showDeleteAllConfirmation('<?php echo wp_nonce_url(admin_url('admin.php?page=automatiza-tech-contacts&action=delete_all'), 'delete_all_contacts'); ?>', <?php echo count($contacts); ?>)"
                       data-delete-url="<?php echo wp_nonce_url(admin_url('admin.php?page=automatiza-tech-contacts&action=delete_all'), 'delete_all_contacts'); ?>"
                       data-contact-count="<?php echo count($contacts); ?>">
                        <span class="dashicons dashicons-trash"></span> Eliminar Todos los Contactos (Solo Admin)
                    </a>
                    <?php elseif (!empty($contacts) && current_user_can('manage_options')): ?>
                    <span class="button button-secondary" style="opacity: 0.5; cursor: not-allowed;" title="Solo el administrador principal puede eliminar todos los contactos">
                        <span class="dashicons dashicons-lock"></span> Eliminar Todos (Restringido)
                    </span>
                    <div class="notice notice-warning inline" style="margin: 10px 0; padding: 8px 12px; display: inline-block; background: #fff3cd; border-left: 4px solid #ffb900;">
                        <p style="margin: 0; font-size: 12px;">
                            <span class="dashicons dashicons-info" style="font-size: 14px; margin-right: 5px;"></span>
                            <strong>Información:</strong> Solo el administrador principal puede eliminar todos los contactos por seguridad.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="alignright">
                    <span class="displaying-num"><?php echo count($contacts); ?> elementos</span>
                </div>
            </div>
            
            <?php if (current_user_can('administrator')): ?>
            <div class="notice notice-info inline" style="margin: 15px 0; padding: 10px 15px; background: #e8f4fd; border-left: 4px solid #72aee6;">
                <p style="margin: 0; font-size: 13px;">
                    <span class="dashicons dashicons-admin-users" style="color: #2271b1; margin-right: 8px;"></span>
                    <strong>Administrador:</strong> Tienes permisos especiales para <strong>✏️ Editar</strong> los datos de cualquier contacto. 
                    Esta funcionalidad solo está disponible para administradores por seguridad.
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Campo de búsqueda asíncrona -->
            <div class="search-box" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e3e6f0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-search" style="color: #2271b1; font-size: 20px;"></span>
                    <input type="text" id="contact-search" placeholder="🔍 Buscar contactos por nombre, email, empresa, teléfono o mensaje..." 
                           style="flex: 1; padding: 10px 15px; border: 2px solid #e3e6f0; border-radius: 25px; font-size: 14px; transition: border-color 0.3s;"
                           onfocus="this.style.borderColor='#2271b1'"
                           onblur="this.style.borderColor='#e3e6f0'">
                    <button type="button" id="clear-search" class="button button-secondary" style="border-radius: 20px; padding: 8px 15px;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 16px;"></span> Limpiar
                    </button>
                </div>
                <div id="search-results-info" style="margin-top: 10px; font-size: 13px; color: #666; display: none;">
                    <span class="dashicons dashicons-info" style="font-size: 14px; margin-right: 5px;"></span>
                    <span id="search-count">0</span> resultados encontrados
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped" id="contacts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Empresa</th>
                        <th>Teléfono</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th style="text-align: center; width: 80px;">👁️ Ver</th>
                        <?php if (current_user_can('administrator')): ?>
                        <th style="text-align: center; width: 80px;">✏️ Editar</th>
                        <?php endif; ?>
                        <th style="text-align: center; width: 80px;">🗑️ Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contacts)): ?>
                        <tr>
                            <td colspan="<?php echo current_user_can('administrator') ? '10' : '9'; ?>" style="text-align: center; padding: 20px;">
                                No hay contactos registrados aún.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?php echo $contact->id; ?></td>
                                <td><strong><?php echo esc_html($contact->name); ?></strong></td>
                                <td><a href="mailto:<?php echo esc_attr($contact->email); ?>"><?php echo esc_html($contact->email); ?></a></td>
                                <td><?php echo esc_html($contact->company); ?></td>
                                <td><?php echo esc_html($contact->phone); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($contact->submitted_at)); ?></td>
                                <td>
                                    <select onchange="updateStatus(<?php echo $contact->id; ?>, this.value)" 
                                            data-original-value="<?php echo esc_attr($contact->status); ?>"
                                            class="status-selector">
                                        <option value="new" <?php selected($contact->status, 'new'); ?>>🆕 Nuevo</option>
                                        <option value="contacted" <?php selected($contact->status, 'contacted'); ?>>📞 Contactado</option>
                                        <option value="contracted" <?php selected($contact->status, 'contracted'); ?>>⭐ Contratado</option>
                                        <option value="closed" <?php selected($contact->status, 'closed'); ?>>🔒 Cerrado</option>
                                    </select>
                                </td>
                                <!-- Columna Ver Detalles -->
                                <td style="text-align: center;">
                                    <a href="#" onclick="showContactDetails(<?php echo $contact->id; ?>)" 
                                       class="button button-small view-contact-btn"
                                       style="background: linear-gradient(135deg, #0073aa, #005a87); color: white; border: none; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;"
                                       title="Ver detalles completos del contacto">
                                       👁️
                                    </a>
                                </td>
                                <!-- Columna Editar (Solo Administradores) -->
                                <?php if (current_user_can('administrator')): ?>
                                <td style="text-align: center;">
                                    <a href="#" onclick="editContact(<?php echo $contact->id; ?>, this)" 
                                       class="button button-small edit-contact-btn"
                                       style="background: linear-gradient(135deg, #72aee6, #2271b1); color: white; border: none; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;"
                                       data-contact-id="<?php echo $contact->id; ?>"
                                       data-contact-name="<?php echo esc_attr($contact->name); ?>"
                                       data-contact-email="<?php echo esc_attr($contact->email); ?>"
                                       data-contact-company="<?php echo esc_attr($contact->company); ?>"
                                       data-contact-phone="<?php echo esc_attr($contact->phone); ?>"
                                       data-contact-message="<?php echo esc_attr($contact->message); ?>"
                                       title="Solo administradores pueden editar contactos">
                                       ✏️
                                    </a>
                                </td>
                                <?php endif; ?>
                                <!-- Columna Eliminar -->
                                <td style="text-align: center;">
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=automatiza-tech-contacts&action=delete&id=' . $contact->id), 'delete_contact'); ?>" 
                                       class="button button-small delete-contact-btn"
                                       style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; border: none; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;"
                                       onclick="return confirm('¿Estás seguro de eliminar este contacto?')"
                                       title="Eliminar este contacto permanentemente">
                                       🗑️
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Modal para ver detalles -->
        <div id="contact-modal" style="display: none;">
            <div class="contact-modal-content">
                <span class="contact-modal-close">&times;</span>
                <div id="contact-details"></div>
            </div>
        </div>
        
        <!-- Modal para confirmación de eliminación masiva -->
        <div id="delete-all-modal" style="display: none;">
            <div class="delete-all-modal-content">
                <div class="delete-all-header">
                    <span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 24px; margin-right: 10px;"></span>
                    <h2 style="margin: 0; color: #dc3232;">⚠️ ADVERTENCIA CRÍTICA</h2>
                </div>
                <div class="delete-all-body">
                    <p><strong>Estás a punto de eliminar TODOS los contactos de forma PERMANENTE.</strong></p>
                    <div class="warning-details">
                        <p>📊 Total de contactos a eliminar: <strong id="contact-count-display">0</strong></p>
                        <p>❌ Esta acción NO se puede deshacer</p>
                        <p>❌ Se perderán todos los datos</p>
                        <p>❌ No hay respaldo automático</p>
                    </div>
                    <div class="confirmation-input">
                        <label for="delete-confirmation">Para confirmar, escribe exactamente: <strong>ELIMINAR</strong></label>
                        <input type="text" id="delete-confirmation" placeholder="Escribe ELIMINAR para confirmar" style="width: 100%; padding: 8px; margin: 10px 0; border: 2px solid #dc3232; border-radius: 4px;">
                    </div>
                </div>
                <div class="delete-all-footer">
                    <button type="button" class="button button-secondary" onclick="cancelDeleteAll()">
                        <span class="dashicons dashicons-no"></span> Cancelar
                    </button>
                    <button type="button" class="button button-primary" id="confirm-delete-btn" onclick="confirmDeleteAll()" disabled style="background-color: #dc3232; border-color: #dc3232;">
                        <span class="dashicons dashicons-trash"></span> Eliminar Todos
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .button.delete-all-contacts {
            background-color: #dc3232 !important;
            border-color: #dc3232 !important;
            color: white !important;
            transition: all 0.3s ease;
        }
        
        .button.delete-all-contacts:hover {
            background-color: #a00 !important;
            border-color: #a00 !important;
            transform: scale(1.05);
        }
        
        .button.delete-all-contacts .dashicons {
            margin-right: 5px;
        }
        
        .tablenav .alignleft .button + .button {
            margin-left: 10px;
        }
        
        #delete-all-modal {
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            display: none;
        }
        
        .delete-all-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #dc3232;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 4px 20px rgba(220, 50, 50, 0.3);
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .delete-all-header {
            background-color: #dc3232;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }
        
        .delete-all-body {
            padding: 20px;
        }
        
        .warning-details {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .warning-details p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .confirmation-input label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #dc3232;
        }
        
        .delete-all-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
            background-color: #f9f9f9;
            border-radius: 0 0 8px 8px;
        }
        
        .delete-all-footer .button {
            margin-left: 10px;
        }
        
        #confirm-delete-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        #contact-modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        .contact-modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
        }
        .contact-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .contact-modal-close:hover {
            color: black;
        }
        
        /* Estilos para selector de estado */
        .status-selector {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            color: #333;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .status-selector:hover {
            border-color: #06d6a0;
            box-shadow: 0 0 5px rgba(6, 214, 160, 0.3);
        }
        
        .status-selector:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 5px rgba(30, 58, 138, 0.3);
        }
        
        .status-selector option {
            padding: 5px;
        }
        
        /* Estados específicos con colores */
        .status-selector[data-original-value="new"] {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
        
        .status-selector[data-original-value="contacted"] {
            background-color: #fff3e0;
            border-color: #ff9800;
        }
        
        .status-selector[data-original-value="contracted"] {
            background-color: #e8f5e8;
            border-color: #4caf50;
            font-weight: bold;
        }
        
        .status-selector[data-original-value="closed"] {
            background-color: #fce4ec;
            border-color: #e91e63;
        }
        
        /* Efecto de procesamiento */
        .status-selector:disabled {
            background-color: #ffc107 !important;
            color: #000 !important;
            cursor: wait;
            opacity: 0.8;
        }
        
        /* Estilos para botón de editar contacto */
        .edit-contact-btn {
            border: none !important;
            font-weight: 600 !important;
            padding: 6px 12px !important;
            border-radius: 20px !important;
            text-decoration: none !important;
            transition: all 0.3s !important;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3) !important;
        }
        
        .edit-contact-btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.5) !important;
            text-decoration: none !important;
        }
        
        /* Indicador de solo admin */
        .edit-contact-btn::after {
            content: " 👨‍💼";
            font-size: 10px;
            opacity: 0.7;
        }
        
        /* Responsive para botones de acción */
        @media (max-width: 768px) {
            .edit-contact-btn {
                display: block !important;
                width: 100% !important;
                margin: 2px 0 !important;
                text-align: center !important;
            }
        }
        </style>
        
        <script>
        function updateStatus(id, status) {
            // Confirmación especial para estado "Contratado"
            if (status === 'contracted') {
                // Obtener información del contacto para mostrar en la confirmación
                var selectElement = event.target;
                var contactRow = selectElement.closest('tr');
                var contactName = 'el contacto';
                var contactEmail = '';
                
                // Obtener nombre del contacto (primera columna)
                if (contactRow) {
                    var nameCell = contactRow.querySelector('td:first-child');
                    if (nameCell) {
                        contactName = nameCell.textContent.trim();
                    }
                    
                    // Obtener email del contacto (segunda columna)
                    var emailCell = contactRow.querySelector('td:nth-child(2)');
                    if (emailCell) {
                        contactEmail = emailCell.textContent.trim();
                    }
                }
                
                // Crear mensaje de confirmación detallado
                var confirmMessage = 
                    "🎉 ¿CONFIRMAR CAMBIO A CONTRATADO?\n\n" +
                    "� Cliente: " + contactName + "\n" +
                    "📧 Email: " + contactEmail + "\n\n" +
                    "⚠️ ESTA ACCIÓN REALIZARÁ LOS SIGUIENTES CAMBIOS:\n\n" +
                    "✅ Moverá el contacto a la tabla de CLIENTES\n" +
                    "📧 Enviará correo automático a automatizatech.bots@gmail.com\n" +
                    "🗑️ Eliminará el contacto de esta lista de contactos\n" +
                    "📋 Registrará la conversión en los logs del sistema\n" +
                    "📊 El cliente aparecerá en el panel de Clientes\n\n" +
                    "⚠️ IMPORTANTE: Esta acción NO se puede deshacer\n\n" +
                    "¿Confirmas que este cliente está oficialmente CONTRATADO?";
                
                // Mostrar confirmación
                if (confirm(confirmMessage)) {
                    // Cambiar el selector para mostrar procesamiento
                    selectElement.disabled = true;
                    selectElement.style.background = '#ffc107';
                    selectElement.style.color = '#000';
                    selectElement.style.fontWeight = 'bold';
                    
                    // Crear nueva opción de procesamiento
                    var processingOption = document.createElement('option');
                    processingOption.value = 'processing';
                    processingOption.text = '⏳ Procesando... Por favor espera';
                    processingOption.selected = true;
                    selectElement.innerHTML = '';
                    selectElement.appendChild(processingOption);
                    
                    // Mostrar mensaje de procesamiento en la fila
                    var statusCell = selectElement.closest('td');
                    var originalHTML = statusCell.innerHTML;
                    
                    // Agregar indicador visual
                    var processingIndicator = document.createElement('div');
                    processingIndicator.style.cssText = 'background: #fff3cd; padding: 5px; border-radius: 4px; font-size: 11px; margin-top: 5px; border: 1px solid #ffc107;';
                    processingIndicator.innerHTML = '⏳ Moviendo a Clientes y enviando correo...';
                    statusCell.appendChild(processingIndicator);
                    
                    // Proceder con el cambio después de un breve delay para mostrar el feedback
                    setTimeout(function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=update_status'); ?>&id=' + id + '&status=' + status + '&_wpnonce=<?php echo wp_create_nonce('update_status'); ?>';
                    }, 1000);
                } else {
                    // Usuario canceló - revertir el selector
                    selectElement.value = selectElement.getAttribute('data-original-value') || 'new';
                    return false;
                }
            }
            // Para otros estados, proceder normalmente con confirmación simple
            else {
                var statusNames = {
                    'pending': '⏳ Pendiente',
                    'contacted': '📞 Contactado',
                    'interested': '👍 Interesado',
                    'not_interested': '👎 No Interesado',
                    'follow_up': '📅 Seguimiento'
                };
                
                var statusName = statusNames[status] || status;
                var confirmMessage = "¿Confirmas cambiar el estado a: " + statusName + "?";
                
                if (confirm(confirmMessage)) {
                    window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=update_status'); ?>&id=' + id + '&status=' + status + '&_wpnonce=<?php echo wp_create_nonce('update_status'); ?>';
                } else {
                    // Revertir el selector
                    event.target.value = event.target.getAttribute('data-original-value') || 'pending';
                    return false;
                }
            }
        }
        
        // Nueva función para mostrar detalles del contacto en modal mejorado
        function showContactDetails(id) {
            // Debug: verificar si automatizaTechAjax está definido
            console.log('automatizaTechAjax:', typeof automatizaTechAjax !== 'undefined' ? automatizaTechAjax : 'undefined');
            
            // Crear modal de carga
            var loadingModal = `
                <div id="contact-details-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center;">
                    <div style="background-color: #fefefe; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="text-align: center; padding: 40px;">
                            <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                            <p style="margin-top: 20px; color: #2271b1; font-size: 16px;">Cargando detalles del contacto...</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingModal);
            
            // Usar ajaxurl como fallback si automatizaTechAjax no está disponible
            var ajaxUrl = (typeof automatizaTechAjax !== 'undefined' && automatizaTechAjax.ajaxurl) 
                         ? automatizaTechAjax.ajaxurl 
                         : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>');
            
            console.log('Using AJAX URL:', ajaxUrl);
            
            // Obtener detalles del contacto via AJAX
            jQuery.post(ajaxUrl, {
                action: 'get_contact_details',
                id: id,
                nonce: '<?php echo wp_create_nonce('get_contact_details'); ?>'
            }, function(response) {
                console.log('AJAX Response:', response);
                // Remover modal de carga
                document.getElementById('contact-details-modal').remove();
                
                if (response.success) {
                    // Crear modal con los detalles
                    var detailsModal = `
                        <div id="contact-details-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center;">
                            <div style="background-color: #fefefe; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 3px solid #2271b1; padding-bottom: 15px;">
                                    <h2 style="margin: 0; color: #2271b1; font-size: 24px;">📄 Detalles del Contacto</h2>
                                    <span onclick="closeContactDetailsModal()" style="cursor: pointer; font-size: 32px; font-weight: bold; color: #999; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#999'">&times;</span>
                                </div>
                                <div class="contact-details-content">
                                    ${response.data}
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', detailsModal);
                } else {
                    alert('Error al cargar los detalles del contacto: ' + (response.data || 'Error desconocido'));
                }
            }).fail(function(xhr, status, error) {
                console.log('AJAX Error:', {xhr: xhr, status: status, error: error});
                // Remover modal de carga en caso de error
                document.getElementById('contact-details-modal').remove();
                alert('Error de conexión al cargar los detalles del contacto. Verifique la consola para más detalles.');
            });
        }
        
        // Función para mostrar detalles del cliente en modal
        function showClientDetailsModal(id) {
            // Crear modal de carga
            var loadingModal = `
                <div id="client-details-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center;">
                    <div style="background-color: #fefefe; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="text-align: center; padding: 40px;">
                            <div style="display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #d63384; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                            <p style="margin-top: 20px; color: #d63384; font-size: 16px;">Cargando detalles del cliente...</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingModal);
            
            // Obtener detalles del cliente via AJAX
            jQuery.post(automatizaTechAjax.ajaxurl, {
                action: 'get_client_details',
                id: id,
                nonce: '<?php echo wp_create_nonce('get_client_details'); ?>'
            }, function(response) {
                // Remover modal de carga
                document.getElementById('client-details-modal').remove();
                
                if (response.success) {
                    // Crear modal con los detalles
                    var detailsModal = `
                        <div id="client-details-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center;">
                            <div style="background-color: #fefefe; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 3px solid #d63384; padding-bottom: 15px;">
                                    <h2 style="margin: 0; color: #d63384; font-size: 24px;">📄 Detalles del Cliente</h2>
                                    <span onclick="closeClientDetailsModal()" style="cursor: pointer; font-size: 32px; font-weight: bold; color: #999; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#999'">&times;</span>
                                </div>
                                <div class="client-details-content">
                                    ${response.data}
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', detailsModal);
                } else {
                    alert('Error al cargar los detalles del cliente: ' + response.data);
                }
            }).fail(function() {
                // Remover modal de carga en caso de error
                document.getElementById('client-details-modal').remove();
                alert('Error de conexión al cargar los detalles del cliente');
            });
        }
        
        // Funciones para cerrar modales de detalles
        function closeContactDetailsModal() {
            var modal = document.getElementById('contact-details-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        function closeClientDetailsModal() {
            var modal = document.getElementById('client-details-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Función de búsqueda asíncrona para contactos
        function searchContacts(searchTerm) {
            jQuery.post(automatizaTechAjax.ajaxurl, {
                action: 'search_contacts',
                search: searchTerm,
                nonce: '<?php echo wp_create_nonce('search_contacts'); ?>'
            }, function(response) {
                if (response.success) {
                    updateContactsTable(response.data, searchTerm);
                }
            });
        }
        
        // Función de búsqueda asíncrona para clientes
        function searchClients(searchTerm) {
            jQuery.post(automatizaTechAjax.ajaxurl, {
                action: 'search_clients',
                search: searchTerm,
                nonce: '<?php echo wp_create_nonce('search_clients'); ?>'
            }, function(response) {
                if (response.success) {
                    updateClientsTable(response.data, searchTerm);
                }
            });
        }
        
        // Función para actualizar la tabla de contactos con resultados de búsqueda
        function updateContactsTable(contacts, searchTerm) {
            var tbody = document.querySelector('#contacts-table tbody');
            if (!tbody) return;
            
            var html = '';
            
            if (contacts.length === 0) {
                html = '<tr><td colspan="8" style="text-align: center; padding: 20px;">No se encontraron contactos que coincidan con la búsqueda.</td></tr>';
            } else {
                contacts.forEach(function(contact) {
                    var statusOptions = {
                        'new': '🆕 Nuevo',
                        'contacted': '📞 Contactado',
                        'contracted': '⭐ Contratado',
                        'closed': '🔒 Cerrado'
                    };
                    
                    html += '<tr>';
                    html += '<td>' + contact.id + '</td>';
                    html += '<td><strong>' + escapeHtml(contact.name) + '</strong></td>';
                    html += '<td><a href="mailto:' + escapeHtml(contact.email) + '">' + escapeHtml(contact.email) + '</a></td>';
                    html += '<td>' + escapeHtml(contact.company || '') + '</td>';
                    html += '<td>' + escapeHtml(contact.phone || '') + '</td>';
                    html += '<td>' + formatDate(contact.submitted_at) + '</td>';
                    
                    // Estado selector
                    html += '<td><select onchange="updateStatus(' + contact.id + ', this.value)" data-original-value="' + contact.status + '" class="status-selector">';
                    Object.keys(statusOptions).forEach(function(key) {
                        html += '<option value="' + key + '"' + (contact.status === key ? ' selected' : '') + '>' + statusOptions[key] + '</option>';
                    });
                    html += '</select></td>';
                    
                    // Acciones
                    html += '<td>';
                    html += '<a href="#" onclick="showContactDetails(' + contact.id + ')" class="button button-small view-contact-btn" style="background: linear-gradient(135deg, #0073aa, #005a87); color: white; margin-right: 3px;" title="Ver detalles completos del contacto">👁️ Ver Detalles</a>';
                    
                    <?php if (current_user_can('administrator')): ?>
                    html += '<a href="#" onclick="editContact(' + contact.id + ', this)" class="button button-small edit-contact-btn" style="background: linear-gradient(135deg, #72aee6, #2271b1); color: white; margin-left: 3px;" data-contact-id="' + contact.id + '" data-contact-name="' + escapeHtml(contact.name) + '" data-contact-email="' + escapeHtml(contact.email) + '" data-contact-company="' + escapeHtml(contact.company || '') + '" data-contact-phone="' + escapeHtml(contact.phone || '') + '" data-contact-message="' + escapeHtml(contact.message || '') + '" title="Solo administradores pueden editar contactos">✏️ Editar</a>';
                    <?php endif; ?>
                    
                    html += '<a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=delete&id='); ?>' + contact.id + '&_wpnonce=<?php echo wp_create_nonce('delete_contact'); ?>" class="button button-small" onclick="return confirm(\'¿Estás seguro de eliminar este contacto?\')">Eliminar</a>';
                    html += '</td>';
                    html += '</tr>';
                });
            }
            
            tbody.innerHTML = html;
            
            // Actualizar información de resultados
            var searchInfo = document.getElementById('search-results-info');
            var searchCount = document.getElementById('search-count');
            if (searchInfo && searchCount) {
                searchCount.textContent = contacts.length;
                searchInfo.style.display = searchTerm ? 'block' : 'none';
            }
        }
        
        // Función para actualizar la tabla de clientes con resultados de búsqueda
        function updateClientsTable(clients, searchTerm) {
            var tbody = document.querySelector('#clients-table tbody');
            if (!tbody) return;
            
            var html = '';
            
            if (clients.length === 0) {
                html = '<tr><td colspan="10" style="text-align: center; padding: 20px;">No se encontraron clientes que coincidan con la búsqueda.</td></tr>';
            } else {
                clients.forEach(function(client) {
                    var statusOptions = {
                        'active': '✅ Activo',
                        'completed': '🎉 Completado',
                        'paused': '⏸️ Pausado',
                        'cancelled': '❌ Cancelado'
                    };
                    
                    html += '<tr>';
                    html += '<td>' + client.id + '</td>';
                    html += '<td><strong>' + escapeHtml(client.name) + '</strong></td>';
                    html += '<td><a href="mailto:' + escapeHtml(client.email) + '">' + escapeHtml(client.email) + '</a></td>';
                    html += '<td>' + escapeHtml(client.company || '') + '</td>';
                    html += '<td>' + escapeHtml(client.phone || '') + '</td>';
                    
                    // Valor del contrato
                    html += '<td>';
                    if (client.contract_value > 0) {
                        html += '$' + Number(client.contract_value).toLocaleString('es-CL') + ' CLP';
                    } else {
                        html += '<span style="color: #999;">Sin definir</span>';
                    }
                    html += '</td>';
                    
                    html += '<td>' + escapeHtml(client.project_type || 'No especificado') + '</td>';
                    
                    // Estado selector  
                    html += '<td><select onchange="updateClientStatus(' + client.id + ', this.value)" class="client-status-selector" data-original-value="' + client.contract_status + '">';
                    Object.keys(statusOptions).forEach(function(key) {
                        html += '<option value="' + key + '"' + (client.contract_status === key ? ' selected' : '') + '>' + statusOptions[key] + '</option>';
                    });
                    html += '</select></td>';
                    
                    html += '<td>' + formatDate(client.contracted_at) + '</td>';
                    
                    // Acciones
                    html += '<td><div class="client-actions">';
                    html += '<button onclick="toggleClientStatus(' + client.id + ', \'' + client.contract_status + '\')" class="button button-small toggle-status-btn ' + (client.contract_status === 'active' ? 'status-active' : 'status-inactive') + '" title="' + (client.contract_status === 'active' ? 'Desactivar cliente' : 'Activar cliente') + '">' + (client.contract_status === 'active' ? '🟢' : '🔴') + '</button>';
                    html += '<a href="#" onclick="showClientDetailsModal(' + client.id + ')" class="button button-small view-client-btn" style="background: linear-gradient(135deg, #0073aa, #005a87); color: white; margin-right: 3px;" title="Ver detalles completos del cliente">👁️ Ver Detalles</a>';
                    html += '<a href="#" onclick="editClient(' + client.id + ', this)" class="button button-small edit-client-btn" style="background: linear-gradient(135deg, #72aee6, #2271b1); color: white; border: none; font-weight: 600; padding: 6px 12px; border-radius: 20px; text-decoration: none; transition: all 0.3s; box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3);" data-client-id="' + client.id + '" data-client-name="' + escapeHtml(client.name) + '" data-client-email="' + escapeHtml(client.email) + '" data-client-company="' + escapeHtml(client.company || '') + '" data-client-phone="' + escapeHtml(client.phone || '') + '" data-client-value="' + client.contract_value + '" data-client-type="' + escapeHtml(client.project_type || '') + '" data-client-status="' + client.contract_status + '" data-client-notes="' + escapeHtml(client.notes || '') + '" title="Editar datos del cliente">✏️ Editar Datos</a>';
                    html += '<a href="<?php echo admin_url('admin.php?page=automatiza-tech-clients&action=delete_client&id='); ?>' + client.id + '&_wpnonce=<?php echo wp_create_nonce('delete_client'); ?>" class="button button-small delete-client-btn" onclick="return confirmDeleteClient(this)">🗑️ Eliminar</a>';
                    html += '</div></td>';
                    html += '</tr>';
                });
            }
            
            tbody.innerHTML = html;
            
            // Actualizar información de resultados
            var searchInfo = document.getElementById('client-search-results-info');
            var searchCount = document.getElementById('client-search-count');
            if (searchInfo && searchCount) {
                searchCount.textContent = clients.length;
                searchInfo.style.display = searchTerm ? 'block' : 'none';
            }
        }
        
        // Funciones auxiliares
        function escapeHtml(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
        }
        
        function formatDate(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('es-CL') + ' ' + date.toLocaleTimeString('es-CL', {hour: '2-digit', minute:'2-digit'});
        }
        
        // Función original para compatibilidad
        function showDetails(id) {
            showContactDetails(id);
        }
        
        // Cerrar modal
        document.querySelector('.contact-modal-close').onclick = function() {
            document.getElementById('contact-modal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('contact-modal')) {
                document.getElementById('contact-modal').style.display = 'none';
            }
            if (event.target == document.getElementById('delete-all-modal')) {
                cancelDeleteAll();
            }
        }
        
        // Función para editar contacto (solo administradores)
        function editContact(id, element) {
            // Obtener datos del contacto desde los atributos data
            var contactData = {
                id: element.getAttribute('data-contact-id'),
                name: element.getAttribute('data-contact-name'),
                email: element.getAttribute('data-contact-email'),
                company: element.getAttribute('data-contact-company'),
                phone: element.getAttribute('data-contact-phone'),
                message: element.getAttribute('data-contact-message')
            };
            
            // Crear modal de edición
            showEditContactModal(contactData);
        }
        
        function showEditContactModal(contactData) {
            // Crear el modal HTML
            var modalHTML = `
                <div id="edit-contact-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center;">
                    <div style="background-color: #fefefe; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 3px solid #d63384; padding-bottom: 15px;">
                            <h2 style="margin: 0; color: #d63384; font-size: 24px;">✏️ Editar Contacto (Solo Admin)</h2>
                            <span onclick="closeEditContactModal()" style="cursor: pointer; font-size: 32px; font-weight: bold; color: #999; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#999'">&times;</span>
                        </div>
                        
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                            <p style="margin: 0; color: #664d03; font-weight: 600;">⚠️ Advertencia: Solo los administradores pueden editar los datos de contacto. Los cambios se aplicarán inmediatamente.</p>
                        </div>
                        
                        <form id="edit-contact-form" method="get" action="<?php echo admin_url('admin.php?page=automatiza-tech-contacts'); ?>">
                            <input type="hidden" name="page" value="automatiza-tech-contacts">
                            <input type="hidden" name="action" value="edit_contact">
                            <input type="hidden" name="id" value="${contactData.id}">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('edit_contact'); ?>">
                            
                            <div style="background: #fff; padding: 25px; border-radius: 10px; border: 1px solid #e3e6f0;">
                                <h3 style="margin: 0 0 20px 0; color: #d63384; font-size: 18px; border-bottom: 2px solid #e3e6f0; padding-bottom: 10px;">📝 Datos del Contacto</h3>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                    <div>
                                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #d63384; font-size: 14px;">👤 Nombre Completo *</label>
                                        <input type="text" name="name" value="${contactData.name || ''}" required
                                               style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                                               placeholder="Nombre y apellido del contacto"
                                               onfocus="this.style.borderColor='#d63384'"
                                               onblur="this.style.borderColor='#e3e6f0'">
                                        <small style="color: #666; font-style: italic;">💡 Campo obligatorio</small>
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #d63384; font-size: 14px;">📧 Email *</label>
                                        <input type="email" name="email" value="${contactData.email || ''}" required
                                               style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                                               placeholder="correo@ejemplo.com"
                                               onfocus="this.style.borderColor='#d63384'"
                                               onblur="this.style.borderColor='#e3e6f0'">
                                        <small style="color: #666; font-style: italic;">💡 Campo obligatorio</small>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                    <div>
                                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #d63384; font-size: 14px;">🏢 Empresa</label>
                                        <input type="text" name="company" value="${contactData.company || ''}"
                                               style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                                               placeholder="Nombre de la empresa"
                                               onfocus="this.style.borderColor='#d63384'"
                                               onblur="this.style.borderColor='#e3e6f0'">
                                        <small style="color: #666; font-style: italic;">💡 Campo opcional</small>
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #d63384; font-size: 14px;">📱 Teléfono</label>
                                        <input type="text" name="phone" value="${contactData.phone || ''}"
                                               style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                                               placeholder="+56 9 1234 5678"
                                               onfocus="this.style.borderColor='#d63384'"
                                               onblur="this.style.borderColor='#e3e6f0'">
                                        <small style="color: #666; font-style: italic;">💡 Incluye código de país si es internacional</small>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 25px;">
                                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #d63384; font-size: 14px;">💬 Mensaje del Contacto</label>
                                    <textarea name="message" rows="5"
                                              style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; resize: vertical; font-size: 14px; transition: border-color 0.3s; font-family: inherit;"
                                              placeholder="Mensaje original o actualizado del contacto..."
                                              onfocus="this.style.borderColor='#d63384'"
                                              onblur="this.style.borderColor='#e3e6f0'">${contactData.message || ''}</textarea>
                                    <small style="color: #666; font-style: italic;">💡 El mensaje completo que envió el cliente</small>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: center; gap: 15px; border-top: 2px solid #e3e6f0; padding-top: 25px; margin-top: 20px;">
                                <button type="button" onclick="closeEditContactModal()" 
                                        style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 30px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 10px rgba(108, 117, 125, 0.3);"
                                        onmouseover="this.style.background='#5a6268'; this.style.transform='translateY(-2px)'"
                                        onmouseout="this.style.background='#6c757d'; this.style.transform='translateY(0)'">
                                    ❌ Cancelar
                                </button>
                                <button type="submit" 
                                        style="background: linear-gradient(135deg, #d63384, #c02456); color: white; padding: 12px 25px; border: none; border-radius: 30px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 15px rgba(214, 51, 132, 0.4);"
                                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(214, 51, 132, 0.6)'"
                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(214, 51, 132, 0.4)'">
                                    💾 Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Agregar el modal al DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        function closeEditContactModal() {
            var modal = document.getElementById('edit-contact-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Variables globales para el modal de eliminación
        var deleteAllUrl = '';
        
        function showDeleteAllConfirmation(url, contactCount) {
            deleteAllUrl = url;
            document.getElementById('contact-count-display').textContent = contactCount;
            document.getElementById('delete-confirmation').value = '';
            document.getElementById('confirm-delete-btn').disabled = true;
            document.getElementById('delete-all-modal').style.display = 'block';
            
            // Focus en el input de confirmación
            setTimeout(function() {
                document.getElementById('delete-confirmation').focus();
            }, 300);
        }
        
        function cancelDeleteAll() {
            document.getElementById('delete-all-modal').style.display = 'none';
            document.getElementById('delete-confirmation').value = '';
            deleteAllUrl = '';
        }
        
        function confirmDeleteAll() {
            if (deleteAllUrl) {
                // Mostrar loading en el botón
                var btn = document.getElementById('confirm-delete-btn');
                btn.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Eliminando...';
                btn.disabled = true;
                
                // Redireccionar después de un breve delay para mostrar el loading
                setTimeout(function() {
                    window.location.href = deleteAllUrl;
                }, 500);
            }
        }
        
        // Validar input de confirmación en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            var confirmInput = document.getElementById('delete-confirmation');
            var confirmBtn = document.getElementById('confirm-delete-btn');
            
            if (confirmInput) {
                confirmInput.addEventListener('input', function() {
                    if (this.value === 'ELIMINAR') {
                        confirmBtn.disabled = false;
                        confirmBtn.style.backgroundColor = '#dc3232';
                        confirmBtn.style.borderColor = '#dc3232';
                    } else {
                        confirmBtn.disabled = true;
                        confirmBtn.style.backgroundColor = '#999';
                        confirmBtn.style.borderColor = '#999';
                    }
                });
                
                // Enter key para confirmar
                confirmInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && this.value === 'ELIMINAR') {
                        confirmDeleteAll();
                    }
                    if (e.key === 'Escape') {
                        cancelDeleteAll();
                    }
                });
            }
            
            // Cerrar modal de edición de contacto con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeEditContactModal();
                    closeContactDetailsModal();
                    closeClientDetailsModal();
                }
            });
            
            // Event listeners para búsqueda de contactos
            var contactSearchInput = document.getElementById('contact-search');
            var contactSearchTimeout;
            
            if (contactSearchInput) {
                contactSearchInput.addEventListener('input', function() {
                    clearTimeout(contactSearchTimeout);
                    var searchTerm = this.value.trim();
                    
                    // Búsqueda con debounce de 300ms
                    contactSearchTimeout = setTimeout(function() {
                        searchContacts(searchTerm);
                    }, 300);
                });
            }
            
            // Event listener para limpiar búsqueda de contactos
            var clearContactSearch = document.getElementById('clear-search');
            if (clearContactSearch) {
                clearContactSearch.addEventListener('click', function() {
                    var searchInput = document.getElementById('contact-search');
                    if (searchInput) {
                        searchInput.value = '';
                        searchContacts('');
                    }
                });
            }
            
            // Event listeners para búsqueda de clientes
            var clientSearchInput = document.getElementById('client-search');
            var clientSearchTimeout;
            
            if (clientSearchInput) {
                clientSearchInput.addEventListener('input', function() {
                    clearTimeout(clientSearchTimeout);
                    var searchTerm = this.value.trim();
                    
                    // Búsqueda con debounce de 300ms
                    clientSearchTimeout = setTimeout(function() {
                        searchClients(searchTerm);
                    }, 300);
                });
            }
            
            // Event listener para limpiar búsqueda de clientes
            var clearClientSearch = document.getElementById('clear-client-search');
            if (clearClientSearch) {
                clearClientSearch.addEventListener('click', function() {
                    var searchInput = document.getElementById('client-search');
                    if (searchInput) {
                        searchInput.value = '';
                        searchClients('');
                    }
                });
            }
        });
        
        // Animación de spin para el loading
        var style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        </script>
        <?php
    }
    
    /**
     * Página de administración de clientes
     */
    public function clients_page() {
        global $wpdb;
        
        // Manejar acciones para clientes
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'update_client':
                    if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'update_client')) {
                        $client_id = intval($_GET['id']);
                        $contract_value = floatval($_GET['contract_value'] ?? 0);
                        $project_type = sanitize_text_field($_GET['project_type'] ?? '');
                        $contract_status = sanitize_text_field($_GET['contract_status'] ?? 'active');
                        $notes = sanitize_textarea_field($_GET['notes'] ?? '');
                        
                        $wpdb->update(
                            $this->clients_table_name,
                            array(
                                'contract_value' => $contract_value,
                                'project_type' => $project_type,
                                'contract_status' => $contract_status,
                                'notes' => $notes
                            ),
                            array('id' => $client_id),
                            array('%f', '%s', '%s', '%s'),
                            array('%d')
                        );
                        echo '<div class="notice notice-success"><p>Cliente actualizado correctamente.</p></div>';
                    }
                    break;
                case 'delete_client':
                    if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_client')) {
                        $wpdb->delete($this->clients_table_name, array('id' => intval($_GET['id'])), array('%d'));
                        echo '<div class="notice notice-success"><p>Cliente eliminado correctamente.</p></div>';
                    }
                    break;
                case 'update_client_status':
                    if (isset($_GET['id']) && isset($_GET['status']) && wp_verify_nonce($_GET['_wpnonce'], 'update_client_status')) {
                        $client_id = intval($_GET['id']);
                        $new_status = sanitize_text_field($_GET['status']);
                        
                        // Validar que el estado sea válido
                        $valid_statuses = array('active', 'completed', 'paused', 'cancelled');
                        if (in_array($new_status, $valid_statuses)) {
                            $result = $wpdb->update(
                                $this->clients_table_name,
                                array('contract_status' => $new_status),
                                array('id' => $client_id),
                                array('%s'),
                                array('%d')
                            );
                            
                            if ($result !== false) {
                                $status_names = array(
                                    'active' => 'Activo',
                                    'completed' => 'Completado',
                                    'paused' => 'Pausado',
                                    'cancelled' => 'Cancelado'
                                );
                                echo '<div class="notice notice-success"><p>Estado del cliente actualizado a: <strong>' . $status_names[$new_status] . '</strong></p></div>';
                            } else {
                                echo '<div class="notice notice-error"><p>Error al actualizar el estado del cliente.</p></div>';
                            }
                        } else {
                            echo '<div class="notice notice-error"><p>Estado no válido.</p></div>';
                        }
                    }
                    break;
                case 'toggle_client_status':
                    if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'toggle_client_status')) {
                        $client_id = intval($_GET['id']);
                        
                        // Obtener el estado actual del cliente
                        $current_status = $wpdb->get_var($wpdb->prepare(
                            "SELECT contract_status FROM {$this->clients_table_name} WHERE id = %d",
                            $client_id
                        ));
                        
                        // Determinar el nuevo estado (toggle entre active y paused)
                        $new_status = ($current_status === 'active') ? 'paused' : 'active';
                        
                        $result = $wpdb->update(
                            $this->clients_table_name,
                            array('contract_status' => $new_status),
                            array('id' => $client_id),
                            array('%s'),
                            array('%d')
                        );
                        
                        if ($result !== false) {
                            $action_text = ($new_status === 'active') ? 'activado' : 'desactivado';
                            echo '<div class="notice notice-success"><p>Cliente <strong>' . $action_text . '</strong> correctamente.</p></div>';
                        } else {
                            echo '<div class="notice notice-error"><p>Error al cambiar el estado del cliente.</p></div>';
                        }
                    }
                    break;
                
                case 'update_client':
                    if (isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'update_client')) {
                        $client_id = intval($_GET['id']);
                        $contract_value = isset($_GET['contract_value']) ? floatval($_GET['contract_value']) : 0;
                        $contract_status = isset($_GET['contract_status']) ? sanitize_text_field($_GET['contract_status']) : 'active';
                        $project_type = isset($_GET['project_type']) ? sanitize_text_field($_GET['project_type']) : '';
                        $notes = isset($_GET['notes']) ? sanitize_textarea_field($_GET['notes']) : '';
                        
                        // Validar estado
                        $valid_statuses = array('active', 'completed', 'paused', 'cancelled');
                        if (!in_array($contract_status, $valid_statuses)) {
                            $contract_status = 'active';
                        }
                        
                        $result = $wpdb->update(
                            $this->clients_table_name,
                            array(
                                'contract_value' => $contract_value,
                                'contract_status' => $contract_status,
                                'project_type' => $project_type,
                                'notes' => $notes,
                                'updated_at' => current_time('mysql')
                            ),
                            array('id' => $client_id),
                            array('%f', '%s', '%s', '%s', '%s'),
                            array('%d')
                        );
                        
                        if ($result !== false) {
                            echo '<div class="notice notice-success"><p>✅ Cliente actualizado exitosamente.</p></div>';
                        } else {
                            echo '<div class="notice notice-error"><p>❌ Error al actualizar el cliente.</p></div>';
                        }
                    }
                    break;
            }
        }
        
        // Obtener clientes
        $clients = $wpdb->get_results("SELECT * FROM {$this->clients_table_name} ORDER BY contracted_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Clientes Contratados - Automatiza Tech</h1>
            
            <?php
            $current_user = wp_get_current_user();
            $is_main_admin = $this->is_main_admin();
            ?>
            
            <div class="notice notice-info" style="margin-top: 15px;">
                <p>
                    <strong>👤 Usuario actual:</strong> <?php echo esc_html($current_user->display_name); ?> (<?php echo esc_html($current_user->user_login); ?>)
                    <span style="margin-left: 15px;">
                        <strong>📊 Total de clientes:</strong> <?php echo count($clients); ?>
                    </span>
                </p>
            </div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo admin_url('admin.php?page=automatiza-tech-contacts'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-arrow-left-alt2"></span> Volver a Contactos
                    </a>
                </div>
                <div class="alignright">
                    <span class="displaying-num"><?php echo count($clients); ?> clientes</span>
                </div>
            </div>
            
            <!-- Campo de búsqueda asíncrona para clientes -->
            <div class="search-box" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e3e6f0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-search" style="color: #d63384; font-size: 20px;"></span>
                    <input type="text" id="client-search" placeholder="🔍 Buscar clientes por nombre, email, empresa, teléfono, proyecto o notas..." 
                           style="flex: 1; padding: 10px 15px; border: 2px solid #e3e6f0; border-radius: 25px; font-size: 14px; transition: border-color 0.3s;"
                           onfocus="this.style.borderColor='#d63384'"
                           onblur="this.style.borderColor='#e3e6f0'">
                    <button type="button" id="clear-client-search" class="button button-secondary" style="border-radius: 20px; padding: 8px 15px;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 16px;"></span> Limpiar
                    </button>
                </div>
                <div id="client-search-results-info" style="margin-top: 10px; font-size: 13px; color: #666; display: none;">
                    <span class="dashicons dashicons-info" style="font-size: 14px; margin-right: 5px;"></span>
                    <span id="client-search-count">0</span> resultados encontrados
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped" id="clients-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Empresa</th>
                        <th>Teléfono</th>
                        <th>Valor Contrato</th>
                        <th>Tipo Proyecto</th>
                        <th style="width: 120px;">Estado</th>
                        <th style="width: 130px;">Fecha Contrato</th>
                        <th style="text-align: center; width: 70px;">🔄 Toggle</th>
                        <th style="text-align: center; width: 70px;">👁️ Ver</th>
                        <?php if (current_user_can('administrator')): ?>
                        <th style="text-align: center; width: 70px;">✏️ Editar</th>
                        <?php else: ?>
                        <th style="text-align: center; width: 70px;">🚫 Editar</th>
                        <?php endif; ?>
                        <th style="text-align: center; width: 70px;">🗑️ Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 20px;">
                                <div style="color: #666;">
                                    <span class="dashicons dashicons-businessman" style="font-size: 48px; margin-bottom: 10px;"></span>
                                    <p><strong>No hay clientes contratados aún.</strong></p>
                                    <p>Cuando cambies el estado de un contacto a "Contratado", aparecerá aquí automáticamente.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?php echo $client->id; ?></td>
                                <td><strong><?php echo esc_html($client->name); ?></strong></td>
                                <td><a href="mailto:<?php echo esc_attr($client->email); ?>"><?php echo esc_html($client->email); ?></a></td>
                                <td><?php echo esc_html($client->company); ?></td>
                                <td><?php echo esc_html($client->phone); ?></td>
                                <td>
                                    <?php if ($client->contract_value > 0): ?>
                                        <strong>$<?php echo number_format($client->contract_value, 2); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #999;">Sin definir</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($client->project_type ?: 'No especificado'); ?></td>
                                <td style="width: 120px; min-width: 120px;">
                                    <select onchange="updateClientStatus(<?php echo $client->id; ?>, this.value)" 
                                            class="client-status-selector" 
                                            data-original-value="<?php echo esc_attr($client->contract_status); ?>"
                                            style="width: 100%; max-width: 110px; font-size: 12px; padding: 2px;">
                                        <option value="active" <?php selected($client->contract_status, 'active'); ?>>✅ Activo</option>
                                        <option value="completed" <?php selected($client->contract_status, 'completed'); ?>>🎉 Completado</option>
                                        <option value="paused" <?php selected($client->contract_status, 'paused'); ?>>⏸️ Pausado</option>
                                        <option value="cancelled" <?php selected($client->contract_status, 'cancelled'); ?>>❌ Cancelado</option>
                                    </select>
                                </td>
                                <td style="width: 130px; min-width: 130px; white-space: nowrap;">
                                    <small><?php echo date('d/m/Y H:i', strtotime($client->contracted_at)); ?></small>
                                </td>
                                
                                <!-- Toggle Activo/Inactivo -->
                                <td style="text-align: center;">
                                    <button onclick="toggleClientStatus(<?php echo $client->id; ?>, '<?php echo $client->contract_status; ?>')" 
                                            class="button button-small toggle-status-btn <?php echo ($client->contract_status === 'active') ? 'status-active' : 'status-inactive'; ?>"
                                            style="background: linear-gradient(135deg, <?php echo ($client->contract_status === 'active') ? '#46b450, #00a32a' : '#dc3232, #c32d2d'; ?>); color: white; border: none; padding: 4px 8px; border-radius: 15px; font-size: 16px;"
                                            title="<?php echo ($client->contract_status === 'active') ? 'Desactivar cliente' : 'Activar cliente'; ?>">
                                        <?php echo ($client->contract_status === 'active') ? '🟢' : '🔴'; ?>
                                    </button>
                                </td>
                                
                                <!-- Ver Detalles -->
                                <td style="text-align: center;">
                                    <a href="#" onclick="showClientDetailsModal(<?php echo $client->id; ?>)" 
                                       class="button button-small view-client-btn"
                                       style="background: linear-gradient(135deg, #0073aa, #005a87); color: white; border: none; padding: 4px 8px; border-radius: 15px; font-size: 16px;"
                                       title="Ver detalles completos del cliente">
                                       👁️
                                    </a>
                                </td>
                                
                                <!-- Editar (solo para administradores) -->
                                <?php if (current_user_can('administrator')): ?>
                                <td style="text-align: center;">
                                    <a href="#" onclick="editClient(<?php echo $client->id; ?>, this)" 
                                       class="button button-small edit-client-btn"
                                       style="background: linear-gradient(135deg, #72aee6, #2271b1); color: white; border: none; padding: 4px 8px; border-radius: 15px; font-size: 16px;"
                                       data-client-id="<?php echo $client->id; ?>"
                                       data-client-name="<?php echo esc_attr($client->name); ?>"
                                       data-client-email="<?php echo esc_attr($client->email); ?>"
                                       data-client-company="<?php echo esc_attr($client->company); ?>"
                                       data-client-phone="<?php echo esc_attr($client->phone); ?>"
                                       data-client-value="<?php echo $client->contract_value; ?>"
                                       data-client-type="<?php echo esc_attr($client->project_type); ?>"
                                       data-client-status="<?php echo $client->contract_status; ?>"
                                       data-client-notes="<?php echo esc_attr($client->notes); ?>"
                                       title="Editar datos del cliente">
                                       ✏️
                                    </a>
                                </td>
                                <?php else: ?>
                                <td style="text-align: center; color: #999;">
                                    <span title="Sin permisos">🚫</span>
                                </td>
                                <?php endif; ?>
                                
                                <!-- Eliminar -->
                                <td style="text-align: center;">
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=automatiza-tech-clients&action=delete_client&id=' . $client->id), 'delete_client'); ?>" 
                                       class="button button-small delete-client-btn" 
                                       style="background: linear-gradient(135deg, #dc3232, #c32d2d); color: white; border: none; padding: 4px 8px; border-radius: 15px; font-size: 16px;"
                                       data-client-id="<?php echo $client->id; ?>"
                                       data-client-name="<?php echo esc_attr($client->name); ?>"
                                       data-client-email="<?php echo esc_attr($client->email); ?>"
                                       data-client-company="<?php echo esc_attr($client->company); ?>"
                                       data-client-value="<?php echo $client->contract_value; ?>"
                                       data-client-status="<?php echo $client->contract_status; ?>"
                                       onclick="return confirmDeleteClient(this)"
                                       title="Eliminar cliente">
                                       🗑️
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d1edff;
            color: #0073aa;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-paused {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Estilos para botón de eliminar cliente */
        .delete-client-btn {
            background-color: #dc3545 !important;
            color: white !important;
            border-color: #dc3545 !important;
            transition: all 0.3s ease;
        }
        
        .delete-client-btn:hover {
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }
        
        .delete-client-btn:disabled {
            opacity: 0.7;
            cursor: wait;
            transform: none;
        }
        
        /* Efecto visual para fila en proceso de eliminación */
        .deleting-row {
            background-color: #ffe6e6 !important;
            opacity: 0.7;
            transition: all 0.5s ease;
        }
        
        .deleting-row td {
            color: #666 !important;
        }
        </style>
        
        <script>
        function showClientDetails(id) {
            // TODO: Implementar modal de detalles del cliente
            alert('Funcionalidad de detalles del cliente - ID: ' + id);
        }
        
        function editClient(id, element) {
            // Obtener datos del cliente desde los atributos data
            var clientData = {
                id: element.getAttribute('data-client-id'),
                name: element.getAttribute('data-client-name'),
                email: element.getAttribute('data-client-email'),
                company: element.getAttribute('data-client-company'),
                phone: element.getAttribute('data-client-phone'),
                value: element.getAttribute('data-client-value'),
                type: element.getAttribute('data-client-type'),
                status: element.getAttribute('data-client-status'),
                notes: element.getAttribute('data-client-notes')
            };
            
            // Crear modal de edición
            showEditClientModal(clientData);
        }
        
        function updateClientStatus(clientId, newStatus) {
            var statusNames = {
                'active': 'Activo',
                'completed': 'Completado',
                'paused': 'Pausado',
                'cancelled': 'Cancelado'
            };
            
            var confirmMessage = '¿Confirmas cambiar el estado del cliente a: ' + statusNames[newStatus] + '?';
            
            if (confirm(confirmMessage)) {
                // Cambiar el selector para mostrar procesamiento
                var selector = event.target;
                selector.disabled = true;
                selector.style.background = '#ffc107';
                
                // Redirigir para actualizar el estado
                window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-clients&action=update_client_status'); ?>&id=' + clientId + '&status=' + newStatus + '&_wpnonce=<?php echo wp_create_nonce('update_client_status'); ?>';
            } else {
                // Revertir el selector si se cancela
                event.target.value = event.target.getAttribute('data-original-value') || 'active';
            }
        }
        
        function toggleClientStatus(clientId, currentStatus) {
            var newStatus = (currentStatus === 'active') ? 'paused' : 'active';
            var action = (newStatus === 'active') ? 'activar' : 'desactivar';
            var confirmMessage = '¿Confirmas ' + action + ' este cliente?';
            
            if (confirm(confirmMessage)) {
                // Cambiar el botón para mostrar procesamiento
                var button = event.target;
                button.disabled = true;
                button.innerHTML = '⏳';
                button.style.background = '#ffc107';
                
                // Redirigir para hacer el toggle
                window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-clients&action=toggle_client_status'); ?>&id=' + clientId + '&_wpnonce=<?php echo wp_create_nonce('toggle_client_status'); ?>';
            }
        }
        
        function showEditClientModal(clientData) {
            // Crear el modal HTML
            var modalHTML = `
                <div id="edit-client-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center;">
                    <div style="background-color: #fefefe; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 3px solid #1e3a8a; padding-bottom: 15px;">
                            <h2 style="margin: 0; color: #1e3a8a; font-size: 24px;">✏️ Editar Datos del Cliente</h2>
                            <span onclick="closeEditModal()" style="cursor: pointer; font-size: 32px; font-weight: bold; color: #999; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c'" onmouseout="this.style.color='#999'">&times;</span>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1e3a8a;">
                            <h3 style="margin: 0 0 10px 0; color: #1e3a8a; font-size: 16px;">📋 Información del Cliente</h3>
                            <p style="margin: 5px 0; color: #555;"><strong>Cliente:</strong> ${clientData.name}</p>
                            <p style="margin: 5px 0; color: #555;"><strong>Email:</strong> ${clientData.email}</p>
                            <p style="margin: 5px 0; color: #555;"><strong>Empresa:</strong> ${clientData.company || 'No especificada'}</p>
                            <p style="margin: 5px 0; color: #555;"><strong>Teléfono:</strong> ${clientData.phone}</p>
                        </div>
                        
                        <form id="edit-client-form" method="get" action="<?php echo admin_url('admin.php?page=automatiza-tech-clients'); ?>">
                            <input type="hidden" name="page" value="automatiza-tech-clients">
                            <input type="hidden" name="action" value="update_client">
                            <input type="hidden" name="id" value="${clientData.id}">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('update_client'); ?>">
                            
                            <div style="background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e3e6f0;">
                                <h3 style="margin: 0 0 20px 0; color: #1e3a8a; font-size: 18px; border-bottom: 2px solid #e3e6f0; padding-bottom: 10px;">� Datos Editables del Contrato</h3>
                            
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                    <div>
                                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #1e3a8a; font-size: 14px;">💰 Valor del Contrato (CLP)</label>
                                        <input type="number" name="contract_value" value="${clientData.value || ''}" step="1000" min="0" 
                                               style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                                               placeholder="Ej: 500000"
                                               onfocus="this.style.borderColor='#1e3a8a'"
                                               onblur="this.style.borderColor='#e3e6f0'">
                                        <small style="color: #666; font-style: italic;">💡 Ingresa el monto sin puntos ni comas</small>
                                    </div>
                                    <div>
                                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #1e3a8a; font-size: 14px;">📊 Estado del Contrato</label>
                                        <select name="contract_status" 
                                                style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; background: white; transition: border-color 0.3s;"
                                                onfocus="this.style.borderColor='#1e3a8a'"
                                                onblur="this.style.borderColor='#e3e6f0'">
                                            <option value="active" ${clientData.status === 'active' ? 'selected' : ''}>✅ Activo - En desarrollo</option>
                                            <option value="completed" ${clientData.status === 'completed' ? 'selected' : ''}>🎉 Completado - Proyecto finalizado</option>
                                            <option value="paused" ${clientData.status === 'paused' ? 'selected' : ''}>⏸️ Pausado - Trabajo suspendido</option>
                                            <option value="cancelled" ${clientData.status === 'cancelled' ? 'selected' : ''}>❌ Cancelado - Contrato terminado</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #1e3a8a; font-size: 14px;">🛠️ Tipo de Proyecto</label>
                                    <input type="text" name="project_type" value="${clientData.type || ''}" 
                                           style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s;"
                                           placeholder="Ej: Desarrollo web, E-commerce, Landing page, Sistema personalizado..."
                                           onfocus="this.style.borderColor='#1e3a8a'"
                                           onblur="this.style.borderColor='#e3e6f0'">
                                    <small style="color: #666; font-style: italic;">💡 Describe brevemente el tipo de trabajo a realizar</small>
                                </div>
                                
                                <div style="margin-bottom: 25px;">
                                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #1e3a8a; font-size: 14px;">📝 Notas del Proyecto</label>
                                    <textarea name="notes" rows="4" 
                                              style="width: 100%; padding: 12px; border: 2px solid #e3e6f0; border-radius: 8px; resize: vertical; font-size: 14px; transition: border-color 0.3s; font-family: inherit;"
                                              placeholder="Agrega notas importantes sobre el cliente, requerimientos específicos, fechas importantes, observaciones del proyecto..."
                                              onfocus="this.style.borderColor='#1e3a8a'"
                                              onblur="this.style.borderColor='#e3e6f0'">${clientData.notes || ''}</textarea>
                                    <small style="color: #666; font-style: italic;">💡 Estas notas te ayudarán a recordar detalles importantes del proyecto</small>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: center; gap: 15px; border-top: 2px solid #e3e6f0; padding-top: 25px; margin-top: 20px;">
                                <button type="button" onclick="closeEditModal()" 
                                        style="background: #6c757d; color: white; padding: 12px 25px; border: none; border-radius: 30px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 10px rgba(108, 117, 125, 0.3);"
                                        onmouseover="this.style.background='#5a6268'; this.style.transform='translateY(-2px)'"
                                        onmouseout="this.style.background='#6c757d'; this.style.transform='translateY(0)'">
                                    ❌ Cancelar
                                </button>
                                <button type="button" onclick="previewChanges()" 
                                        style="background: #ffc107; color: #212529; padding: 12px 25px; border: none; border-radius: 30px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 10px rgba(255, 193, 7, 0.3);"
                                        onmouseover="this.style.background='#e0a800'; this.style.transform='translateY(-2px)'"
                                        onmouseout="this.style.background='#ffc107'; this.style.transform='translateY(0)'">
                                    👀 Vista Previa
                                </button>
                                <button type="submit" 
                                        style="background: linear-gradient(135deg, #06d6a0, #059f7f); color: white; padding: 12px 25px; border: none; border-radius: 30px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 4px 15px rgba(6, 214, 160, 0.4);"
                                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(6, 214, 160, 0.6)'"
                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(6, 214, 160, 0.4)'">
                                    💾 Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Agregar el modal al DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        function closeEditModal() {
            var modal = document.getElementById('edit-client-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
        
        function confirmDeleteClient(element) {
            // Obtener información del cliente desde los atributos data
            var clientId = element.getAttribute('data-client-id');
            var clientName = element.getAttribute('data-client-name');
            var clientEmail = element.getAttribute('data-client-email');
            var clientCompany = element.getAttribute('data-client-company');
            var clientValue = parseFloat(element.getAttribute('data-client-value'));
            var clientStatus = element.getAttribute('data-client-status');
            
            // Formatear el valor del contrato
            var formattedValue = clientValue > 0 ? '$' + clientValue.toLocaleString('es-CL') : 'Sin definir';
            
            // Formatear el estado
            var statusNames = {
                'active': '✅ Activo',
                'completed': '🎉 Completado',
                'paused': '⏸️ Pausado',
                'cancelled': '❌ Cancelado'
            };
            var statusText = statusNames[clientStatus] || clientStatus;
            
            // Crear mensaje de confirmación detallado
            var confirmMessage = 
                "🚨 ¿CONFIRMAR ELIMINACIÓN DE CLIENTE?\n\n" +
                "👤 Cliente: " + clientName + "\n" +
                "📧 Email: " + clientEmail + "\n" +
                "🏢 Empresa: " + (clientCompany || 'No especificada') + "\n" +
                "💰 Valor del contrato: " + formattedValue + "\n" +
                "📊 Estado: " + statusText + "\n\n" +
                "⚠️ ADVERTENCIA IMPORTANTE:\n\n" +
                "🗑️ Esta acción ELIMINARÁ PERMANENTEMENTE:\n" +
                "• Todos los datos del cliente\n" +
                "• Historial de contratos\n" +
                "• Información de proyectos\n" +
                "• Notas y comentarios\n" +
                "• Registros de pagos\n\n" +
                "❌ ESTA ACCIÓN NO SE PUEDE DESHACER\n\n" +
                "Si solo quieres desactivar el cliente, cambia su estado a 'Cancelado' en lugar de eliminarlo.\n\n" +
                "¿Estás COMPLETAMENTE SEGURO de que quieres eliminar este cliente?";
            
            // Mostrar confirmación
            if (confirm(confirmMessage)) {
                // Segunda confirmación de seguridad para eliminación
                var secondConfirm = confirm(
                    "⚠️ CONFIRMACIÓN FINAL ⚠️\n\n" +
                    "Estás a punto de eliminar PERMANENTEMENTE a:\n" +
                    "👤 " + clientName + " (" + clientEmail + ")\n\n" +
                    "✅ SÍ - Eliminar permanentemente\n" +
                    "❌ NO - Cancelar eliminación\n\n" +
                    "¿Proceder con la eliminación?"
                );
                
                if (secondConfirm) {
                    // Cambiar el texto del botón para mostrar que está procesando
                    element.innerHTML = '⏳ Eliminando...';
                    element.style.background = '#dc3545';
                    element.style.color = 'white';
                    element.disabled = true;
                    
                    // Agregar efecto visual a la fila
                    var row = element.closest('tr');
                    if (row) {
                        row.style.background = '#ffe6e6';
                        row.style.opacity = '0.7';
                    }
                    
                    // Proceder con la eliminación
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        </script>
        
        <?php
    }
    
    /**
     * Exportar a CSV
     */
    public function export_to_csv() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        global $wpdb;
        
        $contacts = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY submitted_at DESC", ARRAY_A);
        
        if (empty($contacts)) {
            wp_die('No hay contactos para exportar.');
        }
        
        // Limpiar buffer de salida
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        $filename = 'contactos_automatiza_tech_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Configurar headers para descarga
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Crear contenido CSV
        $csv_content = '';
        
        // BOM para UTF-8
        $csv_content .= chr(0xEF) . chr(0xBB) . chr(0xBF);
        
        // Encabezados
        $headers = array(
            'ID',
            'Nombre',
            'Email',
            'Empresa',
            'Teléfono',
            'Mensaje',
            'Fecha de Envío',
            'Estado',
            'Notas'
        );
        
        $csv_content .= '"' . implode('","', $headers) . '"' . "\n";
        
        // Datos
        foreach ($contacts as $contact) {
            $row = array(
                $contact['id'],
                $contact['name'],
                $contact['email'],
                $contact['company'] ?: '',
                $contact['phone'] ?: '',
                str_replace(array("\r", "\n"), ' ', $contact['message']),
                $contact['submitted_at'],
                $contact['status'],
                $contact['notes'] ?: ''
            );
            
            // Escapar comillas dobles y agregar comillas
            $escaped_row = array();
            foreach ($row as $field) {
                $escaped_row[] = '"' . str_replace('"', '""', $field) . '"';
            }
            
            $csv_content .= implode(',', $escaped_row) . "\n";
        }
        
        // Establecer longitud del contenido
        header('Content-Length: ' . strlen($csv_content));
        
        // Enviar contenido
        echo $csv_content;
        
        // Terminar ejecución
        exit;
    }
}

// Inicializar la clase
new AutomatizaTechContactForm();

// Agregar estilos CSS para el sistema de gestión de clientes
add_action('admin_head', function() {
    $current_screen = get_current_screen();
    if ($current_screen && strpos($current_screen->id, 'automatiza-tech-clients') !== false) {
        ?>
        <style>
        .client-actions {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .client-actions select {
            font-size: 11px;
            padding: 2px 5px;
            border-radius: 3px;
            border: 1px solid #ddd;
            min-width: 100px;
        }
        
        .client-actions button {
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .toggle-btn {
            background: #06d6a0;
            color: white;
        }
        
        .toggle-btn:hover {
            background: #059f7f;
        }
        
        .toggle-btn.inactive {
            background: #ffc107;
            color: black;
        }
        
        .toggle-btn.inactive:hover {
            background: #e0a800;
        }
        
        .view-btn {
            background: #0073aa;
            color: white;
        }
        
        .view-btn:hover {
            background: #005a87;
        }
        
        .edit-btn {
            background: #72aee6;
            color: white;
        }
        
        .edit-btn:hover {
            background: #2271b1;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background: #cce7ff;
            color: #004085;
        }
        
        .status-paused {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Responsive para pantallas pequeñas */
        @media (max-width: 768px) {
            .client-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .client-actions select,
            .client-actions button {
                width: 100%;
                margin: 1px 0;
            }
        }
        
        /* Estilos para el modal */
        #edit-client-modal {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        #edit-client-modal h2 {
            color: #1e3a8a;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        #edit-client-modal label {
            color: #1e3a8a;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }
        
        #edit-client-modal input,
        #edit-client-modal select,
        #edit-client-modal textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        #edit-client-modal input:focus,
        #edit-client-modal select:focus,
        #edit-client-modal textarea:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.1);
        }
        
        #edit-client-modal button[type="submit"] {
            background: #06d6a0;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        #edit-client-modal button[type="submit"]:hover {
            background: #059f7f;
        }
        
        #edit-client-modal button[type="button"] {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        #edit-client-modal button[type="button"]:hover {
            background: #545b62;
        }
        </style>
        <?php
    }
});
?>