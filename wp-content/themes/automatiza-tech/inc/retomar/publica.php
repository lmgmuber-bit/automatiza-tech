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

/** Formulario sin JavaScript, respaldo del diálogo: POST a retomar.php?t=…&agendar=1 con horas en punto de 09:00 a 20:00. */
function at_rt_form_agenda_sinjs(string $t, string $url): string {
    $html = '<form method="post" action="' . esc_url($url . '&agendar=1') . '"><input type="hidden" name="token" value="' . esc_attr(at_rt_token_agenda_hoy($t)) . '">'
        . '<label>Fecha<input type="date" name="fecha" required min="' . esc_attr(current_time('Y-m-d')) . '"></label>'
        . '<label>Hora<select name="hora" required><option value="">Elige una hora</option>';
    for ($h = 9; $h < 21; $h++) { $hora = sprintf('%02d:00', $h); $html .= '<option value="' . $hora . '">' . $hora . '</option>'; }
    return $html . '</select></label><div hidden aria-hidden="true"><label>Sitio web<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div><button type="submit">Agendar reunión</button></form>';
}

/** Diálogo «Agendemos 20 minutos»: el selector de horarios de la portada (at-agenda.js) y retomar-ver.js, que envía
 *  {t, token, fecha, hora, sitio_web} a POST automatiza-tech/v1/retomar-agenda. $abrir = vino con agendar=1; entonces
 *  también va el formulario de siempre dentro de <noscript>, para quien no tenga JavaScript. */
