<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_pagina', 'at_rt_agendar', 'at_rt_token_agenda_hoy');
at_rt_migrar_esquema();
global $wpdb;
$marca = 'rt-publica-' . strtolower(wp_generate_password(12, false, false));
$ids = []; $reuniones = []; $ips = []; $emails = [];
$horario = get_option('automatiza_chat_schedule', null);
register_shutdown_function(static function () use (&$ids, &$reuniones, &$ips, &$emails, $wpdb, $horario) {
    foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
    foreach ($reuniones as $id) { $wpdb->delete($wpdb->prefix . 'automatiza_followup_meetings', ['id' => $id]); }
    foreach ($emails as $email) { $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['email' => $email]); }
    foreach ($ips as $ip) { delete_transient('at_rt_agenda_' . md5($ip)); }
    $horario === null ? delete_option('automatiza_chat_schedule') : update_option('automatiza_chat_schedule', $horario);
});
function rt_publica_fixture(): array {
    global $marca, $ids, $emails;
    $email = $marca . '-' . count($ids) . '@example.com'; $emails[] = $email;
    $id = at_rt_crear(['nombre' => '[PRUEBA]', 'negocio' => '[PRUEBA]', 'email' => $email, 'estado' => 'activo']);
    $ids[] = $id;
    return at_rt_obtener($id);
}
function rt_agenda_pedir(array $s, string $fecha, string $hora, array $cambia = [], ?string $ip = null): array {
    global $ips, $marca, $reuniones;
    $ip = $ip ?? 'prueba-' . $marca . '-' . count($ips); $ips[] = $ip;
    $r = at_rt_agendar($cambia + ['t' => $s['token'], 'token' => at_rt_token_agenda_hoy($s['token']), 'fecha' => $fecha, 'hora' => $hora, 'sitio_web' => ''], $ip);
    if ($r['reunion_id']) { $reuniones[] = $r['reunion_id']; }
    return $r;
}
$s = rt_publica_fixture();
$g = ['t' => $s['token'], 'baja' => 1];
$r = at_rt_pagina($g, 'GET', []);
ok($r['http'] === 200 && substr_count($r['html'], '<button') === 1 && at_rt_obtener($s['id'])['estado'] === 'activo', 'GET baja confirma con un botón y nunca cambia estado');
ok($r['cabeceras']['X-Robots-Tag'] === 'noindex, nofollow' && str_contains($r['html'], 'noindex') && str_contains($r['cabeceras']['Cache-Control'], 'no-store') && $r['cabeceras']['Referrer-Policy'] === 'no-referrer', 'enlace secreto sin índice, caché ni referente');
ok(at_rt_pagina($g, 'POST', ['_wpnonce' => 'invalido'])['http'] === 403 && at_rt_obtener($s['id'])['estado'] === 'activo', 'nonce inválido no da baja');
$nonce = wp_create_nonce('at_rt_baja_' . $s['id']);
$r = at_rt_pagina($g, 'POST', ['_wpnonce' => $nonce]);
ok($r['http'] === 200 && at_rt_obtener($s['id'])['estado'] === 'baja', 'POST nonce confirma baja');
ok($wpdb->get_var($wpdb->prepare("SELECT origen FROM {$wpdb->prefix}at_retomar_bajas WHERE email = %s", $s['email'])) === 'pagina', 'origen página');
$r = at_rt_pagina($g, 'POST', ['_wpnonce' => $nonce]);
ok($r['http'] === 200 && str_contains($r['html'], 'ya no te escribiremos'), 'segundo POST idempotente');
$otro = rt_publica_fixture();
$og = ['t' => $otro['token'], 'baja' => 1];
ok(at_rt_pagina($og, 'POST', ['_wpnonce' => $nonce])['http'] === 403, 'nonce de otro seguimiento rechazado');
ok(at_rt_pagina($og, 'POST', ['List-Unsubscribe' => 'One-Click'])['http'] === 200 && at_rt_obtener($otro['id'])['estado'] === 'baja', 'POST one-click da baja');
ok($wpdb->get_var($wpdb->prepare("SELECT origen FROM {$wpdb->prefix}at_retomar_bajas WHERE email = %s", $otro['email'])) === 'one_click', 'origen one_click');
$r = at_rt_pagina(['t' => str_repeat('f', 32)], 'GET', []);
ok($r['http'] === 404 && str_contains($r['html'], 'Seguimiento no disponible') && str_contains($r['html'], 'noindex'), 'token ajeno 404 propio y noindex');
ok(at_rt_pagina(['t' => ['mal']], 'GET', [])['http'] === 404, 'token estructurado no provoca error');
$s = rt_publica_fixture();
$vista = 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/index.html';
at_rt_guardar($s['id'], ['render' => ['estado' => 'listo', 'view_url' => $vista]]);
$r = at_rt_pagina(['t' => $s['token']], 'GET', []);
ok(str_contains($r['html'], '<iframe') && str_contains($r['html'], esc_url($vista)) && str_contains($r['html'], 'agendar=1'), 'ver presenta iframe autorizado y botón agenda');
at_rt_guardar($s['id'], ['render' => ['view_url' => 'https://example.com/no-autorizado']]);
ok(!str_contains(at_rt_pagina(['t' => $s['token']], 'GET', [])['html'], '<iframe'), 'iframe rechaza URL ajena');
$r = at_rt_pagina(['t' => $s['token'], 'agendar' => 1], 'GET', []);
ok(str_contains($r['html'], 'name="fecha"') && str_contains($r['html'], 'name="hora"') && str_contains($r['html'], 'name="token"') && at_rt_obtener($s['id'])['estado'] === 'activo', 'GET agenda ofrece formulario sin mutación');
// La página es como ver-plan.php: iframe a pantalla completa, barra y diálogo con el selector de horarios de la portada.
$rt_cfg = static function (string $html): ?array {
    return preg_match('/window\.AT_RT_VER = (\{.*?\});<\/script>/', $html, $m) ? json_decode($m[1], true) : null;
};
at_rt_guardar($s['id'], ['render' => ['estado' => 'listo', 'view_url' => $vista]]);
$r = at_rt_pagina(['t' => $s['token']], 'GET', []);
$cfg = $rt_cfg($r['html']);
ok($r['http'] === 200 && str_contains($r['html'], '<body class="at-pt-ver at-rt-ver">') && str_contains($r['html'], '<nav class="at-pt-barra"') && str_contains($r['html'], 'plan-ver.css'), 'ver: iframe a pantalla completa con la barra y el CSS del plan');
ok(str_contains($r['html'], 'referrerpolicy="no-referrer"') && str_contains($r['html'], '<meta name="referrer" content="no-referrer">') && str_contains($r['html'], 'noindex'), 'ver: sin referente ni índice');
ok(str_contains($r['html'], 'data-at-rt-abrir>📅 Agendar 20 minutos</a>') && str_contains($r['html'], esc_url(home_url('/retomar.php?t=' . $s['token']) . '&agendar=1') . '" data-at-rt-abrir'), 'ver: «Agendar 20 minutos» abre el diálogo y sin JavaScript lleva a agendar=1');
ok(str_contains($r['html'], '<a class="at-rt-baja" href="' . esc_url(home_url('/retomar.php?t=' . $s['token']) . '&baja=1') . '">No me interesa</a>'), 'ver: «No me interesa» discreto a la baja de siempre');
ok(str_contains($r['html'], '<dialog id="at-rt-agenda"') && str_contains($r['html'], 'id="at-fecha"') && str_contains($r['html'], 'id="at-slots"') && str_contains($r['html'], 'id="at-scheduled-time"') && str_contains($r['html'], 'name="sitio_web"'), 'ver: diálogo con los campos que usa at-agenda.js y el campo trampa');
ok(str_contains($r['html'], 'at-agenda.js') && str_contains($r['html'], 'retomar-ver.js') && str_contains($r['html'], 'appointments-config'), 'ver: carga el selector de horarios de la portada y retomar-ver.js');
ok(is_array($cfg) && $cfg['t'] === $s['token'] && $cfg['token'] === at_rt_token_agenda_hoy($s['token']) && $cfg['abrir'] === false && str_ends_with($cfg['url'], 'automatiza-tech/v1/retomar-agenda'), 'ver: configuración con t, token del día y la ruta REST de Retomar, diálogo cerrado');
ok(!str_contains($r['html'], '<noscript>') && !str_contains($r['html'], 'name="fecha"'), 'ver: sin agendar=1 no va el formulario sin JavaScript');
ok($r['cabeceras'] === ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer', 'Content-Type' => 'text/html; charset=UTF-8'], 'ver: mismas cabeceras de siempre');
$r = at_rt_pagina(['t' => $s['token'], 'agendar' => 1], 'GET', []);
$cfg = $rt_cfg($r['html']);
ok(is_array($cfg) && $cfg['abrir'] === true && str_contains($r['html'], '<iframe') && str_contains($r['html'], '<noscript><section class="at-rt-sinjs"') && str_contains($r['html'], 'action="' . esc_url(home_url('/retomar.php?t=' . $s['token']) . '&agendar=1') . '"'), 'agendar=1: misma página con el diálogo abierto y el formulario de respaldo sin JavaScript');
ok($r['cabeceras']['Cache-Control'] === 'no-store, no-cache, must-revalidate, max-age=0' && $r['cabeceras']['Referrer-Policy'] === 'no-referrer' && $r['cabeceras']['X-Robots-Tag'] === 'noindex, nofollow', 'agendar=1: mismas cabeceras');
$r = at_rt_pagina(['t' => $s['token'], 'agendar' => 1], 'POST', ['token' => 'malo', 'fecha' => '2030-01-01', 'hora' => '10:00', 'sitio_web' => '']);
ok($r['http'] === 403 && str_contains($r['html'], 'role="status"') && str_contains($r['html'], 'name="fecha"') && at_rt_obtener($s['id'])['estado'] === 'activo', 'POST del formulario sin JavaScript sigue agendando por at_rt_agendar (token malo: 403 y vuelve el formulario)');
$r = at_rt_pagina(['t' => $s['token'], 'agendar' => 1], 'POST', ['t' => str_repeat('f', 32), 'token' => at_rt_token_agenda_hoy($s['token']), 'fecha' => '', 'hora' => '', 'sitio_web' => 'spam']);
ok($r['http'] === 400 && at_rt_obtener($s['id'])['estado'] === 'activo', 'POST sin JavaScript usa el token de la URL, no uno del cuerpo');
$ips[] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$dias = [];
foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d) { $dias[$d] = ['enabled' => true, 'start' => '09:00', 'end' => '22:00']; }
update_option('automatiza_chat_schedule', $dias + ['holidays' => '']);
$fecha = (new DateTimeImmutable(current_time('Y-m-d')))->modify('+15 days')->format('Y-m-d');
// Los slots candidatos se consultan sin modificar datos ajenos.
$hora = '';
foreach (['10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'] as $h) {
    if (!empty(automatiza_tech_check_slot_availability($fecha, $h . ':00')['available'])) { $hora = $h; break; }
}
ok($hora !== '', 'fixture encuentra slot libre real');
ok(rt_agenda_pedir($s, $fecha, $hora, ['token' => 'malo'])['estado_http'] === 403, 'token agenda inválido 403');
ok(rt_agenda_pedir($s, $fecha, $hora, ['token' => at_pt_token_agenda('retomar-' . $s['id'], intdiv(time(), 86400) - 2, wp_salt('nonce'))])['estado_http'] === 403, 'token agenda expirado 403');
ok(rt_agenda_pedir($s, $fecha, $hora, ['sitio_web' => 'spam'])['estado_http'] === 400, 'honeypot no guarda');
ok(at_rt_obtener($s['id'])['estado'] === 'activo', 'rechazos no cambian estado');
$limite_ip = 'limite-' . $marca;
for ($i = 0; $i < 6; $i++) { $rate = rt_agenda_pedir($s, $fecha, '08:00', [], $limite_ip); }
ok($rate['estado_http'] === 429, 'sexto intento por IP devuelve 429');
ok(rt_agenda_pedir($s, $fecha, '21:00')['clave'] === 'fuera_de_horario', 'límite de atención hasta 21:00 aunque configuración sea mayor');
$correos = [];
add_filter('pre_wp_mail', static function ($v, $d) use (&$correos) { $correos[] = $d; return true; }, 20, 2);
$r = rt_agenda_pedir($s, $fecha, $hora);
$f = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_followup_meetings WHERE id = %d", $r['reunion_id']));
ok($r['ok'] && $f && $f->client_email === $s['email'] && $f->status === 'scheduled' && at_rt_obtener($s['id'])['estado'] === 'agendo', 'agenda crea reunión propia y detiene recordatorios');
ok($f && str_starts_with($f->notes, 'Retomar #' . $s['id'] . ' ·') && !str_contains($f->notes, $s['token']) && !str_contains($f->notes, 'http'), 'notas guardan id numérico sin token ni URLs');
ok(count($correos) >= 1, 'patrón de notificación de correo invocado con transporte bloqueado');
ok(rt_agenda_pedir($s, $fecha, '19:00')['clave'] === 'ya_agendada', 'una única reunión futura por seguimiento');
$html = at_rt_pagina(['t' => $s['token'], 'agendar' => 1], 'GET', [])['html'];
ok(str_contains($html, 'Ya tienes tu llamada de seguimiento el ') && !str_contains($html, '<dialog') && !str_contains($html, 'data-at-rt-abrir') && str_contains($html, '<iframe'), 'con la llamada ya agendada la barra lo dice en vez del botón, sin diálogo');
$html = at_rt_pagina(['t' => $s['token']], 'GET', [])['html'];
ok(str_contains($html, substr($hora, 0, 5) . '.</p>') && str_contains($html, 'No me interesa'), 'y muestra la hora agendada');
$q = rt_publica_fixture();
ok(rt_agenda_pedir($q, $fecha, $hora)['clave'] === 'hora_ocupada', 'slot ocupado no puede duplicarse');
$req = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar-agenda');
$req->set_header('Content-Type', 'application/json'); $req->set_body(wp_json_encode(['t' => 'inexistente']));
$rest = rest_do_request($req);
ok($rest->get_status() === 404 && $rest->get_data()['ok'] === false, 'ruta REST pública registrada y valida token');
ok((string) $wpdb->get_var("SELECT IS_FREE_LOCK('" . AT_PT_AGENDA_LOCK . "')") === '1', 'candado compartido queda liberado');
// El límite general cuenta reuniones activas aunque no pertenezcan a Retomar.
$lim = rt_publica_fixture();
for ($i = 0; $i < 2; $i++) {
    $wpdb->insert($wpdb->prefix . 'automatiza_followup_meetings', ['client_name' => '[PRUEBA]', 'client_email' => $lim['email'], 'phone' => '+56900000000', 'meeting_date' => $fecha, 'meeting_time' => ($i ? '19:00:00' : '18:00:00'), 'status' => 'scheduled', 'notes' => '[PRUEBA] ' . $marca]);
    $reuniones[] = (int) $wpdb->insert_id;
}
$r = rt_agenda_pedir($lim, $fecha, '17:00');
ok($r['estado_http'] === 409 && $r['clave'] === 'limite_reuniones' && at_rt_obtener($lim['id'])['estado'] === 'activo', 'máximo dos reuniones activas por correo también en Retomar');
$otra = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
try {
    $tomado = $otra->query("SELECT GET_LOCK('" . AT_PT_AGENDA_LOCK . "', 0)")->fetch_row();
    ok($tomado[0] === '1', 'otra conexión toma el candado compartido de agenda');
    $antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_followup_meetings WHERE client_email = %s", $q['email']));
    $r = rt_agenda_pedir($q, $fecha, '17:00');
    ok($r['estado_http'] === 503 && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_followup_meetings WHERE client_email = %s", $q['email'])) === $antes, 'candado ocupado devuelve 503 y no inserta');
} finally {
    $otra->query("SELECT RELEASE_LOCK('" . AT_PT_AGENDA_LOCK . "')");
    $otra->close();
}
ok((string) $wpdb->get_var("SELECT IS_FREE_LOCK('" . AT_PT_AGENDA_LOCK . "')") === '1', 'candado liberado tras prueba concurrente');

// ---- Ruling 17 (C1): la página fija del prospecto muestra solo lo que ya se le envió ----
// Recordatorio 1 con la presentación A → «Pedir cambios» (B pendiente, B fallida, B lista) → la página sigue con A hasta
// que sale el recordatorio 2 con B.
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', bin2hex(random_bytes(24))); }
if (!defined('AT_N8N_RETOMAR_RENDER')) { define('AT_N8N_RETOMAR_RENDER', 'https://example.invalid/webhook/retomar-render'); }
$pedido_render = null;
// El bloqueo de HTTP del bootstrap sigue: solo el flujo de presentación responde (simulado).
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$pedido_render) {
    if ($url !== AT_N8N_RETOMAR_RENDER) { return $pre; }
    $pedido_render = json_decode($args['body'], true);
    return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []];
}, PHP_INT_MAX, 3);
$rt_pub_vista = static function (int $id, array $body): int {
    $r = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar/' . $id . '/vista');
    $r->set_header('Content-Type', 'application/json'); $r->set_header('X-AT-Secret', AT_REST_SECRET);
    $r->set_body(wp_json_encode($body));
    return rest_do_request($r)->get_status();
};
$rt_pub_render = static function (int $id) use (&$pedido_render, $rt_pub_vista): string {
    $pedido_render = null;
    if (at_rt_pedir_render($id) !== '' || !is_array($pedido_render)) { return ''; }
    $vista = 'https://' . at_pt_host_renderer() . '/p/' . $pedido_render['render']['unique_id'] . '/index.html';
    return $rt_pub_vista($id, ['ok' => true, 'generacion' => $pedido_render['generacion'], 'view_url' => $vista]) === 200 ? $vista : '';
};
$rt_pub_aprobar = static function (int $id, string $emitir, string $aprobar): bool {
    $enlaces = at_rt_emitir_aprobacion($id, $emitir);
    parse_str((string) parse_url((string) ($enlaces['aprobar'] ?? ''), PHP_URL_QUERY), $q);
    return is_array($enlaces) && at_rt_resolver_aprobacion($id, 'aprobar', $q, $aprobar)['ok'];
};
$rt_pub_html = static fn(array $s): string => at_rt_pagina(['t' => $s['token']], 'GET', [])['html'];
$c1 = rt_publica_fixture();
at_rt_guardar($c1['id'], ['telefono' => '', 'redaccion' => ['estado' => 'lista'], 'proximo_at' => '2026-10-19', 'activado_at' => '2026-10-01 00:00:00', 'textos' => ['quedamos' => '[PRUEBA] Versión A', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Partimos']]);
$url_a = $rt_pub_render($c1['id']);
ok($url_a !== '' && str_contains($rt_pub_html($c1), esc_url($url_a)), 'C1: antes del primer envío la página muestra la presentación vigente (A)');
ok($rt_pub_aprobar($c1['id'], '2026-10-19 09:00:00', '2026-10-19 09:30:00') && at_rt_entregar($c1['id'], false, '2026-10-19 10:30:00')['ok'], 'C1: Luis aprueba A y sale el recordatorio 1');
ok((at_rt_obtener($c1['id'])['render']['publicada']['view_url'] ?? '') === $url_a, 'C1: A quedó publicada');
// Pedir cambios: textos nuevos y presentación B.
at_rt_guardar($c1['id'], ['textos' => ['quedamos' => '[PRUEBA] Versión B', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Partimos']]);
$pedido_render = null;
ok(at_rt_pedir_render($c1['id']) === '' && is_array($pedido_render), 'C1: se pide la presentación B');
$html = $rt_pub_html($c1);
ok(str_contains($html, esc_url($url_a)) && !str_contains($html, 'Estamos preparando'), 'C1: mientras se rehace B, el enlace que ya tiene el prospecto sigue mostrando A');
ok($rt_pub_vista($c1['id'], ['ok' => false, 'generacion' => $pedido_render['generacion'], 'nota' => '[PRUEBA] Falló']) === 200 && at_rt_obtener($c1['id'])['render']['estado'] === 'error' && str_contains($rt_pub_html($c1), esc_url($url_a)), 'C1: con un render fallido después del envío, la página sigue mostrando A');
$url_b = $rt_pub_render($c1['id']);
$html = $rt_pub_html($c1);
ok($url_b !== '' && $url_b !== $url_a && str_contains($html, esc_url($url_a)) && !str_contains($html, esc_url($url_b)), 'C1: B lista pero sin aprobar ni enviar: la página sigue con A');
ok($rt_pub_aprobar($c1['id'], '2026-10-26 09:00:00', '2026-10-26 09:30:00') && at_rt_entregar($c1['id'], false, '2026-10-26 10:30:00')['ok'], 'C1: Luis aprueba B y sale el recordatorio 2');
$html = $rt_pub_html($c1);
ok(str_contains($html, esc_url($url_b)) && !str_contains($html, esc_url($url_a)), 'C1: desde el recordatorio 2, la página muestra B');
// Una versión publicada que no es de este seguimiento no se muestra (nunca la vigente sin aprobar en su lugar).
at_rt_guardar($c1['id'], ['render' => array_merge(at_rt_obtener($c1['id'])['render'], ['publicada' => ['view_url' => 'https://example.com/ajena/index.html']])]);
$html = $rt_pub_html($c1);
ok(!str_contains($html, '<iframe') && str_contains($html, 'Estamos preparando'), 'C1: una versión publicada ajena no se muestra ni se cambia por la vigente');
fin();
