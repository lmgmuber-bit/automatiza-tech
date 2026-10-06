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
function at_en_url_ficha(int $crm_id, int $ent_id = 0, string $msg = ''): string {
	$args = ['page' => 'automatiza-crm-ficha', 'id' => $crm_id];
	if ($ent_id > 0) {
		$args['en'] = $ent_id;
	}
	if ($msg !== '') {
		$args['en_msg'] = $msg;
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
	if (!wp_mail($para, $c['asunto'], $c['html'], $cab)) {
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
		return false;
	}
	$c = at_en_correo_respuesta([
		'nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => (int) $n->version_numero, 'texto' => (string) $n->texto,
		'url_pagina' => at_en_url_pagina(home_url(), (string) $ent->codigo), 'logo' => at_en_logo(),
	]);
	return (bool) wp_mail($cli['email'], $c['asunto'], $c['html'], at_en_cabeceras_cliente($cli['email']));
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
