<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/whatsapp-recordatorio-wp-test.php
// 29-sep (decisión de Luis): cuando Meta no entrega un recordatorio de WhatsApp, a Luis le llega un
// correo, igual que con las propuestas (Task 19).
// - Los flujos anotan el wamid en at/v1/whatsapp-envio (tipo + id de la cita o de la reunión).
// - La ruta de estados (at/v1/propuesta-whatsapp-estado) lo encuentra y avisa una sola vez por wamid.
// - Si el 'failed' llega antes que la anotación, se avisa al anotar.
// - Lo que no es un recordatorio anotado (p. ej. ARGOS) sigue ignorado, como antes.
// Los correos se simulan con pre_wp_mail; ninguna llamada HTTP sale de la prueba.
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

foreach (['at_cc_rest_envio_recordatorio', 'at_cc_envio_recordatorio_por_wamid', 'at_cc_registrar_fallo_recordatorio', 'at_cc_texto_recordatorio_no_entregado', 'at_cc_rest_estado_whatsapp', 'at_cc_clave_fallo_pendiente'] as $fn) {
	if (!function_exists($fn)) {
		fwrite(STDERR, "$fn() no está definida: revisa whatsapp-recordatorios.php y whatsapp.php.\n");
		exit(2);
	}
}
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba.\n");
	exit(2);
}

$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
add_filter('pre_http_request', function () { return new WP_Error('sin_red', 'La prueba no sale a la red.'); }, 10, 3);

$marca = 'prueba-wa-rec-' . strtolower(wp_generate_password(6, false, false));
$wamids = [];
function rec_wamid(string $marca, string $sufijo, array &$wamids): string {
	$w = 'wamid.PRUEBAREC' . strtoupper(md5($marca . $sufijo));
	$wamids[] = $w;
	return $w;
}
function rec_pedir(string $ruta, $cuerpo, ?string $clave) {
	$r = new WP_REST_Request('POST', $ruta);
	$r->set_header('content-type', 'application/json');
	if ($clave !== null) { $r->set_header('x-at-secret', $clave); }
	$r->set_body(is_string($cuerpo) ? $cuerpo : wp_json_encode($cuerpo));
	return rest_do_request($r);
}
function rec_estado(array $estados) {
	return rec_pedir('/at/v1/propuesta-whatsapp-estado', ['estados' => $estados], AT_REST_SECRET);
}
function rec_total_notas_propuestas(): int {
	global $wpdb;
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas_details WHERE detail_type = 'whatsapp_no_entregado'");
}
$ruta = '/at/v1/whatsapp-envio';
$manana = wp_date('Y-m-d', time() + DAY_IN_SECONDS);

// Datos de prueba: una cita (lead) y una reunión de seguimiento.
$ok_lead = $wpdb->insert($wpdb->prefix . 'automatiza_leads', [
	'name' => '[PRUEBA] Cliente Recordatorio', 'email' => $marca . '@example.com', 'phone' => '+56 9 1234 5678',
	'session_id' => '', 'source' => 'web', 'status' => 'pending', 'scheduled_date' => $manana, 'scheduled_time' => '10:30:00',
	'token' => md5($marca), 'created_at' => current_time('mysql'),
]);
if (!$ok_lead) { fwrite(STDERR, "No se pudo insertar la cita de prueba: {$wpdb->last_error}\n"); exit(2); }
$lead_id = (int) $wpdb->insert_id;
$ok_reu = $wpdb->insert($wpdb->prefix . 'automatiza_followup_meetings', [
	'client_name' => '[PRUEBA] Cliente Seguimiento', 'client_email' => $marca . '-seg@example.com', 'company_name' => 'Empresa Prueba',
	'phone' => '+56 9 8765 4321', 'meeting_date' => $manana, 'meeting_time' => '16:00:00', 'meeting_subject' => 'Prueba',
	'status' => 'scheduled', 'created_at' => current_time('mysql'),
]);
if (!$ok_reu) { fwrite(STDERR, "No se pudo insertar la reunión de prueba: {$wpdb->last_error}\n"); $wpdb->delete($wpdb->prefix . 'automatiza_leads', ['id' => $lead_id]); exit(2); }
$reunion_id = (int) $wpdb->insert_id;
$notas_antes = rec_total_notas_propuestas();

