<?php
require_once __DIR__ . '/at-maintenance-guard.php';

$date = '2026-01-16';
echo "Date: $date\n";
echo "Day of week: " . strtolower(date('l', strtotime($date))) . "\n";

// Check settings
$settings = array(
    'monday' => array('enabled' => true, 'start' => '09:00', 'end' => '21:00'),
    'tuesday' => array('enabled' => true, 'start' => '09:00', 'end' => '21:00'),
    'wednesday' => array('enabled' => true, 'start' => '09:00', 'end' => '21:00'),
    'thursday' => array('enabled' => true, 'start' => '09:00', 'end' => '21:00'),
    'friday' => array('enabled' => true, 'start' => '09:00', 'end' => '21:00'),
    'saturday' => array('enabled' => true, 'start' => '15:00', 'end' => '17:00'),
    'sunday' => array('enabled' => true, 'start' => '15:00', 'end' => '17:00'),
);

$day_name = strtolower(date('l', strtotime($date)));
echo "Settings for $day_name: " . print_r($settings[$day_name], true) . "\n";
