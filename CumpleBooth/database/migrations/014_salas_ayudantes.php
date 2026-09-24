<?php
/** Salas de ayudantes del juego 3D: la tablet es anfitriona, los celulares mandan acciones. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $autoIncrement = $mysql ? 'BIGINT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_salas (
        id $autoIncrement,
        codigo VARCHAR(8) NOT NULL UNIQUE,
        anfitrion_hash CHAR(64) NOT NULL,
        festejado VARCHAR(40) NOT NULL,
        resumen TEXT NULL,
        ultimo_seq INT NOT NULL DEFAULT 0,
        estado VARCHAR(10) NOT NULL DEFAULT 'activa',
        identity_hmac CHAR(64) NOT NULL,
        created_at $timestamp NOT NULL,
        expires_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL
    )$tableOptions");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_sala_ayudantes (
        id $autoIncrement,
        sala_id BIGINT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        nombre VARCHAR(40) NOT NULL,
        identity_hmac CHAR(64) NOT NULL,
        ultimo_animo INT NOT NULL DEFAULT 0,
        ultimo_copos INT NOT NULL DEFAULT 0,
        ultimo_ayuda INT NOT NULL DEFAULT 0,
        created_at $timestamp NOT NULL,
        last_seen_at $timestamp NOT NULL
    )$tableOptions");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_sala_acciones (
        id $autoIncrement,
        sala_id BIGINT NOT NULL,
        seq INT NOT NULL,
        ayudante_id BIGINT NOT NULL,
        tipo VARCHAR(12) NOT NULL,
        created_at $timestamp NOT NULL,
        created_ts INT NOT NULL
    )$tableOptions");

    foreach ([
        ['idx_salas_expires', 'cc_salas', '(expires_at)'],
        ['idx_sala_ayudantes_sala', 'cc_sala_ayudantes', '(sala_id)'],
        ['idx_sala_acciones_sala_seq', 'cc_sala_acciones', '(sala_id, seq)'],
        ['idx_sala_acciones_sala_ts', 'cc_sala_acciones', '(sala_id, created_ts)'],
    ] as [$name, $table, $columns]) {
        try {
            $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . "$name ON $table $columns");
        } catch (PDOException $e) {
            if (!$mysql || strpos($e->getMessage(), '1061') === false) {
                throw $e;
            }
        }
    }
};
