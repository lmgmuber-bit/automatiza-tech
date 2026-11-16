<?php
// php tests/agenda-rutas-clave/citas-test.php
// mu-plugin de citas: la columna token no sale en ninguna respuesta, per_page tiene tope y send-email solo a correos conocidos.
require __DIR__ . '/dobles.php';
class WP_REST_Response { public $data; public $status; function __construct($d = null, $s = 200) { $this->data = $d; $this->status = $s; } }
function add_action() {}
function esc_html($s) { return htmlspecialchars((string) $s); }
function wp_kses_post($s) { return (string) $s; }
function esc_url_raw($u) { return (string) $u; }
function sanitize_email($e) { return trim((string) $e); }
function sanitize_text_field($s) { return trim((string) $s); }
function is_email($e) { return strpos((string) $e, '@') !== false; }
function current_time($t) { return '2027-01-01 00:00:00'; }
$GLOBALS['correos_enviados'] = [];
function wp_mail($to, $subject, $body, $headers) { $GLOBALS['correos_enviados'][] = $to; return true; }

class FakeWpdb3 {
	public $prefix = 'wp_'; public $sql = []; public $leads = ['cliente@x.test']; public $seguimientos = ['seg@x.test']; public $insert_id = 5;
	function prepare($q, ...$a) { return vsprintf(str_replace('%s', "'%s'", $q), $a); }
	function fila() { return (object) ['id' => 7, 'name' => 'Ana', 'email' => 'cliente@x.test', 'token' => 'valor-secreto-de-prueba', 'status' => 'active']; }
	function get_results($q) { $this->sql[] = $q; return [$this->fila(), $this->fila()]; }
	function get_row($q) { $this->sql[] = $q; return $this->fila(); }
	function get_var($q) {
		$this->sql[] = $q;
		if (preg_match("/LOWER\(email\) = LOWER\('([^']*)'\)/", $q, $m)) { return in_array(strtolower($m[1]), array_map('strtolower', $this->leads), true) ? 1 : 0; }
		if (preg_match("/LOWER\(client_email\) = LOWER\('([^']*)'\)/", $q, $m)) { return in_array(strtolower($m[1]), array_map('strtolower', $this->seguimientos), true) ? 1 : 0; }
		return 2;
	}
	function insert($t, $d) { return 1; }
	function update($t, $d, $w) { return 1; }
}
$wpdb = new FakeWpdb3();

$fuente = raiz() . '/wp-content/mu-plugins/api-appointments-management.php';
$src = file_get_contents($fuente);
comprobar(strpos($src, 'debug_search') === false && strpos($src, '/appointments/debug') === false, 'appointments/debug ya no existe en el archivo');
require $fuente;
$api = new AutomatizaTech_Appointments_API();
class Req2 extends WP_REST_Request { public $json = []; function get_json_params() { return $this->json; } function get_body() { return json_encode($this->json); } }

echo "-- la columna token no sale\n";
$r = $api->get_appointment(new Req2('GET', '/x', [], ['id' => 7]));
comprobar(!isset($r->data['data']->token) && strpos(json_encode($r->data), 'valor-secreto') === false, 'GET /appointments/{id}');
$r = $api->get_appointments(new Req2('GET', '/x', [], []));
comprobar(strpos(json_encode($r->data), 'valor-secreto') === false && count($r->data['data']) === 2, 'GET /appointments');
$r = $api->search_appointments(new Req2('GET', '/x', [], ['email' => 'cliente@x.test']));
comprobar(strpos(json_encode($r->data), 'valor-secreto') === false && $r->data['count'] >= 2, 'GET /appointments/search');
$r = $api->update_appointment(new Req2('PUT', '/x', [], ['id' => 7]));
comprobar(strpos(json_encode($r->data), 'valor-secreto') === false, 'PUT /appointments/{id}');
$q = new Req2('POST', '/x', [], []);
$q->json = ['name' => 'Ana', 'email' => 'a@x.test', 'scheduled_date' => '2027-01-01', 'scheduled_time' => '10:00'];
$r = $api->create_appointment($q);
comprobar(strpos(json_encode($r->data), 'valor-secreto') === false, 'POST /appointments');
comprobar(isset($r->data['data']->name), 'y el resto de la fila sigue');

echo "-- per_page\n";
foreach ([[500, 100], [0, 50], [-5, 50], [20, 20], ['abc', 50]] as [$pide, $espera]) {
	$wpdb->sql = [];
	$r = $api->get_appointments(new Req2('GET', '/x', [], ['per_page' => $pide, 'page' => 1]));
	comprobar($r->data['pagination']['per_page'] === $espera && strpos($wpdb->sql[0], "LIMIT $espera ") !== false, "per_page=$pide se usa como $espera");
}

echo "-- send-email\n";
function enviar($api, $to) { $q = new Req2('POST', '/send-email', [], []); $q->json = ['to' => $to, 'subject' => 'Hola', 'template' => 'default', 'data' => []]; return $api->send_email($q); }
$ini = ini_get('error_log'); $log = sys_get_temp_dir() . '/at-agenda-citas.log'; @unlink($log); ini_set('error_log', $log);
$r = enviar($api, 'desconocido@otro.test');
comprobar($r->status === 403 && $r->data['message'] === 'No autorizado.' && count($GLOBALS['correos_enviados']) === 0, 'direccion desconocida: 403 y 0 envios');
comprobar(strpos((string) @file_get_contents($log), 'desconocido@otro.test') === false && strpos((string) @file_get_contents($log), 'send-email rechazado') !== false, 'queda registrado sin el correo');
$r = enviar($api, 'CLIENTE@x.test');
comprobar($r->status === 200 && $GLOBALS['correos_enviados'] === ['CLIENTE@x.test'], 'correo de un lead (sin distinguir mayusculas): envia');
$r = enviar($api, 'Seg@X.test');
comprobar($r->status === 200 && count($GLOBALS['correos_enviados']) === 2, 'correo de una reunion de seguimiento: envia');
$r = enviar($api, '');
comprobar($r->status === 400 && count($GLOBALS['correos_enviados']) === 2, 'sin correo sigue respondiendo 400');
@unlink($log);

echo "-- permisos del plugin sin la funcion del tema (en este proceso aun no existe)\n";
comprobar(!function_exists('at_agenda_permiso_maquina'), 'la funcion del tema no esta cargada');
comprobar(AutomatizaTech_Appointments_API::permiso_maquina(new WP_REST_Request()) === true, 'aviso (por omision): deja pasar');
comprobar(AutomatizaTech_Appointments_API::permiso_siempre(new WP_REST_Request()) instanceof WP_Error, 'siempre: 401 aunque falte la funcion');
$GLOBALS['opciones']['at_agenda_auth_modo'] = 'exigir';
$x = AutomatizaTech_Appointments_API::permiso_maquina(new WP_REST_Request());
comprobar($x instanceof WP_Error && $x->data['status'] === 401, 'exigir: 401 si falta la funcion del tema');
cierre();
