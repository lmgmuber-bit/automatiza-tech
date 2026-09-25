<?php
/** Interruptor de juegos 3D; repetible en MySQL y SQLite sin cambiar datos existentes. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql) {
        $exists = $pdo->query("SHOW COLUMNS FROM cc_parties LIKE 'games3d_enabled'")->fetch();
    } else {
        $exists = false;
        foreach ($pdo->query('PRAGMA table_info(cc_parties)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ($column['name'] === 'games3d_enabled') { $exists = true; break; }
        }
    }
    if (!$exists) {
        $pdo->exec('ALTER TABLE cc_parties ADD COLUMN games3d_enabled TINYINT(1) NOT NULL DEFAULT 1');
    }
};
