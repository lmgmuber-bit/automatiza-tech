<?php
/**
 * MAXTECH - Manager de AutomatizaTech eXpert
 * Agente IA completo con soporte para archivos, audio e historial
 * 
 * Características:
 * - Chat con OpenAI GPT-4o
 * - Subida de archivos (imágenes, PDFs)
 * - Entrada y salida de voz
 * - Historial de conversaciones persistente
 * - Conexión a N8N para conocimiento de workflows
 */

if (!defined('ABSPATH')) exit;

class ARIA_Agente {
    
    private $api_key; // Se carga desde Bóveda de Credenciales → fallback wp-config.php
    private $nombre_agente = 'MAXTECH';
    private $tabla_historial;
    
    // Configuración N8N
    private $n8n_url = 'https://n8n-n8n.kchiba.easypanel.host';
    private $n8n_api_key = ''; // Se configura en wp_options
    
    public function __construct() {
        global $wpdb;
        $this->tabla_historial = $wpdb->prefix . 'crm_chat_historial';
        
        // Cargar API key de OpenAI: primero Bóveda, luego wp-config.php como fallback
        $this->api_key = $this->load_openai_key();
        
        // Cargar API key de N8N desde opciones
        $this->n8n_api_key = get_option('maxtech_n8n_api_key', '');
        
        // AJAX handlers - Chat
        add_action('wp_ajax_aria_chat', array($this, 'limpiar_output'), 1);
        add_action('wp_ajax_aria_upload', array($this, 'limpiar_output'), 1);
        add_action('wp_ajax_aria_tts', array($this, 'limpiar_output'), 1);
        add_action('wp_ajax_aria_historial', array($this, 'limpiar_output'), 1);
        add_action('wp_ajax_aria_cargar_sesion', array($this, 'limpiar_output'), 1);
        add_action('wp_ajax_aria_nueva_sesion', array($this, 'limpiar_output'), 1);
        add_action('wp_ajax_aria_chat', array($this, 'procesar_chat'));
        add_action('wp_ajax_aria_upload', array($this, 'procesar_upload'));
        add_action('wp_ajax_aria_tts', array($this, 'generar_audio'));
        add_action('wp_ajax_aria_historial', array($this, 'obtener_historial'));
        add_action('wp_ajax_aria_cargar_sesion', array($this, 'cargar_sesion'));
        add_action('wp_ajax_aria_nueva_sesion', array($this, 'nueva_sesion'));
        
        // AJAX handlers - Acciones CRM
        add_action('wp_ajax_maxtech_agendar_seguimiento', array($this, 'agendar_seguimiento'));
        add_action('wp_ajax_maxtech_buscar_cliente', array($this, 'buscar_cliente'));
        add_action('wp_ajax_maxtech_obtener_disponibilidad', array($this, 'obtener_disponibilidad'));
        add_action('wp_ajax_maxtech_obtener_documento', array($this, 'ajax_obtener_documento_cliente'));
        add_action('wp_ajax_maxtech_leer_drive', array($this, 'ajax_leer_archivo_drive'));
        add_action('wp_ajax_maxtech_listar_drive', array($this, 'ajax_listar_carpeta_drive'));
        
        // Admin para configurar N8N - prioridad alta para que cargue después del menú principal
        add_action('admin_menu', array($this, 'agregar_menu_config'), 99);
        add_action('admin_init', array($this, 'registrar_settings'));
    }
    
    /**
     * Cargar API Key de OpenAI desde la Bóveda de Credenciales.
     * Fallback: constante OPENAI_API_KEY en wp-config.php
     */
    private function load_openai_key() {
        // 1. Intentar obtener de la Bóveda de Credenciales
        if (class_exists('AutomatizaTech_Credentials_Vault')) {
            $vault = AutomatizaTech_Credentials_Vault::get_instance();
            $key = $vault->get_api_key('OpenAI', 'ai');
            if (!empty($key)) {
                return $key;
            }
        }
        
        // 2. Fallback: wp-config.php
        if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
            return OPENAI_API_KEY;
        }
        
