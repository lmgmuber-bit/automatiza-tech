<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/archivo-wp-test.php
// Task 14 (aprobada por Luis el 26-sep): estado «Archivada». Al desplegar el cierre, toda propuesta
// «enviada» se vuelve aceptable con un clic desde su enlace; las viejas o reemplazadas se archivan:
// el cliente ya no puede responderlas (ni por la página ni por WhatsApp), quedan como historial y Luis
// las puede desarchivar o registrar una aceptación a mano. Las funciones también se llaman desde un
// script CLI del despliegue (sin sesión, usuario_id 0), así que aquí se llaman igual.
// El panel usa at_pa_estado_etiqueta() (consultas.php) y la página clásica solo se carga en el admin:
// se simula ese contexto, igual que en la ficha real.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'contracts/contract-service.php';
global $wpdb;

foreach (['at_cc_puede_archivar', 'at_cc_archivar_propuesta', 'at_cc_desarchivar_propuesta', 'at_cc_accion_archivar', 'at_cc_accion_desarchivar'] as $fn) {
	if (!function_exists($fn)) {
		fwrite(STDERR, "{$fn}() no está definida: revisa cierre-cliente/archivo.php y cargar.php.\n");
		exit(2);
	}
}

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-cierre-archivo-' . strtolower(wp_generate_password(6, false, false));
$tabla_p = $wpdb->prefix . 'automatiza_propuestas';
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$crm = $wpdb->prefix . 'crm_clientes';
$tech = $wpdb->prefix . 'automatiza_tech_clients';
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

