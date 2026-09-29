<?php
/**
 * Aviso a Luis cuando Meta no entrega un recordatorio de WhatsApp (29-sep-2026, decisión de Luis: el
 * mismo camino que la Task 19 de las propuestas).
 *
 * Los flujos de n8n que le escriben al cliente con plantilla (citas 72 h, 24 h y 1 h; seguimientos de las
 * 8 AM y 8 PM; aviso de reunión agendada) anotan aquí el wamid que les dio Meta. El bot reenvía a
 * at_cc_rest_estado_whatsapp() cada 'failed' que Meta manda por su webhook; si el wamid es de uno de
 * estos envíos, le llega un correo a Luis con el cliente, la cita y el motivo, una sola vez por mensaje.
 *
 * El recordatorio no se reintenta: marcarlo como no enviado haría que su flujo lo mande otra vez en cada
 * pasada. Los WhatsApp de ARGOS a Luis no se anotan, así que un fallo de esos nunca dispara otro aviso.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Lo que anotan los flujos: de qué tabla es el id y cómo se nombra el mensaje en el correo. */
function at_cc_tipos_recordatorio(): array {
	return [
		'cita_72h'            => ['tabla' => 'lead', 'nombre' => 'el recordatorio de 72 horas de su cita'],
		'cita_24h'            => ['tabla' => 'lead', 'nombre' => 'el recordatorio de 24 horas de su cita'],
		'cita_1h'             => ['tabla' => 'lead', 'nombre' => 'el recordatorio de 1 hora de su cita'],
		'seguimiento_8am'     => ['tabla' => 'seguimiento', 'nombre' => 'el recordatorio de las 8 AM de su reunión de hoy'],
		'seguimiento_8pm'     => ['tabla' => 'seguimiento', 'nombre' => 'el recordatorio de las 8 PM de su reunión de mañana'],
		'reunion_demo'        => ['tabla' => 'lead', 'nombre' => 'el aviso de su demo agendada'],
		'reunion_prospecto'   => ['tabla' => 'seguimiento', 'nombre' => 'el aviso de su reunión agendada'],
		'reunion_seguimiento' => ['tabla' => 'seguimiento', 'nombre' => 'el aviso de su reunión de seguimiento agendada'],
	];
}

/** Transient con el envío anotado (tipo, id y hora) de un wamid. */
function at_cc_clave_envio_recordatorio(string $wamid): string {
	return 'at_cc_wa_rec_' . md5($wamid);
}

add_action('rest_api_init', function () {
	register_rest_route('at/v1', '/whatsapp-envio', [
		'methods'             => 'POST',
		'callback'            => 'at_cc_rest_envio_recordatorio',
		'permission_callback' => 'at_cc_rest_auth',
	]);
});

/**
 * Cuerpo: {"wamid":"wamid.…","tipo":"cita_24h","id":123}; 400 si no calza. El envío se guarda 7 días: los
 * 'failed' llegan en segundos, y así cubre un aviso atrasado. Si el 'failed' le ganó a esta anotación, la
 * ruta de estados lo dejó en espera (at_cc_clave_fallo_pendiente()) y se avisa ahora.
 */
