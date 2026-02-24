<?php
/**
 * Automatiza Tech - Módulo de Bóveda de Credenciales
 * 
 * Sistema seguro para almacenar credenciales técnicas de la empresa:
 * - Accesos a servidores
 * - Cuentas de dominio
 * - Credenciales FTP/SFTP
 * - Cuentas de correo
 * - Flujos N8N
 * - APIs y servicios externos
 * 
 * SEGURIDAD:
 * - Todas las contraseñas se encriptan con AES-256-CBC
 * - Se requiere confirmación de password de admin para revelar
 * - Logs de acceso a credenciales sensibles
 */

if (!defined('ABSPATH')) {
    exit;
}

class AutomatizaTech_Credentials_Vault {
    
    private static $instance = null;
    private $table_name;
    private $logs_table;
    private $encryption_key;
    
    // Categorías de credenciales
    const CATEGORIES = [
        'server' => ['icon' => '🖥️', 'label' => 'Servidores', 'color' => '#1e3a8a'],
        'domain' => ['icon' => '🌐', 'label' => 'Dominios', 'color' => '#059669'],
        'hosting' => ['icon' => '☁️', 'label' => 'Hosting', 'color' => '#7c3aed'],
        'ftp' => ['icon' => '📁', 'label' => 'FTP/SFTP', 'color' => '#d97706'],
        'database' => ['icon' => '🗄️', 'label' => 'Bases de Datos', 'color' => '#dc2626'],
        'email' => ['icon' => '📧', 'label' => 'Cuentas de Correo', 'color' => '#0891b2'],
        'n8n' => ['icon' => '🔄', 'label' => 'N8N / Automatizaciones', 'color' => '#ea580c'],
        'api' => ['icon' => '🔌', 'label' => 'APIs', 'color' => '#4f46e5'],
        'social' => ['icon' => '📱', 'label' => 'Redes Sociales', 'color' => '#db2777'],
        'payment' => ['icon' => '💳', 'label' => 'Pasarelas de Pago', 'color' => '#16a34a'],
        'analytics' => ['icon' => '📊', 'label' => 'Analytics / Tracking', 'color' => '#f59e0b'],
        'ai' => ['icon' => '🤖', 'label' => 'IA / OpenAI / Claude', 'color' => '#8b5cf6'],
        'whatsapp' => ['icon' => '💬', 'label' => 'WhatsApp / Mensajería', 'color' => '#22c55e'],
        'google' => ['icon' => '🔵', 'label' => 'Google Services', 'color' => '#4285f4'],
        'wordpress' => ['icon' => '📝', 'label' => 'WordPress', 'color' => '#21759b'],
        'other' => ['icon' => '🔐', 'label' => 'Otros', 'color' => '#6b7280']
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'automatiza_credentials_vault';
        $this->logs_table = $wpdb->prefix . 'automatiza_credentials_logs';
        
        // Generar clave de encriptación basada en AUTH_KEY de WordPress
        $this->encryption_key = $this->derive_encryption_key();
        
        // Crear tablas
        add_action('init', array($this, 'create_tables'));
        
        // Agregar menú de admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_vault_get_credentials', array($this, 'ajax_get_credentials'));
        add_action('wp_ajax_vault_save_credential', array($this, 'ajax_save_credential'));
        add_action('wp_ajax_vault_delete_credential', array($this, 'ajax_delete_credential'));
        add_action('wp_ajax_vault_reveal_password', array($this, 'ajax_reveal_password'));
        add_action('wp_ajax_vault_verify_admin', array($this, 'ajax_verify_admin'));
        add_action('wp_ajax_vault_get_logs', array($this, 'ajax_get_logs'));
    }
    
    /**
     * Derivar clave de encriptación segura
     */
    private function derive_encryption_key() {
        // Usar AUTH_KEY + SECURE_AUTH_KEY de WordPress como base
        $base = defined('AUTH_KEY') ? AUTH_KEY : 'automatiza-tech-default-key';
        $salt = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'automatiza-tech-salt';
        
        // Derivar clave de 256 bits usando PBKDF2
        return hash_pbkdf2('sha256', $base, $salt, 10000, 32, true);
    }
    
    /**
     * Encriptar contraseña
     */
    public function encrypt_password($plaintext) {
        if (empty($plaintext)) return '';
        
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Combinar IV + encrypted y codificar en base64
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Desencriptar contraseña
     */
    public function decrypt_password($ciphertext) {
        if (empty($ciphertext)) return '';
        
        $data = base64_decode($ciphertext);
        if ($data === false || strlen($data) < 17) return '';
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Obtener API Key desencriptada de la bóveda por nombre de servicio y categoría.
     * Uso: AutomatizaTech_Credentials_Vault::get_instance()->get_api_key('OpenAI', 'ai')
     *
     * @param string $service_name  Nombre del servicio (ej: 'OpenAI', 'Google Drive')
     * @param string $category      Categoría opcional (ej: 'ai', 'api', 'google')
     * @return string               API Key desencriptada o cadena vacía
     */
    public function get_api_key($service_name, $category = '') {
        global $wpdb;

        $where = "WHERE service_name = %s AND is_active = 1";
        $params = array($service_name);

        if (!empty($category)) {
            $where .= " AND category = %s";
            $params[] = $category;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT api_key_encrypted FROM {$this->table_name} {$where} LIMIT 1",
                $params
            )
        );

        if ($row && !empty($row->api_key_encrypted)) {
            return $this->decrypt_password($row->api_key_encrypted);
        }

        return '';
    }

    /**
     * Obtener contraseña desencriptada de la bóveda por nombre de servicio.
     *
     * @param string $service_name  Nombre del servicio
     * @param string $category      Categoría opcional
     * @return string               Contraseña desencriptada o cadena vacía
     */
    public function get_password($service_name, $category = '') {
        global $wpdb;

        $where = "WHERE service_name = %s AND is_active = 1";
        $params = array($service_name);

        if (!empty($category)) {
            $where .= " AND category = %s";
            $params[] = $category;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT password_encrypted FROM {$this->table_name} {$where} LIMIT 1",
                $params
            )
        );

        if ($row && !empty($row->password_encrypted)) {
            return $this->decrypt_password($row->password_encrypted);
        }

        return '';
    }

