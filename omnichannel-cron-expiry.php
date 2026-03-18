<?php
/**
 * OmniChannel Period Expiry Cron Handler
 * 
 * Must be loaded by WordPress (add to functions.php or mu-plugins).
 * Handles the 'omnichannel_check_expiry' WP-Cron event.
 * Sends reminder emails at 10, 7, 5, 3 days and suspends at 0.
 */

if (!defined('ABSPATH')) exit;

add_action('omnichannel_check_expiry', 'omnichannel_run_expiry_check');

function omnichannel_run_expiry_check() {
    require_once __DIR__ . '/omnichannel-controller.php';
    $controller = new OmnichannelController();
    $controller->process_expiry_reminders();
}

// Ensure cron is scheduled (self-healing)
add_action('init', function() {
    if (!wp_next_scheduled('omnichannel_check_expiry')) {
        wp_schedule_event(time(), 'twicedaily', 'omnichannel_check_expiry');
    }
});
