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

// Hallazgo T4 ronda 1, punto 1: "pasar a contratado" en Contactos (move_to_clients) no debe crear
// una segunda ficha operativa sin enlazar cuando el cliente ya tiene una ficha por otro camino
// (CRM manual o propuesta aceptada). Se reproduce igual que en la revisión: primero una ficha
// vacía enlazada (T1, como la crea "Convertir a Cliente"), luego move_to_clients() (Contactos)
// para el mismo correo, con un plan real (T2, con plan_id y contract_value).
$marca4 = 'prueba-cierre-t4r1-' . strtolower(wp_generate_password(6, false, false));
$email4 = $marca4 . '@example.com';
$servicios = $wpdb->prefix . 'automatiza_services';
$contactos = $wpdb->prefix . 'automatiza_tech_contacts';

$wpdb->insert($servicios, ['name' => '[PRUEBA] Plan Cierre T4R1', 'price_clp' => 150000, 'status' => 'active'], ['%s', '%d', '%s']);
$plan_id4 = (int) $wpdb->insert_id;

$r1 = at_cc_asegurar_cliente(['nombre' => 'Cliente Ronda1', 'email' => $email4]);
ok(is_array($r1) && $r1['crm_id'] > 0 && $r1['tech_id'] > 0, 'T4R1: ficha T1 (vacía) creada por el puente, como al convertir a cliente en el CRM');

$wpdb->insert($contactos, [
    'name' => 'Cliente Ronda1',
    'email' => $email4,
    'company' => '[PRUEBA]',
    'phone' => '',
    'tax_id' => '11.111.111-1',
    'message' => 'prueba automatizada',
    'status' => 'new',
], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);
$contact_id4 = (int) $wpdb->insert_id;

$form4 = new AutomatizaTechContactForm();
$rm4 = new ReflectionMethod($form4, 'move_to_clients');
$rm4->setAccessible(true);
$ok_move4 = $rm4->invoke($form4, $contact_id4, (string) $plan_id4, false);
ok($ok_move4 === true, 'T4R1: move_to_clients() convierte el contacto');

$t2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tech} WHERE email = %s ORDER BY id DESC LIMIT 1", $email4));
ok($t2 && (int) $t2->plan_id === $plan_id4 && (float) $t2->contract_value === 150000.0, 'T4R1: T2 (Contactos) queda con el plan y el valor del contrato');
ok($t2 && (int) $t2->id !== (int) $r1['tech_id'], 'T4R1: T2 es una ficha distinta de T1 (se reproduce el escenario del hallazgo)');
ok($t2 && (int) $t2->crm_cliente_id === (int) $r1['crm_id'], 'T4R1: T2 queda enlazada al MISMO cliente del CRM que T1, no huérfana');

$ficha_pestana = at_cc_tech_de_crm((int) $r1['crm_id']);
ok($ficha_pestana && (int) $ficha_pestana->id === (int) $t2->id, 'T4R1: la pestaña "Contratos y operación" (at_cc_tech_de_crm) muestra la ficha con el contrato (T2), no la vacía (T1)');

