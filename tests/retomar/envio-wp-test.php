<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_enviar', 'at_rt_tick', 'at_rt_whatsapp_resultado');
at_rt_migrar_esquema();
global $wpdb;
$ids = []; $emails = []; $marca = 'rt-envio-' . strtolower(wp_generate_password(10, false, false));
$mails = []; $falla_mail = false; $alternativas = [];
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
add_filter('pre_wp_mail', static function ($v, $d) use (&$mails, &$falla_mail, &$alternativas) { $mails[] = $d; $mail = new PHPMailer\PHPMailer\PHPMailer(); do_action('phpmailer_init', $mail); $alternativas[] = $mail->AltBody; return !$falla_mail; }, 100, 2);
// Aislar el SELECT del reloj para no mutar seguimientos ajenos del sitio local.
add_filter('query', static function ($sql) use (&$ids, $wpdb) {
    if (str_starts_with($sql, "SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado='activo' AND proximo_at<=")) {
        $sql = str_replace(' ORDER BY id', ' AND id IN (' . implode(',', $ids ?: [0]) . ') ORDER BY id', $sql);
    }
    return $sql;
});
register_shutdown_function(static function () use (&$ids, &$emails, $wpdb) {
    foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
    foreach ($emails as $email) { $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['email' => $email]); }
});
// El reloj ahora también detecta propuestas y arma el resumen semanal (Task 9). Aquí solo se prueba el envío: la detección
// no ve ninguna propuesta y el resumen ninguna fila (queda vacío y no manda correo). Ruling 3: se cuenta que los dos
// filtros calzaron de verdad, y las opciones del resumen se dejan como estaban.
$filtro_det = 0; $filtro_lista = 0;
add_filter('query', static function ($sql) use (&$filtro_det, &$filtro_lista, $wpdb) {
    if (str_starts_with($sql, 'SELECT id, client_email') && str_contains($sql, "FROM {$wpdb->prefix}automatiza_propuestas WHERE client_email <> ''")) { $filtro_det++; return str_replace(' ORDER BY id DESC', ' AND 1=0 ORDER BY id DESC', $sql); }
    if ($sql === "SELECT * FROM {$wpdb->prefix}at_retomar ORDER BY id DESC") { $filtro_lista++; return "SELECT * FROM {$wpdb->prefix}at_retomar WHERE 1=0 ORDER BY id DESC"; }
    return $sql;
});
$resumen_original = [];
foreach (['at_rt_resumen_semana', 'at_rt_resumen_desde', 'at_rt_resumen_dia', 'at_rt_resumen_hora'] as $o) { $resumen_original[$o] = get_option($o, null); }
register_shutdown_function(static function () use ($resumen_original) {
    foreach ($resumen_original as $o => $v) { $v === null ? delete_option($o) : update_option($o, $v); }
});
update_option('at_rt_resumen_semana', ''); update_option('at_rt_resumen_dia', '1'); update_option('at_rt_resumen_hora', '09:00');
/** Enlace firmado de $accion para la aprobación $ap del seguimiento $id, como lo recibe la página. */
function rt_env_q(int $id, string $accion, array $ap): array {
    parse_str((string) parse_url(at_rt_aprobacion_url($id, $accion, (string) ($ap['nonce'] ?? ''), (int) ($ap['vence'] ?? 0)), PHP_URL_QUERY), $q);
    return $q;
}
/** Ruling 10: la aprobación quedó por_aprobar, sin día programado y con los enlaces muertos (aprobar responde «usado»). */
function rt_env_aprobacion_muerta(array $f, array $ap_original): bool {
    $ap = $f['aprobacion'];
    return ($ap['estado'] ?? '') === 'por_aprobar' && ($ap['usada'] ?? null) === true && !isset($ap['programado']) && ($ap['nonce'] ?? '') === ($ap_original['nonce'] ?? null)
        && at_rt_aprobacion_motivo($f, 'aprobar', rt_env_q((int) $f['id'], 'aprobar', $ap_original), (int) $ap_original['vence'] - 60) === 'usado';
}
/**
 * Seguimiento activo listo para salir. Por defecto trae la redacción `lista` y la aprobación de Luis tal como la deja
 * Task 7 al aprobar: {estado:'aprobado', n: el recordatorio que toca, huella: la del render, usada, programado: su
 * proximo_at}. Pasar 'aprobacion' o 'redaccion' en $c deja ese valor tal cual.
 */
