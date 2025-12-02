<?php
/**
 * Custom REST API Endpoints
 * 
 * @package AutomatizaTech
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register API routes
 */
add_action('rest_api_init', function () {
    // Endpoint para obtener el tipo de cambio actual
    register_rest_route('automatiza-tech/v1', '/exchange-rate', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_exchange_rate',
        'permission_callback' => '__return_true' // Endpoint público
    ));

    // Endpoint para guardar leads desde el Chat
    register_rest_route('automatiza-tech/v1', '/leads', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_save_lead',
        'permission_callback' => '__return_true' // Endpoint público (validar origen si es necesario)
    ));

    // Endpoint para verificar disponibilidad (Check Availability)
    register_rest_route('automatiza-tech/v1', '/check-availability', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_check_availability',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para verificar límite de agendamientos
    register_rest_route('automatiza-tech/v1', '/check-limit', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_check_booking_limit',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para obtener leads para recordatorios
    // Modificado para aceptar parámetro en la URL (ej: /leads/reminders/72h) para evitar problemas de query params
    register_rest_route('automatiza-tech/v1', '/leads/reminders(?:/(?P<type>[a-zA-Z0-9]+))?', array(
        'methods' => 'GET',
        'callback' => 'automatiza_tech_get_leads_for_reminders',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para actualizar estado de recordatorio (Ruta con parámetros obligatorios en URL)
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder/(?P<lead_id>\d+)/(?P<type>[a-zA-Z0-9]+)', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_sent',
        'permission_callback' => '__return_true'
    ));

    // Endpoint FALLBACK para actualizar estado (para compatibilidad con versiones anteriores de n8n)
    register_rest_route('automatiza-tech/v1', '/leads/update-reminder', array(
        'methods' => array('POST', 'GET'),
        'callback' => 'automatiza_tech_mark_reminder_sent',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para acciones de usuario (Confirmar/Rechazar/Eliminar)
    register_rest_route('automatiza-tech/v1', '/leads/action', array(
        'methods' => 'GET', // GET para que funcione desde enlaces de correo
        'callback' => 'automatiza_tech_handle_lead_action',
        'permission_callback' => '__return_true'
    ));

    // Endpoint para reagendar cita
    register_rest_route('automatiza-tech/v1', '/leads/reschedule', array(
        'methods' => 'POST',
        'callback' => 'automatiza_tech_reschedule_lead',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Crear tabla de leads al activar el tema (o verificar existencia)
 */
function automatiza_tech_create_leads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $logs_table_name = $wpdb->prefix . 'automatiza_leads_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        session_id varchar(100) DEFAULT '' NOT NULL,
        scheduled_date date DEFAULT NULL,
        scheduled_time time DEFAULT NULL,
        confirmed_attendance tinyint(1) DEFAULT NULL,
        recordatorio72h tinyint(1) DEFAULT 0,
        recordatorio24h tinyint(1) DEFAULT 0,
        recordatorio1h tinyint(1) DEFAULT 0,
        token varchar(64) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $sql_logs = "CREATE TABLE $logs_table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        original_lead_id mediumint(9) NOT NULL,
        deleted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        reason tinytext NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_logs);
}
// Ejecutar creación de tabla al cambiar al tema
add_action('after_switch_theme', 'automatiza_tech_create_leads_table');

// También intentamos crearla si no existe al iniciar (para desarrollo)
add_action('init', function() {
    // Forzamos actualización de tabla v5
    if (!get_option('automatiza_leads_table_created_v5')) {
        automatiza_tech_create_leads_table();
        
        // Populate missing tokens for existing leads
        global $wpdb;
        $table_name = $wpdb->prefix . 'automatiza_leads';
        $leads_without_token = $wpdb->get_results("SELECT id FROM $table_name WHERE token = ''");
        
        if ($leads_without_token) {
            foreach ($leads_without_token as $lead) {
                $wpdb->update(
                    $table_name,
                    array('token' => bin2hex(random_bytes(16))),
                    array('id' => $lead->id)
                );
            }
        }
        
        update_option('automatiza_leads_table_created_v5', true);
    }
});

/**
 * Callback para guardar lead
 */
function automatiza_tech_save_lead($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';

    // Obtener parámetros JSON
    $params = $request->get_json_params();

    // Validar datos básicos
    if (empty($params['name']) || empty($params['email'])) {
        return new WP_Error('missing_params', 'Faltan datos obligatorios (nombre, email)', array('status' => 400));
    }

    $name = sanitize_text_field($params['name']);
    $email = sanitize_email($params['email']);
    $phone = isset($params['phone']) ? sanitize_text_field($params['phone']) : '';
    $session_id = isset($params['session_id']) ? sanitize_text_field($params['session_id']) : '';
    $scheduled_date = isset($params['scheduled_date']) ? sanitize_text_field($params['scheduled_date']) : null;
    $scheduled_time = isset($params['scheduled_time']) ? sanitize_text_field($params['scheduled_time']) : null;
    $confirmed_attendance = isset($params['confirmed_attendance']) ? (int)$params['confirmed_attendance'] : null;
    
    // Validación: Verificar si el email ya tiene 2 o más agendamientos ACTIVOS (futuros)
    $test_email = 'lmgm.uber@gmail.com';
    if (strtolower($email) !== strtolower($test_email)) {
        $current_datetime = current_time('mysql');
        
        // Contamos solo los agendamientos cuya fecha y hora sean mayores o iguales al momento actual
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE email = %s 
             AND CONCAT(scheduled_date, ' ', scheduled_time) >= %s",
            $email,
            $current_datetime
        ));
        
        if ($count >= 2) {
            return new WP_Error('email_limit_reached', 'Este correo ya tiene 2 agendamientos activos. Solo se permiten 2 reuniones pendientes simultáneas.', array('status' => 400));
        }
    }
    
    // Generar token de seguridad
    $token = bin2hex(random_bytes(16));

    // Insertar en base de datos
    $data = array(
        'created_at' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'session_id' => $session_id,
        'token' => $token
    );

    if ($scheduled_date) $data['scheduled_date'] = $scheduled_date;
    if ($scheduled_time) $data['scheduled_time'] = $scheduled_time;
    if ($confirmed_attendance !== null) $data['confirmed_attendance'] = $confirmed_attendance;

    $result = $wpdb->insert($table_name, $data);

    if ($result === false) {
        return new WP_Error('db_error', 'Error al guardar en base de datos', array('status' => 500));
    }

    return array(
        'success' => true,
        'message' => 'Lead guardado correctamente',
        'lead_id' => $wpdb->insert_id
    );
}

/**
 * Callback para obtener el tipo de cambio
 */
function automatiza_tech_get_exchange_rate() {
    if (!function_exists('automatiza_tech_init_currency_updater')) {
        return new WP_Error('dependency_missing', 'Currency Updater function not found', array('status' => 500));
    }

    $updater = automatiza_tech_init_currency_updater();
    $rate = $updater->get_current_exchange_rate();

    if (!$rate) {
        // Intentar obtener el último guardado si falla la API en tiempo real
        $rate = get_option('automatiza_tech_last_exchange_rate', 0);
    }

    if (!$rate) {
        return new WP_Error('no_rate', 'Could not retrieve exchange rate', array('status' => 500));
    }

    return array(
        'currency_from' => 'USD',
        'currency_to' => 'CLP',
        'rate' => (float) $rate,
        'formatted_rate' => '$' . number_format($rate, 2, ',', '.'),
        'timestamp' => current_time('mysql'),
        'source' => 'Banco Central de Chile / Mindicador.cl'
    );
}

/**
 * Callback para verificar disponibilidad
 */
function automatiza_tech_check_availability($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $params = $request->get_json_params();
    $date = isset($params['date']) ? sanitize_text_field($params['date']) : null;

    if (!$date) {
        return new WP_Error('missing_date', 'Fecha requerida', array('status' => 400));
    }

    // 1. Check Admin Settings (Holidays & Schedule)
    $settings = get_option('automatiza_chat_schedule', array());

    // Apply defaults if settings are empty (same as in chat-widget.php)
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (!isset($settings[$day])) {
            $settings[$day] = array(
                'enabled' => true,
                'start' => ($day == 'saturday' || $day == 'sunday') ? '15:00' : '09:00',
                'end' => ($day == 'saturday' || $day == 'sunday') ? '17:00' : '21:00'
            );
        }
    }
    
    // Check Holidays
    $holidays = isset($settings['holidays']) ? explode("\n", $settings['holidays']) : [];
    $holidays = array_map('trim', $holidays);
    if (in_array($date, $holidays)) {
        return array('isFullDay' => true, 'reason' => 'Holiday');
    }

    // Check Day Schedule
    $timestamp = strtotime($date);
    $day_name = strtolower(date('l', $timestamp)); // monday, tuesday...
    
    if (!isset($settings[$day_name]) || empty($settings[$day_name]['enabled'])) {
         return array('isFullDay' => true, 'reason' => 'Day disabled');
    }

    $start_time = $settings[$day_name]['start'];
    $end_time = $settings[$day_name]['end'];

    // 2. Get Booked Slots from DB
    $booked_results = $wpdb->get_results($wpdb->prepare(
        "SELECT scheduled_time FROM $table_name WHERE scheduled_date = %s",
        $date
    ));

    $busy_slots = array();
    foreach ($booked_results as $row) {
        // Format to HH:mm
        $busy_slots[] = substr($row->scheduled_time, 0, 5);
    }

    // 3. Calculate if Full Day
    // Generate all theoretical slots
    $start_hour = (int)explode(':', $start_time)[0];
    $end_hour = (int)explode(':', $end_time)[0];
    $total_slots = 0;
    $available_slots = 0;

    for ($h = $start_hour; $h < $end_hour; $h++) {
        $slot = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
        $total_slots++;
        if (!in_array($slot, $busy_slots)) {
            $available_slots++;
        }
    }

    return array(
        'isFullDay' => ($available_slots === 0),
        'busySlots' => $busy_slots,
        'availableSlotsCount' => $available_slots,
        'workingHours' => array('start' => $start_time, 'end' => $end_time)
    );
}

/**
 * Callback para verificar límite de agendamientos
 */
function automatiza_tech_check_booking_limit($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $params = $request->get_json_params();
    $email = isset($params['email']) ? sanitize_email($params['email']) : '';
    
    if (empty($email)) {
        return new WP_Error('missing_email', 'Email requerido', array('status' => 400));
    }

    $test_email = 'lmgm.uber@gmail.com';
    
    // Si es el email de prueba, siempre permitir
    if (strtolower($email) === strtolower($test_email)) {
        return array(
            'allowed' => true,
            'message' => 'Email de prueba permitido'
        );
    }

    $current_datetime = current_time('mysql');
    
    // Contamos solo los agendamientos cuya fecha y hora sean mayores o iguales al momento actual
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE email = %s 
         AND CONCAT(scheduled_date, ' ', scheduled_time) >= %s",
        $email,
        $current_datetime
    ));
    
    if ($count >= 2) {
        return array(
            'allowed' => false,
            'message' => 'Este correo ya tiene 2 agendamientos activos. Solo se permiten 2 reuniones pendientes simultáneas.'
        );
    }

    return array(
        'allowed' => true,
        'message' => 'Agendamiento permitido'
    );
}

