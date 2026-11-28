<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/panel-ronda2-wp-test.php
// Task 8, ronda 2 de revisión (panel.php):
// - Hallazgo 1: «Pedir respuesta» anota con un tipo interno ('pedido_respuesta'), nunca con el tipo
//   público 'propuesta_enviada': así la línea de tiempo pública del prospecto no muestra la nota y
//   sigue agregando la tarjeta automática «Propuesta Creada» con el PDF.
// - Hallazgo 2: registrar la aceptación a mano exige al menos una fila aceptada cuando la propuesta
//   tiene filas, igual que la página pública (pagina.php).
// - Hallazgo 3: un RUT con dígito verificador equivocado ya no se descarta en silencio: vuelve con
//   un aviso, sin registrar nada.
// - Hallazgo 4: el estado se valida ANTES de intentar registrar (propuesta ya aceptada, o transición
//   manual no permitida); y at_cc_borrar_evidencias() limpia evidencias huérfanas.
// - Hallazgo 5: un candado corto (60 s) evita que un doble «Pedir respuesta» mande dos correos.
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_cc_accion_pedir_respuesta') || !function_exists('at_cc_borrar_evidencias')) {
	fwrite(STDERR, "panel.php no está cargado, o at_cc_borrar_evidencias() no existe: revisa cargar.php.\n");
	exit(2);
}

$marca = 'prueba-panel-ronda2-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];
function crear_propuesta_r2(string $marca, string $status, array &$creadas, array $extra = []): object {
	global $wpdb;
	$fila = array_merge([
		'client_email'       => $marca . '-' . count($creadas) . '@example.com',
		'unique_link_id'     => substr(md5($marca . $status . count($creadas) . microtime(true)), 0, 12),
		'client_name'        => 'Cliente Prueba', 'company_name' => '[PRUEBA] Ronda 2', 'phone' => '',
		'status'             => $status, 'flujo' => 'v3',
		'gamma_prompt_text'  => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text'    => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	], $extra);
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', $fila);
	if (!$ok) {
		fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}

$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$admin_id = (int) ($admins[0] ?? 0);
if ($admin_id <= 0) {
	fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
	exit(2);
}
$det = $wpdb->prefix . 'automatiza_propuestas_details';

// at_cc_accion_pedir_respuesta() y at_cc_accion_registrar_aceptacion() siempre terminan con
// wp_safe_redirect()+exit; se corren en un proceso PHP aparte (mismo patrón que
// panel-respuesta-wp-test.php) y aquí solo se revisa lo que quedó en la base.
function correr_r2(string $accion, array $post, int $admin_id): string {
	$hijo = __DIR__ . '/panel-ronda2-wp-test-run.php';
	$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$proc = proc_open([PHP_BINARY, $hijo, $accion, (string) $admin_id, json_encode($post)], $descriptores, $pipes);
	if (!is_resource($proc)) {
		fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
		exit(2);
	}
	stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	// Sin esto, get_transient()/get_option() en este proceso puede seguir creyendo que la opción no
	// existe (caché 'notoptions' de WordPress) después de un delete_transient() anterior en el mismo
	// proceso, aunque el hijo la haya creado de verdad en la base un instante después.
	wp_cache_flush();
	return $err;
}

// ================= Hallazgo 1 + 5: pedido_respuesta y candado =================
$prop1 = crear_propuesta_r2($marca, 'sent', $creadas, ['pdf_url' => 'https://example.com/prop.pdf']);
delete_transient('at_cc_pedido_' . $prop1->id);
delete_transient('at_cc_aviso_' . $admin_id);
$err = correr_r2('pedir', ['action' => 'at_cc_pedir_respuesta', 'proposal_id' => (string) $prop1->id, '__mail_ok' => true], $admin_id);
$aviso1 = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso1) && ($aviso1['tipo'] ?? '') === 'ok', 'pedir respuesta (correo ok): aviso "ok"' . (is_array($aviso1) ? '' : ' | ' . trim($err)));
$filas1 = $wpdb->get_results($wpdb->prepare("SELECT detail_type FROM {$det} WHERE propuesta_id = %d ORDER BY id", $prop1->id), ARRAY_A);
ok(count($filas1) === 1 && $filas1[0]['detail_type'] === 'pedido_respuesta', 'la nota de "pedir respuesta" queda con detail_type=pedido_respuesta, nunca propuesta_enviada: ' . json_encode($filas1));

// El candado: un segundo «Pedir respuesta» inmediato no debe mandar otro correo ni dejar otra nota.
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('pedir', ['action' => 'at_cc_pedir_respuesta', 'proposal_id' => (string) $prop1->id, '__mail_ok' => true], $admin_id);
$aviso1b = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso1b) && ($aviso1b['tipo'] ?? '') === 'aviso' && strpos((string) ($aviso1b['texto'] ?? ''), 'espera un minuto') !== false, 'segundo "pedir respuesta" inmediato: candado activo, aviso de espera' . (is_array($aviso1b) ? ': ' . $aviso1b['texto'] : ''));
$filas1b = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$det} WHERE propuesta_id = %d", $prop1->id), ARRAY_A);
ok(count($filas1b) === 1, 'el candado evitó una segunda nota (sigue habiendo solo una)');

