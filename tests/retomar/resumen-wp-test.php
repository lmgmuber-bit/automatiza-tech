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
foreach (['at_rt_resumen_semana', 'at_rt_resumen_desde', 'at_rt_resumen_destino', 'at_rt_resumen_dia', 'at_rt_resumen_hora', 'at_rt_wa_automatico'] as $o) { $originales[$o] = get_option($o, null); }
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
	$pedidos_redaccion[] = json_decode($args['body'], true);
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
at_rt_guardar($caro['id'], ['render' => ['estado' => 'pendiente', 'nota' => '']]);
$wpdb->update($wpdb->prefix . 'at_retomar', ['creado_at' => '2026-10-09 10:00:00'], ['id' => $caro['id']]);
$dani = rt_res_seg(['nombre' => '[PRUEBA] Dani Revision'], ['redaccion' => ['estado' => 'revision', 'nota' => '[PRUEBA] nota-de-revision-ia']]);
$eli = rt_res_seg(['nombre' => '[PRUEBA] Eli Vigente']);
$ap_eli = ['estado' => 'aprobado', 'n' => 1, 'nonce' => str_repeat('e', 16), 'huella' => $eli['render']['huella'], 'emitido_at' => '2026-10-12 09:00:00', 'vence' => rt_res_ts('2026-10-19 09:00:00'), 'usada' => true, 'resuelto_at' => '2026-10-12 09:05:00', 'programado' => '2026-10-19'];
at_rt_guardar($eli['id'], ['aprobacion' => $ap_eli]);
$fede = rt_res_seg(['nombre' => '[PRUEBA] Fede Aprobsinrender']);
$ap_fede = array_merge($ap_eli, ['nonce' => str_repeat('f', 16), 'huella' => $fede['render']['huella']]);
at_rt_guardar($fede['id'], ['aprobacion' => $ap_fede, 'render' => array_merge($fede['render'], ['estado' => 'pendiente'])]);
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
ok(str_contains($t_ana, 'Ver la presentación completa') && str_contains($t_ana, rt_res_esc($presentacion)) && str_contains($t_ana, rt_res_esc(at_rt_aprobacion_url($ana['id'], 'ver', $ap_ana['nonce'], $ap_ana['vence']))), '«Ver la presentación completa» es la página pública retomar.php y «Ver el correo» es el enlace ver');
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
ok(count($mails) === $antes + 1 && ($aviso['to'] ?? '') === $destino && ($aviso['subject'] ?? '') === '🔁 Retomar · Recordatorio corregido: [PRUEBA] Rita Aviso', 'un correo al destino con el asunto del recordatorio corregido');
$ap_rita = at_rt_obtener($rita['id'])['aprobacion'];
ok(($ap_rita['estado'] ?? '') === 'por_aprobar' && ($ap_rita['usada'] ?? null) === false && str_contains($aviso['message'] ?? '', rt_res_esc(at_rt_aprobacion_url($rita['id'], 'aprobar', (string) ($ap_rita['nonce'] ?? ''), (int) ($ap_rita['vence'] ?? 0)))), 'emite una aprobación nueva (sale de «cambios») con sus enlaces');
ok(substr_count($aviso['message'] ?? '', '✅ Aprobar y enviar') === 1 && substr_count($aviso['message'] ?? '', 'border:1px solid #d6dde6') === 1 && rt_res_sin_internos($aviso['message'] ?? '') && rt_res_enlaces_del_sitio($aviso['message'] ?? ''), 'una sola tarjeta, sin enlaces internos y todo HTTPS del sitio');
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
	'vacía' => [[], true, []],
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
$esperados = [$seg_at['error'], $seg_at['pendiente hace 7 h'], $seg_at['vacía']];
ok(($pedidos[0] ?? 0) === $det && array_slice($pedidos, 1) === $esperados && $r['redacciones'] === 3, 'primero se pide la redacción del detectado y después se reintentan solo las atascadas');
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
ok($r['resumen']['motivo'] === 'no_toca' && $r['detectados'] === 0 && $r['redacciones'] === 0 && count($mails) === $antes, 'otra pasada en la misma hora: ni resumen ni pedidos repetidos');
$apr = at_rt_resolver_aprobacion($tomas['id'], 'aprobar', rt_res_q($tomas['id'], 'aprobar'), '2026-11-09 09:40:00');
ok($apr['ok'] && ($apr['programado'] ?? '') === '2026-11-09', 'Luis aprueba desde el resumen: sale hoy');
$r = at_rt_tick('2026-11-09 10:30:00');
ok($r['enviados'] === 1 && count(rt_res_correos($tomas['email'])) === 1 && at_rt_obtener($tomas['id'])['aprobacion']['estado'] === 'enviado', 'lunes 10:30: el reloj manda el aprobado');
ok($r['resumen']['motivo'] === 'no_toca' && count($mails) === $antes + 1 && $r['redacciones'] === 0, 'y no repite el resumen ni los pedidos de redacción');
// Sin el esquema listo el reloj no hace nada.
update_option('at_rt_schema', '1', false);
$antes = count($mails); $pedidos_redaccion = [];
update_option('at_rt_resumen_semana', '2026-W45');
$r = at_rt_tick('2026-11-16 10:30:00');
update_option('at_rt_schema', $esquema_original, false);
ok($r['detectados'] === 0 && $r['redacciones'] === 0 && $r['enviados'] === 0 && ($r['resumen']['enviado'] ?? true) === false && count($mails) === $antes && $pedidos_redaccion === [], 'esquema sin listo: el reloj vuelve sin hacer nada');
fin();
