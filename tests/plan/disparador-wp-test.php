<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/disparador-wp-test.php
// Task 6: avisos WordPress → n8n (sin red: pre_http_request los anota y responde lo que diga la prueba).
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_llamar_n8n', 'at_pt_iniciar_borrador', 'at_pt_pedir_render');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
$caido = new WP_Error('http_request_failed', 'cURL error 7: Failed to connect');

// 1) URL de los tres flujos (sobrescribibles en wp-config.php).
ok(preg_match('#/plan-v1-borrador$#', AT_N8N_PLAN_BORRADOR) && preg_match('#/plan-v1-cambios$#', AT_N8N_PLAN_CAMBIOS) && preg_match('#/plan-v1-render$#', AT_N8N_PLAN_RENDER), '1) constantes de los webhooks plan-v1-borrador, plan-v1-cambios y plan-v1-render');

// 2) at_pt_llamar_n8n().
$GLOBALS['pt_http_respuesta'] = 200;
$motivo = at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7, 'modo' => 'draft', 'aviso' => true]);
$ll = end($GLOBALS['pt_http']);
ok($motivo === '', '2) n8n responde 2xx: sin motivo');
ok($ll && $ll['url'] === AT_N8N_PLAN_RENDER && ($ll['args']['method'] ?? '') === 'POST' && (int) ($ll['args']['timeout'] ?? 0) === 15, '2) POST al flujo con 15 s de espera');
ok($ll && ($ll['args']['headers']['X-AT-Secret'] ?? '') === AT_REST_SECRET && ($ll['args']['headers']['Content-Type'] ?? '') === 'application/json', '2) con la clave X-AT-Secret y cuerpo JSON');
ok($ll && $ll['cuerpo'] === ['id' => 7, 'modo' => 'draft', 'aviso' => true], '2) el cuerpo llega tal cual');
// M6 (revisión final): la llamada lleva la clave X-AT-Secret y no sigue redirecciones (un 30x reenviaría la clave a otro
// destino): el 30x cae en «n8n respondió HTTP 30x».
ok($ll && array_key_exists('redirection', $ll['args']) && (int) $ll['args']['redirection'] === 0, '2) sin seguir redirecciones (redirection = 0)');
$GLOBALS['pt_http_respuesta'] = 302;
ok(at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7]) === 'n8n respondió HTTP 302', '2) un 302 no es éxito: «n8n respondió HTTP 302»');
$GLOBALS['pt_http_respuesta'] = 500;
ok(at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7]) === 'n8n respondió HTTP 500', '2) HTTP 500: el motivo lo dice');
$GLOBALS['pt_http_respuesta'] = $caido;
ok(at_pt_llamar_n8n(AT_N8N_PLAN_RENDER, ['id' => 7]) === 'n8n no respondió: cURL error 7: Failed to connect', '2) n8n caído: el motivo lo dice');

// 3) at_pt_iniciar_borrador().
$m = pt_marca();
$cli = pt_cliente($m);
$pid = at_pt_crear_plan(pt_contrato($cli['tech'], null));
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
ok(at_pt_iniciar_borrador($pid) === '' && at_pt_plan($pid)->estado === 'generando', '3) pide el borrador y el plan sigue en «generando»');
$b = pt_llamadas('borrador');
ok(count($b) === 1 && $b[0]['cuerpo'] === ['id' => $pid, 'codigo' => (string) at_pt_plan($pid)->codigo], '3) una llamada al flujo 1 con el id y el código del plan');
$GLOBALS['pt_http_respuesta'] = $caido;
$motivo = at_pt_iniciar_borrador($pid);
$f = at_pt_plan($pid);
ok($motivo !== '' && $f->estado === 'error' && strpos((string) $f->nota, 'n8n no respondió') !== false, '3) n8n caído: el plan queda en «error» con el motivo (Review Focus 5)');
$GLOBALS['pt_http_respuesta'] = 200;
ok(at_pt_iniciar_borrador($pid) === '' && at_pt_plan($pid)->estado === 'generando' && at_pt_plan($pid)->nota === '', '3) «Reintentar borrador»: desde «error» vuelve a «generando» y limpia la nota');
at_pt_guardar($pid, ['estado' => 'borrador', 'payload' => ['version' => 1]]);
$antes = count($GLOBALS['pt_http']);
ok(at_pt_iniciar_borrador($pid) !== '' && at_pt_plan($pid)->estado === 'borrador' && count($GLOBALS['pt_http']) === $antes, '3) con un borrador hecho no se pide otro (borrador → generando no vale)');
ok(at_pt_iniciar_borrador(pt_plan_inexistente()) === 'El plan no existe.', '3) plan que no existe: motivo');

// 4) at_pt_pedir_render().
$GLOBALS['pt_http'] = [];
ok(at_pt_pedir_render($pid, 'draft', true) === '' && (pt_llamadas('render')[0]['cuerpo'] ?? null) === ['id' => $pid, 'codigo' => (string) at_pt_plan($pid)->codigo, 'modo' => 'draft', 'aviso' => true], '4) pide la vista previa con aviso a Luis (id, código, modo y aviso)');
$GLOBALS['pt_http_respuesta'] = 500;
ok(at_pt_pedir_render($pid, 'draft', false) === 'n8n respondió HTTP 500' && at_pt_plan($pid)->estado === 'borrador' && strpos((string) at_pt_plan($pid)->nota, 'vista previa') !== false, '4) vista previa sin n8n: el estado no cambia y queda la nota');
at_pt_guardar($pid, ['estado' => 'aprobando']);
ok(at_pt_pedir_render($pid, 'final', true) !== '' && at_pt_plan($pid)->estado === 'error' && strpos((string) at_pt_plan($pid)->nota, 'versión final') !== false, '4) versión final sin n8n: «aprobando» pasa a «error» con el motivo');
$GLOBALS['pt_http_respuesta'] = 200;
$antes = count($GLOBALS['pt_http']);
ok(at_pt_pedir_render($pid, 'otro', false) === 'Modo de render inválido.' && count($GLOBALS['pt_http']) === $antes, '4) modo desconocido: no llama a n8n');

fin();
