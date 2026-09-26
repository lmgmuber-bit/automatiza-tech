<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/cierre-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$payload = [
	'company_name' => '[PRUEBA] Empresa', 'solution_text' => 'Servicio de prueba.',
	'how_it_works' => [['step_title' => 'Servicio', 'step_text' => 'Servicio de prueba']],
	'pricing_rows' => [
		['service' => 'Fase 1: Servicio de prueba', 'price_usd' => 0, 'price_label' => '$1.000.000 en 2 pagos'],
		['service' => 'Fase 2: Servicio adicional', 'price_usd' => 0, 'price_label' => '$1.500.000 en 2 pagos (estimado)'],
	],
];
$creadas = [];
function crear_propuesta(string $marca, string $status, array $payload, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '@example.com', 'unique_link_id' => substr(md5($marca . $status . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
$det = $wpdb->prefix . 'automatiza_propuestas_details';
// T6 ronda 1 (revisión), hallazgo 4: datos bancarios completos por defecto para que el flujo normal
// de aceptación no traiga el aviso nuevo; el bloque "sin banco" más abajo los vacía a propósito.
update_option('at_cc_banco', 'Banco de Prueba');
update_option('at_cc_tipo_cuenta', 'Cuenta Corriente');
update_option('at_cc_numero_cuenta', '000000000');
update_option('at_cc_titular', 'AutomatizaTech SpA');
update_option('at_cc_rut_titular', '11.111.111-1');

// Lectura
$p = crear_propuesta($marca, 'sent', $payload, $creadas);
ok(at_cc_propuesta_por_codigo($p->unique_link_id)->id == $p->id && at_cc_propuesta_por_codigo('no-existe') === null && at_cc_propuesta_por_codigo('') === null, 'propuesta por código');
ok(count(at_cc_filas_de_propuesta($p)) === 2, 'filas de la propuesta');

// Evalúa y rechaza: sin cliente ni correo al cliente
$correos = [];
$r = at_cc_registrar_respuesta($p, 'evalua', ['canal' => 'pagina', 'comentario' => 'Quiero ver el plazo', 'fecha' => current_time('mysql')]);
ok($r['ok'] && $r['estado'] === 'evaluando' && at_cc_propuesta_por_id($p->id)->status === 'evaluando', 'evalúa: queda en evaluación');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p->id)) === 1, 'evalúa: queda en Seguimiento');
ok(at_cc_crm_de_email($p->client_email) === 0, 'evalúa: no crea cliente');
ok(count($correos) === 1 && $correos[0]['to'] === get_option('admin_email'), 'evalúa: solo avisa a Luis');
$p = at_cc_propuesta_por_id($p->id);
$r = at_cc_registrar_respuesta($p, 'evalua', ['canal' => 'pagina', 'comentario' => 'Otra duda']);
ok($r['ok'] && $r['mensaje'] === 'anotada' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p->id)) === 2, 'evaluar dos veces anota sin cambiar estado');
// T6 ronda 1, hallazgo 3: una segunda duda en el mismo estado también avisa a Luis (antes no avisaba nada).
ok(count($correos) === 2 && $correos[1]['to'] === get_option('admin_email') && strpos($correos[1]['message'], 'Otra duda') !== false, 'evaluar de nuevo con comentario avisa a Luis');
$r = at_cc_registrar_respuesta($p, 'rechaza', ['canal' => 'pagina', 'comentario' => 'Muy caro']);
ok($r['ok'] && at_cc_propuesta_por_id($p->id)->status === 'rechazada', 'rechaza');

