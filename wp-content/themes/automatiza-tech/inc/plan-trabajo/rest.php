<?php
/**
 * Plan de trabajo: rutas REST que usan los flujos de n8n (namespace automatiza-tech/v1).
 * Misma clave que las propuestas: cabecera X-AT-Secret = AT_REST_SECRET (automatiza_proposals_rest_auth,
 * inc/rest-proposals.php; sin clave 401, clave mala 403).
 *   GET  /plan/{id}/contexto        lo que recibe la IA
 *   POST /plan/{id}/borrador        {plan, origen: borrador|cambios} → valida, tabla, fechas, guarda, vista previa
 *   GET  /plan/{id}/render&modo=    cuerpo para el renderer + propuesta_uid (fotos a reutilizar), crm_cliente_id y estado
 *                                   (la base ya trae ?rest_route=: el modo va con &modo=)
 *   POST /plan/{id}/vista           {modo, ok, view_url, pdf_url, faltan, nota} → enlaces y estado
 *   POST /plan/{id}/error           {nota} → estado «error»
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('rest_api_init', 'at_pt_rest_rutas');

function at_pt_rest_rutas(): void {
	$ns = 'automatiza-tech/v1';
	$id = ['id' => ['required' => true, 'validate_callback' => function ($v) { return is_numeric($v); }]];
	$rutas = [
		'contexto' => ['GET', 'at_pt_rest_contexto'],
		'borrador' => ['POST', 'at_pt_rest_borrador'],
		'render'   => ['GET', 'at_pt_rest_render'],
		'vista'    => ['POST', 'at_pt_rest_vista'],
		'error'    => ['POST', 'at_pt_rest_error'],
	];
	foreach ($rutas as $ruta => [$metodo, $callback]) {
		register_rest_route($ns, '/plan/(?P<id>\d+)/' . $ruta, [
			'methods'             => $metodo,
			// Provisorio (Task 7, primer commit): WordPress da 500 antes de mirar la clave si el manejador no existe.
			'callback'            => is_callable($callback) ? $callback : function () { return new WP_Error('at_pt_pendiente', 'Ruta todavía no implementada.', ['status' => 501]); },
			'permission_callback' => 'automatiza_proposals_rest_auth',
			'args'                => $id,
		]);
	}
}

/** El plan de la URL o un 404. */
function at_pt_rest_fila(WP_REST_Request $r) {
	$f = at_pt_plan((int) $r['id']);
	return $f ?: new WP_Error('at_pt_no_existe', 'No existe el plan.', ['status' => 404]);
}

/** Cuerpo JSON como arreglo ([] si no viene). */
function at_pt_rest_cuerpo(WP_REST_Request $r): array {
	$p = $r->get_json_params();
	return is_array($p) ? $p : [];
}

function at_pt_rest_contexto(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	return new WP_REST_Response(at_pt_contexto($f), 200);
}

/**
 * Plan que no sirve: nada se guarda; el plan pasa a «error» (o solo anota, si no puede) con un motivo legible.
 * $prefijo dice de dónde viene el problema: lo que mandó la IA (por defecto) o lo que queda después de aplicar la
 * tabla de tiempos y los días de Luis (la segunda validación). HTTP 422.
 */
function at_pt_rest_rechazar(object $f, array $errores, array $avisos, string $prefijo = 'La IA devolvió un plan que no sirve: '): WP_REST_Response {
	$limpios = [];
	foreach ($errores as $e) {
		$e = mb_substr(sanitize_text_field(is_scalar($e) ? (string) $e : ''), 0, 300);
		if ($e !== '') {
			$limpios[] = $e;
		}
	}
	if (!$limpios) {
		$limpios = ['El plan no pasó la validación.'];
	}
	$nota = $prefijo . implode('; ', array_slice($limpios, 0, 5));
	if (at_pt_transicion_valida((string) $f->estado, 'error')) {
		at_pt_cambiar_estado((int) $f->id, 'error', $nota);
	} else {
		at_pt_guardar((int) $f->id, ['nota' => $nota]);
	}
	return new WP_REST_Response(['ok' => false, 'errores' => $limpios, 'avisos' => array_values(array_map('strval', $avisos))], 422);
}

