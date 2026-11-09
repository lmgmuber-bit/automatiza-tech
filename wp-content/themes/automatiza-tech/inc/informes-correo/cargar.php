<?php
/**
 * Informes semanales del equipo AT por el SMTP del servidor (pedido de Luis, 31-oct-2026): la reunión semanal de los
 * lunes manda su informe a Luis, a Adriana y a Julio desde la cuenta de AutomatizaTech, con el PDF adjunto.
 *
 * POST /automatiza-tech/v1/informes/enviar  · cabecera X-AT-Secret = AT_REST_SECRET
 * Cuerpo JSON: {para, cc?, asunto, html, texto?, pdf_base64?, pdf_nombre?, responder_a?}
 * Destinatarios permitidos: opción at_informes_destinos (lista en la base, no en el repo público).
 * Reply-To permitido: opción at_informes_responder_a. Tope: 30 envíos por hora.
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/puras.php';

const AT_INF_TOPE_HORA = 30;

/** Misma regla que at_fin_auth: cabecera X-AT-Secret igual a AT_REST_SECRET. */
function at_inf_auth(WP_REST_Request $r): bool {
	$cabecera = $r->get_header('x-at-secret');
	return defined('AT_REST_SECRET') && AT_REST_SECRET !== '' && is_string($cabecera) && hash_equals(AT_REST_SECRET, $cabecera);
}

function at_inf_api_enviar(WP_REST_Request $r): WP_REST_Response {
	$enviados = (int) get_transient('at_inf_envios_hora');
	if ($enviados >= AT_INF_TOPE_HORA) {
		return new WP_REST_Response(['code' => 'tope', 'message' => 'Se alcanzó el tope de envíos de la hora.'], 429);
	}
	$v = at_inf_validar((array) $r->get_json_params(), (array) get_option('at_informes_destinos', []), (string) get_option('at_informes_responder_a', ''));
	if (!$v['ok']) {
		return new WP_REST_Response(['code' => 'datos_invalidos', 'message' => 'Datos no válidos.', 'errores' => $v['errores']], 422);
	}
	$d = $v['datos'];

	// El PDF va a un archivo temporal con su nombre final (PHPMailer usa el nombre del archivo) y se borra al terminar.
	$adjuntos = [];
	$carpeta = null;
	if ($d['pdf'] !== null) {
		$carpeta = trailingslashit(get_temp_dir()) . 'at-informe-' . wp_generate_password(12, false, false);
		if (!wp_mkdir_p($carpeta) || file_put_contents($carpeta . '/' . $d['pdf_nombre'], $d['pdf']) === false) {
			return new WP_REST_Response(['code' => 'temporal', 'message' => 'No se pudo preparar el adjunto.'], 500);
		}
		$adjuntos[] = $carpeta . '/' . $d['pdf_nombre'];
	}

	$cabeceras = ['Content-Type: text/html; charset=UTF-8'];
	foreach ($d['cc'] as $copia) { $cabeceras[] = 'Cc: ' . $copia; }
	if ($d['responder_a'] !== '') { $cabeceras[] = 'Reply-To: ' . $d['responder_a']; }

	$texto = $d['texto'];
	$alternativo = static function ($mailer) use ($texto) { if ($texto !== '') { $mailer->AltBody = $texto; } };
	$motivo = '';
	$falla = static function ($error) use (&$motivo) { $motivo = is_wp_error($error) ? $error->get_error_message() : ''; };
	add_action('phpmailer_init', $alternativo);
	add_action('wp_mail_failed', $falla);
	try {
		$ok = wp_mail($d['para'], $d['asunto'], $d['html'], $cabeceras, $adjuntos);
	} finally {
		remove_action('phpmailer_init', $alternativo);
		remove_action('wp_mail_failed', $falla);
		foreach ($adjuntos as $archivo) { @unlink($archivo); }
		if ($carpeta) { @rmdir($carpeta); }
	}
	set_transient('at_inf_envios_hora', $enviados + 1, HOUR_IN_SECONDS);

	if (!$ok) {
		return new WP_REST_Response(['code' => 'no_enviado', 'message' => 'El servidor no pudo enviar el correo.' . ($motivo !== '' ? ' ' . mb_substr($motivo, 0, 160) : '')], 502);
	}
	return new WP_REST_Response(['enviado' => true, 'con_pdf' => $carpeta !== null], 200);
}

add_action('rest_api_init', static function () {
	register_rest_route('automatiza-tech/v1', '/informes/enviar', [
		'methods' => 'POST',
		'callback' => 'at_inf_api_enviar',
		'permission_callback' => 'at_inf_auth',
	]);
});
