<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/rest-render-wp-test.php
// Task 7: GET /plan/{id}/render, POST /plan/{id}/vista y POST /plan/{id}/error.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_rest_render', 'at_pt_rest_vista', 'at_pt_rest_error');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
add_filter('pre_option_at_pt_duraciones', function () { return ''; });
add_filter('pre_option_automatiza_chat_schedule', function () { return ['holidays' => "2026-10-12\n"]; });
$GLOBALS['pt_http_respuesta'] = 200;

// 1) Render: cuerpo para el renderer (con propuesta y un contrato con 6 meses de garantía).
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], pt_propuesta($m), ['ph_mas' => ['garantia_meses_servicio' => '6']]));
ok(pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'draft'])->get_status() === 409, '1) sin contenido todavía: 409');
pt_pedir('POST', "/plan/{$pid}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$f = at_pt_plan($pid);
$r = pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'draft']);
$d = $r->get_data();
ok($r->get_status() === 200 && ($d['ok'] ?? null) === true, '1) render de la vista previa: 200');
ok(($d['render']['document_type'] ?? '') === 'plan' && ($d['render']['unique_id'] ?? '') === $f->codigo && ($d['render']['draft'] ?? null) === true && ($d['render']['image_briefs'] ?? null) === [], '1) tipo plan, código del plan, borrador y sin fotos');
// Se compara el JSON: el cuerpo puede traer objetos vacíos (images = {}) y dos objetos distintos nunca son ===.
ok(wp_json_encode($d['render'] ?? null) === wp_json_encode(at_pt_armar_render(at_pt_payload($f), at_pt_datos_render($f), false)), '1) es exactamente at_pt_armar_render() (mismo JSON)');
ok(($d['propuesta_uid'] ?? '') === substr(md5($m), 0, 12) && ($d['crm_cliente_id'] ?? null) === $cli['crm'], '1) con propuesta: su código (para reutilizar portada y cierre) y el cliente del CRM (botón del correo)');
ok(array_keys($d) === ['ok', 'render', 'propuesta_uid', 'crm_cliente_id', 'estado'] && ($d['estado'] ?? '') === 'borrador', '1) responde también el estado del plan (el flujo 3 no renderiza una vista previa de un plan «aprobando», «listo» o «enviado»)');
ok(($d['render']['company_name'] ?? '') === '[PRUEBA] Empresa ' . $m && ($d['render']['soporte']['garantia_meses'] ?? null) === 6, '1) el renderer recibe el nombre comercial de la propuesta y la garantía del contrato (6), no la del plan (3)');
$final = pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'final'])->get_data();
$slides = array_column($final['render']['image_briefs'] ?? [], 'slide');
ok(($final['render']['draft'] ?? null) === false && in_array('metodo', $slides, true) && in_array('gantt', $slides, true), '1) versión final: con las descripciones de las fotos');
ok(pt_pedir('GET', "/plan/{$pid}/render", null, true, ['modo' => 'otro'])->get_status() === 400 && pt_pedir('GET', '/plan/' . pt_plan_inexistente() . '/render', null, true, ['modo' => 'draft'])->get_status() === 404, '1) modo desconocido: 400; plan que no existe: 404');

// 2) Sin propuesta y cliente sin correo (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$pid2 = at_pt_crear_plan(pt_contrato($cli2['tech'], null));
pt_pedir('POST', "/plan/{$pid2}/borrador", ['plan' => pt_plan_ia(), 'origen' => 'borrador']);
$d2 = pt_pedir('GET', "/plan/{$pid2}/render", null, true, ['modo' => 'final'])->get_data();
$slides2 = array_column($d2['render']['image_briefs'] ?? [], 'slide');
ok(at_pt_plan($pid2)->estado === 'borrador', '2) sin propuesta y sin correo: el borrador igual se hace');
ok(($d2['propuesta_uid'] ?? null) === '' && ($d2['render']['portal_url'] ?? null) === '' && ($d2['crm_cliente_id'] ?? null) === $cli2['crm'], '2) sin código de propuesta ni enlace al portal («Sigue tu proyecto» va sin enlace)');
// Lo que WordPress decide aquí es propuesta_uid: con él n8n reutiliza portada y cierre (Task 14); sin él, no tiene de dónde.
ok(($final['propuesta_uid'] ?? '') !== '' && ($d2['propuesta_uid'] ?? null) === '' && in_array('cover', $slides2, true) && in_array('cierre', $slides2, true), '2) sin propuesta: la final pide todas las fotos (n8n no tiene de dónde reutilizar portada y cierre)');

// 3) Vista previa.
$base = 'https://render.ejemplo.test/p/' . $f->codigo;
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
$f = at_pt_plan($pid);
ok($r->get_status() === 200 && $f->view_url === $base . '/index.html' && $f->pdf_url === $base . '/presentation.pdf' && $f->estado === 'borrador', '3) vista previa lista: guarda los enlaces y el estado no cambia');
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 500']);
$f = at_pt_plan($pid);
ok($f->estado === 'borrador' && $f->view_url === $base . '/index.html' && strpos((string) $f->nota, 'renderer HTTP 500') !== false, '3) vista previa fallida: el estado no cambia, quedan los enlaces anteriores y la nota');
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => true, 'view_url' => 'javascript:alert(1)', 'pdf_url' => '', 'faltan' => [], 'nota' => '']);
ok(at_pt_plan($pid)->view_url === $base . '/index.html', '3) un enlace que no es http(s) no se guarda');
ok(pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'otro', 'ok' => true])->get_status() === 400, '3) modo desconocido: 400');

