<?php
// Panel de Retomar (fase 2): sin «Activar», textos opcionales con notas para la IA, configuración del resumen semanal,
// «Enviarme el resumen ahora», «Volver a redactar», «Reanudar», vincular la propuesta nueva y el botón de la ficha CRM.
// Solo contra el sitio local de prueba. Nada sale a internet: el correo y el HTTP quedan capturados.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_panel', 'at_rt_panel_ejecutar', 'at_rt_panel_guardar', 'at_rt_boton_ficha', 'at_rt_panel_revision');
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', bin2hex(random_bytes(24))); }
global $wpdb;
at_rt_migrar_esquema();
$ids = []; $props = []; $users = []; $crms = []; $emails = [];
$tag = 'rt-panel-' . strtolower(wp_generate_password(9, false, false));
$GLOBALS['rt_pn_prefijo'] = $tag;
$originales = [];
foreach (['at_rt_resumen_dia', 'at_rt_resumen_hora', 'at_rt_resumen_destino', 'at_rt_resumen_semana', 'at_rt_resumen_desde', 'at_rt_wa_automatico'] as $o) { $originales[$o] = get_option($o, null); }
register_shutdown_function(static function () use (&$ids, &$props, &$users, &$crms, &$emails, $originales, $tag, $wpdb) {
	foreach ($originales as $o => $v) { $v === null ? delete_option($o) : update_option($o, $v); }
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}at_retomar WHERE email LIKE %s", $wpdb->esc_like($tag) . '%'));
	foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
	foreach ($props as $id) { $wpdb->delete($wpdb->prefix . 'automatiza_propuestas_details', ['propuesta_id' => $id]); $wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $id]); }
	foreach ($crms as $id) { $wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $id]); $wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $id]); }
	foreach ($emails as $email) { $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['email' => $email]); }
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}at_retomar_bajas WHERE email LIKE %s", $wpdb->esc_like($tag) . '%'));
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ($users as $id) { wp_delete_user($id); }
});
// El resumen y el panel leen toda la base local: se acotan a los correos de esta prueba.
add_filter('query', static function ($sql) use ($wpdb) {
	if ($sql === "SELECT * FROM {$wpdb->prefix}at_retomar ORDER BY id DESC") {
		return "SELECT * FROM {$wpdb->prefix}at_retomar WHERE email LIKE '" . esc_sql($wpdb->esc_like($GLOBALS['rt_pn_prefijo'])) . "%' ORDER BY id DESC";
	}
	return $sql;
});
// Correo y HTTP capturados: solo los dos flujos de n8n (cuando existan sus constantes) responden, y se anota cada pedido.
$mails = []; $falla_mail = false; $pedidos_redaccion = []; $pedidos_render = []; $falla_n8n = false;
add_filter('pre_wp_mail', static function ($v, $d) use (&$mails, &$falla_mail) {
	if (!$falla_mail) { $mails[] = $d; }
	return !$falla_mail;
}, 100, 2);
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$pedidos_redaccion, &$pedidos_render, &$falla_n8n) {
	$cuerpo = json_decode((string) ($args['body'] ?? ''), true);
	if (defined('AT_N8N_RETOMAR_REDACTAR') && $url === AT_N8N_RETOMAR_REDACTAR) { $pedidos_redaccion[] = $cuerpo; return ['response' => ['code' => $falla_n8n ? 500 : 200], 'body' => '{}', 'headers' => []]; }
	if (defined('AT_N8N_RETOMAR_RENDER') && $url === AT_N8N_RETOMAR_RENDER) { $pedidos_render[] = $cuerpo; return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []]; }
	return $pre;
}, PHP_INT_MAX, 3);
$admin = wp_insert_user(['user_login' => $tag, 'user_pass' => wp_generate_password(), 'user_email' => $tag . '@example.com', 'role' => 'administrator']);
$users[] = $admin;
wp_set_current_user($admin);
$p = ['nombre' => '[PRUEBA] Panel', 'negocio' => '[PRUEBA] Negocio', 'email' => $tag . '@example.com', 'telefono' => '+56900000000', 'quedamos' => '[PRUEBA] Conversamos', 'proponemos' => '[PRUEBA] Demo', 'partir' => '[PRUEBA] Primero', 'max_envios' => 4, 'intervalo_dias' => 3];
$emails[] = $p['email'];
$vacios = ['quedamos' => '', 'proponemos' => '', 'partir' => ''];
function panel_action($a, $id, $p = []) { return at_rt_panel_ejecutar($a, $p + ['id' => $id, '_wpnonce' => wp_create_nonce('at_rt_' . $a . '_' . $id)]); }
function rt_pn_hoy(): string { return substr(at_rt_ahora_chile(), 0, 10); }
function rt_pn_email(): string { global $tag, $ids; return $tag . '-' . count($ids) . '@example.com'; }
/** Seguimiento de prueba; $c pisa el alta y $despues se guarda al final. */
function rt_pn_seg(array $c = [], array $despues = []): int {
	global $ids;
	$id = at_rt_crear($c + ['nombre' => '[PRUEBA] Persona ' . count($ids), 'negocio' => '[PRUEBA] Negocio', 'email' => rt_pn_email(), 'telefono' => '', 'estado' => 'activo', 'proximo_at' => '2026-10-01', 'activado_at' => '2026-09-01 00:00:00',
		'textos' => ['quedamos' => '[PRUEBA] Quedamos en retomar la agenda.', 'proponemos' => '[PRUEBA] Proponemos partir simple.', 'partir' => '[PRUEBA] Partimos con una reunión corta.', 'recomendacion' => '[PRUEBA] Recomendamos la agenda.']]);
	$ids[] = $id;
	if ($despues) { at_rt_guardar($id, $despues); }
	return $id;
}
/** Seguimiento con redacción `lista` y presentación `listo` con su huella real (lo que el resumen puede ofrecer para aprobar). */
function rt_pn_lista(array $c = [], array $despues = []): int {
	$id = rt_pn_seg($c);
	$s = at_rt_obtener($id);
	$render = ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/index.html'];
	at_rt_guardar($id, ['redaccion' => ['estado' => 'lista', 'origen' => 'ia', 'nota' => ''], 'render' => $render]);
	$render['huella'] = at_rt_huella_render(at_rt_obtener($id));
	at_rt_guardar($id, ['render' => $render] + $despues);
	return $id;
}
function rt_pn_prop(array $c = []): int {
	global $wpdb, $props, $tag;
	$n = count($props);
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', $c + ['client_email' => $tag . '-prop' . $n . '@example.com', 'unique_link_id' => substr(md5($tag . '-prop-' . $n), 0, 12), 'client_name' => '[PRUEBA] Cliente', 'company_name' => '[PRUEBA] Negocio', 'phone' => '', 'status' => 'sent', 'flujo' => 'v2', 'gamma_prompt_text' => '', 'system_prompt_text' => '', 'created_at' => '2026-09-09 10:00:00']);
	return $props[] = (int) $wpdb->insert_id;
}
function rt_pn_pagina(array $get): string { $_GET = $get; ob_start(); at_rt_panel(); return ob_get_clean(); }

