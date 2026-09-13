<?php
// cumpleclick.com: aplica SOLO la migración 013 (aceptación de Términos) y la registra.
// Vive en domains/cumpleclick.com/database/; `../public` es el enlace a public_html/app.
// Idempotente. Las fiestas ya activas quedan eximidas por la propia migración.
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$version = '013_plan_acceptances';
$ya = $pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');
$ya->execute([$version]);
if ($ya->fetchColumn()) { echo "skip $version\n"; }
else {
    $migracion = require __DIR__ . '/migrations/013_plan_acceptances.php';
    $migracion($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$version, gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
echo "tabla: ", implode(' ', $pdo->query("show tables like 'cc_plan_acceptances'")->fetchAll(PDO::FETCH_COLUMN)), "\n";
$st = $pdo->query('SELECT status, COUNT(*) c FROM cc_plan_acceptances GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
foreach ($st as $r) { echo "  ", $r['status'], ": ", $r['c'], "\n"; }
echo "fiestas activas: ", $pdo->query('SELECT COUNT(*) FROM cc_parties WHERE active = 1')->fetchColumn(), "\n";
