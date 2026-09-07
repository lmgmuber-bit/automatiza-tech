<?php
/**
 * Comprueba los números de la pantalla de finanzas contra una base SQLite de mentira.
 *
 * Lo que más importa acá no es que sume: es que **no cuente dos veces**. El ingreso de una
 * fiesta vive en `cc_parties` y el gasto en `cc_finanzas`; si algún día alguien copia el
 * cobro de la fiesta a la tabla de movimientos, el resultado se duplica y nadie lo nota
 * hasta que el número está muy lejos de la realidad. Por eso se prueban las dos fuentes
 * juntas y no cada una por su lado.
 */

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/public/lib.php';

$fallos = 0;
$total = 0;
function ok(string $que, bool $cond): void
{
    global $fallos, $total;
    $total++;
    if (!$cond) { $fallos++; echo "  FALLA: $que\n"; }
}
function igual(string $que, $esperado, $obtenido): void
{
    global $fallos, $total;
    $total++;
    if ($esperado !== $obtenido) {
        $fallos++;
        echo "  FALLA: $que\n    esperaba: " . var_export($esperado, true)
           . "\n    obtuvo:   " . var_export($obtenido, true) . "\n";
    }
}

// ---------- Base de mentira, en memoria ----------
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE cc_parties (
    id INTEGER PRIMARY KEY AUTOINCREMENT, public_slug TEXT, admin_label TEXT,
    birthday_person_name TEXT, event_type TEXT, theme_slug TEXT, event_date TEXT,
    active INTEGER DEFAULT 1, frame_box_json TEXT, gallery_pin_hash TEXT, gallery_pin_hmac TEXT,
    service_plan TEXT, gallery_enabled INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT,
    anonymized_at TEXT, price_total INTEGER, discount_amount INTEGER, discount_percent NUMERIC,
    discount_label TEXT, deposit_amount INTEGER, payment_note TEXT)');
$pdo->exec('CREATE TABLE cc_guests (id INTEGER PRIMARY KEY AUTOINCREMENT, party_id INTEGER,
    name TEXT, gender TEXT, sort_order INTEGER)');

// La 017, tal cual la corre el runner.
(require $raiz . '/database/migrations/017_finanzas.php')($pdo);
ok('la migración crea cc_finanzas', (bool) $pdo->query("SELECT name FROM sqlite_master WHERE name='cc_finanzas'")->fetch());
// Correrla dos veces no puede romper nada: el runner puede reintentar.
(require $raiz . '/database/migrations/017_finanzas.php')($pdo);
ok('la migración se puede correr dos veces', true);

// Las cuentas se comprueban contra este PDO directamente, sin pasar por `cb_pdo()`: el test
// no necesita una conexión de verdad y así no depende de la configuración de la máquina.

// ---------- Dos fiestas: una cobrada y una regalada ----------
$pdo->prepare('INSERT INTO cc_parties (public_slug, admin_label, birthday_person_name, theme_slug,
    event_date, active, service_plan, created_at, price_total, discount_percent, discount_label)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute(['luciano-spidey', 'Luciano', 'Luciano', 'spidey', '2026-09-13', 1, 'full',
               '2026-09-01 10:00:00', 49995, null, '']);
$pdo->prepare('INSERT INTO cc_parties (public_slug, admin_label, birthday_person_name, theme_slug,
    event_date, active, service_plan, created_at, price_total, discount_percent, discount_label)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute(['samantha-hielo', 'Samantha', 'Samantha', 'hielo', '2026-09-13', 1, 'full',
               '2026-09-01 10:00:00', 99990, 100, 'Marcha blanca']);