// T6 ronda 1, hallazgo 2: rechazada y luego "la sigo evaluando" (o "no, gracias" de nuevo) no es un
// error: la barra pública y el correo «Pedir respuesta» siguen ofreciendo esas salidas a una
// propuesta rechazada. Se anota y avisa a Luis, sin cambiar el estado ni decir "ya_aceptada".
$correos = [];
$rj = crear_propuesta($marca . '-f2', 'rechazada', $payload, $creadas);
$r2 = at_cc_registrar_respuesta($rj, 'evalua', ['canal' => 'pagina', 'comentario' => '¿Hay descuento al contado?']);
ok($r2['ok'] && $r2['mensaje'] === 'anotada' && at_cc_propuesta_por_id($rj->id)->status === 'rechazada', 'rechazada + evaluar: se anota sin cambiar el estado');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $rj->id)) === 1, 'rechazada + evaluar: queda en Seguimiento');
ok(count($correos) === 1 && $correos[0]['to'] === get_option('admin_email') && strpos($correos[0]['message'], 'descuento al contado') !== false, 'rechazada + evaluar: avisa a Luis');
$correos = [];
$r3 = at_cc_registrar_respuesta(at_cc_propuesta_por_id($rj->id), 'rechaza', ['canal' => 'pagina', 'comentario' => 'Sigue sin convencerme']);
ok($r3['ok'] && $r3['mensaje'] === 'anotada' && at_cc_propuesta_por_id($rj->id)->status === 'rechazada', 'rechazada + rechazar de nuevo: se anota sin cambiar el estado');
ok(count($correos) === 1 && $correos[0]['to'] === get_option('admin_email'), 'rechazada + rechazar de nuevo: también avisa a Luis');

// T6 ronda 1, hallazgo 1: si ContractService::create_contract() lanza una excepción (p. ej. FPDF no
// pudo escribir el PDF por cuota de disco o permisos), el cierre no muere con un error fatal: el
// cliente y la bienvenida ya ejecutados no se pierden, el contrato queda como aviso y Luis es
// notificado igual (antes, at_cc_avisar_luis() nunca llegaba a correr).
$dir_falla = sys_get_temp_dir() . '/at-cc-test-pdf-falla-' . getmypid();
if (file_exists($dir_falla)) { @unlink($dir_falla . '/automatiza-tech-contracts'); @rmdir($dir_falla); }
mkdir($dir_falla, 0777, true);
file_put_contents($dir_falla . '/automatiza-tech-contracts', 'esto no es una carpeta'); // fuerza que FPDF no pueda escribir el PDF
$filtro_pdf_falla = function ($u) use ($dir_falla) {
	$u['basedir'] = $dir_falla;
	$u['baseurl'] = 'http://example.invalid/at-cc-test-pdf-falla';
	return $u;
};
add_filter('upload_dir', $filtro_pdf_falla);
$correos = [];
$pf = crear_propuesta($marca . '-f1', 'sent', $payload, $creadas);
$filas_pf = at_cc_filas_aceptadas(at_cc_filas_de_propuesta($pf), [0]);
$r_pf = at_cc_registrar_respuesta($pf, 'acepta', ['canal' => 'pagina', 'nombre' => 'Cliente PDF Falla', 'filas' => $filas_pf, 'fecha' => current_time('mysql'), 'bienvenida' => false]);
remove_filter('upload_dir', $filtro_pdf_falla);
@unlink($dir_falla . '/automatiza-tech-contracts');
@rmdir($dir_falla);
ok($r_pf['ok'] && at_cc_propuesta_por_id($pf->id)->status === 'aceptada', 'PDF falla: igual pasa a aceptada, sin error fatal');
ok($r_pf['crm_id'] > 0 && $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $r_pf['crm_id'])) === 'cliente', 'PDF falla: igual pasa a cliente');
ok(!empty($r_pf['avisos']) && strpos(implode(' | ', $r_pf['avisos']), 'contrato') !== false, 'PDF falla: el contrato queda como aviso, no como fatal' . ($r_pf['avisos'] ? ': ' . implode(' | ', $r_pf['avisos']) : ''));
ok(count(array_filter($correos, function ($x) { return $x['to'] === get_option('admin_email') && strpos((string) $x['subject'], 'aceptó la propuesta') !== false; })) === 1, 'PDF falla: Luis igual recibe el aviso de aceptación');

