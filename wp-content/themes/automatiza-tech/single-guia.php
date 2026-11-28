<?php
/**
 * Una guía: /guias/<slug>/. <head> propio (mismo patrón que las landings): no llama get_header()/get_footer().
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

the_post();
$id        = get_the_ID();
$h1        = get_the_title();
$url       = get_permalink();
$extracto  = has_excerpt() ? get_the_excerpt() : '';
$desc      = at_guias_resumen($extracto !== '' ? $extracto : (string) get_post_field('post_content', $id), 160);
$pub_iso   = (string) get_post_time('c', false);
$mod_iso   = (string) get_post_modified_time('c', false);
$pub_fecha = at_guias_fecha_es((string) get_post_time('Y-m-d', false));
$mod_fecha = at_guias_fecha_es((string) get_post_modified_time('Y-m-d', false));
$imagen    = get_the_post_thumbnail_url($id, 'large') ?: '';
$autor     = at_guias_autor();
$migas     = at_guias_migas($h1, $url);

$jsonld = [
	at_guias_jsonld_articulo(['titulo' => $h1, 'descripcion' => $desc, 'publicado' => $pub_iso, 'modificado' => $mod_iso, 'url' => $url, 'imagen' => $imagen]),
	at_guias_jsonld_migas($migas),
];
[$arriba, $abajo] = at_guias_armazon();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php at_guias_head(['titulo' => $h1 . ' | AutomatizaTech', 'descripcion' => $desc, 'url' => $url, 'tipo' => 'article', 'imagen' => $imagen, 'jsonld' => $jsonld]); ?>
</head>
<body <?php body_class('at-home-premium at-guias'); ?>>
<?php at_guias_scripts_cabecera(); ?>
<a class="gu-salto" href="#contenido">Saltar al contenido</a>
<?php echo $arriba; ?>
<main id="contenido" class="gu-main">
	<article class="gu-wrap gu-wrap--lectura">
		<nav class="gu-migas" aria-label="Migas de pan">
			<ol>
				<li><a href="<?php echo esc_url($migas[0]['url']); ?>"><i class="ph ph-house" aria-hidden="true"></i>Inicio</a></li>
				<li><i class="ph ph-caret-right gu-sep" aria-hidden="true"></i><a href="<?php echo esc_url($migas[1]['url']); ?>">Guías</a></li>
				<li><i class="ph ph-caret-right gu-sep" aria-hidden="true"></i><span aria-current="page"><?php echo esc_html($h1); ?></span></li>
			</ol>
		</nav>
		<header class="gu-cab">
			<span class="gu-etiqueta"><i class="ph ph-book-open" aria-hidden="true"></i>GUÍA</span>
			<h1><?php echo esc_html($h1); ?></h1>
			<?php if ($extracto !== '') : ?><p class="gu-lead"><?php echo esc_html($extracto); ?></p><?php endif; ?>
			<p class="gu-meta">
				<span><i class="ph ph-user-circle" aria-hidden="true"></i><?php echo esc_html($autor['nombre']); ?></span>
				<span><i class="ph ph-calendar-blank" aria-hidden="true"></i>Publicada el <time datetime="<?php echo esc_attr($pub_iso); ?>"><?php echo esc_html($pub_fecha); ?></time></span>
				<span><i class="ph ph-arrows-clockwise" aria-hidden="true"></i>Actualizada el <time datetime="<?php echo esc_attr($mod_iso); ?>"><?php echo esc_html($mod_fecha); ?></time></span>
			</p>
		</header>
		<?php if ($imagen) : ?><figure class="gu-portada"><img src="<?php echo esc_url($imagen); ?>" alt="" loading="eager"></figure><?php endif; ?>

		<div class="gu-cuerpo"><?php the_content(); ?></div>

		<aside class="gu-autor" aria-labelledby="gu-autor-t">
			<i class="ph ph-user-circle gu-autor-ico" aria-hidden="true"></i>
			<div>
				<h2 id="gu-autor-t"><?php echo esc_html($autor['nombre']); ?></h2>
				<p><?php echo esc_html($autor['bio']); ?></p>
			</div>
		</aside>

		<section class="gu-cta" aria-labelledby="gu-cta-t">
			<h2 id="gu-cta-t"><i class="ph ph-calendar-check" aria-hidden="true"></i>¿Quieres aplicarlo en tu negocio?</h2>
			<p>En 15 a 30 minutos revisamos tu caso y te decimos qué conviene hacer primero. Sin costo y sin compromiso.</p>
			<?php echo at_guias_boton_agenda(); ?>
		</section>
	</article>
</main>
<?php echo $abajo; ?>
<?php at_guias_scripts_pie(); ?>
<?php wp_footer(); ?>
</body>
</html>
