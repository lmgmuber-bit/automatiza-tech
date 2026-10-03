<?php
/**
 * Template Name: AT Landing - Aplicaciones Web a Medida
 *
 * Landing SEO P0: "desarrollo de aplicaciones web a medida chile",
 * "portal de clientes a medida". Mismo patrón, reusa CSS/JS/modal home-premium.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/landing-apps-web/body.html');
if ($body === false) { $body = '<!-- landing-apps-web: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/aplicaciones-web-a-medida/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aplicaciones Web y Portales a Medida | AutomatizaTech</title>
<meta name="description" content="Paneles y portales a medida para gestionar clientes, ventas y operación en un solo lugar. Desarrollo de aplicaciones web en Chile. Diagnóstico gratis.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Aplicaciones Web y Portales a Medida | AutomatizaTech">
<meta property="og:description" content="Paneles y portales a medida para gestionar tu operación. Diagnóstico gratis.">
<meta property="og:url" content="<?php echo esc_url($canonical); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($at_img); ?>/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "Desarrollo de aplicaciones web y portales a medida",
  "provider": {
    "@type": "LocalBusiness",
    "name": "AutomatizaTech",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "areaServed": { "@type": "Country", "name": "Chile" }
  },
  "areaServed": { "@type": "Country", "name": "Chile" },
  "description": "Paneles, portales y dashboards a medida para gestionar clientes, ventas y operación en un solo lugar."
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Qué diferencia hay entre un sitio web y una aplicación web?",
      "acceptedAnswer": { "@type": "Answer", "text": "El sitio web informa y capta clientes; la aplicación web gestiona datos: clientes, ventas, inventario o lo que tu operación necesite, con login y roles." }
    },
    {
      "@type": "Question",
      "name": "¿La aplicación puede tener login y distintos roles de usuario?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Definimos roles (administrador, vendedor, cliente) con permisos distintos según lo que necesites." }
    },
    {
      "@type": "Question",
      "name": "¿Se integra con las herramientas que ya uso?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí, se puede conectar a tu CRM, WhatsApp, sistema de pagos o planillas existentes." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta desarrollar una aplicación web a medida?",
      "acceptedAnswer": { "@type": "Answer", "text": "Depende de la cantidad de módulos y usuarios. En el diagnóstico gratuito definimos alcance y cotización por fases." }
    },
    {
      "@type": "Question",
      "name": "¿Quién es dueño del código una vez terminado el proyecto?",
      "acceptedAnswer": { "@type": "Answer", "text": "El cliente. El código y los datos son tuyos; nosotros los construimos y podemos mantenerlos si lo prefieres." }
    }
  ]
}
</script>
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url($at_hp); ?>/at-home.css?v=<?php echo $css_ver; ?>">
</head>
<body <?php body_class('at-home-premium'); ?>>
<script>window.AT_IMG = <?php echo wp_json_encode($at_img); ?>;</script>
<script>
window.AT_HOME = <?php echo wp_json_encode(array(
    'bookingWebhookUrl' => 'https://n8n-n8n.kchiba.easypanel.host/webhook/becd5a16-7b3a-4961-8a2c-e86ca01d069e',
    'availabilityUrl' => esc_url_raw(rest_url('automatiza-tech/v1/check-availability')),
    'attrAjaxUrl' => admin_url('admin-ajax.php'),
    'attrNonce' => wp_create_nonce('at_home_premium_attr'),
    'landing' => 'landing_apps_web',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
