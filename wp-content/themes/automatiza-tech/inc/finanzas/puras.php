<?php
/** Reglas de Finanzas AT sin acceso a WordPress ni efectos externos. */

/** Tipos de movimiento: pago (gasto directo), recarga (saldo a una cuenta), consumo (se descuenta de una cuenta), ajuste (cuadre). */
function at_fin_tipos(): array { return ['pago', 'recarga', 'consumo', 'ajuste']; }

/** Número desde un valor o texto con coma o punto decimal y espacios; null si no es un número. */
function at_fin_monto($v): ?float {
	if (is_int($v)) { return (float) $v; }
	if (is_float($v)) { return is_finite($v) ? $v : null; }
	if (!is_string($v)) { return null; }
	$texto = str_replace(',', '.', preg_replace('/\s+/u', '', $v));
	return preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/D', $texto) === 1 ? (float) $texto : null;
}

function at_fin_texto($v, int $largo): string {
	return is_scalar($v) ? mb_substr(trim((string) $v), 0, $largo) : '';
}

function at_fin_fecha_valida(string $ymd): bool {
	$fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('America/Santiago'));
	return $fecha !== false && $fecha->format('Y-m-d') === $ymd;
}

/** Primera fecha aceptada ('Y-m-d'): antes no se pide dólar ni se registran movimientos. */
function at_fin_fecha_minima(): string { return '2020-01-01'; }

/** Normaliza y valida un movimiento. `$hoy` es 'Y-m-d' (hora de Chile). */
function at_fin_validar(array $d, string $hoy): array {
	$errores = [];
	$tipo = strtolower(at_fin_texto($d['tipo'] ?? '', 20));
	if (!in_array($tipo, at_fin_tipos(), true)) { $errores[] = 'Tipo de movimiento no válido.'; }

	$fecha = at_fin_texto($d['fecha'] ?? '', 10);
	if (!at_fin_fecha_valida($fecha)) { $errores[] = 'Fecha no válida (use AAAA-MM-DD).'; }
	elseif ($fecha > $hoy) { $errores[] = 'La fecha no puede ser futura.'; }
	// Sin dólar publicado antes de 2020 el movimiento quedaría pendiente para siempre.
	elseif ($fecha < at_fin_fecha_minima()) { $errores[] = 'Fecha demasiado antigua.'; }

	$cuenta = strtolower(at_fin_texto($d['cuenta'] ?? '', 60));
	if ($cuenta !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $cuenta) !== 1) { $errores[] = 'Cuenta no válida.'; }
	if ($cuenta === '' && in_array($tipo, ['recarga', 'consumo', 'ajuste'], true)) { $errores[] = 'La cuenta es obligatoria en recargas, consumos y ajustes.'; }

	$moneda = strtoupper(at_fin_texto($d['moneda'] ?? '', 3));
	if (!in_array($moneda, ['CLP', 'USD'], true)) { $errores[] = 'La moneda debe ser CLP o USD.'; }

	$monto_bruto = $d['monto'] ?? null;
	$monto = at_fin_monto($monto_bruto);
	if (is_string($monto_bruto) && preg_match('/^-?\d{1,3}(\.\d{3})+$/D', preg_replace('/\s+/u', '', $monto_bruto) ?? '') === 1) {
		// «15.990» se leería como 15,99: ambiguo en cualquier moneda («12.500» también lo es en dólares).
		$errores[] = 'El monto parece llevar punto de miles: escríbelo sin puntos (15990) o con coma decimal.';
	} elseif ($monto === null || $monto == 0.0) { $errores[] = 'El monto debe ser un número distinto de cero.'; }
	elseif ($monto < 0 && $tipo !== 'ajuste') { $errores[] = 'El monto debe ser positivo (solo un ajuste puede ser negativo).'; }
	elseif ($moneda === 'CLP' && abs($monto - round($monto)) > 0.00005) { $errores[] = 'En pesos no hay decimales.'; }

	$tc_bruto = $d['tipo_cambio'] ?? null;
	$tipo_cambio = null;
	if ($tc_bruto !== null && trim((string) (is_scalar($tc_bruto) ? $tc_bruto : 'x')) !== '') {
		$tipo_cambio = at_fin_monto($tc_bruto);
		if ($tipo_cambio === null || $tipo_cambio <= 0) { $errores[] = 'El tipo de cambio debe ser un número mayor que cero.'; $tipo_cambio = null; }
	}

	$gasto_id = isset($d['gasto_id']) && is_numeric($d['gasto_id']) && (int) $d['gasto_id'] > 0 ? (int) $d['gasto_id'] : null;

	return [
		'ok' => $errores === [],
		'errores' => $errores,
		'datos' => [
			'tipo' => $tipo,
			'fecha' => $fecha,
			'cuenta' => $cuenta,
			'proveedor' => at_fin_texto($d['proveedor'] ?? '', 120),
			'categoria' => at_fin_texto($d['categoria'] ?? '', 60),
			'descripcion' => at_fin_texto($d['descripcion'] ?? '', 255),
			'proyecto' => at_fin_texto($d['proyecto'] ?? '', 80),
			'monto' => $monto ?? 0.0,
			'moneda' => $moneda,
			'tipo_cambio' => $tipo_cambio,
			'gasto_id' => $gasto_id,
			'referencia' => at_fin_texto($d['referencia'] ?? '', 120),
			'registrado_por' => at_fin_texto($d['registrado_por'] ?? '', 60),
			'notas' => is_scalar($d['notas'] ?? null) ? trim((string) $d['notas']) : '',
		],
	];
}

