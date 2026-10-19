<?php
// Aprobación por enlace firmado: emitir, validar y resolver los botones del correo semanal, y la página de confirmación.
// Review Focus 1: los antivirus abren todos los enlaces (GET y HEAD): nada puede aprobar, omitir ni pedir cambios.
// Review Focus 2: doble clic o dos dispositivos: el segundo POST responde «usado» y no cambia nada.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_emitir_aprobacion', 'at_rt_aprobacion_motivo', 'at_rt_resolver_aprobacion', 'at_rt_pagina_aprobacion');
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', bin2hex(random_bytes(24))); }
at_rt_migrar_esquema();
global $wpdb;
$ids = [];
$emails = [];
$marca = 'rt-aprob-' . strtolower(wp_generate_password(8, false, false));
$crm_falso = 987650000 + random_int(1, 9999);
$wa_original = get_option('at_rt_wa_automatico', null);
register_shutdown_function(static function () use (&$ids, &$emails, $wpdb, $crm_falso, $wa_original) {
	foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
	foreach ($emails as $email) { $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['email' => $email]); }
	$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $crm_falso]);
	$wa_original === null ? delete_option('at_rt_wa_automatico') : update_option('at_rt_wa_automatico', $wa_original);
});
update_option('at_rt_wa_automatico', '0');

