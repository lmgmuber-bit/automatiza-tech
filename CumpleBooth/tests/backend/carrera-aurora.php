<?php
/**
 * Aurora de Cristal en la sala de carreras (2026-09-13).
 * La misma sala sirve al Circuito y a Aurora: cada juego solo en su temática, con sus
 * propias reglas de velocidad, ancho de pista y duración, y sin mezclar salas ni puntajes.
 */
if (PHP_SAPI !== 'cli') { exit(2); }
$carreraTemp = tempnam(sys_get_temp_dir(), 'cc-aurora-');
putenv('CARRERA_TEST_DB=' . $carreraTemp);
require __DIR__ . '/carrera-fixture.php';
require_once $carreraRoot . '/public/lib.sala.carrera.php';
$n = 0;
function check_aurora(bool $ok, string $mensaje): void {
    global $n; $n++;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $mensaje); }
}
function entrar_aurora(string $p, int $avatar = 0, string $juego = 'aurora', string $codigo = ''): array {
    return cb_carrera_entrar(['p' => $p, 'nombre' => 'Niña ' . random_int(1, 9999), 'avatar' => $avatar,
        'solicitud' => bin2hex(random_bytes(16)), 'juego' => $juego, 'codigo' => $codigo]);
}
function auth_aurora(array $r, string $p): array {
    return ['p' => $p, 'codigo' => $r['codigo'], 'ayudante' => $r['ayudante']];
}

// Cada juego en su temática.
$r = entrar_aurora('aurora-qa');
check_aurora($r['ok'] && $r['juego'] === 'aurora' && $r['modo'] === 'peques' && $r['vueltas'] === 1,
    'Aurora abre sala en una fiesta de hielo, con 1 vuelta y Peques');
check_aurora(!entrar_aurora('carrera-qa')['ok'], 'Aurora rechaza una fiesta spidey');
check_aurora(!entrar_aurora('aurora-qa', 0, 'circuito')['ok'], 'el Circuito rechaza una fiesta de hielo');
check_aurora(!entrar_aurora('aurora-qa', 0, 'otro')['ok'], 'un juego desconocido se rechaza');

// La parrilla es la misma del juego solo.
check_aurora($r['pilotos'][0]['x'] === -3.0 && $r['pilotos'][1]['x'] === 3.0 && $r['pilotos'][2]['p'] === -7.0,
    'parrilla de a dos, siete metros entre filas, a ±3 del centro');

// Un segundo niño entra a la misma sala y no repite personaje.
$b = entrar_aurora('aurora-qa', 0);
check_aurora($b['ok'] && $b['codigo'] === $r['codigo'], 'el segundo entra a la misma sala sin escribir código');
$humanos = array_values(array_filter($b['pilotos'], static fn($j) => $j['id'] > 0));
check_aurora(count($humanos) === 2 && $humanos[0]['avatar'] === 0 && $humanos[1]['avatar'] === 1,
    'un personaje ya elegido pasa al siguiente libre');
check_aurora(count(array_unique(array_column($b['pilotos'], 'avatar'))) === 6, 'seis personajes distintos en la sala');
$c = entrar_aurora('aurora-qa', 3, 'aurora', $r['codigo']);
check_aurora($c['ok'] && $c['codigo'] === $r['codigo'], 'entra con el código de la sala');

// Solo quien abrió la sala elige ayuda y vueltas.
$cfg = cb_carrera_operar('carrera_configurar', auth_aurora($r, 'aurora-qa') + ['modo' => 'desafio']);
check_aurora($cfg['ok'] && $cfg['modo'] === 'desafio' && $cfg['vueltas'] === 1, 'cambiar la ayuda no toca las vueltas');
$cfg = cb_carrera_operar('carrera_configurar', auth_aurora($r, 'aurora-qa') + ['vueltas' => 2]);
check_aurora($cfg['ok'] && $cfg['vueltas'] === 2 && $cfg['modo'] === 'desafio', 'cambiar las vueltas conserva la ayuda');
$no = cb_carrera_operar('carrera_configurar', auth_aurora($b, 'aurora-qa') + ['modo' => 'peques']);
check_aurora(!$no['ok'] && $no['http'] === 403, 'un invitado no puede configurar');
$mal = cb_carrera_operar('carrera_configurar', auth_aurora($r, 'aurora-qa') + ['modo' => '__proto__']);
check_aurora(!$mal['ok'] && $mal['http'] === 422, 'una ayuda inventada se rechaza');

