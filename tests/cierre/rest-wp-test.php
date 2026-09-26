<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/rest-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: el controlador lo define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '@example.com', 'unique_link_id' => substr(md5($marca), 0, 12), 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Muebles', 'phone' => '+56 9 3333 3333', 'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos']]]), 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$pid = (int) $wpdb->insert_id;
$codigo = substr(md5($marca), 0, 12);
function pedir(array $cuerpo, ?string $clave = null) {
	$r = new WP_REST_Request('POST', '/at/v1/propuesta-respuesta');
	$r->set_header('content-type', 'application/json');
	if ($clave !== null) { $r->set_header('x-at-secret', $clave); }
	$r->set_body(wp_json_encode($cuerpo));
	return rest_do_request($r);
}
ok(pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '56933333333'])->get_status() === 401, 'sin clave: 401');
ok(pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '56933333333'], 'mala')->get_status() === 401, 'clave mala: 401');
ok(pedir(['salida' => 'acepta', 'codigo' => 'no-existe', 'telefono' => '56933333333'], AT_REST_SECRET)->get_data()['motivo'] === 'no_existe', 'código inexistente');
$r = pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '56911112222', 'wamid' => 'w1'], AT_REST_SECRET)->get_data();
ok($r['ok'] === false && $r['motivo'] === 'telefono' && at_cc_propuesta_por_id($pid)->status === 'sent', 'teléfono distinto: no cambia nada');
ok(count($correos) === 1 && $correos[0]['to'] === get_option('admin_email') && strpos($correos[0]['subject'], 'desde otro número') !== false, 'teléfono distinto: avisa a Luis por correo');
$notas = $wpdb->get_col($wpdb->prepare("SELECT detail_type FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
ok(in_array('aviso_operativo', $notas, true), 'teléfono distinto: queda una nota interna aviso_operativo');
ok(!in_array('respuesta_cliente', $notas, true), 'teléfono distinto: ninguna nota pública respuesta_cliente con el teléfono de un tercero');
$r = pedir(['salida' => 'evalua', 'codigo' => $codigo, 'telefono' => '+56 9 3333 3333', 'wamid' => 'w2'], AT_REST_SECRET)->get_data();
ok($r['ok'] === true && $r['estado'] === 'evaluando', 'evalúa por WhatsApp');
$r = pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '933333333', 'wamid' => 'w3'], AT_REST_SECRET)->get_data();
ok($r['ok'] === true && $r['estado'] === 'aceptada' && at_cc_contrato_de_propuesta($pid) !== null, 'acepta por WhatsApp: cierre completo');
$r = pedir(['salida' => 'acepta', 'codigo' => $codigo, 'telefono' => '933333333', 'wamid' => 'w4'], AT_REST_SECRET)->get_data();
ok($r['ok'] === true && $r['motivo'] === 'ya_aceptada', 'dos toques no repiten');
ok(pedir(['salida' => 'otra', 'codigo' => $codigo, 'telefono' => '933333333'], AT_REST_SECRET)->get_data()['ok'] === false, 'salida inválida');
ok(!at_cc_whatsapp_plantilla_activa(), 'plantilla inactiva por defecto');
ok(at_cc_enviar_whatsapp_plantilla(at_cc_propuesta_por_id($pid)) !== '', 'sin plantilla activa no se envía');

$crm = at_cc_crm_de_email($marca . '@example.com');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_contracts WHERE proposal_id = %d", $pid));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
if ($crm) {
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $crm));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm));
}
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));
fin();
