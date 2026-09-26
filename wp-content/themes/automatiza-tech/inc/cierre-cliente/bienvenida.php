<?php
/** Correo de bienvenida con la lista de arranque (reemplaza al del CRM, también al convertir a mano). */
if (!defined('ABSPATH')) {
	exit;
}

define('AT_CC_LOGO', 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');

/** Deja un evento en el historial del CRM. */
function at_cc_historial_crm(int $crm_id, string $tipo, string $titulo, string $descripcion): void {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_historial', [
		'cliente_id'  => $crm_id,
		'tipo_evento' => $tipo,
		'titulo'      => $titulo,
		'descripcion' => $descripcion,
		'usuario_id'  => get_current_user_id(),
		'created_at'  => current_time('mysql'),
	]);
}

function at_cc_enviar_bienvenida(int $crm_id, ?object $p = null, array $filas = []): bool {
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT id, nombre, email, empresa, tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	if (!$c || !is_email((string) $c->email) || (string) $c->tipo !== 'cliente') {
		return false;
	}
	if ($p === null) {
		$p = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE LOWER(TRIM(client_email)) = %s AND status = 'aceptada' ORDER BY id DESC LIMIT 1",
			at_cc_email_normalizado((string) $c->email)
		)) ?: null;
		if ($p) {
			$u = at_cc_ultima_respuesta((int) $p->id);
			$filas = ($u && $u['salida'] === 'acepta') ? (array) ($u['filas'] ?? []) : [];
		}
	}
	$html = at_cc_bienvenida_html([
		'nombre'        => (string) $c->nombre,
		'empresa'       => $p ? (string) $p->company_name : (string) $c->empresa,
		'anticipo'      => $filas ? at_cc_anticipo($filas) : null,
		'banco'         => at_cc_datos_banco(),
		'correo_pago'   => (string) get_option('at_cc_correo_pago', ''),
		'whatsapp'      => at_cc_whatsapp_at(),
		'url_portal'    => function_exists('at_crm_url_portal') ? at_crm_url_portal($crm_id) : '',
		'logo'          => AT_CC_LOGO,
		'con_propuesta' => (bool) $p,
	]);
	$from = defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl';
	$headers = [
		'Content-Type: text/html; charset=UTF-8',
		'From: Automatiza Tech <' . $from . '>',
		'Reply-To: ' . get_option('admin_email'),
		'Bcc: lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl',
	];
	$enviado = wp_mail((string) $c->email, '¡Te damos la bienvenida a AutomatizaTech! Tus primeros pasos', $html, $headers);
	at_cc_historial_crm($crm_id, 'email_bienvenida', 'Correo de bienvenida enviado', $enviado ? 'Se envió la bienvenida con los primeros pasos.' : 'Falló el envío de la bienvenida.');
	return (bool) $enviado;
}
