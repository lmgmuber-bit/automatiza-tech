<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/datos-documento-wp-test.php
// 27-sep: quien acepta a mano o por WhatsApp no deja su documento, y «Datos para tu contrato» no
// lo pedía: el contrato quedaba sin «Documento del cliente» (persona) o «Documento del representante»
// (empresa) y Luis tenía que pedírselo por privado para poder firmar. Ahora, si falta, el formulario pide
// nombre y documento de quien firma, y a Luis le llega un aviso cuando el cliente deja sus datos.
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-ddoc-' . strtolower(wp_generate_password(6, false, false));
$payload = [
	'company_name' => '[PRUEBA] Empresa', 'solution_text' => 'Servicio de prueba.',
	'pricing_rows' => [['service' => 'Fase 1: Servicio de prueba', 'price_usd' => 0, 'price_label' => '$250.000 en 2 pagos']],
];
$creadas = [];
function crear_propuesta_ddoc(string $marca, string $sufijo, array $payload, array &$creadas): object {
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
/** Aceptación a mano (como la registraría Luis), con o sin documento. */
function aceptar_a_mano_ddoc(object $p, string $documento = ''): void {
	$r = at_cc_registrar_respuesta($p, 'acepta', [
		'canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Ana Prueba', 'comentario' => 'Dijo que sí por WhatsApp',
		'tipo_documento' => $documento !== '' ? 'rut' : '', 'documento' => $documento, 'rut' => $documento,
		'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => false, 'usuario_id' => 1,
	]);
	if (!$r['ok'] || !$r['contrato_id']) { fwrite(STDERR, "No se pudo aceptar la propuesta de prueba.\n"); exit(2); }
}
/** El diálogo «Datos para tu contrato» tal como lo ve el cliente al abrir el enlace del correo. */
function dialogo_datos_ddoc(object $p): string {
	$_GET['respuesta'] = 'aceptada';
	ob_start();
	at_cc_render_barra(at_cc_propuesta_por_id($p->id));
	$h = (string) ob_get_clean();
	unset($_GET['respuesta']);
	$i = strpos($h, 'id="at-cc-datos"');
	return $i === false ? '' : substr($h, $i, strpos($h, '</dialog>', $i) - $i);
}
$ph_de = function (object $p): array {
	return json_decode((string) at_cc_contrato_de_propuesta((int) $p->id)->placeholders, true) ?: [];
};
$aviso_luis = function () use (&$correos): array {
	return array_values(array_filter($correos, function ($x) { return $x['to'] === at_cc_correo_avisos() && strpos((string) $x['subject'], 'dejó sus datos para el contrato') !== false; }));
};

// 1) Persona, aceptada a mano sin documento: el formulario pide nombre y documento, y son obligatorios.
$p = crear_propuesta_ddoc($marca, 'persona', $payload, $creadas);
aceptar_a_mano_ddoc($p);
$dlg = dialogo_datos_ddoc($p);
ok($dlg !== '' && strpos($dlg, 'name="documento"') !== false && strpos($dlg, 'name="tipo_documento"') !== false && preg_match('/name="nombre"[^>]*value="Ana Prueba"/', $dlg) === 1, '1) sin documento: el diálogo pide nombre (con el de quien aceptó) y documento');
ok(preg_match('/name="documento"[^>]*required/', $dlg) === 1 && strpos($dlg, '>Pasaporte<') !== false, '1) el documento es obligatorio y admite RUT, DNI o pasaporte');
ok(at_cc_guardar_datos_contrato(at_cc_propuesta_por_id($p->id), ['tipo' => 'persona', 'direccion' => 'Calle Uno 1, Providencia', 'nombre' => 'Ana Prueba']) === 'datos_contrato', '1) sin documento no guarda: vuelve con el aviso');
ok(trim((string) ($ph_de($p)['domicilio_cliente'] ?? '')) === '', '1) y el contrato no cambió');
ok(at_cc_guardar_datos_contrato(at_cc_propuesta_por_id($p->id), ['tipo' => 'persona', 'direccion' => 'Calle Uno 1, Providencia', 'nombre' => 'Ana Prueba', 'tipo_documento' => 'rut', 'documento' => '11.111.111-2']) === 'datos_contrato', '1) RUT con dígito verificador malo: vuelve con el aviso');
$correos = [];
ok(at_cc_guardar_datos_contrato(at_cc_propuesta_por_id($p->id), ['tipo' => 'persona', 'direccion' => 'Calle Uno 1, Providencia', 'nombre' => 'Ana María Prueba', 'tipo_documento' => 'rut', 'documento' => '111111111']) === 'datos_ok', '1) con documento válido: datos_ok');
$ph = $ph_de($p);
$c = at_cc_contrato_de_propuesta((int) $p->id);
ok(($ph['rut_cliente'] ?? '') === '11.111.111-1' && ($ph['razon_social_cliente'] ?? '') === 'Ana María Prueba' && ($ph['domicilio_cliente'] ?? '') === 'Calle Uno 1, Providencia', '1) el contrato va a su nombre, con su RUT formateado y su dirección');
ok(ContractService::faltantes($c) === [], '1) no falta nada para que Luis revise y firme');
$av = $aviso_luis();
ok(count($av) === 1 && strpos($av[0]['message'], 'at-sign-contract.php?token=' . $c->at_review_token) !== false && strpos($av[0]['message'], 'listo para que lo revises y lo firmes') !== false, '1) a Luis le llega el aviso con el enlace para revisar y firmar');
ok($av && strpos($av[0]['message'], '11.111.111-1') === false, '1) el aviso no lleva el número del documento');
ok(strpos(dialogo_datos_ddoc($p), 'name="documento"') === false, '1) con el documento ya guardado, el diálogo no lo vuelve a pedir');

// 2) Empresa, aceptada a mano sin documento: pide el del representante (aquí un DNI).
$e = crear_propuesta_ddoc($marca, 'empresa', $payload, $creadas);
aceptar_a_mano_ddoc($e);
$correos = [];
ok(at_cc_guardar_datos_contrato(at_cc_propuesta_por_id($e->id), ['tipo' => 'empresa', 'direccion' => 'Calle Dos 2, Maipú', 'razon_social' => '[PRUEBA] Empresa SpA', 'rut_empresa' => '10.000.013-K', 'nombre' => 'Pedro Prueba', 'tipo_documento' => 'dni', 'documento' => 'ab 123456']) === 'datos_ok', '2) empresa con representante y DNI: datos_ok');
$ph = $ph_de($e);
ok(($ph['representante_cliente_nombre'] ?? '') === 'Pedro Prueba' && ($ph['tipo_documento_representante'] ?? '') === 'dni' && ($ph['representante_cliente_rut'] ?? '') === 'AB123456' && ($ph['rut_cliente'] ?? '') === '10.000.013-K', '2) el contrato lleva a la empresa y a su representante con su DNI');
ok(ContractService::faltantes(at_cc_contrato_de_propuesta((int) $e->id)) === [], '2) no falta nada para firmar');
ok(count($aviso_luis()) === 1, '2) a Luis le llega el aviso');

// 3) Aceptada con documento (en la página, o a mano con el RUT escrito): el diálogo no lo pide y se guarda como siempre.
$d = crear_propuesta_ddoc($marca, 'con-doc', $payload, $creadas);
aceptar_a_mano_ddoc($d, '11.111.111-1');
ok(strpos(dialogo_datos_ddoc($d), 'name="documento"') === false, '3) con documento: el diálogo no lo pide');
ok(at_cc_guardar_datos_contrato(at_cc_propuesta_por_id($d->id), ['tipo' => 'persona', 'direccion' => 'Calle Tres 3, La Florida']) === 'datos_ok' && ContractService::faltantes(at_cc_contrato_de_propuesta((int) $d->id)) === [], '3) persona + dirección basta y no falta nada');

// 4) Si al guardar todavía falta algo (p. ej. el monto), el aviso a Luis lo dice en vez de «listo».
$f = crear_propuesta_ddoc($marca, 'falta', ['company_name' => '[PRUEBA] Empresa', 'pricing_rows' => [['service' => 'Servicio sin precio', 'price_usd' => 0, 'price_label' => 'A convenir']]], $creadas);
aceptar_a_mano_ddoc($f, '11.111.111-1');
$correos = [];
at_cc_guardar_datos_contrato(at_cc_propuesta_por_id($f->id), ['tipo' => 'persona', 'direccion' => 'Calle Cuatro 4, Santiago']);
$av = $aviso_luis();
ok(count($av) === 1 && strpos($av[0]['message'], 'Todavía falta') !== false && strpos($av[0]['message'], 'listo para que lo revises') === false, '4) si falta algo, el aviso lo dice');

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
