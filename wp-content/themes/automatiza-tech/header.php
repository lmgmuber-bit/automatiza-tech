<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- SEO Meta Tags -->
    <?php if (is_front_page()): ?>
    <meta name="description" content="Automatiza Tech - Conectamos tus ventas, web y CRM. Bots inteligentes para negocios que no se detienen.">
    <meta name="keywords" content="automatización, chatbots, CRM, ventas, WhatsApp, Instagram, atención al cliente, bots inteligentes">
    <meta name="author" content="Automatiza Tech">
    <?php endif; ?>

    <!-- Font Awesome -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17691911857"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'AW-17691911857');
    </script>

    <!-- Event snippet for Compra conversion page -->
    <script>
      gtag('event', 'conversion', {
          'send_to': 'AW-17691911857/AmZuCJaF-7YbELHNlPRB',
          'transaction_id': ''
      });
    </script>

    <?php wp_head(); ?>

    <!-- Critical CSS inline -->
    <style>
        .site-header {
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        /* Critical Hero and Button Styles */
        .hero-section {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 6rem 0;
            text-align: center;
            margin-top: 60px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #ffffff;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #06d6a0;
            font-weight: 600;
        }

        .hero-tagline {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            color: #ffffff;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-width: 200px;
            font-size: 1rem;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .btn-secondary {
            background-color: #06d6a0;
            color: #ffffff;
            border-color: #06d6a0;
        }

        .btn-secondary:hover {
            background-color: #65a30d;
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Footer button specific styles */
        .site-footer .btn-secondary {
            background-color: #06d6a0 !important;
            color: #ffffff !important;
            border-color: #06d6a0 !important;
            font-weight: 600 !important;
        }

        .site-footer .btn-secondary:hover {
            background-color: #05b08a !important;
            color: #ffffff !important;
            border-color: #05b08a !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 15px rgba(6, 214, 160, 0.3) !important;
        }

        .btn-outline {
            background-color: transparent !important;
            color: #ffffff !important;
            border: 2px solid #ffffff !important;
        }

        .btn-outline:hover {
            background-color: #ffffff !important;
            color: #1e40af !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-outline.whatsapp-btn {
            border-color: #25d366 !important;
            color: #25d366 !important;
        }

        .btn-outline.whatsapp-btn:hover {
            background-color: #25d366 !important;
            color: #ffffff !important;
            border-color: #25d366 !important;
        }

        .btn i {
            font-size: 1.2em;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            max-width: 1200px;
            margin: 0 auto;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .site-branding .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .site-branding .logo:hover {
            transform: scale(1.05);
        }

        .site-branding .logo-icon {
            height: 50px;
            width: auto;
            margin-right: 12px;
            transition: all 0.3s ease;
        }

        .site-branding .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .site-branding .logo-text .brand-auto {
            color: #1e3a8a;
        }

        .site-branding .logo-text .brand-tech {
            color: #06d6a0;
        }

        .hero-section {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 6rem 0;
            margin-top: 60px;
        }

        .main-content {
            margin-top: 0;
        }

        /* Responsive logo */
        @media (max-width: 768px) {
            .site-branding .logo-icon {
                height: 40px;
                width: auto;
                margin-right: 8px;
            }
            
            .site-branding .logo-text {
                font-size: 1.2rem;
            }
        }
    
        /* Mobile Menu Styles */
        .mobile-menu-toggle {
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            background-color: rgba(30, 64, 175, 0.1);
        }

        .navbar-toggler-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .navbar-toggler-icon i {
            font-size: 1.2rem;
            color: #1e40af;
            transition: all 0.3s ease;
        }

        /* Rotación removida - ahora usamos íconos diferentes */

        .mobile-navigation {
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 1rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .mobile-nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mobile-nav-menu li {
            margin: 0;
        }

        .mobile-nav-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.3s ease;
        }

        .mobile-nav-menu a:hover {
            background-color: #f8fafc;
            color: #1e40af;
            padding-left: 1.5rem;
        }

        .mobile-nav-menu li:last-child a {
            border-bottom: none;
        }

        /* Animación del collapse */
        .collapse:not(.show) {
            display: none;
        }

        .collapse.show {
            display: block;
        }

        .collapsing {
            height: 0;
            overflow: hidden;
            transition: height 0.35s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .site-header {
                padding: 0.5rem 0;
            }
            
            .header-content {
                flex-wrap: wrap;
            }
            
            .main-navigation {
                display: none;
            }
            
            #mobile-menu {
                width: 100%;
                margin-top: 0.5rem;
            }
        }
</style>
<style>
/* Clase específica para el título de contacto */
.contact-title-white {
    color: #fff !important;
    font-size: 2.5rem !important;
    font-weight: 700 !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
}

        /* Mobile Menu Styles */
        .mobile-menu-toggle {
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            background-color: rgba(30, 64, 175, 0.1);
        }

        .navbar-toggler-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .navbar-toggler-icon i {
            font-size: 1.2rem;
            color: #1e40af;
            transition: all 0.3s ease;
        }

        /* Rotación removida - ahora usamos íconos diferentes */

        .mobile-navigation {
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 1rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .mobile-nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .mobile-nav-menu li {
            margin: 0;
        }

        .mobile-nav-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.3s ease;
        }

        .mobile-nav-menu a:hover {
            background-color: #f8fafc;
            color: #1e40af;
            padding-left: 1.5rem;
        }

        .mobile-nav-menu li:last-child a {
            border-bottom: none;
        }

        /* Animación del collapse */
        .collapse:not(.show) {
            display: none;
        }

        .collapse.show {
            display: block;
        }

        .collapsing {
            height: 0;
            overflow: hidden;
            transition: height 0.35s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .site-header {
                padding: 0.5rem 0;
            }
            
            .header-content {
                flex-wrap: wrap;
            }
            
            .main-navigation {
                display: none;
            }
            
            #mobile-menu {
                width: 100%;
                margin-top: 0.5rem;
            }
        }
</style>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
    <header id="masthead" class="site-header">
        <div class="container">
            <div class="header-content">
                <!-- Logo -->
                <div class="site-branding">
                    <?php
                    // Preferencia de logos: 1) solo-logo.svg  2) Logo-slogan-tagline.svg  3) fallback logo-automatiza-tech.svg
                    $theme_dir  = get_template_directory();
                    $theme_uri  = get_template_directory_uri();
                    $logo_uri   = $theme_uri . '/assets/images/logo-automatiza-tech.svg'; // fallback
                    $candidates = [
                        '/assets/images/solo-logo.svg',
                        '/assets/images/Logo-slogan-tagline.svg',
                    ];
                    foreach ($candidates as $rel) {
                        if (file_exists($theme_dir . $rel)) {
                            $logo_uri = $theme_uri . $rel;
                            break;
                        }
                    }
                    ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="logo" rel="home">
                        <img src="<?php echo esc_url($logo_uri); ?>"
                             alt="<?php bloginfo('name'); ?>"
                             class="logo-icon"
                             loading="eager">
                        <span class="logo-text">
                            <span class="brand-auto">Automatiza</span>
                            <span class="brand-tech">Tech</span>
                        </span>
                    </a>
                </div><!-- .site-branding -->

                <!-- Navigation Menu -->
                <nav id="site-navigation" class="main-navigation">
                    <ul class="nav-menu">
                        <li><a href="#beneficios">Beneficios</a></li>
                        <li><a href="#integraciones">Integraciones</a></li>
                        <li><a href="#servicios">Servicios</a></li>
                        <li><a href="#industrias">Casos de Uso</a></li>
                        <li><a href="#planes">Precios</a></li>
                        <li><a href="#contact">Contacto</a></li>
                    </ul>
                </nav><!-- #site-navigation -->

                <!-- Mobile Menu Button -->
                <button class="mobile-menu-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobile-menu" aria-controls="mobile-menu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon">
                        <i class="fas fa-bars"></i>
                    </span>
                </button>
            </div><!-- .header-content -->

            <!-- Christmas Lights -->
            <div class="christmas-lights">
                <span class="light red"></span>
                <span class="light yellow"></span>
                <span class="light green"></span>
                <span class="light blue"></span>
                <span class="light red"></span>
                <span class="light yellow"></span>
                <span class="light green"></span>
                <span class="light blue"></span>
                <span class="light red"></span>
                <span class="light yellow"></span>
                <span class="light green"></span>
                <span class="light blue"></span>
                <span class="light red"></span>
                <span class="light yellow"></span>
                <span class="light green"></span>
                <span class="light blue"></span>
                <span class="light red"></span>
                <span class="light yellow"></span>
                <span class="light green"></span>
                <span class="light blue"></span>
            </div>

            <!-- Mobile Menu -->
            <div class="collapse" id="mobile-menu">
                <nav class="mobile-navigation">
                    <ul class="mobile-nav-menu">
                        <li><a href="#beneficios">Beneficios</a></li>
                        <li><a href="#integraciones">Integraciones</a></li>
                        <li><a href="#servicios">Servicios</a></li>
                        <li><a href="#industrias">Casos de Uso</a></li>
                        <li><a href="#planes">Precios</a></li>
                        <li><a href="#contact">Contacto</a></li>
                    </ul>
                </nav>
            </div>
        </div><!-- .container -->
    </header><!-- #masthead -->

    <script>
    // Script para arreglar el menú móvil accordion
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButton = document.querySelector('.mobile-menu-toggle');
        const mobileMenu = document.querySelector('#mobile-menu');
        
        if (toggleButton && mobileMenu) {
            const iconElement = toggleButton.querySelector('i');

    // Christmas animations (lightweight, December only)
    (function(){
        try {
            var now = new Date();
            var isDecember = now.getMonth() === 11; // 0-based: 11 = December
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!isDecember || reduceMotion) return;

            document.body.classList.add('christmas-mode');

            // Create floating layer
            var layer = document.createElement('div');
            layer.className = 'christmas-layer';
            document.body.appendChild(layer);

            // Helper to create items
            function addItem(cls, text, x, y){
                var el = document.createElement('span');
                el.className = 'christmas-item ' + cls;
                el.textContent = text;
                el.style.left = x + 'vw';
                el.style.top = y + 'vh';
                layer.appendChild(el);
            }

            // Floating snowflakes
            addItem('flake', '❄', 8, 12);
            addItem('flake', '❄', 24, 28);
            addItem('flake', '❄', 70, 18);
            addItem('flake', '❄', 45, 8);
            
            // Árboles de Navidad
            addItem('tree',  '🎄', 12, 66);
            addItem('tree',  '🎄', 32, 72);
            addItem('tree',  '🎄', 78, 68);
            addItem('tree',  '🎄', 88, 75);
            
            // Regalos y estrellas
            addItem('gift',  '🎁', 82, 22);
            addItem('gift',  '🎁', 18, 24);
            addItem('star',  '⭐',  18, 74);
            addItem('star',  '⭐',  88, 16);
            
            // Santa Claus
            addItem('santa', '🎅', 25, 45);
            addItem('santa', '🎅', 65, 52);

            // Muñeco de nieve (robot navideño)
            addItem('robot', '⛄', 52, 30);
        } catch(e) { /* no-op */ }
    })();
            
            function updateIcon(isOpen) {
                if (iconElement) {
                    if (isOpen) {
                        iconElement.className = 'fas fa-times';
                    } else {
                        iconElement.className = 'fas fa-bars';
                    }
                }
            }
            
            // Función para toggle manual del menú
            toggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                
                const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
                
                if (isExpanded) {
                    // Cerrar menú
                    mobileMenu.classList.remove('show');
                    toggleButton.setAttribute('aria-expanded', 'false');
                    toggleButton.classList.remove('collapsed');
                    updateIcon(false);
                } else {
                    // Abrir menú
                    mobileMenu.classList.add('show');
                    toggleButton.setAttribute('aria-expanded', 'true');
                    toggleButton.classList.add('collapsed');
                    updateIcon(true);
                }
            });
            
            // Cerrar menú al hacer clic en un enlace
            const menuLinks = mobileMenu.querySelectorAll('a');
            menuLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    mobileMenu.classList.remove('show');
                    toggleButton.setAttribute('aria-expanded', 'false');
                    toggleButton.classList.remove('collapsed');
                    updateIcon(false);
                });
            });
            
            // Cerrar menú al hacer clic fuera de él
            document.addEventListener('click', function(e) {
                if (!toggleButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.remove('show');
                    toggleButton.setAttribute('aria-expanded', 'false');
                    toggleButton.classList.remove('collapsed');
                    updateIcon(false);
                }
            });
            
            // Asegurar que inicie con el ícono correcto
            updateIcon(false);
        }
    });
    </script>

    <script>
    // Christmas UI helpers: toggle, stars, modal (independiente del menú móvil)
    (function(){
        try {
            var params = new URLSearchParams(window.location.search);
            var monthOk = (new Date()).getMonth() === 11; // December (0-based)
            var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var forceOn = params.get('christmas') === '1' || localStorage.getItem('christmasEnabled') === '1';
            var forceOff = params.get('christmas') === '0' || localStorage.getItem('christmasDisabled') === '1';
            var enableInitially = !reduced && (forceOn || (monthOk && !forceOff));

            function qs(sel){ return document.querySelector(sel); }
            function ensureLayer(){ var l = qs('.christmas-layer'); if(!l){ l = document.createElement('div'); l.className='christmas-layer'; document.body.appendChild(l); } return l; }
            function ensureStars(){ if(qs('.christmas-star')) return; ['star-tl','star-tr','star-bl','star-br'].forEach(function(cls){ var s=document.createElement('div'); s.className='christmas-star '+cls; s.textContent='⭐'; document.body.appendChild(s); }); }
            // Replacement modal: accessible, styled and light animation
            function ensureModal(){
                if(qs('.christmas-modal-overlay')) return;
                var shown = parseInt(localStorage.getItem('christmasModalShownCount_v2') || '0', 10);
                if (shown >= 10) return; // límite de 10 veces por navegador
                localStorage.setItem('christmasModalShownCount_v2', String(shown + 1));
                var overlay = document.createElement('div');
                overlay.className = 'christmas-modal-overlay';
                overlay.innerHTML = ""
                    + "<div class='christmas-modal' role='dialog' aria-modal='true' aria-labelledby='christmas-title'>"
                    +   "<button class='christmas-modal-close' aria-label='Cerrar'>✖</button>"
                    +   "<div class='christmas-modal-header'>"
                    +       "<span class='christmas-badge' aria-hidden='true'>❄</span>"
                    +       "<h3 id='christmas-title'>Automatiza tu web en Navidad</h3>"
                    +   "</div>"
                    +   "<div class='christmas-modal-content'>"
                    +       "<p>Impulsa tu negocio esta temporada: bots, integraciones y paneles sin complicaciones. Descubre nuestros planes.</p>"
                    +   "</div>"
                    +   "<div class='h-modal-actions'>"
                    +       "<div class='bot-invite' aria-hidden='true'>"
                    +           "<span class='bot bot-wave'>⛄</span>"
                    +           "<span class='bot bot-bounce'>🎄</span>"
                    +           "<span class='bot bot-arrow'>👉</span>"
                    +       "</div>"
                    +       "<a href='#planes' class='btn-primary h-modal-primary cta-pulse-xmas'>Ver Planes</a>"
                    +       "<button type='button' class='btn-ghost h-modal-dismiss'>Cerrar</button>"
                    +   "</div>"
                    + "</div>";
                document.body.appendChild(overlay);
                function dismiss(){ overlay.remove(); }
                var closeBtn = overlay.querySelector('.christmas-modal-close');
                closeBtn.addEventListener('click', dismiss);
                overlay.addEventListener('click', function(e){ if(e.target === overlay) dismiss(); });
                // Accesibilidad: cerrar con ESC
                document.addEventListener('keydown', function onKey(e){ if(e.key === 'Escape'){ dismiss(); document.removeEventListener('keydown', onKey); } });
                // CTA principal: ir a la sección de planes y cerrar modal
                var primary = overlay.querySelector('.h-modal-primary');
                if(primary){ primary.addEventListener('click', function(ev){ ev.preventDefault(); dismiss(); window.location.hash = '#planes'; }); }
                // Botón cerrar secundario
                var dismissBtn = overlay.querySelector('.h-modal-dismiss');
                if(dismissBtn){ dismissBtn.addEventListener('click', dismiss); }
                // Llevar el foco al botón cerrar para lectores de pantalla/teclado
                try { closeBtn.focus(); } catch(_) {}
                // Randomizar bots/costos
                try {
                    var bots = overlay.querySelectorAll('.bot-invite .bot');
                    var faces = ['⛄','🎄','🎁','⭐','❄'];
                    if(bots[0]) bots[0].textContent = faces[Math.floor(Math.random()*faces.length)];
                    if(bots[1]) bots[1].textContent = faces[Math.floor(Math.random()*faces.length)];
                } catch(_) {}
            }
            function populate(layer){ if(layer.childElementCount>0) return; function add(cls, txt, x,y){ var el=document.createElement('span'); el.className='christmas-item '+cls; el.textContent=txt; el.style.left=x+'vw'; el.style.top=y+'vh'; layer.appendChild(el);} add('flake','❄', 8, 12); add('flake','❄', 24, 28); add('flake','❄', 70, 18); add('flake','❄', 45, 8); add('tree','🎄', 12, 66); add('tree','🎄', 32, 72); add('tree','🎄', 78, 68); add('tree','🎄', 88, 75); add('gift','🎁', 82, 22); add('gift','🎁', 18, 24); add('star','⭐', 18, 75); add('star','⭐', 88, 15); add('santa','🎅', 25, 45); add('santa','🎅', 65, 52); add('robot','⛄', 52, 30); add('sleigh-convoy','🦌🦌🎅🛷', -10, 45); }

            function enable(){ 
                document.body.classList.add('christmas-mode'); 
                var layer=ensureLayer(); 
                populate(layer); 
                ensureStars(); 
                localStorage.setItem('christmasDisabled','0'); 
                localStorage.setItem('christmasEnabled','1');
                showMessage('🎄 ¡Modo Navidad activado! Disfruta de la magia navideña ✨', 'success');
            }
            
            function disable(){ 
                document.body.classList.remove('christmas-mode'); 
                document.querySelectorAll('.christmas-layer,.christmas-star').forEach(function(el){ el.remove(); }); 
                localStorage.setItem('christmasDisabled','1'); 
                showMessage('❄ Modo Navidad desactivado. Puedes reactivarlo cuando quieras 🎅', 'info');
            }
            
            function showMessage(text, type){
                var msg = document.createElement('div');
                msg.className = 'christmas-notification christmas-notification-' + type;
                msg.textContent = text;
                msg.style.cssText = 'position:fixed;top:20px;right:20px;background:' + (type === 'success' ? '#16a34a' : '#0ea5e9') + ';color:white;padding:16px 24px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:10000;font-size:14px;max-width:320px;animation:slideInRight 0.3s ease-out;';
                document.body.appendChild(msg);
                setTimeout(function(){ 
                    msg.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(function(){ msg.remove(); }, 300);
                }, 4000);
            }
            
            function ensureToggle(){ 
                if(qs('.christmas-toggle')) return; 
                var t=document.createElement('button'); 
                t.className='christmas-toggle'; 
                t.type='button'; 
                t.title='Activar/Desactivar Navidad'; 
                t.setAttribute('aria-label','Alternar modo Navidad'); 
                t.textContent='❄'; 
                t.addEventListener('click', function(){ 
                    if(document.body.classList.contains('christmas-mode')){ 
                        disable(); 
                    } else { 
                        enable(); 
                    } 
                }); 
                document.body.appendChild(t); 
            }

            // Modal siempre se muestra independientemente del modo navideño
            ensureModal();
            
            if(enableInitially) enable();
            ensureToggle();
        } catch(e) {}
    })();
    </script>


