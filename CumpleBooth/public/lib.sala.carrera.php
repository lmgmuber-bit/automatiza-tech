<?php
/** Carrera como extensión de salas: misma sala, ayudantes y tokens; sin identidad por IP. */
declare(strict_types=1);
require_once __DIR__ . '/lib.sala.php';
require_once __DIR__ . '/lib.puntajes.php';

const CB_CARRERA_META = 2700;
const CB_CARRERA_MAX_MS = 150000;
const CB_CARRERA_MIN_MS = 90000;
const CB_CARRERA_DESCONEXION_MS = 4500;
/** Velocidad base de Aurora por nivel de ayuda, en m/s; la elige quien abre la sala. */
const CB_CARRERA_AURORA_MODOS = ['peques' => 20, 'aventura' => 25, 'desafio' => 30];

/** Datos anteriores sin configuración conservan sus tres vueltas. */
function cb_carrera_vueltas(array $d): int { return max(1, min(5, (int) ($d['vueltas'] ?? 3))); }
function cb_carrera_meta(array $d): int { return cb_carrera_vueltas($d) * 900; }
function cb_carrera_max_ms(array $d): int { return cb_carrera_vueltas($d) * cb_carrera_reglas($d)['max_vuelta_ms']; }
function cb_carrera_min_ms(array $d): int { return cb_carrera_vueltas($d) * cb_carrera_reglas($d)['min_vuelta_ms']; }

/**
 * La misma sala sirve a dos carreras: el Circuito Arácnido (autos, temática spidey) y
 * Aurora de Cristal (trineos, temática hielo), desde el 2026-09-13. Las dos miden 900 m por
 * vuelta y tienen seis plazas; cambian la velocidad, el ancho de la pista y cuánto puede
 * durar una vuelta. Una sala sin `juego` es del Circuito, que es como se crearon todas
 * antes: sus números no cambian.
 */
function cb_carrera_juego(array $d): string { return ($d['juego'] ?? 'circuito') === 'aurora' ? 'aurora' : 'circuito'; }
function cb_carrera_reglas(array $d): array {
    if (cb_carrera_juego($d) === 'aurora') {
        // Aurora corre a 20, 25 o 30 m/s según la ayuda y el impulso multiplica por 1,48:
        // 44,4 m/s. Con el tope del Circuito (34) el servidor botaría las posiciones de un
        // trineo con impulso y lo dejaría congelado para los demás. La pista va de -6,7 a
        // 6,7, no de -1 a 1. Una vuelta en Peques toma unos 45 s y con choques pasa de 60.
        return ['sala' => 'Aurora', 'vel_max' => 0.046, 'x_max' => 6.7,
            'min_vuelta_ms' => 18000, 'max_vuelta_ms' => 80000];
    }
    return ['sala' => 'Circuito', 'vel_max' => 0.034, 'x_max' => 1,
        'min_vuelta_ms' => 30000, 'max_vuelta_ms' => 50000];
}
function cb_carrera_modo(array $d): string {
    $m = (string) ($d['modo'] ?? 'peques');
    return isset(CB_CARRERA_AURORA_MODOS[$m]) ? $m : 'peques';
}
/** Iguala la ayuda de Aurora en el puntaje; en el Circuito vale 1. */
function cb_carrera_ritmo(array $d): float {
    return cb_carrera_juego($d) === 'aurora' ? CB_CARRERA_AURORA_MODOS[cb_carrera_modo($d)] / 30 : 1.0;
}