function at_cc_rest_envio_recordatorio(WP_REST_Request $r) {
	$wamid = $r->get_param('wamid');
	$wamid = is_string($wamid) ? $wamid : '';
	$tipo = $r->get_param('tipo');
	$tipo = is_string($tipo) ? $tipo : '';
	$id = $r->get_param('id');
	$id = (is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id >= 1 && (int) $id <= 2147483647 ? (int) $id : 0;
	if (!at_cc_wamid_valido($wamid) || !isset(at_cc_tipos_recordatorio()[$tipo]) || $id === 0) {
		return new WP_Error('datos_invalidos', 'wamid, tipo o id inválidos.', ['status' => 400]);
	}
	$envio = ['tipo' => $tipo, 'id' => $id, 'enviado' => time()];
	set_transient(at_cc_clave_envio_recordatorio($wamid), $envio, 7 * DAY_IN_SECONDS);
	$avisado = false;
	$clave = at_cc_clave_fallo_pendiente($wamid);
	$pendiente = get_transient($clave);
	if (is_array($pendiente)) {
		delete_transient($clave);
		$avisado = at_cc_registrar_fallo_recordatorio($envio, $wamid, (int) ($pendiente['codigo'] ?? 0), (string) ($pendiente['titulo'] ?? ''));
	}
	return ['ok' => true, 'fallo_avisado' => $avisado];
}

/** El envío anotado de este wamid, o null si no es de un recordatorio (o ya venció). */
function at_cc_envio_recordatorio_por_wamid(string $wamid): ?array {
	$e = get_transient(at_cc_clave_envio_recordatorio($wamid));
	if (!is_array($e) || !isset(at_cc_tipos_recordatorio()[$e['tipo'] ?? '']) || (int) ($e['id'] ?? 0) < 1) {
		return null;
	}
	return $e;
}

/**
 * Correo a Luis por un 'failed' de un recordatorio, una sola vez por wamid. false si ya se había avisado o
 * si otra llamada lo está avisando en este momento. Mismo candado que at_cc_registrar_fallo_whatsapp():
 * add_option() es atómico por la clave única de option_name. El «ya avisado» es un transient de 30 días.
 */
function at_cc_registrar_fallo_recordatorio(array $envio, string $wamid, int $codigo, string $titulo): bool {
	$candado = 'at_cc_wa_rec_fallo_' . md5($wamid);
	if (!add_option($candado, '1', '', false)) {
		return false;
	}
	try {
		$avisado = 'at_cc_wa_rec_avisado_' . md5($wamid);
		if (get_transient($avisado)) {
			return false;
		}
		at_cc_avisar_recordatorio_no_entregado($envio, at_cc_motivo_whatsapp_no_entregado($codigo, $titulo));
		set_transient($avisado, 1, 30 * DAY_IN_SECONDS);
		return true;
	} finally {
		delete_option($candado);
	}
}

/** La cita (lead) o la reunión de seguimiento del envío, con las mismas columnas; null si ya no existe. */
function at_cc_fila_de_recordatorio(array $envio): ?object {
	global $wpdb;
	$id = (int) $envio['id'];
	if (at_cc_tipos_recordatorio()[$envio['tipo']]['tabla'] === 'lead') {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT name AS nombre, '' AS empresa, phone AS telefono, scheduled_date AS fecha, scheduled_time AS hora FROM {$wpdb->prefix}automatiza_leads WHERE id = %d", $id));
	} else {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT client_name AS nombre, company_name AS empresa, phone AS telefono, meeting_date AS fecha, meeting_time AS hora FROM {$wpdb->prefix}automatiza_followup_meetings WHERE id = %d", $id));
	}
	return $fila ?: null;
}

/**
 * Asunto y cuerpo del correo (sin tocar la base, para probarlo solo). $fila es la de
 * at_cc_fila_de_recordatorio(); sin ella el correo dice que la cita ya no está.
 */
function at_cc_texto_recordatorio_no_entregado(array $envio, ?object $fila, string $motivo, string $enlace): array {
	$tipo = at_cc_tipos_recordatorio()[$envio['tipo']];
	$donde = $tipo['tabla'] === 'lead' ? 'la cita' : 'la reunión';
	$nombre = $fila ? trim((string) $fila->nombre) : '';
	$empresa = $fila ? trim((string) $fila->empresa) : '';
	$quien = $nombre !== '' ? $nombre : 'El cliente de ' . $donde . ' ' . (int) $envio['id'];
	$html = '<p>' . esc_html($quien . ($empresa !== '' ? ' (' . $empresa . ')' : '')) . ' no recibió por WhatsApp ' . esc_html($tipo['nombre']) . ': Meta lo recibió, pero no se lo entregó.</p>';
	if ($fila) {
		$fecha = DateTime::createFromFormat('Y-m-d', (string) $fila->fecha);
		$cuando = ($fecha ? $fecha->format('d-m-Y') : (string) $fila->fecha) . ' a las ' . substr((string) $fila->hora, 0, 5);
		$html .= '<p><strong>' . ucfirst(substr($donde, 3)) . ':</strong> ' . esc_html($cuando) . '</p>'
			. '<p><strong>Teléfono:</strong> ' . esc_html((string) $fila->telefono) . '</p>';
	} else {
		$html .= '<p>' . esc_html(ucfirst($donde)) . ' ' . (int) $envio['id'] . ' ya no está en la base.</p>';
	}
	$html .= '<p><strong>Motivo:</strong> ' . esc_html($motivo) . '</p>'
		. '<p>El mensaje no se vuelve a mandar solo. Escríbele o llámalo para confirmar.</p>';
	if ($fila) {
		$html .= '<p><a href="' . esc_url($enlace) . '">Abrir ' . $donde . ' en el panel</a></p>';
	}
	$que = strpos($tipo['nombre'], 'el recordatorio') === 0 ? 'el recordatorio' : 'el aviso';
	return ['asunto' => $quien . ' no recibió ' . $que . ' de WhatsApp', 'html' => $html];
}

/** Manda el correo a Luis (mismo remitente, destinatario y copia que los avisos del cierre). */
function at_cc_avisar_recordatorio_no_entregado(array $envio, string $motivo): void {
	$tabla = at_cc_tipos_recordatorio()[$envio['tipo']]['tabla'];
	$enlace = $tabla === 'lead'
		? admin_url('admin.php?page=automatiza-leads-manager&action=edit&id=' . (int) $envio['id'])
		: admin_url('admin.php?page=automatiza-followup&edit_id=' . (int) $envio['id']);
	$t = at_cc_texto_recordatorio_no_entregado($envio, at_cc_fila_de_recordatorio($envio), $motivo, $enlace);
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$destinatario = at_cc_correo_avisos();
	$headers = array_merge(['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'], at_cc_cabecera_copia($destinatario));
	if (!wp_mail($destinatario, $t['asunto'], $t['html'], $headers)) {
		error_log('at_cc: no salió el correo de un recordatorio de WhatsApp no entregado (' . $envio['tipo'] . ' ' . (int) $envio['id'] . ')');
	}
}
