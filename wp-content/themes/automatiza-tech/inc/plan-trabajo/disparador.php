<?php
/**
 * Plan de trabajo: disparador al firmar el contrato y avisos a los flujos de n8n.
 * WordPress → n8n con la cabecera X-AT-Secret (AT_REST_SECRET), igual que at_v3_llamar_n8n(): no hay
 * secreto nuevo. Las URL se pueden cambiar en wp-config.php (el sitio de prueba local las apunta a su
 * simulador).
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!defined('AT_N8N_PLAN_BORRADOR')) {
	define('AT_N8N_PLAN_BORRADOR', 'https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-borrador');
}
if (!defined('AT_N8N_PLAN_CAMBIOS')) {
	define('AT_N8N_PLAN_CAMBIOS', 'https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-cambios');
}
if (!defined('AT_N8N_PLAN_RENDER')) {
	define('AT_N8N_PLAN_RENDER', 'https://n8n-n8n.kchiba.easypanel.host/webhook/plan-v1-render');
}

/**
 * Avisa a un flujo de n8n con un cuerpo JSON; '' si respondió 2xx, si no el motivo en palabras. $timeout en
 * segundos (15 por defecto): los webhooks del plan contestan al recibir, así que la espera es solo la de la red.
 */
function at_pt_llamar_n8n(string $url, array $cuerpo, int $timeout = 15): string {
	if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
		return 'AT_REST_SECRET no está configurado.';
	}
	$r = wp_remote_post($url, [
		'timeout' => max(1, $timeout),
		// Sin redirecciones: WordPress reenviaría X-AT-Secret al destino de un 30x. Un 30x cae en «n8n respondió HTTP 30x».
		'redirection' => 0,
		'headers' => ['Content-Type' => 'application/json', 'X-AT-Secret' => AT_REST_SECRET],
		'body'    => wp_json_encode($cuerpo),
	]);
	if (is_wp_error($r)) {
		return 'n8n no respondió: ' . $r->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code($r);
	return ($code >= 200 && $code < 300) ? '' : "n8n respondió HTTP {$code}";
}

/**
 * Pide el borrador al flujo «Plan de trabajo · 1 Borrador» (cuerpo {id, codigo}). Deja el plan en
 * «generando»; desde «error» sirve de «Reintentar borrador». Si n8n no recibe el aviso y el plan sigue
 * en «generando», queda en «error» con el motivo. $timeout: segundos de espera del aviso (el oyente de la
 * firma usa 5 para no retener al cliente). Devuelve '' o el motivo.
 */
function at_pt_iniciar_borrador(int $plan_id, int $timeout = 15): string {
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'El plan no existe.';
	}
	if ((string) $f->estado !== 'generando' && !at_pt_cambiar_estado($plan_id, 'generando', '')) {
		return 'El plan está en «' . $f->estado . '»: no se puede pedir un borrador nuevo.';
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_BORRADOR, ['id' => (int) $f->id, 'codigo' => (string) $f->codigo], $timeout);
	if ($motivo !== '') {
		$ahora = at_pt_plan($plan_id);
		// Si n8n alcanzó a contestar con el borrador (el plan ya no está en «generando»), no se pisa.
		if ($ahora && (string) $ahora->estado === 'generando') {
			at_pt_cambiar_estado($plan_id, 'error', 'No se pudo pedir el borrador: ' . $motivo);
		}
	}
	return $motivo;
}

/**
 * Pide la vista previa ($modo 'draft') o la versión final ('final') al flujo «Plan de trabajo · 3 Render»
 * (cuerpo {id, codigo, modo, aviso}; con $aviso, n8n le escribe a Luis cuando está lista). Si n8n no recibe el
 * aviso: en la final con el plan «aprobando», el plan pasa a «error»; si no, solo queda la nota y el
 * estado no cambia. Devuelve '' o el motivo.
 */
function at_pt_pedir_render(int $plan_id, string $modo, bool $aviso): string {
	if (!in_array($modo, ['draft', 'final'], true)) {
		return 'Modo de render inválido.';
	}
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return 'El plan no existe.';
	}
	$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => (int) $f->id, 'codigo' => (string) $f->codigo, 'modo' => $modo, 'aviso' => $aviso]);
	if ($motivo === '') {
		return '';
	}
	$que = $modo === 'final' ? 'la versión final' : 'la vista previa';
	$ahora = at_pt_plan($plan_id);
	if ($modo === 'final' && $ahora && (string) $ahora->estado === 'aprobando') {
		at_pt_cambiar_estado($plan_id, 'error', 'No se pudo pedir ' . $que . ': ' . $motivo);
	} else {
		at_pt_guardar($plan_id, ['nota' => 'No se pudo pedir ' . $que . ': ' . $motivo]);
	}
	return $motivo;
}

/**
 * Escucha at_contrato_firmado, que ContractService::sign_as_client() dispara después de mandar las
 * copias. Contrato de servicios sin plan → crea el plan y pide el borrador. Firma repetida → nada.
 * Nunca lanza: un fallo del plan no afecta la firma (queda en el log y, si n8n no recibe el aviso,
 * el plan en «error», desde donde el panel ofrece «Reintentar borrador»). El aviso espera a lo más 5 s:
 * va dentro de la petición en que el cliente firma y el webhook contesta apenas lo recibe.
 */
function at_pt_al_firmar(?object $contrato): void {
	try {
		if (!$contrato || (string) ($contrato->type ?? '') !== 'servicios') {
			return;
		}
		$cid = (int) ($contrato->id ?? 0);
		if ($cid <= 0 || at_pt_plan_de_contrato($cid)) {
			return;
		}
		$id = at_pt_crear_plan($cid);
		if (is_wp_error($id)) {
			error_log('at_pt_al_firmar (contrato ' . $cid . '): ' . $id->get_error_message());
			return;
		}
		at_pt_iniciar_borrador($id, 5);
	} catch (\Throwable $e) {
		error_log('at_pt_al_firmar: ' . $e->getMessage());
	}
}
add_action('at_contrato_firmado', 'at_pt_al_firmar');
