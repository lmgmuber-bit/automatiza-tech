<?php
/**
 * Template Name: AT Home Premium
 *
 * Standalone home/landing ported from the Claude Design project "Rediseño AT"
 * (file: AutomatizaTech Home.dc.html). Outputs its OWN HTML document and does
 * NOT call get_header()/get_footer(), so the legacy theme nav/footer (old
 * "bots inteligentes" positioning) are bypassed. Fully reversible: activate by
 * assigning this template to a Page, or setting that Page as the front page.
 * The live index.php home is untouched.
 *
 * RACI: visual/frontend = Claude. Form backend, lead capture, tracking events,
 * JSON-LD schema and final SEO meta = Codex (hooks left at wp_head/wp_footer
 * and the demo <form>). Do NOT fire conversions on page load.
 */
if (!defined('ABSPATH')) { exit; }

// Quita el detector de emojis de WP (dispara un worker bloqueado por CSP).
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir = get_template_directory();
$at_uri = get_template_directory_uri();
$at_hp  = $at_uri . '/assets/home-premium';
$at_img = $at_hp . '/img';

$body = @file_get_contents($at_dir . '/assets/home-premium/body.html');
if ($body === false) { $body = '<!-- home-premium: body.html missing -->'; }
// Body references images as "assets/<file>"; point them at the theme img dir.
$body = str_replace('assets/', $at_img . '/', $body);

$css_ver = @filemtime($at_dir . '/assets/home-premium/at-home.css') ?: '1';
$js_ver  = @filemtime($at_dir . '/assets/home-premium/at-home.js') ?: '1';
$agenda_ver = @filemtime($at_dir . '/assets/home-premium/at-agenda.js') ?: '1';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AutomatizaTech — Automatización y tecnología digital premium para negocios</title>
<meta name="description" content="Creamos asistentes inteligentes, automatización, sitios web premium, aplicaciones web, apps móviles y sistemas a medida. Agenda un diagnóstico gratis: qué tecnología necesita tu negocio primero.">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($at_img); ?>/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<!-- Motion libraries the design depends on. Codex: evaluar self-host en producción. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<?php wp_head(); // hook para GA4/GTM/JSON-LD de Codex — sin conversión en load ?>
<!-- Cargado al final del head para ganar a cualquier estilo del tema -->
<link rel="stylesheet" href="<?php echo esc_url($at_hp); ?>/at-home.css?v=<?php echo $css_ver; ?>">
</head>
<body <?php body_class('at-home-premium'); ?>>
<script>window.AT_IMG = <?php echo wp_json_encode($at_img); ?>;</script>
<script>
window.AT_HOME = <?php echo wp_json_encode(array(
    // bookingWebhookUrl: webhook n8n `saveLead` (crea lead + Google Calendar + Meet).
    // DEBE coincidir con el de inc/chat-widget.php. TODO: mover a una constante compartida.
    'bookingWebhookUrl' => 'https://n8n-n8n.kchiba.easypanel.host/webhook/becd5a16-7b3a-4961-8a2c-e86ca01d069e',
    'availabilityUrl' => esc_url_raw(rest_url('automatiza-tech/v1/check-availability')),
    // Atribución (UTM) WP-side por session_id, en paralelo al booking. Sin PII.
    'attrAjaxUrl' => admin_url('admin-ajax.php'),
    'attrNonce' => wp_create_nonce('at_home_premium_attr'),
    'landing' => 'home_premium',
)); ?>;
</script>
<!-- Disponibilidad real para el selector de horarios (pipeline existente automatiza-tech/v1) -->
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode( esc_url_raw( rest_url('automatiza-tech/v1/appointments-config') ) ); ?> };</script>
<?php echo $body; // contenido de plantilla confiable generado por el build ?>
<script src="<?php echo esc_url($at_hp); ?>/at-home.js?v=<?php echo $js_ver; ?>" defer></script>
<script src="<?php echo esc_url($at_hp); ?>/at-agenda.js?v=<?php echo $agenda_ver; ?>" defer></script>
<?php wp_footer(); ?>
</body>
</html>
