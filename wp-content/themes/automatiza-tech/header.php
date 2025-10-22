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
            margin-top: 80px;
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
            width: 50px;
            height: 50px;
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
            margin-top: 80px;
        }

        .main-content {
            margin-top: 0;
        }

        /* Responsive logo */
        @media (max-width: 768px) {
            .site-branding .logo-icon {
                width: 40px;
                height: 40px;
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
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="logo" rel="home">
                        <img src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/logo-automatiza-tech.svg"
                             alt="<?php bloginfo('name'); ?>"
                             class="logo-icon"
                             width="50"
                             height="50"
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


