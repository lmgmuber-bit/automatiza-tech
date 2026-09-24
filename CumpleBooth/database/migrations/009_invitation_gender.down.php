<?php
/** Reversa de 009. Elimina la columna agregada; ninguna otra tabla cambió. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql) {
        $pdo->exec('ALTER TABLE cc_invitations DROP COLUMN birthday_person_gender');
        return;
    }
    try {
        $pdo->exec('ALTER TABLE cc_invitations DROP COLUMN birthday_person_gender');
    } catch (Throwable $e) {
        // SQLite < 3.35 no soporta DROP COLUMN; sin acción segura de reversa
        // en esas versiones. La columna queda NULL e inerte si esto falla.
    }
};
