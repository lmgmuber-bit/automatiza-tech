<?php
/**
 * Plan de trabajo, Etapa 2: agenda web de la llamada de seguimiento desde ver-plan.php.
 *   POST /wp-json/automatiza-tech/v1/plan-seguimiento  {codigo, token, fecha, hora, sitio_web} → {ok, clave, mensaje}
 * Crea una reunión de SEGUIMIENTO (wp_automatiza_followup_meetings) con los datos del cliente que salen del plan y su
 * evento en Google Calendar con Meet (automatiza_tech_create_followup_calendar_event). Nunca toca wp_automatiza_leads
 * (decisión 2). Pública: token del plan (at_pt_token_agenda), plan «enviado», campo trampa vacío, 5 intentos por IP y
 * por hora, y una sola llamada futura por plan.
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_PT_AGENDA_INTENTOS_HORA = 5;
const AT_PT_AGENDA_ESPERA_LOCK = 5; // segundos que espera el candado global de la agenda
const AT_PT_AGENDA_LOCK = 'at_pt_agenda_seguimiento';

add_action('rest_api_init', function () {
	register_rest_route('automatiza-tech/v1', '/plan-seguimiento', [
		'methods'             => 'POST',
		'callback'            => 'at_pt_rest_agendar',
		'permission_callback' => '__return_true', // pública: la protegen el token, el estado del plan y el límite por IP
	]);
});

/** Token del plan para hoy (lo escribe ver-plan.php en la página). */
function at_pt_token_agenda_hoy(string $codigo): string {
	return at_pt_token_agenda($codigo, intdiv(time(), 86400), wp_salt('nonce'));
}

