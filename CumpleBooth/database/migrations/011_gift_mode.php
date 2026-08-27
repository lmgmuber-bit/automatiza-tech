<?php
/**
 * 011: agrega `gift_mode` a cc_invitations.
 *
 * Dos formas de resolver los regalos de un baby shower, y las dos son
 * legítimas (pedido de Luis 2026-08-27):
 *
 *   'list' — los papás cargan lo que necesitan y cada invitado elige de ahí.
 *            Es el modo por defecto y el que ya existía.
 *   'open' — no hay lista. Cada invitado anota lo que va a llevar, y todos
 *            ven lo anotado para que nadie repita. Sirve para las familias a
 *            las que pedir una lista se les hace incómodo, que son muchas.
 *
 * La diferencia es solo de presentación y de quién escribe: los dos modos
 * usan la misma tabla `cc_gift_items` y el mismo mecanismo de reserva. Por eso
 * es una columna y no un esquema aparte.
 *
 * NULL o valor desconocido = 'list', para no tocar invitaciones ya creadas.
 * Los valores válidos se aplican en código, no a nivel de columna, para que
 * MySQL y SQLite compartan el mismo tipo simple. Idempotente, no borra datos.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $hasColumn = static function (PDO $pdo, string $table, string $column) use ($mysql): bool {
        try {
            if ($mysql) {
                $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
                $stmt->execute([$table, $column]);
                return $stmt->fetch() !== false;
            }
            $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                if (($row['name'] ?? '') === $column) {
                    return true;
                }
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    };

    if (!$hasColumn($pdo, 'cc_invitations', 'gift_mode')) {
        $pdo->exec("ALTER TABLE cc_invitations ADD COLUMN gift_mode VARCHAR(10) NOT NULL DEFAULT 'list'");
    }
};
