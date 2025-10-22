<?php
/**
 * Configuración optimizada de WordPress para Automatiza Tech
 * Compatible con Hostinger y optimizado para rendimiento
 */

// ** Configuración de MySQL - Obtén estos datos de tu hosting ** //
/** Nombre de la base de datos de WordPress */
define( 'DB_NAME', 'automatiza_tech_db' );

/** Usuario de la base de datos de MySQL */
define( 'DB_USER', 'automatiza_tech_user' );

/** Contraseña de la base de datos de MySQL */
define( 'DB_PASSWORD', 'tu_password_segura' );

/** Servidor de la base de datos de MySQL */
define( 'DB_HOST', 'localhost' );

/** Charset de la base de datos para crear las tablas */
define( 'DB_CHARSET', 'utf8mb4' );

/** Tipo de collate de la base de datos */
define( 'DB_COLLATE', '' );

/**#@+
 * Claves únicas de autenticación y sales
 * Genera estas claves en: https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         'pon-tu-clave-unica-aqui' );
define( 'SECURE_AUTH_KEY',  'pon-tu-clave-unica-aqui' );
define( 'LOGGED_IN_KEY',    'pon-tu-clave-unica-aqui' );
define( 'NONCE_KEY',        'pon-tu-clave-unica-aqui' );
define( 'AUTH_SALT',        'pon-tu-clave-unica-aqui' );
define( 'SECURE_AUTH_SALT', 'pon-tu-clave-unica-aqui' );
define( 'LOGGED_IN_SALT',   'pon-tu-clave-unica-aqui' );
define( 'NONCE_SALT',       'pon-tu-clave-unica-aqui' );

/**#@-*/

/**
 * Prefijo de la base de datos de WordPress
 */
$table_prefix = 'at_';

/**
 * Configuraciones de optimización y seguridad
 */

// Límite de revisiones de posts (para mejor rendimiento)
define( 'WP_POST_REVISIONS', 3 );

// Limpieza automática de papelera
define( 'EMPTY_TRASH_DAYS', 7 );

// Incrementar límite de memoria
define( 'WP_MEMORY_LIMIT', '512M' );

// Comprimir scripts y estilos
define( 'COMPRESS_SCRIPTS', true );
define( 'COMPRESS_CSS', true );

// Concatenar scripts y estilos
define( 'CONCATENATE_SCRIPTS', true );

// Optimizaciones de base de datos
define( 'WP_ALLOW_REPAIR', false );

// Configuración de cache
define( 'WP_CACHE', true );
define( 'CACHE_EXPIRATION_TIME', 3600 );

// Configuración de cookies
define( 'COOKIE_DOMAIN', '.automatizatech.com' );

// Configuración de SSL
define( 'FORCE_SSL_ADMIN', true );

// Configuraciones de seguridad
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', false );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );

// Incrementar timeout para API calls
define( 'WP_HTTP_BLOCK_EXTERNAL', false );
define( 'WP_ACCESSIBLE_HOSTS', 'api.whatsapp.com,graph.facebook.com,api.instagram.com' );

// Configuración de cron jobs
define( 'DISABLE_WP_CRON', false );
define( 'WP_CRON_LOCK_TIMEOUT', 60 );

// Configuración multisite (si se necesita en el futuro)
// define( 'WP_ALLOW_MULTISITE', true );

// Debug (desactivar en producción)
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', false );

// Configuración de uploads
define( 'ALLOW_UNFILTERED_UPLOADS', false );

// Configuración FTP (si es necesario)
// define( 'FTP_HOST', 'ftp.automatizatech.com' );
// define( 'FTP_USER', 'tu_usuario_ftp' );
// define( 'FTP_PASS', 'tu_password_ftp' );
// define( 'FTP_SSL', true );

/**
 * Configuraciones específicas para Hostinger
 */

// Configuración de Redis (si está disponible)
if ( class_exists( 'Redis' ) ) {
    define( 'WP_REDIS_HOST', '127.0.0.1' );
    define( 'WP_REDIS_PORT', 6379 );
    define( 'WP_REDIS_TIMEOUT', 1 );
    define( 'WP_REDIS_READ_TIMEOUT', 1 );
    define( 'WP_REDIS_DATABASE', 0 );
}

// Configuración de Memcached (si está disponible)
if ( class_exists( 'Memcached' ) ) {
    define( 'MEMCACHED_SERVERS', array(
        'default' => array(
            '127.0.0.1:11211',
        )
    ) );
}

/**
 * Configuraciones de rendimiento adicionales
 */

// Optimización de consultas de base de datos
define( 'WP_USE_EXT_MYSQL', false );

// Configuración de heartbeat (para reducir uso de CPU)
add_action( 'init', function() {
    wp_deregister_script( 'heartbeat' );
});

// Limitar heartbeat para admin
add_filter( 'heartbeat_settings', function( $settings ) {
    $settings['interval'] = 60; // 60 segundos
    return $settings;
});

/**
 * URLs dinámicas para compatibilidad con diferentes entornos
 */
if ( isset( $_SERVER['HTTP_HOST'] ) ) {
    define( 'WP_HOME', 'https://' . $_SERVER['HTTP_HOST'] );
    define( 'WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST'] );
}

/**
 * Configuración de idioma
 */
define( 'WPLANG', 'es_ES' );

/* ¡Eso es todo, deja de editar! Feliz blogging. */

/** Ruta absoluta al directorio de WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Configura las variables de WordPress y los archivos incluidos. */
require_once ABSPATH . 'wp-settings.php';