    /**
     * Crear tablas necesarias
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de credenciales
        $sql_credentials = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            category varchar(50) NOT NULL DEFAULT 'other',
            service_name varchar(255) NOT NULL,
            description text,
            url varchar(500),
            username varchar(255),
            password_encrypted text,
            api_key_encrypted text,
            api_secret_encrypted text,
            extra_data_encrypted text,
            notes text,
            environment varchar(20) DEFAULT 'production',
            is_active tinyint(1) DEFAULT 1,
            created_by bigint(20) UNSIGNED,
            updated_by bigint(20) UNSIGNED,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY is_active (is_active),
            KEY environment (environment)
        ) $charset_collate;";
        
        // Tabla de logs de acceso
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$this->logs_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            credential_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(50) NOT NULL,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY credential_id (credential_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_credentials);
        dbDelta($sql_logs);
    }
    
    /**
     * Agregar menú de admin
     */
    public function add_admin_menu() {
        // Top-level menu (único visible)
        add_menu_page(
            'Bóveda de Credenciales',
            '🔐 Bóveda Tech',
            'manage_options',
            'automatiza-vault',
            array($this, 'render_admin_page'),
            'dashicons-lock',
            30
        );
        // Ya no se agrega como submenu bajo CRM
    }
    
    /**
     * Registrar acceso a credencial
     */
    private function log_access($credential_id, $action) {
        global $wpdb;
        
        $wpdb->insert(
            $this->logs_table,
            array(
                'credential_id' => $credential_id,
                'user_id' => get_current_user_id(),
                'action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Verificar que el usuario es admin principal
     */
    private function is_main_admin() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        $admin_emails = array(
            'contacto@automatiza.tech',
            'admin@automatiza.tech',
            'info@automatiza.tech'
        );
        
        // El admin principal o usuarios con email autorizado
        return $current_user->ID === 1 || in_array($current_user->user_email, $admin_emails);
    }
    
    /**
     * AJAX: Verificar credenciales de admin
     */
    public function ajax_verify_admin() {
        check_ajax_referer('vault_nonce', 'nonce');
        
        if (!$this->is_main_admin()) {
            wp_send_json_error('No tienes permisos para esta acción');
        }
        
        $password = sanitize_text_field($_POST['password'] ?? '');
        $current_user = wp_get_current_user();
        
        // Verificar contraseña del usuario actual
        if (wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
            // Generar token temporal (válido por 5 minutos)
            $token = wp_generate_password(32, false);
            set_transient('vault_access_' . get_current_user_id(), $token, 300);
            
            wp_send_json_success(array(
                'token' => $token,
                'expires' => 300
            ));
        } else {
            wp_send_json_error('Contraseña incorrecta');
        }
    }
    
    /**
     * Verificar token de acceso temporal
     */
    private function verify_access_token($token) {
        $stored_token = get_transient('vault_access_' . get_current_user_id());
        return $stored_token && $stored_token === $token;
    }
    
    /**
     * AJAX: Obtener lista de credenciales
     */
    public function ajax_get_credentials() {
        check_ajax_referer('vault_nonce', 'nonce');
        
        if (!$this->is_main_admin()) {
            wp_send_json_error('Sin permisos');
        }
        
        global $wpdb;
        
        $category = sanitize_text_field($_POST['category'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $where = "WHERE 1=1";
        $params = array();
        
        if (!empty($category) && $category !== 'all') {
            $where .= " AND category = %s";
            $params[] = $category;
        }
        
        if (!empty($search)) {
            $where .= " AND (service_name LIKE %s OR description LIKE %s OR url LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }
        
        $sql = "SELECT id, category, service_name, description, url, username, 
                       environment, is_active, created_at, updated_at,
                       CASE WHEN password_encrypted != '' THEN 1 ELSE 0 END as has_password,
                       CASE WHEN api_key_encrypted != '' THEN 1 ELSE 0 END as has_api_key
                FROM {$this->table_name} 
                $where 
                ORDER BY category, service_name";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $credentials = $wpdb->get_results($sql, ARRAY_A);
        
        // Agregar info de categoría
        foreach ($credentials as &$cred) {
            $cred['category_info'] = self::CATEGORIES[$cred['category']] ?? self::CATEGORIES['other'];
        }
        
        wp_send_json_success($credentials);
    }
    
    /**
     * AJAX: Guardar credencial
     */
    public function ajax_save_credential() {
        check_ajax_referer('vault_nonce', 'nonce');
        
        if (!$this->is_main_admin()) {
            wp_send_json_error('Sin permisos');
        }
        
        // Verificar token de acceso
        $token = sanitize_text_field($_POST['access_token'] ?? '');
        if (!$this->verify_access_token($token)) {
            wp_send_json_error('Sesión expirada. Por favor, confirma tu contraseña nuevamente.');
        }
        
        global $wpdb;
        
        $id = intval($_POST['id'] ?? 0);
        
        $data = array(
            'category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'service_name' => sanitize_text_field($_POST['service_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'url' => esc_url_raw($_POST['url'] ?? ''),
            'username' => sanitize_text_field($_POST['username'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'environment' => sanitize_text_field($_POST['environment'] ?? 'production'),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_by' => get_current_user_id()
        );
        
        // Encriptar campos sensibles si se proporcionan
        if (!empty($_POST['password'])) {
            $data['password_encrypted'] = $this->encrypt_password($_POST['password']);
        }
        if (!empty($_POST['api_key'])) {
            $data['api_key_encrypted'] = $this->encrypt_password($_POST['api_key']);
        }
        if (!empty($_POST['api_secret'])) {
            $data['api_secret_encrypted'] = $this->encrypt_password($_POST['api_secret']);
        }
        if (!empty($_POST['extra_data'])) {
            $data['extra_data_encrypted'] = $this->encrypt_password($_POST['extra_data']);
        }
        
        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d');
        
        if (isset($data['password_encrypted'])) $format[] = '%s';
        if (isset($data['api_key_encrypted'])) $format[] = '%s';
        if (isset($data['api_secret_encrypted'])) $format[] = '%s';
        if (isset($data['extra_data_encrypted'])) $format[] = '%s';
        
        if ($id > 0) {
            // Actualizar
            $result = $wpdb->update(
                $this->table_name,
                $data,
                array('id' => $id),
                $format,
                array('%d')
            );
            
            $this->log_access($id, 'update');
            $message = 'Credencial actualizada correctamente';
        } else {
            // Insertar
            $data['created_by'] = get_current_user_id();
            $format[] = '%d';
            
            $result = $wpdb->insert($this->table_name, $data, $format);
            $id = $wpdb->insert_id;
            
            $this->log_access($id, 'create');
            $message = 'Credencial creada correctamente';
        }
        
        if ($result === false) {
            wp_send_json_error('Error al guardar: ' . $wpdb->last_error);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'id' => $id
        ));
    }
    
    /**
     * AJAX: Eliminar credencial
     */
    public function ajax_delete_credential() {
        check_ajax_referer('vault_nonce', 'nonce');
        
        if (!$this->is_main_admin()) {
            wp_send_json_error('Sin permisos');
        }
        
        $token = sanitize_text_field($_POST['access_token'] ?? '');
        if (!$this->verify_access_token($token)) {
            wp_send_json_error('Sesión expirada');
        }
        
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('ID inválido');
        }
        
        global $wpdb;
        
        $this->log_access($id, 'delete');
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success('Credencial eliminada');
        } else {
            wp_send_json_error('Error al eliminar');
        }
    }
    
    /**
     * AJAX: Revelar contraseña (requiere token de acceso)
     */
    public function ajax_reveal_password() {
        check_ajax_referer('vault_nonce', 'nonce');
        
        if (!$this->is_main_admin()) {
            wp_send_json_error('Sin permisos');
        }
        
        $token = sanitize_text_field($_POST['access_token'] ?? '');
        if (!$this->verify_access_token($token)) {
            wp_send_json_error('TOKEN_EXPIRED');
        }
        
        $id = intval($_POST['id'] ?? 0);
        $field = sanitize_text_field($_POST['field'] ?? 'password');
        
        $valid_fields = array('password', 'api_key', 'api_secret', 'extra_data');
        if (!in_array($field, $valid_fields)) {
            wp_send_json_error('Campo inválido');
        }
        
        global $wpdb;
        
        $encrypted_field = $field . '_encrypted';
        $encrypted = $wpdb->get_var($wpdb->prepare(
            "SELECT {$encrypted_field} FROM {$this->table_name} WHERE id = %d",
            $id
        ));
        
        if (empty($encrypted)) {
            wp_send_json_error('No hay datos para este campo');
        }
        
        $decrypted = $this->decrypt_password($encrypted);
        
        // Registrar acceso
        $this->log_access($id, 'reveal_' . $field);
        
        wp_send_json_success(array(
            'value' => $decrypted
        ));
    }
    
    /**
     * AJAX: Obtener logs de acceso
     */
    public function ajax_get_logs() {
        check_ajax_referer('vault_nonce', 'nonce');
        
        if (!$this->is_main_admin()) {
            wp_send_json_error('Sin permisos');
        }
        
        global $wpdb;
        
        $credential_id = intval($_POST['credential_id'] ?? 0);
        
        $where = "";
        $params = array();
        
        if ($credential_id > 0) {
            $where = "WHERE l.credential_id = %d";
            $params[] = $credential_id;
        }
        
        $sql = "SELECT l.*, u.display_name as user_name, c.service_name
                FROM {$this->logs_table} l
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
                LEFT JOIN {$this->table_name} c ON l.credential_id = c.id
                $where
                ORDER BY l.created_at DESC
                LIMIT 100";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $logs = $wpdb->get_results($sql, ARRAY_A);
        
        wp_send_json_success($logs);
    }
    
    /**
     * Renderizar página de admin
     */
    public function render_admin_page() {
        if (!$this->is_main_admin()) {
            echo '<div class="wrap"><h1>⛔ Acceso Denegado</h1><p>No tienes permisos para acceder a la bóveda de credenciales.</p></div>';
            return;
        }
        
        $current_user = wp_get_current_user();
        ?>
        <div class="wrap vault-wrap">
            <h1>🔐 Bóveda de Credenciales - Automatiza Tech</h1>
            
            <!-- Overlay de autenticación -->
            <div id="vault-auth-overlay" class="vault-auth-overlay">
                <div class="vault-auth-modal">
                    <div class="vault-auth-header">
                        <span class="vault-lock-icon">🔐</span>
                        <h2>Verificación de Seguridad</h2>
                    </div>
                    <p>Para acceder a la bóveda de credenciales, confirma tu identidad ingresando tu contraseña de WordPress.</p>
                    <div class="vault-auth-user">
                        <span class="dashicons dashicons-admin-users"></span>
                        <strong><?php echo esc_html($current_user->display_name); ?></strong>
                        <small>(<?php echo esc_html($current_user->user_email); ?>)</small>
                    </div>
                    <form id="vault-auth-form">
                        <div class="vault-auth-field">
                            <label for="vault-password">🔑 Contraseña</label>
                            <input type="password" id="vault-password" name="password" required autocomplete="current-password">
                        </div>
                        <div class="vault-auth-actions">
                            <button type="submit" class="button button-primary button-hero">
                                <span class="dashicons dashicons-unlock"></span> Desbloquear Bóveda
                            </button>
                        </div>
                        <div id="vault-auth-error" class="vault-auth-error" style="display:none;"></div>
                    </form>
                    <div class="vault-auth-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <small>Todos los accesos quedan registrados por seguridad.</small>
                    </div>
                </div>
            </div>
            
            <!-- Contenido principal (oculto hasta autenticación) -->
            <div id="vault-main-content" class="vault-main-content" style="display:none;">
                
                <!-- Barra de herramientas -->
                <div class="vault-toolbar">
                    <div class="vault-toolbar-left">
                        <select id="vault-category-filter" class="vault-select">
                            <option value="all">📋 Todas las categorías</option>
                            <?php foreach (self::CATEGORIES as $key => $cat): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo $cat['icon'] . ' ' . $cat['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="vault-search" placeholder="🔍 Buscar credencial..." class="vault-search">
                    </div>
                    <div class="vault-toolbar-right">
                        <button type="button" id="vault-add-new" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span> Nueva Credencial
                        </button>
                        <button type="button" id="vault-show-logs" class="button">
                            <span class="dashicons dashicons-list-view"></span> Ver Logs
                        </button>
                        <span id="vault-session-timer" class="vault-session-timer">⏱️ Sesión: 5:00</span>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="vault-stats">
                    <div class="vault-stat">
                        <span class="vault-stat-icon">🔐</span>
                        <span class="vault-stat-number" id="stat-total">0</span>
                        <span class="vault-stat-label">Total</span>
                    </div>
                    <div class="vault-stat">
                        <span class="vault-stat-icon">✅</span>
                        <span class="vault-stat-number" id="stat-active">0</span>
                        <span class="vault-stat-label">Activas</span>
                    </div>
                    <div class="vault-stat">
                        <span class="vault-stat-icon">🏷️</span>
                        <span class="vault-stat-number" id="stat-categories">0</span>
                        <span class="vault-stat-label">Categorías</span>
                    </div>
                </div>
                
                <!-- Lista de credenciales -->
                <div id="vault-credentials-list" class="vault-credentials-list">
                    <div class="vault-loading">
                        <span class="dashicons dashicons-update spin"></span> Cargando credenciales...
                    </div>
                </div>
            </div>
            
            <!-- Modal de edición -->
            <div id="vault-edit-modal" class="vault-modal" style="display:none;">
                <div class="vault-modal-content">
                    <div class="vault-modal-header">
                        <h2 id="vault-modal-title">Nueva Credencial</h2>
                        <button type="button" class="vault-modal-close">&times;</button>
                    </div>
                    <form id="vault-edit-form">
                        <input type="hidden" name="id" id="cred-id" value="0">
                        
                        <div class="vault-form-grid">
                            <div class="vault-form-group">
                                <label>📁 Categoría *</label>
                                <select name="category" id="cred-category" required>
                                    <?php foreach (self::CATEGORIES as $key => $cat): ?>
                                        <option value="<?php echo esc_attr($key); ?>"><?php echo $cat['icon'] . ' ' . $cat['label']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="vault-form-group">
                                <label>🏷️ Nombre del Servicio *</label>
                                <input type="text" name="service_name" id="cred-service_name" required placeholder="Ej: Servidor Principal, OpenAI API...">
                            </div>
                            
                            <div class="vault-form-group vault-full-width">
                                <label>📝 Descripción</label>
                                <input type="text" name="description" id="cred-description" placeholder="Descripción breve del servicio">
                            </div>
                            
                            <div class="vault-form-group">
                                <label>🌐 URL</label>
                                <input type="url" name="url" id="cred-url" placeholder="https://...">
                            </div>
                            
                            <div class="vault-form-group">
                                <label>🌍 Ambiente</label>
                                <select name="environment" id="cred-environment">
                                    <option value="production">🟢 Producción</option>
                                    <option value="staging">🟡 Staging</option>
                                    <option value="development">🟠 Desarrollo</option>
                                    <option value="testing">🔵 Testing</option>
                                </select>
                            </div>
                        </div>
                        
                        <hr class="vault-divider">
                        <h3>🔑 Credenciales de Acceso</h3>
                        
                        <div class="vault-form-grid">
                            <div class="vault-form-group">
                                <label>👤 Usuario</label>
                                <input type="text" name="username" id="cred-username" placeholder="Usuario o email">
                            </div>
                            
                            <div class="vault-form-group">
                                <label>🔒 Contraseña</label>
                                <div class="vault-password-field">
                                    <input type="password" name="password" id="cred-password" placeholder="••••••••">
                                    <button type="button" class="vault-toggle-password" data-target="cred-password">👁️</button>
                                </div>
                                <small class="vault-hint">Dejar vacío para mantener la actual</small>
                            </div>
                            
                            <div class="vault-form-group">
                                <label>🔑 API Key</label>
                                <div class="vault-password-field">
                                    <input type="password" name="api_key" id="cred-api_key" placeholder="API Key o Token">
                                    <button type="button" class="vault-toggle-password" data-target="cred-api_key">👁️</button>
                                </div>
                            </div>
                            
                            <div class="vault-form-group">
                                <label>🔐 API Secret</label>
                                <div class="vault-password-field">
                                    <input type="password" name="api_secret" id="cred-api_secret" placeholder="API Secret">
                                    <button type="button" class="vault-toggle-password" data-target="cred-api_secret">👁️</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vault-form-group vault-full-width">
                            <label>📋 Datos Adicionales (JSON/Texto)</label>
                            <textarea name="extra_data" id="cred-extra_data" rows="3" placeholder='{"host": "...", "port": "...", "otro": "..."}'></textarea>
                            <small class="vault-hint">Para datos adicionales como host, puerto, configuraciones especiales</small>
                        </div>
                        
                        <div class="vault-form-group vault-full-width">
                            <label>📝 Notas</label>
                            <textarea name="notes" id="cred-notes" rows="2" placeholder="Notas importantes, instrucciones de uso..."></textarea>
                        </div>
                        
                        <div class="vault-form-group">
                            <label class="vault-checkbox-label">
                                <input type="checkbox" name="is_active" id="cred-is_active" value="1" checked>
                                <span>✅ Credencial activa</span>
                            </label>
                        </div>
                        
                        <div class="vault-modal-actions">
                            <button type="button" class="button vault-modal-close">Cancelar</button>
                            <button type="submit" class="button button-primary">💾 Guardar Credencial</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Modal de Logs -->
            <div id="vault-logs-modal" class="vault-modal" style="display:none;">
                <div class="vault-modal-content vault-modal-wide">
                    <div class="vault-modal-header">
                        <h2>📋 Registro de Accesos</h2>
                        <button type="button" class="vault-modal-close">&times;</button>
                    </div>
                    <div id="vault-logs-content" class="vault-logs-content">
                        <div class="vault-loading">Cargando logs...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        /* Variables */
        :root {
            --vault-primary: #d63384;
            --vault-secondary: #1e3a8a;
            --vault-success: #10b981;
            --vault-warning: #f59e0b;
            --vault-danger: #ef4444;
            --vault-bg: #f8fafc;
            --vault-card: #ffffff;
            --vault-border: #e2e8f0;
            --vault-text: #1e293b;
            --vault-text-light: #64748b;
        }
        
        .vault-wrap {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        /* Auth Overlay */
        .vault-auth-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.95), rgba(214, 51, 132, 0.95));
            z-index: 100000;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .vault-auth-modal {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 90%;
            text-align: center;
        }
        
        .vault-auth-header {
            margin-bottom: 20px;
        }
        
        .vault-lock-icon {
            font-size: 64px;
            display: block;
            margin-bottom: 15px;
        }
        
        .vault-auth-header h2 {
            color: var(--vault-secondary);
            margin: 0;
        }
        
        .vault-auth-user {
            background: var(--vault-bg);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .vault-auth-field {
            text-align: left;
            margin-bottom: 20px;
        }
        
        .vault-auth-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--vault-text);
        }
        
        .vault-auth-field input {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--vault-border);
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .vault-auth-field input:focus {
            outline: none;
            border-color: var(--vault-primary);
        }
        
        .vault-auth-actions button {
            width: 100%;
            padding: 15px 30px;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .vault-auth-error {
            background: #fef2f2;
            color: var(--vault-danger);
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .vault-auth-warning {
            margin-top: 20px;
            color: var(--vault-text-light);
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        /* Toolbar */
        .vault-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--vault-card);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .vault-toolbar-left, .vault-toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .vault-select, .vault-search {
            padding: 10px 15px;
            border: 2px solid var(--vault-border);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .vault-search {
            min-width: 250px;
            max-width: 100%;
        }
        
        .vault-session-timer {
            background: var(--vault-warning);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        
        /* Stats */
        .vault-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .vault-stat {
            background: var(--vault-card);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .vault-stat-icon {
            font-size: 32px;
            display: block;
            margin-bottom: 10px;
        }
        
        .vault-stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--vault-secondary);
            display: block;
        }
        
        .vault-stat-label {
            color: var(--vault-text-light);
            font-size: 13px;
        }
        
        /* Credentials List */
        .vault-credentials-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .vault-credential-card {
            background: var(--vault-card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--vault-primary);
            transition: all 0.3s;
            overflow: hidden;
        }
        
        .vault-credential-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .vault-credential-card.inactive {
            opacity: 0.6;
            border-left-color: var(--vault-text-light);
        }
        
        .vault-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .vault-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vault-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .vault-card-name {
            margin: 0;
            font-size: 16px;
            color: var(--vault-text);
        }
        
        .vault-card-category {
            font-size: 12px;
            color: var(--vault-text-light);
        }
        
        .vault-card-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .vault-card-badge.production { background: #dcfce7; color: #166534; }
        .vault-card-badge.staging { background: #fef9c3; color: #854d0e; }
        .vault-card-badge.development { background: #ffedd5; color: #9a3412; }
        .vault-card-badge.testing { background: #dbeafe; color: #1e40af; }
        
        .vault-card-body {
            margin-bottom: 15px;
        }
        
        .vault-card-field {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .vault-card-field-label {
            color: var(--vault-text-light);
            min-width: 70px;
        }
        
        .vault-card-field-value {
            color: var(--vault-text);
            word-break: break-all;
        }
        
        .vault-card-field-value a {
            color: var(--vault-secondary);
        }
        
        .vault-password-mask {
            font-family: monospace;
            letter-spacing: 2px;
        }
        
        .vault-reveal-btn {
            background: var(--vault-bg);
            border: 1px solid var(--vault-border);
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .vault-reveal-btn:hover {
            background: var(--vault-primary);
            color: white;
            border-color: var(--vault-primary);
        }
        
        .vault-card-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 15px;
            border-top: 1px solid var(--vault-border);
        }
        
        .vault-card-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .vault-card-btn.edit {
            background: var(--vault-secondary);
            color: white;
        }
        
        .vault-card-btn.delete {
            background: var(--vault-danger);
            color: white;
        }
        
        .vault-card-btn:hover {
            transform: scale(1.05);
        }
        
        /* Modal */
        .vault-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 100001;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
            overflow-y: auto;
            box-sizing: border-box;
        }
        
        .vault-modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }
        
        .vault-modal-wide {
            max-width: 900px;
        }
        
        .vault-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 2px solid var(--vault-border);
            background: linear-gradient(135deg, var(--vault-secondary), var(--vault-primary));
            border-radius: 16px 16px 0 0;
        }
        
        .vault-modal-header h2 {
            margin: 0;
            color: white;
            font-size: 18px;
        }
        
        .vault-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .vault-modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        #vault-edit-form {
            padding: 25px;
        }
        
        .vault-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .vault-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .vault-form-group.vault-full-width {
            grid-column: 1 / -1;
        }
        
        .vault-form-group label {
            font-weight: 600;
            color: var(--vault-text);
            font-size: 13px;
        }
        
        .vault-form-group input,
        .vault-form-group select,
        .vault-form-group textarea {
            padding: 10px 12px;
            border: 2px solid var(--vault-border);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
            width: 100%;
        }
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .vault-form-group input:focus,
        .vault-form-group select:focus,
        .vault-form-group textarea:focus {
            outline: none;
            border-color: var(--vault-primary);
        }
        
        .vault-password-field {
            display: flex;
            gap: 5px;
        }
        
        .vault-password-field input {
            flex: 1;
        }
        
        .vault-toggle-password {
            padding: 8px 12px;
            border: 2px solid var(--vault-border);
            border-radius: 8px;
            background: var(--vault-bg);
            cursor: pointer;
        }
        
        .vault-hint {
            color: var(--vault-text-light);
            font-size: 11px;
        }
        
        .vault-divider {
            border: none;
            border-top: 2px dashed var(--vault-border);
            margin: 25px 0;
        }
        
        .vault-checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .vault-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--vault-border);
            flex-wrap: wrap;
        }
        
        .vault-modal-actions .button {
            min-width: 120px;
        }
        
        /* Logs */
        .vault-logs-content {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .vault-log-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: var(--vault-bg);
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .vault-log-action {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .vault-log-action.create { background: #dcfce7; color: #166534; }
        .vault-log-action.update { background: #dbeafe; color: #1e40af; }
        .vault-log-action.delete { background: #fee2e2; color: #991b1b; }
        .vault-log-action.reveal_password,
        .vault-log-action.reveal_api_key,
        .vault-log-action.reveal_api_secret,
        .vault-log-action.reveal_extra_data { background: #fef3c7; color: #92400e; }
        
        /* Loading */
        .vault-loading {
            text-align: center;
            padding: 40px;
            color: var(--vault-text-light);
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        
        /* Empty state */
        .vault-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: var(--vault-card);
            border-radius: 12px;
        }
        
        .vault-empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        /* ========================================
           RESPONSIVE STYLES - Mobile First
           ======================================== */
        
        /* Large tablets and small desktops */
        @media (max-width: 1200px) {
            .vault-credentials-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Tablets */
        @media (max-width: 992px) {
            .vault-wrap {
                padding: 10px;
            }
            
            .vault-toolbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .vault-toolbar-left,
            .vault-toolbar-right {
                width: 100%;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .vault-credentials-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .vault-modal-content {
                width: 95%;
                max-width: 600px;
                margin: 20px auto;
            }
        }
        
        /* Mobile landscape and small tablets */
        @media (max-width: 768px) {
            .vault-wrap h1 {
                font-size: 1.5rem;
                text-align: center;
            }
            
            .vault-toolbar {
                flex-direction: column;
                padding: 15px;
                gap: 12px;
            }
            
            .vault-toolbar-left, 
            .vault-toolbar-right {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }
            
            .vault-select,
            .vault-search {
                width: 100%;
                min-width: unset;
            }
            
            .vault-toolbar-right .button {
                width: 100%;
                justify-content: center;
            }
            
            .vault-credentials-list {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .vault-card {
                padding: 18px;
            }
            
            .vault-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .vault-card-category {
                font-size: 11px;
            }
            
            .vault-card-name {
                font-size: 16px;
            }
            
            .vault-card-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .vault-card-actions .button {
                flex: 1;
                justify-content: center;
                padding: 8px 12px;
            }
            
            .vault-form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .vault-form-group.full-width {
                grid-column: span 1;
            }
            
            .vault-auth-overlay {
                padding: 15px;
            }
            
            .vault-auth-modal {
                padding: 25px;
                max-width: 100%;
            }
            
            .vault-auth-header h2 {
                font-size: 1.3rem;
            }
            
            .vault-auth-user {
                flex-direction: column;
                text-align: center;
            }
            
            .vault-modal-content {
                width: 98%;
                margin: 10px auto;
                max-height: 95vh;
            }
            
            .vault-modal-header {
                padding: 15px 20px;
            }
            
            .vault-modal-header h2 {
                font-size: 1.2rem;
            }
            
            .vault-modal-body {
                padding: 20px;
            }
            
            .vault-modal-footer {
                padding: 15px 20px;
                flex-direction: column;
                gap: 10px;
            }
            
            .vault-modal-footer .button {
                width: 100%;
                justify-content: center;
            }
            
            /* Session timer responsive */
            .vault-session-timer {
                font-size: 12px;
                padding: 6px 12px;
            }
            
            /* Stats cards responsive */
            .vault-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .vault-stat-card {
                width: 100%;
            }
            
            /* Logs table responsive */
            .vault-logs-table {
                display: block;
                overflow-x: auto;
                font-size: 12px;
            }
            
            .vault-logs-table th,
            .vault-logs-table td {
                padding: 8px 10px;
                white-space: nowrap;
            }
        }
        
        /* Mobile portrait */
        @media (max-width: 480px) {
            .vault-wrap {
                padding: 5px;
            }
            
            .vault-wrap h1 {
                font-size: 1.3rem;
                padding: 0 10px;
            }
            
            .vault-toolbar {
                padding: 12px;
                margin: 10px 0;
                border-radius: 10px;
            }
            
            .vault-card {
                padding: 15px;
                border-radius: 10px;
            }
            
            .vault-card-icon {
                font-size: 28px;
            }
            
            .vault-card-name {
                font-size: 14px;
            }
            
            .vault-card-field label {
                font-size: 11px;
            }
            
            .vault-card-field span {
                font-size: 12px;
            }
            
            .vault-card-actions .button {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .vault-auth-modal {
                padding: 20px;
                border-radius: 15px;
            }
            
            .vault-lock-icon {
                font-size: 48px;
            }
            
            .vault-auth-header h2 {
                font-size: 1.1rem;
            }
            
            .vault-auth-field input {
                padding: 12px;
            }
            
            .vault-auth-actions .button {
                width: 100%;
            }
            
            .vault-modal-content {
                margin: 5px;
                width: calc(100% - 10px);
                border-radius: 12px;
            }
            
            .vault-modal-header {
                padding: 12px 15px;
            }
            
            .vault-modal-body {
                padding: 15px;
            }
            
            .vault-form-group label {
                font-size: 13px;
            }
            
            .vault-form-group input,
            .vault-form-group select,
            .vault-form-group textarea {
                padding: 10px;
                font-size: 14px;
            }
            
            .vault-password-field .button {
                padding: 10px;
            }
            
            .vault-empty {
                padding: 40px 15px;
            }
            
            .vault-empty-icon {
                font-size: 48px;
            }
        }
        
        /* Touch-friendly improvements for mobile */
        @media (hover: none) and (pointer: coarse) {
            .vault-card-actions .button,
            .vault-toolbar-right .button,
            .vault-modal-footer .button,
            .vault-auth-actions .button {
                min-height: 44px;
            }
            
            .vault-form-group input,
            .vault-form-group select,
            .vault-form-group textarea {
                min-height: 44px;
            }
            
            .vault-password-field .button {
                min-width: 44px;
            }
        }
        
        /* Fix for WordPress admin bar on mobile */
        @media screen and (max-width: 782px) {
            .vault-auth-overlay {
                padding-top: 46px;
            }
            
            .vault-modal-overlay {
                padding-top: 46px;
            }
        }
        
        /* Print styles - hide sensitive content */
        @media print {
            .vault-auth-overlay,
            .vault-modal-overlay,
            .vault-card-actions,
            .vault-toolbar-right,
            .vault-password-field .button {
                display: none !important;
            }
            
            .vault-card-field span {
                filter: blur(5px);
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var vaultNonce = '<?php echo wp_create_nonce('vault_nonce'); ?>';
            var accessToken = null;
            var sessionTimer = null;
            var sessionSeconds = 300;
            
            // Autenticación
            $('#vault-auth-form').on('submit', function(e) {
                e.preventDefault();
                
                var password = $('#vault-password').val();
                var btn = $(this).find('button[type="submit"]');
                var originalText = btn.html();
                
                btn.html('<span class="dashicons dashicons-update spin"></span> Verificando...').prop('disabled', true);
                $('#vault-auth-error').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vault_verify_admin',
                        nonce: vaultNonce,
                        password: password
                    },
                    success: function(response) {
                        if (response.success) {
                            accessToken = response.data.token;
                            sessionSeconds = response.data.expires;
                            
                            $('#vault-auth-overlay').fadeOut(300);
                            $('#vault-main-content').fadeIn(300);
                            
                            startSessionTimer();
                            loadCredentials();
                        } else {
                            $('#vault-auth-error').text(response.data).show();
                            btn.html(originalText).prop('disabled', false);
                        }
                    },
                    error: function() {
                        $('#vault-auth-error').text('Error de conexión').show();
                        btn.html(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Timer de sesión
            function startSessionTimer() {
                updateTimerDisplay();
                sessionTimer = setInterval(function() {
                    sessionSeconds--;
                    updateTimerDisplay();
                    
                    if (sessionSeconds <= 0) {
                        clearInterval(sessionTimer);
                        accessToken = null;
                        alert('⏰ Tu sesión ha expirado por seguridad. Por favor, autentícate nuevamente.');
                        location.reload();
                    }
                    
                    if (sessionSeconds === 60) {
                        $('#vault-session-timer').css('background', '#ef4444');
                    }
                }, 1000);
            }
            
            function updateTimerDisplay() {
                var mins = Math.floor(sessionSeconds / 60);
                var secs = sessionSeconds % 60;
                $('#vault-session-timer').text('⏱️ Sesión: ' + mins + ':' + (secs < 10 ? '0' : '') + secs);
            }
            
            // Extender sesión al interactuar
            $(document).on('click keypress', function() {
                if (accessToken && sessionSeconds < 240) {
                    sessionSeconds = 300;
                    $('#vault-session-timer').css('background', '#f59e0b');
                }
            });
            
            // Cargar credenciales
            function loadCredentials() {
                var category = $('#vault-category-filter').val();
                var search = $('#vault-search').val();
                
                $('#vault-credentials-list').html('<div class="vault-loading"><span class="dashicons dashicons-update spin"></span> Cargando credenciales...</div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vault_get_credentials',
                        nonce: vaultNonce,
                        category: category,
                        search: search
                    },
                    success: function(response) {
                        if (response.success) {
                            renderCredentials(response.data);
                        } else {
                            $('#vault-credentials-list').html('<div class="vault-empty"><div class="vault-empty-icon">❌</div><p>Error al cargar credenciales</p></div>');
                        }
                    }
                });
            }
            
            // Renderizar credenciales
            function renderCredentials(credentials) {
                if (credentials.length === 0) {
                    $('#vault-credentials-list').html('<div class="vault-empty"><div class="vault-empty-icon">🔐</div><h3>No hay credenciales</h3><p>Agrega tu primera credencial usando el botón "Nueva Credencial"</p></div>');
                    updateStats(0, 0, 0);
                    return;
                }
                
                var html = '';
                var categories = new Set();
                var activeCount = 0;
                
                credentials.forEach(function(cred) {
                    categories.add(cred.category);
                    if (cred.is_active == 1) activeCount++;
                    
                    var catInfo = cred.category_info;
                    html += `
                        <div class="vault-credential-card ${cred.is_active == 0 ? 'inactive' : ''}" style="border-left-color: ${catInfo.color};" data-id="${cred.id}">
                            <div class="vault-card-header">
                                <div class="vault-card-title">
                                    <div class="vault-card-icon" style="background: ${catInfo.color}20; color: ${catInfo.color};">${catInfo.icon}</div>
                                    <div>
                                        <h4 class="vault-card-name">${escapeHtml(cred.service_name)}</h4>
                                        <span class="vault-card-category">${catInfo.label}</span>
                                    </div>
                                </div>
                                <span class="vault-card-badge ${cred.environment}">${getEnvironmentLabel(cred.environment)}</span>
                            </div>
                            <div class="vault-card-body">
                                ${cred.description ? `<div class="vault-card-field"><span class="vault-card-field-value">${escapeHtml(cred.description)}</span></div>` : ''}
                                ${cred.url ? `<div class="vault-card-field"><span class="vault-card-field-label">🌐 URL:</span><span class="vault-card-field-value"><a href="${escapeHtml(cred.url)}" target="_blank">${escapeHtml(cred.url)}</a></span></div>` : ''}
                                ${cred.username ? `<div class="vault-card-field"><span class="vault-card-field-label">👤 Usuario:</span><span class="vault-card-field-value">${escapeHtml(cred.username)}</span></div>` : ''}
                                ${cred.has_password == 1 ? `<div class="vault-card-field"><span class="vault-card-field-label">🔒 Pass:</span><span class="vault-card-field-value vault-password-mask">••••••••</span> <button class="vault-reveal-btn" data-id="${cred.id}" data-field="password">👁️ Ver</button></div>` : ''}
                                ${cred.has_api_key == 1 ? `<div class="vault-card-field"><span class="vault-card-field-label">🔑 API Key:</span><span class="vault-card-field-value vault-password-mask">••••••••</span> <button class="vault-reveal-btn" data-id="${cred.id}" data-field="api_key">👁️ Ver</button></div>` : ''}
                            </div>
                            <div class="vault-card-footer">
                                <button class="vault-card-btn edit" data-id="${cred.id}">✏️ Editar</button>
                                <button class="vault-card-btn delete" data-id="${cred.id}">🗑️ Eliminar</button>
                            </div>
                        </div>
                    `;
                });
                
                $('#vault-credentials-list').html(html);
                updateStats(credentials.length, activeCount, categories.size);
            }
            
            function updateStats(total, active, cats) {
                $('#stat-total').text(total);
                $('#stat-active').text(active);
                $('#stat-categories').text(cats);
            }
            
            function getEnvironmentLabel(env) {
                var labels = {
                    'production': '🟢 Producción',
                    'staging': '🟡 Staging',
                    'development': '🟠 Desarrollo',
                    'testing': '🔵 Testing'
                };
                return labels[env] || env;
            }
            
            function escapeHtml(text) {
                if (!text) return '';
                return $('<div>').text(text).html();
            }
            
            // Filtros
            $('#vault-category-filter, #vault-search').on('change keyup', debounce(function() {
                loadCredentials();
            }, 300));
            
            function debounce(func, wait) {
                var timeout;
                return function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(func, wait);
                };
            }
            
            // Nueva credencial
            $('#vault-add-new').on('click', function() {
                $('#vault-modal-title').text('Nueva Credencial');
                $('#vault-edit-form')[0].reset();
                $('#cred-id').val(0);
                $('#cred-is_active').prop('checked', true);
                $('#vault-edit-modal').fadeIn(200);
            });
            
            // Editar credencial
            $(document).on('click', '.vault-card-btn.edit', function() {
                var id = $(this).data('id');
                var card = $(this).closest('.vault-credential-card');
                
                $('#vault-modal-title').text('Editar Credencial');
                $('#cred-id').val(id);
                
                // Cargar datos actuales (simplificado - en producción cargarías via AJAX)
                $('#cred-service_name').val(card.find('.vault-card-name').text());
                
                $('#vault-edit-modal').fadeIn(200);
            });
            
            // Guardar credencial
            $('#vault-edit-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=vault_save_credential&nonce=' + vaultNonce + '&access_token=' + accessToken;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            $('#vault-edit-modal').fadeOut(200);
                            loadCredentials();
                        } else {
                            alert('❌ ' + response.data);
                        }
                    }
                });
            });
            
            // Eliminar credencial
            $(document).on('click', '.vault-card-btn.delete', function() {
                var id = $(this).data('id');
                var name = $(this).closest('.vault-credential-card').find('.vault-card-name').text();
                
                if (confirm('⚠️ ¿Eliminar la credencial "' + name + '"?\n\nEsta acción no se puede deshacer.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'vault_delete_credential',
                            nonce: vaultNonce,
                            access_token: accessToken,
                            id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                loadCredentials();
                            } else {
                                alert('❌ ' + response.data);
                            }
                        }
                    });
                }
            });
            
            // Revelar contraseña
            $(document).on('click', '.vault-reveal-btn', function() {
                var btn = $(this);
                var id = btn.data('id');
                var field = btn.data('field');
                
                btn.text('⏳').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vault_reveal_password',
                        nonce: vaultNonce,
                        access_token: accessToken,
                        id: id,
                        field: field
                    },
                    success: function(response) {
                        if (response.success) {
                            var value = response.data.value;
                            btn.prev('.vault-password-mask').text(value).css('color', '#059669');
                            btn.text('✅ Copiado');
                            
                            // Copiar al portapapeles
                            navigator.clipboard.writeText(value);
                            
                            // Ocultar después de 10 segundos
                            setTimeout(function() {
                                btn.prev('.vault-password-mask').text('••••••••').css('color', '');
                                btn.text('👁️ Ver').prop('disabled', false);
                            }, 10000);
                        } else if (response.data === 'TOKEN_EXPIRED') {
                            alert('⏰ Tu sesión ha expirado. Por favor, autentícate nuevamente.');
                            location.reload();
                        } else {
                            alert('❌ ' + response.data);
                            btn.text('👁️ Ver').prop('disabled', false);
                        }
                    }
                });
            });
            
            // Toggle password en formulario
            $(document).on('click', '.vault-toggle-password', function() {
                var target = $('#' + $(this).data('target'));
                var type = target.attr('type') === 'password' ? 'text' : 'password';
                target.attr('type', type);
                $(this).text(type === 'password' ? '👁️' : '🙈');
            });
            
            // Ver logs
            $('#vault-show-logs').on('click', function() {
                $('#vault-logs-content').html('<div class="vault-loading">Cargando logs...</div>');
                $('#vault-logs-modal').fadeIn(200);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'vault_get_logs',
                        nonce: vaultNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderLogs(response.data);
                        }
                    }
                });
            });
            
            function renderLogs(logs) {
                if (logs.length === 0) {
                    $('#vault-logs-content').html('<p style="text-align:center;">No hay registros de acceso.</p>');
                    return;
                }
                
                var html = '';
                logs.forEach(function(log) {
                    var actionLabel = log.action.replace('_', ' ').replace('reveal ', 'Reveló ');
                    html += `
                        <div class="vault-log-item">
                            <span class="vault-log-action ${log.action}">${actionLabel}</span>
                            <strong>${escapeHtml(log.service_name || 'Eliminado')}</strong>
                            <span>por ${escapeHtml(log.user_name)}</span>
                            <span style="color: #64748b;">${log.created_at}</span>
                            <span style="color: #94a3b8; font-size: 11px;">${escapeHtml(log.ip_address)}</span>
                        </div>
                    `;
                });
                
                $('#vault-logs-content').html(html);
            }
            
            // Cerrar modales
            $(document).on('click', '.vault-modal-close', function() {
                $(this).closest('.vault-modal').fadeOut(200);
            });
            
            $(document).on('click', '.vault-modal', function(e) {
                if ($(e.target).hasClass('vault-modal')) {
                    $(this).fadeOut(200);
                }
            });
            
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.vault-modal:visible').fadeOut(200);
                }
            });
        });
        </script>
        <?php
    }
}

// Inicializar
AutomatizaTech_Credentials_Vault::get_instance();
