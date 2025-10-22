<?php
/**
 * Configuración LOCAL de WordPress para Automatiza Tech
 * Para uso con XAMPP, WAMP, MAMP o servidor local
 */

// ** Configuración de MySQL LOCAL ** //
/** Nombre de la base de datos de WordPress */
define( 'DB_NAME', 'automatiza_tech_local' );
define('WP_ALLOW_REPAIR', true);
/** Usuario de la base de datos de MySQL */
define( 'DB_USER', 'root' );

/** Contraseña de la base de datos de MySQL */
// Para XAMPP: generalmente vacío ''
// Para WAMPServer: puede ser vacío '' o la contraseña configurada
define( 'DB_PASSWORD', '' ); 

/** Servidor de la base de datos de MySQL */
define( 'DB_HOST', 'localhost' );

/** Puerto de MySQL (por defecto 3306, pero WAMP puede usar otro) */
// Si WAMP usa un puerto diferente, descomenta y ajusta:
// define( 'DB_HOST', 'localhost:3307' );

/** Charset de la base de datos para crear las tablas */
define( 'DB_CHARSET', 'utf8mb4' );

/** Tipo de collate de la base de datos */
define( 'DB_COLLATE', '' );

/**#@+
 * Claves únicas de autenticación y sales
 * Genera estas claves en: https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         'local-key-1234567890abcdef' );
define( 'SECURE_AUTH_KEY',  'local-secure-key-1234567890' );
define( 'LOGGED_IN_KEY',    'local-logged-key-1234567890' );
define( 'NONCE_KEY',        'local-nonce-key-1234567890' );
define( 'AUTH_SALT',        'local-auth-salt-1234567890' );
define( 'SECURE_AUTH_SALT', 'local-secure-salt-1234567890' );
define( 'LOGGED_IN_SALT',   'local-logged-salt-1234567890' );
define( 'NONCE_SALT',       'local-nonce-salt-1234567890' );

/**#@-*/

/**
 * Prefijo de la base de datos de WordPress
 */
$table_prefix = 'at_local_';

/**
 * Configuraciones para DESARROLLO LOCAL
 */

// URLs dinámicas para desarrollo local
define( 'WP_HOME', 'http://localhost/automatiza-tech' );
define( 'WP_SITEURL', 'http://localhost/automatiza-tech' );

// Configuración de DEBUG (ACTIVADO para desarrollo)
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', true );

// Logs de errores en archivo
ini_set( 'log_errors', 1 );
ini_set( 'error_log', __DIR__ . '/wp-content/debug.log' );

// Límite de revisiones de posts
define( 'WP_POST_REVISIONS', 3 );

// Limpieza automática de papelera
define( 'EMPTY_TRASH_DAYS', 7 );

// Incrementar límite de memoria para desarrollo
define( 'WP_MEMORY_LIMIT', '512M' );

// Desactivar cache en desarrollo
define( 'WP_CACHE', false );

// Permitir edición de archivos (útil en desarrollo)
define( 'DISALLOW_FILE_EDIT', false );
define( 'DISALLOW_FILE_MODS', false );

// Actualizaciones automáticas desactivadas en desarrollo
define( 'WP_AUTO_UPDATE_CORE', false );

// Configuración de cookies para localhost
define( 'COOKIE_DOMAIN', 'localhost' );

// Desactivar SSL en desarrollo local
define( 'FORCE_SSL_ADMIN', false );

// Configuración de timeout más permisiva para desarrollo
define( 'WP_HTTP_BLOCK_EXTERNAL', false );

// Configuración de cron jobs
define( 'DISABLE_WP_CRON', false );
define( 'WP_CRON_LOCK_TIMEOUT', 60 );

// Configuración de uploads más permisiva
define( 'ALLOW_UNFILTERED_UPLOADS', true );

/**
 * Configuraciones específicas para desarrollo local
 */

// Mostrar todos los errores PHP
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

// Configuración de sesiones
ini_set( 'session.gc_maxlifetime', 3600 );

// Aumentar tiempo de ejecución para importaciones
ini_set( 'max_execution_time', 300 );
ini_set( 'max_input_time', 300 );

// Aumentar límites de upload
ini_set( 'upload_max_filesize', '64M' );
ini_set( 'post_max_size', '64M' );

/**
 * Configuración de idioma
 */
define( 'WPLANG', 'es_ES' );

/**
 * Configuraciones de desarrollo adicionales
 */

// Desactivar compresión en desarrollo para facilitar debug
define( 'COMPRESS_SCRIPTS', false );
define( 'COMPRESS_CSS', false );
define( 'CONCATENATE_SCRIPTS', false );

// Query debugging (útil para optimización)
define( 'SAVEQUERIES', true );

// Configuración de mail local (usar MailHog o similar)
define( 'SMTP_HOST', 'localhost' );
define( 'SMTP_PORT', 1025 );
define( 'SMTP_USER', '' );
define( 'SMTP_PASS', '' );
define( 'SMTP_FROM', 'dev@automatizatech.local' );
define( 'SMTP_FROM_NAME', 'Automatiza Tech Local' );

/* ¡Eso es todo, deja de editar! Feliz blogging. */

/** Ruta absoluta al directorio de WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Configura las variables de WordPress y los archivos incluidos. */
require_once ABSPATH . 'wp-settings.php';
