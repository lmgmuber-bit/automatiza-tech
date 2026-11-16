<?php
/**
 * Plugin Name: AT — Informes semanales por el SMTP del servidor
 * Description: Ruta POST /automatiza-tech/v1/informes/enviar (X-AT-Secret) para que la reunión semanal mande sus informes con PDF adjunto desde la cuenta de AutomatizaTech. Solo envía a la lista de la opción at_informes_destinos.
 * Para apagarlo del todo, borrar este archivo: la ruta deja de existir.
 */
if (!defined('ABSPATH')) { exit; }
$at_inf_modulo = WP_CONTENT_DIR . '/themes/automatiza-tech/inc/informes-correo/cargar.php';
if (is_readable($at_inf_modulo)) {
	require_once $at_inf_modulo;
}
unset($at_inf_modulo);