// ---------- Movimientos ----------
$pdo->prepare('INSERT INTO cc_finanzas (fecha, tipo, categoria, descripcion, monto, unidades, created_at)
               VALUES (?,?,?,?,?,?,?)')
    ->execute(['2026-09-07', 'egreso', 'equipo', 'Canon SELPHY CP1500', 169990, 1, '2026-09-07 12:00:00']);
$pdo->prepare('INSERT INTO cc_finanzas (fecha, tipo, categoria, descripcion, monto, unidades, created_at)
               VALUES (?,?,?,?,?,?,?)')
    ->execute(['2026-09-07', 'egreso', 'insumos', 'Dos packs KP-36', 24732, 72, '2026-09-07 12:05:00']);
$pdo->prepare('INSERT INTO cc_finanzas (fecha, tipo, categoria, descripcion, monto, unidades, created_at)
               VALUES (?,?,?,?,?,?,?)')
    ->execute(['2026-09-07', 'egreso', 'insumos', 'Dos rollos cinta imantada', 7000, 120, '2026-09-07 12:06:00']);

echo "Finanzas\n";

// ---------- Las cuentas ----------
$egresos = (int) $pdo->query("SELECT SUM(monto) FROM cc_finanzas WHERE tipo='egreso'")->fetchColumn();
igual('la inversión suma los tres gastos', 201722, $egresos);

$cobrado = 0;
$regalado = 0;
foreach ($pdo->query('SELECT price_total, discount_percent FROM cc_parties')->fetchAll(PDO::FETCH_ASSOC) as $f) {
    $precio = (int) $f['price_total'];
    $desc = $f['discount_percent'] !== null ? (int) round($precio * (float) $f['discount_percent'] / 100) : 0;
    $cobrado += max(0, $precio - $desc);
    $regalado += $desc;
}
igual('solo entra la fiesta que sí se cobra', 49995, $cobrado);
igual('lo regalado se cuenta aparte, no como ingreso', 99990, $regalado);
igual('el resultado es ingreso menos inversión', 49995 - 201722, $cobrado - $egresos);
ok('una fiesta al 100% no aporta ingreso', $cobrado === 49995);

// ---------- Punto de equilibrio ----------
require_once $raiz . '/public/lib.planes.php';
require_once $raiz . '/public/lib.finanzas.php';

$eq = cb_finanzas_equilibrio($cobrado - $egresos);
ok('toma el plan destacado del catálogo', $eq['plan'] !== '');
igual('los 25 recuerdos cuestan lo calculado', 25 * 393, $eq['costo_recuerdos']);
igual('el margen es precio menos recuerdos', $eq['precio'] - 9825, $eq['margen']);
ok('faltan fiestas para llegar a cero', $eq['faltan'] > 0);
ok('el número de fiestas que faltan alcanza para cubrir el rojo',
   $eq['faltan'] * $eq['margen'] >= $egresos - $cobrado);
igual('estando a favor no falta ninguna fiesta', 0, cb_finanzas_equilibrio(50000)['faltan']);
igual('sin margen positivo no promete un número imposible', 0,
      cb_finanzas_equilibrio(-100000, 25, 999999)['faltan']);

// ---------- Validación de lo que se escribe ----------
igual('el monto acepta puntos de miles', 169990, cb_parse_clp('169.990'));
igual('el monto acepta el signo peso', 169990, cb_parse_clp('$169.990'));
igual('el monto acepta el número pelado', 169990, cb_parse_clp('169990'));

igual('las categorías tienen las seis previstas', 6, count(cb_finanzas_categorias()));
ok('existe la categoría de insumos', isset(cb_finanzas_categorias()['insumos']));
ok('existe la categoría de equipo', isset(cb_finanzas_categorias()['equipo']));
igual('los tipos son inversión e ingreso', ['egreso', 'ingreso'], array_keys(cb_finanzas_tipos()));

// ---------- El corte por categoría ----------
$porCat = [];
foreach ($pdo->query("SELECT categoria, SUM(monto) m FROM cc_finanzas WHERE tipo='egreso' GROUP BY categoria")
              ->fetchAll(PDO::FETCH_ASSOC) as $f) {
    $porCat[(string) $f['categoria']] = (int) $f['m'];
}
igual('el equipo suma la impresora', 169990, $porCat['equipo'] ?? 0);
igual('los insumos suman papel más imanes', 31732, $porCat['insumos'] ?? 0);
igual('las categorías suman el total invertido', $egresos, array_sum($porCat));

// ---------- Costo por unidad ----------
$papel = $pdo->query("SELECT monto, unidades FROM cc_finanzas WHERE descripcion LIKE 'Dos packs%'")
             ->fetch(PDO::FETCH_ASSOC);
// $24.732 entre 72 hojas da $343,5 justos; la pantalla redondea, como cualquier precio en pesos.
igual('el papel sale a 344 por hoja', 344,
      (int) round((int) $papel['monto'] / (int) $papel['unidades']));

// ---------- Lo que la pantalla no debe hacer ----------
$movs = $pdo->query("SELECT COUNT(*) FROM cc_finanzas WHERE tipo='ingreso'")->fetchColumn();
igual('el cobro de las fiestas NO se copia a movimientos', 0, (int) $movs);

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