        error_log('ARIA: No se encontró API Key de OpenAI ni en la Bóveda ni en wp-config.php');
        return '';
    }
    
    /**
     * Menú de configuración
     */
    public function agregar_menu_config() {
        // Agregar como submenú del CRM
        add_submenu_page(
            'automatiza-crm',
            'Config MAXTECH',
            '⚙️ Config MAXTECH',
            'manage_options',
            'maxtech-config',
            array($this, 'pagina_config')
        );
        
        // También agregar como página de nivel superior en Ajustes por si falla el submenú
        add_options_page(
            'MAXTECH Config',
            'MAXTECH',
            'manage_options',
            'maxtech-settings',
            array($this, 'pagina_config')
        );
    }
    
    public function registrar_settings() {
        register_setting('maxtech_options', 'maxtech_n8n_api_key');
        register_setting('maxtech_options', 'maxtech_n8n_url');
    }
    
    public function pagina_config() {
        if (isset($_POST['maxtech_save']) && check_admin_referer('maxtech_config_nonce')) {
            update_option('maxtech_n8n_api_key', sanitize_text_field($_POST['n8n_api_key']));
            update_option('maxtech_n8n_url', esc_url_raw($_POST['n8n_url']));
            $this->n8n_api_key = $_POST['n8n_api_key'];
            echo '<div class="notice notice-success"><p>✅ Configuración guardada</p></div>';
        }
        
        $saved_url = get_option('maxtech_n8n_url', $this->n8n_url);
        $saved_key = get_option('maxtech_n8n_api_key', '');
        
        // Probar conexión si hay API key
        $test_result = '';
        if (!empty($saved_key)) {
            $workflows = $this->obtener_workflows_n8n();
            if (is_array($workflows)) {
                $test_result = '<span style="color:green;">✅ Conectado - ' . count($workflows) . ' workflows encontrados</span>';
            } else {
                $test_result = '<span style="color:red;">❌ Error de conexión</span>';
            }
        }
        ?>
        <div class="wrap">
            <h1>⚙️ Configuración MAXTECH</h1>
            <p>Configura la conexión a N8N para que MAXTECH pueda explicar los workflows activos.</p>
            
            <form method="post">
                <?php wp_nonce_field('maxtech_config_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>URL de N8N</th>
                        <td>
                            <input type="url" name="n8n_url" value="<?php echo esc_attr($saved_url); ?>" class="regular-text" placeholder="https://tu-n8n.com">
                            <p class="description">URL base de tu instancia N8N</p>
                        </td>
                    </tr>
                    <tr>
                        <th>API Key de N8N</th>
                        <td>
                            <input type="password" name="n8n_api_key" value="<?php echo esc_attr($saved_key); ?>" class="regular-text" placeholder="n8n_api_...">
                            <p class="description">Genera una API Key en N8N: Settings → API → Create API Key</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Estado</th>
                        <td><?php echo $test_result ?: '<span style="color:gray;">Sin configurar</span>'; ?></td>
                    </tr>
                </table>
                <p><input type="submit" name="maxtech_save" class="button button-primary" value="Guardar Configuración"></p>
            </form>
            
            <hr>
            <h2>📚 Cómo obtener la API Key de N8N</h2>
            <ol>
                <li>Accede a tu instancia N8N: <a href="<?php echo esc_url($saved_url); ?>" target="_blank"><?php echo esc_html($saved_url); ?></a></li>
                <li>Ve a <strong>Settings</strong> (esquina inferior izquierda)</li>
                <li>Selecciona <strong>API</strong></li>
                <li>Haz clic en <strong>Create API Key</strong></li>
                <li>Copia la key y pégala arriba</li>
            </ol>
        </div>
        <?php
    }
    
    /**
     * Limpiar output buffer antes de respuestas AJAX.
     * Descarta warnings/notices de PHP y Xdebug para que solo llegue JSON limpio.
     */
    public function limpiar_output() {
        // Silenciar display de errores (Xdebug puede ignorar WP_DEBUG_DISPLAY)
        @ini_set('display_errors', '0');
        // Limpiar cualquier output previo
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
    }
    
    /**
     * Procesar mensaje del chat
     */
    public function procesar_chat() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        global $wpdb;
        $mensaje = sanitize_textarea_field($_POST['mensaje']);
        $session_id = sanitize_text_field($_POST['session_id']);
        $archivos = isset($_POST['archivos']) ? json_decode(stripslashes($_POST['archivos']), true) : array();
        $user_id = get_current_user_id();
        
        // Guardar mensaje del usuario en historial
        $wpdb->insert($this->tabla_historial, array(
            'session_id' => $session_id,
            'user_id' => $user_id,
            'role' => 'user',
            'content' => $mensaje
        ));
        
        // Obtener contexto del CRM
        $contexto_crm = $this->obtener_contexto_crm();
        
        // Obtener historial de la sesión para contexto
        $historial = $this->obtener_historial_sesion($session_id);

        // Determinar si el usuario actual es administrador
        $es_admin = current_user_can('manage_options');
        
        // Construir mensajes para OpenAI
        $messages = $this->construir_mensajes($contexto_crm, $historial, $mensaje, $archivos, $es_admin);
        
        // Llamar a OpenAI
        $respuesta = $this->llamar_openai($messages, !empty($archivos));
        
        // Verificar si hay comando de agendar en la respuesta
        $texto_respuesta = $respuesta['texto'];
        $reunion_agendada = null;
        
        if (preg_match('/\[AGENDAR_SEGUIMIENTO\](.*?)\[\/AGENDAR_SEGUIMIENTO\]/s', $texto_respuesta, $matches)) {
            $datos_reunion = json_decode($matches[1], true);
            if ($datos_reunion) {
                // Intentar agendar usando funciones existentes del sistema
                $resultado_agenda = $this->ejecutar_agendamiento($datos_reunion);
                if ($resultado_agenda['success']) {
                    $reunion_agendada = $resultado_agenda;
                    // Limpiar el comando de la respuesta
                    $texto_respuesta = preg_replace('/\[AGENDAR_SEGUIMIENTO\].*?\[\/AGENDAR_SEGUIMIENTO\]/s', '', $texto_respuesta);
                    $texto_respuesta .= "\n\n✅ **Reunión agendada exitosamente:**\n";
                    $texto_respuesta .= "📅 Fecha: " . date('d/m/Y', strtotime($datos_reunion['fecha'])) . "\n";
                    $texto_respuesta .= "🕐 Hora: " . $datos_reunion['hora'] . " hrs\n";
                    $texto_respuesta .= "👤 Cliente: " . $datos_reunion['nombre'] . "\n";
                    if (!empty($datos_reunion['empresa'])) {
                        $texto_respuesta .= "🏢 Empresa: " . $datos_reunion['empresa'] . "\n";
                    }
                    $texto_respuesta .= "🔗 ID Reunión: #" . $resultado_agenda['reunion_id'] . "\n";
                    
                    // Mostrar acciones ejecutadas (Calendar, Email, etc)
                    if (!empty($resultado_agenda['acciones'])) {
                        $texto_respuesta .= "\n**Acciones ejecutadas:**\n";
                        foreach ($resultado_agenda['acciones'] as $accion) {
                            $texto_respuesta .= "• " . $accion . "\n";
                        }
                    }
                    
                    // Mostrar link de Meet si se generó
                    if (!empty($resultado_agenda['meet_link'])) {
                        $texto_respuesta .= "\n🎥 **Link Google Meet:** " . $resultado_agenda['meet_link'];
                    }
                } else {
                    $texto_respuesta = preg_replace('/\[AGENDAR_SEGUIMIENTO\].*?\[\/AGENDAR_SEGUIMIENTO\]/s', '', $texto_respuesta);
                    $texto_respuesta .= "\n\n⚠️ No se pudo agendar: " . $resultado_agenda['error'];
                }
            }
        }
        
        $respuesta['texto'] = trim($texto_respuesta);
        
        // Guardar respuesta en historial
        $tokens = isset($respuesta['tokens']) ? $respuesta['tokens'] : 0;
        $wpdb->insert($this->tabla_historial, array(
            'session_id' => $session_id,
            'user_id' => $user_id,
            'role' => 'assistant',
            'content' => $respuesta['texto'],
            'tokens_used' => $tokens
        ));
        
        // Registrar en ai_usage_log
        $this->registrar_consumo($tokens, $respuesta['cost']);
        
        $response_data = array(
            'respuesta' => $respuesta['texto'],
            'tokens' => $tokens
        );
        
        if ($reunion_agendada) {
            $response_data['reunion_agendada'] = $reunion_agendada;
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * Ejecutar agendamiento de reunión - REPLICA EXACTA de la lógica del panel admin
     * Basado en admin-followup-meetings.php líneas 184-290
     */
    private function ejecutar_agendamiento($datos) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'automatiza_followup_meetings';
        
        // Extraer datos (igual que el admin)
        $client_name = sanitize_text_field($datos['nombre']);
        $client_email = sanitize_email($datos['email']);
        $company_name = sanitize_text_field($datos['empresa'] ?? '');
        $phone = sanitize_text_field($datos['telefono'] ?? '');
        $meeting_date = sanitize_text_field($datos['fecha']);
        $meeting_time = sanitize_text_field($datos['hora']);
        $meet_link = ''; // Se llenará si N8N devuelve el link
        $meeting_subject = sanitize_text_field($datos['asunto'] ?? 'Reunión de Seguimiento');
        $notes = sanitize_textarea_field($datos['notas'] ?? '');
        
        // Por defecto desde MAXTECH: enviar email y crear evento en calendar
        $send_email = isset($datos['enviar_email']) ? $datos['enviar_email'] : true;
        $create_calendar_event = isset($datos['crear_evento']) ? $datos['crear_evento'] : true;
        
        // Validaciones básicas (igual que el admin)
        if (empty($client_name) || empty($client_email) || empty($meeting_date) || empty($meeting_time)) {
            return array('success' => false, 'error' => 'Faltan datos obligatorios (nombre, email, fecha, hora)');
        }
        
        if (!is_email($client_email)) {
            return array('success' => false, 'error' => 'El correo electrónico no es válido');
        }
        
        // Validar disponibilidad (verificar ambas tablas: leads y followup) - IGUAL QUE ADMIN
        if (function_exists('automatiza_tech_check_slot_availability')) {
            $availability_check = automatiza_tech_check_slot_availability($meeting_date, $meeting_time, null);
            
            if (!$availability_check['available']) {
                $conflict_type = $availability_check['conflict_type'] === 'demo' ? 'DEMO' : 'Reunión de Seguimiento';
                return array(
                    'success' => false, 
                    'error' => "Horario no disponible: Ya existe una {$conflict_type} programada. Conflicto con: {$availability_check['conflict_details']}"
                );
            }
        }
        
        // Preparar datos para insertar (IGUAL QUE ADMIN)
        $data = array(
            'client_name' => $client_name,
            'client_email' => $client_email,
            'company_name' => $company_name,
            'phone' => $phone,
            'meeting_date' => $meeting_date,
            'meeting_time' => $meeting_time . ':00',
            'meet_link' => $meet_link,
            'meeting_subject' => $meeting_subject,
            'notes' => $notes . "\n[Agendado por MAXTECH - " . wp_get_current_user()->display_name . " - " . date('d/m/Y H:i') . "]",
            'status' => 'scheduled'
        );
        
        // Insertar en BD (IGUAL QUE ADMIN)
        $result = $wpdb->insert($table_name, $data);
        
        if (!$result) {
            return array('success' => false, 'error' => 'Error de base de datos al crear la reunión');
        }
        
        $meeting_id = $wpdb->insert_id;
        
        $resultado = array(
            'success' => true, 
            'reunion_id' => $meeting_id,
            'acciones' => array()
        );
        
        // Crear evento en Google Calendar via N8N si está marcado (IGUAL QUE ADMIN)
        $n8n_result = null;
        if ($create_calendar_event && $meeting_id) {
            if (function_exists('automatiza_tech_create_followup_calendar_event')) {
                $n8n_result = automatiza_tech_create_followup_calendar_event($meeting_id);
                if ($n8n_result && $n8n_result['success']) {
                    // Actualizar meet_link si N8N lo devolvió (IGUAL QUE ADMIN)
                    if (!empty($n8n_result['meet_link'])) {
                        $wpdb->update($table_name, array('meet_link' => $n8n_result['meet_link']), array('id' => $meeting_id));
                        $resultado['meet_link'] = $n8n_result['meet_link'];
                        $resultado['acciones'][] = '📅 Evento creado en Google Calendar';
                        $resultado['acciones'][] = '🔗 Link Meet generado automáticamente';
                    } else {
                        $resultado['acciones'][] = '📅 Evento creado en Google Calendar';
                    }
                    if (!empty($n8n_result['event_id'])) {
                        $resultado['event_id'] = $n8n_result['event_id'];
                    }
                } else {
                    $resultado['acciones'][] = '⚠️ No se pudo crear el evento en calendario: ' . ($n8n_result['message'] ?? 'Error desconocido');
                }
            } else {
                $resultado['acciones'][] = '⚠️ Función de calendario no disponible';
            }
        }
        
        // Enviar correo si está marcado (IGUAL QUE ADMIN)
        if ($send_email && $meeting_id) {
            if (function_exists('automatiza_tech_send_followup_email')) {
                // Re-obtener datos actualizados (con meet_link de N8N si aplica) - IGUAL QUE ADMIN
                $meeting_updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $meeting_id));
                $email_sent = automatiza_tech_send_followup_email($meeting_id);
                
                if ($email_sent) {
                    $wpdb->update($table_name, array('email_sent' => 1), array('id' => $meeting_id));
                    $resultado['acciones'][] = '📧 Correo de invitación enviado a ' . $client_email;
                    $resultado['email_enviado'] = true;
                } else {
                    $resultado['acciones'][] = '⚠️ Error al enviar el correo (revisar configuración SMTP)';
                    $resultado['email_enviado'] = false;
                }
            } else {
                $resultado['acciones'][] = '⚠️ Función de email no disponible';
            }
        }
        
        return $resultado;
    }
    
    /**
     * Procesar subida de archivos
     */
    public function procesar_upload() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        if (empty($_FILES['archivo'])) {
            wp_send_json_error('No se recibió archivo');
        }
        
        $file = $_FILES['archivo'];
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain', 'text/csv');
        
        if (!in_array($file['type'], $allowed)) {
            wp_send_json_error('Tipo de archivo no permitido. Usa: JPG, PNG, GIF, WebP, PDF, TXT, CSV');
        }
        
        // Subir usando WordPress
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        }
        
        // Si es imagen, prepararla para Vision API
        $archivo_data = array(
            'url' => $upload['url'],
            'type' => $file['type'],
            'name' => $file['name']
        );
        
        // Si es PDF o texto, extraer contenido
        if (in_array($file['type'], array('application/pdf', 'text/plain', 'text/csv'))) {
            $archivo_data['contenido'] = $this->extraer_texto($upload['file'], $file['type']);
        }
        
        wp_send_json_success($archivo_data);
    }
    
    /**
     * Generar audio con TTS de OpenAI
     */
    public function generar_audio() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        $texto = sanitize_textarea_field($_POST['texto']);
        
        if (strlen($texto) > 4096) {
            $texto = substr($texto, 0, 4096);
        }
        
        $data = array(
            'model' => 'tts-1',
            'input' => $texto,
            'voice' => 'nova', // nova es voz femenina amigable
            'response_format' => 'mp3'
        );
        
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ),
            CURLOPT_SSL_VERIFYPEER => false
        ));
        
        $audio = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error || $http_code !== 200) {
            wp_send_json_error('Error generando audio');
        }
        
        // Guardar audio temporalmente
        $upload_dir = wp_upload_dir();
        $audio_file = $upload_dir['basedir'] . '/aria-audio/' . uniqid('aria_') . '.mp3';
        $audio_url = $upload_dir['baseurl'] . '/aria-audio/' . basename($audio_file);
        
        // Crear directorio si no existe
        wp_mkdir_p(dirname($audio_file));
        
        file_put_contents($audio_file, $audio);
        
        // Registrar costo TTS (aprox $0.015 por 1000 caracteres)
        $costo = (strlen($texto) / 1000) * 0.015;
        $this->registrar_consumo(0, $costo, 'tts-1');
        
        wp_send_json_success(array('audio_url' => $audio_url));
    }
    
    /**
     * Obtener historial de conversaciones
     */
    public function obtener_historial() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $tabla = $this->tabla_historial;
        
        // Obtener sesiones únicas con subconsulta corregida
        $sesiones = $wpdb->get_results($wpdb->prepare("
            SELECT 
                session_id, 
                MIN(created_at) as inicio, 
                MAX(created_at) as ultimo,
                COUNT(*) as mensajes
            FROM {$tabla}
            WHERE user_id = %d
            GROUP BY session_id
            ORDER BY ultimo DESC
            LIMIT 20
        ", $user_id), ARRAY_A);
        
        // Obtener el primer mensaje de cada sesión
        foreach ($sesiones as &$s) {
            $primer = $wpdb->get_var($wpdb->prepare("
                SELECT content FROM {$tabla} 
                WHERE session_id = %s AND role = 'user' 
                ORDER BY created_at ASC 
                LIMIT 1
            ", $s['session_id']));
            $s['primer_mensaje'] = $primer ? $primer : 'Sin mensaje';
        }
        
        wp_send_json_success($sesiones);
    }
    
    /**
     * Cargar mensajes de una sesión específica
     */
    public function cargar_sesion() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        global $wpdb;
        $session_id = sanitize_text_field($_POST['session_id']);
        $user_id = get_current_user_id();
        
        $mensajes = $wpdb->get_results($wpdb->prepare("
            SELECT role, content, created_at
            FROM {$this->tabla_historial}
            WHERE session_id = %s AND user_id = %d
            ORDER BY created_at ASC
        ", $session_id, $user_id), ARRAY_A);
        
        wp_send_json_success($mensajes);
    }
    
    /**
     * Crear nueva sesión
     */
    public function nueva_sesion() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        $session_id = 'aria_' . get_current_user_id() . '_' . time();
        wp_send_json_success(array('session_id' => $session_id));
    }
    
    /**
     * Obtener historial de una sesión
     */
    private function obtener_historial_sesion($session_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT role, content
            FROM {$this->tabla_historial}
            WHERE session_id = %s
            ORDER BY created_at ASC
            LIMIT 20
        ", $session_id), ARRAY_A);
    }
    
    /**
     * Construir mensajes para OpenAI
     */
    private function construir_mensajes($contexto_crm, $historial, $mensaje_actual, $archivos = array(), $es_admin = false) {
        $messages = array();
        
        // System prompt
        $system = "Eres {$this->nombre_agente}, el Manager de AutomatizaTech eXpert - Tu experto en CRM, Automatizaciones y Monitoreo de Errores. ";
        $system .= "Tienes acceso COMPLETO a los datos de AutomatizaTech: leads, propuestas, reuniones de seguimiento, workflows N8N y errores ARGOS. ";
        $system .= "Eres amigable, profesional y muy técnico cuando se requiere. Respondes en español.\n\n";

        // Identificar al usuario actual
        $current_user = wp_get_current_user();
        $nombre_usuario = $current_user->first_name ?: $current_user->display_name;
        $system .= "USUARIO ACTUAL: {$nombre_usuario}\n";
        $system .= "Salúdalo por su nombre cuando sea la primera interacción de la conversación.\n\n";

        $system .= "DATOS ACTUALES:\n" . $contexto_crm;
        $system .= "\n\nINSTRUCCIONES GENERALES:\n";
        $system .= "1. Responde de forma clara y estructurada\n";
        $system .= "2. Usa emojis para hacer las respuestas más visuales\n";
        $system .= "3. Si te envían imágenes o documentos, analízalos detalladamente\n";
        $system .= "4. Si preguntan por facturación AI, aplica markup del 30%\n";
        $system .= "5. Puedes dar recomendaciones basadas en los datos\n\n";
        
        $system .= "GESTIÓN DE CLIENTES Y LEADS:\n";
        $system .= "6. Tienes acceso a TODOS los leads, propuestas enviadas, clientes contratados y reuniones de seguimiento\n";
        $system .= "7. HAY 2 TIPOS DE CLIENTES:\n";
        $system .= "   - 'CLIENTES CON PROPUESTA ACTIVA': Prospectos a los que se les envió cotización pero AÚN NO han contratado (sección 👥)\n";
        $system .= "   - 'CLIENTES CONTRATADOS/ACTIVOS': Clientes que YA tienen un servicio contratado (sección 👤 con [CONTRATADO])\n";
        $system .= "8. Puedes buscar en ambas listas por nombre, email o empresa\n";
        $system .= "9. Conoces la disponibilidad de agenda para agendar reuniones\n";
        $system .= "10. IMPORTANTE PARA AGENDAR: Cuando el usuario mencione un nombre de cliente, PRIMERO busca en AMBAS listas del contexto. Si encuentras al cliente, YA TIENES su email, empresa y teléfono - NO los pidas de nuevo.\n";
        $system .= "11. Solo pide email/datos si el cliente NO aparece en ninguna de las dos listas\n";
        $system .= "12. Siempre verifica disponibilidad antes de confirmar un horario\n";
        $system .= "13. Para agendar, responde con el formato especial: [AGENDAR_SEGUIMIENTO] seguido de los datos en JSON\n\n";
        
        $system .= "HISTORIAL Y SEGUIMIENTO DE CLIENTES:\n";
        $system .= "14. Cada cliente tiene un HISTORIAL DE SEGUIMIENTO completo con:\n";
        $system .= "    - 📋 Propuestas enviadas, cotizaciones, reuniones pasadas\n";
        $system .= "    - 📎 Documentos adjuntos (presentaciones Gamma, transcripciones de llamadas, contratos, IMÁGENES)\n";
        $system .= "    - 💰 Pagos, boletas y facturas registradas\n";
        $system .= "    - 📊 Estado actual de cada item (pendiente, en progreso, completado)\n";
        $system .= "15. Usa el historial para dar contexto en las respuestas (ej: 'En tu última reunión del 15/01 discutimos...')\n";
        $system .= "16. DOCUMENTOS Y CONTENIDO: Puedes leer el contenido de los documentos adjuntos a cada cliente:\n";
        $system .= "    - 📄 PDF: Texto extraído del documento\n";
        $system .= "    - 📝 Word (.docx): Contenido del documento Word\n";
        $system .= "    - 📊 Excel (.xlsx): Datos de las hojas de cálculo\n";
        $system .= "    - 📽️ PowerPoint (.pptx): Texto de las diapositivas\n";
        $system .= "    - 🖼️ Imágenes (jpg, png): Análisis visual con descripción\n";
        $system .= "    - 📋 Texto/CSV: Contenido directo\n";
        $system .= "    - 🎙️ Transcripciones: Resumen, puntos clave y acciones pendientes\n";
        $system .= "    - 🎨 Presentación Gamma: Link a presentaciones enviadas\n";
        $system .= "17. Cuando un prospecto se contrata, TODO su historial comercial se migra al historial del proyecto\n";
        $system .= "18. USA LA INFORMACIÓN DE DOCUMENTOS para dar respuestas contextuales. El análisis está en [document_analysis] del metadata\n";
        $system .= "19. Si el documento tiene análisis, úsalo para responder preguntas como '¿qué dice el Excel?' o '¿qué muestra la imagen?'\n\n";
        
        $system .= "GOOGLE DRIVE:\n";
        $system .= "20. Tienes acceso a Google Drive de los clientes si tienen una carpeta vinculada\n";
        $system .= "21. Puedes listar archivos de Drive y leer su contenido (Google Docs, Sheets, Slides, PDF, Word, Excel, PPT)\n";
        $system .= "22. Si el cliente tiene drive_folder_id, sus archivos de Drive aparecerán en el contexto\n";
        $system .= "23. Para leer un archivo de Drive, puedes solicitar su contenido por ID\n\n";
        
        $system .= "WORKFLOWS N8N:\n";
        $system .= "24. Si preguntan por workflows, explica detalladamente qué hace cada uno\n";
        $system .= "25. Para explicar un workflow: propósito, triggers, nodos principales y resultado\n";
        $system .= "26. Deduce funciones por palabras clave (WhatsApp, Reminder, Calendar, etc)\n\n";
        
        $system .= "ARGOS - MONITOREO DE ERRORES:\n";
        $system .= "27. Analiza los datos de ARGOS y da diagnósticos claros\n";
        $system .= "28. Sugiere causas y soluciones basándote en el tipo de error\n";
        $system .= "29. Si hay errores críticos sin resolver, menciónalos proactivamente\n";
        $system .= "30. Identifica patrones: workflows o nodos que fallan frecuentemente\n\n";
        
        $system .= "FORMATO ESPECIAL PARA AGENDAR:\n";
        $system .= "Cuando el usuario confirme que quiere agendar y tengas TODOS los datos (nombre, email, fecha, hora), incluye al final de tu respuesta:\n";
        $system .= "[AGENDAR_SEGUIMIENTO]{\"nombre\":\"X\",\"email\":\"X\",\"empresa\":\"X\",\"telefono\":\"X\",\"fecha\":\"YYYY-MM-DD\",\"hora\":\"HH:MM\",\"asunto\":\"Reunión de Seguimiento - AutomatizaTech\"}[/AGENDAR_SEGUIMIENTO]\n";
        $system .= "RECUERDA: Si el cliente está en CUALQUIERA de las 2 listas (propuestas activas o clientes contratados), usa sus datos del contexto directamente. NO pidas email ni teléfono si ya los tienes.\n";
        $system .= "El sistema procesará automáticamente esta instrucción.\n";

        $system .= "\nMÓDULO QA — GESTIÓN DE PRUEBAS DE SOFTWARE:\n";
        $system .= "Tienes conocimiento experto del módulo de QA (Control de Calidad) de AutomatizaTech. Puedes responder preguntas sobre su uso.\n";
        $system .= "MENÚ Y ACCESOS:\n";
        $system .= "- Acceso: Panel de WordPress → menú 'QA Testing' → 'Proyectos QA'\n";
        $system .= "- Puede haber múltiples proyectos (ej: Petsgo Marketplace, sitios de clientes)\n";
        $system .= "- Cada proyecto tiene módulos (secciones del sistema a probar)\n";
        $system .= "- Cada módulo contiene casos de prueba individuales\n\n";
        $system .= "FLUJO DE TRABAJO QA:\n";
        $system .= "1. Crear o importar casos de prueba (ID, título, pasos, resultado esperado)\n";
        $system .= "2. Asignar un tester responsable a cada módulo\n";
        $system .= "3. El tester ejecuta cada caso y cambia el estado\n";
        $system .= "4. Agregar evidencias (capturas, videos) a cada caso\n";
        $system .= "5. Dejar comentarios y Bug ID si se encuentra un defecto\n";
        $system .= "6. Generar informe formal del proyecto\n\n";
        $system .= "ESTADOS DE CASOS:\n";
        $system .= "- Sin probar (🔘): no ejecutado aún — estado inicial\n";
        $system .= "- PASS (✅): ejecutado y resultado correcto\n";
        $system .= "- FAIL (❌): ejecutado, resultado incorrecto — hay un bug\n";
        $system .= "- Bloqueado (⚠️): no se puede ejecutar por impedimento externo\n";
        $system .= "- Omitido (⏭️): excluido intencionalmente del alcance\n";
        $system .= "MÉTRICAS:\n";
        $system .= "- Pass Rate = (PASS / total ejecutados) × 100. Meta: ≥95%\n";
        $system .= "- Vista de módulo muestra barra de progreso por colores\n\n";
        $system .= "FUNCIONALIDADES CLAVE:\n";
        $system .= "- Evidencias: subir JPG, PNG, GIF, WEBP, MP4, WEBM, PDF (máx 10MB)\n";
        $system .= "- Comentarios: agregar, editar (✏️) y eliminar por caso\n";
        $system .= "- Bug ID / Ticket: campo para referenciar JIRA, BUG-XXX, etc.\n";
        $system .= "- Lightbox: clic en imagen abre vista previa ampliada\n";
        $system .= "- Cambio de estado: desde la grilla (tabla) o desde el modal de detalle\n";
        $system .= "- Módulo completado (100%): notificación automática al tester asignado\n";
        $system .= "- Proyecto completado (100%): notificación al cliente\n";
        $system .= "- Glosario: botón 'Glosario' en el header explica términos técnicos\n";
        $system .= "- Estados: botón '🚦 Estados' explica qué significa cada estado QA\n\n";
        $system .= "PERMISOS:\n";
        $system .= "- Administradores: acceso total (crear proyectos, asignar testers, generar informes)\n";
        $system .= "- Testers: ejecutar pruebas, subir evidencias, comentar\n\n";

        // =========================================================
        // CONOCIMIENTO COMPLETO DE AUTOMATIZATECH — EMPRESA Y BACKEND
        // =========================================================
        $system .= "AUTOMATIZATECH — EMPRESA:\n";
        $system .= "AutomatizaTech es una empresa chilena de tecnología especializada en automatización de negocios.\n";
        $system .= "Misión: ayudar a emprendimientos y empresas a optimizar ventas, atención al cliente y operaciones mediante WordPress, CRM personalizado, n8n e IA.\n";
        $system .= "Web: automatizatech.cl | Email: contacto@automatizatech.cl\n\n";

        $system .= "SERVICIOS QUE OFRECE AT:\n";
        $system .= "1. 🌐 Sitio Web WordPress personalizado — landing configurada para capturar leads\n";
        $system .= "2. 🤖 Chatbot WhatsApp Business — responde consultas, agenda reuniones, confirma pagos\n";
        $system .= "3. 📊 CRM personalizado — gestión de leads, propuestas, proyectos, historial y pagos\n";
        $system .= "4. ⚙️ Automatización con n8n — flujos: recordatorios (72h/24h/1h), sincronización de calendario, limpieza de estados Redis\n";
        $system .= "5. 🧠 IA integrada — MAXTECH (asistente interno), análisis de documentos, tracking de consumo OpenAI\n";
        $system .= "6. 🔍 ARGOS — monitoreo de errores en workflows n8n, diagnóstico automático con IA\n";
        $system .= "7. 📋 QA Testing — módulo de pruebas de software para proyectos de clientes\n";
        $system .= "8. 📄 Generación de PDF — cotizaciones, boletas, facturas, informes QA\n";
        $system .= "9. 🔐 Bóveda de Credenciales — almacenamiento encriptado (AES-256) de claves y contraseñas\n";
        $system .= "10. 📈 Dashboard de consumo IA — monitoreo de tokens y costos OpenAI por cliente\n\n";

        $system .= "PLANES COMERCIALES:\n";
        $system .= "- Plan Básico: sitio web + formulario de contacto\n";
        $system .= "- Plan Pro: web + chatbot WhatsApp + CRM\n";
        $system .= "- Plan Ultimate: todo lo anterior + automatizaciones n8n + IA\n";
        $system .= "- Proyectos Personalizados: desarrollo a medida según necesidad del cliente\n";
        $system .= "- Precio base en USD y CLP — el markup para facturación de IA es 30%\n\n";

        $system .= "EQUIPO (usuarios conocidos del backend):\n";
        $system .= "- lgonzalez@automatizatech.cl — administrador principal\n";
        $system .= "- anamaria.sandoval@automatizatech.cl — equipo AT (BCC en notificaciones)\n";
        $system .= "- automatizacionesbotcore@gmail.com — cuenta operativa n8n/bots\n\n";

        $system .= "MENÚS Y SECCIONES DEL PANEL DE ADMINISTRACIÓN WORDPRESS:\n";
        $system .= "Puedes guiar a cualquier usuario backend diciéndole exactamente dónde ir.\n\n";
        $system .= "📌 CONTACTOS (menú principal):\n";
        $system .= "  → 'Leads / Demos': verifica y gestiona demos agendadas desde el sitio web\n";
        $system .= "  → 'Clientes AT': CRM principal con todos los clientes contratados y prospectos activos\n";
        $system .= "  → 'Propuestas': lista de cotizaciones enviadas (con links únicos)\n";
        $system .= "  → 'Seguimientos': reuniones de seguimiento de proyectos activos\n\n";
        $system .= "📌 QA TESTING (menú):\n";
        $system .= "  → 'Proyectos QA': lista de todos los proyectos de prueba\n";
        $system .= "  → Al entrar a un proyecto: ver módulos, casos, estados, evidencias\n";
        $system .= "  → 'Configuración QA': gestionar permisos, importar casos desde Markdown\n\n";
        $system .= "📌 AUTOMATIZA AI (menú):\n";
        $system .= "  → Dashboard de consumo de tokens OpenAI por cliente y por mes\n";
        $system .= "  → Costos estimados, # de llamadas, alertas de uso\n\n";
        $system .= "📌 ARGOS — N8N (menú):\n";
        $system .= "  → Lista de errores de workflows n8n capturados automáticamente\n";
        $system .= "  → Estado, diagnóstico IA, workflow afectado y timestamp\n\n";
        $system .= "📌 MAXTECH / ARIA (widget flotante, esquina inferior derecha):\n";
        $system .= "  → Este chat — disponible en TODAS las páginas del admin\n";
        $system .= "  → Historial de conversaciones por sesión\n";
        $system .= "  → Soporta: texto, imágenes, audio (grabación), archivos (PDF, Excel, Word, PPT)\n\n";
        $system .= "📌 HERRAMIENTAS → 'Bóveda de Credenciales':\n";
        $system .= "  → Guardar/consultar contraseñas y claves de API encriptadas\n\n";
        $system .= "📌 AJUSTES → sub-menú MAXTECH Config:\n";
        $system .= "  → Configurar modelo GPT, temperatura, máximo de tokens para ARIA\n\n";

        $system .= "TECNOLOGÍAS DEL STACK:\n";
        $system .= "- WordPress (PHP) — base del sistema, mu-plugins para módulos críticos\n";
        $system .= "- MySQL — base de datos: tablas wp_crm_*, wp_at_qa_*, wp_automatiza_*, wp_n8n_*\n";
        $system .= "- n8n — motor de automatización de flujos (self-hosted)\n";
        $system .= "- Redis — estado de conversaciones WhatsApp (sesiones temporales)\n";
        $system .= "- OpenAI API (GPT-4, GPT-4o) — procesamiento de lenguaje natural y visión\n";
        $system .= "- Google Calendar API — disponibilidad y agendamiento de reuniones\n";
        $system .= "- Google Drive API — vinculación de carpetas de clientes\n";
        $system .= "- SMTP Hostinger (PROD) / MailHog (local) — envío de correos\n";
        $system .= "- FPDF (PHP) — generación de PDFs\n";
        $system .= "- AES-256-CBC — encriptación de credenciales\n\n";

        $system .= "INSTRUCCIONES PARA RESPONDER A USUARIOS BACKEND:\n";
        $system .= "- Si preguntan '¿cómo hago X?', explica el flujo paso a paso con el menú exacto donde ir\n";
        $system .= "- Si preguntan por un cliente, búscalo en el contexto CRM y entrega sus datos\n";
        $system .= "- Si preguntan por errores de PROD, revisa el contexto ARGOS e interpreta el error\n";
        $system .= "- Si preguntan por el estado de un proyecto QA, indica cómo acceder a él\n";
        $system .= "- Si no tienes datos específicos en el contexto, di que no tienes acceso a ese dato en tiempo real pero explica cómo encontrarlo manualmente\n";
        $system .= "- NUNCA inventes datos de clientes, leads o estados — usa solo lo del contexto\n\n";

        // Informar a ARIA sobre el rol del usuario actual
        $rol_usuario = $es_admin ? 'ADMINISTRADOR' : 'Usuario estándar (no administrador)';
        $system .= "ROL DEL USUARIO ACTUAL: {$rol_usuario}\n\n";

        $system .= "PRIVACIDAD Y ALCANCE DE DATOS:\n";
        $system .= "- Ayuda a TODOS los usuarios con: cómo navegar el backend, cómo usar el módulo QA, cómo crear casos, cómo cambiar estados, cómo asignar testers, y cualquier procedimiento o pregunta funcional del sistema\n";
        $system .= "- Los DATOS PRIVADOS de clientes (emails, teléfonos, RUTs, montos, propuestas específicas, leads concretos) son CONFIDENCIALES — compártelos SOLO con ADMINISTRADORES\n";
        $system .= "- Si el usuario NO es administrador y pide datos privados de un cliente específico (ej: '¿cuál es el email de Juan Pérez?'), niégalo cortésmente y explica que no tienes autorización\n";
        $system .= "- Cuando tengas contexto de un cliente específico, responde SOLO sobre ese cliente — no mezcles información entre clientes\n";
        $system .= "- Nunca reveles datos de un cliente a otro usuario no autorizado\n\n";

        $messages[] = array('role' => 'system', 'content' => $system);
        
        // Historial de la conversación
        foreach ($historial as $h) {
            $content = $h['content'];
            $messages[] = array('role' => $h['role'], 'content' => $content);
        }
        
        // Mensaje actual
        if (!empty($archivos)) {
            // Mensaje con archivos (para Vision API)
            $content = array();
            $content[] = array('type' => 'text', 'text' => $mensaje_actual);
            
            foreach ($archivos as $archivo) {
                if (strpos($archivo['type'], 'image/') === 0) {
                    $content[] = array(
                        'type' => 'image_url',
                        'image_url' => array('url' => $archivo['url'])
                    );
                } elseif (isset($archivo['contenido'])) {
                    // Para PDFs/texto, agregar el contenido al mensaje
                    $content[0]['text'] .= "\n\n[Contenido del archivo {$archivo['name']}]:\n" . $archivo['contenido'];
                }
            }
            
            $messages[] = array('role' => 'user', 'content' => $content);
        } else {
            $messages[] = array('role' => 'user', 'content' => $mensaje_actual);
        }
        
        return $messages;
    }
    
    /**
     * Llamar a OpenAI
     */
    private function llamar_openai($messages, $tiene_imagenes = false) {
        // Usar GPT-4o si hay imágenes, sino GPT-4o-mini
        $model = $tiene_imagenes ? 'gpt-4o' : 'gpt-4o-mini';
        
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 2000
        );
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60
        ));
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return array('texto' => "Error de conexión: {$error}", 'tokens' => 0, 'cost' => 0);
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            $usage = isset($result['usage']) ? $result['usage'] : array();
            $tokens = isset($usage['total_tokens']) ? $usage['total_tokens'] : 0;
            $cost = $this->calcular_costo($model, 
                isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : 0,
                isset($usage['completion_tokens']) ? $usage['completion_tokens'] : 0
            );
            
            return array(
                'texto' => $result['choices'][0]['message']['content'],
                'tokens' => $tokens,
                'cost' => $cost
            );
        }
        
        return array('texto' => "Error al procesar respuesta", 'tokens' => 0, 'cost' => 0);
    }
    
    /**
     * Obtener contexto del CRM
     */
    private function obtener_contexto_crm() {
        global $wpdb;
        
        $contexto = "";
        $fecha = date('d/m/Y H:i');
        $contexto .= "Fecha actual: {$fecha}\n\n";
        
        // Stats del mes
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as requests,
                COALESCE(SUM(tokens_total), 0) as tokens,
                COALESCE(SUM(costo_usd), 0) as costo,
                COALESCE(SUM(CASE WHEN client_identifier LIKE 'cliente_%' THEN costo_usd ELSE 0 END), 0) as facturable
            FROM {$wpdb->prefix}ai_usage_log
            WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())
        ", ARRAY_A);
        
        if (!$stats) {
            $stats = array('requests' => 0, 'tokens' => 0, 'costo' => 0, 'facturable' => 0);
        }
        $facturar = number_format(floatval($stats['facturable']) * 1.3, 2);
        $contexto .= "CONSUMO AI MES ACTUAL:\n";
        $contexto .= "- Peticiones: {$stats['requests']}\n";
        $contexto .= "- Tokens: {$stats['tokens']}\n";
        $contexto .= "- Costo OpenAI: USD {$stats['costo']}\n";
        $contexto .= "- A facturar (+30%): USD {$facturar}\n\n";
        
        // Top clientes
        $clientes = $wpdb->get_results("
            SELECT client_identifier, SUM(costo_usd) as costo, COUNT(*) as requests
            FROM {$wpdb->prefix}ai_usage_log
            WHERE client_identifier LIKE 'cliente_%'
            GROUP BY client_identifier
            ORDER BY costo DESC
            LIMIT 5
        ", ARRAY_A);
        
        if (!$clientes) { $clientes = array(); }
        $contexto .= "TOP CLIENTES AI:\n";
        foreach ($clientes as $c) {
            $nombre = str_replace(array('cliente_', '_'), array('', ' '), $c['client_identifier']);
            $contexto .= "- {$nombre}: {$c['requests']} peticiones, USD {$c['costo']}\n";
        }
        
        // Demos AI activos
        $demos = $wpdb->get_results("
            SELECT client_identifier, COUNT(*) as interacciones
            FROM {$wpdb->prefix}ai_usage_log
            WHERE client_identifier LIKE 'demo_%'
            GROUP BY client_identifier
        ", ARRAY_A);
        
        if (!$demos) { $demos = array(); }
        $contexto .= "\nDEMOS AI ACTIVOS: " . count($demos) . "\n";
        
        // Agregar contexto completo de AutomatizaTech
        $contexto .= $this->obtener_contexto_automatizatech();
        
        // Agregar contexto de workflows N8N
        $contexto .= $this->obtener_contexto_workflows();
        
        // Agregar contexto de errores ARGOS
        $contexto .= $this->obtener_contexto_argos();

        // Agregar contexto del módulo QA
        $contexto .= $this->obtener_contexto_qa();
        
        return $contexto;
    }

    /**
     * Obtener contexto en tiempo real del módulo QA
     */
    private function obtener_contexto_qa() {
        global $wpdb;
        $t_projects = $wpdb->prefix . 'at_qa_projects';
        $t_modules  = $wpdb->prefix . 'at_qa_modules';
        $t_cases    = $wpdb->prefix . 'at_qa_cases';

        // Verificar que las tablas existen
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t_projects}'") !== $t_projects) {
            return '';
        }

        $ctx = "\n\n=== 🧪 MÓDULO QA — ESTADO ACTUAL ===\n";

        $projects = $wpdb->get_results("SELECT * FROM {$t_projects} ORDER BY created_at DESC LIMIT 10", ARRAY_A);
        if (empty($projects)) {
            $ctx .= "No hay proyectos QA registrados aún.\n";
            return $ctx;
        }

        foreach ($projects as $p) {
            $pid = intval($p['id']);
            // Totales por proyecto
            $stats = $wpdb->get_row($wpdb->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN c.status='pass' THEN 1 ELSE 0 END) as pass,
                    SUM(CASE WHEN c.status='fail' THEN 1 ELSE 0 END) as fail,
                    SUM(CASE WHEN c.status='blocked' THEN 1 ELSE 0 END) as blocked,
                    SUM(CASE WHEN c.status='skipped' THEN 1 ELSE 0 END) as skipped,
                    SUM(CASE WHEN c.status='not_tested' OR c.status IS NULL THEN 1 ELSE 0 END) as not_tested
                FROM {$t_cases} c
                JOIN {$t_modules} m ON m.id = c.module_id
                WHERE m.project_id = %d
            ", $pid), ARRAY_A);

            $total    = intval($stats['total'] ?? 0);
            $pass     = intval($stats['pass'] ?? 0);
            $fail     = intval($stats['fail'] ?? 0);
            $blocked  = intval($stats['blocked'] ?? 0);
            $skipped  = intval($stats['skipped'] ?? 0);
            $untested = intval($stats['not_tested'] ?? 0);
            $ejecutados = $pass + $fail + $blocked + $skipped;
            $pass_rate = $ejecutados > 0 ? round(($pass / $ejecutados) * 100, 1) : 0;

            $ctx .= "\n📁 Proyecto: {$p['name']} (ID:{$pid})\n";
            $ctx .= "   Cliente: " . ($p['client_name'] ?? 'N/A') . "\n";
            $ctx .= "   Casos: {$total} total | ✅ {$pass} PASS | ❌ {$fail} FAIL | ⚠️ {$blocked} BLQ | ⏭️ {$skipped} OMIT | 🔘 {$untested} sin probar\n";
            $ctx .= "   Pass Rate: {$pass_rate}%\n";

            // Módulos del proyecto
            $modules = $wpdb->get_results($wpdb->prepare("
                SELECT m.id, m.title, m.assigned_tester, COUNT(c.id) as total_cases,
                    SUM(CASE WHEN c.status='pass' THEN 1 ELSE 0 END) as pass_cases,
                    SUM(CASE WHEN c.status='fail' THEN 1 ELSE 0 END) as fail_cases
                FROM {$t_modules} m
                LEFT JOIN {$t_cases} c ON c.module_id = m.id
                WHERE m.project_id = %d
                GROUP BY m.id
                ORDER BY m.id ASC
            ", $pid), ARRAY_A);

            foreach ($modules as $mod) {
                $mtotal = intval($mod['total_cases'] ?? 0);
                $mpass  = intval($mod['pass_cases'] ?? 0);
                $mfail  = intval($mod['fail_cases'] ?? 0);
                $tester = $mod['assigned_tester'] ? get_userdata($mod['assigned_tester']) : null;
                $tester_name = $tester ? $tester->display_name : 'Sin asignar';
                $mod_name = $mod['title'] ?? '(sin nombre)';
                $ctx .= "   └─ Módulo: {$mod_name} | {$mtotal} casos | ✅{$mpass} ❌{$mfail} | Tester: {$tester_name}\n";
            }
        }

        return $ctx;
    }

    /**
     * Obtener contexto completo de AutomatizaTech (Leads, Propuestas, Seguimientos)
     */
    private function obtener_contexto_automatizatech() {
        global $wpdb;
        
        $leads_table = $wpdb->prefix . 'automatiza_leads';
        $propuestas_table = $wpdb->prefix . 'automatiza_propuestas';
        $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
        
        // Verificar que las tablas principales existan antes de consultarlas
        $tablas_requeridas = array($leads_table, $propuestas_table, $followup_table);
        foreach ($tablas_requeridas as $t) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) {
                return "\n\nDATOS COMERCIALES: Tablas no encontradas en esta instalación.\n";
            }
        }
        
        $contexto = "\n\n=== 📊 DATOS COMERCIALES AUTOMATIZATECH ===\n";
        
        // ========== LEADS / DEMOS ==========
        $contexto .= "\n🎯 LEADS / DEMOS AGENDADAS:\n";
        
        // Estadísticas de leads
        $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $leads_table") ?: 0;
        $leads_hoy = $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE scheduled_date = CURDATE()") ?: 0;
        $leads_semana = $wpdb->get_var("SELECT COUNT(*) FROM $leads_table WHERE scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)") ?: 0;
        
        // Por estado
        $leads_por_estado = $wpdb->get_results("
            SELECT 
                COALESCE(status, 'pendiente') as status, 
                COUNT(*) as total 
            FROM $leads_table 
            GROUP BY status
        ", ARRAY_A);
        
        $contexto .= "- Total leads: {$total_leads}\n";
        $contexto .= "- Demos hoy: {$leads_hoy}\n";
        $contexto .= "- Demos próximos 7 días: {$leads_semana}\n";
        $contexto .= "- Por estado: ";
        $estados_leads = array();
        foreach ($leads_por_estado as $e) {
            $estados_leads[] = "{$e['status']}:{$e['total']}";
        }
        $contexto .= implode(', ', $estados_leads) . "\n";
        
        // Próximas 5 demos
        $proximas_demos = $wpdb->get_results("
            SELECT id, name, email, phone, '' as company, scheduled_date, scheduled_time, status
            FROM $leads_table 
            WHERE scheduled_date >= CURDATE()
            AND (status IS NULL OR status NOT IN ('cancelled', 'no_show', 'completed'))
            ORDER BY scheduled_date ASC, scheduled_time ASC
            LIMIT 5
        ", ARRAY_A);
        
        if (!empty($proximas_demos)) {
            $contexto .= "\n📅 Próximas demos:\n";
            foreach ($proximas_demos as $d) {
                $fecha = date('d/m', strtotime($d['scheduled_date']));
                $hora = substr($d['scheduled_time'], 0, 5);
                $contexto .= "- {$fecha} {$hora}h: {$d['name']}";
                if ($d['company']) $contexto .= " ({$d['company']})";
                $contexto .= " [ID:{$d['id']}]\n";
            }
        }
        
        // ========== PROPUESTAS ==========
        $contexto .= "\n📋 PROPUESTAS ENVIADAS:\n";
        
        $total_propuestas = $wpdb->get_var("SELECT COUNT(*) FROM $propuestas_table") ?: 0;
        $propuestas_por_estado = $wpdb->get_results("
            SELECT 
                COALESCE(status, 'active') as status, 
                COUNT(*) as total 
            FROM $propuestas_table 
            GROUP BY status
        ", ARRAY_A);
        
        $contexto .= "- Total propuestas: {$total_propuestas}\n";
        $estados_prop = array();
        foreach ($propuestas_por_estado as $e) {
            $estados_prop[] = "{$e['status']}:{$e['total']}";
        }
        $contexto .= "- Por estado: " . implode(', ', $estados_prop) . "\n";
        
        // Últimas 5 propuestas
        $ultimas_propuestas = $wpdb->get_results("
            SELECT id, client_name, client_email, company_name, unique_link_id, status, created_at
            FROM $propuestas_table 
            ORDER BY created_at DESC
            LIMIT 5
        ", ARRAY_A);
        
        if (!empty($ultimas_propuestas)) {
            $contexto .= "\n📄 Últimas propuestas:\n";
            foreach ($ultimas_propuestas as $p) {
                $fecha = date('d/m/Y', strtotime($p['created_at']));
                $estado = $p['status'] ?: 'active';
                $contexto .= "- [{$estado}] {$p['client_name']}";
                if ($p['company_name']) $contexto .= " ({$p['company_name']})";
                $contexto .= " - {$fecha} [Link:{$p['unique_link_id']}]\n";
            }
        }
        
        // Clientes con propuesta para seguimiento
        $clientes_propuesta = $wpdb->get_results("
            SELECT id, client_name, client_email, company_name, phone, unique_link_id, created_at
            FROM $propuestas_table 
            WHERE status = 'active' OR status IS NULL
            ORDER BY created_at DESC
            LIMIT 10
        ", ARRAY_A);
        
        if (!empty($clientes_propuesta)) {
            $contexto .= "\n👥 CLIENTES CON PROPUESTA ACTIVA (usa estos datos para agendar seguimiento):\n";
            foreach ($clientes_propuesta as $c) {
                $contexto .= "• Nombre: {$c['client_name']}";
                if ($c['company_name']) $contexto .= " | Empresa: {$c['company_name']}";
                $contexto .= " | Email: {$c['client_email']}";
                if (!empty($c['phone'])) $contexto .= " | Teléfono: {$c['phone']}";
                $contexto .= " | ID_Propuesta: {$c['unique_link_id']}";
                
                // Obtener detalles de seguimiento del prospecto
                $propuesta_details = $wpdb->get_results($wpdb->prepare("
                    SELECT detail_type, title, status, amount, attachment_name, created_at
                    FROM {$wpdb->prefix}automatiza_propuestas_details
                    WHERE propuesta_id = %d
                    ORDER BY created_at DESC
                    LIMIT 5
                ", $c['id']), ARRAY_A);
                
                if (!empty($propuesta_details)) {
                    $contexto .= "\n  📋 Historial de seguimiento:\n";
                    foreach ($propuesta_details as $d) {
                        $fecha_d = date('d/m', strtotime($d['created_at']));
                        $contexto .= "    - [{$fecha_d}] {$d['detail_type']}: {$d['title']} ({$d['status']})";
                        if ($d['amount'] > 0) $contexto .= " \${$d['amount']}";
                        if ($d['attachment_name']) $contexto .= " 📎{$d['attachment_name']}";
                        $contexto .= "\n";
                    }
                }
                $contexto .= "\n";
            }
        }
        
        // ========== CLIENTES CONTRATADOS ==========
        $clients_table = $wpdb->prefix . 'automatiza_tech_clients';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$clients_table}'") !== $clients_table) {
            $contexto .= "\n✅ CLIENTES CONTRATADOS: Tabla no encontrada.\n";
            return $contexto;
        }
        $contexto .= "\n✅ CLIENTES CONTRATADOS (ya tienen servicio activo):\n";
        
        $total_clientes = $wpdb->get_var("SELECT COUNT(*) FROM $clients_table") ?: 0;
        $clientes_activos = $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE contract_status = 'active'") ?: 0;
        
        $contexto .= "- Total clientes contratados: {$total_clientes}\n";
        $contexto .= "- Clientes activos: {$clientes_activos}\n";
        
        // Lista de clientes contratados activos (para agendar reuniones)
        $clientes_contratados = $wpdb->get_results("
            SELECT id, name, email, company, phone, project_type, contract_status, notes,
                   app_url, domain, social_instagram, social_facebook, whatsapp_business,
                   business_industry, sla_level, monthly_fee, operational_notes
            FROM $clients_table 
            WHERE contract_status = 'active'
            ORDER BY contracted_at DESC
            LIMIT 15
        ", ARRAY_A);
        
        if (!empty($clientes_contratados)) {
            $contexto .= "\n👤 CLIENTES ACTIVOS (usa estos datos para agendar reuniones):\n";
            foreach ($clientes_contratados as $cl) {
                $contexto .= "• Nombre: {$cl['name']}";
                if ($cl['company']) $contexto .= " | Empresa: {$cl['company']}";
                $contexto .= " | Email: {$cl['email']}";
                if (!empty($cl['phone'])) $contexto .= " | Teléfono: {$cl['phone']}";
                if ($cl['project_type']) $contexto .= " | Servicio: {$cl['project_type']}";
                $contexto .= " [CONTRATADO]";
                
                // Información operativa del cliente
                $info_operativa = [];
                if (!empty($cl['app_url'])) $info_operativa[] = "Web: {$cl['app_url']}";
                if (!empty($cl['domain'])) $info_operativa[] = "Dominio: {$cl['domain']}";
                if (!empty($cl['whatsapp_business'])) $info_operativa[] = "WA: {$cl['whatsapp_business']}";
                if (!empty($cl['social_instagram'])) $info_operativa[] = "IG: {$cl['social_instagram']}";
                if (!empty($cl['business_industry'])) $info_operativa[] = "Rubro: {$cl['business_industry']}";
                if (!empty($cl['sla_level'])) $info_operativa[] = "SLA: {$cl['sla_level']}";
                if (!empty($cl['monthly_fee']) && $cl['monthly_fee'] > 0) $info_operativa[] = "Cuota: \${$cl['monthly_fee']}/mes";
                
                if (!empty($info_operativa)) {
                    $contexto .= "\n  🔧 Info operativa: " . implode(' | ', $info_operativa);
                }
                
                // Obtener detalles de seguimiento del cliente (incluye documentos)
                $client_details = $wpdb->get_results($wpdb->prepare("
                    SELECT id, detail_type, title, description, status, amount, project_start_date, 
                           attachment_name, attachment_url, attachment_type, metadata, created_at
                    FROM {$wpdb->prefix}automatiza_clients_details
                    WHERE client_id = %d
                    ORDER BY created_at DESC
                    LIMIT 10
                ", $cl['id']), ARRAY_A);
                
                if (!empty($client_details)) {
                    $contexto .= "\n  📊 Historial y documentos del proyecto:\n";
                    foreach ($client_details as $d) {
                        $fecha_d = date('d/m', strtotime($d['created_at']));
                        $contexto .= "    - [{$fecha_d}] {$d['detail_type']}: {$d['title']} ({$d['status']})";
                        if ($d['amount'] > 0) $contexto .= " \${$d['amount']}";
                        if ($d['project_start_date']) $contexto .= " 🚀Inicio:{$d['project_start_date']}";
                        $contexto .= "\n";
                        
                        // Si hay documento adjunto, mostrar info y resumen
                        if (!empty($d['attachment_name'])) {
                            $contexto .= "      📎 Documento: {$d['attachment_name']}";
                            if (!empty($d['attachment_type'])) {
                                $contexto .= " ({$d['attachment_type']})";
                            }
                            $contexto .= "\n";
                        }
                        
                        // Si hay descripción del documento/item (contiene resumen o contenido)
                        if (!empty($d['description'])) {
                            // Limitar a 500 chars para contexto
                            $desc_clean = strip_tags($d['description']);
                            $desc_truncated = substr($desc_clean, 0, 500);
                            if (strlen($desc_clean) > 500) $desc_truncated .= '...';
                            $contexto .= "      📝 Contenido/Resumen: {$desc_truncated}\n";
                        }
                        
                        // Si hay metadata con información adicional
                        if (!empty($d['metadata'])) {
                            $meta = json_decode($d['metadata'], true);
                            if (is_array($meta)) {
                                // Extraer info relevante del metadata
                                $meta_info = [];
                                if (!empty($meta['transcription_summary'])) {
                                    $contexto .= "      🎙️ Resumen llamada: " . substr($meta['transcription_summary'], 0, 300) . "...\n";
                                }
                                if (!empty($meta['key_points'])) {
                                    $contexto .= "      🔑 Puntos clave: " . implode(', ', array_slice($meta['key_points'], 0, 5)) . "\n";
                                }
                                if (!empty($meta['action_items'])) {
                                    $contexto .= "      ✅ Acciones: " . implode(', ', array_slice($meta['action_items'], 0, 3)) . "\n";
                                }
                                if (!empty($meta['gamma_url'])) {
                                    $contexto .= "      🎨 Presentación Gamma: {$meta['gamma_url']}\n";
                                }
                            }
                        }
                    }
                }
                
                // Agregar notas del cliente si existen
                if (!empty($cl['notes'])) {
                    $contexto .= "  📝 Notas: " . substr($cl['notes'], 0, 150) . (strlen($cl['notes']) > 150 ? '...' : '') . "\n";
                }
                
                // Agregar notas operativas si existen
                if (!empty($cl['operational_notes'])) {
                    $contexto .= "  🔒 Notas internas: " . substr($cl['operational_notes'], 0, 100) . (strlen($cl['operational_notes']) > 100 ? '...' : '') . "\n";
                }
                $contexto .= "\n";
            }
        }
        
        // ========== REUNIONES DE SEGUIMIENTO ==========
        $contexto .= "\n🤝 REUNIONES DE SEGUIMIENTO:\n";
        
        $total_seguimientos = $wpdb->get_var("SELECT COUNT(*) FROM $followup_table") ?: 0;
        $seguimientos_hoy = $wpdb->get_var("SELECT COUNT(*) FROM $followup_table WHERE meeting_date = CURDATE() AND status = 'scheduled'") ?: 0;
        $seguimientos_pendientes = $wpdb->get_var("SELECT COUNT(*) FROM $followup_table WHERE meeting_date >= CURDATE() AND status = 'scheduled'") ?: 0;
        
        $seguimientos_por_estado = $wpdb->get_results("
            SELECT status, COUNT(*) as total 
            FROM $followup_table 
            GROUP BY status
        ", ARRAY_A);
        
        $contexto .= "- Total reuniones: {$total_seguimientos}\n";
        $contexto .= "- Hoy: {$seguimientos_hoy}\n";
        $contexto .= "- Pendientes: {$seguimientos_pendientes}\n";
        $estados_seg = array();
        foreach ($seguimientos_por_estado as $e) {
            $estados_seg[] = "{$e['status']}:{$e['total']}";
        }
        $contexto .= "- Por estado: " . implode(', ', $estados_seg) . "\n";
        
        // Próximos seguimientos
        $proximos_seguimientos = $wpdb->get_results("
            SELECT id, client_name, client_email, company_name, meeting_date, meeting_time, status
            FROM $followup_table 
            WHERE meeting_date >= CURDATE()
            AND status = 'scheduled'
            ORDER BY meeting_date ASC, meeting_time ASC
            LIMIT 5
        ", ARRAY_A);
        
        if (!empty($proximos_seguimientos)) {
            $contexto .= "\n📆 Próximas reuniones de seguimiento:\n";
            foreach ($proximos_seguimientos as $s) {
                $fecha = date('d/m', strtotime($s['meeting_date']));
                $hora = substr($s['meeting_time'], 0, 5);
                $contexto .= "- {$fecha} {$hora}h: {$s['client_name']}";
                if ($s['company_name']) $contexto .= " ({$s['company_name']})";
                $contexto .= " [ID:{$s['id']}]\n";
            }
        }
        
        // ========== HORARIOS DISPONIBLES HOY Y MAÑANA ==========
        $contexto .= "\n⏰ DISPONIBILIDAD PARA AGENDAR:\n";
        $contexto .= $this->obtener_disponibilidad_agenda();
        
        return $contexto;
    }
    
    /**
     * Obtener disponibilidad de agenda (próximos 3 días)
     */
    private function obtener_disponibilidad_agenda() {
        global $wpdb;
        
        $leads_table = $wpdb->prefix . 'automatiza_leads';
        $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
        
        $horarios_laborales = array('09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00', '18:00');
        $disponibilidad = "";
        
        // Revisar próximos 3 días hábiles
        $dias_revisados = 0;
        $fecha = new DateTime('now', new DateTimeZone('America/Santiago'));
        
        while ($dias_revisados < 3) {
            $dia_semana = $fecha->format('N'); // 1=Lunes, 7=Domingo
            
            // Saltar fines de semana
            if ($dia_semana >= 6) {
                $fecha->modify('+1 day');
                continue;
            }
            
            $fecha_str = $fecha->format('Y-m-d');
            $fecha_display = $fecha->format('d/m (D)');
            
            // Obtener slots ocupados
            $ocupados_leads = $wpdb->get_col($wpdb->prepare("
                SELECT LEFT(scheduled_time, 5) 
                FROM $leads_table 
                WHERE scheduled_date = %s 
                AND (status IS NULL OR status NOT IN ('cancelled', 'no_show', 'completed'))
            ", $fecha_str));
            
            $ocupados_followup = $wpdb->get_col($wpdb->prepare("
                SELECT LEFT(meeting_time, 5) 
                FROM $followup_table 
                WHERE meeting_date = %s 
                AND status NOT IN ('cancelled', 'completed')
            ", $fecha_str));
            
            $ocupados = array_merge($ocupados_leads ?: array(), $ocupados_followup ?: array());
            
            // Calcular libres
            $libres = array_diff($horarios_laborales, $ocupados);
            
            if (!empty($libres)) {
                $disponibilidad .= "- {$fecha_display}: " . implode(', ', $libres) . " hrs libres\n";
            } else {
                $disponibilidad .= "- {$fecha_display}: COMPLETO\n";
            }
            
            $fecha->modify('+1 day');
            $dias_revisados++;
        }
        
        return $disponibilidad;
    }
    
    /**
     * Obtener contexto de errores de ARGOS
     */
    private function obtener_contexto_argos() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'automatiza_n8n_errors';
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$tabla'") != $tabla) {
            return "\n\nARGOS (Errores N8N): Tabla no encontrada.\n";
        }
        
        $contexto = "\n\n=== 🛡️ ARGOS - MONITOREO DE ERRORES N8N ===\n";
        
        // Estadísticas generales
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $tabla");
        $nuevos = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE status = 'new'");
        $investigando = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE status = 'investigating'");
        $resueltos = $wpdb->get_var("SELECT COUNT(*) FROM $tabla WHERE status = 'resolved'");
        
        $contexto .= "📊 Estadísticas:\n";
        $contexto .= "- Total errores registrados: {$total}\n";
        $contexto .= "- 🔴 Nuevos (sin revisar): {$nuevos}\n";
        $contexto .= "- 🟡 En investigación: {$investigando}\n";
        $contexto .= "- 🟢 Resueltos: {$resueltos}\n\n";
        
        // Errores por severidad
        $por_severidad = $wpdb->get_results("
            SELECT severity, COUNT(*) as cantidad 
            FROM $tabla 
            GROUP BY severity 
            ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')
        ", ARRAY_A);
        
        $contexto .= "⚠️ Por severidad:\n";
        foreach ($por_severidad as $s) {
            $emoji = $s['severity'] == 'critical' ? '🔴' : ($s['severity'] == 'high' ? '🟠' : ($s['severity'] == 'medium' ? '🟡' : '🟢'));
            $contexto .= "- {$emoji} {$s['severity']}: {$s['cantidad']}\n";
        }
        
        // Workflows con más errores (top 5)
        $top_workflows = $wpdb->get_results("
            SELECT workflow_name, COUNT(*) as errores, 
                   MAX(created_at) as ultimo_error
            FROM $tabla 
            GROUP BY workflow_name 
            ORDER BY errores DESC 
            LIMIT 5
        ", ARRAY_A);
        
        $contexto .= "\n🔥 Workflows con más errores:\n";
        foreach ($top_workflows as $wf) {
            $contexto .= "- {$wf['workflow_name']}: {$wf['errores']} errores (último: {$wf['ultimo_error']})\n";
        }
        
        // Nodos problemáticos (top 5)
        $top_nodos = $wpdb->get_results("
            SELECT error_node, COUNT(*) as fallos
            FROM $tabla 
            WHERE error_node != ''
            GROUP BY error_node 
            ORDER BY fallos DESC 
            LIMIT 5
        ", ARRAY_A);
        
        if (!empty($top_nodos)) {
            $contexto .= "\n🔧 Nodos más problemáticos:\n";
            foreach ($top_nodos as $n) {
                $contexto .= "- {$n['error_node']}: {$n['fallos']} fallos\n";
            }
        }
        
        // Últimos 5 errores
        $ultimos = $wpdb->get_results("
            SELECT id, workflow_name, error_node, error_message, severity, status, created_at
            FROM $tabla 
            ORDER BY created_at DESC 
            LIMIT 5
        ", ARRAY_A);
        
        $contexto .= "\n📋 Últimos 5 errores:\n";
        foreach ($ultimos as $e) {
            $emoji_sev = $e['severity'] == 'critical' ? '🔴' : ($e['severity'] == 'high' ? '🟠' : '🟡');
            $emoji_status = $e['status'] == 'new' ? '🆕' : ($e['status'] == 'investigating' ? '🔍' : '✅');
            $msg_corto = substr($e['error_message'], 0, 80);
            $contexto .= "{$emoji_sev}{$emoji_status} #{$e['id']} [{$e['workflow_name']}";
            if ($e['error_node']) $contexto .= " → {$e['error_node']}";
            $contexto .= "] {$msg_corto}...\n";
        }
        
        // Errores frecuentes (patrones)
        $patrones = $wpdb->get_results("
            SELECT 
                CASE 
                    WHEN error_message LIKE '%timeout%' THEN 'Timeout'
                    WHEN error_message LIKE '%connection%' OR error_message LIKE '%ECONNREFUSED%' THEN 'Conexión'
                    WHEN error_message LIKE '%401%' OR error_message LIKE '%403%' OR error_message LIKE '%unauthorized%' THEN 'Autenticación'
                    WHEN error_message LIKE '%404%' OR error_message LIKE '%not found%' THEN 'Recurso no encontrado'
                    WHEN error_message LIKE '%500%' OR error_message LIKE '%internal%' THEN 'Error del servidor'
                    WHEN error_message LIKE '%rate limit%' OR error_message LIKE '%429%' THEN 'Rate Limit'
                    WHEN error_message LIKE '%JSON%' OR error_message LIKE '%parse%' THEN 'Error de formato/JSON'
                    ELSE 'Otros'
                END as tipo_error,
                COUNT(*) as cantidad
            FROM $tabla
            GROUP BY tipo_error
            ORDER BY cantidad DESC
        ", ARRAY_A);
        
        $contexto .= "\n📈 Patrones de errores frecuentes:\n";
        foreach ($patrones as $p) {
            $contexto .= "- {$p['tipo_error']}: {$p['cantidad']}\n";
        }
        
        $contexto .= "\nPuedes preguntarme sobre errores específicos, workflows problemáticos, o pedir análisis de un error concreto por su ID.\n";
        
        return $contexto;
    }
    
    /**
     * Obtener workflows de N8N
     */
    private function obtener_workflows_n8n() {
        $api_key = get_option('maxtech_n8n_api_key', '');
        $base_url = get_option('maxtech_n8n_url', $this->n8n_url);
        
        if (empty($api_key)) {
            return null;
        }
        
        $response = wp_remote_get($base_url . '/api/v1/workflows', array(
            'headers' => array(
                'X-N8N-API-KEY' => $api_key,
                'Accept' => 'application/json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['data']) ? $data['data'] : null;
    }
    
    /**
     * Obtener detalle de un workflow específico
     */
    private function obtener_workflow_detalle($workflow_id) {
        $api_key = get_option('maxtech_n8n_api_key', '');
        $base_url = get_option('maxtech_n8n_url', $this->n8n_url);
        
        if (empty($api_key)) {
            return null;
        }
        
        $response = wp_remote_get($base_url . '/api/v1/workflows/' . $workflow_id, array(
            'headers' => array(
                'X-N8N-API-KEY' => $api_key,
                'Accept' => 'application/json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Generar contexto de workflows para el agente
     */
    private function obtener_contexto_workflows() {
        $workflows = $this->obtener_workflows_n8n();
        
        if (!is_array($workflows) || empty($workflows)) {
            return "\nWORKFLOWS N8N: No configurado o sin acceso.\n";
        }
        
        $contexto = "\n\n=== WORKFLOWS N8N ACTIVOS ===\n";
        $contexto .= "Total: " . count($workflows) . " workflows\n\n";
        
        $activos = 0;
        $inactivos = 0;
        
        foreach ($workflows as $wf) {
            $estado = !empty($wf['active']) ? '🟢 ACTIVO' : '⚪ Inactivo';
            if (!empty($wf['active'])) $activos++; else $inactivos++;
            
            $contexto .= "📋 **{$wf['name']}** ({$estado})\n";
            $contexto .= "   ID: {$wf['id']}\n";
            
            // Analizar nodos del workflow si están disponibles
            if (isset($wf['nodes']) && is_array($wf['nodes'])) {
                $tipos_nodos = array();
                $triggers = array();
                
                foreach ($wf['nodes'] as $node) {
                    $tipo = $node['type'] ?? 'unknown';
                    $tipos_nodos[$tipo] = ($tipos_nodos[$tipo] ?? 0) + 1;
                    
                    // Identificar triggers
                    if (strpos($tipo, 'trigger') !== false || strpos($tipo, 'Trigger') !== false || strpos($tipo, 'webhook') !== false) {
                        $triggers[] = $node['name'] ?? $tipo;
                    }
                }
                
                if (!empty($triggers)) {
                    $contexto .= "   Triggers: " . implode(', ', $triggers) . "\n";
                }
                $contexto .= "   Nodos: " . count($wf['nodes']) . " (" . $this->resumir_nodos($tipos_nodos) . ")\n";
            }
            
            // Tags si existen
            if (!empty($wf['tags'])) {
                $tags = array_map(function($t) { return $t['name'] ?? ''; }, $wf['tags']);
                $contexto .= "   Tags: " . implode(', ', array_filter($tags)) . "\n";
            }
            
            $contexto .= "\n";
        }
        
        $contexto .= "Resumen: {$activos} activos, {$inactivos} inactivos\n";
        
        $contexto .= "\nPuedes preguntarme sobre cualquier workflow y te explicaré qué hace, sus nodos y cómo funciona.\n";
        
        return $contexto;
    }
    
    /**
     * Resumir tipos de nodos
     */
    private function resumir_nodos($tipos) {
        $resumen = array();
        foreach ($tipos as $tipo => $count) {
            // Simplificar nombre del nodo
            $nombre = str_replace(array('n8n-nodes-base.', 'n8n-nodes-', '@'), '', $tipo);
            $resumen[] = "{$nombre}:{$count}";
        }
        return implode(', ', array_slice($resumen, 0, 5));
    }
    
    /**
     * Extraer texto de archivos
     */
    private function extraer_texto($filepath, $mime_type) {
        if ($mime_type === 'text/plain' || $mime_type === 'text/csv') {
            return file_get_contents($filepath);
        }
        
        if ($mime_type === 'application/pdf') {
            // Intentar extraer texto básico del PDF
            $content = file_get_contents($filepath);
            // Extracción simple (para PDFs básicos)
            preg_match_all('/stream\s*(.+?)\s*endstream/s', $content, $matches);
            $text = '';
            foreach ($matches[1] as $match) {
                $decoded = @gzuncompress($match);
                if ($decoded) {
                    $text .= preg_replace('/[^\x20-\x7E\s]/', '', $decoded);
                }
            }
            return substr($text, 0, 5000); // Limitar
        }
        
        return '';
    }
    
    /**
     * Calcular costo
     */
    private function calcular_costo($model, $input, $output) {
        $precios = array(
            'gpt-4o' => array('input' => 0.0025, 'output' => 0.01),
            'gpt-4o-mini' => array('input' => 0.00015, 'output' => 0.0006),
            'tts-1' => array('input' => 0.015, 'output' => 0)
        );
        $p = isset($precios[$model]) ? $precios[$model] : $precios['gpt-4o-mini'];
        return ($input / 1000 * $p['input']) + ($output / 1000 * $p['output']);
    }
    
    /**
     * Registrar consumo
     */
    private function registrar_consumo($tokens, $cost, $model = 'gpt-4o-mini') {
        global $wpdb;
        $user = wp_get_current_user();
        $wpdb->insert($wpdb->prefix . 'ai_usage_log', array(
            'user_id' => get_current_user_id(),
            'user_email' => $user->user_email,
            'client_identifier' => 'interno_aria',
            'model' => $model,
            'model_used' => $model,
            'endpoint' => 'aria_agente',
            'request_endpoint' => 'aria_agente',
            'tokens_total' => $tokens,
            'costo_usd' => $cost,
            'request_type' => 'chat'
        ));
    }
    
    /**
     * Buscar cliente por nombre, email o empresa
     */
    public function buscar_cliente() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        global $wpdb;
        $busqueda = sanitize_text_field($_POST['busqueda']);
        
        $leads_table = $wpdb->prefix . 'automatiza_leads';
        $propuestas_table = $wpdb->prefix . 'automatiza_propuestas';
        
        $resultados = array();
        
        // Buscar en propuestas
        $propuestas = $wpdb->get_results($wpdb->prepare("
            SELECT 'propuesta' as tipo, id, client_name as nombre, client_email as email, 
                   company_name as empresa, phone as telefono, unique_link_id, created_at
            FROM $propuestas_table 
            WHERE client_name LIKE %s 
               OR client_email LIKE %s 
               OR company_name LIKE %s
            ORDER BY created_at DESC
            LIMIT 10
        ", "%{$busqueda}%", "%{$busqueda}%", "%{$busqueda}%"), ARRAY_A);
        
        // Buscar en leads
        $leads = $wpdb->get_results($wpdb->prepare("
            SELECT 'lead' as tipo, id, name as nombre, email, 
                   company as empresa, phone as telefono, scheduled_date, scheduled_time, status
            FROM $leads_table 
            WHERE name LIKE %s 
               OR email LIKE %s 
               OR company LIKE %s
            ORDER BY created_at DESC
            LIMIT 10
        ", "%{$busqueda}%", "%{$busqueda}%", "%{$busqueda}%"), ARRAY_A);
        
        $resultados = array_merge($propuestas ?: array(), $leads ?: array());
        
        wp_send_json_success($resultados);
    }
    
    /**
     * Obtener disponibilidad para una fecha específica
     */
    public function obtener_disponibilidad() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        global $wpdb;
        $fecha = sanitize_text_field($_POST['fecha']);
        
        $leads_table = $wpdb->prefix . 'automatiza_leads';
        $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
        
        $horarios = array('09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00', '18:00');
        
        // Obtener ocupados
        $ocupados_leads = $wpdb->get_col($wpdb->prepare("
            SELECT LEFT(scheduled_time, 5) 
            FROM $leads_table 
            WHERE scheduled_date = %s 
            AND (status IS NULL OR status NOT IN ('cancelled', 'no_show', 'completed'))
        ", $fecha));
        
        $ocupados_followup = $wpdb->get_col($wpdb->prepare("
            SELECT LEFT(meeting_time, 5) 
            FROM $followup_table 
            WHERE meeting_date = %s 
            AND status NOT IN ('cancelled', 'completed')
        ", $fecha));
        
        $ocupados = array_merge($ocupados_leads ?: array(), $ocupados_followup ?: array());
        $libres = array_values(array_diff($horarios, $ocupados));
        
        wp_send_json_success(array(
            'fecha' => $fecha,
            'horarios_libres' => $libres,
            'horarios_ocupados' => array_values($ocupados)
        ));
    }
    
    /**
     * Agendar reunión de seguimiento
     */
    public function agendar_seguimiento() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        global $wpdb;
        $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
        
        // Datos de la reunión
        $cliente_nombre = sanitize_text_field($_POST['cliente_nombre']);
        $cliente_email = sanitize_email($_POST['cliente_email']);
        $empresa = sanitize_text_field($_POST['empresa'] ?? '');
        $telefono = sanitize_text_field($_POST['telefono'] ?? '');
        $fecha = sanitize_text_field($_POST['fecha']);
        $hora = sanitize_text_field($_POST['hora']);
        $asunto = sanitize_text_field($_POST['asunto'] ?? 'Reunión de Seguimiento');
        $notas = sanitize_textarea_field($_POST['notas'] ?? '');
        
        // Validar disponibilidad
        $ocupado = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $followup_table 
            WHERE meeting_date = %s AND LEFT(meeting_time, 5) = %s 
            AND status NOT IN ('cancelled', 'completed')
        ", $fecha, $hora));
        
        if ($ocupado) {
            wp_send_json_error('El horario ya está ocupado. Por favor selecciona otro.');
            return;
        }
        
        // Generar link de Google Meet (opcional - usando formato estándar)
        $meet_link = 'https://meet.google.com/new'; // Se puede mejorar con API de Calendar
        
        // Insertar reunión
        $result = $wpdb->insert($followup_table, array(
            'client_name' => $cliente_nombre,
            'client_email' => $cliente_email,
            'company_name' => $empresa,
            'phone' => $telefono,
            'meeting_date' => $fecha,
            'meeting_time' => $hora . ':00',
            'meet_link' => $meet_link,
            'meeting_subject' => $asunto,
            'notes' => $notas . "\n[Agendado por MAXTECH - " . date('d/m/Y H:i') . "]",
            'status' => 'scheduled',
            'email_sent' => 0,
            'whatsapp_sent' => 0
        ));
        
        if ($result) {
            $reunion_id = $wpdb->insert_id;
            
            // Aquí se podría disparar envío de email/WhatsApp
            // do_action('maxtech_reunion_agendada', $reunion_id);
            
            wp_send_json_success(array(
                'mensaje' => "✅ Reunión agendada exitosamente",
                'reunion_id' => $reunion_id,
                'detalles' => array(
                    'cliente' => $cliente_nombre,
                    'fecha' => date('d/m/Y', strtotime($fecha)),
                    'hora' => $hora,
                    'empresa' => $empresa
                )
            ));
        } else {
            wp_send_json_error('Error al agendar la reunión. Intenta nuevamente.');
        }
    }
    
    /**
     * Extraer contenido de un documento (PDF, imagen, texto)
     * 
     * @param string $url URL del documento
     * @param string $type Tipo de archivo (pdf, image/*, text/*)
     * @return string Contenido extraído o descripción
     */
    private function extraer_contenido_documento($url, $type, $name = '') {
        // Convertir URL a ruta local si es posible
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        
        // Normalizar tipo por extensión si es necesario
        $extension = strtolower(pathinfo($name ?: $url, PATHINFO_EXTENSION));
        
        // Si es imagen, usar Vision API
        if (strpos($type, 'image/') === 0 || in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
            return $this->analizar_imagen_con_vision($url, $name);
        }
        
        // Si es PDF, extraer texto
        if ($type === 'pdf' || strpos($type, 'application/pdf') !== false || $extension === 'pdf') {
            return $this->extraer_texto_pdf($local_path, $name);
        }
        
        // Si es Word (.docx)
        if ($extension === 'docx' || strpos($type, 'wordprocessingml') !== false) {
            return $this->extraer_texto_word($local_path, $name);
        }
        
        // Si es Excel (.xlsx)
        if ($extension === 'xlsx' || strpos($type, 'spreadsheetml') !== false) {
            return $this->extraer_texto_excel($local_path, $name);
        }
        
        // Si es PowerPoint (.pptx)
        if ($extension === 'pptx' || strpos($type, 'presentationml') !== false) {
            return $this->extraer_texto_powerpoint($local_path, $name);
        }
        
        // Si es Word antiguo (.doc) - solo extraer texto básico
        if ($extension === 'doc' || $type === 'application/msword') {
            return $this->extraer_texto_doc_antiguo($local_path, $name);
        }
        
        // Si es texto plano
        if (strpos($type, 'text/') === 0 || in_array($extension, array('txt', 'md', 'csv'))) {
            if (file_exists($local_path)) {
                $content = file_get_contents($local_path);
                return "[Contenido texto] " . substr($content, 0, 3000) . (strlen($content) > 3000 ? '...' : '');
            }
        }
        
        return "Documento: {$name} (tipo: {$type})";
    }
    
    /**
     * Extraer texto de archivo Word (.docx)
     * Los archivos .docx son ZIP con XML dentro
     */
    private function extraer_texto_word($docx_path, $name = '') {
        if (!file_exists($docx_path)) {
            return "Word: {$name} (archivo no encontrado)";
        }
        
        if (!class_exists('ZipArchive')) {
            return "Word: {$name} (ZipArchive no disponible)";
        }
        
        $zip = new ZipArchive();
        if ($zip->open($docx_path) !== true) {
            return "Word: {$name} (no se pudo abrir)";
        }
        
        // El contenido está en word/document.xml
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if (empty($content)) {
            return "Word: {$name} (sin contenido)";
        }
        
        // Extraer texto del XML
        $text = $this->extraer_texto_de_xml($content);
        
        if (!empty($text)) {
            return "[Contenido Word] " . substr($text, 0, 4000) . (strlen($text) > 4000 ? '...' : '');
        }
        
        return "Word: {$name} (contenido vacío)";
    }
    
    /**
     * Extraer texto de archivo Excel (.xlsx)
     */
    private function extraer_texto_excel($xlsx_path, $name = '') {
        if (!file_exists($xlsx_path)) {
            return "Excel: {$name} (archivo no encontrado)";
        }
        
        if (!class_exists('ZipArchive')) {
            return "Excel: {$name} (ZipArchive no disponible)";
        }
        
        $zip = new ZipArchive();
        if ($zip->open($xlsx_path) !== true) {
            return "Excel: {$name} (no se pudo abrir)";
        }
        
        // Obtener strings compartidos (texto de celdas)
        $shared_strings = array();
        $shared_content = $zip->getFromName('xl/sharedStrings.xml');
        if (!empty($shared_content)) {
            $xml = @simplexml_load_string($shared_content);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $shared_strings[] = (string) $si->t;
                }
            }
        }
        
        // Leer las hojas de cálculo
        $all_text = array();
        $sheet_index = 1;
        
        while (($sheet_content = $zip->getFromName("xl/worksheets/sheet{$sheet_index}.xml")) !== false) {
            $xml = @simplexml_load_string($sheet_content);
            if ($xml) {
                $sheet_text = array();
                foreach ($xml->sheetData->row as $row) {
                    $row_text = array();
                    foreach ($row->c as $cell) {
                        $value = '';
                        if (isset($cell->v)) {
                            $v = (string) $cell->v;
                            // Si es tipo 's', es referencia a shared strings
                            if (isset($cell['t']) && (string) $cell['t'] === 's') {
                                $value = isset($shared_strings[(int) $v]) ? $shared_strings[(int) $v] : '';
                            } else {
                                $value = $v;
                            }
                        }
                        if (!empty($value)) {
                            $row_text[] = $value;
                        }
                    }
                    if (!empty($row_text)) {
                        $sheet_text[] = implode(' | ', $row_text);
                    }
                }
                if (!empty($sheet_text)) {
                    $all_text[] = "--- Hoja {$sheet_index} ---\n" . implode("\n", array_slice($sheet_text, 0, 50));
                }
            }
            $sheet_index++;
            if ($sheet_index > 5) break; // Máximo 5 hojas
        }
        
        $zip->close();
        
        $text = implode("\n\n", $all_text);
        if (!empty($text)) {
            return "[Contenido Excel] " . substr($text, 0, 4000) . (strlen($text) > 4000 ? '...' : '');
        }
        
        return "Excel: {$name} (sin datos)";
    }
    
    /**
     * Extraer texto de PowerPoint (.pptx)
     */
    private function extraer_texto_powerpoint($pptx_path, $name = '') {
        if (!file_exists($pptx_path)) {
            return "PowerPoint: {$name} (archivo no encontrado)";
        }
        
        if (!class_exists('ZipArchive')) {
            return "PowerPoint: {$name} (ZipArchive no disponible)";
        }
        
        $zip = new ZipArchive();
        if ($zip->open($pptx_path) !== true) {
            return "PowerPoint: {$name} (no se pudo abrir)";
        }
        
        $all_text = array();
        $slide_index = 1;
        
        // Las diapositivas están en ppt/slides/slide1.xml, slide2.xml, etc.
        while (($slide_content = $zip->getFromName("ppt/slides/slide{$slide_index}.xml")) !== false) {
            $text = $this->extraer_texto_de_xml($slide_content);
            if (!empty($text)) {
                $all_text[] = "--- Diapositiva {$slide_index} ---\n{$text}";
            }
            $slide_index++;
            if ($slide_index > 30) break; // Máximo 30 diapositivas
        }
        
        $zip->close();
        
        $text = implode("\n\n", $all_text);
        if (!empty($text)) {
            return "[Contenido PowerPoint] " . substr($text, 0, 5000) . (strlen($text) > 5000 ? '...' : '');
        }
        
        return "PowerPoint: {$name} (sin contenido)";
    }
    
    /**
     * Extraer texto básico de Word antiguo (.doc)
     */
    private function extraer_texto_doc_antiguo($doc_path, $name = '') {
        if (!file_exists($doc_path)) {
            return "Word: {$name} (archivo no encontrado)";
        }
        
        $content = file_get_contents($doc_path);
        
        // Intentar extraer texto entre marcadores comunes
        $text = '';
        
        // Buscar texto ASCII visible
        preg_match_all('/[\x20-\x7E\xA0-\xFF]{10,}/', $content, $matches);
        if (!empty($matches[0])) {
            $text = implode(' ', $matches[0]);
            $text = preg_replace('/\s+/', ' ', $text);
        }
        
        if (!empty($text)) {
            return "[Contenido Word .doc] " . substr($text, 0, 3000) . (strlen($text) > 3000 ? '...' : '');
        }
        
        return "Word .doc: {$name} (formato antiguo, contenido no extraíble)";
    }
    
    /**
     * Extraer texto limpio de contenido XML (para docx, pptx)
     */
    private function extraer_texto_de_xml($xml_content) {
        // Remover namespaces para facilitar el parsing
        $xml_content = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $xml_content);
        $xml_content = preg_replace('/<[a-z0-9]+:/i', '<', $xml_content);
        $xml_content = preg_replace('/<\/[a-z0-9]+:/i', '</', $xml_content);
        
        // Extraer todo el texto entre tags <t>
        preg_match_all('/<t[^>]*>([^<]*)<\/t>/i', $xml_content, $matches);
        
        if (!empty($matches[1])) {
            $text = implode(' ', $matches[1]);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', trim($text));
            return $text;
        }
        
        // Alternativa: strip_tags
        $text = strip_tags($xml_content);
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        return $text;
    }
    
    /**
     * Analizar imagen usando GPT-4o Vision
     */
    private function analizar_imagen_con_vision($image_url, $context = '') {
        $data = array(
            'model' => 'gpt-4o-mini',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => "Analiza esta imagen de forma concisa. Contexto: {$context}. Extrae: 1) Descripción breve, 2) Texto visible, 3) Información relevante para un CRM. Máximo 300 palabras."
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array('url' => $image_url)
                        )
                    )
                )
            ),
            'max_tokens' => 500
        );
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key
            ),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return "[Análisis de imagen] " . $result['choices'][0]['message']['content'];
        }
        
        return "Imagen: {$context} (no se pudo analizar)";
    }
    
    /**
     * Extraer texto de un PDF
     */
    private function extraer_texto_pdf($pdf_path, $name = '') {
        if (!file_exists($pdf_path)) {
            return "PDF: {$name} (archivo no encontrado)";
        }
        
        // Método 1: Usar pdftotext si está disponible (Linux)
        if (function_exists('shell_exec')) {
            $output = shell_exec('pdftotext -layout -nopgbrk ' . escapeshellarg($pdf_path) . ' - 2>/dev/null');
            if (!empty($output)) {
                return "[Contenido PDF] " . substr(trim($output), 0, 3000) . (strlen($output) > 3000 ? '...' : '');
            }
        }
        
        // Método 2: Extraer texto básico del PDF (streams de texto)
        $content = file_get_contents($pdf_path);
        $text = $this->extraer_texto_pdf_basico($content);
        
        if (!empty($text)) {
            return "[Contenido PDF] " . substr($text, 0, 3000) . (strlen($text) > 3000 ? '...' : '');
        }
        
        return "PDF: {$name} (sin texto extraíble - puede ser imagen escaneada)";
    }
    
    /**
     * Extraer texto básico de contenido PDF (método simple sin librerías)
     */
    private function extraer_texto_pdf_basico($content) {
        // Buscar streams de texto en el PDF
        $text = '';
        
        // Patrón para encontrar texto entre BT y ET (Begin Text / End Text)
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                // Extraer strings entre paréntesis
                if (preg_match_all('/\((.*?)\)/s', $block, $strings)) {
                    $text .= implode(' ', $strings[1]) . ' ';
                }
                // Extraer strings hex
                if (preg_match_all('/<([0-9A-Fa-f]+)>/s', $block, $hex)) {
                    foreach ($hex[1] as $h) {
                        $text .= hex2bin($h) . ' ';
                    }
                }
            }
        }
        
        // Limpiar
        $text = preg_replace('/[^\x20-\x7E\xA0-\xFF\n]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * AJAX: Obtener contenido de documento específico para MAXTECH
     */
    public function ajax_obtener_documento_cliente() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        global $wpdb;
        $detail_id = intval($_POST['detail_id']);
        
        $doc = $wpdb->get_row($wpdb->prepare("
            SELECT attachment_url, attachment_name, attachment_type, description
            FROM {$wpdb->prefix}automatiza_clients_details
            WHERE id = %d
        ", $detail_id), ARRAY_A);
        
        if (!$doc || empty($doc['attachment_url'])) {
            wp_send_json_error('Documento no encontrado');
        }
        
        $contenido = $this->extraer_contenido_documento(
            $doc['attachment_url'], 
            $doc['attachment_type'], 
            $doc['attachment_name']
        );
        
        wp_send_json_success(array(
            'nombre' => $doc['attachment_name'],
            'tipo' => $doc['attachment_type'],
            'descripcion' => $doc['description'],
            'contenido_extraido' => $contenido
        ));
    }
    
    /**
     * AJAX: Leer archivo de Google Drive
     */
    public function ajax_leer_archivo_drive() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $file_id = sanitize_text_field($_POST['file_id'] ?? '');
        
        if (empty($file_id)) {
            wp_send_json_error('File ID requerido');
        }
        
        // Verificar que existe la función de Drive
        if (!function_exists('maxtech_read_drive_file')) {
            wp_send_json_error('Integración de Google Drive no disponible');
        }
        
        $resultado = maxtech_read_drive_file($file_id);
        
        if (is_wp_error($resultado)) {
            wp_send_json_error($resultado->get_error_message());
        }
        
        wp_send_json_success($resultado);
    }
    
    /**
     * AJAX: Listar carpeta de Google Drive
     */
    public function ajax_listar_carpeta_drive() {
        check_ajax_referer('aria_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $folder_id = sanitize_text_field($_POST['folder_id'] ?? 'root');
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        if (!function_exists('maxtech_list_drive_folder')) {
            wp_send_json_error('Integración de Google Drive no disponible');
        }
        
        if (!empty($search)) {
            $resultado = maxtech_search_drive($search, $folder_id !== 'root' ? $folder_id : null);
        } else {
            $resultado = maxtech_list_drive_folder($folder_id);
        }
        
        if (is_wp_error($resultado)) {
            wp_send_json_error($resultado->get_error_message());
        }
        
        wp_send_json_success($resultado);
    }
    
    /**
     * Obtener contenido de carpeta de Drive de un cliente
     * Para usar en el contexto de MAXTECH
     */
    public function obtener_archivos_drive_cliente($drive_folder_id) {
        if (empty($drive_folder_id) || !function_exists('maxtech_list_drive_folder')) {
            return null;
        }
        
        $archivos = maxtech_list_drive_folder($drive_folder_id);
        
        if (is_wp_error($archivos) || empty($archivos['files'])) {
            return null;
        }
        
        $lista = array();
        foreach ($archivos['files'] as $file) {
            $lista[] = array(
                'id' => $file['id'],
                'nombre' => $file['name'],
                'tipo' => $file['mimeType'],
                'modificado' => $file['modifiedTime'] ?? ''
            );
        }
        
        return $lista;
    }
}

new ARIA_Agente();
