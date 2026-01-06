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
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $week_schedule = array();
    
    foreach ($days as $day) {
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
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => array(
            'holidays' => $holidays,
            'weekSchedule' => $week_schedule,
            'timezone' => 'America/Santiago'
        )
    ), 200);
}
