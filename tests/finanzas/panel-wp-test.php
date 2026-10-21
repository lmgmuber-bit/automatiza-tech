<?php
// Pruebas locales del panel de Finanzas AT en wp-admin (pestañas, pagos por mes, cuentas, cuadre y renovaciones). Nunca contra producción.
require __DIR__ . '/../retomar/wp-bootstrap.php';
exigir('at_fin_panel_pagos', 'at_fin_panel_cuentas', 'at_fin_panel_accion', 'at_fin_panel_tabs', 'at_fin_panel_procesar', 'at_fin_fmt_clp', 'at_fin_fmt_usd', 'at_fin_registrar', 'at_finanzas_admin_page');
global $wpdb;

// --- Simulación de mindicador (el bootstrap bloquea todo HTTP; este filtro corre después, con la misma prioridad máxima).
$GLOBALS['mi_modo'] = 'ok';      // ok | error
$GLOBALS['mi_valor'] = 950.0;
$GLOBALS['mi_otras'] = 0;
$GLOBALS['mi_consultas'] = 0;   // cuántas veces se pidió algo a mindicador
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://mindicador.cl/api/dolar/') !== 0) { $GLOBALS['mi_otras']++; return $pre; }
	$GLOBALS['mi_consultas']++;
	if ($GLOBALS['mi_modo'] === 'error') { return new WP_Error('prueba_local', 'mindicador simulado caído'); }
	$serie = [];
	if (preg_match('#/(\d{2})-(\d{2})-(\d{4})$#', $url, $m)) {
		$serie[] = ['fecha' => $m[3] . '-' . $m[2] . '-' . $m[1] . 'T03:00:00.000Z', 'valor' => $GLOBALS['mi_valor']];
	}
	return ['headers' => [], 'body' => wp_json_encode(['version' => '1.7.0', 'codigo' => 'dolar', 'serie' => $serie]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3);

$cache_original = get_option('at_fin_tc_cache', null);
$marca = 'prueba-' . strtolower(wp_generate_password(8, false, false));
$slug_hf = $marca . '-hf';
$slug_neg = $marca . '-neg';
$tm = $wpdb->prefix . 'at_fin_movimientos';
$tc = $wpdb->prefix . 'at_fin_cuentas';
$te = $wpdb->prefix . 'omnichannel_at_expenses';
$users = [];
register_shutdown_function(static function () use ($wpdb, $slug_hf, $slug_neg, $cache_original, $tm, $tc, $te, &$users, $marca) {
	foreach ([$slug_hf, $slug_neg] as $slug) {
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tc} WHERE slug = %s", $slug));
		if ($id) { $wpdb->delete($tm, ['cuenta_id' => $id]); $wpdb->delete($tc, ['id' => $id]); }
	}
	$wpdb->query("DELETE FROM {$tm} WHERE descripcion LIKE '[PRUEBA]%' OR proveedor LIKE 'PRUEBA-%'");
	$wpdb->query("DELETE FROM {$te} WHERE provider LIKE 'PRUEBA-%'");
	delete_transient('at_fin_tc_caida');
	if ($cache_original === null) { delete_option('at_fin_tc_cache'); } else { update_option('at_fin_tc_cache', $cache_original, false); }
	if ($users) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ($users as $id) { wp_delete_user($id); }
	}
});
delete_option('at_fin_tc_cache');
delete_transient('at_fin_tc_caida');
at_fin_migrar();

$admin = wp_insert_user(['user_login' => $marca, 'user_pass' => wp_generate_password(), 'user_email' => $marca . '@example.com', 'role' => 'administrator']);
$suscriptor = wp_insert_user(['user_login' => $marca . '-s', 'user_pass' => wp_generate_password(), 'user_email' => $marca . '-s@example.com', 'role' => 'subscriber']);
$users = [$admin, $suscriptor];
wp_set_current_user($admin);

