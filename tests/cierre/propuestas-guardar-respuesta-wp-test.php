<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/propuestas-guardar-respuesta-wp-test.php
// Ronda 1, hallazgo 2 (T9): at_pa_guardar() pisaba el estado de una propuesta con respuesta del
// cliente (aceptada, evaluando, rechazada) en cada guardado normal: con el checkbox de envío
// (marcado por defecto en propuestas viejas, ficha.php:231) la dejaba en 'sent'; sin él, en
// 'pending'. Una propuesta 'aceptada' que vuelve a 'sent' reabre la idempotencia de
// at_cc_registrar_respuesta() (que solo mira el estado 'aceptada'): una segunda aceptación real del
// cliente, o «Registrar aceptación a mano», corría otra vez at_cc_ejecutar_cierre() completo
// (segundo cliente/contrato/bienvenida). En 'evaluando'/'rechazada', un simple Guardar hacía
// desaparecer la barra pública para aceptar.
// Reproducido en el WordPress local: propuesta con flujo NULL en 'aceptada' (como las viejas).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_pa_guardar')) {
	fwrite(STDERR, "at_pa_guardar() no está definida: revisa propuestas-admin/acciones.php.\n");
	exit(2);
}
if (!function_exists('at_cc_crear_contrato_servicios')) {
	fwrite(STDERR, "at_cc_crear_contrato_servicios() no está definida: revisa cierre-cliente/contrato.php.\n");
	exit(2);
}

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

$table = $wpdb->prefix . 'automatiza_propuestas';
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$marca = 'prueba-pa-guardar-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];

function pa_guardar_crear(string $marca, string $status, ?string $flujo, array &$creadas): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '@example.com',
		'unique_link_id' => substr(md5($marca . $status . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Guardar', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => $flujo, 'gamma_prompt_text' => '{}',
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	$creadas[] = $id;
	return $id;
}

/** Simula el POST de "Guardar" (y, si $enviar, el checkbox de correo marcado) de la ficha. */
function pa_guardar_post(int $id, bool $enviar): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = [
		'automatiza_proposal_nonce' => wp_create_nonce('save_proposal'),
		'proposal_id'   => (string) $id,
		'client_name'   => 'Cliente Prueba',
		'company_name'  => '[PRUEBA] Guardar',
		'phone'         => '+56 9 2222 2222',
		'client_email'  => 'no-cambia@example.com',
		'email_subject' => '', 'email_intro' => '', 'email_highlight' => '', 'email_closing' => '',
		'gamma_url'     => '', 'n8n_url' => '',
	];
	if ($enviar) {
		$_POST['send_email'] = '1';
	}
	$_FILES = [];
	return at_pa_guardar();
}

function pa_guardar_estado(int $id): string {
	global $wpdb;
	return (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
}

// --- 1) Aceptada + Guardar con el checkbox de envío marcado (el valor por defecto en propuestas
//        viejas): no se reenvía el correo, no vuelve a 'sent' y no ofrece de nuevo "Aceptar". ---
$id_acept = pa_guardar_crear($marca, 'aceptada', null, $creadas);
$correos = [];
$msg1 = pa_guardar_post($id_acept, true);
ok(pa_guardar_estado($id_acept) === 'aceptada', 'aceptada + Guardar con envío marcado: el estado no vuelve a "sent"');
ok(count($correos) === 0, 'aceptada + Guardar con envío marcado: no se manda ningún correo (' . count($correos) . ')');
ok(strpos($msg1, 'ya aceptó') !== false, 'aceptada + Guardar con envío marcado: el aviso explica que ya aceptó: ' . $msg1);
ok(strpos($msg1, 'Aceptar la propuesta') === false, 'aceptada + Guardar con envío marcado: no ofrece de nuevo el botón de aceptar');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d", $id_acept)) === 0, 'aceptada + Guardar con envío marcado: no queda ninguna nota nueva de envío en Seguimiento');

// --- 2) Aceptada + Guardar sin correo (checkbox desmarcado): tampoco pierde el estado. ---
$id_acept2 = pa_guardar_crear($marca, 'aceptada', null, $creadas);
pa_guardar_post($id_acept2, false);
ok(pa_guardar_estado($id_acept2) === 'aceptada', 'aceptada + Guardar sin correo: el estado no pasa a "pending"');

// --- 3) Evaluando + Guardar sin correo: tampoco pierde el estado (antes quedaba en "pending" y la
//        barra pública de aceptar desaparecía, at_cc_render_barra() en pagina.php). ---
$id_eval = pa_guardar_crear($marca, 'evaluando', null, $creadas);
pa_guardar_post($id_eval, false);
ok(pa_guardar_estado($id_eval) === 'evaluando', 'evaluando + Guardar sin correo: el estado no pasa a "pending"');

// --- 4) Rechazada + Guardar sin correo: tampoco pierde el estado. ---
$id_rech = pa_guardar_crear($marca, 'rechazada', null, $creadas);
pa_guardar_post($id_rech, false);
ok(pa_guardar_estado($id_rech) === 'rechazada', 'rechazada + Guardar sin correo: el estado no pasa a "pending"');

// --- 5) No rompe lo existente: una propuesta del flujo viejo SIN respuesta del cliente sigue
//        pasando a "pending" con un Guardar sin correo. ---
$id_sent = pa_guardar_crear($marca, 'sent', null, $creadas);
pa_guardar_post($id_sent, false);
ok(pa_guardar_estado($id_sent) === 'pending', 'sent (sin respuesta del cliente) + Guardar sin correo: sigue pasando a "pending" (sin cambios de esta ronda)');

// --- 6) Red de seguridad: at_cc_crear_contrato_servicios() no crea un segundo contrato para la
//        misma propuesta si ya existe uno (defensa extra si, por otra vía, el cierre se ejecutara
//        dos veces). ---
$id_contrato = pa_guardar_crear($marca, 'aceptada', null, $creadas);
$p_contrato = at_cc_propuesta_por_id($id_contrato);
$filas_ct = [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000']];
$aceptante_ct = ['nombre' => 'Cliente Prueba', 'rut' => '11.111.111-1', 'fecha' => current_time('mysql'), 'canal_texto' => 'en la página de la propuesta'];
$c1 = at_cc_crear_contrato_servicios($p_contrato, 0, $filas_ct, $aceptante_ct);
$c2 = at_cc_crear_contrato_servicios($p_contrato, 0, $filas_ct, $aceptante_ct);
ok(!is_wp_error($c1) && !is_wp_error($c2) && $c1 === $c2, 'at_cc_crear_contrato_servicios: una segunda llamada para la misma propuesta devuelve el mismo contrato');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $id_contrato)) === 1, 'at_cc_crear_contrato_servicios: solo queda una fila de contrato para la propuesta');

// Limpieza
if ($creadas) {
	$ids = implode(',', array_map('intval', $creadas));
	$wpdb->query("DELETE FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})");
	$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
	$wpdb->query("DELETE FROM {$table} WHERE id IN ({$ids})");
}

fin();