function at_pt_rest_agendar(WP_REST_Request $r): WP_REST_Response {
	$cuerpo = $r->get_json_params();
	$res = at_pt_agendar_seguimiento(is_array($cuerpo) ? $cuerpo : [], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	return new WP_REST_Response(['ok' => $res['ok'], 'clave' => $res['clave'], 'mensaje' => $res['mensaje']], $res['estado_http']);
}

/** Respuesta de la agenda con el texto de at_pt_mensajes_agenda() (o $mensaje si viene). */
function at_pt_respuesta_agenda(bool $ok, string $clave, int $http, int $reunion = 0, string $mensaje = ''): array {
	return [
		'ok'          => $ok,
		'clave'       => $clave,
		'mensaje'     => $mensaje !== '' ? $mensaje : (at_pt_mensajes_agenda()[$clave] ?? at_pt_mensajes_agenda()['no_guardo']),
		'reunion_id'  => $reunion,
		'estado_http' => $http,
	];
}

/** Reunión de seguimiento futura ya agendada para este plan (la nota parte con «Plan de trabajo <código>»), o null. */
function at_pt_seguimiento_pendiente(string $codigo): ?object {
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_followup_meetings';
	$f = $wpdb->get_row($wpdb->prepare(
		"SELECT id, meeting_date, meeting_time FROM $t WHERE notes LIKE %s AND status = 'scheduled' AND meeting_date >= %s ORDER BY meeting_date, meeting_time LIMIT 1",
		'Plan de trabajo ' . $wpdb->esc_like($codigo) . '%',
		current_time('Y-m-d')
	));
	return $f ?: null;
}

/**
 * Agenda la llamada de seguimiento de un plan enviado. $entrada = {codigo, token, fecha, hora, sitio_web}; $ip para el
 * límite de intentos. Devuelve ['ok', 'clave', 'mensaje', 'reunion_id', 'estado_http'].
 */
function at_pt_agendar_seguimiento(array $entrada, string $ip): array {
	$texto = function (string $k) use ($entrada): string {
		return is_scalar($entrada[$k] ?? null) ? trim((string) $entrada[$k]) : '';
	};
	$codigo = $texto('codigo');
	$fila = $codigo !== '' ? at_pt_plan_por_codigo($codigo) : null;
	if (!$fila || (string) $fila->estado !== 'enviado') {
		return at_pt_respuesta_agenda(false, 'plan_no_disponible', 404);
	}
	if ($texto('sitio_web') !== '') {
		return at_pt_respuesta_agenda(false, 'no_guardo', 400); // campo trampa: un bot
	}
	if (!at_pt_token_agenda_valido($texto('token'), $codigo, intdiv(time(), 86400), wp_salt('nonce'))) {
		return at_pt_respuesta_agenda(false, 'sesion_vencida', 403);
	}
	// Límite por IP: cuenta cada intento con token válido, sirva o no la hora.
	$clave_ip = 'at_pt_agenda_' . md5($ip);
	$intentos = (int) get_transient($clave_ip);
	if ($intentos >= AT_PT_AGENDA_INTENTOS_HORA) {
		return at_pt_respuesta_agenda(false, 'muchos_intentos', 429);
	}
	set_transient($clave_ip, $intentos + 1, HOUR_IN_SECONDS);

	$d = at_pt_datos_agenda((int) $fila->id);
	if (($d['client_email'] ?? '') === '') {
		return at_pt_respuesta_agenda(false, 'sin_correo', 409);
	}
	// Candado global (un solo nombre: también impide que dos planes tomen la misma hora). Cubre solo desde «¿ya tiene
	// llamada?» hasta el INSERT; se suelta antes de llamar a Calendar/n8n y de mandar correos.
	global $wpdb;
	if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', AT_PT_AGENDA_LOCK, AT_PT_AGENDA_ESPERA_LOCK)) !== '1') {
		return at_pt_respuesta_agenda(false, 'no_guardo', 503);
	}
	$reunion = 0;
	try {
		$ya = at_pt_seguimiento_pendiente($codigo);
		if ($ya) {
			return at_pt_respuesta_agenda(false, 'ya_agendada', 409, 0, 'Ya tienes una llamada de seguimiento agendada para el '
				. at_pt_fecha_larga((string) $ya->meeting_date) . ' a las ' . substr((string) $ya->meeting_time, 0, 5) . '. Si necesitas cambiarla, escríbenos por WhatsApp.');
		}
		// El mismo máximo que aplica la reunión que crea el bot: 2 activas por correo, sumando seguimientos y demos.
		if (function_exists('automatiza_tech_limite_reuniones_activas')) {
			$limite = automatiza_tech_limite_reuniones_activas((string) $d['client_email']);
			if ($limite['alcanzado']) {
				return at_pt_respuesta_agenda(false, 'limite_reuniones', 409, 0, $limite['mensaje'] . ' Si necesitas ayuda, escríbenos por WhatsApp.');
			}
		}
		$fecha = $texto('fecha');
		$hora = substr($texto('hora'), 0, 5);
		$disp = [];
		if (function_exists('automatiza_tech_check_availability') && at_pt_ymd($fecha) !== '') {
			$pedido = new WP_REST_Request('POST', '/automatiza-tech/v1/check-availability');
			$pedido->set_param('date', $fecha);
			$res = automatiza_tech_check_availability($pedido);
			$disp = is_array($res) ? $res : [];
		}
		$motivo = at_pt_motivo_hora_agenda($fecha, $texto('hora'), current_time('Y-m-d H:i'), $disp);
		if ($motivo !== '') {
			return at_pt_respuesta_agenda(false, $motivo, in_array($motivo, ['hora_ocupada'], true) ? 409 : 400);
		}
		if (function_exists('automatiza_tech_check_slot_availability') && empty(automatiza_tech_check_slot_availability($fecha, $hora . ':00')['available'])) {
			return at_pt_respuesta_agenda(false, 'hora_ocupada', 409);
		}
		$t = $wpdb->prefix . 'automatiza_followup_meetings';
		$ok = $wpdb->insert($t, [
			'client_name'     => (string) $d['client_name'],
			'client_email'    => (string) $d['client_email'],
			'company_name'    => (string) $d['company_name'],
			'phone'           => (string) $d['phone'],
			'meeting_date'    => $fecha,
			'meeting_time'    => $hora . ':00',
			'meeting_subject' => (string) $d['meeting_subject'],
			'notes'           => (string) $d['notes'] . ' Agendada por el cliente desde el plan.',
			'status'          => 'scheduled',
		]);
		$reunion = $ok ? (int) $wpdb->insert_id : 0;
	} finally {
		$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', AT_PT_AGENDA_LOCK));
	}
	if ($reunion <= 0) {
		return at_pt_respuesta_agenda(false, 'no_guardo', 500);
	}
	// Evento en Google Calendar con Meet (n8n). Si n8n ya mandó el correo, no se repite; si no, va el de siempre.
	$cal = function_exists('automatiza_tech_create_followup_calendar_event') ? automatiza_tech_create_followup_calendar_event($reunion) : [];
	$cal = is_array($cal) ? $cal : [];
	if (!empty($cal['meet_link'])) {
		$wpdb->update($t, ['meet_link' => (string) $cal['meet_link']], ['id' => $reunion]);
	}
	// email_sent = 1 en los dos casos: si lo mandó n8n (antes quedaba en 0 aunque el correo había salido) o el de siempre.
	if (!empty($cal['email_sent'])
		|| (function_exists('automatiza_tech_send_followup_email') && automatiza_tech_send_followup_email($reunion))) {
		$wpdb->update($t, ['email_sent' => 1], ['id' => $reunion]);
	}
	$cuando = at_pt_fecha_larga($fecha) . ' a las ' . $hora;
	at_pt_historial($fila, 'reunion', 'Llamada de seguimiento agendada', 'El cliente agendó desde el plan ' . $codigo . ' la llamada del ' . $cuando . '.');
	if (function_exists('at_cc_correo_avisos')) {
		wp_mail(at_cc_correo_avisos(), '📅 ' . ($d['client_name'] !== '' ? $d['client_name'] : 'Un cliente') . ' agendó la llamada de seguimiento del plan',
			'<p>' . esc_html(($d['client_name'] ?: 'El cliente') . ($d['company_name'] !== '' ? ' (' . $d['company_name'] . ')' : '')) . ' agendó desde su plan de trabajo ' . esc_html($codigo)
			. ' la llamada de seguimiento del <strong>' . esc_html($cuando) . '</strong>.</p>'
			. '<p>' . (!empty($cal['success']) ? 'El evento quedó en Google Calendar' . (!empty($cal['meet_link']) ? ' con Meet.' : '.') : '<strong>No se pudo crear el evento en Google Calendar:</strong> créalo a mano desde «Reuniones de seguimiento».') . '</p>',
			['Content-Type: text/html; charset=UTF-8']);
	}
	return at_pt_respuesta_agenda(true, 'agendada', 200, $reunion, 'Listo: agendamos tu llamada de seguimiento para el ' . $cuando . '. Te llegará la invitación por correo.');
}