// Acepta (cambió de opinión) desde la página
$correos = [];
$p = at_cc_propuesta_por_id($p->id);
$filas = at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]);
$r = at_cc_registrar_respuesta($p, 'acepta', ['canal' => 'pagina', 'nombre' => 'Ana Prueba', 'rut' => '11.111.111-1', 'comentario' => 'Nota interna: pidió factura a nombre de la empresa', 'filas' => $filas, 'fecha' => current_time('mysql'), 'bienvenida' => true, 'ip' => '127.0.0.1']);
ok($r['ok'] && $r['estado'] === 'aceptada' && at_cc_propuesta_por_id($p->id)->status === 'aceptada', 'acepta: propuesta aceptada');
ok($r['crm_id'] > 0 && $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $r['crm_id'])) === 'cliente', 'acepta: pasa a cliente');
ok(at_cc_tech_de_crm((int) $r['crm_id']) !== null, 'acepta: con ficha operativa enlazada');
$c = at_cc_contrato_de_propuesta((int) $p->id);
$ph = $c ? json_decode($c->placeholders, true) : [];
ok($c && $c->type === 'servicios' && $c->template_id === 'servicios_v1' && $c->status === 'at_pending' && (int) $c->client_id === (int) at_cc_tech_de_crm((int) $r['crm_id'])->id, 'acepta: contrato de servicios en borrador para la ficha operativa');
ok(($ph['monto_total'] ?? '') === '$1.000.000' && ($ph['representante_cliente_rut'] ?? '') === '11.111.111-1' && strpos($ph['fases_siguientes'] ?? '', 'Fase 2') !== false && strpos($ph['entregables'] ?? '', 'Servicio') !== false, 'acepta: contrato con los datos de lo aceptado');
$al_cliente = array_values(array_filter($correos, function ($m) use ($p) { return $m['to'] === $p->client_email; }));
ok(count($al_cliente) === 1 && strpos($al_cliente[0]['subject'], 'bienvenida') !== false && strpos($al_cliente[0]['message'], '$500.000') !== false, 'acepta: bienvenida al cliente con el anticipo');
ok($r['avisos'] === [], 'acepta: sin avisos' . ($r['avisos'] ? ': ' . implode(' | ', $r['avisos']) : ''));
ok($r['avisos_operativos'] === [], 'acepta: sin avisos operativos (banco configurado)' . ($r['avisos_operativos'] ? ': ' . implode(' | ', $r['avisos_operativos']) : ''));
$u = at_cc_ultima_respuesta((int) $p->id);
ok($u && $u['salida'] === 'acepta', 'última respuesta es la aceptación');