function at_rt_html_agenda(string $t, string $url, bool $abrir): string {
    $tema = get_template_directory_uri();
    $dir = get_template_directory();
    $ver = static function (string $rel) use ($dir): string {
        $f = $dir . $rel;
        return file_exists($f) ? (string) filemtime($f) : '1';
    };
    $logo = defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
    $html = '<dialog id="at-rt-agenda" class="at-pt-dlg" aria-labelledby="at-rt-agenda-titulo">'
        . '<form id="at-rt-form-agenda" novalidate>'
        . '<div class="at-pt-dlg-cab"><img src="' . esc_url($logo) . '" alt="" width="53" height="44"><span>AutomatizaTech</span></div>'
        . '<h2 id="at-rt-agenda-titulo">Agendemos 20 minutos</h2>'
        . '<p>Conversemos en equipo por videollamada y definamos el próximo paso de tu proyecto. Te llega la invitación por correo.</p>'
        . '<p id="at-rt-agenda-msg" class="at-pt-msg" role="status" hidden></p>'
        . '<div class="at-pt-campos">'
        . '<label for="at-fecha">Día</label><input type="date" id="at-fecha" required>'
        . '<p class="at-pt-etiqueta">Hora</p><div id="at-slots" class="at-pt-horas"><span>Elige una fecha para ver horarios disponibles.</span></div>'
        . '<input type="hidden" id="at-scheduled-time" value=""><input type="hidden" id="at-franja" value="">'
        . '<div class="at-pt-trampa" aria-hidden="true"><label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>'
        . '</div>'
        . '<div class="at-pt-dlg-botones"><button type="button" class="at-pt-btn at-pt-btn--sec" id="at-rt-cerrar">Cerrar</button>'
        . '<button type="submit" class="at-pt-btn at-pt-btn--si">Agendar</button></div>'
        . '</form></dialog>'
        . '<script>window.AT_AGENDA = ' . wp_json_encode(['configUrl' => esc_url_raw(rest_url('automatiza-tech/v1/appointments-config'))], JSON_HEX_TAG) . ';'
        . 'window.AT_RT_VER = ' . wp_json_encode(['url' => esc_url_raw(rest_url('automatiza-tech/v1/retomar-agenda')), 't' => $t, 'token' => at_rt_token_agenda_hoy($t), 'abrir' => $abrir], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>'
        . '<script src="' . esc_url($tema . '/assets/home-premium/at-agenda.js?v=' . $ver('/assets/home-premium/at-agenda.js')) . '"></script>'
        . '<script src="' . esc_url($tema . '/assets/js/retomar-ver.js?v=' . $ver('/assets/js/retomar-ver.js')) . '"></script>';
    if ($abrir) {
        $html .= '<noscript><section class="at-rt-sinjs" aria-labelledby="at-rt-sinjs-titulo"><h1 id="at-rt-sinjs-titulo">Agendemos 20 minutos</h1>'
            . '<p>Elige una fecha y una hora en punto. Confirmaremos la disponibilidad al agendar.</p>' . at_rt_form_agenda_sinjs($t, $url)
            . '<p><a href="' . esc_url($url) . '">Volver a la presentación</a></p></section></noscript>';
    }
    return $html;
}

/** Página de la presentación, como ver-plan.php: iframe a pantalla completa, barra con «Agendar 20 minutos» (abre el
 *  diálogo; sin JavaScript, el enlace lleva a &agendar=1 y al formulario de siempre) y un «No me interesa» discreto. */
function at_rt_html_presentacion(array $s, string $t, string $url, bool $abrir): string {
    $tema = get_template_directory_uri();
    $css = get_template_directory() . '/assets/css/plan-ver.css';
    // Ruling 17: el prospecto ve la presentación del último recordatorio que le salió (render.publicada), nunca la
    // que se está rehaciendo o espera la aprobación de Luis. Solo si todavía no se le envió nada, la vigente.
    $publicada = $s['render']['publicada'] ?? null;
    $vista = is_array($publicada) ? (string) ($publicada['view_url'] ?? '') : (string) ($s['render']['view_url'] ?? '');
    $cerrado = in_array((string) $s['estado'], ['baja', 'rechazado', 'cliente'], true);
    $ya = $cerrado ? null : at_rt_reunion_pendiente((int) $s['id']);
    $html = '<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">'
        . '<title>Retomemos tu proyecto · AutomatizaTech</title>'
        . '<link rel="stylesheet" href="' . esc_url($tema . '/assets/css/plan-ver.css?v=' . (file_exists($css) ? (string) filemtime($css) : '1')) . '">'
        . '<style>body.at-rt-ver>main{flex:1 1 auto}.at-rt-baja{color:#94a3b8;font-size:13px}.at-rt-baja:focus-visible{outline:3px solid var(--at-verde);outline-offset:2px}'
        . '.at-rt-sinjs{position:fixed;inset:0;z-index:10;overflow-y:auto;background:#fff;color:#142c46;padding:24px;font-size:18px;line-height:1.6}.at-rt-sinjs a{color:#076b73}'
        . '.at-rt-sinjs label{display:block;margin:16px 0}.at-rt-sinjs input,.at-rt-sinjs select{display:block;font:inherit;padding:8px;max-width:100%;box-sizing:border-box}'
        . '.at-rt-sinjs button{background:#087b80;color:#fff;border:0;border-radius:8px;padding:12px 20px;font:inherit;cursor:pointer}</style></head>'
        . '<body class="at-pt-ver at-rt-ver">';
    if (at_rt_url_render_valida($vista, $t)) {
        $html .= '<iframe src="' . esc_url($vista) . '" title="Presentación de tu proyecto" referrerpolicy="no-referrer" allow="fullscreen"></iframe>';
    } else {
        $html .= '<main class="at-pt-vacio"><h1>Retomemos tu proyecto</h1><p>Estamos preparando tu presentación.</p></main>';
    }
    $html .= '<nav class="at-pt-barra" aria-label="Agenda una conversación"><p>¿Conversamos 20 minutos sobre tu proyecto?</p>';
    if ($ya) {
        $html .= '<p class="at-pt-ya">Ya tienes tu llamada de seguimiento el ' . esc_html(at_pt_fecha_larga((string) $ya->meeting_date) . ' a las ' . substr((string) $ya->meeting_time, 0, 5)) . '.</p>';
    } elseif (!$cerrado) {
        $html .= '<a class="at-pt-btn at-pt-btn--si" href="' . esc_url($url . '&agendar=1') . '" data-at-rt-abrir>📅 Agendar 20 minutos</a>';
    }
    if ((string) $s['estado'] !== 'baja') {
        $html .= '<a class="at-rt-baja" href="' . esc_url($url . '&baja=1') . '">No me interesa</a>';
    }
    $html .= '</nav>';
    if (!$ya && !$cerrado) {
        $html .= at_rt_html_agenda($t, $url, $abrir);
    }
    return $html . '</body></html>';
}

/** Un nonce específico por seguimiento para evitar aceptar el de otra página. La presentación (con o sin agendar=1)
 *  es at_rt_html_presentacion; la baja y el POST del formulario sin JavaScript siguen en la página simple. */
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
        } elseif (!empty($get['agendar']) && strtoupper($metodo) === 'POST') {
            // Formulario sin JavaScript. El token secreto de la URL manda; nunca aceptar uno distinto en el cuerpo.
            $res = at_rt_agendar(array_merge($post, ['t' => $t]), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $http = $res['estado_http'];
            $mensaje = '<p role="status">' . esc_html($res['mensaje']) . '</p>';
            $volver = '<p><a href="' . esc_url($url) . '">Volver a la presentación</a></p>';
            $contenido = $res['ok'] ? '<h1>Reunión agendada</h1>' . $mensaje . $volver
                : '<h1>Agendemos 20 minutos</h1><p>Elige una fecha y una hora en punto. Confirmaremos la disponibilidad al agendar.</p>' . $mensaje . at_rt_form_agenda_sinjs($t, $url) . $volver;
        } else {
            return ['http' => 200, 'html' => at_rt_html_presentacion($s, $t, $url, !empty($get['agendar'])), 'cabeceras' => $cabeceras];
        }
    }
    $html = '<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Retomemos tu proyecto · AutomatizaTech</title><style>body{margin:0;background:#f1f5f9;color:#142c46;font:18px/1.6 system-ui,sans-serif}main{max-width:1100px;margin:32px auto;padding:24px;background:white;border-radius:16px}h1{line-height:1.2}a{color:#076b73}button,.boton{display:inline-block;background:#087b80;color:white;border:0;border-radius:8px;padding:12px 20px;font:inherit;cursor:pointer;text-decoration:none}label{display:block;margin:16px 0}input,select{display:block;font:inherit;padding:8px;max-width:100%;box-sizing:border-box}@media(max-width:600px){main{margin:0;border-radius:0;padding:20px}h1{font-size:28px}}</style></head><body><main>' . $contenido . '</main></body></html>';
    return ['http' => $http, 'html' => $html, 'cabeceras' => $cabeceras];
}
