<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/pagina-datos-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-cierre-datos-' . strtolower(wp_generate_password(6, false, false));
$payload = [
	'company_name' => '[PRUEBA] Empresa', 'solution_text' => 'Servicio de prueba.',
	'pricing_rows' => [['service' => 'Fase 1: Servicio de prueba', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos']],
];
$creadas = [];
function crear_propuesta_datos(string $marca, array $payload, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . microtime(true) . '@example.com', 'unique_link_id' => substr(md5($marca . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa', 'phone' => '+56 9 2222 2222',
		'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$tech = $wpdb->prefix . 'automatiza_tech_clients';

// Propuesta no aceptada: 'recibida', sin contrato.
$sin_aceptar = crear_propuesta_datos($marca . '-sin-aceptar', $payload, $creadas);
ok(at_cc_guardar_datos_contrato($sin_aceptar, ['tipo' => 'persona', 'direccion' => 'Calle Falsa 123, Santiago']) === 'recibida', 'propuesta no aceptada: recibida');

// Aceptar para tener cliente, ficha operativa y contrato de servicios.
function aceptar_para_prueba(object $p): array {
	$filas = at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]);
	$r = at_cc_registrar_respuesta($p, 'acepta', [
		'canal' => 'pagina', 'nombre' => 'Ana Prueba', 'rut' => '11.111.111-1',
		'filas' => $filas, 'fecha' => current_time('mysql'), 'bienvenida' => false,
	]);
	if (!$r['ok'] || $r['estado'] !== 'aceptada' || !$r['contrato_id']) {
		fwrite(STDERR, "No se pudo aceptar la propuesta de prueba.\n");
		exit(2);
	}
	return $r;
}

// ---------- persona + dirección ----------
$p1 = crear_propuesta_datos($marca . '-persona', $payload, $creadas);
$r1 = aceptar_para_prueba($p1);
$p1 = at_cc_propuesta_por_id($p1->id);
$antes_notas_1 = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p1->id));
$clave1 = at_cc_guardar_datos_contrato($p1, ['tipo' => 'persona', 'direccion' => 'Av. Siempre Viva 742, Providencia']);
ok($clave1 === 'datos_ok', 'persona + dirección: datos_ok');
$c1 = ContractService::get_by_id((int) $r1['contrato_id']);
$ph1 = json_decode($c1->placeholders, true);
ok(($ph1['tipo_cliente'] ?? '') === 'persona', 'persona: tipo_cliente = persona');
ok(($ph1['razon_social_cliente'] ?? '') === 'Ana Prueba', 'persona: razon_social_cliente = nombre de quien aceptó');
ok(($ph1['rut_cliente'] ?? '') === '11.111.111-1', 'persona: rut_cliente = su RUT');
ok(($ph1['domicilio_cliente'] ?? '') === 'Av. Siempre Viva 742, Providencia', 'persona: domicilio_cliente = la dirección');
ok(empty($ph1['revision_at']), 'persona: sin revision_at (Luis no ha revisado)');
$fila_tech_1 = $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id FROM {$tech} WHERE id = %d", (int) $c1->client_id));
ok($fila_tech_1 && $fila_tech_1->billing_address === 'Av. Siempre Viva 742, Providencia', 'persona: ficha operativa recibe billing_address (estaba vacío)');
ok($fila_tech_1 && $fila_tech_1->tax_id === '11.111.111-1', 'persona: ficha operativa recibe tax_id con el RUT de quien aceptó (estaba vacío)');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p1->id)) === $antes_notas_1 + 1, 'persona: queda una nota nueva en Seguimiento');
$nota1 = $wpdb->get_row($wpdb->prepare("SELECT description, metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $p1->id));
$meta1 = json_decode((string) $nota1->metadata, true) ?: [];
ok(strpos((string) $nota1->description, 'Av. Siempre Viva') === false && strpos((string) $nota1->description, '11.111.111-1') === false, 'persona: la descripción pública (Seguimiento) no lleva la dirección ni el RUT');
ok(($meta1['direccion'] ?? '') === 'Av. Siempre Viva 742, Providencia' && ($meta1['tipo'] ?? '') === 'persona', 'persona: la dirección y el tipo quedan en metadata, no en la descripción pública');

// ---------- empresa sin RUT válido ----------
$p2 = crear_propuesta_datos($marca . '-empresa-mal', $payload, $creadas);
$r2 = aceptar_para_prueba($p2);
$p2 = at_cc_propuesta_por_id($p2->id);
$c2_antes = ContractService::get_by_id((int) $r2['contrato_id']);
$ph2_antes = $c2_antes->placeholders;
$fila_tech_2_antes = $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id, company FROM {$tech} WHERE id = %d", (int) $c2_antes->client_id));
$notas_2_antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p2->id));
$clave2 = at_cc_guardar_datos_contrato($p2, ['tipo' => 'empresa', 'direccion' => 'Otra 1', 'razon_social' => '[PRUEBA] Empresa SpA', 'rut_empresa' => '11.111.111-2']);
ok($clave2 === 'datos_contrato', 'empresa sin RUT válido: datos_contrato');
ok(ContractService::get_by_id((int) $r2['contrato_id'])->placeholders === $ph2_antes, 'empresa sin RUT válido: el contrato no cambia');
$fila_tech_2_despues = $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id, company FROM {$tech} WHERE id = %d", (int) $c2_antes->client_id));
ok($fila_tech_2_despues == $fila_tech_2_antes, 'empresa sin RUT válido: la ficha operativa no cambia');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p2->id)) === $notas_2_antes, 'empresa sin RUT válido: no queda nota nueva (nada cambia)');

