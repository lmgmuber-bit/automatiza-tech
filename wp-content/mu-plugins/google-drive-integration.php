<?php
/**
 * Google Drive Integration para MAXTECH
 * Permite leer archivos de Google Drive de clientes
 * 
 * CONFIGURACIÓN:
 * 1. Ir a Google Cloud Console: https://console.cloud.google.com
 * 2. Crear proyecto o usar existente
 * 3. Habilitar "Google Drive API"
 * 4. Crear Service Account (Credenciales > Crear credenciales > Service Account)
 * 5. Descargar JSON de credenciales
 * 6. Guardar en wp-content/uploads/private/google-service-account.json
 * 7. Compartir carpetas de Drive con el email de la Service Account
 */

if (!defined('ABSPATH')) exit;

class Google_Drive_Integration {
    
    private $credentials_path;
    private $access_token;
    private $token_expiry;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->credentials_path = $upload_dir['basedir'] . '/private/google-service-account.json';
        
        // AJAX handlers
        add_action('wp_ajax_maxtech_drive_list_files', array($this, 'ajax_list_files'));
        add_action('wp_ajax_maxtech_drive_get_file', array($this, 'ajax_get_file'));
        add_action('wp_ajax_maxtech_drive_search', array($this, 'ajax_search_files'));
        add_action('wp_ajax_maxtech_drive_read_content', array($this, 'ajax_read_file_content'));
        
        // Admin menu para configurar
        add_action('admin_menu', array($this, 'add_admin_menu'), 100);
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Obtener Access Token usando Service Account
     */
    private function get_access_token() {
        // Verificar si ya tenemos un token válido en cache
        $cached_token = get_transient('google_drive_access_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        if (!file_exists($this->credentials_path)) {
            return new WP_Error('no_credentials', 'No se encontró el archivo de credenciales de Google');
        }
        
        $credentials = json_decode(file_get_contents($this->credentials_path), true);
        
        if (!$credentials || !isset($credentials['client_email']) || !isset($credentials['private_key'])) {
            return new WP_Error('invalid_credentials', 'El archivo de credenciales es inválido');
        }
        
        // Crear JWT
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        );
        
        $now = time();
        $claim = array(
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        );
        
        $jwt_header = $this->base64url_encode(json_encode($header));
        $jwt_claim = $this->base64url_encode(json_encode($claim));
        $signature_input = $jwt_header . '.' . $jwt_claim;
        
        // Firmar con la clave privada
        $private_key = openssl_pkey_get_private($credentials['private_key']);
        if (!$private_key) {
            return new WP_Error('invalid_key', 'No se pudo cargar la clave privada');
        }
        
        openssl_sign($signature_input, $signature, $private_key, 'SHA256');
        $jwt = $signature_input . '.' . $this->base64url_encode($signature);
        
