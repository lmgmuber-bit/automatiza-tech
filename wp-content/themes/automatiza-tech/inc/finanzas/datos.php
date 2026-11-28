<?php
/** Finanzas AT: tablas, cuentas y movimientos (acceso a la base). Las reglas de cálculo viven en puras.php. */

if (!defined('ABSPATH')) exit;

/** Fecha de hoy en Chile ('Y-m-d'). */
function at_fin_hoy(): string {
	return (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d');
}

/** Fecha y hora actuales en Chile para created_at / updated_at. */
function at_fin_ahora(): string {
	return (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s');
}

function at_fin_tabla_cuentas(): string { global $wpdb; return $wpdb->prefix . 'at_fin_cuentas'; }
function at_fin_tabla_movimientos(): string { global $wpdb; return $wpdb->prefix . 'at_fin_movimientos'; }

/** Crea o actualiza las dos tablas (idempotente). Barato: si la opción ya está en 1 no hace nada. */
function at_fin_migrar(): void {
	global $wpdb;
	if (get_option('at_fin_schema') === '1') { return; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cuentas = at_fin_tabla_cuentas();
	$movs = at_fin_tabla_movimientos();
	$cotejo = $wpdb->get_charset_collate();
	dbDelta("CREATE TABLE {$cuentas} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		slug varchar(60) NOT NULL,
		nombre varchar(120) NOT NULL DEFAULT '',
		proveedor varchar(120) NOT NULL DEFAULT '',
		moneda char(3) NOT NULL DEFAULT 'USD',
		activa tinyint(1) NOT NULL DEFAULT 1,
		notas text NOT NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY slug (slug)
	) ENGINE=InnoDB {$cotejo};");
	dbDelta("CREATE TABLE {$movs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		fecha date NOT NULL,
		tipo varchar(10) NOT NULL,
		cuenta_id bigint(20) unsigned DEFAULT NULL,
		proveedor varchar(120) NOT NULL DEFAULT '',
		categoria varchar(60) NOT NULL DEFAULT '',
		descripcion varchar(255) NOT NULL DEFAULT '',
		proyecto varchar(80) NOT NULL DEFAULT '',
		monto decimal(14,4) NOT NULL,
		moneda char(3) NOT NULL,
		tipo_cambio decimal(10,2) DEFAULT NULL,
		monto_clp decimal(14,0) DEFAULT NULL,
		tc_estado varchar(10) NOT NULL DEFAULT 'ok',
		gasto_id bigint(20) unsigned DEFAULT NULL,
		referencia varchar(120) NOT NULL,
		registrado_por varchar(60) NOT NULL DEFAULT '',
		origen varchar(10) NOT NULL DEFAULT 'api',
		notas text NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY referencia (referencia),
		KEY fecha (fecha),
		KEY cuenta_id (cuenta_id)
	) ENGINE=InnoDB {$cotejo};");
	// Solo se marca como hecha si las dos tablas existen de verdad; si dbDelta falló, la siguiente petición reintenta.
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($cuentas))) !== null
		&& $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($movs))) !== null) {
		update_option('at_fin_schema', '1');
	}
}

/**
 * ¿En esta misma petición se registró un movimiento que quedó con el dólar pendiente? Con `$marcar` true lo anota.
 * El panel y la API lo usan para no insistir con una fuente que acaba de fallar.
 */
function at_fin_registro_pendiente(?bool $marcar = null): bool {
	static $hubo = false;
	if ($marcar !== null) { $hubo = $marcar; }
	return $hubo;
}

/* ---------- Cuentas ---------- */

function at_fin_cuenta_fila(?array $fila): ?array {
	if ($fila === null) { return null; }
	$fila['id'] = (int) $fila['id'];
	$fila['activa'] = (int) $fila['activa'];
	return $fila;
}

function at_fin_cuenta(string $slug): ?array {
	global $wpdb;
	$slug = strtolower(trim($slug));
	if ($slug === '') { return null; }
	$fila = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_fin_tabla_cuentas() . ' WHERE slug = %s', $slug), ARRAY_A);
	return at_fin_cuenta_fila($fila ?: null);
}

/** 1 o 0 desde un valor booleano o texto («false», «0», «no», «off» = inactiva); si no se reconoce o no viene, conserva `$actual`. */
function at_fin_activa($valor, $actual): int {
	if ($valor === null) { return (int) $actual; }
	$bool = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	return $bool === null ? (int) $actual : (int) $bool;
}

