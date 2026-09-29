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
