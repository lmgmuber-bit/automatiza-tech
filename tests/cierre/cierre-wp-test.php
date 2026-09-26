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
$r = at_cc_registrar_respuesta($p, 'rechaza', ['canal' => 'pagina', 'comentario' => 'Muy caro']);
ok($r['ok'] && at_cc_propuesta_por_id($p->id)->status === 'rechazada', 'rechaza');

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
