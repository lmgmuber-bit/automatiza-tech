-- Ejecutar en phpMyAdmin o panel de base de datos
CREATE TABLE IF NOT EXISTS wp_automatiza_services (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    category varchar(50) DEFAULT 'pricing',
    price_usd decimal(10,2) DEFAULT 0.00,
    price_clp decimal(12,0) DEFAULT 0,
    description text,
    features text,
    icon varchar(100) DEFAULT 'fas fa-star',
    highlight tinyint(1) DEFAULT 0,
    button_text varchar(100) DEFAULT '',
    whatsapp_message text,
    status varchar(20) DEFAULT 'active',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
--Esto es para el nuevo sistema de servicios y planes en la web esto son los datos iniciales
-- Insertar servicios de ejemplo
INSERT INTO wp_automatiza_services (name, category, price_usd, price_clp, description, features, icon, highlight, button_text, whatsapp_message, status) VALUES
('Plan Básico', 'pricing', 99.00, 79200, 'Perfecto para pequeños emprendimientos que inician su automatización', '["Hasta 1,000 conversaciones/mes","WhatsApp Business API","Respuestas automáticas básicas","Soporte por email","Analíticas básicas","Integración con 1 plataforma"]', 'fas fa-seedling', 0, 'Comenzar Ahora', 'Hola! Me interesa el Plan Básico de automatización. ¿Podrías darme más información?', 'active'),
('Plan Profesional', 'pricing', 199.00, 159200, 'Para empresas en crecimiento que buscan automatización avanzada', '["Todo del Plan Básico","Hasta 5,000 conversaciones/mes","IA avanzada personalizada","Integración con múltiples plataformas","Analíticas avanzadas","API personalizada","Soporte prioritario","Menú interactivo"]', 'fas fa-rocket', 1, 'Más Popular', 'Hola! Me interesa el Plan Profesional de automatización. ¿Podrías darme más información?', 'active'),
('Plan Enterprise', 'pricing', 399.00, 319200, 'Solución completa para grandes empresas con necesidades específicas', '["Todo del Plan Profesional","Conversaciones ilimitadas","Integraciones personalizadas","IA ultra avanzada","Soporte 24/7","Gerente de cuenta dedicado","Implementación personalizada","Dashboard ejecutivo"]', 'fas fa-crown', 0, 'Contactar', 'Hola! Me interesa el Plan Enterprise. ¿Podríamos agendar una reunión para discutir nuestras necesidades?', 'active'),
('Atención 24/7', 'features', 0.00, 0, 'Chatbots inteligentes que nunca descansan. Atiende a tus clientes las 24 horas del día, los 7 días de la semana.', '["Respuesta inmediata","Sin horarios limitados","Cobertura global","Múltiples idiomas"]', 'fas fa-robot', 0, '', '', 'active'),
('Aumenta tus Ventas', 'features', 0.00, 0, 'Convierte más leads en clientes con respuestas automáticas inteligentes y seguimiento personalizado.', '["Lead scoring automático","Seguimiento personalizado","Abandono de carrito","Upselling inteligente"]', 'fas fa-chart-line', 0, '', '', 'active'),
('Fácil Integración', 'features', 0.00, 0, 'Se integra perfectamente con WhatsApp, Instagram, tu sitio web y tu CRM existente.', '["WhatsApp Business API","Instagram Direct","Facebook Messenger","Integración CRM","Webhooks personalizados"]', 'fas fa-cogs', 0, '', '', 'active'),
('Web + WhatsApp Business para Emprendimientos', 'special', 299.00, 239200, 'Sitio web profesional + automatización completa de WhatsApp Business para impulsar tu emprendimiento', '["Sitio web responsive y optimizado","WhatsApp Business API completo","Catálogo de productos integrado","Sistema de pedidos automatizado","Panel de administración","Analíticas de ventas","Capacitación incluida","Soporte por 3 meses"]', 'fas fa-store', 1, '¡Quiero mi Web + WhatsApp!', 'Hola! Me interesa el paquete Web + WhatsApp Business para mi emprendimiento. ¿Podrían darme más detalles?', 'active');