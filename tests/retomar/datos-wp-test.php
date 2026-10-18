<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_crear', 'at_rt_obtener', 'at_rt_por_token', 'at_rt_guardar', 'at_rt_lista', 'at_rt_agregar_envio', 'at_rt_en_bajas', 'at_rt_dar_baja', 'at_rt_revertir_baja', 'at_rt_contexto_parada');
at_rt_migrar_esquema();
global $wpdb;
$marca = 'prueba-rt-' . strtolower(wp_generate_password(12, false, false));
$correo = $marca . '@example.com';
$filas = [];
$extras = [];
$bajas = [];
register_shutdown_function(static function () use (&$filas, &$extras, &$bajas, $wpdb) {
	foreach ($filas as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
	foreach ($bajas as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar_bajas', ['id' => $id]); }
	foreach ($extras as [$tabla, $id]) { $wpdb->delete($tabla, ['id' => $id]); }
});
function rt_fixture(array $campos = []): int {
	global $filas, $correo;
	$id = at_rt_crear($campos + ['nombre' => '[PRUEBA]', 'negocio' => '[PRUEBA]', 'email' => $correo]);
	if ($id) { $filas[] = $id; }
	return $id;
}
$id = rt_fixture(['email' => strtoupper($correo), 'telefono' => '+569 0000 0000', 'intervalo_dias' => 3, 'max_envios' => 9, 'demo_urls' => ['https://example.com/demo'], 'textos' => ['quedamos' => '[PRUEBA]']]);
$s = at_rt_obtener($id);
ok($id > 0 && $s['intervalo_dias'] === 7 && $s['max_envios'] === 4, 'crear limita intervalo y máximo');
ok($s['email'] === $correo && $s['telefono'] === '56900000000', 'normaliza correo y teléfono');
ok($s['demo_urls'] === ['https://example.com/demo'] && $s['textos'] === ['quedamos' => '[PRUEBA]'] && $s['envios'] === [] && $s['render'] === [], 'JSON vuelve como arrays');
ok(at_rt_por_token($s['token'])['id'] === $id && at_rt_token_valido($s['token'], $id, wp_salt('auth')), 'token HMAC corresponde al id y permite lectura');
ok(at_rt_por_token(strtoupper($s['token'])) === null && at_rt_por_token('') === null, 'token exige coincidencia exacta');
ok(at_rt_obtener(0) === null && !at_rt_guardar(PHP_INT_MAX, ['nombre' => '[PRUEBA]']), 'lectura y escritura de id inexistente');
ok(at_rt_guardar($id, ['id' => 1, 'token' => 'cambiado', 'creado_at' => '2000-01-01 00:00:00', 'desconocido' => 'x', 'max_envios' => 0, 'intervalo_dias' => 3, 'estado' => 'activo', 'activado_at' => '2026-10-18 10:00:00']), 'guardar whitelist permite campos válidos');
$editado = at_rt_obtener($id);
ok($editado['token'] === $s['token'] && $editado['creado_at'] === $s['creado_at'] && $editado['id'] === $id && $editado['max_envios'] === 1 && $editado['intervalo_dias'] === 7, 'guardar protege identidad, token y fecha de creación');
ok(at_rt_guardar($id, ['estado' => 'inventado']) === false && at_rt_obtener($id)['estado'] === 'activo', 'rechaza estado fuera del contrato');
ok(at_rt_agregar_envio($id, ['tipo' => 'envio', 'n' => 1, 'fecha' => '2026-10-18', 'correo' => ['estado' => 'enviado'], 'whatsapp' => null]) && at_rt_agregar_envio($id, ['tipo' => 'envio', 'n' => 2, 'correo' => ['estado' => 'enviado']]), 'agrega eventos');
ok(count(at_rt_obtener($id)['envios']) === 2 && !at_rt_agregar_envio(PHP_INT_MAX, ['n' => 1]), 'conserva eventos y falla para id ausente');
at_rt_agregar_envio($id, ['tipo' => 'diferido', 'fecha' => '2026-10-19', 'nota' => '[PRUEBA]']);
at_rt_agregar_envio($id, ['tipo' => 'detenido', 'motivo' => 'baja']);
at_rt_agregar_envio($id, ['tipo' => 'envio', 'n' => 3, 'correo' => ['estado' => 'error']]);
ok(at_rt_envios_realizados(at_rt_obtener($id)['envios']) === 2, 'diferidos, detenciones y errores no consumen recordatorios');
ok(in_array($id, array_column(at_rt_lista(), 'id'), true), 'lista contiene seguimiento creado');
$otro = rt_fixture(['telefono' => '', 'estado' => 'activo']);
$por_tel = rt_fixture(['email' => 'telefono-' . $correo, 'telefono' => '', 'estado' => 'pausado']);
$wpdb->update($wpdb->prefix . 'at_retomar', ['telefono' => '+56 (9) 0000-0000'], ['id' => $por_tel]);
$ajeno = rt_fixture(['email' => 'ajeno-' . $correo, 'telefono' => '', 'estado' => 'activo']);
ok(at_rt_dar_baja($id, 'prueba'), 'da baja por persona');
$baja_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar_bajas WHERE email = %s ORDER BY id DESC LIMIT 1", $correo));
$bajas[] = $baja_id;
ok(at_rt_obtener($id)['estado'] === 'baja' && at_rt_obtener($otro)['estado'] === 'baja' && at_rt_obtener($por_tel)['estado'] === 'baja' && at_rt_obtener($ajeno)['estado'] === 'activo', 'propaga correo O teléfono incluso formato legado sin afectar ajeno');
ok(at_rt_en_bajas(strtoupper($correo), '') && at_rt_en_bajas('', '9 0000 0000') && !at_rt_en_bajas('', '') && !at_rt_en_bajas('ajeno-' . $correo, 'inválido'), 'consulta baja normalizada por cualquiera de los identificadores');
ok(at_rt_dar_baja($id, 'prueba'), 'baja repetida es idempotente');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}at_retomar_bajas WHERE email = %s AND revertido_at IS NULL", $correo)) === 1, 'baja repetida conserva una supresión activa');
ok(!at_rt_revertir_baja($baja_id, '  ') && at_rt_en_bajas($correo, ''), 'revertir requiere motivo');
ok(at_rt_revertir_baja($baja_id, '[PRUEBA] Corrección') && !at_rt_en_bajas($correo, '56900000000'), 'revertir ignora supresión revertida');
ok(at_rt_obtener($otro)['estado'] === 'baja' && $wpdb->get_var($wpdb->prepare("SELECT motivo FROM {$wpdb->prefix}at_retomar_bajas WHERE id = %d", $baja_id)) === '[PRUEBA] Corrección', 'revertir anota motivo sin reactivar seguimientos');
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA]', 'email' => $correo, 'empresa' => '[PRUEBA]', 'tipo' => 'cliente', 'estado' => 'contratado', 'origen' => 'prueba_retomar']);
$crm = (int) $wpdb->insert_id;
$extras[] = [$wpdb->prefix . 'crm_clientes', $crm];
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $correo, 'client_name' => '[PRUEBA]', 'company_name' => '[PRUEBA]', 'unique_link_id' => substr(md5($marca), 0, 12), 'status' => 'rechazada', 'flujo' => 'v3', 'gamma_prompt_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$prop = (int) $wpdb->insert_id;
$extras[] = [$wpdb->prefix . 'automatiza_propuestas', $prop];
ok($crm > 0 && $prop > 0, 'fixtures CRM y propuesta disponibles');
at_rt_guardar($id, ['crm_cliente_id' => $crm, 'propuesta_id' => $prop]);
$c = at_rt_contexto_parada(at_rt_obtener($id));
ok($c['crm_tipo'] === 'cliente' && $c['propuesta_estado'] === 'rechazada' && $c['enviados'] === 2 && $c['max'] === 1 && !$c['baja'], 'contexto lee tipo CRM, status propuesta y envíos');
foreach (['2026-10-18 09:59:59', '2026-10-18 10:00:00', '2026-10-18 10:00:01'] as $i => $fecha) {
	$wpdb->insert($wpdb->prefix . 'automatiza_followup_meetings', ['client_name' => '[PRUEBA]', 'client_email' => $correo, 'meeting_date' => '2026-12-01', 'meeting_time' => '10:00:00', 'status' => 'scheduled', 'created_at' => $fecha]);
	$reunion = (int) $wpdb->insert_id;
	$extras[] = [$wpdb->prefix . 'automatiza_followup_meetings', $reunion];
	ok($reunion > 0 && at_rt_contexto_parada(at_rt_obtener($id))['reunion'] === ($i === 2), 'reunión cuenta solo estrictamente posterior: caso ' . $i);
}
at_rt_guardar($id, ['activado_at' => null]);
ok(!at_rt_contexto_parada(at_rt_obtener($id))['reunion'], 'sin activación no atribuye reuniones');
ok(!at_rt_dar_baja(PHP_INT_MAX, 'prueba') && !at_rt_revertir_baja(PHP_INT_MAX, '[PRUEBA]'), 'bajas inexistentes fallan');
fin();