function rt_env_fixture(array $c = []): array {
    global $ids, $emails, $marca;
    $email = $marca . '-' . count($ids) . '@example.com'; $emails[] = $email;
    $id = at_rt_crear($c + ['nombre' => '[PRUEBA]', 'negocio' => '[PRUEBA]', 'email' => $email, 'telefono' => '+56900000000', 'wa_autorizado' => 1, 'estado' => 'activo', 'proximo_at' => '2026-10-19', 'activado_at' => '2026-10-01 00:00:00']);
    $ids[] = $id; $s = at_rt_obtener($id);
    $campos = ['render' => ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/index.html']];
    if (!array_key_exists('redaccion', $c)) { $campos['redaccion'] = ['estado' => 'lista']; }
    at_rt_guardar($id, $campos); $s = at_rt_obtener($id);
    // Huella real del contenido; un texto que la presentación rechaza (p. ej. con «easypanel») no tiene huella calculable.
    try { $huella = at_rt_huella_render($s); } catch (InvalidArgumentException $e) { $huella = hash('sha256', 'rt-env-sin-huella-' . $id); }
    $campos = ['render' => array_merge($s['render'], ['huella' => $huella])];
    if (!array_key_exists('aprobacion', $c)) {
        $campos['aprobacion'] = ['estado' => 'aprobado', 'n' => at_rt_envios_realizados($s['envios']) + 1, 'nonce' => bin2hex(random_bytes(8)), 'huella' => $huella, 'emitido_at' => '2026-10-12 09:00:00', 'vence' => strtotime('2026-10-19 09:00:00'), 'usada' => true, 'resuelto_at' => '2026-10-12 09:05:00', 'programado' => (string) ($s['proximo_at'] ?: '2026-10-19')];
    }
    at_rt_guardar($id, $campos);
    return at_rt_obtener($id);
}
function rt_env_tick(string $cuando): array { return at_rt_tick($cuando); }
/** Tick que solo ve los seguimientos $solo (el resto de los fixtures queda fuera del SELECT del reloj). */
function rt_env_tick_solo(array $solo, string $cuando): array {
    global $ids; $todos = $ids; $ids = $solo;
    try { return at_rt_tick($cuando); } finally { $ids = $todos; }
}
/** Correos entregados a una dirección. */
function rt_env_correos(string $email): int {
    global $mails; return count(array_filter($mails, static fn($m) => $m['to'] === $email));
}
update_option('at_rt_wa_automatico', '1');
$s = rt_env_fixture();
$r0 = rt_env_tick('2026-10-19 09:30:00');
ok($r0['enviados'] === 0 && count($mails) === 0, '09:30 no entrega');
ok($filtro_det > 0 && $filtro_lista > 0 && $r0['detectados'] === 0 && $r0['resumen']['motivo'] === 'vacio', 'el reloj pasó por la detección y el resumen, acotados por la prueba (sin correo)');
ok(rt_env_tick('2026-10-24 10:30:00')['enviados'] === 0 && count($mails) === 0, 'sábado no entrega');
$r = rt_env_tick('2026-10-19 10:30:00');
ok($r['enviados'] === 1 && count($r['whatsapp']) === 1 && count($mails) === 1, 'primer correo entrega y devuelve un pedido WA');
ok(at_rt_obtener($s['id'])['envios'][0]['whatsapp']['estado'] === 'pendiente', 'evento persistido antes del pedido WA');
rt_env_tick('2026-10-19 11:30:00');
ok(count($mails) === 1, 'dos ticks mismo día un solo correo');
$m = $mails[0]; $headers = implode('\n', $m['headers']);
ok(str_contains($headers, 'List-Unsubscribe: <https://') && str_contains($headers, 'One-Click') && !str_contains($headers, 'mailto:'), 'baja HTTPS OneClick sin mailto');
ok(str_contains($m['message'], '/retomar.php?t=') && !str_contains($m['message'], at_pt_host_renderer()), 'correo enlaza página pública sin URL renderer');
ok(at_rt_obtener($s['id'])['proximo_at'] === '2026-10-26' && at_rt_obtener($s['id'])['enviando'] === null, 'siguiente semana y claim liberado');
$wr = at_rt_whatsapp_resultado(['id' => $s['id'], 'n' => 1, 'codigo' => 131049, 'titulo' => 'Prueba']);
ok($wr['ok'] && at_rt_obtener($s['id'])['envios'][0]['whatsapp']['estado'] === 'error', '131049 registra error en el mismo contacto');
ok(at_rt_whatsapp_resultado(['id' => $s['id'], 'n' => 1, 'wamid' => 'prueba'])['motivo'] === 'sin_cambio' && count(at_rt_obtener($s['id'])['envios']) === 1, 'callback idempotente no repite ni agrega contacto');
ok(!at_rt_whatsapp_resultado(['id' => $s['id'], 'n' => 99, 'wamid' => 'prueba'])['ok'], 'callback ajeno rechazado');
$b = rt_env_fixture(['telefono' => '']); at_rt_dar_baja($b['id'], 'prueba');
$antes = count($mails); rt_env_tick('2026-10-19 10:30:00');
ok(count($mails) === $antes && at_rt_obtener($b['id'])['estado'] === 'baja', 'supresión no recibe correo');
$b2 = rt_env_fixture(['email' => $b['email'], 'telefono' => '']);
ok(rt_env_tick('2026-10-19 10:30:00')['detenidos'] === 1 && at_rt_obtener($b2['id'])['estado'] === 'baja', 'correo en bajas detiene activo');
foreach ([['telefono' => ''], ['wa_autorizado' => 0], ['telefono' => '123']] as $c) {
    $x = rt_env_fixture($c);
    $r = rt_env_tick('2026-10-19 10:30:00');
    ok($r['enviados'] === 1 && $r['whatsapp'] === [], 'sin teléfono/casilla/válido entrega sólo email');
}
$p = rt_env_fixture(['telefono' => '']); at_rt_guardar($p['id'], ['render' => ['estado' => 'pendiente']]);
$r = rt_env_tick('2026-10-23 10:30:00');
ok($r['diferidos'] === 1 && at_rt_obtener($p['id'])['proximo_at'] === '2026-10-26', 'render pendiente difiere al siguiente día hábil, no siete días');
at_rt_guardar($p['id'], ['estado' => 'pausado']);
$ev = [];
for ($i=1;$i<=3;$i++) { $ev[] = ['tipo'=>'envio','n'=>$i,'fecha'=>'2026-09-' . (10+$i),'at'=>'2026-09-11 10:00:00','correo'=>['estado'=>'enviado'],'whatsapp'=>null]; }
// Los tres envíos previos van en el alta: la aprobación del fixture es la del recordatorio que toca (el 4.º).
$cuarto = rt_env_fixture(['telefono' => '+56900000001', 'envios' => $ev]); $r = rt_env_tick('2026-10-19 10:30:00');
ok($r['enviados'] === 1 && $r['whatsapp'][0]['n'] === 4 && at_rt_obtener($cuarto['id'])['estado'] === 'agotado', 'cuarto envío agota y solicita WA');
$p = rt_env_fixture(['telefono' => '', 'textos' => ['quedamos' => 'Consultar easypanel interno']]);
$antes=count($mails); $r=at_rt_entregar($p['id'], false, '2026-10-19 10:30:00');
ok($r['motivo'] === 'interno' && count($mails)===$antes, 'texto interno bloquea entrega'); at_rt_guardar($p['id'], ['estado'=>'pausado']);
$p=rt_env_fixture(['telefono'=>'','enviando'=>'2026-10-19 10:29:00']);
ok(at_rt_entregar($p['id'], false, '2026-10-19 10:30:00')['motivo']==='ocupado_o_inactivo', 'claim vigente impide envío'); at_rt_guardar($p['id'], ['estado'=>'pausado']);
$p=rt_env_fixture(['telefono'=>'']); $falla_mail=true; $r=at_rt_entregar($p['id'],false,'2026-10-19 10:30:00'); $falla_mail=false;
ok(!$r['ok'] && at_rt_obtener($p['id'])['estado']==='pausado' && at_rt_obtener($p['id'])['envios'][0]['correo']['estado']==='error', 'fallo correo se registra y pausa sin retry');
$p=rt_env_fixture(['telefono'=>'']); $antes=count($mails); $r=at_rt_enviar($p['id'],true);
ok($r['ok'] && $r['whatsapp']===null && at_rt_obtener($p['id'])['envios']===[] && count($mails)===$antes+1, 'prueba no consume contacto ni WA');
ok(at_rt_obtener($p['id'])['aprobacion'] === $p['aprobacion'], 'prueba no consume la aprobación');
ok($mails[$antes]['to']===at_cc_correo_avisos() && str_starts_with($mails[$antes]['subject'],'[Prueba] '), 'prueba se dirige al equipo y rotula asunto');
ok(str_contains($alternativas[0], 'Ver resumen y demo: https://') && !str_contains($alternativas[0], '<html'), 'correo tiene alternativa de texto real');
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA]', 'email' => $marca . '-crm@example.com', 'tipo' => 'prospecto']);
$crm = (int) $wpdb->insert_id;
register_shutdown_function(static function () use ($wpdb, $crm) { $wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $crm]); $wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $crm]); });
$b = rt_env_fixture(['crm_cliente_id' => $crm, 'telefono' => '']); at_rt_dar_baja($b['id'], 'prueba'); at_rt_dar_baja($b['id'], 'prueba');
$h = $wpdb->get_results($wpdb->prepare("SELECT tipo_evento,descripcion FROM {$wpdb->prefix}crm_historial WHERE cliente_id=%d", $crm), ARRAY_A);
ok(count($h) === 1 && $h[0]['tipo_evento'] === 'retomar_baja' && !str_contains($h[0]['descripcion'], $b['token']), 'baja real deja una sola historia sin token tras repetición');
$p = rt_env_fixture(['crm_cliente_id' => $crm, 'telefono' => '']); at_rt_entregar($p['id'], false, '2026-10-19 10:30:00');
$h = $wpdb->get_row($wpdb->prepare("SELECT tipo_evento,descripcion FROM {$wpdb->prefix}crm_historial WHERE cliente_id=%d AND tipo_evento='retomar_correo'", $crm), ARRAY_A);
ok($h && !str_contains($h['descripcion'], $p['token']) && !str_contains($h['descripcion'], 'http'), 'correo deja historia CRM sin enlaces secretos');
$p = rt_env_fixture(['telefono' => '', 'proximo_at'=>'2026-10-19']);
at_rt_guardar($p['id'], ['render' => ['estado'=>'listo','view_url'=>'https://example.com/invalida']]);
ok(at_rt_entregar($p['id'],false,'2026-10-19 10:30:00')['motivo']==='diferido' && at_rt_obtener($p['id'])['proximo_at']==='2026-10-20', 'render listo con URL inválida difiere un día hábil');
$sigue = at_rt_obtener($s['id']);
ok(count($sigue['envios'])===1 && $sigue['envios'][0]['whatsapp']['estado']==='error', 'error WA no genera pedido ni nuevo número');
$req=new WP_REST_Request('POST','/automatiza-tech/v1/retomar/tick');
ok(rest_do_request($req)->get_status()===401, 'tick privado rechaza sin secreto');
$req=new WP_REST_Request('POST','/automatiza-tech/v1/retomar/whatsapp-resultado');
ok(rest_do_request($req)->get_status()===401, 'callback privado rechaza sin secreto');
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', 'fixture-local-retomar'); }
$req->set_header('x-at-secret', AT_REST_SECRET); $req->set_body(wp_json_encode(['id'=>$cuarto['id'],'n'=>4,'wamid'=>'fixture-local'])); $req->set_header('Content-Type','application/json');
ok(rest_do_request($req)->get_data()['ok'] === true && at_rt_obtener($cuarto['id'])['envios'][3]['whatsapp']['estado']==='enviado', 'callback autenticado confirma WA en el contacto existente');
$req=new WP_REST_Request('POST','/automatiza-tech/v1/retomar/tick'); $req->set_header('x-at-secret',AT_REST_SECRET);
ok(rest_do_request($req)->get_status()===200, 'tick autenticado registrado');
ok(!at_rt_whatsapp_resultado(['id'=>$p['id'],'n'=>1,'wamid'=>'fixture-local'])['ok'], 'callback sin pedido WA pendiente no inventa entrega');
$p=rt_env_fixture(['telefono'=>'','enviando'=>'2026-10-19 10:00:00']);
ok(at_rt_entregar($p['id'],false,'2026-10-19 10:30:00')['ok'], 'claim vencido después de diez minutos se recupera');
at_rt_guardar($p['id'], ['proximo_at'=>'2026-10-19']); $antes=count($mails);
ok(at_rt_entregar($p['id'],false,'2026-10-19 11:30:00')['motivo']==='ya_intentado' && count($mails)===$antes, 'registro de intento impide repetir aun alterando proximo_at');
$p=rt_env_fixture(['telefono'=>'','crm_cliente_id'=>$crm]); $wpdb->update($wpdb->prefix.'crm_clientes',['tipo'=>'cliente'],['id'=>$crm]);
ok(at_rt_entregar($p['id'],false,'2026-10-19 11:30:00')['motivo']==='cliente' && at_rt_obtener($p['id'])['estado']==='cliente', 'cliente CRM detiene antes del correo');
ok(at_tl_tipos_extra()['retomar_agendo']['label']==='🔁 Agendó reunión' && at_tl_mapa_historial()['retomar_baja']==='retomar_baja', 'etiquetas exactas Retomar integradas a timeline');
$con=rt_env_fixture(['telefono'=>'+56900000002']); at_rt_entregar($con['id'],false,'2026-10-19 10:30:00'); $inyectado=false;
$carrera=static function ($sql) use ($wpdb, $con, &$inyectado) {
    if (!$inyectado && str_starts_with($sql, "UPDATE {$wpdb->prefix}at_retomar SET envios=") && str_contains($sql, 'AND BINARY envios=')) {
        $inyectado=true; at_rt_agregar_envio($con['id'], ['tipo'=>'diferido','fecha'=>'2026-10-20','nota'=>'fixture_concurrente']);
    }
    return $sql;
};
add_filter('query',$carrera,20); $res=at_rt_whatsapp_resultado(['id'=>$con['id'],'n'=>1,'wamid'=>'fixture-concurrente']); remove_filter('query',$carrera,20);
$eventos=at_rt_obtener($con['id'])['envios'];
ok($inyectado && $res['ok'] && count($eventos)===2 && $eventos[0]['whatsapp']['estado']==='enviado' && $eventos[1]['nota']==='fixture_concurrente', 'CAS conserva evento agregado entre lectura y callback');
foreach (['baja','agendo','pausado'] as $i => $parada) {
    $race=rt_env_fixture(['telefono'=>'+5690000000'.(3+$i)]);
    $durante=static function ($v,$mail) use ($race,$parada) {
        if ($mail['to'] === $race['email']) {
            if ($parada==='baja') { at_rt_dar_baja($race['id'],'prueba'); }
            else { at_rt_guardar($race['id'],['estado'=>$parada,'proximo_at'=>null]); }
        }
        return $v;
    };
    add_filter('pre_wp_mail',$durante,150,2); $resultado=at_rt_entregar($race['id'],false,'2026-10-19 10:30:00'); remove_filter('pre_wp_mail',$durante,150);
    $final=at_rt_obtener($race['id']);
    ok($resultado['ok'] && $final['estado']===$parada && $final['proximo_at']===null && count($final['envios'])===1 && $final['envios'][0]['correo']['estado']==='enviado' && $final['envios'][0]['whatsapp']===null && $resultado['whatsapp']===null, 'parada '.$parada.' durante correo conserva entrega y detiene WA sin reactivar');
}
$dedup=rt_env_fixture(['crm_cliente_id'=>$crm,'telefono'=>'']); $inyectado=false;
$antes=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id=%d AND tipo_evento='retomar_baja'",$crm));
$carrera=static function($sql) use($dedup,&$inyectado) {
    if (!$inyectado && $sql==='START TRANSACTION') { $inyectado=true; at_rt_dar_baja($dedup['id'],'prueba'); }
    return $sql;
};
add_filter('query',$carrera,20); $resultado=at_rt_dar_baja($dedup['id'],'prueba'); remove_filter('query',$carrera,20);
$despues=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id=%d AND tipo_evento='retomar_baja'",$crm));
ok($inyectado && $resultado && $despues===$antes+1, 'baja concurrente entre snapshot y BEGIN genera una sola historia');

