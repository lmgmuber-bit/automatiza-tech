<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-wp-test.php
// Task 5: tabla del plan, migración, crear idempotente y lecturas.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/datos-prueba.php';
global $wpdb;
$ids = function (array $filas): array { return array_map(function ($p) { return (int) $p->id; }, $filas); };

// 1) Tabla y migración.
delete_option('at_plan_schema');
delete_transient('at_pt_migrar_intento');
at_pt_migrar_esquema();
$esperadas = ['id', 'contrato_id', 'crm_cliente_id', 'tech_id', 'propuesta_id', 'codigo', 'estado', 'fecha_inicio', 'payload', 'comentarios', 'nota', 'view_url', 'pdf_url', 'created_at', 'updated_at', 'enviado_at'];
ok(get_option('at_plan_schema') === '1', '1) la migración deja at_plan_schema = 1');
ok($wpdb->get_col('SHOW COLUMNS FROM ' . at_pt_tabla()) === $esperadas, '1) la tabla tiene las 16 columnas del contrato de interfaces, en orden');
$indices = [];
foreach ((array) $wpdb->get_results('SHOW INDEX FROM ' . at_pt_tabla()) as $i) {
	$indices[$i->Key_name] = (int) $i->Non_unique === 0 ? 'unico' : 'normal';
}
ok(($indices['PRIMARY'] ?? '') === 'unico' && ($indices['uniq_contrato'] ?? '') === 'unico' && ($indices['uniq_codigo'] ?? '') === 'unico' && ($indices['idx_crm'] ?? '') === 'normal', '1) índices: primario, uniq_contrato, uniq_codigo e idx_crm');
$col_estado = $wpdb->get_row('SHOW COLUMNS FROM ' . at_pt_tabla() . " LIKE 'estado'");
ok($col_estado && $col_estado->Default === 'generando', '1) un plan nace en «generando»');
delete_option('at_plan_schema');
at_pt_migrar_esquema();
ok(get_option('at_plan_schema') === '1' && $wpdb->get_col('SHOW COLUMNS FROM ' . at_pt_tabla()) === $esperadas, '1) correr la migración otra vez no cambia la tabla');
ok(has_action('admin_init', 'at_pt_migrar_esquema') !== false, '1) la migración cuelga de admin_init');

// 2) Crear el plan de un contrato de servicios firmado.
$m = pt_marca();
$cli = pt_cliente($m);
$prop = pt_propuesta($m);
$cid = pt_contrato($cli['tech'], $prop);
$pid = at_pt_crear_plan($cid);
$f = is_int($pid) ? at_pt_plan($pid) : null;
ok(is_int($pid) && $pid > 0, '2) crea el plan de un contrato de servicios firmado');
ok($f && $f->estado === 'generando' && (int) $f->contrato_id === $cid && (int) $f->tech_id === $cli['tech'] && (int) $f->crm_cliente_id === $cli['crm'] && (int) $f->propuesta_id === $prop, '2) enlaza contrato, ficha operativa, cliente del CRM y propuesta; nace en «generando»');
ok($f && preg_match('/^[A-Za-z0-9]{12}$/', (string) $f->codigo) === 1, '2) código de 12 letras o números');
ok($f && $f->payload === null && $f->fecha_inicio === null && $f->view_url === null && $f->nota === null, '2) todavía sin contenido, fecha, vista ni nota');

// 3) Idempotente: un contrato, un plan.
ok(at_pt_crear_plan($cid) === $pid, '3) crear otra vez devuelve el mismo plan');
ok((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $cid)) === 1, '3) sigue habiendo un solo plan para ese contrato');

// 4) Contratos que no llevan plan.
$sop = pt_contrato($cli['tech'], null, ['type' => 'soporte']);
$e = at_pt_crear_plan($sop);
ok(is_wp_error($e) && $e->get_error_code() === 'at_pt_no_servicios' && !at_pt_plan_de_contrato($sop), '4) un contrato de soporte no tiene plan');
$sin_firma = pt_contrato($cli['tech'], null, ['status' => 'sent']);
$e = at_pt_crear_plan($sin_firma);
ok(is_wp_error($e) && $e->get_error_code() === 'at_pt_sin_firma' && !at_pt_plan_de_contrato($sin_firma), '4) un contrato sin la firma del cliente no tiene plan');
$e = at_pt_crear_plan(pt_contrato_inexistente());
ok(is_wp_error($e) && $e->get_error_code() === 'at_pt_sin_contrato', '4) un contrato que no existe: error con motivo');

