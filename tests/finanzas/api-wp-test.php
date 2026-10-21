<?php
// Pruebas locales de la API de Finanzas AT (rutas REST, dictámenes de moneda, caché del día y errores de la base). Nunca contra producción.
require __DIR__ . '/../retomar/wp-bootstrap.php';
exigir('at_fin_auth', 'at_fin_registrar', 'at_fin_cuenta_guardar', 'at_fin_resumen_mes', 'at_fin_completar_pendientes', 'at_fin_tipo_cambio');
global $wpdb;

// El secreto de prueba: el del sitio local si existe (nunca se imprime); si no, uno propio.
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') { define('AT_REST_SECRET', 'secreto-de-prueba-' . bin2hex(random_bytes(8))); }
$SECRETO = AT_REST_SECRET;

// --- Simulación de mindicador: sirve un valor por día salvo los días indicados en mi_sin_valor.
$GLOBALS['mi_modo'] = 'ok';      // ok | error
$GLOBALS['mi_valor'] = 950.0;
$GLOBALS['mi_sin_valor'] = [];   // fechas 'd-m-Y' que responden serie vacía
$GLOBALS['mi_urls'] = [];
$GLOBALS['mi_otras'] = 0;
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://mindicador.cl/api/dolar/') !== 0) { $GLOBALS['mi_otras']++; return $pre; }
	$GLOBALS['mi_urls'][] = $url;
	if ($GLOBALS['mi_modo'] === 'error') { return new WP_Error('prueba_local', 'mindicador simulado caído'); }
	$serie = [];
	if (preg_match('#/(\d{2})-(\d{2})-(\d{4})$#', $url, $m) && !in_array($m[1] . '-' . $m[2] . '-' . $m[3], $GLOBALS['mi_sin_valor'], true)) {
		$serie[] = ['fecha' => $m[3] . '-' . $m[2] . '-' . $m[1] . 'T03:00:00.000Z', 'valor' => $GLOBALS['mi_valor']];
	}
	return ['headers' => [], 'body' => wp_json_encode(['version' => '1.7.0', 'codigo' => 'dolar', 'serie' => $serie]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);

$cache_original = get_option('at_fin_tc_cache', null);
$marca = 'prueba-' . strtolower(wp_generate_password(8, false, false));
$slugs = [$marca . '-usd', $marca . '-clp', $marca . '-hf'];
$tm = $wpdb->prefix . 'at_fin_movimientos';
$tc = $wpdb->prefix . 'at_fin_cuentas';
register_shutdown_function(static function () use ($wpdb, &$slugs, $cache_original, $tm, $tc) {
	foreach ($slugs as $slug) {
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tc} WHERE slug = %s", $slug));
		if ($id) { $wpdb->delete($tm, ['cuenta_id' => $id]); $wpdb->delete($tc, ['id' => $id]); }
	}
	$wpdb->query("DELETE FROM {$tm} WHERE descripcion LIKE '[PRUEBA]%'");
	delete_transient('at_fin_tc_caida');
	if ($cache_original === null) { delete_option('at_fin_tc_cache'); } else { update_option('at_fin_tc_cache', $cache_original, false); }
});
delete_option('at_fin_tc_cache');
delete_transient('at_fin_tc_caida');
at_fin_migrar();

/** Llama a la API como lo haría n8n (JSON + cabecera de secreto). $cabecera: null = valor correcto, false = sin cabecera. */
function api(string $metodo, string $ruta, array $datos = [], $cabecera = null): WP_REST_Response {
	global $SECRETO;
	$r = new WP_REST_Request($metodo, '/automatiza-tech/v1' . $ruta);
	if ($cabecera !== false) { $r->set_header('x-at-secret', $cabecera ?? $SECRETO); }
	if ($metodo === 'GET') {
		foreach ($datos as $k => $v) { $r->set_param($k, $v); }
	} else {
		$r->set_header('content-type', 'application/json');
		$r->set_body(wp_json_encode($datos));
	}
	return rest_do_request($r);
}

$hoy = at_fin_hoy();
$ayer = (new DateTimeImmutable('yesterday', new DateTimeZone('America/Santiago')))->format('Y-m-d');
$mes_actual = substr($hoy, 0, 7);

