<?php
// cumpleclick.com: aplica SOLO la migración 023 (usuarios del backoffice) y la registra.
// Vive en domains/cumpleclick.com/database/; `../public` es el enlace a public_html/app.
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$version = '023_admin_users';
$ya = $pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');
$ya->execute([$version]);
if ($ya->fetchColumn()) { echo "skip $version\n"; }
else {
    $migracion = require __DIR__ . '/migrations/023_admin_users.php';
    $migracion($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$version, gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
echo "tablas: ", implode(' ', $pdo->query("show tables like 'cc_admin_user%'")->fetchAll(PDO::FETCH_COLUMN)), "\n";
echo "usuarios: ", $pdo->query('SELECT COUNT(*) FROM cc_admin_users')->fetchColumn(), "\n";
