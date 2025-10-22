-- Script de configuración inicial para Automatiza Tech WordPress
-- Compatible con MySQL y optimizado para rendimiento

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS automatiza_tech_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE automatiza_tech_db;

-- Crear usuario con permisos limitados para seguridad
CREATE USER IF NOT EXISTS 'automatiza_tech_user'@'localhost' IDENTIFIED BY 'tu_password_segura_aqui';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON automatiza_tech_db.* TO 'automatiza_tech_user'@'localhost';
FLUSH PRIVILEGES;

-- Configuraciones de optimización para MySQL
SET GLOBAL innodb_buffer_pool_size = 134217728; -- 128MB
SET GLOBAL query_cache_size = 67108864; -- 64MB
SET GLOBAL query_cache_type = 1;
SET GLOBAL max_connections = 100;
SET GLOBAL wait_timeout = 600;
SET GLOBAL interactive_timeout = 600;

-- Configuraciones específicas para WordPress
SET GLOBAL innodb_flush_log_at_trx_commit = 2;
SET GLOBAL sync_binlog = 0;

-- Crear tabla personalizada para leads de contacto
CREATE TABLE IF NOT EXISTS at_contact_leads (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    company varchar(255) DEFAULT NULL,
    phone varchar(50) DEFAULT NULL,
    message text,
    source varchar(100) DEFAULT 'website',
    status enum('new','contacted','qualified','converted') DEFAULT 'new',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla para analytics personalizadas
CREATE TABLE IF NOT EXISTS at_analytics (
    id int(11) NOT NULL AUTO_INCREMENT,
    event_type varchar(100) NOT NULL,
    event_category varchar(100) DEFAULT NULL,
    event_action varchar(100) DEFAULT NULL,
    event_label varchar(255) DEFAULT NULL,
    user_ip varchar(45) DEFAULT NULL,
    user_agent text,
    referrer varchar(500) DEFAULT NULL,
    page_url varchar(500) DEFAULT NULL,
    session_id varchar(100) DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla para configuraciones del tema
CREATE TABLE IF NOT EXISTS at_theme_options (
    id int(11) NOT NULL AUTO_INCREMENT,
    option_name varchar(255) NOT NULL,
    option_value longtext,
    autoload enum('yes','no') DEFAULT 'yes',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_option_name (option_name),
    INDEX idx_autoload (autoload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones iniciales del tema
INSERT INTO at_theme_options (option_name, option_value) VALUES
('hero_title', 'Automatiza Tech'),
('hero_subtitle', 'Conectamos tus ventas, web y CRM.'),
('hero_tagline', 'Bots inteligentes para negocios que no se detienen.'),
('whatsapp_number', '+1234567890'),
('contact_email', 'info@automatizatech.com'),
('contact_address', 'Disponible en toda Latinoamérica'),
('primary_color', '#1e40af'),
('secondary_color', '#84cc16'),
('footer_text', 'Conectamos tus ventas, web y CRM con bots inteligentes para negocios que no se detienen.'),
('copyright_text', 'Automatiza Tech. Todos los derechos reservados.'),
('social_facebook', 'https://www.facebook.com/automatizatech'),
('social_instagram', 'https://www.instagram.com/automatizatech'),
('social_linkedin', 'https://www.linkedin.com/company/automatizatech'),
('social_twitter', 'https://www.twitter.com/automatizatech'),
('google_analytics_id', ''),
('facebook_pixel_id', ''),
('site_optimization_enabled', 'yes'),
('lazy_loading_enabled', 'yes'),
('minify_css', 'yes'),
('minify_js', 'yes'),
('enable_gzip', 'yes'),
('cache_enabled', 'yes'),
('preload_critical_css', 'yes'),
('defer_non_critical_js', 'yes')
ON DUPLICATE KEY UPDATE 
option_value = VALUES(option_value),
updated_at = CURRENT_TIMESTAMP;

-- Crear tabla para log de errores
CREATE TABLE IF NOT EXISTS at_error_log (
    id int(11) NOT NULL AUTO_INCREMENT,
    error_type varchar(100) NOT NULL,
    error_message text NOT NULL,
    file_path varchar(500) DEFAULT NULL,
    line_number int(11) DEFAULT NULL,
    user_id int(11) DEFAULT NULL,
    user_ip varchar(45) DEFAULT NULL,
    request_uri varchar(500) DEFAULT NULL,
    user_agent text,
    stack_trace text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_error_type (error_type),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla para cache personalizada
CREATE TABLE IF NOT EXISTS at_cache (
    cache_key varchar(255) NOT NULL,
    cache_value longtext,
    expiry_time int(11) NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cache_key),
    INDEX idx_expiry_time (expiry_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla para suscripciones a newsletter
CREATE TABLE IF NOT EXISTS at_newsletter_subscribers (
    id int(11) NOT NULL AUTO_INCREMENT,
    email varchar(255) NOT NULL,
    name varchar(255) DEFAULT NULL,
    status enum('active','unsubscribed','bounced') DEFAULT 'active',
    source varchar(100) DEFAULT 'website',
    subscription_date timestamp DEFAULT CURRENT_TIMESTAMP,
    unsubscribe_date timestamp NULL DEFAULT NULL,
    confirmation_token varchar(100) DEFAULT NULL,
    confirmed_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_email (email),
    INDEX idx_status (status),
    INDEX idx_subscription_date (subscription_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear vista para estadísticas de leads
CREATE OR REPLACE VIEW at_lead_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_leads,
    COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
    COUNT(CASE WHEN status = 'contacted' THEN 1 END) as contacted_leads,
    COUNT(CASE WHEN status = 'qualified' THEN 1 END) as qualified_leads,
    COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
    ROUND((COUNT(CASE WHEN status = 'converted' THEN 1 END) / COUNT(*)) * 100, 2) as conversion_rate
FROM at_contact_leads 
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Crear procedimiento almacenado para limpiar datos antiguos
DELIMITER //
CREATE PROCEDURE CleanOldData()
BEGIN
    -- Limpiar logs de errores más antiguos de 30 días
    DELETE FROM at_error_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Limpiar analytics más antiguos de 90 días
    DELETE FROM at_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Limpiar cache expirada
    DELETE FROM at_cache WHERE expiry_time < UNIX_TIMESTAMP();
    
    -- Optimizar tablas después de la limpieza
    OPTIMIZE TABLE at_error_log, at_analytics, at_cache;
END //
DELIMITER ;

-- Crear evento para ejecutar limpieza automática
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS daily_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
  CALL CleanOldData();

-- Configuraciones finales de optimización
-- Configurar MySQL para mejor rendimiento con WordPress
SET GLOBAL innodb_file_per_table = 1;
SET GLOBAL innodb_flush_method = 'O_DIRECT';
SET GLOBAL tmp_table_size = 134217728; -- 128MB
SET GLOBAL max_heap_table_size = 134217728; -- 128MB

-- Crear índices adicionales para WordPress (si las tablas ya existen)
-- Estos se ejecutarán solo si las tablas de WordPress ya están creadas

-- Ejemplo de consulta para verificar y crear índices
-- ALTER TABLE wp_posts ADD INDEX idx_post_name (post_name);
-- ALTER TABLE wp_posts ADD INDEX idx_post_parent (post_parent);
-- ALTER TABLE wp_postmeta ADD INDEX idx_meta_key_value (meta_key, meta_value(10));

COMMIT;