<?php
// Pruebas locales de Finanzas AT: tablas, registro idempotente y tipo de cambio. Nunca contra producción.
require __DIR__ . '/../retomar/wp-bootstrap.php';
exigir('at_fin_migrar', 'at_fin_cuenta_guardar', 'at_fin_cuenta', 'at_fin_cuentas', 'at_fin_registrar', 'at_fin_movimientos_mes', 'at_fin_movimientos_cuenta', 'at_fin_completar_pendientes', 'at_fin_tipo_cambio');
global $wpdb;

// --- Simulación de mindicador (el bootstrap bloquea todo HTTP; este filtro corre después, con la misma prioridad máxima).
$GLOBALS['mi_modo'] = 'ok';      // ok | error | vacio
$GLOBALS['mi_valor'] = 950.0;
$GLOBALS['mi_urls'] = [];
$GLOBALS['mi_otras'] = 0;
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://mindicador.cl/api/dolar/') !== 0) {
		$GLOBALS['mi_otras']++;
		return $pre;
	}
	$GLOBALS['mi_urls'][] = $url;
	if ($GLOBALS['mi_modo'] === 'error') { return new WP_Error('prueba_local', 'mindicador simulado caído'); }
	$serie = [];
	if ($GLOBALS['mi_modo'] === 'ok' && preg_match('#/(\d{2})-(\d{2})-(\d{4})$#', $url, $m)) {
		$serie[] = ['fecha' => $m[3] . '-' . $m[2] . '-' . $m[1] . 'T03:00:00.000Z', 'valor' => $GLOBALS['mi_valor']];
	}
	return ['headers' => [], 'body' => wp_json_encode(['version' => '1.7.0', 'codigo' => 'dolar', 'serie' => $serie]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);

$cache_original = get_option('at_fin_tc_cache', null);
$esquema_original = get_option('at_fin_schema', null);
$marca = 'prueba-' . strtolower(wp_generate_password(8, false, false));
$cuentas_slug = [$marca . '-hf'];
register_shutdown_function(static function () use ($wpdb, &$cuentas_slug, $cache_original) {
	$tm = $wpdb->prefix . 'at_fin_movimientos';
	$tc = $wpdb->prefix . 'at_fin_cuentas';
	foreach ($cuentas_slug as $slug) {
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tc} WHERE slug = %s", $slug));
		if ($id) { $wpdb->delete($tm, ['cuenta_id' => $id]); $wpdb->delete($tc, ['id' => $id]); }
	}
	$wpdb->query("DELETE FROM {$tm} WHERE descripcion LIKE '[PRUEBA]%'");
	if ($cache_original === null) { delete_option('at_fin_tc_cache'); } else { update_option('at_fin_tc_cache', $cache_original, false); }
});
delete_option('at_fin_tc_cache');

