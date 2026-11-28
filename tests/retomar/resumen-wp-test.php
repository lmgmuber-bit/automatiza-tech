<?php
// Correo semanal de Retomar, aviso «recordatorio corregido» y reloj por hora (fase 2). Solo contra el sitio local de prueba.
// Review Focus 4: nunca dos resúmenes la misma semana (aunque el reloj corra varias veces en la misma hora) y, si wp_mail
// falla, se reintenta en la pasada siguiente.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_resumen', 'at_rt_aviso_listo', 'at_rt_tick', 'at_rt_reintentar_redacciones');
at_rt_migrar_esquema();
global $wpdb;
// Con flujo de redacción (simulado): una redacción sin terminar es «En preparación» y el reloj la puede volver a pedir.
if (!defined('AT_N8N_RETOMAR_REDACTAR')) { define('AT_N8N_RETOMAR_REDACTAR', 'https://example.invalid/webhook/retomar-redactar'); }
$marca = 'rt-res-' . strtolower(wp_generate_password(10, false, false));
$destino = $marca . '-luis@example.com';
$ids = []; $pids = [];
$mails = []; $falla_mail = false; $pedidos_redaccion = [];
$filtros = ['lista' => 0, 'reloj' => 0, 'deteccion' => 0];
$GLOBALS['rt_res_prefijo'] = $marca;
$originales = [];
foreach (['at_rt_resumen_semana', 'at_rt_resumen_desde', 'at_rt_resumen_destino', 'at_rt_resumen_copia', 'at_rt_resumen_dia', 'at_rt_resumen_hora', 'at_rt_wa_automatico'] as $o) { $originales[$o] = get_option($o, null); }
$esquema_original = get_option('at_rt_schema');
register_shutdown_function(static function () use (&$ids, &$pids, $marca, $originales, $esquema_original, $wpdb) {
	update_option('at_rt_schema', $esquema_original, false);
	foreach ($originales as $o => $v) { $v === null ? delete_option($o) : update_option($o, $v); }
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}at_retomar WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
	foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
	foreach ($pids as $pid) {
		$wpdb->delete($wpdb->prefix . 'automatiza_propuestas_details', ['propuesta_id' => $pid]);
		$wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $pid]);
	}
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}at_retomar_bajas WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
});
update_option('at_rt_resumen_destino', $destino);
delete_option('at_rt_resumen_copia');
update_option('at_rt_resumen_dia', '1');
update_option('at_rt_resumen_hora', '09:00');
update_option('at_rt_wa_automatico', '1');
// Solo se cuentan los correos que salieron de verdad; con $falla_mail, wp_mail devuelve false.
add_filter('pre_wp_mail', static function ($v, $d) use (&$mails, &$falla_mail) {
	if (!$falla_mail) { $mails[] = $d; }
	return !$falla_mail;
}, 100, 2);
// El bloqueo de HTTP del bootstrap sigue: SOLO el flujo de redacción responde (simulado) y se anota cada pedido.
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$pedidos_redaccion) {
	if ($url !== AT_N8N_RETOMAR_REDACTAR) { return $pre; }
	$pedidos_redaccion[] = json_decode($args['body'], true) + ['timeout' => $args['timeout'] ?? null];
	return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []];
}, PHP_INT_MAX, 3);
// Ruling 3: el resumen (at_rt_lista), el reloj y la detección leen toda la base local. Se acotan a los correos de esta
// prueba que empiezan con $GLOBALS['rt_res_prefijo'], y $filtros cuenta que cada filtro calzó de verdad.
add_filter('query', static function ($sql) use (&$filtros, $wpdb) {
	$like = "'" . esc_sql($wpdb->esc_like($GLOBALS['rt_res_prefijo'])) . "%'";
	if ($sql === "SELECT * FROM {$wpdb->prefix}at_retomar ORDER BY id DESC") {
		$filtros['lista']++;
		return "SELECT * FROM {$wpdb->prefix}at_retomar WHERE email LIKE {$like} ORDER BY id DESC";
	}
	if (str_starts_with($sql, "SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado='activo' AND proximo_at<=") && str_ends_with($sql, ' ORDER BY id')) {
		$filtros['reloj']++;
		return substr($sql, 0, -strlen(' ORDER BY id')) . " AND email LIKE {$like} ORDER BY id";
	}
	if (str_starts_with($sql, 'SELECT id, client_email') && str_contains($sql, "FROM {$wpdb->prefix}automatiza_propuestas WHERE client_email <> ''")) {
		$filtros['deteccion']++;
		return str_replace(' ORDER BY id DESC', " AND client_email LIKE {$like} ORDER BY id DESC", $sql);
	}
	return $sql;
});

/**
 * Seguimiento activo que toca el lunes 2026-10-19, con redacción `lista` (y una nota de la IA que nunca debe salir en el
 * correo) y render `listo` con su huella real; la vista vive en el host del renderer, que tampoco puede aparecer en el
 * correo. $c pisa el alta y $despues se guarda al final (redaccion, render, aprobacion...).
 */
