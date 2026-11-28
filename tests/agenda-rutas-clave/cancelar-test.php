<?php
// php tests/agenda-rutas-clave/cancelar-test.php
// El GET de cancelar (con o sin confirmed=1) no cancela; solo el POST. Enlaces vencidos o alterados no cancelan.
if (getenv('ESCENARIO') !== false && getenv('ESCENARIO') !== '') {
	// ---- proceso hijo: ejecuta el manejador real de cancelar con dobles ----
	require __DIR__ . '/dobles.php';
	class FakeWpdb {
		public $prefix = 'wp_'; public $updates = [];
		function prepare($q, ...$a) { return $q; }
		function get_row($q) { return (object) ['id' => 7, 'status' => 'scheduled', 'client_name' => 'Ana', 'client_email' => 'ana@x.test', 'company_name' => 'E', 'meeting_subject' => 'Tema', 'meeting_date' => '2027-01-01', 'meeting_time' => '10:00:00', 'google_event_id' => '']; }
		function update($t, $d, $w) { $this->updates[] = $d; return 1; }
	}
	$wpdb = new FakeWpdb();
	function get_bloginfo($k = '') { return 'Sitio'; }
	function esc_html($s) { return htmlspecialchars((string) $s); }
	function esc_attr($s) { return htmlspecialchars((string) $s); }
	function esc_url($s) { return htmlspecialchars((string) $s); }
	function current_time($t) { return '2027-01-01 00:00:00'; }
	function status_header($c) { echo "[HTTP $c]"; }
	function wp_mail() { echo '[MAIL]'; return true; }
	function automatiza_tech_delete_google_calendar_event() {}
	require $tema . '/agenda-rutas-clave/puras.php';
	require $tema . '/agenda-rutas-clave/enlaces.php';
	$f = $tema . '/admin-followup-meetings.php';
	eval(extraer_funcion($f, 'automatiza_tech_render_followup_action_page'));
	eval(extraer_funcion($f, 'automatiza_tech_followup_email_cancel'));

	$e = getenv('ESCENARIO');
	$params = json_decode((string) getenv('PARAMS'), true) ?: [];
	if (getenv('GRACIA')) { $GLOBALS['opciones']['at_agenda_enlaces_gracia_hasta'] = getenv('GRACIA'); }
	if (getenv('FIRMAR') === 'bien' || getenv('FIRMAR') === 'mal') {
		$vence = time() + 3600;
		$t = at_agenda_firma(7, 'cancel', $vence);
		if (getenv('FIRMAR') === 'mal') { $t = ($t[0] === 'a' ? 'b' : 'a') . substr($t, 1); }
		$params['e'] = (string) $vence; $params['t'] = $t;
	}
	register_shutdown_function(function () { echo "\nUPDATES=" . count($GLOBALS['wpdb']->updates) . ' ESTADO=' . ($GLOBALS['wpdb']->updates[0]['status'] ?? '-') . "\n"; });
	automatiza_tech_followup_email_cancel(new WP_REST_Request(getenv('METODO') ?: 'GET', '/x', [], array_merge(['id' => 7], $params)));
	exit;
}

require __DIR__ . '/dobles.php';
function correr(string $metodo, array $params = [], string $firmar = '', string $gracia = ''): string {
	$env = array_merge(getenv() ?: [], ['ESCENARIO' => '1', 'METODO' => $metodo, 'PARAMS' => json_encode($params), 'FIRMAR' => $firmar, 'GRACIA' => $gracia]);
	$p = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'error_log=' . sys_get_temp_dir() . '/at-agenda-cancelar.log', __FILE__], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, $env);
	$out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); proc_close($p);
	return $out;
}

echo "-- GET no cancela\n";
$o = correr('GET');
comprobar(strpos($o, 'UPDATES=0') !== false, 'GET sin parametros no cancela');
comprobar(strpos($o, '<form method="post"') !== false && strpos($o, 'cancelar definitivamente') !== false, 'GET muestra la confirmacion con un boton que hace POST');
comprobar(strpos($o, '[MAIL]') === false, 'GET no avisa a los administradores');
$o = correr('GET', ['confirmed' => '1']);
comprobar(strpos($o, 'UPDATES=0') !== false, 'GET con confirmed=1 tampoco cancela');
comprobar(strpos($o, '<form method="post"') !== false, 'GET con confirmed=1 sigue pidiendo confirmar');
$o = correr('GET', ['confirmed' => '1'], 'bien');
comprobar(strpos($o, 'UPDATES=0') !== false, 'GET firmado con confirmed=1 tampoco cancela');
comprobar(preg_match('/action="[^"]*cancel\/7\?e=\d+&amp;t=[0-9a-f]{64}"/', $o) === 1, 'el formulario apunta a una URL firmada');

echo "-- POST cancela\n";
$o = correr('POST');
comprobar(strpos($o, 'UPDATES=1 ESTADO=cancelled') !== false, 'POST cancela (en gracia, sin firma)');
comprobar(strpos($o, '[MAIL]') !== false, 'POST avisa a los administradores como antes');
$o = correr('POST', [], 'bien', '2020-01-01');
comprobar(strpos($o, 'UPDATES=1 ESTADO=cancelled') !== false, 'POST con firma buena cancela aunque la gracia haya pasado');

echo "-- enlaces que no sirven\n";
$o = correr('POST', [], '', '2020-01-01');
comprobar(strpos($o, 'UPDATES=0') !== false && strpos($o, 'Enlace vencido') !== false && strpos($o, '[HTTP 403]') !== false, 'pasada la gracia, sin firma no cancela y dice que el enlace vencio');
$o = correr('GET', [], '', '2020-01-01');
comprobar(strpos($o, 'UPDATES=0') !== false && strpos($o, 'Enlace vencido') !== false, 'pasada la gracia, el GET sin firma muestra enlace vencido');
$o = correr('POST', [], 'mal', '2020-01-01');
comprobar(strpos($o, 'UPDATES=0') !== false && strpos($o, 'Enlace vencido') !== false, 'firma alterada no cancela');
$o = correr('POST', [], 'mal');
comprobar(strpos($o, 'UPDATES=0') !== false, 'firma alterada no cancela ni siquiera durante la gracia');
@unlink(sys_get_temp_dir() . '/at-agenda-cancelar.log');
cierre();
