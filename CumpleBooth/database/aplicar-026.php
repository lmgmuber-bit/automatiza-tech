<?php
// Ejecutor CLI de un solo uso; migración ANTES de publicar lib.ferias.php, feria-api.php, api.php y upload.php.
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__.'/../public/lib.php';
$pdo = cb_pdo();
$version = '026_modo_feria';
$ya = $pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');
$ya->execute([$version]);
if ($ya->fetchColumn()) { echo "skip $version\n"; }
else {
    (require __DIR__.'/migrations/026_modo_feria.php')($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version, applied_at) VALUES (?, ?)')
        ->execute([$version, gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
foreach (['cc_ferias', 'cc_feria_fotos'] as $t) {
    echo "tabla: $t; filas: ", $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(), "\n";
}
