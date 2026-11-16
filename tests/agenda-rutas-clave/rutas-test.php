<?php
// php tests/agenda-rutas-clave/rutas-test.php
// Permiso de las rutas (aviso, siempre o publico) y enlaces firmados en los recordatorios por correo.
require __DIR__ . '/dobles.php';
eval(extraer_funcion($tema . '/cierre-cliente/whatsapp.php', 'at_cc_rest_auth'));
require $tema . '/agenda-rutas-clave/puras.php';

/** Devuelve ['ruta metodos' => maquina|siempre|publica] de todos los register_rest_route de un archivo. */
function rutas_de(string $archivo): array {
	$src = str_replace("\r\n", "\n", file_get_contents($archivo));
	$out = [];
	preg_match_all("/register_rest_route\('automatiza-tech\/v1', ('[^\n]*?'), array\((.*?)\n\s*\)\);/s", $src, $m, PREG_SET_ORDER);
	foreach ($m as $r) {
		$metodos = preg_match("/'methods' => (array\([^)]*\)|'[A-Z]+')/", $r[2], $mm) ? $mm[1] : '?';
		$perm = preg_match("/'permission_callback' => (function[^\n]*|'[a-z_]+'|array\([^)]*\))/", $r[2], $pp) ? $pp[1] : '?';
		$cat = strpos($perm, 'permiso_maquina') !== false ? 'maquina' : (strpos($perm, 'permiso_siempre') !== false ? 'siempre' : (strpos($perm, '__return_true') !== false ? 'publica' : $perm));
		$out[trim($r[1], "'") . ' ' . $metodos] = $cat;
	}
	return $out;
}

$api = rutas_de($tema . '/api-endpoints.php');
$citas = rutas_de(raiz() . '/wp-content/mu-plugins/api-appointments-management.php');
$agenda = rutas_de($tema . '/admin-followup-meetings.php');

$de_maquina_api = ['/leads/reminders(?:', '/leads/reminders-wa(?:', '/leads/reminders-8pm', '/leads/reminders-8am', '/leads/reminders-wa-8pm', '/leads/reminders-wa-8am',
	'/leads/update-reminder-daily/', '/leads/update-reminder-wa-daily/', '/leads/update-reminder/(?P', '/leads/update-reminder-wa/(?P', '/leads/confirm-attendance/', '/leads/check-event', 'mark-whatsapp-sent', "/leads 'POST'"];
$maquina = array_keys(array_filter($api, fn($v) => $v === 'maquina'));
foreach ($de_maquina_api as $frag) {
	$hit = array_filter($maquina, fn($k) => strpos($k, $frag) !== false);
	comprobar(count($hit) === 1, "api-endpoints (aviso): $frag", implode(' | ', $hit));
}
comprobar(count($maquina) === count($de_maquina_api), 'api-endpoints: solo esas rutas son de aviso (' . count($maquina) . ')', implode(' | ', $maquina));
$siempre_api = array_keys(array_filter($api, fn($v) => $v === 'siempre'));
comprobar(count($siempre_api) === 1 && strpos($siempre_api[0], '/leads/update-reminder ') === 0, 'api-endpoints (siempre): /leads/update-reminder sin id', implode(' | ', $siempre_api));
foreach (['/exchange-rate', '/check-availability', '/check-limit', '/leads/action', '/leads/reschedule'] as $r) {
	$hit = array_filter($api, fn($v, $k) => preg_match('#^' . preg_quote($r, '#') . ' #', $k), ARRAY_FILTER_USE_BOTH);
	comprobar($hit && count(array_filter($hit, fn($v) => $v !== 'publica')) === 0, "api-endpoints (publico): $r");
}

$m = array_keys(array_filter($citas, fn($v) => $v === 'maquina'));
comprobar(count($m) === 3 && count(array_filter($m, fn($k) => strpos($k, '/appointments/search') === 0)) === 1 && count(array_filter($m, fn($k) => strpos($k, "'DELETE'") !== false)) === 1 && count(array_filter($m, fn($k) => strpos($k, '/send-email') === 0)) === 1, 'citas (aviso): appointments/search, DELETE appointments/{id} y send-email', implode(' | ', $m));
$s = array_keys(array_filter($citas, fn($v) => $v === 'siempre'));
comprobar(count($s) === 5, 'citas (siempre): listar, ver, crear, PUT y PATCH', implode(' | ', $s));
comprobar(count(array_filter($citas, fn($v) => $v === 'publica')) === 0, 'citas: ninguna ruta queda publica', implode(' | ', array_keys($citas)));
comprobar(count(array_filter(array_keys($citas), fn($k) => strpos($k, 'debug') !== false)) === 0, 'citas: appointments/debug ya no existe');