// T6 ronda 1 (revisión), hallazgo 2: el RUT y la nota interna no van en la descripción (la
// descripción puede verla el cliente en su portal, «Ver mi portal»); quedan solo en la metadata,
// que es de donde los lee el panel interno.
$fila_resp = $wpdb->get_row($wpdb->prepare("SELECT description, metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $p->id));
ok($fila_resp && strpos($fila_resp->description, '11.111.111-1') === false && strpos($fila_resp->description, 'pidió factura') === false && strpos($fila_resp->description, 'Ana Prueba') === false, 'la descripción pública no lleva el RUT, el nombre ni la nota interna');
ok($fila_resp && strpos($fila_resp->metadata, '11.111.111-1') !== false && strpos($fila_resp->metadata, 'pidió factura') !== false, 'la metadata interna sí lleva el RUT y la nota');
// Task 15: quien registra solo con 'rut' (como antes) sigue funcionando: queda como RUT en la metadata,
// en el contrato y en el aviso a Luis («Documento: RUT …»).
$meta_rut = $fila_resp ? (json_decode((string) $fila_resp->metadata, true) ?: []) : [];
ok(($meta_rut['tipo_documento'] ?? null) === 'rut' && ($meta_rut['documento'] ?? null) === '11.111.111-1' && ($meta_rut['rut'] ?? null) === '11.111.111-1', 'T15: una aceptación con solo «rut» queda con tipo_documento=rut y el número');
ok(($ph['tipo_documento_representante'] ?? '') === 'rut' && ($ph['tipo_documento_cliente'] ?? '') === 'rut', 'T15: su contrato nace con los dos tipos de documento en RUT');
$aviso_luis_rut = array_values(array_filter($correos, function ($x) { return $x['to'] === get_option('admin_email') && strpos((string) $x['subject'], 'aceptó la propuesta') !== false; }));
ok($aviso_luis_rut && strpos((string) $aviso_luis_rut[0]['message'], 'Documento: RUT 11.111.111-1') !== false, 'T15: el aviso a Luis dice «Documento: RUT 11.111.111-1»');
// Una respuesta guardada antes de la Task 15 (metadata sin tipo_documento ni documento) se lee como RUT.
$wpdb->insert($det, ['propuesta_id' => (int) $p->id, 'detail_type' => 'respuesta_cliente', 'title' => 'Aceptó la propuesta', 'description' => 'Cómo: en la página de la propuesta', 'status' => 'completed', 'completed_date' => current_time('Y-m-d'), 'metadata' => wp_json_encode(['salida' => 'acepta', 'canal' => 'pagina', 'nombre' => 'Ana Prueba', 'rut' => '11.111.111-1', 'filas' => $filas]), 'created_at' => current_time('mysql')]);
$id_vieja = (int) $wpdb->insert_id;
$u_vieja = at_cc_ultima_respuesta((int) $p->id, 'acepta');
ok($u_vieja && $u_vieja['tipo_documento'] === 'rut' && $u_vieja['documento'] === '11.111.111-1' && $u_vieja['rut'] === '11.111.111-1', 'T15: una respuesta vieja con solo «rut» se lee como RUT (tipo y número)');
$wpdb->delete($det, ['id' => $id_vieja]);
$fila_resp_cols = $wpdb->get_row($wpdb->prepare("SELECT attachment_url, attachment_name FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $p->id));
ok($fila_resp_cols && $fila_resp_cols->attachment_url === null && $fila_resp_cols->attachment_name === null, 'la fila no llena attachment_url/attachment_name con la evidencia');

// T6 ronda 1 (revisión), hallazgo 1: una nota simple con el mismo detail_type (el widget de
// Seguimiento o at_cc_anotar_simple(), Tasks 10/10b) no tiene 'salida' en su metadata y no debe
// tapar la aceptación real al pedir la última respuesta ni al mandar la bienvenida.
at_cc_anotar_simple($p, 'respuesta_cliente', 'Nota de Luis', 'Sin relación con la aceptación');
$u_tras_nota = at_cc_ultima_respuesta((int) $p->id);
ok($u_tras_nota && $u_tras_nota['salida'] === 'acepta' && $u_tras_nota['filas'] === $filas, 'una nota simple posterior no tapa la última aceptación real');
$correos = [];
$bienvenida_2 = at_cc_enviar_bienvenida((int) $r['crm_id']);
ok($bienvenida_2 && count($correos) === 1 && strpos($correos[0]['message'], '$500.000') !== false, 'la bienvenida sigue con el anticipo aunque haya una nota simple después');

// Idempotente
$r2 = at_cc_registrar_respuesta(at_cc_propuesta_por_id($p->id), 'acepta', ['canal' => 'pagina', 'filas' => $filas]);
ok($r2['ok'] && $r2['mensaje'] === 'ya_aceptada' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $p->id)) === 1, 'aceptar dos veces no repite nada');

// Borrador: no se acepta desde la página
$b = crear_propuesta($marca, 'borrador', $payload, $creadas);
ok(!at_cc_registrar_respuesta($b, 'acepta', ['canal' => 'pagina'])['ok'] && at_cc_propuesta_por_id($b->id)->status === 'borrador', 'borrador no se acepta');

// T6 ronda 1 (revisión), hallazgo 5: un paso del cierre que falla (aquí, correo inválido: no se
// pudo pasar a cliente) queda en Seguimiento además de en el aviso a Luis; antes solo quedaba el
// registro genérico de "Aceptó la propuesta", sin rastro del fallo.
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
	'client_email' => 'correo-invalido-sin-arroba', 'unique_link_id' => substr(md5($marca . '-invalido' . microtime(true)), 0, 12),
	'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa', 'phone' => '+56 9 2222 2222',
	'status' => 'sent', 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
	'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
]);
$creadas[] = (int) $wpdb->insert_id;
$pi = at_cc_propuesta_por_id((int) $wpdb->insert_id);
$r_pi = at_cc_registrar_respuesta($pi, 'acepta', ['canal' => 'pagina', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($pi), [0])]);
ok($r_pi['ok'] && at_cc_propuesta_por_id($pi->id)->status === 'aceptada' && strpos(implode(' | ', $r_pi['avisos']), 'No se pudo pasar a cliente') !== false, 'correo inválido: aceptada con el aviso');
ok($r_pi['contrato_id'] === null && at_cc_contrato_de_propuesta((int) $pi->id) === null, 'correo inválido: sin contrato');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'cierre_incompleto'", $pi->id)) === 1, 'correo inválido: el fallo queda en Seguimiento');
$fila_ci = $wpdb->get_row($wpdb->prepare("SELECT description FROM {$det} WHERE propuesta_id = %d AND detail_type = 'cierre_incompleto' LIMIT 1", $pi->id));
ok($fila_ci && strpos($fila_ci->description, 'No se pudo pasar a cliente') !== false, 'correo inválido: el registro de Seguimiento trae el aviso');
$r_pi2 = at_cc_registrar_respuesta(at_cc_propuesta_por_id($pi->id), 'acepta', ['canal' => 'pagina']);
ok($r_pi2['ok'] && $r_pi2['mensaje'] === 'ya_aceptada', 'correo inválido: el reintento no repite nada');

