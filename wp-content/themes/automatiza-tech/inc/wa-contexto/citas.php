<?php
/**
 * Citas del número para cancelar o reprogramar con botones por WhatsApp (diseño 2026-10-29-whatsapp-citas-botones).
 * GET /wp-json/automatiza-tech/v1/wa-citas?phone=… — solo para n8n (clave X-AT-Secret, como wa-contexto).
 * Si una lectura de la base falla, responde 503 con estado «no_disponible»: el bot avisa a Luis y nunca pide datos.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('rest_api_init', function () {
	$permiso = function (WP_REST_Request $r) {
		return function_exists('at_cc_rest_auth')
			? at_cc_rest_auth($r)
			: new WP_Error('no_autorizado', 'Clave inválida.', ['status' => 401]);
	};
	register_rest_route('automatiza-tech/v1', '/wa-citas', [
		'methods'             => 'GET',
		'callback'            => 'at_wa_rest_citas',
		'permission_callback' => $permiso,
		'args'                => ['phone' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']],
	]);
	register_rest_route('automatiza-tech/v1', '/wa-citas/cancelar', [
		'methods'             => 'POST',
		'callback'            => 'at_wa_rest_cancelar_cita',
		'permission_callback' => $permiso,
	]);
});

/**
 * Las citas activas y futuras del número: diagnósticos y seguimientos por los últimos 9 dígitos del teléfono o por los
 * correos de las fichas del número (en /leads el teléfono es opcional). Lanza AtWaConsultaNoDisponible si la base falla.
 */
function at_wa_citas(string $tel): array {
	$u9 = at_wa_ultimos9($tel);
	if ($u9 === '') {
		return [];
	}
	global $wpdb;
	$p = $wpdb->prefix;
	$correos = [];
	$empresas = [];
	foreach (at_wa_fichas_por_telefono($tel) as $f) {
		foreach (array_merge([(string) ($f['agenda']['correo'] ?? '')], (array) ($f['correos'] ?? [])) as $c) {
			$c = at_wa_correo((string) $c);
			if ($c !== '') {
				$correos[$c] = true;
				$empresas[$c] = $empresas[$c] ?? (string) ($f['agenda']['empresa'] ?? '');
			}
		}
	}
	$correos = array_keys($correos);
	$ahora = current_time('mysql');
	$leer = function (string $sql_select, string $tabla, string $col_correo, string $col_fecha, string $col_hora) use ($wpdb, $p, $u9, $correos, $ahora) {
		$donde = at_wa_sql_ultimos9('phone') . ' = %s';
		if ($correos) {
			$donde .= " OR LOWER(TRIM({$col_correo})) IN (" . at_wa_marcas(count($correos)) . ')';
		}
		// Revisión B-CITAS-08: las inactivas se descartan antes del LIMIT (50 canceladas no esconden una activa).
		$activa = "(status IS NULL OR status NOT IN ('" . implode("', '", AT_WA_CITA_INACTIVA) . "'))";
		$sql = "SELECT {$sql_select} FROM {$p}{$tabla} WHERE ({$donde}) AND {$activa} AND CONCAT({$col_fecha}, ' ', {$col_hora}) > %s ORDER BY {$col_fecha}, {$col_hora} LIMIT 50";
		return (array) at_wa_q('get_results', $wpdb->prepare($sql, $u9, ...array_merge($correos, [$ahora])));
	};
	$diagnosticos = $leer('id, name, email, scheduled_date, scheduled_time, status', 'automatiza_leads', 'email', 'scheduled_date', 'scheduled_time');
	$seguimientos = $leer('*', 'automatiza_followup_meetings', 'client_email', 'meeting_date', 'meeting_time');
	$diag = array_map(function ($l) use ($empresas) {
		$l = (array) $l;
		$l['empresa'] = $empresas[at_wa_correo((string) $l['email'])] ?? '';
		return $l;
	}, $diagnosticos);
	$seg = array_map(function ($m) {
		$fila = (array) $m;
		$fila['company'] = (string) ($fila['company_name'] ?? '');
		$fila['vinculo'] = at_wa_vinculo_texto($m);
		return $fila;
	}, $seguimientos);
	return at_wa_citas_armar($diag, $seg, current_time('Y-m-d H:i'));
}

