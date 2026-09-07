<?php
/**
 * 009: agrega `birthday_person_gender` a cc_invitations.
 *
 * Permite elegir la narración de cierre de Alice correcta ("cumpleañero" vs
 * "cumpleañera") por invitación en vez de un único audio genérico compartido
 * (pedido de Luis 2026-08-12). NULL = sin especificar, el código cae al
 * audio neutro existente (sin romper invitaciones ya creadas). Valores
 * válidos aplicados en código, no a nivel de columna, para que MySQL y
 * SQLite compartan el mismo tipo simple. Idempotente, no borra datos.
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

    if (!$hasColumn($pdo, 'cc_invitations', 'birthday_person_gender')) {
        $pdo->exec("ALTER TABLE cc_invitations ADD COLUMN birthday_person_gender VARCHAR(1) NULL");
    }
};