// ---------- Task 8: solo sale lo que Luis aprobó ----------
// Sin aprobación (o por aprobar, con cambios pedidos u omitida): nada sale y nada cambia; el claim se libera.
foreach (['sin emitir' => [], 'por_aprobar' => ['estado' => 'por_aprobar', 'n' => 1, 'nonce' => str_repeat('a', 16), 'huella' => 'x', 'usada' => false], 'cambios' => ['estado' => 'cambios', 'n' => 1, 'usada' => true], 'omitido' => ['estado' => 'omitido', 'n' => 1, 'usada' => true]] as $caso => $ap) {
    $na = rt_env_fixture(['telefono' => '', 'aprobacion' => $ap]);
    $r = at_rt_entregar($na['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($na['id']);
    ok(!$r['ok'] && $r['motivo'] === 'sin_aprobacion' && rt_env_correos($na['email']) === 0 && $f['envios'] === [] && $f['estado'] === 'activo' && $f['proximo_at'] === '2026-10-19' && $f['enviando'] === null && $f['aprobacion'] === $ap, 'activo ' . $caso . ': sin_aprobacion, no sale nada, no cambia nada y libera el claim');
}
$na = rt_env_fixture(['telefono' => '', 'aprobacion' => []]);
$r = rt_env_tick_solo([$na['id']], '2026-10-19 10:30:00');
ok($r['enviados'] === 0 && $r['whatsapp'] === [] && rt_env_correos($na['email']) === 0 && at_rt_obtener($na['id'])['estado'] === 'activo', 'el reloj no envía un activo sin aprobación');
// Aprobado para mañana: hoy no sale; mañana sí.
$pm = rt_env_fixture(['telefono' => '']); $ap = $pm['aprobacion']; $ap['programado'] = '2026-10-20'; at_rt_guardar($pm['id'], ['aprobacion' => $ap]);
$r = at_rt_entregar($pm['id'], false, '2026-10-19 10:30:00');
ok($r['motivo'] === 'sin_aprobacion' && rt_env_correos($pm['email']) === 0 && at_rt_obtener($pm['id'])['aprobacion'] === $ap && at_rt_obtener($pm['id'])['enviando'] === null, 'aprobado para mañana: hoy no sale y la aprobación sigue intacta');
$r = at_rt_entregar($pm['id'], false, '2026-10-20 10:30:00');
ok($r['ok'] && rt_env_correos($pm['email']) === 1, 'aprobado para mañana: sale el día programado');
$pm = rt_env_fixture(['telefono' => '']); $ap = $pm['aprobacion']; $ap['programado'] = 'mañana';
at_rt_guardar($pm['id'], ['aprobacion' => $ap]);
ok(at_rt_entregar($pm['id'], false, '2026-10-19 10:30:00')['motivo'] === 'sin_aprobacion' && rt_env_correos($pm['email']) === 0, 'aprobado con fecha programada ilegible: no sale');
// Aprobado: un correo y la aprobación queda consumida en la misma transacción que el envío.
$ok1 = rt_env_fixture(['telefono' => '']);
$r = at_rt_entregar($ok1['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($ok1['id']);
ok($r['ok'] && rt_env_correos($ok1['email']) === 1 && count($f['envios']) === 1 && $f['envios'][0]['n'] === 1, 'aprobado: sale un correo');
ok($f['aprobacion'] === ['estado' => 'enviado', 'n' => 1, 'at' => '2026-10-19 10:30:00'], 'aprobado: la aprobación queda {estado: enviado, n, at}');
// Review Focus 2: dos ticks seguidos con el mismo aprobado → un solo correo; la aprobación consumida no sirve otro día.
$dt = rt_env_fixture(['telefono' => '', 'proximo_at' => '2026-10-21']);
rt_env_tick_solo([$dt['id']], '2026-10-21 10:30:00'); rt_env_tick_solo([$dt['id']], '2026-10-21 10:30:00');
ok(rt_env_correos($dt['email']) === 1, 'dos ticks seguidos con el mismo aprobado: un solo correo');
at_rt_guardar($dt['id'], ['proximo_at' => '2026-10-21']); rt_env_tick_solo([$dt['id']], '2026-10-21 11:30:00');
ok(rt_env_correos($dt['email']) === 1, 'otro tick el mismo día, aun con proximo_at de vuelta a hoy: sigue un solo correo');
at_rt_guardar($dt['id'], ['proximo_at' => '2026-10-22']);
$r = at_rt_entregar($dt['id'], false, '2026-10-22 10:30:00');
ok($r['motivo'] === 'sin_aprobacion' && rt_env_correos($dt['email']) === 1 && count(at_rt_obtener($dt['id'])['envios']) === 1, 'la aprobación consumida no sirve para el día siguiente');
// Dos relojes a la vez: el segundo llega mientras el primero manda el correo.
$doble = rt_env_fixture(['telefono' => '']); $segundo = null;
$durante = static function ($v, $mail) use ($doble, &$segundo) {
    if ($mail['to'] === $doble['email'] && $segundo === null) { $segundo = at_rt_entregar($doble['id'], false, '2026-10-19 10:30:30'); }
    return $v;
};
add_filter('pre_wp_mail', $durante, 150, 2); at_rt_entregar($doble['id'], false, '2026-10-19 10:30:00'); remove_filter('pre_wp_mail', $durante, 150);
ok(($segundo['motivo'] ?? '') === 'ocupado_o_inactivo' && rt_env_correos($doble['email']) === 1 && at_rt_obtener($doble['id'])['aprobacion']['estado'] === 'enviado', 'dos relojes a la vez con el mismo aprobado: un solo correo');
// Review Focus 3: aprobado y después se da de baja o agenda → nada sale y queda detenido.
$bj = rt_env_fixture(['telefono' => '']);
$wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $bj['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]);
$r = at_rt_entregar($bj['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($bj['id']);
ok($r['motivo'] === 'baja' && rt_env_correos($bj['email']) === 0 && $f['estado'] === 'baja' && $f['proximo_at'] === null, 'aprobado y luego baja: no sale nada y queda en baja');
ok(rt_env_aprobacion_muerta($f, $bj['aprobacion']), 'Ruling 10: aprobado y luego baja: la aprobación no sobrevive a la detención (por_aprobar, enlaces muertos)');
ok(count($f['envios']) === 1 && ($f['envios'][0]['tipo'] ?? '') === 'detenido' && ($f['envios'][0]['motivo'] ?? '') === 'baja' && ($f['envios'][0]['at'] ?? '') === '2026-10-19 10:30:00' && $f['enviando'] === null, 'la detención deja un solo evento «detenido» y libera el claim');
$bj = rt_env_fixture(['telefono' => '']); at_rt_dar_baja($bj['id'], 'prueba');
rt_env_tick_solo([$bj['id']], '2026-10-19 10:30:00');
ok(rt_env_correos($bj['email']) === 0 && at_rt_obtener($bj['id'])['estado'] === 'baja', 'aprobado y luego baja desde su página: el reloj no le envía');
$ag = rt_env_fixture(['telefono' => '']);
$wpdb->insert($wpdb->prefix . 'automatiza_followup_meetings', ['client_name' => '[PRUEBA]', 'client_email' => $ag['email'], 'meeting_date' => '2026-12-01', 'meeting_time' => '10:00:00', 'status' => 'scheduled', 'created_at' => '2026-10-15 12:00:00']);
$reunion = (int) $wpdb->insert_id;
register_shutdown_function(static function () use ($wpdb, $reunion) { $wpdb->delete($wpdb->prefix . 'automatiza_followup_meetings', ['id' => $reunion]); });
$r = at_rt_entregar($ag['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($ag['id']);
ok($reunion > 0 && $r['motivo'] === 'agendo' && rt_env_correos($ag['email']) === 0 && $f['estado'] === 'agendo' && $f['proximo_at'] === null, 'aprobado y luego agenda una reunión: no sale nada y queda en agendó');
ok(rt_env_aprobacion_muerta($f, $ag['aprobacion']), 'Ruling 10: aprobado y luego agenda: la aprobación no sobrevive a la detención');
// Una aprobación que no estaba aprobada (por aprobar) no cambia al detener.
$pa = rt_env_fixture(['telefono' => '', 'aprobacion' => ['estado' => 'por_aprobar', 'n' => 1, 'nonce' => str_repeat('d', 16), 'huella' => 'x', 'usada' => false]]);
$wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $pa['email'], 'telefono' => '', 'origen' => 'prueba', 'creado_at' => current_time('mysql')]);
at_rt_entregar($pa['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($pa['id']);
ok($f['estado'] === 'baja' && $f['aprobacion'] === $pa['aprobacion'], 'detener sin una aprobación aprobada deja la aprobación como estaba');
// Lo aprobado debe ser exactamente lo que sale: si cambió después de aprobar, no sale y vuelve a pedir aprobación.
// [cambio, ¿la presentación quedó vieja?]: si el contenido actual ya no es el del render, el render deja de estar `listo`.
$vencidas = [
    'huella del render distinta' => [static function (array $s): void { at_rt_guardar($s['id'], ['render' => array_merge($s['render'], ['huella' => hash('sha256', 'otra presentación')])]); }, true],
    'textos cambiados sin rehacer la presentación' => [static function (array $s): void { at_rt_guardar($s['id'], ['textos' => ['quedamos' => '[PRUEBA] Texto nuevo que nadie aprobó']]); }, true],
    'recordatorio n distinto' => [static function (array $s): void { at_rt_guardar($s['id'], ['aprobacion' => array_merge($s['aprobacion'], ['n' => 2])]); }, false],
    'redacción pendiente' => [static function (array $s): void { at_rt_guardar($s['id'], ['redaccion' => ['estado' => 'pendiente']]); }, false],
];
foreach ($vencidas as $caso => [$cambiar, $render_viejo]) {
    $v = rt_env_fixture(['telefono' => '+56900000011']); $cambiar($v);
    $r = at_rt_entregar($v['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($v['id']); $ultimo = end($f['envios']) ?: [];
    ok(!$r['ok'] && $r['motivo'] === 'aprobacion_vencida' && $r['whatsapp'] === null && rt_env_correos($v['email']) === 0 && at_rt_envios_realizados($f['envios']) === 0 && $f['enviando'] === null && $f['estado'] === 'activo', 'aprobado con ' . $caso . ': no sale nada (aprobacion_vencida)');
    ok($render_viejo ? ($f['render']['estado'] ?? '') !== 'listo' : ($f['render']['estado'] ?? '') === 'listo', 'aprobado con ' . $caso . ': ' . ($render_viejo ? 'la presentación vieja deja de estar lista' : 'la presentación sigue lista'));
    ok(($f['aprobacion']['estado'] ?? '') === 'por_aprobar' && ($f['aprobacion']['usada'] ?? null) === true && !isset($f['aprobacion']['programado']) && ($ultimo['tipo'] ?? '') === 'diferido' && ($ultimo['nota'] ?? '') === 'aprobacion_vencida', 'aprobado con ' . $caso . ': la aprobación vuelve a por_aprobar con los enlaces muertos y queda anotado');
    $r = at_rt_entregar($v['id'], false, '2026-10-19 11:30:00');
    ok($r['motivo'] === 'sin_aprobacion' && rt_env_correos($v['email']) === 0 && count(at_rt_obtener($v['id'])['envios']) === count($f['envios']), 'aprobado con ' . $caso . ': el reloj siguiente no envía ni repite la anotación');
}
// Si la aprobación cambia mientras sale el correo, no se pisa ni se pide el WhatsApp de lo que ya no está aprobado.
$cm = rt_env_fixture(['telefono' => '+56900000012']);
$nueva = ['estado' => 'por_aprobar', 'n' => 1, 'nonce' => str_repeat('b', 16), 'huella' => $cm['aprobacion']['huella'], 'emitido_at' => '2026-10-19 10:30:01', 'vence' => strtotime('2026-10-26 10:30:01'), 'usada' => false];
$durante = static function ($v, $mail) use ($cm, $nueva) { if ($mail['to'] === $cm['email']) { at_rt_guardar($cm['id'], ['aprobacion' => $nueva]); } return $v; };
add_filter('pre_wp_mail', $durante, 150, 2); $r = at_rt_entregar($cm['id'], false, '2026-10-19 10:30:00'); remove_filter('pre_wp_mail', $durante, 150); $f = at_rt_obtener($cm['id']);
ok($r['ok'] && $r['whatsapp'] === null && $f['aprobacion'] === $nueva && count($f['envios']) === 1 && $f['envios'][0]['correo']['estado'] === 'enviado' && $f['envios'][0]['whatsapp'] === null, 'aprobación cambiada durante el correo: no se consume ni pide WhatsApp; el envío queda registrado');
// Ruling 10: correo fallido: no sale nada, el seguimiento queda en pausa y lo aprobado no sobrevive (enlaces muertos).
$cf = rt_env_fixture(['telefono' => '']); $falla_mail = true; $r = at_rt_entregar($cf['id'], false, '2026-10-19 10:30:00'); $falla_mail = false;
$f = at_rt_obtener($cf['id']);
ok(!$r['ok'] && $r['motivo'] === 'correo_error' && $f['estado'] === 'pausado' && rt_env_aprobacion_muerta($f, $cf['aprobacion']), 'correo fallido: la aprobación vuelve a por_aprobar con los enlaces muertos y el seguimiento queda en pausa');
ok(count($f['envios']) === 1 && $f['envios'][0]['correo']['estado'] === 'error', 'el correo fallido queda registrado una vez');
// 2.º recordatorio con WhatsApp autorizado y el interruptor encendido: también lleva WhatsApp.
$wa2 = rt_env_fixture(['telefono' => '+56900000013', 'envios' => [['tipo' => 'envio', 'n' => 1, 'fecha' => '2026-10-12', 'at' => '2026-10-12 10:30:00', 'correo' => ['estado' => 'enviado'], 'whatsapp' => null]]]);
$r = rt_env_tick_solo([$wa2['id']], '2026-10-19 10:30:00');
ok($wa2['aprobacion']['n'] === 2 && $r['enviados'] === 1 && count($r['whatsapp']) === 1 && $r['whatsapp'][0]['n'] === 2 && $r['whatsapp'][0]['id'] === $wa2['id'], '2.º envío aprobado con WhatsApp autorizado: un pedido de WhatsApp');
ok(at_rt_obtener($wa2['id'])['aprobacion'] === ['estado' => 'enviado', 'n' => 2, 'at' => '2026-10-19 10:30:00'], '2.º envío consume su aprobación con n = 2');
// «Enviar prueba al equipo» funciona sin aprobación y no crea ninguna.
$pr = rt_env_fixture(['telefono' => '', 'aprobacion' => []]); $antes = count($mails);
$r = at_rt_enviar($pr['id'], true); $f = at_rt_obtener($pr['id']);
ok($r['ok'] && count($mails) === $antes + 1 && $mails[$antes]['to'] === at_cc_correo_avisos() && rt_env_correos($pr['email']) === 0 && $f['aprobacion'] === [] && $f['envios'] === [], 'enviar prueba al equipo funciona sin aprobación y no la crea');
// Propuesta vinculada: si está `lista` y es v3, el recordatorio aprobado la deja `sent` con una nota; otra, sin cambios.
$props = [];
register_shutdown_function(static function () use ($wpdb, &$props) {
    foreach ($props as $pid) { $wpdb->delete($wpdb->prefix . 'automatiza_propuestas_details', ['propuesta_id' => $pid]); $wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $pid]); }
});
$rt_env_propuesta = static function (string $status, ?string $flujo) use ($wpdb, $marca, &$props): int {
    $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '-prop' . count($props) . '@example.com', 'client_name' => '[PRUEBA]', 'company_name' => '[PRUEBA]', 'unique_link_id' => substr(md5($marca . 'prop' . count($props)), 0, 12), 'status' => $status, 'flujo' => $flujo, 'gamma_prompt_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
    $pid = (int) $wpdb->insert_id; $props[] = $pid; return $pid;
};
// El sitio local va por http y el enlace v3 de la presentación exige https (igual que en render-wp-test.php).
$https_local = static fn($url) => preg_replace('~^http:~', 'https:', $url);
add_filter('home_url', $https_local);
$rt_env_estado_prop = static fn(int $pid): string => (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}automatiza_propuestas WHERE id=%d", $pid));
$rt_env_notas_prop = static fn(int $pid): array => $wpdb->get_results($wpdb->prepare("SELECT detail_type, title FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id=%d", $pid), ARRAY_A);
$pl = $rt_env_propuesta('lista', 'v3'); $sp = rt_env_fixture(['telefono' => '', 'propuesta_id' => $pl]);
$r = at_rt_entregar($sp['id'], false, '2026-10-19 10:30:00'); $notas = $rt_env_notas_prop($pl);
ok($r['ok'] && $rt_env_estado_prop($pl) === 'sent', 'propuesta v3 en lista queda sent tras el envío aprobado');
ok(count($notas) === 1 && $notas[0]['title'] === 'Enviada con el recordatorio de Retomar' && $notas[0]['detail_type'] === 'aviso_operativo', 'propuesta pasada a sent deja una sola nota interna');
foreach ([['sent', 'v3'], ['evaluando', 'v3'], ['lista', null]] as [$estado, $flujo]) {
    $po = $rt_env_propuesta($estado, $flujo); $so = rt_env_fixture(['telefono' => '', 'propuesta_id' => $po]);
    $r = at_rt_entregar($so['id'], false, '2026-10-19 10:30:00');
    ok($r['ok'] && $rt_env_estado_prop($po) === $estado && $rt_env_notas_prop($po) === [], 'propuesta ' . $estado . ' (' . ($flujo ?? 'sin flujo') . '): el envío no la toca');
}
$pp = $rt_env_propuesta('lista', 'v3'); $spp = rt_env_fixture(['telefono' => '', 'propuesta_id' => $pp]);
ok(at_rt_enviar($spp['id'], true)['ok'] && $rt_env_estado_prop($pp) === 'lista' && $rt_env_notas_prop($pp) === [], 'la prueba al equipo no pasa la propuesta a sent');
$pn = $rt_env_propuesta('lista', 'v3'); $spn = rt_env_fixture(['telefono' => '', 'propuesta_id' => $pn, 'aprobacion' => []]);
ok(at_rt_entregar($spn['id'], false, '2026-10-19 10:30:00')['motivo'] === 'sin_aprobacion' && $rt_env_estado_prop($pn) === 'lista', 'sin aprobación la propuesta sigue en lista');
// Ronda 1: el contenido cambia fuera de Retomar después de aprobar (aquí, el flujo de la propuesta vinculada). El reloj
// vence la aprobación y pide solo una presentación nueva; con ella lista, la aprobación nueva sí sale (sin bucle).
if (!defined('AT_N8N_RETOMAR_RENDER')) { define('AT_N8N_RETOMAR_RENDER', 'https://example.com/prueba-render'); }
$pedido_render = null;
// El bloqueo de HTTP del bootstrap sigue: SOLO el flujo de render obtiene respuesta simulada.
$timeout_render = null;
$render_simulado = static function ($pre, $args, $url) use (&$pedido_render, &$timeout_render) {
    if ($url !== AT_N8N_RETOMAR_RENDER) { return $pre; }
    $pedido_render = json_decode($args['body'], true);
    $timeout_render = $args['timeout'] ?? null;
    return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []];
};
add_filter('pre_http_request', $render_simulado, PHP_INT_MAX, 3);
$pf = $rt_env_propuesta('sent', 'v2'); $auto = rt_env_fixture(['telefono' => '', 'propuesta_id' => $pf]);
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['flujo' => 'v3'], ['id' => $pf]);
$r = at_rt_entregar($auto['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($auto['id']);
ok($r['motivo'] === 'aprobacion_vencida' && rt_env_correos($auto['email']) === 0 && ($f['aprobacion']['estado'] ?? '') === 'por_aprobar', 'flujo de la propuesta cambiado después de aprobar: no sale y la aprobación vence');
ok(($f['render']['estado'] ?? '') === 'pendiente' && is_array($pedido_render) && ($pedido_render['id'] ?? 0) === $auto['id'] && ($f['render']['generacion'] ?? '') === ($pedido_render['generacion'] ?? null) && hash_equals(at_rt_huella_render($f), (string) ($f['render']['huella'] ?? '')) && !hash_equals($auto['render']['huella'], (string) ($f['render']['huella'] ?? '')), 'el reloj pide solo una presentación nueva con la huella del contenido actual');
ok($timeout_render === 5, 'Ruling 12: la presentación que pide el reloj espera 5 s a n8n');
ok(at_rt_emitir_aprobacion($auto['id'], '2026-10-19 10:35:00') === null, 'mientras se rehace la presentación no se puede volver a aprobar');
$vista = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar/' . $auto['id'] . '/vista');
$vista->set_header('Content-Type', 'application/json'); $vista->set_header('X-AT-Secret', AT_REST_SECRET);
$vista->set_body(wp_json_encode(['ok' => true, 'generacion' => $pedido_render['generacion'] ?? '', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . ($pedido_render['render']['unique_id'] ?? '') . '/']));
ok(rest_do_request($vista)->get_status() === 200 && at_rt_obtener($auto['id'])['render']['estado'] === 'listo', 'el renderer devuelve la presentación nueva lista');
$enlaces = at_rt_emitir_aprobacion($auto['id'], '2026-10-19 11:00:00');
parse_str((string) parse_url((string) ($enlaces['aprobar'] ?? ''), PHP_URL_QUERY), $q);
$aprobado = at_rt_resolver_aprobacion($auto['id'], 'aprobar', $q, '2026-10-19 11:05:00');
ok(is_array($enlaces) && $aprobado['ok'] && ($aprobado['programado'] ?? '') === '2026-10-19', 'se emite y se aprueba la versión nueva');
$r = rt_env_tick_solo([$auto['id']], '2026-10-19 11:30:00'); $f = at_rt_obtener($auto['id']);
$vencimientos = count(array_filter($f['envios'], static fn($e) => ($e['nota'] ?? '') === 'aprobacion_vencida'));
ok($r['enviados'] === 1 && rt_env_correos($auto['email']) === 1 && ($f['aprobacion']['estado'] ?? '') === 'enviado' && $vencimientos === 1, 'la aprobación nueva sale en el reloj siguiente, sin volver a vencer');
// Contenido que ya no se puede presentar: el render queda en error con una nota clara y no se pide nada al renderer.
$pedido_render = null; $ce = rt_env_fixture(['telefono' => '']);
at_rt_guardar($ce['id'], ['textos' => ['quedamos' => str_repeat('a', 601)]]);
$r = at_rt_entregar($ce['id'], false, '2026-10-19 10:30:00'); $f = at_rt_obtener($ce['id']);
ok($r['motivo'] === 'aprobacion_vencida' && rt_env_correos($ce['email']) === 0 && ($f['render']['estado'] ?? '') === 'error' && str_starts_with((string) ($f['render']['nota'] ?? ''), 'El contenido cambió después de aprobar y la presentación ya no se puede armar') && $pedido_render === null, 'contenido que ya no se puede presentar: render en error con nota clara y sin pedir otra presentación');
ok(at_rt_emitir_aprobacion($ce['id'], '2026-10-19 10:35:00') === null, 'con la presentación en error no se vuelve a emitir la aprobación');
remove_filter('pre_http_request', $render_simulado, PHP_INT_MAX);
remove_filter('home_url', $https_local);
// Interruptor del WhatsApp automático (pedido de Luis, 18-oct): apagado = solo correo, sin pedido de WhatsApp.
update_option('at_rt_wa_automatico', '0');
$sa = rt_env_fixture(['proximo_at' => '2026-10-20']);
$ra = rt_env_tick('2026-10-20 10:30:00');
$ea = at_rt_obtener($sa['id'])['envios'];
ok($ra['enviados'] >= 1 && $ra['whatsapp'] === [] && ($ea[0]['whatsapp'] ?? null) === null, 'WhatsApp automático apagado: sale el correo y no se pide WhatsApp');
ok(!at_rt_wa_automatico(), 'el interruptor apagado se lee como apagado');
update_option('at_rt_wa_automatico', '1');
ok(at_rt_wa_automatico(), 'el interruptor encendido se lee como encendido');
delete_option('at_rt_wa_automatico');
ok(!at_rt_wa_automatico(), 'sin opción guardada, el WhatsApp automático queda apagado');
fin();