function ar_crear(string $marca, string $status, ?string $flujo, array &$creadas): object {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '-' . count($creadas) . '@example.com',
		'unique_link_id' => substr(md5($marca . $status . microtime(true) . count($creadas)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Archivo', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => $flujo,
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = $id;
	return at_cc_propuesta_por_id($id);
}
function ar_estado(int $id): string {
	global $wpdb;
	return (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
}
/** Notas de Seguimiento de la propuesta con ese título, de la más nueva a la más vieja. */
function ar_notas(int $id, string $titulo): array {
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND title = %s ORDER BY id DESC", $id, $titulo));
}
function ar_capturar(callable $fn): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}
function ar_checkbox(string $html, string $name): string {
	return preg_match('/<input type="checkbox" name="' . preg_quote($name, '/') . '"[^>]*>/', $html, $m) ? $m[0] : '';
}
/** Aviso que dejó la acción del proceso hijo. Se vacía la caché en memoria de este proceso: después de
 *  delete_transient(), WordPress anota la opción como inexistente ('notoptions') y get_transient() ya no
 *  volvería a leer la base, donde el hijo sí la escribió. */
function ar_aviso(int $usuario_id) {
	wp_cache_flush();
	return get_transient('at_cc_aviso_' . $usuario_id);
}
/** Corre una acción admin-post (termina en exit) en un proceso aparte; devuelve su salida. */
function ar_accion(string $accion, int $usuario_id, int $propuesta_id, string $modo): string {
	$proc = proc_open([PHP_BINARY, __DIR__ . '/archivo-wp-test-run.php', $accion, (string) $usuario_id, (string) $propuesta_id, $modo], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($proc)) {
		return '';
	}
	$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	return (string) $out;
}

// ================= 1) Archivar una enviada (como el script CLI del despliegue: usuario 0) =================
$p = ar_crear($marca, 'sent', null, $creadas);
$r = at_cc_archivar_propuesta($p, 0);
ok($r['ok'] === true && ar_estado((int) $p->id) === 'archivada', 'archivar una sent: queda archivada: ' . $r['mensaje']);
ok(is_string($r['mensaje']) && $r['mensaje'] !== '', 'archivar devuelve un mensaje para el aviso');
$notas = ar_notas((int) $p->id, 'Propuesta archivada');
ok(count($notas) === 1 && $notas[0]->detail_type === 'aviso_operativo', 'queda una nota «Propuesta archivada» de tipo aviso_operativo');
ok(in_array('aviso_operativo', at_cc_tipos_internos(), true), 'aviso_operativo es un tipo interno: la nota no sale en las vistas públicas');
ok(($notas[0]->description ?? '') === 'El cliente ya no puede responderla desde su enlace; queda como historial.', 'la nota explica qué significa archivar');
$meta = json_decode((string) ($notas[0]->metadata ?? ''), true);
ok(is_array($meta) && ($meta['estado_anterior'] ?? '') === 'sent' && array_key_exists('usuario_id', $meta) && (int) $meta['usuario_id'] === 0, 'metadata: estado_anterior=sent y usuario_id=0');
// Archivar de nuevo: no cambia nada ni repite la nota.
$r2 = at_cc_archivar_propuesta(at_cc_propuesta_por_id((int) $p->id), 0);
ok($r2['ok'] === false && $r2['mensaje'] !== '' && count(ar_notas((int) $p->id, 'Propuesta archivada')) === 1, 'archivar una ya archivada: ok=false con mensaje y sin segunda nota');

// ================= 2) La página pública no dibuja la barra de respuesta =================
$pa = at_cc_propuesta_por_id((int) $p->id);
$barra = ar_capturar(function () use ($pa) { at_cc_render_barra($pa); });
ok($barra === '', 'archivada: at_cc_render_barra() no imprime nada (' . strlen($barra) . ' bytes)');
$control = ar_crear($marca, 'sent', null, $creadas);
$barra_control = ar_capturar(function () use ($control) { at_cc_render_barra($control); });
ok($barra_control !== '' && strpos($barra_control, 'at_cc_responder') !== false, 'control: una sent sí dibuja la barra');

// ================= 3) El cliente no puede responder una archivada (página ni WhatsApp) =================
foreach (['pagina', 'whatsapp'] as $canal) {
	foreach (['acepta', 'evalua', 'rechaza'] as $salida) {
		$correos = [];
		$rr = at_cc_registrar_respuesta(at_cc_propuesta_por_id((int) $p->id), $salida, [
			'canal' => $canal, 'nombre' => 'Cliente Prueba', 'rut' => '11.111.111-1', 'comentario' => 'Prueba sobre archivada',
			'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($p), [0]), 'fecha' => current_time('mysql'), 'bienvenida' => true,
		]);
		ok($rr['ok'] === false && ar_estado((int) $p->id) === 'archivada' && count($correos) === 0, "archivada + {$salida} por {$canal}: no cambia el estado ni manda correos (" . count($correos) . ')');
	}
}
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente'", $p->id)) === 0, 'archivada: no queda ninguna respuesta del cliente en Seguimiento');
ok(at_cc_contrato_de_propuesta((int) $p->id) === null && !at_cc_crm_de_email((string) $p->client_email), 'archivada: no se creó cliente ni contrato');
// El endpoint real del botón de WhatsApp (mismo teléfono, botón «Acepto»).
$req = new WP_REST_Request('POST', '/automatiza/v1/propuesta-respuesta');
$req->set_param('codigo', (string) $p->unique_link_id);
$req->set_param('salida', 'acepta');
$req->set_param('telefono', '+56 9 2222 2222');
$req->set_param('wamid', 'wamid.' . $marca);
$correos = [];
$rw = at_cc_rest_respuesta_whatsapp($req);
ok(is_array($rw) && $rw['ok'] === false && ar_estado((int) $p->id) === 'archivada' && count($correos) === 0, 'archivada + botón «Acepto» del WhatsApp: no se aplica: ' . wp_json_encode($rw));

// ================= 4) Aceptación manual (Luis) desde archivada: cierre completo =================
$pm = ar_crear($marca, 'sent', null, $creadas);
at_cc_archivar_propuesta($pm, 0);
$pm = at_cc_propuesta_por_id((int) $pm->id);
ok($pm->status === 'archivada', 'precondición: la propuesta de la aceptación manual está archivada');
$correos = [];
$rm = at_cc_registrar_respuesta($pm, 'acepta', [
	'canal' => 'manual', 'canal_manual' => 'whatsapp', 'nombre' => 'Cliente Prueba', 'rut' => '11.111.111-1',
	'comentario' => 'Me confirmó por WhatsApp (prueba).', 'filas' => at_cc_filas_aceptadas(at_cc_filas_de_propuesta($pm), [0]),
	'fecha' => current_time('mysql'), 'bienvenida' => true, 'usuario_id' => $admin_id,
]);
$pm2 = at_cc_propuesta_por_id((int) $pm->id);
ok($rm['ok'] === true && $rm['estado'] === 'aceptada' && $pm2->status === 'aceptada', 'archivada + aceptación manual: queda aceptada: ' . $rm['mensaje']);
ok(!empty($rm['crm_id']) && !empty($rm['contrato_id']) && empty($rm['avisos']), 'aceptación manual: cliente y contrato creados sin avisos: ' . implode(' | ', (array) $rm['avisos']));
ok(!at_cc_cierre_incompleto($pm2) && at_cc_tech_de_crm((int) $rm['crm_id']) !== null, 'aceptación manual: el cierre queda completo (ficha única + ficha operativa + contrato)');
ok(count(array_filter($correos, function ($c) use ($pm2) { return ($c['to'] ?? '') === $pm2->client_email; })) === 1, 'aceptación manual: la bienvenida sale una vez al cliente');
// Una aceptada ya no se puede archivar.
$ra = at_cc_archivar_propuesta($pm2, 0);
ok($ra['ok'] === false && $ra['mensaje'] !== '' && ar_estado((int) $pm2->id) === 'aceptada' && count(ar_notas((int) $pm2->id, 'Propuesta archivada')) === 1, 'no se puede archivar una aceptada (ok=false, sigue aceptada, sin nota nueva): ' . $ra['mensaje']);

// ================= 5) Estados que no se archivan =================
foreach (['aceptada', 'contracted', 'ajustando'] as $estado) {
	$q = ar_crear($marca, $estado, $estado === 'ajustando' ? 'v3' : null, $creadas);
	$rq = at_cc_archivar_propuesta($q, 0);
	ok($rq['ok'] === false && $rq['mensaje'] !== '' && ar_estado((int) $q->id) === $estado && count(ar_notas((int) $q->id, 'Propuesta archivada')) === 0, "no se archiva desde {$estado}: " . $rq['mensaje']);
}

// ================= 6) Desarchivar vuelve al estado anterior =================
$rd = at_cc_desarchivar_propuesta(at_cc_propuesta_por_id((int) $p->id), 0);
ok($rd['ok'] === true && ar_estado((int) $p->id) === 'sent', 'desarchivar la de sent: vuelve a sent: ' . $rd['mensaje']);
$nd = ar_notas((int) $p->id, 'Propuesta desarchivada');
ok(count($nd) === 1 && $nd[0]->detail_type === 'aviso_operativo', 'queda una nota «Propuesta desarchivada» de tipo aviso_operativo');
$pp = ar_crear($marca, 'pending', null, $creadas);
at_cc_archivar_propuesta($pp, 0);
$rdp = at_cc_desarchivar_propuesta(at_cc_propuesta_por_id((int) $pp->id), 0);
ok($rdp['ok'] === true && ar_estado((int) $pp->id) === 'pending', 'desarchivar la de pending: vuelve a pending');
// Dos archivados seguidos: manda la ÚLTIMA nota.
$pl = ar_crear($marca, 'lista', 'v3', $creadas);
at_cc_archivar_propuesta($pl, 0);
at_cc_desarchivar_propuesta(at_cc_propuesta_por_id((int) $pl->id), 0);
ok(ar_estado((int) $pl->id) === 'lista', 'desarchivar la de lista: vuelve a lista');
$wpdb->update($tabla_p, ['status' => 'error'], ['id' => $pl->id]);
at_cc_archivar_propuesta(at_cc_propuesta_por_id((int) $pl->id), 0);
at_cc_desarchivar_propuesta(at_cc_propuesta_por_id((int) $pl->id), 0);
ok(ar_estado((int) $pl->id) === 'error', 'archivada dos veces: desarchivar usa la última nota (error, no lista)');
// Archivada sin nota (por ejemplo, a mano en la base): vuelve a sent.
$ps = ar_crear($marca, 'archivada', null, $creadas);
$rds = at_cc_desarchivar_propuesta($ps, 0);
ok($rds['ok'] === true && ar_estado((int) $ps->id) === 'sent', 'archivada sin nota: desarchivar la deja en sent');
// Desarchivar algo que no está archivado: no hace nada.
$pn = ar_crear($marca, 'evaluando', null, $creadas);
$rdn = at_cc_desarchivar_propuesta($pn, 0);
ok($rdn['ok'] === false && $rdn['mensaje'] !== '' && ar_estado((int) $pn->id) === 'evaluando' && count(ar_notas((int) $pn->id, 'Propuesta desarchivada')) === 0, 'desarchivar una no archivada: ok=false y sin cambios');

// ================= 7) Guardar la ficha no cambia el estado ni reenvía =================
function ar_guardar_post(int $id, bool $enviar): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = [
		'automatiza_proposal_nonce' => wp_create_nonce('save_proposal'),
		'proposal_id' => (string) $id, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Archivo',
		'phone' => '+56 9 2222 2222', 'client_email' => 'no-cambia@example.com',
		'email_subject' => '', 'email_intro' => '', 'email_highlight' => '', 'email_closing' => '',
		'gamma_url' => '', 'n8n_url' => '',
	];
	if ($enviar) {
		$_POST['send_email'] = '1';
		$_POST['at_cc_whatsapp'] = '1';
	}
	$_FILES = [];
	$m = at_pa_guardar();
	$_POST = [];
	$_SERVER['REQUEST_METHOD'] = 'GET';
	return $m;
}
wp_set_current_user($admin_id);
$pg = ar_crear($marca, 'sent', null, $creadas);
at_cc_archivar_propuesta($pg, 0);
$correos = [];
$mg = ar_guardar_post((int) $pg->id, true);
ok(ar_estado((int) $pg->id) === 'archivada' && count($correos) === 0, 'archivada + Guardar con envío marcado: sigue archivada y no sale ningún correo (' . count($correos) . ')');
ok(strpos($mg, 'archivada') !== false && strpos($mg, 'Aceptar la propuesta') === false, 'archivada + Guardar con envío marcado: el aviso explica que está archivada: ' . wp_strip_all_tags($mg));
ar_guardar_post((int) $pg->id, false);
ok(ar_estado((int) $pg->id) === 'archivada', 'archivada + Guardar sin envío: no pasa a pending');

// La página clásica (&clasico=1) tiene la misma guarda.
function ar_clasico_guardar(int $id, bool $enviar): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_GET = [];
	$_POST = [
		'automatiza_proposal_nonce' => wp_create_nonce('save_proposal'),
		'proposal_id' => (string) $id, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Archivo',
		'phone' => '+56 9 2222 2222', 'client_email' => 'no-cambia@example.com',
		'email_subject' => '', 'email_intro' => '', 'email_highlight' => '', 'email_closing' => '',
		'gamma_url' => '', 'n8n_url' => '',
	];
	if ($enviar) {
		$_POST['send_email'] = '1';
	}
	$_FILES = [];
	$h = ar_capturar('automatiza_tech_proposals_page_clasico');
	$_POST = [];
	$_SERVER['REQUEST_METHOD'] = 'GET';
	return $h;
}
$correos = [];
$hc = ar_clasico_guardar((int) $pg->id, true);
ok(ar_estado((int) $pg->id) === 'archivada' && count($correos) === 0, 'clásico, archivada + Guardar con envío marcado: sigue archivada y sin correo (' . count($correos) . ')');
ok(strpos($hc, 'está archivada') !== false, 'clásico, archivada: el aviso explica por qué no se envió');
ar_clasico_guardar((int) $pg->id, false);
ok(ar_estado((int) $pg->id) === 'archivada', 'clásico, archivada + Guardar sin envío: no pasa a pending');
$_GET = ['edit_id' => (string) $pg->id];
$he = ar_capturar('automatiza_tech_proposals_page_clasico');
$_GET = [];
$cb_clasico = ar_checkbox($he, 'send_email');
ok(strpos($cb_clasico, 'disabled') !== false && strpos($cb_clasico, 'checked') === false, 'clásico, archivada: la casilla de envío sale deshabilitada y sin marcar: ' . $cb_clasico);
ok(strpos($he, 'Esta propuesta está archivada: desarchívala para volver a enviarla.') !== false, 'clásico, archivada: muestra el aviso de archivada');

