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
		$sql = "SELECT {$sql_select} FROM {$p}{$tabla} WHERE ({$donde}) AND CONCAT({$col_fecha}, ' ', {$col_hora}) > %s ORDER BY {$col_fecha}, {$col_hora} LIMIT 50";
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
 * el estado quedó cancelado. Lanza AtWaConsultaNoDisponible si la base falla al revisar la cita.
 */
function at_wa_cita_cancelar(string $tel, string $tipo, int $id, string $motivo): array {
	$cita = at_wa_cita_del_numero($tel, $tipo, $id);
	if (!$cita) {
		return ['ok' => false, 'motivo' => 'no_vigente', 'cita' => null];
	}
	global $wpdb;
	$ahora = current_time('mysql');
	if ($tipo === 'diagnostico') {
		$tabla = $wpdb->prefix . 'automatiza_leads';
		$cambios = ['status' => 'cancelled', 'cancelled_at' => $ahora, 'updated_at' => $ahora];
	} else {
		$tabla = $wpdb->prefix . 'automatiza_followup_meetings';
		$cambios = ['status' => 'cancelled', 'updated_at' => $ahora];
	}
	$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tabla} WHERE id = %d", $id));
	$hecho = $wpdb->update($tabla, $cambios, ['id' => $id]);
	if ($hecho === false || !$fila) {
		error_log("[wa-citas] no se pudo cancelar la cita {$tipo} {$id}");
		return ['ok' => false, 'motivo' => 'error', 'cita' => $cita];
	}
	$evento = (string) ($fila->google_event_id ?? '');
	if ($evento === '' && $tipo === 'diagnostico') {
		$evento = (string) ($fila->event_id ?? '');
	}
	if ($evento !== '' && function_exists('automatiza_tech_delete_google_calendar_event')
		&& !automatiza_tech_delete_google_calendar_event($evento)) {
		error_log("[wa-citas] la cita {$tipo} {$id} quedó cancelada, pero no se pudo quitar su evento de Google Calendar");
	}
	at_wa_cita_avisar($cita, $motivo, $tel);
	return ['ok' => true, 'motivo' => '', 'cita' => $cita];
}

/** Correo a la persona y aviso a Luis de una cita cancelada o reprogramada por WhatsApp. */
function at_wa_cita_avisar(array $cita, string $motivo, string $tel): void {
	$reprogramada = $motivo === 'reprogramada';
	$etiqueta = esc_html($cita['etiqueta']);
	$nombre = esc_html(function_exists('at_wa_primer_nombre') ? at_wa_primer_nombre((string) $cita['nombre']) : (string) $cita['nombre']);
	$cabeceras = ['Content-Type: text/html; charset=UTF-8'];
	if (is_email((string) $cita['correo'])) {
		$asunto = $reprogramada ? 'Tu cita se reprogramó' : 'Tu cita fue cancelada';
		$cuerpo = '<p>Hola' . ($nombre !== '' ? ' ' . $nombre : '') . ',</p>'
			. ($reprogramada
				? "<p>Tu cita <strong>{$etiqueta}</strong> se reprogramó. Te llegó aparte la confirmación de la nueva fecha.</p>"
				: "<p>Tu cita <strong>{$etiqueta}</strong> fue cancelada, como nos pediste por WhatsApp. Si quieres agendar otra, escríbenos cuando quieras.</p>")
			. '<p>Equipo AutomatizaTech</p>';
		if (!wp_mail((string) $cita['correo'], $asunto, $cuerpo, $cabeceras)) {
			error_log("[wa-citas] no salió el correo a la persona de la cita {$cita['tipo']} {$cita['id']}");
		}
	}
	$asunto_luis = '[WhatsApp] Cita ' . ($reprogramada ? 'reprogramada' : 'cancelada') . ': ' . $cita['etiqueta'];
	$cuerpo_luis = "<p>Por WhatsApp, desde el número " . esc_html($tel) . ", se " . ($reprogramada ? 'reprogramó' : 'canceló') . " la cita <strong>{$etiqueta}</strong>"
		. ' (' . esc_html($cita['tipo']) . ' ' . (int) $cita['id'] . ').</p>';
	if (!wp_mail((string) get_option('admin_email'), $asunto_luis, $cuerpo_luis, $cabeceras)) {
		error_log("[wa-citas] no salió el aviso a Luis de la cita {$cita['tipo']} {$cita['id']}");
	}
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