// A mano desde pending, sin bienvenida
$correos = [];
$m = crear_propuesta($marca, 'pending', $payload, $creadas);
$r = at_cc_registrar_respuesta($m, 'acepta', ['canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Cliente Prueba', 'comentario' => 'Dijo que sí por WhatsApp', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($m), [0, 1]), 'fecha' => '2026-09-20 12:00:00', 'bienvenida' => false, 'usuario_id' => 1]);
ok($r['ok'] && at_cc_propuesta_por_id($m->id)->status === 'aceptada', 'a mano desde pending');
ok(count(array_filter($correos, function ($x) use ($m) { return $x['to'] === $m->client_email; })) === 0, 'a mano sin bienvenida: no escribe al cliente');
ok(count(array_filter($correos, function ($x) { return strpos((string) $x['subject'], 'aceptó la propuesta') !== false; })) === 0, 'a mano no manda el aviso «aceptó la propuesta» (lo registró Luis)');
$ph_m = json_decode(at_cc_contrato_de_propuesta((int) $m->id)->placeholders, true);
ok(($ph_m['monto_total'] ?? '') === '$2.500.000' && strpos($ph_m['canal_aceptacion'] ?? '', 'por WhatsApp') === 0, 'a mano: contrato con lo marcado y el canal');

// T6 ronda 1 (revisión), hallazgo 6: la aceptación manual también se ejercita de punta a punta desde
// 'lista' (antes solo se probaba desde 'pending').
$correos = [];
$l = crear_propuesta($marca, 'lista', $payload, $creadas);
$r_l = at_cc_registrar_respuesta($l, 'acepta', ['canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Cliente Prueba', 'comentario' => 'Dijo que sí por WhatsApp', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($l), [0, 1]), 'fecha' => '2026-09-20 12:00:00', 'bienvenida' => false, 'usuario_id' => 1]);
ok($r_l['ok'] && at_cc_propuesta_por_id($l->id)->status === 'aceptada', 'a mano desde lista');
ok(count(array_filter($correos, function ($x) use ($l) { return $x['to'] === $l->client_email; })) === 0, 'a mano desde lista sin bienvenida: no escribe al cliente');
$ph_l = json_decode(at_cc_contrato_de_propuesta((int) $l->id)->placeholders, true);
ok(($ph_l['monto_total'] ?? '') === '$2.500.000' && strpos($ph_l['canal_aceptacion'] ?? '', 'por WhatsApp') === 0, 'a mano desde lista: contrato con lo marcado y el canal');

