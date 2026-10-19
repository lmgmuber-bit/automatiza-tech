<?php
/** Página por token y agenda de Retomar. GET nunca cambia el estado. */
if (!defined('ABSPATH')) { exit; }
add_action('rest_api_init', static function () {
    register_rest_route('automatiza-tech/v1', '/retomar-agenda', ['methods' => 'POST', 'callback' => 'at_rt_rest_agendar', 'permission_callback' => '__return_true']);
});
function at_rt_rest_agendar(WP_REST_Request $r): WP_REST_Response {
    $d = $r->get_json_params();
    $res = at_rt_agendar(is_array($d) ? $d : [], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return new WP_REST_Response(['ok' => $res['ok'], 'clave' => $res['clave'], 'mensaje' => $res['mensaje']], $res['estado_http']);
}
function at_rt_token_agenda_hoy(string $t): string {
    $s = at_rt_por_token($t);
    return $s ? at_pt_token_agenda('retomar-' . $s['id'], intdiv(time(), 86400), wp_salt('nonce')) : '';
}
function at_rt_reunion_pendiente(int $id): ?object {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT id, meeting_date, meeting_time FROM {$wpdb->prefix}automatiza_followup_meetings WHERE notes LIKE %s AND status = 'scheduled' AND CONCAT(meeting_date, ' ', meeting_time) > %s ORDER BY meeting_date, meeting_time LIMIT 1", 'Retomar #' . $id . ' ·%', current_time('mysql'))) ?: null;
}
function at_rt_agendar(array $entrada, string $ip): array {
	$texto = function (string $k) use ($entrada): string {
		return is_scalar($entrada[$k] ?? null) ? trim((string) $entrada[$k]) : '';
	};
	$fila = at_rt_por_token($texto('t'));
	if (!$fila || in_array($fila['estado'], ['baja', 'rechazado', 'cliente'], true) || at_rt_en_bajas($fila['email'], $fila['telefono'])) {
		return at_pt_respuesta_agenda(false, 'no_guardo', 404, 0, 'Este seguimiento no está disponible.');
	}
	if ($texto('sitio_web') !== '') {
		return at_pt_respuesta_agenda(false, 'no_guardo', 400); // campo trampa: un bot
	}
	if (!at_pt_token_agenda_valido($texto('token'), 'retomar-' . $fila['id'], intdiv(time(), 86400), wp_salt('nonce'))) {
		return at_pt_respuesta_agenda(false, 'sesion_vencida', 403);
	}
	// Límite por IP: cuenta cada intento con token válido, sirva o no la hora.
	$clave_ip = 'at_rt_agenda_' . md5($ip);
	$intentos = (int) get_transient($clave_ip);
	if ($intentos >= AT_PT_AGENDA_INTENTOS_HORA) {
		return at_pt_respuesta_agenda(false, 'muchos_intentos', 429);
	}
	set_transient($clave_ip, $intentos + 1, HOUR_IN_SECONDS);

	$d = ['client_name' => $fila['nombre'], 'client_email' => $fila['email'], 'company_name' => $fila['negocio'], 'phone' => $fila['telefono'], 'meeting_subject' => 'Retomemos tu proyecto — ' . $fila['negocio'], 'notes' => 'Retomar #' . $fila['id'] . ' ·'];
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
		$fila = at_rt_obtener($fila['id']);
		if (!$fila || in_array($fila['estado'], ['baja', 'rechazado', 'cliente'], true) || at_rt_en_bajas($fila['email'], $fila['telefono'])) { return at_pt_respuesta_agenda(false, 'no_guardo', 409); }
		$ya = at_rt_reunion_pendiente($fila['id']);
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
		if (!empty($disp['workingHours']['end']) && $disp['workingHours']['end'] > '21:00') { $disp['workingHours']['end'] = '21:00'; }
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
			'notes'           => (string) $d['notes'] . ' Agendada por el prospecto desde Retomar.',
			'status'          => 'scheduled',
		]);
		$reunion = $ok ? (int) $wpdb->insert_id : 0;
		if ($reunion > 0 && !at_rt_guardar($fila['id'], ['estado' => 'agendo', 'proximo_at' => null])) { $wpdb->delete($t, ['id' => $reunion]); $reunion = 0; }
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
	if (function_exists('at_cc_historial_crm') && !empty($fila['crm_cliente_id'])) { at_cc_historial_crm($fila['crm_cliente_id'], 'retomar_agendo', 'Reunión agendada', 'Retomar #' . $fila['id'] . ': llamada del ' . $cuando . '.'); }
	if (function_exists('at_cc_correo_avisos')) {
		wp_mail(at_cc_correo_avisos(), '📅 ' . ($d['client_name'] !== '' ? $d['client_name'] : 'Un cliente') . ' agendó la llamada de seguimiento de Retomar',
			'<p>' . esc_html(($d['client_name'] ?: 'El cliente') . ($d['company_name'] !== '' ? ' (' . $d['company_name'] . ')' : '')) . ' agendó desde ' . esc_html('Retomar #' . $fila['id'])
			. ' la llamada de seguimiento del <strong>' . esc_html($cuando) . '</strong>.</p>'
			. '<p>' . (!empty($cal['success']) ? 'El evento quedó en Google Calendar' . (!empty($cal['meet_link']) ? ' con Meet.' : '.') : '<strong>No se pudo crear el evento en Google Calendar:</strong> créalo a mano desde «Reuniones de seguimiento».') . '</p>',
			['Content-Type: text/html; charset=UTF-8']);
	}
	return at_pt_respuesta_agenda(true, 'agendada', 200, $reunion, 'Listo: agendamos tu llamada de seguimiento para el ' . $cuando . '. Te llegará la invitación por correo.');
}

