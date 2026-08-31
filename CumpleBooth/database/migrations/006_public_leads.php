<?php
/** Solicitudes comerciales recibidas desde la landing pública. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $date = $mysql ? 'DATE' : 'TEXT';
    $autoIncrement = $mysql ? 'BIGINT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_leads (
        id $autoIncrement,
        public_ref VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        organization VARCHAR(160) NULL,
        email VARCHAR(254) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        event_type VARCHAR(40) NOT NULL,
        event_date $date NULL,
        commune VARCHAR(120) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        source VARCHAR(30) NOT NULL DEFAULT 'website',
        privacy_version VARCHAR(20) NOT NULL,
        consented_at $timestamp NOT NULL,
        ip_hmac CHAR(64) NOT NULL,
        user_agent_hmac CHAR(64) NOT NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL
    )$tableOptions");

    foreach ([
        'idx_leads_status_created' => '(status, created_at)',
        'idx_leads_email' => '(email)',
    ] as $name => $columns) {
        try {
            $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON cc_leads $columns");
        } catch (PDOException $e) {
            if (!$mysql || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    }
};