// Pedir respuesta y bienvenida sin banco
$correos = [];
$s = crear_propuesta($marca, 'sent', $payload, $creadas);
ok(at_cc_enviar_pedido_respuesta($s) && strpos($correos[0]['message'], 'Aceptar la propuesta') !== false && strpos($correos[0]['message'], 'responder=aceptar') !== false, 'pedir respuesta manda el bloque de aceptar');
foreach (['at_cc_banco', 'at_cc_tipo_cuenta', 'at_cc_numero_cuenta', 'at_cc_titular', 'at_cc_rut_titular'] as $o) { delete_option($o); }
ok(!at_cc_banco_completo(at_cc_datos_banco()), 'sin datos bancarios');

// T6 ronda 1 (revisión), hallazgo 4: si salió la bienvenida y faltan los datos bancarios, el aviso a
// Luis lo dice (antes: la bienvenida decía «los datos de transferencia van por separado» y Luis no
// se enteraba de que tenía que mandarlos él mismo).
// T6 ronda 2, hallazgo 2: faltar los datos bancarios no es un paso fallido -el cliente se creó, el
// contrato quedó en borrador y la bienvenida salió-, así que ya no comparte el 'cierre_incompleto'
// que usan los fallos reales (antes, CUALQUIER aceptación exitosa sin banco configurado lo disparaba).
// Sigue avisando a Luis por correo y queda en Seguimiento con su propio tipo, 'aviso_operativo'.
$correos = [];
$sb = crear_propuesta($marca . '-banco', 'sent', $payload, $creadas);
$r_sb = at_cc_registrar_respuesta($sb, 'acepta', ['canal' => 'pagina', 'nombre' => 'Cliente Sin Banco', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($sb), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => true]);
ok($r_sb['ok'] && at_cc_propuesta_por_id($sb->id)->status === 'aceptada', 'sin banco: igual queda aceptada');
ok($r_sb['avisos'] === [], 'sin banco: no es un aviso de fallo real' . ($r_sb['avisos'] ? ': ' . implode(' | ', $r_sb['avisos']) : ''));
ok(in_array('Faltan los datos bancarios (Propuestas › Ajustes del cierre): envíale al cliente los datos de transferencia.', $r_sb['avisos_operativos'], true), 'sin banco: queda como aviso operativo' . (!empty($r_sb['avisos_operativos']) ? ': ' . implode(' | ', $r_sb['avisos_operativos']) : ''));
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'cierre_incompleto'", $sb->id)) === 0, 'sin banco: no queda como cierre incompleto');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'aviso_operativo'", $sb->id)) === 1, 'sin banco: queda un aviso operativo propio en Seguimiento');
ok(count(array_filter($correos, function ($x) use ($sb) { return $x['to'] === $sb->client_email; })) === 1, 'sin banco: la bienvenida igual sale');
ok(count(array_filter($correos, function ($x) { return $x['to'] === get_option('admin_email') && strpos((string) $x['message'], 'Faltan los datos bancarios') !== false; })) === 1, 'sin banco: Luis igual recibe el aviso por correo');

// Un prospecto no recibe la bienvenida de cliente
$antes = count($correos);
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Prospecto Prueba', 'email' => 'prueba-cierre-prospecto@example.com', 'tipo' => 'prospecto']);
$prospecto = (int) $wpdb->insert_id;
ok(at_cc_enviar_bienvenida($prospecto) === false && count($correos) === $antes, 'un prospecto no recibe la bienvenida de cliente');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $prospecto));

