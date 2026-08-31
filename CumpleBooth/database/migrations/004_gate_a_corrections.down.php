<?php
/** Rollback seguro de 004_gate_a_corrections (best-effort sin pérdida de datos). */
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

    // Revertir tipos de output a valores legados. Mismo orden seguro que la migración
    // de subida: ampliar el enum primero para que quepan valores viejos Y nuevos a la
    // vez, escribir el valor legado, y recién después angostar al enum final viejo.
    // Escribir 'personalized' mientras el enum activo todavía era
    // ('personalized_image','personalized_video') fallaba o truncaba el valor.
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_invitation_outputs MODIFY output_type ENUM('generic','personalized','personalized_image','personalized_video') NOT NULL DEFAULT 'generic'");
    }
    $pdo->exec("UPDATE cc_invitation_outputs SET output_type = 'personalized' WHERE output_type IN ('personalized_image','personalized_video')");
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_invitation_outputs MODIFY output_type ENUM('generic','personalized') NOT NULL DEFAULT 'generic'");
    }

    // Revertir estados no publicados a aprobados/draft seguros
    $pdo->exec("UPDATE cc_invitations SET status = 'approved' WHERE status = 'published'");
    $pdo->exec("UPDATE cc_invitations SET status = 'draft' WHERE status IN ('revoked','archived')");
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_invitations MODIFY status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft'");
    }

    // Quitar columnas agregadas y renombrar de vuelta
    if ($mysql) {
        foreach (['event_time','published_at','published_by','revoked_at','revoked_by','prompt_template'] as $col) {
            if ($hasColumn($pdo, 'cc_invitations', $col)) {
                $pdo->exec("ALTER TABLE cc_invitations DROP COLUMN $col");
            }
        }
        if ($hasColumn($pdo, 'cc_invitations', 'birthday_person_name')) {
            $pdo->exec("ALTER TABLE cc_invitations CHANGE birthday_person_name guest_name VARCHAR(255) NULL");
        }
    } else {
        // SQLite: renombrar de vuelta; dejar columnas extra (SQLite no siempre soporta DROP COLUMN).
        if ($hasColumn($pdo, 'cc_invitations', 'birthday_person_name')) {
            $pdo->exec("ALTER TABLE cc_invitations RENAME COLUMN birthday_person_name TO guest_name");
        }
    }
};
