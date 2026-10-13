<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/firma-contrato-wp-test.php
// Task 6: la firma real del cliente (ContractService::sign_as_client) dispara el plan y nunca falla por él.
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
exigir('at_pt_al_firmar');
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba: se define solo en el wp-config del sitio local (valor de prueba).\n");
	exit(2);
}
require __DIR__ . '/datos-prueba.php';

// PNG de 1x1 (el formato de la firma dibujada).
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

/** Firma el contrato como el cliente y anota el PDF firmado y la imagen de la firma para borrarlos al final. */
function pt_firmar(int $cid, string $png) {
	$c = ContractService::get_by_id($cid);
	$r = ContractService::sign_as_client((string) $c->sign_token, ['signer_name' => 'Cliente Prueba', 'signer_rut' => '11.111.111-1', 'signer_email' => 'prueba-plan-firma@example.com', 'method' => 'canvas', 'signature_dataurl' => $png]);
	$x = ContractService::get_by_id($cid);
	if ($x) {
		pt_archivo(ContractService::storage_dir() . '/' . $x->contract_number . '-FIRMADO.pdf');
		$up = wp_upload_dir();
		$firma = str_replace($up['baseurl'], $up['basedir'], (string) $x->signature_image_url);
		if (preg_match('#/signatures/sig-client-[0-9a-f]+\.(png|jpeg)$#', $firma)) {
			pt_archivo($firma);
		}
	}
	return $r;
}

// 0) Respaldo textual: el gancho está en contract-service.php, en try/catch y después de las copias por correo.
// Lo que importa (orden y fila ya firmada) lo prueba el espía de la prueba 2 por comportamiento.
$fuente = (string) file_get_contents(ABSPATH . 'contracts/contract-service.php');
$patron = '/send_signed_copy_internal\(\$fresh, \$signed_path\);\s*(\/\/[^\n]*\n\s*)?try \{\s*do_action\(\'at_contrato_firmado\', \$fresh\);\s*\} catch \(\\\\Throwable \$e\) \{\s*error_log\(\'at_contrato_firmado: \' \. \$e->getMessage\(\)\);\s*\}\s*return \$fresh;/';
ok(preg_match($patron, $fuente) === 1, '0) contract-service.php dispara at_contrato_firmado en try/catch, después de las copias');

// 1) Firma real de un contrato de servicios. Un espía anota cómo llega el gancho: estado del contrato y
// cuántos correos habían salido (sign_as_client manda dos: la copia al cliente y la interna).
$m = pt_marca();
$cli = pt_cliente($m);
$cid = pt_contrato($cli['tech'], pt_propuesta($m), ['status' => 'sent']);
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
$GLOBALS['pt_correos'] = [];
$visto = null;
$espia = function ($c) use (&$visto) { $visto = ['correos' => count($GLOBALS['pt_correos']), 'status' => (string) ($c->status ?? '')]; };
add_action('at_contrato_firmado', $espia, 1);
$r = pt_firmar($cid, $png);
remove_action('at_contrato_firmado', $espia, 1);
ok(!is_wp_error($r) && $r->status === 'signed', '1) el cliente firma como siempre' . (is_wp_error($r) ? ' (' . $r->get_error_code() . ')' : ''));
ok($visto !== null && $visto['status'] === 'signed' && $visto['correos'] === 2, '2) el gancho corre con el contrato ya firmado y después de las dos copias por correo');
$f = at_pt_plan_de_contrato($cid);
ok($f && $f->estado === 'generando', '3) la firma crea el plan en «generando»');
$b = pt_llamadas('borrador');
ok(count($b) === 1 && ($b[0]['cuerpo']['id'] ?? 0) === ($f ? (int) $f->id : -1), '4) y avisa al flujo 1 de n8n con el id del plan');

// 2) n8n caído al firmar (Review Focus 5).
$cid2 = pt_contrato($cli['tech'], null, ['status' => 'sent']);
$GLOBALS['pt_http_respuesta'] = new WP_Error('http_request_failed', 'cURL error 7: Failed to connect');
$r2 = pt_firmar($cid2, $png);
ok(!is_wp_error($r2) && $r2->status === 'signed', '5) n8n caído: la firma no falla');
$f2 = at_pt_plan_de_contrato($cid2);
ok($f2 && $f2->estado === 'error' && strpos((string) $f2->nota, 'n8n') !== false, '6) n8n caído: el plan queda en «error» con el motivo');

// 3) Algo del plan lanza una excepción: la firma igual termina.
$GLOBALS['pt_http_respuesta'] = 200;
$lanzador = function () { throw new RuntimeException('falla simulada del plan'); };
add_action('at_contrato_firmado', $lanzador, 1);
$r3 = pt_firmar(pt_contrato($cli['tech'], null, ['status' => 'sent']), $png);
remove_action('at_contrato_firmado', $lanzador, 1);
ok(!is_wp_error($r3) && $r3->status === 'signed', '7) si el plan lanza, la firma igual termina');

// 4) Contrato de soporte: se firma y no tiene plan.
$cid4 = pt_contrato($cli['tech'], null, ['type' => 'soporte', 'status' => 'sent']);
$r4 = pt_firmar($cid4, $png);
ok(!is_wp_error($r4) && $r4->status === 'signed' && !at_pt_plan_de_contrato($cid4), '8) un contrato de soporte se firma y no tiene plan');

fin();