// T6 ronda 2, hallazgo 1: un cliente (tipo='cliente') con el correo mal formado (no vacío, p. ej. sin
// arroba) ya no desaparece sin rastro: antes de este fix, la guarda de entrada de
// at_cc_enviar_bienvenida() volvía false antes de wp_mail()/at_cc_historial_crm(), y el controlador
// (_enviar_correo_bienvenida) ya no cae al correo antiguo para un 'cliente' (hallazgo 3, ronda 1) -
// así que ni el correo ni el historial quedaban, y Luis nunca se enteraba de que un cliente recién
// convertido no recibió su bienvenida.
$correos = [];
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Cliente Correo Invalido', 'email' => 'no-es-un-email-valido', 'tipo' => 'cliente']);
$cliente_correo_malo = (int) $wpdb->insert_id;
$hist_antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $cliente_correo_malo));
ok(at_cc_enviar_bienvenida($cliente_correo_malo) === false && count($correos) === 0, 'cliente con correo mal formado: no manda nada');
$hist_despues = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $cliente_correo_malo));
ok($hist_despues === $hist_antes + 1, 'cliente con correo mal formado: ahora queda un rastro en el historial (antes desaparecía sin dejar ninguno)');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $cliente_correo_malo));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $cliente_correo_malo));

// T6 ronda 1 (revisión), hallazgo 3: un Bcc rechazado hace que wp_mail() devuelva false aunque el
// correo principal sí llegó; el controlador (_enviar_correo_bienvenida, disparado por «Convertir a
// Cliente») decide por el tipo del registro en el CRM, no por ese valor, para no mandar además la
// bienvenida antigua (el cliente recibiría dos correos de bienvenida).
$correos = [];
$correo_bcc = 'prueba-cierre-bcc-rechazado@example.com';
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Cliente Bcc Rechazado', 'email' => $correo_bcc, 'tipo' => 'cliente']);
$cliente_bcc = (int) $wpdb->insert_id;
$filtro_bcc_falla = function ($preempt, $atts) use ($correo_bcc) {
	return $atts['to'] === $correo_bcc ? false : $preempt;
};
add_filter('pre_wp_mail', $filtro_bcc_falla, 20, 2);
$m_bienvenida_ctrl = new ReflectionMethod($GLOBALS['at_crm_ai'], '_enviar_correo_bienvenida');
$m_bienvenida_ctrl->setAccessible(true);
$m_bienvenida_ctrl->invoke($GLOBALS['at_crm_ai'], $cliente_bcc);
remove_filter('pre_wp_mail', $filtro_bcc_falla, 20);
ok(count($correos) === 1 && strpos((string) $correos[0]['subject'], 'bienvenida') !== false, 'Bcc rechazado en un cliente: no manda además la bienvenida antigua' . ' (correos: ' . count($correos) . ')');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $cliente_bcc));

// Revisión final (26-sep), hallazgo 8: «Pedir respuesta» a una rechazada no manda el enlace de dudas
// (la página de una rechazada solo ofrece aceptar); a una en evaluación, sí.
$correos = [];
$p8 = crear_propuesta($marca . '-rf8', 'rechazada', $payload, $creadas);
ok(at_cc_enviar_pedido_respuesta($p8) && count($correos) === 1 && strpos($correos[0]['message'], 'responder=aceptar') !== false && strpos($correos[0]['message'], 'responder=evaluar') === false && strpos($correos[0]['message'], '¿Tienes dudas') === false, 'RF8: rechazada: el correo lleva solo el enlace de aceptar');
$correos = [];
$p8e = crear_propuesta($marca . '-rf8e', 'evaluando', $payload, $creadas);
ok(at_cc_enviar_pedido_respuesta($p8e) && count($correos) === 1 && strpos($correos[0]['message'], 'responder=evaluar') !== false, 'RF8: en evaluación: el correo sigue con el enlace de dudas');