// ---------- 1) Clave ----------
$w1 = rec_wamid($marca, '1', $wamids);
ok(rec_pedir($ruta, ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => $lead_id], null)->get_status() === 401, 'sin clave: 401');
ok(rec_pedir($ruta, ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => $lead_id], 'otra-clave')->get_status() === 401, 'clave equivocada: 401');
ok(at_cc_envio_recordatorio_por_wamid($w1) === null, 'sin clave no se anota nada');

// ---------- 2) Cuerpos inválidos: 400 y nada anotado ----------
$malos = [
	'wamid raro'     => ['wamid' => 'hola', 'tipo' => 'cita_24h', 'id' => $lead_id],
	'tipo que no es' => ['wamid' => $w1, 'tipo' => 'cita_2h', 'id' => $lead_id],
	'id 0'           => ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => 0],
	'id negativo'    => ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => -3],
	'id con letras'  => ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => '12a'],
	'id decimal'     => ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => 1.5],
	'sin id'         => ['wamid' => $w1, 'tipo' => 'cita_24h'],
];
foreach ($malos as $nombre => $cuerpo) {
	ok(rec_pedir($ruta, $cuerpo, AT_REST_SECRET)->get_status() === 400, "cuerpo inválido ($nombre): 400");
}
ok(at_cc_envio_recordatorio_por_wamid($w1) === null, 'los cuerpos inválidos no anotan nada');

// ---------- 3) Anotar un recordatorio de cita ----------
$res = rec_pedir($ruta, ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => $lead_id], AT_REST_SECRET);
ok($res->get_status() === 200 && $res->get_data() === ['ok' => true, 'fallo_avisado' => false], 'anotar cita_24h: 200 {ok, fallo_avisado:false}');
$e1 = at_cc_envio_recordatorio_por_wamid($w1);
ok(is_array($e1) && $e1['tipo'] === 'cita_24h' && $e1['id'] === $lead_id, 'el envío queda anotado con tipo e id');
if (!wp_using_ext_object_cache()) {
	$vence = (int) get_option('_transient_timeout_' . at_cc_clave_envio_recordatorio($w1));
	ok(abs($vence - (time() + 7 * DAY_IN_SECONDS)) < 120, 'la anotación dura 7 días');
}
$res = rec_pedir($ruta, ['wamid' => $w1, 'tipo' => 'cita_24h', 'id' => (string) $lead_id], AT_REST_SECRET);
ok($res->get_status() === 200, 'el id también se acepta como texto de dígitos (así lo manda n8n a veces)');

// ---------- 4) Un estado que no es 'failed' no avisa ----------
$correos = [];
$res = rec_estado([['wamid' => $w1, 'estado' => 'delivered']]);
ok($res->get_data()['avisados'] === 0 && $res->get_data()['ignorados'] === 1 && !$correos, "'delivered': ignorado, sin correo");

// ---------- 5) 'failed' de un recordatorio anotado: un correo a Luis ----------
$res = rec_estado([['wamid' => $w1, 'estado' => 'failed', 'codigo' => 131026, 'titulo' => 'Message undeliverable', 'telefono' => '56912345678']]);
ok($res->get_data()['avisados'] === 1 && $res->get_data()['ignorados'] === 0, "'failed' de la cita: avisados 1");
ok(count($correos) === 1, 'sale exactamente un correo (' . count($correos) . ')');
$c = $correos[0] ?? ['to' => '', 'subject' => '', 'message' => '', 'headers' => []];
ok($c['to'] === at_cc_correo_avisos(), 'el correo va al destinatario de los avisos del cierre');
ok($c['subject'] === '[PRUEBA] Cliente Recordatorio no recibió el recordatorio de WhatsApp', 'asunto: ' . $c['subject']);
$fecha_vista = DateTime::createFromFormat('Y-m-d', $manana)->format('d-m-Y');
ok(strpos($c['message'], 'el recordatorio de 24 horas de su cita') !== false, 'dice qué recordatorio');
ok(strpos($c['message'], $fecha_vista . ' a las 10:30') !== false, 'trae la fecha y la hora de la cita');
ok(strpos($c['message'], '+56 9 1234 5678') !== false, 'trae el teléfono para llamarlo');
ok(strpos($c['message'], esc_html(at_cc_motivo_whatsapp_no_entregado(131026, ''))) !== false, 'explica el motivo (131026)');
ok(strpos(html_entity_decode($c['message']), 'page=automatiza-leads-manager&action=edit&id=' . $lead_id . '"') !== false, 'enlaza la cita en el panel');
ok(strpos($c['message'], 'no se vuelve a mandar solo') !== false, 'aclara que no se reintenta');
ok(rec_total_notas_propuestas() === $notas_antes, 'no toca las notas de las propuestas');

