<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/pagina-wp-test.php
// T7 ronda 1, hallazgo 1: automatizatech.cl está detrás del CDN de Hostinger (no de Cloudflare), así
// que CF-Connecting-IP y X-Forwarded-For las puede poner el propio cliente. La IP de evidencia debe
// ser siempre REMOTE_ADDR, y el límite de intentos no debe poder saltarse cambiando esas cabeceras.
require __DIR__ . '/wp-bootstrap.php';

$marca = 'prueba-cierre-pagina-' . strtolower(wp_generate_password(6, false, false));

// ---------- at_cc_ip() ignora las cabeceras manipulables; at_cc_ip_reenviada() las recoge aparte ----------
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): sin cabeceras, usa REMOTE_ADDR');
ok(at_cc_ip_reenviada() === '', 'at_cc_ip_reenviada(): sin cabeceras, vacío');

$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.99';
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): con CF-Connecting-IP falsa, sigue siendo REMOTE_ADDR');
ok(at_cc_ip_reenviada() === '198.51.100.99', 'at_cc_ip_reenviada(): recoge la CF-Connecting-IP, solo informativa');

$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 10.0.0.1';
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): con X-Forwarded-For también falso, sigue siendo REMOTE_ADDR');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
ok(at_cc_ip_reenviada() === '198.51.100.1', 'at_cc_ip_reenviada(): sin CF-Connecting-IP, toma el primer valor de X-Forwarded-For');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

// ---------- el límite por código (el principal) no se salta cambiando la cabecera ----------
$accion = 'test_' . $marca;
$codigo = 'codigo-' . $marca;
for ($i = 0; $i < 3; $i++) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.10.10.' . $i; // una cabecera distinta en cada envío
	ok(at_cc_limite_codigo_ok($accion, $codigo, 3, HOUR_IN_SECONDS) === true, 'límite por código: intento ' . ($i + 1) . ' de 3 permitido');
}
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.10.10.99'; // otra cabecera más, distinta a las tres anteriores
ok(at_cc_limite_codigo_ok($accion, $codigo, 3, HOUR_IN_SECONDS) === false, 'límite por código: el 4º intento se bloquea aunque la cabecera cambió en cada envío');
ok(at_cc_limite_codigo_ok($accion, $codigo . '-otro', 3, HOUR_IN_SECONDS) === true, 'límite por código: un código de propuesta distinto tiene su propio contador');

// ---------- el límite amplio por IP real (REMOTE_ADDR) tampoco lo evita la cabecera ----------
$accion_ip = 'test_ip_' . $marca;
$_SERVER['REMOTE_ADDR'] = '203.0.113.30';
for ($i = 0; $i < 2; $i++) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.20.20.' . $i;
	ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === true, 'límite por IP: intento ' . ($i + 1) . ' de 2 permitido');
}
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.20.20.99';
ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === false, 'límite por IP: se bloquea aunque la cabecera cambió (la clave es REMOTE_ADDR, no la cabecera)');
$_SERVER['REMOTE_ADDR'] = '203.0.113.31'; // otra conexión real: no comparte el contador de la anterior
ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === true, 'límite por IP: otra IP real (REMOTE_ADDR distinto) tiene su propio contador');

