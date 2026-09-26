<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/portal-pdf-wp-test.php   (WordPress local de prueba)
//
// Task 5b (cotejo con PROD del 25-sep): la línea de tiempo pública del cliente
// (render_public_timeline() en wp-content/mu-plugins/crm-ai-completo.php) enlaza los PDF de los
// contratos por la descarga con permiso (admin-ajax.php?action=at_download_contract&...&token=...),
// nunca directo al archivo en uploads/automatiza-tech-contracts, que el módulo de contratos
// bloquea con .htaccess (el enlace directo da 403).
//
// render_public_timeline() termina con exit, así que la vista se genera en un proceso hijo
// (este mismo archivo con --vista <cid> <token>) y el padre revisa el HTML que imprime.
if (($argv[1] ?? '') === '--vista') {
	require __DIR__ . '/wp-bootstrap.php';
	$_GET = ['crm_view' => 'timeline', 'cid' => (string) ($argv[2] ?? ''), 'token' => (string) ($argv[3] ?? '')];
	$GLOBALS['at_crm_ai']->render_public_timeline();
	exit(0);
}

require __DIR__ . '/wp-bootstrap.php';
if (!class_exists('ContractService')) {
	require_once ABSPATH . 'contracts/contract-service.php';
}
global $wpdb;
add_filter('pre_wp_mail', function () { return true; });
$tabla = ContractService::table();
$crm = $wpdb->prefix . 'crm_clientes';
$tech = $wpdb->prefix . 'automatiza_tech_clients';

$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$email = $marca . '@example.com';
$r = at_cc_asegurar_cliente(['nombre' => 'Prueba Portal', 'email' => $email, 'empresa' => '[PRUEBA] Portal', 'telefono' => '+56 9 1111 1111', 'valor' => 1000, 'servicios' => 'Fase 1']);
ok(is_array($r) && $r['crm_id'] > 0 && $r['tech_id'] > 0, 'cliente de prueba en las dos listas');

$up = wp_upload_dir();
$carpeta = trailingslashit($up['baseurl']) . 'automatiza-tech-contracts';
$sufijo = strtoupper(substr(md5($marca), 0, 8));
$contratos = [];
foreach (['signed' => 'F', 'at_signed' => 'A'] as $estado => $letra) {
	$numero = 'AT-CTR-PRUEBA-' . $sufijo . $letra;
	$fila = [
		'client_id' => (int) $r['tech_id'], 'contract_number' => $numero, 'type' => 'servicios', 'template_id' => 'servicios_v1',
		'placeholders' => '{}', 'status' => $estado, 'sign_token' => bin2hex(random_bytes(32)), 'at_review_token' => bin2hex(random_bytes(32)),
		'pdf_url' => $carpeta . '/' . $numero . '.pdf',
	];
	if ($estado === 'signed') {
		$fila['signed_pdf_url'] = $carpeta . '/' . $numero . '-FIRMADO.pdf';
		$fila['signed_at'] = current_time('mysql');
	}
	$ok = $wpdb->insert($tabla, $fila);
	ok((bool) $ok, "contrato de prueba en estado {$estado}");
	$contratos[$estado] = ['id' => (int) $wpdb->insert_id, 'token' => $fila['sign_token']];
}

$url = $GLOBALS['at_crm_ai']->url_portal((int) $r['crm_id']);
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
ok(!empty($q['cid']) && !empty($q['token']), 'el cliente tiene enlace a su portal');

$proc = proc_open([PHP_BINARY, __FILE__, '--vista', (string) ($q['cid'] ?? ''), (string) ($q['token'] ?? '')], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
$html = is_resource($proc) ? (string) stream_get_contents($tubos[1]) : '';
if (is_resource($proc)) {
	stream_get_contents($tubos[2]);
	fclose($tubos[1]);
	fclose($tubos[2]);
	proc_close($proc);
}
ok(strpos($html, 'Tus Contratos') !== false, 'la vista pública muestra la sección de contratos');

// Enlaces de descarga de la vista, ya decodificados.
preg_match_all('/href="([^"]*action=at_download_contract[^"]*)"/', $html, $m);
$enlaces = [];
foreach ($m[1] as $href) {
	$href = html_entity_decode($href, ENT_QUOTES, 'UTF-8');
	parse_str((string) parse_url($href, PHP_URL_QUERY), $p);
	$enlaces[(int) ($p['contract_id'] ?? 0)] = ['href' => $href, 'p' => $p];
}
$f = $enlaces[$contratos['signed']['id']] ?? null;
ok($f && strpos($f['href'], 'admin-ajax.php?action=at_download_contract') !== false && ($f['p']['signed'] ?? '') === '1' && ($f['p']['token'] ?? '') === $contratos['signed']['token'], 'firmado: «Descargar PDF» va por la descarga con permiso, el PDF firmado y el token');
$a = $enlaces[$contratos['at_signed']['id']] ?? null;
ok($a && strpos($a['href'], 'admin-ajax.php?action=at_download_contract') !== false && !isset($a['p']['signed']) && ($a['p']['token'] ?? '') === $contratos['at_signed']['token'], 'listo para revisar: «Ver contrato» va por la descarga con permiso, el PDF preliminar y el token');
foreach ($contratos as $estado => $x) {
	$c = ContractService::get_by_token($x['token']);
	ok($c && (int) $c->id === $x['id'], "el token del enlace ({$estado}) abre ese mismo contrato en la descarga");
}
ok(strpos($html, 'automatiza-tech-contracts') === false, 'la vista no enlaza directo a uploads/automatiza-tech-contracts');

$wpdb->query($wpdb->prepare("DELETE FROM {$tabla} WHERE id IN (%d, %d)", $contratos['signed']['id'], $contratos['at_signed']['id']));
$wpdb->query($wpdb->prepare("DELETE FROM {$tech} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$crm} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
fin();
