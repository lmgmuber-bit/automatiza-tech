<?php
// CLI privado, mismo contrato que aplicar-027.php. Migración ANTES de subir el código del baby shower con regalo.
if(PHP_SAPI!=='cli'){exit(2);}
require __DIR__.'/../public/lib.php';
$pdo=cb_pdo();$version='028_rsvp_regalo';
$ya=$pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');$ya->execute([$version]);
if($ya->fetchColumn()){echo "skip $version\n";}
else{
    (require __DIR__.'/migrations/028_rsvp_regalo.php')($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version,applied_at) VALUES (?,?)')->execute([$version,gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
// Comprobación: las columnas responden y nadie tiene equipo ni regalo todavía.
echo "cc_rsvps con team: ",$pdo->query('SELECT COUNT(*) FROM cc_rsvps WHERE team IS NOT NULL')->fetchColumn(),"\n";
echo "cc_rsvps con gift_item_id: ",$pdo->query('SELECT COUNT(*) FROM cc_rsvps WHERE gift_item_id IS NOT NULL')->fetchColumn(),"\n";
echo "cc_rsvps con baby_photo_key: ",$pdo->query('SELECT COUNT(*) FROM cc_rsvps WHERE baby_photo_key IS NOT NULL')->fetchColumn(),"\n";
