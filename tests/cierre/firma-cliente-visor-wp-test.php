<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/firma-cliente-visor-wp-test.php
// 27-sep, primer contrato real (propuesta 53): en la página de firma del cliente
// (contracts/sign-contract.php) el visor del contrato decía «❌ Acceso denegado.». El iframe armaba su
// dirección como secure_pdf_url(...) . '?v=' . time(): esa URL ya trae '?action=…&token=<hex>', así que
// el token llegaba como '<hex>?v=1727…', get_by_token() lo rechazaba (no calza con /^[a-f0-9]{64}$/) y la
// descarga respondía 403. La página de revisión de AT (at-sign-contract.php) ya usaba add_query_arg().
// Esta prueba dibuja la página del cliente como la ve el cliente y pide el PDF con la URL del visor.
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;

$tabla = ContractService::table();
$marca = strtoupper('PRUEBA-VISOR-' . wp_generate_password(6, false, false));
$token = bin2hex(random_bytes(32));
$wpdb->insert($tabla, [
	'contract_number' => $marca, 'type' => 'servicios', 'status' => 'sent', 'client_id' => 0,
	'placeholders' => wp_json_encode(['razon_social_cliente' => 'Cliente Prueba', 'nombre_proyecto' => 'Proyecto de prueba']),
	'sign_token' => $token, 'at_review_token' => bin2hex(random_bytes(32)),
	'pdf_url' => ContractService::storage_url() . '/' . $marca . '.pdf', 'created_at' => current_time('mysql'),
]);
$id = (int) $wpdb->insert_id;
if (!$id) {
	fwrite(STDERR, "No se pudo insertar el contrato de prueba: {$wpdb->last_error}\n");
	exit(2);
}
$pdf = ContractService::storage_dir() . '/' . $marca . '.pdf';
file_put_contents($pdf, "%PDF-1.4\n% contrato de prueba\n");

function fv_hijo(array $args): string {
	$proc = proc_open(array_merge([PHP_BINARY, __DIR__ . '/firma-cliente-visor-wp-test-run.php'], $args),
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null);
	$salida = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	return $salida . ($error !== '' ? "\n[stderr] " . $error : '');
}

$html = fv_hijo(['pagina', $token]);
ok(strpos($html, 'Contrato listo para tu firma') !== false, '1) la página de firma del cliente se dibuja');
$src = preg_match('/<iframe src="([^"]+)"/', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
ok($src !== '', '1) la página trae el visor del contrato (iframe)');
parse_str((string) parse_url($src, PHP_URL_QUERY), $q);
ok(substr_count($src, '?') === 1, '1) la URL del visor tiene un solo «?» (antes: …&token=<hex>?v=…): ' . $src);
ok(($q['token'] ?? '') === $token, '1) el token que llega a la descarga es exactamente el del contrato');
ok((int) ($q['contract_id'] ?? 0) === $id && ($q['action'] ?? '') === 'at_download_contract', '1) apunta a la descarga protegida de este contrato');
ok(isset($q['v']), '1) conserva el parámetro que evita la caché del navegador');

$descarga = fv_hijo(['descarga', wp_json_encode($q)]);
ok(strpos($descarga, '%PDF') === 0, '2) con la URL del visor, la descarga entrega el PDF (no «Acceso denegado»): ' . substr($descarga, 0, 60));

// 3) Sigue cerrada sin token o con uno ajeno.
$sin = $q;
$sin['token'] = bin2hex(random_bytes(32));
ok(strpos(fv_hijo(['descarga', wp_json_encode($sin)]), 'Acceso denegado') !== false, '3) con otro token: «Acceso denegado» (sigue protegida)');
unset($sin['token']);
ok(strpos(fv_hijo(['descarga', wp_json_encode($sin)]), 'Acceso denegado') !== false, '3) sin token: «Acceso denegado»');

// Limpieza
@unlink($pdf);
$wpdb->delete($tabla, ['id' => $id], ['%d']);

fin();
