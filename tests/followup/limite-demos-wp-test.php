<?php
// Correr: AT_WP_LOAD=<ruta> php tests/followup/limite-demos-wp-test.php
// 05-oct-2026: las demos (bot del sitio web y bot de WhatsApp: /check-limit y POST /leads) aplicaban una regla
// distinta a la de las reuniones de seguimiento: contaban solo demos y también las canceladas. Ahora las tres vías
// usan automatiza_tech_limite_reuniones_activas(): 2 activas por correo sumando demos y seguimientos, sin canceladas.
// Se mantiene la excepción del correo de prueba de Luis.
require __DIR__ . '/../cierre/wp-bootstrap.php';
global $wpdb;
foreach (['automatiza_tech_limite_reuniones_activas', 'automatiza_tech_check_booking_limit', 'automatiza_tech_save_lead'] as $f) {
	if (!function_exists($f)) { fwrite(STDERR, "$f() no está definida.\n"); exit(2); }
}
$leads = $wpdb->prefix . 'automatiza_leads';
$reuniones = $wpdb->prefix . 'automatiza_followup_meetings';
$marca = 'ld' . substr(md5(uniqid('', true)), 0, 8);
$fecha = (new DateTimeImmutable(current_time('Y-m-d')))->modify('+10 days')->format('Y-m-d');
$creados = ['leads' => [], 'reuniones' => []];
register_shutdown_function(function () use (&$creados, $wpdb, $leads, $reuniones) {
	foreach ($creados['leads'] as $id) { $wpdb->delete($leads, ['id' => $id]); }
	foreach ($creados['reuniones'] as $id) { $wpdb->delete($reuniones, ['id' => $id]); }
	// Lo que haya creado POST /leads si dejara pasar (prueba en rojo): se borra por correo de prueba.
	$wpdb->query("DELETE FROM $leads WHERE email LIKE '%-ld%@example.com'");
});
// La prueba nunca sale a la red ni manda correos.
add_filter('pre_http_request', function () { return new WP_Error('prueba', 'HTTP bloqueado en la prueba'); }, 1);
add_filter('pre_wp_mail', '__return_false', 1);
function ld_demo(string $correo, string $hora, string $estado): void {
	global $wpdb, $leads, $creados, $fecha, $marca;
	$wpdb->insert($leads, ['created_at' => current_time('mysql'), 'name' => 'Prueba límite', 'email' => $correo, 'phone' => '+56900000000',
		'session_id' => $marca, 'token' => $marca . $hora, 'scheduled_date' => $fecha, 'scheduled_time' => $hora, 'status' => $estado]);
	$creados['leads'][] = (int) $wpdb->insert_id;
}
function ld_seguimiento(string $correo, string $hora): void {
	global $wpdb, $reuniones, $creados, $fecha;
	$wpdb->insert($reuniones, ['client_name' => 'Prueba límite', 'client_email' => $correo, 'phone' => '', 'meeting_date' => $fecha,
		'meeting_time' => $hora, 'meeting_subject' => 'Reunión de Seguimiento', 'notes' => 'prueba', 'status' => 'scheduled']);
	$creados['reuniones'][] = (int) $wpdb->insert_id;
}
function ld_check(string $correo): array {
	$r = new WP_REST_Request('POST', '/automatiza-tech/v1/check-limit');
	$r->set_header('Content-Type', 'application/json');
	$r->set_body(wp_json_encode(['email' => $correo]));
	return (array) automatiza_tech_check_booking_limit($r);
}
function ld_lead(string $correo): array {
	$r = new WP_REST_Request('POST', '/automatiza-tech/v1/leads');
	$r->set_header('Content-Type', 'application/json');
	$r->set_body(wp_json_encode(['name' => 'Prueba límite', 'email' => $correo, 'phone' => '+56900000000', 'scheduled_date' => $GLOBALS['fecha'], 'scheduled_time' => '15:00']));
	$res = automatiza_tech_save_lead($r);
	return is_wp_error($res) ? ['error' => $res->get_error_code(), 'mensaje' => $res->get_error_message(), 'status' => $res->get_error_data()['status'] ?? 0] : ['ok' => true];
}

// 1) Dos demos canceladas ya no bloquean (antes sí: la consulta no miraba el estado).
$c1 = "canceladas-$marca@example.com";
ld_demo($c1, '09:00:00', 'cancelled');
ld_demo($c1, '10:00:00', 'cancelled');
$r1 = ld_check($c1);
ok(($r1['allowed'] ?? null) === true, 'dos demos canceladas no cuentan: /check-limit permite agendar');

// 2) Una demo y un seguimiento activos sí bloquean (antes los seguimientos no contaban).
$c2 = "mixto-$marca@example.com";
ld_demo($c2, '11:00:00', 'active');
ld_seguimiento($c2, '12:00:00');
$r2 = ld_check($c2);
ok(($r2['allowed'] ?? null) === false, 'una demo y un seguimiento activos: /check-limit no permite una tercera');
ok(strpos((string) ($r2['message'] ?? ''), 'Ya tienes 2 reuniones activas (1 demo(s) y 1 seguimiento(s))') === 0, 'mismo mensaje que el bot de WhatsApp y la página del plan');
$antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $leads WHERE email = %s", $c2));
$l2 = ld_lead($c2);
ok(($l2['error'] ?? '') === 'email_limit_reached' && (int) ($l2['status'] ?? 0) === 400 && strpos((string) ($l2['mensaje'] ?? ''), 'Ya tienes 2 reuniones activas') === 0, 'POST /leads rechaza con el mismo mensaje (código de error de siempre)');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $leads WHERE email = %s", $c2)) === $antes, 'POST /leads rechazado no crea la demo');

// 3) Una sola reunión activa todavía permite otra.
$c3 = "una-$marca@example.com";
ld_seguimiento($c3, '13:00:00');
ok((ld_check($c3)['allowed'] ?? null) === true, 'con una reunión activa sí se puede agendar otra');

// 4) La excepción del correo de prueba de Luis se mantiene.
$rp = ld_check('lmgm.uber@gmail.com');
ok(($rp['allowed'] ?? null) === true && ($rp['message'] ?? '') === 'Email de prueba permitido', 'el correo de prueba de Luis sigue sin límite');

fin();