function salida(callable $f): string { ob_start(); $f(); return (string) ob_get_clean(); }
function cantidad(string $donde, array $args = []): int { global $wpdb, $tm; return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tm} WHERE {$donde}", ...$args)); }
/** Id del último movimiento de una cuenta (lo que el formulario de cuadre manda como `base`). */
function base_cuenta(string $slug): int { global $wpdb, $tm, $tc; return (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(m.id), 0) FROM {$tm} m JOIN {$tc} c ON c.id = m.cuenta_id WHERE c.slug = %s", $slug)); }
/** Fragmento de la tarjeta de una cuenta en la pestaña Cuentas. */
function tarjeta(string $html, string $slug): string {
	$i = strpos($html, 'data-cuenta="' . $slug . '"');
	if ($i === false) { return ''; }
	$j = strpos($html, 'data-cuenta="', $i + 10);
	return substr($html, $i, $j === false ? null : $j - $i);
}

$hoy = at_fin_hoy();
$ayer = (new DateTimeImmutable('yesterday', new DateTimeZone('America/Santiago')))->format('Y-m-d');

// --- Formatos
ok(at_fin_fmt_clp(1234567) === '$1.234.567', 'pesos con separador de miles chileno');
ok(at_fin_fmt_clp(0) === '$0' && at_fin_fmt_clp(1500.4) === '$1.500', 'pesos sin decimales');
ok(at_fin_fmt_clp(-1500) === '-$1.500', 'pesos negativos con signo delante');
ok(at_fin_fmt_clp(-0.3) === '$0', 'un negativo que redondea a cero no lleva signo');
ok(at_fin_fmt_usd(12.5) === 'US$12,50' && at_fin_fmt_usd(1.26) === 'US$1,26' && at_fin_fmt_usd(1234.5) === 'US$1.234,50', 'dólares con 2 decimales, coma decimal y punto de miles');
ok(at_fin_fmt_usd(-3) === '-US$3,00', 'dólares negativos');

// --- Pestañas
$tabs = salida(static fn() => at_fin_panel_tabs('pagos'));
ok(strpos($tabs, 'tab=pagos') !== false && strpos($tabs, 'tab=cuentas') !== false && strpos($tabs, 'page=at-finanzas') !== false, 'la barra enlaza las pestañas pagos y cuentas');
ok(preg_match('/nav-tab-active[^>]*>[^<]*Pagos por mes/u', $tabs) === 1, 'la pestaña activa lleva nav-tab-active');
ok(strpos($tabs, 'Suscripciones') !== false && strpos($tabs, 'Cuentas') !== false, 'tres pestañas: Suscripciones, Pagos por mes y Cuentas');
ok(at_fin_panel_tab_actual('cuentas') === 'cuentas' && at_fin_panel_tab_actual('pagos') === 'pagos' && at_fin_panel_tab_actual('xyz') === 'suscripciones' && at_fin_panel_tab_actual(null) === 'suscripciones' && at_fin_panel_tab_actual(['pagos']) === 'suscripciones', 'tab desconocida o ausente → suscripciones');

// --- Cuentas de prueba
at_fin_cuenta_guardar(['slug' => $slug_hf, 'nombre' => '[PRUEBA] Cuenta HF', 'proveedor' => 'Higgsfield', 'moneda' => 'USD']);
at_fin_cuenta_guardar(['slug' => $slug_neg, 'nombre' => '[PRUEBA] Neg <b>x</b>', 'proveedor' => 'Otro', 'moneda' => 'USD']);
$r = at_fin_registrar(['tipo' => 'recarga', 'fecha' => $ayer, 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'categoria' => 'IA', 'descripcion' => '[PRUEBA] recarga', 'monto' => 40, 'moneda' => 'USD', 'tipo_cambio' => 1000], 'api');
$c = at_fin_registrar(['tipo' => 'consumo', 'fecha' => $ayer, 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] consumo', 'monto' => 5, 'moneda' => 'USD'], 'api');
at_fin_registrar(['tipo' => 'recarga', 'fecha' => $ayer, 'cuenta' => $slug_neg, 'proveedor' => 'Otro', 'descripcion' => '[PRUEBA] recarga neg', 'monto' => 2, 'moneda' => 'USD'], 'api');
at_fin_registrar(['tipo' => 'consumo', 'fecha' => $ayer, 'cuenta' => $slug_neg, 'proveedor' => 'Otro', 'descripcion' => '[PRUEBA] consumo neg', 'monto' => 5, 'moneda' => 'USD'], 'api');
ok(is_array($r) && is_array($c), 'preparación: recarga 40 y consumo 5 en la cuenta de prueba');

// --- Pestaña Cuentas
$html = salida('at_fin_panel_cuentas');
$t_hf = tarjeta($html, $slug_hf);
$t_neg = tarjeta($html, $slug_neg);
ok($t_hf !== '' && strpos($t_hf, '[PRUEBA] Cuenta HF') !== false, 'Cuentas: muestra el nombre de la cuenta');
ok(strpos($t_hf, 'US$35,00') !== false, 'Cuentas: muestra el saldo estimado (40 − 5)');
ok(strpos($t_hf, 'at-fin-negativo') === false, 'Cuentas: un saldo positivo no lleva la clase de saldo negativo');
ok(strpos($t_hf, '12,5') !== false && strpos($t_hf, 'at-fin-medidor') !== false, 'Cuentas: medidor con el % usado desde la última recarga (5 de 40 = 12,5 %)');
ok($t_neg !== '' && strpos($t_neg, 'at-fin-negativo') !== false && strpos($t_neg, '-US$3,00') !== false, 'Cuentas: saldo negativo (2 − 5) con la clase at-fin-negativo');
ok(strpos($html, '<b>x</b>') === false && strpos($html, '&lt;b&gt;x&lt;/b&gt;') !== false, 'Cuentas: el nombre se escapa');
ok(preg_match('/name="base" value="' . base_cuenta($slug_hf) . '"/', $t_hf) === 1 && base_cuenta($slug_hf) > 0, 'Cuentas: el formulario de cuadre manda `base` = id del último movimiento de la cuenta');
ok(preg_match('/name="base" value="0"/', $t_neg) === 0, 'Cuentas: la base de cada cuenta es la suya');
ok(strpos($t_hf, 'name="accion" value="cuadrar"') !== false && strpos($t_hf, 'name="saldo_real"') !== false && strpos($t_hf, 'name="_wpnonce"') !== false, 'Cuentas: formulario de cuadre con acción, saldo real y nonce');
ok(strpos($html, 'at-fin-scroll') !== false, 'Cuentas: las tablas van dentro de un contenedor con scroll horizontal');
ok(strpos($html, 'at-fin-tabla-ancha') === false, 'Cuentas: los últimos movimientos de cada tarjeta son tablas chicas (sin la clase ancha)');

// --- Cuadrar
$cuadre = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '30']);
ok($cuadre['ok'] === true && strpos($cuadre['mensaje'], '-US$5,00') !== false, 'cuadrar 30 con estimado 35: ok y dice el ajuste (−US$5,00)');
$aj = $wpdb->get_row($wpdb->prepare("SELECT m.* FROM {$tm} m JOIN {$tc} c ON c.id = m.cuenta_id WHERE c.slug = %s AND m.tipo = 'ajuste'", $slug_hf), ARRAY_A);
ok($aj && (float) $aj['monto'] === -5.0 && $aj['moneda'] === 'USD' && $aj['origen'] === 'panel' && $aj['registrado_por'] === 'panel' && $aj['fecha'] === $hoy, 'registra un ajuste de −5 USD, origen panel, de hoy');
ok(strpos((string) $aj['notas'], 'saldo real del panel: 30') !== false, 'la nota dice «saldo real del panel: 30»');
ok(abs(at_fin_cuenta($slug_hf) !== null ? at_fin_saldo(at_fin_movimientos_cuenta(at_fin_cuenta($slug_hf)['id']))['saldo'] - 30.0 : 99) < 0.0001, 'el saldo de la cuenta queda en 30');
$n = cantidad('1=1');
$igual = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '30,00']);
ok($igual['ok'] === true && cantidad('1=1') === $n, 'cuadrar con diferencia 0: ok y no registra nada');
ok(stripos($igual['mensaje'], 'coincide') !== false, 'y lo explica («ya coincide»)');
// Un segundo cuadre del mismo día con la misma diferencia es otro ajuste (no un duplicado del primero).
at_fin_registrar(['tipo' => 'consumo', 'fecha' => $hoy, 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] consumo de hoy', 'monto' => 5, 'moneda' => 'USD'], 'api');
$cuadre2 = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '20']);
ok($cuadre2['ok'] === true && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tm} m JOIN {$tc} c ON c.id = m.cuenta_id WHERE c.slug = %s AND m.tipo = 'ajuste'", $slug_hf)) === 2, 'dos cuadres del mismo día con la misma diferencia (−5) registran dos ajustes');
ok(abs(at_fin_saldo(at_fin_movimientos_cuenta(at_fin_cuenta($slug_hf)['id']))['saldo'] - 20.0) < 0.0001, 'y el saldo queda en 20');
// Idempotencia: la referencia es determinista (cuenta + último movimiento + saldo real) y `base` evita cuadrar sobre una cuenta que cambió.
$base_a = base_cuenta($slug_hf);
$post_a = ['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '12', 'base' => (string) $base_a];
$ajustes_antes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tm} m JOIN {$tc} c ON c.id = m.cuenta_id WHERE c.slug = %s AND m.tipo = 'ajuste'", $slug_hf));
$primero = at_fin_panel_accion($post_a);
$segundo = at_fin_panel_accion($post_a);
$ajustes_ahora = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tm} m JOIN {$tc} c ON c.id = m.cuenta_id WHERE c.slug = %s AND m.tipo = 'ajuste'", $slug_hf));
ok($primero['ok'] === true && $ajustes_ahora === $ajustes_antes + 1, 'cuadrar con base: el primer POST registra el ajuste');
ok($ajustes_ahora === $ajustes_antes + 1 && ($segundo['nivel'] ?? '') === 'warning', 'dos POST idénticos seguidos dejan un solo ajuste (el segundo ve que la cuenta cambió)');
ok(wp_json_encode($post_a) !== '' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tm} WHERE referencia = %s", 'cuadre-' . $slug_hf . '-' . $base_a . '-12')) === 1, 'la referencia del ajuste es cuadre-<slug>-<base>-<saldo real>');
// Carrera: dos POST con la misma base que pasan la revisión a la vez chocan en el UNIQUE y el segundo devuelve el mismo movimiento.
$base_b = base_cuenta($slug_hf);
$fila_a = at_fin_registrar(['tipo' => 'ajuste', 'fecha' => $hoy, 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'descripcion' => 'Cuadre de saldo (panel)', 'monto' => 3, 'moneda' => 'USD', 'referencia' => 'cuadre-' . $slug_hf . '-' . $base_b . '-15', 'registrado_por' => 'panel'], 'panel');
$fila_b = at_fin_registrar(['tipo' => 'ajuste', 'fecha' => $hoy, 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'descripcion' => 'Cuadre de saldo (panel)', 'monto' => 3, 'moneda' => 'USD', 'referencia' => 'cuadre-' . $slug_hf . '-' . $base_b . '-15', 'registrado_por' => 'panel'], 'panel');
ok(is_array($fila_a) && is_array($fila_b) && $fila_b['estado'] === 'existente' && $fila_b['id'] === $fila_a['id'], 'misma referencia en una carrera: el segundo devuelve el mismo movimiento (existente), sin duplicar');
// `base` viejo después de un movimiento nuevo: no registra y avisa.
$viejo = base_cuenta($slug_hf);
at_fin_registrar(['tipo' => 'consumo', 'fecha' => $hoy, 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] consumo después de abrir', 'monto' => 1, 'moneda' => 'USD'], 'api');
$n = cantidad('1=1');
$rancio = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '99', 'base' => (string) $viejo]);
ok($rancio['ok'] === false && ($rancio['nivel'] ?? '') === 'warning' && $rancio['mensaje'] === 'La cuenta cambió desde que abriste la página. Revisa el saldo y vuelve a cuadrar.' && cantidad('1=1') === $n, '`base` viejo tras un movimiento nuevo: no registra nada y muestra el aviso');
$rancio0 = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '99', 'base' => '0']);
ok($rancio0['ok'] === false && cantidad('1=1') === $n, '`base` 0 con una cuenta que ya tiene movimientos también se rechaza');
$est = at_fin_saldo(at_fin_movimientos_cuenta(at_fin_cuenta($slug_hf)['id']))['saldo'];
$cero_dif = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => (string) $est, 'base' => (string) base_cuenta($slug_hf)]);
ok($cero_dif['ok'] === true && cantidad('1=1') === $n && ($cero_dif['nivel'] ?? 'x') === 'x', 'con base vigente y diferencia 0 sigue sin registrar nada');
at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '20']);   // deja la cuenta en 20, como la esperan las pruebas siguientes
$sube = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '25,5']);
ok($sube['ok'] === true && abs(at_fin_saldo(at_fin_movimientos_cuenta(at_fin_cuenta($slug_hf)['id']))['saldo'] - 25.5) < 0.0001 && strpos($sube['mensaje'], 'US$5,50') !== false, 'cuadrar hacia arriba (25,5): ajuste positivo de US$5,50');
$cero = at_fin_panel_accion(['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '0']);
ok($cero['ok'] === true && abs(at_fin_saldo(at_fin_movimientos_cuenta(at_fin_cuenta($slug_hf)['id']))['saldo']) < 0.0001, 'un saldo real de 0 es válido');
$n = cantidad('1=1');
foreach ([['saldo_real' => 'abc'], ['saldo_real' => ''], ['cuenta' => 'no-existe-' . $marca, 'saldo_real' => '10'], ['cuenta' => '', 'saldo_real' => '10']] as $malo) {
	$res = at_fin_panel_accion($malo + ['accion' => 'cuadrar', 'cuenta' => $slug_hf]);
	ok($res['ok'] === false && $res['mensaje'] !== '', 'cuadrar inválido (' . json_encode($malo, JSON_UNESCAPED_UNICODE) . ') → ok false con mensaje');
}
ok(cantidad('1=1') === $n, 'los cuadres inválidos no registran nada');
$desconocida = at_fin_panel_accion(['accion' => 'borrar-todo']);
ok($desconocida['ok'] === false && at_fin_panel_accion([])['ok'] === false, 'una acción desconocida o ausente → ok false');

// --- Registrar desde el panel
$reg = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'categoria' => 'IA', 'descripcion' => '[PRUEBA] pago panel ' . $marca, 'monto' => '1,26', 'moneda' => 'USD']);
ok($reg['ok'] === true && strpos($reg['mensaje'], 'US$1,26') !== false, 'registrar "1,26" USD: ok y muestra «US$1,26» tal como se interpretó');
ok(strpos($reg['mensaje'], 'Al dólar de $950,00') !== false, 'el mensaje muestra el dólar aplicado con 2 decimales ($950,00)');
$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE descripcion = %s", '[PRUEBA] pago panel ' . $marca), ARRAY_A);
ok($fila && $fila['origen'] === 'panel' && $fila['registrado_por'] === 'panel' && $fila['fecha'] === $hoy && (float) $fila['monto'] === 1.26 && (float) $fila['tipo_cambio'] === 950.0, 'queda con origen panel, fecha de hoy y el dólar del día');
$otra = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'categoria' => 'IA', 'descripcion' => '[PRUEBA] pago panel ' . $marca, 'monto' => '1.26', 'moneda' => 'USD']);
ok($otra['ok'] === true && stripos($otra['mensaje'], 'ya estaba') !== false && cantidad('descripcion = %s', ['[PRUEBA] pago panel ' . $marca]) === 1, 'repetir el mismo pago no lo duplica y lo avisa');
$punto = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] punto', 'monto' => '1.500', 'moneda' => 'CLP']);
ok($punto['ok'] === false && strpos($punto['mensaje'], 'El monto parece llevar punto de miles: escríbelo sin puntos (15990) o con coma decimal.') !== false && cantidad('descripcion = %s', ['[PRUEBA] punto']) === 0, 'registrar "1.500" en pesos: error visible por el punto de miles y no se guarda');
$decimales = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] decimales', 'monto' => '12,5', 'moneda' => 'CLP']);
ok($decimales['ok'] === false && strpos($decimales['mensaje'], 'En pesos no hay decimales.') !== false && cantidad('descripcion = %s', ['[PRUEBA] decimales']) === 0, 'registrar "12,5" en pesos: error «En pesos no hay decimales.»');
$grande = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] grande', 'monto' => '15990', 'moneda' => 'CLP']);
ok($grande['ok'] === true && strpos($grande['mensaje'], '$15.990') !== false, 'registrar 15990 en pesos: el mensaje dice $15.990');
$GLOBALS['mi_modo'] = 'error';
delete_option('at_fin_tc_cache');
$sin_tc = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] sin dólar ' . $marca, 'monto' => '2', 'moneda' => 'USD']);
$GLOBALS['mi_modo'] = 'ok';
ok(at_fin_registro_pendiente() === true, 'registrar un pendiente deja anotado que en esta petición hubo uno');
// En esa misma petición abrir «Pagos por mes» no insiste con la fuente aunque ya se hubiera recuperado (la pausa venció).
delete_transient('at_fin_tc_caida');
$antes_consultas = $GLOBALS['mi_consultas'];
salida(static fn() => at_fin_panel_pagos(substr($hoy, 0, 7)));
$estado_sin_tc = $wpdb->get_var($wpdb->prepare("SELECT tc_estado FROM {$tm} WHERE descripcion = %s", '[PRUEBA] sin dólar ' . $marca));
ok($GLOBALS['mi_consultas'] === $antes_consultas && $estado_sin_tc === 'pendiente', 'misma petición con un pendiente recién registrado: la pestaña no llama a la fuente y el pendiente sigue pendiente');
at_fin_registro_pendiente(false);   // la petición siguiente: ya no hay marca
ok($sin_tc['ok'] === true && strpos($sin_tc['mensaje'], 'Se guardó sin el valor en pesos; se completará solo cuando la fuente del dólar responda.') !== false && strpos($sin_tc['mensaje'], 'al abrir') === false, 'registrar con la fuente del dólar caída: dice que se completará solo, sin mandar a abrir la pestaña');
$mala = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] mala', 'monto' => 'abc', 'moneda' => 'USD']);
ok($mala['ok'] === false && stripos($mala['mensaje'], 'monto') !== false, 'monto inválido → ok false con el motivo');
$moneda = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'recarga', 'cuenta' => $slug_hf, 'proveedor' => 'Higgsfield', 'descripcion' => '[PRUEBA] moneda', 'monto' => '10000', 'moneda' => 'CLP']);
ok($moneda['ok'] === false && stripos($moneda['mensaje'], 'moneda') !== false, 'recarga en CLP a cuenta USD → ok false (moneda)');
$futura = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] futuro', 'fecha' => '2999-01-01', 'monto' => '5', 'moneda' => 'CLP']);
ok($futura['ok'] === false, 'fecha futura → ok false');
$con_fecha = at_fin_panel_accion(['accion' => 'registrar', 'tipo' => 'pago', 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] fecha ' . $marca, 'fecha' => $ayer, 'monto' => '7', 'moneda' => 'CLP', 'registrado_por' => 'otro', 'origen' => 'api']);
$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE descripcion = %s", '[PRUEBA] fecha ' . $marca), ARRAY_A);
ok($con_fecha['ok'] === true && $fila['fecha'] === $ayer && $fila['origen'] === 'panel' && $fila['registrado_por'] === 'panel', 'respeta la fecha indicada y no deja que el formulario cambie origen ni quién registró');

// --- Pestaña Pagos por mes
at_fin_registrar(['tipo' => 'pago', 'fecha' => '2020-06-10', 'proveedor' => 'PRUEBA-Prov <i>z</i>', 'categoria' => 'IA', 'descripcion' => '[PRUEBA] junio <script>alert(1)</script>', 'monto' => 100, 'moneda' => 'USD', 'tipo_cambio' => 1000], 'api');
at_fin_registrar(['tipo' => 'pago', 'fecha' => '2020-06-12', 'proveedor' => 'PRUEBA-Host', 'categoria' => 'hosting', 'descripcion' => '[PRUEBA] hosting junio', 'monto' => 20000, 'moneda' => 'CLP'], 'api');
at_fin_registrar(['tipo' => 'consumo', 'fecha' => '2020-06-15', 'cuenta' => $slug_hf, 'proveedor' => 'PRUEBA-Prov', 'descripcion' => '[PRUEBA] consumo junio', 'monto' => 1, 'moneda' => 'USD'], 'api');
$GLOBALS['mi_modo'] = 'error';
at_fin_registrar(['tipo' => 'pago', 'fecha' => '2020-06-20', 'proveedor' => 'PRUEBA-Pend', 'descripcion' => '[PRUEBA] pendiente junio', 'monto' => 3, 'moneda' => 'USD'], 'api');
// La fuente sigue caída mientras se muestran estos meses: abrir la pestaña reintenta los pendientes y, si pudiera, los completaría.
$mensual = salida(static fn() => at_fin_panel_pagos('2020-06'));
ok(strpos($mensual, '$120.000') !== false, 'Pagos: muestra el total del mes en pesos ($100.000 + $20.000)');
ok(strpos($mensual, 'junio 2020') !== false, 'Pagos: nombra el mes en español');
ok(strpos($mensual, 'Hosting') !== false && strpos($mensual, '$20.000') !== false && strpos($mensual, '$100.000') !== false, 'Pagos: desglose por categoría con nombre legible y por proveedor');
ok(strpos($mensual, '<script>alert(1)</script>') === false && strpos($mensual, '&lt;script&gt;') !== false && strpos($mensual, '<i>z</i>') === false, 'Pagos: todo el texto del usuario se escapa');
ok(strpos($mensual, 'pendiente') !== false, 'Pagos: marca el movimiento con dólar pendiente');
ok(strpos($mensual, '1 pago o recarga en dólares espera su valor en pesos') !== false && strpos($mensual, 'sin tipo de cambio') === false, 'Pagos: avisa cuántos pagos o recargas en dólares esperan el valor en pesos');
ok(strpos($mensual, '$1.000,00') !== false, 'Pagos: el dólar de cada movimiento se muestra con 2 decimales ($1.000,00)');
ok(strpos($mensual, '[PRUEBA] consumo junio') !== false, 'Pagos: lista también el consumo (aunque no sume)');
ok(strpos($mensual, 'at-fin-scroll') !== false, 'Pagos: las tablas van dentro de un contenedor con scroll horizontal');
ok(strpos($mensual, 'name="accion" value="registrar"') !== false && strpos($mensual, 'name="_wpnonce"') !== false && strpos($mensual, 'name="monto"') !== false, 'Pagos: formulario de registro con acción y nonce');
ok(strpos($mensual, '2020-05') !== false || strpos($mensual, 'mayo 2020') !== false, 'Pagos: trae la comparación con los meses anteriores');
ok(substr_count($mensual, 'at-fin-tabla-ancha') === 1 && preg_match('/<table class="widefat striped at-fin-tabla-ancha">\s*<thead>/', $mensual) === 1, 'Pagos: solo la tabla de movimientos lleva la clase ancha; por categoría, por proveedor y comparación no');
ok(strpos(salida('at_fin_panel_estilos'), '.at-fin-scroll table{min-width') === false && strpos(salida('at_fin_panel_estilos'), 'table.at-fin-tabla-ancha{min-width') !== false, 'CSS: el ancho mínimo ya no es de todas las tablas, solo de la clase ancha');
// Pendientes del mes: cuentan solo pagos y recargas; un consumo pendiente no da a entender que el total esté incompleto.
at_fin_registrar(['tipo' => 'pago', 'fecha' => '2020-08-10', 'proveedor' => 'PRUEBA-Pend', 'descripcion' => '[PRUEBA] pago pendiente agosto', 'monto' => 2, 'moneda' => 'USD'], 'api');
at_fin_registrar(['tipo' => 'recarga', 'fecha' => '2020-08-11', 'cuenta' => $slug_hf, 'proveedor' => 'PRUEBA-Pend', 'descripcion' => '[PRUEBA] recarga pendiente agosto', 'monto' => 2, 'moneda' => 'USD'], 'api');
at_fin_registrar(['tipo' => 'consumo', 'fecha' => '2020-09-10', 'cuenta' => $slug_hf, 'proveedor' => 'PRUEBA-Pend', 'descripcion' => '[PRUEBA] consumo pendiente septiembre', 'monto' => 1, 'moneda' => 'USD'], 'api');
at_fin_registrar(['tipo' => 'consumo', 'fecha' => '2020-08-12', 'cuenta' => $slug_hf, 'proveedor' => 'PRUEBA-Pend', 'descripcion' => '[PRUEBA] consumo pendiente agosto', 'monto' => 1, 'moneda' => 'USD'], 'api');
$agosto = salida(static fn() => at_fin_panel_pagos('2020-08'));
ok(strpos($agosto, '2 pagos o recargas en dólares esperan su valor en pesos') !== false, 'Pagos: con dos pagos/recargas pendientes (y un consumo pendiente) dice 2, sin contar el consumo');
$septiembre = salida(static fn() => at_fin_panel_pagos('2020-09'));
ok(strpos($septiembre, 'esperan su valor') === false && strpos($septiembre, 'espera su valor') === false && strpos($septiembre, 'notice-warning') === false, 'Pagos: un mes solo con un consumo pendiente no muestra el aviso de pendientes');
$julio = salida(static fn() => at_fin_panel_pagos('2020-07'));
ok(strpos($julio, 'sin datos') !== false, 'Pagos: un mes vacío dice «sin datos»');
ok(strpos($julio, '$120.000') !== false, 'Pagos: la comparación de julio muestra el total de junio');
ok(strpos($mensual, 'sin datos') === false, 'Pagos: un mes con movimientos no dice «sin datos»');
$GLOBALS['mi_modo'] = 'ok';
delete_transient('at_fin_tc_caida');
$mes_mal = salida(static fn() => at_fin_panel_pagos('basura'));
ok(strpos($mes_mal, 'Gasto de ' . at_fin_mes_es(substr($hoy, 0, 7))) !== false && strpos($mes_mal, 'name="mes" value="' . substr($hoy, 0, 7) . '"') !== false, 'Pagos: un mes inválido muestra el mes actual (título y selector)');
// Abrir la pestaña completa los dólares pendientes.
delete_option('at_fin_tc_cache');
$GLOBALS['mi_modo'] = 'error';
$pen = at_fin_registrar(['tipo' => 'pago', 'fecha' => $ayer, 'proveedor' => 'PRUEBA-Pend2', 'descripcion' => '[PRUEBA] pendiente ayer ' . $marca, 'monto' => 4, 'moneda' => 'USD'], 'api');
$GLOBALS['mi_modo'] = 'ok';
delete_transient('at_fin_tc_caida');
at_fin_registro_pendiente(false);   // petición nueva: la fuente ya respondería
ok(is_array($pen) && $wpdb->get_var($wpdb->prepare("SELECT tc_estado FROM {$tm} WHERE id = %d", $pen['id'])) === 'pendiente', 'preparación: pago pendiente por fuente caída');
salida(static fn() => at_fin_panel_pagos(substr($ayer, 0, 7)));
$fp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE id = %d", $pen['id']), ARRAY_A);
ok($fp['tc_estado'] === 'ok' && (int) $fp['monto_clp'] === 3800, 'abrir «Pagos por mes» completó el tipo de cambio pendiente (4 × 950)');
$GLOBALS['mi_modo'] = 'error';
ok(salida(static fn() => at_fin_panel_pagos('2020-06')) !== '', 'con la fuente caída la pestaña igual se muestra');
$GLOBALS['mi_modo'] = 'ok';
delete_transient('at_fin_tc_caida');

// --- Procesar el formulario (nonce y permisos)
$_SERVER['REQUEST_METHOD'] = 'POST';
$post_base = ['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '3'];
$_POST = $post_base;
$sin_nonce = at_fin_panel_procesar();
ok(is_array($sin_nonce) && $sin_nonce['ok'] === false, 'sin nonce no se hace nada');
$_POST = $post_base + ['_wpnonce' => 'basura'];
ok(at_fin_panel_procesar()['ok'] === false, 'con nonce falso no se hace nada');
$n = cantidad('1=1');
$_POST = $post_base + ['_wpnonce' => wp_create_nonce('at_fin_panel')];
wp_set_current_user($suscriptor);
$_POST['_wpnonce'] = wp_create_nonce('at_fin_panel');
$sin_permiso = at_fin_panel_procesar();
ok($sin_permiso['ok'] === false && cantidad('1=1') === $n, 'sin manage_options no se hace nada, aunque el nonce sea válido');
wp_set_current_user($admin);
$_POST = wp_slash($post_base + ['_wpnonce' => wp_create_nonce('at_fin_panel')]);
$bien = at_fin_panel_procesar();
ok(is_array($bien) && $bien['ok'] === true && cantidad('1=1') === $n + 1, 'con nonce y permisos ejecuta la acción');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = ['accion' => 'cuadrar', 'cuenta' => $slug_hf, 'saldo_real' => '1', '_wpnonce' => wp_create_nonce('at_fin_panel')];
ok(at_fin_panel_procesar() === null, 'una petición GET no ejecuta nada');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
ok(at_fin_panel_procesar() === null, 'un POST sin acción del panel se ignora (lo atiende el catálogo)');
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

// --- Página completa: pestañas y regresión del catálogo
$_GET = [];
$pagina = salida('at_finanzas_admin_page');
ok(strpos($pagina, 'Gasto mensual') !== false, 'regresión: sin tab, la página de suscripciones sigue mostrando «Gasto mensual»');
ok(strpos($pagina, 'nav-tab-wrapper') !== false && strpos($pagina, 'tab=pagos') !== false, 'y trae la barra de pestañas');
$_GET = ['tab' => 'suscripciones'];
ok(strpos(salida('at_finanzas_admin_page'), 'Gasto mensual') !== false, 'tab=suscripciones es la de siempre');
$_GET = ['tab' => 'xyz'];
ok(strpos(salida('at_finanzas_admin_page'), 'Gasto mensual') !== false, 'una tab desconocida cae en suscripciones');
$_GET = ['tab' => 'cuentas'];
$pg_cuentas = salida('at_finanzas_admin_page');
ok(strpos($pg_cuentas, '[PRUEBA] Cuenta HF') !== false && strpos($pg_cuentas, 'Gasto mensual') === false && strpos($pg_cuentas, 'Finanzas AT') !== false, 'tab=cuentas dibuja las cuentas, con el título, y no el catálogo');
$_GET = ['tab' => 'pagos', 'mes' => '2020-06'];
$pg_pagos = salida('at_finanzas_admin_page');
ok(strpos($pg_pagos, '$122.850') !== false && strpos($pg_pagos, 'Gasto mensual') === false, 'tab=pagos&mes=2020-06 dibuja los pagos de ese mes (con el dólar pendiente ya completado: 120.000 + 3 × 950)');
wp_set_current_user($suscriptor);
$muerto = false;
add_filter('wp_die_handler', static function () use (&$muerto) { return static function () use (&$muerto) { $muerto = true; throw new RuntimeException('wp_die'); }; });
try { salida('at_finanzas_admin_page'); } catch (RuntimeException $e) { }
ok($muerto, 'sin manage_options, la página corta con wp_die (también las pestañas nuevas)');
wp_set_current_user($admin);
$_GET = [];

// --- Renovaciones: «Sí, lo pagué» registra el pago real
$fin = at_finanzas_get_controller();
$hace_2_meses = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->modify('-2 months')->format('Y-m-d');
function renovar(int $id, string $accion = 'renew_paid'): string {
	$_GET = ['page' => 'at-finanzas', 'action' => $accion, 'id' => (string) $id, '_wpnonce' => wp_create_nonce('at_fin_renew_' . $id)];
	$out = salida('at_finanzas_admin_page');
	$_GET = [];
	return $out;
}
$crear = static function (array $d) use ($fin): int { $r = $fin->create_expense($d + ['period' => 'monthly', 'start_date' => '', 'active' => true, 'notes' => '']); return (int) ($r['id'] ?? 0); };
$id_clp = $crear(['category' => 'hosting', 'provider' => 'PRUEBA-Hostinger ' . $marca, 'description' => 'plan', 'amount' => 15990, 'currency' => 'CLP', 'start_date' => $hace_2_meses]);
ok($id_clp > 0, 'preparación: gasto de catálogo mensual vencido');
$sal = renovar($id_clp);
$ref = 'renov-' . $id_clp . '-' . substr($hoy, 0, 7);
$mov = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE referencia = %s", $ref), ARRAY_A);
ok(strpos($sal, 'Renovación registrada') !== false, 'la renovación sigue funcionando y lo dice');
ok($mov && $mov['tipo'] === 'pago' && (float) $mov['monto'] === 15990.0 && $mov['moneda'] === 'CLP' && $mov['proveedor'] === 'PRUEBA-Hostinger ' . $marca && $mov['categoria'] === 'hosting', 'registra un pago con proveedor, categoría, monto y moneda del catálogo');
ok($mov && (int) $mov['gasto_id'] === $id_clp && $mov['origen'] === 'panel' && $mov['registrado_por'] === 'panel' && $mov['fecha'] === $hoy && (int) $mov['monto_clp'] === 15990, 'con gasto_id del catálogo, origen panel, registrado por panel y la fecha de hoy (Chile)');
ok(strpos($sal, 'Pagos por mes') !== false, 'y avisa que el pago quedó en «Pagos por mes»');
$n = cantidad('referencia = %s', [$ref]);
renovar($id_clp);
ok($n === 1 && cantidad('referencia = %s', [$ref]) === 1, 'repetir el clic no duplica el pago (misma referencia)');
$id_usd = $crear(['category' => 'apis_ia', 'provider' => 'PRUEBA-OpenAI ' . $marca, 'description' => '', 'amount' => 12.5, 'currency' => 'USD', 'start_date' => $hace_2_meses]);
renovar($id_usd);
$mov = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tm} WHERE referencia = %s", 'renov-' . $id_usd . '-' . substr($hoy, 0, 7)), ARRAY_A);
ok($mov && (float) $mov['monto'] === 12.5 && $mov['moneda'] === 'USD' && (int) $mov['monto_clp'] === 11875, 'renovación en USD: registra 12,5 USD con el dólar del día (11.875 CLP)');
$id_skip = $crear(['category' => 'hosting', 'provider' => 'PRUEBA-NoRenueva ' . $marca, 'description' => '', 'amount' => 5000, 'currency' => 'CLP', 'start_date' => $hace_2_meses]);
$sal = renovar($id_skip, 'renew_skip');
ok(cantidad('referencia = %s', ['renov-' . $id_skip . '-' . substr($hoy, 0, 7)]) === 0 && strpos($sal, 'no renovado') !== false, '«No / cancelado» no registra ningún pago');
// Si el registro del pago falla, la renovación igual se cumple y la página avisa.
$id_cero = $crear(['category' => 'hosting', 'provider' => 'PRUEBA-Cero ' . $marca, 'description' => '', 'amount' => 0, 'currency' => 'CLP', 'start_date' => $hace_2_meses]);
$sal = renovar($id_cero);
ok(strpos($sal, 'Renovación registrada') !== false && strpos($sal, 'no se pudo registrar') !== false, 'si el pago no se puede registrar (monto 0) la renovación igual se completa y se avisa');
ok(cantidad('referencia = %s', ['renov-' . $id_cero . '-' . substr($hoy, 0, 7)]) === 0, 'y no queda ningún movimiento a medias');
$activos = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$te} WHERE provider = %s AND active = 1", 'PRUEBA-Cero ' . $marca));
ok($activos === 1, 'el catálogo renovó: queda el período nuevo activo');

ok($GLOBALS['mi_otras'] === 0, 'ninguna otra petición HTTP salió en las pruebas');
fin();