// --- Autenticación
$base = ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Higgsfield', 'categoria' => 'IA', 'descripcion' => '[PRUEBA] suscripción ' . $marca, 'monto' => 12.5, 'moneda' => 'USD', 'registrado_por' => 'Prueba'];
ok(api('POST', '/finanzas/movimiento', $base, false)->get_status() === 401, 'movimiento sin cabecera → 401');
ok(api('POST', '/finanzas/movimiento', $base, 'clave-mala')->get_status() === 401, 'movimiento con cabecera mala → 401');
ok(api('GET', '/finanzas/resumen', [], false)->get_status() === 401, 'resumen sin cabecera → 401');
ok(api('GET', '/finanzas/resumen', [], 'clave-mala')->get_status() === 401, 'resumen con cabecera mala → 401');
ok(api('GET', '/finanzas/cuentas', [], false)->get_status() === 401, 'cuentas sin cabecera → 401');
ok(api('POST', '/finanzas/cuenta', ['slug' => 'x', 'nombre' => 'x'], false)->get_status() === 401, 'cuenta sin cabecera → 401');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tm} WHERE descripcion = '" . esc_sql($base['descripcion']) . "'") === 0, 'las peticiones sin autorización no guardan nada');
// at_fin_auth directo: sin secreto configurado o con cabecera no-cadena, falla cerrado.
$r_ok = new WP_REST_Request('GET', '/x'); $r_ok->set_header('x-at-secret', $SECRETO);
ok(at_fin_auth($r_ok) === true, 'at_fin_auth acepta el secreto correcto');
ok(at_fin_auth(new WP_REST_Request('GET', '/x')) === false, 'at_fin_auth rechaza sin cabecera');

// --- POST /finanzas/movimiento
$GLOBALS['mi_urls'] = [];
$r1 = api('POST', '/finanzas/movimiento', $base);
$d1 = $r1->get_data();
ok($r1->get_status() === 201 && ($d1['estado'] ?? '') === 'creado' && ($d1['id'] ?? 0) > 0, 'POST válido → 201 creado con id');
$r2 = api('POST', '/finanzas/movimiento', $base);
$d2 = $r2->get_data();
ok($r2->get_status() === 200 && ($d2['estado'] ?? '') === 'existente' && ($d2['id'] ?? 0) === ($d1['id'] ?? -1), 'el mismo POST → 200 existente, mismo id');
$conRef = array_merge($base, ['referencia' => 'ref-' . $marca]);
ok(api('POST', '/finanzas/movimiento', $conRef)->get_status() === 201, 'con referencia propia → 201');
$r409 = api('POST', '/finanzas/movimiento', array_merge($conRef, ['monto' => 99]));
ok($r409->get_status() === 409 && ($r409->get_data()['code'] ?? '') === 'duplicado', 'misma referencia con otro monto → 409 duplicado');
$r422 = api('POST', '/finanzas/movimiento', array_merge($base, ['monto' => 'abc']));
$d422 = $r422->get_data();
ok($r422->get_status() === 422 && is_array($d422['errores'] ?? null) && $d422['errores'] !== [], 'monto abc → 422 con lista de errores');
$r422b = api('POST', '/finanzas/movimiento', ['tipo' => 'cualquiera']);
ok($r422b->get_status() === 422 && count($r422b->get_data()['errores'] ?? []) >= 2, 'varios errores se devuelven todos');
$rx = api('POST', '/finanzas/movimiento', array_merge($base, ['tipo' => 'consumo', 'cuenta' => 'no-existe-' . $marca]));
ok($rx->get_status() === 422 && ($rx->get_data()['code'] ?? '') === 'cuenta_inexistente', 'cuenta inexistente → 422');
$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $d1['id']), ARRAY_A);
ok($fila && $fila['origen'] === 'api' && (float) $fila['tipo_cambio'] === 950.0, 'la API registra con origen api y el dólar del día');

