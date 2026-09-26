<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/contexto-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba.\n");
	exit(2);
}
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$codigo = substr(md5($marca), 0, 12);
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '@example.com', 'unique_link_id' => $codigo, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa', 'phone' => '+56 9 4444 5555', 'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos']]]), 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$pid = (int) $wpdb->insert_id;
function contexto(array $cuerpo, ?string $clave = null) {
	$r = new WP_REST_Request('POST', '/at/v1/propuesta-contexto');
	$r->set_header('content-type', 'application/json');
	if ($clave !== null) { $r->set_header('x-at-secret', $clave); }
	$r->set_body(wp_json_encode($cuerpo));
	return rest_do_request($r);
}
ok(contexto(['telefono' => '56944445555'])->get_status() === 401, 'sin clave: 401');
ok(contexto(['telefono' => '56900000001'], AT_REST_SECRET)->get_data()['tiene'] === false, 'otro número: sin propuesta');
$r = contexto(['telefono' => '944445555'], AT_REST_SECRET)->get_data();
ok($r['tiene'] === true && $r['codigo'] === $codigo && strpos($r['propuesto'], 'Fase 1') === 0 && strpos($r['url'], $codigo) !== false, 'mismo número en otro formato: encuentra la propuesta');
ok(count($correos) === 0, 'sin mensaje no avisa');
$r = contexto(['telefono' => '+56 9 4444 5555', 'mensaje' => "Sí, acepto\nla propuesta"], AT_REST_SECRET)->get_data();
ok($r['tiene'] === true && count($correos) === 1 && $correos[0]['to'] === get_option('admin_email'), 'con mensaje: avisa a Luis');
ok(strpos((string) $correos[0]['message'], 'acepto') !== false, 'el correo trae el mensaje');
$n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'mensaje_whatsapp'", $pid));
ok($n === 1, 'queda anotado en Seguimiento');
// Ronda 1 (26-sep), hallazgo 1: dos mensajes seguidos dentro de los 30 minutos → dos notas, un solo correo.
contexto(['telefono' => '56944445555', 'mensaje' => 'otra duda'], AT_REST_SECRET);
ok(count($correos) === 1, 'otro mensaje dentro de 30 minutos: no repite el correo');
$n2 = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'mensaje_whatsapp'", $pid));
ok($n2 === 2, 'dos mensajes seguidos: dos notas aunque el correo no se repita');
ok(at_cc_propuesta_por_id($pid)->status === 'sent', 'un mensaje de texto no cambia el estado');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'aceptada'], ['id' => $pid]);
ok(contexto(['telefono' => '56944445555'], AT_REST_SECRET)->get_data()['tiene'] === false, 'aceptada: ya no está pendiente');

delete_transient('at_cc_ctx_aviso_' . $pid);
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));

/** Crea una propuesta de prueba aislada (su propio teléfono) y la borra al final del callback. */
function contexto_con_propuesta_aislada(string $marca, string $telefono, callable $cuerpo): void {
	global $wpdb;
	$sufijo = strtolower(wp_generate_password(6, false, false));
	$codigo = substr(md5($marca . $sufijo), 0, 12);
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '-' . $sufijo . '@example.com', 'unique_link_id' => $codigo, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] ' . $marca, 'phone' => $telefono, 'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => []]), 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
	$id = (int) $wpdb->insert_id;
	$cuerpo($id);
	delete_transient('at_cc_ctx_aviso_' . $id);
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
}

// Ronda 1 (26-sep), hallazgo 2: el mensaje se guarda tal cual llegó, sin que sanitize_textarea_field()
// borre '%XX' o convierta '<' en entidad; la salida (correo) sí queda escapada.
contexto_con_propuesta_aislada('especial', '+56 9 4444 8888', function (int $id) use (&$correos, $wpdb) {
	contexto(['telefono' => '56944448888', 'mensaje' => 'El 50%del anticipo lo pago hoy'], AT_REST_SECRET);
	$nota = $wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'mensaje_whatsapp' ORDER BY id DESC LIMIT 1", $id));
	ok($nota === 'El 50%del anticipo lo pago hoy', "el '%' seguido de dos caracteres no se borra al guardar la nota");
	delete_transient('at_cc_ctx_aviso_' . $id);
	contexto(['telefono' => '56944448888', 'mensaje' => 'precio <1M?'], AT_REST_SECRET);
	$nota2 = $wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'mensaje_whatsapp' ORDER BY id DESC LIMIT 1", $id));
	ok($nota2 === 'precio <1M?', "el signo '<' no se convierte en entidad al guardar la nota");
	$ultimo = (string) end($correos)['message'];
	ok(strpos($ultimo, '&lt;1M?') !== false && strpos($ultimo, '<1M?') === false, "el correo escapa el '<' (nunca lo muestra como etiqueta activa)");
});

// Ronda 1 (26-sep), hallazgo 3: un mensaje de más de 1000 caracteres queda recortado en la nota y en el correo.
contexto_con_propuesta_aislada('largo', '+56 9 4444 9999', function (int $id) use (&$correos, $wpdb) {
	$largo = str_repeat('A', 1000) . 'SOBRA-NO-DEBE-QUEDAR';
	contexto(['telefono' => '56944449999', 'mensaje' => $largo], AT_REST_SECRET);
	$nota = (string) $wpdb->get_var($wpdb->prepare("SELECT description FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'mensaje_whatsapp' ORDER BY id DESC LIMIT 1", $id));
	ok(mb_strlen($nota) === 1000 && strpos($nota, 'SOBRA') === false, 'mensaje de más de 1000 caracteres: la nota queda recortada a 1000');
	$ultimo = (string) end($correos)['message'];
	ok(strpos($ultimo, 'SOBRA') === false, 'mensaje de más de 1000 caracteres: el correo también queda recortado');
});

fin();
