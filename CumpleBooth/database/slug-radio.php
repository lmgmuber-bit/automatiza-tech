<?php
// Lectura: dónde aparecen los slugs viejos (BD: toda columna de texto de toda tabla; disco: carpetas del almacén).
if (PHP_SAPI !== 'cli') { exit(2); }
require __DIR__ . '/../public/lib.php';
$pdo = cb_pdo();
$viejos = ['demo-frozen-vip', 'qa-spidey'];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (!preg_match('/char|text|json|blob/i', $c['Type'])) { continue; }
        foreach ($viejos as $v) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM `$t` WHERE `{$c['Field']}` LIKE ?");
            $st->execute(['%' . $v . '%']);
            $n = (int) $st->fetchColumn();
            if ($n > 0) {
                $ej = $pdo->prepare("SELECT `{$c['Field']}` FROM `$t` WHERE `{$c['Field']}` LIKE ? LIMIT 1");
                $ej->execute(['%' . $v . '%']);
                echo str_pad("$t.{$c['Field']}", 44) . " $v: $n filas  ej: " . substr((string) $ej->fetchColumn(), 0, 90) . "\n";
            }
        }
    }
}
echo "\nregla de slug: ";
$ref = new ReflectionFunction('cb_valid_public_slug');
$src = file($ref->getFileName());
echo trim(implode('', array_slice($src, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1))), "\n";
foreach (['isidora-reino-de-hielo', 'luciano-spidey'] as $n) {
    echo "  $n valido=" . var_export(cb_valid_public_slug($n), true) . " libre=" . var_export(cb_load_party_raw($n) === null, true) . "\n";
}
echo "\ncarpetas en almacen con esos slugs:\n";
foreach (glob(dirname(__DIR__) . '/almacen/*/*', GLOB_ONLYDIR) as $d) {
    foreach ($viejos as $v) { if (basename($d) === $v) { echo "  $d (" . count(glob("$d/*")) . " entradas)\n"; } }
}
echo "\nconfig: photo_dir=" . cb_config('photo_dir') . " invitation_dir=" . cb_config('invitation_dir') . "\n";
