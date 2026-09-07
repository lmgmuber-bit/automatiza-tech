<?php
/**
 * Puntajes de los juegos 3D, para la tabla de posiciones de la fiesta.
 *
 * Se guarda **cada intento**, no el mejor: el mejor se calcula al leer. Guardar solo el
 * máximo obligaría a decidir en el momento de escribir qué significa "mejor" —y ese criterio
 * cambió una vez ya (suma contra mejor intento)—, además de impedir mostrar cuántas veces
 * jugó cada niño, que es lo que hace que la tabla se sienta viva durante la fiesta.
 *
 * `juego` es texto y no una tabla aparte a propósito: los juegos son tres, viven en carpetas
 * distintas y algunos los escribe otro agente. Una llave foránea obligaría a registrar cada
 * juego nuevo antes de que pueda reportar, y el primer puntaje se perdería en silencio.
 *
 * No hay datos personales: `jugador` es el nombre de pila que el niño elige en la tablet, el
 * mismo que ya vive en `cc_guests`.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    if ($mysql) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_puntajes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                party_id INT NOT NULL,
                juego VARCHAR(40) NOT NULL,
                jugador VARCHAR(60) NOT NULL,
                puntaje INT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_puntajes_fiesta (party_id, juego),
                INDEX idx_puntajes_jugador (party_id, jugador)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cc_puntajes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            party_id INTEGER NOT NULL,
            juego TEXT NOT NULL,
            jugador TEXT NOT NULL,
            puntaje INTEGER NOT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_puntajes_fiesta ON cc_puntajes (party_id, juego)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_puntajes_jugador ON cc_puntajes (party_id, jugador)');
};