/**
 * Callback para obtener leads para recordatorios
 */
function automatiza_tech_get_leads_for_reminders($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $type = $request['type']; // Prioridad: Parámetro de ruta
    
    if (empty($type)) {
        $type = $request->get_param('type'); // Fallback: Query param
    }
    
    // Fallback extremo: $_GET/$_REQUEST
    if (empty($type) && isset($_GET['type'])) $type = $_GET['type'];
    if (empty($type) && isset($_REQUEST['type'])) $type = $_REQUEST['type'];

    // Debug logging
    error_log("Reminder API called. Type resolved: " . print_r($type, true));
    
    if (!in_array($type, ['72h', '24h', '1h'])) {
        $debug_info = array(
            'received_params' => $request->get_params(),
            'GET_params' => $_GET,
            'REQUEST_params' => $_REQUEST
        );
        error_log("Invalid type. Debug info: " . print_r($debug_info, true));
        return new WP_Error('invalid_type', 'Tipo de recordatorio inválido. Debug: ' . json_encode($debug_info), array('status' => 400));
    }

    $now = current_time('mysql');
    $leads = [];

    if ($type === '72h') {
        // Entre 49 y 72 horas antes
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 49 hours'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 72 hours'));
        
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND recordatorio72h = 0",
            $start_range, $end_range
        ));
    } elseif ($type === '24h') {
        // Entre 2 y 24 horas antes
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 2 hours'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 24 hours'));

        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND recordatorio24h = 0",
            $start_range, $end_range
        ));
    } elseif ($type === '1h') {
        // Entre 1 hora y 1 hora 59 minutos antes
        $start_range = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour'));
        $end_range = date('Y-m-d H:i:s', strtotime($now . ' + 1 hour 59 minutes'));

        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE CONCAT(scheduled_date, ' ', scheduled_time) BETWEEN %s AND %s 
             AND recordatorio1h = 0",
            $start_range, $end_range
        ));
    }

    // Formatear fechas para visualización (DD-MM-YYYY)
    if (!empty($leads)) {
        foreach ($leads as $lead) {
            $lead->scheduled_date = date('d-m-Y', strtotime($lead->scheduled_date));
            $lead->scheduled_time = substr($lead->scheduled_time, 0, 5);
        }
    }

    return $leads;
}

