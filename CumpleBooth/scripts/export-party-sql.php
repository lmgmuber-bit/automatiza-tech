<?php
declare(strict_types=1);

/**
 * Exporta una o varias fiestas desde la BD local como SQL listo para pegar
 * en phpMyAdmin de PROD. Hostinger no acepta MySQL remoto y SSH está
 * bloqueado, así que este es el camino: sembrar local con
 * seed-demos-prod.php --apply, exportar acá, pegar en PROD.
 *
 * No depende de que los IDs numéricos coincidan entre local y PROD: usa
 * `public_slug` (UNIQUE) para el upsert de cc_parties y una subconsulta por
 * ese slug para resolver el party_id de cc_guests. Reemplaza SIEMPRE los
 * invitados de esa fiesta (DELETE + INSERT) para que quede igual que en local.
 *
 * Uso:
 *   php scripts/export-party-sql.php demo-kpop-vip              > kpop.sql
 *   php scripts/export-party-sql.php demo-kpop-vip demo-frozen-vip > dos.sql
 *   php scripts/export-party-sql.php --all                      > todas-las-demo.sql
 *   (--all = toda fiesta cuyo public_slug empiece con "demo-" y termine en "-vip")
 */

require __DIR__ . '/_cli.php';

$args = array_slice($_SERVER['argv'], 1);
if (!$args) {
    fwrite(STDERR, "Uso: php scripts/export-party-sql.php <slug> [<slug>...] | --all\n");
    exit(1);
}

$pdo = cb_pdo();

if ($args === ['--all']) {
    $stmt = $pdo->query("SELECT public_slug FROM cc_parties WHERE public_slug LIKE 'demo-%-vip' ORDER BY public_slug");
    $slugs = array_column($stmt->fetchAll(), 'public_slug');
    if (!$slugs) {
        fwrite(STDERR, "No hay fiestas demo-*-vip en la BD local. Corre primero seed-demos-prod.php --apply.\n");
        exit(2);
    }
} else {
    $slugs = $args;
}

$q = static fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v);

echo "-- " . count($slugs) . " fiesta(s): " . implode(', ', $slugs) . "\n";
echo "-- Generado " . gmdate('Y-m-d H:i:s') . " UTC. Pegar completo en phpMyAdmin de PROD\n";
echo "-- (BD u402745362_cumple), pestaña SQL.\n\n";

foreach ($slugs as $slug) {
    $stmt = $pdo->prepare('SELECT * FROM cc_parties WHERE public_slug = ?');
    $stmt->execute([$slug]);
    $party = $stmt->fetch();
    if (!$party) {
        fwrite(STDERR, "Aviso: no existe la fiesta '{$slug}' en la BD local, se omite.\n");
        continue;
    }

    $stmt = $pdo->prepare('SELECT * FROM cc_guests WHERE party_id = ? ORDER BY sort_order');
    $stmt->execute([$party['id']]);
    $guests = $stmt->fetchAll();

    echo "-- === {$slug} ({$party['theme_slug']}) — " . count($guests) . " invitados ===\n";

    echo "INSERT INTO cc_parties (public_slug, birthday_person_name, admin_label, service_plan, gallery_enabled, theme_slug, event_date, active, frame_box_json, gallery_pin_hash, gallery_pin_hmac, created_at, updated_at, anonymized_at)\n";
    echo "VALUES ({$q($party['public_slug'])}, {$q($party['birthday_person_name'])}, {$q($party['admin_label'])}, {$q($party['service_plan'])}, {$q($party['gallery_enabled'])}, {$q($party['theme_slug'])}, {$q($party['event_date'])}, {$q($party['active'])}, {$q($party['frame_box_json'])}, {$q($party['gallery_pin_hash'])}, {$q($party['gallery_pin_hmac'])}, {$q($party['created_at'])}, {$q($party['updated_at'])}, {$q($party['anonymized_at'])})\n";
    echo "ON DUPLICATE KEY UPDATE birthday_person_name=VALUES(birthday_person_name), admin_label=VALUES(admin_label), service_plan=VALUES(service_plan), gallery_enabled=VALUES(gallery_enabled), theme_slug=VALUES(theme_slug), event_date=VALUES(event_date), active=VALUES(active), frame_box_json=VALUES(frame_box_json), gallery_pin_hash=VALUES(gallery_pin_hash), gallery_pin_hmac=VALUES(gallery_pin_hmac), updated_at=VALUES(updated_at);\n\n";

    echo "DELETE FROM cc_guests WHERE party_id = (SELECT id FROM cc_parties WHERE public_slug = {$q($slug)});\n\n";

    foreach ($guests as $g) {
        echo "INSERT INTO cc_guests (party_id, name, gender, sort_order, created_at)\n";
        echo "SELECT id, {$q($g['name'])}, {$q($g['gender'])}, {$q($g['sort_order'])}, {$q($g['created_at'])} FROM cc_parties WHERE public_slug = {$q($slug)};\n";
    }
    echo "\n";
}
