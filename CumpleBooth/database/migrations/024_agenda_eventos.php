<?php
/** AT-CUMPLECLICK-018: logística separada de las fiestas; compatible MySQL/SQLite. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $options = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_agenda_eventos (
        id $id, party_id $fk NULL UNIQUE,
        tipo VARCHAR(16) NOT NULL DEFAULT 'fiesta',
        titulo VARCHAR(160) NOT NULL, fecha DATE NOT NULL,
        hora_inicio TIME NULL, hora_fin TIME NULL, hora_montaje TIME NULL,
        lugar VARCHAR(255) NOT NULL DEFAULT '',
        contacto_nombre VARCHAR(160) NOT NULL DEFAULT '', contacto_telefono VARCHAR(40) NOT NULL DEFAULT '',
        estado VARCHAR(16) NOT NULL DEFAULT 'consulta', notas TEXT NOT NULL,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        CONSTRAINT fk_agenda_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$options");
    try {
        $pdo->exec('CREATE INDEX '.($mysql ? '' : 'IF NOT EXISTS ').'idx_agenda_fecha ON cc_agenda_eventos(fecha)');
    } catch (PDOException $e) {
        if (!$mysql || (int)($e->errorInfo[1] ?? 0) !== 1061) { throw $e; }
    }
};
