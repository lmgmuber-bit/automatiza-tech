    <footer id="colophon" class="site-footer">
        <div class="container">
            <div class="footer-content">
                <!-- Company Info -->
                <div class="footer-section">
                    <h3>Automatiza Tech</h3>
                    <p>Conectamos tus ventas, web y CRM con bots inteligentes para negocios que no se detienen.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/automatizatech" target="_blank" rel="noopener" title="Facebook">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="https://www.instagram.com/automatizatech" target="_blank" rel="noopener" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://www.linkedin.com/company/automatizatech" target="_blank" rel="noopener" title="LinkedIn">
                            <i class="fab fa-linkedin"></i>
                        </a>
                        <a href="https://www.twitter.com/automatizatech" target="_blank" rel="noopener" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Services -->
                <div class="footer-section">
                    <h3>Servicios</h3>
                    <ul>
                        <li><a href="#beneficios">Chatbots Inteligentes</a></li>
                        <li><a href="#integraciones">Integración WhatsApp</a></li>
                        <li><a href="#integraciones">Automatización Instagram</a></li>
                        <li><a href="#integraciones">CRM Integration</a></li>
                        <li><a href="#planes">Consultoría Personalizada</a></li>
                    </ul>
                </div>

                <!-- Industries -->
                <div class="footer-section">
                    <h3>Industrias</h3>
                    <ul>
                        <li><a href="#industrias">E-commerce</a></li>
                        <li><a href="#industrias">Salud</a></li>
                        <li><a href="#industrias">Educación</a></li>
                        <li><a href="#industrias">Restaurantes</a></li>
                        <li><a href="#industrias">Inmobiliaria</a></li>
                        <li><a href="#industrias">Servicios</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="footer-section">
                    <h3>Contacto</h3>
                    <ul>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:info@automatizatech.com">info@automatizatech.com</a>
                        </li>
                        <li>
                            <i class="fab fa-whatsapp"></i>
                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa conocer más sobre Automatiza Tech')); ?>" target="_blank">
                                <?php echo esc_html(get_theme_mod('whatsapp_number', '+1 (234) 567-890')); ?>
                            </a>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            Atención 24/7 con nuestros bots
                        </li>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            Disponible en toda Latinoamérica
                        </li>
                    </ul>
                    
                    <!-- CTA Button -->
                    <div class="footer-cta mt-3">
                        <a href="<?php echo esc_url(get_whatsapp_url('Hola! Quiero solicitar una demo de Automatiza Tech')); ?>" 
                           class="btn btn-secondary" target="_blank">
                            <i class="fab fa-whatsapp"></i> Solicita tu Demo
                        </a>
                    </div>
                </div>
            </div><!-- .footer-content -->

            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p>&copy; <?php echo date('Y'); ?> Automatiza Tech. Todos los derechos reservados.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="footer-links">
                            <a href="/privacy-policy">Política de Privacidad</a>
                            <span class="separator">|</span>
                            <a href="/terms-of-service">Términos de Servicio</a>
                            <span class="separator">|</span>
                            <a href="/cookies-policy">Política de Cookies</a>
                        </div>
                    </div>
                </div>
            </div><!-- .footer-bottom -->
        </div><!-- .container -->
    </footer><!-- #colophon -->

</div><!-- #page -->

<!-- Back to Top Button -->
<button id="back-to-top" class="back-to-top" title="Volver arriba">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Cookie Notice -->
<div id="cookie-notice" class="cookie-notice" style="display: none;">
    <div class="container">
        <div class="cookie-content">
            <p>Utilizamos cookies para mejorar tu experiencia en nuestro sitio web. Al continuar navegando, aceptas nuestro uso de cookies.</p>
            <div class="cookie-buttons">
                <button id="accept-cookies" class="btn btn-primary btn-sm">Aceptar</button>
                <a href="/cookies-policy" class="btn btn-outline btn-sm">Más información</a>
            </div>
        </div>
    </div>
</div>

<!-- Schema.org Local Business structured data -->
<?php if (is_front_page()): ?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "Automatiza Tech",
    "description": "Conectamos tus ventas, web y CRM. Bots inteligentes para negocios que no se detienen.",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+1234567890')); ?>",
    "email": "info@automatizatech.com",
    "address": {
        "@type": "PostalAddress",
        "addressRegion": "Latinoamérica"
    },
    "openingHours": "Mo-Su 00:00-24:00",
    "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Servicios de Automatización",
        "itemListElement": [
            {
                "@type": "Offer",
                "itemOffered": {
                    "@type": "Service",
                    "name": "Chatbots Inteligentes",
                    "description": "Automatización de atención al cliente 24/7"
                }
            },
            {
                "@type": "Offer",
                "itemOffered": {
                    "@type": "Service",
                    "name": "Integración WhatsApp",
                    "description": "Automatización de conversaciones en WhatsApp Business"
                }
            },
            {
                "@type": "Offer",
                "itemOffered": {
                    "@type": "Service",
                    "name": "CRM Integration",
                    "description": "Sincronización con sistemas CRM existentes"
                }
            }
        ]
    }
}
</script>
<?php endif; ?>

<?php wp_footer(); ?>

<!-- Performance monitoring script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Back to top button
    const backToTopButton = document.getElementById('back-to-top');
    if (backToTopButton) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        });

        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Cookie notice
    const cookieNotice = document.getElementById('cookie-notice');
    const acceptCookies = document.getElementById('accept-cookies');
    
    if (cookieNotice && !localStorage.getItem('cookies-accepted')) {
        cookieNotice.style.display = 'block';
    }
    
    if (acceptCookies) {
        acceptCookies.addEventListener('click', function() {
            localStorage.setItem('cookies-accepted', 'true');
            cookieNotice.style.display = 'none';
        });
    }

    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements for animation
    document.querySelectorAll('.feature-card, .integration-item, .industry-card, .pricing-card').forEach(el => {
        observer.observe(el);
    });
});

// Page load performance tracking
window.addEventListener('load', function() {
    if ('performance' in window) {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        if (loadTime > 3000) {
            console.warn('Page load time exceeded 3 seconds:', loadTime + 'ms');
        }
    }
});
</script>

</body>
</html>