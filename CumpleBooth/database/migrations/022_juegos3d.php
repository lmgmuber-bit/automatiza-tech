<?php
/** Interruptor de los juegos 3D por fiesta. Por defecto prendidos: apagar una fiesta que
 *  ya existe por un cambio de esquema seria una sorpresa desagradable. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    try {
        $pdo->exec('ALTER TABLE cc_parties ADD COLUMN games3d_enabled TINYINT(1) NOT NULL DEFAULT 1');
    } catch (PDOException $e) {
        // 1060 = la columna ya existe. Correrla dos veces no puede romper nada.
        if (!$mysql || strpos($e->getMessage(), '1060') === false) { throw $e; }
    }
};
