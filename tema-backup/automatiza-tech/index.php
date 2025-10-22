<?php
/**
 * Template principal de Automatiza Tech
 *
 * @package AutomatizaTech
 */

get_header(); ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content fade-in-up">
                <h1 class="hero-title">
                    <?php echo esc_html(get_theme_mod('hero_title', 'Automatiza Tech')); ?>
                </h1>
                <p class="hero-subtitle">
                    <?php echo esc_html(get_theme_mod('hero_subtitle', 'Conectamos tus ventas, web y CRM.')); ?>
                </p>
                <p class="hero-tagline">
                    <?php echo esc_html(get_theme_mod('hero_tagline', 'Bots inteligentes para negocios que no se detienen.')); ?>
                </p>
                <div class="hero-cta">
                    <a href="#contact" class="btn btn-secondary">Solicita tu Demo</a>
                    <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa conocer más sobre Automatiza Tech')); ?>" 
                       class="btn btn-outline" target="_blank">
                        <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="beneficios">
        <div class="container">
            <h2 class="section-title">¿Por qué elegir Automatiza Tech?</h2>
            <p class="text-center text-muted mb-5">Automatiza tu atención, ahorra tiempo, escala tu negocio</p>
            
            <div class="features-grid">
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>Atención 24/7</h3>
                    <p>Chatbots inteligentes que nunca descansan. Atiende a tus clientes las 24 horas del día, los 7 días de la semana.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Aumenta tus Ventas</h3>
                    <p>Convierte más leads en clientes con respuestas automáticas inteligentes y seguimiento personalizado.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Fácil Integración</h3>
                    <p>Se integra perfectamente con WhatsApp, Instagram, tu sitio web y tu CRM existente.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Ahorra Tiempo</h3>
                    <p>Automatiza respuestas frecuentes y libera tiempo para enfocarte en hacer crecer tu negocio.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Mejor Experiencia</h3>
                    <p>Respuestas instantáneas y personalizadas que mejoran la satisfacción de tus clientes.</p>
                </div>
                
                <div class="feature-card fade-in-up">
                    <div class="feature-icon">
                        <i class="fas fa-analytics"></i>
                    </div>
                    <h3>Analíticas Avanzadas</h3>
                    <p>Métricas detalladas para optimizar tu estrategia de atención al cliente y ventas.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Integrations Section -->
    <section class="integrations-section" id="integraciones">
        <div class="container">
            <h2 class="section-title">Integraciones Disponibles</h2>
            <p class="text-center text-muted mb-5">Conecta con todas las plataformas que ya usas</p>
            
            <div class="integrations-grid">
                <div class="integration-item">
                    <div class="integration-icon text-success mb-3">
                        <i class="fab fa-whatsapp fa-3x"></i>
                    </div>
                    <h4>WhatsApp</h4>
                    <p>Automatiza conversaciones en WhatsApp Business</p>
                </div>
                
                <div class="integration-item">
                    <div class="integration-icon text-danger mb-3">
                        <i class="fab fa-instagram fa-3x"></i>
                    </div>
                    <h4>Instagram</h4>
                    <p>Gestiona mensajes directos automáticamente</p>
                </div>
                
                <div class="integration-item">
                    <div class="integration-icon text-primary mb-3">
                        <i class="fas fa-globe fa-3x"></i>
                    </div>
                    <h4>Sitio Web</h4>
                    <p>Chat widget para tu página web</p>
                </div>
                
                <div class="integration-item">
                    <div class="integration-icon text-warning mb-3">
                        <i class="fas fa-database fa-3x"></i>
                    </div>
                    <h4>CRM</h4>
                    <p>Sincroniza con tu CRM favorito</p>
                </div>
                
                <div class="integration-item">
                    <div class="integration-icon text-info mb-3">
                        <i class="fab fa-facebook-messenger fa-3x"></i>
                    </div>
                    <h4>Messenger</h4>
                    <p>Automatiza Facebook Messenger</p>
                </div>
                
                <div class="integration-item">
                    <div class="integration-icon text-secondary mb-3">
                        <i class="fas fa-envelope fa-3x"></i>
                    </div>
                    <h4>Email</h4>
                    <p>Integra con tu sistema de email marketing</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Industries Section -->
    <section class="industries-section bg-light" id="industrias">
        <div class="container">
            <h2 class="section-title">Casos de Uso por Industria</h2>
            <p class="text-center text-muted mb-5">Soluciones específicas para cada tipo de negocio</p>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="industry-card card h-100">
                        <div class="card-body text-center">
                            <div class="industry-icon text-primary mb-3">
                                <i class="fas fa-store fa-3x"></i>
                            </div>
                            <h5 class="card-title">E-commerce</h5>
                            <p class="card-text">Automatiza consultas de productos, seguimiento de pedidos y soporte post-venta.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="industry-card card h-100">
                        <div class="card-body text-center">
                            <div class="industry-icon text-success mb-3">
                                <i class="fas fa-heartbeat fa-3x"></i>
                            </div>
                            <h5 class="card-title">Salud</h5>
                            <p class="card-text">Gestiona citas médicas, recordatorios y consultas básicas de pacientes.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="industry-card card h-100">
                        <div class="card-body text-center">
                            <div class="industry-icon text-warning mb-3">
                                <i class="fas fa-graduation-cap fa-3x"></i>
                            </div>
                            <h5 class="card-title">Educación</h5>
                            <p class="card-text">Atiende consultas de estudiantes, información de cursos y procesos de inscripción.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="industry-card card h-100">
                        <div class="card-body text-center">
                            <div class="industry-icon text-danger mb-3">
                                <i class="fas fa-utensils fa-3x"></i>
                            </div>
                            <h5 class="card-title">Restaurantes</h5>
                            <p class="card-text">Toma pedidos automáticamente, gestiona reservas y ofrece menús interactivos.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="industry-card card h-100">
                        <div class="card-body text-center">
                            <div class="industry-icon text-info mb-3">
                                <i class="fas fa-home fa-3x"></i>
                            </div>
                            <h5 class="card-title">Inmobiliaria</h5>
                            <p class="card-text">Califica leads, agenda visitas y proporciona información de propiedades.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="industry-card card h-100">
                        <div class="card-body text-center">
                            <div class="industry-icon text-secondary mb-3">
                                <i class="fas fa-briefcase fa-3x"></i>
                            </div>
                            <h5 class="card-title">Servicios</h5>
                            <p class="card-text">Gestiona cotizaciones, agenda citas y brinda soporte técnico automatizado.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing-section" id="planes">
        <div class="container">
            <h2 class="section-title">Planes y Precios</h2>
            <p class="text-center text-muted mb-5">Elige el plan que mejor se adapte a tu negocio</p>
            
            <div class="row justify-content-center">
                <div class="col-md-4 mb-4">
                    <div class="pricing-card card h-100">
                        <div class="card-header text-center bg-light">
                            <h5 class="card-title">Básico</h5>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">99</span>
                                <span class="period">/mes</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Hasta 1,000 conversaciones/mes</li>
                                <li><i class="fas fa-check text-success"></i> WhatsApp y Web Chat</li>
                                <li><i class="fas fa-check text-success"></i> Respuestas automáticas básicas</li>
                                <li><i class="fas fa-check text-success"></i> Soporte por email</li>
                                <li><i class="fas fa-check text-success"></i> Analíticas básicas</li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="#contact" class="btn btn-outline-primary">Comenzar</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="pricing-card card h-100 border-primary">
                        <div class="card-header text-center bg-primary text-white">
                            <h5 class="card-title">Profesional</h5>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">199</span>
                                <span class="period">/mes</span>
                            </div>
                            <span class="badge badge-light">Más Popular</span>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Hasta 5,000 conversaciones/mes</li>
                                <li><i class="fas fa-check text-success"></i> Todas las integraciones</li>
                                <li><i class="fas fa-check text-success"></i> IA avanzada</li>
                                <li><i class="fas fa-check text-success"></i> Soporte prioritario</li>
                                <li><i class="fas fa-check text-success"></i> Analíticas avanzadas</li>
                                <li><i class="fas fa-check text-success"></i> API personalizada</li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="#contact" class="btn btn-primary">Comenzar</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="pricing-card card h-100">
                        <div class="card-header text-center bg-secondary text-white">
                            <h5 class="card-title">Enterprise</h5>
                            <div class="price">
                                <span class="currency">$</span>
                                <span class="amount">399</span>
                                <span class="period">/mes</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> Conversaciones ilimitadas</li>
                                <li><i class="fas fa-check text-success"></i> Integraciones personalizadas</li>
                                <li><i class="fas fa-check text-success"></i> IA ultra avanzada</li>
                                <li><i class="fas fa-check text-success"></i> Soporte 24/7</li>
                                <li><i class="fas fa-check text-success"></i> Gerente de cuenta dedicado</li>
                                <li><i class="fas fa-check text-success"></i> Implementación personalizada</li>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <a href="#contact" class="btn btn-secondary">Contactar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="section-title text-white text-center">¿Listo para automatizar tu negocio?</h2>
                    <p class="text-center text-white mb-5">Completa el formulario y uno de nuestros expertos te contactará en menos de 24 horas</p>
                    
                    <?php echo do_shortcode('[contact_form]'); ?>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- WhatsApp Float Button -->
<a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa conocer más sobre Automatiza Tech')); ?>" 
   class="whatsapp-float" target="_blank" title="Contáctanos por WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<?php get_footer(); ?>