// ---- Sin «Activar»: guardar es lo que deja el seguimiento activo ----
$hoy = rt_pn_hoy();
$r = panel_action('activar', 0);
ok(is_wp_error($r) && $r->get_error_code() === 'accion', 'activar ya no existe: error accion');
// Sin flujos configurados (las constantes de n8n se definen más abajo): no se pide nada y se dice claro.
$r = panel_action('guardar', 0, array_replace($p, $vacios + ['notas' => '[PRUEBA] Nota para la IA']));
ok(!is_wp_error($r), 'guardar sin textos crea el seguimiento');
$id = $r['id']; $ids[] = $id;
$s = at_rt_obtener($id);
ok($s['estado'] === 'activo' && substr($s['activado_at'], 0, 10) === $hoy && $s['proximo_at'] === $hoy, 'guardar sin textos: activo, activado_at y proximo_at de hoy');
ok($s['intervalo_dias'] === 7 && $s['textos'] === [] && $s['redaccion'] === [], 'intervalo 3 se ajusta a 7; sin textos y sin redacción pedida (sin flujo)');
ok(str_contains($r['mensaje'], 'redacción') && str_contains($r['mensaje'], 'no está configurado'), 'sin flujo de redacción lo dice (texto a mano)');
ok($s['notas'] === '[PRUEBA] Nota para la IA', 'notas para la IA se guardan');
ok($s['render']['estado'] === 'pendiente', 'sin textos la presentación queda pendiente');
// Dos de tres: error y nada cambia.
foreach ([['quedamos' => '', 'proponemos' => 'x', 'partir' => 'y'], ['quedamos' => 'x', 'proponemos' => 'y', 'partir' => '']] as $dos) {
	$antes = at_rt_obtener($id);
	$r = panel_action('guardar', $id, array_replace($p, $dos));
	ok(is_wp_error($r) && str_contains($r->get_error_message(), 'Completa los tres textos o deja los tres vacíos') && at_rt_obtener($id) === $antes, 'con dos de tres textos: error y nada cambia');
}
// Notas: solo los últimos 4000 caracteres.
$r = panel_action('guardar', $id, array_replace($p, $vacios + ['notas' => str_repeat('a', 500) . str_repeat('b', 4000)]));
ok(!is_wp_error($r) && at_rt_obtener($id)['notas'] === str_repeat('b', 4000), 'notas se acotan a sus últimos 4000 caracteres');
$r = panel_action('guardar', $id, array_replace($p, $vacios));
ok(!is_wp_error($r) && at_rt_obtener($id)['notas'] === str_repeat('b', 4000), 'guardar sin enviar «notas» conserva las notas');
// Con los tres textos: lista a mano, sin redacción; la presentación queda pendiente porque no hay flujo.
$r = panel_action('guardar', $id, $p);
$s = at_rt_obtener($id);
ok(!is_wp_error($r) && $s['redaccion']['estado'] === 'lista' && $s['redaccion']['origen'] === 'manual' && preg_match('/^[a-f0-9]{16}$/D', $s['redaccion']['generacion']), 'con los tres textos: redaccion lista, origen manual y generación nueva');
ok(array_keys($s['textos']) === ['quedamos', 'proponemos', 'partir'] && $s['estado'] === 'activo', 'tres claves de textos sin proyecto obligatorio y sigue activo');
ok($s['render']['estado'] === 'pendiente' && !array_intersect(['generacion', 'huella', 'uid', 'view_url', 'pdf_url'], array_keys($s['render'])), 'guardar invalida la presentación anterior (sin flujo queda pendiente)');
// Un guardado no cambia el estado de quien está respondido/pausado y siempre invalida la presentación.
$view = 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/';
at_rt_guardar($id, ['estado' => 'respondio', 'proximo_at' => null, 'render' => ['estado' => 'listo', 'generacion' => str_repeat('a', 16), 'huella' => str_repeat('b', 64), 'uid' => $s['token'] . '-' . str_repeat('a', 16) . '-retomar', 'view_url' => $view, 'pdf_url' => $view . 'presentacion.pdf']]);
$r = panel_action('guardar', $id, array_replace($p, ['estado' => 'activo', 'envios' => []]));
ok(!is_wp_error($r) && at_rt_obtener($id)['estado'] === 'respondio' && at_rt_obtener($id)['proximo_at'] === null && at_rt_obtener($id)['render']['estado'] === 'pendiente', 'guardar ignora estado enviado por POST y no activa a quien respondió');
// Prueba al equipo: sin presentación lista no sale; con ella, no suma envíos.
$antes = at_rt_obtener($id)['envios'];
at_rt_guardar($id, ['render' => ['estado' => 'listo', 'view_url' => $view]]);
$r = panel_action('prueba', $id);
ok(!is_wp_error($r) && at_rt_obtener($id)['envios'] === $antes && count($mails) === 1, 'prueba manda solo al equipo y no suma envíos');
$r = panel_action('respondio', $id);
ok(!is_wp_error($r) && at_rt_obtener($id)['estado'] === 'respondio' && at_rt_obtener($id)['proximo_at'] === null, 'Respondió detiene el seguimiento');

