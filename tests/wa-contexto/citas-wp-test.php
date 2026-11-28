<?php
// Citas futuras del número y ruta wa-citas (solo para n8n). Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/citas-wp-test.php
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
if (!defined('AT_REST_SECRET')) {
	define('AT_REST_SECRET', 'clave-de-prueba-wa-citas');
}
require_once __DIR__ . '/fallos-wpdb.php';

function wa_pedir_citas(string $tel, ?string $clave): WP_REST_Response {
	$r = new WP_REST_Request('GET', '/automatiza-tech/v1/wa-citas');
	$r->set_query_params(['phone' => $tel]);
	if ($clave !== null) {
		$r->set_header('X-AT-Secret', $clave);
	}
	return rest_ensure_response(rest_do_request($r));
}
$ids = function (array $citas) { return array_map(function ($c) { return $c['tipo'][0] . $c['id']; }, $citas); };

ok(wa_pedir_citas('56900000000', null)->get_status() === 401, 'sin clave → 401');
$vacio = wa_numero_libre();
$r = wa_pedir_citas('56' . $vacio, AT_REST_SECRET);
ok($r->get_status() === 200 && $r->get_data()['estado'] === 'ok' && $r->get_data()['citas'] === [], 'con clave y número sin citas → 200, citas vacías');

// Diagnóstico por teléfono con otro formato
$n = wa_numero_libre();
$l1 = wa_lead(wa_correo('d1'), wa_con_espacios($n), wa_dia(3), '10:00');
$c = at_wa_citas('56' . $n);
ok($ids($c) === ['d' . $l1], 'diagnóstico por teléfono con otro formato');

// Diagnóstico sin teléfono, por el correo de la ficha del número
$n2 = wa_numero_libre();
[$crm2, $tech2] = wa_cliente_crm('d2', wa_con_espacios($n2));
$l2 = wa_lead(wa_correo('d2'), '', wa_dia(4), '11:00');
ok(in_array('d' . $l2, $ids(at_wa_citas('+56' . $n2)), true), 'diagnóstico sin teléfono por correo de la ficha');

// Seguimiento sin teléfono, por el correo de la ficha
$s2 = wa_seguimiento(wa_correo('d2'), '', wa_dia(5), '15:00');
$c2 = at_wa_citas($n2);
ok(in_array('s' . $s2, $ids($c2), true), 'seguimiento por correo de la ficha, sin teléfono');
$filaSeg = array_values(array_filter($c2, function ($x) { return $x['tipo'] === 'seguimiento'; }))[0] ?? [];
ok(($filaSeg['empresa'] ?? '') === '[PRUEBA] Empresa' && ($filaSeg['correo'] ?? '') === wa_correo('d2'), 'el seguimiento trae empresa y correo para reprogramar');

// Una cita de otro número no aparece; cita_del_numero de otro número → null
$otro = wa_numero_libre();
$l3 = wa_lead(wa_correo('otro'), wa_con_espacios($otro), wa_dia(3), '12:00');
ok(!in_array('d' . $l3, $ids(at_wa_citas('56' . $n)), true), 'una cita de otro número no aparece');
ok(at_wa_cita_del_numero('56' . $n, 'diagnostico', $l3) === null, 'cita_del_numero de otro número → null');
ok((at_wa_cita_del_numero('56' . $n, 'diagnostico', $l1)['id'] ?? 0) === $l1, 'cita_del_numero propia → la cita');

// B-CITAS-08: 50 citas futuras canceladas no esconden una activa posterior
$n50 = wa_numero_libre();
for ($i = 0; $i < 50; $i++) {
	wa_lead(wa_correo('c50'), wa_con_espacios($n50), wa_dia(2), sprintf('%02d:%02d', 8 + intdiv($i, 6), ($i % 6) * 10), 'cancelled');
}
$activa = wa_lead(wa_correo('c50'), wa_con_espacios($n50), wa_dia(9), '10:00');
ok($ids(at_wa_citas('56' . $n50)) === ['d' . $activa], '50 canceladas antes no esconden la activa');

// Base caída → 503 no_disponible
foreach (['automatiza_leads', 'automatiza_followup_meetings'] as $tabla) {
	$r = con_falla($tabla, function () use ($n) { return wa_pedir_citas('56' . $n, AT_REST_SECRET); });
	ok($r->get_status() === 503 && $r->get_data()['estado'] === 'no_disponible' && $r->get_data()['citas'] === [], "si falla {$tabla}: 503 no_disponible");
}

fin();
