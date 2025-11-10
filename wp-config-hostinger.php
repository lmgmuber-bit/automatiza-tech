<?php
/* ==========================================
   WordPress config — Hostinger (SAFE TEMPLATE)
   Dominio: automatizatech.shop
   ========================================== */

/* 1) Rellena con los datos reales de hPanel */
define('DB_NAME',     'REEMPLAZA_DB_NAME');
define('DB_USER',     'REEMPLAZA_DB_USER');
define('DB_PASSWORD', 'REEMPLAZA_DB_PASSWORD');
define('DB_HOST',     'localhost'); // En Hostinger suele ser 'localhost'

/* Charset/Collation */
define('DB_CHARSET',  'utf8mb4');
define('DB_COLLATE',  '');

/* 2) Detección de HTTPS y URLs dinámicas (evita bucles) */
$https_on = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '')
    || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
if ($https_on) { $_SERVER['HTTPS'] = 'on'; }
if (!defined('WP_HOME') && !defined('WP_SITEURL') && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = $https_on ? 'https' : 'http';
    define('WP_HOME',    $scheme . '://' . $_SERVER['HTTP_HOST']);
    define('WP_SITEURL', $scheme . '://' . $_SERVER['HTTP_HOST']);
}
/*
   Si prefieres forzar el dominio fijo, descomenta estas dos líneas cuando
   la web ya resuelva bien por HTTPS y dominio correcto:
   define('WP_HOME',    'https://automatizatech.shop');
   define('WP_SITEURL', 'https://automatizatech.shop');
*/

/* Prefijo de tablas (ajusta si tu base ya tiene otro) */
$table_prefix = 'wp_';

/* 3) Claves/SALTs (sustituye por valores únicos de https://api.wordpress.org/secret-key/1.1/salt/) */
define('AUTH_KEY',         'PÉGALA_AQUÍ');
define('SECURE_AUTH_KEY',  'PÉGALA_AQUÍ');
define('LOGGED_IN_KEY',    'PÉGALA_AQUÍ');
define('NONCE_KEY',        'PÉGALA_AQUÍ');
define('AUTH_SALT',        'PÉGALA_AQUÍ');
define('SECURE_AUTH_SALT', 'PÉGALA_AQUÍ');
define('LOGGED_IN_SALT',   'PÉGALA_AQUÍ');
define('NONCE_SALT',       'PÉGALA_AQUÍ');

/* 4) Producción segura */
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', true);   // guarda en wp-content/debug.log
define('WP_DEBUG_DISPLAY', false);

define('WP_ENVIRONMENT_TYPE', 'production');

define('DISALLOW_FILE_EDIT', true);
// Si tienes problemas con actualizaciones, comenta FS_METHOD y usa el gestor de archivos.
define('FS_METHOD', 'direct');

define('FORCE_SSL_ADMIN', false); // Activa true cuando el SSL del dominio funcione OK

define('WP_MEMORY_LIMIT', '256M');

define('DISABLE_WP_CRON', false);

/* Final */
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once ABSPATH . 'wp-settings.php';