// ---- Nonce y permisos de todas las acciones ----
$acciones = ['guardar', 'previa', 'prueba', 'pausar', 'respondio', 'baja', 'revertir_baja', 'whatsapp_manual', 'config', 'resumen', 'redactar', 'reanudar'];
foreach ($acciones as $a) {
	$antes = at_rt_obtener($id);
	$r = at_rt_panel_ejecutar($a, ['id' => $id, '_wpnonce' => 'invalido', 'dia' => '2', 'hora' => '07:00']);
	ok(is_wp_error($r) && $r->get_error_code() === 'nonce' && at_rt_obtener($id) === $antes, $a . ' nonce inválido no muta');
}
ok(get_option('at_rt_resumen_dia', null) === ($originales['at_rt_resumen_dia']) && count($mails) === 1, 'ni config ni resumen hicieron nada con nonce inválido');
wp_set_current_user(0);
foreach ($acciones as $a) {
	$antes = at_rt_obtener($id);
	$r = panel_action($a, $id, $p);
	ok(is_wp_error($r) && $r->get_error_code() === 'permiso' && at_rt_obtener($id) === $antes, $a . ' exige manage_options en backend');
}
wp_set_current_user($admin);
foreach ($acciones as $a) { ok(has_action('admin_post_at_rt_' . $a) !== false, 'admin_post registrado ' . $a); }
ok(has_action('admin_post_at_rt_activar') === false, 'ya no hay admin_post de activar');

// ---- Paradas al guardar: quien no puede recibir nada queda en borrador, con el motivo ----
$pb = array_replace($p, $vacios + ['email' => $tag . '-rechazada@example.com']);
$rid = rt_pn_prop(['client_email' => $pb['email'], 'status' => 'rechazada']);
$r = panel_action('guardar', 0, array_replace($pb, ['propuesta_id' => $rid]));
$ids[] = $r['id'];
ok(!is_wp_error($r) && at_rt_obtener($r['id'])['estado'] === 'borrador' && str_contains($r['mensaje'], 'rechazada'), 'propuesta rechazada: se guarda como borrador con aviso claro');
$r = panel_action('guardar', 0, array_replace($pb, ['email' => '']));
$ids[] = $r['id'];
ok(!is_wp_error($r) && at_rt_obtener($r['id'])['estado'] === 'borrador' && str_contains($r['mensaje'], 'correo'), 'sin correo: borrador con aviso');
$bajaid = rt_pn_seg(['email' => $tag . '-baja@example.com', 'telefono' => ''], ['estado' => 'borrador', 'proximo_at' => null]);
$r = panel_action('baja', $bajaid);
ok(!is_wp_error($r) && at_rt_en_bajas($tag . '-baja@example.com', ''), 'baja registra supresión');
$r = panel_action('guardar', 0, array_replace($p, $vacios + ['email' => $tag . '-baja@example.com', 'telefono' => '']));
$ids[] = $r['id'];
ok(!is_wp_error($r) && at_rt_obtener($r['id'])['estado'] === 'borrador' && str_contains($r['mensaje'], 'bajas'), 'contacto en bajas: borrador con aviso');
ok(is_wp_error(panel_action('revertir_baja', $bajaid, ['motivo' => ''])), 'revertir baja exige motivo');
ok(!is_wp_error(panel_action('revertir_baja', $bajaid, ['motivo' => '[PRUEBA] Solicitud registrada'])) && !at_rt_en_bajas($tag . '-baja@example.com', '') && at_rt_obtener($bajaid)['estado'] === 'baja', 'revertir no reactiva');
foreach (['baja', 'rechazado', 'cliente', 'agendo', 'agotado'] as $cerrado) {
	$cid = rt_pn_seg([], ['estado' => $cerrado, 'proximo_at' => null]);
	$antes = at_rt_obtener($cid);
	$r = panel_action('reanudar', $cid);
	ok(is_wp_error($r) && at_rt_obtener($cid) === $antes, 'reanudar no reanuda a quien está en ' . $cerrado);
}

