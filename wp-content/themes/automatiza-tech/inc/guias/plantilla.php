<?php
/** Piezas compartidas por archive-guia.php y single-guia.php: <head> propio y armazón (encabezado, pie y modal de agenda). */
if (!defined('ABSPATH')) { exit; }
require_once get_template_directory() . '/inc/at-servicios.php';

/**
 * Imprime el contenido del <head>. $m: titulo, descripcion, url, tipo (website|article), imagen (opcional), jsonld (lista de arrays).
 * La plantilla es la única dueña de título, descripción y canonical (inc/seo-tecnico/cargar.php quita los de WordPress).
 */
function at_guias_head(array $m): void {
	$hp  = get_template_directory_uri() . '/assets/home-premium';
	$img = $hp . '/img';
	$dir = get_template_directory();
	$ver = static function (string $ruta) use ($dir) { return @filemtime($dir . $ruta) ?: '1'; };
	$imagen = !empty($m['imagen']) ? $m['imagen'] : AT_GUIAS_LOGO;
	?>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($m['titulo']); ?></title>
<meta name="description" content="<?php echo esc_attr($m['descripcion']); ?>">
<link rel="canonical" href="<?php echo esc_url($m['url']); ?>">
<meta property="og:type" content="<?php echo esc_attr($m['tipo']); ?>">
<meta property="og:locale" content="es_CL">
<meta property="og:site_name" content="AutomatizaTech">
<meta property="og:title" content="<?php echo esc_attr($m['titulo']); ?>">
<meta property="og:description" content="<?php echo esc_attr($m['descripcion']); ?>">
<meta property="og:url" content="<?php echo esc_url($m['url']); ?>">
<meta property="og:image" content="<?php echo esc_url($imagen); ?>">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($img); ?>/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<?php foreach ($m['jsonld'] as $j) { echo '<script type="application/ld+json">' . at_guias_json($j) . "</script>\n"; } ?>
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url($hp); ?>/at-home.css?v=<?php echo $ver('/assets/home-premium/at-home.css'); ?>">
<link rel="stylesheet" href="<?php echo esc_url(get_template_directory_uri()); ?>/assets/guias/guias.css?v=<?php echo $ver('/assets/guias/guias.css'); ?>">
<?php
}

/** Devuelve el armazón de la página: [encabezado, pie con modal de agenda]. */
function at_guias_armazon(): array {
	$dir = get_template_directory();
	$img = get_template_directory_uri() . '/assets/home-premium/img';
	$arma = static function (string $archivo) use ($dir, $img) {
		$h = @file_get_contents($dir . '/assets/guias/' . $archivo);
		if ($h === false) { return '<!-- guias: ' . esc_html($archivo) . ' no encontrado -->'; }
		$h = str_replace('assets/', $img . '/', $h);
		$h = str_replace('{{HOME_URL}}', esc_url(home_url('/')), $h);
		$h = str_replace('{{MENU_SERVICIOS}}', at_menu_servicios_html(''), $h);
		$h = str_replace('{{MENU_SERVICIOS_MOVIL}}', at_menu_servicios_movil_html(''), $h);
		$h = str_replace('{{GUIAS_PIE}}', at_guias_pie_html(), $h);
		return str_replace('{{PREFERENCIAS_COOKIES}}', at_preferencias_cookies_html(), $h);
	};
	return [$arma('shell-top.html'), $arma('shell-bottom.html') . at_menu_servicios_extras()];
}

/** Configuración que at-home.js y at-agenda.js esperan, igual que en las landings. */
function at_guias_scripts_cabecera(): void {
	$img = get_template_directory_uri() . '/assets/home-premium/img';
	?>
<script>window.AT_IMG = <?php echo wp_json_encode($img); ?>;</script>
<script>
window.AT_HOME = <?php echo wp_json_encode([
	'bookingWebhookUrl' => 'https://n8n-n8n.kchiba.easypanel.host/webhook/becd5a16-7b3a-4961-8a2c-e86ca01d069e',
	'availabilityUrl'   => esc_url_raw(rest_url('automatiza-tech/v1/check-availability')),
	'attrAjaxUrl'       => admin_url('admin-ajax.php'),
	'attrNonce'         => wp_create_nonce('at_home_premium_attr'),
	'landing'           => 'guias',
]); ?>;
</script>
<script>window.AT_AGENDA = { configUrl: <?php echo wp_json_encode(esc_url_raw(rest_url('automatiza-tech/v1/appointments-config'))); ?> };</script>
<?php
}

function at_guias_scripts_pie(): void {
	$dir = get_template_directory();
	$hp  = get_template_directory_uri() . '/assets/home-premium';
	foreach (['at-home.js', 'at-agenda.js'] as $js) {
		echo '<script src="' . esc_url($hp . '/' . $js) . '?v=' . (@filemtime($dir . '/assets/home-premium/' . $js) ?: '1') . '" defer></script>' . "\n";
	}
}

/** Botón que abre el modal de agenda (at-home.js lo engancha por data-act). */
function at_guias_boton_agenda(string $clase = 'gu-btn'): string {
	return '<a href="#contacto" data-act="openModal" class="' . esc_attr($clase) . '"><i class="ph ph-calendar-check" aria-hidden="true"></i>Agenda tu diagnóstico gratis<i class="ph ph-arrow-right" aria-hidden="true"></i></a>';
}
