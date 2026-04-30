<?php
/**
 * Debug: Verificar disponibilidad para seguimientos
 */
require_once(__DIR__ . '/wp-load.php');

global $wpdb;
$date = isset($_GET['date']) ? $_GET['date'] : '2026-01-16';

echo "<h2>Debug Check-Availability para: $date</h2>";

// 1. Check Admin Settings
$settings = get_option('automatiza_chat_schedule', array());
echo "<h3>1. Configuración del Calendario:</h3>";
echo "<pre>" . print_r($settings, true) . "</pre>";

// Apply defaults if settings are empty
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
echo "<h3>2. Feriados:</h3>";
echo "<pre>" . print_r($holidays, true) . "</pre>";
echo "<p>¿Es feriado? " . (in_array($date, $holidays) ? 'SÍ' : 'NO') . "</p>";

// Check Day Schedule
$timestamp = strtotime($date);
$day_name = strtolower(date('l', $timestamp));
echo "<h3>3. Día de la semana: $day_name</h3>";
echo "<p>¿Día habilitado? " . (isset($settings[$day_name]) && !empty($settings[$day_name]['enabled']) ? 'SÍ' : 'NO') . "</p>";

if (isset($settings[$day_name])) {
    echo "<p>Horario: " . $settings[$day_name]['start'] . " - " . $settings[$day_name]['end'] . "</p>";
}

// 2. Get Booked Slots from DB - DEMOS
$leads_table = $wpdb->prefix . 'automatiza_leads';
$booked_results = $wpdb->get_results($wpdb->prepare(
    "SELECT scheduled_time FROM $leads_table WHERE scheduled_date = %s AND (status IS NULL OR status NOT IN ('cancelled', 'no_show'))",
    $date
));

echo "<h3>4. Slots ocupados en DEMOS ($leads_table):</h3>";
echo "<pre>" . print_r($booked_results, true) . "</pre>";

$busy_slots = array();
foreach ($booked_results as $row) {
    $busy_slots[] = substr($row->scheduled_time, 0, 5);
}

// 2.1 Get Booked Slots from FOLLOWUP MEETINGS table
$followup_table = $wpdb->prefix . 'automatiza_followup_meetings';
$followup_results = $wpdb->get_results($wpdb->prepare(
    "SELECT meeting_time FROM $followup_table WHERE meeting_date = %s AND status NOT IN ('cancelled', 'completed')",
    $date
));

echo "<h3>5. Slots ocupados en SEGUIMIENTOS ($followup_table):</h3>";
echo "<pre>" . print_r($followup_results, true) . "</pre>";

foreach ($followup_results as $row) {
    $slot = substr($row->meeting_time, 0, 5);
    if (!in_array($slot, $busy_slots)) {
        $busy_slots[] = $slot;
    }
}

echo "<h3>6. Total de slots ocupados:</h3>";
echo "<pre>" . print_r($busy_slots, true) . "</pre>";

// 3. Calculate if Full Day
$start_time = $settings[$day_name]['start'] ?? '09:00';
$end_time = $settings[$day_name]['end'] ?? '21:00';
$start_hour = (int)explode(':', $start_time)[0];
$end_hour = (int)explode(':', $end_time)[0];

echo "<h3>7. Cálculo de disponibilidad:</h3>";
echo "<p>Rango horario: $start_hour:00 - $end_hour:00</p>";

$total_slots = 0;
$available_slots = 0;
$available_list = [];

for ($h = $start_hour; $h < $end_hour; $h++) {
    $slot = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
    $total_slots++;
    if (!in_array($slot, $busy_slots)) {
        $available_slots++;
        $available_list[] = $slot;
    }
}

echo "<p>Total slots: $total_slots</p>";
echo "<p>Slots disponibles: $available_slots</p>";
echo "<p>Lista disponibles: " . implode(', ', $available_list) . "</p>";

// Resultado final
$result = array(
    'isFullDay' => ($available_slots === 0),
    'busySlots' => $busy_slots,
    'availableSlotsCount' => $available_slots,
    'workingHours' => array('start' => $start_time, 'end' => $end_time)
);

echo "<h3>8. Resultado del API:</h3>";
echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
