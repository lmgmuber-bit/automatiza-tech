<?php
/**
 * Medición con GA4 y Google Ads (lo incluye functions.php). Los IDs no son secretos (salen en el
 * código de la página) y viven en dos opciones de WordPress, para no tocar código al cambiarlos:
 *   wp option update at_ga4_measurement_id G-XXXXXXXXXX
 *   wp option update at_google_ads_id AW-XXXXXXXXXX
 * Sin opciones válidas no se imprime nada.
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/puras.php';

function at_med_ids_configurados(): array {
	return at_med_ids((string) get_option('at_ga4_measurement_id', ''), (string) get_option('at_google_ads_id', ''));
}

add_action('wp_head', function () {
	echo at_med_script_cabecera(at_med_ids_configurados());
}, 2);

/** Después de at-consentimiento.php (prioridad 99), que define window.atLead. */
add_action('wp_footer', function () {
	if (at_med_ids_configurados()) {
		echo at_med_script_lead();
	}
}, 100);
