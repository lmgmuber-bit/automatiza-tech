<?php
/**
 * Edad que cumple el protagonista de la fiesta.
 *
 * Hasta ahora la edad solo viajaba en la URL del juego (`?edad=`), así que había que
 * acordarse de escribirla en cada enlace y no aparecía en ningún otro lado. Es un dato de la
 * fiesta —se sabe al cargarla y no cambia—, así que vive en la ficha y de ahí lo toman el
 * menú de juegos, los juegos y cualquier cosa que venga después.
 *
 * Se guarda como número y no como fecha de nacimiento a propósito: lo que se muestra es
 * "cumple 5 años", y pedir la fecha exacta de un niño es pedir un dato personal que no hace
 * falta para eso.
 *
 * Acepta NULL: las fiestas que ya existen no la tienen, y la pantalla debe funcionar igual
 * mientras nadie la escriba.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $existentes = [];
    $stmt = $pdo->query($mysql ? 'SHOW COLUMNS FROM cc_parties' : 'PRAGMA table_info(cc_parties)');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $existentes[] = (string) ($fila['Field'] ?? $fila['name'] ?? '');
    }
    if (in_array('birthday_age', $existentes, true)) {
        return;
    }
    $pdo->exec('ALTER TABLE cc_parties ADD COLUMN birthday_age ' . ($mysql ? 'TINYINT NULL' : 'INTEGER NULL'));
};