// ---------- 6) El mismo 'failed' otra vez: no repite ----------
$correos = [];
$res = rec_estado([['wamid' => $w1, 'estado' => 'failed', 'codigo' => 131026]]);
ok($res->get_data()['avisados'] === 0 && $res->get_data()['ignorados'] === 1 && !$correos, 'el mismo failed otra vez: ya avisado, sin correo');

// ---------- 7) El 'failed' le gana a la anotación (reunión de las 8 AM) ----------
$w2 = rec_wamid($marca, '2', $wamids);
$correos = [];
$res = rec_estado([['wamid' => $w2, 'estado' => 'failed', 'codigo' => 131049, 'titulo' => 'healthy ecosystem']]);
ok($res->get_data()['ignorados'] === 1 && !$correos && is_array(get_transient(at_cc_clave_fallo_pendiente($w2))), 'failed antes de anotar: ignorado y en espera');
$res = rec_pedir($ruta, ['wamid' => $w2, 'tipo' => 'seguimiento_8am', 'id' => $reunion_id], AT_REST_SECRET);
ok($res->get_data() === ['ok' => true, 'fallo_avisado' => true], 'al anotar, el fallo en espera se avisa (fallo_avisado:true)');
ok(count($correos) === 1 && get_transient(at_cc_clave_fallo_pendiente($w2)) === false, 'un correo y sale de la espera');
$c = $correos[0] ?? ['subject' => '', 'message' => ''];
ok($c['subject'] === '[PRUEBA] Cliente Seguimiento no recibió el recordatorio de WhatsApp', 'asunto de la reunión: ' . $c['subject']);
ok(strpos($c['message'], '(Empresa Prueba)') !== false && strpos($c['message'], 'Reunión:</strong> ' . $fecha_vista . ' a las 16:00') !== false, 'trae la empresa y la hora de la reunión');
ok(strpos(html_entity_decode($c['message']), 'page=automatiza-followup&edit_id=' . $reunion_id . '"') !== false, 'enlaza la reunión en el panel');
$correos = [];
rec_estado([['wamid' => $w2, 'estado' => 'failed', 'codigo' => 131049]]);
ok(!$correos, 'y no se repite si Meta lo manda de nuevo');

// ---------- 8) Un wamid que no es de un recordatorio (p. ej. ARGOS): ignorado como antes ----------
$w3 = rec_wamid($marca, '3', $wamids);
$correos = [];
$res = rec_estado([['wamid' => $w3, 'estado' => 'failed', 'codigo' => 131047]]);
ok($res->get_data()['avisados'] === 0 && $res->get_data()['ignorados'] === 1 && !$correos, 'failed sin anotación: ignorado, sin correo (sin bucle con ARGOS)');

// ---------- 9) La cita ya no existe ----------
$w4 = rec_wamid($marca, '4', $wamids);
$sin_id = (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) FROM {$wpdb->prefix}automatiza_leads") + 1000;
rec_pedir($ruta, ['wamid' => $w4, 'tipo' => 'reunion_demo', 'id' => $sin_id], AT_REST_SECRET);
$correos = [];
rec_estado([['wamid' => $w4, 'estado' => 'failed', 'codigo' => 0]]);
$c = $correos[0] ?? ['subject' => '', 'message' => ''];
ok(count($correos) === 1 && $c['subject'] === "El cliente de la cita $sin_id no recibió el aviso de WhatsApp", 'cita borrada: igual avisa (' . $c['subject'] . ')');
ok(strpos($c['message'], "La cita $sin_id ya no está en la base") !== false && strpos($c['message'], 'href=') === false, 'dice que la cita ya no está y no enlaza nada');

