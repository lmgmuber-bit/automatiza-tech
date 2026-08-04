<?php
/** Prompts privados y editables asociados a assets visuales de una temática. */
return static function (PDO $pdo): void {
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $timestamp = $mysql ? 'DATETIME' : 'TEXT';
    $tableOptions = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_theme_prompts (
        theme_slug VARCHAR(40) NOT NULL,
        asset_key VARCHAR(120) NOT NULL,
        prompt_text TEXT NOT NULL,
        created_at $timestamp NOT NULL,
        updated_at $timestamp NOT NULL,
        PRIMARY KEY (theme_slug, asset_key)
    )$tableOptions");
};