// ---- Validaciones del formulario ----
$antes = at_rt_obtener($id);
foreach ([['nombre' => str_repeat('á', 121)], ['quedamos' => str_repeat('á', 601)], ['partir' => implode("\n", array_fill(0, 7, 'a'))], ['demo_1' => 'http://example.com/demo'], ['demo_2' => 'https://oculto.easypanel.host/demo'], ['intervalo_dias' => 256], ['max_envios' => 5], ['propuesta_id' => PHP_INT_MAX], ['crm_cliente_id' => PHP_INT_MAX]] as $bad) {
	$r = panel_action('guardar', $id, array_replace($p, $bad));
	ok(is_wp_error($r) && at_rt_obtener($id) === $antes, 'validación rechaza sin truncar ni modificar');
}
$r = panel_action('guardar', $id, array_replace($p, ['crear_crm' => 1]));
if (!is_wp_error($r)) { $crms[] = at_rt_obtener($id)['crm_cliente_id']; }
ok(!is_wp_error($r) && $wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$wpdb->prefix}crm_clientes WHERE id=%d", end($crms))) === 'prospecto', 'crear CRM solo con flag explícito y tipo prospecto');
$pid = rt_pn_prop(['client_email' => 'otro@example.com']);
$antes = at_rt_obtener($id);
$r = panel_action('guardar', $id, array_replace($p, ['propuesta_id' => $pid]));
ok(is_wp_error($r) && at_rt_obtener($id) === $antes, 'propuesta de otro correo no se vincula');
$r = panel_action('guardar', $id, array_replace($p, ['imagen_url' => 'https://example.com/' . str_repeat('a', 500)]));
ok(is_wp_error($r) && at_rt_obtener($id) === $antes, 'URL excede columna 500 sin truncado silencioso');

// ---- Compartir a mano por WhatsApp: deja rastro, no cuenta como envío ni mueve el próximo ----
$rm = panel_action('guardar', 0, array_replace($p, ['email' => $tag . '-manual@example.com', 'telefono' => '+56 9 0000 0000']));
$mid = $rm['id']; $ids[] = $mid;
at_rt_guardar($mid, ['proximo_at' => '2026-11-02']);
$antes = at_rt_obtener($mid);
$r = panel_action('whatsapp_manual', $mid);
$desp = at_rt_obtener($mid); $ult = end($desp['envios']);
ok(!is_wp_error($r) && str_starts_with($r['url'] ?? '', 'https://wa.me/56900000000?text='), 'compartir a mano devuelve wa.me del prospecto');
ok(is_array($ult) && $ult['tipo'] === 'manual' && $ult['canal'] === 'whatsapp', 'compartir a mano queda anotado');
ok(at_rt_envios_realizados($desp['envios']) === at_rt_envios_realizados($antes['envios']) && $desp['proximo_at'] === '2026-11-02', 'compartir a mano no suma envío ni mueve el próximo');
at_rt_guardar($mid, ['telefono' => '']);
ok(is_wp_error(panel_action('whatsapp_manual', $mid)), 'sin teléfono avisa en vez de abrir WhatsApp');

// ---- Reanudar: activo, hoy, aprobación muerta; nunca envía ----
$rid1 = rt_pn_lista([], ['estado' => 'pausado', 'proximo_at' => null, 'aprobacion' => ['estado' => 'aprobado', 'n' => 1, 'nonce' => str_repeat('a', 16), 'huella' => str_repeat('b', 64), 'usada' => true, 'programado' => '2026-10-20']]);
$mails_antes = count($mails);
$r = panel_action('reanudar', $rid1);
$s = at_rt_obtener($rid1);
ok(!is_wp_error($r) && $s['estado'] === 'activo' && $s['proximo_at'] === $hoy && substr($s['activado_at'], 0, 10) === $hoy, 'reanudar: activo con próximo envío hoy');
ok($s['aprobacion']['estado'] === 'por_aprobar' && $s['aprobacion']['usada'] === true && !isset($s['aprobacion']['programado']), 'reanudar deja la aprobación muerta (por_aprobar, usada, sin día programado)');
ok(count($mails) === $mails_antes && at_rt_envios_realizados($s['envios']) === 0, 'reanudar no envía nada por sí mismo');
$rid2 = rt_pn_lista([], ['estado' => 'respondio', 'proximo_at' => null]);
ok(!is_wp_error(panel_action('reanudar', $rid2)) && at_rt_obtener($rid2)['estado'] === 'activo', 'reanudar también sirve para quien había respondido');
$rid3 = rt_pn_seg([], ['estado' => 'activo']);
ok(is_wp_error(panel_action('reanudar', $rid3)), 'reanudar solo para pausado o respondió');
$rprop = rt_pn_prop(['status' => 'rechazada']);
$rid4 = rt_pn_seg([], ['estado' => 'pausado', 'proximo_at' => null, 'propuesta_id' => $rprop]);
$antes = at_rt_obtener($rid4);
$r = panel_action('reanudar', $rid4);
ok(is_wp_error($r) && str_contains($r->get_error_message(), 'rechazada') && at_rt_obtener($rid4) === $antes, 'reanudar con propuesta rechazada: error y nada cambia');

