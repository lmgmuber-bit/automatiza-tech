<?php
/**
 * Posiciones de la fiesta: quién ganó en cada juego y quién ganó el cumpleaños.
 *
 * La decisión de fondo es que **los puntajes de dos juegos no se suman**. Un juego de
 * plataformas reparte miles de puntos por nivel y otro reparte decenas por turno; sumarlos
 * hace que el de números grandes decida la fiesta entera y el otro no influya en nada.
 *
 * Por eso cada juego reparte su propio podio y la tabla general cuenta **medallas**: 3 por
 * un primer lugar, 2 por un segundo, 1 por un tercero. Así los dos juegos pesan igual, y un
 * niño que fue el mejor en uno solo sigue apareciendo arriba.
 *
 * Dentro de cada juego se compara el **mejor intento**, no la suma: con la suma gana el que
 * se queda pegado a la tablet toda la tarde, no el que juega bien. El que jugó una vez y le
 * salió bien compite de igual a igual con el que jugó diez.
 */

require_once __DIR__ . '/lib.php';

/** Cuánto vale cada lugar del podio en la tabla general. */
const CB_MEDALLAS = [1 => 3, 2 => 2, 3 => 1];

/** Los juegos que pueden reportar, con el nombre que ve la gente. */
function cb_juegos(): array
{
    return [
        'reino-hielo' => ['nombre' => 'Reino de Hielo en 3D', 'tema' => 'hielo'],
        'festival' => ['nombre' => 'El Festival de las Estrellas', 'tema' => 'hielo'],
        'aracnida' => ['nombre' => 'Aventura Arácnida en 3D', 'tema' => 'spidey'],
        // Segundo juego de la temática Spidey, escrito aparte. Convive con el anterior: los
        // dos aparecen en el menú de esa fiesta. Va en `spidey` y NO en `heroes` a propósito
        // —son dos temáticas distintas del admin, aunque compartan el mundo 3D—, y meterlo
        // en `heroes` lo haría aparecer en Misión 3D, que es otra fiesta.
        'impulso' => ['nombre' => 'Impulso Arácnido', 'tema' => 'spidey'],
        'mision' => ['nombre' => 'Misión 3D', 'tema' => 'heroes'],
    ];
}

/** Los juegos de una temática, en el orden en que se muestran. */
function cb_juegos_de_tema(string $tema): array
{
    $r = [];
    foreach (cb_juegos() as $id => $j) {
        if ($j['tema'] === $tema) { $r[$id] = $j; }
    }
    return $r;
}

