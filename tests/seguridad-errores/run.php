<?php
// php tests/seguridad-errores/run.php
$fallos = 0;
foreach (['conflicto-horario-test'] as $t) {
    echo "=== $t\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . "/$t.php"), $rc);
    $fallos += $rc ? 1 : 0;
}
echo $fallos ? "\nHAY FALLAS ($fallos archivos)\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);
