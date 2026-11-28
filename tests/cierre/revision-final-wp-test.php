<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/revision-final-wp-test.php
// Revisión final de la rama (26-sep), ronda 1: hallazgos 1 (botón «Notificar Avance»), 2 (candado del
// formulario «Registrar aceptación»), 3 (acciones del CRM con correos repetidos), 4 (guardado de la
// página clásica), 7 («Completar cierre» en el panel) y 11 (capacidad en la ficha operativa por AJAX).
// El panel usa at_pa_estado_etiqueta() (consultas.php) y la página clásica solo se carga en el admin:
// se simula ese contexto, igual que en la ficha real.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-rf-' . strtolower(wp_generate_password(6, false, false));
$crm = $wpdb->prefix . 'crm_clientes';
$tech = $wpdb->prefix . 'automatiza_tech_clients';
$tabla_p = $wpdb->prefix . 'automatiza_propuestas';
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$creadas = [];

$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$admin_id = (int) ($admins[0] ?? 0);
if ($admin_id <= 0) {
	fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
	exit(2);
}
$editor_id = wp_insert_user(['user_login' => $marca . '-editor', 'user_email' => $marca . '-editor@example.com', 'user_pass' => wp_generate_password(24), 'role' => 'editor']);
if (is_wp_error($editor_id)) {
	fwrite(STDERR, 'No se pudo crear el usuario editor de prueba: ' . $editor_id->get_error_message() . "\n");
	exit(2);
}