function cb_puntajes_disponible(): bool
{
    if (cb_storage_mode() !== 'db') { return false; }
    try {
        cb_pdo()->query('SELECT 1 FROM cc_puntajes LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Anota un intento. Devuelve `['ok' => bool, 'error' => string]`.
 *
 * El nombre se limpia igual que en el resto de la aplicación y se recorta: viene escrito por
 * un niño en una tablet, así que puede traer cualquier cosa. El puntaje se acota para que un
 * valor absurdo —por error o por alguien jugando con la consola— no deje la tabla inservible
 * para el resto de la fiesta.
 */
function cb_puntaje_anotar(string $slug, string $juego, string $jugador, int $puntaje): array
{
    if (!cb_puntajes_disponible()) {
        return ['ok' => false, 'error' => 'Las posiciones necesitan la migración 018.'];
    }
    if (!isset(cb_juegos()[$juego])) {
        return ['ok' => false, 'error' => 'Ese juego no existe.'];
    }
    $partyId = cb_party_db_id($slug);
    if ($partyId === null) {
        return ['ok' => false, 'error' => 'La fiesta no existe.'];
    }
    $jugador = cb_puntaje_nombre($jugador);
    if ($jugador === '') {
        return ['ok' => false, 'error' => 'Falta el nombre del jugador.'];
    }
    if ($puntaje < 0 || $puntaje > 999999) {
        return ['ok' => false, 'error' => 'El puntaje está fuera de rango.'];
    }

    cb_pdo()->prepare('INSERT INTO cc_puntajes (party_id, juego, jugador, puntaje, created_at)
                       VALUES (?, ?, ?, ?, ?)')
        ->execute([$partyId, $juego, $jugador, $puntaje, date('Y-m-d H:i:s')]);
    return ['ok' => true, 'error' => ''];
}

/**
 * Normaliza el nombre para que un mismo niño no aparezca dos veces.
 *
 * "lucho", "Lucho " y "LUCHO" son el mismo invitado; sin esto la tabla se llena de dobles y
 * deja de servir justo cuando más se mira. Se guarda con la primera letra en mayúscula, que
 * es como se ve bien impreso en el recuerdito.
 */
function cb_puntaje_nombre(string $bruto): string
{
    $limpio = preg_replace('/[\x00-\x1f<>]/u', '', trim($bruto));
    $limpio = preg_replace('/\s+/u', ' ', (string) $limpio);
    $limpio = mb_substr((string) $limpio, 0, 40);
    return $limpio === '' ? '' : mb_convert_case($limpio, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Todo lo que necesita la pantalla de posiciones de una fiesta.
 *
 * Devuelve el podio de cada juego y la tabla general por medallas. Un jugador aparece una
 * sola vez por juego, con su mejor intento y cuántas veces jugó.
 */
function cb_posiciones(string $slug): array
{
    $vacio = ['hay_datos' => false, 'juegos' => [], 'general' => [], 'partidas' => 0, 'jugadores' => 0];
    if (!cb_puntajes_disponible()) { return $vacio; }
    $partyId = cb_party_db_id($slug);
    if ($partyId === null) { return $vacio; }

    $stmt = cb_pdo()->prepare(
        'SELECT juego, jugador, MAX(puntaje) AS mejor, COUNT(*) AS veces
         FROM cc_puntajes WHERE party_id = ?
         GROUP BY juego, jugador
         ORDER BY juego, mejor DESC, jugador'
    );
    $stmt->execute([$partyId]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$filas) { return $vacio; }

    $catalogo = cb_juegos();
    $juegos = [];
    $medallas = [];
    $partidas = 0;
    $jugadores = [];

    foreach ($filas as $f) {
        $id = (string) $f['juego'];
        $nombre = cb_puntaje_nombre((string) $f['jugador']);
        $juegos[$id] ??= ['id' => $id, 'nombre' => $catalogo[$id]['nombre'] ?? $id, 'tabla' => []];
        $juegos[$id]['tabla'][] = [
            'lugar' => count($juegos[$id]['tabla']) + 1,
            'jugador' => $nombre,
            'puntaje' => (int) $f['mejor'],
            'veces' => (int) $f['veces'],
        ];
        $partidas += (int) $f['veces'];
        $jugadores[$nombre] = true;
    }

    // Las medallas se reparten después de armar cada podio: hasta no tener el juego
    // completo no se sabe quién quedó tercero.
    foreach ($juegos as $j) {
        foreach ($j['tabla'] as $fila) {
            $nombre = $fila['jugador'];
            $medallas[$nombre] ??= ['jugador' => $nombre, 'oro' => 0, 'plata' => 0, 'bronce' => 0, 'total' => 0];
            $lugar = $fila['lugar'];
            if ($lugar > 3) { continue; }
            $medallas[$nombre][[1 => 'oro', 2 => 'plata', 3 => 'bronce'][$lugar]]++;
            $medallas[$nombre]['total'] += CB_MEDALLAS[$lugar];
        }
    }

    // Empate en puntos: primero quien tenga más oros, después más platas. Un primer lugar
    // vale más que dos terceros aunque sumen parecido.
    $general = array_values($medallas);
    usort($general, static function ($a, $b) {
        return [$b['total'], $b['oro'], $b['plata'], $a['jugador']]
            <=> [$a['total'], $a['oro'], $a['plata'], $b['jugador']];
    });
    foreach ($general as $i => &$g) { $g['lugar'] = $i + 1; }
    unset($g);

    return [
        'hay_datos' => true,
        'juegos' => array_values($juegos),
        'general' => $general,
        'partidas' => $partidas,
        'jugadores' => count($jugadores),
    ];
}
