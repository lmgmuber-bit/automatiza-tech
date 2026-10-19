<?php
// Detección automática de propuestas estancadas (Retomar, fase 2). Solo contra el sitio local de prueba.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_detectar', 'at_rt_fecha_envio_propuesta');
at_rt_migrar_esquema();
global $wpdb;
// El pedido de redacción debe intentarse (y fallar porque el HTTP está bloqueado): no existe ese flujo en pruebas.
if (!defined('AT_N8N_RETOMAR_REDACTAR')) { define('AT_N8N_RETOMAR_REDACTAR', 'https://example.invalid/webhook/retomar-redactar'); }
$marca = 'rt-det-' . strtolower(wp_generate_password(10, false, false));
$ahora = '2026-10-19 09:00:00'; $hoy = '2026-10-19';
$pids = []; $emails = []; $crm_ids = []; $esquema_original = get_option('at_rt_schema');
$tel_baja = '56900990099'; $origen_baja = 'rt-det-prueba';
// Aislar el SELECT de propuestas de la detección: sólo se ven las propuestas de esta prueba (Ruling 3).
add_filter('query', static function ($sql) use (&$pids, $wpdb) {
    if (str_starts_with($sql, 'SELECT id, client_email') && str_contains($sql, "FROM {$wpdb->prefix}automatiza_propuestas WHERE status IN")) {
        $sql = str_replace(' ORDER BY id', ' AND id IN (' . implode(',', $pids ?: [0]) . ') ORDER BY id', $sql);
    }
    return $sql;
});
register_shutdown_function(static function () use (&$pids, &$emails, &$crm_ids, $marca, $esquema_original, $tel_baja, $origen_baja, $wpdb) {
    update_option('at_rt_schema', $esquema_original, false);
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}at_retomar WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
    foreach ($pids as $id) {
        $wpdb->delete($wpdb->prefix . 'at_retomar', ['propuesta_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'automatiza_propuestas_details', ['propuesta_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $id]);
    }
    foreach ($emails as $email) { $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['email' => $email]); }
    $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['telefono' => $tel_baja, 'origen' => $origen_baja]);
    foreach ($crm_ids as $crm) { $wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $crm]); $wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $crm]); }
});
/** Fecha y hora de hace $dias días respecto de $ahora (08:15). */
function rt_det_dia(int $dias): string { return (new DateTimeImmutable('2026-10-19'))->modify("-{$dias} days")->format('Y-m-d') . ' 08:15:00'; }
function rt_det_prop(array $c = []): array {
    global $wpdb, $pids, $emails, $marca;
    $n = count($pids);
    $fila = $c + ['client_email' => $marca . '-' . $n . '@example.com', 'unique_link_id' => $marca . $n, 'client_name' => '[PRUEBA] Cliente ' . $n, 'company_name' => '[PRUEBA] Negocio ' . $n,
        'phone' => '+56900000000', 'status' => 'sent', 'flujo' => 'v3', 'created_at' => rt_det_dia(20)];
    $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', $fila);
    $id = (int) $wpdb->insert_id; $pids[] = $id;
    if (stripos($fila['client_email'], $marca) === 0) { $emails[] = $fila['client_email']; } // sólo correos propios: el borrado final no toca bajas ajenas
    return ['id' => $id, 'email' => $fila['client_email'], 'n' => $n];
}
function rt_det_evento(int $propuesta, string $tipo, array $c = []): void {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'automatiza_propuestas_details', $c + ['propuesta_id' => $propuesta, 'detail_type' => $tipo, 'title' => '[PRUEBA] ' . $tipo, 'status' => 'completed', 'created_at' => rt_det_dia(30)]);
}
function rt_det_crm(string $email, string $tipo, string $telefono = ''): int {
    global $wpdb, $crm_ids;
    $wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA]', 'email' => $email, 'tipo' => $tipo, 'telefono' => $telefono]);
    return $crm_ids[] = (int) $wpdb->insert_id;
}
function rt_det_seguimiento(int $propuesta): ?array {
    global $wpdb;
    $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE propuesta_id = %d ORDER BY id LIMIT 1", $propuesta));
    return $id > 0 ? at_rt_obtener($id) : null;
}
function rt_det_cuantos(array $propuestas): int {
    global $wpdb;
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}at_retomar WHERE propuesta_id IN (" . implode(',', array_map('intval', $propuestas ?: [0])) . ')');
}

