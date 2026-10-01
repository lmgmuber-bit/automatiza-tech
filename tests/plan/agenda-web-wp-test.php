<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/agenda-web-wp-test.php
// Etapa 2, Task 4: la agenda web crea una reunión de SEGUIMIENTO (nunca una demo ni un lead), con token, límite por IP
// y una sola llamada futura por plan.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_agendar_seguimiento', 'at_pt_token_agenda_hoy');
global $wpdb;
$m = ptc_marca();
$reuniones = $wpdb->prefix . 'automatiza_followup_meetings';
$leads = $wpdb->prefix . 'automatiza_leads';

// Horario conocido (se restaura al terminar): lunes a viernes 09-18, sin feriados.
$horario_antes = get_option('automatiza_chat_schedule', null);
register_shutdown_function(function () use ($horario_antes) {
	$horario_antes === null ? delete_option('automatiza_chat_schedule') : update_option('automatiza_chat_schedule', $horario_antes);
});
$dias = [];
foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $d) { $dias[$d] = ['enabled' => true, 'start' => '09:00', 'end' => '18:00']; }
foreach (['saturday', 'sunday'] as $d) { $dias[$d] = ['enabled' => false, 'start' => '09:00', 'end' => '18:00']; }
update_option('automatiza_chat_schedule', $dias + ['holidays' => '']);
// Un día hábil de la semana que viene (siempre en el futuro y dentro de los 90 días).
$dia = new DateTimeImmutable(current_time('Y-m-d') . ' 00:00:00');
$dia = $dia->modify('+7 days');
while (in_array($dia->format('N'), ['6', '7'], true)) { $dia = $dia->modify('+1 day'); }
$fecha = $dia->format('Y-m-d');

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return $nulo; }, 10, 2);

/** Plan enviado de un cliente nuevo (con o sin correo). */
function plan_enviado(string $marca, string $correo = 'prueba-plan-agenda@example.com'): object {
	$c = ptc_cliente($marca, $correo);
	$plan = ptc_plan(ptc_contrato($c['tech'], $marca, null, 'servicios', 'signed', $correo));
	ptc_sembrar((int) $plan->id);
	at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html']);
	return at_pt_plan((int) $plan->id);
}
function pedir(object $plan, string $fecha, string $hora, string $ip, array $cambia = []): array {
	$r = at_pt_agendar_seguimiento($cambia + ['codigo' => (string) $plan->codigo, 'token' => at_pt_token_agenda_hoy((string) $plan->codigo), 'fecha' => $fecha, 'hora' => $hora, 'sitio_web' => ''], $ip);
	if (!empty($r['reunion_id'])) { $GLOBALS['ptc_creado']['reuniones'][] = (int) $r['reunion_id']; }
	return $r;
}
$ip = function (string $s) { return '203.0.113.' . (abs(crc32($s . $GLOBALS['m'])) % 250 + 1); };

$p = plan_enviado($m . 'a');
$leads_antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM $leads");
$correos = [];
$r = pedir($p, $fecha, '10:00', $ip('a'));
$fila = $r['reunion_id'] ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $reuniones WHERE id = %d", $r['reunion_id'])) : null;
ok($r['ok'] === true && $r['clave'] === 'agendada' && $r['estado_http'] === 200 && $fila !== null, 'agenda la llamada');
ok($fila && $fila->meeting_date === $fecha && substr((string) $fila->meeting_time, 0, 5) === '10:00' && $fila->status === 'scheduled', 'reunión con la fecha y la hora elegidas, programada');
ok($fila && strpos((string) $fila->meeting_subject, 'Seguimiento del plan de trabajo') === 0 && strpos((string) $fila->notes, 'Plan de trabajo ' . $p->codigo) === 0 && $fila->client_email === 'prueba-plan-agenda@example.com', 'decisión 2: tipo fijo de seguimiento, datos del cliente desde el plan');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM $leads") === $leads_antes, 'decisión 2: ninguna demo ni lead');
ok(strpos($r['mensaje'], at_pt_fecha_larga($fecha)) !== false && strpos($r['mensaje'], '10:00') !== false, 'el mensaje dice el día y la hora');
$a_luis = array_values(array_filter($correos, function ($c) { return strpos((string) $c['subject'], 'agendó la llamada de seguimiento') !== false; }));
ok(count($a_luis) === 1, 'aviso a Luis por correo');

// Review Focus 1: una sola llamada futura por plan
$r2 = pedir($p, $fecha, '15:00', $ip('a2'));
ok($r2['ok'] === false && $r2['clave'] === 'ya_agendada' && $r2['estado_http'] === 409 && strpos($r2['mensaje'], at_pt_fecha_larga($fecha)) !== false, 'Review Focus 1: la segunda vez dice cuándo es la que ya tiene');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $reuniones WHERE notes LIKE %s", 'Plan de trabajo ' . $wpdb->esc_like((string) $p->codigo) . '%')) === 1, 'queda una sola reunión');

