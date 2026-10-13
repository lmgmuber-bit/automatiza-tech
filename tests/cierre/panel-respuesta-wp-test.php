<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/panel-respuesta-wp-test.php
// Task 8, ronda 1 de revisión (panel.php):
// - Hallazgo 1: al registrar la aceptación a mano, el recordatorio operativo ('avisos_operativos',
//   p. ej. datos bancarios sin configurar) debe quedar en el aviso que ve Luis en el panel, porque en
//   el canal manual at_cc_avisar_luis() no manda ningún correo.
// - Hallazgo 2: el enlace de WhatsApp con "…&responder=aceptar" solo debe ofrecerse cuando la página
//   pública sí dibuja la barra para aceptar (at_cc_puede_pedir_respuesta(): sent/evaluando/rechazada).
// at_cc_render_panel_respuesta() usa at_pa_estado_etiqueta()/at_pa_fecha_corta() (consultas.php), que
// el tema solo carga si is_admin() (admin-proposals.php); admin-post.php sí corre en ese contexto, así
// que se simula aquí, igual que en la ficha real.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_cc_render_panel_respuesta') || !function_exists('at_cc_accion_registrar_aceptacion')) {
	fwrite(STDERR, "panel.php no está cargado: revisa cargar.php.\n");
	exit(2);
}

