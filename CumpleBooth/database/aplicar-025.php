<?php
// CLI privado, mismo contrato que aplicar-023.php. Migración antes de lib/admin.
if(PHP_SAPI!=='cli'){exit(2);}
require __DIR__.'/../public/lib.php';
$pdo=cb_pdo();$version='025_marketing_contenido';
$ya=$pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');$ya->execute([$version]);
if($ya->fetchColumn()){echo "skip $version\n";}
else{
    (require __DIR__.'/migrations/025_marketing_contenido.php')($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version,applied_at) VALUES (?,?)')->execute([$version,gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
foreach(['cc_marketing_piezas','cc_marketing_semanas']as$table){echo "$table: ",$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(),"\n";}
