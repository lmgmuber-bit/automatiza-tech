<?php
/** Quita la columna: se pierden los juegos apagados de cada fiesta (todos vuelven a aparecer). */
return static function (PDO $pdo): void {
    $pdo->exec('ALTER TABLE cc_parties DROP COLUMN animador_apagados');
};
