<?php
/**
 * Página de error 404 de automatizatech.cl (UX-4 / CON-9 del diagnóstico del 12-nov-2026).
 *
 * Documento propio y liviano con el diseño de la portada (home-premium). A propósito:
 *  - NO llama get_header()/get_footer(): esos archivos son del tema antiguo (otro nombre, otro logo,
 *    precio de oferta antiguo y la etiqueta de Google Ads con su conversión).
 *  - NO llama wp_head(): sin etiquetas de seguimiento ni píxeles. Su <title>, <meta robots>
 *    (noindex) y favicon van escritos aquí.
 *  - SÍ llama wp_footer(): el aviso de cookies (mu-plugin at-consentimiento) se dibuja ahí y el enlace
 *    «Preferencias de cookies» lo necesita. Se quita el chat flotante para mantenerla liviana.
 */
if (!defined('ABSPATH')) { exit; }

status_header(404);
nocache_headers();
remove_action('wp_footer', 'automatiza_tech_render_chat_widget');

require_once get_template_directory() . '/inc/at-servicios.php';

$at_img = get_template_directory_uri() . '/assets/home-premium/img';
$at_ico = 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Página no encontrada | AutomatizaTech</title>
<meta name="robots" content="noindex, follow">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($at_img); ?>/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#070b14;--surface:#0b131f;--text:#f4f7fb;--text-2:#9bacc1;--text-3:#94a6ba;--accent:#2dd4bf;--accent-2:#5eead4;--bd1:rgba(255,255,255,.07);--bd2:rgba(255,255,255,.095);--bd3:rgba(255,255,255,.16)}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;min-height:100vh;display:flex;flex-direction:column;background:radial-gradient(900px 560px at 78% -6%,rgba(45,212,191,.10),transparent 62%),var(--bg);color:var(--text-2);font-family:'Manrope',system-ui,sans-serif;font-size:16px;line-height:1.65;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
svg{flex:none}
.e-top{display:flex;align-items:center;padding:16px clamp(20px,4vw,44px);border-bottom:1px solid var(--bd1)}
.e-logo{display:flex;align-items:center;gap:11px;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:17px;letter-spacing:-.01em;color:var(--text)}
.e-logo img{width:34px;height:34px;object-fit:contain}
.e-logo b{color:var(--accent);font-weight:600}
main{flex:1;width:100%;max-width:1040px;margin:0 auto;padding:clamp(40px,7vw,88px) 24px 64px}
.e-code{display:inline-flex;align-items:center;gap:12px;font-family:ui-monospace,'JetBrains Mono',monospace;font-size:12px;font-weight:500;letter-spacing:.18em;color:var(--accent)}
.e-code i{width:24px;height:1px;background:var(--accent)}
h1{margin:20px 0 0;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:clamp(32px,5.2vw,56px);line-height:1.08;letter-spacing:-.03em;color:var(--text);text-wrap:balance}
h1 span{color:var(--accent)}
.e-lead{margin:20px 0 0;max-width:560px;font-size:clamp(16px,1.6vw,18px);color:var(--text-2)}
.e-cta{margin-top:32px;display:flex;flex-wrap:wrap;gap:13px}
.e-btn{display:inline-flex;align-items:center;gap:10px;min-height:48px;padding:14px 24px;border-radius:11px;font-family:'Space Grotesk',sans-serif;font-size:15.5px;font-weight:600;transition:transform .2s,background .2s,border-color .2s}
.e-btn--si{background:var(--accent);color:#061018;box-shadow:0 12px 30px -16px rgba(45,212,191,.7)}
.e-btn--si:hover{background:var(--accent-2);transform:translateY(-2px)}
.e-btn--no{border:1px solid var(--bd3);color:var(--text)}
.e-btn--no:hover{border-color:rgba(45,212,191,.4);background:rgba(255,255,255,.03)}
h2{margin:clamp(44px,6vw,72px) 0 0;display:flex;align-items:center;gap:10px;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:20px;color:var(--text)}
.e-grid{margin-top:18px;display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:12px}
.e-card{display:flex;align-items:center;gap:14px;padding:16px 18px;background:var(--surface);border:1px solid var(--bd2);border-radius:14px;transition:border-color .25s,transform .25s}
.e-card:hover{border-color:rgba(45,212,191,.45);transform:translateY(-2px)}
.e-card .ic{width:44px;height:44px;flex:none;border-radius:12px;background:rgba(45,212,191,.08);border:1px solid rgba(45,212,191,.18);display:flex;align-items:center;justify-content:center}
.e-card strong{display:block;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:16px;color:var(--text)}
.e-card small{display:block;font-size:13px;line-height:1.45;color:var(--text-3)}
.e-foot{border-top:1px solid var(--bd1);padding:22px clamp(20px,4vw,44px);display:flex;flex-wrap:wrap;gap:12px 22px;justify-content:space-between;align-items:center;font-size:13px;color:var(--text-3)}
.e-foot a{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--text-3)}
.e-foot a:hover{color:var(--text)}
a:focus-visible{outline:2px solid var(--accent-2);outline-offset:3px;border-radius:8px}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body <?php body_class('at-404'); ?>>
<header class="e-top">
  <a class="e-logo" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url($at_img); ?>/at-logo.png" alt="" width="34" height="34"><span>Automatiza<b>Tech</b></span></a>
</header>
<main>
  <span class="e-code"><i></i>ERROR 404</span>
  <h1>No encontramos <span>esta página</span></h1>
  <p class="e-lead">Puede que la dirección esté mal escrita o que la página ya no exista. Vuelve al inicio, mira nuestros servicios o cuéntanos qué necesita tu negocio.</p>
  <div class="e-cta">
    <a class="e-btn e-btn--si" href="<?php echo esc_url(home_url('/')); ?>">
      <svg <?php echo $at_ico; ?>><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>Volver al inicio
    </a>
    <a class="e-btn e-btn--no" href="<?php echo esc_url(home_url('/#contacto')); ?>">
      <svg <?php echo $at_ico; ?>><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Contáctanos
    </a>
  </div>

  <h2><svg <?php echo $at_ico; ?> style="color:var(--accent)"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Nuestros servicios</h2>
  <div class="e-grid">
<?php foreach (at_servicios_lista() as $slug => $s) : ?>
    <a class="e-card" href="<?php echo at_servicio_url($slug); ?>">
      <span class="ic"><?php echo $s['icono']; // SVG propio de at_servicios_lista() ?></span>
      <span><strong><?php echo esc_html($s['nombre']); ?></strong><small><?php echo esc_html($s['texto']); ?></small></span>
    </a>
<?php endforeach; ?>
  </div>
</main>
<footer class="e-foot">
  <span>&copy; <?php echo esc_html(gmdate('Y')); ?> AutomatizaTech SpA</span>
  <span style="display:flex;flex-wrap:wrap;gap:8px 22px">
    <a href="<?php echo esc_url(home_url('/privacidad/')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/></svg>Privacidad</a>
    <?php echo at_preferencias_cookies_html(); ?>
  </span>
</footer>
<?php wp_footer(); ?>
</body>
</html>
