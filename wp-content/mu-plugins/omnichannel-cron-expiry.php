<?php
/**
 * Plugin Name: OmniChannel Period Expiry Cron
 * Description: Handles automatic expiry reminders and suspension for OmniChannel clients.
 */

if (!defined('ABSPATH')) exit;

$cron_file = ABSPATH . 'omnichannel-cron-expiry.php';
if (file_exists($cron_file)) {
    require_once $cron_file;
}