$wpdb->query($wpdb->prepare("DELETE FROM {$tech} WHERE email LIKE %s", $wpdb->esc_like($marca4) . '%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$crm} WHERE email LIKE %s", $wpdb->esc_like($marca4) . '%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$contactos} WHERE email LIKE %s", $wpdb->esc_like($marca4) . '%'));
$wpdb->delete($servicios, ['id' => $plan_id4]);

// Hallazgo T4 ronda 1, punto 2: 'respuesta_cliente' no puede ser el primer tipo de
// get_detail_types(), porque showAddDetailModal() no preselecciona ningún <option> para un
// registro nuevo y el navegador elige el primero (quedaría marcado "Respuesta del cliente" sin
// que nadie lo haya elegido). Debe seguir presente en la lista, solo que no primero.
$tipos = AutomatizaTech_Client_Details::get_detail_types();
$claves_tipos = array_keys($tipos);
ok($claves_tipos[0] !== 'respuesta_cliente', 'T4R1: respuesta_cliente no es el tipo por defecto de un registro nuevo de Seguimiento');
ok(isset($tipos['respuesta_cliente']), 'T4R1: respuesta_cliente sigue disponible en la lista de tipos');

// Revisión final (26-sep), hallazgo 9: las notas internas del cierre están en la lista (si no, al
// editarlas con ✏️ el <select> caía en la primera opción, 'propuesta_enviada', que es pública), con
// la marca «(interno)» y ninguna como primera opción.
foreach (['pedido_respuesta', 'cierre_incompleto', 'aviso_operativo'] as $tipo_interno) {
	ok(isset($tipos[$tipo_interno]) && strpos($tipos[$tipo_interno]['label'], '(interno)') !== false, "RF9: {$tipo_interno} está en get_detail_types() con la etiqueta «(interno)»");
	ok($claves_tipos[0] !== $tipo_interno, "RF9: {$tipo_interno} no es la primera opción del select");
}
ok($claves_tipos[0] === 'propuesta_enviada', 'RF9: la primera opción sigue siendo propuesta_enviada (sin cambios para un registro nuevo)');

add_filter('pre_wp_mail', '__return_true'); // create_contract() avisa por correo a quien revisa.
$marca_rf = 'prueba-cierre-rf-' . strtolower(wp_generate_password(6, false, false));

// Revisión final (26-sep), hallazgo 1: nombre, empresa y teléfono llegan a las dos tablas como texto
// plano aunque vengan con etiquetas (el nombre de la página pública se recibe tal cual).
$email_x = $marca_rf . '-x@example.com';
$rx = at_cc_asegurar_cliente(['nombre' => 'Ana <img src=x onerror=alert(1)> Prueba', 'email' => $email_x, 'empresa' => '<b>[PRUEBA]</b> Empresa', 'telefono' => '+56 9 1111 1111<script>x</script>']);
$cx = is_array($rx) ? $wpdb->get_row($wpdb->prepare("SELECT nombre, empresa, telefono FROM {$crm} WHERE id = %d", $rx['crm_id'])) : null;
$tx = is_array($rx) ? $wpdb->get_row($wpdb->prepare("SELECT name, company, phone FROM {$tech} WHERE id = %d", $rx['tech_id'])) : null;
ok($cx && $cx->nombre === 'Ana Prueba' && $tx && $tx->name === 'Ana Prueba', 'RF1: el nombre queda sin etiquetas en el CRM y en la ficha operativa: ' . ($cx->nombre ?? '?') . ' / ' . ($tx->name ?? '?'));
ok($cx && $cx->empresa === '[PRUEBA] Empresa' && $tx && $tx->company === '[PRUEBA] Empresa', 'RF1: la empresa queda sin etiquetas en las dos tablas');
ok($cx && $tx && strpos($cx->telefono . $tx->phone, '<') === false && $cx->telefono === '+56 9 1111 1111', 'RF1: el teléfono queda sin etiquetas en las dos tablas');
$email_x2 = $marca_rf . '-x2@example.com';
$rx2 = at_cc_asegurar_cliente(['nombre' => '<img src=x onerror=alert(1)>', 'email' => $email_x2]);
$cx2 = is_array($rx2) ? $wpdb->get_var($wpdb->prepare("SELECT nombre FROM {$crm} WHERE id = %d", $rx2['crm_id'])) : null;
$tx2 = is_array($rx2) ? $wpdb->get_var($wpdb->prepare("SELECT name FROM {$tech} WHERE id = %d", $rx2['tech_id'])) : null;
ok($cx2 === $email_x2 && $tx2 === $email_x2, 'RF1: un nombre que es solo una etiqueta queda vacío y la ficha toma el correo: ' . var_export($cx2, true));

// Revisión final (26-sep), hallazgo 3: con dos filas del CRM con el mismo correo, quien llama con
// crm_id convierte y enlaza ESA fila, no la de id más bajo; y la ficha de una fila no se le quita
// para dársela a la otra.
$email_dup = $marca_rf . '-dup@example.com';
$wpdb->insert($crm, ['nombre' => 'Fila A', 'email' => $email_dup, 'tipo' => 'prospecto', 'estado' => 'nuevo']);
$crm_a = (int) $wpdb->insert_id;
$wpdb->insert($crm, ['nombre' => 'Fila B', 'email' => $email_dup, 'tipo' => 'cliente', 'estado' => 'contratado']);
$crm_b = (int) $wpdb->insert_id;
$rb = at_cc_asegurar_cliente(['crm_id' => $crm_b, 'nombre' => 'Fila B', 'email' => $email_dup]);
ok(is_array($rb) && $rb['crm_id'] === $crm_b, 'RF3: con crm_id se usa esa fila del CRM (B), no la de id menor (A)');
ok($wpdb->get_var($wpdb->prepare("SELECT tipo FROM {$crm} WHERE id = %d", $crm_a)) === 'prospecto', 'RF3: la otra fila con el mismo correo (A) sigue como prospecto');
ok(is_array($rb) && at_cc_tech_de_crm($crm_b) && (int) at_cc_tech_de_crm($crm_b)->id === $rb['tech_id'] && at_cc_tech_de_crm($crm_a) === null, 'RF3: la ficha operativa queda enlazada a B y no a A');
$ra = at_cc_asegurar_cliente(['nombre' => 'Fila A', 'email' => $email_dup]);
ok(is_array($ra) && $ra['crm_id'] === $crm_a && is_array($rb) && $ra['tech_id'] !== $rb['tech_id'], 'RF3: sin crm_id (por correo) toma A y le crea su propia ficha, sin reusar la de B');
ok(is_array($rb) && at_cc_tech_de_crm($crm_b) && (int) at_cc_tech_de_crm($crm_b)->id === $rb['tech_id'], 'RF3: B conserva su ficha operativa');
// La búsqueda por correo prefiere una ficha sin enlazar.
$email_pref = $marca_rf . '-pref@example.com';
$wpdb->insert($crm, ['nombre' => 'Otra fila', 'email' => $email_pref, 'tipo' => 'cliente']);
$crm_otra = (int) $wpdb->insert_id;
$wpdb->insert($tech, ['name' => 'Ficha de otra fila', 'email' => $email_pref, 'contract_status' => 'active', 'crm_cliente_id' => $crm_otra]);
$tech_otra = (int) $wpdb->insert_id;
$wpdb->insert($tech, ['name' => 'Ficha suelta', 'email' => $email_pref, 'contract_status' => 'active']);
$tech_suelta = (int) $wpdb->insert_id;
$wpdb->insert($crm, ['nombre' => 'Fila nueva', 'email' => $email_pref, 'tipo' => 'cliente']);
$crm_nueva = (int) $wpdb->insert_id;
$rp = at_cc_asegurar_cliente(['crm_id' => $crm_nueva, 'email' => $email_pref]);
ok(is_array($rp) && $rp['tech_id'] === $tech_suelta && (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$tech} WHERE id = %d", $tech_otra)) === $crm_otra, 'RF3: por correo se toma la ficha sin enlazar y la de otra fila no se toca');

// Revisión final (26-sep), hallazgo 6: con dos fichas operativas enlazadas al mismo cliente del CRM,
// la pestaña avisa y lista también los contratos de la ficha que no es la principal.
require_once ABSPATH . 'contracts/contract-service.php';
$email_6 = $marca_rf . '-6@example.com';
$r6 = at_cc_asegurar_cliente(['nombre' => 'Cliente Dos Fichas', 'email' => $email_6, 'valor' => 1000]);
$ct6 = ContractService::create_contract(['client_id' => $r6['tech_id'], 'proposal_id' => 0, 'type' => 'servicios', 'template_id' => 'servicios_v1', 'placeholders' => ['razon_social_cliente' => '[PRUEBA] Dos Fichas'], 'created_by' => 0]);
ob_start();
at_cc_render_contratos_otras_fichas($r6['crm_id'], $r6['tech_id']);
ok(ob_get_clean() === '', 'RF6: con una sola ficha no se agrega nada a la pestaña');
$wpdb->insert($tech, ['name' => 'Cliente Dos Fichas', 'email' => $email_6, 'contract_status' => 'active', 'contract_value' => 2000, 'crm_cliente_id' => $r6['crm_id']]);
$t6b = (int) $wpdb->insert_id;
$principal6 = at_cc_tech_de_crm($r6['crm_id']);
ok($principal6 && (int) $principal6->id === $t6b, 'RF6: la ficha principal pasa a ser la más nueva (la del contrato de servicios queda oculta en el widget)');
ok(count(at_cc_techs_de_crm($r6['crm_id'])) === 2, 'RF6: at_cc_techs_de_crm lista las dos fichas');
ob_start();
at_cc_render_contratos_otras_fichas($r6['crm_id'], (int) $principal6->id);
$h6 = (string) ob_get_clean();
ok(is_object($ct6) && strpos($h6, esc_html($ct6->contract_number)) !== false, 'RF6: la pestaña lista el contrato de servicios de la otra ficha');
ok(strpos($h6, '2 fichas operativas enlazadas') !== false, 'RF6: la pestaña avisa que hay más de una ficha');
if (is_object($ct6)) {
	$wpdb->delete(ContractService::table(), ['id' => $ct6->id]);
	@unlink(ContractService::storage_dir() . '/' . $ct6->contract_number . '.pdf');
}

$wpdb->query($wpdb->prepare("DELETE FROM {$tech} WHERE email LIKE %s", $wpdb->esc_like($marca_rf) . '%'));
$wpdb->query($wpdb->prepare("DELETE FROM {$crm} WHERE email LIKE %s", $wpdb->esc_like($marca_rf) . '%'));

fin();
