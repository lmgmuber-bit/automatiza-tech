<?php
/**
 * Template principal de Automatiza Tech
 *
 * @package AutomatizaTech
 */

get_header(); ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="hero-section hero-with-banner">

        <!-- Imagen de fondo DESKTOP (robot izquierda, espacio derecho para el contenido) -->
        <div class="hero-banner-img hero-desktop-only">
            <img src="<?php echo esc_url(home_url('/promo-assets/gemini-05-hero.png')); ?>"
                 alt="AutomatizaTech — Plataforma Omnicanal con IA"
                 loading="eager" fetchpriority="high" />
        </div>

        <!-- Fondo animado MOBILE (reemplaza la imagen pixelada) -->
        <div class="hero-mobile-bg" aria-hidden="true">
            <canvas id="heroMobileCanvas"></canvas>
            <div class="hero-mobile-robot">
                <img src="<?php echo esc_url(home_url('/promo-assets/gemini-05-hero.png')); ?>"
                     alt="" loading="eager" />
                <div class="hero-mobile-glow"></div>
            </div>
            <!-- Partículas flotantes CSS -->
            <div class="hm-particle hm-p1"></div>
            <div class="hm-particle hm-p2"></div>
            <div class="hm-particle hm-p3"></div>
            <div class="hm-particle hm-p4"></div>
            <div class="hm-particle hm-p5"></div>
            <div class="hm-particle hm-p6"></div>
        </div>

        <div class="container">
            <div class="hero-content fade-in-up">
                <h1 class="hero-title">
                    <?php echo esc_html(get_theme_mod('hero_title', 'Automatiza Tech')); ?>
                </h1>
                <p class="hero-subtitle">
                    <?php echo esc_html(get_theme_mod('hero_subtitle', 'Conectamos tus ventas, web y CRM.')); ?>
                </p>
                <p class="hero-tagline">
                    <?php echo esc_html(get_theme_mod('hero_tagline', 'Automatización y tecnología digital premium para hacer crecer tu negocio.')); ?>
                </p>
                <div class="hero-cta">
                    <!-- Demo Button with Robot -->
                    <div class="btn-robot-container">
                        <div class="robot-peek-btn demo-robot">
                            <div class="robot-bot">🤖</div>
                            <div class="chat-bubble">
                                <span class="chat-text">¡Prueba gratis! 🎯</span>
                                <div class="bubble-tail"></div>
                            </div>
                        </div>
                        <a href="#" class="btn btn-secondary demo-btn" onclick="event.preventDefault(); openAgendaModal();">Solicita tu Demo</a>
                    </div>

                    <!-- WhatsApp Button with Robot -->
                    <div class="btn-robot-container">
                        <div class="robot-peek-btn whatsapp-robot">
                            <div class="robot-bot">🤖</div>
                            <div class="chat-bubble">
                                <span class="chat-text">¡Hablemos ya! 💬</span>
                                <div class="bubble-tail"></div>
                            </div>
                        </div>
                        <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa conocer más sobre Automatiza Tech')); ?>"
                           class="btn btn-outline whatsapp-btn" target="_blank" rel="noopener">
                            <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section - Dynamic Content -->
    <?php echo render_features_section(); ?>

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

    <!-- Services Section - Dynamic Content -->
    <?php echo render_special_services_section(); ?>

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

    <!-- Pricing Section - Dynamic Content -->
    <?php echo render_pricing_section(); ?>

    <!-- Special Price Section (deshabilitada a solicitud) -->
    <?php /* echo render_special_price_section(); */ ?>

    <!-- Spacer between Pricing and Contact (removido) -->
    <!-- <div class="section-spacer"></div> -->

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="container">
              <div class="row">
                  <div class="col-lg-8 mx-auto">
                      <h2 class="section-title text-white text-center contact-title-white">¿Listo para automatizar tu negocio?</h2>
                      <p class="text-center text-white mb-5">Completa el formulario y uno de nuestros expertos te contactará en menos de 24 horas</p>
                      <?php echo do_shortcode('[contact_form]'); ?>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Mensajes para el botón de Demo
const demoMessages = [
    "¡Prueba gratis! 🎯",
    "¡Demo sin costo! 💎",
    "¡Ve la magia! ✨",
    "¡Descubre el poder! 🚀",
    "¡Solicita ya! ⚡"
];

// Mensajes para el botón de WhatsApp
const whatsappMessages = [
    "¡Hablemos ya! 💬",
    "¡Contacta ahora! 📞",
    "¡Estoy aquí! 🤖",
    "¡Chatea conmigo! 💭",
    "¡Te respondo ya! ⚡"
];

let demoIndex = 0;
let whatsappIndex = 0;

function rotateDemoMessage() {
    const demoElement = document.querySelector('.demo-robot .chat-text');
    if (demoElement) {
        demoElement.style.opacity = '0.7';
        setTimeout(() => {
            demoElement.textContent = demoMessages[demoIndex];
            demoElement.style.opacity = '1';
            demoIndex = (demoIndex + 1) % demoMessages.length;
        }, 200);
    }
}

