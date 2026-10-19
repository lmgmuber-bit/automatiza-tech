<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_crear', 'at_rt_campos', 'at_rt_config', 'at_rt_obtener', 'at_rt_por_token', 'at_rt_guardar', 'at_rt_lista', 'at_rt_agregar_envio', 'at_rt_en_bajas', 'at_rt_dar_baja', 'at_rt_revertir_baja', 'at_rt_contexto_parada');
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
// Fase 2: textos, redacción, aprobación y fotos.
ok($s['notas'] === '' && $s['redaccion'] === [] && $s['aprobacion'] === [] && $s['fotos'] === [], 'crear sin campos nuevos deja notas vacías y arrays vacíos');
$fotos = ['portada' => ['url' => 'https://x/a.jpg', 'hash' => 'h']];
$id2 = rt_fixture(['redaccion' => ['estado' => 'lista'], 'aprobacion' => ['estado' => 'por_aprobar', 'n' => 1], 'fotos' => $fotos, 'notas' => 'nota']);
$s2 = at_rt_obtener($id2);
ok($id2 > 0 && $s2['redaccion'] === ['estado' => 'lista'] && $s2['aprobacion'] === ['estado' => 'por_aprobar', 'n' => 1] && $s2['fotos'] === $fotos && $s2['notas'] === 'nota', 'crear y obtener devuelven los mismos arrays y la nota');
ok(at_rt_guardar($id2, ['notas' => "Primera línea\nSegunda línea", 'aprobacion' => ['estado' => 'aprobado', 'n' => 2, 'usada' => false], 'fotos' => []]), 'guardar acepta notas y JSON nuevos');
$s2 = at_rt_obtener($id2);
ok($s2['notas'] === "Primera línea\nSegunda línea" && $s2['aprobacion']['estado'] === 'aprobado' && $s2['aprobacion']['usada'] === false && $s2['fotos'] === [] && $s2['redaccion'] === ['estado' => 'lista'], 'guardar cambia solo lo enviado y conserva los saltos de línea de la nota');
ok(at_rt_guardar($id2, ['notas' => '<b>sin</b> etiquetas']) && at_rt_obtener($id2)['notas'] === 'sin etiquetas', 'la nota se limpia de etiquetas');
foreach (['redaccion', 'aprobacion', 'fotos'] as $k) {
	ok(at_rt_campos([$k => 'texto']) === null && !at_rt_guardar($id2, [$k => 'texto']), "{$k} rechaza un valor que no sea arreglo");
}
ok(at_rt_obtener($id2)['aprobacion']['estado'] === 'aprobado', 'un guardado rechazado no cambia la fila');
$wpdb->update($wpdb->prefix . 'at_retomar', ['redaccion' => '"suelto"', 'aprobacion' => 'no es json', 'fotos' => '', 'notas' => ''], ['id' => $id2]);
$s2 = at_rt_obtener($id2);
ok($s2['redaccion'] === [] && $s2['aprobacion'] === [] && $s2['fotos'] === [] && $s2['notas'] === '', 'JSON corrupto o escalar se lee como arreglo vacío');
ok(at_rt_campos(['estado' => 'espera_propuesta']) !== null && at_rt_campos(['estado' => 'inventado']) === null, 'espera_propuesta es un estado válido y un estado inventado no');
$id3 = rt_fixture(['estado' => 'espera_propuesta']);
ok($id3 > 0 && at_rt_obtener($id3)['estado'] === 'espera_propuesta', 'crear con estado espera_propuesta');

