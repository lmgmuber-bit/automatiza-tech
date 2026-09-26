<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/clientes-wp-test.php   (WordPress local de prueba)
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
$crm = $wpdb->prefix . 'crm_clientes';
$tech = $wpdb->prefix . 'automatiza_tech_clients';

at_cc_migrar_esquema();
ok(in_array('crm_cliente_id', $wpdb->get_col("SHOW COLUMNS FROM {$tech}"), true), 'columna crm_cliente_id creada');
ok(get_option('at_cierre_schema') === '1', 'esquema marcado');

// Hallazgo T3 ronda 1: si el ALTER TABLE falla (tabla bloqueada, sin privilegio ALTER en
// hosting compartido, etc.), el esquema NO debe marcarse como migrado, para que se reintente
// más adelante. Lo simulamos bloqueando la tabla desde una segunda conexión MySQL (medido:
// SHOW COLUMNS no se bloquea con LOCK TABLES ... WRITE de otra sesión, pero ALTER TABLE sí
// espera el metadata lock y falla al vencer lock_wait_timeout).
//
// Hallazgo T3 ronda 2, punto 1: el mensaje registrado debe llevar el error REAL de MySQL, no
// quedar vacío porque el SHOW COLUMNS de verificación (una consulta exitosa) corre entre el
// ALTER fallido y la lectura de $wpdb->last_error. Se comprueba capturando error_log() en un
// archivo temporal propio (fuera del repo) y revisando que el mensaje no termine en ": ".
//
// Hallazgo T3 ronda 2, punto 2: mientras la migración siga fallando, no debe reintentar el
// ALTER en cada llamada sin límite: debe haber un enfriamiento (transient) entre reintentos.
// Se comprueba liberando la tabla pero dejando el enfriamiento activo: el esquema debe seguir
// sin migrarse; solo al "expirar" el enfriamiento (simulado con delete_transient) se reintenta.
delete_option('at_cierre_schema');
delete_transient('at_cc_migrar_intento');
$wpdb->query("ALTER TABLE {$tech} DROP COLUMN crm_cliente_id");
$host_candado = DB_HOST;
$puerto_candado = 3306;
if (strpos($host_candado, ':') !== false) {
	[$host_candado, $puerto_candado] = explode(':', $host_candado, 2);
	$puerto_candado = (int) $puerto_candado;
}
$candado = mysqli_init();
$con_candado = $candado ? @mysqli_real_connect($candado, $host_candado, DB_USER, DB_PASSWORD, DB_NAME, $puerto_candado) : false;
if ($con_candado && mysqli_query($candado, "LOCK TABLES {$tech} WRITE")) {
	$log_temporal = sys_get_temp_dir() . '/at_cc_test_error_' . getmypid() . '.log';
	@unlink($log_temporal);
	$error_log_previo = ini_get('error_log');
	ini_set('error_log', $log_temporal);

	$wpdb->query('SET SESSION lock_wait_timeout = 2');
	at_cc_migrar_esquema();
	ok(get_option('at_cierre_schema') !== '1', 'ALTER bloqueado: el esquema NO se marca como migrado');
	ok(!in_array('crm_cliente_id', $wpdb->get_col("SHOW COLUMNS FROM {$tech}"), true), 'ALTER bloqueado: la columna sigue sin crearse');
	ok((bool) get_transient('at_cc_migrar_intento'), 'ALTER bloqueado: se activa el enfriamiento de reintento');

	$contenido_log = @file_get_contents($log_temporal) ?: '';
	ini_set('error_log', $error_log_previo);
	@unlink($log_temporal);
	ok((bool) preg_match('/at_cc: no se pudo agregar crm_cliente_id a \S+: .+/', $contenido_log), 'el error registrado lleva el mensaje real de MySQL, no queda vacío');

	mysqli_query($candado, 'UNLOCK TABLES');
	mysqli_close($candado);
	$wpdb->query('SET SESSION lock_wait_timeout = DEFAULT');

	// La tabla ya está libre, pero el enfriamiento sigue activo: no debe reintentar el ALTER.
	at_cc_migrar_esquema();
	ok(get_option('at_cierre_schema') !== '1', 'con enfriamiento activo: el esquema sigue sin marcarse aunque la tabla ya esté libre');
	ok(!in_array('crm_cliente_id', $wpdb->get_col("SHOW COLUMNS FROM {$tech}"), true), 'con enfriamiento activo: la columna sigue sin crearse');

	// Se simula que el enfriamiento expiró (en producción, a los 5 minutos) y se reintenta.
	delete_transient('at_cc_migrar_intento');
	at_cc_migrar_esquema();
	ok(get_option('at_cierre_schema') === '1', 'enfriamiento expirado: el esquema se marca al fin');
	ok(in_array('crm_cliente_id', $wpdb->get_col("SHOW COLUMNS FROM {$tech}"), true), 'enfriamiento expirado: la columna queda creada');
} else {
	echo "AVISO: no se pudo abrir una segunda conexión MySQL para simular el bloqueo de la tabla; se omite la prueba de los hallazgos T3 ronda 2 (error real y enfriamiento de reintento).\n";
	at_cc_migrar_esquema();
}