// Revisión final (26-sep), hallazgo 7: un cierre a medias se completa con la última aceptación, sin
// repetir lo que ya se hizo.
$cuenta = function (string $sql, ...$args) use ($wpdb): int { return (int) $wpdb->get_var($wpdb->prepare($sql, ...$args)); };
$sql_contratacion = "SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id = %d AND detail_type = 'contratacion'";
$sql_conversion = "SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'conversion' AND titulo = 'Aceptó la propuesta'";
// (a) Correo inválido (la propuesta $pi de más arriba): no se pudo pasar a cliente. Luis corrige el correo y completa.
ok(at_cc_cierre_incompleto(at_cc_propuesta_por_id($pi->id)), 'RF7: correo inválido: el cierre queda incompleto');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '-rf7@example.com'], ['id' => $pi->id]);
$pi_ok = at_cc_propuesta_por_id($pi->id);
$correos = [];
$r7 = at_cc_completar_cierre($pi_ok, true);
$crm7 = at_cc_crm_de_email($pi_ok->client_email);
ok($r7['ok'] && $r7['avisos'] === [] && $crm7 > 0 && $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm7)) === 'cliente' && at_cc_tech_de_crm($crm7) !== null, 'RF7: completar: pasa a cliente con su ficha operativa' . ($r7['avisos'] ? ': ' . implode(' | ', $r7['avisos']) : ''));
$c7 = at_cc_contrato_de_propuesta((int) $pi->id);
$ph7 = $c7 ? json_decode($c7->placeholders, true) : [];
ok($c7 && $c7->type === 'servicios' && ($ph7['monto_total'] ?? '') === '$1.000.000' && (int) $c7->client_id === (int) at_cc_tech_de_crm($crm7)->id, 'RF7: completar: crea el contrato de servicios con lo aceptado');
ok(!at_cc_cierre_incompleto($pi_ok), 'RF7: completar: el cierre ya no está incompleto');
ok(count(array_filter($correos, function ($x) use ($pi_ok) { return $x['to'] === $pi_ok->client_email && strpos((string) $x['subject'], 'bienvenida') !== false; })) === 1, 'RF7: completar: la bienvenida sale una vez');
ok($cuenta($sql_contratacion, $pi->id) === 1 && $cuenta($sql_conversion, $crm7) === 1, 'RF7: completar: Seguimiento migrado y conversión anotada una vez');
$correos = [];
$r7b = at_cc_completar_cierre($pi_ok, true);
ok($r7b['ok'] && count(array_filter($correos, function ($x) use ($pi_ok) { return $x['to'] === $pi_ok->client_email; })) === 0, 'RF7: completar dos veces: no reenvía la bienvenida');
ok($cuenta($sql_contratacion, $pi->id) === 1 && $cuenta($sql_conversion, $crm7) === 1 && $cuenta("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $pi->id) === 1, 'RF7: completar dos veces: no repite la migración, la conversión ni el contrato');
// (b) Contrato que falta (se borra el de la propuesta $p, aceptada completa más arriba): se crea el
// contrato y nada más (la bienvenida ya había salido).
$wpdb->delete(ContractService::table(), ['proposal_id' => $p->id]);
$p_fresca = at_cc_propuesta_por_id($p->id);
ok(at_cc_cierre_incompleto($p_fresca), 'RF7: sin contrato: el cierre queda incompleto');
$contratacion_antes = $cuenta($sql_contratacion, $p->id);
$crm_p = at_cc_crm_de_email($p->client_email);
$conversion_antes = $cuenta($sql_conversion, $crm_p);
$correos = [];
$r7c = at_cc_completar_cierre($p_fresca, true);
ok($r7c['ok'] && at_cc_contrato_de_propuesta((int) $p->id) !== null && !at_cc_cierre_incompleto($p_fresca), 'RF7: sin contrato: completar crea el contrato');
ok(count(array_filter($correos, function ($x) use ($p_fresca) { return $x['to'] === $p_fresca->client_email; })) === 0, 'RF7: sin contrato: no reenvía la bienvenida que ya había salido');
ok($cuenta($sql_contratacion, $p->id) === $contratacion_antes && $cuenta($sql_conversion, $crm_p) === $conversion_antes, 'RF7: sin contrato: no vuelve a migrar el Seguimiento ni a anotar la conversión');
ok(!at_cc_completar_cierre(at_cc_propuesta_por_id($p8->id), true)['ok'], 'RF7: una propuesta que no está aceptada no se completa');

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
