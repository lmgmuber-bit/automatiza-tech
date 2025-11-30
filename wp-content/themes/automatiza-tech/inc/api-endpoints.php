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
});

/**
 * Crear tabla de leads al activar el tema (o verificar existencia)
 */
function automatiza_tech_create_leads_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
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
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
// Ejecutar creación de tabla al cambiar al tema
add_action('after_switch_theme', 'automatiza_tech_create_leads_table');

// También intentamos crearla si no existe al iniciar (para desarrollo)
add_action('init', function() {
    // Forzamos actualización de tabla v2
    if (!get_option('automatiza_leads_table_created_v2')) {
        automatiza_tech_create_leads_table();
        update_option('automatiza_leads_table_created_v2', true);
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

    // Insertar en base de datos
    $data = array(
        'created_at' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'session_id' => $session_id
    );

    if ($scheduled_date) $data['scheduled_date'] = $scheduled_date;
    if ($scheduled_time) $data['scheduled_time'] = $scheduled_time;

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
        'availableSlotsCount' => $available_slots
    );
}