// --- Cuentas: POST y GET
$rc = api('POST', '/finanzas/cuenta', ['slug' => strtoupper($marca) . '-HF', 'nombre' => '[PRUEBA] Higgsfield', 'proveedor' => 'Higgsfield', 'moneda' => 'USD']);
$dc = $rc->get_data();
ok($rc->get_status() === 201 && ($dc['slug'] ?? '') === $marca . '-hf' && ($dc['moneda'] ?? '') === 'USD' && ($dc['id'] ?? 0) > 0, 'POST cuenta nueva → 201 con la cuenta');
$rc2 = api('POST', '/finanzas/cuenta', ['slug' => $marca . '-hf', 'nombre' => '[PRUEBA] Higgsfield 2']);
ok($rc2->get_status() === 200 && ($rc2->get_data()['nombre'] ?? '') === '[PRUEBA] Higgsfield 2' && ($rc2->get_data()['id'] ?? 0) === $dc['id'], 'POST cuenta existente → 200, actualiza y conserva el id');
ok(api('POST', '/finanzas/cuenta', ['slug' => 'Mal Slug!', 'nombre' => 'x'])->get_status() === 422, 'cuenta con slug malo → 422');
$rrec = api('POST', '/finanzas/movimiento', ['tipo' => 'recarga', 'fecha' => $ayer, 'cuenta' => $marca . '-hf', 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] recarga', 'monto' => 20, 'moneda' => 'USD']);
$rcon = api('POST', '/finanzas/movimiento', ['tipo' => 'consumo', 'fecha' => $ayer, 'cuenta' => $marca . '-hf', 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] consumo', 'monto' => 3, 'moneda' => 'USD']);
ok($rrec->get_status() === 201 && $rcon->get_status() === 201, 'recarga y consumo con cuenta → 201');
$lista = api('GET', '/finanzas/cuentas')->get_data();
$mia = null;
foreach ($lista as $c) { if (($c['slug'] ?? '') === $marca . '-hf') { $mia = $c; } }
ok(is_array($lista) && array_is_list($lista) && $mia !== null, 'GET cuentas devuelve una lista con la cuenta de prueba');
ok($mia && abs($mia['saldo']['saldo'] - 17.0) < 0.0001, 'la cuenta trae su saldo (20 − 3)');

// --- Dictamen 1: la moneda del movimiento debe coincidir con la de la cuenta
$rm = api('POST', '/finanzas/movimiento', ['tipo' => 'recarga', 'fecha' => $ayer, 'cuenta' => $marca . '-hf', 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] recarga en pesos', 'monto' => 10000, 'moneda' => 'CLP']);
$dm = $rm->get_data();
ok($rm->get_status() === 422 && ($dm['code'] ?? '') === 'moneda_cuenta' && ($dm['errores'] ?? []) !== [], 'recarga en CLP a cuenta USD → 422 moneda_cuenta');
foreach (['consumo' => 5, 'ajuste' => -2] as $tipo => $monto) {
	$e = at_fin_registrar(['tipo' => $tipo, 'fecha' => $ayer, 'cuenta' => $marca . '-hf', 'proveedor' => 'Higgsfield', 'descripcion' => "[PRUEBA] {$tipo} en pesos", 'monto' => $monto, 'moneda' => 'CLP'], 'api');
	ok(is_wp_error($e) && $e->get_error_code() === 'moneda_cuenta' && ($e->get_error_data()['status'] ?? 0) === 422 && stripos($e->get_error_message(), 'moneda') !== false, "{$tipo} en CLP a cuenta USD → moneda_cuenta 422 con mensaje claro");
}
$ok_ajuste = at_fin_registrar(['tipo' => 'ajuste', 'fecha' => $ayer, 'cuenta' => $marca . '-hf', 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] ajuste en USD', 'monto' => -1, 'moneda' => 'USD'], 'api');
ok(is_array($ok_ajuste) && $ok_ajuste['estado'] === 'creado', 'ajuste en la moneda de la cuenta → creado');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tm} WHERE descripcion LIKE '[PRUEBA]%en pesos'") === 0, 'los movimientos con moneda distinta no se guardan');
$pago_clp = at_fin_registrar(['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Hostinger', 'descripcion' => '[PRUEBA] pago CLP ' . $marca, 'monto' => 15990, 'moneda' => 'CLP'], 'api');
ok(is_array($pago_clp), 'un pago sin cuenta no se ve afectado por la regla de moneda');
// Cambiar la moneda de una cuenta con movimientos se rechaza; sin movimientos se permite.
$cambio_mal = at_fin_cuenta_guardar(['slug' => $marca . '-hf', 'moneda' => 'CLP']);
ok(is_wp_error($cambio_mal) && $cambio_mal->get_error_code() === 'moneda_cuenta' && ($cambio_mal->get_error_data()['status'] ?? 0) === 422, 'cambiar la moneda de una cuenta con movimientos → 422 moneda_cuenta');
ok(at_fin_cuenta($marca . '-hf')['moneda'] === 'USD', 'la moneda de la cuenta no cambió');
ok(is_int(at_fin_cuenta_guardar(['slug' => $marca . '-hf', 'moneda' => 'USD', 'nombre' => '[PRUEBA] Higgsfield 3'])), 'repetir la misma moneda sí se permite');
$vacia = at_fin_cuenta_guardar(['slug' => $marca . '-clp', 'nombre' => '[PRUEBA] vacía', 'moneda' => 'USD']);
ok(is_int($vacia) && is_int(at_fin_cuenta_guardar(['slug' => $marca . '-clp', 'moneda' => 'CLP'])) && at_fin_cuenta($marca . '-clp')['moneda'] === 'CLP', 'una cuenta sin movimientos sí puede cambiar de moneda');

// --- Dictamen 2: el dólar de hoy sin publicar se usa pero no se guarda en la caché bajo la fecha de hoy
delete_option('at_fin_tc_cache');
$GLOBALS['mi_sin_valor'] = [(new DateTimeImmutable($hoy))->format('d-m-Y')];
$GLOBALS['mi_valor'] = 941.0;
$v_hoy = at_fin_tipo_cambio($hoy);
ok($v_hoy === 941.0, 'hoy sin publicar: devuelve el valor del último día publicado');
ok(!isset((get_option('at_fin_tc_cache') ?: [])[$hoy]), 'ese valor NO se guarda en la caché bajo la fecha de hoy');
$GLOBALS['mi_sin_valor'] = [];
$GLOBALS['mi_valor'] = 945.0;
ok(at_fin_tipo_cambio($hoy) === 945.0, 'cuando hoy se publica se consulta de nuevo y se obtiene su valor');
ok(((get_option('at_fin_tc_cache') ?: [])[$hoy] ?? null) === 945.0, 'y entonces sí se guarda en la caché');
// Una fecha pasada con valor de un día anterior (fin de semana) sí se guarda.
delete_option('at_fin_tc_cache');
$GLOBALS['mi_valor'] = 931.5;
$GLOBALS['mi_sin_valor'] = ['14-02-2026', '15-02-2026'];
ok(at_fin_tipo_cambio('2026-02-15') === 931.5 && (get_option('at_fin_tc_cache') ?: [])['2026-02-15'] === 931.5, 'una fecha pasada sin valor propio usa el anterior y sí se guarda');
$GLOBALS['mi_sin_valor'] = [];
$GLOBALS['mi_valor'] = 950.0;
delete_option('at_fin_tc_cache');

// --- Dictamen 3: un conflicto UNIQUE no filtra «Duplicate entry» ni deja cambiado suppress_errors
$prev = $wpdb->suppress_errors(false);
$ref_carrera = 'carrera-' . $marca;
$datos_carrera = ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Carrera', 'descripcion' => '[PRUEBA] carrera ' . $marca, 'monto' => 7, 'moneda' => 'CLP', 'referencia' => $ref_carrera];
$wpdb->show_errors(true);
$simular = static function (string $contiene, callable $conflicto) use ($wpdb): callable {
	$f = null;
	$f = static function ($sql) use (&$f, $contiene, $conflicto) {
		if (stripos($sql, 'INSERT INTO') === 0 && strpos($sql, $contiene) !== false) {
			remove_filter('query', $f, 1);
			$conflicto($sql);   // otra petición se adelanta con la misma fila
		}
		return $sql;
	};
	add_filter('query', $f, 1);
	return $f;
};
ob_start();
$f = $simular($tm, static function ($sql) use ($wpdb) { $wpdb->query($sql); });
$carrera = at_fin_registrar($datos_carrera, 'api');
remove_filter('query', $f, 1);
$salida = ob_get_clean();
ok(is_array($carrera) && $carrera['estado'] === 'existente', 'carrera en el insert del movimiento: responde existente');
ok(stripos($salida, 'duplicate') === false && stripos($salida, 'WordPress database error') === false, 'el error de la base no se imprime (movimiento)');
ok($wpdb->suppress_errors === false, 'suppress_errors vuelve a su valor anterior (false) tras el registro');
$slug_carrera = $marca . '-usd';
ob_start();
$f = $simular($tc, static function ($sql) use ($wpdb) { $wpdb->query($sql); });
$cuenta_carrera = at_fin_cuenta_guardar(['slug' => $slug_carrera, 'nombre' => '[PRUEBA] carrera', 'moneda' => 'USD']);
remove_filter('query', $f, 1);
$salida = ob_get_clean();
ok(is_int($cuenta_carrera) && $cuenta_carrera > 0 && at_fin_cuenta($slug_carrera)['id'] === $cuenta_carrera, 'carrera en el insert de la cuenta: devuelve la cuenta existente');
ok(stripos($salida, 'duplicate') === false && stripos($salida, 'WordPress database error') === false, 'el error de la base no se imprime (cuenta)');
ok($wpdb->suppress_errors === false, 'suppress_errors vuelve a su valor anterior (false) tras guardar la cuenta');
$wpdb->suppress_errors(true);
at_fin_registrar(array_merge($datos_carrera, ['referencia' => 'otra-' . $marca]), 'api');
ok($wpdb->suppress_errors === true, 'si estaba en true, sigue en true');
$wpdb->suppress_errors($prev);
$wpdb->show_errors(false);

// --- GET /finanzas/resumen
$GLOBALS['mi_urls'] = [];
$res = api('GET', '/finanzas/resumen', ['mes' => substr($ayer, 0, 7)]);
$dr = $res->get_data();
ok($res->get_status() === 200 && ($dr['mes'] ?? '') === substr($ayer, 0, 7), 'resumen con mes explícito → 200');
$esperado = at_fin_resumen_mes(at_fin_movimientos_mes(substr($ayer, 0, 7)));
ok(($dr['total_clp'] ?? null) === $esperado['total_clp'] && (array) ($dr['por_categoria'] ?? null) === $esperado['por_categoria'] && (array) ($dr['por_proveedor'] ?? null) === $esperado['por_proveedor'] && ($dr['pendientes'] ?? null) === $esperado['pendientes'], 'el resumen coincide con at_fin_resumen_mes');
ok((((array) $dr['por_categoria'])['IA'] ?? 0) >= (int) round(12.5 * 950), 'incluye el pago de IA de la prueba');
// Mes de prueba aislado: 2020-03, con un pago, una recarga, un consumo y un ajuste.
$f_prueba = '2020-03-10';
$tabla_cuentas_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tc} WHERE slug = %s", $marca . '-hf'));
foreach ([
	['pago', 'IA', 'Proveedor A', 100, null],
	['recarga', 'IA', 'Proveedor A', 50, $marca . '-hf'],
	['consumo', 'IA', 'Proveedor A', 30, $marca . '-hf'],
	['ajuste', 'IA', 'Proveedor A', -5, $marca . '-hf'],
] as [$tipo, $cat, $prov, $monto, $cuenta]) {
	$e = at_fin_registrar(['tipo' => $tipo, 'fecha' => $f_prueba, 'cuenta' => $cuenta ?? '', 'proveedor' => $prov, 'categoria' => $cat, 'descripcion' => "[PRUEBA] {$tipo} 2020", 'monto' => $monto, 'moneda' => 'USD', 'tipo_cambio' => 1000], 'api');
	if (is_wp_error($e)) { echo "FALLA preparar {$tipo}: " . $e->get_error_message() . "\n"; $GLOBALS['fallas']++; }
}
$r2020 = api('GET', '/finanzas/resumen', ['mes' => '2020-03'])->get_data();
ok(($r2020['total_clp'] ?? null) === 150000 && (array) ($r2020['por_categoria'] ?? null) === ['IA' => 150000], 'resumen suma solo pagos y recargas (100 + 50 USD × 1000), no consumo ni ajuste');
$comp = $r2020['comparacion'] ?? [];
ok(count($comp) === 6 && $comp[0]['mes'] === '2019-09' && $comp[5]['mes'] === '2020-02', 'comparacion: los 6 meses anteriores, del más antiguo al más reciente');
ok(array_reduce($comp, static fn($c, $m) => $c && array_keys($m) === ['mes', 'total_clp'] && $m['total_clp'] === 0, true), 'meses sin movimientos vienen en cero, con solo mes y total_clp');
$r2020b = api('GET', '/finanzas/resumen', ['mes' => '2020-05'])->get_data();
ok(($r2020b['comparacion'][4]['mes'] ?? '') === '2020-03' && ($r2020b['comparacion'][4]['total_clp'] ?? null) === 150000, 'la comparación trae el total real de un mes anterior');
$ant = api('GET', '/finanzas/resumen', ['mes' => '2020-01'])->get_data();
ok(($ant['comparacion'][0]['mes'] ?? '') === '2019-07' && ($ant['comparacion'][5]['mes'] ?? '') === '2019-12', 'la comparación cruza el cambio de año');
$def = api('GET', '/finanzas/resumen')->get_data();
ok(($def['mes'] ?? '') === $mes_actual && count($def['comparacion'] ?? []) === 6, 'sin mes: usa el mes actual de Chile');
foreach (['2026-13', '2026-00', '26-05', 'abc', '2026-5', '2026-05-01'] as $malo) {
	$rm_ = api('GET', '/finanzas/resumen', ['mes' => $malo]);
	ok($rm_->get_status() === 422 && ($rm_->get_data()['errores'] ?? []) !== [], "mes inválido «{$malo}» → 422");
}
ok((api('GET', '/finanzas/resumen', ['mes' => ''])->get_data()['mes'] ?? '') === $mes_actual, 'mes vacío (como lo manda n8n sin valor) → mes actual');

// --- Mes vacío: totales en cero y por_* como objetos JSON vacíos (nunca [])
$vacio = api('GET', '/finanzas/resumen', ['mes' => '2020-01']);
$dv = $vacio->get_data();
ok($vacio->get_status() === 200 && ($dv['total_clp'] ?? null) === 0 && ($dv['pendientes'] ?? null) === 0 && (array) ($dv['por_categoria'] ?? 1) === [] && (array) ($dv['por_proveedor'] ?? 1) === [], 'mes vacío: total_clp 0, pendientes 0, por_categoria y por_proveedor vacíos');
$json_vacio = wp_json_encode($dv);
ok(strpos($json_vacio, '"por_categoria":{}') !== false && strpos($json_vacio, '"por_proveedor":{}') !== false, 'mes vacío: por_categoria y por_proveedor serializan como {} y no como []');
$json_lleno = wp_json_encode(api('GET', '/finanzas/resumen', ['mes' => '2020-03'])->get_data());
ok(strpos($json_lleno, '"por_categoria":{"IA":150000}') !== false, 'mes con datos: por_categoria sigue siendo un objeto con sus claves');

// --- Monto con coma o con punto: el segundo registro es el mismo movimiento
$coma = array_merge($base, ['descripcion' => '[PRUEBA] monto coma ' . $marca, 'monto' => '1,26']);
$rcoma = api('POST', '/finanzas/movimiento', $coma);
$dcoma = $rcoma->get_data();
ok($rcoma->get_status() === 201 && ($dcoma['estado'] ?? '') === 'creado', 'monto "1,26" → 201 creado');
$rpunto = api('POST', '/finanzas/movimiento', array_merge($coma, ['monto' => '1.26']));
$dpunto = $rpunto->get_data();
ok($rpunto->get_status() === 200 && ($dpunto['estado'] ?? '') === 'existente' && ($dpunto['id'] ?? 0) === ($dcoma['id'] ?? -1), 'monto "1.26" → 200 existente, mismo id que "1,26"');
ok((float) $wpdb->get_var($wpdb->prepare("SELECT monto FROM {$tm} WHERE id = %d", $dcoma['id'])) === 1.26, 'el monto guardado es 1,26');

// --- Pendientes: el registro siguiente los completa y no altera su respuesta
delete_option('at_fin_tc_cache');
$GLOBALS['mi_modo'] = 'error';
$pen = at_fin_registrar(array_merge($base, ['descripcion' => '[PRUEBA] pendiente ' . $marca, 'fecha' => $ayer, 'monto' => 4]), 'api');
ok(is_array($pen) && $wpdb->get_var($wpdb->prepare("SELECT tc_estado FROM {$tm} WHERE id = %d", $pen['id'])) === 'pendiente', 'preparación: movimiento pendiente por fuente caída');
$GLOBALS['mi_modo'] = 'ok';
delete_transient('at_fin_tc_caida');   // pasaron los 10 minutos de pausa
$sig = api('POST', '/finanzas/movimiento', array_merge($base, ['descripcion' => '[PRUEBA] siguiente ' . $marca, 'monto' => 2]));
ok($sig->get_status() === 201 && ($sig->get_data()['estado'] ?? '') === 'creado' && array_keys($sig->get_data()) === ['id', 'estado'], 'el registro siguiente responde 201 {id, estado} sin más campos');
$fp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $pen['id']), ARRAY_A);
ok($fp['tc_estado'] === 'ok' && (int) $fp['monto_clp'] === 3800, 'y completó el tipo de cambio del pendiente anterior (4 × 950)');
// Una falla al completar no cambia la respuesta de la API.
$GLOBALS['mi_modo'] = 'error';
delete_option('at_fin_tc_cache');
$pen2 = at_fin_registrar(array_merge($base, ['descripcion' => '[PRUEBA] pendiente 2 ' . $marca, 'monto' => 3]), 'api');
$sig2 = api('POST', '/finanzas/movimiento', array_merge($base, ['descripcion' => '[PRUEBA] siguiente 2 ' . $marca, 'monto' => 1.5]));
ok($sig2->get_status() === 201 && ($sig2->get_data()['estado'] ?? '') === 'creado', 'con la fuente caída el registro igual responde 201 creado');
$GLOBALS['mi_modo'] = 'ok';
delete_transient('at_fin_tc_caida');