// --- Propuestas de prueba -------------------------------------------------------------------------------
$v3 = rt_det_prop();                                                       // sent v3 de hace 20 días -> activo
$sin_flujo = rt_det_prop(['flujo' => null]);                               // sent sin flujo -> espera_propuesta
$antiguo = rt_det_prop(['flujo' => 'v2']);                                 // sent con otro flujo -> espera_propuesta
$catorce = rt_det_prop(['created_at' => rt_det_dia(14)]);                  // justo 14 días -> entra
$trece = rt_det_prop(['created_at' => rt_det_dia(13)]);                    // 13 días -> no
$reciente = rt_det_prop(['created_at' => rt_det_dia(5)]);                  // hace 5 días -> no
$aceptada = rt_det_prop(['status' => 'aceptada']);
$archivada = rt_det_prop(['status' => 'archivada']);
$rechazada = rt_det_prop(['status' => 'rechazada']);
$cliente = rt_det_prop(); $crm_cliente = rt_det_crm($cliente['email'], 'cliente');
$baja = rt_det_prop(); $wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => $baja['email'], 'telefono' => '', 'origen' => $origen_baja, 'creado_at' => current_time('mysql')]);
$baja_tel = rt_det_prop(['phone' => '+56900990099']);
$wpdb->insert($wpdb->prefix . 'at_retomar_bajas', ['email' => '', 'telefono' => $tel_baja, 'origen' => $origen_baja, 'creado_at' => current_time('mysql')]);
$prospecto = rt_det_prop(['status' => 'evaluando', 'phone' => '']); $crm_prospecto = rt_det_crm($prospecto['email'], 'prospecto', '+56900000007');
$ficha_ajena = rt_det_prop(['phone' => '']); $crm_cerrado = rt_det_crm($ficha_ajena['email'], 'cerrado', '+56900000008');
$ev_reciente = rt_det_prop(['created_at' => rt_det_dia(40)]);              // el envío registrado en el historial es de hace 5 días -> no
rt_det_evento($ev_reciente['id'], 'propuesta_enviada', ['completed_date' => substr(rt_det_dia(5), 0, 10)]);
rt_det_evento($ev_reciente['id'], 'propuesta_enviada', ['completed_date' => substr(rt_det_dia(35), 0, 10)]);
rt_det_evento($ev_reciente['id'], 'email', ['completed_date' => substr(rt_det_dia(60), 0, 10)]);
$ev_viejo = rt_det_prop(['created_at' => rt_det_dia(3)]);                  // el registro de envío es de hace 25 días -> entra
rt_det_evento($ev_viejo['id'], 'propuesta_enviada', ['completed_date' => substr(rt_det_dia(30), 0, 10)]);
rt_det_evento($ev_viejo['id'], 'propuesta_enviada', ['completed_date' => substr(rt_det_dia(25), 0, 10)]);
$mayusculas = rt_det_prop(['client_email' => strtoupper($marca) . '-MAYUS@EXAMPLE.COM']);   // sin seguimiento: entra, correo en minúsculas
$mayus_previo = rt_det_prop(['client_email' => strtoupper($marca) . '-PREVIO@EXAMPLE.COM']);
$previo = at_rt_crear(['nombre' => '[PRUEBA]', 'email' => strtolower($marca) . '-previo@example.com', 'estado' => 'baja']);
$sin_correo = rt_det_prop(['client_email' => '']);
$mal_correo = rt_det_prop(['client_email' => 'esto-no-es-un-correo']);
$gemela_a = rt_det_prop(); $gemela_b = rt_det_prop(['client_email' => $gemela_a['email']]);   // mismo correo en dos propuestas

// --- Fecha de envío ------------------------------------------------------------------------------------
$f_sin_evento = at_rt_fecha_envio_propuesta(['id' => $v3['id'], 'created_at' => rt_det_dia(20)]);
ok($f_sin_evento === substr(rt_det_dia(20), 0, 10) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $f_sin_evento) === 1, 'sin registro de envío: la fecha de creación, en Y-m-d estricto');
ok(at_rt_fecha_envio_propuesta(['id' => $ev_reciente['id'], 'created_at' => rt_det_dia(40)]) === substr(rt_det_dia(5), 0, 10), 'con registros de envío: el más reciente (y un correo común no cuenta)');
$ev_creado = rt_det_prop(['status' => 'draft']); rt_det_evento($ev_creado['id'], 'propuesta_enviada', ['created_at' => rt_det_dia(12)]);
ok(at_rt_fecha_envio_propuesta(['id' => $ev_creado['id'], 'created_at' => rt_det_dia(40)]) === substr(rt_det_dia(12), 0, 10), 'un registro sin fechas propias usa el día en que se anotó');
$ev_programado = rt_det_prop(['status' => 'draft']); rt_det_evento($ev_programado['id'], 'propuesta_enviada', ['scheduled_date' => substr(rt_det_dia(9), 0, 10)]);
ok(at_rt_fecha_envio_propuesta(['id' => $ev_programado['id'], 'created_at' => rt_det_dia(40)]) === substr(rt_det_dia(9), 0, 10), 'prioridad completed > scheduled > created, como la línea de tiempo');
ok(at_rt_fecha_envio_propuesta(['id' => 0, 'created_at' => '0000-00-00 00:00:00']) === '' && at_rt_fecha_envio_propuesta(['id' => 0]) === '', 'sin fecha válida devuelve vacío (la regla lo trata como reciente)');

