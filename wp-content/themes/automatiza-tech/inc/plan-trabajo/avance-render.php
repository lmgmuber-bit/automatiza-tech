<?php
/**
 * Estado del proyecto (Etapa 2): render de la presentación (spec §7). GET /plan/{id}/render&modo=estado arma el cuerpo
 * para n8n; POST /plan/{id}/vista con modo estado guarda lo que n8n cuenta; at_av_pedir_render() avisa a n8n («3 Render»,
 * modo estado). Nunca cambia el estado del plan ni su view_url/pdf_url. El cliente solo ve publicados.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Token de la vista previa con borradores (R3): 10 hex, fijo por plan, no se adivina sin la sal del sitio. */
function at_av_token_previa(string $codigo): string {
	return substr(hash_hmac('sha256', 'at-estado-previa|' . $codigo, wp_salt('auth')), 0, 10);
}

/** Carpeta del renderer: <código>-estado (pública) o <código>-previa-<token> (vista previa). */
function at_av_uid_render(string $codigo, bool $previa): string {
	return $previa ? $codigo . '-previa-' . at_av_token_previa($codigo) : $codigo . '-estado';
}

/** Enlace del renderer para ese plan y ese registro: solo https://<renderer>/p/<uid>/index.html o presentation.pdf. */
function at_av_url_render_valida(string $u, string $codigo, bool $previa): string {
	$u = trim($u);
	$patron = '#^https://' . preg_quote(at_pt_host_renderer(), '#') . '/p/' . preg_quote(at_av_uid_render($codigo, $previa), '#') . '/(index\.html|presentation\.pdf)$#';
	return preg_match($patron, $u) ? $u : '';
}

/**
 * URL pública (ver-entregable.php) de un entregable, solo si existe y es del MISMO cliente del CRM que el plan
 * (spec §9: un cierre no puede enlazar el entregable de otro cliente). '' si no.
 */
function at_av_entregable_del_plan(object $fila, int $entregable_id): string {
	if ($entregable_id <= 0 || !function_exists('at_en_por_id') || !function_exists('at_en_detalle') || !function_exists('at_en_crm_de_detalle') || !function_exists('at_en_url_pagina')) {
		return '';
	}
	$crm_plan = (int) at_pt_db_partes($fila)['crm_id'];
	$e = at_en_por_id($entregable_id);
	$detalle = $e ? at_en_detalle((int) $e->detalle_id) : null;
	if (!$e || !$detalle || $crm_plan <= 0 || at_en_crm_de_detalle($detalle) !== $crm_plan) {
		return '';
	}
	return at_en_url_pagina(home_url(), (string) $e->codigo);
}

/** URL públicas (ver-entregable.php) de los entregables que nombran los cierres y son de este cliente: [id => url]. */
function at_av_urls_entregables(object $fila, array $avance): array {
	$out = [];
	foreach ($avance['cierres'] as $c) {
		$id = (int) ($c['entregable_id'] ?? 0);
		if ($id <= 0 || isset($out[$id])) {
			continue;
		}
		$u = at_av_entregable_del_plan($fila, $id);
		if ($u !== '') {
			$out[$id] = $u;
		}
	}
	return $out;
}

/** GET /plan/{id}/render&modo=estado[&previa=1]: cuerpo para el renderer. Solo planes enviados con la migración hecha. */
function at_av_rest_render_estado(object $f, bool $previa) {
	if ((string) $f->estado !== 'enviado') {
		return new WP_Error('at_av_no_enviado', 'El estado del proyecto existe solo para planes enviados.', ['status' => 409]);
	}
	if (!at_av_columna_lista()) {
		return new WP_Error('at_av_sin_columna', 'Falta la migración del avance (columna avance).', ['status' => 409]);
	}
	$avance = at_av_leer($f);
	$datos = at_pt_datos_render($f) + ['previa_token' => at_av_token_previa((string) $f->codigo), 'entregables' => at_av_urls_entregables($f, $avance)];
	return new WP_REST_Response([
		'ok'             => true,
		'render'         => at_av_armar_render_estado(at_pt_payload($f), $avance, $datos, $previa, current_time('Y-m-d')),
		'plan_codigo'    => (string) $f->codigo,
		'propuesta_uid'  => at_pt_uid_propuesta($f),
		'crm_cliente_id' => (int) at_pt_db_partes($f)['crm_id'],
		'estado'         => (string) $f->estado,
	], 200);
}

/**
 * POST /plan/{id}/vista con modo estado: guarda el resultado en avance.render (o render_previa). Con un enlace válido de
 * ESE plan y ESE registro, queda «listo»; si no, «error» con la nota, y los enlaces anteriores se conservan (el cliente
 * sigue viendo la última versión lista: Review Focus 5). Nunca toca el estado del plan ni su view_url/pdf_url.
 */
