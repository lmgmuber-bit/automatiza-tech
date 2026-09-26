<?php
/** WhatsApp del cierre: desde el teléfono de Luis (wa.me) o, cuando Meta apruebe la plantilla, automático vía n8n. */
if (!defined('ABSPATH')) {
	exit;
}

/** La plantilla de Meta está aprobada y conectada (opción que activa el controlador, y la URL del flujo de n8n). */
function at_cc_whatsapp_plantilla_activa(): bool {
	return get_option('at_cc_wa_plantilla_activa') === '1' && defined('AT_N8N_CC_WHATSAPP') && AT_N8N_CC_WHATSAPP !== '' && function_exists('at_cc_enviar_whatsapp_plantilla');
}

/**
 * Botón para mandar el WhatsApp desde el teléfono de Luis; '' si no hay teléfono.
 * Ronda 1, hallazgo 1: NO pasar $u por esc_url(). esc_url() borra '%0a'/'%0d' de cualquier URL, y
 * at_cc_url_wa_me() arma 'https://wa.me/<dígitos>?text=' . rawurlencode($texto), donde el texto
 * (at_cc_texto_whatsapp()) trae un salto de línea justo después del enlace para aceptar; esc_url()
 * dejaba el mensaje pegado ("...aceptar aquí: https://…&responder=aceptarSi tienes dudas..."),
 * y WhatsApp tomaba "&responder=aceptarSi" como parte del enlace. $u ya es seguro por construcción
 * (esquema https fijo, dígitos de at_cc_telefono_normalizado() y texto con rawurlencode, que nunca
 * deja '<', '>', '"', '\'' ni '&' sueltos), así que basta con esc_attr() para el atributo href.
 */
function at_cc_boton_wa_me(object $p): string {
	$u = at_cc_url_wa_me((string) $p->phone, at_cc_texto_whatsapp((string) $p->client_name, (string) $p->company_name, at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id, 'aceptar')));
	return $u === '' ? '' : '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_attr($u) . '">💬 Enviar por mi WhatsApp</a>';
}

/**
 * Después de enviar la propuesta por correo: queda anotada en Seguimiento y, si se pidió, sale o se
 * ofrece el WhatsApp.
 * Ajuste del controlador (obligatorio, no 'propuesta_enviada'): render_public_prospect_timeline()
 * (crm-ai-completo.php) solo agrega la tarjeta automática «Propuesta Creada» con el PDF cuando la
 * propuesta NO tiene ninguna nota de tipo 'propuesta_enviada'. Por eso el envío por correo se anota
 * con el tipo público 'email' (existe en get_detail_types(), no oculta esa tarjeta) y el WhatsApp con
 * el tipo interno 'pedido_respuesta' (at_cc_tipos_internos(), puras.php; el mismo que usa panel.php
 * para «Pedir respuesta»), que nunca se muestra en una página pública.
 */
function at_cc_tras_envio(object $p, bool $pidio_whatsapp): string {
	at_cc_anotar_simple($p, 'email', 'Propuesta enviada por correo, con el botón para aceptarla');
	if (!$pidio_whatsapp) {
		return '';
	}
	if (trim((string) $p->phone) === '') {
		return '<div class="notice notice-warning"><p>No se mandó WhatsApp: la propuesta no tiene teléfono.</p></div>';
	}
	if (at_cc_whatsapp_plantilla_activa()) {
		$error = at_cc_enviar_whatsapp_plantilla($p);
		if ($error === '') {
			at_cc_anotar_simple($p, 'pedido_respuesta', 'WhatsApp enviado con la plantilla (Meta confirma la entrega después)');
			return '<div class="notice notice-success"><p>WhatsApp enviado con los botones para responder. Meta confirma la entrega después.</p></div>';
		}
		return '<div class="notice notice-warning"><p>El WhatsApp automático falló (' . esc_html($error) . '). Mándalo desde tu teléfono: ' . at_cc_boton_wa_me($p) . '</p></div>';
	}
	return '<div class="notice notice-info"><p>Falta el WhatsApp: ' . at_cc_boton_wa_me($p) . ' (abre tu WhatsApp con el mensaje y el enlace para aceptar ya escritos).</p></div>';
}

/** Tras «Pedir respuesta»: detalles para el aviso del panel. */
function at_cc_whatsapp_tras_pedido(object $p): array {
	if (trim((string) $p->phone) === '') {
		return ['Sin teléfono: no se mandó WhatsApp.'];
	}
	if (at_cc_whatsapp_plantilla_activa()) {
		$error = at_cc_enviar_whatsapp_plantilla($p);
		if ($error === '') {
			at_cc_anotar_simple($p, 'pedido_respuesta', 'Se le pidió la respuesta por WhatsApp (plantilla)');
			return ['WhatsApp enviado con los botones para responder.'];
		}
		return ['El WhatsApp automático falló (' . $error . '): usa «Enviar por mi WhatsApp».'];
	}
	return ['Para el WhatsApp, usa «Enviar por mi WhatsApp».'];
}

