<?php
/**
 * Finanzas: cuánto se ha invertido y cuánto ha entrado.
 *
 * Hay dos orígenes de plata y a propósito **no se mezclan en la misma tabla**:
 *
 *  - Las fiestas, que ya guardan su cobro en `cc_parties`. De ahí salen los ingresos por
 *    servicio, calculados con el mismo `cb_party_billing()` que usa el comprobante, para que
 *    la pantalla de finanzas y la boleta del cliente no puedan decir números distintos.
 *  - Todo lo demás, en `cc_finanzas`: la impresora, el papel, los imanes, los acrílicos, la
 *    publicidad, y los ingresos que no vienen de una fiesta cargada.
 *
 * Lo regalado se cuenta aparte. Una fiesta de marcha blanca con 100% de descuento no es un
 * ingreso de cero pesos que se pueda ignorar: es plata que se decidió no cobrar, y saber
 * cuánta se lleva regalada es justamente lo que dice si la marcha blanca se fue de las manos.
 */

// `lib.php` va primero y explícito: acá se usan `cb_storage_mode()`, `cb_pdo()` y
// `cb_load_parties()`, que viven ahí. La pantalla del admin ya lo carga antes, pero una
// librería que solo funciona si alguien más la preparó es una trampa para el próximo que
// la use desde un script.
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/lib.cliente.php';
require_once __DIR__ . '/lib.planes.php';

/** Categorías de egreso. La clave se guarda; el texto es lo que se ve. */
function cb_finanzas_categorias(): array
{
    return [
        'equipo' => 'Equipo (impresora, tablet, soportes)',
        'insumos' => 'Insumos (papel, tinta, imanes)',
        'marketing' => 'Marketing y publicidad',
        'servicio' => 'Servicios (dominio, hosting, correo)',
        'traslado' => 'Traslado y montaje',
        'otro' => 'Otro',
    ];
}

function cb_finanzas_tipos(): array
{
    return ['egreso' => 'Inversión o gasto', 'ingreso' => 'Ingreso'];
}