function at_av_rest_vista_estado(object $f, array $p): WP_REST_Response {
	$previa = !empty($p['previa']) && $p['previa'] !== 'false';
	$ok = !empty($p['ok']) && $p['ok'] !== 'false';
	$codigo = (string) $f->codigo;
	$view = at_av_url_render_valida((string) ($p['view_url'] ?? ''), $codigo, $previa);
	$pdf = at_av_url_render_valida((string) ($p['pdf_url'] ?? ''), $codigo, $previa);
	$nota = mb_substr(sanitize_textarea_field((string) ($p['nota'] ?? '')), 0, 500);
	$ahora = current_time('mysql');
	if ($ok && $view !== '' && str_ends_with($view, '/index.html')) {
		$reg = ['estado' => 'listo', 'view_url' => $view, 'pdf_url' => str_ends_with($pdf, '/presentation.pdf') ? $pdf : '', 'nota' => $nota, 'at' => $ahora];
	} else {
		$reg = ['estado' => 'error', 'nota' => $nota !== '' ? $nota : 'El renderer no devolvió una presentación válida.', 'at' => $ahora];
	}
	// Render público serializado: si mientras este pedido corría llegó otro (repetir), lo que devuelve este es del pedido
	// anterior y puede no traer lo último que se publicó. Se guardan sus enlaces pero sigue «pendiente» y se manda el
	// siguiente pedido; solo la devolución del último deja «listo». Se decide sobre el registro vigente (reintenta si cambió).
	$repite = false;
	$guardado = at_av_cambiar_render((int) $f->id, $previa ? 'render_previa' : 'render', function (array $actual) use ($reg, $previa, $ahora, &$repite): array {
		$repite = !$previa && $actual['repetir'] === true;
		return $repite
			? array_merge($reg, ['estado' => 'pendiente', 'nota' => '', 'repetir' => false, 'pedido_at' => $ahora])
			: array_merge($reg, ['repetir' => false]);
	});
	if ($repite && $guardado) {
		at_av_avisar_render($f, false);
	}
	return new WP_REST_Response(['ok' => true, 'estado' => (string) $f->estado], 200);
}

/** Presentación que ve el cliente en ver-estado.php: la última lista, si hay al menos un cierre publicado; '' si no. */
function at_av_vista_publica(object $fila): string {
	if (!at_av_columna_lista()) {
		return '';
	}
	$a = at_av_leer($fila);
	if (!at_av_publicados($a)) {
		return '';
	}
	return at_av_url_render_valida((string) $a['render']['view_url'], (string) $fila->codigo, false);
}

/** Segundos que un pedido de render público sigue contando como «en curso» (después se da por perdido y se pide otro). */
const AT_AV_RENDER_VIGENCIA = 600;

/** Avisa a n8n («3 Render», modo estado). Si no lo recibe deja el registro en «error» con el motivo (los enlaces
 *  anteriores se conservan) y sin pedido pendiente. Devuelve '' o el motivo. */
function at_av_avisar_render(object $f, bool $previa): string {
	$cuerpo = ['id' => (int) $f->id, 'codigo' => (string) $f->codigo, 'modo' => 'estado'];
	if ($previa) {
		$cuerpo['previa'] = true;
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, $cuerpo);
	if ($motivo !== '') {
		at_av_guardar_render((int) $f->id, $previa ? 'render_previa' : 'render', ['estado' => 'error', 'nota' => 'No se pudo pedir la presentación: ' . $motivo, 'at' => current_time('mysql'), 'repetir' => false]);
	}
	return $motivo;
}

/**
 * Pide a n8n el render de «Estado del proyecto» (o su vista previa con borradores). Deja el registro en «pendiente»;
 * si n8n no recibe el aviso, en «error» con el motivo (los enlaces anteriores se conservan). Devuelve '' o el motivo.
 *
 * El render público se serializa: n8n devuelve el resultado de cada pedido por su cuenta y, si dos corren a la vez, la
 * devolución de uno anterior podría marcar «lista» una presentación sin lo último publicado. Por eso, si ya hay un
 * pedido público «pendiente» de hace menos de AT_AV_RENDER_VIGENCIA segundos, no se avisa a n8n: se anota `repetir` y
 * este pedido sale solo cuando llegue la devolución del que está en curso (at_av_rest_vista_estado). La vista previa no
 * se serializa. Devuelve '' también cuando el pedido queda en cola.
 */
function at_av_pedir_render(int $plan_id, bool $previa = false): string {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'El plan no existe.';
	}
	$clave = $previa ? 'render_previa' : 'render';
	$en_cola = false;
	$guardado = at_av_cambiar_render($plan_id, $clave, function (array $actual) use ($previa, &$en_cola): array {
		$ahora = current_time('mysql');
		$en_cola = !$previa && $actual['estado'] === 'pendiente' && $actual['pedido_at'] !== ''
			&& abs(strtotime($ahora) - (int) strtotime($actual['pedido_at'])) < AT_AV_RENDER_VIGENCIA;
		return $en_cola
			? ['repetir' => true]
			: ['estado' => 'pendiente', 'nota' => '', 'at' => $ahora, 'pedido_at' => $ahora, 'repetir' => false];
	});
	if (!$guardado) {
		return 'No pudimos registrar el pedido de la presentación (el plan cambió a la vez): inténtalo de nuevo.';
	}
	return $en_cola ? '' : at_av_avisar_render($f, $previa);
}

/** Rehace la presentación pública solo si hay al menos un cierre publicado (R4). Devuelve '' o el motivo. */
function at_av_refrescar(int $plan_id): string {
	$f = at_pt_plan($plan_id);
	if (!$f || !at_av_publicados(at_av_leer($f))) {
		return '';
	}
	return at_av_pedir_render($plan_id, false);
}
