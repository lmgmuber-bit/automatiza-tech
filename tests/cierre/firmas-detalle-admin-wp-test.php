<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/firmas-detalle-admin-wp-test.php
// Pendiente del cierre (26-sep): en el detalle de un contrato del admin (📜 Contratos › un contrato)
// las imágenes de las firmas se veían rotas. Las firmas se guardan en
// uploads/automatiza-tech-contracts/signatures/, carpeta con «Deny from all» (403 por URL, a propósito:
// son datos personales), y el detalle las pedía por esa URL. Ahora el detalle las incrusta leídas en el
// servidor (data: URI), solo si el archivo está dentro de signatures/ y es PNG o JPEG; lo demás no se
// dibuja. La carpeta sigue cerrada.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'contracts/contract-service.php';
require_once ABSPATH . 'contracts/admin-contracts.php';
global $wpdb;

$tabla = ContractService::table();
$dir = ContractService::storage_dir() . '/signatures';
wp_mkdir_p($dir);
$up = wp_upload_dir();
$marca = 'prueba-firmas-' . strtolower(wp_generate_password(6, false, false));
$creados_archivos = [];
$creados_contratos = [];

// PNG de 1x1 válido (el formato que guarda la firma dibujada).
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$f_at = $dir . '/sig-at-' . $marca . '.png';
$f_cli = $dir . '/sig-client-' . $marca . '.png';
file_put_contents($f_at, $png);
file_put_contents($f_cli, $png);
$creados_archivos = [$f_at, $f_cli];
$url = function (string $ruta) use ($up): string { return str_replace($up['basedir'], $up['baseurl'], $ruta); };

function fd_contrato(string $marca, string $sufijo, string $url_at, string $url_cli, array &$creados): int {
	global $wpdb;
	$wpdb->insert(ContractService::table(), [
		'contract_number' => strtoupper($marca . '-' . $sufijo), 'type' => 'soporte', 'status' => 'signed',
		'client_id' => 0, 'placeholders' => '{}', 'created_at' => current_time('mysql'),
		'sign_token' => md5('cli' . $marca . $sufijo), 'at_review_token' => md5('at' . $marca . $sufijo),
		'at_signed_at' => current_time('mysql'), 'at_signer_name' => 'Firmante AT', 'at_signer_rut' => '11.111.111-1',
		'at_signature_image_url' => $url_at,
		'signed_at' => current_time('mysql'), 'signer_name' => 'Firmante Cliente', 'signer_rut' => '22.222.222-2',
		'signer_email' => 'cliente@example.com', 'signature_image_url' => $url_cli,
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) { fwrite(STDERR, "No se pudo insertar el contrato de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creados[] = $id;
	return $id;
}

function fd_detalle(int $id): string {
	$_GET = ['page' => 'at-contracts', 'id' => (string) $id];
	ob_start();
	AT_Contracts_Admin::render_page();
	$h = (string) ob_get_clean();
	$_GET = [];
	return $h;
}

wp_set_current_user((int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0));

// 1) Firmas guardadas en signatures/: se ven, incrustadas, sin la URL bloqueada.
$id = fd_contrato($marca, 'ok', $url($f_at), $url($f_cli), $creados_contratos);
$h = fd_detalle($id);
ok(substr_count($h, "src='data:image/png;base64,") === 2, '1) las dos firmas se dibujan incrustadas (data:image/png)');
ok(strpos($h, 'automatiza-tech-contracts/signatures') === false, '1) el detalle ya no pide la URL de la carpeta privada (403)');
ok(strpos($h, base64_encode($png)) !== false, '1) la imagen incrustada es el archivo guardado');

// 2) Archivo que ya no existe: no se dibuja una imagen rota; se avisa.
$id2 = fd_contrato($marca, 'falta', $url($dir . '/sig-at-no-existe-' . $marca . '.png'), '', $creados_contratos);
$h2 = fd_detalle($id2);
ok(strpos($h2, '<img') === false, '2) firma sin archivo: no hay <img> roto');
ok(strpos($h2, 'Imagen de la firma no disponible') !== false, '2) firma sin archivo: se avisa que no está disponible');

// 3) Una URL fuera de signatures/ (p. ej. un PDF del contrato o algo que no es imagen) no se incrusta.
$pdf_falso = ContractService::storage_dir() . '/' . $marca . '-no-firma.png';
file_put_contents($pdf_falso, $png);
$creados_archivos[] = $pdf_falso;
$id3 = fd_contrato($marca, 'fuera', $url($pdf_falso), $url($dir . '/../../../../wp-config.php'), $creados_contratos);
$h3 = fd_detalle($id3);
ok(strpos($h3, '<img') === false, '3) archivos fuera de signatures/ (o con ../): no se incrustan');

// 4) Dentro de signatures/ pero no es una imagen: no se incrusta.
$txt = $dir . '/sig-client-' . $marca . '.png.txt';
file_put_contents($txt, 'no soy una imagen');
$creados_archivos[] = $txt;
$id4 = fd_contrato($marca, 'noimg', '', $url($txt), $creados_contratos);
$h4 = fd_detalle($id4);
ok(strpos($h4, '<img') === false, '4) un archivo que no es PNG/JPEG no se incrusta');

// 5) La carpeta sigue cerrada: el .htaccess con «Deny from all» no cambió.
ok(strpos((string) @file_get_contents(ContractService::storage_dir() . '/.htaccess'), 'Deny from all') !== false, '5) la carpeta de contratos sigue con «Deny from all»');

// Limpieza
foreach ($creados_archivos as $f) {
	@unlink($f);
}
if ($creados_contratos) {
	$wpdb->query("DELETE FROM {$tabla} WHERE id IN (" . implode(',', array_map('intval', $creados_contratos)) . ')');
}

fin();