/** Manda la plantilla de Meta a través del flujo de n8n. '' si respondió 2xx; si no, el motivo. */
function at_cc_enviar_whatsapp_plantilla(object $p): string {
	if (get_option('at_cc_wa_plantilla_activa') !== '1' || !defined('AT_N8N_CC_WHATSAPP') || AT_N8N_CC_WHATSAPP === '') {
		return 'La plantilla de WhatsApp no está activa.';
	}
	if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
		return 'AT_REST_SECRET no está configurado.';
	}
	$telefono = at_cc_telefono_normalizado((string) $p->phone);
	if ($telefono === '') {
		return 'La propuesta no tiene teléfono.';
	}
	$filas = at_cc_filas_de_propuesta($p);
	$propuesto = $filas ? trim(($filas[0]['service'] ?? '') . ', ' . ($filas[0]['price_label'] ?? ''), ' ,') : '';
	$r = wp_remote_post(AT_N8N_CC_WHATSAPP, [
		'timeout' => 15,
		'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
		'body'    => wp_json_encode([
			'codigo'    => (string) $p->unique_link_id,
			'telefono'  => $telefono,
			'nombre'    => (string) $p->client_name,
			'empresa'   => (string) $p->company_name,
			'propuesto' => $propuesto,
			'url'       => at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id),
		]),
	]);
	if (is_wp_error($r)) {
		return $r->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code($r);
	return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}

add_action('rest_api_init', function () {
	register_rest_route('at/v1', '/propuesta-respuesta', [
		'methods'             => 'POST',
		'callback'            => 'at_cc_rest_respuesta_whatsapp',
		'permission_callback' => 'at_cc_rest_auth',
	]);
});

function at_cc_rest_auth(WP_REST_Request $r) {
	if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
		return new WP_Error('sin_clave', 'AT_REST_SECRET no está configurado.', ['status' => 500]);
	}
	$dada = (string) $r->get_header('x_at_secret');
	if ($dada === '' || !hash_equals((string) AT_REST_SECRET, $dada)) {
		return new WP_Error('no_autorizado', 'Clave inválida.', ['status' => 401]);
	}
	return true;
}

/**
 * true si ya existe una nota interna 'aviso_operativo' de esta propuesta con este wamid (ronda 1,
 * hallazgo 2): evita que un reintento del webhook (mismo wamid) repita la nota y el correo a Luis.
 * $wamid vacío nunca deduplica: no hay forma de distinguir dos toques sin id de mensaje.
 */
function at_cc_wamid_ya_avisado(int $propuesta_id, string $wamid): bool {
	if ($wamid === '') {
		return false;
	}
	global $wpdb;
	$metadatas = $wpdb->get_col($wpdb->prepare(
		"SELECT metadata FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'aviso_operativo' AND metadata IS NOT NULL",
		$propuesta_id
	));
	foreach ((array) $metadatas as $m) {
		$d = json_decode((string) $m, true);
		if (is_array($d) && (string) ($d['wamid'] ?? '') === $wamid) {
			return true;
		}
	}
	return false;
}

