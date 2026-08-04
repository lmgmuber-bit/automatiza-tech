<?php
/**
 * Reversa de 007. Solo borra las tablas nuevas; ninguna tabla anterior fue
 * alterada, así que revertir devuelve el esquema exactamente al estado de 006.
 * El orden importa por las claves foráneas.
 */
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_event_media');
    $pdo->exec('DROP TABLE IF EXISTS cc_event_album_tokens');
    $pdo->exec('DROP TABLE IF EXISTS cc_event_albums');
};
