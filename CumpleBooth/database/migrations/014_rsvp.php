<?php
/**
 * 014: confirmaciones de asistencia (RSVP) por evento.
 *
 * Las confirmaciones son del EVENTO (party_id), no de la invitación, por la
 * misma razón que las predicciones (decisión 2026-08-25): una fiesta puede
 * tener más de una invitación y la familia quiere UNA lista de confirmados.
 *
 * En cumpleaños infantiles confirma el apoderado e indica a qué niños trae;
 * en baby showers confirma la persona adulta y guest_names queda vacío.
 * Aditiva e idempotente.
 */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $fk = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cc_rsvps (
            id $id,
            party_id $fk NOT NULL,
            family_name VARCHAR(120) NOT NULL,
            guest_names VARCHAR(400) NULL,
            created_at $timestamp NOT NULL,
            updated_at $timestamp NOT NULL
        )$tableOptions"
    );
    try {
        $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'idx_rsvp_party ON cc_rsvps(party_id)');
    } catch (PDOException $e) {
        // 1061: el índice ya existe (MySQL no soporta IF NOT EXISTS aquí).
        if (!$mysql || strpos($e->getMessage(), '1061') === false) {
            throw $e;
        }
    }
};
