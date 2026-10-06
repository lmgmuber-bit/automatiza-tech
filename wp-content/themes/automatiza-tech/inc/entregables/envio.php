<?php
/**
 * Envío por correo (versión, prueba, aviso de nota, aviso de respuesta) y textos/enlaces de WhatsApp que manda Luis.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_logo(): string {
	return defined('AT_CC_LOGO') ? AT_CC_LOGO : home_url('/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png');
}

/** Pestaña «Entregables» de la ficha del cliente en el CRM. */
function at_en_url_ficha(int $crm_id, int $ent_id = 0, string $msg = '', int $n_img = 0): string {
	$args = ['page' => 'automatiza-crm-ficha', 'id' => $crm_id];
	if ($ent_id > 0) {
		$args['en'] = $ent_id;
	}
	if ($msg !== '') {
		$args['en_msg'] = $msg;
	}
	if ($n_img > 0) {
		$args['en_img'] = $n_img;
	}
	return add_query_arg($args, admin_url('admin.php')) . '#tab-entregables';
}

/** Datos del cliente del CRM y título del entregable. */
function at_en_cliente(object $ent): array {
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT nombre, email, telefono, empresa FROM {$wpdb->prefix}crm_clientes WHERE id = %d", (int) $ent->crm_id));
	$d = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}automatiza_clients_details WHERE id = %d", (int) $ent->detalle_id));
	$nombre = trim((string) ($c->nombre ?? ''));
	$titulo = trim((string) preg_replace('/\s+/u', ' ', (string) ($d->title ?? '')));
	return [
		'nombre'   => $nombre !== '' ? trim(explode(' ', $nombre)[0]) : '',
		'email'    => is_email((string) ($c->email ?? '')) ? (string) $c->email : '',
		'telefono' => (string) ($c->telefono ?? ''),
		'empresa'  => (string) ($c->empresa ?? ''),
		'titulo'   => $titulo !== '' ? $titulo : 'Entregable',
	];
}

function at_en_from(): string {
	return 'From: Automatiza Tech <' . (defined('SMTP_USER') ? SMTP_USER : 'contacto@automatizatech.cl') . '>';
}

/** Cabeceras de un correo al cliente: Reply-To de avisos y copias ocultas (sin duplicar ni copiar al destinatario). */
function at_en_cabeceras_cliente(string $para): array {
	$h = ['Content-Type: text/html; charset=UTF-8', at_en_from()];
	if (function_exists('at_cc_correo_avisos')) {
		$h[] = 'Reply-To: ' . at_cc_correo_avisos();
	}
	$bcc = [];
	foreach (function_exists('at_cc_cabecera_copia') ? at_cc_cabecera_copia($para) : [] as $linea) {
		$bcc[] = strtolower(trim(substr($linea, 4)));
	}
	$bcc[] = 'lgonzalez@automatizatech.cl';
	foreach (array_unique($bcc) as $b) {
		if ($b !== '' && $b !== strtolower(trim($para))) {
			$h[] = 'Bcc: ' . $b;
		}
	}
	return $h;
}

/** Clave del motivo de la última falla de envío de quien está en el panel (cada usuario ve solo el suyo). */
function at_en_clave_motivo_fallo(int $ent_id): string {
	return 'at_en_fallo_' . get_current_user_id() . '_' . $ent_id;
}

/** Guarda 60 s el motivo de una falla de envío para mostrarlo en el panel tras la redirección. */
function at_en_guardar_motivo_fallo(int $ent_id, string $motivo): void {
	$motivo = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($motivo)));
	if ($motivo === '') {
		delete_transient(at_en_clave_motivo_fallo($ent_id));
		return;
	}
	set_transient(at_en_clave_motivo_fallo($ent_id), mb_substr($motivo, 0, 300, 'UTF-8'), MINUTE_IN_SECONDS);
}

/** Motivo guardado de la última falla de envío ('' si no hay o ya pasó un minuto). Se escapa al mostrar. */
function at_en_motivo_fallo(int $ent_id): string {
	$m = get_transient(at_en_clave_motivo_fallo($ent_id));
	return is_string($m) ? $m : '';
}

/** wp_mail que, si falla, deja guardado el motivo que informó wp_mail_failed (SMTP, autenticación…). */
function at_en_enviar_correo(int $ent_id, string $para, string $asunto, string $html, array $cab): bool {
	$motivo = '';
	$captura = function ($err) use (&$motivo) {
		if (is_wp_error($err)) {
			$motivo = (string) $err->get_error_message();
		}
	};
	add_action('wp_mail_failed', $captura);
	try {
		$ok = (bool) wp_mail($para, $asunto, $html, $cab);
	} finally {
		remove_action('wp_mail_failed', $captura);
	}
	at_en_guardar_motivo_fallo($ent_id, $ok ? '' : $motivo);
	return $ok;
}

