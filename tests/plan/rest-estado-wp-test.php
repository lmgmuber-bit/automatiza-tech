<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/rest-estado-wp-test.php
// Estado del proyecto, Etapa 2 — Task 5: GET /plan/{id}/render&modo=estado, POST /plan/{id}/vista con modo estado,
// at_av_pedir_render, at_av_refrescar y at_av_vista_publica (Review Focus 2 y 5).
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_rest_render_estado', 'at_av_rest_vista_estado', 'at_av_pedir_render', 'at_av_refrescar', 'at_av_vista_publica', 'at_av_url_render_valida', 'at_av_token_previa', 'at_pt_uid_propuesta');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba.\n");
	exit(2);
}
at_av_migrar_esquema();
if (!at_av_columna_lista()) {
	fwrite(STDERR, "La columna avance no está lista en el sitio de prueba.\n");
	exit(2);
}
function pedir(string $metodo, string $ruta, $cuerpo = null, array $query = []): WP_REST_Response {
	$r = new WP_REST_Request($metodo, '/automatiza-tech/v1' . $ruta);
	$r->set_header('x-at-secret', AT_REST_SECRET);
	if ($query) {
		$r->set_query_params($query);
	}
	if ($cuerpo !== null) {
		$r->set_header('content-type', 'application/json');
		$r->set_body(wp_json_encode($cuerpo));
	}
	return rest_do_request($r);
}
/** Importa y aplica un cierre (solo cifras y enlaces: sin archivos que subir). */
function con_cierre(array $avance, array $plan, string $codigo, string $titulo, string $bloque): array {
	$d = ['formato' => 'at-cierre-v1', 'plan' => $codigo, 'cierre' => ['titulo' => $titulo, 'bloques' => [$bloque], 'fecha_real' => '2026-10-10',
		'antes' => ['texto' => 'Antes de ' . $titulo, 'evidencias' => ['c']], 'despues' => ['texto' => 'Después.', 'evidencias' => ['e']]],
		'evidencias' => [['ref' => 'c', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4', 'fecha' => '2026-10-09'],
			['ref' => 'e', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/']]];
	$v = at_av_validar_import($d, $codigo, $plan, $avance);
	if (!$v['ok']) {
		fwrite(STDERR, 'El cierre de prueba no valida: ' . implode(' | ', $v['errores']) . "\n");
		exit(2);
	}
	return at_av_aplicar_import($avance, $v, 'c' . substr(md5($titulo), 0, 6), '2026-10-13 10:00:00');
}

$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-rest-estado@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-rest-estado@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
$f = at_pt_plan($id);
$cod = (string) $f->codigo;
$payload = at_pt_payload($f);

ok(pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado'])->get_status() === 409, '1) plan que no está enviado: 409');
ptc_estado($id, 'enviado');
at_pt_guardar($id, ['view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $cod . '/index.html']);
$vista_plan = (string) at_pt_plan($id)->view_url;
$r = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado']);
$d = $r->get_data();
ok($r->get_status() === 200 && array_keys($d) === ['ok', 'render', 'plan_codigo', 'propuesta_uid', 'crm_cliente_id', 'estado'], '2) enviado: 200 con render, código del plan, propuesta, ficha y estado');
ok(($d['render']['document_type'] ?? '') === 'estado' && ($d['render']['unique_id'] ?? '') === $cod . '-estado' && ($d['render']['cierres'] ?? null) === [] && $d['plan_codigo'] === $cod, '2) sin cierres: cuerpo de estado vacío de cierres');
ok(pedir('GET', "/plan/{$id}/render", null, ['modo' => 'draft'])->get_data()['render']['document_type'] === 'plan', '2) el render del plan sigue igual');
ok(pedir('GET', "/plan/{$id}/render", null, ['modo' => 'otro'])->get_status() === 400, '2) modo desconocido: 400');

$a = con_cierre(at_av_vacio(), $payload, $cod, 'Borrador secreto', 'Desarrollo')['avance'];
$a = con_cierre($a, $payload, $cod, 'Diseño terminado', 'Diseño')['avance'];
$id_pub = $a['cierres'][1]['id'];
$a = at_av_cambiar_estado_cierre($a, $id_pub, 'publicado', '2026-10-13 11:00:00');
ok(is_array($a) && at_av_guardar($id, $a), '3) avance con un borrador y un publicado');
$d = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado'])->get_data();
ok(array_column($d['render']['cierres'], 'titulo') === ['Diseño terminado'] && !str_contains((string) wp_json_encode($d), 'Borrador secreto'), '3) el cuerpo público no trae el borrador (Review Focus 2)');
$d = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado', 'previa' => '1'])->get_data();
ok(count($d['render']['cierres']) === 2 && $d['render']['unique_id'] === $cod . '-previa-' . at_av_token_previa($cod) && $d['render']['draft'] === true, '3) la vista previa trae los dos, en su ruta con token (R3)');
ok(preg_match('/^[0-9a-f]{10}$/', at_av_token_previa($cod)) && at_av_token_previa($cod) === at_av_token_previa($cod), '3) token de 10, fijo por plan');
// F2: el entregable de un cierre solo se enlaza si es del mismo cliente del plan (spec §9).
add_filter('home_url', fn() => 'https://example.com');
$otro = ptc_cliente($m, 'prueba-rest-estado-otro@example.com');
$mk_ent = function (int $tech, string $titulo): array {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $tech, 'detail_type' => 'entregable', 'title' => $titulo, 'status' => 'completed', 'attachment_url' => 'https://example.com/v1', 'created_by' => 1]);
	$det = (int) $wpdb->insert_id;
	$eid = at_en_activar($det);
	return [$det, is_int($eid) ? $eid : 0];
};
[$det_mio, $ent_mio] = $mk_ent($c['tech'], '[PRUEBA] Entregable propio');
[$det_otro, $ent_otro] = $mk_ent($otro['tech'], '[PRUEBA] Entregable ajeno');
register_shutdown_function(function () use ($ent_mio, $ent_otro, $det_mio, $det_otro) {
	global $wpdb;
	foreach ([$ent_mio, $ent_otro] as $e) {
		$wpdb->delete(at_en_tablas()['e'], ['id' => $e]);
	}
	foreach ([$det_mio, $det_otro] as $dd) {
		$wpdb->delete($wpdb->prefix . 'automatiza_clients_details', ['id' => $dd]);
	}
});
ok($ent_mio > 0 && $ent_otro > 0, '3b) entregables de prueba creados (uno del cliente del plan, otro de otro cliente)');
$f_plan = at_pt_plan($id);
$mio = at_av_entregable_del_plan($f_plan, $ent_mio);
ok($mio !== '' && str_starts_with($mio, 'https://example.com/ver-entregable.php?id='), '3b) el entregable del mismo cliente da su URL pública');
ok(at_av_entregable_del_plan($f_plan, $ent_otro) === '' && at_av_entregable_del_plan($f_plan, 999999991) === '' && at_av_entregable_del_plan($f_plan, 0) === '', '3b) el de otro cliente, uno que no existe y 0 dan vacío');
$a_orig = at_av_leer(at_pt_plan($id));
$a_ent = $a_orig;
$a_ent['cierres'][1]['entregable_id'] = $ent_otro;
at_av_guardar($id, $a_ent);
$d = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado'])->get_data();
ok(($d['render']['cierres'][0]['entregable_url'] ?? 'x') === '', '3b) un cierre publicado con el entregable de OTRO cliente: entregable_url vacío');
$a_ent['cierres'][1]['entregable_id'] = $ent_mio;
at_av_guardar($id, $a_ent);
$d = pedir('GET', "/plan/{$id}/render", null, ['modo' => 'estado'])->get_data();
ok(($d['render']['cierres'][0]['entregable_url'] ?? '') === $mio, '3b) con el entregable del mismo cliente: entregable_url es su enlace');
at_av_guardar($id, $a_orig);

$vista = 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-estado/index.html';
$pdf = 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-estado/presentation.pdf';
$r = pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'ok' => true, 'view_url' => $vista, 'pdf_url' => $pdf, 'nota' => 'Estado del proyecto actualizado']);
$l = at_av_leer(at_pt_plan($id));
ok($r->get_status() === 200 && $l['render']['estado'] === 'listo' && $l['render']['view_url'] === $vista && $l['render']['pdf_url'] === $pdf && $l['render']['nota'] === 'Estado del proyecto actualizado', '4) /vista estado: guarda la presentación lista');
ok((string) at_pt_plan($id)->estado === 'enviado' && (string) at_pt_plan($id)->view_url === $vista_plan, '4) el plan sigue enviado y con su propia vista');
ok(at_av_vista_publica(at_pt_plan($id)) === $vista, '4) ver-estado mostrará esa presentación');
pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'ok' => false, 'nota' => 'el renderer no respondió (HTTP 502)']);
$l = at_av_leer(at_pt_plan($id));
ok($l['render']['estado'] === 'error' && $l['render']['view_url'] === $vista && str_contains($l['render']['nota'], '502') && at_av_vista_publica(at_pt_plan($id)) === $vista, '5) un render fallido conserva la última presentación lista (Review Focus 5)');
foreach (['https://evil.example/p/' . $cod . '-estado/index.html', 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-previa-' . at_av_token_previa($cod) . '/index.html', 'https://' . at_pt_host_renderer() . '/p/OTROplan0001-estado/index.html'] as $mala) {
	pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'ok' => true, 'view_url' => $mala]);
	ok(at_av_leer(at_pt_plan($id))['render']['view_url'] === $vista && at_av_leer(at_pt_plan($id))['render']['estado'] === 'error', '6) un enlace ajeno, de la vista previa o de otro plan no se guarda como público');
}
$prev = 'https://' . at_pt_host_renderer() . '/p/' . $cod . '-previa-' . at_av_token_previa($cod) . '/index.html';
pedir('POST', "/plan/{$id}/vista", ['modo' => 'estado', 'previa' => true, 'ok' => true, 'view_url' => $prev]);
$l = at_av_leer(at_pt_plan($id));
ok($l['render_previa']['estado'] === 'listo' && $l['render_previa']['view_url'] === $prev && $l['render']['view_url'] === $vista, '7) la vista previa va a su propio registro');