/**
 * Callback para marcar recordatorio como enviado
 */
function automatiza_tech_mark_reminder_sent($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    // 1. Intentar obtener de la ruta (URL Path)
    $lead_id = isset($request['lead_id']) ? $request['lead_id'] : null;
    $type = isset($request['type']) ? $request['type'] : null;

    // 2. Si no están en la ruta, buscar en Body/Query (Fallback)
    if (empty($lead_id)) {
        $lead_id = $request->get_param('lead_id');
    }
    if (empty($type)) {
        $type = $request->get_param('type');
    }
    
    // 3. Fallback final para JSON Body crudo (si n8n envía JSON pero WP no lo parsea)
    if (empty($lead_id) || empty($type)) {
        $json_params = $request->get_json_params();
        if (!empty($json_params)) {
            if (empty($lead_id) && isset($json_params['lead_id'])) $lead_id = $json_params['lead_id'];
            if (empty($type) && isset($json_params['type'])) $type = $json_params['type'];
        }
    }
    
    $lead_id = (int)$lead_id;

    if (!$lead_id || !in_array($type, ['72h', '24h', '1h'])) {
        // Log para depuración
        error_log("Update Reminder Failed. ID: $lead_id, Type: $type. Request Params: " . print_r($request->get_params(), true));
        return new WP_Error('invalid_params', 'Parámetros inválidos. Recibido ID: ' . $lead_id . ' Type: ' . $type, array('status' => 400));
    }

    $column = 'recordatorio' . $type;
    
    $result = $wpdb->update(
        $table_name,
        array($column => 1),
        array('id' => $lead_id),
        array('%d'),
        array('%d')
    );

    return array('success' => true, 'updated' => $result);
}

