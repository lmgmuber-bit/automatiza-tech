<?php
/**
 * Descuento en porcentaje por fiesta.
 *
 * Hasta ahora el descuento solo se podía escribir en pesos, y en la práctica se piensa al
 * revés: "a esta fiesta le hago 20%", o "esta va sin costo" (100%). Calcularlo a mano cada
 * vez es donde aparecen los errores de monto en un comprobante que ya está impreso.
 *
 * El porcentaje manda cuando está puesto: `discount_amount` se guarda igual, pero derivado
 * del precio, para que el comprobante siga leyendo un monto y no tenga que hacer cuentas.
 * DECIMAL y no float: 12,5% tiene que valer 12,5 exacto, no 12,499999.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $existentes = [];
    $stmt = $pdo->query($mysql ? 'SHOW COLUMNS FROM cc_parties' : 'PRAGMA table_info(cc_parties)');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $existentes[] = (string) ($fila['Field'] ?? $fila['name'] ?? '');
    }
    if (in_array('discount_percent', $existentes, true)) {
        return;
    }
    $tipo = $mysql ? 'DECIMAL(5,2) NULL' : 'NUMERIC NULL';
    $pdo->exec('ALTER TABLE cc_parties ADD COLUMN discount_percent ' . $tipo);
};
