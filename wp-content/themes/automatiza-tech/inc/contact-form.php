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
        add_action('wp_ajax_filter_contacts', array($this, 'filter_contacts'));
        add_action('wp_ajax_send_email_to_new_contacts', array($this, 'send_email_to_new_contacts'));
        add_action('wp_ajax_get_available_plans', array($this, 'get_available_plans'));
        // Hook de download_invoice movido a invoice-handlers.php para evitar duplicados
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
        
        // Verificar si existe la columna plan_id y agregarla si no existe
        $column_plan = $wpdb->get_results("SHOW COLUMNS FROM {$this->clients_table_name} LIKE 'plan_id'");
        
        if (empty($column_plan)) {
            // Agregar la columna plan_id si no existe
            $wpdb->query("ALTER TABLE {$this->clients_table_name} ADD COLUMN plan_id mediumint(9) AFTER contracted_at");
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
            tax_id varchar(50),
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
            tax_id varchar(50),
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
        $tax_id = $this->validate_and_sanitize_tax_id($_POST['tax_id'] ?? '');
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
        
        if (empty($tax_id)) {
            wp_send_json_error('El RUT/DNI/Pasaporte es obligatorio.');
            wp_die();
        }
        
        // Validar RUT chileno si el teléfono es de Chile
        if (!empty($phone) && strpos($phone, '+56') === 0) {
            if (!$this->validate_chilean_rut($tax_id)) {
                wp_send_json_error('El RUT chileno ingresado no es válido. Por favor verifica el número y el dígito verificador.');
                wp_die();
            }
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
                'tax_id' => $tax_id,
                'message' => $message,
                'submitted_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
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
     * Validar y sanitizar RUT/DNI/Pasaporte
     */
    private function validate_and_sanitize_tax_id($tax_id) {
        // Remover espacios
        $tax_id = trim($tax_id);
        
        // Sanitizar
        $tax_id = sanitize_text_field($tax_id);
        
        // Remover caracteres peligrosos pero mantener guiones, puntos y letras (para RUT, DNI, Pasaportes)
        $tax_id = preg_replace('/[<>"\']/', '', $tax_id);
        
        // Validar longitud (entre 5 y 50 caracteres)
        if (strlen($tax_id) < 5 || strlen($tax_id) > 50) {
            return '';
        }
        
        // Validar que contenga solo números, letras, guiones y puntos
        if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $tax_id)) {
            return '';
        }
        
        return $tax_id;
    }
    
    /**
     * Validar RUT chileno
     */
    private function validate_chilean_rut($rut) {
        // Limpiar el RUT
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        
        if (strlen($rut) < 2) {
            return false;
        }
        
        // Separar cuerpo y dígito verificador
        $body = substr($rut, 0, -1);
        $dv = substr($rut, -1);
        
        // Validar que el cuerpo sea numérico
        if (!is_numeric($body)) {
            return false;
        }
        
        // Validar longitud (7-8 dígitos)
        if (strlen($body) < 7 || strlen($body) > 8) {
            return false;
        }
        
        // Calcular dígito verificador
        $sum = 0;
        $multiplier = 2;
        
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += $body[$i] * $multiplier;
            $multiplier = $multiplier < 7 ? $multiplier + 1 : 2;
        }
        
        $calculated_dv = 11 - ($sum % 11);
        
        if ($calculated_dv == 11) {
            $calculated_dv = '0';
        } elseif ($calculated_dv == 10) {
            $calculated_dv = 'K';
        } else {
            $calculated_dv = (string)$calculated_dv;
        }
        
        return $dv === $calculated_dv;
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
            // América del Sur
            '+54' => array('length' => array(12, 13), 'digits' => array(9, 10)), // Argentina
            '+591' => array('length' => array(11, 12), 'digits' => array(8, 9)), // Bolivia
            '+55' => array('length' => array(12, 13), 'digits' => array(10, 11)), // Brasil
            '+56' => array('length' => 12, 'digits' => 9), // Chile: +56 + 9 dígitos = 12 total
            '+57' => array('length' => 13, 'digits' => 10), // Colombia
            '+593' => array('length' => 12, 'digits' => 9), // Ecuador
            '+594' => array('length' => 12, 'digits' => 9), // Guyana Francesa
            '+592' => array('length' => 11, 'digits' => 7), // Guyana
            '+595' => array('length' => 12, 'digits' => 9), // Paraguay
            '+51' => array('length' => 12, 'digits' => 9), // Perú
            '+597' => array('length' => 11, 'digits' => 7), // Surinam
            '+598' => array('length' => 12, 'digits' => 9), // Uruguay
            '+58' => array('length' => 13, 'digits' => 10), // Venezuela
            
            // América Central y Caribe
            '+501' => array('length' => 11, 'digits' => 7), // Belice
            '+506' => array('length' => 12, 'digits' => 8), // Costa Rica
            '+53' => array('length' => 12, 'digits' => 8), // Cuba
            '+503' => array('length' => 12, 'digits' => 8), // El Salvador
            '+502' => array('length' => 12, 'digits' => 8), // Guatemala
            '+509' => array('length' => 12, 'digits' => 8), // Haití
            '+504' => array('length' => 12, 'digits' => 8), // Honduras
            '+52' => array('length' => 13, 'digits' => 10), // México
            '+505' => array('length' => 12, 'digits' => 8), // Nicaragua
            '+507' => array('length' => 12, 'digits' => 8), // Panamá
            '+1787' => array('length' => 14, 'digits' => 10), // Puerto Rico
            '+1939' => array('length' => 14, 'digits' => 10), // Puerto Rico (alternativo)
            '+1809' => array('length' => 14, 'digits' => 10), // República Dominicana
            '+1829' => array('length' => 14, 'digits' => 10), // República Dominicana (alternativo)
            '+1849' => array('length' => 14, 'digits' => 10), // República Dominicana (alternativo)
            
            // Otros países comunes
            '+34' => array('length' => 12, 'digits' => 9), // España
            '+1' => array('length' => 12, 'digits' => 10), // USA/Canadá
            '+351' => array('length' => 12, 'digits' => 9), // Portugal
            '+44' => array('length' => 13, 'digits' => 10), // Reino Unido
            '+33' => array('length' => 12, 'digits' => 9) // Francia
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
                    // NUEVO: Validar que el primer dígito sea 9 (números móviles chilenos)
                    if ($number_part[0] !== '9') {
                        return ''; // Los números chilenos deben empezar con 9
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
     * Detectar país basado en código telefónico
     */
    private function detect_country_from_phone($phone) {
        if (empty($phone)) {
            return 'CL'; // Por defecto Chile
        }
        
        // Códigos telefónicos internacionales
        $country_codes = array(
            '+56' => 'CL',  // Chile
            '+1'  => 'US',  // USA/Canadá
            '+54' => 'AR',  // Argentina
            '+57' => 'CO',  // Colombia
            '+52' => 'MX',  // México
            '+51' => 'PE',  // Perú
            '+34' => 'ES',  // España
            '+55' => 'BR',  // Brasil
            '+593' => 'EC', // Ecuador
            '+595' => 'PY', // Paraguay
            '+598' => 'UY', // Uruguay
            '+58' => 'VE',  // Venezuela
            '+506' => 'CR', // Costa Rica
            '+507' => 'PA', // Panamá
            '+503' => 'SV', // El Salvador
            '+504' => 'HN', // Honduras
            '+505' => 'NI', // Nicaragua
            '+502' => 'GT', // Guatemala
        );
        
        // Buscar el código más largo primero (para evitar conflictos como +1 vs +1787)
        $codes_by_length = $country_codes;
        uksort($codes_by_length, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($codes_by_length as $code => $country) {
            if (strpos($phone, $code) === 0) {
                return $country;
            }
        }
        
        // Por defecto Chile
        return 'CL';
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
        // Enviar notificación a automatizatech.bots@gmail.com
        $to = 'automatizatech.bots@gmail.com';
        $subject = '📧 Nuevo contacto desde Automatiza Tech - ' . $name;
        
        // Plantilla HTML mejorada para la notificación
        $body = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; background: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: #f5f5f5; padding: 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px;">🆕 Nuevo Contacto</h1>
                                    <p style="color: #f0f0f0; margin: 10px 0 0 0; font-size: 14px;">Automatiza Tech</p>
                                </td>
                            </tr>
                            
                            <!-- Contenido -->
                            <tr>
                                <td style="padding: 30px;">
                                    <div style="background: #f8f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin-bottom: 20px;">
                                        <h2 style="color: #667eea; margin: 0 0 15px 0; font-size: 18px;">👤 Información del Contacto</h2>
                                        
                                        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0; width: 30%;">
                                                    <strong style="color: #667eea;">Nombre:</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    ' . esc_html($name) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    <strong style="color: #667eea;">Email:</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    <a href="mailto:' . esc_attr($email) . '" style="color: #667eea; text-decoration: none;">' . esc_html($email) . '</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    <strong style="color: #667eea;">Empresa:</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    ' . (empty($company) ? '<em style="color: #999;">No especificada</em>' : esc_html($company)) . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    <strong style="color: #667eea;">Teléfono:</strong>
                                                </td>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                                    ' . (empty($phone) ? '<em style="color: #999;">No especificado</em>' : '<a href="tel:' . esc_attr($phone) . '" style="color: #667eea; text-decoration: none;">' . esc_html($phone) . '</a>') . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <strong style="color: #667eea;">Fecha:</strong>
                                                </td>
                                                <td style="padding: 8px 0;">
                                                    ' . current_time('d/m/Y H:i:s') . '
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <div style="background: #fff9f0; padding: 20px; border-radius: 8px; border-left: 4px solid #ff9800; margin-bottom: 20px;">
                                        <h3 style="color: #ff9800; margin: 0 0 10px 0; font-size: 16px;">💬 Mensaje:</h3>
                                        <p style="color: #333; margin: 0; line-height: 1.6; white-space: pre-wrap;">' . esc_html($message) . '</p>
                                    </div>
                                    
                                    <div style="text-align: center; margin-top: 25px;">
                                        <a href="' . admin_url('admin.php?page=automatiza-tech-contacts') . '" style="display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);">
                                            📋 Ver en Panel de Admin
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background: #f8f9ff; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;">
                                    <p style="color: #666; margin: 0; font-size: 12px;">
                                        🌐 Enviado desde: <a href="' . home_url() . '" style="color: #667eea; text-decoration: none;">' . home_url() . '</a>
                                    </p>
                                    <p style="color: #999; margin: 5px 0 0 0; font-size: 11px;">
                                        Este es un mensaje automático del sistema de contacto de Automatiza Tech
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Automatiza Tech <' . get_option('admin_email') . '>',
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
    private function move_to_clients($contact_id, $plan_id = null) {
        global $wpdb;
        
        // Obtener datos del contacto
        $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $contact_id));
        
        if (!$contact) {
            return false;
        }
        
        // Manejar múltiples planes (si vienen separados por comas)
        $plan_ids = array();
        $plans_data = array();
        $contract_value = 0.00;
        $project_types = array();
        
        // DEBUG: Log del plan_id recibido
        error_log("DEBUG move_to_clients: plan_id recibido = " . var_export($plan_id, true));
        
        if ($plan_id) {
            // Si vienen múltiples IDs separados por comas
            if (strpos($plan_id, ',') !== false) {
                $plan_ids = array_map('intval', explode(',', $plan_id));
                error_log("DEBUG: Múltiples planes detectados: " . implode(', ', $plan_ids));
            } else {
                $plan_ids = array(intval($plan_id));
                error_log("DEBUG: Un solo plan detectado: " . $plan_id);
            }
            
            // Obtener datos de todos los planes
            foreach ($plan_ids as $pid) {
                $plan = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}automatiza_services WHERE id = %d",
                    $pid
                ));
                if ($plan) {
                    $plans_data[] = $plan;
                    $contract_value += floatval($plan->price_clp);
                    $project_types[] = $plan->name;
                    error_log("DEBUG: Plan agregado: ID={$pid}, Nombre={$plan->name}, Precio={$plan->price_clp}");
                }
            }
            
            error_log("DEBUG: Total de planes procesados: " . count($plans_data));
        }
        
        // Usar el primer plan como principal (para compatibilidad)
        $plan_data = !empty($plans_data) ? $plans_data[0] : null;
        $plan_id_main = !empty($plan_ids) ? $plan_ids[0] : null;
        $project_type = !empty($project_types) ? implode(' + ', $project_types) : '';
        
        // Detectar país del cliente basado en código telefónico
        $country = $this->detect_country_from_phone($contact->phone);
        
        // Insertar en tabla de clientes
        $result = $wpdb->insert(
            $this->clients_table_name,
            array(
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'company' => $contact->company,
                'phone' => $contact->phone,
                'tax_id' => $contact->tax_id,
                'country' => $country,
                'original_message' => $contact->message,
                'contacted_at' => $contact->submitted_at,
                'contracted_at' => current_time('mysql'),
                'plan_id' => $plan_id_main,
                'contract_value' => $contract_value,
                'project_type' => $project_type,
                'contract_status' => 'active',
                'notes' => $contact->notes
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Obtener el ID del cliente recién creado
            $client_id = $wpdb->insert_id;
            
            // Obtener datos completos del cliente
            $client_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->clients_table_name} WHERE id = %d",
                $client_id
            ));
            
            // Eliminar de tabla de contactos
            $wpdb->delete($this->table_name, array('id' => $contact_id), array('%d'));
            
            // Log de la conversión
            $plans_log = !empty($project_types) ? implode(' + ', $project_types) : 'Sin plan';
            error_log("CLIENTE CONVERTIDO: {$contact->name} ({$contact->email}) movido de contactos a clientes. Plan(es): {$plans_log}");
            
            // Enviar correo de notificación para cliente contratado con factura
            // Pasar todos los planes para la factura
            $this->send_contracted_client_email($client_data, $plans_data);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Generar cotización cuando un contacto pasa a estado "interested"
     * Similar a move_to_clients pero:
     * - NO mueve a tabla de clientes
     * - Genera cotización (no factura)
     * - Número formato: C-AT-YYYYMMDD-XXXX
     * - Validez: 3 días
     */
    private function move_to_interested($contact_id, $plan_id = null) {
        global $wpdb;
        
        // Obtener datos del contacto
        $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $contact_id));
        
        if (!$contact) {
            return false;
        }
        
        // Manejar múltiples planes (si vienen separados por comas)
        $plan_ids = array();
        $plans_data = array();
        $quotation_value = 0.00;
        $plan_types = array();
        
        // DEBUG: Log del plan_id recibido
        error_log("DEBUG move_to_interested: plan_id recibido = " . var_export($plan_id, true));
        
        if ($plan_id) {
            // Si vienen múltiples IDs separados por comas
            if (strpos($plan_id, ',') !== false) {
                $plan_ids = array_map('intval', explode(',', $plan_id));
                error_log("DEBUG: Múltiples planes detectados: " . implode(', ', $plan_ids));
            } else {
                $plan_ids = array(intval($plan_id));
                error_log("DEBUG: Un solo plan detectado: " . $plan_id);
            }
            
            // Obtener datos de todos los planes
            foreach ($plan_ids as $pid) {
                $plan = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}automatiza_services WHERE id = %d",
                    $pid
                ));
                if ($plan) {
                    $plans_data[] = $plan;
                    $quotation_value += floatval($plan->price_clp);
                    $plan_types[] = $plan->name;
                    error_log("DEBUG: Plan agregado: ID={$pid}, Nombre={$plan->name}, Precio={$plan->price_clp}");
                }
            }
            
            error_log("DEBUG: Total de planes procesados: " . count($plans_data));
        }
        
        if (empty($plans_data)) {
            error_log("ERROR move_to_interested: No se encontraron planes válidos");
            return false;
        }
        
        // Log de la generación de cotización
        $plans_log = implode(' + ', $plan_types);
        error_log("COTIZACION GENERADA: {$contact->name} ({$contact->email}) - Plan(es): {$plans_log}");
        
        // Generar y enviar cotización
        $this->send_quotation_email($contact, $plans_data);
        
        // Actualizar estado a "interested" (el contacto NO se mueve a clientes)
        $wpdb->update(
            $this->table_name,
            array('status' => 'interested'),
            array('id' => $contact_id),
            array('%s'),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Enviar correo de notificación cuando un cliente es contratado
     */
    private function send_contracted_client_email($client_data, $plans_data = null) {
        // Configurar SMTP para desarrollo local
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        
        // Enviar correo al cliente con la factura
        if ($plans_data) {
            $this->send_invoice_email_to_client($client_data, $plans_data);
        }
        
        // Email de destino para notificación interna
        $to = 'automatizatech.bots@gmail.com';
        
        // Preparar información de planes para el asunto
        $plans_names = array();
        if (is_array($plans_data)) {
            foreach ($plans_data as $plan) {
                $plans_names[] = $plan->name;
            }
        }
        $plans_text = !empty($plans_names) ? ' - Plan(es): ' . implode(', ', $plans_names) : '';
        
        // Asunto del correo
        $subject = '🎉 ¡Nuevo Cliente Contratado! - ' . $client_data->name . $plans_text;
        
        // Obtener URL del sitio para el encabezado
        $site_url = get_site_url();
        $admin_url = admin_url('admin.php?page=automatiza-tech-clients');
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        
        // Construir HTML de planes contratados
        $plans_html = '';
        if (is_array($plans_data) && !empty($plans_data)) {
            $total_clp = 0;
            $plans_list = '';
            
            foreach ($plans_data as $index => $plan) {
                $plan_num = $index + 1;
                $total_clp += floatval($plan->price_clp);
                
                $plans_list .= "
                <div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 6px; border-left: 3px solid #06d6a0;'>
                    <p style='margin: 5px 0;'><strong style='color: #1e3a8a;'>Plan {$plan_num}:</strong> <span style='font-size: 1.1em;'>" . esc_html($plan->name) . "</span></p>
                    <p style='margin: 5px 0;'><strong style='color: #06d6a0;'>Precio:</strong> <span style='font-weight: bold;'>$" . number_format($plan->price_clp, 0, ',', '.') . " CLP</span></p>
                    " . (!empty($plan->description) ? "<p style='margin: 5px 0; color: #6c757d;'><em>" . esc_html($plan->description) . "</em></p>" : "") . "
                </div>";
            }
            
            $plans_html = "
            <div class='info-box' style='border-left: 4px solid #06d6a0;'>
                <h3 style='color: #06d6a0; margin-top: 0;'>💼 Planes Contratados</h3>
                {$plans_list}
                <div style='margin-top: 15px; padding: 12px; background: #e8f5f1; border-radius: 5px; text-align: center;'>
                    <p style='margin: 5px 0; font-size: 1.2em;'><strong>TOTAL:</strong> <span style='color: #06d6a0; font-size: 1.3em; font-weight: bold;'>$" . number_format($total_clp, 0, ',', '.') . " CLP</span></p>
                </div>
                <p style='margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 5px; text-align: center;'>✉️ <strong>Se ha enviado la factura automáticamente al cliente</strong></p>
            </div>";
        }
        
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
                <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 140px; height: auto; margin-bottom: 10px;'>
                <h1>🎉 ¡Nuevo Cliente Contratado!</h1>
                <p>Se ha convertido un contacto a cliente en AutomatizaTech</p>
            </div>
            
            <div class='content'>
                <div class='info-box'>
                    <h3 style='color: #1e3a8a; margin-top: 0;'>📋 Información del Cliente</h3>
                    <p><span class='label'>Nombre:</span> <span class='value'>" . esc_html($client_data->name) . "</span></p>
                    <p><span class='label'>Email:</span> <span class='value'>" . esc_html($client_data->email) . "</span></p>
                    <p><span class='label'>Empresa:</span> <span class='value'>" . esc_html($client_data->company ?: 'No especificada') . "</span></p>
                    <p><span class='label'>Teléfono:</span> <span class='value'>" . esc_html($client_data->phone ?: 'No especificado') . "</span></p>
                    <p><span class='label'>Contactado:</span> <span class='value'>" . current_time('d/m/Y H:i', strtotime($client_data->contacted_at)) . "</span></p>
                    <p><span class='label'>Contratado:</span> <span class='value'>" . current_time('d/m/Y H:i', strtotime($client_data->contracted_at)) . "</span></p>
                </div>
                
            " . $plans_html . "                " . (!empty($client_data->original_message) ? "
                <div class='message-box'>
                    <h4 style='color: #1976d2; margin-top: 0;'>💬 Mensaje Original</h4>
                    <p>" . nl2br(esc_html($client_data->original_message)) . "</p>
                </div>
                " : "") . "
                
                " . (!empty($client_data->notes) ? "
                <div class='info-box'>
                    <h4 style='color: #1e3a8a; margin-top: 0;'>📝 Notas</h4>
                    <p>" . nl2br(esc_html($client_data->notes)) . "</p>
                </div>
                " : "") . "
                
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='{$admin_url}' class='cta'>👥 Ver Panel de Clientes</a>
                </div>
                
                <div class='footer'>
                    <p>📧 Correo enviado automáticamente desde <strong>AutomatizaTech</strong></p>
                    <p>🌐 <a href='{$site_url}'>{$site_url}</a></p>
                    <p>📅 " . current_time('d/m/Y H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Headers para HTML
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <info@automatizatech.shop>',
            'Reply-To: info@automatizatech.shop'
        );
        
        // Enviar el correo
        $sent = wp_mail($to, $subject, $message, $headers);
        
        // Log del resultado
        if ($sent) {
            error_log("CORREO ENVIADO: Notificación de cliente contratado enviada a {$to} para {$client_data->name} ({$client_data->email})");
        } else {
            error_log("ERROR CORREO: No se pudo enviar notificación de cliente contratado para {$client_data->name} ({$client_data->email})");
            
            // Crear backup del correo en archivo para revisión manual
            $this->save_email_to_file($to, $subject, $message, $client_data);
        }
        
        return $sent;
    }
    
    /**
     * Enviar correo con factura al cliente
     */
    private function send_invoice_email_to_client($client_data, $plans_data) {
        // Configurar SMTP
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        
        // Generar factura HTML (para BD)
        $invoice_html = $this->generate_invoice_html($client_data, $plans_data);
        $invoice_number = 'AT-' . date('Ymd') . '-' . str_pad($client_data->id, 4, '0', STR_PAD_LEFT);
        
        // Guardar factura HTML en archivo (para backup)
        $invoice_html_path = $this->save_invoice_file($invoice_html, $client_data, $invoice_number);
        
        // Generar PDF para adjuntar al correo
        $invoice_pdf_path = $this->generate_and_save_pdf($client_data, $plans_data, $invoice_number);
        
        // Guardar factura en base de datos
        $this->save_invoice_to_database($client_data, $plans_data, $invoice_number, $invoice_html, $invoice_pdf_path);
        
        // Colores de AutomatizaTech (azul-turquesa del logo)
        $primary_color = '#0047AB';
        $secondary_color = '#00CED1';
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        
        // Email del cliente
        $to = $client_data->email;
        
        // ANTI-SPAM: Asunto personalizado sin emojis, con nombre del cliente
        $subject = 'Bienvenido a AutomatizaTech - Factura ' . $invoice_number . ' - ' . $client_data->name;
        
        $site_url = get_site_url();
        
        // Construir el mensaje HTML profesional y amable
        $message = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333;
            background: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .email-header {
            background: {$primary_color};
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: 4px solid {$secondary_color};
        }
        .email-header h1 {
            font-size: 1.8em;
            margin-bottom: 8px;
        }
        .email-header p {
            font-size: 1em;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 1.2em;
            color: {$primary_color};
            margin-bottom: 20px;
            font-weight: 600;
        }
        .message-text {
            margin: 15px 0;
            color: #555;
            line-height: 1.8;
        }
        .plan-highlight {
            background: #f8f9fa;
            border-left: 4px solid {$secondary_color};
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .plan-highlight h3 {
            color: {$primary_color};
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .plan-name {
            font-size: 1.4em;
            color: {$secondary_color};
            font-weight: bold;
            margin: 10px 0;
        }
        .plan-price {
            font-size: 2em;
            color: {$primary_color};
            font-weight: bold;
            margin: 15px 0;
        }
        .invoice-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .invoice-info h4 {
            color: {$primary_color};
            margin-bottom: 10px;
        }
        .invoice-number {
            font-size: 1.3em;
            font-weight: bold;
            color: {$secondary_color};
            margin: 10px 0;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, {$secondary_color}, #05c29a);
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1em;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(6, 214, 160, 0.3);
            transition: transform 0.3s;
        }
        .support-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .support-box h4 {
            color: {$primary_color};
            margin-bottom: 10px;
        }
        .contact-info {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        .email-footer {
            background: {$primary_color};
            color: white;
            padding: 30px;
            text-align: center;
        }
        .email-footer p {
            margin: 8px 0;
            opacity: 0.9;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            color: {$secondary_color};
            text-decoration: none;
            margin: 0 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 160px; height: auto; margin-bottom: 15px;'>
            <h1>Bienvenido a AutomatizaTech</h1>
            <p>Gracias por confiar en nosotros</p>
        </div>
        
        <div class='email-body'>
            <div class='greeting'>
                Hola " . esc_html($client_data->name) . ",
            </div>
            
            <p class='message-text'>
                Gracias por confiar en AutomatizaTech para tu proyecto de transformación digital. 
                <strong>📎 Encontrarás tu factura adjunta en formato PDF</strong> con todos los detalles de tu contratación.
            </p>";
            
        // Generar HTML de planes contratados
        if (is_array($plans_data) && !empty($plans_data)) {
            $total_clp = 0;
            $message .= "<div class='plan-highlight'>
                <h3>" . (count($plans_data) > 1 ? 'Planes Contratados' : 'Plan Contratado') . "</h3>";
            
            foreach ($plans_data as $index => $plan) {
                $total_clp += floatval($plan->price_clp);
                
                if (count($plans_data) > 1) {
                    $message .= "<div style='background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 2px solid {$secondary_color};'>";
                    $message .= "<div style='font-size: 0.9em; color: #6c757d; margin-bottom: 5px;'>Plan " . ($index + 1) . "</div>";
                }
                
                $message .= "<div class='plan-name'>" . esc_html($plan->name) . "</div>";
                $message .= "<div class='plan-price'>$" . number_format($plan->price_clp, 0, ',', '.') . " CLP</div>";
                
                if (!empty($plan->description)) {
                    $message .= "<p class='message-text' style='margin-top: 15px;'>" . esc_html($plan->description) . "</p>";
                }
                
                if (count($plans_data) > 1) {
                    $message .= "</div>";
                }
            }
            
            if (count($plans_data) > 1) {
                $message .= "<div style='margin-top: 20px; padding: 15px; background: {$secondary_color}; color: white; border-radius: 8px; text-align: center;'>
                    <div style='font-size: 1.2em; font-weight: bold;'>TOTAL: $" . number_format($total_clp, 0, ',', '.') . " CLP</div>
                </div>";
            }
            
            $message .= "</div>";
        }
        
        $message .= "            <p class='message-text'>
                📄 <strong>Factura PDF adjunta:</strong> Revisa el archivo adjunto para ver el detalle completo 
                de tu contratación. Te recomendamos guardar este documento para tus registros contables.
            </p>
            
            <div class='invoice-info'>
                <h4>Información de la Factura</h4>
                <div class='invoice-number'>{$invoice_number}</div>
                <p style='color: #666;'>Fecha: " . date('d/m/Y H:i') . "</p>
            </div>
            
            <p class='message-text'>
                Nuestro equipo se pondrá en contacto contigo en las próximas 24-48 horas para coordinar 
                el inicio de tu proyecto y resolver cualquier consulta que puedas tener.
            </p>
            
            <div class='support-box'>
                <h4>Información de Contacto</h4>
                <p style='color: #666; margin-bottom: 10px;'>Si tienes consultas, puedes contactarnos:</p>
                <div class='contact-info'>
                    Email: <strong>info@automatizatech.shop</strong><br>
                    Teléfono: <strong>+56 9 6432 4169</strong><br>
                    Sitio web: <strong>{$site_url}</strong>
                </div>
            </div>
            
            <p class='message-text' style='margin-top: 20px; color: #666;'>
                Saludos cordiales,<br>
                <strong>Equipo AutomatizaTech</strong>
            </p>
        </div>
        
        <div class='email-footer'>
            <p style='font-size: 1em; margin-bottom: 10px;'><strong>AutomatizaTech</strong></p>
            <p style='font-size: 0.9em;'>Soluciones de automatización digital</p>
            <p style='font-size: 0.85em; margin-top: 15px;'>
                {$site_url} | info@automatizatech.shop<br>
                Copyright " . date('Y') . " AutomatizaTech. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>
</html>";
        
        // ANTI-SPAM: Headers profesionales y transaccionales
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <info@automatizatech.shop>',
            'Reply-To: info@automatizatech.shop',
            'Bcc: automatizatech.bots@gmail.com',
            'X-Priority: 1 (Highest)',
            'X-MSMail-Priority: High',
            'Importance: High',
            'X-Mailer: AutomatizaTech Invoicing System v1.0',
            'List-Unsubscribe: <mailto:unsubscribe@automatizatech.shop>',
            'Precedence: bulk',
            'X-Auto-Response-Suppress: OOF, DR, RN, NRN, AutoReply'
        );
        
        // Adjuntar factura PDF
        $attachments = array();
        if ($invoice_pdf_path && file_exists($invoice_pdf_path)) {
            $attachments = array($invoice_pdf_path);
        }
        
        // ANTI-SPAM: Agregar versión texto plano alternativa
        add_action('phpmailer_init', function($phpmailer) use ($client_data, $plans_data, $invoice_number, $site_url) {
            // Versión texto plano para mejor deliverability
            $plain_text = "Hola " . $client_data->name . ",\n\n";
            $plain_text .= "Gracias por confiar en AutomatizaTech para tu proyecto de transformación digital.\n\n";
            $plain_text .= "** FACTURA PDF ADJUNTA **\n\n";
            $plain_text .= "Encontrarás tu factura en formato PDF adjunta a este correo.\n";
            $plain_text .= "Te recomendamos guardarla para tus registros contables.\n\n";
            
            // Manejar múltiples planes
            if (is_array($plans_data) && !empty($plans_data)) {
                if (count($plans_data) > 1) {
                    $plain_text .= "PLANES CONTRATADOS\n";
                    $plain_text .= "------------------\n";
                    $total_clp = 0;
                    foreach ($plans_data as $index => $plan) {
                        $plan_num = $index + 1;
                        $total_clp += floatval($plan->price_clp);
                        $plain_text .= "Plan {$plan_num}: " . $plan->name . "\n";
                        $plain_text .= "Precio: $" . number_format($plan->price_clp, 0, ',', '.') . " CLP\n\n";
                    }
                    $plain_text .= "TOTAL: $" . number_format($total_clp, 0, ',', '.') . " CLP\n\n";
                } else {
                    // Un solo plan
                    $plan = $plans_data[0];
                    $plain_text .= "PLAN CONTRATADO\n";
                    $plain_text .= "---------------\n";
                    $plain_text .= "Plan: " . $plan->name . "\n";
                    $plain_text .= "Precio: $" . number_format($plan->price_clp, 0, ',', '.') . " CLP\n\n";
                }
            }
            
            $plain_text .= "FACTURA\n";
            $plain_text .= "-------\n";
            $plain_text .= "Número: " . $invoice_number . "\n";
            $plain_text .= "Fecha: " . current_time('d/m/Y H:i') . "\n\n";
            $plain_text .= "Nuestro equipo se pondrá en contacto contigo en las próximas 24-48 horas.\n\n";
            $plain_text .= "INFORMACIÓN DE CONTACTO\n";
            $plain_text .= "-----------------------\n";
            $plain_text .= "Email: info@automatizatech.shop\n";
            $plain_text .= "Teléfono: +56 9 6432 4169\n";
            $plain_text .= "Web: " . $site_url . "\n\n";
            $plain_text .= "Saludos cordiales,\n";
            $plain_text .= "Equipo AutomatizaTech\n";
            
            $phpmailer->AltBody = $plain_text;
        });
        
        // Enviar correo
        $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        
        // Log
        if ($sent) {
            error_log("FACTURA ENVIADA: Factura {$invoice_number} enviada a {$client_data->email} ({$client_data->name})");
        } else {
            error_log("ERROR FACTURA: No se pudo enviar factura {$invoice_number} a {$client_data->email}");
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
        
        $phpmailer->From     = 'info@automatizatech.shop';
        $phpmailer->FromName = 'AutomatizaTech';
        
        // Log de configuración
        error_log("SMTP CONFIGURADO: Host={$phpmailer->Host}, Port={$phpmailer->Port}, Auth=" . ($phpmailer->SMTPAuth ? 'true' : 'false'));
    }
    
    /**
     * Guardar factura en la base de datos
     */
    private function save_invoice_to_database($client_data, $plans_data, $invoice_number, $invoice_html, $invoice_path) {
        global $wpdb;
        
        $invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
        
        // Soportar tanto un solo plan como múltiples planes
        $plans_array = is_array($plans_data) ? $plans_data : array($plans_data);
        
        // Calcular totales sumando todos los planes
        $subtotal = 0;
        $plan_names = array();
        $first_plan_id = null;
        
        foreach ($plans_array as $plan) {
            $subtotal += floatval($plan->price_clp);
            $plan_names[] = $plan->name;
            if ($first_plan_id === null && isset($plan->id)) {
                $first_plan_id = $plan->id;
            }
        }
        
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        // Concatenar nombres de planes
        $all_plan_names = implode(' + ', $plan_names);
        
        // Datos para el QR
        $qr_data = "FACTURA: {$invoice_number}\nCliente: {$client_data->name}\nPlan(es): {$all_plan_names}\nTotal: $" . number_format($total, 0, ',', '.');
        
        // Insertar o actualizar factura
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$invoices_table} WHERE invoice_number = %s",
            $invoice_number
        ));
        
        if ($existing) {
            // Actualizar factura existente
            $wpdb->update(
                $invoices_table,
                [
                    'invoice_html' => $invoice_html,
                    'invoice_file_path' => $invoice_path,
                    'qr_code_data' => $qr_data
                ],
                ['id' => $existing],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insertar nueva factura
            $wpdb->insert(
                $invoices_table,
                [
                    'invoice_number' => $invoice_number,
                    'client_id' => $client_data->id,
                    'client_name' => $client_data->name,
                    'client_email' => $client_data->email,
                    'plan_id' => $first_plan_id,
                    'plan_name' => $all_plan_names,
                    'subtotal' => $subtotal,
                    'iva' => $iva,
                    'total' => $total,
                    'invoice_html' => $invoice_html,
                    'invoice_file_path' => $invoice_path,
                    'qr_code_data' => $qr_data,
                    'status' => 'active'
                ],
                ['%s', '%d', '%s', '%s', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s']
            );
        }
        
        error_log("FACTURA GUARDADA EN BD: {$invoice_number} - Planes: {$all_plan_names}");
    }
    
    /**
     * ========================================================================
     * FUNCIONES PARA COTIZACIONES (ESTADO "INTERESTED")
     * ========================================================================
     */
    
    /**
     * Enviar cotización al contacto interesado
     */
    private function send_quotation_email($contact_data, $plans_data) {
        // Configurar SMTP
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        
        // Generar número de cotización: C-AT-YYYYMMDD-XXXX
        $quotation_number = $this->generate_quotation_number();
        
        // Fecha de validez (3 días desde ahora)
        $valid_until = date('Y-m-d H:i:s', strtotime('+3 days'));
        
        // Generar PDF de cotización
        $quotation_pdf_path = $this->generate_and_save_quotation_pdf($contact_data, $plans_data, $quotation_number, $valid_until);
        
        // Generar HTML de cotización
        $quotation_html = $this->generate_quotation_html($contact_data, $plans_data, $quotation_number, $valid_until);
        
        // Guardar cotización en BD
        $this->save_quotation_to_database($contact_data, $plans_data, $quotation_number, $quotation_html, $quotation_pdf_path, $valid_until);
        
        // Enviar email al contacto con la cotización
        $this->send_quotation_email_to_contact($contact_data, $plans_data, $quotation_number, $quotation_pdf_path, $valid_until);
        
        // Enviar notificación interna
        $this->send_quotation_notification_internal($contact_data, $plans_data, $quotation_number);
    }
    
    /**
     * Generar número de cotización formato: C-AT-YYYYMMDD-XXXX
     */
    private function generate_quotation_number() {
        global $wpdb;
        $quotations_table = $wpdb->prefix . 'automatiza_tech_quotations';
        
        // Obtener último número del día
        $today = date('Ymd');
        $prefix = "C-AT-{$today}-";
        
        $last_number = $wpdb->get_var($wpdb->prepare(
            "SELECT quotation_number FROM {$quotations_table} 
             WHERE quotation_number LIKE %s 
             ORDER BY id DESC LIMIT 1",
            $prefix . '%'
        ));
        
        if ($last_number) {
            // Extraer el número secuencial
            $parts = explode('-', $last_number);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generar PDF de cotización usando QuotationPDFFPDF
     */
    private function generate_and_save_quotation_pdf($contact_data, $plans_data, $quotation_number, $valid_until) {
        error_log("DEBUG generate_and_save_quotation_pdf: Recibiendo " . count($plans_data) . " planes");
        if (is_array($plans_data)) {
            foreach ($plans_data as $idx => $plan) {
                error_log("DEBUG Quotation PDF: Plan " . ($idx + 1) . " - ID={$plan->id}, Nombre={$plan->name}");
            }
        }
        
        // Cargar clase QuotationPDF
        require_once(get_template_directory() . '/lib/quotation-pdf-fpdf.php');
        
        try {
            // Crear PDF
            $pdf = new QuotationPDFFPDF($contact_data, $plans_data, $quotation_number, $valid_until);
            $pdf->build();
            
            // Ruta de guardado
            $upload_dir = wp_upload_dir();
            $quotations_dir = $upload_dir['basedir'] . '/automatiza-tech-quotations/';
            
            if (!file_exists($quotations_dir)) {
                wp_mkdir_p($quotations_dir);
            }
            
            $filename = $quotation_number . '-' . sanitize_file_name($contact_data->name) . '.pdf';
            $filepath = $quotations_dir . $filename;
            
            // Guardar PDF
            $result = $pdf->save_to_file($filepath);
            
            if ($result && file_exists($filepath)) {
                $filesize = filesize($filepath);
                error_log("PDF COTIZACION generado exitosamente con FPDF: {$filepath} ({$filesize} bytes)");
                return $filepath;
            } else {
                error_log("ERROR: No se pudo guardar PDF de cotización en: {$filepath}");
                return null;
            }
            
        } catch (Exception $e) {
            error_log("ERROR generando PDF de cotización: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generar HTML de cotización
     */
    private function generate_quotation_html($contact_data, $plans_data, $quotation_number, $valid_until) {
        $site_url = get_site_url();
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        
        // Calcular totales
        $subtotal = 0;
        $plans_html = '';
        $plans_names = array();
        
        foreach ($plans_data as $index => $plan) {
            $plan_num = $index + 1;
            $price = floatval($plan->price_clp);
            $subtotal += $price;
            $plans_names[] = $plan->name;
            
            $plans_html .= "
            <tr>
                <td style='padding: 10px; border: 1px solid #e3e6f0; text-align: center;'>{$plan_num}</td>
                <td style='padding: 10px; border: 1px solid #e3e6f0;'>" . esc_html($plan->name) . "</td>
                <td style='padding: 10px; border: 1px solid #e3e6f0; text-align: center;'>1</td>
                <td style='padding: 10px; border: 1px solid #e3e6f0; text-align: right;'>$" . number_format($price, 0, ',', '.') . "</td>
            </tr>";
        }
        
        $fecha_emision = date('d-m-Y H:i');
        $fecha_validez = date('d-m-Y', strtotime($valid_until));
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Cotización {$quotation_number}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; }
                .header { background: linear-gradient(135deg, #1e3a8a, #06d6a0); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { background: #fff; padding: 30px; border: 1px solid #e3e6f0; }
                .quotation-info { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center; }
                .contact-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background: #00bfb3; color: white; padding: 12px; text-align: left; }
                td { padding: 10px; border: 1px solid #e3e6f0; }
                .total-row { background: #e8f5f1; font-weight: bold; font-size: 18px; }
                .conditions { background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; border-top: 2px solid #e3e6f0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <img src='{$logo_url}' alt='AutomatizaTech' style='max-width: 150px; margin-bottom: 10px;'>
                <h1>🎯 COTIZACIÓN</h1>
                <p>Nro: {$quotation_number}</p>
            </div>
            
            <div class='content'>
                <div class='quotation-info'>
                    <p><strong>Fecha de emisión:</strong> {$fecha_emision}</p>
                    <p style='color: #ff9800; font-weight: bold; font-size: 16px;'>⏰ Válida hasta: {$fecha_validez} (3 días)</p>
                </div>
                
                <div class='contact-info'>
                    <h3>Datos del Contacto</h3>
                    <p><strong>Nombre:</strong> " . esc_html($contact_data->name) . "</p>
                    <p><strong>Email:</strong> " . esc_html($contact_data->email) . "</p>
                    " . (!empty($contact_data->company) ? "<p><strong>Empresa:</strong> " . esc_html($contact_data->company) . "</p>" : "") . "
                    " . (!empty($contact_data->phone) ? "<p><strong>Teléfono:</strong> " . esc_html($contact_data->phone) . "</p>" : "") . "
                </div>
                
                <h3>Servicios Cotizados</h3>
                <table>
                    <thead>
                        <tr>
                            <th style='width: 50px;'>#</th>
                            <th>Descripción</th>
                            <th style='width: 100px;'>Cantidad</th>
                            <th style='width: 150px;'>Precio CLP</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$plans_html}
                        <tr class='total-row'>
                            <td colspan='3' style='text-align: right; padding: 15px;'>TOTAL COTIZADO:</td>
                            <td style='text-align: right; padding: 15px;'>$" . number_format($subtotal, 0, ',', '.') . "</td>
                        </tr>
                    </tbody>
                </table>
                
                <p style='font-size: 12px; color: #6c757d; font-style: italic;'>* Los precios no incluyen IVA. Al contratar se emitirá factura con impuestos vigentes.</p>
                
                <div class='conditions'>
                    <h3 style='color: #ff9800;'>📋 Condiciones de la Cotización</h3>
                    <ul>
                        <li>Esta cotización tiene una validez de <strong>3 días calendario</strong> desde su emisión.</li>
                        <li>Los precios están expresados en pesos chilenos (CLP) y no incluyen IVA.</li>
                        <li>Al aceptar esta cotización y contratar el servicio, se emitirá factura tributaria.</li>
                        <li>Los plazos de implementación se definirán en conjunto al confirmar el servicio.</li>
                        <li>Para confirmar su interés, responda a este correo o contáctenos directamente.</li>
                    </ul>
                </div>
            </div>
            
            <div class='footer'>
                <p><strong>IMPORTANTE:</strong> Este documento es una COTIZACIÓN y NO tiene efectos tributarios.</p>
                <p>AutomatizaTech - Transformación Digital</p>
                <p>{$site_url}</p>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Guardar cotización en base de datos
     */
    private function save_quotation_to_database($contact_data, $plans_data, $quotation_number, $quotation_html, $quotation_path, $valid_until) {
        global $wpdb;
        
        $quotations_table = $wpdb->prefix . 'automatiza_tech_quotations';
        
        // Calcular totales
        $subtotal = 0;
        $plan_names = array();
        $first_plan_id = null;
        
        foreach ($plans_data as $plan) {
            $subtotal += floatval($plan->price_clp);
            $plan_names[] = $plan->name;
            if ($first_plan_id === null && isset($plan->id)) {
                $first_plan_id = $plan->id;
            }
        }
        
        $iva = $subtotal * 0.19;
        $total = $subtotal + $iva;
        
        $all_plan_names = implode(' + ', $plan_names);
        
        // Datos para el QR
        $qr_data = "COTIZACIÓN: {$quotation_number}\nContacto: {$contact_data->name}\nPlan(es): {$all_plan_names}\nTotal: $" . number_format($total, 0, ',', '.');
        
        // Insertar cotización
        $wpdb->insert(
            $quotations_table,
            [
                'quotation_number' => $quotation_number,
                'contact_id' => $contact_data->id,
                'contact_name' => $contact_data->name,
                'contact_email' => $contact_data->email,
                'contact_company' => $contact_data->company ?? '',
                'contact_phone' => $contact_data->phone ?? '',
                'plan_id' => $first_plan_id,
                'plan_name' => $all_plan_names,
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'quotation_html' => $quotation_html,
                'quotation_file_path' => $quotation_path,
                'qr_code_data' => $qr_data,
                'status' => 'pending',
                'valid_until' => $valid_until
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
        );
        
        error_log("COTIZACION GUARDADA EN BD: {$quotation_number} - Planes: {$all_plan_names} - Válida hasta: {$valid_until}");
    }
    
    /**
     * Enviar email con cotización al contacto
     */
    private function send_quotation_email_to_contact($contact_data, $plans_data, $quotation_number, $quotation_pdf_path, $valid_until) {
        $to = $contact_data->email;
        $subject = 'Tu Cotización ' . $quotation_number . ' - AutomatizaTech';
        
        $site_url = get_site_url();
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        
        // Detectar país y configurar moneda
        $country = 'CL';
        if (isset($contact_data->country) && !empty($contact_data->country)) {
            $country = strtoupper($contact_data->country);
        } elseif (isset($contact_data->phone)) {
            if (strpos($contact_data->phone, '+56') === 0) $country = 'CL';
            elseif (strpos($contact_data->phone, '+1') === 0) $country = 'US';
        }
        
        $currency = ($country === 'CL') ? 'CLP' : 'USD';
        $currency_symbol = ($country === 'CL') ? '$' : 'USD $';
        
        // Preparar lista de planes y calcular totales
        $plans_list = '';
        $subtotal = 0;
        
        foreach ($plans_data as $index => $plan) {
            $plan_num = $index + 1;
            $price = floatval($plan->price_clp);
            $subtotal += $price;
            
            $plans_list .= "
            <tr>
                <td style='padding: 12px 15px; border-bottom: 1px solid #e5e7eb; color: #374151;'>
                    <strong>" . esc_html($plan->name) . "</strong>
                    " . (!empty($plan->description) ? "<br><span style='font-size: 12px; color: #6b7280;'>" . esc_html($plan->description) . "</span>" : "") . "
                </td>
                <td style='padding: 12px 15px; border-bottom: 1px solid #e5e7eb; text-align: center; color: #374151;'>1</td>
                <td style='padding: 12px 15px; border-bottom: 1px solid #e5e7eb; text-align: right; color: #374151; font-weight: 600;'>" . $currency_symbol . " " . number_format($price, 0, ',', '.') . "</td>
            </tr>";
        }
        
        // Calcular IVA y total
        $iva = 0;
        $total = $subtotal;
        if ($country === 'CL') {
            $iva = $subtotal * 0.19;
            $total = $subtotal + $iva;
        }
        
        $fecha_validez = current_time('d/m/Y', strtotime($valid_until));
        
        // HTML optimizado para evitar spam con colores del degradado azul-turquesa
        $message = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizacion AutomatizaTech</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header con degradado azul-turquesa -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0047AB 0%, #00CED1 100%); padding: 40px 20px; text-align: center;">
                            <img src="' . $logo_url . '" alt="AutomatizaTech" style="max-width: 150px; height: auto; display: block; margin: 0 auto 20px auto; background-color: rgba(255,255,255,0.1); padding: 10px; border-radius: 10px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: bold; letter-spacing: 0.5px;">AutomatizaTech SpA</h1>
                            <p style="color: #e0f2f1; margin: 8px 0 0 0; font-size: 14px;">Soluciones tecnológicas profesionales</p>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 35px 30px;">
                            <h2 style="color: #0047AB; margin: 0 0 20px 0; font-size: 22px;">Hola ' . esc_html($contact_data->name) . '</h2>
                            
                            <p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0; font-size: 15px;">
                                Gracias por tu interés en nuestros servicios. Te enviamos la cotización <strong>' . $quotation_number . '</strong> con el detalle de los planes seleccionados.
                            </p>
                            
                            <!-- Info de la cotizacion -->
                            <table width="100%" cellpadding="15" cellspacing="0" border="0" style="background-color: #f0f9ff; border-left: 4px solid #00CED1; border-radius: 4px; margin: 20px 0;">
                                <tr>
                                    <td>
                                        <p style="margin: 5px 0; color: #0047AB; font-size: 14px;"><strong>Número de Cotización:</strong> ' . $quotation_number . '</p>
                                        <p style="margin: 5px 0; color: #0047AB; font-size: 14px;"><strong>Válida hasta:</strong> ' . $fecha_validez . ' (3 días calendario)</p>
                                        <p style="margin: 5px 0; color: #0047AB; font-size: 14px;"><strong>Moneda:</strong> ' . $currency . '</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Tabla de servicios -->
                            <h3 style="color: #0047AB; margin: 25px 0 15px 0; font-size: 18px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px;">
                                Servicios Cotizados
                            </h3>
                            
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e5e7eb; border-radius: 4px; margin-bottom: 20px;">
                                <thead>
                                    <tr style="background-color: #00CED1;">
                                        <th style="padding: 12px 15px; text-align: left; color: #ffffff; font-size: 13px; font-weight: 600;">Plan</th>
                                        <th style="padding: 12px 15px; text-align: center; color: #ffffff; font-size: 13px; font-weight: 600; width: 80px;">Cant.</th>
                                        <th style="padding: 12px 15px; text-align: right; color: #ffffff; font-size: 13px; font-weight: 600; width: 120px;">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . $plans_list . '
                                    
                                    <!-- Subtotal -->
                                    <tr>
                                        <td colspan="2" style="padding: 12px 15px; text-align: right; color: #374151; font-weight: 600; border-top: 2px solid #e5e7eb;">
                                            Subtotal:
                                        </td>
                                        <td style="padding: 12px 15px; text-align: right; color: #374151; font-weight: 600; border-top: 2px solid #e5e7eb;">
                                            ' . $currency_symbol . ' ' . number_format($subtotal, 0, ',', '.') . '
                                        </td>
                                    </tr>';
        
        // Agregar IVA solo para Chile
        if ($country === 'CL') {
            $message .= '
                                    <tr>
                                        <td colspan="2" style="padding: 12px 15px; text-align: right; color: #374151;">
                                            IVA (19%):
                                        </td>
                                        <td style="padding: 12px 15px; text-align: right; color: #374151;">
                                            ' . $currency_symbol . ' ' . number_format($iva, 0, ',', '.') . '
                                        </td>
                                    </tr>';
        }
        
        $message .= '
                                    <!-- Total -->
                                    <tr style="background-color: #f0f9ff;">
                                        <td colspan="2" style="padding: 15px; text-align: right; color: #0047AB; font-size: 16px; font-weight: bold; border-top: 2px solid #00CED1;">
                                            TOTAL COTIZADO:
                                        </td>
                                        <td style="padding: 15px; text-align: right; color: #0047AB; font-size: 16px; font-weight: bold; border-top: 2px solid #00CED1;">
                                            ' . $currency_symbol . ' ' . number_format($total, 0, ',', '.') . '
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <!-- Boton de accion -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="mailto:info@automatizatech.shop?subject=Consulta sobre Cotizacion ' . $quotation_number . '" style="display: inline-block; background: linear-gradient(135deg, #0047AB 0%, #00CED1 100%); color: #ffffff; text-decoration: none; padding: 14px 30px; border-radius: 6px; font-weight: bold; font-size: 15px;">
                                            Responder Cotización
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #6b7280; line-height: 1.6; margin: 20px 0 0 0; font-size: 13px; text-align: center;">
                                Si tienes preguntas o necesitas más información, no dudes en contactarnos. Estamos aquí para ayudarte.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 20px 30px; border-top: 1px solid #e5e7eb; text-align: center;">
                            <p style="margin: 0 0 8px 0; color: #374151; font-size: 14px; font-weight: 600;">AutomatizaTech SpA</p>
                            <p style="margin: 0 0 5px 0; color: #6b7280; font-size: 12px;">info@automatizatech.shop</p>
                            <p style="margin: 0; color: #6b7280; font-size: 12px;">' . $site_url . '</p>
                        </td>
                    </tr>
                </table>
                
                <!-- Nota legal -->
                <p style="color: #9ca3af; font-size: 11px; text-align: center; margin: 15px 0 0 0; max-width: 600px;">
                    Este correo contiene información sobre tu cotización. Para consultas, responde directamente a este email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        // Headers optimizados para evitar spam
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: AutomatizaTech <info@automatizatech.shop>',
            'Reply-To: info@automatizatech.shop',
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3',
            'Importance: Normal'
        );
        
        // Adjuntar PDF si existe
        $attachments = array();
        if ($quotation_pdf_path && file_exists($quotation_pdf_path)) {
            $attachments[] = $quotation_pdf_path;
        }
        
        $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        
        if ($sent) {
            error_log("COTIZACION ENVIADA: Cotización {$quotation_number} enviada a {$contact_data->email} ({$contact_data->name})");
        } else {
            error_log("ERROR COTIZACION: No se pudo enviar cotización {$quotation_number} a {$contact_data->email}");
        }
        
        return $sent;
    }
    
    /**
     * Enviar notificación interna sobre nueva cotización
     */
    private function send_quotation_notification_internal($contact_data, $plans_data, $quotation_number) {
        $to = 'automatizatech.bots@gmail.com';
        
        $plans_names = array();
        $total = 0;
        foreach ($plans_data as $plan) {
            $plans_names[] = $plan->name;
            $total += floatval($plan->price_clp);
        }
        $plans_text = implode(', ', $plans_names);
        
        $subject = '💰 Nueva Cotización Generada - ' . $quotation_number . ' - ' . $contact_data->name;
        
        $site_url = get_site_url();
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        $admin_url = admin_url('admin.php?page=automatiza-tech-contacts');
        
        $message = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #ff9800, #06d6a0); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 25px; border-radius: 0 0 8px 8px; }
                .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .label { font-weight: bold; color: #1e3a8a; display: inline-block; width: 120px; }
                .cta { background: #06d6a0; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; display: inline-block; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 140px; height: auto; margin-bottom: 10px;'>
                <h1>💰 Nueva Cotización Generada</h1>
                <p>Se ha enviado una cotización a un contacto interesado</p>
            </div>
            
            <div class='content'>
                <div class='info-box'>
                    <h3 style='color: #1e3a8a; margin-top: 0;'>📋 Información de la Cotización</h3>
                    <p><span class='label'>Número:</span> {$quotation_number}</p>
                    <p><span class='label'>Contacto:</span> " . esc_html($contact_data->name) . "</p>
                    <p><span class='label'>Email:</span> " . esc_html($contact_data->email) . "</p>
                    " . (!empty($contact_data->company) ? "<p><span class='label'>Empresa:</span> " . esc_html($contact_data->company) . "</p>" : "") . "
                    " . (!empty($contact_data->phone) ? "<p><span class='label'>Teléfono:</span> " . esc_html($contact_data->phone) . "</p>" : "") . "
                </div>
                
                <div class='info-box' style='border-left: 4px solid #ff9800;'>
                    <h3 style='color: #ff9800; margin-top: 0;'>💼 Servicios Cotizados</h3>
                    <p><strong>{$plans_text}</strong></p>
                    <p style='font-size: 1.2em;'><strong>Total:</strong> <span style='color: #06d6a0; font-weight: bold;'>$" . number_format($total, 0, ',', '.') . " CLP</span></p>
                </div>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <a href='{$admin_url}' class='cta'>Ver en Panel de Administración</a>
                </div>
            </div>
        </body>
        </html>";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            error_log("CORREO ENVIADO: Notificación de cotización enviada a automatizatech.bots@gmail.com para {$contact_data->name}");
        }
        
        return $sent;
    }
    
    /**
     * ========================================================================
     * FIN FUNCIONES PARA COTIZACIONES
     * ========================================================================
     */
    
    /**
     * Generar factura/boleta en HTML para el cliente
     */
    private function generate_invoice_html($client_data, $plans_data) {
        // Cargar librería de QR Code
        require_once(get_template_directory() . '/lib/qrcode.php');
        
        // Número de factura único
        $invoice_number = 'AT-' . date('Ymd') . '-' . str_pad($client_data->id ?? rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $invoice_date = date('d/m/Y');
        $site_url = get_site_url();
        $logo_url = get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png';
        
        // Colores de AutomatizaTech
        $primary_color = '#1e3a8a';    // Azul oscuro
    $secondary_color = '#06d6a0';   // Verde agua
    $accent_color = '#f59e0b';      // Naranja
    
    // Soportar tanto un solo plan como múltiples planes
    $plans_array = is_array($plans_data) ? $plans_data : array($plans_data);
    
    // Calcular IVA (19% en Chile) sumando todos los planes
    $subtotal = 0;
    foreach ($plans_array as $plan) {
        $subtotal += floatval($plan->price_clp);
    }
    $iva = $subtotal * 0.19;
    $total = $subtotal + $iva;        $html = "<!DOCTYPE html>
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
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid {$secondary_color};
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .info-block h3 {
            color: {$primary_color};
            margin-bottom: 15px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-block p {
            margin: 8px 0;
            font-size: 0.95em;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            display: inline-block;
            width: 120px;
        }
        .invoice-details {
            padding: 25px 30px;
        }
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
        .service-table thead {
            background: {$primary_color};
            color: white;
        }
        .service-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        .service-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .service-table tbody tr:hover {
            background: #f9fafb;
        }
        .service-description {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .totals {
            margin-top: 20px;
            text-align: right;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .totals .row {
            display: flex;
            justify-content: flex-end;
            margin: 8px 0;
            font-size: 1em;
        }
        .totals .label {
            margin-right: 40px;
            color: #555;
            font-weight: 600;
            min-width: 150px;
            text-align: right;
        }
        .totals .amount {
            min-width: 150px;
            text-align: right;
            font-weight: bold;
        }
        .total-row {
            border-top: 3px solid {$secondary_color};
            padding-top: 15px;
            margin-top: 15px;
        }
        .total-row .label {
            color: {$primary_color};
            font-size: 1.3em;
        }
        .total-row .amount {
            color: {$secondary_color};
            font-size: 1.5em;
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
            font-size: 0.9em;
        }
        .features-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: {$secondary_color};
            font-weight: bold;
            font-size: 1.2em;
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
        <!-- Header -->
        <div class='invoice-header'>
            <img src='{$logo_url}' alt='AutomatizaTech Logo' style='max-width: 110px; height: auto; margin-bottom: 8px;'>
            <h1>🧾 FACTURA</h1>
            <p>AutomatizaTech - Soluciones Digitales Profesionales</p>
        </div>
        
        <!-- Info de Factura y Cliente -->
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
                " . ($client_data->company ? "<p><span class='info-label'>Empresa:</span> " . esc_html($client_data->company) . "</p>" : "") . "
                " . ($client_data->phone ? "<p><span class='info-label'>Teléfono:</span> " . esc_html($client_data->phone) . "</p>" : "") . "
            </div>
        </div>
        
        <!-- Detalles del Servicio -->
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
                <tbody>";
                
                // Iterar sobre todos los planes
                foreach ($plans_array as $plan) {
                    $plan_price = floatval($plan->price_clp);
                    $html .= "
                    <tr>
                        <td>
                            <strong style='color: {$primary_color}; font-size: 1.1em;'>" . esc_html($plan->name) . "</strong>
                            <div class='service-description'>" . esc_html($plan->description) . "</div>
                            " . (!empty($plan->features) ? "
                            <ul class='features-list'>
                                " . implode('', array_map(function($feature) {
                                    return "<li>" . esc_html(trim($feature)) . "</li>";
                                }, explode("\n", $plan->features))) . "
                            </ul>
                            " : "") . "
                        </td>
                        <td style='text-align: center; font-size: 1.1em; font-weight: 600;'>1</td>
                        <td style='text-align: right; font-size: 1.1em; font-weight: 600;'>$" . number_format($plan_price, 0, ',', '.') . "</td>
                    </tr>";
                }
                
                $html .= "
                </tbody>
            </table>
            
            <!-- Totales -->
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
        
        <!-- Código QR de Validación -->
        <div class='qr-validation' style='text-align: center; padding: 12px 30px; background: #f9fafb; border-top: 2px solid {$secondary_color};'>
            <h3 style='color: {$primary_color}; margin-bottom: 6px;'>🔒 Validación de Factura</h3>
            <p style='margin-bottom: 8px; color: #666;'>Escanea el QR para validar la autenticidad</p>";
    
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
        
        <!-- Footer -->
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
     * Guardar factura como archivo HTML
     */
    private function save_invoice_file($html_content, $client_data, $invoice_number) {
        $upload_dir = wp_upload_dir();
        $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
        
        // Crear directorio si no existe
        if (!file_exists($invoices_dir)) {
            wp_mkdir_p($invoices_dir);
        }
        
        // Nombre del archivo
        $filename = $invoice_number . '-' . sanitize_file_name($client_data->name) . '.html';
        $filepath = $invoices_dir . $filename;
        
        // Guardar archivo
        file_put_contents($filepath, $html_content);
        
        return $filepath;
    }
    
    /**
     * Generar y guardar factura en formato PDF usando FPDF
     */
    private function generate_and_save_pdf($client_data, $plans_data, $invoice_number) {
        // DEBUG: Verificar cuántos planes se reciben
        error_log("DEBUG generate_and_save_pdf: Recibiendo " . count($plans_data) . " planes");
        if (is_array($plans_data)) {
            foreach ($plans_data as $idx => $plan) {
                error_log("DEBUG PDF: Plan " . ($idx + 1) . " - ID={$plan->id}, Nombre={$plan->name}");
            }
        }
        
        // Cargar generador de PDF con FPDF
        require_once(get_template_directory() . '/lib/invoice-pdf-fpdf.php');
        
        $upload_dir = wp_upload_dir();
        $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
        
        // Crear directorio si no existe
        if (!file_exists($invoices_dir)) {
            wp_mkdir_p($invoices_dir);
        }
        
        try {
            // Generar PDF con FPDF (100% PHP, sin dependencias externas)
            // Pasar array de planes al generador
            $pdf_generator = new InvoicePDFFPDF($client_data, $plans_data, $invoice_number);
            
            $pdf_path = $invoices_dir . $invoice_number . '-' . sanitize_file_name($client_data->name) . '.pdf';
            
            // Guardar PDF
            $success = $pdf_generator->save($pdf_path);
            
            if ($success && file_exists($pdf_path) && filesize($pdf_path) > 0) {
                error_log("PDF generado exitosamente con FPDF: {$pdf_path} (" . filesize($pdf_path) . " bytes)");
                return $pdf_path;
            } else {
                error_log("Error: PDF generado pero archivo vacío o no existe");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Error al generar PDF con FPDF: " . $e->getMessage());
            return false;
        }
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
        $filename = 'cliente-contratado-' . current_time('Y-m-d_H-i-s') . '-' . sanitize_file_name($contact->name) . '.html';
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
                <p><strong>Fecha:</strong> " . current_time('Y-m-d H:i:s') . "</p>
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
                $formatted_time = current_time('Y-m-d H:i:s', $file_time);
                
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
     * Obtener lista de planes disponibles para el combo
     */
    public function get_available_plans() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        global $wpdb;
        
        // Obtener planes activos con precios definidos
        $plans = $wpdb->get_results("
            SELECT id, name, description, price_clp, price_usd
            FROM {$wpdb->prefix}automatiza_services
            WHERE status = 'active'
            AND (price_clp > 0 OR price_usd > 0)
            ORDER BY id ASC
        ");
        
        if (!$plans) {
            wp_send_json_error('No hay planes disponibles');
            wp_die();
        }
        
        wp_send_json_success($plans);
        wp_die();
    }
    
    /**
     * Descargar factura en PDF
     */
    public function download_invoice() {
        // LOG: Inicio de descarga
        error_log('=== INICIO DESCARGA FACTURA ===');
        error_log('Usuario autenticado: ' . (is_user_logged_in() ? 'SÍ' : 'NO'));
        
        // Verificar que el usuario esté autenticado
        if (!is_user_logged_in()) {
            error_log('ERROR: Usuario no autenticado');
            wp_die('No autorizado', 'Error', array('response' => 403));
        }
        
        // Obtener número de factura
        if (!isset($_GET['invoice_number']) || empty($_GET['invoice_number'])) {
            error_log('ERROR: Número de factura no proporcionado');
            wp_die('Número de factura no proporcionado', 'Error', array('response' => 400));
        }
        
        $invoice_number = sanitize_text_field($_GET['invoice_number']);
        error_log('Factura solicitada: ' . $invoice_number);
        
        // Construir ruta del archivo PDF
        $upload_dir = wp_upload_dir();
        $invoices_dir = $upload_dir['basedir'] . '/automatiza-tech-invoices/';
        error_log('Directorio de facturas: ' . $invoices_dir);
        error_log('Existe directorio: ' . (is_dir($invoices_dir) ? 'SÍ' : 'NO'));
        
        // Buscar el archivo PDF (puede tener el nombre del cliente al final)
        $pdf_files = glob($invoices_dir . $invoice_number . '*.pdf');
        error_log('Patrón de búsqueda: ' . $invoices_dir . $invoice_number . '*.pdf');
        error_log('Archivos encontrados: ' . count($pdf_files));
        if (!empty($pdf_files)) {
            error_log('Primer archivo: ' . $pdf_files[0]);
        }
        
        if (empty($pdf_files)) {
            error_log('ERROR: No se encontraron archivos PDF para: ' . $invoice_number);
            // Listar todos los archivos en el directorio
            if (is_dir($invoices_dir)) {
                $all_files = scandir($invoices_dir);
                error_log('Archivos en directorio: ' . print_r($all_files, true));
            }
            wp_die('Factura no encontrada: ' . esc_html($invoice_number), 'Error 404', array('response' => 404));
        }
        
        $pdf_file = $pdf_files[0]; // Tomar el primero si hay varios
        error_log('Archivo PDF seleccionado: ' . $pdf_file);
        error_log('Existe archivo: ' . (file_exists($pdf_file) ? 'SÍ' : 'NO'));
        error_log('Es legible: ' . (is_readable($pdf_file) ? 'SÍ' : 'NO'));
        error_log('Tamaño: ' . (file_exists($pdf_file) ? filesize($pdf_file) . ' bytes' : 'N/A'));
        
        if (!file_exists($pdf_file)) {
            error_log('ERROR: El archivo no existe: ' . $pdf_file);
            wp_die('Archivo de factura no existe', 'Error 404', array('response' => 404));
        }
        
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        error_log('Enviando headers para descarga...');
        
        // Configurar headers para descarga
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdf_file) . '"');
        header('Content-Length: ' . filesize($pdf_file));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        error_log('Enviando archivo PDF: ' . basename($pdf_file));
        
        // Enviar archivo
        readfile($pdf_file);
        error_log('=== FIN DESCARGA FACTURA ===');
        exit;
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
     * Filtrar contactos por búsqueda y estado
     */
    public function filter_contacts() {
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Verificar nonce para seguridad
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'filter_contacts')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        global $wpdb;
        
        $where_clauses = array();
        $values = array();
        
        // Si hay término de búsqueda
        if (!empty($search_term)) {
            $like_term = '%' . $wpdb->esc_like($search_term) . '%';
            $where_clauses[] = "(name LIKE %s OR email LIKE %s OR company LIKE %s OR phone LIKE %s OR message LIKE %s)";
            $values = array_merge($values, array($like_term, $like_term, $like_term, $like_term, $like_term));
        }
        
        // Si hay filtro de estado
        if (!empty($status)) {
            $where_clauses[] = "status = %s";
            $values[] = $status;
        }
        
        // Construir la consulta
        $sql = "SELECT * FROM {$this->table_name}";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $sql .= " ORDER BY submitted_at DESC";
        
        if (!empty($values)) {
            $contacts = $wpdb->get_results($wpdb->prepare($sql, $values));
        } else {
            $contacts = $wpdb->get_results($sql);
        }
        
        wp_send_json_success($contacts);
        wp_die();
    }
    
    /**
     * Enviar email a todos los contactos con estado "Nuevo"
     */
    public function send_email_to_new_contacts() {
        // Verificar permisos
        if (!current_user_can('administrator')) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            wp_die();
        }
        
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'send_email_new_contacts')) {
            wp_send_json_error('Error de seguridad');
            wp_die();
        }
        
        global $wpdb;
        
        // Obtener todos los contactos con estado "new"
        $contacts = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE status = 'new'");
        
        if (empty($contacts)) {
            wp_send_json_error('No hay contactos con estado "Nuevo" para enviar correos');
            wp_die();
        }
        
        $sent_count = 0;
        $failed_count = 0;
        $failed_emails = array();
        
        foreach ($contacts as $contact) {
            $subject = '¡Descubre cómo Automatiza Tech puede transformar tu negocio! 🚀';
            
            $body = $this->get_email_template($contact->name);
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: Automatiza Tech <' . get_option('admin_email') . '>',
                'Reply-To: Automatiza Tech <info@automatizatech.cl>',
                'Bcc: automatizatech.bots@gmail.com'
            );
            
            $result = wp_mail($contact->email, $subject, $body, $headers);
            
            if ($result) {
                $sent_count++;
                
                // Cambiar estado a "contacted" después del envío exitoso
                $wpdb->update(
                    $this->table_name,
                    array('status' => 'contacted'),
                    array('id' => $contact->id),
                    array('%s'),
                    array('%d')
                );
                
                // Log de éxito
                if (WP_DEBUG && WP_DEBUG_LOG) {
                    error_log('Automatiza Tech - Correo enviado exitosamente a: ' . $contact->email . ' - Estado cambiado a "contacted"');
                }
            } else {
                $failed_count++;
                $failed_emails[] = $contact->email;
                
                // Log de error
                if (WP_DEBUG && WP_DEBUG_LOG) {
                    error_log('Automatiza Tech - Error al enviar correo a: ' . $contact->email);
                }
            }
            
            // Pausa pequeña entre envíos para evitar sobrecarga del servidor SMTP
            usleep(500000); // 0.5 segundos
        }
        
        $message = "✅ Se enviaron $sent_count correos exitosamente.";
        
        if ($sent_count > 0) {
            $message .= "\n📋 Los contactos han sido actualizados al estado 'Contactado'.";
        }
        
        if ($failed_count > 0) {
            $message .= "\n⚠️ $failed_count correos fallaron: " . implode(', ', $failed_emails);
            $message .= "\nLos contactos con fallos permanecen en estado 'Nuevo'.";
        }
        
        // Enviar respuesta con flag de recarga
        wp_send_json_success(array(
            'message' => $message,
            'sent' => $sent_count,
            'failed' => $failed_count,
            'reload' => true  // Flag para recargar la página
        ));
        wp_die();
    }
    
    /**
     * Plantilla de email profesional con información de planes
     */
    private function get_email_template($name) {
        global $wpdb;
        
        $whatsapp_number = get_theme_mod('whatsapp_number', '+56 9 4033 1127');
        $whatsapp_url = get_whatsapp_url('Hola! Me interesa conocer más sobre los planes de Automatiza Tech');
        
        // Obtener planes desde la base de datos
        $plans = get_active_automatiza_services('pricing');
        
        if (empty($plans)) {
            // Fallback si no hay planes en BD
            $plans = array();
        }
        
        $template = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Automatiza Tech - Nuestros Planes</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                            <!-- Header con logo y bot animado -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center; position: relative;">
                                    <!-- Logo de Automatiza Tech -->
                                    <div style="margin-bottom: 25px;">
                                        <img src="' . esc_url(get_template_directory_uri() . '/assets/images/logo-automatiza-tech.png') . '" alt="Automatiza Tech - Bots inteligentes para negocios" style="max-width: 320px; width: 100%; height: auto; display: block; margin: 0 auto;" />
                                    </div>
                                    <!-- Bot animado -->
                                    <div style="font-size: 60px; margin-bottom: 10px; animation: bounce 2s infinite;">🤖</div>
                                    <p style="color: #f0f0f0; margin: 10px 0 0 0; font-size: 18px; font-weight: 300;">✨ Bots inteligentes para negocios que no se detienen ✨</p>
                                </td>
                            </tr>
                            
                            <!-- Decoración de bots -->
                            <tr>
                                <td style="background: linear-gradient(to bottom, #667eea, #ffffff); padding: 30px 20px; text-align: center;">
                                    <div style="font-size: 40px; letter-spacing: 20px;">🤖💬🚀⚡🎯</div>
                                </td>
                            </tr>
                            
                            <!-- Saludo personalizado -->
                            <tr>
                                <td style="padding: 40px 40px 30px 40px; background: #ffffff;">
                                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 25px; border-radius: 15px; text-align: center; margin-bottom: 25px;">
                                        <h2 style="color: #ffffff; margin: 0; font-size: 28px; text-shadow: 1px 1px 2px rgba(0,0,0,0.2);">¡Hola ' . esc_html($name) . '! 👋✨</h2>
                                    </div>
                                    <div style="background: #f8f9ff; border-left: 5px solid #667eea; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                                        <p style="color: #333333; font-size: 16px; line-height: 1.8; margin: 0 0 15px 0;">
                                            <strong style="color: #667eea;">🎉 ¡Gracias por tu interés!</strong><br>
                                            Nos emociona enormemente poder ayudarte a transformar tu negocio con nuestras soluciones de automatización inteligente 🚀
                                        </p>
                                        <p style="color: #555555; font-size: 15px; line-height: 1.8; margin: 0;">
                                            Nuestros bots están diseñados para hacer tu vida más fácil, automatizando tareas repetitivas y permitiéndote enfocarte en lo que realmente importa: <strong>hacer crecer tu negocio</strong> 💪
                                        </p>
                                    </div>
                                    <div style="text-align: center; padding: 20px 0;">
                                        <div style="display: inline-block; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); padding: 15px 30px; border-radius: 50px; box-shadow: 0 4px 15px rgba(252, 182, 159, 0.4);">
                                            <p style="margin: 0; color: #333; font-size: 16px; font-weight: bold;">🎁 Descubre el plan perfecto para ti 🎁</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>';
            
            // Generar planes dinámicamente desde la base de datos
            if (!empty($plans)) {
                $plan_colors = array(
                    array('border' => '#06d6a0', 'color' => '#06d6a0', 'bg' => '#ffffff', 'icon' => '🌟'),
                    array('border' => '#1e40af', 'color' => '#1e40af', 'bg' => '#f8f9ff', 'icon' => '🚀'),
                    array('border' => '#dc2626', 'color' => '#dc2626', 'bg' => '#ffffff', 'icon' => '💼')
                );
                
                foreach ($plans as $index => $plan) {
                    $color_scheme = $plan_colors[$index % count($plan_colors)];
                    $features_array = json_decode($plan->features, true);
                    if (!is_array($features_array)) {
                        $features_array = explode(',', $plan->features);
                    }
                    
                    $price_text = $plan->price_usd > 0 
                        ? '$' . number_format($plan->price_usd, 0) . ' USD/mes' 
                        : 'Cotización personalizada';
                    
                    // Crear gradiente único para cada plan
                    $plan_gradients = array(
                        array('from' => '#a8edea', 'to' => '#fed6e3', 'border' => '#06d6a0'),
                        array('from' => '#d299c2', 'to' => '#fef9d7', 'border' => '#1e40af'),
                        array('from' => '#ffecd2', 'to' => '#fcb69f', 'border' => '#dc2626')
                    );
                    $gradient = $plan_gradients[$index % count($plan_gradients)];
                    
                    $template .= '
                            <tr>
                                <td style="padding: 0 40px ' . ($index === count($plans) - 1 ? '30px' : '20px') . ' 40px;">
                                    <div style="background: linear-gradient(135deg, ' . $gradient['from'] . ' 0%, ' . $gradient['to'] . ' 100%); border-radius: 20px; padding: 5px; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: transform 0.3s;">
                                        <div style="background: #ffffff; border-radius: 15px; padding: 30px; position: relative;">';
                    
                    // Badge destacado si el plan tiene highlight
                    if ($plan->highlight) {
                        $template .= '
                                            <div style="position: absolute; top: -15px; right: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 8px 20px; border-radius: 25px; font-size: 12px; font-weight: bold; box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4); animation: pulse 2s infinite;">
                                                ⭐ ' . strtoupper(esc_html($plan->button_text ? $plan->button_text : 'MÁS POPULAR')) . ' ⭐
                                            </div>';
                    }
                    
                    $template .= '
                                            <div style="text-align: center; margin-bottom: 20px;">
                                                <div style="font-size: 60px; margin-bottom: 10px;">' . $color_scheme['icon'] . '</div>
                                                <h3 style="color: ' . $color_scheme['color'] . '; margin: 0 0 10px 0; font-size: 26px; font-weight: bold;">
                                                    ' . esc_html($plan->name) . '
                                                </h3>
                                                <div style="width: 80px; height: 3px; background: linear-gradient(to right, ' . $gradient['from'] . ', ' . $gradient['to'] . '); margin: 0 auto; border-radius: 2px;"></div>
                                            </div>';
                    
                    if (!empty($plan->description)) {
                        $template .= '
                                            <div style="background: ' . $gradient['from'] . '20; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid ' . $color_scheme['border'] . ';">
                                                <p style="color: #555; font-size: 15px; margin: 0; line-height: 1.6; font-style: italic;">
                                                    💡 ' . esc_html($plan->description) . '
                                                </p>
                                            </div>';
                    }
                    
                    $template .= '
                                            <div style="background: #f8f9ff; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                                                <div style="font-weight: bold; color: #667eea; margin-bottom: 15px; font-size: 16px;">✨ Características incluidas:</div>
                                                <ul style="color: #333333; font-size: 14px; line-height: 2; padding-left: 0; list-style: none; margin: 0;">';
                    
                    foreach ($features_array as $feature_index => $feature) {
                        $check_emojis = array('✅', '🎯', '⚡', '💎', '🚀', '💪', '🌟', '🔥');
                        $check_emoji = $check_emojis[$feature_index % count($check_emojis)];
                        $template .= '
                                                    <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;"><span style="margin-right: 10px;">' . $check_emoji . '</span>' . esc_html(trim($feature)) . '</li>';
                    }
                    
                    $template .= '
                                                </ul>
                                            </div>
                                            <div style="text-align: center; background: linear-gradient(135deg, ' . $gradient['from'] . ' 0%, ' . $gradient['to'] . ' 100%); padding: 20px; border-radius: 12px; margin-top: 20px;">
                                                <div style="color: #333; font-size: 14px; margin-bottom: 5px; font-weight: 600;">💰 Precio especial</div>
                                                <div style="color: ' . $color_scheme['color'] . '; font-size: 32px; font-weight: bold; text-shadow: 2px 2px 4px rgba(255,255,255,0.5);">
                                                    ' . $price_text . '
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>';
                }
            } else {
                // Fallback si no hay planes en BD
                $template .= '
                            <tr>
                                <td style="padding: 0 40px 30px 40px;">
                                    <div style="border: 2px solid #06d6a0; border-radius: 10px; padding: 25px; text-align: center;">
                                        <p style="color: #666666; font-size: 16px;">
                                            Para conocer nuestros planes y precios, por favor contáctanos directamente.
                                        </p>
                                    </div>
                                </td>
                            </tr>';
            }
            
            $template .= '
                            
                            <!-- Separador con bots -->
                            <tr>
                                <td style="padding: 30px 40px; text-align: center;">
                                    <div style="font-size: 35px; letter-spacing: 15px; opacity: 0.6;">🤖💬🤖💬🤖</div>
                                </td>
                            </tr>
                            
                            <!-- CTA -->
                            <tr>
                                <td style="padding: 40px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); text-align: center; position: relative;">
                                    <div style="font-size: 50px; margin-bottom: 15px;">🎯</div>
                                    <h3 style="color: #ffffff; margin: 0 0 15px 0; font-size: 26px; text-shadow: 2px 2px 4px rgba(0,0,0,0.2);">🚀 ¿Listo para despegar?</h3>
                                    <p style="color: #f0f0f0; font-size: 16px; margin: 0 0 25px 0; line-height: 1.6;">
                                        ✨ Contáctanos hoy mismo y descubre cómo nuestros bots pueden revolucionar tu negocio ✨
                                    </p>
                                    <div style="margin: 20px 0;">
                                        <a href="' . esc_url($whatsapp_url) . '" style="display: inline-block; background: linear-gradient(135deg, #25D366, #128C7E); color: white; padding: 18px 45px; text-decoration: none; border-radius: 50px; font-size: 17px; font-weight: bold; margin: 10px; box-shadow: 0 8px 20px rgba(37, 211, 102, 0.4); transition: transform 0.3s;">
                                            💬 Hablar por WhatsApp
                                        </a>
                                        <br>
                                        <a href="' . esc_url(home_url('/#contacto')) . '" style="display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 18px 45px; text-decoration: none; border-radius: 50px; font-size: 17px; font-weight: bold; margin: 10px; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4); border: 2px solid #ffffff;">
                                            📧 Visitar Sitio Web
                                        </a>
                                    </div>
                                    <div style="margin-top: 25px; padding: 20px; background: rgba(255,255,255,0.2); border-radius: 15px; backdrop-filter: blur(10px);">
                                        <p style="color: #ffffff; margin: 0; font-size: 14px; line-height: 1.8;">
                                            � <strong>¿Tienes dudas?</strong><br>
                                            Nuestro equipo está listo para ayudarte a elegir el plan perfecto<br>
                                            ¡Escríbenos sin compromiso! 😊
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Footer mejorado -->
                            <tr>
                                <td style="padding: 40px; background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); text-align: center;">
                                    <div style="font-size: 45px; margin-bottom: 15px;">🤖</div>
                                    <p style="color: #ffffff; margin: 0 0 15px 0; font-size: 18px; font-weight: bold;">
                                        Automatiza Tech
                                    </p>
                                    <p style="color: #a5b4fc; margin: 0 0 20px 0; font-size: 14px; line-height: 1.6;">
                                        ✨ Conectamos tus ventas, web y CRM ✨<br>
                                        🤖 Bots inteligentes que trabajan 24/7 para ti
                                    </p>
                                    <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 15px; margin-bottom: 20px;">
                                        <p style="color: #ffffff; margin: 0 0 10px 0; font-size: 14px;">
                                            📱 WhatsApp: <a href="https://wa.me/' . str_replace([' ', '+'], '', $whatsapp_number) . '" style="color: #25D366; text-decoration: none; font-weight: bold;">' . esc_html($whatsapp_number) . '</a>
                                        </p>
                                        <p style="color: #ffffff; margin: 0 0 10px 0; font-size: 14px;">
                                            📧 Email: <a href="mailto:info@automatizatech.cl" style="color: #60a5fa; text-decoration: none; font-weight: bold;">info@automatizatech.cl</a>
                                        </p>
                                        <p style="color: #ffffff; margin: 0; font-size: 14px;">
                                            🌐 Web: <a href="' . esc_url(home_url()) . '" style="color: #60a5fa; text-decoration: none; font-weight: bold;">' . str_replace(['http://', 'https://'], '', home_url()) . '</a>
                                        </p>
                                    </div>
                                    
                                    <!-- Redes Sociales -->
                                    <div style="margin: 25px 0; padding: 20px 0; border-top: 2px solid rgba(255,255,255,0.2); border-bottom: 2px solid rgba(255,255,255,0.2);">
                                        <p style="color: #ffffff; margin: 0 0 15px 0; font-size: 16px; font-weight: bold;">
                                            📱 Síguenos en Redes Sociales
                                        </p>
                                        <table cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="padding: 0 15px;">
                                                    <a href="https://www.instagram.com/automatizatech.cl" target="_blank" style="display: inline-block; text-decoration: none;">
                                                        <table cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); border-radius: 12px; padding: 12px 20px;">
                                                            <tr>
                                                                <td style="text-align: center;">
                                                                    <span style="font-size: 24px;">📷</span>
                                                                    <span style="color: #ffffff; font-size: 14px; font-weight: bold; margin-left: 8px; vertical-align: middle;">Instagram</span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                                <td style="padding: 0 15px;">
                                                    <a href="https://www.facebook.com/AutomatizaTech.cl" target="_blank" style="display: inline-block; text-decoration: none;">
                                                        <table cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #4267B2 0%, #3b5998 100%); border-radius: 12px; padding: 12px 20px;">
                                                            <tr>
                                                                <td style="text-align: center;">
                                                                    <span style="font-size: 24px;">👍</span>
                                                                    <span style="color: #ffffff; font-size: 14px; font-weight: bold; margin-left: 8px; vertical-align: middle;">Facebook</span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="color: #a5b4fc; margin: 15px 0 0 0; font-size: 13px;">
                                            ✨ Únete a nuestra comunidad y mantente al día con tips, novedades y ofertas exclusivas
                                        </p>
                                    </div>
                                    
                                    <div style="padding-top: 20px; margin-top: 0;">
                                        <p style="color: #a5b4fc; margin: 0; font-size: 12px; line-height: 1.6;">
                                            © ' . date('Y') . ' Automatiza Tech. Todos los derechos reservados.<br>
                                            Impulsando negocios con tecnología inteligente 🚀
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
        
        return $template;
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
                            // Soportar múltiples planes: "1,2,3" → mantener como string
                            $plan_id = isset($_GET['plan_id']) ? sanitize_text_field($_GET['plan_id']) : null;
                            $result = $this->move_to_clients($contact_id, $plan_id);
                            if ($result) {
                                if ($plan_id) {
                                    echo '<div class="notice notice-success"><p>🎉 ¡Contacto movido a Clientes exitosamente! Se ha generado y enviado la factura al cliente por correo electrónico.</p></div>';
                                } else {
                                    echo '<div class="notice notice-success"><p>🎉 ¡Contacto movido a Clientes exitosamente! El cliente ahora aparece en la sección de Clientes.</p></div>';
                                }
                            } else {
                                echo '<div class="notice notice-error"><p>❌ Error al mover el contacto a Clientes.</p></div>';
                            }
                        } elseif ($new_status === 'interested') {
                            // Si el estado es "interested", generar cotización
                            // Soportar múltiples planes: "1,2,3" → mantener como string
                            $plan_id = isset($_GET['plan_id']) ? sanitize_text_field($_GET['plan_id']) : null;
                            if ($plan_id) {
                                $result = $this->move_to_interested($contact_id, $plan_id);
                                if ($result) {
                                    echo '<div class="notice notice-success"><p>💰 ¡Cotización generada exitosamente! Se ha enviado la cotización al contacto por correo electrónico. Validez: 3 días.</p></div>';
                                } else {
                                    echo '<div class="notice notice-error"><p>❌ Error al generar la cotización. Asegúrate de seleccionar al menos un plan.</p></div>';
                                }
                            } else {
                                // Sin planes, solo actualizar estado
                                $wpdb->update(
                                    $this->table_name,
                                    array('status' => $new_status),
                                    array('id' => $contact_id),
                                    array('%s'),
                                    array('%d')
                                );
                                echo '<div class="notice notice-warning"><p>⚠️ Estado actualizado a "interested" pero no se generó cotización porque no se seleccionaron planes.</p></div>';
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
            
            <!-- Campo de búsqueda y filtros -->
            <div class="search-box" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e3e6f0;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span class="dashicons dashicons-search" style="color: #2271b1; font-size: 20px;"></span>
                    <input type="text" id="contact-search" placeholder="🔍 Buscar contactos por nombre, email, empresa, teléfono o mensaje..." 
                           style="flex: 1; padding: 10px 15px; border: 2px solid #e3e6f0; border-radius: 25px; font-size: 14px; transition: border-color 0.3s;"
                           onfocus="this.style.borderColor='#2271b1'"
                           onblur="this.style.borderColor='#e3e6f0'">
                    <button type="button" id="clear-search" class="button button-secondary" style="border-radius: 20px; padding: 8px 15px;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 16px;"></span> Limpiar
                    </button>
                </div>
                <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label for="status-filter" style="font-weight: 600; color: #2271b1;">
                            <span class="dashicons dashicons-filter" style="font-size: 16px;"></span> Filtrar por Estado:
                        </label>
                        <select id="status-filter" style="padding: 8px 12px; border: 2px solid #e3e6f0; border-radius: 20px; font-size: 14px;">
                            <option value="">📋 Todos los estados</option>
                            <option value="new">🆕 Nuevo</option>
                            <option value="contacted">📞 Contactado</option>
                            <option value="follow_up">📅 Seguimiento</option>
                            <option value="interested">💡 Interesado</option>
                            <option value="not_interested">👎 No Interesado</option>
                            <option value="contracted">⭐ Contratado</option>
                            <option value="closed">🔒 Cerrado</option>
                        </select>
                    </div>
                    <?php if (current_user_can('administrator')): ?>
                    <button type="button" id="send-email-new-contacts" class="button button-primary" 
                            style="background: linear-gradient(135deg, #16a34a, #15803d); border: none; padding: 8px 15px; border-radius: 20px; font-weight: 600; box-shadow: 0 2px 8px rgba(22,163,74,0.3);">
                        <span class="dashicons dashicons-email" style="font-size: 16px;"></span> Enviar Email a Contactos "Nuevo"
                    </button>
                    <?php /* 
                    // Botón de regenerar facturas desactivado
                    <button type="button" id="regenerate-invoices-qr" class="button button-primary" 
                            style="background: linear-gradient(135deg, #dc3545, #c82333); border: none; padding: 8px 15px; border-radius: 20px; font-weight: 600; box-shadow: 0 2px 8px rgba(220,53,69,0.3); margin-left: 10px;">
                        <span class="dashicons dashicons-update" style="font-size: 16px;"></span> Regenerar Facturas con QR
                    </button>
                    */ ?>
                    <?php endif; ?>
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
                                        <option value="follow_up" <?php selected($contact->status, 'follow_up'); ?>>📅 Seguimiento</option>
                                        <option value="interested" <?php selected($contact->status, 'interested'); ?>>💡 Interesado</option>
                                        <option value="not_interested" <?php selected($contact->status, 'not_interested'); ?>>👎 No Interesado</option>
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
        
        .status-selector[data-original-value="follow_up"] {
            background-color: #fff9c4;
            border-color: #fbc02d;
        }
        
        .status-selector[data-original-value="interested"] {
            background-color: #f3e5f5;
            border-color: #9c27b0;
        }
        
        .status-selector[data-original-value="not_interested"] {
            background-color: #ffebee;
            border-color: #f44336;
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
            // Confirmación especial para estado "Contratado" o "Interesado"
            if (status === 'contracted' || status === 'interested') {
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
                
                // Mostrar modal de selección de plan
                if (status === 'contracted') {
                    showPlanSelectionModal(id, contactName, contactEmail, selectElement, 'contracted');
                } else {
                    showPlanSelectionModal(id, contactName, contactEmail, selectElement, 'interested');
                }
            }
            // Para otros estados, proceder normalmente con confirmación simple
            else {
                var statusNames = {
                    'new': '🆕 Nuevo',
                    'contacted': '📞 Contactado',
                    'follow_up': '📅 Seguimiento',
                    'interested': '💰 Interesado',
                    'not_interested': '👎 No Interesado',
                    'closed': '🔒 Cerrado'
                };
                
                var statusName = statusNames[status] || status;
                var confirmMessage = "¿Confirmas cambiar el estado a: " + statusName + "?";
                
                if (confirm(confirmMessage)) {
                    window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=update_status'); ?>&id=' + id + '&status=' + status + '&_wpnonce=<?php echo wp_create_nonce('update_status'); ?>';
                } else {
                    // Revertir el selector
                    event.target.value = event.target.getAttribute('data-original-value') || 'new';
                    return false;
                }
            }
        }
        
        // Función para mostrar modal de selección de plan cuando se marca como contratado o interesado
        window.showPlanSelectionModal = function(contactId, contactName, contactEmail, selectElement, mode) {
            // mode puede ser 'contracted' o 'interested'
            mode = mode || 'contracted';
            
            var isQuotation = (mode === 'interested');
            var headerTitle = isQuotation ? '💰 Generar Cotización' : '💼 Plan Contratado';
            var headerSubtitle = isQuotation ? 'Selecciona los servicios a cotizar' : 'Selecciona el plan que contrató el cliente';
            var headerGradient = isQuotation ? 'linear-gradient(135deg, #ff9800, #06d6a0)' : 'linear-gradient(135deg, #1e3a8a, #06d6a0)';
            var importantText = isQuotation ? 
                'Al confirmar, se generará una cotización con validez de 3 días y se enviará por correo electrónico al contacto.' :
                'Al confirmar, se generará una factura automática y se enviará por correo electrónico al cliente junto con un mensaje de bienvenida profesional.';
            var buttonText = isQuotation ? '✅ Generar Cotización' : '✅ Confirmar Contrato';
            var buttonOnclick = isQuotation ? 
                `confirmQuotationSelection(${contactId}, '${contactName}', '${contactEmail}')` :
                `confirmPlanSelection(${contactId}, '${contactName}', '${contactEmail}')`;
            
            // Crear modal con selector de planes
            var modalHTML = `
                <div id="plan-selection-modal" style="position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); display: flex; justify-content: center; align-items: center; animation: fadeIn 0.3s;">
                    <div style="background: linear-gradient(135deg, #ffffff, #f0f9ff); padding: 0; border-radius: 20px; width: 90%; max-width: 600px; max-height: 90%; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s;">
                        <div style="background: ${headerGradient}; color: white; padding: 30px; text-align: center; border-radius: 20px 20px 0 0;">
                            <h2 style="margin: 0 0 10px 0; font-size: 2em;">${headerTitle}</h2>
                            <p style="margin: 0; opacity: 0.9; font-size: 1.1em;">${headerSubtitle}</p>
                        </div>
                        
                        <div style="padding: 30px;">
                            <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid #06d6a0;">
                                <h3 style="color: #1e3a8a; margin: 0 0 15px 0; font-size: 1.2em;">👤 Contacto</h3>
                                <p style="margin: 5px 0; color: #555;"><strong>Nombre:</strong> ${contactName}</p>
                                <p style="margin: 5px 0; color: #555;"><strong>Email:</strong> ${contactEmail}</p>
                            </div>
                            
                            <div style="background: #fef3c7; padding: 20px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid #f59e0b;">
                                <h3 style="color: #92400e; margin: 0 0 10px 0; font-size: 1.1em;">⚠️ Importante</h3>
                                <p style="margin: 0; color: #78350f; font-size: 0.95em;">
                                    ${importantText}
                                </p>
                            </div>
                            
                            <div style="margin-bottom: 25px;">
                                <label style="display: block; color: #1e3a8a; font-weight: 600; margin-bottom: 10px; font-size: 1.1em;">
                                    📊 Selecciona los Planes (puedes seleccionar varios):
                                </label>
                                
                                <!-- Instrucciones visuales para selección múltiple -->
                                <div style="background: #fff3cd; border: 2px dashed #ffc107; border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                                    <p style="margin: 0; font-size: 0.95em; color: #856404; line-height: 1.6;">
                                        <strong>💡 Para seleccionar MÚLTIPLES planes:</strong><br>
                                        • <strong>Windows:</strong> Mantén presionado <kbd style="background: white; padding: 2px 6px; border-radius: 3px; border: 1px solid #ccc;">CTRL</kbd> y haz clic en cada plan<br>
                                        • <strong>Mac:</strong> Mantén presionado <kbd style="background: white; padding: 2px 6px; border-radius: 3px; border: 1px solid #ccc;">⌘ CMD</kbd> y haz clic en cada plan<br>
                                        • Los planes seleccionados quedarán <span style="background: #0096C7; color: white; padding: 2px 6px; border-radius: 3px;">resaltados en azul</span>
                                    </p>
                                </div>
                                
                                <select id="plan-selector" multiple size="5" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 1em; background: white; color: #333; cursor: pointer; transition: all 0.3s;">
                                    <?php
                                    global $wpdb;
                                    $plans = $wpdb->get_results("SELECT id, name, price_clp, price_usd, description FROM {$wpdb->prefix}automatiza_services WHERE status = 'active' AND (price_clp > 0 OR price_usd > 0) ORDER BY id ASC");
                                    foreach ($plans as $plan) {
                                        echo '<option value="' . $plan->id . '" data-price-clp="' . $plan->price_clp . '" data-price-usd="' . $plan->price_usd . '">' . 
                                             esc_html($plan->name) . ' - $' . number_format($plan->price_usd, 2) . ' USD / $' . number_format($plan->price_clp, 0, ',', '.') . ' CLP</option>';
                                    }
                                    ?>
                                </select>
                                
                                <!-- Contador de planes seleccionados -->
                                <div id="selected-count" style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-radius: 6px; text-align: center; font-weight: 600; color: #1976d2; display: none;">
                                    <span id="count-number">0</span> plan(es) seleccionado(s)
                                </div>
                            </div>
                            
                            <div id="plan-preview" style="display: none; background: linear-gradient(135deg, #f0f9ff, #e0f2fe); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #06d6a0;">
                                <h4 style="color: #1e3a8a; margin: 0 0 10px 0;">Plan seleccionado:</h4>
                                <p id="plan-name-preview" style="font-size: 1.3em; color: #06d6a0; font-weight: bold; margin: 5px 0;"></p>
                                <p id="plan-price-preview" style="font-size: 1.5em; color: #1e3a8a; font-weight: bold; margin: 5px 0;"></p>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 30px;">
                                <button onclick="${buttonOnclick}" 
                                        style="flex: 1; background: linear-gradient(135deg, #06d6a0, #05c29a); color: white; border: none; padding: 15px 30px; border-radius: 25px; font-size: 1.1em; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(6, 214, 160, 0.3); transition: all 0.3s;">
                                    ${buttonText}
                                </button>
                                <button onclick="cancelPlanSelection()" 
                                        style="flex: 1; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: none; padding: 15px 30px; border-radius: 25px; font-size: 1.1em; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); transition: all 0.3s;">
                                    ❌ Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(50px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                    #plan-selector:focus {
                        outline: none;
                        border-color: #06d6a0;
                        box-shadow: 0 0 0 3px rgba(6, 214, 160, 0.1);
                    }
                    button:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
                    }
                </style>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Agregar evento al selector de planes (soporte múltiple)
            document.getElementById('plan-selector').addEventListener('change', function() {
                var preview = document.getElementById('plan-preview');
                var selectedOptions = Array.from(this.selectedOptions);
                var countDiv = document.getElementById('selected-count');
                var countNumber = document.getElementById('count-number');
                
                // Actualizar contador de planes seleccionados
                if (selectedOptions.length > 0) {
                    countDiv.style.display = 'block';
                    countNumber.textContent = selectedOptions.length;
                } else {
                    countDiv.style.display = 'none';
                }
                
                if (selectedOptions.length > 0) {
                    var planNames = [];
                    var totalUsd = 0;
                    var totalClp = 0;
                    
                    selectedOptions.forEach(function(option) {
                        var planName = option.textContent.split(' - ')[0];
                        var priceUsd = parseFloat(option.getAttribute('data-price-usd'));
                        var priceClp = parseInt(option.getAttribute('data-price-clp'));
                        
                        planNames.push(planName);
                        totalUsd += priceUsd;
                        totalClp += priceClp;
                    });
                    
                    var planText = selectedOptions.length === 1 
                        ? planNames[0] 
                        : planNames.join(' + ');
                    
                    document.getElementById('plan-name-preview').textContent = planText;
                    document.getElementById('plan-price-preview').textContent = 
                        '$' + totalUsd.toFixed(2) + ' USD / $' + totalClp.toLocaleString('es-CL') + ' CLP';
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            });
            
            // Guardar referencia al selector original para poder revertir
            window.originalSelectElement = selectElement;
        };
        
        // Función para confirmar la selección del plan (soporte múltiple)
        window.confirmPlanSelection = function(contactId, contactName, contactEmail) {
            var planSelector = document.getElementById('plan-selector');
            var selectedOptions = Array.from(planSelector.selectedOptions);
            var planIds = selectedOptions.map(opt => opt.value);
            
            if (planIds.length === 0) {
                alert('❌ Por favor selecciona al menos un plan antes de continuar.');
                return;
            }
            
            // Convertir array de IDs a string separado por comas
            var planId = planIds.join(',');
            
            // Cerrar modal
            document.getElementById('plan-selection-modal').remove();
            
            // Mostrar indicador de procesamiento
            if (window.originalSelectElement) {
                window.originalSelectElement.disabled = true;
                window.originalSelectElement.style.background = '#ffc107';
                window.originalSelectElement.style.color = '#000';
                window.originalSelectElement.style.fontWeight = 'bold';
                
                var processingOption = document.createElement('option');
                processingOption.value = 'processing';
                processingOption.text = '⏳ Procesando... Por favor espera';
                processingOption.selected = true;
                window.originalSelectElement.innerHTML = '';
                window.originalSelectElement.appendChild(processingOption);
                
                var statusCell = window.originalSelectElement.closest('td');
                var processingIndicator = document.createElement('div');
                processingIndicator.style.cssText = 'background: #fff3cd; padding: 10px; border-radius: 8px; font-size: 12px; margin-top: 8px; border: 1px solid #ffc107; text-align: center;';
                processingIndicator.innerHTML = '⏳ Moviendo a Clientes, generando factura y enviando correo...';
                statusCell.appendChild(processingIndicator);
            }
            
            // Redirigir con el plan_id
            setTimeout(function() {
                window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=update_status'); ?>&id=' + contactId + '&status=contracted&plan_id=' + planId + '&_wpnonce=<?php echo wp_create_nonce('update_status'); ?>';
            }, 1000);
        };
        
        // Función para confirmar selección de planes para COTIZACIÓN (estado interested)
        window.confirmQuotationSelection = function(contactId, contactName, contactEmail) {
            var planSelector = document.getElementById('plan-selector');
            var selectedOptions = Array.from(planSelector.selectedOptions);
            var planIds = selectedOptions.map(opt => opt.value);
            
            if (planIds.length === 0) {
                alert('❌ Por favor selecciona al menos un plan antes de continuar.');
                return;
            }
            
            // Convertir array de IDs a string separado por comas
            var planId = planIds.join(',');
            
            // Cerrar modal
            document.getElementById('plan-selection-modal').remove();
            
            // Mostrar indicador de procesamiento
            if (window.originalSelectElement) {
                window.originalSelectElement.disabled = true;
                window.originalSelectElement.style.background = '#ff9800';
                window.originalSelectElement.style.color = '#fff';
                window.originalSelectElement.style.fontWeight = 'bold';
                
                var processingOption = document.createElement('option');
                processingOption.value = 'processing';
                processingOption.text = '⏳ Procesando... Por favor espera';
                processingOption.selected = true;
                window.originalSelectElement.innerHTML = '';
                window.originalSelectElement.appendChild(processingOption);
                
                var statusCell = window.originalSelectElement.closest('td');
                var processingIndicator = document.createElement('div');
                processingIndicator.style.cssText = 'background: #fff3cd; padding: 10px; border-radius: 8px; font-size: 12px; margin-top: 8px; border: 1px solid #ff9800; text-align: center;';
                processingIndicator.innerHTML = '⏳ Generando cotización y enviando correo...';
                statusCell.appendChild(processingIndicator);
            }
            
            // Redirigir con el plan_id al estado 'interested'
            setTimeout(function() {
                window.location.href = '<?php echo admin_url('admin.php?page=automatiza-tech-contacts&action=update_status'); ?>&id=' + contactId + '&status=interested&plan_id=' + planId + '&_wpnonce=<?php echo wp_create_nonce('update_status'); ?>';
            }, 1000);
        };
        
        // Función para cancelar la selección
        window.cancelPlanSelection = function() {
            document.getElementById('plan-selection-modal').remove();
            
            // Revertir el selector al valor original
            if (window.originalSelectElement) {
                window.originalSelectElement.value = window.originalSelectElement.getAttribute('data-original-value') || 'new';
            }
        };
        
        // Nueva función para mostrar detalles del contacto en modal mejorado (GLOBAL)
        window.showContactDetails = function(id) {
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
        
        // Función para mostrar detalles del cliente en modal (GLOBAL)
        window.showClientDetailsModal = function(id) {
            console.log('showClientDetailsModal called with id:', id);
            
            // Verificar que jQuery esté disponible
            if (typeof jQuery === 'undefined') {
                alert('Error: jQuery no está disponible');
                return;
            }
            
            // Obtener la URL de AJAX
            var ajaxUrl = (typeof automatizaTechAjax !== 'undefined' && automatizaTechAjax.ajaxurl) 
                         ? automatizaTechAjax.ajaxurl 
                         : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>');
            
            console.log('Using AJAX URL:', ajaxUrl);
            
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
            jQuery.post(ajaxUrl, {
                action: 'get_client_details',
                id: id,
                nonce: '<?php echo wp_create_nonce('get_client_details'); ?>'
            }, function(response) {
                console.log('AJAX response:', response);
                
                // Remover modal de carga
                var loadingModal = document.getElementById('client-details-modal');
                if (loadingModal) {
                    loadingModal.remove();
                }
                
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
                    console.error('Error from server:', response.data);
                    alert('Error al cargar los detalles del cliente: ' + response.data);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX request failed:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                
                // Remover modal de carga en caso de error
                var loadingModal = document.getElementById('client-details-modal');
                if (loadingModal) {
                    loadingModal.remove();
                }
                alert('Error de conexión al cargar los detalles del cliente. Por favor, revisa la consola para más detalles.');
            });
        }
        
        // Funciones para cerrar modales de detalles (GLOBALES)
        window.closeContactDetailsModal = function() {
            var modal = document.getElementById('contact-details-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        window.closeClientDetailsModal = function() {
            var modal = document.getElementById('client-details-modal');
            if (modal) {
                modal.remove();
            }
        }
        
        // Función de búsqueda asíncrona para contactos (GLOBAL)
        window.searchContacts = function(searchTerm) {
            var ajaxUrl = (typeof automatizaTechAjax !== 'undefined' && automatizaTechAjax.ajaxurl) 
                         ? automatizaTechAjax.ajaxurl 
                         : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>');
            jQuery.post(ajaxUrl, {
                action: 'search_contacts',
                search: searchTerm,
                nonce: '<?php echo wp_create_nonce('search_contacts'); ?>'
            }, function(response) {
                if (response.success) {
                    updateContactsTable(response.data, searchTerm);
                }
            });
        }
        
        // Función de búsqueda asíncrona para clientes (GLOBAL)
        window.searchClients = function(searchTerm) {
            var ajaxUrl = (typeof automatizaTechAjax !== 'undefined' && automatizaTechAjax.ajaxurl) 
                         ? automatizaTechAjax.ajaxurl 
                         : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>');
            jQuery.post(ajaxUrl, {
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
                    html += '<a href="#" onclick="showClientDetailsModal(' + client.id + '); return false;" class="button button-small view-client-btn" style="background: linear-gradient(135deg, #0073aa, #005a87); color: white; border: none; padding: 6px 12px; border-radius: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; box-shadow: 0 2px 5px rgba(0,115,170,0.3); margin-right: 3px;" title="Ver detalles completos del cliente">👁️ Ver</a>';
                    html += '<a href="#" onclick="editClient(' + client.id + ', this); return false;" class="button button-small edit-client-btn" style="background: linear-gradient(135deg, #72aee6, #2271b1); color: white; border: none; font-weight: 600; padding: 6px 12px; border-radius: 20px; text-decoration: none; cursor: pointer; display: inline-block; transition: all 0.3s; box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3); margin-right: 3px;" data-client-id="' + client.id + '" data-client-name="' + escapeHtml(client.name) + '" data-client-email="' + escapeHtml(client.email) + '" data-client-company="' + escapeHtml(client.company || '') + '" data-client-phone="' + escapeHtml(client.phone || '') + '" data-client-value="' + client.contract_value + '" data-client-type="' + escapeHtml(client.project_type || '') + '" data-client-status="' + client.contract_status + '" data-client-notes="' + escapeHtml(client.notes || '') + '" title="Editar datos del cliente">✏️ Editar</a>';
                    html += '<a href="<?php echo admin_url('admin.php?page=automatiza-tech-clients&action=delete_client&id='); ?>' + client.id + '&_wpnonce=<?php echo wp_create_nonce('delete_client'); ?>" class="button button-small delete-client-btn" onclick="return confirmDeleteClient(this)" style="margin-right: 3px;">🗑️ Eliminar</a>';
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
                    var statusFilter = document.getElementById('status-filter');
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    if (statusFilter) {
                        statusFilter.value = '';
                    }
                    filterContacts('', '');
                });
            }
            
            // Event listener para filtro por estado
            var statusFilter = document.getElementById('status-filter');
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    var searchInput = document.getElementById('contact-search');
                    var searchTerm = searchInput ? searchInput.value.trim() : '';
                    var statusValue = this.value;
                    filterContacts(searchTerm, statusValue);
                });
            }
            
            // También actualizar la búsqueda para incluir el filtro
            if (contactSearchInput) {
                contactSearchInput.removeEventListener('input', contactSearchInput._handler);
                contactSearchInput._handler = function() {
                    clearTimeout(contactSearchTimeout);
                    var searchTerm = this.value.trim();
                    var statusFilter = document.getElementById('status-filter');
                    var statusValue = statusFilter ? statusFilter.value : '';
                    
                    contactSearchTimeout = setTimeout(function() {
                        filterContacts(searchTerm, statusValue);
                    }, 300);
                };
                contactSearchInput.addEventListener('input', contactSearchInput._handler);
            }
            
            // Función para filtrar contactos por búsqueda y estado
            window.filterContacts = function(searchTerm, status) {
                var ajaxUrl = (typeof automatizaTechAjax !== 'undefined' && automatizaTechAjax.ajaxurl) 
                             ? automatizaTechAjax.ajaxurl 
                             : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>');
                jQuery.post(ajaxUrl, {
                    action: 'filter_contacts',
                    search: searchTerm,
                    status: status,
                    nonce: '<?php echo wp_create_nonce('filter_contacts'); ?>'
                }, function(response) {
                    if (response.success) {
                        updateContactsTable(response.data, searchTerm);
                    }
                });
            }
            
            // Event listener para enviar email a contactos nuevos
            var sendEmailBtn = document.getElementById('send-email-new-contacts');
            if (sendEmailBtn) {
                sendEmailBtn.addEventListener('click', function() {
                    if (!confirm('¿Estás seguro de enviar un correo a todos los contactos con estado "Nuevo"? Esta acción enviará un email profesional con información de los planes de Automatiza Tech.')) {
                        return;
                    }
                    
                    var btn = this;
                    var originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> Enviando correos...';
                    
                    var ajaxUrl = (typeof automatizaTechAjax !== 'undefined' && automatizaTechAjax.ajaxurl) 
                                 ? automatizaTechAjax.ajaxurl 
                                 : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>');
                    
                    jQuery.post(ajaxUrl, {
                        action: 'send_email_to_new_contacts',
                        nonce: '<?php echo wp_create_nonce('send_email_new_contacts'); ?>'
                    }, function(response) {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        
                        if (response.success) {
                            // Mostrar mensaje
                            var message = typeof response.data === 'object' ? response.data.message : response.data;
                            alert('✅ ' + message);
                            
                            // Recargar página si el servidor lo indica
                            if (typeof response.data === 'object' && response.data.reload === true) {
                                location.reload();
                            }
                        } else {
                            alert('❌ Error: ' + response.data);
                        }
                    }).fail(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        alert('❌ Error de conexión al enviar los correos');
                    });
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
                    <?php /* 
                    // Botón de regenerar facturas desactivado
                    if (current_user_can('administrator')): ?>
                    <button type="button" onclick="regenerateAllInvoicesQR()" class="button button-primary" style="margin-left: 10px; background: linear-gradient(135deg, #06d6a0, #059f7f); border: none; box-shadow: 0 2px 5px rgba(6, 214, 160, 0.3);">
                        <span class="dashicons dashicons-update"></span> Regenerar QR de Facturas
                    </button>
                    <?php endif;
                    */ ?>
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
                        <th style="text-align: center; width: 80px;">📄 Factura</th>
                        <th style="text-align: center; width: 70px;">🗑️ Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 20px;">
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
                                    <a href="#" onclick="showClientDetailsModal(<?php echo $client->id; ?>); return false;" 
                                       class="button button-small view-client-btn"
                                       style="background: linear-gradient(135deg, #0073aa, #005a87); color: white; border: none; padding: 6px 12px; border-radius: 15px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; box-shadow: 0 2px 5px rgba(0,115,170,0.3); transition: all 0.3s ease;"
                                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,115,170,0.4)';"
                                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(0,115,170,0.3)';"
                                       title="Ver detalles completos del cliente">
                                       👁️ Ver
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
                                
                                <!-- Descargar Factura -->
                                <td style="text-align: center;">
                                    <?php
                                    // Verificar si existe factura para este cliente
                                    $invoice_number = 'AT-' . date('Ymd', strtotime($client->contracted_at)) . '-' . str_pad($client->id, 4, '0', STR_PAD_LEFT);
                                    $invoices_table = $wpdb->prefix . 'automatiza_tech_invoices';
                                    $invoice = $wpdb->get_row($wpdb->prepare(
                                        "SELECT id, invoice_number FROM {$invoices_table} WHERE invoice_number = %s AND status = 'active'",
                                        $invoice_number
                                    ));
                                    
                                    if ($invoice): ?>
                                        <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                            <!-- Botón Ver PDF -->
                                            <a href="<?php echo admin_url('admin-ajax.php?action=download_invoice&invoice_number=' . urlencode($invoice->invoice_number)); ?>" 
                                               target="_blank"
                                               class="button button-small view-invoice-btn"
                                               style="background: linear-gradient(135deg, #1e3a8a, #1e40af); color: white; border: none; padding: 6px 10px; border-radius: 15px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; box-shadow: 0 2px 5px rgba(30,58,138,0.3); transition: all 0.3s ease;"
                                               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(30,58,138,0.4)';"
                                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(30,58,138,0.3)';"
                                               title="Ver PDF de factura <?php echo esc_attr($invoice->invoice_number); ?>">
                                               👁️ Ver
                                            </a>
                                            
                                            <!-- Botón Descargar -->
                                            <a href="<?php echo admin_url('admin-ajax.php?action=download_invoice&invoice_number=' . urlencode($invoice->invoice_number)); ?>" target="_blank" 
                                               class="button button-small download-invoice-btn"
                                               style="background: linear-gradient(135deg, #46b450, #00a32a); color: white; border: none; padding: 6px 10px; border-radius: 15px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; box-shadow: 0 2px 5px rgba(70,180,80,0.3); transition: all 0.3s ease;"
                                               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(70,180,80,0.4)';"
                                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 5px rgba(70,180,80,0.3)';"
                                               title="Descargar factura <?php echo esc_attr($invoice->invoice_number); ?>">
                                               📥 Descargar
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px; font-style: italic;" title="No hay factura generada para este cliente">
                                            Sin factura
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
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
        
        /**
         * Regenerar QR de todas las facturas
         */
        function regenerateAllInvoicesQR() {
            if (!confirm('🔄 ¿Regenerar el código QR de todas las facturas?\n\nEsto actualizará todas las facturas existentes para que tengan el código QR correcto con la URL de validación.\n\n⚠️ Este proceso puede tardar varios segundos dependiendo de la cantidad de facturas.\n\n¿Deseas continuar?')) {
                return;
            }
            
            // Mostrar indicador de carga
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin" style="animation: rotation 1s infinite linear;"></span> Regenerando...';
            btn.disabled = true;
            
            // Agregar animación de rotación
            const style = document.createElement('style');
            style.innerHTML = '@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } } .spin { display: inline-block; }';
            document.head.appendChild(style);
            
            // Realizar la petición AJAX
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'regenerate_all_invoices_qr',
                    nonce: '<?php echo wp_create_nonce("regenerate_invoices_qr"); ?>'
                },
                success: function(response) {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    
                    if (response.success) {
                        alert('✅ Éxito\n\n' + response.data.message + '\n\n' + 
                              '📊 Facturas procesadas: ' + response.data.processed + '\n' +
                              '❌ Errores: ' + response.data.errors);
                        
                        // Recargar la página para mostrar los cambios
                        if (response.data.processed > 0) {
                            location.reload();
                        }
                    } else {
                        alert('❌ Error\n\n' + (response.data.message || 'Error desconocido al regenerar las facturas.'));
                    }
                },
                error: function(xhr, status, error) {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    alert('❌ Error de conexión\n\nNo se pudo completar la operación. Por favor, intenta nuevamente.\n\nError: ' + error);
                }
            });
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