function rt_res_seg(array $c = [], array $despues = [], string $prefijo = ''): array {
	global $ids, $marca;
	$email = ($prefijo !== '' ? $prefijo : $marca) . '-' . count($ids) . '@example.com';
	$id = at_rt_crear($c + ['nombre' => '[PRUEBA] Sin nombre ' . count($ids), 'negocio' => '[PRUEBA] Negocio', 'email' => $email, 'telefono' => '', 'wa_autorizado' => 0, 'estado' => 'activo', 'proximo_at' => '2026-10-19', 'activado_at' => '2026-10-01 00:00:00',
		'textos' => ['quedamos' => '[PRUEBA] Quedamos en retomar la agenda.', 'proponemos' => '[PRUEBA] Proponemos partir simple.', 'partir' => '[PRUEBA] Partimos con una reunión corta.', 'recomendacion' => '[PRUEBA] Recomendamos partir por la agenda.']]);
	$ids[] = $id;
	$s = at_rt_obtener($id);
	$render = ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/index.html'];
	at_rt_guardar($id, ['redaccion' => ['estado' => 'lista', 'origen' => 'ia', 'nota' => '[PRUEBA] nota-de-la-ia-' . $id], 'render' => $render]);
	try { $render['huella'] = at_rt_huella_render(at_rt_obtener($id)); }
	catch (InvalidArgumentException $e) { $render['huella'] = hash('sha256', 'rt-res-sin-huella-' . $id); } // contenido que no se puede presentar
	at_rt_guardar($id, ['render' => $render]);
	if ($despues) { at_rt_guardar($id, $despues); }
	return at_rt_obtener($id);
}
function rt_res_prop(array $c = []): int {
	global $wpdb, $pids, $marca;
	$n = count($pids);
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', $c + ['client_email' => $marca . '-prop' . $n . '@example.com', 'unique_link_id' => substr(md5($marca . '-prop-' . $n), 0, 12), 'client_name' => '[PRUEBA] Cliente', 'company_name' => '[PRUEBA] Negocio', 'phone' => '', 'status' => 'sent', 'flujo' => 'v2', 'gamma_prompt_text' => '', 'system_prompt_text' => '', 'created_at' => '2026-09-09 10:00:00']);
	return $pids[] = (int) $wpdb->insert_id;
}
/** Correos que salieron a $to. */
function rt_res_correos(string $to): array {
	global $mails;
	return array_values(array_filter($mails, static fn($m) => $m['to'] === $to));
}
/** La tarjeta (trozo del HTML) del prospecto $nombre, o '' si no está. */
function rt_res_tarjeta(string $html, string $nombre): string {
	foreach (explode('border:1px solid #d6dde6', $html) as $trozo) {
		if (str_contains($trozo, htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))) { return $trozo; }
	}
	return '';
}
function rt_res_esc(string $url): string { return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/** Query de un enlace firmado de la aprobación vigente de $id para $accion. */
function rt_res_q(int $id, string $accion): array {
	$ap = at_rt_obtener($id)['aprobacion'];
	parse_str((string) parse_url(at_rt_aprobacion_url($id, $accion, (string) ($ap['nonce'] ?? ''), (int) ($ap['vence'] ?? 0)), PHP_URL_QUERY), $q);
	return $q;
}
function rt_res_ts(string $ahora): int { return at_rt_ahora($ahora)->getTimestamp(); }
/** Todo enlace del correo es HTTPS y del sitio (D8: nunca el renderer ni easypanel). */
function rt_res_enlaces_del_sitio(string $html): bool {
	preg_match_all('/href="([^"]*)"/', $html, $m);
	if (!$m[1]) { return false; }
	foreach ($m[1] as $href) {
		$url = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if (!str_starts_with($url, 'https://') || parse_url($url, PHP_URL_HOST) !== parse_url(home_url('/'), PHP_URL_HOST)) { return false; }
	}
	return true;
}
function rt_res_sin_internos(string $html): bool {
	return stripos($html, 'easypanel') === false && stripos($html, at_pt_host_renderer()) === false;
}

// ---- Seguimientos del primer resumen (lunes 2026-10-19) ----
$p_ana = rt_res_prop(['created_at' => '2026-09-09 10:00:00']);
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', ['propuesta_id' => $p_ana, 'detail_type' => 'propuesta_enviada', 'title' => '[PRUEBA] enviada', 'status' => 'completed', 'completed_date' => '2026-09-29', 'created_at' => '2026-09-29 10:00:00']);
$ana = rt_res_seg(['nombre' => '[PRUEBA] Ana Lista', 'propuesta_id' => $p_ana, 'telefono' => '+56900000001', 'wa_autorizado' => 1]);
$p_beto = rt_res_prop(['created_at' => '2026-10-04 10:00:00']);
$ids[] = $beto_id = at_rt_crear(['nombre' => '[PRUEBA] Beto Espera', 'negocio' => '[PRUEBA] Negocio', 'email' => $marca . '-beto@example.com', 'estado' => 'espera_propuesta', 'propuesta_id' => $p_beto, 'proximo_at' => '2026-10-19', 'activado_at' => '2026-10-05 09:00:00', 'render' => ['estado' => 'pendiente', 'nota' => 'Esperando redacción']]);
$caro = rt_res_seg(['nombre' => '[PRUEBA] Caro Sinrender']);
at_rt_guardar($caro['id'], ['render' => ['estado' => 'pendiente', 'nota' => '', 'pedido_at' => '2026-10-19 08:00:00']]); // pedida hace una hora
$wpdb->update($wpdb->prefix . 'at_retomar', ['creado_at' => '2026-10-09 10:00:00'], ['id' => $caro['id']]);
$dani = rt_res_seg(['nombre' => '[PRUEBA] Dani Revision'], ['redaccion' => ['estado' => 'revision', 'nota' => '[PRUEBA] nota-de-revision-ia']]);
$eli = rt_res_seg(['nombre' => '[PRUEBA] Eli Vigente']);
$ap_eli = ['estado' => 'aprobado', 'n' => 1, 'nonce' => str_repeat('e', 16), 'huella' => $eli['render']['huella'], 'emitido_at' => '2026-10-12 09:00:00', 'vence' => rt_res_ts('2026-10-19 09:00:00'), 'usada' => true, 'resuelto_at' => '2026-10-12 09:05:00', 'programado' => '2026-10-19'];
at_rt_guardar($eli['id'], ['aprobacion' => $ap_eli]);
$fede = rt_res_seg(['nombre' => '[PRUEBA] Fede Aprobsinrender']);
$ap_fede = array_merge($ap_eli, ['nonce' => str_repeat('f', 16), 'huella' => $fede['render']['huella']]);
at_rt_guardar($fede['id'], ['aprobacion' => $ap_fede, 'render' => array_merge($fede['render'], ['estado' => 'pendiente', 'pedido_at' => '2026-10-19 08:00:00'])]);
$gabi = rt_res_seg(['nombre' => '[PRUEBA] Gabi Huellavieja']);
at_rt_guardar($gabi['id'], ['aprobacion' => array_merge($ap_eli, ['nonce' => str_repeat('a', 16), 'huella' => hash('sha256', 'presentación anterior')])]);
$hugo = rt_res_seg(['nombre' => '[PRUEBA] Hugo Futuro', 'proximo_at' => '2026-10-26']);
$ines = rt_res_seg(['nombre' => '[PRUEBA] Ines Pausada', 'estado' => 'pausado']);
$juan = rt_res_seg(['nombre' => '[PRUEBA] Juan Baja']);
$wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $juan['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]);
$enviados4 = [];
for ($i = 1; $i <= 4; $i++) { $enviados4[] = ['tipo' => 'envio', 'n' => $i, 'fecha' => '2026-09-0' . $i, 'at' => '2026-09-0' . $i . ' 10:00:00', 'correo' => ['estado' => 'enviado'], 'whatsapp' => null]; }
$kati = rt_res_seg(['nombre' => '[PRUEBA] Kati Tope', 'envios' => $enviados4]);
$pia = rt_res_seg(['nombre' => '[PRUEBA] Pia Rendererror']);
at_rt_guardar($pia['id'], ['render' => array_merge($pia['render'], ['estado' => 'error', 'nota' => 'No se pudo pedir la presentación.'])]);
$quique = rt_res_seg(['nombre' => '[PRUEBA] Quique Interno', 'textos' => ['quedamos' => '[PRUEBA] Revisa https://oculto.easypanel.host/panel', 'proponemos' => '[PRUEBA] Proponemos.', 'partir' => '[PRUEBA] Partir.']]);
// Salieron de Retomar: solo estados de cierre cambiados después del resumen anterior.
$salidas = [];
foreach (['[PRUEBA] Lalo Salebaja' => ['baja', '2026-10-15 12:00:00'], '[PRUEBA] Mona Saleagendo' => ['agendo', '2026-10-16 12:00:00'], '[PRUEBA] Nico Salevieja' => ['agotado', '2026-10-01 00:00:00'], '[PRUEBA] Olga Pausareciente' => ['pausado', '2026-10-16 12:00:00']] as $nombre => [$estado, $cuando]) {
	$ids[] = $sid = at_rt_crear(['nombre' => $nombre, 'negocio' => '[PRUEBA] Negocio', 'email' => $marca . '-sal' . count($salidas) . '@example.com', 'estado' => $estado]);
	$wpdb->update($wpdb->prefix . 'at_retomar', ['actualizado_at' => $cuando], ['id' => $sid]);
	$salidas[$nombre] = $sid;
}
update_option('at_rt_resumen_semana', '2026-W42');
update_option('at_rt_resumen_desde', '2026-10-12 09:00:00');

// ---- Antes de la hora no sale; el lunes a las 09:00 sale uno solo ----
ok(at_rt_resumen('2026-10-19 08:59:00') === ['enviado' => false, 'tarjetas' => 0, 'motivo' => 'no_toca'] && $mails === [], 'lunes 08:59: todavía no toca el resumen');
ok(at_rt_resumen('2026-10-18 23:30:00')['motivo'] === 'no_toca' && $mails === [], 'domingo 23:30 (semana del resumen anterior): no toca');
$r = at_rt_resumen('2026-10-19 09:00:00');
ok($r === ['enviado' => true, 'tarjetas' => 8, 'motivo' => 'enviado'], 'lunes 09:00: sale el resumen con 8 tarjetas');
ok($filtros['lista'] > 0, 'el filtro de la prueba acotó de verdad la lista del resumen');
$al_destino = rt_res_correos($destino);
ok(count($mails) === 1 && count($al_destino) === 1, 'un solo correo y va al destino de la configuración');
$html = $al_destino[0]['message'] ?? '';
ok(($al_destino[0]['subject'] ?? '') === '🔁 Retomar · 8 prospectos para revisar esta semana', 'asunto «🔁 Retomar · 8 prospectos para revisar esta semana»');
ok(in_array('Content-Type: text/html; charset=UTF-8', (array) ($al_destino[0]['headers'] ?? []), true), 'el resumen va en HTML');
ok(array_filter((array) ($al_destino[0]['headers'] ?? []), static fn($h) => stripos((string) $h, 'cc:') === 0) === [], 'sin copia configurada, el resumen no lleva Cc');
ok(substr_count($html, '✅ Aprobar y enviar') === 2 && str_contains($html, 'retomar-aprobar.php') && substr_count($html, 'Necesita propuesta nueva') === 1 && substr_count($html, 'En preparación') === 2 && substr_count($html, 'Necesita revisión') === 3, 'dos para aprobar, una sin propuesta nueva, dos en preparación y tres en revisión');
ok(rt_res_sin_internos($html) && rt_res_enlaces_del_sitio($html), 'D8: sin easypanel ni el host del renderer, y todo enlace es HTTPS del sitio');
ok(!str_contains($html, '<img') && !str_contains($html, 's.w.org'), 'sin imágenes: la miniatura es un bloque HTML y los emojis van como texto');
ok(has_filter('wp_mail', 'wp_staticize_emoji_for_email') !== false, 'el filtro de emojis de WordPress vuelve a quedar puesto para los demás correos');
ok(!str_contains($html, 'nota-de-la-ia') && !str_contains($html, 'nota-de-revision-ia'), 'la nota de la IA nunca sale en el correo semanal');
$ap_ana = at_rt_obtener($ana['id'])['aprobacion'];
ok(($ap_ana['estado'] ?? '') === 'por_aprobar' && ($ap_ana['n'] ?? 0) === 1 && ($ap_ana['usada'] ?? null) === false && ($ap_ana['emitido_at'] ?? '') === '2026-10-19 09:00:00' && ($ap_ana['huella'] ?? '') === $ana['render']['huella'], 'listo: se emite la aprobación con la hora de la pasada');
$t_ana = rt_res_tarjeta($html, '[PRUEBA] Ana Lista');
$presentacion = set_url_scheme(home_url('/retomar.php?t=' . $ana['token']), 'https');
ok($t_ana !== '' && str_contains($t_ana, '✅ Aprobar y enviar') && str_contains($t_ana, rt_res_esc(at_rt_aprobacion_url($ana['id'], 'aprobar', $ap_ana['nonce'], $ap_ana['vence']))), 'la tarjeta de Ana lleva su botón firmado de aprobar');
$ver_ana = at_rt_aprobacion_url($ana['id'], 'ver', $ap_ana['nonce'], $ap_ana['vence']);
ok(str_contains($t_ana, 'Ver la presentación completa') && str_contains($t_ana, 'href="' . rt_res_esc($ver_ana . '#presentacion') . '"') && str_contains($t_ana, 'href="' . rt_res_esc($ver_ana) . '"') && !str_contains($t_ana, rt_res_esc($presentacion)), 'Ruling 17: «Ver la presentación completa» es la página firmada «ver» (#presentacion), no la pública del prospecto; «Ver el correo» es el mismo enlace');
ok(str_contains($t_ana, 'hace 20 días') && str_contains($t_ana, 'WhatsApp ✓') && str_contains($t_ana, '[PRUEBA] Quedamos en retomar la agenda.') && str_contains($t_ana, '[PRUEBA] Recomendamos partir por la agenda.'), 'días desde el envío de la propuesta, WhatsApp autorizado y los textos');
ok(at_rt_aprobacion_motivo(at_rt_obtener($ana['id']), 'aprobar', rt_res_q($ana['id'], 'aprobar'), rt_res_ts('2026-10-19 09:30:00')) === '', 'el enlace de aprobar del correo es válido');
ok(substr_count($html, 'WhatsApp ✓') === 1, 'sin WhatsApp autorizado, la tarjeta dice solo correo');
$t_beto = rt_res_tarjeta($html, '[PRUEBA] Beto Espera');
ok(str_contains($t_beto, 'Necesita propuesta nueva') && str_contains($t_beto, 'hace 15 días') && !str_contains($t_beto, 'retomar-aprobar.php') && at_rt_obtener($beto_id)['aprobacion'] === [], 'espera_propuesta: «Necesita propuesta nueva», sin botones ni aprobación');
$t_caro = rt_res_tarjeta($html, '[PRUEBA] Caro Sinrender');
ok(str_contains($t_caro, 'En preparación') && str_contains($t_caro, 'hace 10 días') && !str_contains($t_caro, 'retomar-aprobar.php') && at_rt_obtener($caro['id'])['aprobacion'] === [], 'sin render: «En preparación», con los días desde el alta y sin emitir');
ok(str_contains(rt_res_tarjeta($html, '[PRUEBA] Dani Revision'), 'Necesita revisión') && at_rt_obtener($dani['id'])['aprobacion'] === [], 'redacción en revisión: «Necesita revisión»');
ok(rt_res_tarjeta($html, '[PRUEBA] Eli Vigente') === '' && at_rt_obtener($eli['id'])['aprobacion'] === $ap_eli, 'aprobado vigente: no va como tarjeta ni se vuelve a emitir');
$t_fede = rt_res_tarjeta($html, '[PRUEBA] Fede Aprobsinrender');
ok(str_contains($t_fede, 'En preparación') && !str_contains($t_fede, 'retomar-aprobar.php') && at_rt_obtener($fede['id'])['aprobacion'] === $ap_fede, 'aprobado con la presentación sin terminar: «En preparación» y la aprobación intacta');
$ap_gabi = at_rt_obtener($gabi['id'])['aprobacion'];
ok(str_contains(rt_res_tarjeta($html, '[PRUEBA] Gabi Huellavieja'), '✅ Aprobar y enviar') && ($ap_gabi['estado'] ?? '') === 'por_aprobar' && ($ap_gabi['huella'] ?? '') === $gabi['render']['huella'], 'aprobado con huella vieja: se trata como no aprobado y se emite de nuevo');
ok(rt_res_tarjeta($html, '[PRUEBA] Hugo Futuro') === '' && rt_res_tarjeta($html, '[PRUEBA] Ines Pausada') === '', 'próximo envío futuro o seguimiento pausado: no va');
ok(rt_res_tarjeta($html, '[PRUEBA] Juan Baja') === '' && at_rt_obtener($juan['id'])['aprobacion'] === [], 'condición de parada (baja): no va y no se emite');
ok(rt_res_tarjeta($html, '[PRUEBA] Kati Tope') === '' && at_rt_obtener($kati['id'])['aprobacion'] === [], 'llegó al tope de recordatorios: no va y no se emite');
ok(str_contains(rt_res_tarjeta($html, '[PRUEBA] Pia Rendererror'), 'Necesita revisión') && at_rt_obtener($pia['id'])['aprobacion'] === [], 'presentación con error: «Necesita revisión» y sin emitir');
ok(str_contains(rt_res_tarjeta($html, '[PRUEBA] Quique Interno'), 'Necesita revisión') && at_rt_obtener($quique['id'])['aprobacion'] === [], 'textos con un enlace interno: «Necesita revisión» y sin emitir');
ok(strpos($html, '✅ Aprobar y enviar') < strpos($html, 'Necesita propuesta nueva') && strpos($html, '✅ Aprobar y enviar') < strpos($html, 'En preparación'), 'lo que hay que aprobar va primero');
ok(str_contains($html, 'Salieron de Retomar') && str_contains($html, '[PRUEBA] Lalo Salebaja') && str_contains($html, 'pidió no recibir más mensajes') && str_contains($html, '[PRUEBA] Mona Saleagendo') && str_contains($html, 'agendó una reunión'), '«Salieron de Retomar»: baja y agendó desde el resumen anterior');
ok(!str_contains($html, '[PRUEBA] Nico Salevieja') && !str_contains($html, '[PRUEBA] Olga Pausareciente'), 'una salida anterior al resumen pasado o un seguimiento en pausa no aparecen');
ok(get_option('at_rt_resumen_semana') === '2026-W43' && get_option('at_rt_resumen_desde') === '2026-10-19 09:00:00', 'queda marcada la semana y la hora del resumen');

// ---- Review Focus 4: la misma semana no sale otro, aunque el reloj corra varias veces ----
$ap_antes = at_rt_obtener($ana['id'])['aprobacion'];
ok(at_rt_resumen('2026-10-19 09:30:00') === ['enviado' => false, 'tarjetas' => 0, 'motivo' => 'no_toca'] && at_rt_resumen('2026-10-19 09:00:00')['motivo'] === 'no_toca' && count($mails) === 1, 'segunda y tercera llamada la misma semana: sin correo');
ok(at_rt_resumen('2026-10-21 15:00:00')['motivo'] === 'no_toca' && count($mails) === 1, 'el miércoles de la misma semana tampoco');
ok(at_rt_obtener($ana['id'])['aprobacion'] === $ap_antes, 'sin resumen no se rotan los enlaces que Luis ya tiene');
// Otra pasada con el resumen en curso (candado de MySQL tomado por otra conexión): no manda nada.
$otra = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$tomado = (string) $otra->get_var("SELECT GET_LOCK('{$wpdb->prefix}at_rt_resumen', 0)");
$ocupado = at_rt_resumen('2026-10-19 10:00:00', true);
$otra->get_var("SELECT RELEASE_LOCK('{$wpdb->prefix}at_rt_resumen')");
ok($tomado === '1' && $ocupado['motivo'] === 'ocupado' && !$ocupado['enviado'] && count($mails) === 1 && at_rt_obtener($ana['id'])['aprobacion'] === $ap_antes, 'con otro resumen en curso no sale un segundo ni se rotan los enlaces');
// Forzar («Enviarme el resumen ahora»): sale aunque ya se envió, y los enlaces del resumen anterior responden «cambio».
$q_viejo = rt_res_q($ana['id'], 'aprobar');
$r = at_rt_resumen('2026-10-19 11:00:00', true);
$al_destino = rt_res_correos($destino);
ok($r['enviado'] && $r['motivo'] === 'enviado' && count($al_destino) === 2, 'forzar: sale aunque ya se envió esta semana');
ok(at_rt_aprobacion_motivo(at_rt_obtener($ana['id']), 'aprobar', $q_viejo, rt_res_ts('2026-10-19 11:05:00')) === 'cambio', 'tras el resumen nuevo, el enlace del resumen anterior da «cambio»');
ok(at_rt_resolver_aprobacion($ana['id'], 'aprobar', $q_viejo, '2026-10-19 11:05:00')['motivo'] === 'cambio' && at_rt_obtener($ana['id'])['aprobacion']['estado'] === 'por_aprobar', 'y no sirve para aprobar');
ok(!str_contains($al_destino[1]['message'], '[PRUEBA] Lalo Salebaja') && get_option('at_rt_resumen_desde') === '2026-10-19 11:00:00', 'las salidas ya contadas no se repiten: «desde» avanza al resumen nuevo');

// ---- Review Focus 4: si wp_mail falla, no se marca la semana y la pasada siguiente lo manda ----
$falla_mail = true;
$r = at_rt_resumen('2026-10-26 09:00:00');
$falla_mail = false;
ok($r['enviado'] === false && $r['motivo'] === 'correo_error' && count($mails) === 2, 'wp_mail falla: correo_error');
ok(get_option('at_rt_resumen_semana') === '2026-W43' && get_option('at_rt_resumen_desde') === '2026-10-19 11:00:00', 'con el correo fallido la semana no queda marcada');
$r = at_rt_resumen('2026-10-26 10:00:00');
ok($r['enviado'] && $r['motivo'] === 'enviado' && count(rt_res_correos($destino)) === 3 && get_option('at_rt_resumen_semana') === '2026-W44', 'la pasada siguiente sí lo manda y marca la semana');
ok(str_contains(rt_res_tarjeta(end($mails)['message'], '[PRUEBA] Hugo Futuro'), '✅ Aprobar y enviar'), 'la semana siguiente aparece el que tocaba más adelante');

// ---- Sin tarjetas ni salidas: no sale nada y la semana queda marcada ----
$GLOBALS['rt_res_prefijo'] = $marca . '-nadie';
$antes = count($mails);
ok(at_rt_resumen('2026-11-02 09:00:00') === ['enviado' => false, 'tarjetas' => 0, 'motivo' => 'vacio'] && count($mails) === $antes, 'sin nada que revisar: no sale correo (vacio)');
ok(get_option('at_rt_resumen_semana') === '2026-W45' && get_option('at_rt_resumen_desde') === '2026-10-26 10:00:00', 'y la semana queda marcada');
ok(at_rt_resumen('2026-11-02 12:00:00')['motivo'] === 'no_toca', 'marcada la semana vacía, no se vuelve a armar');
$GLOBALS['rt_res_prefijo'] = $marca;

// ---- Aviso «recordatorio corregido» ----
$rita = rt_res_seg(['nombre' => '[PRUEBA] Rita Aviso'], ['aprobacion' => ['estado' => 'cambios', 'n' => 1, 'usada' => true]]);
$antes = count($mails);
ok(at_rt_aviso_listo($rita['id']) === true, 'el aviso sale');
$aviso = $mails[$antes] ?? [];
ok(array_filter((array) ($aviso['headers'] ?? []), static fn($h) => stripos((string) $h, 'cc:') === 0) === [], 'sin copia configurada, el aviso no lleva Cc');
ok(count($mails) === $antes + 1 && ($aviso['to'] ?? '') === $destino && ($aviso['subject'] ?? '') === '🔁 Retomar · Recordatorio corregido: [PRUEBA] Rita Aviso', 'un correo al destino con el asunto del recordatorio corregido');
$ap_rita = at_rt_obtener($rita['id'])['aprobacion'];
ok(($ap_rita['estado'] ?? '') === 'por_aprobar' && ($ap_rita['usada'] ?? null) === false && str_contains($aviso['message'] ?? '', rt_res_esc(at_rt_aprobacion_url($rita['id'], 'aprobar', (string) ($ap_rita['nonce'] ?? ''), (int) ($ap_rita['vence'] ?? 0)))), 'emite una aprobación nueva (sale de «cambios») con sus enlaces');
ok(substr_count($aviso['message'] ?? '', '✅ Aprobar y enviar') === 1 && substr_count($aviso['message'] ?? '', 'border:1px solid #d6dde6') === 1 && rt_res_sin_internos($aviso['message'] ?? '') && rt_res_enlaces_del_sitio($aviso['message'] ?? ''), 'una sola tarjeta, sin enlaces internos y todo HTTPS del sitio');
ok(str_contains($aviso['message'] ?? '', 'href="' . rt_res_esc(at_rt_aprobacion_url($rita['id'], 'ver', (string) ($ap_rita['nonce'] ?? ''), (int) ($ap_rita['vence'] ?? 0)) . '#presentacion') . '"'), 'Ruling 17: en el aviso, «Ver la presentación completa» también es la página firmada «ver»');
$falla_mail = true;
$cambios = ['estado' => 'cambios', 'n' => 1, 'usada' => true];
at_rt_guardar($rita['id'], ['aprobacion' => $cambios]);
ok(at_rt_aviso_listo($rita['id']) === false && count($mails) === $antes + 1, 'con wp_mail fallando el aviso devuelve false');
$falla_mail = false;
$wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $rita['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]);
at_rt_guardar($rita['id'], ['aprobacion' => $cambios]);
ok(at_rt_aviso_listo($rita['id']) === false && count($mails) === $antes + 1 && at_rt_obtener($rita['id'])['aprobacion'] === $cambios, 'con una condición de parada no se emite ni se avisa');
$sara = rt_res_seg(['nombre' => '[PRUEBA] Sara Pausada', 'estado' => 'pausado'], ['aprobacion' => $cambios]);
ok(at_rt_aviso_listo($sara['id']) === false && count($mails) === $antes + 1 && at_rt_obtener($sara['id'])['aprobacion'] === $cambios, 'seguimiento en pausa: no se avisa');
$teo = rt_res_seg(['nombre' => '[PRUEBA] Teo Sinrender'], ['aprobacion' => $cambios]);
at_rt_guardar($teo['id'], ['render' => array_merge($teo['render'], ['estado' => 'pendiente'])]);
ok(at_rt_aviso_listo($teo['id']) === false && count($mails) === $antes + 1, 'sin presentación lista no hay nada que avisar');

// ---- Copia (CC): el resumen y el aviso van también a la copia, con una cabecera Cc ----
$copia = $marca . '-copia@example.com';
$semana_guardada = get_option('at_rt_resumen_semana'); $desde_guardado = get_option('at_rt_resumen_desde');
update_option('at_rt_resumen_copia', $copia);
$antes = count($mails);
$r = at_rt_resumen('2026-10-19 12:00:00', true);
$resumen_cc = $mails[$antes] ?? [];
ok($r['enviado'] && count($mails) === $antes + 1 && ($resumen_cc['to'] ?? '') === $destino && in_array('Cc: ' . $copia, (array) ($resumen_cc['headers'] ?? []), true) && in_array('Content-Type: text/html; charset=UTF-8', (array) ($resumen_cc['headers'] ?? []), true), 'con copia, el resumen va al destino con la cabecera Cc y sigue en HTML');
$uma = rt_res_seg(['nombre' => '[PRUEBA] Uma Copia'], ['aprobacion' => ['estado' => 'cambios', 'n' => 1, 'usada' => true]]);
$antes = count($mails);
ok(at_rt_aviso_listo($uma['id']) === true && count($mails) === $antes + 1 && in_array('Cc: ' . $copia, (array) ($mails[$antes]['headers'] ?? []), true) && ($mails[$antes]['to'] ?? '') === $destino, 'con copia, el aviso va al destino con la cabecera Cc');
update_option('at_rt_resumen_copia', $destino);
$antes = count($mails);
at_rt_guardar($uma['id'], ['aprobacion' => ['estado' => 'cambios', 'n' => 2, 'usada' => true]]);
ok(at_rt_aviso_listo($uma['id']) === true && array_filter((array) ($mails[$antes]['headers'] ?? []), static fn($h) => stripos((string) $h, 'cc:') === 0) === [], 'con la copia igual al destino no se agrega Cc');
delete_option('at_rt_resumen_copia');
update_option('at_rt_resumen_semana', $semana_guardada); update_option('at_rt_resumen_desde', $desde_guardado);

// ---- Reloj por hora: detectar → reintentar redacciones → resumen → (en la ventana) envío ----
$tk = $marca . '-tk';
$GLOBALS['rt_res_prefijo'] = $tk;
update_option('at_rt_resumen_semana', '2026-W45');

$p_det = rt_res_prop(['client_email' => $tk . '-prop@example.com', 'client_name' => '[PRUEBA] Ulises Detectado', 'flujo' => 'v3', 'created_at' => '2026-10-20 10:00:00']);
$tomas = rt_res_seg(['nombre' => '[PRUEBA] Tomas Reloj', 'proximo_at' => '2026-11-09'], [], $tk);
$atascadas = [
	'error' => [['estado' => 'error', 'pedida_at' => '2026-11-08 09:00:00', 'pedido' => '[PRUEBA] Pedido de Luis'], true, []],
	'pendiente hace 7 h' => [['estado' => 'pendiente', 'pedida_at' => '2026-11-09 02:00:00'], true, []],
	'pendiente hace 4 h' => [['estado' => 'pendiente', 'pedida_at' => '2026-11-09 04:30:00'], false, []],
	// Detectado + 3 atascadas = 4 pedidos: con el tope de 3 por pasada (Ruling 12), esta espera la pasada siguiente.
	'vacía (cuarto pedido: espera el tope)' => [[], false, []],
	'error con reintento del reloj hace 2 h' => [['estado' => 'error', 'pedida_at' => '2026-11-09 07:00:00', 'reintento_at' => '2026-11-09 07:00:00'], false, []],
	'revisión' => [['estado' => 'revision', 'pedida_at' => '2026-11-01 09:00:00'], false, []],
	'pausado' => [['estado' => 'error', 'pedida_at' => '2026-11-01 09:00:00'], false, ['estado' => 'pausado']],
	'próximo envío futuro' => [['estado' => 'error', 'pedida_at' => '2026-11-01 09:00:00'], false, ['proximo_at' => '2026-11-16']],
	'dado de baja' => [['estado' => 'error', 'pedida_at' => '2026-11-01 09:00:00'], false, []],
];
$seg_at = [];
foreach ($atascadas as $caso => [$redaccion, $reintenta, $alta]) {
	$s = rt_res_seg($alta + ['nombre' => '[PRUEBA] Atascada ' . $caso, 'proximo_at' => '2026-11-09'], [], $tk);
	at_rt_guardar($s['id'], ['redaccion' => $redaccion, 'render' => ['estado' => 'pendiente', 'nota' => 'Esperando redacción']]);
	if ($caso === 'dado de baja') { $wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $s['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]); }
	$seg_at[$caso] = $s['id'];
}
$antes = count($mails); $pedidos_redaccion = [];
$filtros = ['lista' => 0, 'reloj' => 0, 'deteccion' => 0];
$r = at_rt_tick('2026-11-09 09:05:00');
$det = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE propuesta_id = %d LIMIT 1", $p_det));
ok($filtros['deteccion'] > 0 && $filtros['reloj'] > 0 && $filtros['lista'] > 0, 'los filtros de la prueba acotaron de verdad la detección, el reloj y el resumen');
ok($r['detectados'] === 1 && $det > 0 && at_rt_obtener($det)['estado'] === 'activo', 'lunes 09:05: el reloj detecta la propuesta estancada');
$pedidos = array_column($pedidos_redaccion, 'id');
$esperados = [$seg_at['error'], $seg_at['pendiente hace 7 h']];
ok(($pedidos[0] ?? 0) === $det && array_slice($pedidos, 1) === $esperados && $r['redacciones'] === 2, 'primero se pide la redacción del detectado y después se reintentan las atascadas, hasta 3 pedidos por pasada');
ok(array_unique(array_column($pedidos_redaccion, 'timeout')) === [5], 'los pedidos que hace el reloj esperan 5 s a n8n');
foreach ($atascadas as $caso => [$redaccion, $reintenta]) {
	$red = at_rt_obtener($seg_at[$caso])['redaccion'];
	ok($reintenta ? (($red['estado'] ?? '') === 'pendiente' && ($red['pedida_at'] ?? '') === '2026-11-09 09:05:00' && ($red['reintento_at'] ?? '') === '2026-11-09 09:05:00') : $red === $redaccion, 'redacción ' . $caso . ': ' . ($reintenta ? 'se vuelve a pedir con la hora del reloj' : 'no se vuelve a pedir'));
}
ok((at_rt_obtener($seg_at['error'])['redaccion']['pedido'] ?? '') === '[PRUEBA] Pedido de Luis', 'el reintento conserva el pedido de Luis');
$res_tick = rt_res_correos($destino);
ok($r['resumen'] === ['enviado' => true, 'tarjetas' => 8, 'motivo' => 'enviado'] && count($mails) === $antes + 1 && count($res_tick) >= 1 && end($res_tick) === end($mails), 'lunes 09:05: el reloj manda el resumen');
ok($r['enviados'] === 0 && count(array_filter($mails, static fn($m) => str_starts_with((string) $m['to'], $tk))) === 0, 'y ningún correo a los prospectos fuera de la ventana');
$html_tk = end($mails)['message'];
ok(str_contains(rt_res_tarjeta($html_tk, '[PRUEBA] Tomas Reloj'), '✅ Aprobar y enviar') && str_contains(rt_res_tarjeta($html_tk, '[PRUEBA] Ulises Detectado'), 'En preparación') && str_contains(rt_res_tarjeta($html_tk, '[PRUEBA] Ulises Detectado'), 'hace 20 días'), 'el resumen del reloj trae el listo y el recién detectado en preparación');
$antes = count($mails);
$r = at_rt_tick('2026-11-09 09:35:00');
$vacia = at_rt_obtener($seg_at['vacía (cuarto pedido: espera el tope)'])['redaccion'];
ok($r['resumen']['motivo'] === 'no_toca' && $r['detectados'] === 0 && $r['redacciones'] === 1 && count($mails) === $antes, 'otra pasada en la misma hora: ni resumen ni pedidos repetidos');
ok(($vacia['estado'] ?? '') === 'pendiente' && ($vacia['pedida_at'] ?? '') === '2026-11-09 09:35:00', 'la que esperó por el tope se pide en la pasada siguiente');
$apr = at_rt_resolver_aprobacion($tomas['id'], 'aprobar', rt_res_q($tomas['id'], 'aprobar'), '2026-11-09 09:40:00');
ok($apr['ok'] && ($apr['programado'] ?? '') === '2026-11-09', 'Luis aprueba desde el resumen: sale hoy');
$r = at_rt_tick('2026-11-09 10:30:00');
ok($r['enviados'] === 1 && count(rt_res_correos($tomas['email'])) === 1 && at_rt_obtener($tomas['id'])['aprobacion']['estado'] === 'enviado', 'lunes 10:30: el reloj manda el aprobado');
ok($r['resumen']['motivo'] === 'no_toca' && count($mails) === $antes + 1 && $r['redacciones'] === 0, 'y no repite el resumen ni los pedidos de redacción');
// ---- Ruling 12: en la ventana, el envío de lo aprobado va primero (la lista de WhatsApp queda armada antes de cualquier
// pedido lento a n8n) y una pasada inicia como máximo 3 pedidos de redacción (detección + reintentos), cada uno a 5 s ----
$tp = $marca . '-tope';
$GLOBALS['rt_res_prefijo'] = $tp;
$eventos = [];
add_filter('pre_wp_mail', static function ($v, $d) use (&$eventos) { $eventos[] = 'correo:' . $d['to']; return $v; }, 90, 2);
// Antes que el bloqueo del bootstrap: anota cada intento de HTTP al flujo de redacción y su timeout, sin responder.
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$eventos) {
	if ($url === AT_N8N_RETOMAR_REDACTAR) { $eventos[] = 'http:' . (int) (json_decode($args['body'], true)['id'] ?? 0) . ':' . ($args['timeout'] ?? ''); }
	return $pre;
}, 1, 3);
$solo_http = static fn(array $ev): array => array_values(array_filter($ev, static fn($e) => str_starts_with($e, 'http:')));
$vale = rt_res_seg(['nombre' => '[PRUEBA] Vale Whatsapp', 'proximo_at' => '2026-11-23', 'telefono' => '+56900000009', 'wa_autorizado' => 1], [], $tp);
at_rt_guardar($vale['id'], ['aprobacion' => ['estado' => 'aprobado', 'n' => 1, 'nonce' => str_repeat('b', 16), 'huella' => $vale['render']['huella'], 'emitido_at' => '2026-11-16 09:00:00', 'vence' => rt_res_ts('2026-11-23 09:00:00'), 'usada' => true, 'resuelto_at' => '2026-11-16 09:05:00', 'programado' => '2026-11-23', 'destino' => at_rt_aprobacion_destino($vale)]]);
$atasco = [];
foreach (['Uno', 'Dos'] as $cual) {
	$s = rt_res_seg(['nombre' => '[PRUEBA] Atasco ' . $cual, 'proximo_at' => '2026-11-23'], [], $tp);
	at_rt_guardar($s['id'], ['redaccion' => ['estado' => 'error', 'pedida_at' => '2026-11-20 10:00:00'], 'render' => ['estado' => 'pendiente']]);
	$atasco[] = $s['id'];
}
$props_tope = [];
for ($i = 1; $i <= 4; $i++) { $props_tope[] = rt_res_prop(['client_email' => $tp . '-prop' . $i . '@example.com', 'client_name' => '[PRUEBA] Detectado ' . $i, 'flujo' => 'v3', 'created_at' => '2026-11-02 10:00:00']); }
update_option('at_rt_resumen_semana', '2026-W47');
$antes = count($mails);
$r = at_rt_tick('2026-11-23 10:30:00');
$http = $solo_http($eventos);
$detectados_tope = array_map(static fn($pid) => (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE propuesta_id = %d LIMIT 1", $pid)), $props_tope);
ok($r['enviados'] === 1 && count($r['whatsapp']) === 1 && ($r['whatsapp'][0]['id'] ?? 0) === $vale['id'], 'en la ventana el reloj devuelve su pedido de WhatsApp aunque después haga pedidos a n8n');
ok(($eventos[0] ?? '') === 'correo:' . $vale['email'] && array_search($http[0] ?? '', $eventos, true) > 0, 'el correo aprobado sale antes de cualquier pedido de redacción');
ok($r['detectados'] === 4 && count(array_filter($detectados_tope)) === 4 && count($http) === 3 && $r['redacciones'] === 0, 'cuatro detectados pero solo 3 pedidos de redacción en la pasada (tope)');
ok(array_unique(array_map(static fn($e) => substr($e, strrpos($e, ':') + 1), $http)) === ['5'], 'los pedidos del reloj usan un timeout de 5 s');
$sin_pedir = array_values(array_filter($detectados_tope, static fn($id) => at_rt_obtener($id)['redaccion'] === []));
ok(count($sin_pedir) === 1 && at_rt_obtener($sin_pedir[0])['estado'] === 'activo', 'el detectado que no cupo queda activo con la redacción vacía, para el reintento');
ok(at_rt_obtener($atasco[0])['redaccion'] === ['estado' => 'error', 'pedida_at' => '2026-11-20 10:00:00'] && at_rt_obtener($atasco[1])['redaccion'] === ['estado' => 'error', 'pedida_at' => '2026-11-20 10:00:00'], 'sin cupo, las atascadas esperan la pasada siguiente');
ok(count($mails) === $antes + 2 && ($mails[$antes]['to'] ?? '') === $vale['email'] && ($mails[$antes + 1]['to'] ?? '') === $destino, 'el resumen semanal sale después del envío al prospecto');
$eventos = [];
$r = at_rt_tick('2026-11-23 11:30:00');
ok($solo_http($eventos) === ['http:' . $atasco[0] . ':5', 'http:' . $atasco[1] . ':5', 'http:' . $sin_pedir[0] . ':5'] && $r['redacciones'] === 3 && $r['detectados'] === 0, 'la pasada siguiente pide las dos atascadas y el detectado que esperaba: otra vez 3');
$eventos = [];
$r = at_rt_tick('2026-11-23 12:30:00');
ok($solo_http($eventos) === [] && $r['redacciones'] === 0 && $r['enviados'] === 0, 'con todo pedido, la tercera pasada no llama a n8n');
// ---- I4 e I2: lo que no se arregla solo se ve «Necesita revisión», nunca «En preparación» para siempre ----
$tp_tipo = $marca . '-tipo';
$ahora_t = '2026-10-19 12:00:00';
$tipo_de = static fn(array $c, array $despues = []): string => at_rt_resumen_tipo(rt_res_seg($c, $despues, $tp_tipo), $ahora_t);
$espera_red = ['estado' => 'pendiente', 'nota' => 'Esperando redacción'];
$casos_tipo = [
	'presentación pendiente hace 25 h' => [[], ['render' => ['estado' => 'pendiente', 'pedido_at' => '2026-10-18 11:00:00']], 'revision'],
	'presentación pendiente hace 23 h' => [[], ['render' => ['estado' => 'pendiente', 'pedido_at' => '2026-10-18 13:00:00']], 'preparacion'],
	'presentación pendiente sin pedido_at (nunca se pidió)' => [[], ['render' => ['estado' => 'pendiente']], 'revision'],
	'presentación en error' => [[], ['render' => ['estado' => 'error', 'nota' => 'x']], 'revision'],
	'redacción en error, sin reintento del reloj' => [[], ['redaccion' => ['estado' => 'error', 'pedida_at' => '2026-10-19 08:00:00'], 'render' => $espera_red], 'preparacion'],
	'redacción en error después de un reintento del reloj' => [[], ['redaccion' => ['estado' => 'error', 'pedida_at' => '2026-10-19 08:00:00', 'reintento_at' => '2026-10-19 08:00:00'], 'render' => $espera_red], 'revision'],
	'redacción pendiente durante un reintento' => [[], ['redaccion' => ['estado' => 'pendiente', 'pedida_at' => '2026-10-19 11:00:00', 'reintento_at' => '2026-10-19 11:00:00'], 'render' => $espera_red], 'preparacion'],
	'correo inválido, sin aprobar' => [['email' => ''], [], 'revision'],
	'lista y con correo válido' => [[], [], 'normal'],
];
foreach ($casos_tipo as $caso => [$c, $despues, $esperado]) { ok($tipo_de($c, $despues) === $esperado, "tipo ({$caso}): {$esperado}"); }
$aprobado_con = static fn(array $s): array => ['estado' => 'aprobado', 'n' => 1, 'nonce' => str_repeat('a', 16), 'huella' => (string) $s['render']['huella'], 'usada' => true, 'programado' => '2026-10-19', 'destino' => at_rt_aprobacion_destino($s)];
$xa = rt_res_seg([], [], $tp_tipo);
at_rt_guardar($xa['id'], ['aprobacion' => $aprobado_con($xa)]);
ok(at_rt_resumen_tipo(at_rt_obtener($xa['id']), $ahora_t) === '', 'tipo: aprobado vigente con correo válido no va (espera su envío)');
$casos_aprobado = [
	[['estado' => 'error', 'nota' => 'x'], 'revision', 'aprobado con la presentación en error'],
	[['estado' => 'pendiente', 'pedido_at' => '2026-10-19 11:00:00'], 'preparacion', 'aprobado con la presentación pedida hace una hora'],
	[['estado' => 'pendiente', 'pedido_at' => '2026-10-18 11:00:00'], 'revision', 'aprobado con la presentación pendiente hace 25 h'],
];
foreach ($casos_aprobado as [$render, $esperado, $caso]) {
	at_rt_guardar($xa['id'], ['render' => array_merge($xa['render'], $render)]);
	ok(at_rt_resumen_tipo(at_rt_obtener($xa['id']), $ahora_t) === $esperado, "tipo ({$caso}): {$esperado}");
}
ok(at_rt_panel_revision(at_rt_obtener($xa['id']), $ahora_t) === 'Necesita revisión', 'el panel dice «Necesita revisión» con la misma regla');
$xc = rt_res_seg(['email' => ''], [], $tp_tipo);
at_rt_guardar($xc['id'], ['aprobacion' => $aprobado_con($xc)]);
ok(at_rt_resumen_tipo(at_rt_obtener($xc['id']), $ahora_t) === 'revision', 'tipo (I2): aprobado vigente con el correo inválido: revision, nunca espera un envío que no puede salir');
// Días: una propuesta vinculada que sigue `lista` nunca se envió; se cuentan desde el alta del seguimiento.
$p_lista = rt_res_prop(['status' => 'lista', 'flujo' => 'v3', 'created_at' => '2026-09-01 10:00:00']);
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', ['propuesta_id' => $p_lista, 'detail_type' => 'propuesta_enviada', 'title' => '[PRUEBA] enviada', 'status' => 'completed', 'completed_date' => '2026-09-02', 'created_at' => '2026-09-02 10:00:00']);
$sl = rt_res_seg(['propuesta_id' => $p_lista], [], $tp_tipo);
$wpdb->update($wpdb->prefix . 'at_retomar', ['creado_at' => '2026-10-14 10:00:00'], ['id' => $sl['id']]);
ok(at_rt_resumen_dias(at_rt_obtener($sl['id']), '2026-10-19') === 5, 'días: con la propuesta vinculada en lista, desde el alta del seguimiento');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'sent'], ['id' => $p_lista]);
ok(at_rt_resumen_dias(at_rt_obtener($sl['id']), '2026-10-19') === 47, 'días: ya enviada, desde el envío anotado');
// Si Luis aprueba mientras se arma el resumen (la emisión nunca pisa un aprobado vigente), no va tarjeta.
$rc = rt_res_seg([], [], $tp_tipo);
$snap = at_rt_obtener($rc['id']);
at_rt_guardar($rc['id'], ['aprobacion' => $aprobado_con($snap)]);
$ap_rc = at_rt_obtener($rc['id'])['aprobacion'];
ok(at_rt_resumen_tipo($snap, $ahora_t) === 'normal' && at_rt_resumen_tarjeta($snap, $ahora_t) === null && at_rt_obtener($rc['id'])['aprobacion'] === $ap_rc, 'aprobado entretanto: sin tarjeta y la aprobación de Luis intacta');

// ---- I4: el reloj vuelve a pedir las presentaciones atascadas, dentro del mismo tope de 3 pedidos por pasada ----
if (!defined('AT_N8N_RETOMAR_RENDER')) { define('AT_N8N_RETOMAR_RENDER', 'https://example.invalid/webhook/retomar-render'); }
$pedidos_render = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$pedidos_render) {
	if ($url !== AT_N8N_RETOMAR_RENDER) { return $pre; }
	$pedidos_render[] = ['id' => (int) (json_decode($args['body'], true)['id'] ?? 0), 'timeout' => $args['timeout'] ?? null];
	return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []];
}, PHP_INT_MAX, 3);
$rr = $marca . '-rr';
$GLOBALS['rt_res_prefijo'] = $rr;
update_option('at_rt_resumen_semana', '2026-W49'); // el resumen de la semana ya salió: solo se prueba el reintento
$rr_atascadas = [];
foreach (['Uno', 'Dos'] as $cual) {
	$s = rt_res_seg(['nombre' => '[PRUEBA] Redacción atascada ' . $cual, 'proximo_at' => '2026-12-01'], [], $rr);
	at_rt_guardar($s['id'], ['redaccion' => ['estado' => 'error', 'pedida_at' => '2026-11-28 10:00:00'], 'render' => $espera_red]);
	$rr_atascadas[] = $s['id'];
}
$rr_casos = [
	'pendiente hace 7 h' => [[], ['estado' => 'pendiente', 'pedido_at' => '2026-12-01 02:00:00'], true],
	'pendiente hace 3 h' => [[], ['estado' => 'pendiente', 'pedido_at' => '2026-12-01 06:00:00'], false],
	'pendiente hace 7 h, reintentada hace 2 h' => [[], ['estado' => 'pendiente', 'pedido_at' => '2026-12-01 02:00:00', 'reintento_at' => '2026-12-01 07:05:00'], false],
	'pendiente sin pedido_at' => [[], ['estado' => 'pendiente'], true],
	'en error' => [[], ['estado' => 'error', 'pedido_at' => '2026-11-30 09:00:00'], false],
	'lista' => [[], null, false],
	'pausado' => [['estado' => 'pausado'], ['estado' => 'pendiente', 'pedido_at' => '2026-11-30 09:00:00'], false],
	'próximo envío futuro' => [['proximo_at' => '2026-12-08'], ['estado' => 'pendiente', 'pedido_at' => '2026-11-30 09:00:00'], false],
	'dado de baja' => [[], ['estado' => 'pendiente', 'pedido_at' => '2026-11-30 09:00:00'], false],
];
$rr_ids = [];
foreach ($rr_casos as $caso => [$alta, $render, $pide]) {
	$s = rt_res_seg($alta + ['nombre' => '[PRUEBA] Presentación ' . $caso, 'proximo_at' => '2026-12-01'], [], $rr);
	if ($render !== null) { at_rt_guardar($s['id'], ['render' => array_merge($s['render'], $render)]); }
	if ($caso === 'dado de baja') { $wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $s['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]); }
	$rr_ids[$caso] = $s['id'];
}
$antes_rr = array_map(static fn(int $id): array => at_rt_obtener($id)['render'], $rr_ids);
$filtros = ['lista' => 0, 'reloj' => 0, 'deteccion' => 0];
$r = at_rt_tick('2026-12-01 09:05:00');
$r7 = at_rt_obtener($rr_ids['pendiente hace 7 h'])['render'];
ok($filtros['reloj'] > 0 && $r['redacciones'] === 2 && ($r['renders'] ?? null) === 1 && $pedidos_render === [['id' => $rr_ids['pendiente hace 7 h'], 'timeout' => 5]], 'martes 09:05: 2 redacciones atascadas + 1 presentación atascada = el tope de 3 pedidos; la presentación espera 5 s a n8n');
ok($r7['estado'] === 'pendiente' && $r7['pedido_at'] === '2026-12-01 09:05:00' && $r7['reintento_at'] === '2026-12-01 09:05:00' && preg_match('/^[a-f0-9]{16}$/D', (string) ($r7['generacion'] ?? '')) === 1 && $r7['generacion'] !== ($antes_rr['pendiente hace 7 h']['generacion'] ?? null), 'la presentación atascada se pide de nuevo con la hora del reloj y queda anotado el reintento');
$sin_cambio = true;
foreach ($rr_casos as $caso => [$alta, $render, $pide]) { if ($caso !== 'pendiente hace 7 h') { $sin_cambio = $sin_cambio && at_rt_obtener($rr_ids[$caso])['render'] === $antes_rr[$caso]; } }
ok($sin_cambio, 'las demás no se tocan (recientes, reintentadas hace poco, en error, listas, en pausa, futuras, de baja) y la sin pedido_at espera el tope');
ok(at_rt_resumen_tipo(at_rt_obtener($rr_ids['pendiente hace 7 h']), '2026-12-01 09:05:00') === 'preparacion', 'recién pedida de nuevo: «En preparación»');
$r = at_rt_tick('2026-12-01 09:35:00');
ok($r['redacciones'] === 0 && ($r['renders'] ?? null) === 1 && array_column($pedidos_render, 'id') === [$rr_ids['pendiente hace 7 h'], $rr_ids['pendiente sin pedido_at']], 'la pasada siguiente pide la que esperó el tope');
$r = at_rt_tick('2026-12-01 10:35:00');
ok(($r['renders'] ?? null) === 0 && count($pedidos_render) === 2, 'una vez cada 24 h: la tercera pasada no vuelve a pedirlas');
ok(at_rt_reintentar_renders('no es una fecha', 3) === [] && at_rt_reintentar_renders('2026-12-01 11:35:00', 0) === [], 'con una hora ilegible o sin cupo no se pide nada');

// ---- Re-revisión de la ola final: el contenido cambia por fuera de las rutas de guardado de Retomar (la propuesta vinculada
// pasa a v3) sin reiniciar el render. La tarjeta no se ofrece para aprobar —cada «Aprobar» respondería «cambio»—: la
// presentación queda desactualizada (conservando la publicada), se pide de nuevo y, con la nueva lista, se aprueba ----
// En PROD el sitio es HTTPS; el sitio local no, y el enlace a la propuesta v3 (home_url) debe ser HTTPS para presentarse.
$https_dr = static fn($url) => set_url_scheme($url, 'https');
add_filter('home_url', $https_dr);
$dr = $marca . '-deriva';
$GLOBALS['rt_res_prefijo'] = $dr;
$p_dr = rt_res_prop(['flujo' => 'v2']);
$deriva = rt_res_seg(['nombre' => '[PRUEBA] Deriva Externa', 'propuesta_id' => $p_dr, 'proximo_at' => '2026-12-07'], [], $dr);
$pub_dr = ['view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $deriva['token'] . '-' . str_repeat('9', 16) . '-retomar/index.html', 'pdf_url' => '', 'huella' => 'publicada', 'generacion' => str_repeat('9', 16)];
at_rt_guardar($deriva['id'], ['render' => array_merge($deriva['render'], ['publicada' => $pub_dr])]);
$e_dr = at_rt_emitir_aprobacion($deriva['id'], '2026-11-30 09:00:00');
$ap_dr = at_rt_obtener($deriva['id'])['aprobacion'];
ok(is_array($e_dr) && at_rt_panel_revision(at_rt_obtener($deriva['id']), '2026-12-07 09:00:00') === 'Lista para aprobar', 'deriva: antes del cambio externo, lista para aprobar');
$huella_dr = (string) at_rt_obtener($deriva['id'])['render']['huella'];
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['flujo' => 'v3'], ['id' => $p_dr]);
ok(at_rt_huella_render(at_rt_obtener($deriva['id'])) !== $huella_dr, 'deriva: la propuesta vinculada pasó a v3 por fuera y el contenido ya no es el de la presentación');
// Contenido que ya no se puede presentar (siete líneas guardadas por fuera, sin pasar por el panel): «Necesita revisión».
$rota = rt_res_seg(['nombre' => '[PRUEBA] Deriva Rota', 'proximo_at' => '2026-12-07'], [], $dr);
at_rt_guardar($rota['id'], ['render' => array_merge($rota['render'], ['publicada' => $pub_dr]), 'textos' => array_merge($rota['textos'], ['quedamos' => implode("\n", array_fill(0, 7, '[PRUEBA] línea'))])]);
$pedidos_render = [];
update_option('at_rt_resumen_semana', '2026-W49');
$antes = count($mails);
$r = at_rt_resumen('2026-12-07 09:00:00');
$html_dr = (string) (end($mails)['message'] ?? '');
$t_dr = rt_res_tarjeta($html_dr, '[PRUEBA] Deriva Externa');
$f_dr = at_rt_obtener($deriva['id']);
ok($r['enviado'] && count($mails) === $antes + 1 && str_contains($t_dr, 'En preparación') && !str_contains($t_dr, 'retomar-aprobar.php') && !str_contains($t_dr, '✅ Aprobar y enviar'), 'deriva: la tarjeta del resumen es «En preparación», sin botones de aprobar');
ok($f_dr['aprobacion'] === $ap_dr, 'deriva: no se emite una aprobación nueva');
ok($pedidos_render === [['id' => $deriva['id'], 'timeout' => 5]] && ($f_dr['render']['estado'] ?? '') === 'pendiente' && ($f_dr['render']['pedido_at'] ?? '') === '2026-12-07 09:00:00' && ($f_dr['render']['huella'] ?? '') === at_rt_huella_render($f_dr), 'deriva: la presentación queda pendiente y se pide de nuevo (5 s) con la huella del contenido actual');
ok(($f_dr['render']['publicada'] ?? null) === $pub_dr, 'deriva: la versión publicada se conserva');
ok(at_rt_resumen_tipo($f_dr, '2026-12-07 09:00:00') === 'preparacion' && at_rt_panel_revision($f_dr, '2026-12-07 09:00:00') === 'Preparando la presentación', 'deriva: el panel la ve en preparación, ya no «Lista para aprobar»');
$f_rota = at_rt_obtener($rota['id']);
ok(str_contains(rt_res_tarjeta($html_dr, '[PRUEBA] Deriva Rota'), 'Necesita revisión') && ($f_rota['render']['estado'] ?? '') === 'error' && str_contains((string) ($f_rota['render']['nota'] ?? ''), 'Corrígelo en el panel Retomar') && ($f_rota['render']['publicada'] ?? null) === $pub_dr && $f_rota['aprobacion'] === [], 'deriva a un contenido que no se puede presentar: «Necesita revisión», presentación en error con nota, la publicada intacta y sin emitir');
$gen_dr = (string) $f_dr['render']['generacion'];
$cb_dr = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar/' . $deriva['id'] . '/vista');
$cb_dr->set_header('Content-Type', 'application/json');
$cb_dr->set_header('X-AT-Secret', AT_REST_SECRET);
$cb_dr->set_body(wp_json_encode(['ok' => true, 'generacion' => $gen_dr, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $deriva['token'] . '-' . $gen_dr . '-retomar/index.html', 'pdf_url' => '']));
ok(rest_do_request($cb_dr)->get_status() === 200 && (at_rt_obtener($deriva['id'])['render']['estado'] ?? '') === 'listo', 'deriva: llega la presentación nueva (callback listo con la huella nueva)');
$r = at_rt_resumen('2026-12-14 09:00:00');
$t_dr = rt_res_tarjeta((string) (end($mails)['message'] ?? ''), '[PRUEBA] Deriva Externa');
$apr_dr = at_rt_resolver_aprobacion($deriva['id'], 'aprobar', rt_res_q($deriva['id'], 'aprobar'), '2026-12-14 09:30:00');
ok($r['enviado'] && str_contains($t_dr, '✅ Aprobar y enviar') && $apr_dr['ok'] && ($apr_dr['motivo'] ?? '') === '', 'deriva: el resumen siguiente la ofrece para aprobar y «Aprobar» funciona, sin «cambio»');
// Dentro del reloj, los pedidos que nacen de la emisión respetan el tope de 3 por pasada: dos redacciones atascadas + una
// presentación desactualizada llenan el cupo; la otra queda pendiente («En preparación», no «Necesita revisión») y la pide
// el reintento de presentaciones de una pasada siguiente (I4: a las 6 h).
$dt = $marca . '-derivatk';
$GLOBALS['rt_res_prefijo'] = $dt;
foreach (['Uno', 'Dos'] as $cual) {
	$s = rt_res_seg(['nombre' => '[PRUEBA] Deriva atascada ' . $cual, 'proximo_at' => '2026-12-21'], [], $dt);
	at_rt_guardar($s['id'], ['redaccion' => ['estado' => 'error', 'pedida_at' => '2026-12-18 10:00:00'], 'render' => $espera_red]);
}
$dt_ids = [];
foreach (['Uno', 'Dos'] as $cual) {
	$pid = rt_res_prop(['flujo' => 'v2']);
	$dt_ids[$cual] = rt_res_seg(['nombre' => '[PRUEBA] Deriva reloj ' . $cual, 'propuesta_id' => $pid, 'proximo_at' => '2026-12-21'], [], $dt)['id'];
	$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['flujo' => 'v3'], ['id' => $pid]);
}
$dt_antes = at_rt_obtener($dt_ids['Uno'])['render'];
update_option('at_rt_resumen_semana', '2026-W51');
$pedidos_render = []; $pedidos_redaccion = [];
$filtros = ['lista' => 0, 'reloj' => 0, 'deteccion' => 0];
$r = at_rt_tick('2026-12-21 09:05:00');
$html_dt = (string) (end($mails)['message'] ?? '');
$f_uno = at_rt_obtener($dt_ids['Uno']);
ok($filtros['lista'] > 0 && $r['redacciones'] === 2 && $r['resumen']['enviado'] && $pedidos_render === [['id' => $dt_ids['Dos'], 'timeout' => 5]], 'reloj: 2 redacciones + 1 presentación desactualizada pedida desde el resumen = el tope de 3');
ok(($f_uno['render']['estado'] ?? '') === 'pendiente' && ($f_uno['render']['pedido_at'] ?? '') === '2026-12-21 09:05:00' && ($f_uno['render']['generacion'] ?? null) === ($dt_antes['generacion'] ?? null), 'reloj: la que no cupo queda pendiente con la hora de la pasada, sin pedirla');
ok(str_contains(rt_res_tarjeta($html_dt, '[PRUEBA] Deriva reloj Uno'), 'En preparación') && str_contains(rt_res_tarjeta($html_dt, '[PRUEBA] Deriva reloj Dos'), 'En preparación') && at_rt_resumen_tipo($f_uno, '2026-12-21 09:05:00') === 'preparacion', 'reloj: las dos van «En preparación», ninguna «Necesita revisión»');
$pedidos_render = [];
$r = at_rt_tick('2026-12-21 15:06:00');
ok(in_array(['id' => $dt_ids['Uno'], 'timeout' => 5], $pedidos_render, true) && ($r['renders'] ?? 0) >= 1, 'reloj: la que esperó el tope la pide el reintento de presentaciones de una pasada siguiente');
remove_filter('home_url', $https_dr);

// Sin el esquema listo el reloj no hace nada.
update_option('at_rt_schema', '1', false);
$antes = count($mails); $pedidos_redaccion = [];
update_option('at_rt_resumen_semana', '2026-W45');
$r = at_rt_tick('2026-11-16 10:30:00');
update_option('at_rt_schema', $esquema_original, false);
ok($r['detectados'] === 0 && $r['redacciones'] === 0 && $r['enviados'] === 0 && ($r['resumen']['enviado'] ?? true) === false && count($mails) === $antes && $pedidos_redaccion === [], 'esquema sin listo: el reloj vuelve sin hacer nada');
fin();
