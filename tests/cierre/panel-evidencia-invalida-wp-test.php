<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/panel-evidencia-invalida-wp-test.php
// T11 ronda 1 (26-sep), hallazgo 3 (panel.php): revisado el código y probado en el WordPress local, con
// una evidencia inválida (texto plano con extensión .png) at_cc_accion_registrar_aceptacion() YA
// avisa a Luis -no se registró en silencio como un éxito en verde-: at_cc_guardar_evidencias() agrega
// "falsa.png: no es una imagen JPG, PNG o WEBP" a $ev['errores'], y ese arreglo entra a $detalles
// (junto con avisos/avisos_operativos), que hace que el aviso final sea "aviso" (amarillo) en vez de
// "ok" (verde) y liste el motivo. Esta prueba deja esa conducta bajo prueba automatizada -antes sin
// cobertura: panel-ronda2-wp-test-run.php siempre corre con $_FILES = []- para que no se rompa después
// sin que ningún test lo note.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_cc_accion_registrar_aceptacion')) {
	fwrite(STDERR, "panel.php no está cargado: revisa cargar.php.\n");
	exit(2);
}

$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$admin_id = (int) ($admins[0] ?? 0);
if ($admin_id <= 0) {
	fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
	exit(2);
}

$marca = 'prueba-evidencia-invalida-' . strtolower(wp_generate_password(6, false, false));
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
	'client_email' => $marca . '@example.com', 'unique_link_id' => substr(md5($marca . microtime(true)), 0, 12),
	'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Evidencia inválida', 'phone' => '',
	'status' => 'sent', 'flujo' => 'v3',
	'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
	'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
]);
$pid = (int) $wpdb->insert_id;

$post = [
	'proposal_id' => (string) $pid, 'canal' => 'whatsapp', 'nota' => 'Dijo que sí (prueba evidencia inválida).',
	'nombre' => 'Cliente Prueba', 'rut' => '', 'fecha' => current_time('Y-m-d'), 'filas' => ['0'], 'bienvenida' => '0',
];

delete_transient('at_cc_aviso_' . $admin_id);
$hijo = __DIR__ . '/panel-evidencia-invalida-wp-test-run.php';
$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open([PHP_BINARY, $hijo, (string) $admin_id, json_encode($post)], $descriptores, $pipes);
if (!is_resource($proc)) {
	fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
	exit(2);
}
stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);
wp_cache_flush(); // igual que panel-ronda2-wp-test.php: refresca la caché de opciones/transients tras el hijo.

$aviso = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso), 'la acción dejó un aviso guardado para el panel: ' . trim($err));
ok(is_array($aviso) && ($aviso['tipo'] ?? '') === 'aviso', 'con una evidencia inválida: el aviso queda como "aviso" (amarillo), NUNCA "ok" (verde)' . (is_array($aviso) ? ': tipo=' . ($aviso['tipo'] ?? '') : ''));
$detalles = is_array($aviso) ? (array) ($aviso['detalles'] ?? []) : [];
ok(in_array('falsa.png: no es una imagen JPG, PNG o WEBP', $detalles, true), 'el aviso le dice a Luis que "falsa.png" no se guardó' . ($detalles ? ': ' . implode(' | ', $detalles) : ' (vacío)'));
ok(at_cc_propuesta_por_id($pid)->status === 'aceptada', 'de paso, la aceptación sí se registró (la evidencia inválida no bloquea el resto)');
$dir_ev = at_cc_dir_evidencias() . '/' . $pid;
ok(!is_dir($dir_ev) || count(glob($dir_ev . '/*.{jpg,png,webp}', GLOB_BRACE)) === 0, 'la imagen inválida no quedó guardada en disco (solo se listó como error)');

// Limpieza
delete_transient('at_cc_aviso_' . $admin_id);
$c = at_cc_contrato_de_propuesta($pid);
if ($c) {
	@unlink(ContractService::storage_dir() . '/' . $c->contract_number . '.pdf');
	$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id = %d", $c->id));
}
if (is_dir($dir_ev)) {
	foreach (glob($dir_ev . '/*') ?: [] as $f) {
		@unlink($f);
	}
	@rmdir($dir_ev);
}
$email = $wpdb->get_var($wpdb->prepare("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));
$crm_id = $email ? at_cc_crm_de_email($email) : null;
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $pid));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id = %d", $pid));
if ($crm_id) {
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $crm_id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm_id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
}
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid));

fin();
