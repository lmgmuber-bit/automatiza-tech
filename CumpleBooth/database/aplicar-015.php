<?php
// cumpleclick.com: aplica SOLO la migración 015 (contactos de la fiesta y cobro) y la registra.
// Vive en domains/cumpleclick.com/database/; `../public` es el enlace a public_html/app.
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$version = '015_party_contacts_billing';
$ya = $pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');
$ya->execute([$version]);
if ($ya->fetchColumn()) { echo "skip $version\n"; }
else {
    $migracion = require __DIR__ . '/migrations/015_party_contacts_billing.php';
    $migracion($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$version, gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
echo "tabla: ", implode(' ', $pdo->query("show tables like 'cc_party_contacts'")->fetchAll(PDO::FETCH_COLUMN)), "\n";
$cols = $pdo->query('SHOW COLUMNS FROM cc_parties')->fetchAll(PDO::FETCH_COLUMN);
echo "columnas de cobro: ", implode(' ', array_intersect($cols, ['price_total', 'discount_amount', 'discount_label', 'deposit_amount', 'payment_note'])), "\n";
echo "contactos cargados: ", $pdo->query('SELECT COUNT(*) FROM cc_party_contacts')->fetchColumn(), "\n";
