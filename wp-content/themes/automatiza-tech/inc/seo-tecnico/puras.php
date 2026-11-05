<?php
/**
 * SEO técnico de automatizatech.cl sin acceso a WordPress (prueba: tests/seo-tecnico/puras-test.php).
 * Origen: prueba manual del 5-nov-2026 (títulos, descripciones y canonical duplicados; sitemap con
 * relleno de la instalación; robots.txt con Sitemap repetido y una línea LLMS que no es estándar).
 */

/**
 * Plantillas que escriben su propio <title>, descripción y canonical en el <head> (o, el home, su
 * canonical en automatiza_tech_home_premium_head). En ellas WordPress no debe imprimir los suyos.
 */
function at_seo_plantillas_propias(): array {
	return [
		'template-home-premium.php',
		'template-landing-asistentes-ia.php',
		'template-landing-automatizacion.php',
		'template-landing-sitios-premium.php',
		'template-landing-apps-web.php',
		'template-landing-apps-moviles.php',
		'template-landing-sistemas-medida.php',
		'template-landing-portal-omnicliente.php',
		'template-legal.php',
	];
}

/**
 * Contenido que no va al sitemap ni al índice: el relleno de la instalación de WordPress y la página
 * técnica que valida facturas. Se quita del sitemap sin despublicarlo ni borrarlo (eso lo decide Luis).
 * Clave = tipo de entrada, valor = slugs.
 */
function at_seo_excluidos(): array {
	return [
		'post' => ['hola-mundo'],
		'page' => ['pagina-ejemplo', 'validar-factura'],
	];
}

/** Proveedores del sitemap de WordPress que se apagan. `users` publica el usuario del autor. */
function at_seo_proveedor_apagado(string $nombre): bool {
	return $nombre === 'users';
}

/**
 * Rastreadores con IA por función, según el Estándar AT de visibilidad web e IA (Shared-Tools/Estandar-Visibilidad-Web-IA.md;
 * nombres publicados por OpenAI y Anthropic): búsqueda y acceso pedido por una persona, separados del entrenamiento.
 */
function at_seo_bots_busqueda(): array {
	return ['OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User'];
}

function at_seo_bots_entrenamiento(): array {
	return ['GPTBot', 'ClaudeBot', 'Google-Extended'];
}

/**
 * Líneas que se agregan al robots.txt virtual de WordPress. El núcleo ya escribe el grupo `*` y la
 * línea Sitemap (WP_Sitemaps::add_robots), así que aquí no se repite.
 *
 * @param bool   $publico            blog_public: si el sitio pide no indexarse, no se agrega nada.
 * @param bool   $bloquea_entrenamiento true = GPTBot, ClaudeBot y Google-Extended reciben Disallow: /.
 * @param string $llms               URL del llms.txt; va como comentario (LLMS: no es una directiva).
 */
function at_seo_robots_extra(bool $publico, bool $bloquea_entrenamiento, string $llms): string {
	if (!$publico) { return ''; }
	$t  = "\n# Buscadores con IA y visitas pedidas por una persona: mismo acceso que el resto.\n";
	foreach (at_seo_bots_busqueda() as $bot) { $t .= "User-agent: {$bot}\n"; }
	$t .= "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
	if ($bloquea_entrenamiento) {
		$t .= "\n# Entrenamiento de modelos: no autorizado. No afecta a Google Search ni a la búsqueda con IA.\n";
		foreach (at_seo_bots_entrenamiento() as $bot) { $t .= "User-agent: {$bot}\n"; }
		$t .= "Disallow: /\n";
	}
	if ($llms !== '') { $t .= "\n# Índice para asistentes de IA: {$llms}\n"; }
	return $t;
}