function rf_crear_propuesta(string $marca, string $status, ?string $flujo, array &$creadas, string $email = ''): object {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $email !== '' ? $email : $marca . '-' . $status . '-' . count($creadas) . '@example.com',
		'unique_link_id' => substr(md5($marca . $status . microtime(true) . count($creadas)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Revisión final', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => $flujo,
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = $id;
	return at_cc_propuesta_por_id($id);
}
function rf_capturar(callable $fn): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}
/** Corre un handler AJAX real: wp_send_json()/wp_die() terminan en una excepción en vez de exit. */
function rf_ajax(callable $fn): string {
	$lanzar = function () { return function () { throw new RuntimeException('wp_die'); }; };
	add_filter('wp_doing_ajax', '__return_true');
	add_filter('wp_die_ajax_handler', $lanzar);
	ob_start();
	try {
		$fn();
	} catch (RuntimeException $e) {
		// Fin normal del handler.
	}
	$out = (string) ob_get_clean();
	remove_filter('wp_die_ajax_handler', $lanzar);
	remove_filter('wp_doing_ajax', '__return_true');
	return $out;
}
/** Corre una acción admin-post (termina en exit) en un proceso aparte; devuelve su salida. */
function rf_accion(string $accion, int $usuario_id, array $post): string {
	$proc = proc_open([PHP_BINARY, __DIR__ . '/revision-final-wp-test-run.php', $accion, (string) $usuario_id, wp_json_encode($post)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($proc)) {
		return '';
	}
	$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	return (string) $out;
}

// ---------- Hallazgo 2: el candado deshabilita solo los botones ----------
$p_env = rf_crear_propuesta($marca, 'sent', 'v3', $creadas);
$h_forms = rf_capturar(function () use ($p_env) { at_cc_render_formularios_respuesta($p_env); });
ok(strpos($h_forms, "querySelectorAll('button[form=\"'+id+'\"], #'+id+' button')") !== false, 'RF2: el candado busca solo botones (button[form=…] y los botones dentro del formulario)');
ok(strpos($h_forms, "querySelectorAll('[form=\"'+id+'\"]')") === false, 'RF2: el candado ya no toma todos los controles con form=… (canal, nota, filas, RUT, evidencia)');
$h_panel_env = rf_capturar(function () use ($p_env) { at_cc_render_panel_respuesta($p_env); });
ok(preg_match_all('/<(select|textarea|input)[^>]*form="at-cc-f-aceptar"/', $h_panel_env) >= 6 && strpos($h_panel_env, '<button type="submit" class="button button-primary" form="at-cc-f-aceptar"') !== false, 'RF2: los campos del registro y su botón siguen apuntando al formulario at-cc-f-aceptar');

// ---------- Hallazgo 7: «Completar cierre» en el panel ----------
// Propuesta aceptada con el cierre a medias: correo inválido, no se pudo pasar a cliente.
$p7 = rf_crear_propuesta($marca, 'sent', 'v3', $creadas, 'correo-invalido-sin-arroba');
$r7 = at_cc_registrar_respuesta($p7, 'acepta', ['canal' => 'pagina', 'nombre' => 'Cliente Prueba', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p7), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => true]);
$p7 = at_cc_propuesta_por_id($p7->id);
ok($p7->status === 'aceptada' && at_cc_cierre_incompleto($p7), 'RF7: precondición: aceptada con el cierre a medias');
$h7 = rf_capturar(function () use ($p7) { at_cc_render_panel_respuesta($p7); });
$f7 = rf_capturar(function () use ($p7) { at_cc_render_formularios_respuesta($p7); });
ok(strpos($h7, 'El cierre quedó a medias') !== false && strpos($h7, 'form="at-cc-f-completar">🔁 Completar cierre</button>') !== false, 'RF7: el panel de una aceptada a medias ofrece «Completar cierre»');
ok(strpos($f7, 'id="at-cc-f-completar"') !== false && strpos($f7, 'value="at_cc_completar_cierre"') !== false && strpos($f7, 'name="_wpnonce"') !== false, 'RF7: el formulario real de «Completar cierre» (admin-post con nonce) está en la ficha');
// Sin permiso: un editor no puede completar.
$wpdb->update($tabla_p, ['client_email' => $marca . '-rf7@example.com'], ['id' => $p7->id]);
$out_editor = rf_accion('completar', (int) $editor_id, ['proposal_id' => $p7->id, 'bienvenida' => '1']);
ok(strpos($out_editor, 'Sin permiso') !== false && at_cc_contrato_de_propuesta((int) $p7->id) === null, 'RF7: un usuario sin manage_options no puede completar el cierre');
// Luis completa (ya corrigió el correo).
$out_admin = rf_accion('completar', $admin_id, ['proposal_id' => $p7->id, 'bienvenida' => '1']);
$p7 = at_cc_propuesta_por_id($p7->id);
$crm7 = at_cc_crm_de_email($p7->client_email);
ok(strpos($out_admin, 'REDIRIGE ') !== false && $crm7 > 0 && at_cc_tech_de_crm($crm7) !== null && at_cc_contrato_de_propuesta((int) $p7->id) !== null, 'RF7: «Completar cierre» pasa a cliente y crea el contrato: ' . trim(substr($out_admin, 0, 300)));
ok(substr_count($out_admin, 'CORREO {"to":"' . $p7->client_email . '"') === 1, 'RF7: «Completar cierre» manda la bienvenida una vez');
$h7b = rf_capturar(function () use ($p7) { at_cc_render_panel_respuesta($p7); });
$f7b = rf_capturar(function () use ($p7) { at_cc_render_formularios_respuesta($p7); });
ok(strpos($h7b, 'Completar cierre') === false && strpos($f7b, 'at-cc-f-completar"') === false, 'RF7: con el cierre completo ya no se ofrece «Completar cierre»');
$out_repite = rf_accion('completar', $admin_id, ['proposal_id' => $p7->id, 'bienvenida' => '1']);
ok(strpos($out_repite, 'CORREO') === false && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . ContractService::table() . " WHERE proposal_id = %d", $p7->id)) === 1, 'RF7: repetir la acción con el cierre completo no manda nada ni crea otro contrato');

