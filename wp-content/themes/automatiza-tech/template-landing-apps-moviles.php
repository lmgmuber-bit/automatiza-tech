<?php
/**
 * Template Name: AT Landing - Apps Móviles a Medida
 *
 * Landing SEO P0: "desarrollo apps móviles chile", "app a medida pyme".
 * Mismo patrón, reusa CSS/JS/modal home-premium.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/landing-apps-moviles/body.html');
if ($body === false) { $body = '<!-- landing-apps-moviles: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/desarrollo-apps-moviles-chile/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Desarrollo de Apps Móviles a Medida en Chile | AutomatizaTech</title>
<meta name="description" content="Apps nativas y multiplataforma para acercar tu producto al bolsillo de tus clientes. Desarrollo de apps móviles a medida en Chile. Diagnóstico gratis.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Desarrollo de Apps Móviles a Medida en Chile | AutomatizaTech">
<meta property="og:description" content="Apps nativas y multiplataforma para tu negocio. Diagnóstico gratis.">
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
  "serviceType": "Desarrollo de aplicaciones móviles a medida",
  "provider": {
    "@type": "LocalBusiness",
    "name": "AutomatizaTech",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "areaServed": { "@type": "Country", "name": "Chile" }
  },
  "areaServed": { "@type": "Country", "name": "Chile" },
  "description": "Apps móviles nativas y multiplataforma para acercar tu producto al bolsillo de tus clientes, con notificaciones y pagos integrados."
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿La app funciona en iPhone y Android?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Desarrollamos apps multiplataforma que funcionan en ambos sistemas desde una sola base de código." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta una app a medida?",
      "acceptedAnswer": { "@type": "Answer", "text": "Depende de las funciones que necesites. En el diagnóstico gratuito definimos el alcance mínimo viable y su costo." }
    },
    {
      "@type": "Question",
      "name": "¿Necesito una app o me sirve un sitio web?",
      "acceptedAnswer": { "@type": "Answer", "text": "Si tus clientes vuelven seguido o necesitas notificaciones push, una app suma valor real. Si es solo informar, un sitio premium puede bastar; en el diagnóstico lo evaluamos juntos." }
    },
    {
      "@type": "Question",
      "name": "¿Incluye notificaciones push?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí, las notificaciones push para avisar promociones, pedidos o novedades se pueden incluir en el desarrollo." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto se demora en publicarse en las tiendas?",
      "acceptedAnswer": { "@type": "Answer", "text": "Tras terminar el desarrollo, la revisión de Google Play y App Store toma entre 2 y 10 días adicionales según la tienda." }
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
    'landing' => 'landing_apps_moviles',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
