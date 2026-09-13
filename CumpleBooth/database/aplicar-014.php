<?php
// cumpleclick.com: aplica SOLO la migración 014 (salas de ayudantes) y la registra en
// cc_schema_migrations. Vive en domains/cumpleclick.com/database/ (fuera de public_html);
// `../public` es el enlace simbólico a public_html/app. Idempotente.
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Solo CLI.\n"); exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$version = '014_salas_ayudantes';
$ya = $pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');
$ya->execute([$version]);
if ($ya->fetchColumn()) { echo "skip $version\n"; exit(0); }
$migracion = require __DIR__ . '/migrations/014_salas_ayudantes.php';
$migracion($pdo);
$pdo->prepare('INSERT INTO cc_schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$version, gmdate('Y-m-d H:i:s')]);
echo "applied $version\n";