/** ¿Está lista la tabla? Sin base de datos la pantalla no tiene nada que mostrar. */
function cb_finanzas_disponible(): bool
{
    if (cb_storage_mode() !== 'db') { return false; }
    try {
        cb_pdo()->query('SELECT 1 FROM cc_finanzas LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Movimientos guardados a mano, del más reciente al más viejo. */
function cb_finanzas_movimientos(int $limite = 500): array
{
    if (!cb_finanzas_disponible()) { return []; }
    $stmt = cb_pdo()->prepare(
        'SELECT id, fecha, tipo, categoria, descripcion, monto, unidades, party_slug, nota
         FROM cc_finanzas ORDER BY fecha DESC, id DESC LIMIT ' . max(1, $limite)
    );
    $stmt->execute();
    $filas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $filas[] = [
            'id' => (int) $f['id'],
            'fecha' => (string) $f['fecha'],
            'tipo' => (string) $f['tipo'],
            'categoria' => (string) $f['categoria'],
            'descripcion' => (string) $f['descripcion'],
            'monto' => (int) $f['monto'],
            'unidades' => $f['unidades'] === null ? null : (int) $f['unidades'],
            'party_slug' => (string) ($f['party_slug'] ?? ''),
            'nota' => (string) ($f['nota'] ?? ''),
        ];
    }
    return $filas;
}

/**
 * Guarda un movimiento. Devuelve `['ok' => bool, 'errors' => string[], 'id' => ?int]`.
 * Si viene `id`, actualiza en vez de crear.
 */
function cb_finanzas_guardar(array $datos): array
{
    if (!cb_finanzas_disponible()) {
        return ['ok' => false, 'errors' => ['Las finanzas necesitan la base de datos y la migración 017.'], 'id' => null];
    }

    $errores = [];
    $fecha = trim((string) ($datos['fecha'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errores[] = 'La fecha va en formato día/mes/año.';
    }
    $tipo = (string) ($datos['tipo'] ?? '');
    if (!isset(cb_finanzas_tipos()[$tipo])) {
        $errores[] = 'Hay que decir si es una inversión o un ingreso.';
    }
    $categoria = (string) ($datos['categoria'] ?? 'otro');
    if (!isset(cb_finanzas_categorias()[$categoria])) { $categoria = 'otro'; }
    $descripcion = trim((string) ($datos['descripcion'] ?? ''));
    if ($descripcion === '') {
        $errores[] = 'Escribe qué es, para reconocerlo después.';
    }
    // Se acepta escrito como se lee: "169.990", "$169.990", "169990".
    $monto = cb_parse_clp($datos['monto'] ?? '');
    if ($monto === null || $monto <= 0) {
        $errores[] = 'El monto tiene que ser un número mayor que cero.';
    }
    $unidades = trim((string) ($datos['unidades'] ?? ''));
    $unidades = $unidades === '' ? null : max(0, (int) $unidades);

    if ($errores) { return ['ok' => false, 'errors' => $errores, 'id' => null]; }

    $pdo = cb_pdo();
    $id = isset($datos['id']) && (int) $datos['id'] > 0 ? (int) $datos['id'] : null;
    $campos = [
        $fecha, $tipo, $categoria, mb_substr($descripcion, 0, 160), (int) $monto, $unidades,
        mb_substr(trim((string) ($datos['party_slug'] ?? '')), 0, 80) ?: null,
        mb_substr(trim((string) ($datos['nota'] ?? '')), 0, 500) ?: null,
    ];

    if ($id !== null) {
        $stmt = $pdo->prepare(
            'UPDATE cc_finanzas SET fecha = ?, tipo = ?, categoria = ?, descripcion = ?,
                    monto = ?, unidades = ?, party_slug = ?, nota = ? WHERE id = ?'
        );
        $stmt->execute(array_merge($campos, [$id]));
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO cc_finanzas (fecha, tipo, categoria, descripcion, monto, unidades,
                                  party_slug, nota, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute(array_merge($campos, [date('Y-m-d H:i:s')]));
    return ['ok' => true, 'errors' => [], 'id' => (int) $pdo->lastInsertId()];
}

/** Borra un movimiento. Devuelve true si existía. */
function cb_finanzas_borrar(int $id): bool
{
    if (!cb_finanzas_disponible() || $id <= 0) { return false; }
    $stmt = cb_pdo()->prepare('DELETE FROM cc_finanzas WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/**
 * Los ingresos que vienen de las fiestas, una fila por fiesta con cobro cargado.
 *
 * `cobrado` es lo que el cliente termina pagando (precio menos descuento) y `regalado` lo
 * que se decidió no cobrar. Las fiestas sin precio cargado no aparecen: todavía no son
 * plata, ni a favor ni en contra.
 */
function cb_finanzas_fiestas(): array
{
    if (cb_storage_mode() !== 'db') { return []; }
    $filas = [];
    foreach (cb_load_parties()['parties'] as $slug => $fiesta) {
        $slug = (string) ($fiesta['public_slug'] ?? $slug);
        if ($slug === '') { continue; }
        $cobro = cb_party_billing($slug);
        if ($cobro['price_total'] === null) { continue; }
        $filas[] = [
            'slug' => $slug,
            'nombre' => (string) ($fiesta['admin_label'] ?? '') !== ''
                ? (string) $fiesta['admin_label']
                : ((string) ($fiesta['nombre'] ?? '') !== '' ? (string) $fiesta['nombre'] : $slug),
            'fecha' => (string) ($fiesta['fecha'] ?? ''),
            'activa' => !empty($fiesta['activa']),
            'precio' => (int) $cobro['price_total'],
            'cobrado' => (int) $cobro['total'],
            'regalado' => (int) $cobro['discount_amount'],
            'motivo' => (string) $cobro['discount_label'],
            'anticipo' => (int) $cobro['deposit_amount'],
        ];
    }
    // Las más nuevas primero, como en el listado de fiestas.
    usort($filas, static fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    return $filas;
}

/**
 * El resumen completo: totales, corte por categoría, serie por mes y punto de equilibrio.
 *
 * Todo se calcula acá y no en la pantalla para que los tests puedan comprobar los números
 * sin levantar una sesión de admin.
 */
function cb_finanzas_resumen(): array
{
    $movimientos = cb_finanzas_movimientos();
    $fiestas = cb_finanzas_fiestas();

    $invertido = 0;
    $ingresoManual = 0;
    $porCategoria = [];
    $meses = [];

    $mes = static function (string $fecha): string {
        return strlen($fecha) >= 7 ? substr($fecha, 0, 7) : '';
    };
    $sumarMes = static function (array &$meses, string $clave, string $campo, int $monto): void {
        if ($clave === '') { return; }
        if (!isset($meses[$clave])) { $meses[$clave] = ['mes' => $clave, 'ingreso' => 0, 'egreso' => 0]; }
        $meses[$clave][$campo] += $monto;
    };

    foreach ($movimientos as $m) {
        if ($m['tipo'] === 'egreso') {
            $invertido += $m['monto'];
            $porCategoria[$m['categoria']] = ($porCategoria[$m['categoria']] ?? 0) + $m['monto'];
            $sumarMes($meses, $mes($m['fecha']), 'egreso', $m['monto']);
        } else {
            $ingresoManual += $m['monto'];
            $sumarMes($meses, $mes($m['fecha']), 'ingreso', $m['monto']);
        }
    }

    $ingresoFiestas = 0;
    $regalado = 0;
    foreach ($fiestas as $f) {
        $ingresoFiestas += $f['cobrado'];
        $regalado += $f['regalado'];
        $sumarMes($meses, $mes($f['fecha']), 'ingreso', $f['cobrado']);
    }

    $ingresado = $ingresoFiestas + $ingresoManual;
    ksort($meses);

    arsort($porCategoria);
    $categorias = [];
    foreach ($porCategoria as $clave => $monto) {
        $categorias[] = [
            'clave' => $clave,
            'nombre' => cb_finanzas_categorias()[$clave] ?? $clave,
            'monto' => $monto,
            'parte' => $invertido > 0 ? $monto / $invertido : 0.0,
        ];
    }

    return [
        'invertido' => $invertido,
        'ingresado' => $ingresado,
        'ingreso_fiestas' => $ingresoFiestas,
        'ingreso_manual' => $ingresoManual,
        'regalado' => $regalado,
        'resultado' => $ingresado - $invertido,
        'fiestas_cobradas' => count(array_filter($fiestas, static fn($f) => $f['cobrado'] > 0)),
        'fiestas_regaladas' => count(array_filter($fiestas, static fn($f) => $f['cobrado'] === 0)),
        'categorias' => $categorias,
        'meses' => array_values($meses),
        'movimientos' => $movimientos,
        'fiestas' => $fiestas,
        'equilibrio' => cb_finanzas_equilibrio($ingresado - $invertido),
    ];
}

/**
 * Cuántas fiestas faltan para quedar en cero, al precio y margen de hoy.
 *
 * El margen sale del plan destacado del catálogo menos lo que cuestan los recuerdos
 * impresos, que es el único costo que crece con cada fiesta. Si ya está a favor, devuelve 0.
 */
function cb_finanzas_equilibrio(int $resultado, int $recuerdos = 25, int $costoRecuerdo = 393): array
{
    $plan = null;
    if (function_exists('cb_planes')) {
        foreach (cb_planes()['planes'] as $p) {
            if (!empty($p['destacado'])) { $plan = $p; break; }
        }
    }
    $precio = (int) ($plan['precio'] ?? 0);
    $costo = $recuerdos * $costoRecuerdo;
    $margen = $precio - $costo;

    return [
        'plan' => (string) ($plan['nombre'] ?? ''),
        'precio' => $precio,
        'costo_recuerdos' => $costo,
        'margen' => $margen,
        // Si el margen no es positivo la pregunta no tiene respuesta: no se recupera vendiendo más.
        'faltan' => $margen > 0 && $resultado < 0 ? (int) ceil(-$resultado / $margen) : 0,
    ];
}