// Puntaje: la ayuda elegida no compra una mejor marca.
$base = ['fin' => 135000, 'asistido' => false, 'humano_p' => 0.0];
check_aurora(cb_carrera_puntaje($base, 3, 20 / 30) === cb_carrera_puntaje(['fin' => 90000] + $base, 3, 1.0),
    '135 s en Peques valen lo mismo que 90 s en Desafío');
check_aurora(cb_carrera_puntaje(['fin' => null, 'asistido' => true, 'humano_p' => -14.0], 1, 1.0) === 0,
    'un trineo que no avanzó desde la parrilla no queda con puntaje negativo');

// Reglas de posición por juego.
$d = ['fase' => 'corriendo', 'inicio' => 0, 'vueltas' => 1, 'juego' => 'aurora', 'modo' => 'desafio'];
$j = cb_carrera_ficha(0, 0, 7, 'Ana', 0, 'aurora');
cb_carrera_posicion($j, $d, ['pos' => ['p' => 220.0, 'x' => 5.9, 'ms' => 5000, 'seq' => 1]], 5000);
check_aurora($j['p'] === 220.0 && $j['x'] === 5.9, 'Aurora acepta un trineo con impulso (44 m/s) y x hasta 6,7');
$k = cb_carrera_ficha(0, 0, 7, 'Ana', 0);
cb_carrera_posicion($k, ['fase' => 'corriendo', 'inicio' => 0, 'vueltas' => 1], ['pos' => ['p' => 220.0, 'x' => 5.9, 'ms' => 5000, 'seq' => 1]], 5000);
check_aurora($k['p'] === 0.0, 'el Circuito conserva su tope de 34 m/s');
$e = cb_carrera_ficha(0, 0, 7, 'Ana', 0, 'aurora');
cb_carrera_posicion($e, $d, ['pos' => ['p' => 900.0, 'x' => 0, 'ms' => 17000, 'seq' => 1]], 17000);
check_aurora($e['fin'] === null, 'llegar a la meta en 17 s no cuenta');
$f = cb_carrera_ficha(0, 0, 7, 'Ana', 0, 'aurora');
cb_carrera_posicion($f, $d, ['pos' => ['p' => 900.0, 'x' => 0, 'ms' => 20000, 'seq' => 1]], 20000);
check_aurora($f['fin'] === 20000, 'la llegada a una velocidad posible cuenta');

// Carrera entera contra la máquina hasta el podio, anotada como Aurora.
$solo = entrar_aurora('aurora-qa-dos', 2);
cb_carrera_operar('carrera_iniciar', auth_aurora($solo, 'aurora-qa-dos'));
$pdo = cb_pdo();
$sala = cb_carrera_leer($pdo, $solo['codigo']);
$datos = cb_carrera_datos($sala);
$datos['inicio'] -= 200000;
cb_carrera_guardar($pdo, (int) $sala['id'], $datos);
$final = cb_carrera_operar('carrera_estado', auth_aurora($solo, 'aurora-qa-dos'));
check_aurora($final['fase'] === 'podio', 'la sala llega al podio');
$bots = array_filter($final['pilotos'], static fn($j) => $j['id'] === 0);
check_aurora(count(array_filter($bots, static fn($j) => $j['fin'] !== null)) === 5, 'los cinco trineos de la máquina llegan a la meta');
check_aurora(min(array_column($bots, 'fin')) >= 18000, 'ningún trineo de la máquina llega antes del mínimo');
$juegos = $pdo->query('SELECT juego, COUNT(*) FROM cc_puntajes GROUP BY juego')->fetchAll(PDO::FETCH_KEY_PAIR);
check_aurora(($juegos['aurora'] ?? 0) >= 1 && !isset($juegos['circuito']), 'el puntaje queda anotado como aurora');

// Una sala del Circuito y una de Aurora nunca se mezclan.
$circuito = cb_carrera_entrar(['p' => 'carrera-qa', 'nombre' => 'Piloto', 'avatar' => 0, 'solicitud' => bin2hex(random_bytes(16))]);
check_aurora($circuito['ok'] && $circuito['juego'] === 'circuito' && $circuito['vueltas'] === 3, 'sin `juego` sigue siendo el Circuito de siempre');
$cruce = entrar_aurora('carrera-qa', 0, 'aurora', $circuito['codigo']);
check_aurora(!$cruce['ok'], 'no se entra a una sala del Circuito con Aurora');

echo "aurora-carrera: {$n} checks OK\n";
@unlink($carreraTemp);
