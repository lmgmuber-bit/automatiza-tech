<?php
/**
 * Listado de guías: /guias/. <head> propio (mismo patrón que las landings): no llama get_header()/get_footer().
 */
if (!defined('ABSPATH')) { exit; }

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

$pagina = max(1, (int) get_query_var('paged'));
$url    = $pagina > 1 ? home_url('/guias/page/' . $pagina . '/') : home_url('/guias/');
$titulo = 'Guías para pymes en Chile: automatización, web y leyes | AutomatizaTech';
$desc   = 'Guías claras y prácticas de AutomatizaTech para pymes de Chile: automatización, WhatsApp, sitios web y cumplimiento legal digital.';
if ($pagina > 1) { $titulo = 'Guías, página ' . $pagina . ' | AutomatizaTech'; }

$items = [];
while (have_posts()) {
	the_post();
	$items[] = [
		'titulo'  => get_the_title(),
		'url'     => get_permalink(),
		'extracto'=> at_guias_resumen(has_excerpt() ? get_the_excerpt() : get_the_content(), 180),
		'iso'     => get_post_time('Y-m-d', false),
		'fecha'   => at_guias_fecha_es((string) get_post_time('Y-m-d', false)),
		'imagen'  => get_the_post_thumbnail_url(get_the_ID(), 'medium_large') ?: '',
	];
}
rewind_posts();

$jsonld = [at_guias_jsonld_migas(at_guias_migas())];
if ($items) {
	$jsonld[] = at_guias_jsonld_coleccion($url, 'Guías de AutomatizaTech', $desc, array_map(static function ($i) { return ['titulo' => $i['titulo'], 'url' => $i['url']]; }, $items));
}
[$arriba, $abajo] = at_guias_armazon();
$paginacion = paginate_links([
	'type' => 'array', 'prev_text' => '<i class="ph ph-caret-right gu-ico-prev" aria-hidden="true"></i><span class="gu-sr">Anterior</span>',
	'next_text' => '<span class="gu-sr">Siguiente</span><i class="ph ph-caret-right" aria-hidden="true"></i>',
]);
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<?php at_guias_head(['titulo' => $titulo, 'descripcion' => $desc, 'url' => $url, 'tipo' => 'website', 'jsonld' => $jsonld]); ?>
</head>
<body <?php body_class('at-home-premium at-guias'); ?>>
<?php at_guias_scripts_cabecera(); ?>
<a class="gu-salto" href="#contenido">Saltar al contenido</a>
<?php echo $arriba; ?>
<main id="contenido" class="gu-main">
	<div class="gu-wrap">
		<nav class="gu-migas" aria-label="Migas de pan">
			<ol>
				<li><a href="<?php echo esc_url(home_url('/')); ?>"><i class="ph ph-house" aria-hidden="true"></i>Inicio</a></li>
				<li><i class="ph ph-caret-right gu-sep" aria-hidden="true"></i><span aria-current="page">Guías</span></li>
			</ol>
		</nav>
		<header class="gu-cab">
			<span class="gu-etiqueta"><i class="ph ph-book-open" aria-hidden="true"></i>GUÍAS</span>
			<h1>Guías para que tu negocio use mejor la tecnología</h1>
			<p class="gu-lead">Explicaciones claras, sin letra chica, sobre automatización, WhatsApp, sitios web y las normas que afectan a una pyme en Chile.</p>
		</header>

		<?php if ($items) : ?>
		<ul class="gu-lista">
			<?php foreach ($items as $g) : ?>
			<li class="gu-tarjeta">
				<article>
					<?php if ($g['imagen']) : ?><a class="gu-tarjeta-img" href="<?php echo esc_url($g['url']); ?>" tabindex="-1" aria-hidden="true"><img src="<?php echo esc_url($g['imagen']); ?>" alt="" loading="lazy"></a><?php endif; ?>
					<h2><a href="<?php echo esc_url($g['url']); ?>"><?php echo esc_html($g['titulo']); ?></a></h2>
					<?php if ($g['fecha']) : ?><p class="gu-fecha"><i class="ph ph-calendar-blank" aria-hidden="true"></i><time datetime="<?php echo esc_attr($g['iso']); ?>"><?php echo esc_html($g['fecha']); ?></time></p><?php endif; ?>
					<p class="gu-extracto"><?php echo esc_html($g['extracto']); ?></p>
					<a class="gu-btn gu-btn--suave" href="<?php echo esc_url($g['url']); ?>">Leer la guía<i class="ph ph-arrow-right" aria-hidden="true"></i><span class="gu-sr">: <?php echo esc_html($g['titulo']); ?></span></a>
				</article>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php if ($paginacion) : ?>
		<nav class="gu-paginas" aria-label="Páginas de guías"><?php echo implode("\n", $paginacion); ?></nav>
		<?php endif; ?>
		<?php else : ?>
		<section class="gu-vacio" aria-labelledby="gu-vacio-t">
			<i class="ph ph-newspaper gu-vacio-ico" aria-hidden="true"></i>
			<h2 id="gu-vacio-t">Pronto publicaremos la primera guía</h2>
			<p>Estamos terminando la primera guía. Mientras tanto, cuéntanos qué necesita tu negocio y te orientamos sin costo.</p>
			<?php echo at_guias_boton_agenda(); ?>
		</section>
		<?php endif; ?>
	</div>
</main>
<?php echo $abajo; ?>
<?php at_guias_scripts_pie(); ?>
<?php wp_footer(); ?>
</body>
</html>