$ag = array_filter($agenda, fn($v) => $v === 'maquina');
comprobar(count($ag) === 8, 'agenda (aviso): las 8 rutas de n8n de la fase 1 (' . count($ag) . ')');

echo "-- recordatorios por correo: enlaces firmados solo con credencial, y nada se quita\n";
class FakeWpdb2 { public $prefix = 'wp_'; function prepare($q, ...$a) { return $q; }
	function get_results($q) { return [(object) ['id' => 7, 'name' => 'Ana', 'email' => 'ana@x.test', 'company_name' => 'E', 'phone' => '+569', 'meeting_date' => '2027-01-01', 'meeting_time' => '10:00:00', 'meet_link' => '', 'meeting_subject' => 'T', 'status' => 'scheduled']]; } }
$wpdb = new FakeWpdb2();
function automatiza_tech_attach_recipient_metadata($m, $t) { $m->recipients = ['x']; }
$f = $tema . '/api-endpoints.php';
foreach (['automatiza_tech_get_leads_reminder_8am', 'automatiza_tech_get_leads_reminder_8pm'] as $fn) {
	eval(extraer_funcion($f, $fn));
	ini_set('error_log', sys_get_temp_dir() . '/at-agenda-rutas.log');
	foreach (['sin cabecera' => [], 'cabecera invalida' => ['x_at_secret' => 'otra-clave']] as $caso => $cab) {
		$sin = $fn(new WP_REST_Request('GET', '/x', $cab))[0];
		foreach (['confirm_url', 'reschedule_url', 'cancel_url'] as $k) { comprobar(!isset($sin->$k), "$fn, $caso: no agrega $k"); }
		comprobar(isset($sin->id) && isset($sin->email) && isset($sin->meet_link) && isset($sin->recipients), "$fn, $caso: responde como antes");
	}
	$r0 = $fn(new WP_REST_Request('GET', '/x', ['x_at_secret' => AT_REST_SECRET]))[0];
	foreach (['id', 'name', 'email', 'company_name', 'phone', 'meeting_date', 'meeting_time', 'meet_link', 'meeting_subject', 'scheduled_date', 'scheduled_time', 'recipients'] as $k) {
		comprobar(isset($r0->$k), "$fn conserva $k");
	}
	foreach (['confirm', 'reschedule', 'cancel'] as $a) {
		$u = $r0->{$a . '_url'} ?? '';
		comprobar(strpos($u, '/followup-meetings/email-action/' . $a . '/7?e=') !== false, "$fn con credencial agrega {$a}_url");
		parse_str((string) parse_url($u, PHP_URL_QUERY), $q);
		comprobar(!empty($q['t']) && at_agenda_evaluar_enlace(7, $a, $q['e'] ?? '', $q['t'], time())['estado'] === 'ok', "{$a}_url de $fn viene firmada y vale");
	}
}

echo "-- permiso 'siempre'\n";
@unlink(sys_get_temp_dir() . '/at-agenda-rutas.log');
$GLOBALS['opciones']['at_agenda_auth_modo'] = 'aviso';
comprobar(at_agenda_permiso_siempre(new WP_REST_Request('GET', '/x', ['x_at_secret' => AT_REST_SECRET])) === true, 'con la credencial pasa');
$n = at_agenda_permiso_siempre(new WP_REST_Request('GET', '/x', []));
comprobar($n instanceof WP_Error && $n->data['status'] === 401, 'sin cabecera responde 401 aunque el modo sea aviso');
$n = at_agenda_permiso_siempre(new WP_REST_Request('GET', '/x', ['x_at_secret' => 'otra-clave']));
comprobar($n instanceof WP_Error && $n->data['status'] === 401, 'con cabecera invalida responde 401');
comprobar(strpos((string) @file_get_contents(sys_get_temp_dir() . '/at-agenda-rutas.log'), 'otra-clave') === false, 'no registra el valor de la cabecera');
@unlink(sys_get_temp_dir() . '/at-agenda-rutas.log');
cierre();
