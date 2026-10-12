<?php
/**
 * Juegos del animador que el admin apagó en una fiesta de adulto (AT-CUMPLECLICK-026).
 *
 * Una columna propia y no una clave del registro: `cb_save_parties()` reescribe la fila con
 * una lista fija de columnas y esta no está en ella. La escriben solo
 * `cb_save_party_animador_apagados()` y el arreglo JSON de ids que lee el kiosco.
 * Por eso el código anterior (188ba45) no la lee ni la pisa: sirve para el rollback.
 * Lleva el número 027 porque el 026 ya lo usa `026_modo_feria` en otras ramas.
 * Repetible en MySQL y SQLite; NULL = ninguno apagado.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql) {
        $exists = $pdo->query("SHOW COLUMNS FROM cc_parties LIKE 'animador_apagados'")->fetch();
    } else {
        $exists = false;
        foreach ($pdo->query('PRAGMA table_info(cc_parties)')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if ($column['name'] === 'animador_apagados') { $exists = true; break; }
        }
    }
    if (!$exists) {
        $pdo->exec('ALTER TABLE cc_parties ADD COLUMN animador_apagados TEXT NULL');
    }
};
