<?php
/** SEO técnico: un solo título, descripción y canonical por página, sitemap limpio y robots.txt. Lo incluye functions.php. */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/puras.php';

/**
 * Decisión de Luis (5-nov-2026): GPTBot, ClaudeBot y Google-Extended no leen el sitio para entrenar.
 * No afecta a Googlebot, Bingbot ni a la búsqueda con IA. false = vuelven a quedar bajo el grupo `*`.
 */
function at_seo_bloquea_entrenamiento(): bool {
	return true;
}

/** En las plantillas con <head> propio, WordPress no imprime un segundo <title> ni un segundo canonical. */
add_action('template_redirect', function () {
	if (is_page_template(at_seo_plantillas_propias())) {
		remove_action('wp_head', '_wp_render_title_tag', 1);
		remove_action('wp_head', 'rel_canonical');
	}
});

/** IDs de lo excluido del sitemap, por tipo de entrada. */
function at_seo_ids_excluidos(string $tipo): array {
	static $ids = [];
	if (!isset($ids[$tipo])) {
		$ids[$tipo] = [];
		foreach (at_seo_excluidos()[$tipo] ?? [] as $slug) {
			$p = get_page_by_path($slug, OBJECT, $tipo);
			if ($p) { $ids[$tipo][] = (int) $p->ID; }
		}
	}
	return $ids[$tipo];
}

add_filter('wp_sitemaps_add_provider', function ($proveedor, $nombre) {
	return at_seo_proveedor_apagado((string) $nombre) ? false : $proveedor;
}, 10, 2);

add_filter('wp_sitemaps_posts_query_args', function ($args, $tipo) {
	$fuera = at_seo_ids_excluidos((string) $tipo);
	if ($fuera) {
		$args['post__not_in'] = array_merge($args['post__not_in'] ?? [], $fuera);
	}
	return $args;
}, 10, 2);

/** «Sin categoría» es la categoría por defecto de WordPress: no es una sección del sitio. */
add_filter('wp_sitemaps_taxonomies_query_args', function ($args, $taxonomia) {
	if ($taxonomia === 'category') {
		$args['exclude'] = array_merge((array) ($args['exclude'] ?? []), [(int) get_option('default_category')]);
	}
	return $args;
}, 10, 2);

/** Lo que sale del sitemap tampoco se indexa: el relleno, la categoría por defecto y el archivo de autor. */
add_filter('wp_robots', function ($robots) {
	$fuera = is_author()
		|| is_category((int) get_option('default_category'))
		|| (is_singular() && in_array((int) get_queried_object_id(), at_seo_ids_excluidos((string) get_post_type()), true));
	if ($fuera) {
		$robots['noindex'] = true;
		$robots['follow'] = true;
	}
	return $robots;
});

add_filter('robots_txt', function ($salida, $publico) {
	return $salida . at_seo_robots_extra((bool) $publico, at_seo_bloquea_entrenamiento(), home_url('/llms.txt'));
}, 10, 2);