/** Crea la cuenta o, si el slug ya existe, actualiza los campos que vengan. Devuelve el id. */
function at_fin_cuenta_guardar(array $d) {
	global $wpdb;
	at_fin_migrar();
	$slug = strtolower(at_fin_texto($d['slug'] ?? '', 60));
	if (preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $slug) !== 1) {
		return new WP_Error('cuenta_invalida', 'El slug de la cuenta no es válido.', ['status' => 422]);
	}
	$existente = at_fin_cuenta($slug);
	$nombre = array_key_exists('nombre', $d) ? at_fin_texto($d['nombre'], 120) : ($existente['nombre'] ?? '');
	if ($nombre === '') {
		return new WP_Error('cuenta_invalida', 'La cuenta necesita un nombre.', ['status' => 422]);
	}
	$moneda = array_key_exists('moneda', $d) && at_fin_texto($d['moneda'], 3) !== '' ? strtoupper(at_fin_texto($d['moneda'], 3)) : ($existente['moneda'] ?? 'USD');
	if (!in_array($moneda, ['CLP', 'USD'], true)) {
		return new WP_Error('cuenta_invalida', 'La moneda de la cuenta debe ser CLP o USD.', ['status' => 422]);
	}
	$campos = [
		'nombre' => $nombre,
		'proveedor' => array_key_exists('proveedor', $d) ? at_fin_texto($d['proveedor'], 120) : ($existente['proveedor'] ?? ''),
		'moneda' => $moneda,
		'activa' => at_fin_activa($d['activa'] ?? null, $existente['activa'] ?? 1),
		'notas' => array_key_exists('notas', $d) ? (is_scalar($d['notas']) ? trim((string) $d['notas']) : '') : ($existente['notas'] ?? ''),
		'updated_at' => at_fin_ahora(),
	];
	$tabla = at_fin_tabla_cuentas();
	if ($existente) {
		if ($moneda !== $existente['moneda'] && at_fin_cuenta_tiene_movimientos($existente['id'])) {
			$msg = 'No se puede cambiar la moneda de una cuenta que ya tiene movimientos (' . $existente['moneda'] . ').';
			return new WP_Error('moneda_cuenta', $msg, ['status' => 422, 'errores' => [$msg]]);
		}
		if ($wpdb->update($tabla, $campos, ['id' => $existente['id']]) === false) {
			return new WP_Error('error_bd', 'No se pudo actualizar la cuenta.', ['status' => 500]);
		}
		return $existente['id'];
	}
	$campos += ['slug' => $slug, 'created_at' => $campos['updated_at']];
	// Un conflicto UNIQUE (otra petición creó el slug) se resuelve abajo: que «Duplicate entry» no llegue a ninguna respuesta.
	$suprimido = $wpdb->suppress_errors(true);
	$insertado = $wpdb->insert($tabla, $campos);
	$wpdb->suppress_errors($suprimido);
	if ($insertado === false) {
		$de_otro = at_fin_cuenta($slug);
		return $de_otro ? $de_otro['id'] : new WP_Error('error_bd', 'No se pudo crear la cuenta.', ['status' => 500]);
	}
	return (int) $wpdb->insert_id;
}

/** ¿La cuenta ya tiene algún movimiento? */
function at_fin_cuenta_tiene_movimientos(int $cuenta_id): bool {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_fin_tabla_movimientos() . ' WHERE cuenta_id = %d', $cuenta_id)) > 0;
}

/** Todas las cuentas, cada una con su saldo estimado (clave `saldo`, ver at_fin_saldo). */
function at_fin_cuentas(): array {
	global $wpdb;
	$cuentas = $wpdb->get_results('SELECT * FROM ' . at_fin_tabla_cuentas() . ' ORDER BY nombre ASC, id ASC', ARRAY_A) ?: [];
	// Orden fecha, id: así «la última recarga» es siempre la registrada después, también con dos recargas el mismo día.
	$movs = $wpdb->get_results('SELECT id, cuenta_id, tipo, fecha, monto FROM ' . at_fin_tabla_movimientos() . ' WHERE cuenta_id IS NOT NULL ORDER BY fecha ASC, id ASC', ARRAY_A) ?: [];
	$por_cuenta = [];
	foreach ($movs as $m) { $por_cuenta[(int) $m['cuenta_id']][] = $m; }
	foreach ($cuentas as &$c) {
		$c = at_fin_cuenta_fila($c);
		$c['saldo'] = at_fin_saldo($por_cuenta[$c['id']] ?? []);
	}
	unset($c);
	return $cuentas;
}

/* ---------- Movimientos ---------- */