// ---- Configuración del resumen semanal ----
$destino = $tag . '-luis@example.com';
$r = panel_action('config', 0, ['dia' => '3', 'hora' => '08:30', 'destino' => $destino]);
ok(!is_wp_error($r) && get_option('at_rt_resumen_dia') === '3' && get_option('at_rt_resumen_hora') === '08:30' && get_option('at_rt_resumen_destino') === $destino && at_rt_config() === ['dia' => 3, 'hora' => '08:30', 'destino' => $destino], 'config guarda día, hora y destino');
foreach ([['dia' => '3', 'hora' => '25:00', 'destino' => $destino], ['dia' => '3', 'hora' => '8:30', 'destino' => $destino], ['dia' => '8', 'hora' => '08:30', 'destino' => $destino], ['dia' => '0', 'hora' => '08:30', 'destino' => $destino], ['dia' => '3', 'hora' => '09:00', 'destino' => 'no-es-correo']] as $mala) {
	$r = panel_action('config', 0, $mala);
	ok(is_wp_error($r) && get_option('at_rt_resumen_dia') === '3' && get_option('at_rt_resumen_hora') === '08:30' && get_option('at_rt_resumen_destino') === $destino, 'config con ' . json_encode($mala) . ': error y no cambia nada');
}
ok(str_contains(panel_action('config', 0, ['dia' => '3', 'hora' => '25:00', 'destino' => ''])->get_error_message(), 'HH:MM'), 'la hora inválida dice el formato');
$r = panel_action('config', 0, ['dia' => '1', 'hora' => '09:00', 'destino' => '']);
ok(!is_wp_error($r) && get_option('at_rt_resumen_destino') === '' && at_rt_config()['destino'] === at_cc_correo_avisos(), 'destino vacío vuelve al correo de avisos');
panel_action('config', 0, ['dia' => '1', 'hora' => '09:00', 'destino' => $destino]);

// ---- «Enviarme el resumen ahora» ----
$sid = rt_pn_lista(['nombre' => '[PRUEBA] Para el resumen']);
$mails_antes = count($mails);
$r = panel_action('resumen', 0);
$para_luis = array_values(array_filter($mails, static fn($m) => $m['to'] === $destino));
ok(!is_wp_error($r) && count($mails) === $mails_antes + 1 && count($para_luis) === 1, 'resumen forzado: un correo capturado al destino');
ok(!is_wp_error($r) && str_contains($r['mensaje'], 'resumen') && !str_contains($r['mensaje'], $destino), 'el aviso no repite el correo del destino');
$r = panel_action('resumen', 0);
ok(!is_wp_error($r) && count($mails) === $mails_antes + 2, 'forzado: manda aunque ya haya salido esta semana');
$GLOBALS['rt_pn_prefijo'] = 'no-existe-' . $tag;
$mails_antes = count($mails);
$r = panel_action('resumen', 0);
ok(!is_wp_error($r) && $r['mensaje'] === 'No hay prospectos para revisar esta semana.' && count($mails) === $mails_antes, 'sin nada que revisar: «No hay prospectos para revisar esta semana.» y no manda correo');
$GLOBALS['rt_pn_prefijo'] = $tag;
$falla_mail = true;
$r = panel_action('resumen', 0);
ok(is_wp_error($r) && $r->get_error_code() === 'resumen' && str_contains($r->get_error_message(), 'correo'), 'si el correo falla: error claro');
$falla_mail = false;