/**
 * Callback para manejar acciones de usuario (Confirmar/Rechazar/Eliminar)
 */
function automatiza_tech_handle_lead_action($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    $logs_table_name = $wpdb->prefix . 'automatiza_leads_logs';
    
    $lead_id = $request->get_param('id');
    $token = $request->get_param('token');
    $action = $request->get_param('action'); // confirm, reject, delete

    if (!$lead_id || !in_array($action, ['confirm', 'reject', 'delete'])) {
        wp_die('Enlace inválido o expirado.', 'Error', array('response' => 400));
    }

    // Verificar Token de Seguridad
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    
    if (!$lead || !$token || !hash_equals($lead->token, $token)) {
         wp_die('Enlace no autorizado o token inválido.', 'Acceso Denegado', array('response' => 403));
    }

    // Configuración visual común
    $site_title = get_bloginfo('name');
    $home_url = home_url();
    $logo_src = 'https://automatizatech.shop/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
    
    // Forzar cabecera HTML
    header('Content-Type: text/html; charset=UTF-8');

    // --- LÓGICA PARA REAGENDAR (REJECT) ---
    if ($action === 'reject') {
        // No actualizamos nada aún, mostramos formulario de reagendamiento
        ?>
        <!DOCTYPE html>
        <html lang="<?php echo get_bloginfo('language'); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reagendar Cita - <?php echo esc_html($site_title); ?></title>
            <style>
                body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
                .card { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; overflow: hidden; }
                .header { background-color: #1e40af; padding: 30px 20px; }
                .logo { max-height: 60px; width: auto; display: block; margin: 0 auto; }
                .content { padding: 40px 30px; }
                h2 { color: #1e40af; margin-top: 0; }
                p { color: #555; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; }
                
                .form-group { margin-bottom: 15px; text-align: left; }
                label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #666; }
                input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
                
                .btn { display: block; width: 100%; padding: 12px 0; margin-top: 10px; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; transition: all 0.3s; font-size: 14px; border: none; cursor: pointer; }
                .btn-primary { background-color: #1e40af; }
                .btn-primary:hover { background-color: #15308a; }
                .btn-danger { background-color: white; color: #dc3545; border: 1px solid #dc3545; margin-top: 15px; }
                .btn-danger:hover { background-color: #fff5f5; }
                
                .loading { opacity: 0.6; pointer-events: none; }
                #message-box { margin-top: 15px; font-size: 13px; display: none; }
                .success { color: #06d6a0; }
                .error { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="header">
                    <img src="<?php echo esc_url($logo_src); ?>" alt="<?php echo esc_attr($site_title); ?>" class="logo">
                </div>
                <div class="content" id="reschedule-container">
                    <h2>Reagendar Cita</h2>
                    <p>Lamentamos que no puedas asistir. Por favor selecciona una nueva fecha y hora para tu reunión.</p>
                    
                    <form id="reschedule-form">
                        <input type="hidden" id="lead_id" value="<?php echo esc_attr($lead_id); ?>">
                        <input type="hidden" id="token" value="<?php echo esc_attr($token); ?>">
                        
                        <div class="form-group">
                            <label>Nueva Fecha:</label>
                            <input type="date" id="date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Nuevo Horario:</label>
                            <select id="time" required disabled>
                                <option value="">Selecciona una fecha primero</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" id="submit-btn">Confirmar Nuevo Horario</button>
                    </form>

                    <div id="message-box"></div>

                    <a href="<?php echo esc_url(home_url('/wp-json/automatiza-tech/v1/leads/action?id=' . $lead_id . '&token=' . $token . '&action=delete')); ?>" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas cancelar definitivamente la cita?');">Cancelar Cita Definitivamente</a>
                </div>
            </div>

            <script>
                const dateInput = document.getElementById('date');
                const timeSelect = document.getElementById('time');
                const form = document.getElementById('reschedule-form');
                const submitBtn = document.getElementById('submit-btn');
                const msgBox = document.getElementById('message-box');
                const leadId = document.getElementById('lead_id').value;
                const token = document.getElementById('token').value;

                dateInput.addEventListener('change', function() {
                    const dateVal = this.value;
                    if (!dateVal) return;

                    timeSelect.innerHTML = '<option>Cargando horarios...</option>';
                    timeSelect.disabled = true;

                    fetch('/wp-json/automatiza-tech/v1/check-availability', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ date: dateVal })
                    })
                    .then(response => response.json())
                    .then(data => {
                        timeSelect.innerHTML = '';
                        if (data.isFullDay) {
                            timeSelect.innerHTML = '<option value="">Día completo</option>';
                        } else {
                            // Usar horarios devueltos por el backend (respetando configuración del panel)
                            const startHour = parseInt(data.workingHours.start.split(':')[0]); 
                            const endHour = parseInt(data.workingHours.end.split(':')[0]);
                            let hasSlots = false;
                            
                            timeSelect.innerHTML = '<option value="">Selecciona una hora</option>';

                            for (let h = startHour; h < endHour; h++) {
                                const timeStr = h.toString().padStart(2, '0') + ':00';
                                if (!data.busySlots.includes(timeStr)) {
                                    const option = document.createElement('option');
                                    option.value = timeStr;
                                    option.textContent = timeStr;
                                    timeSelect.appendChild(option);
                                    hasSlots = true;
                                }
                            }
                            
                            if (!hasSlots) {
                                timeSelect.innerHTML = '<option value="">Sin horarios disponibles</option>';
                                timeSelect.disabled = true;
                            } else {
                                timeSelect.disabled = false;
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        timeSelect.innerHTML = '<option>Error al cargar</option>';
                    });
                });

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const date = dateInput.value;
                    const time = timeSelect.value;

                    if (!date || !time) return;

                    submitBtn.textContent = 'Procesando...';
                    submitBtn.classList.add('loading');

                    fetch('/wp-json/automatiza-tech/v1/leads/reschedule', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ lead_id: leadId, token: token, date: date, time: time })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Formatear fecha para mostrar
                            const [year, month, day] = date.split('-');
                            const formattedDate = `${day}-${month}-${year}`;

                            document.getElementById('reschedule-container').innerHTML = `
                                <h2>¡Cita Reagendada!</h2>
                                <p>Tu nueva cita ha sido confirmada para el <strong>${formattedDate}</strong> a las <strong>${time}</strong>.</p>
                                <a href="<?php echo esc_url($home_url); ?>" class="btn btn-primary">Volver al Inicio</a>
                            `;
                        } else {
                            throw new Error(data.message || 'Error desconocido');
                        }
                    })
                    .catch(err => {
                        msgBox.style.display = 'block';
                        msgBox.className = 'error';
                        msgBox.textContent = err.message;
                        submitBtn.textContent = 'Confirmar Nuevo Horario';
                        submitBtn.classList.remove('loading');
                    });
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    // --- LÓGICA PARA CONFIRMAR O ELIMINAR ---
    if ($action === 'confirm') {
        $wpdb->update($table_name, array('confirmed_attendance' => 1), array('id' => $lead_id));
        $message = '¡Gracias! Tu asistencia ha sido confirmada.';
    } elseif ($action === 'delete') {
        // Obtener datos antes de borrar
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
        
        if ($lead) {
            // Mover a logs
            $wpdb->insert($logs_table_name, array(
                'original_lead_id' => $lead->id,
                'deleted_at' => current_time('mysql'),
                'name' => $lead->name,
                'email' => $lead->email,
                'reason' => 'Usuario eliminó agendamiento desde correo'
            ));
            
            // Borrar de leads
            $wpdb->delete($table_name, array('id' => $lead_id));
            $message = 'Lamentamos que no puedas asistir, pero entendemos que surgen imprevistos.<br><br>Te invitamos a seguir visitando nuestro sitio web y, cuando estés listo, volver a coordinar una llamada para descubrir cómo nuestras automatizaciones pueden potenciar tu negocio.<br><br>¡Esperamos verte pronto!';
        } else {
            $message = 'El agendamiento ya no existe.';
        }
    }

    echo '<!DOCTYPE html>
    <html lang="' . get_bloginfo('language') . '">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . esc_html($site_title) . ' - Respuesta</title>
        <style>
            body { font-family: "Poppins", Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .card { background: white; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; overflow: hidden; }
            .header { background-color: #1e40af; padding: 30px 20px; }
            .logo { max-height: 60px; width: auto; display: block; margin: 0 auto; }
            .content { padding: 40px 30px; }
            p { color: #555; font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem; }
            .btn { display: inline-block; padding: 12px 30px; background-color: #1e40af; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; transition: background 0.3s; font-size: 14px; }
            .btn:hover { background-color: #15308a; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="header">
                <img src="' . esc_url($logo_src) . '" alt="' . esc_attr($site_title) . '" class="logo">
            </div>
            <div class="content">
                <p>' . $message . '</p>
                <a href="' . esc_url($home_url) . '" class="btn">Volver al Inicio</a>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

/**
 * Callback para reagendar cita
 */
function automatiza_tech_reschedule_lead($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $params = $request->get_json_params();
    $lead_id = isset($params['lead_id']) ? (int)$params['lead_id'] : 0;
    $token = isset($params['token']) ? sanitize_text_field($params['token']) : '';
    $new_date = isset($params['date']) ? sanitize_text_field($params['date']) : '';
    $new_time = isset($params['time']) ? sanitize_text_field($params['time']) : '';

    if (!$lead_id || !$new_date || !$new_time) {
        return new WP_Error('missing_params', 'Faltan datos para reagendar', array('status' => 400));
    }

    // Verificar Token
    $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
    if (!$lead || !hash_equals($lead->token, $token)) {
        return new WP_Error('invalid_token', 'Token inválido', array('status' => 403));
    }

    // Verificar disponibilidad nuevamente (doble check)
    $availability_req = new WP_REST_Request('POST', '/automatiza-tech/v1/check-availability');
    $availability_req->set_body_params(array('date' => $new_date));
    $availability = automatiza_tech_check_availability($availability_req);

    if (is_wp_error($availability) || (isset($availability['isFullDay']) && $availability['isFullDay'])) {
         return new WP_Error('unavailable', 'El día seleccionado ya no está disponible', array('status' => 400));
    }
    
    if (in_array(substr($new_time, 0, 5), $availability['busySlots'])) {
        return new WP_Error('unavailable_slot', 'El horario seleccionado ya no está disponible', array('status' => 400));
    }

    // Actualizar cita
    $result = $wpdb->update(
        $table_name,
        array(
            'scheduled_date' => $new_date,
            'scheduled_time' => $new_time,
            'confirmed_attendance' => 1, // Se asume confirmado al reagendar
            'recordatorio72h' => 0, // Resetear recordatorios
            'recordatorio24h' => 0,
            'recordatorio1h' => 0
        ),
        array('id' => $lead_id),
        array('%s', '%s', '%d', '%d', '%d', '%d'),
        array('%d')
    );

    if ($result === false) {
        return new WP_Error('db_error', 'Error al actualizar la cita', array('status' => 500));
    }

    return array(
        'success' => true, 
        'message' => 'Cita reagendada correctamente'
    );
}