/**
 * POST /plan/{id}/borrador — llega el plan de la IA; sigue el «Orden de uso único» del Grupo A (Task 2).
 * 'borrador' se acepta con el plan en «generando», o en «error» si todavía no tiene contenido (un borrador tardío
 * nunca pisa lo que Luis editó); 'cambios', en «cambios» o «error». Si no, llegó tarde (409) y no se aplica.
 *   borrador: at_pt_validar_entrada(…, true) (el «Arranque» de la IA se cambia por el fijo) → días de Luis →
 *             tabla de tiempos → at_pt_validar_plan otra vez (la tabla puede pasar el tope de 130 días hábiles).
 *   cambios:  at_pt_validar_entrada(…) → días de Luis → avisos de las actividades de Luis que la IA quitó →
 *             lo que cambió la IA queda «ia» (IA · revisar) → at_pt_validar_plan otra vez. No reaplica la tabla.
 * Después calcula fechas en días hábiles desde la fecha de inicio guardada (o la de defecto) y, si esa fecha no es
 * hábil, desde el hábil siguiente; guarda, pasa a «borrador» y pide la vista previa con aviso a Luis. Los avisos
 * van en la respuesta y en la nota.
 */
function at_pt_rest_borrador(WP_REST_Request $r) {
	$f = at_pt_rest_fila($r);
	if (is_wp_error($f)) {
		return $f;
	}
	$p = at_pt_rest_cuerpo($r);
	$origen = (string) ($p['origen'] ?? 'borrador');
	if (!in_array($origen, ['borrador', 'cambios'], true)) {
		return new WP_REST_Response(['ok' => false, 'errores' => ['origen debe ser «borrador» o «cambios».'], 'avisos' => []], 400);
	}
	$anterior = at_pt_payload($f);
	$admitidos = $origen === 'borrador' ? ['generando', 'error'] : ['cambios', 'error'];
	$tardio = !in_array((string) $f->estado, $admitidos, true)
		|| ($origen === 'borrador' && (string) $f->estado === 'error' && !empty($anterior['fases']));
	if ($tardio) {
		return new WP_REST_Response(['ok' => false, 'errores' => ['El plan está en «' . $f->estado . '»: este ' . $origen . ' llegó tarde y no se aplica.'], 'avisos' => []], 409);
	}
	$ctx = at_pt_contexto($f);
	// El objeto o el texto de la IA (con o sin cerco ```json) va directo a la validación del Grupo A: aquí no se decodifica.
	$v = at_pt_validar_entrada($p['plan'] ?? null, $origen === 'borrador');
	$avisos = array_values(array_map('strval', (array) ($v['avisos'] ?? [])));
	if (empty($v['ok'])) {
		return at_pt_rest_rechazar($f, (array) ($v['errores'] ?? []), $avisos);
	}
	$plan = $v['plan'];
	if (trim((string) ($plan['proyecto'] ?? '')) === '') {
		// Sin nombre de proyecto: el del contrato. El aviso de la validación ya no aplica.
		$plan['proyecto'] = $ctx['proyecto'];
		$avisos = array_values(array_diff($avisos, ['El plan no trae el nombre del proyecto.']));
	}
	$plan = at_pt_respetar_dias_luis($anterior, $plan);
	if ($origen === 'borrador') {
		$plan = at_pt_aplicar_tabla($plan, at_pt_duraciones());
	} else {
		$avisos = array_merge($avisos, at_pt_luis_perdidas($anterior, $plan));
		$plan = at_pt_marcar_ediciones($anterior, $plan, 'ia');
	}
	// Segunda validación: la tabla o los días de Luis pueden pasar los topes (sus avisos repiten los de la primera).
	$v2 = at_pt_validar_plan($plan);
	if (empty($v2['ok'])) {
		return at_pt_rest_rechazar($f, (array) ($v2['errores'] ?? []), $avisos, 'El plan no cabe con la tabla de tiempos y los días que fijó Luis: ');
	}
	$feriados = at_pt_feriados();
	$inicio = (string) ($f->fecha_inicio ?? '');
	if (!at_pt_db_fecha_valida($inicio)) {
		$inicio = at_pt_inicio_por_defecto($ctx['fecha_firma'], $feriados);
	}
	if (!at_pt_es_habil($inicio, $feriados)) {
		$inicio = at_pt_siguiente_habil($inicio, $feriados);
	}
	$plan = at_pt_calcular_fechas($v2['plan'], $inicio, $feriados);
	$plan['fecha_inicio'] = $inicio;
	$plan['fecha_firma'] = $ctx['fecha_firma'];
	$guardado = at_pt_guardar((int) $f->id, [
		'payload'      => $plan,
		'fecha_inicio' => $inicio,
		'estado'       => 'borrador',
		'nota'         => $avisos ? 'Avisos del borrador: ' . implode('; ', $avisos) : '',
	]);
	if (!$guardado) {
		return new WP_REST_Response(['ok' => false, 'errores' => ['No se pudo guardar el plan.'], 'avisos' => $avisos], 500);
	}
	$motivo = at_pt_pedir_render((int) $f->id, 'draft', true);
	if ($motivo !== '') {
		$avisos[] = 'No se pudo pedir la vista previa: ' . $motivo;
	}
	return new WP_REST_Response(['ok' => true, 'errores' => [], 'avisos' => $avisos], 200);
}