/** La cita si está entre las activas y futuras de ese número; null si no (de otro número, pasada o cancelada). */
function at_wa_cita_del_numero(string $tel, string $tipo, int $id): ?array {
	foreach (at_wa_citas($tel) as $c) {
		if ($c['tipo'] === $tipo && $c['id'] === $id) {
			return $c;
		}
	}
	return null;
}

function at_wa_rest_citas(WP_REST_Request $r): WP_REST_Response {
	try {
		return new WP_REST_Response(['estado' => 'ok', 'citas' => at_wa_citas((string) $r->get_param('phone'))], 200);
	} catch (AtWaConsultaNoDisponible $e) {
		error_log('[wa-citas] una lectura de la base falló; las citas del número quedan no_disponible');
		return new WP_REST_Response(['estado' => 'no_disponible', 'citas' => []], 503);
	}
}

/**
 * Cancela una cita del número con todos los efectos: estado cancelado (nunca se borra la fila), evento de Google Calendar,
 * correo a la persona y aviso a Luis. $motivo: 'cancelada' o 'reprogramada'. Devuelve {ok, motivo, cita}; ok es true solo si
 * el estado quedó cancelado. Lanza AtWaConsultaNoDisponible si la base falla al revisar o al escribir la cita.
 */
function at_wa_cita_cancelar(string $tel, string $tipo, int $id, string $motivo): array {
	$cita = at_wa_cita_del_numero($tel, $tipo, $id);
	if (!$cita) {
		return ['ok' => false, 'motivo' => 'no_vigente', 'cita' => null, 'efectos' => null];
	}
	global $wpdb;
	$tabla = $wpdb->prefix . ($tipo === 'diagnostico' ? 'automatiza_leads' : 'automatiza_followup_meetings');
	$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tabla} WHERE id = %d", $id));
	if (!$fila) {
		return ['ok' => false, 'motivo' => 'error', 'cita' => $cita, 'efectos' => null];
	}
	if (!at_wa_cita_marcar_cancelada($tipo, $id)) {
		// Otro pedido (un doble toque) ya la canceló: no se repiten el borrado del evento ni los correos.
		return ['ok' => false, 'motivo' => 'no_vigente', 'cita' => null, 'efectos' => null];
	}
	$evento = (string) ($fila->google_event_id ?? '');
	if ($evento === '' && $tipo === 'diagnostico') {
		$evento = (string) ($fila->event_id ?? '');
	}
	$calendario = null;
	if ($evento !== '') {
		$calendario = function_exists('automatiza_tech_delete_google_calendar_event') && automatiza_tech_delete_google_calendar_event($evento);
		if (!$calendario) {
			error_log("[wa-citas] la cita {$tipo} {$id} quedó cancelada, pero no se pudo quitar su evento de Google Calendar");
		}
	}
	$avisos = at_wa_cita_avisar($cita, $motivo, $tel, $calendario);
	// Revisión B-CITAS-04: ok dice que la cita quedó cancelada; efectos dice qué más se hizo, para no prometer lo que falló.
	return ['ok' => true, 'motivo' => '', 'cita' => $cita, 'efectos' => ['calendario' => $calendario, 'correo' => $avisos['correo'], 'aviso' => $avisos['aviso']]];
}

/**
 * Pasa la cita a cancelada solo si sigue activa, en una sola sentencia: de dos pedidos simultáneos gana uno (revisión B-CITAS-05).
 * Nunca borra la fila. Devuelve true si este pedido hizo el cambio.
 */
function at_wa_cita_marcar_cancelada(string $tipo, int $id): bool {
	global $wpdb;
	$ahora = current_time('mysql');
	$activa = "(status IS NULL OR status NOT IN ('" . implode("', '", AT_WA_CITA_INACTIVA) . "'))";
	if ($tipo === 'diagnostico') {
		$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}automatiza_leads SET status = 'cancelled', cancelled_at = %s, updated_at = %s WHERE id = %d AND {$activa}", $ahora, $ahora, $id);
	} else {
		$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}automatiza_followup_meetings SET status = 'cancelled', updated_at = %s WHERE id = %d AND {$activa}", $ahora, $id);
	}
	$wpdb->last_error = '';
	$filas = $wpdb->query($sql);
	if ($filas === false || (string) $wpdb->last_error !== '') {
		// Revisión R2-03: un error al escribir no es «otro pedido ya la canceló» (eso es 0 filas).
		throw new AtWaConsultaNoDisponible();
	}
	return $filas === 1;
}

