<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/cierre-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$payload = [
	'company_name' => '[PRUEBA] Muebles', 'solution_text' => 'Configurador 3D de módulos.',
	'how_it_works' => [['step_title' => 'Configurador', 'step_text' => 'Arrastrar y apilar módulos']],
	'pricing_rows' => [
		['service' => 'Fase 1: Configurador 3D', 'price_usd' => 0, 'price_label' => '$2.000.000 en 2 pagos'],
		['service' => 'Fase 2: Sitio web', 'price_usd' => 0, 'price_label' => '$2.500.000 en 2 pagos (estimado)'],
	],
];
$creadas = [];
function crear_propuesta(string $marca, string $status, array $payload, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '@example.com', 'unique_link_id' => substr(md5($marca . $status . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Muebles', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => 'v3', 'gamma_prompt_text' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
$det = $wpdb->prefix . 'automatiza_propuestas_details';

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
$r = at_cc_registrar_respuesta($p, 'acepta', ['canal' => 'pagina', 'nombre' => 'Ana Prueba', 'rut' => '11.111.111-1', 'filas' => $filas, 'fecha' => current_time('mysql'), 'bienvenida' => true, 'ip' => '127.0.0.1']);
ok($r['ok'] && $r['estado'] === 'aceptada' && at_cc_propuesta_por_id($p->id)->status === 'aceptada', 'acepta: propuesta aceptada');
ok($r['crm_id'] > 0 && $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $r['crm_id'])) === 'cliente', 'acepta: pasa a cliente');
ok(at_cc_tech_de_crm((int) $r['crm_id']) !== null, 'acepta: con ficha operativa enlazada');
$c = at_cc_contrato_de_propuesta((int) $p->id);
$ph = $c ? json_decode($c->placeholders, true) : [];
ok($c && $c->type === 'servicios' && $c->template_id === 'servicios_v1' && $c->status === 'at_pending' && (int) $c->client_id === (int) at_cc_tech_de_crm((int) $r['crm_id'])->id, 'acepta: contrato de servicios en borrador para la ficha operativa');
ok(($ph['monto_total'] ?? '') === '$2.000.000' && ($ph['representante_cliente_rut'] ?? '') === '11.111.111-1' && strpos($ph['fases_siguientes'] ?? '', 'Fase 2') !== false && strpos($ph['entregables'] ?? '', 'Configurador') !== false, 'acepta: contrato con los datos de lo aceptado');
$al_cliente = array_values(array_filter($correos, function ($m) use ($p) { return $m['to'] === $p->client_email; }));
ok(count($al_cliente) === 1 && strpos($al_cliente[0]['subject'], 'bienvenida') !== false && strpos($al_cliente[0]['message'], '$1.000.000') !== false, 'acepta: bienvenida al cliente con el anticipo');
ok($r['avisos'] === [], 'acepta: sin avisos' . ($r['avisos'] ? ': ' . implode(' | ', $r['avisos']) : ''));
$u = at_cc_ultima_respuesta((int) $p->id);
ok($u && $u['salida'] === 'acepta', 'última respuesta es la aceptación');

// Idempotente
$r2 = at_cc_registrar_respuesta(at_cc_propuesta_por_id($p->id), 'acepta', ['canal' => 'pagina', 'filas' => $filas]);
ok($r2['ok'] && $r2['mensaje'] === 'ya_aceptada' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $p->id)) === 1, 'aceptar dos veces no repite nada');

// Borrador: no se acepta desde la página
$b = crear_propuesta($marca, 'borrador', $payload, $creadas);
ok(!at_cc_registrar_respuesta($b, 'acepta', ['canal' => 'pagina'])['ok'] && at_cc_propuesta_por_id($b->id)->status === 'borrador', 'borrador no se acepta');

// A mano desde pending, sin bienvenida
$correos = [];
$m = crear_propuesta($marca, 'pending', $payload, $creadas);
$r = at_cc_registrar_respuesta($m, 'acepta', ['canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Cliente Prueba', 'comentario' => 'Dijo que sí por WhatsApp', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($m), [0, 1]), 'fecha' => '2026-09-20 12:00:00', 'bienvenida' => false, 'usuario_id' => 1]);
ok($r['ok'] && at_cc_propuesta_por_id($m->id)->status === 'aceptada', 'a mano desde pending');
ok(count(array_filter($correos, function ($x) use ($m) { return $x['to'] === $m->client_email; })) === 0, 'a mano sin bienvenida: no escribe al cliente');
ok(count(array_filter($correos, function ($x) { return strpos((string) $x['subject'], 'aceptó la propuesta') !== false; })) === 0, 'a mano no manda el aviso «aceptó la propuesta» (lo registró Luis)');
$ph_m = json_decode(at_cc_contrato_de_propuesta((int) $m->id)->placeholders, true);
ok(($ph_m['monto_total'] ?? '') === '$4.500.000' && strpos($ph_m['canal_aceptacion'] ?? '', 'por WhatsApp') === 0, 'a mano: contrato con lo marcado y el canal');

// Pedir respuesta y bienvenida sin banco
$correos = [];
$s = crear_propuesta($marca, 'sent', $payload, $creadas);
ok(at_cc_enviar_pedido_respuesta($s) && strpos($correos[0]['message'], 'Aceptar la propuesta') !== false && strpos($correos[0]['message'], 'responder=aceptar') !== false, 'pedir respuesta manda el bloque de aceptar');
foreach (['at_cc_banco', 'at_cc_tipo_cuenta', 'at_cc_numero_cuenta', 'at_cc_titular', 'at_cc_rut_titular'] as $o) { delete_option($o); }
ok(!at_cc_banco_completo(at_cc_datos_banco()), 'sin datos bancarios');

// Un prospecto no recibe la bienvenida de cliente
$antes = count($correos);
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => 'Prospecto Prueba', 'email' => 'prueba-cierre-prospecto@example.com', 'tipo' => 'prospecto']);
$prospecto = (int) $wpdb->insert_id;
ok(at_cc_enviar_bienvenida($prospecto) === false && count($correos) === $antes, 'un prospecto no recibe la bienvenida de cliente');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $prospecto));

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