// Review Focus 2: la hora dejó de servir
$q = plan_enviado($m . 'b');
$rq = pedir($q, $fecha, '10:00', $ip('b'));
ok($rq['ok'] === false && $rq['clave'] === 'hora_ocupada' && $rq['estado_http'] === 409, 'Review Focus 2: hora tomada por otra reunión');
ok(pedir($q, $fecha, '08:00', $ip('b2'))['clave'] === 'fuera_de_horario', 'fuera de horario');
ok(pedir($q, '2020-01-06', '10:00', $ip('b3'))['clave'] === 'pasada', 'día pasado');
$sabado = $dia; while ($sabado->format('N') !== '6') { $sabado = $sabado->modify('+1 day'); }
ok(pedir($q, $sabado->format('Y-m-d'), '10:00', $ip('b4'))['clave'] === 'dia_no_disponible', 'día que no se atiende');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $reuniones WHERE notes LIKE %s", 'Plan de trabajo ' . $wpdb->esc_like((string) $q->codigo) . '%')) === 0, 'ninguno de esos intentos creó una reunión');

// Seguridad: token, trampa, estado del plan, código
ok(pedir($q, $fecha, '11:00', $ip('c'), ['token' => str_repeat('a', 24)])['clave'] === 'sesion_vencida', 'token que no es del plan: sesión vencida (403)');
ok(pedir($q, $fecha, '11:00', $ip('c2'), ['sitio_web' => 'spam'])['ok'] === false, 'campo trampa lleno: no agenda');
$borrador = plan_enviado($m . 'd');
ptc_estado((int) $borrador->id, 'listo');
$rb = pedir($borrador, $fecha, '11:00', $ip('d'));
ok($rb['clave'] === 'plan_no_disponible' && $rb['estado_http'] === 404, 'Review Focus 5: un plan que no está enviado no agenda (404)');
$ri = at_pt_agendar_seguimiento(['codigo' => 'NoExiste1234', 'token' => '', 'fecha' => $fecha, 'hora' => '11:00'], $ip('d2'));
ok($ri['clave'] === 'plan_no_disponible' && $ri['estado_http'] === 404, 'Review Focus 5: código inventado (404)');
ok(at_pt_agendar_seguimiento(['codigo' => ['x'], 'fecha' => $fecha], $ip('d3'))['estado_http'] === 404, 'entrada con forma rara: 404 sin errores');

// Review Focus 3: sin correo
$s = plan_enviado($m . 'e', '');
$rs = pedir($s, $fecha, '12:00', $ip('e'));
ok($rs['clave'] === 'sin_correo' && $rs['estado_http'] === 409, 'Review Focus 3: sin correo pide escribir por WhatsApp');

// Límite por IP
$l = plan_enviado($m . 'f');
$misma = $ip('limite');
$claves = [];
for ($i = 0; $i < AT_PT_AGENDA_INTENTOS_HORA + 1; $i++) { $claves[] = pedir($l, $fecha, '08:00', $misma)['clave']; }
ok(end($claves) === 'muchos_intentos' && count(array_filter($claves, function ($c) { return $c === 'fuera_de_horario'; })) === AT_PT_AGENDA_INTENTOS_HORA, 'el sexto intento de la misma IP en una hora se rechaza (429)');
ok(pedir($l, $fecha, '13:00', $ip('otra'))['ok'] === true, 'otra IP sí puede');

// Candado: si otra conexión tiene el candado de la agenda, no se agenda nada y se responde 503; al soltarlo, sí.
$otra = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$fila_lock = $otra->query("SELECT GET_LOCK('" . AT_PT_AGENDA_LOCK . "', 0)")->fetch_row();
ok($fila_lock[0] === '1', 'preparación: la segunda conexión toma el candado de la agenda');
$k = plan_enviado($m . 'g');
$t0 = microtime(true);
$rk = pedir($k, $fecha, '14:00', $ip('g'));
$espera = microtime(true) - $t0;
ok($rk['ok'] === false && $rk['clave'] === 'no_guardo' && $rk['estado_http'] === 503, 'candado tomado por otro: 503 no_guardo');
ok($espera >= AT_PT_AGENDA_ESPERA_LOCK - 0.5, 'esperó el candado antes de rendirse');
$cuenta_k = function () use ($wpdb, $reuniones, $k) { return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $reuniones WHERE notes LIKE %s", 'Plan de trabajo ' . $wpdb->esc_like((string) $k->codigo) . '%')); };
ok($cuenta_k() === 0, 'con el candado tomado no se insertó ninguna reunión');
$otra->query("SELECT RELEASE_LOCK('" . AT_PT_AGENDA_LOCK . "')");
$rk2 = pedir($k, $fecha, '14:00', $ip('g2'));
ok($rk2['ok'] === true && $rk2['clave'] === 'agendada' && $cuenta_k() === 1, 'soltado el candado, la misma petición agenda');
$libre = $wpdb->get_var("SELECT IS_FREE_LOCK('" . AT_PT_AGENDA_LOCK . "')");
ok((string) $libre === '1', 'el candado queda libre después de agendar');
$otra->close();

// La ruta REST existe, es pública y devuelve el estado HTTP
$req = new WP_REST_Request('POST', '/automatiza-tech/v1/plan-seguimiento');
$req->set_header('Content-Type', 'application/json');
$req->set_body(wp_json_encode(['codigo' => 'NoExiste1234', 'token' => '', 'fecha' => $fecha, 'hora' => '10:00']));
$res = rest_do_request($req);
ok($res->get_status() === 404 && ($res->get_data()['ok'] ?? null) === false && ($res->get_data()['mensaje'] ?? '') !== '', 'POST /plan-seguimiento: 404 con mensaje para un código inventado');

fin();
