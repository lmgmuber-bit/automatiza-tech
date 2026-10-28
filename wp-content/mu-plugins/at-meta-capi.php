<?php
/**
 * Plugin Name: AT — Lead por la API de Conversiones de Meta
 * Description: Manda a Meta un evento Lead por cada primera agenda de /leads (web, Instagram y WhatsApp). Inerte hasta que la opción at_capi_activo valga 1 y PixelYourSite tenga token.
 * Para apagarlo del todo, borrar este archivo: la acción de /leads queda sin oyentes.
 */
if (!defined('ABSPATH')) { exit; }
$at_capi_modulo = WP_CONTENT_DIR . '/themes/automatiza-tech/inc/meta-capi/cargar.php';
if (is_readable($at_capi_modulo)) {
	require_once $at_capi_modulo;
}
unset($at_capi_modulo);