/** Envía la versión N al cliente (o a Luis si $prueba). Una versión ya enviada por correo no se reenvía. */
function at_en_enviar_version(int $ent_id, int $numero, bool $prueba): array {
	$ent = at_en_por_id($ent_id);
	if (!$ent) {
		return ['ok' => false, 'motivo' => 'sin_entregable'];
	}
	$v = at_en_version($ent_id, $numero);
	if (!$v) {
		return ['ok' => false, 'motivo' => 'sin_version'];
	}
	if (!$prueba && $v->enviado_correo_at !== null) {
		return ['ok' => false, 'motivo' => 'ya_enviada'];
	}
	$cli = at_en_cliente($ent);
	$para = $prueba ? (function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : (string) get_option('admin_email')) : $cli['email'];
	if ($para === '') {
		return ['ok' => false, 'motivo' => 'sin_correo'];
	}
	// Envío real: se reclama la versión de forma atómica para que dos clics no manden dos correos.
	if (!$prueba && !at_en_reclamar_envio_correo($ent_id, $numero)) {
		return ['ok' => false, 'motivo' => 'ya_enviada'];
	}
	$c = at_en_correo_version([
		'nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => $numero, 'url_version' => (string) $v->url,
		'url_pagina' => at_en_url_pagina(home_url(), (string) $ent->codigo), 'mensaje' => (string) $v->mensaje,
		'logo' => at_en_logo(), 'prueba' => $prueba,
	]);
	$cab = $prueba ? ['Content-Type: text/html; charset=UTF-8', at_en_from()] : at_en_cabeceras_cliente($para);
	if (!at_en_enviar_correo($ent_id, $para, $c['asunto'], $c['html'], $cab)) {
		if (!$prueba) {
			at_en_soltar_envio_correo($ent_id, $numero);
		}
		return ['ok' => false, 'motivo' => 'correo_fallo'];
	}
	if (!$prueba) {
		at_en_historial((int) $ent->crm_id, 'entregable_version', $cli['titulo'] . ': versión ' . $numero . ' enviada por correo', 'Enlace: ' . $v->url . ' · Entregable ' . $ent_id . '.');
	}
	return ['ok' => true, 'motivo' => ''];
}

/** Aviso a Luis de una nota nueva del cliente. Si falla, la nota queda marcada «sin aviso». */
function at_en_avisar_nota(int $nota_id): bool {
	$n = at_en_nota($nota_id);
	$ent = $n ? at_en_por_id((int) $n->entregable_id) : null;
	if (!$n || !$ent || $n->autor !== 'cliente') {
		return false;
	}
	$cli = at_en_cliente($ent);
	$imgs = json_decode((string) $n->imagenes, true);
	$c = at_en_correo_nota_luis([
		'cliente' => (string) $n->nombre, 'empresa' => $cli['empresa'], 'titulo' => $cli['titulo'], 'numero' => (int) $n->version_numero,
		'texto' => (string) $n->texto, 'imagenes' => is_array($imgs) ? count($imgs) : 0,
		'url_ficha' => at_en_url_ficha((int) $ent->crm_id, (int) $ent->id), 'logo' => at_en_logo(),
	]);
	$para = function_exists('at_cc_correo_avisos') ? at_cc_correo_avisos() : (string) get_option('admin_email');
	$ok = wp_mail($para, $c['asunto'], $c['html'], ['Content-Type: text/html; charset=UTF-8', at_en_from()]);
	at_en_marcar_aviso($nota_id, (bool) $ok);
	return (bool) $ok;
}

/** Aviso al cliente de una respuesta de AT. */
function at_en_avisar_respuesta(int $nota_id): bool {
	$n = at_en_nota($nota_id);
	$ent = $n ? at_en_por_id((int) $n->entregable_id) : null;
	if (!$n || !$ent || $n->autor !== 'at') {
		return false;
	}
	$cli = at_en_cliente($ent);
	if ($cli['email'] === '') {
		at_en_guardar_motivo_fallo((int) $ent->id, ''); // sin correo no hay falla de envío que explicar
		return false;
	}
	$c = at_en_correo_respuesta([
		'nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => (int) $n->version_numero, 'texto' => (string) $n->texto,
		'url_pagina' => at_en_url_pagina(home_url(), (string) $ent->codigo), 'logo' => at_en_logo(),
	]);
	return at_en_enviar_correo((int) $ent->id, $cli['email'], $c['asunto'], $c['html'], at_en_cabeceras_cliente($cli['email']));
}

function at_en_texto_wa_version(object $ent, int $numero): string {
	$cli = at_en_cliente($ent);
	return at_en_texto_whatsapp_version($cli['nombre'], $numero, $cli['titulo'], at_en_url_pagina(home_url(), (string) $ent->codigo));
}

function at_en_url_wa_version(object $ent, int $numero): string {
	return at_en_url_wa(at_en_cliente($ent)['telefono'], at_en_texto_wa_version($ent, $numero));
}

function at_en_texto_wa_respuesta(object $ent, int $numero): string {
	$cli = at_en_cliente($ent);
	return at_en_texto_whatsapp_respuesta($cli['nombre'], $numero, $cli['titulo'], at_en_url_pagina(home_url(), (string) $ent->codigo));
}

function at_en_url_wa_respuesta(object $ent, int $numero): string {
	return at_en_url_wa(at_en_cliente($ent)['telefono'], at_en_texto_wa_respuesta($ent, $numero));
}
