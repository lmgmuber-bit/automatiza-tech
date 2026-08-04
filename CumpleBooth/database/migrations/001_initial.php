<?php
/** Esquema inicial portable para MySQL/MariaDB y SQLite (tests/local). */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $partyId = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $bool = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $text = $mysql ? 'VARCHAR(255)' : 'TEXT';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $createIndex = static function (string $name, string $table, string $columns) use ($pdo, $mysql): void {
        try {
            $pdo->exec('CREATE ' . ($mysql ? '' : '') . 'INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON $table($columns)");
        } catch (PDOException $e) {
            // MySQL 8 no acepta IF NOT EXISTS para CREATE INDEX; 1061 significa que ya existe.
            if (!$mysql || (string) $e->getCode() !== '42000' || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    };

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_parties (
        id $id,
        slug VARCHAR(40) NOT NULL UNIQUE,
        name $text NOT NULL,
        theme_slug VARCHAR(40) NOT NULL,
        event_date VARCHAR(10) NULL,
        active $bool NOT NULL DEFAULT 0,
        frame_box_json TEXT NULL,
        gallery_pin_hash $text NULL,
        gallery_pin_hmac VARCHAR(64) NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        anonymized_at $timestamp NULL
    )$tableOptions");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_guests (
        id $id,
        party_id $partyId NOT NULL,
        name $text NOT NULL,
        gender VARCHAR(1) NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at $timestamp NOT NULL,
        CONSTRAINT fk_cc_guests_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_cc_guests_party', 'cc_guests', 'party_id, sort_order');
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_photos (
        id $id,
        party_id $partyId NOT NULL,
        access_token VARCHAR(64) NOT NULL UNIQUE,
        storage_key $text NOT NULL UNIQUE,
        original_name $text NOT NULL,
        byte_size BIGINT NOT NULL,
        width INTEGER NOT NULL,
        height INTEGER NOT NULL,
        sha256 VARCHAR(64) NOT NULL,
        created_at $timestamp NOT NULL,
        deleted_at $timestamp NULL,
        CONSTRAINT fk_cc_photos_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_cc_photos_party_created', 'cc_photos', 'party_id, created_at');
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_rate_limits (
        bucket_key VARCHAR(128) NOT NULL PRIMARY KEY,
        window_started_at BIGINT NOT NULL,
        hits INTEGER NOT NULL DEFAULT 0,
        blocked_until BIGINT NOT NULL DEFAULT 0,
        updated_at BIGINT NOT NULL
    )$tableOptions");
};
