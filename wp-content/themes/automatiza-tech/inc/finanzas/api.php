<?php
/** Finanzas AT: API REST para registrar y consultar gastos (n8n, Balance). Todas las rutas exigen la cabecera X-AT-Secret. */

if (!defined('ABSPATH')) exit;

/** Misma regla que at_rt_auth (Retomar), sin depender de ese módulo: cabecera X-AT-Secret igual a AT_REST_SECRET. */
function at_fin_auth(WP_REST_Request $r): bool {
	$cabecera = $r->get_header('x-at-secret');
	return defined('AT_REST_SECRET') && AT_REST_SECRET !== '' && is_string($cabecera) && hash_equals(AT_REST_SECRET, $cabecera);
}

/** Cuerpo de la petición como arreglo: JSON si lo es; si no, los parámetros de formulario. */
function at_fin_api_cuerpo(WP_REST_Request $r): array {
	$json = $r->get_json_params();
	if (is_array($json)) { return $json; }
	return (array) $r->get_body_params();
}

/** Respuesta de error con el formato de la API: {code, message, errores?} y el estado HTTP del WP_Error. */
function at_fin_api_error(WP_Error $e): WP_REST_Response {
	$datos = (array) $e->get_error_data();
	$cuerpo = ['code' => $e->get_error_code(), 'message' => $e->get_error_message()];
	if (isset($datos['errores']) && is_array($datos['errores'])) { $cuerpo['errores'] = array_values($datos['errores']); }
	return new WP_REST_Response($cuerpo, (int) ($datos['status'] ?? 500));
}

/** POST /finanzas/movimiento: registra un movimiento (idempotente). */
function at_fin_api_movimiento(WP_REST_Request $r): WP_REST_Response {
	$res = at_fin_registrar(at_fin_api_cuerpo($r), 'api');
	if (is_wp_error($res)) { return at_fin_api_error($res); }
	if ($res['estado'] === 'creado') {
		// Completa los dólares que no se pudieron obtener antes (el spec lo hace «en el próximo registro»).
		// Si el recién guardado quedó pendiente la fuente sigue caída: no se insiste (cada intento cuesta hasta 10 s).
		// Nada de esto puede cambiar ni romper la respuesta de este registro.
		try {
			global $wpdb;
			$estado = $wpdb->get_var($wpdb->prepare('SELECT tc_estado FROM ' . at_fin_tabla_movimientos() . ' WHERE id = %d', $res['id']));
			if ($estado !== 'pendiente') { at_fin_completar_pendientes(5); }
		} catch (Throwable $e) {
			// Se ignora a propósito.
		}
	}
	return new WP_REST_Response(['id' => $res['id'], 'estado' => $res['estado']], $res['estado'] === 'creado' ? 201 : 200);
}

/** GET /finanzas/resumen?mes=AAAA-MM: gasto del mes y comparación con los 6 meses anteriores. */
function at_fin_api_resumen(WP_REST_Request $r): WP_REST_Response {
	$mes = $r->get_param('mes');
	if ($mes === null || $mes === '') { $mes = substr(at_fin_hoy(), 0, 7); }
	if (!is_string($mes) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $mes) !== 1) {
		$msg = 'El mes debe tener el formato AAAA-MM (por ejemplo 2026-10).';
		return new WP_REST_Response(['code' => 'mes_invalido', 'message' => $msg, 'errores' => [$msg]], 422);
	}
	$resumen = at_fin_resumen_mes(at_fin_movimientos_mes($mes));
	// Objetos JSON también cuando están vacíos (un arreglo vacío saldría como [] y rompería a quien espera claves).
	$resumen['por_categoria'] = (object) $resumen['por_categoria'];
	$resumen['por_proveedor'] = (object) $resumen['por_proveedor'];
	return new WP_REST_Response(['mes' => $mes] + $resumen + ['comparacion' => at_fin_comparacion($mes)], 200);
}

/** GET /finanzas/cuentas: todas las cuentas con su saldo. */
function at_fin_api_cuentas(WP_REST_Request $r): WP_REST_Response {
	return new WP_REST_Response(array_values(at_fin_cuentas()), 200);
}

/** POST /finanzas/cuenta: crea la cuenta o actualiza la que tenga ese slug. */
function at_fin_api_cuenta(WP_REST_Request $r): WP_REST_Response {
	$cuerpo = at_fin_api_cuerpo($r);
	$slug = strtolower(at_fin_texto($cuerpo['slug'] ?? '', 60));
	$existia = $slug !== '' && at_fin_cuenta($slug) !== null;
	$id = at_fin_cuenta_guardar($cuerpo);
	if (is_wp_error($id)) { return at_fin_api_error($id); }
	$cuenta = at_fin_cuenta($slug);
	if ($cuenta === null) { return new WP_REST_Response(['code' => 'error_bd', 'message' => 'No se pudo leer la cuenta guardada.'], 500); }
	return new WP_REST_Response($cuenta, $existia ? 200 : 201);
}

add_action('rest_api_init', static function (): void {
	register_rest_route('automatiza-tech/v1', '/finanzas/movimiento', ['methods' => 'POST', 'permission_callback' => 'at_fin_auth', 'callback' => 'at_fin_api_movimiento']);
	register_rest_route('automatiza-tech/v1', '/finanzas/resumen', ['methods' => 'GET', 'permission_callback' => 'at_fin_auth', 'callback' => 'at_fin_api_resumen']);
	register_rest_route('automatiza-tech/v1', '/finanzas/cuentas', ['methods' => 'GET', 'permission_callback' => 'at_fin_auth', 'callback' => 'at_fin_api_cuentas']);
	register_rest_route('automatiza-tech/v1', '/finanzas/cuenta', ['methods' => 'POST', 'permission_callback' => 'at_fin_auth', 'callback' => 'at_fin_api_cuenta']);
});