// 4) Versión final.
at_pt_guardar($pid, ['estado' => 'aprobando']);
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => ['gantt', 'fase_2'], 'nota' => '']);
$f = at_pt_plan($pid);
ok($f->estado === 'error' && strpos((string) $f->nota, 'gantt') !== false && strpos((string) $f->nota, 'fase_2') !== false, '4) versión final con fotos faltantes: «error» diciendo cuáles');
at_pt_guardar($pid, ['estado' => 'aprobando']);
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 502']);
ok(at_pt_plan($pid)->estado === 'error' && strpos((string) at_pt_plan($pid)->nota, 'renderer HTTP 502') !== false, '4) versión final fallida: «error» con el motivo');
at_pt_guardar($pid, ['estado' => 'aprobando']);
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
ok(at_pt_plan($pid)->estado === 'listo' && ($r->get_data()['estado'] ?? '') === 'listo' && at_pt_plan($pid)->nota === '', '4) versión final completa: «aprobando» pasa a «listo»');
// Una vista previa vieja que termina después de la versión final no la pisa (D10).
$vieja = 'https://render.ejemplo.test/p/' . $f->codigo . '/vieja.html';
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => true, 'view_url' => $vieja, 'pdf_url' => $vieja, 'faltan' => [], 'nota' => '']);
ok(($r->get_data()['estado'] ?? '') === 'listo' && at_pt_plan($pid)->view_url === $base . '/index.html' && at_pt_plan($pid)->pdf_url === $base . '/presentation.pdf' && at_pt_plan($pid)->nota === '', '4) plan «listo»: una vista previa que llega tarde no guarda sus enlaces');
at_pt_guardar($pid, ['estado' => 'aprobando']);
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'draft', 'ok' => false, 'view_url' => '', 'pdf_url' => '', 'faltan' => [], 'nota' => 'renderer HTTP 500']);
ok(at_pt_plan($pid)->estado === 'aprobando' && at_pt_plan($pid)->view_url === $base . '/index.html' && at_pt_plan($pid)->nota === '', '4) plan «aprobando»: el resultado de una vista previa vieja no se anota');
at_pt_guardar($pid, ['estado' => 'listo']);
// D18: la nota de una versión final completa (fotos revisadas, o el aviso de las que siguen con texto) se guarda, no se borra.
$revisadas = 'Fotos revisadas con GPT-4o: sin texto';
at_pt_guardar($pid, ['estado' => 'aprobando', 'nota' => '']);
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => $revisadas]);
ok(at_pt_plan($pid)->estado === 'listo' && ($r->get_data()['estado'] ?? '') === 'listo' && at_pt_plan($pid)->nota === $revisadas, '4) versión final completa con nota: «listo» y la nota queda guardada (D18)');
at_pt_guardar($pid, ['estado' => 'listo', 'nota' => '']);
$r = pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => $revisadas]);
ok(at_pt_plan($pid)->estado === 'listo' && at_pt_plan($pid)->nota === $revisadas, '4) versión final completa repetida con el plan ya «listo»: la nota también se guarda');
at_pt_guardar($pid, ['estado' => 'listo', 'nota' => '']);

// 5) n8n avisa un error.
at_pt_guardar($pid2, ['estado' => 'generando', 'nota' => '']);
$r = pt_pedir('POST', "/plan/{$pid2}/error", ['nota' => 'La IA no devolvió JSON <script>alert(1)</script>']);
$f2 = at_pt_plan($pid2);
ok($r->get_status() === 200 && $f2->estado === 'error' && strpos((string) $f2->nota, 'La IA no devolvió JSON') === 0 && strpos((string) $f2->nota, '<script') === false, '5) error desde n8n: «error» con la nota, sin HTML (Review Focus 2)');
pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'aviso tardío']);
ok(at_pt_plan($pid)->estado === 'listo' && at_pt_plan($pid)->nota === 'aviso tardío', '5) con el plan «listo», un error tardío solo deja la nota');
// Los flujos 1 y 2 llaman a /error ante cualquier respuesta que no sea 200 con ok, también ante el 409 de un borrador
// tardío: ese aviso no puede tumbar un borrador bueno ni un plan que espera su versión final.
at_pt_guardar($pid, ['estado' => 'borrador', 'nota' => '']);
pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'WordPress no guardó el plan (HTTP 409)']);
ok(at_pt_plan($pid)->estado === 'borrador' && strpos((string) at_pt_plan($pid)->nota, 'HTTP 409') !== false, '5) un borrador duplicado que llegó tarde (409) no tumba un borrador bueno: solo deja la nota');
at_pt_guardar($pid, ['estado' => 'aprobando', 'nota' => '']);
pt_pedir('POST', "/plan/{$pid}/error", ['nota' => 'WordPress no guardó el plan (HTTP 409)']);
$aprobando = at_pt_plan($pid)->estado;
pt_pedir('POST', "/plan/{$pid}/vista", ['modo' => 'final', 'ok' => true, 'view_url' => $base . '/index.html', 'pdf_url' => $base . '/presentation.pdf', 'faltan' => [], 'nota' => '']);
ok($aprobando === 'aprobando' && at_pt_plan($pid)->estado === 'listo', '5) ni un plan «aprobando»: sigue esperando y su versión final lo deja «listo»');
at_pt_guardar($pid2, ['estado' => 'cambios']);
pt_pedir('POST', "/plan/{$pid2}/error", []);
ok(at_pt_plan($pid2)->estado === 'error' && at_pt_plan($pid2)->nota === 'n8n avisó un error sin detalle.', '5) sin nota: un texto por defecto');
ok(pt_pedir('POST', '/plan/' . pt_plan_inexistente() . '/error', ['nota' => 'x'])->get_status() === 404, '5) plan que no existe: 404');

fin();