// ---------- Hallazgo 3: las acciones del CRM usan la fila donde se apretó el botón ----------
$email3 = $marca . '-dup@example.com';
$wpdb->insert($crm, ['nombre' => 'Fila A', 'email' => $email3, 'tipo' => 'prospecto', 'estado' => 'nuevo']);
$crm_a = (int) $wpdb->insert_id;
$wpdb->insert($crm, ['nombre' => 'Fila B', 'email' => $email3, 'tipo' => 'cliente', 'estado' => 'contratado']);
$crm_b = (int) $wpdb->insert_id;
rf_accion('crear_ficha', $admin_id, ['crm_id' => $crm_b]);
ok(at_cc_tech_de_crm($crm_b) !== null && at_cc_tech_de_crm($crm_a) === null, 'RF3: «Crear ficha operativa» en la fila B enlaza la ficha a B, no a la A de id menor');
ok($wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$crm} WHERE id = %d", $crm_a)) === 'prospecto', 'RF3: «Crear ficha operativa» en B no convierte a A');
$email3c = $marca . '-dupc@example.com';
$wpdb->insert($crm, ['nombre' => 'Fila C', 'email' => $email3c, 'tipo' => 'prospecto', 'estado' => 'nuevo']);
$crm_c = (int) $wpdb->insert_id;
$wpdb->insert($crm, ['nombre' => 'Fila D', 'email' => $email3c, 'tipo' => 'prospecto', 'estado' => 'nuevo']);
$crm_d = (int) $wpdb->insert_id;
wp_set_current_user($admin_id);
$_POST = ['id' => (string) $crm_d, 'nonce' => wp_create_nonce('convertir_cliente_nonce')];
$_REQUEST = $_POST;
$out_conv = rf_ajax(function () { $GLOBALS['at_crm_ai']->ajax_convertir_cliente(); });
ok(strpos($out_conv, 'success') !== false && at_cc_tech_de_crm($crm_d) !== null && at_cc_tech_de_crm($crm_c) === null, 'RF3: «Convertir a Cliente» en la fila D enlaza su ficha a D, no a la C de id menor');
ok($wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$crm} WHERE id = %d", $crm_c)) === 'prospecto', 'RF3: «Convertir a Cliente» en D no convierte a C');

// ---------- Hallazgos 1 y 11: ficha operativa (client-operations-module.php) ----------
$wpdb->insert($tech, ['name' => "O'Brien <b>x</b>", 'email' => $marca . '-ops@example.com', 'contract_status' => 'active']);
$tech_ops = (int) $wpdb->insert_id;
$ops = AutomatizaTech_Client_Operations::get_instance();
$h_modal = rf_capturar(function () use ($ops, $wpdb, $tech, $tech_ops) { $ops->render_full_details_modal($wpdb->get_row($wpdb->prepare("SELECT * FROM {$tech} WHERE id = %d", $tech_ops))); });
ok(strpos($h_modal, 'data-client-name="O&#039;Brien &lt;b&gt;x&lt;/b&gt;"') !== false, 'RF1: el botón «Notificar Avance» lleva el nombre escapado en data-client-name');
ok(preg_match('/onclick="openNotifyProgressModal\(([^"]*)\)"/', $h_modal, $m_onclick) === 1 && strpos($m_onclick[1], 'Brien') === false && strpos($m_onclick[1], "getAttribute('data-client-name')") !== false, 'RF1: el onclick no lleva el nombre: lo lee de data-client-name');
$h_scripts = rf_capturar(function () { AutomatizaTech_Client_Operations::render_scripts(); });
ok(strpos($h_scripts, '${clientName}') === false && strpos($h_scripts, '${clientEmail}') === false && strpos($h_scripts, ".textContent = clientName || ''") !== false, 'RF1: el modal pone nombre y correo con textContent, no como HTML');
// Hallazgo 11: el detalle completo exige manage_options.
wp_set_current_user((int) $editor_id);
$_POST = ['client_id' => (string) $tech_ops, 'nonce' => wp_create_nonce('client_operations_nonce')];
$_REQUEST = $_POST;
$out_ed = rf_ajax(function () use ($ops) { $ops->ajax_get_full_details(); });
$json_ed = json_decode($out_ed, true);
ok(is_array($json_ed) && $json_ed['success'] === false && ($json_ed['data'] ?? '') === 'Sin permisos' && strpos($out_ed, 'Brien') === false, 'RF11: un editor con nonce válido no obtiene la ficha operativa: ' . substr($out_ed, 0, 120));
wp_set_current_user($admin_id);
$_POST = ['client_id' => (string) $tech_ops, 'nonce' => wp_create_nonce('client_operations_nonce')];
$_REQUEST = $_POST;
$out_ad = rf_ajax(function () use ($ops) { $ops->ajax_get_full_details(); });
$json_ad = json_decode($out_ad, true);
ok(is_array($json_ad) && $json_ad['success'] === true && strpos((string) $json_ad['data'], 'Notificar Avance') !== false, 'RF11: un administrador sí la obtiene');
wp_set_current_user(0);
$_POST = [];
$_REQUEST = [];

