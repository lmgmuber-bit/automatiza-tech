    <footer id="colophon" class="site-footer">
        <div class="container">
            <div class="footer-content">
                <!-- Company Info -->
                <div class="footer-section">
                    <h3>Automatiza Tech</h3>
                    <p>Conectamos tus ventas, web y CRM con bots inteligentes para negocios que no se detienen.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/automatizatech.cl" target="_blank" rel="noopener" title="Facebook">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="https://www.instagram.com/automatizatech.cl/" target="_blank" rel="noopener" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://www.linkedin.com/company/automatizatech" target="_blank" rel="noopener" title="LinkedIn">
                            <i class="fab fa-linkedin"></i>
                        </a>
                        <a href="https://www.twitter.com/automatizatech.cl" target="_blank" rel="noopener" title="Twitter">
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
                            <a href="mailto:contacto@automatizatech.cl">contacto@automatizatech.cl</a>
                        </li>
                        <li>
                            <i class="fab fa-whatsapp"></i>
                            <a href="<?php echo esc_url(get_whatsapp_url('Hola! Me interesa conocer más sobre Automatiza Tech')); ?>" target="_blank">
                                <?php echo esc_html(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>
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

            <!-- Logo centrado entre el contenido y el bar inferior -->
            <?php
            $theme_dir = get_template_directory();
            $theme_uri = get_template_directory_uri();
            $svg_rel   = '/assets/images/Logo-slogan-tagline.svg';
            $png_rel   = '/assets/images/Logo-slogan-tagline-2x.png';
            $mid_logo_uri = file_exists($theme_dir . $svg_rel) ? ($theme_uri . $svg_rel) : ($theme_uri . $png_rel);
            ?>
            <div class="footer-mid-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="footer-mid-logo-link" rel="home">
                    <img src="<?php echo esc_url($mid_logo_uri); ?>"
                         alt="<?php echo esc_attr(get_bloginfo('name')); ?>"
                         class="footer-mid-logo-img"
                         loading="lazy" />
                </a>
            </div>

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
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "email": "contacto@automatizatech.cl",
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

<!-- Demo Modal -->
<div id="demo-modal" class="demo-modal">
    <div class="demo-modal-content">
        <span class="close-demo-modal">&times;</span>
        <div class="demo-modal-header">
            <h3>Agendar Demo</h3>
            <p>Selecciona una fecha y hora para tu reunión</p>
        </div>
        <div class="demo-modal-body">
            <form id="demo-modal-form">
                <div class="form-group">
                    <input type="text" name="name" placeholder="Tu Nombre" required minlength="2" maxlength="30">
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Tu Correo" required maxlength="50">
                </div>
                <div class="form-group">
                    <label>País:</label>
                    <select name="country_code" class="form-control">
                        <optgroup label="América del Sur">
                            <option value="+54">🇦🇷 Argentina (+54)</option>
                            <option value="+591">🇧🇴 Bolivia (+591)</option>
                            <option value="+55">🇧🇷 Brasil (+55)</option>
                            <option value="+56" selected>🇨🇱 Chile (+56)</option>
                            <option value="+57">🇨🇴 Colombia (+57)</option>
                            <option value="+593">🇪🇨 Ecuador (+593)</option>
                            <option value="+595">🇵🇾 Paraguay (+595)</option>
                            <option value="+51">🇵🇪 Perú (+51)</option>
                            <option value="+598">🇺🇾 Uruguay (+598)</option>
                            <option value="+58">🇻🇪 Venezuela (+58)</option>
                        </optgroup>
                        <optgroup label="América Central">
                            <option value="+506">🇨🇷 Costa Rica (+506)</option>
                            <option value="+503">🇸🇻 El Salvador (+503)</option>
                            <option value="+502">🇬🇹 Guatemala (+502)</option>
                            <option value="+504">🇭🇳 Honduras (+504)</option>
                            <option value="+52">🇲🇽 México (+52)</option>
                            <option value="+505">🇳🇮 Nicaragua (+505)</option>
                            <option value="+507">🇵🇦 Panamá (+507)</option>
                        </optgroup>
                        <optgroup label="Caribe">
                            <option value="+53">🇨🇺 Cuba (+53)</option>
                            <option value="+1809">🇩🇴 Rep. Dominicana (+1809)</option>
                            <option value="+1787">🇵🇷 Puerto Rico (+1787)</option>
                        </optgroup>
                        <optgroup label="Otros">
                            <option value="+1">🇺🇸 USA/Canadá (+1)</option>
                            <option value="+34">🇪🇸 España (+34)</option>
                            <option value="+351">🇵🇹 Portugal (+351)</option>
                            <option value="+44">🇬🇧 Reino Unido (+44)</option>
                            <option value="+33">🇫🇷 Francia (+33)</option>
                        </optgroup>
                    </select>
                </div>
                <div class="form-group">
                    <label>Teléfono:</label>
                    <input type="tel" name="phone" placeholder="912345678" required minlength="8" maxlength="15">
                </div>
                
                <div class="form-group">
                    <label>Fecha deseada:</label>
                    <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Hora:</label>
                    <select name="time" required disabled>
                        <option value="">Selecciona una fecha primero</option>
                    </select>
                </div>

                <div class="error-msg" style="display:none; color: #dc3545; margin-bottom: 10px; text-align: center;"></div>
                <div class="success-msg" style="display:none; color: #06d6a0; margin-bottom: 10px; text-align: center;"></div>

                <button type="submit" class="submit-demo-modal-btn btn btn-primary w-100">Agendar Reunión</button>
            </form>
        </div>
    </div>
</div>

<!-- Validación de teléfono para el modal de demo (misma lógica que formulario de contacto) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modalPhoneInput = document.querySelector('#demo-modal-form input[name="phone"]');
    var modalCountrySelect = document.querySelector('#demo-modal-form select[name="country_code"]');
    
    if (modalPhoneInput && modalCountrySelect) {
        // Bloquear letras, solo permitir números
        modalPhoneInput.addEventListener('keypress', function(e) {
            var char = String.fromCharCode(e.which);
            // Chile: primer dígito debe ser 9
            if (modalCountrySelect.value === '+56' && this.value.length === 0 && char !== '9') {
                e.preventDefault();
                return;
            }
            // Solo números
            if (!/[0-9]/.test(char)) {
                e.preventDefault();
            }
        });
        
        // Limpiar al pegar
        modalPhoneInput.addEventListener('paste', function(e) {
            var self = this;
            setTimeout(function() {
                var cleanValue = self.value.replace(/[^0-9]/g, '');
                if (modalCountrySelect.value === '+56' && cleanValue.length > 0 && cleanValue[0] !== '9') {
                    self.value = '';
                    return;
                }
                self.value = cleanValue;
            }, 0);
        });
        
        // Validación en tiempo real
        modalPhoneInput.addEventListener('input', function() {
            var cleanValue = this.value.replace(/[^0-9]/g, '');
            if (modalCountrySelect.value === '+56' && cleanValue.length > 0 && cleanValue[0] !== '9') {
                this.value = '';
                return;
            }
            this.value = cleanValue;
        });
        
        // Función para ajustar límites del teléfono según país
        function updateModalPhoneLimits() {
            if (modalCountrySelect.value === '+56') {
                // Chile: exactamente 9 dígitos
                modalPhoneInput.setAttribute('minlength', '9');
                modalPhoneInput.setAttribute('maxlength', '9');
                modalPhoneInput.setAttribute('placeholder', '912345678');
            } else {
                // Otros países: 8-15 dígitos
                modalPhoneInput.setAttribute('minlength', '8');
                modalPhoneInput.setAttribute('maxlength', '15');
                modalPhoneInput.setAttribute('placeholder', 'Número de teléfono');
            }
        }
        
        // Aplicar límites al cargar
        updateModalPhoneLimits();
        
        // Al cambiar país, limpiar si no cumple y actualizar límites
        modalCountrySelect.addEventListener('change', function() {
            var phone = modalPhoneInput.value;
            if (this.value === '+56' && phone.length > 0 && phone[0] !== '9') {
                modalPhoneInput.value = '';
            }
            updateModalPhoneLimits();
        });
    }
});
</script>

<?php wp_footer(); ?>

<!-- Performance monitoring script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Detectar #AgendarDemo en la URL y abrir el modal de demo
    if (window.location.hash === '#AgendarDemo' || window.location.hash === '#agendardemo') {
        var demoModal = document.getElementById('demo-modal');
        if (demoModal) {
            demoModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Limpiar el hash de la URL sin recargar
            if (history.replaceState) {
                history.replaceState(null, null, window.location.pathname + window.location.search);
            }
        }
    }

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