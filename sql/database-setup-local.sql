-- Script de configuración LOCAL para Automatiza Tech WordPress
-- Para uso con XAMPP, WAMP, MAMP o servidor local

-- Crear base de datos LOCAL
CREATE DATABASE IF NOT EXISTS automatiza_tech_local 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Usar la base de datos
USE automatiza_tech_local;

-- Configuraciones optimizadas para desarrollo local
SET GLOBAL innodb_buffer_pool_size = 67108864; -- 64MB para local
SET GLOBAL query_cache_size = 33554432; -- 32MB para local
SET GLOBAL query_cache_type = 1;
SET GLOBAL max_connections = 50; -- Menos conexiones para local
SET GLOBAL wait_timeout = 28800; -- 8 horas para desarrollo
SET GLOBAL interactive_timeout = 28800;

-- Configuraciones para desarrollo local
SET GLOBAL innodb_flush_log_at_trx_commit = 0; -- Más rápido para desarrollo
SET GLOBAL sync_binlog = 0;
SET GLOBAL general_log = 1; -- Activar log general para debug

-- Crear tabla personalizada para leads de contacto
CREATE TABLE IF NOT EXISTS at_local_contact_leads (
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

-- Crear tabla para analytics personalizadas (LOCAL)
CREATE TABLE IF NOT EXISTS at_local_analytics (
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

-- Crear tabla para configuraciones del tema (LOCAL)
CREATE TABLE IF NOT EXISTS at_local_theme_options (
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

-- Insertar configuraciones iniciales del tema para DESARROLLO LOCAL
INSERT INTO at_local_theme_options (option_name, option_value) VALUES
('hero_title', 'Automatiza Tech - DESARROLLO'),
('hero_subtitle', 'Conectamos tus ventas, web y CRM.'),
('hero_tagline', 'Bots inteligentes para negocios que no se detienen. [ENTORNO DE DESARROLLO]'),
('whatsapp_number', '+1234567890'),
('contact_email', 'dev@automatizatech.local'),
('contact_address', 'Entorno de Desarrollo Local'),
('primary_color', '#1e40af'),
('secondary_color', '#84cc16'),
('footer_text', 'Automatiza Tech - Entorno de Desarrollo Local'),
('copyright_text', 'Automatiza Tech - Desarrollo Local. Todos los derechos reservados.'),
('social_facebook', 'https://www.facebook.com/automatizatech'),
('social_instagram', 'https://www.instagram.com/automatizatech'),
('social_linkedin', 'https://www.linkedin.com/company/automatizatech'),
('social_twitter', 'https://www.twitter.com/automatizatech'),
('google_analytics_id', ''), -- Vacío en desarrollo
('facebook_pixel_id', ''), -- Vacío en desarrollo
('site_optimization_enabled', 'no'), -- Desactivado en desarrollo
('lazy_loading_enabled', 'no'), -- Desactivado en desarrollo
('minify_css', 'no'), -- Desactivado en desarrollo
('minify_js', 'no'), -- Desactivado en desarrollo
('enable_gzip', 'no'), -- Desactivado en desarrollo
('cache_enabled', 'no'), -- Desactivado en desarrollo
('preload_critical_css', 'no'), -- Desactivado en desarrollo
('defer_non_critical_js', 'no'), -- Desactivado en desarrollo
('development_mode', 'yes'), -- Indicador de desarrollo
('debug_mode', 'yes'), -- Debug activado
('local_environment', 'yes') -- Entorno local
ON DUPLICATE KEY UPDATE 
option_value = VALUES(option_value),
updated_at = CURRENT_TIMESTAMP;

-- Crear tabla para log de errores de desarrollo
CREATE TABLE IF NOT EXISTS at_local_error_log (
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

-- Crear tabla para testing y desarrollo
CREATE TABLE IF NOT EXISTS at_local_test_data (
    id int(11) NOT NULL AUTO_INCREMENT,
    test_name varchar(255) NOT NULL,
    test_data longtext,
    test_result varchar(255) DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_test_name (test_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar datos de prueba para desarrollo
INSERT INTO at_local_contact_leads (name, email, company, phone, message, source, status) VALUES
('Juan Pérez', 'juan.perez@ejemplo.com', 'Empresa Demo SA', '+52123456789', 'Mensaje de prueba para desarrollo local', 'website', 'new'),
('María González', 'maria.gonzalez@test.com', 'Test Company', '+52987654321', 'Otro mensaje de prueba', 'website', 'contacted'),
('Carlos Rodríguez', 'carlos@demo.local', 'Demo Local Corp', '+52555123456', 'Lead de ejemplo para testing', 'website', 'qualified');

-- Insertar eventos de analytics de prueba
INSERT INTO at_local_analytics (event_type, event_category, event_action, event_label, user_ip, page_url, session_id) VALUES
('page_view', 'engagement', 'view', 'homepage', '127.0.0.1', 'http://localhost/automatiza-tech/', 'local_session_1'),
('form_submit', 'conversion', 'submit', 'contact_form', '127.0.0.1', 'http://localhost/automatiza-tech/', 'local_session_1'),
('click', 'engagement', 'cta_click', 'solicita_demo', '127.0.0.1', 'http://localhost/automatiza-tech/', 'local_session_2');

-- Crear vista para estadísticas de desarrollo
CREATE OR REPLACE VIEW at_local_lead_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_leads,
    COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
    COUNT(CASE WHEN status = 'contacted' THEN 1 END) as contacted_leads,
    COUNT(CASE WHEN status = 'qualified' THEN 1 END) as qualified_leads,
    COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
    ROUND((COUNT(CASE WHEN status = 'converted' THEN 1 END) / COUNT(*)) * 100, 2) as conversion_rate
FROM at_local_contact_leads 
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Crear procedimiento para limpiar datos de desarrollo
DELIMITER //
CREATE PROCEDURE CleanLocalData()
BEGIN
    -- Limpiar logs de errores más antiguos de 7 días (menos tiempo en desarrollo)
    DELETE FROM at_local_error_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Limpiar analytics más antiguos de 30 días (menos tiempo en desarrollo)
    DELETE FROM at_local_analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Limpiar datos de test antiguos
    DELETE FROM at_local_test_data WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Optimizar tablas después de la limpieza
    OPTIMIZE TABLE at_local_error_log, at_local_analytics, at_local_test_data;
    
    SELECT 'Limpieza de datos de desarrollo completada' as message;
END //
DELIMITER ;

-- Usuario adicional para desarrollo (opcional)
CREATE USER IF NOT EXISTS 'dev_user'@'localhost' IDENTIFIED BY 'dev_password';
GRANT ALL PRIVILEGES ON automatiza_tech_local.* TO 'dev_user'@'localhost';

-- Configuraciones finales para desarrollo local
SET GLOBAL general_log_file = 'mysql-general.log';
SET GLOBAL slow_query_log = 1;
SET GLOBAL slow_query_log_file = 'mysql-slow.log';
SET GLOBAL long_query_time = 2;

-- Mostrar resumen de configuración
SELECT 'Base de datos local configurada exitosamente' as status;
SELECT DATABASE() as current_database;
SELECT USER() as current_user;
SELECT NOW() as setup_time;

COMMIT;