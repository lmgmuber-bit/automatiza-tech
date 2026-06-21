    <footer id="colophon" class="site-footer">
        <div class="container">
            <div class="footer-content">
                <!-- Company Info -->
                <div class="footer-section">
                    <h3>Automatiza Tech</h3>
                    <p>Automatización y tecnología digital premium para hacer crecer tu negocio: asistentes inteligentes, automatización, sitios premium, apps y sistemas a medida.</p>
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
                        <li><a href="#beneficios">Asistentes Inteligentes</a></li>
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
                            Atención 24/7 con asistentes inteligentes
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
    "description": "Automatización y tecnología digital premium para negocios: asistentes inteligentes, automatización, sitios web premium, apps y sistemas a medida.",
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
                    "name": "Asistentes Inteligentes",
                    "description": "Atención y ventas automatizadas 24/7 en WhatsApp, Instagram y web"
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

<!-- =============================================
     MODAL PROMOCIONAL — Imagen Planes
     Se muestra una vez por sesión al cargar el home
============================================= -->
<div id="promo-modal" style="
    display:none;
    position:fixed;
    inset:0;
    z-index:99999;
    background:rgba(5,15,35,0.88);
    backdrop-filter:blur(6px);
    align-items:center;
    justify-content:center;
    animation:promoFadeIn 0.35s ease;
