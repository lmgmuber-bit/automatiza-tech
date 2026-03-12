<?php
/**
 * Google Drive OAuth Integration para MAXTECH
 * Permite conectar Google Drive usando OAuth 2.0 (sin Service Account)
 * Compatible con cuentas de empresa y personales
 *
 * CONFIGURACIÓN:
 * 1. Ir a Google Cloud Console: https://console.cloud.google.com
 * 2. Crear proyecto o usar existente
 * 3. Habilitar "Google Drive API"
 * 4. Ir a Credenciales > Crear credenciales > ID de cliente de OAuth
 * 5. Tipo: Aplicación web
 * 6. URI de redireccionamiento autorizado: https://tudominio.cl/wp-admin/admin-ajax.php?action=maxtech_drive_oauth_callback
 * 7. Guardar Client ID y Client Secret en opciones de WP
 */

if (!defined('ABSPATH')) exit;

class Google_Drive_OAuth {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $option_token = 'maxtech_drive_oauth_token';
    private $option_refresh = 'maxtech_drive_oauth_refresh';

    public function __construct() {
        $this->client_id = get_option('maxtech_drive_client_id', '');
        $this->client_secret = get_option('maxtech_drive_client_secret', '');
        $this->redirect_uri = admin_url('admin-ajax.php?action=maxtech_drive_oauth_callback');

        add_action('admin_menu', array($this, 'add_admin_menu'), 101);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_maxtech_drive_oauth_start', array($this, 'oauth_start'));
        add_action('wp_ajax_maxtech_drive_oauth_callback', array($this, 'oauth_callback'));
    }

    public function add_admin_menu() {
        // Original submenu (kept for backward compatibility)
        add_submenu_page(
            'crm-automatiza',
            'Google Drive OAuth',
            '☁️ Google Drive OAuth',
            'manage_options',
            'maxtech-google-drive-oauth',
            array($this, 'render_admin_page')
        );
        // Also add under the main CRM menu so it's visible: 'automatiza-crm'
        add_submenu_page(
            'automatiza-crm',
            'Google Drive OAuth',
            '☁️ Google Drive OAuth',
            'manage_options',
            'maxtech-google-drive-oauth',
            array($this, 'render_admin_page')
        );
    }

    public function register_settings() {
        register_setting('maxtech_drive_oauth_settings', 'maxtech_drive_client_id');
        register_setting('maxtech_drive_oauth_settings', 'maxtech_drive_client_secret');
    }

    public function render_admin_page() {
        $token = get_option($this->option_token);
        $refresh = get_option($this->option_refresh);
        ?>
        <div class="wrap">
            <h1>☁️ Google Drive OAuth - MAXTECH</h1>
            <form method="post" action="options.php">
                <?php settings_fields('maxtech_drive_oauth_settings'); ?>
                <table class="form-table">
                    <tr><th>Client ID</th><td><input type="text" name="maxtech_drive_client_id" value="<?php echo esc_attr($this->client_id); ?>" style="width: 400px;"></td></tr>
                    <tr><th>Client Secret</th><td><input type="text" name="maxtech_drive_client_secret" value="<?php echo esc_attr($this->client_secret); ?>" style="width: 400px;"></td></tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
            <hr>
            <h2>Conexión</h2>
            <?php if ($token): ?>
                <p style="color: green;">✅ Conectado a Google Drive</p>
                <form method="post"><button name="disconnect" class="button">Desconectar</button></form>
            <?php else: ?>
                <a href="<?php echo esc_url($this->get_auth_url()); ?>" class="button button-primary">Conectar con Google Drive</a>
            <?php endif; ?>
        </div>
        <?php
        if (isset($_POST['disconnect'])) {
            delete_option($this->option_token);
            delete_option($this->option_refresh);
            echo '<p>Desconectado.</p>';
        }
    }

    public function get_auth_url() {
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive', // Permiso completo para poder copiar/convertir PDFs
            'access_type' => 'offline',
            'prompt' => 'consent',
        );
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function oauth_start() {
        wp_redirect($this->get_auth_url());
        exit;
    }

