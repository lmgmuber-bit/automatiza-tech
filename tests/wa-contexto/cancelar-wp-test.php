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
$GLOBALS['wa_calendar_falla'] = false;
$GLOBALS['wa_mail_falla'] = false;
add_filter('pre_http_request', function ($pre, $args, $url) {
	$GLOBALS['wa_http'][] = ['url' => $url, 'body' => (string) ($args['body'] ?? '')];
	if ($GLOBALS['wa_calendar_falla'] && strpos($url, 'delete-calendar-event') !== false) {
		return new WP_Error('http_request_failed', 'calendar caído (simulado)');
	}
	return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, 10, 3);
add_filter('pre_wp_mail', function ($nulo, $atts) {
	$GLOBALS['wa_mails'][] = $atts;
	return !$GLOBALS['wa_mail_falla'];
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
ok(strpos((string) $GLOBALS['wa_mails'][0]['subject'], 'cancelado tu cita') !== false, 'asunto de cancelación (el correo de siempre)');
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

// B-CITAS-05: la transición a cancelada es atómica; el segundo que llega no gana ni repite efectos
$l6 = wa_lead(wa_correo('c6'), wa_con_espacios($n), wa_dia(7), '10:00');
ok(at_wa_cita_marcar_cancelada('diagnostico', $l6) === true, 'la primera transición gana');
ok(at_wa_cita_marcar_cancelada('diagnostico', $l6) === false, 'la segunda transición no gana (ya estaba cancelada)');
$s6 = wa_seguimiento(wa_correo('c6'), wa_con_espacios($n), wa_dia(7), '12:00');
ok(at_wa_cita_marcar_cancelada('seguimiento', $s6) === true && at_wa_cita_marcar_cancelada('seguimiento', $s6) === false, 'lo mismo en el seguimiento');

// B-CITAS-04: Calendar caído → cancelada en la base, pero el resultado lo dice y el aviso a Luis pide borrarlo a mano
$l7 = wa_lead(wa_correo('c7'), wa_con_espacios($n), wa_dia(8), '10:00');
$GLOBALS['wa_mails'] = [];
$GLOBALS['wa_calendar_falla'] = true;
$r7 = wa_cancelar($tel, 'diagnostico', $l7, AT_REST_SECRET);
$GLOBALS['wa_calendar_falla'] = false;
$e7 = $r7->get_data()['efectos'] ?? [];
ok($r7->get_status() === 200 && $r7->get_data()['ok'] === true && ($e7['calendario'] ?? null) === false && ($e7['correo'] ?? null) === true, 'Calendar caído → ok con efectos.calendario=false');
$aviso = end($GLOBALS['wa_mails']);
ok(strpos((string) $aviso['message'], 'Calendar') !== false && strpos((string) $aviso['message'], 'mano') !== false, 'el aviso a Luis dice que el evento quedó pendiente');

// B-CITAS-04: correo caído → efectos.correo=false (el bot no promete el correo)
$l8 = wa_lead(wa_correo('c8'), wa_con_espacios($n), wa_dia(8), '12:00');
$GLOBALS['wa_mail_falla'] = true;
$r8 = wa_cancelar($tel, 'diagnostico', $l8, AT_REST_SECRET);
$GLOBALS['wa_mail_falla'] = false;
ok($r8->get_data()['ok'] === true && ($r8->get_data()['efectos']['correo'] ?? null) === false, 'correo caído → efectos.correo=false');

// B-CITAS-06: la cita del seguimiento trae la nota y el asunto para reprogramar
$s9 = wa_seguimiento(wa_correo('c9'), wa_con_espacios($n), wa_dia(9), '15:00');
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->prefix . 'automatiza_followup_meetings', ['notes' => 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.'], ['id' => $s9]);
ok((at_wa_cita_del_numero($tel, 'seguimiento', $s9)['notas'] ?? '') === 'Plan de trabajo Ab3dE6gH9jK1. Agendada por el cliente por WhatsApp.', 'la cita del seguimiento trae su nota');

// Revisión R2-03: un error de la base al escribir no es «otro ya la canceló»: 503, la cita sigue activa y no hay efectos
class WaWpdbFallaEscritura {
	public $last_error = '';
	private $real;
	public function __construct($real) { $this->real = $real; }
	public function __get($n) { return $this->real->$n; }
	public function __call($m, $a) {
		if ($m === 'query' && stripos((string) ($a[0] ?? ''), 'UPDATE') === 0) {
			$this->last_error = 'falla simulada al escribir';
			return false;
		}
		$this->real->last_error = '';
		$r = $this->real->$m(...$a);
		$this->last_error = (string) $this->real->last_error;
		return $r;
	}
}
$l10 = wa_lead(wa_correo('c10'), wa_con_espacios($n), wa_dia(10), '10:00');
$GLOBALS['wa_mails'] = [];
$GLOBALS['wa_http'] = [];
$real = $GLOBALS['wpdb'];
$GLOBALS['wpdb'] = new WaWpdbFallaEscritura($real);
try {
	$r10 = wa_cancelar($tel, 'diagnostico', $l10, AT_REST_SECRET);
} finally {
	$GLOBALS['wpdb'] = $real;
}
ok($r10->get_status() === 503 && $r10->get_data()['motivo'] === 'no_disponible', 'falla al escribir → 503 no_disponible (no 409)');
ok(wa_fila('automatiza_leads', $l10)->status === 'active' && $GLOBALS['wa_mails'] === [] && wa_borrar_evento_llamadas() === [], 'la cita sigue activa y no hubo efectos');

// Pedido de Luis (30-oct): al cancelar se reusan los correos de cancelación que ya existían, con sus enlaces para volver a agendar
$correo_a = function (string $para) {
	foreach (array_reverse($GLOBALS['wa_mails']) as $m) {
		if ((array) $m['to'] === [$para] || $m['to'] === $para) {
			return $m;
		}
	}
	return null;
};
$GLOBALS['wa_mails'] = [];
$l11 = wa_lead(wa_correo('c11'), wa_con_espacios($n), wa_dia(11), '10:00');
wa_cancelar($tel, 'diagnostico', $l11, AT_REST_SECRET);
$m11 = $correo_a(wa_correo('c11'));
ok($m11 && $m11['subject'] === 'Lamentamos que hayas cancelado tu cita - AutomatizaTech', 'diagnóstico: el asunto del correo de cancelación de siempre');
ok($m11 && strpos((string) $m11['message'], 'Agendar Nueva Cita') !== false && strpos((string) $m11['message'], 'wa.me/56927002984') !== false, 'diagnóstico: trae el botón para agendar de nuevo por WhatsApp');
$GLOBALS['wa_mails'] = [];
$s12 = wa_seguimiento(wa_correo('c12'), wa_con_espacios($n), wa_dia(12), '16:30');
wa_cancelar($tel, 'seguimiento', $s12, AT_REST_SECRET);
$m12 = $correo_a(wa_correo('c12'));
ok($m12 && $m12['subject'] === '❌ Lamentamos la cancelación de tu reunión de seguimiento', 'seguimiento: el asunto del correo de cancelación de siempre');
ok($m12 && strpos((string) $m12['message'], 'reagendar%20mi%20reuni') !== false && strpos((string) $m12['message'], 'Ana Prueba') !== false
	&& strpos((string) $m12['message'], wa_dia(12)) !== false && strpos((string) $m12['message'], '{{') === false, 'seguimiento: trae el enlace para reagendar, el nombre y la fecha, sin expresiones de n8n');

// Base caída al revisar la cita → 503, nada cancelado
$l5 = wa_lead(wa_correo('c5'), wa_con_espacios($n), wa_dia(6), '10:00');
$r5 = con_falla('automatiza_followup_meetings', function () use ($tel, $l5) { return wa_cancelar($tel, 'diagnostico', $l5, AT_REST_SECRET); });
ok($r5->get_status() === 503 && $r5->get_data()['motivo'] === 'no_disponible' && wa_fila('automatiza_leads', $l5)->status === 'active', 'base caída → 503 no_disponible y la cita sigue activa');

fin();