// --- Esquema
delete_option('at_fin_schema');
at_fin_migrar();
$tm = $wpdb->prefix . 'at_fin_movimientos';
$tc = $wpdb->prefix . 'at_fin_cuentas';
ok($wpdb->get_var("SHOW TABLES LIKE '{$tc}'") === $tc, 'tabla de cuentas creada');
ok($wpdb->get_var("SHOW TABLES LIKE '{$tm}'") === $tm, 'tabla de movimientos creada');
ok(get_option('at_fin_schema') === '1', "opción de esquema es string '1'");
$cols_cuentas = array_column($wpdb->get_results("SHOW COLUMNS FROM {$tc}", ARRAY_A), 'Field');
ok($cols_cuentas === ['id', 'slug', 'nombre', 'proveedor', 'moneda', 'activa', 'notas', 'created_at', 'updated_at'], 'cuentas: columnas del spec en orden');
$cols_mov = array_column($wpdb->get_results("SHOW COLUMNS FROM {$tm}", ARRAY_A), 'Field');
ok($cols_mov === ['id', 'fecha', 'tipo', 'cuenta_id', 'proveedor', 'categoria', 'descripcion', 'proyecto', 'monto', 'moneda', 'tipo_cambio', 'monto_clp', 'tc_estado', 'gasto_id', 'referencia', 'registrado_por', 'origen', 'notas', 'created_at'], 'movimientos: columnas del spec en orden');
$indices = $wpdb->get_results("SHOW INDEX FROM {$tm}", ARRAY_A);
$unica_ref = array_filter($indices, static fn($i) => $i['Column_name'] === 'referencia' && (int) $i['Non_unique'] === 0);
ok(count($unica_ref) === 1, 'referencia es UNIQUE');
ok(in_array('fecha', array_column($indices, 'Column_name'), true) && in_array('cuenta_id', array_column($indices, 'Column_name'), true), 'índices por fecha y cuenta_id');
$unica_slug = array_filter($wpdb->get_results("SHOW INDEX FROM {$tc}", ARRAY_A), static fn($i) => $i['Column_name'] === 'slug' && (int) $i['Non_unique'] === 0);
ok(count($unica_slug) === 1, 'slug es UNIQUE');
$antes = $wpdb->get_results("SHOW COLUMNS FROM {$tm}", ARRAY_A);
at_fin_migrar();
ok($wpdb->get_results("SHOW COLUMNS FROM {$tm}", ARRAY_A) === $antes, 'segunda migración no cambia el esquema');
$engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $tm));
ok(strtolower((string) $engine) === 'innodb', 'movimientos es InnoDB');

// --- Cuentas
$slug = $marca . '-hf';
$id_cuenta = at_fin_cuenta_guardar(['slug' => $slug, 'nombre' => '[PRUEBA] HF', 'proveedor' => 'Higgsfield', 'moneda' => 'USD']);
ok(is_int($id_cuenta) && $id_cuenta > 0, 'cuenta nueva devuelve id');
ok(at_fin_cuenta_guardar(['slug' => strtoupper($slug), 'nombre' => '[PRUEBA] HF 2', 'proveedor' => 'Higgsfield', 'moneda' => 'USD', 'notas' => 'n']) === $id_cuenta, 'repetir la cuenta devuelve el mismo id');
$c = at_fin_cuenta($slug);
ok($c && $c['nombre'] === '[PRUEBA] HF 2' && $c['moneda'] === 'USD' && (int) $c['activa'] === 1 && (int) $c['id'] === $id_cuenta, 'la repetición actualiza el nombre');
ok(at_fin_cuenta('no-existe-' . $marca) === null, 'cuenta inexistente → null');
ok(is_wp_error(at_fin_cuenta_guardar(['slug' => 'Mal Slug!', 'nombre' => 'x', 'moneda' => 'USD'])), 'slug no válido → WP_Error');
$ids_antes = $wpdb->get_var("SELECT COUNT(*) FROM {$tc} WHERE slug = '{$slug}'");
ok((int) $ids_antes === 1, 'una sola fila por slug');

// --- Tipo de cambio
$hoy = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d');
$ayer = (new DateTimeImmutable('yesterday', new DateTimeZone('America/Santiago')))->format('Y-m-d');
$GLOBALS['mi_urls'] = [];
$v = at_fin_tipo_cambio($ayer);
ok($v === 950.0, 'tipo de cambio desde mindicador simulado');
ok(count($GLOBALS['mi_urls']) === 1 && substr($GLOBALS['mi_urls'][0], -10) === DateTimeImmutable::createFromFormat('Y-m-d', $ayer)->format('d-m-Y'), 'URL con la fecha DD-MM-AAAA');
$GLOBALS['mi_urls'] = [];
ok(at_fin_tipo_cambio($ayer) === 950.0 && $GLOBALS['mi_urls'] === [], 'segunda consulta del mismo día sale de la caché');
$cache = get_option('at_fin_tc_cache');
ok(is_array($cache) && ($cache[$ayer] ?? null) === 950.0, 'caché en at_fin_tc_cache por fecha');

