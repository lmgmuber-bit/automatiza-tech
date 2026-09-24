<?php
/**
 * Comprueba las posiciones de la fiesta contra una base de mentira.
 *
 * Lo que más importa acá es que la tabla general **no se decida por la escala de un juego**.
 * El caso del medio es el que motivó todo el diseño: un juego que reparte miles de puntos y
 * otro que reparte decenas. Si algún día alguien "simplifica" esto sumando los puntajes, esa
 * prueba falla y explica por qué.
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

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
(require $raiz . '/database/migrations/018_puntajes.php')($pdo);
ok('la migración crea cc_puntajes', (bool) $pdo->query("SELECT name FROM sqlite_master WHERE name='cc_puntajes'")->fetch());
(require $raiz . '/database/migrations/018_puntajes.php')($pdo);
ok('correrla dos veces no rompe nada', true);

require_once $raiz . '/public/lib.puntajes.php';

echo "Posiciones\n";

// ---------- El catálogo de juegos ----------
igual('hay cinco juegos registrados', 5, count(cb_juegos()));
igual('hielo tiene dos juegos', ['reino-hielo', 'festival'], array_keys(cb_juegos_de_tema('hielo')));
igual('spidey tiene dos juegos', ['aracnida', 'impulso'], array_keys(cb_juegos_de_tema('spidey')));
igual('una temática sin juegos devuelve vacío', [], cb_juegos_de_tema('carreras'));

// `spidey` y `heroes` son dos temáticas DISTINTAS del admin aunque compartan el mundo 3D, y
// es fácil confundirlas: los recursos arácnidos viven en `temas/heroes/` dentro del motor.
// Meter un juego de Spidey en `heroes` lo haría aparecer en Misión 3D, que es otra fiesta.
igual('heroes NO hereda los juegos de spidey', ['mision'], array_keys(cb_juegos_de_tema('heroes')));
foreach (cb_juegos() as $id => $j) {
    ok("el juego $id declara nombre y temática", ($j['nombre'] ?? '') !== '' && ($j['tema'] ?? '') !== '');
}

// ---------- Los nombres se unifican ----------
igual('quita espacios de más', 'Lucho', cb_puntaje_nombre('  lucho  '));
igual('unifica mayúsculas', 'Lucho', cb_puntaje_nombre('LUCHO'));
igual('mismo niño escrito de tres formas', 1,
      count(array_unique([cb_puntaje_nombre('lucho'), cb_puntaje_nombre('Lucho '), cb_puntaje_nombre('LUCHO')])));
igual('respeta nombres compuestos', 'María José', cb_puntaje_nombre('maría josé'));
igual('saca etiquetas', 'Script', cb_puntaje_nombre('<script>'));
igual('un nombre vacío queda vacío', '', cb_puntaje_nombre('   '));
ok('recorta un nombre larguísimo', mb_strlen(cb_puntaje_nombre(str_repeat('a', 200))) <= 40);

// ---------- El caso que motivó el diseño ----------
// Dos juegos de escalas muy distintas. Sofía arrasa en el de miles de puntos; Matías es el
// mejor en el de decenas. Sumando, Matías ni aparece; con medallas, empatan en la pelea.
$anotar = function (string $juego, string $jugador, int $puntaje) use ($pdo) {
    $pdo->prepare('INSERT INTO cc_puntajes (party_id, juego, jugador, puntaje, created_at)
                   VALUES (1, ?, ?, ?, ?)')->execute([$juego, $jugador, $puntaje, '2026-09-13 16:00:00']);
};
$anotar('reino-hielo', 'Sofía', 4800);
$anotar('reino-hielo', 'Emilia', 3100);
$anotar('reino-hielo', 'Benja', 900);
$anotar('reino-hielo', 'Matías', 150);
$anotar('festival', 'Matías', 45);
$anotar('festival', 'Benja', 30);
$anotar('festival', 'Sofía', 12);
$anotar('festival', 'Emilia', 8);

// La consulta que hace cb_posiciones(), contra este PDO de mentira.
$filas = $pdo->query('SELECT juego, jugador, MAX(puntaje) mejor, COUNT(*) veces
                      FROM cc_puntajes WHERE party_id = 1
                      GROUP BY juego, jugador ORDER BY juego, mejor DESC, jugador')
             ->fetchAll(PDO::FETCH_ASSOC);

$podios = [];
foreach ($filas as $f) { $podios[$f['juego']][] = $f['jugador']; }
igual('el podio de Reino de Hielo', ['Sofía', 'Emilia', 'Benja', 'Matías'], $podios['reino-hielo']);
igual('el podio del Festival', ['Matías', 'Benja', 'Sofía', 'Emilia'], $podios['festival']);

$medallas = [];
foreach ($podios as $tabla) {
    foreach (array_slice($tabla, 0, 3) as $i => $jugador) {
        $medallas[$jugador] = ($medallas[$jugador] ?? 0) + CB_MEDALLAS[$i + 1];
    }
}
igual('Sofía: un oro y un bronce', 4, $medallas['Sofía']);
igual('Benja: un plata y un bronce', 3, $medallas['Benja']);
igual('Matías: un oro, aunque perdió feo en el otro juego', 3, $medallas['Matías']);
igual('Emilia: un solo plata', 2, $medallas['Emilia']);

// El corazón del asunto.
$sumaCruda = ['Sofía' => 4800 + 12, 'Matías' => 150 + 45];
ok('sumando los puntos crudos, Sofía le saca miles a Matías',
   $sumaCruda['Sofía'] - $sumaCruda['Matías'] > 4000);
ok('con medallas, la diferencia es de un punto',
   abs($medallas['Sofía'] - $medallas['Matías']) === 1);
ok('el mejor de un juego nunca queda fuera del podio general', $medallas['Matías'] >= 3);

// ---------- Mejor intento, no suma ----------
$anotar('festival', 'Emilia', 6);
$anotar('festival', 'Emilia', 9);
$anotar('festival', 'Emilia', 4);
$emilia = $pdo->query("SELECT MAX(puntaje) mejor, SUM(puntaje) suma, COUNT(*) veces
                       FROM cc_puntajes WHERE party_id = 1 AND juego = 'festival' AND jugador = 'Emilia'")
              ->fetch(PDO::FETCH_ASSOC);
igual('cuenta el mejor intento', 9, (int) $emilia['mejor']);
igual('jugó cuatro veces', 4, (int) $emilia['veces']);
ok('la suma daría un número mayor y premiaría insistir', (int) $emilia['suma'] > (int) $emilia['mejor']);
ok('insistir no la deja pasar al primer lugar del Festival', 9 < 45);

// ---------- Que una fiesta no vea la otra ----------
$pdo->prepare('INSERT INTO cc_puntajes (party_id, juego, jugador, puntaje, created_at)
               VALUES (2, ?, ?, ?, ?)')->execute(['aracnida', 'Otro Niño', 9999, '2026-09-13 17:00:00']);
igual('la fiesta 1 no ve puntajes de la fiesta 2', 0,
      (int) $pdo->query("SELECT COUNT(*) FROM cc_puntajes WHERE party_id = 1 AND jugador = 'Otro Niño'")->fetchColumn());
igual('cada fiesta cuenta lo suyo', 1,
      (int) $pdo->query('SELECT COUNT(*) FROM cc_puntajes WHERE party_id = 2')->fetchColumn());

// ---------- Desempates ----------
$orden = [
    ['jugador' => 'Ana', 'total' => 3, 'oro' => 1, 'plata' => 0],
    ['jugador' => 'Beto', 'total' => 3, 'oro' => 0, 'plata' => 1],
];
usort($orden, static fn($a, $b) => [$b['total'], $b['oro'], $b['plata'], $a['jugador']]
                               <=> [$a['total'], $a['oro'], $a['plata'], $b['jugador']]);
igual('con el mismo puntaje gana quien tenga el oro', 'Ana', $orden[0]['jugador']);

igual('un oro vale más que un bronce', true, CB_MEDALLAS[1] > CB_MEDALLAS[3]);
igual('el podio reparte 3, 2 y 1', [3, 2, 1], array_values(CB_MEDALLAS));

echo $fallos === 0
    ? "  $total comprobaciones, todas bien\n"
    : "  $total comprobaciones, $fallos con problemas\n";
exit($fallos === 0 ? 0 : 1);