// ---------- Hallazgo 4: la página clásica no reenvía ni cambia el estado con respuesta del cliente ----------
function rf_clasico_guardar(int $id, bool $enviar): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_GET = [];
	$_POST = [
		'automatiza_proposal_nonce' => wp_create_nonce('save_proposal'),
		'proposal_id' => (string) $id, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Revisión final',
		'phone' => '+56 9 2222 2222', 'client_email' => 'no-cambia@example.com',
		'email_subject' => '', 'email_intro' => '', 'email_highlight' => '', 'email_closing' => '',
		'gamma_url' => '', 'n8n_url' => '',
	];
	if ($enviar) {
		$_POST['send_email'] = '1';
	}
	$_FILES = [];
	$h = rf_capturar('automatiza_tech_proposals_page_clasico');
	$_POST = [];
	$_SERVER['REQUEST_METHOD'] = 'GET';
	return $h;
}
function rf_estado(int $id): string {
	global $wpdb;
	return (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
}
foreach (['aceptada' => 'ya aceptó', 'evaluando' => 'la sigue evaluando', 'rechazada' => 'ya rechazó'] as $estado => $frase) {
	$pc = rf_crear_propuesta($marca . '-clasico', $estado, null, $creadas);
	$correos = [];
	$hc = rf_clasico_guardar((int) $pc->id, true);
	ok(rf_estado((int) $pc->id) === $estado && count($correos) === 0, "RF4: clásico, {$estado} + Guardar con envío marcado: no vuelve a «sent» ni manda correo (" . count($correos) . ')');
	ok(strpos($hc, $frase) !== false, "RF4: clásico, {$estado}: el aviso explica por qué no se envió");
	rf_clasico_guardar((int) $pc->id, false);
	ok(rf_estado((int) $pc->id) === $estado, "RF4: clásico, {$estado} + Guardar sin envío: no pasa a «pending»");
	$_GET = ['edit_id' => (string) $pc->id];
	$he = rf_capturar('automatiza_tech_proposals_page_clasico');
	ok(preg_match('/<input type="checkbox" name="send_email" value="1" id="send_email" disabled/', $he) === 1, "RF4: clásico, {$estado}: la casilla de envío aparece deshabilitada");
}
$ps = rf_crear_propuesta($marca . '-clasico', 'sent', null, $creadas);
$_GET = ['edit_id' => (string) $ps->id];
$hs = rf_capturar('automatiza_tech_proposals_page_clasico');
ok(preg_match('/<input type="checkbox" name="send_email" value="1" id="send_email" checked/', $hs) === 1, 'RF4: clásico, sent sin respuesta: la casilla sigue marcada (sin cambios)');
rf_clasico_guardar((int) $ps->id, false);
ok(rf_estado((int) $ps->id) === 'pending', 'RF4: clásico, sent sin respuesta + Guardar sin envío: sigue pasando a «pending» (sin cambios)');
$_GET = [];

// Limpieza
$ids = implode(',', array_map('intval', $creadas));
$emails = $wpdb->get_col("SELECT client_email FROM {$tabla_p} WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
foreach ((array) $wpdb->get_results("SELECT contract_number FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})") as $cn) {
	@unlink(ContractService::storage_dir() . '/' . $cn->contract_number . '.pdf');
}
$wpdb->query("DELETE FROM " . ContractService::table() . " WHERE proposal_id IN ({$ids})");
$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
$crm_marca = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$crm} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
$todos_crm = array_unique(array_merge(array_map('intval', $crm_ids), array_map('intval', $crm_marca)));
if ($todos_crm) {
	$lista = implode(',', $todos_crm);
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$tech} WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$crm} WHERE id IN ({$lista})");
}
$wpdb->query($wpdb->prepare("DELETE FROM {$tech} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
$wpdb->query("DELETE FROM {$tabla_p} WHERE id IN ({$ids})");
wp_delete_user((int) $editor_id);
fin();