// La vista pública del prospecto: sigue mostrando "Propuesta Creada" (con PDF) y nunca la nota interna.
$hijo_render = __DIR__ . '/panel-timeline-wp-test-render.php';
$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open([PHP_BINARY, $hijo_render, (string) $prop1->id, $prop1->client_email], $descriptores, $pipes);
$html1 = '';
$err1 = '';
if (is_resource($proc)) {
	$html1 = stream_get_contents($pipes[1]);
	$err1 = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
}
ok(strpos($html1, 'Propuesta Creada') !== false, 'la vista pública del prospecto sigue mostrando "Propuesta Creada": ' . trim($err1));
ok(strpos($html1, 'Se le pidió la respuesta') === false, 'la vista pública del prospecto NO muestra la nota de "pedir respuesta"');

// Correo fallido: misma anotación interna ('pedido_respuesta'), aviso de error solo visible a Luis.
$prop1f = crear_propuesta_r2($marca, 'sent', $creadas);
delete_transient('at_cc_pedido_' . $prop1f->id);
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('pedir', ['action' => 'at_cc_pedir_respuesta', 'proposal_id' => (string) $prop1f->id, '__mail_ok' => false], $admin_id);
$aviso1f = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso1f) && ($aviso1f['tipo'] ?? '') === 'error', 'pedir respuesta (correo falla): aviso "error"');
$filas1f = $wpdb->get_results($wpdb->prepare("SELECT detail_type FROM {$det} WHERE propuesta_id = %d", $prop1f->id), ARRAY_A);
ok(count($filas1f) === 1 && $filas1f[0]['detail_type'] === 'pedido_respuesta', 'correo fallido: también queda con detail_type=pedido_respuesta (no propuesta_enviada)');

// ================= Hallazgo 2: sin filas aceptadas, con filas en la propuesta =================
$prop2 = crear_propuesta_r2($marca, 'sent', $creadas);
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop2->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba ronda 2).', 'nombre' => 'Cliente Prueba', 'rut' => '', 'fecha' => current_time('Y-m-d'),
	'filas' => [], 'bienvenida' => '0',
], $admin_id);
$aviso2 = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso2) && ($aviso2['tipo'] ?? '') === 'error' && strpos((string) ($aviso2['texto'] ?? ''), 'Marca qué aceptó') !== false, 'sin filas marcadas (con filas en la propuesta): aviso "Marca qué aceptó el cliente"' . (is_array($aviso2) ? ': ' . $aviso2['texto'] : ''));
ok(at_cc_propuesta_por_id($prop2->id)->status === 'sent', 'la propuesta NO quedó aceptada');
$filas2 = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$det} WHERE propuesta_id = %d", $prop2->id), ARRAY_A);
ok(count($filas2) === 0, 'ninguna nota de Seguimiento se creó: el chequeo de filas corrió antes de registrar');