// ================= 8) Ficha: casillas de envío y bloque «Respuesta del cliente» =================
foreach ([null, 'v3'] as $flujo) {
	$pf = ar_crear($marca, 'sent', $flujo, $creadas);
	at_cc_archivar_propuesta($pf, 0);
	$pf = at_cc_propuesta_por_id((int) $pf->id);
	$hf = ar_capturar(function () use ($pf) { at_pa_render_ficha($pf, ''); });
	$et = $flujo ?? 'clásica';
	$cb_correo = ar_checkbox($hf, 'send_email');
	$cb_wa = ar_checkbox($hf, 'at_cc_whatsapp');
	ok(strpos($cb_correo, 'disabled') !== false && strpos($cb_correo, 'checked') === false, "ficha {$et}, archivada: send_email deshabilitado y sin marcar: {$cb_correo}");
	ok(strpos($cb_wa, 'disabled') !== false && strpos($cb_wa, 'checked') === false, "ficha {$et}, archivada: at_cc_whatsapp deshabilitado y sin marcar: {$cb_wa}");
	ok(strpos($hf, 'Esta propuesta está archivada: desarchívala para volver a enviarla.') !== false, "ficha {$et}, archivada: aviso de archivada");
	ok(strpos($hf, 'Esta propuesta ya tiene respuesta del cliente') === false && strpos($hf, 'Se habilita cuando la propuesta esté') === false, "ficha {$et}, archivada: no mezcla los otros avisos");
	ok(strpos($hf, 'id="at-cc-f-desarchivar"') !== false, "ficha {$et}, archivada: el formulario de Desarchivar va en la ficha (fuera del formulario principal)");
}

