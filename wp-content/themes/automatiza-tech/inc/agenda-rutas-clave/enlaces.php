<?php
/**
 * Agenda de seguimiento: guarda de los enlaces de correo (confirmar, reagendar, cancelar).
 * Va aparte de puras.php porque dibuja la página de WordPress.
 */
if (!defined('ABSPATH')) {
	exit;
}

/**
 * true si el enlace sirve. Si no, dibuja la página de «enlace vencido» (403) y devuelve false: quien llama hace exit.
 * Los enlaces sin firma se aceptan solo durante la gracia y quedan anotados.
 */
function at_agenda_guardia_enlace($request, int $id, string $accion): bool {
	$ev = at_agenda_evaluar_enlace($id, $accion, $request->get_param('e'), $request->get_param('t'));
	if ($ev['estado'] === 'sin_firma_en_gracia') {
		error_log("agenda-rutas: enlace sin firma aceptado en gracia accion={$accion} id={$id}");
	}
	if ($ev['permitir']) {
		return true;
	}
	error_log("agenda-rutas: enlace rechazado estado={$ev['estado']} accion={$accion} id={$id}");
	if (function_exists('status_header')) {
		status_header(403);
	}
	header('Content-Type: text/html; charset=UTF-8');
	[$titulo, $mensaje] = at_agenda_texto_enlace_vencido();
	automatiza_tech_render_followup_action_page(
		'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png',
		get_bloginfo('name'),
		home_url(),
		$titulo,
		$mensaje,
		'warning'
	);
	return false;
}