function at_fin_monto_texto($v): string { return number_format((float) (at_fin_monto($v) ?? 0.0), 4, '.', ''); }

/** Referencia automática (anti-duplicado) cuando quien registra no manda una. */
function at_fin_referencia(array $datos): string {
	return 'auto-' . sha1(implode('|', [
		$datos['tipo'] ?? '', $datos['fecha'] ?? '', $datos['cuenta'] ?? '', $datos['proveedor'] ?? '',
		at_fin_monto_texto($datos['monto'] ?? null), $datos['moneda'] ?? '', $datos['descripcion'] ?? '',
	]));
}

/** Dólar de la fecha desde una serie ['Y-m-d' => valor]: el último valor entre 7 días atrás y la fecha (inclusive). */
function at_fin_tc_de_serie(string $fecha, array $serie): ?float {
	if (!at_fin_fecha_valida($fecha)) { return null; }
	$dia = new DateTimeImmutable($fecha, new DateTimeZone('America/Santiago'));
	for ($atras = 0; $atras <= 7; $atras++) {
		$clave = $dia->modify('-' . $atras . ' days')->format('Y-m-d');
		if (isset($serie[$clave]) && is_numeric($serie[$clave])) { return (float) $serie[$clave]; }
	}
	return null;
}

/** Gasto del mes: solo pagos y recargas suman (el consumo ya se contó en la recarga). Pendientes = sin tipo de cambio. */
function at_fin_resumen_mes(array $movs): array {
	$total = 0;
	$por_categoria = [];
	$por_proveedor = [];
	$pendientes = 0;
	foreach ($movs as $m) {
		if (($m['tc_estado'] ?? '') === 'pendiente') { $pendientes++; }
		if (!in_array($m['tipo'] ?? '', ['pago', 'recarga'], true)) { continue; }
		$clp = (int) round((float) ($m['monto_clp'] ?? 0));
		$categoria = trim((string) ($m['categoria'] ?? '')) ?: 'Sin categoría';
		$proveedor = trim((string) ($m['proveedor'] ?? '')) ?: 'Sin proveedor';
		$total += $clp;
		$por_categoria[$categoria] = ($por_categoria[$categoria] ?? 0) + $clp;
		$por_proveedor[$proveedor] = ($por_proveedor[$proveedor] ?? 0) + $clp;
	}
	return ['total_clp' => $total, 'por_categoria' => $por_categoria, 'por_proveedor' => $por_proveedor, 'pendientes' => $pendientes];
}

/** Saldo estimado de una cuenta (en su moneda): recargas + ajustes − consumos, y cuánto se usó desde la última recarga. */
function at_fin_saldo(array $movs): array {
	$recargado = $consumido = $ajustes = 0.0;
	$ultima = null;
	$ultima_id = 0;
	foreach ($movs as $m) {
		$monto = (float) (at_fin_monto($m['monto'] ?? null) ?? 0.0);
		$tipo = $m['tipo'] ?? '';
		if ($tipo === 'recarga') {
			$recargado += $monto;
			// La última es la de mayor (fecha, id): con dos recargas el mismo día gana la registrada después, venga como venga el orden.
			$fecha_m = (string) ($m['fecha'] ?? '');
			$id_m = (int) ($m['id'] ?? 0);
			if ($ultima === null || $fecha_m > $ultima['fecha'] || ($fecha_m === $ultima['fecha'] && $id_m >= $ultima_id)) {
				$ultima = ['fecha' => $fecha_m, 'monto' => $monto];
				$ultima_id = $id_m;
			}
		} elseif ($tipo === 'consumo') {
			$consumido += $monto;
		} elseif ($tipo === 'ajuste') {
			$ajustes += $monto;
		}
	}
	$usado = 0.0;
	if ($ultima !== null) {
		foreach ($movs as $m) {
			if (($m['tipo'] ?? '') === 'consumo' && (string) ($m['fecha'] ?? '') >= $ultima['fecha']) { $usado += (float) (at_fin_monto($m['monto'] ?? null) ?? 0.0); }
		}
	}
	return [
		'recargado' => round($recargado, 4),
		'consumido' => round($consumido, 4),
		'ajustes' => round($ajustes, 4),
		'saldo' => round($recargado + $ajustes - $consumido, 4),
		'ultima_recarga' => $ultima === null ? null : ['fecha' => $ultima['fecha'], 'monto' => round($ultima['monto'], 4)],
		'usado_desde_recarga' => round($usado, 4),
		'pct_usado' => $ultima !== null && $ultima['monto'] > 0 ? round($usado / $ultima['monto'] * 100, 2) : null,
	];
}

/** ¿Son el mismo movimiento? Compara tipo, fecha, cuenta, proveedor, monto, moneda y descripción. */
function at_fin_mismos_datos(array $a, array $b): bool {
	foreach (['tipo', 'fecha', 'cuenta', 'proveedor', 'moneda', 'descripcion'] as $k) {
		if (trim((string) ($a[$k] ?? '')) !== trim((string) ($b[$k] ?? ''))) { return false; }
	}
	return at_fin_monto_texto($a['monto'] ?? null) === at_fin_monto_texto($b['monto'] ?? null);
}
