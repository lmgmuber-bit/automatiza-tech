<?php
// php tests/seguridad-errores/lint-test.php [base]   (base por omision: origin/main)
// php -l limpio en todo PHP tocado respecto a la base. Un archivo que ya fallaba en la base
// no cuenta como falla nueva.
require __DIR__ . '/harness.php';
$base = $argv[1] ?? 'origin/main';
$r = raiz();
$lista = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($r) . ' diff --name-only --diff-filter=AM ' . escapeshellarg($base) . '..HEAD')));
foreach ($lista as $f) {
    if (substr($f, -4) !== '.php' || !is_file("$r/$f")) { continue; }
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$r/$f") . ' 2>&1');
    $ok = strpos((string) $out, 'No syntax errors') !== false;
    if (!$ok) {
        $antes = shell_exec('git -C ' . escapeshellarg($r) . ' show ' . escapeshellarg("$base:$f") . ' | ' . escapeshellarg(PHP_BINARY) . ' -l 2>&1');
        if (strpos((string) $antes, 'No syntax errors') === false) { echo "  (ya fallaba en la base: $f)\n"; $ok = true; }
    }
    comprobar($ok, "php -l $f", trim((string) $out));
}
cierre();