">
    <div style="
        position:relative;
        width:min(820px,95vw);
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 0 60px rgba(6,214,160,0.3),0 30px 80px rgba(0,0,0,0.7);
        border:1px solid rgba(6,214,160,0.25);
        background:#0a1628;
    ">
        <!-- Botón cerrar — z-index 999 para estar siempre por encima del badge -->
        <button onclick="closePromoModal()" style="
            position:absolute;top:8px;right:8px;
            background:rgba(10,22,40,0.9);color:#fff;
            border:2px solid #fff;border-radius:50%;
            width:38px;height:38px;font-size:18px;font-weight:900;cursor:pointer;
            z-index:999;display:flex;align-items:center;justify-content:center;
            transition:background 0.2s,transform 0.15s;
            box-shadow:0 2px 10px rgba(0,0,0,0.8);
            line-height:1;flex-shrink:0;
        " onmouseover="this.style.background='#06d6a0';this.style.borderColor='#06d6a0';this.style.transform='scale(1.1)'"
           onmouseout="this.style.background='rgba(10,22,40,0.9)';this.style.borderColor='#fff';this.style.transform='scale(1)'"
           aria-label="Cerrar">&#x2715;</button>

        <!-- Badge superior -->
        <div style="
            position:absolute;top:14px;left:50%;transform:translateX(-50%);
            background:linear-gradient(135deg,#ff6b35,#ff4500);
            color:#fff;font-size:12px;font-weight:800;
            padding:5px 18px;border-radius:50px;letter-spacing:1px;
            z-index:10;box-shadow:0 4px 14px rgba(255,107,53,0.5);white-space:nowrap;
        ">🎁 PROMOCIÓN ESPECIAL — 1 MES GRATIS</div>

        <!-- Imagen única: planes -->
        <div style="position:relative;cursor:pointer;" onclick="openAgendaModal()">
            <img src="<?php echo home_url(); ?>/promo-assets/gemini-04-planes3.png"
                 alt="Planes AutomatizaTech — Básico $99, Profesional $199, Enterprise $399"
                 style="width:100%;max-height:480px;object-fit:cover;display:block;">
            <!-- Overlay hover -->
            <div class="promo-img-overlay" style="
                position:absolute;inset:0;
                background:rgba(6,214,160,0.08);
                display:flex;align-items:flex-end;justify-content:center;
                padding-bottom:24px;opacity:0;transition:opacity 0.3s;
            ">
                <span style="
                    background:linear-gradient(135deg,#06d6a0,#10b981);
                    color:#0a1628;font-size:16px;font-weight:800;
                    padding:12px 36px;border-radius:50px;
                    box-shadow:0 4px 20px rgba(6,214,160,0.5);
                ">📅 Agendar Demo Gratuita →</span>
            </div>
        </div>

        <!-- Footer -->
        <div style="
            padding:14px 28px;
            display:flex;align-items:center;justify-content:space-between;
            background:linear-gradient(135deg,rgba(6,214,160,0.07),rgba(10,22,40,1));
            border-top:1px solid rgba(6,214,160,0.15);
        ">
            <span style="color:rgba(255,255,255,0.5);font-size:12px;">
                Solo por tiempo limitado • automatizatech.cl
            </span>
            <button onclick="openAgendaModal()" style="
                background:linear-gradient(135deg,#06d6a0,#10b981);
                color:#0a1628;font-weight:800;font-size:14px;
                border:none;border-radius:50px;padding:10px 28px;
                cursor:pointer;box-shadow:0 4px 18px rgba(6,214,160,0.4);
                transition:transform 0.2s,box-shadow 0.2s;letter-spacing:0.3px;
            " onmouseover="this.style.transform='scale(1.04)'"
               onmouseout="this.style.transform='scale(1)'">
                📅 Agendar Demo Gratis
            </button>
        </div>
    </div>
</div>

<!-- =============================================
     MODAL AGENDAMIENTO (se abre desde promo-modal)
============================================= -->
<div id="agenda-modal" style="
    display:none;
    position:fixed;inset:0;
    z-index:100000;
    background:rgba(5,15,35,0.92);
    backdrop-filter:blur(8px);
    align-items:center;justify-content:center;
    animation:promoFadeIn 0.3s ease;
">
    <div style="
        position:relative;
        width:min(480px,95vw);
        border-radius:18px;
        background:linear-gradient(160deg,#0d2044,#0a1628);
        border:1px solid rgba(6,214,160,0.3);
        box-shadow:0 0 50px rgba(6,214,160,0.2),0 30px 70px rgba(0,0,0,0.8);
        overflow:hidden;
    ">
        <!-- Header -->
        <div style="
            background:linear-gradient(135deg,#06d6a0,#10b981);
            padding:22px 28px 18px;
            display:flex;align-items:center;gap:14px;
        ">
            <span style="font-size:32px;">📅</span>
            <div>
                <h3 style="margin:0;color:#0a1628;font-size:18px;font-weight:800;">Agendar Demo Gratuita</h3>
                <p style="margin:0;color:rgba(10,22,40,0.75);font-size:13px;">Selecciona fecha y hora para tu reunión</p>
            </div>
            <button onclick="closeAgendaModal()" style="
                margin-left:auto;background:rgba(10,22,40,0.2);color:#0a1628;
                border:none;border-radius:50%;width:34px;height:34px;
                font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;
            ">&times;</button>
        </div>

        <!-- Formulario -->
        <div style="padding:20px 22px;box-sizing:border-box;width:100%;overflow:hidden;">
            <form id="agenda-modal-form" onsubmit="submitAgendaForm(event)" style="width:100%;box-sizing:border-box;">
                <div style="margin-bottom:14px;">
                    <input type="text" name="name" placeholder="Tu nombre completo" required minlength="2" maxlength="60"
                           style="width:100%;padding:11px 14px;border-radius:8px;border:1px solid rgba(6,214,160,0.3);background:rgba(255,255,255,0.05);color:#fff;font-size:14px;box-sizing:border-box;outline:none;"
                           onfocus="this.style.borderColor='#06d6a0'" onblur="this.style.borderColor='rgba(6,214,160,0.3)'">
                </div>
                <div style="margin-bottom:14px;">
                    <input type="email" name="email" placeholder="Tu correo electrónico" required maxlength="80"
                           style="width:100%;padding:11px 14px;border-radius:8px;border:1px solid rgba(6,214,160,0.3);background:rgba(255,255,255,0.05);color:#fff;font-size:14px;box-sizing:border-box;outline:none;"
                           onfocus="this.style.borderColor='#06d6a0'" onblur="this.style.borderColor='rgba(6,214,160,0.3)'">
                </div>
                <div style="display:flex;gap:8px;margin-bottom:14px;width:100%;box-sizing:border-box;overflow:hidden;">
                    <select name="country_code" style="flex:0 0 auto;width:135px;min-width:0;padding:11px 8px;border-radius:8px;border:1px solid rgba(6,214,160,0.3);background:#0d2044;color:#fff;font-size:13px;outline:none;box-sizing:border-box;">
                        <option value="+56" selected>🇨🇱 Chile (+56)</option>
                        <option value="+54">🇦🇷 Argentina (+54)</option>
                        <option value="+57">🇨🇴 Colombia (+57)</option>
                        <option value="+52">🇲🇽 México (+52)</option>
                        <option value="+51">🇵🇪 Perú (+51)</option>
                        <option value="+593">🇪🇨 Ecuador (+593)</option>
                        <option value="+598">🇺🇾 Uruguay (+598)</option>
                        <option value="+58">🇻🇪 Venezuela (+58)</option>
                        <option value="+1">🇺🇸 USA/CA (+1)</option>
                        <option value="+34">🇪🇸 España (+34)</option>
                    </select>
                    <input type="tel" name="phone" placeholder="912345678" required minlength="8" maxlength="15"
                           style="flex:1 1 0;min-width:0;padding:11px 12px;border-radius:8px;border:1px solid rgba(6,214,160,0.3);background:rgba(255,255,255,0.05);color:#fff;font-size:14px;box-sizing:border-box;outline:none;width:100%;"
                           onfocus="this.style.borderColor='#06d6a0'" onblur="this.style.borderColor='rgba(6,214,160,0.3)'">
                </div>
                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <div style="flex:1;">
                        <label style="color:rgba(255,255,255,0.6);font-size:12px;display:block;margin-bottom:5px;">Fecha deseada</label>
                        <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>"
                               style="width:100%;padding:11px 12px;border-radius:8px;border:1px solid rgba(6,214,160,0.3);background:rgba(255,255,255,0.05);color:#fff;font-size:13px;box-sizing:border-box;outline:none;color-scheme:dark;"
                               onfocus="this.style.borderColor='#06d6a0'" onblur="this.style.borderColor='rgba(6,214,160,0.3)'">
                    </div>
                    <div style="flex:1;">
                        <label style="color:rgba(255,255,255,0.6);font-size:12px;display:block;margin-bottom:5px;">Hora preferida</label>
                        <select name="time" required disabled style="width:100%;padding:11px 12px;border-radius:8px;border:1px solid rgba(6,214,160,0.3);background:#0d2044;color:#fff;font-size:13px;outline:none;box-sizing:border-box;">
                            <option value="" selected>-- Selecciona fecha primero --</option>
                        </select>
                    </div>
                </div>
                <div id="agenda-msg" style="display:none;text-align:center;padding:8px;border-radius:8px;margin-bottom:12px;font-size:13px;"></div>
                <button type="submit" id="agenda-submit-btn" style="
                    width:100%;padding:13px;
                    background:linear-gradient(135deg,#06d6a0,#10b981);
                    color:#0a1628;font-weight:800;font-size:15px;
                    border:none;border-radius:10px;cursor:pointer;
                    transition:opacity 0.2s;letter-spacing:0.3px;
                ">Confirmar Agendamiento →</button>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes promoFadeIn { from{opacity:0;transform:scale(0.96)} to{opacity:1;transform:scale(1)} }
.promo-img-overlay:hover, div:hover > .promo-img-overlay { opacity:1 !important; }
</style>

<script>
(function(){
    var isHome = <?php echo (is_front_page() || is_home()) ? 'true' : 'false'; ?>;

    // ── Funciones globales — siempre disponibles (botón hero, promo-modal, etc.) ──
    window.closePromoModal = function(){
        var m = document.getElementById('promo-modal');
        if (m){ m.style.opacity='0'; setTimeout(function(){ m.style.display='none'; m.style.opacity=''; },300); }
        // sessionStorage eliminado — ahora usa contador localStorage (máx 25)
    };

    window.openAgendaModal = function(){
        closePromoModal();
        setTimeout(function(){
            var a = document.getElementById('agenda-modal');
            if (a) {
                a.style.display = 'flex';
                if (agendaDateInput && agendaDateInput.value) {
                    loadAgendaAvailability(agendaDateInput.value);
                } else {
                    resetAgendaTimeSelect('-- Selecciona fecha primero --', true);
                }
            }
        }, 350);
    };

    window.closeAgendaModal = function(){
        var a = document.getElementById('agenda-modal');
        agendaIsSubmitting = false;
        if (a){ a.style.opacity='0'; setTimeout(function(){ a.style.display='none'; a.style.opacity=''; },300); }
    };

    var agendaForm = document.getElementById('agenda-modal-form');
    var agendaDateInput = agendaForm ? agendaForm.querySelector('input[name="date"]') : null;
    var agendaTimeSelect = agendaForm ? agendaForm.querySelector('select[name="time"]') : null;
    var agendaAvailabilityUrl = '<?php echo esc_url(rest_url("automatiza-tech/v1/check-availability")); ?>';
    var agendaIsSubmitting = false;

    function resetAgendaTimeSelect(placeholder, disabled){
        if (!agendaTimeSelect) return;
        agendaTimeSelect.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder || '-- Selecciona hora --';
        opt.selected = true;
        agendaTimeSelect.appendChild(opt);
        agendaTimeSelect.disabled = !!disabled;
    }

    function toMinutes(hhmm){
        var parts = (hhmm || '').split(':');
        if (parts.length < 2) return null;
        var hh = parseInt(parts[0], 10);
        var mm = parseInt(parts[1], 10);
        if (isNaN(hh) || isNaN(mm)) return null;
        return (hh * 60) + mm;
    }

    function loadAgendaAvailability(dateVal){
        if (!agendaTimeSelect) return;
        if (!dateVal) {
            resetAgendaTimeSelect('-- Selecciona fecha primero --', true);
            return;
        }

        resetAgendaTimeSelect('Verificando disponibilidad...', true);

        fetch(agendaAvailabilityUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date: dateVal })
        })
        .then(function(resp){
            if (!resp.ok) {
                return resp.json().catch(function(){ return {}; }).then(function(payload){
                    throw new Error(payload.message || 'Error de disponibilidad');
                });
            }
            return resp.json();
        })
        .then(function(data){
            if (!data || data.isFullDay) {
                resetAgendaTimeSelect('Día completo / Sin cupos', true);
                return;
            }

            var workingHours = data.workingHours || {};
            var start = (workingHours.start || '').toString();
            var end = (workingHours.end || '').toString();
            var startHour = parseInt(start.split(':')[0], 10);
            var endHour = parseInt(end.split(':')[0], 10);

            if (isNaN(startHour) || isNaN(endHour) || endHour <= startHour) {
                resetAgendaTimeSelect('No hay horarios disponibles', true);
                return;
            }

            var busySlots = Array.isArray(data.busySlots) ? data.busySlots : [];
            var now = new Date();
            var yyyy = now.getFullYear();
            var mm = String(now.getMonth() + 1).padStart(2, '0');
            var dd = String(now.getDate()).padStart(2, '0');
            var todayStr = yyyy + '-' + mm + '-' + dd;
            var nowMinutes = (now.getHours() * 60) + now.getMinutes();

            agendaTimeSelect.innerHTML = '';
            var first = document.createElement('option');
            first.value = '';
            first.textContent = '-- Selecciona hora --';
            first.selected = true;
            agendaTimeSelect.appendChild(first);

            var availableCount = 0;

            for (var h = startHour; h < endHour; h++) {
                var hh = String(h).padStart(2, '0');
                var slot = hh + ':00';

                if (dateVal === todayStr) {
                    var slotMinutes = toMinutes(slot);
                    if (slotMinutes !== null && slotMinutes <= nowMinutes) {
                        continue;
                    }
                }

                if (busySlots.indexOf(slot) !== -1) {
                    continue;
                }

                var option = document.createElement('option');
                option.value = slot;
                option.textContent = slot;
                agendaTimeSelect.appendChild(option);
                availableCount++;
            }

            if (availableCount === 0) {
                resetAgendaTimeSelect('Sin horarios disponibles hoy', true);
                return;
            }

            agendaTimeSelect.disabled = false;
        })
        .catch(function(){
            resetAgendaTimeSelect('Error al verificar disponibilidad', true);
        });
    }

    if (agendaDateInput) {
        agendaDateInput.addEventListener('change', function(){
            loadAgendaAvailability(this.value);
        });
    }

    window.submitAgendaForm = function(e){
        e.preventDefault();
        if (agendaIsSubmitting) {
            return;
        }

        var btn  = document.getElementById('agenda-submit-btn');
        var msg  = document.getElementById('agenda-msg');
        var form = document.getElementById('agenda-modal-form');
        agendaIsSubmitting = true;
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        btn.style.opacity = '0.65';
        btn.style.cursor = 'not-allowed';

        function showMsg(ok, text){
            msg.style.display  = 'block';
            msg.style.background = ok ? 'rgba(6,214,160,0.12)' : 'rgba(255,100,100,0.1)';
            msg.style.color      = ok ? '#06d6a0' : '#ff6b6b';
            msg.style.border     = ok ? '1px solid rgba(6,214,160,0.3)' : '1px solid rgba(255,100,100,0.2)';
            msg.textContent      = text;
        }

        var data  = new FormData(form);
        var date  = data.get('date')  || '';
        var time  = data.get('time')  || '';
        var cc    = data.get('country_code') || '+56';
        var phone = data.get('phone') || '';
        var name  = data.get('name') || '';
        var email = data.get('email') || '';
        var phoneDigits = String(phone || '').replace(/\D+/g, '');

        function unlockAgendaButton(){
            agendaIsSubmitting = false;
            btn.disabled = false;
            btn.textContent = 'Confirmar Agendamiento →';
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        }

        function getAgendaSessionId(){
            try {
                var sessionKey = 'automatiza_chat_session_id';
                var sid = localStorage.getItem(sessionKey);
                if (!sid) {
                    sid = 'session-' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem(sessionKey, sid);
                }
                return sid;
            } catch (_e) {
                return 'session-' + Math.random().toString(36).substr(2, 9);
            }
        }

        function sendCorporateDemoNotification(){
            var notifyData = new FormData();
            notifyData.append('action', 'send_corporate_demo_notification');
            notifyData.append('nonce', '<?php echo wp_create_nonce("automatiza_tech_nonce"); ?>');
            notifyData.append('name', String(name || '').trim());
            notifyData.append('email', String(email || '').trim());
            notifyData.append('phone', String(cc || '+56') + String(phoneDigits || ''));
            notifyData.append('company', 'Demo — Modal Promocional');
            notifyData.append('date', String(date || '').trim());
            notifyData.append('time', String(time || '').trim());

            return fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: notifyData
            })
            .then(function(resp){
                if (!resp.ok) {
                    throw new Error('No se pudo contactar el servicio de notificación corporativa');
                }
                return resp.json();
            })
            .then(function(result){
                if (!result || !result.success) {
                    throw new Error((result && result.data) ? result.data : 'Error al enviar notificación corporativa');
                }
                return result;
            });
        }

        var canUseChatFlow =
            typeof AutomatizaAIChat !== 'undefined' &&
            !!AutomatizaAIChat &&
            !!AutomatizaAIChat.apiUrl &&
            !!AutomatizaAIChat.webhookUrl;

        if (canUseChatFlow) {
            btn.textContent = 'Verificando disponibilidad...';

            fetch(AutomatizaAIChat.apiUrl + 'check-limit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            })
            .then(function(resp){
                if (!resp.ok) {
                    throw new Error('No se pudo verificar límite de agendamientos');
                }
                return resp.json();
            })
            .then(function(limitResponse){
                if (!limitResponse.allowed) {
                    throw new Error(limitResponse.message || 'Límite de agendamientos alcanzado');
                }

                btn.textContent = 'Agendando...';

                var payload = {
                    action: 'saveLead',
                    sessionId: getAgendaSessionId(),
                    leadData: {
                        name: String(name || '').trim(),
                        email: String(email || '').trim(),
                        phone: String(cc || '+56') + String(phoneDigits || ''),
                        scheduled_date: String(date || '').trim(),
                        scheduled_time: String(time || '').trim()
                    }
                };

                return fetch(AutomatizaAIChat.webhookUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            })
            .then(function(resp){
                if (!resp.ok) {
                    throw new Error('Error al conectar con el servidor de agendamiento');
                }
                return resp.text();
            })
            .then(function(responseRaw){
                var successMessage = '¡Cita agendada! Revisa tu correo con el enlace de la demo.';
                var responseText = String(responseRaw || '');
                try {
                    var parsed = JSON.parse(responseRaw);
                    responseText = parsed.text || parsed.output || parsed.message || responseText;
                    successMessage = responseText || successMessage;
                } catch (_e) {}

                var normalizedResponse = String(responseText || '').toLowerCase();
                if (
                    normalizedResponse.indexOf('ocupad') !== -1 ||
                    normalizedResponse.indexOf('no disponible') !== -1 ||
                    normalizedResponse.indexOf('slot_taken') !== -1
                ) {
                    throw new Error('El horario seleccionado ya no está disponible. Elige otro horario.');
                }

                if (
                    normalizedResponse.indexOf('error') !== -1 ||
                    normalizedResponse.indexOf('failed') !== -1 ||
                    normalizedResponse.indexOf('inválid') !== -1 ||
                    normalizedResponse.indexOf('invalid') !== -1
                ) {
                    throw new Error(responseText || 'Error al agendar la cita.');
                }

                return sendCorporateDemoNotification()
                    .catch(function(notificationError){
                        console.warn('No se pudo enviar notificación corporativa:', notificationError);
                    })
                    .then(function(){
                        return successMessage;
                    });
            })
            .then(function(successMessage){
                showMsg(true, '✅ ' + successMessage);
                form.reset();
                resetAgendaTimeSelect('-- Selecciona fecha primero --', true);
                setTimeout(closeAgendaModal, 3500);
            })
            .catch(function(err){
                showMsg(false, '❌ ' + (err && err.message ? err.message : 'Error al agendar la cita.'));
                unlockAgendaButton();
            });

            return;
        }

        // Fallback: handler simple de contacto (si no está disponible el flujo de chatbot/N8N)
        data.append('action',   'contact_form');
        data.append('nonce',    '<?php echo wp_create_nonce("automatiza_tech_nonce"); ?>');
        data.append('company',  'Demo — Modal Promocional');
        data.append('message',  'Solicitud de demo gratuita desde modal promo. Fecha: ' + date + ' Hora: ' + time);
        data.set('phone', cc + phoneDigits);

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method:'POST', body:data })
        .then(function(r){ return r.json(); })
        .then(function(res){
            if(res.success){
                showMsg(true, '✅ ' + (res.data || '¡Solicitud enviada! Te contactaremos en menos de 24 horas.'));
                form.reset();
                resetAgendaTimeSelect('-- Selecciona fecha primero --', true);
                setTimeout(closeAgendaModal, 3500);
            } else {
                showMsg(false, '❌ ' + (res.data || 'Error al enviar. Intenta de nuevo.'));
                unlockAgendaButton();
            }
        })
        .catch(function(){
            showMsg(false, '❌ Error de conexión. Por favor intenta de nuevo.');
            unlockAgendaButton();
        });
    };

    // Cerrar con ESC (siempre activo)
    document.addEventListener('keydown', function(e){
        if(e.key==='Escape'){ closePromoModal(); closeAgendaModal(); }
    });
    // Cerrar al click fuera (siempre activo)
    var pm = document.getElementById('promo-modal');
    var am = document.getElementById('agenda-modal');
    if (pm) pm.addEventListener('click', function(e){ if(e.target===this) closePromoModal(); });
    if (am) am.addEventListener('click', function(e){ if(e.target===this) closeAgendaModal(); });

    // Mostrar promo-modal en home hasta un máximo de 25 veces (contador en localStorage)
    if (isHome) {
        var PROMO_MAX  = 25;
        var promoCount = parseInt(localStorage.getItem('at_promo_count') || '0', 10);
        if (promoCount < PROMO_MAX) {
            setTimeout(function(){
                var m = document.getElementById('promo-modal');
                if (m) {
                    m.style.display = 'flex';
                    localStorage.setItem('at_promo_count', promoCount + 1);
                }
            }, 2000);
        }
    }
})();
</script>


<?php wp_footer(); ?>

<!-- Performance monitoring script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Detectar #AgendarDemo en la URL y scrollear al formulario
    if (window.location.hash === '#AgendarDemo' || window.location.hash === '#agendardemo') {
        var contactSection = document.getElementById('contact');
        if (contactSection) {
            setTimeout(function(){ contactSection.scrollIntoView({behavior:'smooth'}); }, 400);
        }
        if (history.replaceState) {
            history.replaceState(null, null, window.location.pathname + window.location.search);
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