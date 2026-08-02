<?php
/** Historial versionado de cc_theme_prompts: cada guardado agrega una fila
 * nueva acá (append-only), nunca se sobreescribe. cc_theme_prompts sigue
 * siendo "la versión actual" para no tocar cb_load_theme_prompts(); esta
 * tabla es solo el registro histórico consultable desde el admin. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $autoIncrement = $mysql ? 'BIGINT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_theme_prompt_history (
        id $autoIncrement,
        theme_slug VARCHAR(40) NOT NULL,
        asset_key VARCHAR(120) NOT NULL,
        prompt_text TEXT NOT NULL,
        action VARCHAR(10) NOT NULL DEFAULT 'save',
        created_by VARCHAR(80) NOT NULL DEFAULT '',
        created_at $timestamp NOT NULL
    )$tableOptions");

    // MySQL 8 no acepta IF NOT EXISTS para CREATE INDEX; 1061 significa que
    // ya existe (mismo patrón que 001_initial.php).
    try {
        $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ')
            . 'idx_theme_prompt_history_lookup ON cc_theme_prompt_history (theme_slug, asset_key, created_at)');
    } catch (PDOException $e) {
        if (!$mysql || (string) $e->getCode() !== '42000' || strpos($e->getMessage(), '1061') === false) {
            throw $e;
        }
    }
};