function at_fin_movimiento_por_referencia(string $referencia): ?array {
	global $wpdb;
	$fila = $wpdb->get_row($wpdb->prepare(
		'SELECT m.*, c.slug AS cuenta FROM ' . at_fin_tabla_movimientos() . ' m LEFT JOIN ' . at_fin_tabla_cuentas() . ' c ON c.id = m.cuenta_id WHERE m.referencia = %s',
		$referencia
	), ARRAY_A);
	return $fila ?: null;
}

/**
 * Registra un movimiento de forma idempotente. Devuelve ['id' => int, 'estado' => 'creado'|'existente'] o WP_Error
 * (422 datos o cuenta no válidos, 409 referencia repetida con otros datos, 500 base de datos).
 */
function at_fin_registrar(array $entrada, string $origen) {
	global $wpdb;
	at_fin_migrar();
	$val = at_fin_validar($entrada, at_fin_hoy());
	if (!$val['ok']) {
		return new WP_Error('validacion', implode(' ', $val['errores']), ['status' => 422, 'errores' => $val['errores']]);
	}
	$datos = $val['datos'];

	$cuenta_id = null;
	if ($datos['cuenta'] !== '') {
		$cuenta = at_fin_cuenta($datos['cuenta']);
		if ($cuenta === null) {
			return new WP_Error('cuenta_inexistente', 'La cuenta indicada no existe.', ['status' => 422]);
		}
		// Recarga, consumo y ajuste cambian el saldo de la cuenta, que se lleva en su propia moneda: no se mezclan.
		if (in_array($datos['tipo'], ['recarga', 'consumo', 'ajuste'], true) && $datos['moneda'] !== $cuenta['moneda']) {
			$msg = 'La moneda del movimiento (' . $datos['moneda'] . ') no coincide con la de la cuenta (' . $cuenta['moneda'] . '); regístrelo en ' . $cuenta['moneda'] . '.';
			return new WP_Error('moneda_cuenta', $msg, ['status' => 422, 'errores' => [$msg]]);
		}
		$cuenta_id = $cuenta['id'];
	}

	$referencia = $datos['referencia'] !== '' ? $datos['referencia'] : at_fin_referencia($datos);
	$ya = at_fin_movimiento_por_referencia($referencia);
	if ($ya !== null) {
		return at_fin_respuesta_repetida($ya, $datos);
	}

	// Tipo de cambio: CLP vale 1; si viene manual se respeta; si no, el dólar observado del día (o pendiente).
	if ($datos['moneda'] === 'CLP') {
		$tipo_cambio = 1.0;
		$tc_estado = 'ok';
	} elseif ($datos['tipo_cambio'] !== null) {
		$tipo_cambio = round((float) $datos['tipo_cambio'], 2);
		$tc_estado = 'manual';
	} else {
		$tipo_cambio = at_fin_tipo_cambio($datos['fecha']);
		$tipo_cambio = $tipo_cambio === null ? null : round($tipo_cambio, 2);
		$tc_estado = $tipo_cambio === null ? 'pendiente' : 'ok';
	}
	$monto_clp = $tipo_cambio === null ? null : (int) round($datos['monto'] * $tipo_cambio);

	$fila = [
		'fecha' => $datos['fecha'],
		'tipo' => $datos['tipo'],
		'cuenta_id' => $cuenta_id,
		'proveedor' => $datos['proveedor'],
		'categoria' => $datos['categoria'],
		'descripcion' => $datos['descripcion'],
		'proyecto' => $datos['proyecto'],
		'monto' => at_fin_monto_texto($datos['monto']),
		'moneda' => $datos['moneda'],
		'tipo_cambio' => $tipo_cambio === null ? null : number_format($tipo_cambio, 2, '.', ''),
		'monto_clp' => $monto_clp,
		'tc_estado' => $tc_estado,
		'gasto_id' => $datos['gasto_id'],
		'referencia' => $referencia,
		'registrado_por' => $datos['registrado_por'],
		'origen' => $origen === 'panel' ? 'panel' : 'api',
		'notas' => $datos['notas'],
		'created_at' => at_fin_ahora(),
	];
	// Un conflicto UNIQUE de referencia (carrera entre dos peticiones) se resuelve abajo: que «Duplicate entry» no llegue a ninguna respuesta.
	$suprimido = $wpdb->suppress_errors(true);
	$insertado = $wpdb->insert(at_fin_tabla_movimientos(), $fila);
	$wpdb->suppress_errors($suprimido);
	if ($insertado === false) {
		// Otra petición pudo registrar la misma referencia entre la consulta y el insert.
		$ya = at_fin_movimiento_por_referencia($referencia);
		return $ya !== null ? at_fin_respuesta_repetida($ya, $datos) : new WP_Error('error_bd', 'No se pudo guardar el movimiento.', ['status' => 500]);
	}
	if ($tc_estado === 'pendiente') { at_fin_registro_pendiente(true); }
	return ['id' => (int) $wpdb->insert_id, 'estado' => 'creado'];
}