// Día sin valor: se usa el anterior (el simulador responde serie vacía salvo para un día concreto).
$dia_sin = '2026-02-14';
$dia_con = '2026-02-12';
$GLOBALS['mi_valor'] = 931.5;
delete_option('at_fin_tc_cache');
$GLOBALS['mi_modo'] = 'ok';
add_filter('pre_http_request', $solo_dia_con = static function ($pre, $args, $url) use ($dia_con) {
	if (strpos($url, 'https://mindicador.cl/api/dolar/') !== 0) { return $pre; }
	if (substr($url, -10) !== '12-02-2026') {
		return ['headers' => [], 'body' => '{"version":"1.7.0","codigo":"dolar","serie":[]}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
	}
	return $pre;
}, PHP_INT_MAX, 3);
$GLOBALS['mi_urls'] = [];
ok(at_fin_tipo_cambio($dia_sin) === 931.5, 'día sin valor usa el último anterior');
ok(count($GLOBALS['mi_urls']) === 3, 'consulta los días hacia atrás solo hasta encontrar valor (3 llamadas)');
remove_filter('pre_http_request', $solo_dia_con, PHP_INT_MAX);

// Sin valores en ningún día → null; error de red → null, sin guardar nada en caché.
delete_option('at_fin_tc_cache');
$GLOBALS['mi_modo'] = 'vacio';
$GLOBALS['mi_urls'] = [];
ok(at_fin_tipo_cambio('2026-03-01') === null, 'sin valor en 8 días → null');
ok(count($GLOBALS['mi_urls']) === 8, 'consulta como máximo 8 días (hoy + 7 anteriores)');
$GLOBALS['mi_modo'] = 'error';
ok(at_fin_tipo_cambio('2026-03-01') === null, 'error de la fuente → null');
ok(!isset((get_option('at_fin_tc_cache') ?: [])['2026-03-01']), 'un fallo no se guarda en la caché');

// Caché limitada a 120 entradas.
$grande = [];
for ($i = 0; $i < 125; $i++) { $grande[(new DateTimeImmutable('2025-01-01'))->modify("+{$i} days")->format('Y-m-d')] = 900.0 + $i; }
update_option('at_fin_tc_cache', $grande, false);
$GLOBALS['mi_modo'] = 'ok';
$GLOBALS['mi_valor'] = 960.0;
at_fin_tipo_cambio('2026-04-01');
$cache = get_option('at_fin_tc_cache');
ok(count($cache) <= 120 && isset($cache['2026-04-01']), 'la caché no pasa de 120 entradas y guarda el valor nuevo');
delete_option('at_fin_tc_cache');
$GLOBALS['mi_valor'] = 950.0;
$GLOBALS['mi_modo'] = 'ok';

// --- Registro: pago USD con tipo de cambio de la fuente
$base = ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Higgsfield', 'categoria' => 'IA', 'descripcion' => '[PRUEBA] suscripción', 'monto' => 12.5, 'moneda' => 'USD', 'registrado_por' => 'Prueba'];
$GLOBALS['mi_urls'] = [];
$r = at_fin_registrar($base, 'api');
ok(is_array($r) && $r['estado'] === 'creado' && $r['id'] > 0, 'pago USD se crea');
$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $r['id']), ARRAY_A);
ok((float) $fila['tipo_cambio'] === 950.0 && (int) $fila['monto_clp'] === (int) round(12.5 * 950) && $fila['tc_estado'] === 'ok', 'monto_clp = round(monto × tc) y tc_estado ok');
ok($fila['origen'] === 'api' && $fila['registrado_por'] === 'Prueba' && strpos($fila['referencia'], 'auto-') === 0 && $fila['cuenta_id'] === null, 'origen, quién registró, referencia automática y sin cuenta');
$r2 = at_fin_registrar($base, 'api');
ok(is_array($r2) && $r2['estado'] === 'existente' && $r2['id'] === $r['id'], 'repetir sin referencia → existente, mismo id');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tm} WHERE descripcion = %s AND fecha = %s", $base['descripcion'], $ayer)) === 1, 'un solo registro');
$r3_otra = at_fin_registrar(array_merge($base, ['referencia' => 'x-' . $marca]), 'api');
ok(is_array($r3_otra) && $r3_otra['estado'] === 'creado', 'con referencia propia es otro registro');
$r4 = at_fin_registrar(array_merge($base, ['referencia' => 'x-' . $marca, 'monto' => 99]), 'api');
ok(is_wp_error($r4) && $r4->get_error_code() === 'duplicado' && ($r4->get_error_data()['status'] ?? 0) === 409, 'misma referencia con otro monto → 409 duplicado');
$r5 = at_fin_registrar(array_merge($base, ['referencia' => 'x-' . $marca]), 'api');
ok(is_array($r5) && $r5['estado'] === 'existente' && $r5['id'] === $r3_otra['id'], 'misma referencia con los mismos datos → existente');

