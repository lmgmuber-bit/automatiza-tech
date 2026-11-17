<?php
/**
 * Casilla anti-robot de Cloudflare Turnstile en los formularios de agenda (16-nov-2026).
 *
 * Carga assets/js/at-turnstile.js en todas las páginas públicas con la clave del sitio, que es pública. La clave
 * secreta no vive en WordPress: la usa n8n («Agente Tech Web (PROD)», nodo «Verificar Turnstile») para revisar el
 * token antes de guardar la cita.
 *
 * Fuera de automatizatech.cl (copias locales o de prueba) usa la clave de prueba oficial de Cloudflare, que siempre
 * pasa, porque la real solo funciona en los dominios autorizados del widget.
 * La opción at_turnstile_sitekey permite cambiar la clave sin tocar código; vacía = la de abajo.
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_TURNSTILE_SITEKEY = '0x4AAAAAAGEZRAJNqT7xuy97';
const AT_TURNSTILE_SITEKEY_PRUEBA = '1x00000000000000000000AA';

/** Clave del sitio que corresponde al host. */
function at_turnstile_sitekey(string $host): string {
	$host = strtolower(preg_replace('/:\d+$/', '', trim($host)));
	if (!in_array($host, ['automatizatech.cl', 'www.automatizatech.cl'], true)) {
		return AT_TURNSTILE_SITEKEY_PRUEBA;
	}
	$opcion = function_exists('get_option') ? trim((string) get_option('at_turnstile_sitekey', '')) : '';
	return $opcion !== '' ? $opcion : AT_TURNSTILE_SITEKEY;
}

function at_turnstile_scripts(): void {
	if (is_admin()) {
		return;
	}
	$ruta = get_template_directory() . '/assets/js/at-turnstile.js';
	wp_enqueue_script(
		'at-turnstile',
		get_template_directory_uri() . '/assets/js/at-turnstile.js',
		[],
		file_exists($ruta) ? (string) filemtime($ruta) : '1',
		true
	);
	$host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
	wp_add_inline_script('at-turnstile', 'window.AT_TURNSTILE = ' . wp_json_encode(['sitekey' => at_turnstile_sitekey($host)]) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'at_turnstile_scripts', 5);
