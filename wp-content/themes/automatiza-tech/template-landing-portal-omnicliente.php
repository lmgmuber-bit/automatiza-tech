<?php
/**
 * Template Name: AT Landing - Portal OmniCliente (Planes)
 *
 * Landing SEO/Ads P0 (Fase 2): "software atencion multicanal chile",
 * "portal whatsapp instagram para empresas", "precio bot whatsapp chile".
 * Landing de conversión directa para ads pagados: precios visibles arriba.
 * Mismo patrón, reusa CSS/JS/modal home-premium.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/landing-portal-omnicliente/body.html');
if ($body === false) { $body = '<!-- landing-portal-omnicliente: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/portal-omnicliente-precios/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Portal OmniCliente: WhatsApp, Instagram y Web en un Panel | Planes desde US$99 | AutomatizaTech</title>
<meta name="description" content="Atiende WhatsApp, Instagram, Messenger y web desde un solo panel con IA. Planes desde 99 USD/mes, 1 mes gratis en Básico y Profesional. Diagnóstico gratis.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Portal OmniCliente: WhatsApp, Instagram y Web en un Panel | AutomatizaTech">
<meta property="og:description" content="Atiende todos tus canales desde un solo panel con IA. Planes desde 99 USD/mes, 1 mes gratis.">
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
  "@type": "Product",
  "name": "Portal OmniCliente",
  "description": "Software de atención omnicanal con IA: WhatsApp, Instagram, Messenger y web en un solo panel, con asistente virtual, CRM y reportes.",
  "brand": { "@type": "Brand", "name": "AutomatizaTech" },
  "offers": [
    { "@type": "Offer", "name": "Plan Básico", "price": "99", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
    { "@type": "Offer", "name": "Plan Profesional", "price": "199", "priceCurrency": "USD", "availability": "https://schema.org/InStock" },
    { "@type": "Offer", "name": "Plan Enterprise", "price": "399", "priceCurrency": "USD", "availability": "https://schema.org/InStock" }
  ]
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta el Portal OmniCliente?",
      "acceptedAnswer": { "@type": "Answer", "text": "Desde 99 USD/mes en el plan Básico, 199 USD/mes en Profesional (el más elegido) y 399 USD/mes en Enterprise. Básico y Profesional incluyen 1 mes gratis." }
    },
    {
      "@type": "Question",
      "name": "¿Qué canales puedo conectar?",
      "acceptedAnswer": { "@type": "Answer", "text": "WhatsApp, Instagram, Messenger y el chat de tu sitio web, todos desde un mismo panel con historial de cada cliente." }
    },
    {
      "@type": "Question",
      "name": "¿Cómo funciona el mes gratis?",
      "acceptedAnswer": { "@type": "Answer", "text": "El primer mes no se cobra en los planes Básico y Profesional. La facturación comienza el segundo mes; puedes cancelar antes sin costo." }
    },
    {
      "@type": "Question",
      "name": "¿Puedo cambiar de plan más adelante?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Puedes subir de plan en cualquier momento si tu volumen de conversaciones crece." }
    },
    {
      "@type": "Question",
      "name": "¿Incluye el asistente con inteligencia artificial?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí, todos los planes incluyen IA para responder automáticamente; los planes superiores incluyen IA más avanzada y funciones adicionales de análisis." }
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
    'landing' => 'landing_portal_omnicliente',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