// --- Validación → 422
$mala = at_fin_registrar(array_merge($base, ['monto' => 'abc']), 'api');
ok(is_wp_error($mala) && ($mala->get_error_data()['status'] ?? 0) === 422, 'datos no válidos → 422');
$sin_cuenta = at_fin_registrar(['tipo' => 'consumo', 'fecha' => $ayer, 'cuenta' => 'no-existe-' . $marca, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] consumo', 'monto' => 3, 'moneda' => 'USD'], 'api');
ok(is_wp_error($sin_cuenta) && ($sin_cuenta->get_error_data()['status'] ?? 0) === 422, 'consumo con cuenta inexistente → 422');

// --- Consumo con cuenta existente
$con = at_fin_registrar(['tipo' => 'consumo', 'fecha' => $ayer, 'cuenta' => $slug, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] consumo', 'monto' => 3, 'moneda' => 'USD'], 'panel');
ok(is_array($con) && $con['estado'] === 'creado', 'consumo con cuenta se crea');
$fc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $con['id']), ARRAY_A);
ok((int) $fc['cuenta_id'] === $id_cuenta && $fc['origen'] === 'panel', 'resuelve cuenta → cuenta_id y guarda el origen');
$rec = at_fin_registrar(['tipo' => 'recarga', 'fecha' => $ayer, 'cuenta' => $slug, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] recarga', 'monto' => 20, 'moneda' => 'USD'], 'api');
ok(is_array($rec), 'recarga con cuenta se crea');
$movs = at_fin_movimientos_cuenta($id_cuenta);
ok(count($movs) === 2, 'movimientos de la cuenta');
$cuentas = array_filter(at_fin_cuentas(), static fn($x) => $x['slug'] === $slug);
$cuenta_con_saldo = array_values($cuentas)[0] ?? null;
ok($cuenta_con_saldo && abs($cuenta_con_saldo['saldo']['saldo'] - 17.0) < 0.0001, 'at_fin_cuentas trae el saldo (20 − 3)');
ok(count(at_fin_movimientos_cuenta($id_cuenta, 1)) === 1, 'límite de movimientos por cuenta');
$mes = at_fin_movimientos_mes(substr($ayer, 0, 7));
$descs = array_column($mes, 'descripcion');
ok(in_array('[PRUEBA] suscripción', $descs, true) && in_array('[PRUEBA] recarga', $descs, true), 'movimientos del mes');

// --- Tipo de cambio manual: no llama HTTP
$GLOBALS['mi_urls'] = [];
$man = at_fin_registrar(array_merge($base, ['descripcion' => '[PRUEBA] manual', 'tipo_cambio' => 1000, 'monto' => 10]), 'api');
$fm = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $man['id']), ARRAY_A);
ok($fm['tc_estado'] === 'manual' && (int) $fm['monto_clp'] === 10000 && (float) $fm['tipo_cambio'] === 1000.0, 'tipo de cambio manual: estado manual y monto_clp');
ok($GLOBALS['mi_urls'] === [], 'el manual no llama a mindicador');

// --- CLP: tipo de cambio 1 y sin HTTP
$GLOBALS['mi_urls'] = [];
$clp = at_fin_registrar(['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Hostinger', 'descripcion' => '[PRUEBA] clp', 'monto' => 15990, 'moneda' => 'CLP'], 'api');
$fl = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $clp['id']), ARRAY_A);
ok((float) $fl['tipo_cambio'] === 1.0 && (int) $fl['monto_clp'] === 15990 && $fl['tc_estado'] === 'ok', 'pago CLP: tipo_cambio 1 y monto_clp igual al monto');
ok($GLOBALS['mi_urls'] === [], 'CLP no llama a mindicador');