function cb_carrera_ms(): int { return (int) floor(microtime(true) * 1000); }
function cb_carrera_error(string $texto, int $http = 409): array {
    return ['ok' => false, 'error' => $texto, 'http' => $http];
}
function cb_carrera_lock(PDO $pdo): string {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}
function cb_carrera_datos(array $sala): array {
    return json_decode((string) $sala['datos'], true, 32, JSON_THROW_ON_ERROR);
}
function cb_carrera_guardar(PDO $pdo, int $id, array $d): void {
    $pdo->prepare('UPDATE cc_sala_carreras SET datos=? WHERE sala_id=?')
        ->execute([json_encode($d, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $id]);
}
function cb_carrera_leer(PDO $pdo, string $codigo): ?array {
    $stmt = $pdo->prepare('SELECT s.*, c.party_id, c.datos FROM cc_salas s
        JOIN cc_sala_carreras c ON c.sala_id=s.id WHERE s.codigo=?' . cb_carrera_lock($pdo));
    $stmt->execute([$codigo]);
    return $stmt->fetch() ?: null;
}
function cb_carrera_ficha(int $slot, int $now, int $id = 0, string $nombre = '', int $avatar = 0, string $juego = 'circuito'): array {
    $f = ['slot' => $slot, 'id' => $id, 'nombre' => $nombre ?: 'Piloto ' . ($slot + 1),
        'avatar' => $avatar, 'p' => 0.0, 'x' => ($slot % 3 - 1) * .6, 'seq' => -1, 'last' => $now,
        'bot' => $id === 0, 'asistido' => false, 'fin' => null, 'humano_p' => 0.0,
        'humano_ms' => 0, 'anotado' => false, 'join' => '', 'turbo' => false];
    if ($juego === 'aurora') {
        // La parrilla de Aurora: de a dos, siete metros entre filas, a ±3 del centro. Es la
        // misma que arma el juego solo, así ningún trineo salta de lugar al partir.
        $f['p'] = $f['humano_p'] = $f['p0'] = (float) (-intdiv($slot, 2) * 7);
        $f['x'] = $slot % 2 ? 3.0 : -3.0;
        if ($nombre === '') { $f['nombre'] = 'Trineo ' . ($slot + 1); }
    }
    return $f;
}
function cb_carrera_puntaje(array $j, int $vueltas = 3, float $ritmo = 1.0): int {
    // Equivalente a tres vueltas: la duración elegida no compra una mejor marca.
    // `ritmo` iguala la ayuda de Aurora: en Peques el trineo va a 20 m/s y en Desafío a 30,
    // así que el mismo niño tarda un 50 % más en Peques. Sin esto la tabla premiaría elegir
    // Desafío y no correr bien. En el Circuito vale 1 y el cálculo es el de siempre.
    return $j['fin'] !== null && !$j['asistido']
        ? max(50000, 200000 - (int) round($j['fin'] * $ritmo * 3 / $vueltas))
        : max(0, (int) round($j['humano_p'] * 30 / $vueltas));
}
/** En espera los bots completan los roles ausentes; los humanos del Circuito sí pueden repetir. */
function cb_carrera_completar_equipo(array &$d): void {
    if ($d['fase'] !== 'espera') { return; }
    if (cb_carrera_juego($d) === 'aurora') {
        // En Aurora cada personaje tiene UN trineo en la escena: dos niños con la princesa
        // se dibujarían como uno solo. El que llegó antes conserva su elección y el
        // siguiente toma el próximo personaje libre. En el Circuito cada auto se clona.
        $tomados = [];
        foreach ($d['pilotos'] as &$j) {
            if (!$j['id']) { continue; }
            $avatar = max(0, min(5, (int) $j['avatar']));
            for ($n = 0; $n < 6 && isset($tomados[$avatar]); $n++) { $avatar = ($avatar + 1) % 6; }
            $j['avatar'] = $avatar; $tomados[$avatar] = true;
        }
        unset($j);
    }
    $usados = [];
    foreach ($d['pilotos'] as $j) { if ($j['id']) { $usados[(int) $j['avatar']] = true; } }
    foreach ($d['pilotos'] as &$j) {
        if ($j['id']) { continue; }
        $avatar = (int) $j['avatar'];
        if ($avatar < 0 || $avatar > 5 || isset($usados[$avatar])) {
            for ($avatar = 0; $avatar < 6 && isset($usados[$avatar]); $avatar++) {}
        }
        $j['avatar'] = min(5, $avatar); $usados[$j['avatar']] = true;
    }
    unset($j);
}
function cb_carrera_anotar(array &$d, array &$j): void {
    if (!$j['id'] || $j['anotado']) { return; }
    $r = cb_puntaje_anotar($d['fiesta'], cb_carrera_juego($d), $j['nombre'],
        cb_carrera_puntaje($j, cb_carrera_vueltas($d), cb_carrera_ritmo($d)));
    if (!empty($r['ok'])) { $j['anotado'] = true; }
}
function cb_carrera_partir(array &$d, int $now): void {
    if ($d['fase'] !== 'espera') { return; }
    $d['fase'] = 'cuenta';
    $d['inicio'] = $now + 3500;
    foreach ($d['pilotos'] as &$j) { $j['last'] = $d['inicio']; }
    unset($j);
}
/** Solo el servidor conduce bots y fija su llegada. Humanos reportan tiempos, no puestos. */
function cb_carrera_avanzar(array &$d, int $now): void {
    if ($d['fase'] === 'espera' && $now >= $d['creada'] + 45000) {
        cb_carrera_partir($d, $d['creada'] + 45000);
    }
    if ($d['fase'] === 'cuenta' && $now >= $d['inicio']) { $d['fase'] = 'corriendo'; }
    if ($d['fase'] !== 'corriendo') { return; }
    $elapsed = max(0, min(cb_carrera_max_ms($d), $now - $d['inicio']));
    $meta = cb_carrera_meta($d);
    $aurora = cb_carrera_juego($d) === 'aurora';
    foreach ($d['pilotos'] as &$j) {
        if ($j['fin'] !== null) { continue; }
        if (!$j['bot'] && $now - $j['last'] > CB_CARRERA_DESCONEXION_MS) {
            $j['bot'] = true;
            $j['asistido'] = true;
            cb_carrera_anotar($d, $j);
        }
        if ($j['bot']) {
            // Aurora: los rivales van entre el 90 % y el 101 % de la velocidad de la ayuda
            // elegida, como los del juego solo. A la de Peques, un bot del Circuito (22 a
            // 26 m/s) le sacaría ventaja de sobra a un niño de cuatro años.
            $vel = $aurora
                ? CB_CARRERA_AURORA_MODOS[cb_carrera_modo($d)] * (0.90 + (($d['semilla'] + $j['slot'] * 7) % 11) * 0.011)
                : 22.3 + (($d['semilla'] + $j['slot'] * 7) % 11) * 0.34;
            $base = $j['id'] ? $j['humano_p'] : (float) ($j['p0'] ?? 0);
            $desde = $j['id'] ? $j['humano_ms'] : 0;
            $j['p'] = min($meta, $base + max(0, $elapsed - $desde) * $vel / 1000);
            $j['x'] = sin($elapsed / 4000 + $j['slot'] * 2) * ($aurora ? 4.5 : 0.65);
            if ($j['p'] >= $meta) {
                $j['fin'] = max(cb_carrera_min_ms($d), (int) round($desde + ($meta - $base) * 1000 / $vel));
            }
        }
    }
    unset($j);
    $terminados = count(array_filter($d['pilotos'], static fn($j) => $j['fin'] !== null));
    if ($elapsed >= cb_carrera_max_ms($d) || $terminados === 6) {
        $d['fase'] = 'podio';
        // Cierra aun si un cliente desapareció antes de poder enviar su beacon.
        foreach ($d['pilotos'] as &$j) { cb_carrera_anotar($d, $j); }
        unset($j);
    }
}
function cb_carrera_publica(array $sala, array $d, int $id, int $now): array {
    $pilotos = [];
    foreach ($d['pilotos'] as $j) {
        $pilotos[] = array_intersect_key($j, array_flip([
            'slot', 'id', 'nombre', 'avatar', 'p', 'x', 'bot', 'asistido', 'fin', 'humano_p', 'humano_ms', 'turbo'
        ])) + ['puntaje' => cb_carrera_puntaje($j, cb_carrera_vueltas($d), cb_carrera_ritmo($d))];
    }
    $orden = $pilotos;
    usort($orden, static function ($a, $b) {
        return [($a['fin'] ?? PHP_INT_MAX), -$a['p'], $a['slot']]
            <=> [($b['fin'] ?? PHP_INT_MAX), -$b['p'], $b['slot']];
    });
    return ['ok' => true, 'codigo' => $sala['codigo'], 'yo' => $id,
        'anfitrion' => $d['host'] === $id, 'fase' => $d['fase'], 'ahora' => $now,
        'inicio' => $d['inicio'], 'autoinicio' => $d['creada'] + 45000,
        'semilla' => $d['semilla'], 'pilotos' => $pilotos,
        'orden' => array_column($orden, 'slot'), 'vueltas' => cb_carrera_vueltas($d),
        'max_ms' => cb_carrera_max_ms($d), 'meta' => cb_carrera_meta($d),
        'juego' => cb_carrera_juego($d), 'modo' => cb_carrera_modo($d)];
}

/** La fiesta serializa descubrimiento/creación: dos primeros clics no crean dos salas. */
function cb_carrera_entrar(array $i): array {
    $slug = $i['p'] ?? null;
    $nonce = cb_sala_token_valido($i['solicitud'] ?? null);
    $nombre = cb_sala_nombre($i['nombre'] ?? '', 40);
    $codigo = ($i['codigo'] ?? '') === '' ? '' : cb_sala_codigo_valido($i['codigo']);
    // Sin `juego` es el Circuito: así sigue entrando el cliente del Circuito que ya está en PROD.
    $juego = $i['juego'] ?? 'circuito';
    if (!in_array($juego, ['circuito', 'aurora'], true)) { $juego = null; }
    if (!is_string($slug) || !cb_valid_public_slug($slug) || $nonce === null || $nombre === '' || $codigo === null || $juego === null) {
        return cb_carrera_error('Revisa el enlace o el código de la sala.', 422);
    }
    $pdo = cb_sala_pdo(); $now = cb_carrera_ms();
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id, theme_slug, active FROM cc_parties WHERE public_slug=?' . cb_carrera_lock($pdo));
        $q->execute([$slug]); $fiesta = $q->fetch();
        if (!$fiesta || $fiesta['theme_slug'] !== cb_juegos()[$juego]['tema'] || !(int) $fiesta['active']) {
            $pdo->rollBack(); return cb_carrera_error('Esta fiesta no tiene la carrera disponible.', 404);
        }
        $q = $pdo->prepare('SELECT s.codigo FROM cc_salas s JOIN cc_sala_carreras c ON c.sala_id=s.id
            WHERE c.party_id=? AND s.estado=? AND s.expires_at>? ORDER BY s.id DESC LIMIT 30');
        $q->execute([(int) $fiesta['id'], 'activa', cb_sala_ahora()]);
        $sala = null; $d = null; $join = cb_hash_token($nonce);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $c) {
            if ($codigo !== '' && $codigo !== $c) { continue; }
            $candidata = cb_carrera_leer($pdo, $c);
            $datos = cb_carrera_datos($candidata);
            // Una sala de otra carrera no se toca: ni se entra ni se avanza desde acá.
            if (cb_carrera_juego($datos) !== $juego) { continue; }
            cb_carrera_avanzar($datos, $now);
            foreach ($datos['pilotos'] as $j) {
                if ($j['join'] === $join) {
                    cb_carrera_guardar($pdo, (int) $candidata['id'], $datos);
                    $pdo->commit();
                    return cb_carrera_publica($candidata, $datos, $j['id'], $now)
                        + ['ayudante' => substr(cb_hmac($nonce . $c, 'carrera-jugador'), 0, 32)];
                }
            }
            cb_carrera_guardar($pdo, (int) $candidata['id'], $datos);
            if ($datos['fase'] === 'espera' && count(array_filter($datos['pilotos'], static fn($j) => $j['id'] > 0)) < 6) {
                $sala = $candidata; $d = $datos; break;
            }
        }
        if ($sala === null && $codigo !== '') {
            $pdo->rollBack(); return cb_carrera_error('Esa sala ya partió, está llena o pertenece a otra fiesta.');
        }
        if ($sala === null) {
            // Cuota por fiesta, no por WiFi. Impide creación ilimitada de salas.
            $q = $pdo->prepare('SELECT COUNT(*) FROM cc_sala_carreras c JOIN cc_salas s ON s.id=c.sala_id WHERE c.party_id=? AND s.created_at>?');
            $q->execute([(int) $fiesta['id'], gmdate('Y-m-d H:i:s', time() - 60)]);
            if ((int) $q->fetchColumn() >= 12) { $pdo->rollBack(); return cb_carrera_error('Espera un momento antes de abrir otra sala.', 429); }
            $creada = cb_sala_crear(cb_carrera_reglas(['juego' => $juego])['sala'], $nonce);
            $sala = cb_sala_buscar($pdo, $creada['codigo']);
            $d = ['fase' => 'espera', 'fiesta' => $slug, 'creada' => $now, 'inicio' => 0,
                'host' => 0, 'vueltas' => 3, 'semilla' => random_int(1, 99999), 'pilotos' => []];
            // Aurora parte con una vuelta, como el juego solo: seis niños chicos en la salida.
            if ($juego === 'aurora') { $d['juego'] = 'aurora'; $d['modo'] = 'peques'; $d['vueltas'] = 1; }
            for ($n = 0; $n < 6; $n++) { $d['pilotos'][] = cb_carrera_ficha($n, $now, 0, '', $n, $juego); }
            $pdo->prepare('INSERT INTO cc_sala_carreras(sala_id,party_id,datos) VALUES(?,?,?)')
                ->execute([(int) $sala['id'], (int) $fiesta['id'], json_encode($d)]);
        }
        $ayudante = cb_sala_unirse($sala['codigo'], $nombre, $nonce);
        if (!$ayudante['ok']) { $pdo->rollBack(); return $ayudante; }
        $token = substr(cb_hmac($nonce . $sala['codigo'], 'carrera-jugador'), 0, 32);
        $pdo->prepare('UPDATE cc_sala_ayudantes SET token_hash=?, nombre=? WHERE id=?')
            ->execute([cb_hash_token($token), $nombre, $ayudante['id']]);
        foreach ($d['pilotos'] as $n => $j) {
            if ($j['id']) { continue; }
            $d['pilotos'][$n] = cb_carrera_ficha($n, $now, $ayudante['id'], $nombre, max(0, min(5, (int) ($i['avatar'] ?? 0))), $juego);
            $d['pilotos'][$n]['join'] = $join;
            break;
        }
        if (!$d['host']) { $d['host'] = $ayudante['id']; }
        cb_carrera_completar_equipo($d);
        cb_carrera_guardar($pdo, (int) $sala['id'], $d);
        $pdo->commit();
        return cb_carrera_publica($sala, $d, $ayudante['id'], $now) + ['ayudante' => $token];
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
}

/** Tiempo recibido se valida contra reloj servidor y progreso previo; paquetes viejos no retroceden. */
function cb_carrera_posicion(array &$j, array $d, array $i, int $now): void {
    if ($d['fase'] !== 'corriendo' || $j['bot'] || $j['fin'] !== null || !isset($i['pos'])) { return; }
    $pos = $i['pos'];
    if (!is_array($pos)) { return; }
    foreach (['p', 'x', 'ms', 'seq'] as $k) {
        if (!isset($pos[$k]) || !is_numeric($pos[$k]) || !is_finite((float) $pos[$k])) { return; }
    }
    $reglas = cb_carrera_reglas($d); $vmax = $reglas['vel_max']; $xmax = $reglas['x_max'];
    $seq = (int) $pos['seq']; $elapsed = $now - $d['inicio'];
    $ms = (int) $pos['ms']; $p = (float) $pos['p'];
    if ($seq <= $j['seq'] || $ms < $j['humano_ms'] || $ms > $elapsed + 500 || $ms < $elapsed - 4500
        || $ms > cb_carrera_max_ms($d) || $p < $j['p'] || $p > cb_carrera_meta($d)
        || $p > $ms * $vmax + 1 || $p - $j['p'] > ($ms - $j['humano_ms']) * $vmax + 1) { return; }
    $j['p'] = $p; $j['x'] = max(-$xmax, min($xmax, (float) $pos['x']));
    $j['seq'] = $seq; $j['humano_p'] = $p; $j['humano_ms'] = $ms;
    $j['turbo'] = !empty($pos['turbo']);
    if ($p >= cb_carrera_meta($d) && $ms >= cb_carrera_min_ms($d)) { $j['fin'] = $ms; }
}

function cb_carrera_operar(string $op, array $i): array {
    if ($op === 'carrera_entrar') { return cb_carrera_entrar($i); }
    $codigo = cb_sala_codigo_valido($i['codigo'] ?? null);
    $token = cb_sala_token_valido($i['ayudante'] ?? null);
    $slug = $i['p'] ?? null;
    if ($codigo === null || $token === null || !is_string($slug)) { return cb_carrera_error('La sesión de carrera no es válida.', 403); }
    $pdo = cb_sala_pdo(); $now = cb_carrera_ms();
    $pdo->beginTransaction();
    try {
        $sala = cb_carrera_leer($pdo, $codigo);
        if (!$sala || $sala['estado'] !== 'activa' || $sala['expires_at'] < cb_sala_ahora()) {
            $pdo->rollBack(); return cb_carrera_error('Esta sala ya terminó.', 404);
        }
        $d = cb_carrera_datos($sala);
        $a = cb_sala_ayudante($pdo, $sala, $token);
        if ($d['fiesta'] !== $slug || !$a) { $pdo->rollBack(); return cb_carrera_error('No puedes entrar a esta sala.', 403); }
        $slot = array_search((int) $a['id'], array_column($d['pilotos'], 'id'), true);
        if ($slot === false) { $pdo->rollBack(); return cb_carrera_error('La plaza ya no está disponible.', 403); }
        cb_carrera_avanzar($d, $now);
        $j = &$d['pilotos'][$slot];
        if ($op === 'carrera_configurar') {
            if ($d['host'] !== (int) $a['id']) { $pdo->rollBack(); return cb_carrera_error('Solo quien abrió la sala puede elegir las vueltas.', 403); }
            if ($d['fase'] !== 'espera') { $pdo->rollBack(); return cb_carrera_error('La carrera ya partió; las vueltas no se pueden cambiar.'); }
            // Aurora también deja elegir la ayuda, sola o junto con las vueltas.
            $conModo = cb_carrera_juego($d) === 'aurora' && isset($i['modo']);
            if ($conModo && (!is_string($i['modo']) || !isset(CB_CARRERA_AURORA_MODOS[$i['modo']]))) {
                $pdo->rollBack(); return cb_carrera_error('Elige Peques, Aventura o Desafío.', 422);
            }
            if (!($conModo && !isset($i['vueltas']))
                && (!isset($i['vueltas']) || !is_int($i['vueltas']) || $i['vueltas'] < 1 || $i['vueltas'] > 5)) {
                $pdo->rollBack(); return cb_carrera_error('Elige entre 1 y 5 vueltas.', 422);
            }
            if (isset($i['vueltas'])) { $d['vueltas'] = $i['vueltas']; }
            if ($conModo) { $d['modo'] = $i['modo']; }
        }
        if ($op === 'carrera_iniciar') {
            if ($d['host'] !== (int) $a['id']) { $pdo->rollBack(); return cb_carrera_error('Solo quien abrió la sala puede iniciar.', 403); }
            cb_carrera_partir($d, $now);
        }
        if ($op === 'carrera_estado' && ($now - ($j['peticion'] ?? 0)) >= 120) {
            cb_carrera_posicion($j, $d, $i, $now);
            $j['last'] = max($j['last'], $now); $j['peticion'] = $now;
            if ($d['fase'] === 'espera') { $j['avatar'] = max(0, min(5, (int) ($i['avatar'] ?? $j['avatar']))); }
        }
        if ($op === 'carrera_salir') {
            if ($d['fase'] === 'espera') {
                $d['pilotos'][$slot] = cb_carrera_ficha($slot, $now, 0, '', $slot, cb_carrera_juego($d));
                // Libera también la identidad heredada: entrar/salir no consume cupos para siempre.
                $pdo->prepare('DELETE FROM cc_sala_acciones WHERE ayudante_id=? AND sala_id=?')->execute([(int) $a['id'], (int) $sala['id']]);
                $pdo->prepare('DELETE FROM cc_sala_ayudantes WHERE id=? AND sala_id=?')->execute([(int) $a['id'], (int) $sala['id']]);
                if ($d['host'] === (int) $a['id']) {
                    $resto = array_values(array_filter($d['pilotos'], static fn($p) => $p['id'] > 0));
                    $d['host'] = $resto[0]['id'] ?? 0;
                }
            } else {
                cb_carrera_posicion($j, $d, $i, $now);
                if ($j['fin'] === null) { $j['asistido'] = true; $j['bot'] = true; }
                cb_carrera_anotar($d, $j);
            }
        }
        if ($op === 'carrera_puntaje') {
            if ($j['fin'] === null && !$j['asistido'] && $d['fase'] !== 'podio') {
                $pdo->rollBack(); return cb_carrera_error('Tu carrera todavía sigue.');
            }
            cb_carrera_anotar($d, $j);
        }
        unset($j);
        cb_carrera_completar_equipo($d);
        cb_carrera_avanzar($d, $now);
        cb_carrera_guardar($pdo, (int) $sala['id'], $d);
        $pdo->commit();
        return cb_carrera_publica($sala, $d, (int) $a['id'], $now);
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
}
