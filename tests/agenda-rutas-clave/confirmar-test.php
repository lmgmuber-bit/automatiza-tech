<?php
// php tests/agenda-rutas-clave/confirmar-test.php
// El GET de confirmar no confirma (solo muestra un boton que hace POST) y las paginas escapan los datos de la base.
if (getenv('ESCENARIO') !== false && getenv('ESCENARIO') !== '') {
	// ---- proceso hijo: ejecuta los manejadores reales con dobles ----
	require __DIR__ . '/dobles.php';
	class FakeWpdb {
		public $prefix = 'wp_'; public $updates = [];
		function prepare($q, ...$a) { return $q; }
		function get_row($q) { return (object) ['id' => 7, 'status' => 'scheduled', 'client_name' => '<script>alert(1)</script>Ana', 'client_email' => 'ana@x.test', 'company_name' => 'E', 'meeting_subject' => '<img src=x onerror=alert(2)>', 'meeting_date' => '2027-01-01', 'meeting_time' => '10:00:00', 'meet_link' => '<b>enlace</b>', 'google_event_id' => '']; }
		function update($t, $d, $w) { $this->updates[] = $d; return 1; }
	}
	$wpdb = new FakeWpdb();
	function get_bloginfo($k = '') { return $k === 'language' ? 'es"><script>x</script>' : 'Sitio'; }
	function esc_html($s) { return htmlspecialchars((string) $s); }
	function esc_attr($s) { return htmlspecialchars((string) $s); }
	function esc_url($s) { return htmlspecialchars((string) $s); }
	function current_time($t) { return '2027-01-01 00:00:00'; }
	function status_header($c) { echo "[HTTP $c]"; }
	function wp_mail() { return true; }
	function automatiza_tech_delete_google_calendar_event() {}
	require $tema . '/agenda-rutas-clave/puras.php';
	require $tema . '/agenda-rutas-clave/enlaces.php';
	$f = $tema . '/admin-followup-meetings.php';
	eval(extraer_funcion($f, 'automatiza_tech_render_followup_action_page'));
	$e = getenv('ESCENARIO');
	eval(extraer_funcion($f, $e === 'cancelar' ? 'automatiza_tech_followup_email_cancel' : 'automatiza_tech_followup_email_confirm'));
	register_shutdown_function(function () { echo "\nUPDATES=" . count($GLOBALS['wpdb']->updates) . "\n"; });
	$req = new WP_REST_Request(getenv('METODO') ?: 'GET', '/x', [], ['id' => 7]);
	($e === 'cancelar' ? 'automatiza_tech_followup_email_cancel' : 'automatiza_tech_followup_email_confirm')($req);
	exit;
}

require __DIR__ . '/dobles.php';
function correr(string $escenario, string $metodo): string {
	$env = array_merge(getenv() ?: [], ['ESCENARIO' => $escenario, 'METODO' => $metodo]);
	$p = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'error_log=' . sys_get_temp_dir() . '/at-agenda-confirmar.log', __FILE__], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, $env);
	$out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); proc_close($p);
	return $out;
}

echo "-- confirmar: GET no confirma\n";
$o = correr('confirmar', 'GET');
comprobar(strpos($o, 'UPDATES=0') !== false, 'GET no actualiza nada');
comprobar(strpos($o, '<form method="post"') !== false && strpos($o, 'Confirmar mi asistencia') !== false, 'GET muestra un boton que hace POST');
comprobar(preg_match('/action="[^"]*confirm\/7\?e=\d+&amp;t=[0-9a-f]{64}"/', $o) === 1, 'el boton apunta a una URL firmada');
echo "-- confirmar: POST confirma\n";
$o = correr('confirmar', 'POST');
comprobar(strpos($o, 'UPDATES=1') !== false, 'POST confirma');

echo "-- salida escapada\n";
foreach (['confirmar' => ['GET', 'POST'], 'cancelar' => ['GET', 'POST']] as $esc => $metodos) {
	foreach ($metodos as $m) {
		$o = correr($esc, $m);
		comprobar(strpos($o, '<script>') === false && strpos($o, '<img src=x') === false && strpos($o, '<b>enlace</b>') === false, "$esc $m: nombre, asunto y enlace de la base salen escapados");
		comprobar(strpos($o, '&lt;script&gt;alert(1)&lt;/script&gt;Ana') !== false, "$esc $m: el nombre aparece como texto");
	}
}
@unlink(sys_get_temp_dir() . '/at-agenda-confirmar.log');
cierre();
