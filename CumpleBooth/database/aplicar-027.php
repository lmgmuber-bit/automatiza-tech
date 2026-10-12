<?php
// CLI privado, mismo contrato que aplicar-025.php. Migración ANTES de subir lib.php y admin/index.php.
if(PHP_SAPI!=='cli'){exit(2);}
require __DIR__.'/../public/lib.php';
$pdo=cb_pdo();$version='027_animador_apagados';
$ya=$pdo->prepare('SELECT 1 FROM cc_schema_migrations WHERE version = ?');$ya->execute([$version]);
if($ya->fetchColumn()){echo "skip $version\n";}
else{
    (require __DIR__.'/migrations/027_animador_apagados.php')($pdo);
    $pdo->prepare('INSERT INTO cc_schema_migrations (version,applied_at) VALUES (?,?)')->execute([$version,gmdate('Y-m-d H:i:s')]);
    echo "applied $version\n";
}
// Comprobación: la columna responde y nadie tiene juegos apagados todavía.
echo "cc_parties con animador_apagados: ",$pdo->query('SELECT COUNT(*) FROM cc_parties WHERE animador_apagados IS NOT NULL')->fetchColumn(),"\n";
