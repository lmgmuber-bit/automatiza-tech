<?php
/** Reversa explícita de 010. No se ejecuta automáticamente. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

    $pdo->exec('DROP TABLE IF EXISTS cc_predictions');
    $pdo->exec('DROP TABLE IF EXISTS cc_invitation_tokens');
    $pdo->exec('DROP TABLE IF EXISTS cc_gift_items');

    $hasColumn = false;
    if ($mysql) {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'
        );
        $stmt->execute(['cc_invitations', 'event_type']);
        $hasColumn = $stmt->fetch() !== false;
    } else {
        foreach ($pdo->query('PRAGMA table_info(cc_invitations)')->fetchAll() as $row) {
            if (($row['name'] ?? '') === 'event_type') {
                $hasColumn = true;
                break;
            }
        }
    }
    if ($hasColumn) {
        try {
            $pdo->exec('ALTER TABLE cc_invitations DROP COLUMN event_type');
        } catch (Throwable $e) {
            // SQLite anterior a 3.35 no permite DROP COLUMN. La columna queda
            // con el default neutro y no altera el comportamiento anterior.
        }
    }
};
