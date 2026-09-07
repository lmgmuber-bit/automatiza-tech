<?php
/**
 * Aceptación de Términos y Condiciones + firma electrónica simple por plan/fiesta.
 *
 * Una fila por enlace de aceptación enviado al cliente. La evidencia (texto
 * aceptado, hash, firma, IP, user-agent, fechas) vive acá y en archivos privados
 * fuera del webroot; la fila sobrevive al borrado de la fiesta (FK SET NULL +
 * snapshot de slug/etiqueta) porque es respaldo legal, no dato operativo.
 *
 * Las fiestas que ya estaban activas antes de esta migración quedan eximidas
 * automáticamente (status=waived) para no bloquear su edición; el motivo queda
 * registrado y es auditable desde el backoffice.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $partyId = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $bool = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $status = $mysql
        ? "ENUM('pending','accepted','revoked','expired','waived') NOT NULL DEFAULT 'pending'"
        : "VARCHAR(20) NOT NULL DEFAULT 'pending'";
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_plan_acceptances (
        id $id,
        party_id $partyId NULL,
        party_public_slug VARCHAR(80) NOT NULL,
        party_admin_label VARCHAR(255) NULL,
        public_token_hash VARCHAR(64) NOT NULL UNIQUE,
        receipt_token_hash VARCHAR(64) NULL UNIQUE,
        status $status,
        plan_code VARCHAR(20) NOT NULL,
        plan_summary_json TEXT NOT NULL,
        client_name VARCHAR(160) NOT NULL,
        client_email VARCHAR(254) NOT NULL,
        client_phone VARCHAR(30) NULL,
        legal_version VARCHAR(20) NOT NULL,
        legal_text_sha256 VARCHAR(64) NOT NULL,
        accepted_terms $bool NOT NULL DEFAULT 0,
        accepted_privacy $bool NOT NULL DEFAULT 0,
        accepted_minors $bool NOT NULL DEFAULT 0,
        accepted_marketing $bool NOT NULL DEFAULT 0,
        signer_name VARCHAR(160) NULL,
        signer_rut VARCHAR(20) NULL,
        signer_email VARCHAR(254) NULL,
        signer_relationship VARCHAR(40) NULL,
        signature_storage_key VARCHAR(255) NULL,
        signature_sha256 VARCHAR(64) NULL,
        evidence_storage_key VARCHAR(255) NULL,
        evidence_sha256 VARCHAR(64) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(400) NULL,
        client_meta_json TEXT NULL,
        view_count INT NOT NULL DEFAULT 0,
        first_viewed_at $timestamp NULL,
        accepted_at $timestamp NULL,
        expires_at $timestamp NULL,
        revoked_at $timestamp NULL,
        waived_reason VARCHAR(255) NULL,
        client_mail_sent_at $timestamp NULL,
        internal_mail_sent_at $timestamp NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        created_by VARCHAR(120) NULL,
        CONSTRAINT fk_plan_acceptances_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE SET NULL
    )$tableOptions");

    foreach ([
        'idx_plan_acceptances_party' => '(party_id, status)',
        'idx_plan_acceptances_slug' => '(party_public_slug)',
        'idx_plan_acceptances_status' => '(status, created_at)',
    ] as $name => $columns) {
        try {
            $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON cc_plan_acceptances $columns");
        } catch (PDOException $e) {
            // MySQL 8 no acepta IF NOT EXISTS para CREATE INDEX; 1061 significa que ya existe.
            if (!$mysql || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    }

    // Fiestas activas pre-existentes: eximidas con motivo para que la regla
    // "no activar sin aceptación" no rompa lo que ya estaba operando.
    $now = gmdate('Y-m-d H:i:s');
    $rows = $pdo->query('SELECT id, public_slug, admin_label FROM cc_parties WHERE active = 1')->fetchAll();
    $exists = $pdo->prepare('SELECT 1 FROM cc_plan_acceptances WHERE party_id = ? AND status IN (\'accepted\', \'waived\')');
    $insert = $pdo->prepare('INSERT INTO cc_plan_acceptances
        (party_id, party_public_slug, party_admin_label, public_token_hash, status, plan_code, plan_summary_json, client_name, client_email,
         legal_version, legal_text_sha256, waived_reason, created_at, updated_at, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($rows as $row) {
        $exists->execute([(int) $row['id']]);
        if ($exists->fetch() !== false) {
            continue;
        }
        $insert->execute([
            (int) $row['id'],
            (string) $row['public_slug'],
            (string) ($row['admin_label'] ?? ''),
            hash('sha256', 'waived:' . bin2hex(random_bytes(16))),
            'waived',
            'legacy',
            json_encode(['motivo' => 'Fiesta activa antes de la migración 013'], JSON_UNESCAPED_UNICODE),
            '',
            '',
            'n/a',
            str_repeat('0', 64),
            'Fiesta activa antes de exigir aceptación de Términos (migración 013).',
            $now,
            $now,
            'migration:013',
        ]);
    }
};
