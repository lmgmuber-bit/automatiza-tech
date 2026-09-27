<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/correo-contrato-firmado-wp-test.php
// 27-sep, primer contrato real: el correo «Contrato firmado» al cliente tenía el botón «Acceder al portal y ver el
// contrato» apuntando a /portal-omnichannel/?contract=…, que en PROD responde 404. Ahora el botón descarga el PDF
// firmado con el token vigente del contrato. La prueba arma el correo real y pide la descarga con su enlace.
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

$tabla = ContractService::table();
$marca = strtoupper('PRUEBA-FIRMADO-' . wp_generate_password(6, false, false));
$token = bin2hex(random_bytes(32));
$wpdb->insert($tabla, [
	'contract_number' => $marca, 'type' => 'servicios', 'status' => 'signed', 'client_id' => 0, 'placeholders' => '{}',
	'sign_token' => $token, 'at_review_token' => bin2hex(random_bytes(32)), 'created_at' => current_time('mysql'),
	'at_signer_name' => 'Firmante AT', 'at_signed_at' => current_time('mysql'),
	'signer_name' => 'Cliente Prueba', 'signer_email' => 'cliente-prueba@example.com', 'signed_at' => current_time('mysql'),
	'signed_document_hash' => str_repeat('a', 64),
]);
$id = (int) $wpdb->insert_id;
if (!$id) {
	fwrite(STDERR, "No se pudo insertar el contrato de prueba: {$wpdb->last_error}\n");
	exit(2);
}
$pdf = ContractService::storage_dir() . '/' . $marca . '-FIRMADO.pdf';
file_put_contents($pdf, "%PDF-1.4\n% contrato firmado de prueba\n");
$c = ContractService::get_by_id($id);

ContractMailer::send_signed_copy($c, $pdf);
$m = $correos[0] ?? [];
$html = (string) ($m['message'] ?? '');
ok(($m['to'] ?? '') === 'cliente-prueba@example.com' && in_array($pdf, (array) ($m['attachments'] ?? []), true), '1) el correo va al cliente con el PDF firmado adjunto');
ok(strpos($html, 'portal-omnichannel') === false, '1) el botón ya no apunta al portal que da 404');
$href = preg_match('/href="([^"]*at_download_contract[^"]*)"/', $html, $mm) ? html_entity_decode($mm[1], ENT_QUOTES) : '';
ok($href !== '' && strpos($html, 'Descargar el contrato firmado') !== false, '1) el botón «Descargar el contrato firmado» trae la descarga protegida');
parse_str((string) parse_url($href, PHP_URL_QUERY), $q);
ok(($q['token'] ?? '') === $token && ($q['signed'] ?? '') === '1' && (int) ($q['contract_id'] ?? 0) === $id, '1) con el token vigente del contrato y la versión firmada');

// 2) Ese enlace entrega de verdad el PDF firmado (mismo proceso hijo que la prueba del visor de firma).
$proc = proc_open([PHP_BINARY, __DIR__ . '/firma-cliente-visor-wp-test-run.php', 'descarga', wp_json_encode($q)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$salida = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);
ok(strpos($salida, '%PDF') === 0 && strpos($salida, 'contrato firmado de prueba') !== false, '2) el enlace del botón descarga el PDF firmado: ' . substr($salida, 0, 50));

// 3) La copia interna sigue igual: a contacto@ (o OMNI_MASTER_EMAIL) con el PDF y el enlace al panel.
$correos = [];
ContractMailer::send_signed_copy_internal($c, $pdf);
ok(in_array($pdf, (array) ($correos[0]['attachments'] ?? []), true) && strpos((string) ($correos[0]['message'] ?? ''), 'page=at-contracts') !== false, '3) la copia interna lleva el PDF y el enlace al panel de contratos');

@unlink($pdf);
$wpdb->delete($tabla, ['id' => $id], ['%d']);
fin();
