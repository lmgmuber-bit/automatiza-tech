<?php
/**
 * Revierte 011 quitando `gift_mode`.
 *
 * SQLite no supo hacer DROP COLUMN hasta 3.35, así que se intenta y si falla
 * se deja la columna: sobra sin molestar —el código la ignora si no existe—
 * y perder la base entera por deshacer una columna aditiva sería peor.
 */
return static function (PDO $pdo): void {
    try {
        $pdo->exec('ALTER TABLE cc_invitations DROP COLUMN gift_mode');
    } catch (Throwable $e) {
        // Motor sin DROP COLUMN: la columna queda, inofensiva.
    }
};