/** El bot principal de WhatsApp llama aquí cuando el cliente toca un botón de la plantilla. */
function at_cc_rest_respuesta_whatsapp(WP_REST_Request $r) {
	$salida = sanitize_key((string) $r->get_param('salida'));
	$tel = sanitize_text_field((string) $r->get_param('telefono'));
	$wamid = sanitize_text_field((string) $r->get_param('wamid'));
	$p = at_cc_propuesta_por_codigo(sanitize_text_field((string) $r->get_param('codigo')));
	if (!$p) {
		return ['ok' => false, 'motivo' => 'no_existe'];
	}
	$esperado = at_cc_telefono_normalizado((string) $p->phone);
	if ($esperado === '' || $esperado !== at_cc_telefono_normalizado($tel)) {
		if (!at_cc_wamid_ya_avisado((int) $p->id, $wamid)) {
			at_cc_anotar_simple($p, 'aviso_operativo', 'Respuesta por WhatsApp desde un número distinto (no se aplicó)', 'Salida: ' . $salida . ' · número que respondió: ' . $tel, ['wamid' => $wamid]);
			at_cc_avisar_numero_distinto($p, $salida, $tel);
		}
		return ['ok' => false, 'motivo' => 'telefono'];
	}
	// Ronda 1, hallazgo 1: la propuesta ya está aceptada y tocaron un botón distinto de «Acepto» (los
	// botones de la plantilla quedan en el chat para siempre). No se repite el cierre ni cambia el
	// estado; solo queda un rastro interno y un aviso a Luis, deduplicado por wamid como arriba.
	if ((string) $p->status === 'aceptada' && $salida !== 'acepta') {
		if (!at_cc_wamid_ya_avisado((int) $p->id, $wamid)) {
			at_cc_anotar_simple($p, 'aviso_operativo', 'Tocó un botón de WhatsApp después de haber aceptado (no se aplicó)', 'Salida: ' . $salida, ['wamid' => $wamid]);
			at_cc_avisar_respuesta_tras_aceptar($p, $salida);
		}
		return ['ok' => false, 'motivo' => 'ya_aceptada_otra_salida'];
	}
	// T14 ronda 1, hallazgo 2: los botones de la plantilla quedan en el chat aunque la propuesta se haya
	// archivado. at_cc_registrar_respuesta() la rechaza (el bot recibe la misma respuesta de siempre),
	// pero el intento queda como nota interna para Luis, deduplicada por wamid como las de arriba.
	if ((string) $p->status === 'archivada' && isset(at_cc_salidas()[$salida]) && !at_cc_wamid_ya_avisado((int) $p->id, $wamid)) {
		at_cc_anotar_intento_archivada($p, $salida, 'whatsapp', ['wamid' => $wamid, 'telefono' => $tel]);
	}
	$res = at_cc_registrar_respuesta($p, $salida, [
		'canal'      => 'whatsapp',
		'telefono'   => $tel,
		'wamid'      => $wamid,
		'nombre'     => (string) $p->client_name,
		'filas'      => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]),
		'fecha'      => current_time('mysql'),
		'bienvenida' => true,
	]);
	return ['ok' => (bool) $res['ok'], 'estado' => (string) $res['estado'], 'motivo' => (string) $res['mensaje']];
}

/** Aviso a Luis cuando alguien responde el WhatsApp de la propuesta desde otro número (aprobado por Luis el 25-sep). */
function at_cc_avisar_numero_distinto(object $p, string $salida, string $tel): void {
	$botones = ['acepta' => 'Acepto la propuesta', 'evalua' => 'La sigo evaluando', 'rechaza' => 'No, gracias'];
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$html = '<p>Alguien tocó «' . esc_html($botones[$salida] ?? $salida) . '» en el WhatsApp de la propuesta ' . esc_html((string) $p->unique_link_id)
		. ', pero desde un número distinto al de la propuesta (' . esc_html($tel) . '). No se cambió el estado.</p>'
		. '<p>Revisa la ficha y confirma con el cliente. Si la respuesta es válida, regístrala con «Registrar aceptación».</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$destinatario = at_cc_correo_avisos();
	$headers = array_merge(['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'], at_cc_cabecera_copia($destinatario));
	wp_mail($destinatario, 'Respuesta a la propuesta de ' . $quien . ' desde otro número', $html, $headers);
}

/**
 * Aviso a Luis cuando alguien toca «No, gracias» o «La sigo evaluando» en el WhatsApp de la propuesta
 * DESPUÉS de haber aceptado (ronda 1, hallazgo 1). No se cambió nada: es informativo, para que Luis
 * confirme con el cliente si hace falta (mismo formato que at_cc_avisar_numero_distinto).
 */
function at_cc_avisar_respuesta_tras_aceptar(object $p, string $salida): void {
	$botones = ['acepta' => 'Acepto la propuesta', 'evalua' => 'La sigo evaluando', 'rechaza' => 'No, gracias'];
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$boton = $botones[$salida] ?? $salida;
	$html = '<p>' . esc_html($quien) . ' tocó «' . esc_html($boton) . '» en el WhatsApp de la propuesta ' . esc_html((string) $p->unique_link_id)
		. ', pero ya la había aceptado antes. No se cambió el estado ni se repitió el cierre.</p>'
		. '<p>Puede ser un toque accidental o un mensaje viejo del chat: confirma con el cliente si hace falta.</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$destinatario = at_cc_correo_avisos();
	$headers = array_merge(['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'], at_cc_cabecera_copia($destinatario));
	wp_mail($destinatario, $quien . ' tocó «' . $boton . '» en WhatsApp después de aceptar', $html, $headers);
}

add_action('rest_api_init', function () {
	register_rest_route('at/v1', '/propuesta-contexto', [
		'methods'             => 'POST',
		'callback'            => 'at_cc_rest_contexto_whatsapp',
		'permission_callback' => 'at_cc_rest_auth',
	]);
});

