<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/rest-contexto-wp-test.php
// Task 7: las cinco rutas REST del plan, su clave y GET /plan/{id}/contexto.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_rest_rutas', 'at_pt_rest_contexto');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
add_filter('pre_option_at_pt_duraciones', function () { return ''; });

// 1) Rutas registradas con la clave de siempre.
$rutas = rest_get_server()->get_routes();
$bien = 0;
foreach (['contexto' => 'GET', 'borrador' => 'POST', 'render' => 'GET', 'vista' => 'POST', 'error' => 'POST'] as $ruta => $metodo) {
	$h = $rutas['/automatiza-tech/v1/plan/(?P<id>\d+)/' . $ruta][0] ?? null;
	if ($h && ($h['permission_callback'] ?? '') === 'automatiza_proposals_rest_auth' && !empty($h['methods'][$metodo])) {
		$bien++;
	}
}
ok($bien === 5, '1) las 5 rutas del plan existen, con su método y la clave X-AT-Secret (automatiza_proposals_rest_auth)');

// 2) Sin clave o con clave mala no entra nadie.
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], pt_propuesta($m, ['transcript' => str_repeat('Hablamos del sitio. ', 400)])));
ok(pt_pedir('GET', "/plan/{$pid}/contexto", null, false)->get_status() === 401, '2) sin clave: 401');
ok(pt_pedir('GET', "/plan/{$pid}/contexto", null, 'clave-mala')->get_status() === 403, '2) clave mala: 403');
ok(pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'x'], false)->get_status() === 401 && at_pt_plan($pid)->estado === 'generando', '2) sin clave nadie cambia el estado');

// 3) Contexto con propuesta.
$r = pt_pedir('GET', "/plan/{$pid}/contexto");
$d = $r->get_data();
ok($r->get_status() === 200 && $d === at_pt_contexto(at_pt_plan($pid)), '3) contexto: 200 y exactamente at_pt_contexto()');
ok(($d['codigo'] ?? '') === at_pt_plan($pid)->codigo && is_array($d['propuesta'] ?? null) && mb_strlen($d['propuesta']['extracto_reunion']) === 3000, '3) con el código del plan y el extracto de la reunión acotado a 3000 caracteres');
ok(pt_pedir('GET', '/plan/' . pt_plan_inexistente() . '/contexto')->get_status() === 404, '3) plan que no existe: 404');

// 4) Contrato sin propuesta (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$pid2 = at_pt_crear_plan(pt_contrato($cli2['tech'], null));
$d2 = pt_pedir('GET', "/plan/{$pid2}/contexto")->get_data();
ok(is_array($d2) && array_key_exists('propuesta', $d2) && $d2['propuesta'] === null && $d2['contrato']['alcance'] !== '', '4) sin propuesta: propuesta null y lo contratado igual llega');

fin();