$pan = at_cc_propuesta_por_id((int) $pg->id);
$h_arch = ar_capturar(function () use ($pan) { at_cc_render_panel_respuesta($pan); });
ok(strpos($h_arch, 'Estado: <strong>Archivada</strong>') !== false, 'panel, archivada: «Estado: Archivada»');
ok(strpos($h_arch, 'Archivada: el cliente ya no puede responderla. Queda como historial.') !== false, 'panel, archivada: explica qué significa');
ok(preg_match('/<button type="submit" class="button[^"]*" form="at-cc-f-desarchivar"[^>]*>[^<]*Desarchivar<\/button>/', $h_arch) === 1, 'panel, archivada: botón «Desarchivar» apuntando a su formulario');
ok(strpos($h_arch, 'Registrar aceptación a mano') !== false && strpos($h_arch, 'form="at-cc-f-aceptar"') !== false, 'panel, archivada: sigue el registro de aceptación a mano');
ok(strpos($h_arch, 'form="at-cc-f-archivar"') === false && strpos($h_arch, 'form="at-cc-f-pedir"') === false && strpos($h_arch, 'wa.me') === false, 'panel, archivada: sin Archivar, sin «Pedir respuesta» y sin WhatsApp de aceptar');
$f_arch = ar_capturar(function () use ($pan) { at_cc_render_formularios_respuesta($pan); });
ok(preg_match('/<form id="at-cc-f-desarchivar"[^>]*>.*value="at_cc_desarchivar".*name="_wpnonce".*<\/form>/sU', $f_arch) === 1, 'formularios, archivada: at-cc-f-desarchivar con acción y nonce');
ok(strpos($f_arch, 'id="at-cc-f-archivar"') === false, 'formularios, archivada: sin formulario de archivar');
ok(strpos($f_arch, "candado('at-cc-f-desarchivar')") !== false && strpos($f_arch, "candado('at-cc-f-archivar')") !== false, 'formularios: el candado contra doble envío cubre archivar y desarchivar');

