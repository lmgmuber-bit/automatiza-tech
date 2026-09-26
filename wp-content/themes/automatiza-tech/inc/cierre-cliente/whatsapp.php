<?php
/** WhatsApp del cierre: desde el teléfono de Luis (wa.me) o, cuando Meta apruebe la plantilla, automático vía n8n. */
if (!defined('ABSPATH')) {
	exit;
}

/** La plantilla de Meta está aprobada y conectada (opción que activa el controlador, y la URL del flujo de n8n). */
function at_cc_whatsapp_plantilla_activa(): bool {
	return get_option('at_cc_wa_plantilla_activa') === '1' && defined('AT_N8N_CC_WHATSAPP') && AT_N8N_CC_WHATSAPP !== '' && function_exists('at_cc_enviar_whatsapp_plantilla');
}

/** Botón para mandar el WhatsApp desde el teléfono de Luis; '' si no hay teléfono. */
function at_cc_boton_wa_me(object $p): string {
	$u = at_cc_url_wa_me((string) $p->phone, at_cc_texto_whatsapp((string) $p->client_name, (string) $p->company_name, at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id, 'aceptar')));
	return $u === '' ? '' : '<a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url($u) . '">💬 Enviar por mi WhatsApp</a>';
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