$marca = 'prueba-cierre-' . strtolower(wp_generate_password(6, false, false));
$email = $marca . '@example.com';

$r = at_cc_asegurar_cliente(['nombre' => 'Prueba Cierre', 'email' => '  ' . strtoupper($email) . ' ', 'empresa' => '[PRUEBA] Empresa', 'telefono' => '+56 9 1111 1111', 'valor' => 1000000, 'servicios' => 'Fase 1']);
ok(is_array($r) && $r['crm_id'] > 0 && $r['tech_id'] > 0, 'cliente nuevo en las dos listas');
$c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$crm} WHERE id = %d", $r['crm_id']));
ok($c && $c->tipo === 'cliente' && $c->estado === 'contratado' && $c->email === $email, 'CRM: cliente contratado con el correo normalizado');
$t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tech} WHERE id = %d", $r['tech_id']));
ok($t && (int) $t->crm_cliente_id === $r['crm_id'] && (float) $t->contract_value === 1000000.0, 'ficha operativa enlazada y con su valor');

$r2 = at_cc_asegurar_cliente(['nombre' => 'Otro', 'email' => $email]);
ok($r2 === $r, 'la segunda llamada devuelve los mismos ids');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$crm} WHERE email = %s", $email)) === 1, 'sin duplicado en el CRM');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tech} WHERE email = %s", $email)) === 1, 'sin duplicado en la ficha operativa');

$email3 = $marca . '-3@example.com';
$wpdb->insert($crm, ['nombre' => 'Prospecto', 'email' => $email3, 'tipo' => 'prospecto', 'estado' => 'nuevo']);
$pid = (int) $wpdb->insert_id;
$r3 = at_cc_asegurar_cliente(['nombre' => 'Prospecto', 'email' => $email3, 'fecha_contrato' => '2026-09-20 10:00:00']);
$c3 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$crm} WHERE id = %d", $pid));
ok($r3['crm_id'] === $pid && $c3->tipo === 'cliente' && $c3->fecha_contrato === '2026-09-20 10:00:00', 'prospecto existente pasa a cliente con la fecha dada');
at_cc_asegurar_cliente(['email' => $email3, 'fecha_contrato' => '2026-09-25 10:00:00']);
ok($wpdb->get_var($wpdb->prepare("SELECT fecha_contrato FROM {$crm} WHERE id = %d", $pid)) === '2026-09-20 10:00:00', 'la fecha de un cliente existente no se pisa');

$email5 = $marca . '-5@example.com';
$wpdb->insert($tech, ['name' => 'Solo ficha', 'email' => $email5, 'contract_status' => 'active']);
$tid5 = (int) $wpdb->insert_id;
$r5 = at_cc_asegurar_cliente(['nombre' => 'Solo ficha', 'email' => $email5]);
ok($r5['tech_id'] === $tid5 && (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$tech} WHERE id = %d", $tid5)) === $r5['crm_id'], 'ficha operativa existente se enlaza sin duplicar');

ok(is_wp_error(at_cc_asegurar_cliente(['email' => 'no-es-correo'])), 'correo inválido devuelve error');
ok((int) at_cc_tech_de_crm($r['crm_id'])->id === $r['tech_id'], 'at_cc_tech_de_crm encuentra la ficha');
ok(at_cc_tech_de_crm(0) === null, 'sin id no hay ficha');
ok(at_cc_crm_de_email(strtoupper($email)) === $r['crm_id'] && at_cc_crm_de_email('nadie@example.com') === 0, 'at_cc_crm_de_email');

$wpdb->query($wpdb->prepare("DELETE FROM {$tech} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$crm} WHERE email LIKE %s", $wpdb->esc_like($marca) . '%'));
fin();
