<?php
/** Marketing: tablas independientes, idempotentes en MySQL y SQLite. */
return static function(PDO $pdo):void {
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $id=$mysql?'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $options=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_marketing_piezas (
        id $id, fecha_programada DATE NOT NULL, hora TIME NULL,
        formato VARCHAR(16) NOT NULL, pilar VARCHAR(80) NOT NULL, titulo VARCHAR(160) NOT NULL,
        gancho TEXT NOT NULL, copy TEXT NOT NULL, hashtags VARCHAR(255) NOT NULL,
        primer_comentario TEXT NOT NULL, asset_key VARCHAR(255) NULL, asset_url_externa TEXT NULL,
        estado VARCHAR(16) NOT NULL DEFAULT 'idea', publicado_url TEXT NULL, publicado_at DATETIME NULL,
        metricas_json TEXT NULL, notas TEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        UNIQUE (fecha_programada,titulo)
    )$options");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cc_marketing_semanas (
        semana DATE NOT NULL PRIMARY KEY, seguidores BIGINT NOT NULL DEFAULT 0,
        alcance BIGINT NOT NULL DEFAULT 0, guardados BIGINT NOT NULL DEFAULT 0,
        mensajes_whatsapp BIGINT NOT NULL DEFAULT 0, notas TEXT NOT NULL
    )$options");
};