$marca = 'prueba-panel-respuesta-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];
function crear_propuesta_panel(string $marca, string $status, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '@example.com', 'unique_link_id' => substr(md5($marca . $status . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Panel', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => 'v3',
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
function render_panel(object $p): string {
	ob_start();
	at_cc_render_panel_respuesta($p);
	return (string) ob_get_clean();
}

// ---------- Hallazgo 2: el botón de WhatsApp solo aparece cuando la barra pública existe ----------
$sent = crear_propuesta_panel($marca, 'sent', $creadas);
$h_sent = render_panel($sent);
ok(strpos($h_sent, 'Enviar por mi WhatsApp') !== false && strpos($h_sent, 'wa.me') !== false, 'estado sent (puede pedir respuesta): sí ofrece el WhatsApp con el enlace de aceptar');

// Ronda 1, hallazgo 1 (whatsapp.php): este botón usaba el mismo esc_url($wa) que borraba el salto de
// línea del mensaje y pegaba "responder=aceptar" con el texto siguiente. Con esc_attr(), el parámetro
// text= del href debe traer el salto de línea real y el enlace para aceptar debe quedar íntegro.
if (preg_match('/href="([^"]+)"/', $h_sent, $m_href) && preg_match('/[?&]text=([^&]*)/', html_entity_decode($m_href[1], ENT_QUOTES, 'UTF-8'), $m_text)) {
	$texto_panel = rawurldecode($m_text[1]);
	ok(strpos($texto_panel, "\n") !== false, 'panel.php: el mensaje de wa.me trae el salto de línea real (esc_attr no lo borra)');
	preg_match('#https?://\S*/ver-presentacion\.php\?id=\S*#', $texto_panel, $m_enlace_panel);
	$enlace_panel = $m_enlace_panel[0] ?? '';
	ok($enlace_panel !== '' && substr($enlace_panel, -strlen('responder=aceptar')) === 'responder=aceptar', 'panel.php: el enlace para aceptar dentro del mensaje termina en "responder=aceptar", sin texto pegado');
} else {
	ok(false, 'panel.php: no se pudo extraer el parámetro text= del botón de wa.me');
}

// Task 15: «Registrar aceptación a mano» pide el tipo de documento (RUT, DNI o pasaporte) y el número.
ok(strpos($h_sent, '<tr><th>Documento</th><td><select name="tipo_documento" form="at-cc-f-aceptar">') !== false, 'T15: el panel pide «Documento» con un selector name="tipo_documento" del formulario de aceptar');
ok(strpos($h_sent, '<option value="rut" selected>RUT</option>') !== false && strpos($h_sent, '<option value="dni">DNI</option>') !== false && strpos($h_sent, '<option value="pasaporte">Pasaporte</option>') !== false, 'T15: el selector del panel ofrece RUT (elegido), DNI y Pasaporte');
ok(strpos($h_sent, '<tr><th>Número (si lo tienes)</th><td><input type="text" class="regular-text" name="documento" form="at-cc-f-aceptar"') !== false, 'T15: el número va en «Número (si lo tienes)» (name="documento")');
ok(strpos($h_sent, 'name="rut"') === false && strpos($h_sent, 'RUT (si lo tienes)') === false, 'T15: el panel ya no pide «RUT (si lo tienes)»');

foreach (['lista', 'pending', 'borrador', 'generando', 'ajustando', 'error', 'contracted'] as $estado_sin_barra) {
	$q = crear_propuesta_panel($marca, $estado_sin_barra, $creadas);
	$h = render_panel($q);
	ok(strpos($h, 'wa.me') === false, "estado {$estado_sin_barra} (la página pública no dibuja la barra): NO ofrece el WhatsApp con el enlace de aceptar");
	ok(strpos($h, 'se habilita cuando la propuesta esté enviada') !== false, "estado {$estado_sin_barra}: explica cuándo se habilita el WhatsApp");
}

// ---------- Hallazgo 1: el recordatorio de datos bancarios llega al aviso del panel ----------
foreach (['at_cc_banco', 'at_cc_tipo_cuenta', 'at_cc_numero_cuenta', 'at_cc_titular', 'at_cc_rut_titular'] as $o) {
	delete_option($o);
}
ok(!at_cc_banco_completo(at_cc_datos_banco()), 'sin datos bancarios (precondición de la prueba)');
$sb = crear_propuesta_panel($marca . '-banco', 'sent', $creadas);
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$admin_id = (int) ($admins[0] ?? 0);
if ($admin_id <= 0) {
	fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
	exit(2);
}
delete_transient('at_cc_aviso_' . $admin_id);
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

// at_cc_accion_registrar_aceptacion() siempre termina con wp_safe_redirect()+exit; se corre en un
// proceso PHP aparte (mismo patrón que panel-timeline-wp-test.php) y aquí se revisa el aviso real
// que quedó guardado (set_transient corre antes del exit, así que ya está en la base al terminar).
$hijo = __DIR__ . '/panel-respuesta-wp-test-run.php';
$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open([PHP_BINARY, $hijo, (string) $sb->id, (string) $admin_id], $descriptores, $pipes);
if (!is_resource($proc)) {
	fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
	exit(2);
}
$salida_hijo = stream_get_contents($pipes[1]);
$errores_hijo = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);

$aviso = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso), 'la acción sí dejó un aviso guardado para el panel: ' . trim($errores_hijo) . ' | ' . trim($salida_hijo));
ok(is_array($aviso) && (int) ($aviso['propuesta_id'] ?? 0) === (int) $sb->id, 'el aviso es de la propuesta correcta');
ok(is_array($aviso) && ($aviso['tipo'] ?? '') === 'aviso', 'sin banco: el aviso queda como "aviso" (amarillo), no "ok" ni "error"' . (is_array($aviso) ? ': tipo=' . ($aviso['tipo'] ?? '') : ''));
$detalles = is_array($aviso) ? (array) ($aviso['detalles'] ?? []) : [];
ok(in_array('Faltan los datos bancarios (Propuestas › Ajustes del cierre): envíale al cliente los datos de transferencia.', $detalles, true), 'el recordatorio de datos bancarios está en los detalles del aviso' . ($detalles ? ': ' . implode(' | ', $detalles) : ' (vacío)'));
ok(at_cc_propuesta_por_id($sb->id)->status === 'aceptada', 'de paso, la propuesta sí quedó aceptada');
delete_transient('at_cc_aviso_' . $admin_id);

// Limpieza (mismo patrón que cierre-wp-test.php: contrato + PDF, Seguimiento, cliente CRM, propuesta)
$c = at_cc_contrato_de_propuesta((int) $sb->id);
if ($c) {
	@unlink(ContractService::storage_dir() . '/' . $c->contract_number . '.pdf');
	$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id = %d", $c->id));
}
$ids = implode(',', array_map('intval', $creadas));
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
if ($crm_ids) {
	$lista = implode(',', array_map('intval', $crm_ids));
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN ({$lista})");
}
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");

fin();
