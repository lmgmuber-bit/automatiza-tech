<?php
/**
 * AT Maintenance Guard
 * ====================
 * Cualquier script de mantenimiento (debug-*, check-*, setup-*, fix-*, test-*,
 * add-*, update-*, reset-*, revert-*, mark-*, purge-*, flush-*, etc.) debe
 * incluir este archivo en su PRIMERA linea ejecutable, asi:
 *
 *   <?php require_once __DIR__ . '/at-maintenance-guard.php';
 *
 * Bloquea ejecucion publica y exige rol administrador. En CLI (wp-cli o php
 * directo desde shell) se permite la ejecucion sin restriccion.
 */

if (defined('AT_MAINTENANCE_GUARD_OK')) {
    return;
}

if (PHP_SAPI === 'cli') {
    define('AT_MAINTENANCE_GUARD_OK', true);
    return;
}

if (!defined('ABSPATH')) {
    require_once __DIR__ . '/wp-load.php';
}

if (!function_exists('is_user_logged_in') || !is_user_logged_in()
    || !current_user_can('manage_options')) {
    if (function_exists('status_header')) {
        status_header(403);
    } else {
        header('HTTP/1.1 403 Forbidden');
    }
    exit('Acceso denegado');
}

define('AT_MAINTENANCE_GUARD_OK', true);