// ================= Hallazgo 3: RUT inválido =================
$prop3 = crear_propuesta_r2($marca, 'sent', $creadas);
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop3->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba ronda 2).', 'nombre' => 'Cliente Prueba', 'rut' => '11.111.111-2', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso3 = get_transient('at_cc_aviso_' . $admin_id);
// Task 15: el aviso pasa a hablar del documento (RUT, DNI o pasaporte). Este envío trae solo 'rut'
// (el formulario anterior): se valida como RUT.
ok(is_array($aviso3) && ($aviso3['tipo'] ?? '') === 'error' && ($aviso3['texto'] ?? '') === 'El documento no es válido: corrígelo o déjalo en blanco.', 'RUT con dígito verificador equivocado: aviso "El documento no es válido…"' . (is_array($aviso3) ? ': ' . $aviso3['texto'] : ''));
ok(at_cc_propuesta_por_id($prop3->id)->status === 'sent', 'la propuesta NO quedó aceptada con el RUT inválido');
$filas3 = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$det} WHERE propuesta_id = %d", $prop3->id), ARRAY_A);
ok(count($filas3) === 0, 'ninguna nota de Seguimiento se creó: el chequeo de RUT corrió antes de registrar');

// RUT vacío sigue siendo válido (es opcional): la aceptación sí se registra.
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop3->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba ronda 2).', 'nombre' => 'Cliente Prueba', 'rut' => '', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso3b = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso3b) && in_array($aviso3b['tipo'] ?? '', ['ok', 'aviso'], true), 'RUT vacío sigue siendo válido: la aceptación se registra' . (is_array($aviso3b) ? ': tipo=' . $aviso3b['tipo'] . ' ' . implode(' | ', (array) ($aviso3b['detalles'] ?? [])) : ''));
ok(at_cc_propuesta_por_id($prop3->id)->status === 'aceptada', 'de paso, esta sí quedó aceptada (RUT en blanco es válido)');

// ================= Task 15: aceptación a mano con DNI o pasaporte =================
// Pasaporte inválido (2 caracteres): se rechaza ANTES de guardar evidencias o registrar nada.
$prop15x = crear_propuesta_r2($marca, 'sent', $creadas);
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop15x->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba T15).', 'nombre' => 'Cliente Prueba', 'tipo_documento' => 'pasaporte', 'documento' => 'AB', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso15x = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso15x) && ($aviso15x['tipo'] ?? '') === 'error' && ($aviso15x['texto'] ?? '') === 'El documento no es válido: corrígelo o déjalo en blanco.', 'T15: pasaporte de 2 caracteres: aviso «El documento no es válido: corrígelo o déjalo en blanco.»' . (is_array($aviso15x) ? ': ' . $aviso15x['texto'] : ''));
ok(at_cc_propuesta_por_id($prop15x->id)->status === 'sent' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d", $prop15x->id)) === 0, 'T15: con el documento inválido no se registra nada');
// Tipo desconocido con número: tampoco.
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop15x->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba T15).', 'nombre' => 'Cliente Prueba', 'tipo_documento' => 'cedula', 'documento' => '12345678', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso15y = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso15y) && ($aviso15y['texto'] ?? '') === 'El documento no es válido: corrígelo o déjalo en blanco.' && at_cc_propuesta_por_id($prop15x->id)->status === 'sent', 'T15: tipo de documento desconocido: mismo aviso y no se registra');
// DNI válido: se registra, con el tipo y el número en la metadata y en el contrato.
$prop15 = crear_propuesta_r2($marca, 'sent', $creadas);
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop15->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba T15).', 'nombre' => 'Cliente Prueba', 'tipo_documento' => 'dni', 'documento' => '12.345.678', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso15 = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso15) && in_array($aviso15['tipo'] ?? '', ['ok', 'aviso'], true) && at_cc_propuesta_por_id($prop15->id)->status === 'aceptada', 'T15: aceptación a mano con DNI: se registra' . (is_array($aviso15) ? ': ' . $aviso15['texto'] : ''));
$meta15 = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $prop15->id)), true) ?: [];
ok(($meta15['tipo_documento'] ?? null) === 'dni' && ($meta15['documento'] ?? null) === '12.345.678' && ($meta15['rut'] ?? null) === '', 'T15: la metadata de la aceptación a mano guarda tipo_documento=dni y el número, sin RUT');
$c15 = at_cc_contrato_de_propuesta((int) $prop15->id);
$ph15 = $c15 ? (json_decode((string) $c15->placeholders, true) ?: []) : [];
ok(($ph15['tipo_documento_representante'] ?? '') === 'dni' && ($ph15['representante_cliente_rut'] ?? '') === '12.345.678', 'T15: el contrato de la aceptación a mano recibe el DNI');