// I3: «ya es cliente» mira también cualquier ficha cliente con el mismo correo, aunque no esté vinculada al seguimiento.
$correo_sf = 'sinficha-' . $correo;
$sf = rt_fixture(['email' => $correo_sf, 'telefono' => '']);
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA]', 'email' => $correo_sf, 'empresa' => '[PRUEBA]', 'tipo' => 'prospecto', 'estado' => 'nuevo', 'origen' => 'prueba_retomar']);
$extras[] = [$wpdb->prefix . 'crm_clientes', $ficha_prospecto = (int) $wpdb->insert_id];
at_rt_guardar($sf, ['crm_cliente_id' => $ficha_prospecto]);
ok(at_rt_contexto_parada(at_rt_obtener($sf))['crm_tipo'] === 'prospecto', 'I3: ficha vinculada de prospecto y ninguna cliente: crm_tipo prospecto');
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA]', 'email' => '  ' . strtoupper($correo_sf) . ' ', 'empresa' => '[PRUEBA]', 'tipo' => 'cliente', 'estado' => 'contratado', 'origen' => 'prueba_retomar']);
$extras[] = [$wpdb->prefix . 'crm_clientes', (int) $wpdb->insert_id];
$c_sf = at_rt_contexto_parada(at_rt_obtener($sf));
ok($c_sf['crm_tipo'] === 'cliente' && at_rt_motivo_parada($c_sf) === 'cliente', 'I3: otra ficha cliente con el mismo correo (mayúsculas y espacios), no vinculada: el seguimiento ya es cliente');
at_rt_guardar($sf, ['crm_cliente_id' => null]);
ok(at_rt_contexto_parada(at_rt_obtener($sf))['crm_tipo'] === 'cliente', 'I3: sin ficha vinculada, la ficha cliente del mismo correo igual cuenta');
ok(at_rt_contexto_parada(at_rt_obtener($ajeno))['crm_tipo'] === '' && at_rt_contexto_parada(['email' => '', 'crm_cliente_id' => 0])['crm_tipo'] === '', 'I3: un correo sin ficha cliente, o vacío, no es cliente');

// Configuración del resumen semanal: valores fuera de rango vuelven al defecto.
$previas = [];
foreach (['at_rt_resumen_dia', 'at_rt_resumen_hora', 'at_rt_resumen_destino'] as $opcion) {
	$previas[$opcion] = get_option($opcion, null);
	delete_option($opcion);
}
register_shutdown_function(static function () use ($previas) {
	foreach ($previas as $opcion => $valor) {
		if ($valor === null) { delete_option($opcion); } else { update_option($opcion, $valor, false); }
	}
});
ok(at_rt_config() === ['dia' => 1, 'hora' => '09:00', 'destino' => at_cc_correo_avisos()], 'config sin opciones devuelve lunes 09:00 y el correo de avisos');
foreach (['9' => 1, '0' => 1, '8' => 1, 'x' => 1, '1abc' => 1, '' => 1, '2' => 2, '7' => 7] as $dia => $esperado) {
	update_option('at_rt_resumen_dia', (string) $dia, false);
	ok(at_rt_config()['dia'] === $esperado, "día '{$dia}' da {$esperado}");
}
update_option('at_rt_resumen_dia', '1', false); // las horas se prueban un lunes: el domingo tiene su propio tope (abajo)
foreach (['25:00' => '09:00', '24:00' => '09:00', '9:00' => '09:00', '10:60' => '09:00', 'abc' => '09:00', '' => '09:00', '00:00' => '00:00', '14:30' => '14:30', '23:59' => '23:59'] as $hora => $esperada) {
	update_option('at_rt_resumen_hora', (string) $hora, false);
	ok(at_rt_config()['hora'] === $esperada, "hora '{$hora}' da {$esperada}");
}
// Domingo con una hora posterior a su última pasada (23:05) nunca saldría: la combinación vuelve al defecto.
update_option('at_rt_resumen_dia', '7', false);
foreach (['23:30' => [1, '09:00'], '23:06' => [1, '09:00'], '23:05' => [7, '23:05'], '20:00' => [7, '20:00']] as $hora => [$dia_esperado, $hora_esperada]) {
	update_option('at_rt_resumen_hora', $hora, false);
	$cfg = at_rt_config();
	ok($cfg['dia'] === $dia_esperado && $cfg['hora'] === $hora_esperada, "domingo {$hora} da día {$dia_esperado} a las {$hora_esperada}");
}
update_option('at_rt_resumen_dia', '6', false);
update_option('at_rt_resumen_hora', '23:30', false);
ok(at_rt_config()['dia'] === 6 && at_rt_config()['hora'] === '23:30', 'sábado 23:30 se conserva (el domingo lo recupera)');
update_option('at_rt_resumen_destino', 'esto no es un correo', false);
ok(at_rt_config()['destino'] === at_cc_correo_avisos(), 'destino inválido vuelve al correo de avisos');
update_option('at_rt_resumen_destino', '  rt-destino@example.com ', false);
ok(at_rt_config()['destino'] === 'rt-destino@example.com', 'destino válido se usa, sin espacios');
update_option('at_rt_resumen_destino', '', false);
ok(at_rt_config()['destino'] === at_cc_correo_avisos(), 'destino vacío usa el correo de avisos');
fin();