// ---- Con los flujos de n8n configurados (simulados) ----
define('AT_N8N_RETOMAR_REDACTAR', 'https://example.invalid/webhook/retomar-redactar');
define('AT_N8N_RETOMAR_RENDER', 'https://example.invalid/webhook/retomar-render');
$r = panel_action('guardar', 0, array_replace($p, $vacios + ['email' => $tag . '-ia@example.com']));
$iaid = $r['id']; $ids[] = $iaid;
$s = at_rt_obtener($iaid);
ok(!is_wp_error($r) && $s['estado'] === 'activo' && $s['redaccion']['estado'] === 'pendiente' && preg_match('/^[a-f0-9]{16}$/D', $s['redaccion']['generacion']), 'guardar sin textos pide la redacción: queda pendiente con generación');
ok(count($pedidos_redaccion) === 1 && $pedidos_redaccion[0]['id'] === $iaid && $pedidos_redaccion[0]['generacion'] === $s['redaccion']['generacion'] && count($pedidos_render) === 0, 'n8n recibe {id, generacion} de la redacción y no se pide la presentación todavía');
ok(str_contains($r['mensaje'], 'Redacción solicitada'), 'el aviso dice que la redacción quedó solicitada');
$generacion_ia = $s['redaccion']['generacion'];
$pedidos_redaccion = []; $pedidos_render = [];
// Con los tres textos: lista a mano y presentación pedida; la redacción no se pide.
$r = panel_action('guardar', $iaid, array_replace($p, ['email' => $tag . '-ia@example.com']));
$s = at_rt_obtener($iaid);
ok(!is_wp_error($r) && $s['redaccion']['estado'] === 'lista' && $s['redaccion']['origen'] === 'manual' && $s['redaccion']['generacion'] !== $generacion_ia, 'a mano: redaccion lista/manual con generación nueva');
ok(count($pedidos_render) === 1 && $pedidos_render[0]['id'] === $iaid && count($pedidos_redaccion) === 0 && $s['render']['estado'] === 'pendiente' && ($s['render']['generacion'] ?? '') === $pedidos_render[0]['generacion'], 'a mano: se pide la presentación y no la redacción');
// Un callback tardío de la IA (generación anterior, o la misma ya resuelta) recibe 409 y no pisa lo escrito.
$textos_a_mano = $s['textos'];
$ia = ['ok' => true, 'textos' => ['quedamos' => 'La IA quedamos.', 'proponemos' => 'La IA proponemos.', 'partir' => 'La IA partir.', 'recomendacion' => 'La IA recomienda.'], 'image_briefs' => [], 'nota' => 'tarde'];
$tarde = at_rt_guardar_redaccion($iaid, $ia + ['generacion' => $generacion_ia]);
ok(is_wp_error($tarde) && ($tarde->get_error_data()['status'] ?? 0) === 409 && at_rt_obtener($iaid)['textos'] === $textos_a_mano, 'callback tardío de la IA: 409 y los textos a mano quedan intactos');
$tarde = at_rt_guardar_redaccion($iaid, $ia + ['generacion' => $s['redaccion']['generacion']]);
ok(is_wp_error($tarde) && ($tarde->get_error_data()['status'] ?? 0) === 409 && at_rt_obtener($iaid)['redaccion']['origen'] === 'manual', 'ni siquiera con la generación nueva: ya está lista');
// Borrar los textos vuelve a pedir la redacción.
$pedidos_redaccion = []; $pedidos_render = [];
$r = panel_action('guardar', $iaid, array_replace($p, $vacios + ['email' => $tag . '-ia@example.com']));
ok(!is_wp_error($r) && at_rt_obtener($iaid)['redaccion']['estado'] === 'pendiente' && count($pedidos_redaccion) === 1 && count($pedidos_render) === 0, 'dejar los tres textos vacíos vuelve a pedir la redacción');

// ---- Volver a redactar ----
$pedidos_redaccion = [];
$r = panel_action('redactar', $iaid);
$s = at_rt_obtener($iaid);
ok(!is_wp_error($r) && count($pedidos_redaccion) === 1 && $pedidos_redaccion[0]['generacion'] === $s['redaccion']['generacion'] && $s['redaccion']['estado'] === 'pendiente', 'redactar pide la redacción con una generación nueva');
$pausado = rt_pn_seg([], ['estado' => 'pausado', 'proximo_at' => null]);
$espera = rt_pn_seg([], ['estado' => 'espera_propuesta']);
foreach ([$pausado, $espera] as $no) {
	$antes = at_rt_obtener($no); $n = count($pedidos_redaccion);
	$r = panel_action('redactar', $no);
	ok(is_wp_error($r) && at_rt_obtener($no) === $antes && count($pedidos_redaccion) === $n, 'redactar solo con el seguimiento activo (' . $antes['estado'] . ')');
}
$falla_n8n = true;
$r = panel_action('redactar', $iaid);
ok(is_wp_error($r) && str_contains($r->get_error_message(), 'No se pudo pedir la redacción') && at_rt_obtener($iaid)['redaccion']['estado'] === 'error', 'n8n falla: mensaje claro y la redacción queda en error');
$falla_n8n = false;

