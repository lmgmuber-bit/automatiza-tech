<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/firma-wp-test.php
// Task 6: el módulo escucha at_contrato_firmado (lo dispara ContractService::sign_as_client()).
// Aquí el gancho se dispara con do_action(); la firma real está en firma-contrato-wp-test.php.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_al_firmar');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';
global $wpdb;

ok(has_action('at_contrato_firmado', 'at_pt_al_firmar') === 10, '0) el módulo escucha at_contrato_firmado');

// 1) Contrato de servicios firmado: plan en «generando» y aviso al flujo 1.
$m = pt_marca();
$cli = pt_cliente($m);
$cid = pt_contrato($cli['tech'], pt_propuesta($m));
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
do_action('at_contrato_firmado', pt_contrato_fila($cid));
$f = at_pt_plan_de_contrato($cid);
ok($f && $f->estado === 'generando', '1) contrato de servicios firmado: crea el plan en «generando»');
ok(count(pt_llamadas('borrador')) === 1 && (pt_llamadas('borrador')[0]['cuerpo']['id'] ?? 0) === ($f ? (int) $f->id : -1), '1) y le pide el borrador a n8n una vez');
ok((int) (pt_llamadas('borrador')[0]['args']['timeout'] ?? 0) === 5, '1) al firmar, el aviso a n8n no retiene al cliente más de 5 s');

// 2) Firma repetida (Review Focus 5).
do_action('at_contrato_firmado', pt_contrato_fila($cid));
ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $cid)) === 1 && count(pt_llamadas('borrador')) === 1, '2) firma repetida: un solo plan y ningún aviso nuevo');

// 3) Contrato que no es de servicios (Review Focus 5).
$sop = pt_contrato($cli['tech'], null, ['type' => 'soporte']);
do_action('at_contrato_firmado', pt_contrato_fila($sop));
ok(!at_pt_plan_de_contrato($sop) && count(pt_llamadas('borrador')) === 1, '3) contrato de soporte: ni plan ni aviso');

// 4) Manda la base: un objeto que dice «firmado» de un contrato que en la base no lo está.
$sin = pt_contrato($cli['tech'], null, ['status' => 'sent']);
$viejo = pt_contrato_fila($sin);
$viejo->status = 'signed';
do_action('at_contrato_firmado', $viejo);
ok(!at_pt_plan_de_contrato($sin) && count(pt_llamadas('borrador')) === 1, '4) si en la base el contrato no está firmado, no hay plan');

// 5) Nunca lanza.
$lanzo = false;
try {
	at_pt_al_firmar(null);
	at_pt_al_firmar(new WP_Error('x', 'no es un contrato'));
	at_pt_al_firmar((object) ['id' => 'abc', 'type' => 'servicios']);
} catch (\Throwable $e) {
	$lanzo = true;
}
ok(!$lanzo, '5) con datos raros (null, un error, un id que no es número) no lanza');

// 6) n8n caído al firmar, contrato sin propuesta y cliente sin correo (Review Focus 4 y 5).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$cid2 = pt_contrato($cli2['tech'], null);
$GLOBALS['pt_http_respuesta'] = new WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
do_action('at_contrato_firmado', pt_contrato_fila($cid2));
$f2 = at_pt_plan_de_contrato($cid2);
ok($f2 && $f2->estado === 'error' && strpos((string) $f2->nota, 'n8n no respondió') !== false, '6) n8n caído: el plan queda en «error» con el motivo, listo para «Reintentar borrador»');
ok($f2 && $f2->propuesta_id === null, '6) contrato sin propuesta y cliente sin correo: igual tiene plan');

fin();