// ================= Hallazgo 4: estado inválido para aceptar a mano =================
$prop4 = crear_propuesta_r2($marca, 'contracted', $creadas);
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop4->id, 'canal' => 'whatsapp',
	'nota' => 'Dijo que sí (prueba ronda 2).', 'nombre' => 'Cliente Prueba', 'rut' => '', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso4 = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso4) && ($aviso4['tipo'] ?? '') === 'error' && strpos((string) ($aviso4['texto'] ?? ''), 'no está esperando respuesta') !== false, 'estado "contracted" (transición manual no permitida): aviso "Esta propuesta no está esperando respuesta"' . (is_array($aviso4) ? ': ' . $aviso4['texto'] : ''));
$filas4 = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$det} WHERE propuesta_id = %d", $prop4->id), ARRAY_A);
ok(count($filas4) === 0, 'ninguna nota de Seguimiento se creó: el chequeo de estado corrió antes de llamar a at_cc_registrar_respuesta()');

// Propuesta ya aceptada: aviso "ok" (no un error), y no se repite el registro.
delete_transient('at_cc_aviso_' . $admin_id);
correr_r2('aceptar', [
	'action' => 'at_cc_registrar_aceptacion', 'proposal_id' => (string) $prop3->id, 'canal' => 'whatsapp',
	'nota' => 'Segundo intento (prueba ronda 2).', 'nombre' => 'Cliente Prueba', 'rut' => '', 'fecha' => current_time('Y-m-d'),
	'filas' => ['0'], 'bienvenida' => '0',
], $admin_id);
$aviso4b = get_transient('at_cc_aviso_' . $admin_id);
ok(is_array($aviso4b) && ($aviso4b['tipo'] ?? '') === 'ok' && strpos((string) ($aviso4b['texto'] ?? ''), 'ya estaba aceptada') !== false, 'propuesta ya aceptada: aviso "ok" (no un error), "ya estaba aceptada: no se repitió nada"' . (is_array($aviso4b) ? ': ' . $aviso4b['texto'] : ''));

// ================= Hallazgo 4: at_cc_borrar_evidencias() borra solo lo que corresponde =================
// Prueba directa (sin pasar por admin-post.php): simula lo que quedaría en disco si, pese al chequeo
// de estado de arriba, una carrera entre dos pestañas dejara evidencias guardadas para un registro que
// no se aplicó.
$dir_ev = at_cc_dir_evidencias() . '/999999999';
wp_mkdir_p($dir_ev);
$nombre_valido = bin2hex(random_bytes(12)) . '.jpg';
$nombre_invalido = 'no-calza-el-patron.jpg';
file_put_contents($dir_ev . '/' . $nombre_valido, 'x');
file_put_contents($dir_ev . '/' . $nombre_invalido, 'x');
at_cc_borrar_evidencias(999999999, [['archivo' => $nombre_valido], ['archivo' => $nombre_invalido]]);
ok(!is_file($dir_ev . '/' . $nombre_valido), 'at_cc_borrar_evidencias() borra el archivo con nombre válido');
ok(is_file($dir_ev . '/' . $nombre_invalido), 'at_cc_borrar_evidencias() NO toca un nombre que no calza con el patrón (defensa extra, mismo patrón que at_cc_ver_evidencia())');
@unlink($dir_ev . '/' . $nombre_invalido);
@rmdir($dir_ev);

// Limpieza (mismo patrón que panel-respuesta-wp-test.php: contrato + PDF, Seguimiento, cliente CRM, propuestas)
delete_transient('at_cc_aviso_' . $admin_id);
foreach ($creadas as $pid) {
	delete_transient('at_cc_pedido_' . $pid);
}
foreach ([$prop3, $prop15, $prop15x] as $px) {
	$c3 = at_cc_contrato_de_propuesta((int) $px->id);
	if ($c3) {
		@unlink(ContractService::storage_dir() . '/' . $c3->contract_number . '.pdf');
		$wpdb->query($wpdb->prepare("DELETE FROM " . ContractService::table() . " WHERE id = %d", $c3->id));
	}
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
