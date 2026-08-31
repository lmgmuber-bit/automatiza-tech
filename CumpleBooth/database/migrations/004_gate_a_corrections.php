<?php
/** Gate A: correcciones de modelo de invitaciones (004).
 *  - guest_name -> birthday_person_name
 *  - Agrega event_time, published_at/by, revoked_at/by.
 *  - Extiende status con published, revoked, archived.
 *  - Outputs: generic/personalized -> personalized_image/personalized_video.
 * Idempotente. No borra datos. MySQL y SQLite.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';

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

    // cc_invitations: renombrar guest_name -> birthday_person_name
    if ($hasColumn($pdo, 'cc_invitations', 'guest_name')) {
        if ($mysql) {
            $pdo->exec("ALTER TABLE cc_invitations CHANGE guest_name birthday_person_name VARCHAR(255) NULL");
        } else {
            $pdo->exec("ALTER TABLE cc_invitations RENAME COLUMN guest_name TO birthday_person_name");
        }
    }

    // Nuevas columnas de invitación
    $newInvitationColumns = [
        'event_time' => 'VARCHAR(8)',
        'published_at' => $timestamp,
        'published_by' => 'VARCHAR(120)',
        'revoked_at' => $timestamp,
        'revoked_by' => 'VARCHAR(120)',
    ];
    foreach ($newInvitationColumns as $col => $type) {
        if (!$hasColumn($pdo, 'cc_invitations', $col)) {
            $pdo->exec("ALTER TABLE cc_invitations ADD COLUMN $col $type NULL");
        }
    }

    // Extender enum de status en MySQL; SQLite usa TEXT/NUMERIC affinity.
    // Orden seguro: 1) ampliar el enum para que quepan valores viejos Y nuevos a la
    // vez (nunca truncar datos existentes), 2) normalizar filas legadas, 3) recién
    // entonces aplicar el enum final restringido. El orden inverso (que tenía este
    // archivo antes) puede fallar o truncar 'rejected' en una instalación con datos.
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_invitations MODIFY status ENUM('draft','pending','approved','rejected','published','revoked','archived') NOT NULL DEFAULT 'draft'");
    }
    // 'rejected' no existe en el nuevo flujo (draft/pending/approved/published/revoked/archived);
    // se normaliza a 'archived' (estado terminal, ya no editable) en MySQL y SQLite por igual.
    $pdo->exec("UPDATE cc_invitations SET status = 'archived' WHERE status = 'rejected'");
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_invitations MODIFY status ENUM('draft','pending','approved','published','revoked','archived') NOT NULL DEFAULT 'draft'");
    }

    // cc_invitation_outputs: mismo orden seguro que arriba — ampliar, normalizar,
    // recién después aplicar el enum final. Antes este archivo aplicaba el enum
    // final ('personalized_image','personalized_video') ANTES de normalizar los
    // valores legados 'generic'/'personalized', lo que puede fallar o truncarlos.
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_invitation_outputs MODIFY output_type ENUM('generic','personalized','personalized_image','personalized_video') NOT NULL DEFAULT 'personalized_image'");
    }
    // Normalizar valores legados (generic/personalized) a imagen por defecto
    $pdo->exec("UPDATE cc_invitation_outputs SET output_type = 'personalized_image' WHERE output_type NOT IN ('personalized_image','personalized_video')");
    // Inferir videos por MIME
    $stmt = $pdo->prepare("UPDATE cc_invitation_outputs SET output_type = 'personalized_video' WHERE output_type = 'personalized_image' AND file_mime LIKE 'video/%'");
    $stmt->execute();
    if ($mysql) {
        // Recién ahora, con todos los valores ya normalizados, se aplica el enum final.
        $pdo->exec("ALTER TABLE cc_invitation_outputs MODIFY output_type ENUM('personalized_image','personalized_video') NOT NULL DEFAULT 'personalized_image'");
    }

    // Plantilla de prompt editable por invitación
    if (!$hasColumn($pdo, 'cc_invitations', 'prompt_template')) {
        $pdo->exec("ALTER TABLE cc_invitations ADD COLUMN prompt_template TEXT NULL");
    }

    // Asegurar índice sobre token hash (puede no existir en instalaciones tempranas)
    if ($mysql) {
        $idx = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $idx->execute(['cc_invitations', 'uniq_token_hash']);
        if ($idx->fetch() === false) {
            try {
                $pdo->exec('ALTER TABLE cc_invitations ADD UNIQUE INDEX uniq_token_hash (public_token_hash)');
            } catch (Throwable $e) {
                // Ya puede ser UNIQUE por creación original; ignorar.
            }
        }
    }
};