/** La propuesta más reciente enviada o en evaluación para ese teléfono; null si no hay. */
function at_cc_propuesta_pendiente_por_telefono(string $tel): ?object {
	global $wpdb;
	$n = at_cc_telefono_normalizado($tel);
	if (strlen($n) < 8) {
		return null;
	}
	// Ronda 1 de revisión (26-sep), hallazgo 4: con $wpdb->prepare() (los estados son literales, pero
	// van como parámetros igual que el resto del archivo) y tope 1000 en vez de 200: con ~2 leads/mes
	// (Docs/2026-09-04-RADIOGRAFIA-AT-ESTUDIO-EXHAUSTIVO.md) el total de propuestas 'sent'/'evaluando'
	// nunca se acerca a esa cifra, así que 1000 sigue trayendo todas sin paginar.
	$filas = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE status IN (%s,%s) AND phone <> '' ORDER BY id DESC LIMIT 1000",
		'sent',
		'evaluando'
	));
	foreach ((array) $filas as $p) {
		if (at_cc_telefono_normalizado((string) $p->phone) === $n) {
			return $p;
		}
	}
	return null;
}

/** El bot principal consulta aquí antes de pasarle un mensaje de texto a la IA. Un texto nunca cambia el estado. */
function at_cc_rest_contexto_whatsapp(WP_REST_Request $r) {
	$p = at_cc_propuesta_pendiente_por_telefono(sanitize_text_field((string) $r->get_param('telefono')));
	if (!$p) {
		return ['tiene' => false];
	}
	// Ronda 1 de revisión (26-sep), hallazgo 2: no usar sanitize_textarea_field() para guardar — borra
	// '%XX' (lo confunde con una secuencia %-encoded) y convierte '<' en la entidad '&lt;' antes de que
	// el mensaje llegue a la nota o al correo. Se guarda el texto tal cual llegó (solo UTF-8 válido y
	// sin caracteres de control) y se escapa recién a la salida (esc_html en el correo y en el CRM).
	$mensaje = trim(wp_check_invalid_utf8((string) $r->get_param('mensaje'), true));
	$mensaje = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $mensaje);
	if ($mensaje !== '') {
		$mensaje = mb_substr($mensaje, 0, 1000);
		// Ajuste del controlador (26-sep): 'mensaje_whatsapp' es un tipo interno (at_cc_tipos_internos(),
		// puras.php), no el público 'respuesta_cliente': es una nota, nunca una respuesta real.
		// Ronda 1 de revisión (26-sep), hallazgo 1: la nota se guarda SIEMPRE; solo el correo a Luis
		// queda detrás del transient de 30 minutos, para no perder mensajes intermedios del chat.
		at_cc_anotar_simple($p, 'mensaje_whatsapp', 'Mensaje por WhatsApp sobre la propuesta (no cambia el estado)', $mensaje);
		$clave = 'at_cc_ctx_aviso_' . (int) $p->id;
		if (!get_transient($clave)) {
			set_transient($clave, 1, 30 * MINUTE_IN_SECONDS);
			at_cc_avisar_mensaje_whatsapp($p, $mensaje);
		}
	}
	$filas = at_cc_filas_de_propuesta($p);
	return [
		'tiene'     => true,
		'codigo'    => (string) $p->unique_link_id,
		'nombre'    => (string) $p->client_name,
		'empresa'   => (string) $p->company_name,
		'estado'    => (string) $p->status,
		'propuesto' => $filas ? trim(($filas[0]['service'] ?? '') . ', ' . ($filas[0]['price_label'] ?? ''), ' ,') : '',
		'url'       => at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id),
	];
}

/** Correo a Luis con lo que el cliente escribió por WhatsApp sobre su propuesta. */
function at_cc_avisar_mensaje_whatsapp(object $p, string $mensaje): void {
	$quien = trim((string) $p->company_name) !== '' ? (string) $p->company_name : (string) $p->client_name;
	$html = '<p>' . esc_html($quien) . ' escribió por WhatsApp sobre su propuesta (' . esc_html((string) $p->unique_link_id) . '):</p>'
		. '<blockquote style="border-left:3px solid #10b981;margin:0 0 12px 0;padding:8px 12px">' . nl2br(esc_html($mensaje)) . '</blockquote>'
		. '<p>El bot le pidió usar el botón «Acepto la propuesta» o el enlace para dejar registrada su respuesta; un mensaje de texto no cambia el estado. Si ya te dijo que sí, regístralo con «Registrar aceptación». Puede haber más mensajes en el chat: este aviso sale como máximo una vez cada 30 minutos.</p>'
		. '<p><a href="' . esc_url(admin_url('admin.php?page=automatiza-proposals&edit_id=' . (int) $p->id . '&tab=envio')) . '">Abrir la propuesta en el panel</a></p>';
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$destinatario = at_cc_correo_avisos();
	$headers = array_merge(['Content-Type: text/html; charset=UTF-8', 'From: Automatiza Tech <' . $from . '>'], at_cc_cabecera_copia($destinatario));
	wp_mail($destinatario, $quien . ' escribió por WhatsApp sobre su propuesta', $html, $headers);
}
