<?php
/**
 * Template Name: AT Legal
 *
 * Página legal genérica: sirve /terminos, /privacidad y /cookies según el
 * slug de la página WP. Contenido en assets/legal/<slug>.html.
 * Liviana a propósito: sin three.js/anime, solo tipografía y estilos de marca.
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$at_dir  = get_template_directory();
$at_uri  = get_template_directory_uri();
$at_img  = $at_uri . '/assets/home-premium/img';

$slug    = sanitize_file_name(get_post_field('post_name', get_queried_object_id()));
$file    = $at_dir . '/assets/legal/' . $slug . '.html';
$body    = file_exists($file) ? file_get_contents($file) : '<h1>Documento no encontrado</h1><p>Este documento legal no está disponible. Escríbenos a contacto@automatizatech.cl.</p>';

$titles = array(
    'terminos'   => 'Términos de Servicio | AutomatizaTech',
    'privacidad' => 'Política de Privacidad | AutomatizaTech',
    'cookies'    => 'Política de Cookies | AutomatizaTech',
);
$descs = array(
    'terminos'   => 'Términos y condiciones de los servicios y del Portal OmniCliente de AutomatizaTech SpA.',
    'privacidad' => 'Cómo AutomatizaTech SpA trata tus datos personales conforme a las Leyes 19.628 y 21.719.',
    'cookies'    => 'Qué cookies usa automatizatech.cl y cómo aceptarlas o rechazarlas.',
);
$title = isset($titles[$slug]) ? $titles[$slug] : get_the_title() . ' | AutomatizaTech';
$desc  = isset($descs[$slug]) ? $descs[$slug] : '';
$canonical = get_permalink();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($title); ?></title>
<meta name="description" content="<?php echo esc_attr($desc); ?>">
<link rel="canonical" href="<?php echo esc_url($canonical); ?>">
<meta name="robots" content="index,follow">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($at_img); ?>/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{--bg:#0a1628;--surface:#0d2044;--bd:rgba(45,212,191,.22);--accent:#2dd4bf;--text:#eef4fb;--text2:#b9c6d8;--text3:#8fa1b8}
html{scroll-behavior:smooth}
body.at-legal{margin:0;background:var(--bg);color:var(--text2);font-family:'Manrope',sans-serif;font-size:15.5px;line-height:1.7;-webkit-font-smoothing:antialiased}
.at-lg-top{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px clamp(20px,4vw,44px);background:rgba(10,22,40,.92);backdrop-filter:blur(8px);border-bottom:1px solid var(--bd)}
.at-lg-top a{color:var(--text);text-decoration:none;font-family:'Space Grotesk';font-weight:600;font-size:15px}
.at-lg-top nav{display:flex;gap:18px;flex-wrap:wrap}
.at-lg-top nav a{font-size:13px;color:var(--text3);font-family:'Manrope';font-weight:600}
.at-lg-top nav a:hover,.at-lg-top nav a[aria-current]{color:var(--accent)}
.at-lg-wrap{max-width:860px;margin:0 auto;padding:clamp(36px,6vw,64px) 24px 90px}
.at-lg-wrap h1{font-family:'Space Grotesk';font-weight:600;font-size:clamp(28px,4.5vw,42px);line-height:1.12;letter-spacing:-.02em;color:var(--text);margin:0 0 8px}
.at-lg-wrap .at-lg-updated{font-size:13px;color:var(--text3);margin-bottom:34px}
.at-lg-wrap h2{font-family:'Space Grotesk';font-weight:600;font-size:21px;letter-spacing:-.01em;color:var(--text);margin:38px 0 12px;padding-top:8px;border-top:1px solid rgba(255,255,255,.06)}
.at-lg-wrap h3{font-family:'Space Grotesk';font-weight:600;font-size:16.5px;color:var(--text);margin:24px 0 8px}
.at-lg-wrap p{margin:0 0 14px}
.at-lg-wrap ul,.at-lg-wrap ol{margin:0 0 16px;padding-left:22px}
.at-lg-wrap li{margin-bottom:8px}
.at-lg-wrap a{color:var(--accent);text-decoration:none}
.at-lg-wrap a:hover{text-decoration:underline}
.at-lg-wrap strong{color:var(--text)}
.at-lg-wrap table{width:100%;border-collapse:collapse;margin:0 0 18px;font-size:14px}
.at-lg-wrap th,.at-lg-wrap td{text-align:left;padding:9px 12px;border:1px solid rgba(255,255,255,.1);vertical-align:top}
.at-lg-wrap th{background:var(--surface);color:var(--text);font-family:'Space Grotesk';font-weight:600}
.at-lg-box{background:var(--surface);border:1px solid var(--bd);border-radius:12px;padding:18px 20px;margin:0 0 18px}
.at-lg-foot{border-top:1px solid var(--bd);padding:22px clamp(20px,4vw,44px);display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;font-size:13px;color:var(--text3)}
.at-lg-foot a{color:var(--text3);text-decoration:none;margin-right:14px}
.at-lg-foot a:hover{color:var(--accent)}
@media(max-width:640px){.at-lg-wrap table{display:block;overflow-x:auto}}
</style>
</head>
<body <?php body_class('at-legal'); ?>>
<header class="at-lg-top">
    <a href="<?php echo esc_url(home_url('/')); ?>">Automatiza<span style="color:var(--accent)">Tech</span></a>
    <nav aria-label="Documentos legales">
        <a href="/terminos/" <?php echo $slug === 'terminos' ? 'aria-current="page"' : ''; ?>>Términos</a>
        <a href="/privacidad/" <?php echo $slug === 'privacidad' ? 'aria-current="page"' : ''; ?>>Privacidad</a>
        <a href="/cookies/" <?php echo $slug === 'cookies' ? 'aria-current="page"' : ''; ?>>Cookies</a>
    </nav>
</header>
<main class="at-lg-wrap">
<?php echo $body; // HTML estático controlado por AT (assets/legal/*.html) ?>
</main>
<footer class="at-lg-foot">
    <span>&copy; <?php echo date('Y'); ?> AutomatizaTech SpA · RUT 78.363.717-0 · Providencia, Chile</span>
    <span>
        <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
        <a href="mailto:contacto@automatizatech.cl">contacto@automatizatech.cl</a>
    </span>
</footer>
<?php wp_footer(); ?>
</body>
</html>
