<?php
// La reunión de seguimiento queda ligada a su contrato o propuesta. Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/followup-vinculo-wp-test.php
// El WordPress local ejecuta la copia de PROD de admin-followup-meetings.php: aquí se prueba el módulo de ESTA copia del repo
// (migración y vínculo) y, aparte, que el archivo de la rama tenga la llamada en la API.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
global $wpdb;
$wpdb->show_errors(false);
$t = $wpdb->prefix . 'automatiza_followup_meetings';
$reuniones = [];
register_shutdown_function(function () use (&$reuniones, $t) { global $wpdb; foreach ($reuniones as $id) { $wpdb->delete($t, ['id' => $id]); } });

// 1) Migración idempotente
$wpdb->last_error = '';
at_wa_followup_migrar_columnas();
at_wa_followup_migrar_columnas();
ok($wpdb->last_error === '', 'la migración corre dos veces sin error');
$cols = $wpdb->get_col("SHOW COLUMNS FROM $t", 0);
ok(in_array('contrato_id', $cols, true) && in_array('propuesta_id', $cols, true), 'existen las columnas contrato_id y propuesta_id');
$idx = $wpdb->get_col("SHOW INDEX FROM $t", 2);
ok(in_array('idx_contrato_id', $idx, true) && in_array('idx_propuesta_id', $idx, true), 'existen sus índices');

// Datos de un cliente con un contrato firmado y su plan, y de otro cliente
$n = wa_numero_libre();
[$crm, $tech] = wa_cliente_crm('f', wa_con_espacios($n));
$k = wa_contrato($tech, 'servicios', 'signed', true);
$codigo = wa_plan($k, 'Proyecto Prueba Vínculo');
[$crm2, $tech2] = wa_cliente_crm('g', wa_con_espacios(wa_numero_libre()));
$ajeno = wa_contrato($tech2, 'servicios', 'signed', true);
$pr = wa_propuesta(wa_correo('f'), '', 'sent', '[PRUEBA] Propuesta vínculo');

$base = function (string $nota = '') use ($n) {
	return ['client_name' => 'Julio Prueba', 'client_email' => wa_correo('f'), 'phone' => '56' . $n, 'company_name' => '', 'meeting_date' => wp_date('Y-m-d', strtotime('+400 days')),
		'meeting_time' => '10:00:00', 'meeting_subject' => 'Seguimiento: prueba', 'notes' => $nota !== '' ? $nota : 'Agendada desde whatsapp', 'status' => 'scheduled', 'created_at' => current_time('mysql'),
		'meet_link' => 'https://meet.google.com/prueba-wa', 'google_event_id' => 'evento-prueba-wa'];
};
$pedido = function (array $params) { $r = new WP_REST_Request('POST', '/automatiza-tech/v1/followup-meetings'); $r->set_body_params($params); return $r; };
$guardar = function (array $data) use (&$reuniones, $t) { global $wpdb; $ok = $wpdb->insert($t, $data); if ($ok) { $reuniones[] = (int) $wpdb->insert_id; } return $ok ? (int) $wpdb->insert_id : 0; };

// 2) Contrato del mismo cliente
$d = at_wa_followup_datos_con_vinculo($base('Plan de trabajo ' . $codigo . '. Agendada por el cliente por WhatsApp.'), $pedido(['contrato_id' => (string) $k]));
$id = $guardar($d);
ok($id > 0 && (int) $wpdb->get_var($wpdb->prepare("SELECT contrato_id FROM $t WHERE id = %d", $id)) === $k, 'el contrato del mismo cliente queda guardado');
ok(($p = at_pt_seguimiento_pendiente($codigo)) && (int) $p->id === $id, 'la nota «Plan de trabajo <código>» se conserva y el plan ve la reunión');

// 3) Contrato de otro cliente: la reunión se guarda igual, sin vínculo
$d = at_wa_followup_datos_con_vinculo($base(), $pedido(['contrato_id' => (string) $ajeno, 'propuesta_id' => '999999999']));
$id = $guardar($d);
$fila = $wpdb->get_row($wpdb->prepare("SELECT contrato_id, propuesta_id FROM $t WHERE id = %d", $id));
ok($id > 0 && $fila->contrato_id === null && $fila->propuesta_id === null, 'un contrato o propuesta de otro cliente se descarta y la reunión se guarda');

// 4) Propuesta del mismo cliente
$d = at_wa_followup_datos_con_vinculo($base(), $pedido(['propuesta_id' => (string) $pr]));
ok(($d['propuesta_id'] ?? null) === $pr && array_key_exists('contrato_id', $d) && $d['contrato_id'] === null, 'la propuesta del mismo cliente se acepta');

// 5) Sin vínculo: los datos quedan como hoy
$sin = $base();
ok(at_wa_followup_datos_con_vinculo($sin, $pedido([])) === $sin, 'sin vínculo, los datos no cambian');
ok(at_wa_followup_datos_con_vinculo($sin, $pedido(['contrato_id' => 'abc', 'propuesta_id' => '-3'])) === $sin, 'valores que no son ids positivos no cambian nada');

// 6) El archivo de la rama llama al módulo antes de guardar la reunión
$api = file_get_contents(__DIR__ . '/../../wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php');
$ini = strpos($api, 'function automatiza_tech_create_followup_meeting_api(');
$fin = strpos($api, "\nfunction ", $ini + 10);
$cuerpo = substr($api, $ini, $fin - $ini);
$llamada = strpos($cuerpo, "function_exists('at_wa_followup_datos_con_vinculo')");
ok($llamada !== false && $llamada < strpos($cuerpo, '$wpdb->insert($table_name, $data)'), 'la API de la rama aplica el vínculo antes del insert, con function_exists');
fin();
