<?php
/**
 * 010: infraestructura extensible para baby shower.
 *
 * Es aditiva e idempotente. Las invitaciones existentes conservan la modalidad
 * child_birthday. La lista de regalos y sus tokens pertenecen a una invitación;
 * las predicciones pertenecen al evento (party_id), porque el kiosco opera con
 * el slug del evento y una fiesta puede tener más de una invitación.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $hasColumn = static function (string $table, string $column) use ($pdo, $mysql): bool {
        if ($mysql) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.columns
                 WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'
            );
            $stmt->execute([$table, $column]);
            return $stmt->fetch() !== false;
        }
        foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    };

    $createIndex = static function (string $name, string $table, string $columns) use ($pdo, $mysql): void {
        try {
            $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON $table($columns)");
        } catch (PDOException $e) {
            if (!$mysql || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    };

    if (!$hasColumn('cc_invitations', 'event_type')) {
        $pdo->exec("ALTER TABLE cc_invitations ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT 'child_birthday'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_gift_items (
        id $id,
        invitation_id $fk NOT NULL,
        position INTEGER NOT NULL DEFAULT 0,
        title VARCHAR(120) NOT NULL,
        notes VARCHAR(400) NULL,
        added_by VARCHAR(20) NOT NULL DEFAULT 'parents',
        status VARCHAR(20) NOT NULL DEFAULT 'available',
        claimed_name VARCHAR(80) NULL,
        claimed_token CHAR(32) NULL,
        claimed_at $timestamp NULL,
        moderation_status VARCHAR(20) NOT NULL DEFAULT 'approved',
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_gift_items_invitation FOREIGN KEY (invitation_id) REFERENCES cc_invitations(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_gift_items_invitation_position', 'cc_gift_items', 'invitation_id, position');
    $createIndex('idx_gift_items_status', 'cc_gift_items', 'invitation_id, status, moderation_status');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_invitation_tokens (
        id $id,
        invitation_id $fk NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        purpose VARCHAR(30) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        expires_at $timestamp NULL,
        created_at $timestamp NOT NULL,
        created_by VARCHAR(120) NULL,
        revoked_at $timestamp NULL,
        CONSTRAINT fk_invitation_tokens_invitation FOREIGN KEY (invitation_id) REFERENCES cc_invitations(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_invitation_tokens_owner', 'cc_invitation_tokens', 'invitation_id, purpose, status');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_predictions (
        id $id,
        party_id $fk NOT NULL,
        guest_name VARCHAR(80) NOT NULL,
        parecido VARCHAR(12) NOT NULL,
        peso VARCHAR(16) NOT NULL,
        fecha VARCHAR(12) NOT NULL,
        puntaje_juego INTEGER NULL,
        submission_token_hash CHAR(64) NOT NULL,
        created_at $timestamp NOT NULL,
        UNIQUE (party_id, submission_token_hash),
        CONSTRAINT fk_predictions_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_predictions_party_created', 'cc_predictions', 'party_id, created_at');
};
