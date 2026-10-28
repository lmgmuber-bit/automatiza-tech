<?php
/** Quita las tres columnas: se pierden equipo, vínculo al regalo y clave de la foto (el archivo de la foto queda en disco). */
return static function (PDO $pdo): void {
    foreach (['baby_photo_key', 'gift_item_id', 'team'] as $columna) {
        $pdo->exec("ALTER TABLE cc_rsvps DROP COLUMN $columna");
    }
};