// ---------- empresa completa (company de la ficha ya viene con un valor: no se pisa) ----------
$p3 = crear_propuesta_datos($marca . '-empresa-ok', $payload, $creadas);
$r3 = aceptar_para_prueba($p3);
$p3 = at_cc_propuesta_por_id($p3->id);
$c3 = ContractService::get_by_id((int) $r3['contrato_id']);
$company_antes = (string) $wpdb->get_var($wpdb->prepare("SELECT company FROM {$tech} WHERE id = %d", (int) $c3->client_id));
ok($company_antes !== '', 'empresa completa: la ficha operativa ya trae una empresa (la de la propuesta)');
$clave3 = at_cc_guardar_datos_contrato($p3, ['tipo' => 'empresa', 'direccion' => 'Calle Uno 1, Ñuñoa', 'razon_social' => '[PRUEBA] Empresa SpA', 'rut_empresa' => '10.000.013-K']);
ok($clave3 === 'datos_ok', 'empresa completa: datos_ok');
$ph3 = json_decode(ContractService::get_by_id((int) $r3['contrato_id'])->placeholders, true);
ok(($ph3['tipo_cliente'] ?? '') === 'empresa' && ($ph3['razon_social_cliente'] ?? '') === '[PRUEBA] Empresa SpA' && ($ph3['rut_cliente'] ?? '') === '10.000.013-K', 'empresa completa: contrato con razón social y RUT de la empresa');
$company_despues = (string) $wpdb->get_var($wpdb->prepare("SELECT company FROM {$tech} WHERE id = %d", (int) $c3->client_id));
ok($company_despues === $company_antes, 'empresa completa: la ficha operativa no pisa la empresa que ya tenía');
$nota3 = $wpdb->get_row($wpdb->prepare("SELECT description, metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $p3->id));
$meta3 = json_decode((string) $nota3->metadata, true) ?: [];
ok(strpos((string) $nota3->description, 'Empresa SpA') === false && strpos((string) $nota3->description, '10.000.013-K') === false && strpos((string) $nota3->description, 'Calle Uno 1') === false, 'empresa completa: la descripción pública (Seguimiento) no lleva razón social, RUT ni dirección');
ok(($meta3['razon_social'] ?? '') === '[PRUEBA] Empresa SpA' && ($meta3['rut'] ?? '') === '10.000.013-K' && ($meta3['direccion'] ?? '') === 'Calle Uno 1, Ñuñoa', 'empresa completa: razón social, RUT y dirección quedan en metadata');

// ---------- Task 15: persona que aceptó con DNI ----------
$p10 = crear_propuesta_datos($marca . '-persona-dni', $payload, $creadas);
$r10 = at_cc_registrar_respuesta($p10, 'acepta', [
	'canal' => 'pagina', 'nombre' => 'Ana Prueba', 'tipo_documento' => 'dni', 'documento' => '12345678',
	'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p10), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => false,
]);
ok($r10['ok'] && $r10['estado'] === 'aceptada' && $r10['contrato_id'], 'T15: aceptación con DNI: aceptada y con contrato');
$p10 = at_cc_propuesta_por_id($p10->id);
$ph10_antes = json_decode(ContractService::get_by_id((int) $r10['contrato_id'])->placeholders, true);
ok(($ph10_antes['tipo_documento_representante'] ?? '') === 'dni' && ($ph10_antes['representante_cliente_rut'] ?? '') === '12345678' && ($ph10_antes['tipo_documento_cliente'] ?? '') === 'rut', 'T15: el contrato nace con el DNI de quien aceptó como documento del representante');
$clave10 = at_cc_guardar_datos_contrato($p10, ['tipo' => 'persona', 'direccion' => 'Av. Siempre Viva 742, Providencia']);
ok($clave10 === 'datos_ok', 'T15: persona con DNI + dirección: datos_ok');
$c10 = ContractService::get_by_id((int) $r10['contrato_id']);
$ph10 = json_decode($c10->placeholders, true);
ok(($ph10['tipo_documento_cliente'] ?? '') === 'dni' && ($ph10['rut_cliente'] ?? '') === '12345678' && ($ph10['razon_social_cliente'] ?? '') === 'Ana Prueba', 'T15: persona natural: el contrato toma el tipo y el número de documento de quien aceptó');
ok(strpos(ContractService::comparecencia_cliente($ph10), '**Ana Prueba** (en adelante "**EL CLIENTE**"), DNI N° **12345678**, correo ') === 0, 'T15: la comparecencia dice «DNI N° **12345678**»');
ok(ContractService::faltantes($c10) === [], 'T15: con los demás datos del contrato, faltantes() queda vacío');
$fila_tech_10 = $wpdb->get_row($wpdb->prepare("SELECT tax_id FROM {$tech} WHERE id = %d", (int) $c10->client_id));
ok($fila_tech_10 && $fila_tech_10->tax_id === '12345678', 'T15: la ficha operativa recibe el número de documento (tax_id acepta RUT, DNI o pasaporte, como el formulario de contacto)');

// ---------- Task 15: empresa cuyo representante aceptó con pasaporte ----------
$p11 = crear_propuesta_datos($marca . '-empresa-pasaporte', $payload, $creadas);
$r11 = at_cc_registrar_respuesta($p11, 'acepta', [
	'canal' => 'pagina', 'nombre' => 'Ana Prueba', 'tipo_documento' => 'pasaporte', 'documento' => 'AB123456',
	'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p11), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => false,
]);
$p11 = at_cc_propuesta_por_id($p11->id);
$clave11 = at_cc_guardar_datos_contrato($p11, ['tipo' => 'empresa', 'direccion' => 'Calle Uno 1, Ñuñoa', 'razon_social' => '[PRUEBA] Empresa SpA', 'rut_empresa' => '10.000.013-K']);
$ph11 = json_decode(ContractService::get_by_id((int) $r11['contrato_id'])->placeholders, true);
ok($clave11 === 'datos_ok' && ($ph11['tipo_documento_cliente'] ?? '') === 'rut' && ($ph11['rut_cliente'] ?? '') === '10.000.013-K' && ($ph11['tipo_documento_representante'] ?? '') === 'pasaporte', 'T15: empresa: el cliente queda con RUT y el representante con su pasaporte');
ok(strpos(ContractService::comparecencia_cliente($ph11), 'RUT **10.000.013-K**, representada por **Ana Prueba**, pasaporte N° **AB123456**, correo') !== false, 'T15: la comparecencia de la empresa: «representada por **Ana Prueba**, pasaporte N° **AB123456**»');
ok(ContractService::faltantes(ContractService::get_by_id((int) $r11['contrato_id'])) === [], 'T15: empresa con representante con pasaporte: faltantes() vacío');

// ---------- billing_address y tax_id ya poblados: no se pisan (T7 ronda 1, hallazgo 4) ----------
$p5 = crear_propuesta_datos($marca . '-ficha-poblada', $payload, $creadas);
$r5 = aceptar_para_prueba($p5);
$p5 = at_cc_propuesta_por_id($p5->id);
$c5 = ContractService::get_by_id((int) $r5['contrato_id']);
$wpdb->update($tech, ['billing_address' => 'Dirección ya existente 99, Santiago', 'tax_id' => '9.999.999-9'], ['id' => (int) $c5->client_id]);
$clave5 = at_cc_guardar_datos_contrato($p5, ['tipo' => 'persona', 'direccion' => 'Dirección nueva 100']);
ok($clave5 === 'datos_ok', 'ficha ya con billing_address y tax_id: datos_ok');
$fila_tech_5 = $wpdb->get_row($wpdb->prepare("SELECT billing_address, tax_id FROM {$tech} WHERE id = %d", (int) $c5->client_id));
ok($fila_tech_5 && $fila_tech_5->billing_address === 'Dirección ya existente 99, Santiago', 'ficha ya con billing_address: no se pisa');
ok($fila_tech_5 && $fila_tech_5->tax_id === '9.999.999-9', 'ficha ya con tax_id: no se pisa');

// ---------- contrato ya firmado (bad_status del servicio): no es un error de validación del cliente (T7 ronda 1, hallazgo 3) ----------
$p7 = crear_propuesta_datos($marca . '-firmado', $payload, $creadas);
$r7 = aceptar_para_prueba($p7);
$p7 = at_cc_propuesta_por_id($p7->id);
$wpdb->update(ContractService::table(), ['status' => 'signed'], ['id' => (int) $r7['contrato_id']]);
$ph7_antes = ContractService::get_by_id((int) $r7['contrato_id'])->placeholders;
$clave7 = at_cc_guardar_datos_contrato($p7, ['tipo' => 'persona', 'direccion' => 'Calle Firmado 7']);
ok($clave7 === 'datos_recibidos', 'contrato ya firmado (bad_status): datos_recibidos, no el falso aviso de validación "datos_contrato"');
ok(ContractService::get_by_id((int) $r7['contrato_id'])->placeholders === $ph7_antes, 'contrato ya firmado: el contrato no cambia');

// ---------- FPDF no puede escribir el PDF al guardar los datos: no es un error fatal (T7 ronda 1, hallazgo 2) ----------
$p8 = crear_propuesta_datos($marca . '-pdf-falla', $payload, $creadas);
$r8 = aceptar_para_prueba($p8);
$p8 = at_cc_propuesta_por_id($p8->id);
$notas_8_antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'cierre_incompleto'", $p8->id));
// Mismo truco que en cierre-wp-test.php (T6 ronda 1, hallazgo 1): la carpeta de contratos es en
// realidad un archivo, así que FPDF no puede escribir el PDF y ContractService::guardar_marcadores()
// lanza una excepción.
$dir_falla_8 = sys_get_temp_dir() . '/at-cc-test-pdf-falla-datos-' . getmypid();
if (file_exists($dir_falla_8)) { @unlink($dir_falla_8 . '/automatiza-tech-contracts'); @rmdir($dir_falla_8); }
mkdir($dir_falla_8, 0777, true);
file_put_contents($dir_falla_8 . '/automatiza-tech-contracts', 'esto no es una carpeta');
$filtro_pdf_falla_8 = function ($u) use ($dir_falla_8) {
	$u['basedir'] = $dir_falla_8;
	$u['baseurl'] = 'http://example.invalid/at-cc-test-pdf-falla-datos';
	return $u;
};
add_filter('upload_dir', $filtro_pdf_falla_8);
$clave8 = at_cc_guardar_datos_contrato($p8, ['tipo' => 'persona', 'direccion' => 'Calle PDF Falla 8']);
remove_filter('upload_dir', $filtro_pdf_falla_8);
@unlink($dir_falla_8 . '/automatiza-tech-contracts');
@rmdir($dir_falla_8);
ok($clave8 === 'datos_recibidos', 'PDF falla al guardar los datos: datos_recibidos, sin error fatal');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'cierre_incompleto'", $p8->id)) === $notas_8_antes + 1, 'PDF falla al guardar los datos: queda una nota de cierre_incompleto en Seguimiento');
$nota8 = $wpdb->get_row($wpdb->prepare("SELECT description FROM {$det} WHERE propuesta_id = %d AND detail_type = 'cierre_incompleto' ORDER BY id DESC LIMIT 1", $p8->id));
ok($nota8 && strpos((string) $nota8->description, 'datos del contrato') !== false, 'PDF falla al guardar los datos: la nota trae el mensaje de la excepción');

// ---------- con la revisión de Luis ya guardada ----------
$p4 = crear_propuesta_datos($marca . '-revisado', $payload, $creadas);
$r4 = aceptar_para_prueba($p4);
$p4 = at_cc_propuesta_por_id($p4->id);
ContractService::guardar_revision((int) $r4['contrato_id'], ['plazo' => 'Ocho semanas']);
$ph4_antes = ContractService::get_by_id((int) $r4['contrato_id'])->placeholders;
$notas_4_antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p4->id));
$clave4 = at_cc_guardar_datos_contrato($p4, ['tipo' => 'persona', 'direccion' => 'Calle Dos 2']);
ok($clave4 === 'datos_recibidos', 'con revisión de Luis ya guardada: datos_recibidos');
ok(ContractService::get_by_id((int) $r4['contrato_id'])->placeholders === $ph4_antes, 'con revisión ya guardada: el contrato no cambia');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p4->id)) === $notas_4_antes + 1, 'con revisión ya guardada: igual queda la nota en Seguimiento');
$nota4 = $wpdb->get_row($wpdb->prepare("SELECT description, metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $p4->id));
$meta4 = json_decode((string) $nota4->metadata, true) ?: [];
ok(strpos((string) $nota4->description, 'Calle Dos 2') === false, 'con revisión ya guardada: la descripción pública tampoco lleva la dirección');
ok(($meta4['direccion'] ?? '') === 'Calle Dos 2', 'con revisión ya guardada: la dirección queda en metadata igual');

// ---------- la barra pública ofrece «Datos para tu contrato» solo cuando corresponde ----------
ob_start();
at_cc_render_barra(at_cc_propuesta_por_id($p1->id));
$html_p1 = (string) ob_get_clean();
ok(strpos($html_p1, 'Datos para tu contrato') !== false && strpos($html_p1, 'id="at-cc-datos"') !== false, 'aceptada sin revisión de Luis: la barra ofrece el botón y el diálogo');
ok(strpos($html_p1, 'A mi nombre (persona natural)') !== false && strpos($html_p1, 'De una empresa') !== false && strpos($html_p1, 'Razón social de la empresa') !== false && strpos($html_p1, 'RUT de la empresa') !== false && strpos($html_p1, 'Dirección (calle, número, comuna y ciudad)') !== false, 'el diálogo trae los campos y textos pedidos');
$_GET['respuesta'] = 'aceptada';
ob_start();
at_cc_render_barra(at_cc_propuesta_por_id($p1->id));
$html_p1_abre = (string) ob_get_clean();
unset($_GET['respuesta']);
ok(strpos($html_p1_abre, '"at-cc-datos"') !== false && strpos($html_p1_abre, 'var inicial = "at-cc-datos"') !== false, 'con respuesta=aceptada el diálogo se abre solo');
ob_start();
at_cc_render_barra(at_cc_propuesta_por_id($p4->id));
$html_p4 = (string) ob_get_clean();
ok(strpos($html_p4, 'Datos para tu contrato') === false, 'con la revisión de Luis ya guardada: la barra no ofrece el botón');

// ---------- T11 ronda 1, hallazgo 2: en 'rechazada' solo se ofrece "Acepto la propuesta" ----------
// Una propuesta rechazada solo puede pasar a 'aceptada' (at_cc_transicion_respuesta_valida()); tocar
// "La sigo evaluando" o "No, gracias" no la sacaba de 'rechazada' (respuesta.php anota una nota para
// Luis pero no cambia el estado), así que esos dos botones no debían seguir ofreciéndose.
$p9 = crear_propuesta_datos($marca . '-rechazada', $payload, $creadas);
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'rechazada'], ['id' => (int) $p9->id]);
ob_start();
at_cc_render_barra(at_cc_propuesta_por_id($p9->id));
$html_p9 = (string) ob_get_clean();
ok(strpos($html_p9, 'Acepto la propuesta') !== false && strpos($html_p9, 'id="at-cc-acepta"') !== false, 'rechazada: sigue ofreciendo "Acepto la propuesta" y su diálogo');
ok(strpos($html_p9, 'La sigo evaluando') === false && strpos($html_p9, 'No, gracias') === false, 'rechazada: ya no ofrece "La sigo evaluando" ni "No, gracias"');
ok(strpos($html_p9, 'id="at-cc-evalua"') === false && strpos($html_p9, 'id="at-cc-rechaza"') === false, 'rechazada: tampoco deja sus diálogos (ni sus formularios) en el HTML');

// Limpieza
$ids = implode(',', array_map('intval', $creadas));
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$wpdb->query("DELETE FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})");
$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
if ($crm_ids) {
	$lista = implode(',', array_map('intval', $crm_ids));
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN ({$lista})");
}
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
fin();
