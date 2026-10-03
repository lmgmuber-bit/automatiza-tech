<?php
/**
 * Template Name: AT Landing - Asistentes Inteligentes IA
 *
 * Landing SEO P0 (backlog Fase 2): keyword head "chatbot whatsapp chile",
 * "bot para whatsapp negocios", "asistente inteligente con ia". Standalone
 * (no get_header()/get_footer()), reusa el design system de home-premium
 * (mismo CSS/JS/modal) para mantener consistencia visual y no duplicar motion.
 * Contenido propio en assets/landing-asistentes-ia/body.html.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium'; // CSS/JS/imágenes compartidas con el home
$at_img = $at_hp . '/img';
$at_lp  = $at_uri . '/assets/landing-asistentes-ia';

$body = @file_get_contents($at_dir . '/assets/landing-asistentes-ia/body.html');
if ($body === false) { $body = '<!-- landing-asistentes-ia: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
// Nav/footer enlazan de vuelta al home (esta landing es una página aparte).
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/asistentes-inteligentes-ia-whatsapp/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Asistente Inteligente con IA para WhatsApp e Instagram | AutomatizaTech</title>
<meta name="description" content="Chatbot con inteligencia artificial que responde, agenda y vende 24/7 en WhatsApp e Instagram. Implementación en Chile, diagnóstico gratis para tu negocio.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Asistente Inteligente con IA para WhatsApp e Instagram | AutomatizaTech">
<meta property="og:description" content="Chatbot con IA que responde, agenda y vende 24/7 en WhatsApp e Instagram. Diagnóstico gratis.">
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
  "serviceType": "Asistente virtual con inteligencia artificial para WhatsApp e Instagram",
  "provider": {
    "@type": "LocalBusiness",
    "name": "AutomatizaTech",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "areaServed": { "@type": "Country", "name": "Chile" }
  },
  "areaServed": { "@type": "Country", "name": "Chile" },
  "description": "Chatbot con inteligencia artificial que responde consultas, agenda citas y califica clientes 24/7 en WhatsApp, Instagram y web."
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta un chatbot de WhatsApp con IA en Chile?",
      "acceptedAnswer": { "@type": "Answer", "text": "Depende del plan del Portal OmniCliente: desde 99 USD/mes en el plan Básico, con 1 mes gratis. El diagnóstico gratuito de 15 a 30 minutos define qué plan conviene según tu volumen de mensajes." }
    },
    {
      "@type": "Question",
      "name": "¿El asistente funciona en Instagram además de WhatsApp?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. El mismo asistente atiende WhatsApp, Instagram, Messenger y el chat de tu sitio web desde un panel unificado (Portal OmniCliente)." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto se demora la implementación?",
      "acceptedAnswer": { "@type": "Answer", "text": "Tras el diagnóstico gratuito, la implementación típica toma entre 1 y 3 semanas según la complejidad del catálogo de servicios y las integraciones necesarias." }
    },
    {
      "@type": "Question",
      "name": "¿Necesito instalar algo o cambiar de número de WhatsApp?",
      "acceptedAnswer": { "@type": "Answer", "text": "No. El asistente se conecta a tu número de WhatsApp Business actual mediante la API oficial de Meta. No necesitas cambiar de número ni instalar aplicaciones adicionales." }
    },
    {
      "@type": "Question",
      "name": "¿Qué pasa si el asistente no puede responder algo?",
      "acceptedAnswer": { "@type": "Answer", "text": "El asistente deriva la conversación a un agente humano de tu equipo cuando detecta una consulta fuera de su alcance, sin dejar al cliente sin respuesta." }
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
    'landing' => 'landing_asistentes_ia',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
