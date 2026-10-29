<?php
/**
 * GET /wp-json/automatiza-tech/v1/wa-contexto?phone=…&texto=… — solo para n8n (clave X-AT-Secret, como las rutas del
 * cierre de cliente). Dice si el número es de una persona nueva, un prospecto o un cliente, con sus opciones.
 * La agenda (nombre completo, correo y empresa) es para que n8n agende; nunca va a la conversación.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('rest_api_init', function () {
	register_rest_route('automatiza-tech/v1', '/wa-contexto', [
		'methods'             => 'GET',
		'callback'            => 'at_wa_rest_contexto',
		'permission_callback' => function (WP_REST_Request $r) {
			return function_exists('at_cc_rest_auth')
				? at_cc_rest_auth($r)
				: new WP_Error('no_autorizado', 'Clave inválida.', ['status' => 401]);
		},
		'args'                => [
			'phone' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
			'texto' => ['required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
		],
	]);
});

function at_wa_rest_contexto(WP_REST_Request $r): array {
	$texto = mb_substr((string) $r->get_param('texto'), 0, 500);
	return at_wa_contexto((string) $r->get_param('phone'), $texto);
}