$h_sent = ar_capturar(function () use ($control) { at_cc_render_panel_respuesta($control); });
ok(preg_match('/<button type="submit" class="button button-secondary" form="at-cc-f-archivar"[^>]*>🗄️ Archivar<\/button>/u', $h_sent) === 1, 'panel, sent: botón secundario «🗄️ Archivar»');
ok(strpos($h_sent, '¿Archivar esta propuesta? El cliente ya no podrá responderla desde su enlace. Puedes desarchivarla cuando quieras.') !== false, 'panel, sent: Archivar pide confirmación con el texto exacto');
$f_sent = ar_capturar(function () use ($control) { at_cc_render_formularios_respuesta($control); });
ok(preg_match('/<form id="at-cc-f-archivar"[^>]*>.*value="at_cc_archivar".*name="_wpnonce".*<\/form>/sU', $f_sent) === 1 && strpos($f_sent, 'id="at-cc-f-desarchivar"') === false, 'formularios, sent: at-cc-f-archivar con acción y nonce, sin desarchivar');
foreach (['borrador', 'error', 'lista', 'pending'] as $estado) {
	$qx = ar_crear($marca, $estado, 'v3', $creadas);
	$hx = ar_capturar(function () use ($qx) { at_cc_render_panel_respuesta($qx); });
	ok(strpos($hx, 'form="at-cc-f-archivar"') !== false, "panel, {$estado}: ofrece Archivar");
}
foreach (['ajustando', 'generando'] as $estado) {
	$qx = ar_crear($marca, $estado, 'v3', $creadas);
	$hx = ar_capturar(function () use ($qx) { at_cc_render_panel_respuesta($qx); });
	$fx = ar_capturar(function () use ($qx) { at_cc_render_formularios_respuesta($qx); });
	ok(strpos($hx, 'form="at-cc-f-archivar"') === false && strpos($fx, 'id="at-cc-f-archivar"') === false, "panel, {$estado}: no ofrece Archivar");
}
$h_acep = ar_capturar(function () use ($pm2) { at_cc_render_panel_respuesta($pm2); });
ok(strpos($h_acep, 'at-cc-f-archivar') === false && strpos($h_acep, 'Desarchivar') === false, 'panel, aceptada: ni Archivar ni Desarchivar');