/** Referencia ya usada: mismos datos → el existente; otros datos → conflicto 409. */
function at_fin_respuesta_repetida(array $existente, array $datos) {
	if (at_fin_mismos_datos($datos, $existente)) {
		return ['id' => (int) $existente['id'], 'estado' => 'existente'];
	}
	return new WP_Error('duplicado', 'Ya existe un movimiento con esa referencia y datos distintos.', ['status' => 409]);
}

/** Movimientos de un mes ('Y-m'), los más recientes primero, con el slug de su cuenta en `cuenta`. */
function at_fin_movimientos_mes(string $mes): array {
	global $wpdb;
	if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $mes) !== 1) { return []; }
	$desde = $mes . '-01';
	$hasta = (new DateTimeImmutable($desde))->modify('first day of next month')->format('Y-m-d');
	return $wpdb->get_results($wpdb->prepare(
		'SELECT m.*, c.slug AS cuenta FROM ' . at_fin_tabla_movimientos() . ' m LEFT JOIN ' . at_fin_tabla_cuentas() . ' c ON c.id = m.cuenta_id WHERE m.fecha >= %s AND m.fecha < %s ORDER BY m.fecha DESC, m.id DESC',
		$desde, $hasta
	), ARRAY_A) ?: [];
}

/** Un movimiento por id, con el slug de su cuenta en `cuenta`; null si no existe. */
function at_fin_movimiento(int $id): ?array {
	global $wpdb;
	$fila = $wpdb->get_row($wpdb->prepare(
		'SELECT m.*, c.slug AS cuenta FROM ' . at_fin_tabla_movimientos() . ' m LEFT JOIN ' . at_fin_tabla_cuentas() . ' c ON c.id = m.cuenta_id WHERE m.id = %d',
		$id
	), ARRAY_A);
	return $fila ?: null;
}

/** Gasto total (CLP) de los `$cantidad` meses anteriores a `$mes` ('Y-m'), del más antiguo al más reciente: [['mes' => 'Y-m', 'total_clp' => int], ...]. */
function at_fin_comparacion(string $mes, int $cantidad = 6): array {
	$primero = new DateTimeImmutable($mes . '-01');
	$comparacion = [];
	for ($atras = max(1, $cantidad); $atras >= 1; $atras--) {
		$m = $primero->modify('-' . $atras . ' months')->format('Y-m');
		$comparacion[] = ['mes' => $m, 'total_clp' => at_fin_resumen_mes(at_fin_movimientos_mes($m))['total_clp']];
	}
	return $comparacion;
}

/** Últimos movimientos de una cuenta, los más recientes primero. */
function at_fin_movimientos_cuenta(int $cuenta_id, int $limite = 50): array {
	global $wpdb;
	return $wpdb->get_results($wpdb->prepare(
		'SELECT * FROM ' . at_fin_tabla_movimientos() . ' WHERE cuenta_id = %d ORDER BY fecha DESC, id DESC LIMIT %d',
		$cuenta_id, max(1, $limite)
	), ARRAY_A) ?: [];
}

/** Reintenta el tipo de cambio de los movimientos pendientes. Devuelve cuántos se completaron. */
function at_fin_completar_pendientes(int $max = 20): int {
	global $wpdb;
	$tabla = at_fin_tabla_movimientos();
	$pendientes = $wpdb->get_results($wpdb->prepare(
		"SELECT id, fecha, monto FROM {$tabla} WHERE tc_estado = 'pendiente' ORDER BY fecha DESC, id DESC LIMIT %d",
		max(1, $max)
	), ARRAY_A) ?: [];
	$completados = 0;
	$fallos = 0;
	foreach ($pendientes as $p) {
		$tc = at_fin_tipo_cambio($p['fecha']);
		if ($tc === null) {
			// Con la fuente caída cada intento cuesta hasta 5 s por día consultado (y se pausa 10 min): no insistir.
			if (++$fallos >= 3) { break; }
			continue;
		}
		$tc = round($tc, 2);
		$ok = $wpdb->update($tabla, [
			'tipo_cambio' => number_format($tc, 2, '.', ''),
			'monto_clp' => (int) round((float) $p['monto'] * $tc),
			'tc_estado' => 'ok',
		], ['id' => (int) $p['id']]);
		if ($ok !== false) { $completados++; }
	}
	return $completados;
}
