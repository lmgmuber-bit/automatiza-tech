<?php
/**
 * Template Name: AT Landing - Sitios Web Premium
 *
 * Landing SEO P0: "desarrollo web premium chile", "sitio web para pyme".
 * Mismo patrón que las otras landings, reusa CSS/JS/modal de home-premium.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/landing-sitios-premium/body.html');
if ($body === false) { $body = '<!-- landing-sitios-premium: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/sitios-web-premium-chile/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sitios Web Premium para Negocios en Chile | AutomatizaTech</title>
<meta name="description" content="Diseño de sitios web premium, rápidos y pensados para vender. Tu imagen digital a la altura de tu negocio. Diagnóstico gratis.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Sitios Web Premium para Negocios en Chile | AutomatizaTech">
<meta property="og:description" content="Diseño de sitios web premium, rápidos y pensados para vender. Diagnóstico gratis.">
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
  "serviceType": "Diseño y desarrollo de sitios web premium",
  "provider": {
    "@type": "LocalBusiness",
    "name": "AutomatizaTech",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "areaServed": { "@type": "Country", "name": "Chile" }
  },
  "areaServed": { "@type": "Country", "name": "Chile" },
  "description": "Diseño y desarrollo de sitios web premium a medida, rápidos y optimizados para convertir visitas en clientes."
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta un sitio web premium?",
      "acceptedAnswer": { "@type": "Answer", "text": "El precio depende del alcance (número de páginas, integraciones, contenido). En el diagnóstico gratuito te damos una cotización clara según tu caso." }
    },
    {
      "@type": "Question",
      "name": "¿El sitio incluye hosting y dominio?",
      "acceptedAnswer": { "@type": "Answer", "text": "Podemos gestionar hosting y dominio por ti, o dejarlo publicado en el hosting que ya tengas contratado." }
    },
    {
      "@type": "Question",
      "name": "¿Puedo editar el contenido yo mismo después?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Dejamos un panel de administración simple para que edites textos, imágenes y precios sin depender de nosotros." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto se demora el proyecto?",
      "acceptedAnswer": { "@type": "Answer", "text": "Un sitio premium típico toma entre 2 y 4 semanas desde la aprobación del diseño." }
    },
    {
      "@type": "Question",
      "name": "¿El sitio viene optimizado para SEO?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Metadatos, velocidad de carga y estructura de contenido quedan configurados para posicionar en buscadores desde el lanzamiento." }
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
    'landing' => 'landing_sitios_premium',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
