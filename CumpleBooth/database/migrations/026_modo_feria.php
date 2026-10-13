<?php
/**
 * AT-CUMPLECLICK-020: modo feria. Una feria cuelga de una fiesta normal (cc_parties) por party_id, así no se toca el
 * tipo de evento (binario cumpleaños/baby shower, repetido en lib.php). cc_feria_fotos numera las fotos F-### de cada
 * feria y guarda modo, temática, nombre e impresiones. Compatible MySQL/SQLite, idempotente.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $options = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_ferias (
        id $id,
        party_id $fk NOT NULL UNIQUE,
        nombre VARCHAR(160) NOT NULL,
        organizador VARCHAR(160) NOT NULL DEFAULT '',
        organizador_ig VARCHAR(80) NOT NULL DEFAULT '',
        lugar VARCHAR(255) NOT NULL DEFAULT '',
        fecha DATE NOT NULL,
        hora_inicio TIME NULL,
        mesa VARCHAR(40) NOT NULL DEFAULT '',
        notas TEXT NOT NULL,
        mundos_infantil TEXT NOT NULL,
        mundos_adulto TEXT NOT NULL,
        retencion_dias INTEGER NOT NULL DEFAULT 7,
        max_fotos INTEGER NOT NULL DEFAULT 1000,
        activa INTEGER NOT NULL DEFAULT 1,
        duplicada_de $fk NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        CONSTRAINT fk_ferias_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$options");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_feria_fotos (
        id $id,
        feria_id $fk NOT NULL,
        numero INTEGER NOT NULL,
        reserva VARCHAR(64) NOT NULL UNIQUE,
        photo_id $fk NULL,
        modo VARCHAR(16) NOT NULL DEFAULT 'infantil',
        theme_slug VARCHAR(64) NOT NULL DEFAULT '',
        nombre VARCHAR(40) NOT NULL DEFAULT '',
        impresiones INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        CONSTRAINT fk_feria_fotos_feria FOREIGN KEY (feria_id) REFERENCES cc_ferias(id) ON DELETE CASCADE,
        CONSTRAINT fk_feria_fotos_photo FOREIGN KEY (photo_id) REFERENCES cc_photos(id) ON DELETE SET NULL
    )$options");
    $indices = [
        'uq_feria_fotos_numero' => 'CREATE UNIQUE INDEX %s uq_feria_fotos_numero ON cc_feria_fotos(feria_id, numero)',
        'idx_feria_fotos_photo' => 'CREATE INDEX %s idx_feria_fotos_photo ON cc_feria_fotos(photo_id)',
    ];
    foreach ($indices as $sql) {
        try {
            $pdo->exec(sprintf($sql, $mysql ? '' : 'IF NOT EXISTS'));
        } catch (PDOException $e) {
            if (!$mysql || (int) ($e->errorInfo[1] ?? 0) !== 1061) { throw $e; }
        }
    }
};