// ---------- 10) Otra llamada lo está avisando (candado tomado) ----------
$w5 = rec_wamid($marca, '5', $wamids);
rec_pedir($ruta, ['wamid' => $w5, 'tipo' => 'cita_1h', 'id' => $lead_id], AT_REST_SECRET);
add_option('at_cc_wa_rec_fallo_' . md5($w5), '1', '', false);
$correos = [];
ok(at_cc_registrar_fallo_recordatorio(at_cc_envio_recordatorio_por_wamid($w5), $w5, 131026, '') === false && !$correos, 'con el candado tomado no manda otro correo');
delete_option('at_cc_wa_rec_fallo_' . md5($w5));
ok(at_cc_registrar_fallo_recordatorio(at_cc_envio_recordatorio_por_wamid($w5), $w5, 131026, '') === true && count($correos) === 1, 'suelto el candado, avisa una vez');
ok(get_option('at_cc_wa_rec_fallo_' . md5($w5)) === false, 'el candado se suelta al terminar');

// ---------- 11) Varios estados en una llamada ----------
$w6 = rec_wamid($marca, '6', $wamids);
$w7 = rec_wamid($marca, '7', $wamids);
rec_pedir($ruta, ['wamid' => $w6, 'tipo' => 'seguimiento_8pm', 'id' => $reunion_id], AT_REST_SECRET);
$correos = [];
$res = rec_estado([['wamid' => $w6, 'estado' => 'failed', 'codigo' => 131026], ['wamid' => $w7, 'estado' => 'failed', 'codigo' => 131026]]);
ok($res->get_data()['avisados'] === 1 && $res->get_data()['ignorados'] === 1 && count($correos) === 1, 'uno anotado y uno no: avisados 1, ignorados 1, un correo');

// ---------- 12) Textos por tipo (sin base) ----------
$fila = (object) ['nombre' => 'Ana', 'empresa' => '', 'telefono' => '+56 9 0000 0000', 'fecha' => '2026-10-01', 'hora' => '09:15:00'];
$t = at_cc_texto_recordatorio_no_entregado(['tipo' => 'reunion_prospecto', 'id' => 7], $fila, 'motivo', 'https://example.com');
ok($t['asunto'] === 'Ana no recibió el aviso de WhatsApp' && strpos($t['html'], 'el aviso de su reunión agendada') !== false, 'aviso de reunión: asunto y texto');
$t = at_cc_texto_recordatorio_no_entregado(['tipo' => 'cita_1h', 'id' => 7], $fila, 'motivo', 'https://example.com');
ok($t['asunto'] === 'Ana no recibió el recordatorio de WhatsApp' && strpos($t['html'], '01-10-2026 a las 09:15') !== false && strpos($t['html'], '()') === false, 'cita de 1 h: asunto, fecha y sin paréntesis vacíos');
$t = at_cc_texto_recordatorio_no_entregado(['tipo' => 'cita_72h', 'id' => 7], (object) ['nombre' => '<b>X</b>', 'empresa' => '', 'telefono' => '', 'fecha' => '2026-10-01', 'hora' => '09:15'], 'motivo', 'https://example.com');
ok(strpos($t['html'], '<b>X</b>') === false && strpos($t['html'], '&lt;b&gt;X&lt;/b&gt;') !== false, 'el nombre se escapa en el correo');
ok(count(at_cc_tipos_recordatorio()) === 8, 'ocho tipos: citas 72h/24h/1h, 8 AM, 8 PM y tres avisos de reunión');

// ---------- Limpieza ----------
foreach ($wamids as $w) {
	delete_transient(at_cc_clave_envio_recordatorio($w));
	delete_transient('at_cc_wa_rec_avisado_' . md5($w));
	delete_transient(at_cc_clave_fallo_pendiente($w));
	delete_option('at_cc_wa_rec_fallo_' . md5($w));
}
$wpdb->delete($wpdb->prefix . 'automatiza_leads', ['id' => $lead_id]);
$wpdb->delete($wpdb->prefix . 'automatiza_followup_meetings', ['id' => $reunion_id]);
fin();
