<?php
/** Extensión 1:1 de las salas existentes; no crea otra identidad ni código de sala. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $options = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_sala_carreras (
        sala_id BIGINT NOT NULL PRIMARY KEY,
        party_id BIGINT UNSIGNED NOT NULL,
        datos TEXT NOT NULL,
        CONSTRAINT fk_carrera_sala FOREIGN KEY (sala_id) REFERENCES cc_salas(id) ON DELETE CASCADE
    )$options");
    try {
        $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'idx_carrera_fiesta ON cc_sala_carreras(party_id)');
    } catch (PDOException $e) {
        if (!$mysql || strpos($e->getMessage(), '1061') === false) { throw $e; }
    }
};
