<?php
/**
 * Guías de AutomatizaTech (/guias/): tipo de contenido propio, plantillas con <head> propio y estilos.
 * Guía de uso y de subida: Docs/GUIAS-AT.md. Lo incluye functions.php.
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/puras.php';
require_once __DIR__ . '/plantilla.php';

add_action('init', function () {
	register_post_type('guia', [
		'labels' => [
			'name' => 'Guías', 'singular_name' => 'Guía', 'add_new' => 'Agregar guía', 'add_new_item' => 'Agregar guía',
			'edit_item' => 'Editar guía', 'new_item' => 'Nueva guía', 'view_item' => 'Ver guía', 'search_items' => 'Buscar guías',
			'not_found' => 'No hay guías', 'all_items' => 'Todas las guías', 'menu_name' => 'Guías',
		],
		'public'        => true,
		'has_archive'   => 'guias',
		'rewrite'       => ['slug' => 'guias', 'with_front' => false],
		'show_in_rest'  => true, // editor de bloques
		'menu_icon'     => 'dashicons-book-alt',
		'menu_position' => 21,
		'supports'      => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author'],
	]);
});

/** Respaldo: la primera vez que carga el código nuevo, WordPress aprende las rutas /guias/ solo (igual se documenta `wp rewrite flush`). */
add_action('init', function () {
	if (get_option('at_guias_rewrite') !== '1') {
		flush_rewrite_rules(false);
		update_option('at_guias_rewrite', '1', false);
	}
}, 99);

function at_guias_es_pagina(): bool {
	return is_post_type_archive('guia') || is_singular('guia');
}

/** Íconos Phosphor 2.1.1 alojados en el tema: solo en las páginas de guías (el enlace «Guías» del menú va en SVG). */
add_action('wp_head', function () {
	if (!at_guias_es_pagina()) { return; }
	$d = get_template_directory_uri() . '/assets/vendor/phosphor/2.1.1';
	echo '<link rel="preload" href="' . esc_url($d . '/Phosphor.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
	echo '<link rel="stylesheet" href="' . esc_url($d . '/phosphor.css') . '">' . "\n";
}, 3);

/** Mientras no haya ninguna guía publicada, /guias/ no se indexa (se abre sola al publicar la primera). */
add_filter('wp_robots', function ($robots) {
	if (is_post_type_archive('guia') && !have_posts()) {
		$robots['noindex'] = true;
		$robots['follow'] = true;
	}
	return $robots;
});
