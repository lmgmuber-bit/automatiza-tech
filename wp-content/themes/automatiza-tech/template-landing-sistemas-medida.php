<?php
/**
 * Template Name: AT Landing - Sistemas a Medida
 *
 * Landing SEO P0: "software a medida chile", "sistema de gestión a medida".
 * Mismo patrón, reusa CSS/JS/modal home-premium.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/landing-sistemas-medida/body.html');
if ($body === false) { $body = '<!-- landing-sistemas-medida: body.html missing -->'; }
$body = str_replace('assets/', $at_img . '/', $body);
$body = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';

$canonical = home_url('/sistemas-a-medida-chile/');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sistemas y Software a Medida para tu Negocio en Chile | AutomatizaTech</title>
<meta name="description" content="Cuando lo estándar no alcanza, desarrollamos el software exacto que tu operación necesita. Sistemas a medida en Chile. Diagnóstico gratis.">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Sistemas y Software a Medida para tu Negocio en Chile | AutomatizaTech">
<meta property="og:description" content="Cuando lo estándar no alcanza, desarrollamos el software exacto que necesitas. Diagnóstico gratis.">
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
  "serviceType": "Desarrollo de sistemas y software a medida",
  "provider": {
    "@type": "LocalBusiness",
    "name": "AutomatizaTech",
    "url": "<?php echo esc_url(home_url()); ?>",
    "telephone": "<?php echo esc_attr(get_theme_mod('whatsapp_number', '+56 9 2700 2984')); ?>",
    "areaServed": { "@type": "Country", "name": "Chile" }
  },
  "areaServed": { "@type": "Country", "name": "Chile" },
  "description": "Desarrollamos el software exacto que tu operación necesita cuando ninguna herramienta estándar del mercado resuelve tu proceso."
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "¿Qué tipo de sistemas desarrollan?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sistemas de inventario, facturación, gestión de proyectos, reservas y cualquier proceso específico de tu negocio que no resuelva bien un software genérico." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto cuesta un sistema a medida?",
      "acceptedAnswer": { "@type": "Answer", "text": "Depende del alcance y la cantidad de módulos. En el diagnóstico técnico gratuito definimos el proyecto por fases con costos claros en cada una." }
    },
    {
      "@type": "Question",
      "name": "¿Puede integrarse con lo que ya tengo?",
      "acceptedAnswer": { "@type": "Answer", "text": "Sí. Diseñamos el sistema para conectarse con tus herramientas actuales, como contabilidad, CRM o WhatsApp." }
    },
    {
      "@type": "Question",
      "name": "¿Qué pasa si necesito cambios después de la entrega?",
      "acceptedAnswer": { "@type": "Answer", "text": "Ofrecemos soporte y mejora continua post-entrega. Los cambios de alcance se cotizan como una fase adicional." }
    },
    {
      "@type": "Question",
      "name": "¿Cuánto se demora un proyecto típico?",
      "acceptedAnswer": { "@type": "Answer", "text": "Un sistema a medida se implementa por fases; la primera fase funcional suele estar lista entre 4 y 8 semanas según la complejidad." }
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
    'landing' => 'landing_sistemas_medida',
)); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
