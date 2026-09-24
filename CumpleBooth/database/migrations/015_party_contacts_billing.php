<?php
/**
 * Contactos de la fiesta y datos de cobro.
 *
 * Quien contrata no siempre es la madre o el padre: puede ser una abuela, una tía o un
 * amigo de la familia, y muchas veces hay más de una persona a la que hay que escribirle.
 * Hasta ahora el único correo del cliente vivía dentro de la aceptación de Términos
 * (`cc_plan_acceptances.client_email`), que se emite una vez y no sirve como agenda.
 *
 * Los montos van en pesos enteros (CLP no usa decimales) y como `INT`: guardarlos en
 * texto obliga a limpiar y convertir en cada lectura, y un total mal sumado en un
 * comprobante es un problema con el cliente.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $autoIncrement = $mysql ? 'BIGINT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_party_contacts (
        id $autoIncrement,
        party_id BIGINT NOT NULL,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(254) NOT NULL,
        phone VARCHAR(40) NULL,
        relationship VARCHAR(20) NOT NULL DEFAULT 'otro',
        is_primary TINYINT NOT NULL DEFAULT 0,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL
    )$tableOptions");

    try {
        $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'idx_party_contacts_party ON cc_party_contacts (party_id)');
    } catch (PDOException $e) {
        // 1061 = el índice ya existe (MySQL no soporta IF NOT EXISTS en CREATE INDEX).
        if (!$mysql || strpos($e->getMessage(), '1061') === false) { throw $e; }
    }

    // Cobro de la fiesta. Se agregan de a una para poder correr la migración sobre una
    // base que ya tenga alguna columna (por un intento anterior a medias).
    $columnas = [
        'price_total' => 'INT NULL',
        'discount_amount' => 'INT NULL',
        'discount_label' => 'VARCHAR(80) NULL',
        'deposit_amount' => 'INT NULL',
        'payment_note' => 'VARCHAR(160) NULL',
    ];
    $existentes = [];
    foreach ($pdo->query('SELECT * FROM cc_parties LIMIT 0')->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $existentes = array_keys($fila);
    }
    if (!$existentes) {
        // Sin filas, la consulta anterior no revela columnas: se pregunta al esquema.
        $stmt = $pdo->query($mysql ? 'SHOW COLUMNS FROM cc_parties' : "PRAGMA table_info(cc_parties)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $existentes[] = (string) ($fila['Field'] ?? $fila['name'] ?? '');
        }
    }
    foreach ($columnas as $nombre => $tipo) {
        if (in_array($nombre, $existentes, true)) { continue; }
        $pdo->exec("ALTER TABLE cc_parties ADD COLUMN $nombre $tipo");
    }
};
