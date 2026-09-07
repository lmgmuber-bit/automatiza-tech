<?php
/**
 * Movimientos de plata: lo que se invierte y lo que entra.
 *
 * Los ingresos de las fiestas ya viven en `cc_parties` (price_total, descuento, anticipo) y
 * **no se copian acá**: tener el mismo monto en dos tablas es la forma segura de que un día
 * digan cosas distintas. Esta tabla guarda lo que la aplicación no sabe por sí sola: la
 * impresora, el papel, los imanes, los acrílicos, la publicidad, y cualquier ingreso que no
 * venga de una fiesta cargada.
 *
 * `monto` en pesos enteros, como el resto del cobro: en Chile no hay decimales y un INT no
 * se puede redondear mal.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    if ($mysql) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cc_finanzas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fecha DATE NOT NULL,
                tipo VARCHAR(10) NOT NULL,
                categoria VARCHAR(40) NOT NULL,
                descripcion VARCHAR(160) NOT NULL,
                monto INT NOT NULL,
                unidades INT NULL,
                party_slug VARCHAR(80) NULL,
                nota TEXT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_finanzas_fecha (fecha),
                INDEX idx_finanzas_tipo (tipo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cc_finanzas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha TEXT NOT NULL,
            tipo TEXT NOT NULL,
            categoria TEXT NOT NULL,
            descripcion TEXT NOT NULL,
            monto INTEGER NOT NULL,
            unidades INTEGER NULL,
            party_slug TEXT NULL,
            nota TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_finanzas_fecha ON cc_finanzas (fecha)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_finanzas_tipo ON cc_finanzas (tipo)');
};
