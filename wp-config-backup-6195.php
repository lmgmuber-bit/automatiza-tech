<?php 
/** 
 * Configuracion WordPress IDENTICA a phpMyAdmin 
 * Usuario: root 
 * Fecha: 20-10-2025  9:02:42,06 
 */ 
 
// ** CONFIGURACION EXACTA DE PHPMYADMIN ** // 
define( 'DB_NAME', 'automatiza_tech' ); 
define( 'DB_USER', 'root' ); 
define( 'DB_PASSWORD', '' ); 
define( 'DB_HOST', 'localhost' ); 
define( 'DB_CHARSET', 'utf8mb4' ); 
define( 'DB_COLLATE', '' ); 
 
// ** CONFIGURACION ADICIONAL ** // 
define( 'WP_DEBUG', false ); 
define( 'WP_DEBUG_LOG', false ); 
define( 'WP_DEBUG_DISPLAY', false ); 
@ini_set( 'display_errors', 0 ); 
@error_reporting( 0 ); 
 
// ** URLS FIJAS PARA DESARROLLO LOCAL ** // 
define( 'WP_HOME', 'http://localhost/automatiza-tech' ); 
define( 'WP_SITEURL', 'http://localhost/automatiza-tech' ); 
 
// ** CLAVES DE SEGURIDAD ** // 
define( 'AUTH_KEY',         'automatiza-tech-auth-5502' ); 
define( 'SECURE_AUTH_KEY',  'automatiza-tech-secure-1151' ); 
define( 'LOGGED_IN_KEY',    'automatiza-tech-logged-10140' ); 
define( 'NONCE_KEY',        'automatiza-tech-nonce-17431' ); 
define( 'AUTH_SALT',        'automatiza-tech-auth-salt-6821' ); 
define( 'SECURE_AUTH_SALT', 'automatiza-tech-secure-salt-6832' ); 
define( 'LOGGED_IN_SALT',   'automatiza-tech-logged-salt-14430' ); 
define( 'NONCE_SALT',       'automatiza-tech-nonce-salt-19221' ); 
 
// ** PREFIJO DE TABLAS ** // 
$table_prefix = 'wp_'; 
 
// ** WORDPRESS CORE ** // 
if ( ! defined( 'ABSPATH' ) ) { 
	define( 'ABSPATH', __DIR__ . '/' ); 
} 
 
require_once ABSPATH . 'wp-settings.php'; 
