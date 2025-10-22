<?php 
/** 
 * Configuracion WordPress - SIN WARNINGS 
 * Automatiza Tech 
 */ 
 
// ** MySQL ** // 
define('DB_NAME', 'automatiza_tech'); 
define('DB_USER', 'root'); 
define('DB_PASSWORD', ''); 
define('DB_HOST', 'localhost'); 
define('DB_CHARSET', 'utf8mb4'); 
define('DB_COLLATE', ''); 
 
// ** DESACTIVAR WARNINGS COMPLETAMENTE ** // 
define('WP_DEBUG', false); 
define('WP_DEBUG_LOG', false); 
define('WP_DEBUG_DISPLAY', false); 
@ini_set('display_errors', 0); 
@ini_set('log_errors', 0); 
@error_reporting(0); 
 
// ** Claves de seguridad ** // 
define('AUTH_KEY',         'clave-unica-auth-1234567890'); 
define('SECURE_AUTH_KEY',  'clave-unica-secure-1234567890'); 
define('LOGGED_IN_KEY',    'clave-unica-logged-1234567890'); 
define('NONCE_KEY',        'clave-unica-nonce-1234567890'); 
define('AUTH_SALT',        'salt-unica-auth-1234567890'); 
define('SECURE_AUTH_SALT', 'salt-unica-secure-1234567890'); 
define('LOGGED_IN_SALT',   'salt-unica-logged-1234567890'); 
define('NONCE_SALT',       'salt-unica-nonce-1234567890'); 
 
// ** Configuracion de tablas ** // 
$table_prefix = 'wp_'; 
 
// ** WordPress ** // 
if ( ! defined( 'ABSPATH' ) ) { 
	define( 'ABSPATH', __DIR__ . '/' ); 
} 
require_once ABSPATH . 'wp-settings.php'; 
