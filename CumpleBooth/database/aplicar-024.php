<?php
// Ejecutor CLI de un solo uso; migración ANTES de publicar lib/admin.
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__.'/../public/lib.php';
$pdo = cb_pdo();
$version = '024_agenda_eventos';
$ya = $pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');
$ya->execute([$version]);
if ($ya->fetchColumn()) { echo "skip $version\n"; }
else {
    (require __DIR__.'/migrations/024_agenda_eventos.php')($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version, applied_at) VALUES (?, ?)')
        ->execute([$version, gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
echo "tabla: cc_agenda_eventos; filas: ", $pdo->query('SELECT COUNT(*) FROM cc_agenda_eventos')->fetchColumn(), "\n";
