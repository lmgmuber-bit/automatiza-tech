<?php
/**
 * Perfil del protagonista por evento.
 *
 * El vocabulario y el esquema son genéricos: soportan una o más personas
 * destacadas y cualquier tipo de evento sin acoplarse a cumpleaños infantiles.
 * La migración es aditiva e idempotente; no crea perfiles ni modifica filas
 * existentes salvo por el nuevo default neutro de cc_parties.event_type.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $bool = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $decimal = $mysql ? 'DECIMAL(10,4)' : 'REAL';
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
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        foreach ($stmt->fetchAll() as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    };

    $createIndex = static function (string $name, string $table, string $columns, bool $unique = false) use ($pdo, $mysql): void {
        try {
            $pdo->exec('CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX '
                . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON $table($columns)");
        } catch (PDOException $e) {
            if (!$mysql || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    };

    if (!$hasColumn('cc_parties', 'event_type')) {
        $after = $mysql ? ' AFTER birthday_person_name' : '';
        $pdo->exec("ALTER TABLE cc_parties ADD COLUMN event_type VARCHAR(40) NOT NULL DEFAULT 'child_birthday'$after");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_profiles (
        id $id,
        party_id $fk NOT NULL UNIQUE,
        is_enabled $bool NOT NULL DEFAULT 0,
        public_title VARCHAR(160) NULL,
        cta_label VARCHAR(120) NULL,
        intro_style VARCHAR(40) NOT NULL DEFAULT 'magical',
        intro_phrase VARCHAR(160) NULL,
        design_variant VARCHAR(40) NOT NULL DEFAULT 'theme',
        locale VARCHAR(12) NOT NULL DEFAULT 'es-CL',
        privacy_version VARCHAR(30) NULL,
        privacy_confirmed_at $timestamp NULL,
        privacy_confirmed_by VARCHAR(120) NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_event_profiles_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_event_profiles_enabled', 'cc_event_profiles', 'is_enabled');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_profile_sections (
        id $id,
        profile_id $fk NOT NULL,
        section_key VARCHAR(50) NOT NULL,
        public_label VARCHAR(100) NOT NULL,
        is_public $bool NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_event_profile_sections_profile FOREIGN KEY (profile_id) REFERENCES cc_event_profiles(id) ON DELETE CASCADE,
        UNIQUE (profile_id, section_key)
    )$tableOptions");
    $createIndex('idx_event_profile_sections_order', 'cc_event_profile_sections', 'profile_id, sort_order');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_featured_people (
        id $id,
        profile_id $fk NOT NULL,
        public_id CHAR(32) NOT NULL UNIQUE,
        display_name VARCHAR(120) NOT NULL,
        nickname VARCHAR(120) NULL,
        intro_text VARCHAR(600) NULL,
        is_public $bool NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        photo_public_consent $bool NOT NULL DEFAULT 0,
        photo_ai_consent $bool NOT NULL DEFAULT 0,
        consent_recorded_at $timestamp NULL,
        consent_recorded_by VARCHAR(120) NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_featured_people_profile FOREIGN KEY (profile_id) REFERENCES cc_event_profiles(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_featured_people_order', 'cc_featured_people', 'profile_id, sort_order');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_profile_fields (
        id $id,
        profile_id $fk NOT NULL,
        featured_person_id $fk NULL,
        section_id $fk NULL,
        section_key VARCHAR(50) NOT NULL,
        field_key VARCHAR(80) NOT NULL,
        public_label VARCHAR(100) NOT NULL,
        value_text TEXT NOT NULL,
        value_type VARCHAR(20) NOT NULL DEFAULT 'text',
        is_public $bool NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_event_profile_fields_profile FOREIGN KEY (profile_id) REFERENCES cc_event_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_profile_fields_person FOREIGN KEY (featured_person_id) REFERENCES cc_featured_people(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_profile_fields_section FOREIGN KEY (section_id) REFERENCES cc_event_profile_sections(id) ON DELETE SET NULL
    )$tableOptions");
    $createIndex('idx_event_profile_fields_person_order', 'cc_event_profile_fields', 'featured_person_id, section_key, sort_order');
    $createIndex('idx_event_profile_fields_public', 'cc_event_profile_fields', 'profile_id, is_public');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_profile_media (
        id $id,
        profile_id $fk NOT NULL,
        featured_person_id $fk NULL,
        media_kind VARCHAR(30) NOT NULL,
        access_token CHAR(32) NOT NULL UNIQUE,
        storage_key VARCHAR(255) NOT NULL UNIQUE,
        mime VARCHAR(80) NOT NULL,
        byte_size BIGINT NOT NULL DEFAULT 0,
        width INTEGER NOT NULL DEFAULT 0,
        height INTEGER NOT NULL DEFAULT 0,
        duration_seconds $decimal NULL,
        has_audio $bool NOT NULL DEFAULT 0,
        sha256 CHAR(64) NOT NULL,
        alt_text VARCHAR(180) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        is_public $bool NOT NULL DEFAULT 0,
        authorized_for_ai $bool NOT NULL DEFAULT 0,
        authorization_recorded_at $timestamp NULL,
        authorization_recorded_by VARCHAR(120) NULL,
        metadata_json TEXT NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        deleted_at $timestamp NULL,
        CONSTRAINT fk_event_profile_media_profile FOREIGN KEY (profile_id) REFERENCES cc_event_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_profile_media_person FOREIGN KEY (featured_person_id) REFERENCES cc_featured_people(id) ON DELETE SET NULL
    )$tableOptions");
    $createIndex('idx_event_profile_media_profile_kind', 'cc_event_profile_media', 'profile_id, media_kind, status');
    $createIndex('idx_event_profile_media_person', 'cc_event_profile_media', 'featured_person_id, media_kind');
    $createIndex('idx_event_profile_media_sha', 'cc_event_profile_media', 'profile_id, sha256');

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_profile_generations (
        id $id,
        profile_id $fk NOT NULL,
        output_media_id $fk NULL,
        provider VARCHAR(40) NOT NULL DEFAULT 'higgsfield',
        model_key VARCHAR(100) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        prompt_text TEXT NOT NULL,
        negative_prompt TEXT NULL,
        request_json TEXT NULL,
        quote_amount $decimal NULL,
        quote_currency VARCHAR(12) NULL,
        quote_credits INTEGER NULL,
        quote_expires_at $timestamp NULL,
        idempotency_key CHAR(64) NOT NULL UNIQUE,
        provider_job_id VARCHAR(160) NULL UNIQUE,
        approved_at $timestamp NULL,
        approved_by VARCHAR(120) NULL,
        started_at $timestamp NULL,
        completed_at $timestamp NULL,
        failed_at $timestamp NULL,
        error_code VARCHAR(80) NULL,
        error_message VARCHAR(500) NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_event_profile_generations_profile FOREIGN KEY (profile_id) REFERENCES cc_event_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_profile_generations_media FOREIGN KEY (output_media_id) REFERENCES cc_event_profile_media(id) ON DELETE SET NULL
    )$tableOptions");
    $createIndex('idx_event_profile_generations_profile', 'cc_event_profile_generations', 'profile_id, created_at');
    $createIndex('idx_event_profile_generations_status', 'cc_event_profile_generations', 'status, updated_at');
};