// ---- Vincular la propuesta nueva a quien espera una ----
$correo_v = $tag . '-vincula@example.com';
$vieja = rt_pn_prop(['client_email' => $correo_v, 'status' => 'sent', 'flujo' => 'v2']);
$nueva = rt_pn_prop(['client_email' => $correo_v, 'status' => 'lista', 'flujo' => 'v3']);
$vid = rt_pn_seg(['email' => $correo_v, 'propuesta_id' => $vieja], ['estado' => 'espera_propuesta']);
$pv = array_replace($p, $vacios + ['email' => $correo_v]);
$pedidos_redaccion = [];
$r = panel_action('guardar', $vid, $pv + ['propuesta_id' => $nueva]);
$s = at_rt_obtener($vid);
$estado_vieja = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id=%d", $vieja));
$notas_vieja = $wpdb->get_col($wpdb->prepare("SELECT title FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id=%d", $vieja));
ok(!is_wp_error($r) && $estado_vieja === 'archivada' && in_array('Reemplazada por la propuesta ' . $nueva, $notas_vieja, true), 'vincular la v3: la anterior queda archivada con la nota «Reemplazada por la propuesta N»');
ok($s['estado'] === 'activo' && $s['propuesta_id'] === $nueva && $s['proximo_at'] === $hoy && substr($s['activado_at'], 0, 10) === $hoy, 'vincular la v3: el seguimiento pasa a activo con próximo envío hoy');
ok(count($pedidos_redaccion) === 1 && $pedidos_redaccion[0]['id'] === $vid && $s['redaccion']['estado'] === 'pendiente', 'vincular la v3: se pide la redacción');
ok($wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id=%d", $nueva)) === 'lista', 'la propuesta nueva no se toca');
// Si la anterior no se puede archivar, no cambia nada.
$correo_a = $tag . '-acepto@example.com';
$vieja_a = rt_pn_prop(['client_email' => $correo_a, 'status' => 'aceptada', 'flujo' => 'v2']);
$nueva_a = rt_pn_prop(['client_email' => $correo_a, 'status' => 'lista', 'flujo' => 'v3']);
$aid = rt_pn_seg(['email' => $correo_a, 'propuesta_id' => $vieja_a], ['estado' => 'espera_propuesta']);
$antes = at_rt_obtener($aid); $n = count($pedidos_redaccion);
$r = panel_action('guardar', $aid, array_replace($pv, ['email' => $correo_a]) + ['propuesta_id' => $nueva_a]);
ok(is_wp_error($r) && str_contains($r->get_error_message(), 'ya la aceptó') && at_rt_obtener($aid) === $antes && count($pedidos_redaccion) === $n, 'archivar falla: se informa el motivo y el seguimiento no cambia');
// Una propuesta que no usa la plantilla AT no saca al seguimiento de la espera.
$correo_b = $tag . '-sigue@example.com';
$vieja_b = rt_pn_prop(['client_email' => $correo_b, 'status' => 'sent', 'flujo' => 'v2']);
$otra_b = rt_pn_prop(['client_email' => $correo_b, 'status' => 'sent', 'flujo' => 'v2']);
$bid = rt_pn_seg(['email' => $correo_b, 'propuesta_id' => $vieja_b], ['estado' => 'espera_propuesta']);
$r = panel_action('guardar', $bid, array_replace($pv, ['email' => $correo_b]) + ['propuesta_id' => $otra_b]);
ok(!is_wp_error($r) && at_rt_obtener($bid)['estado'] === 'espera_propuesta' && $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id=%d", $vieja_b)) === 'sent', 'una propuesta que no es v3 no activa ni archiva nada');

// ---- Botón en la ficha CRM ----
ob_start(); at_rt_boton_ficha(['id' => 5, 'tipo' => 'prospecto']); $boton = ob_get_clean();
ok(str_contains($boton, 'page=at-retomar&amp;crm=5') && str_contains($boton, 'Retomar'), 'botón de la ficha de un prospecto: enlace a at-retomar con ?crm=');
ob_start(); at_rt_boton_ficha(['id' => 5, 'tipo' => 'cliente']); ok(ob_get_clean() === '', 'con tipo=cliente no imprime nada');
ob_start(); at_rt_boton_ficha(['id' => 5]); ok(ob_get_clean() === '', 'sin tipo no imprime nada');
$crm = $wpdb->insert($wpdb->prefix . 'crm_clientes', ['tipo' => 'prospecto', 'estado' => 'nuevo', 'nombre' => '[PRUEBA] Ficha Nombre', 'empresa' => '[PRUEBA] Ficha Negocio', 'email' => $tag . '-ficha@example.com', 'telefono' => '+56911112222', 'fecha_contacto' => current_time('mysql')]);
$crm_id = (int) $wpdb->insert_id; $crms[] = $crm_id;
$crm_cliente = $wpdb->insert($wpdb->prefix . 'crm_clientes', ['tipo' => 'cliente', 'estado' => 'nuevo', 'nombre' => '[PRUEBA] Ya Cliente', 'empresa' => '', 'email' => $tag . '-cli@example.com', 'telefono' => '', 'fecha_contacto' => current_time('mysql')]);
$cliente_id = (int) $wpdb->insert_id; $crms[] = $cliente_id;
$html = rt_pn_pagina(['page' => 'at-retomar', 'crm' => $crm_id]);
ok(str_contains($html, 'id="rt-nombre"') && str_contains($html, 'value="[PRUEBA] Ficha Nombre"') && str_contains($html, 'value="[PRUEBA] Ficha Negocio"') && str_contains($html, 'value="' . $tag . '-ficha@example.com"') && str_contains($html, 'value="+56911112222"'), '?crm=<id> precarga nombre, negocio, correo y teléfono de la ficha');
ok(preg_match('/<option[^>]*value="' . $crm_id . '"[^>]*selected/', $html) === 1, '?crm=<id> deja la ficha elegida en la lista');
$html = rt_pn_pagina(['page' => 'at-retomar', 'crm' => $cliente_id]);
ok(!str_contains($html, 'value="[PRUEBA] Ya Cliente"'), '?crm= de un cliente no precarga nada');
// Si la ficha ya tiene seguimiento, se abre ese en vez de uno nuevo.
$ficha_seg = rt_pn_seg(['crm_cliente_id' => $crm_id, 'nombre' => '[PRUEBA] Seguimiento de la ficha']);
$html = rt_pn_pagina(['page' => 'at-retomar', 'crm' => $crm_id]);
ok(str_contains($html, 'Editar seguimiento #' . $ficha_seg), '?crm=<id> de una ficha con seguimiento abre ese seguimiento');

// ---- La página ----
$s = at_rt_obtener($id);
$html = rt_pn_pagina(['page' => 'at-retomar', 'id' => $id]);
ok(str_contains($html, 'Márcala solo si esta persona te escribió o conversó contigo por WhatsApp') && str_contains($html, 'id="rt-wa"') && str_contains($html, 'id="rt-media"'), 'checkbox autorización, ayuda exacta y selector medios');
ok(str_contains($html, 'name="demo_2"') && !str_contains($html, 'name="demo_3"') && str_contains($html, 'for="rt-quedamos"'), 'hasta dos demos y labels accesibles');
ok(str_contains($html, 'Texto final (opcional: si lo dejas vacío, lo redacta la IA)') && str_contains($html, 'Notas para la IA') && str_contains($html, 'name="notas"'), 'textos opcionales y notas para la IA en el formulario');
ok(preg_match('/<textarea[^>]*name="(quedamos|proponemos|partir)"[^>]*required/', $html) !== 1 && !str_contains($html, '<iframe'), 'los textos ya no son obligatorios; render pendiente no expone iframe');
ok(!str_contains($html, '>Activar<') && !str_contains($html, 'at_rt_activar'), 'no queda ningún botón Activar');
ok(str_contains($html, 'name="dia"') && str_contains($html, 'name="hora"') && str_contains($html, 'name="destino"') && str_contains($html, '📬 Enviarme el resumen ahora'), 'configuración del resumen y botón «Enviarme el resumen ahora»');
$html_lista = rt_pn_pagina(['page' => 'at-retomar']);
ok(str_contains($html_lista, '<th>Revisión</th>') && !str_contains($html_lista, at_pt_host_renderer()) && !stripos($html_lista, 'easypanel'), 'la lista tiene la columna «Revisión» y no muestra datos internos');
// Botones según el estado.
$html_activo = rt_pn_pagina(['page' => 'at-retomar', 'id' => $iaid]);
ok(str_contains($html_activo, '✨ Volver a redactar') && !str_contains($html_activo, '▶️ Reanudar'), 'activo: «Volver a redactar» y sin «Reanudar»');
$html_pausado = rt_pn_pagina(['page' => 'at-retomar', 'id' => $pausado]);
ok(str_contains($html_pausado, '▶️ Reanudar') && !str_contains($html_pausado, '✨ Volver a redactar'), 'pausado: «Reanudar» y sin «Volver a redactar»');
$html_espera = rt_pn_pagina(['page' => 'at-retomar', 'id' => $espera]);
ok(!str_contains($html_espera, '▶️ Reanudar') && !str_contains($html_espera, '✨ Volver a redactar'), 'en espera de propuesta: ninguno de los dos');
$html_baja = rt_pn_pagina(['page' => 'at-retomar', 'id' => $bajaid]);
ok(!str_contains($html_baja, '▶️ Reanudar'), 'en baja: sin «Reanudar»');
// Aviso del WhatsApp.
update_option('at_rt_wa_automatico', '1');
$html = rt_pn_pagina(['page' => 'at-retomar']);
ok(str_contains($html, 'sale en cada recordatorio que apruebes a quien tenga la casilla') && !str_contains($html, 'primer y el último'), 'aviso del WhatsApp encendido: sale en cada recordatorio aprobado');
update_option('at_rt_wa_automatico', '0');
ok(str_contains(rt_pn_pagina(['page' => 'at-retomar']), 'WhatsApp automático: en pausa'), 'aviso del WhatsApp apagado intacto');

// ---- Columna «Revisión» ----
$rev = static fn(int $sid): string => at_rt_panel_revision(at_rt_obtener($sid), $hoy);
$un = rt_pn_seg([], ['estado' => 'espera_propuesta']);
ok($rev($un) === 'Necesita propuesta nueva', 'revisión: espera_propuesta = Necesita propuesta nueva');
$un = rt_pn_seg([], ['redaccion' => ['estado' => 'pendiente', 'generacion' => str_repeat('c', 16)]]);
ok($rev($un) === 'Redactando', 'revisión: redacción pendiente = Redactando');
$un = rt_pn_seg([], ['redaccion' => ['estado' => 'revision', 'nota' => 'x']]);
ok($rev($un) === 'Necesita revisión', 'revisión: redacción en revisión = Necesita revisión');
$un = rt_pn_lista();
ok($rev($un) === 'Lista para aprobar', 'revisión: lista y con presentación = Lista para aprobar');
$huella = at_rt_obtener($un)['render']['huella'];
at_rt_guardar($un, ['aprobacion' => ['estado' => 'aprobado', 'n' => 1, 'huella' => $huella, 'usada' => true, 'programado' => '2026-10-20']]);
ok($rev($un) === 'Aprobado, sale el 2026-10-20', 'revisión: aprobado y vigente = Aprobado, sale el AAAA-MM-DD');
at_rt_guardar($un, ['render' => ['estado' => 'pendiente', 'nota' => 'x']]);
ok($rev($un) === 'Aprobado, esperando la presentación', 'revisión: aprobado sin presentación = Aprobado, esperando la presentación');
$un = rt_pn_lista([], ['estado' => 'pausado', 'proximo_at' => null]);
ok($rev($un) === '—', 'revisión: pausado = guion');
$un = rt_pn_lista([], ['aprobacion' => ['estado' => 'omitido', 'n' => 1, 'usada' => true, 'resuelto_at' => at_rt_ahora_chile()]]);
ok($rev($un) === 'Omitido esta semana', 'revisión: omitido esta semana');
foreach ([$sid, $iaid, $vid, $pausado] as $x) { $texto = $rev($x); ok(!stripos($texto, 'easypanel') && !str_contains($texto, at_pt_host_renderer()) && !str_contains($texto, 'http'), 'la revisión no trae datos internos (#' . $x . ')'); }
fin();