$solo_borrador = at_av_cambiar_estado_cierre($l, $id_pub, 'borrador', '2026-10-13 12:00:00');
at_av_guardar($id, $solo_borrador);
ok(at_av_vista_publica(at_pt_plan($id)) === '', '8) sin cierres publicados ver-estado no muestra la presentación aunque haya una lista (Review Focus 2)');
ok(at_av_refrescar($id) === '' && at_av_leer(at_pt_plan($id))['render']['estado'] === 'error', '8) sin publicados no se pide render (R4)');
at_av_guardar($id, at_av_cambiar_estado_cierre(at_av_leer(at_pt_plan($id)), $id_pub, 'publicado', '2026-10-13 12:05:00'));
// En esta prueba toda llamada HTTP sale cortada (fixtures-panel.php): es n8n caído.
$motivo = at_av_pedir_render($id);
$l = at_av_leer(at_pt_plan($id));
ok($motivo !== '' && $l['render']['estado'] === 'error' && str_starts_with($l['render']['nota'], 'No se pudo pedir la presentación') && $l['render']['view_url'] === $vista, '9) n8n caído: queda en error con el motivo y conserva la última presentación (Review Focus 5)');
ok(at_av_refrescar($id) !== '', '9) con publicados, refrescar sí pide el render');
fin();