// ================= 9) Lista: vista «Archivadas» e insignia gris =================
ok(at_pa_estado_etiqueta('archivada') === ['etiqueta' => 'Archivada', 'clase' => 'at-estado--archivadas'], 'lista: etiqueta Archivada con su clase');
$css = (string) file_get_contents(get_template_directory() . '/assets/css/propuestas-admin.css');
ok(preg_match('/\.at-estado--archivadas\s*\{[^}]*background:\s*#[0-9a-f]{6}/i', $css) === 1, 'CSS: la insignia .at-estado--archivadas tiene su color');

// ================= 10) Acciones admin-post: nonce y capacidad =================
$pk = ar_crear($marca, 'sent', null, $creadas);
$out_malo = ar_accion('archivar', $admin_id, (int) $pk->id, 'malo');
ok(ar_estado((int) $pk->id) === 'sent' && strpos($out_malo, 'REDIRIGE') === false, 'archivar con nonce inventado: no hace nada');
$out_cruz = ar_accion('archivar', $admin_id, (int) $pk->id, 'cruzado');
ok(ar_estado((int) $pk->id) === 'sent' && strpos($out_cruz, 'REDIRIGE') === false, 'archivar con el nonce de desarchivar: no hace nada');
$out_ed = ar_accion('archivar', (int) $editor_id, (int) $pk->id, 'valido');
ok(ar_estado((int) $pk->id) === 'sent' && strpos($out_ed, 'Sin permiso') !== false, 'archivar como editor (sin manage_options): «Sin permiso» y no hace nada');
delete_transient('at_cc_aviso_' . $admin_id);
$out_ad = ar_accion('archivar', $admin_id, (int) $pk->id, 'valido');
ok(ar_estado((int) $pk->id) === 'archivada', 'archivar como administrador con nonce: queda archivada: ' . trim(substr($out_ad, 0, 300)));
ok(strpos($out_ad, 'REDIRIGE ') !== false && strpos($out_ad, 'edit_id=' . (int) $pk->id) !== false && strpos($out_ad, 'tab=envio') !== false, 'archivar: vuelve a la ficha, pestaña Envío');
$av = ar_aviso($admin_id);
ok(is_array($av) && (int) ($av['propuesta_id'] ?? 0) === (int) $pk->id && ($av['tipo'] ?? '') === 'ok' && ($av['texto'] ?? '') !== '', 'archivar: deja el aviso verde para la ficha');
$nk = ar_notas((int) $pk->id, 'Propuesta archivada');
$mk = json_decode((string) ($nk[0]->metadata ?? ''), true);
ok(count($nk) === 1 && (int) ($mk['usuario_id'] ?? 0) === $admin_id, 'archivar desde el panel: la nota guarda quién archivó');