/** Un nonce específico por seguimiento para evitar aceptar el de otra página. */
function at_rt_pagina(array $get, string $metodo, array $post): array {
    $cabeceras = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer', 'Content-Type' => 'text/html; charset=UTF-8'];
    $t = is_string($get['t'] ?? null) ? $get['t'] : '';
    $s = at_rt_por_token($t);
    $http = $s ? 200 : 404;
    $contenido = '<h1>Seguimiento no disponible</h1><p>Revisa el enlace que te enviamos.</p>';
    if ($s) {
        $url = home_url('/retomar.php?t=' . rawurlencode($t));
        $accion = 'at_rt_baja_' . $s['id'];
        if (!empty($get['baja'])) {
            if (strtoupper($metodo) === 'POST') {
                $click = ($post['List-Unsubscribe'] ?? '') === 'One-Click';
                $nonce = is_string($post['_wpnonce'] ?? null) ? $post['_wpnonce'] : '';
                if (!$click && !wp_verify_nonce($nonce, $accion)) {
                    $http = 403; $contenido = '<h1>Recarga la página</h1><p>La confirmación venció. Abre nuevamente el enlace de baja.</p>';
                } elseif (!at_rt_dar_baja($s['id'], $click ? 'one_click' : 'pagina')) {
                    $http = 500; $contenido = '<h1>No pudimos confirmar la baja</h1><p>Inténtalo de nuevo.</p>';
                } else {
                    $contenido = '<h1>Listo, ya no te escribiremos</h1><p>No recibirás más recordatorios de Retomar.</p>';
                }
            } else {
                $contenido = '<h1>¿Dejar de recibir recordatorios?</h1><p>Confirma para que no volvamos a escribirte sobre este proyecto.</p><form method="post"><input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce($accion)) . '"><button type="submit">No me interesa, no volver a escribirme</button></form>';
            }
        } elseif (!empty($get['agendar'])) {
            $mensaje = '';
            if (strtoupper($metodo) === 'POST') {
                // El token secreto de la URL manda; nunca aceptar uno distinto en el cuerpo.
                $res = at_rt_agendar(array_merge($post, ['t' => $t]), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                $http = $res['estado_http'];
                $mensaje = '<p role="status">' . esc_html($res['mensaje']) . '</p>';
                if ($res['ok']) { $contenido = '<h1>Reunión agendada</h1>' . $mensaje; }
            }
            if (empty($res['ok'])) {
                $contenido = '<h1>Agendemos 20 minutos</h1><p>Elige una fecha y una hora en punto. Confirmaremos la disponibilidad al agendar.</p>' . $mensaje
                    . '<form method="post"><input type="hidden" name="token" value="' . esc_attr(at_rt_token_agenda_hoy($t)) . '">'
                    . '<label>Fecha<input type="date" name="fecha" required min="' . esc_attr(current_time('Y-m-d')) . '"></label>'
                    . '<label>Hora<select name="hora" required><option value="">Elige una hora</option>';
                for ($h = 9; $h < 21; $h++) { $hora = sprintf('%02d:00', $h); $contenido .= '<option value="' . $hora . '">' . $hora . '</option>'; }
                $contenido .= '</select></label><div hidden aria-hidden="true"><label>Sitio web<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div><button type="submit">Agendar reunión</button></form><p><a href="' . esc_url($url) . '">Volver a la presentación</a></p>';
            }
        } else {
            $contenido = '<h1>Retomemos tu proyecto</h1>';
            // Ruling 17: el prospecto ve la presentación del último recordatorio que le salió (render.publicada), nunca la
            // que se está rehaciendo o espera la aprobación de Luis. Solo si todavía no se le envió nada, la vigente.
            $publicada = $s['render']['publicada'] ?? null;
            $vista = is_array($publicada) ? (string) ($publicada['view_url'] ?? '') : (string) ($s['render']['view_url'] ?? '');
            if (at_rt_url_render_valida($vista, $t)) {
                $contenido .= '<iframe title="Presentación de tu proyecto" referrerpolicy="no-referrer" src="' . esc_url($vista) . '" style="width:100%;height:75vh;border:0"></iframe>';
            } else { $contenido .= '<p>Estamos preparando tu presentación.</p>'; }
            $contenido .= '<p><a class="boton" href="' . esc_url($url . '&agendar=1') . '">Agendar una reunión</a></p><p><a href="' . esc_url($url . '&baja=1') . '">No me interesa, no volver a escribirme</a></p>';
        }
    }
    $html = '<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Retomemos tu proyecto · AutomatizaTech</title><style>body{margin:0;background:#f1f5f9;color:#142c46;font:18px/1.6 system-ui,sans-serif}main{max-width:1100px;margin:32px auto;padding:24px;background:white;border-radius:16px}h1{line-height:1.2}a{color:#076b73}button,.boton{display:inline-block;background:#087b80;color:white;border:0;border-radius:8px;padding:12px 20px;font:inherit;cursor:pointer;text-decoration:none}label{display:block;margin:16px 0}input,select{display:block;font:inherit;padding:8px;max-width:100%;box-sizing:border-box}@media(max-width:600px){main{margin:0;border-radius:0;padding:20px}h1{font-size:28px}}</style></head><body><main>' . $contenido . '</main></body></html>';
    return ['http' => $http, 'html' => $html, 'cabeceras' => $cabeceras];
}