// --- Con el esquema sin listo, no hace nada -----------------------------------------------------------
update_option('at_rt_schema', '1', false);
$vacio = at_rt_detectar($ahora);
update_option('at_rt_schema', $esquema_original, false);
ok($vacio === ['creados' => [], 'espera' => []] && rt_det_cuantos($pids) === 0, 'esquema no listo: retorno anticipado sin crear nada');
ok(at_rt_detectar('no es una fecha') === ['creados' => [], 'espera' => []] && rt_det_cuantos($pids) === 0, 'fecha ilegible: no crea nada');

// --- Candado: otra pasada en curso ----------------------------------------------------------------------
$otra = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$tomado = (string) $otra->get_var("SELECT GET_LOCK('{$wpdb->prefix}at_rt_detectar', 0)");
$ocupado = at_rt_detectar($ahora);
$otra->get_var("SELECT RELEASE_LOCK('{$wpdb->prefix}at_rt_detectar')");
ok($tomado === '1' && $ocupado === ['creados' => [], 'espera' => []] && rt_det_cuantos($pids) === 0, 'otra pasada en curso: no se duplica ni se crea nada');

// --- Primera pasada --------------------------------------------------------------------------------------
$r1 = at_rt_detectar($ahora);
$s_v3 = rt_det_seguimiento($v3['id']);
ok($s_v3 !== null && $s_v3['estado'] === 'activo' && $s_v3['propuesta_id'] === $v3['id'], 'sent v3 de hace 20 días: seguimiento activo con propuesta_id');
ok($s_v3['activado_at'] === $ahora && $s_v3['proximo_at'] === $hoy && $s_v3['wa_autorizado'] === 0 && $s_v3['canales'] === 'correo,whatsapp', 'activado ahora, próximo hoy, sin WhatsApp autorizado y con los dos canales');
ok($s_v3['nombre'] === '[PRUEBA] Cliente ' . $v3['n'] && $s_v3['negocio'] === '[PRUEBA] Negocio ' . $v3['n'] && $s_v3['email'] === $v3['email'] && $s_v3['telefono'] === at_pt_telefono_wa('+56900000000'), 'nombre, negocio, correo y teléfono vienen de la propuesta');
ok($s_v3['render'] === ['estado' => 'pendiente', 'nota' => 'Esperando redacción'] && $s_v3['crm_cliente_id'] === null, 'render pendiente y sin ficha CRM');
ok(preg_match('/^[a-f0-9]{16}$/D', (string) ($s_v3['redaccion']['generacion'] ?? '')) === 1 && in_array($s_v3['redaccion']['estado'] ?? '', ['pendiente', 'error'], true), 'se pidió la redacción (n8n bloqueado en pruebas: queda pendiente o en error)');
$s_sf = rt_det_seguimiento($sin_flujo['id']); $s_ant = rt_det_seguimiento($antiguo['id']);
ok($s_sf !== null && $s_sf['estado'] === 'espera_propuesta' && $s_sf['redaccion'] === [] && $s_sf['proximo_at'] === $hoy && $s_sf['activado_at'] === $ahora, 'sent sin flujo: espera_propuesta y sin pedir redacción');
ok($s_ant !== null && $s_ant['estado'] === 'espera_propuesta' && $s_ant['redaccion'] === [], 'sent con otro flujo (v2): también espera_propuesta');
ok($r1['creados'] === array_values(array_unique($r1['creados'])) && in_array($s_v3['id'], $r1['creados'], true) && !in_array($s_v3['id'], $r1['espera'], true), 'creados lleva al activo');
ok(in_array($s_sf['id'], $r1['espera'], true) && in_array($s_ant['id'], $r1['espera'], true) && !in_array($s_sf['id'], $r1['creados'], true), 'espera lleva a los que esperan propuesta (sin repetirse en creados)');
ok(rt_det_seguimiento($catorce['id'])['estado'] === 'activo' && rt_det_seguimiento($trece['id']) === null, '14 días exactos entran; 13 días no');
ok(rt_det_seguimiento($reciente['id']) === null, 'enviada hace 5 días: no entra');
ok(rt_det_seguimiento($aceptada['id']) === null && rt_det_seguimiento($archivada['id']) === null && rt_det_seguimiento($rechazada['id']) === null, 'aceptada, archivada y rechazada: no entran');
ok(rt_det_seguimiento($cliente['id']) === null, 'correo de una ficha cliente: no entra');
ok(rt_det_seguimiento($baja['id']) === null && rt_det_seguimiento($baja_tel['id']) === null, 'correo o teléfono en bajas: no entra');
ok(rt_det_seguimiento($ev_reciente['id']) === null, 'la propuesta con envío registrado hace 5 días no entra aunque se creó hace 40');
$s_evv = rt_det_seguimiento($ev_viejo['id']);
ok($s_evv !== null && $s_evv['estado'] === 'activo', 'el registro de envío de hace 25 días manda sobre la fecha de creación (hace 3)');
$s_pro = rt_det_seguimiento($prospecto['id']);
ok($s_pro !== null && $s_pro['crm_cliente_id'] === $crm_prospecto && $s_pro['telefono'] === at_pt_telefono_wa('+56900000007') && $s_pro['estado'] === 'activo', 'ficha CRM prospecto: se vincula y aporta el teléfono que falta (evaluando también entra)');
$h = $wpdb->get_results($wpdb->prepare("SELECT tipo_evento, titulo, descripcion FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $crm_prospecto), ARRAY_A);
ok(count($h) === 1 && $h[0]['tipo_evento'] === 'retomar_detectado' && $h[0]['titulo'] === '🔁 Entró a Retomar' && $h[0]['descripcion'] === 'Retomar #' . $s_pro['id'], 'historial CRM: retomar_detectado «🔁 Entró a Retomar»');
$s_aj = rt_det_seguimiento($ficha_ajena['id']);
ok($s_aj !== null && $s_aj['crm_cliente_id'] === null && $s_aj['telefono'] === '', 'una ficha que no es prospecto (cerrado) no se vincula ni aporta teléfono');
$s_may = rt_det_seguimiento($mayusculas['id']);
ok($s_may !== null && $s_may['email'] === strtolower($mayusculas['email']), 'correo con mayúsculas: el seguimiento queda en minúsculas');
ok(rt_det_seguimiento($mayus_previo['id']) === null && $previo > 0, 'ya hay un seguimiento (en cualquier estado) con ese correo, aunque con otras mayúsculas: no entra');
$s_ga = rt_det_seguimiento($gemela_a['id']);
ok($s_ga !== null && rt_det_seguimiento($gemela_b['id']) === null, 'dos propuestas con el mismo correo en la misma pasada: un solo seguimiento (el de la primera)');
ok(rt_det_seguimiento($sin_correo['id']) === null && rt_det_seguimiento($mal_correo['id']) === null, 'sin correo o con correo inválido: no entra');
$esperados_creados = [$s_v3['id'], rt_det_seguimiento($catorce['id'])['id'], $s_evv['id'], $s_pro['id'], $s_aj['id'], $s_may['id'], $s_ga['id']];
$esperados_espera = [$s_sf['id'], $s_ant['id']];
sort($esperados_creados); $creados = $r1['creados']; sort($creados); $espera = $r1['espera']; sort($espera);
ok($creados === $esperados_creados && $espera === $esperados_espera, 'el resultado lista exactamente los seguimientos nuevos');

// --- Segunda pasada: nada nuevo ------------------------------------------------------------------------
$antes = rt_det_cuantos($pids);
$r2 = at_rt_detectar($ahora);
ok($r2 === ['creados' => [], 'espera' => []] && rt_det_cuantos($pids) === $antes && $antes === count($esperados_creados) + count($esperados_espera), 'correr dos veces no duplica nada');
$r3 = at_rt_detectar('2026-10-20 09:00:00');
$s_trece = rt_det_seguimiento($trece['id']);
ok($s_trece !== null && $r3 === ['creados' => [$s_trece['id']], 'espera' => []] && $s_trece['activado_at'] === '2026-10-20 09:00:00' && $s_trece['proximo_at'] === '2026-10-20', 'al día siguiente la de 13 días ya cumple 14 y entra, con la fecha de esa pasada');
ok(rt_det_seguimiento($reciente['id']) === null && rt_det_cuantos($pids) === $antes + 1, 'la de 5 días sigue fuera y nada más se duplica');
fin();