// Limpieza: cabeceras de prueba y los transients que quedaron con el máximo alcanzado.
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
delete_transient('at_cc_lim_cod_' . md5($accion . '|' . $codigo));
delete_transient('at_cc_lim_cod_' . md5($accion . '|' . $codigo . '-otro'));
delete_transient('at_cc_lim_' . md5($accion_ip . '|203.0.113.30'));
delete_transient('at_cc_lim_' . md5($accion_ip . '|203.0.113.31'));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// ================= Task 15: aceptar en la página con RUT, DNI o pasaporte =================
// Un cliente extranjero no tiene RUT: el diálogo pide el tipo de documento y el número, y solo el RUT
// se valida con dígito verificador. La acción real termina con wp_safe_redirect()+exit, así que se
// corre en un proceso aparte con el mismo hijo de pagina-respuesta-publica-wp-test.php.
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$creadas = [];
function t15_crear(string $marca, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'      => $marca . '-t15-' . count($creadas) . '@example.com',
		'unique_link_id'    => substr(md5($marca . 't15' . count($creadas) . microtime(true)), 0, 12),
		'client_name'       => 'Cliente Prueba', 'company_name' => '[PRUEBA] Documento', 'phone' => '',
		'status'            => 'sent', 'flujo' => 'v3',
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text'   => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
function t15_aceptar(object $p, array $campos): array {
	$post = array_merge([
		'action' => 'at_cc_responder', 'codigo' => (string) $p->unique_link_id, 'salida' => 'acepta',
		'nombre' => 'Ana Prueba', 'acepto' => '1', 'filas' => ['0'],
		'_wpnonce' => wp_create_nonce('at_cc_responder_' . $p->unique_link_id),
	], $campos);
	$proc = proc_open([PHP_BINARY, __DIR__ . '/pagina-respuesta-publica-wp-test-run.php', 'responder', 'POST', json_encode($post), '{}'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($proc)) { fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n"); exit(2); }
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	$correos = [];
	if (preg_match_all('/^MAIL:(.*)$/m', $out, $mm)) {
		foreach ($mm[1] as $b) { $correos[] = json_decode((string) base64_decode(trim($b)), true); }
	}
	return ['redirect' => preg_match('/^REDIRECT:(.*)$/m', $out, $m) ? trim($m[1]) : '', 'correos' => $correos, 'stderr' => trim($err)];
}
function t15_meta(int $id): array {
	global $wpdb, $det;
	$m = $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $id));
	return $m ? (json_decode((string) $m, true) ?: []) : [];
}
function t15_correo_luis(array $correos): string {
	foreach ($correos as $c) {
		if (($c['to'] ?? '') === get_option('admin_email') && strpos((string) ($c['subject'] ?? ''), 'aceptó la propuesta') !== false) {
			return (string) $c['message'];
		}
	}
	return '';
}

// El diálogo «Aceptar la propuesta» pide el tipo de documento (RUT elegido) y el número.
$t15_render = t15_crear($marca, $creadas);
ob_start();
at_cc_render_barra($t15_render);
$h15 = (string) ob_get_clean();
ok(strpos($h15, '<label for="at-cc-tipo-doc">Tu documento</label>') !== false && strpos($h15, '<select id="at-cc-tipo-doc" name="tipo_documento">') !== false, 'T15: el diálogo pide «Tu documento» con un selector name="tipo_documento"');
ok(strpos($h15, '<option value="rut" selected>RUT</option>') !== false && strpos($h15, '<option value="dni">DNI</option>') !== false && strpos($h15, '<option value="pasaporte">Pasaporte</option>') !== false, 'T15: el selector ofrece RUT (elegido), DNI y Pasaporte');
ok(strpos($h15, '<label for="at-cc-documento">Número de documento</label>') !== false && strpos($h15, 'name="documento" required placeholder="12.345.678-9"') !== false, 'T15: el número va en «Número de documento» (name="documento", placeholder 12.345.678-9)');
ok(strpos($h15, 'name="rut"') === false && strpos($h15, 'Tu RUT') === false, 'T15: el diálogo ya no pide «Tu RUT»');

// DNI válido: aceptada, con el tipo y el número en la metadata, en el contrato y en el aviso a Luis.
$t15_dni = t15_crear($marca, $creadas);
$r15 = t15_aceptar($t15_dni, ['tipo_documento' => 'dni', 'documento' => ' 12.345.678 ']);
ok(strpos($r15['redirect'], 'respuesta=aceptada') !== false && at_cc_propuesta_por_id((int) $t15_dni->id)->status === 'aceptada', 'T15: aceptar con DNI válido: la propuesta queda aceptada: ' . $r15['redirect'] . ' ' . $r15['stderr']);
$m15 = t15_meta((int) $t15_dni->id);
ok(($m15['tipo_documento'] ?? null) === 'dni' && ($m15['documento'] ?? null) === '12.345.678' && ($m15['rut'] ?? null) === '', 'T15: la metadata guarda tipo_documento=dni y el número formateado, sin RUT: ' . wp_json_encode(array_intersect_key($m15, ['tipo_documento' => 1, 'documento' => 1, 'rut' => 1])));
$c15 = at_cc_contrato_de_propuesta((int) $t15_dni->id);
$ph15 = $c15 ? (json_decode((string) $c15->placeholders, true) ?: []) : [];
ok(($ph15['tipo_documento_representante'] ?? '') === 'dni' && ($ph15['representante_cliente_rut'] ?? '') === '12.345.678', 'T15: el contrato recibe el DNI de quien aceptó como documento del representante');
$luis15 = t15_correo_luis($r15['correos']);
ok(strpos($luis15, 'Documento: DNI 12.345.678') !== false && strpos($luis15, 'RUT:') === false, 'T15: el aviso a Luis dice «Documento: DNI 12.345.678»: ' . wp_strip_all_tags($luis15));

// Pasaporte de 3 caracteres, RUT inválido o tipo desconocido: vuelve con 'datos' y no registra nada.
foreach ([
	'pasaporte de 3 caracteres' => ['tipo_documento' => 'pasaporte', 'documento' => 'X12'],
	'RUT inválido'              => ['tipo_documento' => 'rut', 'documento' => '11.111.111-2'],
	'tipo desconocido'          => ['tipo_documento' => 'cedula', 'documento' => '12345678'],
	'sin número'                => ['tipo_documento' => 'dni', 'documento' => ''],
] as $caso => $campos) {
	$q = t15_crear($marca, $creadas);
	$rq = t15_aceptar($q, $campos);
	$nq = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d", $q->id));
	ok(strpos($rq['redirect'], 'respuesta=datos') !== false && at_cc_propuesta_por_id((int) $q->id)->status === 'sent' && $nq === 0, "T15: {$caso}: vuelve con «datos» y no registra nada: " . $rq['redirect']);
}

// Compatibilidad: una página abierta desde antes (manda solo 'rut') se acepta como RUT.
$t15_viejo = t15_crear($marca, $creadas);
$r15v = t15_aceptar($t15_viejo, ['rut' => '11.111.111-1']);
$m15v = t15_meta((int) $t15_viejo->id);
ok(strpos($r15v['redirect'], 'respuesta=aceptada') !== false && ($m15v['tipo_documento'] ?? null) === 'rut' && ($m15v['documento'] ?? null) === '11.111.111-1' && ($m15v['rut'] ?? null) === '11.111.111-1', 'T15: el formulario anterior (solo «rut») se acepta como RUT: ' . $r15v['redirect']);
ok(strpos(t15_correo_luis($r15v['correos']), 'Documento: RUT 11.111.111-1') !== false, 'T15: el aviso a Luis dice «Documento: RUT 11.111.111-1»');

// Limpieza Task 15: contratos y PDF, Seguimiento, cliente CRM, propuestas y los límites de intentos.
$ids = implode(',', array_map('intval', $creadas));
foreach ($wpdb->get_results("SELECT id, contract_number FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})") as $cx) {
	@unlink(ContractService::storage_dir() . '/' . $cx->contract_number . '.pdf');
}
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$codigos = $wpdb->get_col("SELECT unique_link_id FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
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
foreach ($codigos as $cod) {
	delete_transient('at_cc_lim_cod_' . md5('responder|' . $cod));
}
delete_transient('at_cc_lim_' . md5('responder|198.51.100.77'));

fin();
