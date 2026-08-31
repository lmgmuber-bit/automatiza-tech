<?php
/**
 * Álbum Recuerdo: álbum por evento, tokens de aporte revocables y material
 * multimedia curado.
 *
 * Nomenclatura genérica a propósito (`event_album`, `event_media`,
 * `contributor_*`): la misma estructura debe servir después para bodas, baby
 * shower, bautizos, corporativos o mascotas sin renombrar tablas ni columnas.
 *
 * Aditiva: no altera ninguna tabla existente, así que nada de lo que ya
 * funciona puede romperse al aplicarla.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $bool = $mysql ? 'TINYINT(1)' : 'INTEGER';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $decimal = $mysql ? 'DECIMAL(7,2)' : 'REAL';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    // SQLite no acepta ENUM; en ambos motores el valor se valida además en PHP.
    $albumStatus = $mysql
        ? "ENUM('draft','collecting','closed','published') NOT NULL DEFAULT 'draft'"
        : "TEXT NOT NULL DEFAULT 'draft'";
    $tokenPurpose = $mysql
        ? "ENUM('intake','view') NOT NULL DEFAULT 'intake'"
        : "TEXT NOT NULL DEFAULT 'intake'";
    $tokenStatus = $mysql
        ? "ENUM('active','revoked') NOT NULL DEFAULT 'active'"
        : "TEXT NOT NULL DEFAULT 'active'";
    $mediaSource = $mysql
        ? "ENUM('booth','guest','organizer') NOT NULL"
        : 'TEXT NOT NULL';
    $mediaKind = $mysql
        ? "ENUM('image','video') NOT NULL"
        : 'TEXT NOT NULL';
    $moderation = $mysql
        ? "ENUM('pending','approved','hidden','removed') NOT NULL DEFAULT 'pending'"
        : "TEXT NOT NULL DEFAULT 'pending'";

    $createIndex = static function (string $name, string $table, string $columns) use ($pdo, $mysql): void {
        try {
            $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON $table($columns)");
        } catch (PDOException $e) {
            // 1061 = índice duplicado en MySQL 8, que no acepta IF NOT EXISTS aquí.
            if (!$mysql || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    };

    // Un álbum por evento. `cover_media_id` queda sin FK a propósito: apunta a
    // cc_event_media, que se crea después, y una referencia circular obligaría
    // a un ALTER extra sin ganar integridad real (la portada se valida en PHP
    // contra el propio álbum antes de guardarse).
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_albums (
        id $id,
        party_id $fk NOT NULL UNIQUE,
        status $albumStatus,
        template_key VARCHAR(40) NOT NULL DEFAULT 'kids-theme',
        title VARCHAR(160) NULL,
        subtitle VARCHAR(240) NULL,
        cover_media_id $fk NULL,
        intake_enabled $bool NOT NULL DEFAULT 0,
        intake_videos $bool NOT NULL DEFAULT 0,
        intake_closes_at $timestamp NULL,
        intake_message VARCHAR(400) NULL,
        require_pin $bool NOT NULL DEFAULT 1,
        retention_days INTEGER NOT NULL DEFAULT 90,
        published_at $timestamp NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        CONSTRAINT fk_event_albums_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_event_albums_status', 'cc_event_albums', 'status');

    // Tabla propia en vez de una columna en el álbum: regenerar el enlace debe
    // revocar el anterior sin perder el histórico de qué carteles se imprimieron,
    // y el mismo mecanismo sirve para el enlace de lectura de la revista.
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_album_tokens (
        id $id,
        album_id $fk NOT NULL,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        purpose $tokenPurpose,
        status $tokenStatus,
        expires_at $timestamp NULL,
        created_at $timestamp NOT NULL,
        created_by VARCHAR(120) NULL,
        revoked_at $timestamp NULL,
        CONSTRAINT fk_album_tokens_album FOREIGN KEY (album_id) REFERENCES cc_event_albums(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_album_tokens_album', 'cc_event_album_tokens', 'album_id, purpose, status');

    // Las fotos de cabina NO se copian: `photo_id` referencia cc_photos y el
    // álbum solo aporta orden, aprobación y portada. `storage_key` se usa
    // exclusivamente para lo que sube un invitado o el organizador.
    //
    // `access_token` es NULL en las filas de cabina a propósito: esas se sirven
    // por ver.php con el token que ya tiene cc_photos, y emitir un segundo
    // token para el mismo archivo solo agregaría otra puerta que vigilar.
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_event_media (
        id $id,
        album_id $fk NOT NULL,
        party_id $fk NOT NULL,
        source $mediaSource,
        media_kind $mediaKind,
        photo_id $fk NULL,
        access_token VARCHAR(64) NULL UNIQUE,
        storage_key VARCHAR(255) NULL UNIQUE,
        thumb_storage_key VARCHAR(255) NULL,
        poster_storage_key VARCHAR(255) NULL,
        original_name VARCHAR(255) NULL,
        mime VARCHAR(80) NULL,
        byte_size BIGINT NOT NULL DEFAULT 0,
        width INTEGER NOT NULL DEFAULT 0,
        height INTEGER NOT NULL DEFAULT 0,
        duration_seconds $decimal NULL,
        sha256 VARCHAR(64) NULL,
        contributor_name VARCHAR(80) NULL,
        contributor_message VARCHAR(280) NULL,
        moderation_status $moderation,
        sort_order INTEGER NOT NULL DEFAULT 0,
        consent_version VARCHAR(20) NULL,
        uploader_hmac VARCHAR(64) NULL,
        created_at $timestamp NOT NULL,
        reviewed_at $timestamp NULL,
        reviewed_by VARCHAR(120) NULL,
        removed_at $timestamp NULL,
        CONSTRAINT fk_event_media_album FOREIGN KEY (album_id) REFERENCES cc_event_albums(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_media_party FOREIGN KEY (party_id) REFERENCES cc_parties(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_media_photo FOREIGN KEY (photo_id) REFERENCES cc_photos(id) ON DELETE CASCADE
    )$tableOptions");
    $createIndex('idx_event_media_album_order', 'cc_event_media', 'album_id, sort_order');
    $createIndex('idx_event_media_album_state', 'cc_event_media', 'album_id, moderation_status, source');
    $createIndex('idx_event_media_party', 'cc_event_media', 'party_id');
    $createIndex('idx_event_media_sha', 'cc_event_media', 'album_id, sha256');
};