$out_dmalo = ar_accion('desarchivar', $admin_id, (int) $pk->id, 'malo');
ok(ar_estado((int) $pk->id) === 'archivada' && strpos($out_dmalo, 'REDIRIGE') === false, 'desarchivar con nonce inventado: no hace nada');
$out_dcruz = ar_accion('desarchivar', $admin_id, (int) $pk->id, 'cruzado');
ok(ar_estado((int) $pk->id) === 'archivada' && strpos($out_dcruz, 'REDIRIGE') === false, 'desarchivar con el nonce de archivar: no hace nada');
$out_ded = ar_accion('desarchivar', (int) $editor_id, (int) $pk->id, 'valido');
ok(ar_estado((int) $pk->id) === 'archivada' && strpos($out_ded, 'Sin permiso') !== false, 'desarchivar como editor: «Sin permiso» y no hace nada');
delete_transient('at_cc_aviso_' . $admin_id);
$out_dad = ar_accion('desarchivar', $admin_id, (int) $pk->id, 'valido');
ok(ar_estado((int) $pk->id) === 'sent' && strpos($out_dad, 'REDIRIGE ') !== false && strpos($out_dad, 'tab=envio') !== false, 'desarchivar como administrador con nonce: vuelve a sent y a la ficha');
$avd = ar_aviso($admin_id);
ok(is_array($avd) && (int) ($avd['propuesta_id'] ?? 0) === (int) $pk->id && ($avd['tipo'] ?? '') === 'ok', 'desarchivar: deja el aviso verde para la ficha');
// Archivar una aceptada desde el panel: aviso rojo, sin cambios.
delete_transient('at_cc_aviso_' . $admin_id);
$out_acep = ar_accion('archivar', $admin_id, (int) $pm2->id, 'valido');
$ave = ar_aviso($admin_id);
ok(ar_estado((int) $pm2->id) === 'aceptada' && is_array($ave) && ($ave['tipo'] ?? '') === 'error', 'archivar una aceptada desde el panel: aviso de error y sigue aceptada');
delete_transient('at_cc_aviso_' . $admin_id);

// Limpieza (mismo patrón que revision-final-wp-test.php)
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
