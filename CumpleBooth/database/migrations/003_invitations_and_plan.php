<?php
/** Gate A: public_slug, planes/galería, módulo de invitaciones y manifiesto visual. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $bool = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    // Helpers
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

    $indexExists = static function (PDO $pdo, string $table, string $indexName) use ($mysql): bool {
        try {
            if ($mysql) {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
                $stmt->execute([$table, $indexName]);
                return $stmt->fetch() !== false;
            }
            $stmt = $pdo->prepare('PRAGMA index_list(' . $table . ')');
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                if (($row['name'] ?? '') === $indexName) {
                    return true;
                }
            }
            return false;
        } catch (Throwable $e) {
            return false;
        }
    };

    // Renombrar y agregar columnas en cc_parties
    if ($mysql) {
        $pdo->exec("ALTER TABLE cc_parties CHANGE slug public_slug VARCHAR(80) NOT NULL");
        $pdo->exec("ALTER TABLE cc_parties CHANGE name birthday_person_name VARCHAR(255) NOT NULL");
    } else {
        $pdo->exec("ALTER TABLE cc_parties RENAME COLUMN slug TO public_slug");
        $pdo->exec("ALTER TABLE cc_parties RENAME COLUMN name TO birthday_person_name");
    }
    $after = $mysql ? ' AFTER birthday_person_name' : '';
    if (!$hasColumn($pdo, 'cc_parties', 'admin_label')) {
        $pdo->exec("ALTER TABLE cc_parties ADD COLUMN admin_label VARCHAR(255) NULL$after");
    }
    $after = $mysql ? ' AFTER admin_label' : '';
    $servicePlanType = $mysql ? "ENUM('booth','full') NOT NULL DEFAULT 'booth'" : "VARCHAR(10) NOT NULL DEFAULT 'booth'";
    if (!$hasColumn($pdo, 'cc_parties', 'service_plan')) {
        $pdo->exec("ALTER TABLE cc_parties ADD COLUMN service_plan $servicePlanType$after");
    }
    $after = $mysql ? ' AFTER service_plan' : '';
    if (!$hasColumn($pdo, 'cc_parties', 'gallery_enabled')) {
        $pdo->exec("ALTER TABLE cc_parties ADD COLUMN gallery_enabled $bool NOT NULL DEFAULT 0$after");
    }
    if (!$indexExists($pdo, 'cc_parties', 'uniq_public_slug')) {
        if ($mysql) {
            $pdo->exec("ALTER TABLE cc_parties ADD UNIQUE INDEX uniq_public_slug (public_slug)");
        } else {
            $pdo->exec("CREATE UNIQUE INDEX uniq_public_slug ON cc_parties(public_slug)");
        }
    }

    // Generador de public_slug: <nombre>-<tema>-<sufijo>
    $generatePublicSlug = static function (PDO $pdo, string $birthdayName, string $themePublicName): string {
        $sanitize = static function (string $s): string {
            $s = strtolower(trim($s));
            $s = strtr($s, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
            ]);
            $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
            $s = trim((string) $s, '-');
            return substr($s, 0, 40);
        };
        $namePart = $sanitize($birthdayName);
        $themePart = $sanitize($themePublicName);
        $base = $namePart . '-' . $themePart;
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'fiesta';
        }
        $base = substr($base, 0, 55);
        $attempts = 0;
        do {
            $suffix = bin2hex(random_bytes(12)); // 96 bits (mínimo 96, cabe en VARCHAR(80): 55 + 1 + 24)
            $slug = $base . '-' . $suffix;
            $stmt = $pdo->prepare('SELECT 1 FROM cc_parties WHERE public_slug = ?');
            $stmt->execute([$slug]);
            $exists = $stmt->fetch() !== false;
            $attempts++;
        } while ($exists && $attempts < 10);
        if ($exists) {
            throw new RuntimeException('No se pudo generar un public_slug único para la fiesta demo.');
        }
        return $slug;
    };

    // Actualizar fiesta demo
    $demoPublicSlug = $generatePublicSlug($pdo, 'DEMO', 'Aventura Perruna');
    $update = $pdo->prepare('UPDATE cc_parties SET public_slug=?, admin_label=?, birthday_person_name=?, theme_slug=?, service_plan=?, gallery_enabled=?, updated_at=? WHERE public_slug=?');
    $update->execute([
        $demoPublicSlug,
        'DEMO-BLUEY',
        'DEMO',
        'familia-canina',
        'full',
        1,
        gmdate('Y-m-d H:i:s'),
        'demo',
    ]);

    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $partyId = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $invitationId = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $invitationStatus = $mysql ? "ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft'" : "VARCHAR(20) NOT NULL DEFAULT 'draft'";
    $outputType = $mysql ? "ENUM('generic','personalized') NOT NULL DEFAULT 'generic'" : "VARCHAR(20) NOT NULL DEFAULT 'generic'";
    $outputStatus = $mysql ? "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'" : "VARCHAR(20) NOT NULL DEFAULT 'pending'";
    $createIndex = static function (string $name, string $table, string $columns) use ($pdo, $mysql): void {
        try {
            $pdo->exec('CREATE ' . ($mysql ? '' : '') . 'INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON $table($columns)");
        } catch (PDOException $e) {
            if (!$mysql || (string) $e->getCode() !== '42000' || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    };

    // Tabla de invitaciones
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_invitations (
        id $id,
        public_token_hash VARCHAR(64) NOT NULL UNIQUE,
        party_id $partyId NULL,
        theme_slug VARCHAR(40) NULL,
        admin_label VARCHAR(255) NULL,
        guest_name VARCHAR(255) NULL,
        event_date VARCHAR(10) NULL,
        address TEXT NULL,
        message TEXT NULL,
        language VARCHAR(10) NULL,
        channel VARCHAR(20) NULL,
        status $invitationStatus,
        visual_version INT NOT NULL DEFAULT 1,
        download_count INT NOT NULL DEFAULT 0,
        last_downloaded_at $timestamp NULL,
        expires_at $timestamp NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        created_by VARCHAR(120) NULL,
        approved_at $timestamp NULL,
        approved_by VARCHAR(120) NULL,
        CONSTRAINT fk_invitations_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE SET NULL
    )$tableOptions");
    $createIndex('idx_invitations_party', 'cc_invitations', 'party_id');
    $createIndex('idx_invitations_theme', 'cc_invitations', 'theme_slug');
    $createIndex('idx_invitations_status', 'cc_invitations', 'status');

    // Tabla de outputs de invitación
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_invitation_outputs (
        id $id,
        invitation_id $invitationId NOT NULL,
        output_type $outputType,
        asset_key VARCHAR(120) NOT NULL,
        file_storage_key VARCHAR(255) NOT NULL UNIQUE,
        status $outputStatus,
        visual_source_json TEXT NULL,
        file_mime VARCHAR(120) NULL,
        file_byte_size BIGINT NULL,
        file_sha256 VARCHAR(64) NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        reviewed_at $timestamp NULL,
        reviewed_by VARCHAR(120) NULL,
        CONSTRAINT fk_invitation_outputs_invitation FOREIGN KEY (invitation_id) REFERENCES cc_invitations(id) ON DELETE CASCADE,
        UNIQUE (invitation_id, asset_key)
    )$tableOptions");

    // Tabla de manifiestos visuales versionados
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_visual_manifests (
        id $id,
        party_id $partyId NULL,
        invitation_id $invitationId NULL,
        theme_slug VARCHAR(40) NULL,
        version INT NOT NULL,
        manifest_json TEXT NOT NULL,
        created_at $timestamp NOT NULL,
        created_by VARCHAR(120) NULL,
        CONSTRAINT fk_visual_manifests_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE,
        CONSTRAINT fk_visual_manifests_invitation FOREIGN KEY (invitation_id) REFERENCES cc_invitations(id) ON DELETE CASCADE,
        UNIQUE (party_id, version),
        UNIQUE (invitation_id, version),
        UNIQUE (theme_slug, version)
    )$tableOptions");

    // Asegurar que cc_theme_prompts exista para referencias FK
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_theme_prompts (
        theme_slug VARCHAR(40) NOT NULL,
        asset_key VARCHAR(120) NOT NULL,
        prompt_text TEXT NOT NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        PRIMARY KEY (theme_slug, asset_key)
    )$tableOptions");
};
