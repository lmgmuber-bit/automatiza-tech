<?php
// Correr: AT_WP_LOAD=<ruta> php tests/followup/verify-client-wp-test.php
// 01-oct-2026: el bot se caía al agendar una reunión por WhatsApp («Invalid JSON in response body» en
// el nodo Verify Client) desde el botón del plan de trabajo:
// - las propuestas guardan el teléfono como «+56 9 1234 5678» y la búsqueda con «56912345678» no calzaba;
// - sin propuesta, el respaldo en las citas pedía la columna `company`, que automatiza_leads no tiene, y
//   la consulta fallaba.
// La prueba enciende show_errors() para que cualquier falla de la base quede a la vista.
require __DIR__ . '/../cierre/wp-bootstrap.php';
global $wpdb;
if (!function_exists('automatiza_tech_verify_client_by_phone')) {
	fwrite(STDERR, "automatiza_tech_verify_client_by_phone() no está definida.\n");
	exit(2);
}
$wpdb->show_errors(true);

function vc_pedir(string $tel): array {
	global $wpdb;
	$wpdb->last_error = '';
	$r = new WP_REST_Request('GET', '/automatiza-tech/v1/verify-client');
	$r->set_query_params(['phone' => $tel]);
	ob_start();
	$res = rest_do_request($r);
	$impreso = (string) ob_get_clean();
	return ['data' => (array) $res->get_data(), 'status' => $res->get_status(), 'impreso' => $impreso, 'error_bd' => (string) $wpdb->last_error];
}

// Números al azar que no existen en la base local (últimos 9 dígitos únicos).
function vc_numero(): string {
	global $wpdb;
	do {
		$n = '9' . str_pad((string) wp_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
		$usado = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas WHERE phone LIKE %s", '%' . substr($n, -4) . '%'))
			+ (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_leads WHERE phone LIKE %s", '%' . substr($n, -4) . '%'));
	} while ($usado > 0);
	return $n;
}
function vc_con_espacios(string $n9): string {
	return '+56 ' . substr($n9, 0, 1) . ' ' . substr($n9, 1, 4) . ' ' . substr($n9, 5, 4);
}

$marca = 'prueba-vc-' . strtolower(wp_generate_password(6, false, false));
$propuestas = [];
$leads = [];
function vc_propuesta(string $marca, string $sufijo, string $phone, string $creada, array &$propuestas): array {
	global $wpdb;
	$codigo = substr(md5($marca . $sufijo . microtime(true)), 0, 12);
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $sufijo . '@example.com', 'unique_link_id' => $codigo,
		'client_name' => 'Cliente Prueba ' . $sufijo, 'company_name' => '[PRUEBA] Empresa ' . $sufijo, 'phone' => $phone,
		'status' => 'aceptada', 'flujo' => 'v3', 'gamma_prompt_text' => '{}', 'transcript_text' => '', 'system_prompt_text' => '',
		'created_at' => $creada,
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$propuestas[] = (int) $wpdb->insert_id;
	return ['id' => (int) $wpdb->insert_id, 'codigo' => $codigo];
}
function vc_lead(string $marca, string $sufijo, string $phone, array &$leads): int {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_leads', [
		'name' => '[PRUEBA] Prospecto ' . $sufijo, 'email' => $marca . '-' . $sufijo . '@example.com', 'phone' => $phone,
		'session_id' => '', 'source' => 'web', 'status' => 'pending', 'scheduled_date' => wp_date('Y-m-d'), 'scheduled_time' => '10:00:00',
		'token' => md5($marca . $sufijo), 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la cita de prueba: {$wpdb->last_error}\n"); exit(2); }
	$leads[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
}

// ---------- 1) Cliente con propuesta guardada con espacios (el caso del 01-oct) ----------
$n1 = vc_numero();
$p1 = vc_propuesta($marca, 'a', vc_con_espacios($n1), current_time('mysql'), $propuestas);
foreach (['56' . $n1, '+56' . $n1, $n1, vc_con_espacios($n1)] as $entrada) {
	$r = vc_pedir($entrada);
	ok($r['data']['is_client'] === true && $r['data']['client_type'] === 'proposal' && $r['data']['proposal_id'] === $p1['codigo'],
		'propuesta guardada «+56 9 XXXX XXXX» se encuentra con «' . preg_replace('/\d/', '9', $entrada) . '»');
	ok($r['impreso'] === '' && $r['error_bd'] === '', '   sin error de la base ni nada impreso antes del JSON');
}

// ---------- 2) Sin propuesta, con cita (demo): la consulta de citas ya no pide `company` ----------
$n2 = vc_numero();
$l2 = vc_lead($marca, 'b', vc_con_espacios($n2), $leads);
$r = vc_pedir('56' . $n2);
ok($r['impreso'] === '' && $r['error_bd'] === '', 'cita sin propuesta: sin error de la base (antes: Unknown column company)');
ok(($r['data']['is_client'] ?? null) === true && ($r['data']['client_type'] ?? '') === 'lead' && ($r['data']['company_name'] ?? null) === '',
	'cita sin propuesta: se reconoce como prospecto y company_name viene vacío');

// ---------- 3) Número desconocido: el caso que tumbaba al bot ----------
$r = vc_pedir('56' . vc_numero());
ok($r['impreso'] === '' && $r['error_bd'] === '', 'número desconocido: sin error de la base ni HTML antes del JSON');
ok(($r['data']['is_client'] ?? null) === false && ($r['data']['client_type'] ?? '') === 'none', 'número desconocido: no es cliente');
ok(json_decode((string) wp_json_encode($r['data']), true) === $r['data'], 'la respuesta se serializa como JSON válido');

// ---------- 4) Entradas cortas o raras ----------
foreach (['123', '', 'hola', '+56 9'] as $entrada) {
	$r = vc_pedir($entrada);
	ok(($r['data']['is_client'] ?? true) !== true && $r['impreso'] === '', 'entrada «' . $entrada . '»: no es cliente y no imprime errores');
}

// ---------- 5) Con propuesta y cita, gana la propuesta; con dos propuestas, la más reciente ----------
$n5 = vc_numero();
$vieja = vc_propuesta($marca, 'c-vieja', '+56' . $n5, wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS), $propuestas);
$nueva = vc_propuesta($marca, 'c-nueva', vc_con_espacios($n5), current_time('mysql'), $propuestas);
vc_lead($marca, 'c', '56' . $n5, $leads);
$r = vc_pedir('56' . $n5);
ok(($r['data']['client_type'] ?? '') === 'proposal' && ($r['data']['proposal_id'] ?? '') === $nueva['codigo'], 'propuesta antes que cita, y la más reciente de dos');

// ---------- 6) No confunde números que comparten el final de 8 dígitos ----------
$n6 = vc_numero();
vc_propuesta($marca, 'd', '+56' . $n6, current_time('mysql'), $propuestas);
$otro = '8' . substr($n6, 1);
$r = vc_pedir('56' . $otro);
ok(($r['data']['is_client'] ?? null) === false, 'compara los 9 últimos dígitos completos: «8…» no calza con «9…»');

// ---------- Limpieza ----------
foreach ($propuestas as $id) {
	$wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $id]);
}
foreach ($leads as $id) {
	$wpdb->delete($wpdb->prefix . 'automatiza_leads', ['id' => $id]);
}
fin();
