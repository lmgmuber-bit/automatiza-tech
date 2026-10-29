<?php
// Cancelar una cita del número por WhatsApp (ruta wa-citas/cancelar, solo para n8n).
// Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/cancelar-wp-test.php
// Las llamadas al webhook de Google Calendar y los correos se interceptan: ninguna prueba toca Calendar ni manda correos.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
require_once __DIR__ . '/fallos-wpdb.php';
if (!defined('AT_REST_SECRET')) {
	define('AT_REST_SECRET', 'clave-de-prueba-wa-cancelar');
}

$GLOBALS['wa_http'] = [];
$GLOBALS['wa_mails'] = [];
add_filter('pre_http_request', function ($pre, $args, $url) {
	$GLOBALS['wa_http'][] = ['url' => $url, 'body' => (string) ($args['body'] ?? '')];
	return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, 10, 3);
add_filter('pre_wp_mail', function ($nulo, $atts) {
	$GLOBALS['wa_mails'][] = $atts;
	return true;
}, 10, 2);

function wa_cancelar(string $tel, string $tipo, int $id, ?string $clave, string $motivo = 'cancelada'): WP_REST_Response {
	$r = new WP_REST_Request('POST', '/automatiza-tech/v1/wa-citas/cancelar');
	$r->set_header('Content-Type', 'application/json');
	$r->set_body(wp_json_encode(['phone' => $tel, 'tipo' => $tipo, 'id' => $id, 'motivo' => $motivo]));
	if ($clave !== null) {
		$r->set_header('X-AT-Secret', $clave);
	}
	return rest_ensure_response(rest_do_request($r));
}
function wa_fila(string $tabla, int $id) {
	global $wpdb;
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$tabla} WHERE id = %d", $id));
}
function wa_borrar_evento_llamadas(): array {
	return array_values(array_filter($GLOBALS['wa_http'], function ($h) { return strpos($h['url'], 'delete-calendar-event') !== false; }));
}

$n = wa_numero_libre();
$tel = '56' . $n;
$l = wa_lead(wa_correo('c1'), wa_con_espacios($n), wa_dia(3), '10:00');
$s = wa_seguimiento(wa_correo('c1'), wa_con_espacios($n), wa_dia(4), '16:00');

ok(wa_cancelar($tel, 'diagnostico', $l, null)->get_status() === 401, 'sin clave → 401');

// Cancelar diagnóstico propio
$antes = (int) $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}automatiza_leads");
$r = wa_cancelar($tel, 'diagnostico', $l, AT_REST_SECRET);
$f = wa_fila('automatiza_leads', $l);
ok($r->get_status() === 200 && $r->get_data()['ok'] === true && ($r->get_data()['cita']['id'] ?? 0) === $l, 'cancelar diagnóstico propio → 200 ok');
ok($f->status === 'cancelled' && !empty($f->cancelled_at), 'el diagnóstico queda cancelled con cancelled_at');
$ev = wa_borrar_evento_llamadas();
ok(count($ev) === 1 && strpos($ev[0]['body'], $f->google_event_id) !== false, '1 llamada al webhook de borrar evento, con su id');
ok(count($GLOBALS['wa_mails']) === 2, '2 correos: a la persona y a Luis');
ok(in_array(wa_correo('c1'), (array) $GLOBALS['wa_mails'][0]['to'], true) || $GLOBALS['wa_mails'][0]['to'] === wa_correo('c1'), 'el primer correo va a la persona');
ok(strpos((string) $GLOBALS['wa_mails'][0]['subject'], 'cancelada') !== false, 'asunto de cancelada');
ok((int) $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}automatiza_leads") === $antes, 'la fila nunca se borra');

// Doble cancelación
$mails = count($GLOBALS['wa_mails']);
$r2 = wa_cancelar($tel, 'diagnostico', $l, AT_REST_SECRET);
ok($r2->get_status() === 409 && $r2->get_data()['motivo'] === 'no_vigente' && count($GLOBALS['wa_mails']) === $mails, 'doble cancelación → 409 no_vigente y un solo correo');

// Cancelar seguimiento propio, como reprogramada
$GLOBALS['wa_http'] = [];
$r3 = wa_cancelar($tel, 'seguimiento', $s, AT_REST_SECRET, 'reprogramada');
$fs = wa_fila('automatiza_followup_meetings', $s);
$ev = wa_borrar_evento_llamadas();
ok($r3->get_status() === 200 && $fs->status === 'cancelled', 'cancelar seguimiento propio → cancelled');
ok(count($ev) === 1 && strpos($ev[0]['body'], $fs->google_event_id) !== false, 'webhook con el google_event_id del seguimiento');
ok(strpos((string) end($GLOBALS['wa_mails'])['subject'], 'reprogram') !== false || strpos((string) $GLOBALS['wa_mails'][count($GLOBALS['wa_mails']) - 2]['subject'], 'reprogram') !== false, 'el motivo reprogramada se nota en el correo');

// Cita de otro número
$otro = wa_numero_libre();
$lo = wa_lead(wa_correo('otro'), wa_con_espacios($otro), wa_dia(3), '12:00');
$r4 = wa_cancelar($tel, 'diagnostico', $lo, AT_REST_SECRET);
ok($r4->get_status() === 409 && wa_fila('automatiza_leads', $lo)->status === 'active', 'cita de otro número → 409 no_vigente y la fila sigue activa');

// Tipo inválido
ok(wa_cancelar($tel, 'otra', $l, AT_REST_SECRET)->get_status() === 409, 'tipo inválido → 409');

// Base caída al revisar la cita → 503, nada cancelado
$l5 = wa_lead(wa_correo('c5'), wa_con_espacios($n), wa_dia(6), '10:00');
$r5 = con_falla('automatiza_followup_meetings', function () use ($tel, $l5) { return wa_cancelar($tel, 'diagnostico', $l5, AT_REST_SECRET); });
ok($r5->get_status() === 503 && $r5->get_data()['motivo'] === 'no_disponible' && wa_fila('automatiza_leads', $l5)->status === 'active', 'base caída → 503 no_disponible y la cita sigue activa');

fin();