// 5) Contrato sin propuesta y cliente sin correo (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$cid2 = pt_contrato($cli2['tech'], null);
$pid2 = at_pt_crear_plan($cid2);
$f2 = is_int($pid2) ? at_pt_plan($pid2) : null;
ok($f2 && $f2->propuesta_id === null && (int) $f2->crm_cliente_id === $cli2['crm'], '5) contrato sin propuesta y cliente sin correo: el plan igual se crea');

// 6) Ficha operativa que todavía no está enlazada al CRM.
$m3 = pt_marca();
$cli3 = pt_cliente($m3, true, false);
$cid3 = pt_contrato($cli3['tech'], null);
$pid3 = at_pt_crear_plan($cid3);
$f3 = is_int($pid3) ? at_pt_plan($pid3) : null;
ok($f3 && $f3->crm_cliente_id === null && !in_array($pid3, $ids(at_pt_planes_de_crm($cli3['crm'])), true), '6) ficha sin enlazar: el plan queda sin cliente del CRM y no aparece en su ficha');
$wpdb->update($wpdb->prefix . 'automatiza_tech_clients', ['crm_cliente_id' => $cli3['crm']], ['id' => $cli3['tech']]);
ok(in_array($pid3, $ids(at_pt_planes_de_crm($cli3['crm'])), true), '6) al enlazar la ficha, el plan aparece en el cliente del CRM');

// 7) Lecturas.
$codigo = $f ? (string) $f->codigo : '';
ok($codigo !== '' && (int) at_pt_plan_por_codigo($codigo)->id === $pid, '7) por código exacto');
$cambiado = ctype_digit($codigo) ? null : (strtolower($codigo) !== $codigo ? strtolower($codigo) : strtoupper($codigo));
ok($cambiado === null || at_pt_plan_por_codigo($cambiado) === null, '7) el código distingue mayúsculas');
ok(at_pt_plan_por_codigo('corto') === null && at_pt_plan_por_codigo("abc' OR 1=1 #") === null, '7) un código mal formado no busca nada');
ok((int) at_pt_plan_de_contrato($cid)->id === $pid && at_pt_plan_de_contrato($sop) === null, '7) plan de un contrato');
$cid_b = pt_contrato($cli['tech'], null);
$pid_b = at_pt_crear_plan($cid_b);
ok($ids(at_pt_planes_de_crm($cli['crm'])) === [$pid_b, $pid], '7) planes del cliente del CRM, del más nuevo al más viejo');
ok(at_pt_planes_de_crm(0) === [] && at_pt_plan(0) === null && at_pt_plan(pt_plan_inexistente()) === null, '7) ids vacíos o inexistentes: nada');

// 8) Contenido del plan.
ok(at_pt_payload($f) === [], '8) sin contenido: arreglo vacío');
ok(at_pt_payload((object) ['payload' => '{roto']) === [] && at_pt_payload((object) ['payload' => '"texto"']) === [], '8) JSON roto o que no es objeto: arreglo vacío');
ok(at_pt_payload((object) ['payload' => '{"version":1,"fases":[]}']) === ['version' => 1, 'fases' => []], '8) JSON válido: el arreglo');

// 9) El código nunca repite el de una propuesta (el renderer publica los dos en /p/<código>/).
$usado = substr(md5($m), 0, 12);
$vueltas = 0;
$repetir = function ($clave) use (&$vueltas, $usado) { $vueltas++; return $vueltas === 1 ? $usado : $clave; };
add_filter('random_password', $repetir);
$cid9 = pt_contrato($cli['tech'], null);
$pid9 = at_pt_crear_plan($cid9);
remove_filter('random_password', $repetir);
ok(is_int($pid9) && $vueltas >= 2 && at_pt_plan($pid9)->codigo !== $usado, '9) si el código sale igual al de una propuesta, se genera otro');

fin();