    public function oauth_callback() {
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'body' => array(
                    'code' => $code,
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'redirect_uri' => $this->redirect_uri,
                    'grant_type' => 'authorization_code',
                ),
                'timeout' => 30
            ));
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['access_token'])) {
                update_option($this->option_token, $body['access_token']);
                if (isset($body['refresh_token'])) {
                    update_option($this->option_refresh, $body['refresh_token']);
                }
                // Guardar expiración (normalmente 3600s)
                $expires_in = $body['expires_in'] ?? 3590;
                update_option('maxtech_drive_token_expires', time() + $expires_in);

                wp_redirect(admin_url('admin.php?page=maxtech-google-drive-oauth'));
                exit;
            } else {
                echo '<p>Error: ' . esc_html(json_encode($body)) . '</p>';
            }
        } else {
            echo '<p>No se recibió código de autorización.</p>';
        }
        exit;
    }

    /**
     * Obtener access token, refrescando si es necesario
     */
    public function get_access_token() {
        $token = get_option($this->option_token);
        $refresh = get_option($this->option_refresh);
        $expires = get_option('maxtech_drive_token_expires', 0);

        // Si expiró o está por expirar (margen 60s), y tenemos refresh token -> refrescar
        if ($refresh && (empty($token) || time() > ($expires - 60))) {
            // Refrescar token
            $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
                'body' => array(
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'refresh_token' => $refresh,
                    'grant_type' => 'refresh_token',
                ),
                'timeout' => 30
            ));
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['access_token'])) {
                $token = $body['access_token'];
                update_option($this->option_token, $token);
                // Actualizar expiración
                $new_expires = $body['expires_in'] ?? 3590;
                update_option('maxtech_drive_token_expires', time() + $new_expires);
                return $token;
            }
            // Si falla el refresco pero había token antiguo, quizá devolver false o intentar usar el viejo?
            // Devolvemos el viejo por si acaso, aunque probablemente falle.
             return $token; 
        }
        return $token;
    }

    /**
     * Listar archivos de Drive
     */
    public function list_files($folder_id = 'root') {
        $token = $this->get_access_token();
        if (!$token) return false;
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => "'{$folder_id}' in parents and trashed = false",
            'fields' => 'files(id, name, mimeType, size, modifiedTime, webViewLink, iconLink)',
            'pageSize' => 100,
            'orderBy' => 'modifiedTime desc'
        ));
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        if (is_wp_error($response)) return false;
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Leer archivo de Drive (descargar)
     */
    public function get_file_content($file_id) {
        $token = $this->get_access_token();
        if (!$token) return false;
        $meta_url = "https://www.googleapis.com/drive/v3/files/{$file_id}?fields=id,name,mimeType,size";
        $meta_response = wp_remote_get($meta_url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 30));
        $metadata = json_decode(wp_remote_retrieve_body($meta_response), true);
        $mime_type = $metadata['mimeType'] ?? '';
        $file_name = $metadata['name'] ?? '';
        $export_formats = array(
            'application/vnd.google-apps.document' => 'text/plain',
            'application/vnd.google-apps.spreadsheet' => 'text/csv',
            'application/vnd.google-apps.presentation' => 'text/plain',
        );
        if (isset($export_formats[$mime_type])) {
            $export_url = "https://www.googleapis.com/drive/v3/files/{$file_id}/export?mimeType=" . urlencode($export_formats[$mime_type]);
            $response = wp_remote_get($export_url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 60));
        } else {
            $download_url = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
            $response = wp_remote_get($download_url, array('headers' => array('Authorization' => 'Bearer ' . $token), 'timeout' => 60));
        }
        if (is_wp_error($response)) return false;
        $content = wp_remote_retrieve_body($response);
        return array('id' => $file_id, 'name' => $file_name, 'mimeType' => $mime_type, 'content' => $content, 'size' => strlen($content));
    }

    /**
     * Convertir PDF a Texto (Truco: Copiar como Google Doc -> Exportar TXT -> Borrar Copia)
     */
    public function convert_pdf_to_text($file_id) {
        $token = $this->get_access_token();
        if (!$token) return false;

        // 1. Copiar PDF convirtiéndolo a Google Doc
        $copy_url = "https://www.googleapis.com/drive/v3/files/{$file_id}/copy";
        $copy_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body' => json_encode(array(
                'mimeType' => 'application/vnd.google-apps.document', // Forzar conversión OCR
                'name'     => 'Temp_OCR_' . uniqid()
            )),
            'timeout' => 30
        );

        $copy_response = wp_remote_post($copy_url, $copy_args);
        
        if (is_wp_error($copy_response)) return false;

        $copy_data = json_decode(wp_remote_retrieve_body($copy_response), true);
        if (!isset($copy_data['id'])) return false; // Falló la copia/conversión

        $new_id = $copy_data['id'];

        // 2. Exportar el nuevo Doc como Texto Plano
        $export_url = "https://www.googleapis.com/drive/v3/files/{$new_id}/export?mimeType=text/plain";
        $export_response = wp_remote_get($export_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));

        $extracted_text = '';
        if (!is_wp_error($export_response)) {
            $extracted_text = wp_remote_retrieve_body($export_response);
        }

        // 3. Borrar el archivo temporal
        $delete_url = "https://www.googleapis.com/drive/v3/files/{$new_id}";
        wp_remote_request($delete_url, array(
            'method' => 'DELETE',
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 20
        ));

        return $extracted_text;
    }
}

new Google_Drive_OAuth();