function rotateWhatsAppMessage() {
    const whatsappElement = document.querySelector('.whatsapp-robot .chat-text');
    if (whatsappElement) {
        whatsappElement.style.opacity = '0.7';
        setTimeout(() => {
            whatsappElement.textContent = whatsappMessages[whatsappIndex];
            whatsappElement.style.opacity = '1';
            whatsappIndex = (whatsappIndex + 1) % whatsappMessages.length;
        }, 200);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Robots de los botones del hero
    // Rotar mensajes del demo cada 8 segundos
    setInterval(rotateDemoMessage, 8000);
    
    // Rotar mensajes del WhatsApp cada 7 segundos (offset)
    setTimeout(() => {
        setInterval(rotateWhatsAppMessage, 7000);
    }, 3500);
    
    // Hover effects para botones del hero
    const demoBtnContainer = document.querySelector('.btn-robot-container:has(.demo-btn)');
    const whatsappBtnContainer = document.querySelector('.btn-robot-container:has(.whatsapp-btn)');
    
    if (demoBtnContainer) {
        const demoBtn = demoBtnContainer.querySelector('.demo-btn');
        const demoRobotText = demoBtnContainer.querySelector('.demo-robot .chat-text');
        
        demoBtnContainer.addEventListener('mouseenter', () => {
            if (demoRobotText) {
                demoRobotText.textContent = "¡Perfecto! ¡Solicita! 🎯";
            }
        });
        
        demoBtnContainer.addEventListener('mouseleave', () => {
            if (demoRobotText) {
                setTimeout(() => {
                    demoRobotText.textContent = demoMessages[demoIndex % demoMessages.length];
                }, 1000);
            }
        });
    }
    
    if (whatsappBtnContainer) {
        const whatsappBtnHero = whatsappBtnContainer.querySelector('.whatsapp-btn');
        const whatsappRobotText = whatsappBtnContainer.querySelector('.whatsapp-robot .chat-text');
        
        whatsappBtnContainer.addEventListener('mouseenter', () => {
            if (whatsappRobotText) {
                whatsappRobotText.textContent = "¡Genial! ¡Chateemos! 💬";
            }
        });
        
        whatsappBtnContainer.addEventListener('mouseleave', () => {
            if (whatsappRobotText) {
                setTimeout(() => {
                    whatsappRobotText.textContent = whatsappMessages[whatsappIndex % whatsappMessages.length];
                }, 1000);
            }
        });
    }
});

// Limpiar interval al salir de la página
window.addEventListener('beforeunload', () => {
    if (messageInterval) {
        clearInterval(messageInterval);
    }
});

/* =====================================================
   HERO MOBILE — Canvas de partículas animadas
   Solo se activa en pantallas <= 768px
   ===================================================== */
(function() {
    function isMobile() { return window.innerWidth <= 768; }
    if (!isMobile()) return;

    const canvas  = document.getElementById('heroMobileCanvas');
    if (!canvas) return;
    const ctx     = canvas.getContext('2d');
    let W, H, particles = [], animFrame;

    function resize() {
        const bg = canvas.parentElement;
        W = canvas.width  = bg.offsetWidth  || window.innerWidth;
        H = canvas.height = bg.offsetHeight || 420;
    }

    // Crear partículas
    function createParticles() {
        particles = [];
        const count = Math.floor(W / 14);
        for (let i = 0; i < count; i++) {
            particles.push({
                x:    Math.random() * W,
                y:    Math.random() * H,
                r:    Math.random() * 2.2 + 0.6,
                dx:   (Math.random() - 0.5) * 0.45,
                dy:   -Math.random() * 0.6 - 0.2,
                a:    Math.random() * 0.7 + 0.15,
                hue:  Math.random() > 0.6 ? 170 : 215  // teal o azul
            });
        }
    }

    // Dibujar líneas entre partículas cercanas
    function drawConnections() {
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dx  = particles[i].x - particles[j].x;
                const dy  = particles[i].y - particles[j].y;
                const dist = Math.sqrt(dx*dx + dy*dy);
                if (dist < 80) {
                    ctx.beginPath();
                    ctx.strokeStyle = `rgba(6,214,160,${0.12 * (1 - dist/80)})`;
                    ctx.lineWidth = 0.6;
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.stroke();
                }
            }
        }
    }

    function drawGrid() {
        ctx.strokeStyle = 'rgba(6,214,160,0.045)';
        ctx.lineWidth   = 0.5;
        const step = 38;
        for (let x = 0; x < W; x += step) {
            ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke();
        }
        for (let y = 0; y < H; y += step) {
            ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
        }
    }

    function tick() {
        ctx.clearRect(0, 0, W, H);

        // Fondo gradiente (canvas)
        const grad = ctx.createLinearGradient(0, 0, W, H);
        grad.addColorStop(0,   '#071424');
        grad.addColorStop(0.5, '#0a1f38');
        grad.addColorStop(1,   '#062a2a');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, W, H);

        drawGrid();
        drawConnections();

        // Partículas
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `hsla(${p.hue},90%,65%,${p.a})`;
            ctx.fill();

            p.x += p.dx;
            p.y += p.dy;
            if (p.y < -4)  { p.y = H + 4; p.x = Math.random() * W; }
            if (p.x < -4)  { p.x = W + 4; }
            if (p.x > W+4) { p.x = -4; }
        });

        animFrame = requestAnimationFrame(tick);
    }

    function init() {
        resize();
        createParticles();
        tick();
    }

    window.addEventListener('resize', () => {
        if (!isMobile()) { cancelAnimationFrame(animFrame); return; }
        resize();
        createParticles();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

<?php get_footer(); ?>