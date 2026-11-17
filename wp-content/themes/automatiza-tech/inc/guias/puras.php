<?php
/**
 * Guías de AutomatizaTech: funciones puras, sin WordPress (prueba: tests/guias/puras-test.php).
 * Cubren datos estructurados (JSON-LD), migas de pan, fechas en español y el resumen para la meta description.
 */

const AT_GUIAS_BASE = 'https://automatizatech.cl';
const AT_GUIAS_LOGO = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';

/** Autor de las guías. La biografía la aprueba Luis antes de publicar la primera guía. */
function at_guias_autor(): array {
	return [
		'nombre' => 'Luis Miguel González',
		'url'    => AT_GUIAS_BASE . '/',
		'bio'    => 'Luis Miguel González encabeza AutomatizaTech, un equipo chileno de estrategia, diseño, desarrollo e IA. Ayuda a pymes de Chile a conectar WhatsApp, CRM, agenda y pagos, y a construir sitios y sistemas a medida.',
	];
}

/** «2026-11-16» o «2026-11-16T10:00:00-03:00» → «16 de noviembre de 2026». Vacío si no es una fecha válida. */
function at_guias_fecha_es(string $iso): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) { return ''; }
	$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	return (int) $m[3] . ' de ' . $meses[(int) $m[2] - 1] . ' de ' . $m[1];
}

/** Texto plano de máximo $max caracteres, cortado en palabra completa, para la meta description. */
function at_guias_resumen(string $texto, int $max = 160): string {
	$t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	if (mb_strlen($t) <= $max) { return $t; }
	$corte = mb_substr($t, 0, $max - 1);
	$sp = mb_strrpos($corte, ' ');
	if ($sp !== false && $sp > $max * 0.6) { $corte = mb_substr($corte, 0, $sp); }
	return rtrim($corte, " ,;:.-") . '…';
}

/** Migas: Inicio › Guías [› título]. Cada miga trae nombre y url (la última, la página actual, también). */
function at_guias_migas(string $titulo = '', string $url_guia = ''): array {
	$m = [
		['nombre' => 'Inicio', 'url' => AT_GUIAS_BASE . '/'],
		['nombre' => 'Guías',  'url' => AT_GUIAS_BASE . '/guias/'],
	];
	if ($titulo !== '') { $m[] = ['nombre' => $titulo, 'url' => $url_guia !== '' ? $url_guia : AT_GUIAS_BASE . '/guias/']; }
	return $m;
}

/** Serializa JSON-LD seguro para ir dentro de <script type="application/ld+json">. */
function at_guias_json(array $datos): string {
	return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);
}

function at_guias_jsonld_migas(array $migas): array {
	$items = [];
	foreach (array_values($migas) as $i => $m) {
		$items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $m['nombre'], 'item' => $m['url']];
	}
	return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/** @param array $d titulo, descripcion, publicado, modificado (ISO 8601), url y, opcional, imagen. */
function at_guias_jsonld_articulo(array $d): array {
	$autor = at_guias_autor();
	$a = [
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => $d['titulo'],
		'description'      => $d['descripcion'],
		'datePublished'    => $d['publicado'],
		'dateModified'     => $d['modificado'] !== '' ? $d['modificado'] : $d['publicado'],
		'inLanguage'       => 'es-CL',
		'author'           => ['@type' => 'Person', 'name' => $autor['nombre'], 'url' => $autor['url']],
		'publisher'        => [
			'@type' => 'Organization',
			'name'  => 'AutomatizaTech',
			'url'   => AT_GUIAS_BASE . '/',
			'logo'  => ['@type' => 'ImageObject', 'url' => AT_GUIAS_LOGO],
		],
		'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $d['url']],
	];
	if (!empty($d['imagen'])) { $a['image'] = [$d['imagen']]; }
	return $a;
}

/** @param array $items cada uno con titulo y url. */
function at_guias_jsonld_coleccion(string $url, string $titulo, string $descripcion, array $items): array {
	$lista = [];
	foreach (array_values($items) as $i => $it) {
		$lista[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it['titulo'], 'url' => $it['url']];
	}
	return [
		'@context'    => 'https://schema.org',
		'@type'       => 'CollectionPage',
		'name'        => $titulo,
		'description' => $descripcion,
		'url'         => $url,
		'inLanguage'  => 'es-CL',
		'isPartOf'    => ['@type' => 'WebSite', 'name' => 'AutomatizaTech', 'url' => AT_GUIAS_BASE . '/'],
		'mainEntity'  => ['@type' => 'ItemList', 'itemListElement' => $lista],
	];
}