/**
 * El correo de cancelación de siempre: el de los diagnósticos («Lamentamos que hayas cancelado tu cita», con «Agendar Nueva
 * Cita» y el sitio; ruta /send-email, plantilla cancellation) o el de los seguimientos (correo-cancelacion-seguimiento.php).
 */
function at_wa_correo_cancelacion(array $cita): bool {
	$nombre = trim((string) $cita['nombre']) !== '' ? (string) $cita['nombre'] : 'Cliente';
	if ($cita['tipo'] === 'diagnostico') {
		$pedido = new WP_REST_Request('POST', '/automatiza-tech/v1/send-email');
		$pedido->set_header('Content-Type', 'application/json');
		$pedido->set_body(wp_json_encode(['to' => (string) $cita['correo'], 'subject' => 'Lamentamos que hayas cancelado tu cita - AutomatizaTech',
			'template' => 'cancellation', 'data' => ['clientName' => $nombre, 'summary' => (string) $cita['etiqueta']]]));
		$respuesta = rest_do_request($pedido);
		return $respuesta->get_status() === 200 && !empty(((array) $respuesta->get_data())['success']);
	}
	$de = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	return (bool) wp_mail((string) $cita['correo'], '❌ Lamentamos la cancelación de tu reunión de seguimiento',
		at_wa_correo_cancelacion_seguimiento($nombre, substr((string) $cita['cuando'], 0, 10), substr((string) $cita['cuando'], 11, 5)),
		['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $de . '>']);
}

/** Aviso corto a la persona cuando su cita se reprogramó (la confirmación de la nueva la manda la agenda). */
function at_wa_correo_reprogramada(array $cita, string $nombre, string $etiqueta, array $cabeceras): bool {
	$cuerpo = '<p>Hola' . ($nombre !== '' ? ' ' . $nombre : '') . ',</p>'
		. "<p>Tu cita <strong>{$etiqueta}</strong> se reprogramó. Te llegó aparte la confirmación de la nueva fecha.</p>"
		. '<p>Equipo AutomatizaTech</p>';
	return (bool) wp_mail((string) $cita['correo'], 'Tu cita se reprogramó', $cuerpo, $cabeceras);
}

/** Correo a la persona y aviso a Luis de una cita cancelada o reprogramada por WhatsApp. */
function at_wa_cita_avisar(array $cita, string $motivo, string $tel, ?bool $calendario = null): array {
	$reprogramada = $motivo === 'reprogramada';
	$etiqueta = esc_html($cita['etiqueta']);
	$nombre = esc_html(function_exists('at_wa_primer_nombre') ? at_wa_primer_nombre((string) $cita['nombre']) : (string) $cita['nombre']);
	$cabeceras = ['Content-Type: text/html; charset=UTF-8'];
	$correo = false;
	if (is_email((string) $cita['correo'])) {
		$correo = $reprogramada ? at_wa_correo_reprogramada($cita, $nombre, $etiqueta, $cabeceras) : at_wa_correo_cancelacion($cita);
		if (!$correo) {
			error_log("[wa-citas] no salió el correo a la persona de la cita {$cita['tipo']} {$cita['id']}");
		}
	}
	$asunto_luis = '[WhatsApp] Cita ' . ($reprogramada ? 'reprogramada' : 'cancelada') . ': ' . $cita['etiqueta'];
	$cuerpo_luis = "<p>Por WhatsApp, desde el número " . esc_html($tel) . ", se " . ($reprogramada ? 'reprogramó' : 'canceló') . " la cita <strong>{$etiqueta}</strong>"
		. ' (' . esc_html($cita['tipo']) . ' ' . (int) $cita['id'] . ').</p>';
	if ($calendario === false) {
		$cuerpo_luis .= '<p><strong>Pendiente:</strong> no se pudo quitar el evento de Google Calendar. Bórralo a mano.</p>';
	}
	if (!$correo) {
		$cuerpo_luis .= '<p><strong>Pendiente:</strong> no salió el correo de confirmación a la persona.</p>';
	}
	$aviso = (bool) wp_mail((string) get_option('admin_email'), $asunto_luis, $cuerpo_luis, $cabeceras);
	if (!$aviso) {
		error_log("[wa-citas] no salió el aviso a Luis de la cita {$cita['tipo']} {$cita['id']}");
	}
	return ['correo' => $correo, 'aviso' => $aviso];
}

function at_wa_rest_cancelar_cita(WP_REST_Request $r): WP_REST_Response {
	$tipo = (string) $r->get_param('tipo');
	$motivo = $r->get_param('motivo') === 'reprogramada' ? 'reprogramada' : 'cancelada';
	if (!in_array($tipo, ['diagnostico', 'seguimiento'], true)) {
		return new WP_REST_Response(['ok' => false, 'motivo' => 'no_vigente', 'cita' => null], 409);
	}
	try {
		$x = at_wa_cita_cancelar(sanitize_text_field((string) $r->get_param('phone')), $tipo, (int) $r->get_param('id'), $motivo);
	} catch (AtWaConsultaNoDisponible $e) {
		error_log('[wa-citas] una lectura de la base falló al cancelar; no se canceló nada');
		return new WP_REST_Response(['ok' => false, 'motivo' => 'no_disponible', 'cita' => null], 503);
	}
	return new WP_REST_Response($x, $x['ok'] ? 200 : ($x['motivo'] === 'no_vigente' ? 409 : 500));
}

/**
 * El límite de 2 reuniones activas (automatiza_tech_limite_reuniones_activas) sin contar la cita que se está reprogramando.
 * $excluir = ['tipo' => 'diagnostico'|'seguimiento', 'id' => int]; otro tipo no excluye nada. Misma respuesta que el original.
 */
function at_wa_limite_con_excluir(string $email, array $excluir): array {
	global $wpdb;
	$p = $wpdb->prefix;
	$ahora = current_time('mysql');
	$sin_seg = ($excluir['tipo'] ?? '') === 'seguimiento' ? (int) ($excluir['id'] ?? 0) : 0;
	$sin_diag = ($excluir['tipo'] ?? '') === 'diagnostico' ? (int) ($excluir['id'] ?? 0) : 0;
	$seguimientos = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$p}automatiza_followup_meetings WHERE client_email = %s AND CONCAT(meeting_date, ' ', meeting_time) >= %s
		 AND status NOT IN ('cancelled', 'completed') AND id <> %d", $email, $ahora, $sin_seg));
	$demos = (int) $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM {$p}automatiza_leads WHERE email = %s AND CONCAT(scheduled_date, ' ', scheduled_time) >= %s
		 AND (status IS NULL OR status NOT IN ('cancelled', 'no_show')) AND id <> %d", $email, $ahora, $sin_diag));
	return [
		'alcanzado'    => ($seguimientos + $demos) >= 2,
		'seguimientos' => $seguimientos,
		'demos'        => $demos,
		'mensaje'      => 'Ya tienes 2 reuniones activas (' . $demos . ' demo(s) y ' . $seguimientos . ' seguimiento(s)). Cancela o espera a que pase alguna para agendar otra.',
	];
}

/** El excluir de un pedido a /leads o /followup-meetings: solo vale con la clave de n8n (si no, saltarse el límite sería público). */
function at_wa_excluir_de_request(WP_REST_Request $r): array {
	if (!function_exists('at_cc_rest_auth') || at_cc_rest_auth($r) !== true) {
		return [];
	}
	$tipo = (string) $r->get_param('excluir_tipo');
	$id = (int) $r->get_param('excluir_id');
	return in_array($tipo, ['diagnostico', 'seguimiento'], true) && $id > 0 ? ['tipo' => $tipo, 'id' => $id] : [];
}
