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
function rt_env_fixture(array $c = []): array {
    global $ids, $emails, $marca;
    $email = $marca . '-' . count($ids) . '@example.com'; $emails[] = $email;
    $id = at_rt_crear($c + ['nombre' => '[PRUEBA]', 'negocio' => '[PRUEBA]', 'email' => $email, 'telefono' => '+56900000000', 'wa_autorizado' => 1, 'estado' => 'activo', 'proximo_at' => '2026-10-19', 'activado_at' => '2026-10-01 00:00:00']);
    $ids[] = $id; $s = at_rt_obtener($id);
    at_rt_guardar($id, ['render' => ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/index.html']]);
    return at_rt_obtener($id);
}
function rt_env_tick(string $cuando): array { return at_rt_tick($cuando); }
$s = rt_env_fixture();
ok(rt_env_tick('2026-10-19 09:30:00')['enviados'] === 0 && count($mails) === 0, '09:30 no entrega');
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
$cuarto = rt_env_fixture(['telefono' => '+56900000001']); $ev = [];
for ($i=1;$i<=3;$i++) { $ev[] = ['tipo'=>'envio','n'=>$i,'fecha'=>'2026-09-' . (10+$i),'at'=>'2026-09-11 10:00:00','correo'=>['estado'=>'enviado'],'whatsapp'=>null]; }
at_rt_guardar($cuarto['id'], ['envios' => $ev]); $r = rt_env_tick('2026-10-19 10:30:00');
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
ok($mails[$antes]['to']===at_cc_correo_avisos() && str_starts_with($mails[$antes]['subject'],'[Prueba] '), 'prueba se dirige al equipo y rotula asunto');
ok(str_contains($alternativas[0], 'Ver la presentación: https://') && !str_contains($alternativas[0], '<html'), 'correo tiene alternativa de texto real');
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
fin();