/** Seguimiento listo para aprobar: activo, redacción lista y render listo con la huella real de su contenido. */
function rt_ap_seg(array $extra = []): int {
	global $ids, $emails, $marca;
	$email = $marca . '-' . count($ids) . '@example.com';
	$emails[] = $email;
	$id = at_rt_crear($extra + ['nombre' => '[PRUEBA] Ana', 'negocio' => '[PRUEBA] Panadería', 'email' => $email, 'telefono' => '+56900000000', 'wa_autorizado' => 1, 'estado' => 'activo', 'activado_at' => '2026-10-01 00:00:00', 'proximo_at' => '2026-10-19', 'textos' => ['quedamos' => '[PRUEBA] Quedamos en retomar el pedido.', 'proponemos' => '[PRUEBA] Proponemos partir simple.', 'partir' => '[PRUEBA] Partimos esta semana.']]);
	$ids[] = $id;
	rt_ap_al_dia($id);
	return $id;
}
/** Deja la redacción lista y el render listo con la huella del contenido actual (como un render recién terminado). */
function rt_ap_al_dia(int $id): void {
	$s = at_rt_obtener($id);
	at_rt_guardar($id, ['redaccion' => ['estado' => 'lista', 'generacion' => str_repeat('a', 16), 'intentos' => 1], 'render' => ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/index.html']]);
	$s = at_rt_obtener($id);
	at_rt_guardar($id, ['render' => array_merge($s['render'], ['huella' => at_rt_huella_render($s)])]);
}
/** Query del enlace firmado, como la recibe la página en $_GET. */
function rt_ap_q(string $url): array {
	parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
	return $q;
}
/** Lo que una decisión puede cambiar: si esto es igual antes y después, no pasó nada. */
function rt_ap_foto(int $id): array {
	$s = at_rt_obtener($id);
	return [$s['aprobacion'], $s['proximo_at'], $s['notas'], $s['redaccion'], $s['estado'], $s['envios']];
}
function rt_ap_ahora(): string { return (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s'); }
function rt_ap_post(array $q, array $extra = []): array {
	return at_rt_pagina_aprobacion($q, 'POST', $extra + ['_wpnonce' => wp_create_nonce('at_rt_aprobar_' . (int) $q['id'])]);
}

// ---- Emitir ----
$a = rt_ap_seg();
$ahora = rt_ap_ahora();
$enlaces = at_rt_emitir_aprobacion($a, $ahora);
$ap = at_rt_obtener($a)['aprobacion'];
ok(is_array($enlaces) && array_keys($enlaces) === ['aprobar', 'cambios', 'omitir', 'ver'], 'emitir devuelve los cuatro enlaces');
ok($ap['estado'] === 'por_aprobar' && $ap['n'] === 1 && preg_match('/^[a-f0-9]{16}$/D', $ap['nonce']) === 1 && $ap['huella'] === at_rt_obtener($a)['render']['huella'] && $ap['usada'] === false && $ap['emitido_at'] === $ahora, 'aprobación por_aprobar con n, nonce hex16, huella del render y sin usar');
ok($ap['vence'] === at_rt_ahora($ahora)->getTimestamp() + 604800, 'vence a los 604800 s de la emisión');
$todas_https = true;
foreach ($enlaces as $accion => $url) {
	$q = rt_ap_q($url);
	$todas_https = $todas_https && at_rt_url_correo($url) === $url && str_starts_with($url, 'https://') && str_contains($url, '/retomar-aprobar.php?')
		&& (int) $q['id'] === $a && $q['a'] === $accion && $q['k'] === $ap['nonce'] && (int) $q['v'] === $ap['vence']
		&& at_rt_firma_valida($q['f'], $a, $accion, $ap['nonce'], $ap['vence'], wp_salt('auth'));
}
ok($todas_https, 'cada enlace es HTTPS, pasa at_rt_url_correo y va firmado con wp_salt(auth) para su acción');
$n2 = rt_ap_seg();
at_rt_guardar($n2, ['envios' => [['tipo' => 'envio', 'n' => 1, 'correo' => ['estado' => 'enviado']], ['tipo' => 'diferido']]]);
at_rt_emitir_aprobacion($n2, $ahora);
ok(at_rt_obtener($n2)['aprobacion']['n'] === 2, 'n = recordatorios enviados + 1');
$sin = rt_ap_seg();
at_rt_guardar($sin, ['redaccion' => ['estado' => 'pendiente']]);
ok(at_rt_emitir_aprobacion($sin, $ahora) === null && at_rt_obtener($sin)['aprobacion'] === [], 'sin redacción lista no emite ni guarda');
at_rt_guardar($sin, ['redaccion' => ['estado' => 'lista'], 'render' => ['estado' => 'pendiente', 'huella' => 'x']]);
ok(at_rt_emitir_aprobacion($sin, $ahora) === null && at_rt_obtener($sin)['aprobacion'] === [], 'sin render listo no emite');
at_rt_guardar($sin, ['render' => ['estado' => 'listo']]);
ok(at_rt_emitir_aprobacion($sin, $ahora) === null && at_rt_obtener($sin)['aprobacion'] === [], 'render listo sin huella no emite (un enlace sin huella no se podría invalidar)');
ok(at_rt_emitir_aprobacion($a, 'no es fecha') === null && at_rt_obtener($a)['aprobacion'] === $ap, 'fecha inválida no emite ni toca la aprobación vigente');
ok(at_rt_emitir_aprobacion(PHP_INT_MAX, $ahora) === null, 'seguimiento inexistente: null');

// ---- Motivos (orden fijado: firma, vencido, no_disponible, cambio, usado, no_disponible por estado) ----
$s = at_rt_obtener($a);
$qa = rt_ap_q($enlaces['aprobar']);
$qv = rt_ap_q($enlaces['ver']);
$t = time();
ok(at_rt_aprobacion_motivo($s, 'aprobar', $qa, $t) === '', 'enlace vigente: sin motivo');
$mala = $qa; $mala['f'] = substr($qa['f'], 0, -1) . ($qa['f'][31] === '0' ? '1' : '0');
ok(at_rt_aprobacion_motivo($s, 'aprobar', $mala, $t) === 'firma', 'firma alterada: firma');
ok(at_rt_aprobacion_motivo($s, 'aprobar', $qv, $t) === 'firma', 'la firma de «ver» no sirve para «aprobar»');
ok(at_rt_aprobacion_motivo($s, 'aprobar', array_merge($qa, ['v' => (string) ($ap['vence'] + 86400)]), $t) === 'firma', 'alargar el vencimiento rompe la firma');
ok(at_rt_aprobacion_motivo($s, 'aprobar', array_merge($qa, ['id' => (string) $n2]), $t) === 'firma', 'cambiar el id rompe la firma');
ok(at_rt_aprobacion_motivo($s, 'aprobar', ['id' => [$a], 'k' => ['x'], 'v' => 'x', 'f' => null], $t) === 'firma', 'parámetros estructurados: firma, sin error');
ok(at_rt_aprobacion_motivo([], 'aprobar', $mala, $t) === 'firma', 'orden: firma antes que no_disponible (no revela si el seguimiento existe)');
ok(at_rt_aprobacion_motivo($s, 'aprobar', $qa, $ap['vence']) === '' && at_rt_aprobacion_motivo($s, 'aprobar', $qa, $ap['vence'] + 1) === 'vencido', 'ts = vence vale; ts > vence: vencido');
ok(at_rt_aprobacion_motivo([], 'aprobar', $qa, $ap['vence'] + 1) === 'vencido', 'orden: vencido antes que no_disponible');
ok(at_rt_aprobacion_motivo([], 'aprobar', $qa, $t) === 'no_disponible', 'sin seguimiento: no_disponible');
ok(at_rt_aprobacion_motivo(array_merge($s, ['aprobacion' => []]), 'aprobar', $qa, $t) === 'no_disponible', 'sin aprobación: no_disponible');
ok(at_rt_aprobacion_motivo(array_merge($s, ['id' => $n2]), 'aprobar', $qa, $t) === 'no_disponible', 'seguimiento distinto del enlace: no_disponible');
$otro_nonce = array_merge($s, ['aprobacion' => array_merge($ap, ['nonce' => str_repeat('b', 16)])]);
ok(at_rt_aprobacion_motivo($otro_nonce, 'aprobar', $qa, $t) === 'cambio' && at_rt_aprobacion_motivo($otro_nonce, 'ver', $qv, $t) === 'cambio', 'nonce distinto del vigente: cambio (también ver)');
ok(at_rt_aprobacion_motivo($otro_nonce, 'aprobar', $qa, $ap['vence'] + 1) === 'vencido', 'orden: vencido antes que cambio');
$otra_huella = array_merge($s, ['render' => array_merge($s['render'], ['huella' => 'otra'])]);
ok(at_rt_aprobacion_motivo($otra_huella, 'aprobar', $qa, $t) === 'cambio', 'huella del render distinta: cambio');
$sin_huella = array_merge($s, ['render' => ['estado' => 'pendiente', 'nota' => 'Contenido guardado']]);
ok(at_rt_aprobacion_motivo($sin_huella, 'aprobar', $qa, $t) === 'cambio', 'render guardado de nuevo sin huella: cambio');
$usada = array_merge($s, ['aprobacion' => array_merge($ap, ['usada' => true, 'estado' => 'aprobado'])]);
ok(at_rt_aprobacion_motivo($usada, 'aprobar', $qa, $t) === 'usado' && at_rt_aprobacion_motivo($usada, 'omitir', rt_ap_q($enlaces['omitir']), $t) === 'usado', 'usada: usado');
ok(at_rt_aprobacion_motivo(array_merge($usada, ['aprobacion' => array_merge($usada['aprobacion'], ['nonce' => str_repeat('c', 16)])]), 'aprobar', $qa, $t) === 'cambio', 'orden: cambio antes que usado');
ok(at_rt_aprobacion_motivo($usada, 'ver', $qv, $t) === '', '«ver» no es de un solo uso');
$estado_raro = array_merge($s, ['aprobacion' => array_merge($ap, ['estado' => 'enviado'])]);
ok(at_rt_aprobacion_motivo($estado_raro, 'aprobar', $qa, $t) === 'no_disponible' && at_rt_aprobacion_motivo($estado_raro, 'ver', $qv, $t) === '', 'estado distinto de por_aprobar sin usar: no_disponible para ejecutar, «ver» sigue');

// ---- GET y HEAD nunca cambian nada (Review Focus 1) ----
$antes = rt_ap_foto($a);
$nonce_wp = wp_create_nonce('at_rt_aprobar_' . $a);
$todo_igual = true;
foreach (['aprobar', 'cambios', 'omitir'] as $accion) {
	foreach (['GET', 'HEAD', 'get', 'OPTIONS'] as $metodo) {
		$r = at_rt_pagina_aprobacion(rt_ap_q($enlaces[$accion]), $metodo, ['_wpnonce' => $nonce_wp, 'pedido' => '[PRUEBA] cambio']);
		$todo_igual = $todo_igual && $r['http'] === 200 && rt_ap_foto($a) === $antes;
	}
}
ok($todo_igual, 'GET, HEAD y otros métodos de aprobar, cambios y omitir responden 200 sin cambiar aprobación, proximo_at, notas ni redacción (aunque traigan nonce y pedido)');
$r = at_rt_pagina_aprobacion($qa, 'GET', []);
ok(str_contains($r['html'], '<form method="post"') && str_contains($r['html'], 'name="_wpnonce" value="' . $nonce_wp . '"') && str_contains($r['html'], 'recordatorio 1 de 4') && str_contains($r['html'], '[PRUEBA] Ana'), 'GET aprobar: confirmación con un formulario POST y el nonce at_rt_aprobar_<id>');
ok(str_contains($r['html'], 'entre 10:00 y 13:00') && str_contains($r['html'], esc_url($enlaces['ver'])), 'GET aprobar dice cuándo saldría y enlaza a «ver»');
ok(str_contains($r['html'], esc_html(at_rt_obtener($a)['email'])) && str_contains($r['html'], '+56900000000') && str_contains($r['html'], 'WhatsApp automático está en pausa'), 'GET aprobar muestra la foto del destinatario: correo y teléfono, y que el WhatsApp automático está en pausa');
ok($r['cabeceras']['X-Robots-Tag'] === 'noindex, nofollow' && str_contains($r['cabeceras']['Cache-Control'], 'no-store') && $r['cabeceras']['Referrer-Policy'] === 'no-referrer' && str_contains($r['html'], 'noindex'), 'sin índice, caché ni referente');
$r = at_rt_pagina_aprobacion(rt_ap_q($enlaces['cambios']), 'GET', []);
ok(str_contains($r['html'], 'name="pedido"') && str_contains($r['html'], '¿Qué cambiamos?') && str_contains($r['html'], 'maxlength="1000"'), 'GET cambios: cuadro «¿Qué cambiamos?»');
$r = at_rt_pagina_aprobacion(rt_ap_q($enlaces['omitir']), 'GET', []);
ok(str_contains($r['html'], '<form method="post"') && str_contains($r['html'], 'Esta semana no'), 'GET omitir: confirmación');

// ---- Ver: el correo tal como le llega, de solo lectura ----
$r = at_rt_pagina_aprobacion($qv, 'GET', []);
ok($r['http'] === 200 && str_contains($r['html'], '[PRUEBA] Ana') && str_contains($r['html'], 'Ver resumen y demo') && str_contains($r['html'], 'srcdoc=') && str_contains($r['html'], 'sandbox=""'), 'ver muestra el nombre del prospecto y el correo con «Ver resumen y demo»');
// Ruling 17: Luis ve la presentación candidata (la vigente, aún no publicada) dentro de la página firmada, no en el correo.
$src_vigente = 'src="' . esc_url(at_rt_obtener($a)['render']['view_url']) . '"';
ok(str_contains($r['html'], 'id="correo"') && str_contains($r['html'], 'id="presentacion"') && str_contains($r['html'], $src_vigente) && str_contains($r['html'], 'referrerpolicy="no-referrer"'), 'ver: además del correo, la presentación vigente en un iframe con su ancla #presentacion');
ok(str_contains($r['html'], esc_html(at_rt_asunto(1, '[PRUEBA] Ana'))) && !str_contains(str_replace($src_vigente, '', $r['html']), at_pt_host_renderer()), 'ver muestra el asunto y el host del renderer solo en el iframe de la presentación');
ok(($r['cabeceras']['X-Frame-Options'] ?? '') === 'DENY', 'ver: la página misma sigue sin poder enmarcarse');
ok(!str_contains($r['html'], '<form') && !str_contains($r['html'], 'a=aprobar') && !str_contains($r['html'], 'a=omitir') && !str_contains($r['html'], 'a=cambios'), 'ver no tiene formulario ni enlaces para decidir');
ok(str_contains($r['html'], 'href="' . esc_url(at_rt_datos_correo(at_rt_obtener($a))['url_ver']) . '"'), 'ver enlaza a la página pública del prospecto (fuera del sandbox)');
ok(at_rt_pagina_aprobacion($qv, 'POST', ['_wpnonce' => $nonce_wp])['http'] === 200 && rt_ap_foto($a) === $antes, 'POST de ver no cambia nada');

// ---- Firma alterada y nonce de WordPress ----
$r = at_rt_pagina_aprobacion($mala, 'GET', []);
ok($r['http'] === 403 && !str_contains($r['html'], '[PRUEBA] Ana') && !str_contains($r['html'], '<form'), 'GET con firma alterada: 403 sin datos del prospecto');
ok(rt_ap_post($mala)['http'] === 403 && rt_ap_foto($a) === $antes, 'POST con firma alterada: 403 y nada cambia');
ok(at_rt_pagina_aprobacion(['id' => (string) PHP_INT_MAX, 'a' => 'aprobar', 'k' => str_repeat('a', 16), 'v' => (string) (time() + 99), 'f' => str_repeat('a', 32)], 'GET', [])['http'] === 403, 'seguimiento inexistente con firma falsa: 403, no 404');
ok(at_rt_pagina_aprobacion(['a' => ['x']], 'GET', [])['http'] === 403, 'parámetros estructurados: 403 sin error');
$r = at_rt_pagina_aprobacion($qa, 'POST', ['_wpnonce' => 'invalido']);
ok($r['http'] === 403 && rt_ap_foto($a) === $antes, 'POST sin nonce de WordPress válido: 403 y nada cambia');
ok(at_rt_pagina_aprobacion($qa, 'POST', ['_wpnonce' => wp_create_nonce('at_rt_aprobar_' . $n2)])['http'] === 403 && rt_ap_foto($a) === $antes, 'nonce de otro seguimiento: 403');

// ---- Aprobar por la página y doble POST (Review Focus 2) ----
$esperado = at_rt_programar(rt_ap_ahora());
$r = rt_ap_post($qa);
$s = at_rt_obtener($a);
ok($r['http'] === 200 && $s['aprobacion']['estado'] === 'aprobado' && $s['aprobacion']['usada'] === true && $s['aprobacion']['programado'] === $esperado && $s['proximo_at'] === $esperado, 'POST aprobar: aprobado, usada y programado = proximo_at = at_rt_programar(ahora)');
ok($s['aprobacion']['nonce'] === $ap['nonce'] && $s['aprobacion']['huella'] === $ap['huella'] && $s['aprobacion']['n'] === 1 && $s['estado'] === 'activo' && $s['envios'] === [], 'aprobar conserva nonce, huella y n, y no envía nada');
ok(($s['aprobacion']['destino'] ?? null) === ['email' => $s['email'], 'telefono_wa' => '56900000000', 'whatsapp' => true] && ($s['aprobacion']['destino'] ?? null) === at_rt_aprobacion_destino($s), 'Ruling 18: aprobar guarda la foto del destinatario tal como la mostró la página (con WhatsApp aunque el interruptor esté apagado)');
ok(str_contains($r['html'], 'Sale el') && str_contains($r['html'], at_pt_fecha_larga($esperado)) && str_contains($r['html'], 'entre 10:00 y 13:00'), 'tras aprobar muestra la fecha programada');
ok(str_contains($r['html'], 'Compartir a mano') && str_contains($r['html'], esc_url(at_rt_url_wa_manual('56900000000', at_rt_panel_texto_manual($s)))), 'WhatsApp automático apagado, casilla y teléfono: enlace «💬 Compartir a mano» a wa.me');
$tras = rt_ap_foto($a);
$r = rt_ap_post($qa);
ok($r['http'] === 409 && str_contains($r['html'], 'ya se usó') && rt_ap_foto($a) === $tras, 'segundo POST: usado y nada cambia');
ok(rt_ap_post(rt_ap_q($enlaces['omitir']))['http'] === 409 && rt_ap_post(rt_ap_q($enlaces['cambios']), ['pedido' => '[PRUEBA] x'])['http'] === 409 && rt_ap_foto($a) === $tras, 'los otros botones del mismo correo también quedan usados');
ok(at_rt_resolver_aprobacion($a, 'aprobar', $qa, rt_ap_ahora())['motivo'] === 'usado', 'resolver: segundo aprobar → usado');
ok(at_rt_pagina_aprobacion($qv, 'GET', [])['http'] === 200, '«ver» sigue funcionando después de aprobar');
$w = rt_ap_seg(['wa_autorizado' => 0]);
$ew = at_rt_emitir_aprobacion($w, rt_ap_ahora());
ok(!str_contains(rt_ap_post(rt_ap_q($ew['aprobar']))['html'], 'wa.me'), 'sin casilla de WhatsApp: sin «Compartir a mano»');
update_option('at_rt_wa_automatico', '1');
$w2 = rt_ap_seg();
$ew2 = at_rt_emitir_aprobacion($w2, rt_ap_ahora());
ok(!str_contains(rt_ap_post(rt_ap_q($ew2['aprobar']))['html'], 'wa.me'), 'WhatsApp automático encendido: sin «Compartir a mano»');
update_option('at_rt_wa_automatico', '0');

// ---- Programación con fecha inyectada: viernes 14:00 → lunes ----
$v = rt_ap_seg();
$ev = at_rt_emitir_aprobacion($v, '2026-10-19 09:00:00');
$antes_v = rt_ap_foto($v);
ok(at_rt_resolver_aprobacion($v, 'aprobar', rt_ap_q($ev['aprobar']), '2026-10-26 09:00:01')['motivo'] === 'vencido' && rt_ap_foto($v) === $antes_v, 'resolver después de vencer: vencido y nada cambia');
$res = at_rt_resolver_aprobacion($v, 'aprobar', rt_ap_q($ev['aprobar']), '2026-10-23 14:00:00');
$sv = at_rt_obtener($v);
ok($res['ok'] === true && $res['motivo'] === '' && $res['programado'] === '2026-10-26' && $sv['aprobacion']['programado'] === '2026-10-26' && $sv['proximo_at'] === '2026-10-26', 'aprobar el viernes 2026-10-23 14:00 → programado lunes 2026-10-26');
$antes_v = rt_ap_foto($v);
$res = at_rt_resolver_aprobacion($v, 'aprobar', rt_ap_q($ev['aprobar']), '2026-10-23 14:00:05');
ok($res['ok'] === false && $res['motivo'] === 'usado' && rt_ap_foto($v) === $antes_v, 'resolver: segundo POST → usado, sin cambios');
ok(at_rt_resolver_aprobacion($v, 'ver', rt_ap_q($ev['ver']), '2026-10-23 14:00:05')['ok'] === false && rt_ap_foto($v) === $antes_v, 'resolver no ejecuta «ver»');
// Ruling 15: la última pasada del reloj en la ventana es la de las 12:05; aprobado a las 12:30 ya no sale ese día.
$v1230 = rt_ap_seg();
$e1230 = at_rt_emitir_aprobacion($v1230, '2026-10-19 09:00:00');
$res = at_rt_resolver_aprobacion($v1230, 'aprobar', rt_ap_q($e1230['aprobar']), '2026-10-23 12:30:00');
ok($res['ok'] && $res['programado'] === '2026-10-26' && str_contains($res['mensaje'], 'lunes 26'), 'aprobar el viernes 12:30 → programado el lunes (no hay pasada entre 12:05 y 13:00)');
$v1204 = rt_ap_seg();
$e1204 = at_rt_emitir_aprobacion($v1204, '2026-10-19 09:00:00');
ok(at_rt_resolver_aprobacion($v1204, 'aprobar', rt_ap_q($e1204['aprobar']), '2026-10-23 12:04:00')['programado'] === '2026-10-23', 'aprobar el viernes 12:04 → sale ese viernes en la pasada de las 12:05');

// ---- T7: aprobar (y cambios u omitir) exige lo mismo que el envío; «ver» sigue mostrando ----
$cambia = [
	'textos guardados sin rehacer la presentación' => static function (int $id): void { at_rt_guardar($id, ['textos' => array_merge(at_rt_obtener($id)['textos'], ['quedamos' => '[PRUEBA] Texto que nadie aprobó'])]); },
	'presentación sin terminar (misma huella)' => static function (int $id): void { at_rt_guardar($id, ['render' => array_merge(at_rt_obtener($id)['render'], ['estado' => 'pendiente'])]); },
	'redacción pendiente' => static function (int $id): void { at_rt_guardar($id, ['redaccion' => ['estado' => 'pendiente', 'generacion' => str_repeat('b', 16)]]); },
	'otro recordatorio (salió uno entretanto)' => static function (int $id): void { at_rt_guardar($id, ['envios' => [['tipo' => 'envio', 'n' => 1, 'correo' => ['estado' => 'enviado']]]]); },
];
foreach ($cambia as $caso => $cambiar) {
	$t7 = rt_ap_seg();
	$e7 = at_rt_emitir_aprobacion($t7, rt_ap_ahora());
	$cambiar($t7);
	$s7 = at_rt_obtener($t7);
	$antes_t7 = rt_ap_foto($t7);
	$motivos = array_map(static fn(string $a): string => at_rt_aprobacion_motivo($s7, $a, rt_ap_q($e7[$a]), time()), ['aprobar', 'cambios', 'omitir', 'ver']);
	ok($motivos === ['cambio', 'cambio', 'cambio', ''], 'T7, ' . $caso . ': aprobar, cambios y omitir dicen «cambio»; «ver» sigue mostrando');
	$res = at_rt_resolver_aprobacion($t7, 'aprobar', rt_ap_q($e7['aprobar']), rt_ap_ahora());
	$pag = at_rt_pagina_aprobacion(rt_ap_q($e7['aprobar']), 'GET', []);
	ok(!$res['ok'] && $res['motivo'] === 'cambio' && !str_contains($res['mensaje'], 'Sale el') && $pag['http'] === 409 && !str_contains($pag['html'], '<form') && rt_ap_foto($t7) === $antes_t7, 'T7, ' . $caso . ': nunca dice «Aprobado. Sale el…» y nada cambia');
}

// ---- Emitir no pisa un aprobado vigente ----
$vig = rt_ap_seg();
$evig = at_rt_emitir_aprobacion($vig, rt_ap_ahora());
at_rt_resolver_aprobacion($vig, 'aprobar', rt_ap_q($evig['aprobar']), rt_ap_ahora());
$ap_vig = at_rt_obtener($vig)['aprobacion'];
ok($ap_vig['estado'] === 'aprobado' && at_rt_emitir_aprobacion($vig, rt_ap_ahora()) === null && at_rt_obtener($vig)['aprobacion'] === $ap_vig, 'emitir sobre un aprobado vigente (misma huella, render listo): no emite y la aprobación queda intacta');
at_rt_guardar($vig, ['aprobacion' => array_merge($ap_vig, ['huella' => hash('sha256', 'presentación anterior')])]);
ok(is_array(at_rt_emitir_aprobacion($vig, rt_ap_ahora())) && at_rt_obtener($vig)['aprobacion']['estado'] === 'por_aprobar', 'un aprobado con huella vieja sí se vuelve a emitir');

// ---- Render rehecho y emisión nueva → cambio ----
$h = rt_ap_seg();
$eh = at_rt_emitir_aprobacion($h, rt_ap_ahora());
// Contenido nuevo y presentación rehecha con su huella (como deja un render terminado).
at_rt_guardar($h, ['textos' => array_merge(at_rt_obtener($h)['textos'], ['quedamos' => '[PRUEBA] Quedamos en retomar el pedido, versión nueva.'])]);
rt_ap_al_dia($h);
$antes_h = rt_ap_foto($h);
$r = at_rt_pagina_aprobacion(rt_ap_q($eh['aprobar']), 'GET', []);
ok($r['http'] === 409 && str_contains($r['html'], 'cambió') && !str_contains($r['html'], '<form'), 'render rehecho: GET dice que el recordatorio cambió, sin formulario');
ok(rt_ap_post(rt_ap_q($eh['aprobar']))['http'] === 409 && rt_ap_foto($h) === $antes_h, 'render rehecho: POST aprobar no aprueba');
$e1 = at_rt_emitir_aprobacion($h, rt_ap_ahora());
$e2 = at_rt_emitir_aprobacion($h, rt_ap_ahora());
$s2 = at_rt_obtener($h);
ok($s2['aprobacion']['nonce'] !== rt_ap_q($e1['aprobar'])['k'] && rt_ap_q($e2['aprobar'])['k'] === $s2['aprobacion']['nonce'], 'emitir de nuevo rota el nonce');
ok(at_rt_aprobacion_motivo($s2, 'aprobar', rt_ap_q($e1['aprobar']), time()) === 'cambio' && at_rt_aprobacion_motivo($s2, 'ver', rt_ap_q($e1['ver']), time()) === 'cambio' && at_rt_aprobacion_motivo($s2, 'aprobar', rt_ap_q($e2['aprobar']), time()) === '', 'el enlace viejo da cambio y el nuevo vale');
$antes_h = rt_ap_foto($h);
ok(rt_ap_post(rt_ap_q($e1['omitir']))['http'] === 409 && rt_ap_foto($h) === $antes_h, 'POST con el enlace viejo no cambia nada');

// ---- Pedir cambios ----
$c = rt_ap_seg();
$ec = at_rt_emitir_aprobacion($c, rt_ap_ahora());
$qc = rt_ap_q($ec['cambios']);
$antes_c = rt_ap_foto($c);
foreach (['', "   \n ", str_repeat('x', 1001), '<b></b>'] as $malo) {
	$res = at_rt_resolver_aprobacion($c, 'cambios', $qc, rt_ap_ahora(), $malo);
	ok($res['ok'] === false && $res['motivo'] === 'pedido' && rt_ap_foto($c) === $antes_c, 'cambios con pedido ' . json_encode(mb_substr($malo, 0, 12)) . ': error y nada cambia');
}
$r = rt_ap_post($qc, ['pedido' => '']);
ok($r['http'] === 422 && str_contains($r['html'], 'name="pedido"') && rt_ap_foto($c) === $antes_c, 'página: cambios sin pedido → 422, vuelve a mostrar el cuadro y nada cambia');
ok(at_rt_resolver_aprobacion($c, 'cambios', array_merge($qc, ['f' => str_repeat('0', 32)]), rt_ap_ahora(), '')['motivo'] === 'firma', 'con firma mala responde firma antes de revisar el pedido');
if (!defined('AT_N8N_RETOMAR_REDACTAR')) {
	$sf = rt_ap_seg();
	$esf = at_rt_emitir_aprobacion($sf, rt_ap_ahora());
	$res = at_rt_resolver_aprobacion($sf, 'cambios', rt_ap_q($esf['cambios']), rt_ap_ahora(), '[PRUEBA] Más cercano');
	$ssf = at_rt_obtener($sf);
	ok($res['ok'] === true && $ssf['aprobacion']['estado'] === 'cambios' && $ssf['redaccion']['estado'] === 'revision' && str_contains($res['mensaje'], 'panel'), 'sin flujo de redacción: el pedido queda anotado y la redacción en revisión (no vuelve igual la semana siguiente)');
	define('AT_N8N_RETOMAR_REDACTAR', 'https://example.com/prueba-redactar');
}
$pedido = "[PRUEBA] Que suene más cercano\ny que mencione la agenda";
$res = at_rt_resolver_aprobacion($c, 'cambios', $qc, rt_ap_ahora(), $pedido);
$sc = at_rt_obtener($c);
ok($res['ok'] === true && $sc['aprobacion']['estado'] === 'cambios' && $sc['aprobacion']['usada'] === true, 'cambios con pedido: estado cambios y usada');
ok(str_contains($sc['notas'], $pedido), 'el pedido queda en las notas');
ok(in_array($sc['redaccion']['estado'], ['pendiente', 'error'], true) && ($sc['redaccion']['pedido'] ?? '') === $pedido && $sc['redaccion']['generacion'] !== str_repeat('a', 16), 'se pidió la redacción con el pedido (HTTP bloqueado en pruebas: pendiente o error)');
ok($sc['proximo_at'] === $antes_c[1] && $sc['estado'] === 'activo', 'cambios no toca proximo_at ni el estado del seguimiento');
ok(at_rt_resolver_aprobacion($c, 'cambios', $qc, rt_ap_ahora(), '[PRUEBA] otra vez')['motivo'] === 'usado', 'el enlace de cambios es de un solo uso');
$largo = rt_ap_seg(['notas' => str_repeat('n', 3990)]);
$el = at_rt_emitir_aprobacion($largo, rt_ap_ahora());
ok(at_rt_obtener($largo)['notas'] === str_repeat('n', 3990), 'fixture: notas de 3990 caracteres');
at_rt_resolver_aprobacion($largo, 'cambios', rt_ap_q($el['cambios']), rt_ap_ahora(), '[PRUEBA] Pedido al final');
$nl = at_rt_obtener($largo)['notas'];
ok(mb_strlen($nl) <= 4000 && str_ends_with($nl, '[PRUEBA] Pedido al final'), 'las notas se recortan a sus últimos 4000 caracteres y conservan el pedido');
$simular = true;
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$simular) {
	return ($simular && $url === AT_N8N_RETOMAR_REDACTAR) ? ['response' => ['code' => 200], 'body' => '{}', 'headers' => []] : $pre;
}, PHP_INT_MAX, 3);
$cp = rt_ap_seg();
$ecp = at_rt_emitir_aprobacion($cp, rt_ap_ahora());
$r = rt_ap_post(rt_ap_q($ecp['cambios']), ['pedido' => '[PRUEBA] Más corto']);
$scp = at_rt_obtener($cp);
ok($r['http'] === 200 && $scp['aprobacion']['estado'] === 'cambios' && $scp['redaccion']['estado'] === 'pendiente' && str_contains($scp['notas'], '[PRUEBA] Más corto') && str_contains($r['html'], 'cambios'), 'página: POST cambios con pedido pide la redacción (n8n simulado: pendiente)');
$simular = false;

// ---- Esta semana no ----
$o = rt_ap_seg(['crm_cliente_id' => $crm_falso]);
$eo = at_rt_emitir_aprobacion($o, rt_ap_ahora());
$antes_o = at_rt_obtener($o);
$r = rt_ap_post(rt_ap_q($eo['omitir']));
$so = at_rt_obtener($o);
$ev_o = $so['envios'][0] ?? [];
ok($r['http'] === 200 && $so['aprobacion']['estado'] === 'omitido' && $so['aprobacion']['usada'] === true && $so['proximo_at'] === $antes_o['proximo_at'] && count($so['envios']) === 1 && $so['redaccion'] === $antes_o['redaccion'], 'omitir: omitido y usada, sin tocar proximo_at ni redacción, con un solo evento en envíos');
ok(array_keys($ev_o) === ['tipo', 'fecha', 'at', 'n'] && $ev_o['tipo'] === 'omitido' && $ev_o['n'] === 1 && str_starts_with($ev_o['at'], $ev_o['fecha']) && at_rt_envios_realizados($so['envios']) === 0, 'omitir deja rastro en envíos {tipo:omitido, fecha, at, n} sin consumir un recordatorio');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id=%d AND tipo_evento='retomar_omitido' AND titulo=%s", $crm_falso, '🔁 Esta semana no')) === 1, 'omitir deja «🔁 Esta semana no» en el historial del CRM');
$o2 = rt_ap_seg(); // sin ficha CRM: el rastro queda igual en envíos
$eo2 = at_rt_emitir_aprobacion($o2, '2026-10-19 09:00:00');
ok(at_rt_resolver_aprobacion($o2, 'omitir', rt_ap_q($eo2['omitir']), '2026-10-19 09:30:00')['ok'] && at_rt_obtener($o2)['envios'] === [['tipo' => 'omitido', 'fecha' => '2026-10-19', 'at' => '2026-10-19 09:30:00', 'n' => 1]], 'omitir sin ficha CRM: el evento queda en envíos con la hora de la decisión');
ok(rt_ap_post(rt_ap_q($eo['aprobar']))['http'] === 409 && at_rt_obtener($o)['aprobacion']['estado'] === 'omitido', 'después de omitir, aprobar con el mismo correo no aprueba');

// ---- Paradas justo antes de aprobar ----
$b = rt_ap_seg();
$eb = at_rt_emitir_aprobacion($b, rt_ap_ahora());
$wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => at_rt_obtener($b)['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]);
$antes_b = rt_ap_foto($b);
$res = at_rt_resolver_aprobacion($b, 'aprobar', rt_ap_q($eb['aprobar']), rt_ap_ahora());
ok($res['ok'] === false && $res['motivo'] === 'baja' && $res['mensaje'] !== '' && rt_ap_foto($b) === $antes_b, 'prospecto en bajas: no se aprueba y nada cambia');
$r = rt_ap_post(rt_ap_q($eb['aprobar']));
ok($r['http'] === 409 && !str_contains($r['html'], 'Sale el') && rt_ap_foto($b) === $antes_b, 'página: aprobar a quien se dio de baja responde 409 con mensaje claro');
ok(!str_contains(at_rt_pagina_aprobacion(rt_ap_q($eb['aprobar']), 'GET', [])['html'], '<form'), 'GET de aprobar a quien se dio de baja: sin formulario');
$res = at_rt_resolver_aprobacion($b, 'omitir', rt_ap_q($eb['omitir']), rt_ap_ahora());
ok($res['ok'] === true && at_rt_obtener($b)['aprobacion']['estado'] === 'omitido', 'omitir sigue permitido con una parada (no envía nada)');
$ag = rt_ap_seg(['max_envios' => 1]);
at_rt_guardar($ag, ['envios' => [['tipo' => 'envio', 'n' => 1, 'correo' => ['estado' => 'enviado']]]]);
$eag = at_rt_emitir_aprobacion($ag, rt_ap_ahora());
ok(at_rt_resolver_aprobacion($ag, 'aprobar', rt_ap_q($eag['aprobar']), rt_ap_ahora())['motivo'] === 'agotado' && at_rt_obtener($ag)['aprobacion']['estado'] === 'por_aprobar', 'tope alcanzado: agotado y no se aprueba');
$pa = rt_ap_seg();
$epa = at_rt_emitir_aprobacion($pa, rt_ap_ahora());
at_rt_guardar($pa, ['estado' => 'pausado', 'proximo_at' => null]);
$res = at_rt_resolver_aprobacion($pa, 'aprobar', rt_ap_q($epa['aprobar']), rt_ap_ahora());
ok($res['ok'] === false && $res['motivo'] === 'inactivo' && at_rt_obtener($pa)['proximo_at'] === null && at_rt_obtener($pa)['aprobacion']['estado'] === 'por_aprobar', 'seguimiento en pausa: inactivo, no se aprueba ni se programa');

// ---- Ver con la presentación todavía rehaciéndose: no hay iframe, se dice que se está preparando ----
$pv = rt_ap_seg();
$epv = at_rt_emitir_aprobacion($pv, rt_ap_ahora());
at_rt_guardar($pv, ['render' => ['estado' => 'pendiente', 'huella' => at_rt_obtener($pv)['render']['huella'], 'generacion' => str_repeat('d', 16)]]);
$html_pv = at_rt_pagina_aprobacion(rt_ap_q($epv['ver']), 'GET', [])['html'];
ok(str_contains($html_pv, 'id="presentacion"') && str_contains($html_pv, 'se está preparando') && substr_count($html_pv, '<iframe') === 1 && !str_contains($html_pv, at_pt_host_renderer()), 'ver con la presentación sin terminar: solo el correo y el aviso de que se está preparando');

// ---- Escapado ----
$x = rt_ap_seg();
$wpdb->update($wpdb->prefix . 'at_retomar', ['negocio' => '<script>alert(1)</script> & "Co"', 'nombre' => '[PRUEBA] <b onmouseover="x">Ana</b>'], ['id' => $x]);
rt_ap_al_dia($x); // el nombre y el negocio son parte de la presentación: la huella sigue al contenido nuevo
$ex = at_rt_emitir_aprobacion($x, rt_ap_ahora());
$sin_script = true;
foreach (['aprobar', 'cambios', 'omitir', 'ver'] as $accion) {
	$html = at_rt_pagina_aprobacion(rt_ap_q($ex[$accion]), 'GET', [])['html'];
	$sin_script = $sin_script && !str_contains($html, '<script') && !str_contains($html, '<b onmouseover');
}
ok($sin_script, 'ninguna página trae <script> ni etiquetas del nombre o negocio');
$html = at_rt_pagina_aprobacion(rt_ap_q($ex['aprobar']), 'GET', [])['html'];
ok(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Co&quot;'), 'confirmación: el negocio sale escapado');
$html = at_rt_pagina_aprobacion(rt_ap_q($ex['ver']), 'GET', [])['html'];
ok(str_contains($html, '&amp;lt;script&amp;gt;') && !str_contains($html, 'srcdoc="<'), 'ver: el correo va en srcdoc con doble escapado (no se decodifica a <script>)');
$r = rt_ap_post(rt_ap_q($ex['aprobar']));
ok($r['http'] === 200 && !str_contains($r['html'], '<script') && !str_contains($r['html'], '<b onmouseover'), 'tras aprobar, la página también escapa');

fin();