        // Intercambiar JWT por Access Token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            // Guardar en cache por 55 minutos (el token dura 60)
            set_transient('google_drive_access_token', $body['access_token'], 55 * MINUTE_IN_SECONDS);
            return $body['access_token'];
        }
        
        return new WP_Error('token_error', 'No se pudo obtener el token: ' . json_encode($body));
    }
    
    /**
     * Base64 URL safe encoding
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Listar archivos de una carpeta de Drive
     */
    public function list_files($folder_id = 'root', $page_token = null) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }
        
        $query = "'{$folder_id}' in parents and trashed = false";
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $query,
            'fields' => 'nextPageToken, files(id, name, mimeType, size, createdTime, modifiedTime, webViewLink, iconLink)',
            'pageSize' => 100,
            'pageToken' => $page_token,
            'orderBy' => 'modifiedTime desc'
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Buscar archivos en Drive
     */
    public function search_files($query, $folder_id = null) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }
        
        $search_query = "name contains '{$query}' and trashed = false";
        if ($folder_id) {
            $search_query .= " and '{$folder_id}' in parents";
        }
        
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(array(
            'q' => $search_query,
            'fields' => 'files(id, name, mimeType, size, modifiedTime, webViewLink, parents)',
            'pageSize' => 50,
            'orderBy' => 'modifiedTime desc'
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Obtener contenido de un archivo
     */
    public function get_file_content($file_id) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }
        
        // Primero obtener metadata del archivo
        $meta_url = "https://www.googleapis.com/drive/v3/files/{$file_id}?fields=id,name,mimeType,size";
        $meta_response = wp_remote_get($meta_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token),
            'timeout' => 30
        ));
        
        if (is_wp_error($meta_response)) {
            return $meta_response;
        }
        
        $metadata = json_decode(wp_remote_retrieve_body($meta_response), true);
        $mime_type = $metadata['mimeType'] ?? '';
        $file_name = $metadata['name'] ?? '';
        
        // Para Google Docs, Sheets, Slides - exportar como texto/csv
        $export_formats = array(
            'application/vnd.google-apps.document' => 'text/plain',
            'application/vnd.google-apps.spreadsheet' => 'text/csv',
            'application/vnd.google-apps.presentation' => 'text/plain',
        );
        
        if (isset($export_formats[$mime_type])) {
            // Es un documento de Google, exportar
            $export_url = "https://www.googleapis.com/drive/v3/files/{$file_id}/export?mimeType=" . urlencode($export_formats[$mime_type]);
            $response = wp_remote_get($export_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
        } else {
            // Es un archivo normal, descargar
            $download_url = "https://www.googleapis.com/drive/v3/files/{$file_id}?alt=media";
            $response = wp_remote_get($download_url, array(
                'headers' => array('Authorization' => 'Bearer ' . $token),
                'timeout' => 60
            ));
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = wp_remote_retrieve_body($response);
        
        return array(
            'id' => $file_id,
            'name' => $file_name,
            'mimeType' => $mime_type,
            'content' => $content,
            'size' => strlen($content)
        );
    }
    
    /**
     * Leer y extraer texto de un archivo de Drive
     * Soporta: Google Docs, Sheets, Slides, PDF, Word, Excel, PowerPoint, TXT
     */
    public function read_and_extract_content($file_id) {
        $file_data = $this->get_file_content($file_id);
        
        if (is_wp_error($file_data)) {
            return $file_data;
        }
        
        $mime_type = $file_data['mimeType'];
        $content = $file_data['content'];
        $name = $file_data['name'];
        
        // Google Docs ya vienen como texto plano
        if (strpos($mime_type, 'google-apps.document') !== false ||
            strpos($mime_type, 'google-apps.presentation') !== false) {
            return array(
                'name' => $name,
                'type' => 'Google Doc/Slides',
                'content' => substr($content, 0, 5000) . (strlen($content) > 5000 ? '...' : '')
            );
        }
        
        // Google Sheets viene como CSV
        if (strpos($mime_type, 'google-apps.spreadsheet') !== false) {
            return array(
                'name' => $name,
                'type' => 'Google Sheets',
                'content' => "[Datos CSV]\n" . substr($content, 0, 5000) . (strlen($content) > 5000 ? '...' : '')
            );
        }
        
        // Para archivos binarios, guardar temporalmente y procesar
        $temp_file = wp_tempnam($name);
        file_put_contents($temp_file, $content);
        
        $extracted = '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        // Usar las funciones de extracción existentes
        if (class_exists('Client_Details_Module')) {
            $module = new Client_Details_Module();
            // Los métodos son privados, así que reimplementamos aquí
        }
        
        switch ($extension) {
            case 'txt':
            case 'md':
            case 'csv':
                $extracted = substr($content, 0, 5000);
                break;
                
            case 'docx':
                $extracted = $this->extract_docx($temp_file);
                break;
                
            case 'xlsx':
                $extracted = $this->extract_xlsx($temp_file);
                break;
                
            case 'pptx':
                $extracted = $this->extract_pptx($temp_file);
                break;
                
            case 'pdf':
                $extracted = $this->extract_pdf($temp_file);
                break;
                
            default:
                $extracted = "Archivo: {$name} (tipo: {$mime_type})";
        }
        
        // Limpiar archivo temporal
        @unlink($temp_file);
        
        return array(
            'name' => $name,
            'type' => $extension,
            'content' => $extracted
        );
    }
    
    /**
     * Extraer texto de DOCX
     */
    private function extract_docx($path) {
        if (!class_exists('ZipArchive')) return 'ZipArchive no disponible';
        
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return 'No se pudo abrir el archivo';
        
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if (empty($content)) return 'Sin contenido';
        
        // Extraer texto
        $content = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $content);
        $content = preg_replace('/<[a-z0-9]+:/i', '<', $content);
        $content = preg_replace('/<\/[a-z0-9]+:/i', '</', $content);
        preg_match_all('/<t[^>]*>([^<]*)<\/t>/i', $content, $matches);
        
        $text = implode(' ', $matches[1] ?? array());
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        return "[Word] " . substr($text, 0, 4000) . (strlen($text) > 4000 ? '...' : '');
    }
    
    /**
     * Extraer texto de XLSX
     */
    private function extract_xlsx($path) {
        if (!class_exists('ZipArchive')) return 'ZipArchive no disponible';
        
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return 'No se pudo abrir el archivo';
        
        // Shared strings
        $shared = array();
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_xml) {
            $xml = @simplexml_load_string($shared_xml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $shared[] = (string) $si->t;
                }
            }
        }
        
        // Leer hojas
        $all_text = array();
        for ($i = 1; $i <= 3; $i++) {
            $sheet = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if (!$sheet) break;
            
            $xml = @simplexml_load_string($sheet);
            if (!$xml || !isset($xml->sheetData)) continue;
            
            $rows = array();
            foreach ($xml->sheetData->row as $row) {
                $cells = array();
                foreach ($row->c as $cell) {
                    if (isset($cell->v)) {
                        $v = (string) $cell->v;
                        if (isset($cell['t']) && (string) $cell['t'] === 's') {
                            $cells[] = $shared[(int) $v] ?? '';
                        } else {
                            $cells[] = $v;
                        }
                    }
                }
                if ($cells) $rows[] = implode(' | ', $cells);
            }
            if ($rows) $all_text[] = implode("\n", array_slice($rows, 0, 30));
        }
        
        $zip->close();
        
        $text = implode("\n---\n", $all_text);
        return "[Excel] " . substr($text, 0, 4000) . (strlen($text) > 4000 ? '...' : '');
    }
    
    /**
     * Extraer texto de PPTX
     */
    private function extract_pptx($path) {
        if (!class_exists('ZipArchive')) return 'ZipArchive no disponible';
        
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return 'No se pudo abrir el archivo';
        
        $all_text = array();
        for ($i = 1; $i <= 20; $i++) {
            $slide = $zip->getFromName("ppt/slides/slide{$i}.xml");
            if (!$slide) break;
            
            $slide = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $slide);
            $slide = preg_replace('/<[a-z0-9]+:/i', '<', $slide);
            $slide = preg_replace('/<\/[a-z0-9]+:/i', '</', $slide);
            preg_match_all('/<t[^>]*>([^<]*)<\/t>/i', $slide, $matches);
            
            if (!empty($matches[1])) {
                $text = implode(' ', $matches[1]);
                $all_text[] = "Slide {$i}: " . $text;
            }
        }
        
        $zip->close();
        
        $text = implode("\n", $all_text);
        return "[PowerPoint] " . substr($text, 0, 4000) . (strlen($text) > 4000 ? '...' : '');
    }
    
    /**
     * Extraer texto de PDF
     */
    private function extract_pdf($path) {
        // Intentar con pdftotext
        if (function_exists('shell_exec')) {
            $output = @shell_exec('pdftotext -layout -nopgbrk ' . escapeshellarg($path) . ' - 2>/dev/null');
            if (!empty($output)) {
                return "[PDF] " . substr(trim($output), 0, 4000) . (strlen($output) > 4000 ? '...' : '');
            }
        }
        
        // Método básico
        $content = file_get_contents($path);
        preg_match_all('/\((.*?)\)/', $content, $matches);
        
        if (!empty($matches[1])) {
            $text = implode(' ', array_filter($matches[1], function($t) {
                return strlen($t) > 2 && preg_match('/[a-zA-Z]/', $t);
            }));
            if (strlen($text) > 50) {
                return "[PDF] " . substr($text, 0, 4000);
            }
        }
        
        return "[PDF] No se pudo extraer texto";
    }
    
    /**
     * AJAX: Listar archivos
     */
    public function ajax_list_files() {
        check_ajax_referer('maxtech_drive_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $folder_id = sanitize_text_field($_POST['folder_id'] ?? 'root');
        $result = $this->list_files($folder_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Buscar archivos
     */
    public function ajax_search_files() {
        check_ajax_referer('maxtech_drive_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $folder_id = sanitize_text_field($_POST['folder_id'] ?? null);
        
        if (empty($query)) {
            wp_send_json_error('Query requerido');
        }
        
        $result = $this->search_files($query, $folder_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Leer contenido de archivo
     */
    public function ajax_read_file_content() {
        check_ajax_referer('maxtech_drive_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $file_id = sanitize_text_field($_POST['file_id'] ?? '');
        
        if (empty($file_id)) {
            wp_send_json_error('File ID requerido');
        }
        
        $result = $this->read_and_extract_content($file_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Admin menu
     */
    public function add_admin_menu() {
        // Este menú está deshabilitado porque se usa OAuth, no Service Account
        // Si necesitas habilitarlo, descomenta las líneas siguientes.
        // add_submenu_page(
        //     'crm-automatiza',
        //     'Google Drive',
        //     '☁️ Google Drive',
        //     'manage_options',
        //     'maxtech-google-drive',
        //     array($this, 'render_admin_page')
        // );
        // add_submenu_page(
        //     'automatiza-crm',
        //     'Google Drive',
        //     '☁️ Google Drive',
        //     'manage_options',
        //     'maxtech-google-drive',
        //     array($this, 'render_admin_page')
        // );
    }
    
    /**
     * Registrar settings
     */
    public function register_settings() {
        register_setting('maxtech_drive_settings', 'maxtech_drive_default_folder');
        register_setting('maxtech_drive_settings', 'maxtech_drive_client_folders');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $token = $this->get_access_token();
        $is_connected = !is_wp_error($token);
        
        ?>
        <div class="wrap">
            <h1>☁️ Google Drive - MAXTECH</h1>
            
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h2>Estado de Conexión</h2>
                <?php if ($is_connected): ?>
                    <p style="color: green; font-size: 16px;">✅ Conectado a Google Drive</p>
                <?php else: ?>
                    <p style="color: red; font-size: 16px;">❌ No conectado: <?php echo esc_html($token->get_error_message()); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>📋 Instrucciones de Configuración</h2>
                <ol style="line-height: 2;">
                    <li>Ir a <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                    <li>Crear un proyecto nuevo o usar uno existente</li>
                    <li>Ir a <strong>APIs y Servicios > Biblioteca</strong></li>
                    <li>Buscar y habilitar <strong>"Google Drive API"</strong></li>
                    <li>Ir a <strong>Credenciales > Crear credenciales > Cuenta de servicio</strong></li>
                    <li>Crear la cuenta de servicio (nombre: "maxtech-drive")</li>
                    <li>En la cuenta creada, ir a <strong>Claves > Agregar clave > JSON</strong></li>
                    <li>Descargar el archivo JSON</li>
                    <li>Subir el archivo a: <code><?php echo esc_html($this->credentials_path); ?></code></li>
                    <li><strong>Importante:</strong> Compartir las carpetas de Drive con el email de la Service Account</li>
                </ol>
                
                <h3>📧 Email de Service Account:</h3>
                <?php
                if (file_exists($this->credentials_path)) {
                    $creds = json_decode(file_get_contents($this->credentials_path), true);
                    if (isset($creds['client_email'])) {
                        echo '<code style="background: #f0f0f0; padding: 10px; display: block; font-size: 14px;">' . esc_html($creds['client_email']) . '</code>';
                        echo '<p><em>Comparte las carpetas de Google Drive con este email para dar acceso a MAXTECH.</em></p>';
                    }
                } else {
                    echo '<p style="color: orange;">⚠️ Primero sube el archivo de credenciales.</p>';
                }
                ?>
            </div>
            
            <?php if ($is_connected): ?>
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>📁 Explorador de Archivos</h2>
                <div id="drive-explorer">
                    <p>Cargando archivos...</p>
                </div>
                
                <h3 style="margin-top: 20px;">🔍 Buscar Archivos</h3>
                <input type="text" id="drive-search" placeholder="Buscar por nombre..." style="width: 300px; padding: 8px;">
                <button type="button" class="button button-primary" onclick="searchDriveFiles()">Buscar</button>
                <div id="drive-search-results" style="margin-top: 10px;"></div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Cargar archivos de la raíz
                loadDriveFiles('root');
            });
            
            function loadDriveFiles(folderId) {
                jQuery.post(ajaxurl, {
                    action: 'maxtech_drive_list_files',
                    nonce: '<?php echo wp_create_nonce('maxtech_drive_nonce'); ?>',
                    folder_id: folderId
                }, function(response) {
                    if (response.success && response.data.files) {
                        var html = '<ul style="list-style: none; padding: 0;">';
                        response.data.files.forEach(function(file) {
                            var icon = file.mimeType.includes('folder') ? '📁' : '📄';
                            var clickAction = file.mimeType.includes('folder') 
                                ? 'loadDriveFiles(\'' + file.id + '\')'
                                : 'readDriveFile(\'' + file.id + '\', \'' + file.name.replace(/'/g, "\\'") + '\')';
                            html += '<li style="padding: 5px 0; cursor: pointer;" onclick="' + clickAction + '">';
                            html += icon + ' ' + file.name;
                            html += '</li>';
                        });
                        html += '</ul>';
                        if (folderId !== 'root') {
                            html = '<a href="#" onclick="loadDriveFiles(\'root\'); return false;">⬅️ Volver</a><br><br>' + html;
                        }
                        jQuery('#drive-explorer').html(html);
                    } else {
                        jQuery('#drive-explorer').html('<p style="color: red;">Error al cargar archivos</p>');
                    }
                });
            }
            
            function searchDriveFiles() {
                var query = jQuery('#drive-search').val();
                if (!query) return;
                
                jQuery('#drive-search-results').html('<p>Buscando...</p>');
                
                jQuery.post(ajaxurl, {
                    action: 'maxtech_drive_search',
                    nonce: '<?php echo wp_create_nonce('maxtech_drive_nonce'); ?>',
                    query: query
                }, function(response) {
                    if (response.success && response.data.files) {
                        var html = '<ul style="list-style: none; padding: 0;">';
                        response.data.files.forEach(function(file) {
                            html += '<li style="padding: 5px 0;">';
                            html += '📄 <a href="#" onclick="readDriveFile(\'' + file.id + '\', \'' + file.name.replace(/'/g, "\\'") + '\'); return false;">' + file.name + '</a>';
                            html += '</li>';
                        });
                        html += '</ul>';
                        jQuery('#drive-search-results').html(html || '<p>No se encontraron archivos</p>');
                    }
                });
            }
            
            function readDriveFile(fileId, fileName) {
                alert('Leyendo: ' + fileName + '...\nEl contenido se mostrará en la consola.');
                
                jQuery.post(ajaxurl, {
                    action: 'maxtech_drive_read_content',
                    nonce: '<?php echo wp_create_nonce('maxtech_drive_nonce'); ?>',
                    file_id: fileId
                }, function(response) {
                    console.log('Contenido de ' + fileName + ':', response);
                    if (response.success) {
                        alert('✅ Contenido extraído!\n\nTipo: ' + response.data.type + '\n\nPrimeros 500 caracteres:\n' + response.data.content.substring(0, 500));
                    } else {
                        alert('❌ Error: ' + response.data);
                    }
                });
            }
            </script>
            <?php endif; ?>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
                <h2>🔗 Vincular Carpetas a Clientes</h2>
                <p>Para que MAXTECH acceda automáticamente a los archivos de un cliente, guarda el ID de carpeta de Drive en la ficha del cliente.</p>
                <p><strong>Formato del ID:</strong> El ID está en la URL de la carpeta de Drive:</p>
                <code>https://drive.google.com/drive/folders/<strong>1ABC123xyz...</strong></code>
                <p>El ID sería: <code>1ABC123xyz...</code></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Método público para que MAXTECH acceda a Drive
     * @param string $client_drive_folder_id ID de carpeta de Drive del cliente
     * @param string $search_query Búsqueda opcional
     * @return array Lista de archivos o contenido
     */
    public function get_client_drive_files($client_drive_folder_id, $search_query = null) {
        if ($search_query) {
            return $this->search_files($search_query, $client_drive_folder_id);
        }
        return $this->list_files($client_drive_folder_id);
    }
    
    /**
     * Leer archivo específico de Drive
     */
    public function read_drive_file($file_id) {
        return $this->read_and_extract_content($file_id);
    }
}

// Inicializar
new Google_Drive_Integration();

/**
 * Helper function para usar desde MAXTECH
 */
function maxtech_read_drive_file($file_id) {
    $drive = new Google_Drive_Integration();
    return $drive->read_drive_file($file_id);
}

function maxtech_list_drive_folder($folder_id) {
    $drive = new Google_Drive_Integration();
    return $drive->list_files($folder_id);
}

function maxtech_search_drive($query, $folder_id = null) {
    $drive = new Google_Drive_Integration();
    return $drive->search_files($query, $folder_id);
}