// --- Fuente caída: pendiente; luego se completa
delete_option('at_fin_tc_cache');
$GLOBALS['mi_modo'] = 'error';
$GLOBALS['mi_urls'] = [];
$pen = at_fin_registrar(array_merge($base, ['descripcion' => '[PRUEBA] pendiente', 'fecha' => $hoy, 'monto' => 4]), 'api');
$fp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $pen['id']), ARRAY_A);
ok(is_array($pen) && $pen['estado'] === 'creado' && $fp['tc_estado'] === 'pendiente' && $fp['monto_clp'] === null && $fp['tipo_cambio'] === null, 'API caída: se guarda pendiente, sin monto_clp');
ok(at_fin_completar_pendientes() === 0, 'con la API caída no se completa nada');
ok($wpdb->get_var($wpdb->prepare("SELECT tc_estado FROM {$tm} WHERE id = %d", $pen['id'])) === 'pendiente', 'sigue pendiente');
$GLOBALS['mi_modo'] = 'ok';
$completados = at_fin_completar_pendientes();
ok($completados >= 1, 'con la API de vuelta se completan pendientes');
$fp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $pen['id']), ARRAY_A);
ok($fp['tc_estado'] === 'ok' && (int) $fp['monto_clp'] === 3800 && (float) $fp['tipo_cambio'] === 950.0, 'el pendiente queda con tipo de cambio y monto_clp (4 × 950)');

// --- activa: el texto «false» significa inactiva
$slug_act = $marca . '-hf';
foreach ([['false', 0], ['FALSE', 0], ['0', 0], [false, 0], [0, 0], ['no', 0], ['off', 0], ['true', 1], ['1', 1], [true, 1], ['yes', 1], ['on', 1]] as [$valor, $esperado_activa]) {
	at_fin_cuenta_guardar(['slug' => $slug_act, 'activa' => $esperado_activa === 0]);   // deja la cuenta en el estado contrario
	at_fin_cuenta_guardar(['slug' => $slug_act, 'activa' => $valor]);
	ok((int) at_fin_cuenta($slug_act)['activa'] === $esperado_activa, 'activa = ' . var_export($valor, true) . ' → ' . $esperado_activa);
}
at_fin_cuenta_guardar(['slug' => $slug_act, 'activa' => false]);
at_fin_cuenta_guardar(['slug' => $slug_act, 'activa' => 'quizás']);
ok((int) at_fin_cuenta($slug_act)['activa'] === 0, 'un valor irreconocible no cambia el estado de la cuenta');
at_fin_cuenta_guardar(['slug' => $slug_act, 'nombre' => '[PRUEBA] sin tocar activa']);
ok((int) at_fin_cuenta($slug_act)['activa'] === 0, 'sin la clave activa, la cuenta conserva su estado');
at_fin_cuenta_guardar(['slug' => $slug_act, 'activa' => true]);

// --- Comparación de meses
$comp = at_fin_comparacion('2020-02');
ok(count($comp) === 6 && $comp[0] === ['mes' => '2019-08', 'total_clp' => 0] && $comp[5]['mes'] === '2020-01', 'at_fin_comparacion: 6 meses anteriores, del más antiguo al más reciente, cruzando el año');
ok(count(at_fin_comparacion('2026-10', 3)) === 3, 'at_fin_comparacion acepta otra cantidad de meses');

// --- Hooks
ok(has_action('admin_init', 'at_fin_migrar') !== false && has_action('rest_api_init', 'at_fin_migrar') !== false, 'migración enganchada en admin_init y rest_api_init');
ok($GLOBALS['mi_otras'] === 0, 'ninguna otra petición HTTP salió en las pruebas');

if ($esquema_original !== null) { update_option('at_fin_schema', $esquema_original); }
fin();
