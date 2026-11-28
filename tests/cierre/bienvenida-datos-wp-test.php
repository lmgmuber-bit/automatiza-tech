<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/bienvenida-datos-wp-test.php
// 27-sep: quien acepta a mano o por WhatsApp no deja la dirección ni a nombre de quién va el
// contrato, y la bienvenida le pedía responder el correo con esos datos. Ahora, si al contrato le faltan,
// la bienvenida trae el botón a «Datos para tu contrato». Para eso sale después de crear el contrato.
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-bdatos-' . strtolower(wp_generate_password(6, false, false));
$payload = [
	'company_name' => '[PRUEBA] Empresa', 'solution_text' => 'Servicio de prueba.',
	'pricing_rows' => [
		['service' => 'Fase 1: Servicio de prueba', 'price_usd' => 0, 'price_label' => '$250.000 en 2 pagos'],
		['service' => 'Gestión mensual', 'price_usd' => 0, 'price_label' => '$120.000 al mes'],
	],
];
$creadas = [];
function crear_propuesta_bd(string $marca, string $sufijo, array $payload, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $sufijo . '@example.com', 'unique_link_id' => substr(md5($marca . $sufijo . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa', 'phone' => '+56 9 2222 2222',
		'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
$al_cliente = function (object $p) use (&$correos): array {
	return array_values(array_filter($correos, function ($x) use ($p) { return $x['to'] === $p->client_email && strpos((string) $x['subject'], 'bienvenida') !== false; }));
};
$respondenos = 'respóndenos este correo con esos datos';

// 1) Puro: con url_datos, botón a «Datos para tu contrato» en vez de pedir que responda el correo.
$h = at_cc_bienvenida_html(['nombre' => 'Ana', 'con_propuesta' => true, 'url_datos' => 'https://e.cl/ver-presentacion.php?id=abc&respuesta=completar']);
ok(strpos($h, 'href="https://e.cl/ver-presentacion.php?id=abc&amp;respuesta=completar"') !== false && strpos($h, 'Completar mis datos del contrato') !== false, '1) con url_datos: botón «Completar mis datos del contrato» con el enlace escapado');
ok(strpos($h, $respondenos) === false, '1) con url_datos: ya no le pide responder el correo con los datos');
$h_sin = at_cc_bienvenida_html(['nombre' => 'Ana', 'con_propuesta' => true]);
ok(strpos($h_sin, $respondenos) !== false && strpos($h_sin, 'Completar mis datos') === false, '1) sin url_datos: sigue el texto de siempre');
$h_np = at_cc_bienvenida_html(['nombre' => 'Ana', 'con_propuesta' => false, 'url_datos' => 'https://e.cl/x']);
ok(strpos($h_np, 'Completar mis datos') === false, '1) sin propuesta no hay botón aunque llegue url_datos');

// 2) Aceptación a mano con bienvenida: el correo trae el enlace de su propuesta a «Datos para tu contrato».
$correos = [];
$m = crear_propuesta_bd($marca, 'mano', $payload, $creadas);
$r = at_cc_registrar_respuesta($m, 'acepta', ['canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Cliente Prueba', 'comentario' => 'Dijo que sí por WhatsApp', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($m), [0, 1]), 'fecha' => current_time('mysql'), 'bienvenida' => true, 'usuario_id' => 1]);
$b = $al_cliente($m);
$esperado = htmlspecialchars(at_cc_url_datos_contrato(at_cc_propuesta_por_id($m->id)), ENT_QUOTES, 'UTF-8');
ok($r['ok'] && count($b) === 1, '2) a mano con bienvenida: sale una bienvenida al cliente');
ok($esperado !== '' && strpos($esperado, 'respuesta=completar') !== false && strpos($esperado, $m->unique_link_id) !== false, '2) el contrato quedó sin dirección, así que hay enlace de datos');
ok($b && strpos($b[0]['message'], 'href="' . $esperado . '"') !== false && strpos($b[0]['message'], 'Completar mis datos del contrato') !== false, '2) la bienvenida trae el botón con ese enlace');
ok($b && strpos($b[0]['message'], '$125.000') !== false, '2) y sigue con el anticipo (50 % de lo único aceptado)');

// 3) Aceptación en la página con los datos del contrato: el contrato ya tiene dirección, sin botón.
$correos = [];
$pg = crear_propuesta_bd($marca, 'pagina', $payload, $creadas);
$r = at_cc_registrar_respuesta($pg, 'acepta', ['canal' => 'pagina', 'nombre' => 'Cliente Prueba', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($pg), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => true, 'datos_contrato' => ['tipo_cliente' => 'persona', 'domicilio_cliente' => 'Calle Prueba 123, Santiago']]);
$b = $al_cliente($pg);
ok($r['ok'] && count($b) === 1 && strpos($b[0]['message'], 'respuesta=completar') === false && strpos($b[0]['message'], 'Completar mis datos') === false, '3) aceptó en la página con sus datos: la bienvenida no trae el botón');

// 4) Bienvenida reenviada después, con el contrato ya revisado por AT: tampoco hay botón (el formulario ya no lo aceptaría).
$c = at_cc_contrato_de_propuesta((int) $m->id);
$ph = json_decode((string) $c->placeholders, true) ?: [];
$ph['revision_at'] = current_time('mysql');
$wpdb->update(ContractService::table(), ['placeholders' => wp_json_encode($ph)], ['id' => $c->id]);
$correos = [];
at_cc_enviar_bienvenida(at_cc_crm_de_email($m->client_email), at_cc_propuesta_por_id($m->id), at_cc_filas_aceptadas(at_cc_filas_de_propuesta($m), [0]));
$b = $al_cliente($m);
ok($b && strpos($b[0]['message'], 'Completar mis datos') === false, '4) contrato ya revisado por AT: sin botón');

// Limpieza
$ids = implode(',', array_map('intval', $creadas));
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$wpdb->query("DELETE FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
if ($crm_ids) {
	$lista = implode(',', array_map('intval', $crm_ids));
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN ({$lista})");
}
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
fin();
