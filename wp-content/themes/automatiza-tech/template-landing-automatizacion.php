<?php
/**
 * Template Name: AT Landing - Automatización de Negocios
 *
 * Landing SEO P0 (backlog Fase 2): "automatización de procesos pyme",
 * "automatización whatsapp crm agenda". Mismo patrón que
 * template-landing-asistentes-ia.php, reusa CSS/JS/modal de home-premium.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/landing-automatizacion/body.html');
if ($body === false) { $body = '<!-- landing-automatizacion: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/automatizacion-negocios-whatsapp-crm/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Automatización de Negocios: WhatsApp, CRM, Agenda y Reportes | AutomatizaTech</title>
<meta name="description" content="Conectamos WhatsApp, CRM, agenda y pagos en un solo sistema. Automatización de procesos para PyMEs en Chile. Diagnóstico gratis.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Automatización de Negocios: WhatsApp, CRM, Agenda y Reportes | AutomatizaTech">
<meta property="og:description" content="Conectamos WhatsApp, CRM, agenda y pagos en un solo sistema. Diagnóstico gratis.">
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
  "serviceType": "Automatización de procesos de negocio (WhatsApp, CRM, agenda, pagos)",
  "provider": {
    "@type": "LocalBusiness",
    "name": "AutomatizaTech",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "areaServed": { "@type": "Country", "name": "Chile" }
  },
  "areaServed": { "@type": "Country", "name": "Chile" },
  "description": "Conectamos WhatsApp, CRM, agenda, pagos y reportes en un solo flujo automatizado para que la operación no dependa de trabajo manual."
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Qué herramientas se pueden conectar entre sí?",
      "acceptedAnswer": { "@type": "Answer", "text": "WhatsApp Business, Instagram, tu CRM actual o uno nuevo, Google Calendar, pasarelas de pago y planillas. Si tiene una API o webhook, generalmente se puede conectar." }
    },
    {
      "@type": "Question",
      "name": "¿Necesito saber programar para usarlo?",
      "acceptedAnswer": { "@type": "Answer", "text": "No. Nosotros construimos y mantenemos la automatización. Tu equipo solo usa las herramientas como siempre, pero ahora sincronizadas." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto tiempo le ahorra esto a mi equipo?",
      "acceptedAnswer": { "@type": "Answer", "text": "Depende del proceso, pero eliminar la copia manual de datos entre planillas y apps suele liberar varias horas semanales por persona." }
    },
    {
      "@type": "Question",
      "name": "¿Sirve si ya tengo un CRM?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Conectamos tu automatización al CRM que ya usas; no es necesario migrar de plataforma." }
    },
    {
      "@type": "Question",
      "name": "¿Qué pasa si más adelante cambio de herramienta?",
      "acceptedAnswer": { "@type": "Answer", "text": "Actualizamos la automatización para que apunte a la nueva herramienta. El flujo de datos no se pierde." }
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
    'landing' => 'landing_automatizacion',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