// --- Montos con punto de miles y pesos con decimales: 422 por la API, sin guardar nada
$miles = 'El monto parece llevar punto de miles: escríbelo sin puntos (15990) o con coma decimal.';
$antes_miles = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tm}");
$rm1 = api('POST', '/finanzas/movimiento', ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Hostinger', 'descripcion' => '[PRUEBA] miles ' . $marca, 'monto' => '15.990', 'moneda' => 'CLP']);
ok($rm1->get_status() === 422 && ($rm1->get_data()['code'] ?? '') === 'validacion' && in_array($miles, $rm1->get_data()['errores'] ?? [], true), '«15.990» CLP por la API → 422 con el aviso del punto de miles');
$rm2 = api('POST', '/finanzas/movimiento', ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] miles usd ' . $marca, 'monto' => '12.500', 'moneda' => 'USD']);
ok($rm2->get_status() === 422 && in_array($miles, $rm2->get_data()['errores'] ?? [], true), '«12.500» USD por la API → 422 (ambiguo en cualquier moneda)');
$rm3 = api('POST', '/finanzas/movimiento', ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Hostinger', 'descripcion' => '[PRUEBA] decimales clp ' . $marca, 'monto' => '12,5', 'moneda' => 'CLP']);
ok($rm3->get_status() === 422 && in_array('En pesos no hay decimales.', $rm3->get_data()['errores'] ?? [], true), '«12,5» CLP por la API → 422 «En pesos no hay decimales.»');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tm}") === $antes_miles, 'esos tres rechazos no guardaron nada');
$rm4 = api('POST', '/finanzas/movimiento', ['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'Hostinger', 'descripcion' => '[PRUEBA] sin punto ' . $marca, 'monto' => '15990', 'moneda' => 'CLP']);
ok($rm4->get_status() === 201, '«15990» CLP por la API → 201');
$rfa = api('POST', '/finanzas/movimiento', ['tipo' => 'pago', 'fecha' => '2019-12-31', 'proveedor' => 'Hostinger', 'descripcion' => '[PRUEBA] antigua ' . $marca, 'monto' => '5000', 'moneda' => 'CLP']);
ok($rfa->get_status() === 422 && in_array('Fecha demasiado antigua.', $rfa->get_data()['errores'] ?? [], true), 'una fecha anterior a 2020 por la API → 422 «Fecha demasiado antigua.»');

// --- Rutas registradas con el namespace esperado
$rutas = rest_get_server()->get_routes('automatiza-tech/v1');
foreach (['/automatiza-tech/v1/finanzas/movimiento', '/automatiza-tech/v1/finanzas/resumen', '/automatiza-tech/v1/finanzas/cuentas', '/automatiza-tech/v1/finanzas/cuenta'] as $ruta) {
	ok(isset($rutas[$ruta]), "ruta registrada: {$ruta}");
}
ok($GLOBALS['mi_otras'] === 0, 'ninguna otra petición HTTP salió en las pruebas');
fin();
