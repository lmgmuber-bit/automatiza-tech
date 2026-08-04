<?php
require __DIR__ . '/_cli.php';
$pdo = cb_pdo();
$timeType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'DATETIME' : 'TEXT';
$tableOptions = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
$pdo->exec("CREATE TABLE IF NOT EXISTS cc_schema_migrations (version VARCHAR(120) NOT NULL PRIMARY KEY, applied_at $timeType NOT NULL)$tableOptions");
$applied = array_flip($pdo->query('SELECT version FROM cc_schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob(dirname(__DIR__) . '/database/migrations/[0-9][0-9][0-9]_*.php') ?: [];
sort($files, SORT_STRING);
foreach ($files as $file) {
    if (substr($file, -9) === '.down.php') { continue; }
    $version = basename($file, '.php');
    if (isset($applied[$version])) { fwrite(STDOUT, "skip $version\n"); continue; }
    $migration = require $file;
    if (!is_callable($migration)) { throw new RuntimeException("Migración inválida: $version"); }
    // MySQL hace implicit commit en DDL; SQLite sí permite envolver la migración.
    $transactional = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql';
    if ($transactional) { $pdo->beginTransaction(); }
    try {
        $migration($pdo);
        $pdo->prepare('INSERT INTO cc_schema_migrations (version,applied_at) VALUES (?,?)')->execute([$version, gmdate('Y-m-d H:i:s')]);
        if ($transactional) { $pdo->commit(); }
        fwrite(STDOUT, "applied $version\n");
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
}
