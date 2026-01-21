<?php
/**
 * API REST para configuración de agendamiento
 * Endpoint para que N8N obtenga horarios y fechas bloqueadas
 */

if (!defined('ABSPATH')) {
    exit;
}

// Registrar endpoint REST API
add_action('rest_api_init', function () {
    register_rest_route('automatiza-tech/v1', '/appointments-config', array(
        'methods' => 'GET',
        'callback' => 'automatiza_get_appointments_config',
        'permission_callback' => '__return_true', // Público, sin autenticación
    ));
});

/**
 * Obtener configuración de horarios y fechas bloqueadas
 */
function automatiza_get_appointments_config() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_leads';
    
    $schedule_settings = get_option('automatiza_chat_schedule', array());
    
    // Extraer fechas bloqueadas
    $holidays_raw = isset($schedule_settings['holidays']) ? $schedule_settings['holidays'] : '';
    $holidays = array();
    
    if (!empty($holidays_raw)) {
        $lines = preg_split('/\r\n|\r|\n/', $holidays_raw);
        foreach ($lines as $line) {
            $fecha = trim($line);
            // Validar formato YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $holidays[] = $fecha;
            }
        }
    }
    
    // Extraer horarios por día
    $days_names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $week_schedule = array();
    
    foreach ($days_names as $day) {
        if (isset($schedule_settings[$day])) {
            $week_schedule[$day] = array(
                'enabled' => !empty($schedule_settings[$day]['enabled']),
                'start' => isset($schedule_settings[$day]['start']) ? $schedule_settings[$day]['start'] : '09:00',
                'end' => isset($schedule_settings[$day]['end']) ? $schedule_settings[$day]['end'] : '18:00'
            );
        } else {
            // Valores por defecto - Domingo deshabilitado, Sábado opcional
            $week_schedule[$day] = array(
                'enabled' => ($day != 'sunday'), // Domingo siempre deshabilitado por defecto
                'start' => ($day == 'saturday') ? '10:00' : '09:00',
                'end' => ($day == 'saturday') ? '14:00' : '18:00'
            );
        }
    }
    
    // Obtener información de días ocupados para los próximos 30 días
    $busy_dates = array();
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+30 days'));
    
    // Obtener todas las citas de DEMO de los próximos 30 días (excluyendo canceladas)
    $booked_slots = $wpdb->get_results($wpdb->prepare(
        "SELECT scheduled_date, scheduled_time FROM $table_name 
         WHERE scheduled_date >= %s AND scheduled_date <= %s 
         AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
        $today,
        $end_date
    ));
    
    // Obtener citas de SEGUIMIENTO de los próximos 30 días (validación cruzada)
    $followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
    $followup_slots = $wpdb->get_results($wpdb->prepare(
        "SELECT meeting_date, meeting_time FROM $followup_table 
         WHERE meeting_date >= %s AND meeting_date <= %s 
         AND status NOT IN ('cancelled', 'completed')",
        $today,
        $end_date
    ));
    
    // Agrupar slots ocupados por fecha (DEMOs)
    $slots_by_date = array();
    foreach ($booked_slots as $slot) {
        $date = $slot->scheduled_date;
        if (!isset($slots_by_date[$date])) {
            $slots_by_date[$date] = array();
        }
        // Formato HH:mm
        $slots_by_date[$date][] = substr($slot->scheduled_time, 0, 5);
    }
    
    // Agregar slots de SEGUIMIENTO (validación cruzada)
    foreach ($followup_slots as $slot) {
        $date = $slot->meeting_date;
        if (!isset($slots_by_date[$date])) {
            $slots_by_date[$date] = array();
        }
        $time = substr($slot->meeting_time, 0, 5);
        // Evitar duplicados
        if (!in_array($time, $slots_by_date[$date])) {
            $slots_by_date[$date][] = $time;
        }
    }
    
    // Para cada fecha con citas, verificar si está llena
    foreach ($slots_by_date as $date => $busy_slots) {
        $timestamp = strtotime($date);
        $day_index = (int)date('w', $timestamp); // 0=domingo, 1=lunes...
        $day_name = $days_names[$day_index];
        
        $day_config = $week_schedule[$day_name];
        
        if (!$day_config['enabled']) {
            continue; // Día no habilitado, no incluir
        }
        
        // Calcular total de slots del día
        $start_hour = (int)explode(':', $day_config['start'])[0];
        $end_hour = (int)explode(':', $day_config['end'])[0];
        $total_slots = $end_hour - $start_hour;
        
        // Calcular slots disponibles
        $available_count = $total_slots - count($busy_slots);
        
        $busy_dates[$date] = array(
            'busySlots' => $busy_slots,
            'totalSlots' => $total_slots,
            'availableSlots' => $available_count,
            'isFull' => ($available_count <= 0)
        );
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'holidays' => $holidays,
            'weekSchedule' => $week_schedule,
            'busyDates' => $busy_dates,
            'timezone' => 'America/Santiago'
        )
    ), 200);
}
