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
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '@example.com', 'unique_link_id' => $codigo, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Muebles', 'phone' => '+56 9 4444 5555', 'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos']]]), 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
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
contexto(['telefono' => '56944445555', 'mensaje' => 'otra duda'], AT_REST_SECRET);
ok(count($correos) === 1, 'otro mensaje dentro de 30 minutos: no repite el correo');
ok(at_cc_propuesta_por_id($pid)->status === 'sent', 'un mensaje de texto no cambia el estado');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'aceptada'], ['id' => $pid]);
ok(contexto(['telefono' => '56944445555'], AT_REST_SECRET)->get_data()['tiene'] === false, 'aceptada: ya no está pendiente');

delete_transient('at_cc_ctx_aviso_' . $pid);
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));
fin();
