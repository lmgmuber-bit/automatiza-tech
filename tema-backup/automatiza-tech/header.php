<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/css/critical.css" as="style">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- SEO Meta Tags -->
    <?php if (is_front_page()): ?>
    <meta name="description" content="Automatiza Tech - Conectamos tus ventas, web y CRM. Bots inteligentes para negocios que no se detienen. Automatiza tu atención al cliente 24/7.">
    <meta name="keywords" content="automatización, chatbots, CRM, ventas, WhatsApp, Instagram, atención al cliente, bots inteligentes">
    <meta name="author" content="Automatiza Tech">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Automatiza Tech - Conectamos tus ventas, web y CRM">
    <meta property="og:description" content="Bots inteligentes para negocios que no se detienen. Automatiza tu atención, ahorra tiempo, escala tu negocio.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo esc_url(home_url()); ?>">
    <meta property="og:site_name" content="Automatiza Tech">
    <meta property="og:locale" content="es_ES">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Automatiza Tech - Conectamos tus ventas, web y CRM">
    <meta name="twitter:description" content="Bots inteligentes para negocios que no se detienen. Automatiza tu atención, ahorra tiempo, escala tu negocio.">
    
    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "Automatiza Tech",
        "description": "Conectamos tus ventas, web y CRM. Bots inteligentes para negocios que no se detienen.",
        "url": "<?php echo esc_url(home_url()); ?>",
        "logo": "<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/logo.png",
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+1234567890')); ?>",
            "contactType": "customer service"
        },
        "sameAs": [
            "https://www.facebook.com/automatizatech",
            "https://www.instagram.com/automatizatech",
            "https://www.linkedin.com/company/automatizatech"
        ]
    }
    </script>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/apple-touch-icon.png">
    
    <?php wp_head(); ?>
    
    <!-- Font Awesome (Async load) -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    
    <!-- Critical CSS inline -->
    <style>
        /* Critical CSS for above-the-fold content */
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
        
        .hero-section {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);
            color: #ffffff;
            padding: 6rem 0;
            margin-top: 80px;
        }
        
        .main-content {
            margin-top: 0;
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
                    <?php if (has_custom_logo()): ?>
                        <?php the_custom_logo(); ?>
                    <?php else: ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="logo" rel="home">
                            <img src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/logo.png" 
                                 alt="<?php bloginfo('name'); ?>" 
                                 width="50" 
                                 height="50"
                                 loading="eager">
                            <span class="logo-text">
                                <span style="color: #1e40af;">Automatiza</span> 
                                <span style="color: #84cc16;">Tech</span>
                            </span>
                        </a>
                    <?php endif; ?>
                </div><!-- .site-branding -->

                <!-- Navigation Menu -->
                <nav id="site-navigation" class="main-navigation">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'menu_id'        => 'primary-menu',
                        'container'      => false,
                        'menu_class'     => 'nav-menu',
                        'fallback_cb'    => function() {
                            echo '<ul class="nav-menu">';
                            echo '<li><a href="#beneficios">Beneficios</a></li>';
                            echo '<li><a href="#integraciones">Integraciones</a></li>';
                            echo '<li><a href="#industrias">Casos de Uso</a></li>';
                            echo '<li><a href="#planes">Precios</a></li>';
                            echo '<li><a href="#contact">Contacto</a></li>';
                            echo '</ul>';
                        }
                    ));
                    ?>
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
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'menu_id'        => 'mobile-primary-menu',
                        'container'      => false,
                        'menu_class'     => 'mobile-nav-menu',
                        'fallback_cb'    => function() {
                            echo '<ul class="mobile-nav-menu">';
                            echo '<li><a href="#beneficios">Beneficios</a></li>';
                            echo '<li><a href="#integraciones">Integraciones</a></li>';
                            echo '<li><a href="#industrias">Casos de Uso</a></li>';
                            echo '<li><a href="#planes">Precios</a></li>';
                            echo '<li><a href="#contact">Contacto</a></li>';
                            echo '</ul>';
                        }
                    ));
                    ?>
                </nav>
            </div>
        </div><!-- .container -->
    </header><!-- #masthead -->