<?php
// php tests/agenda-rutas-clave/run.php
$fallos = 0;
foreach (['permiso-test', 'recorte-test', 'enlaces-test', 'cancelar-test'] as $t) {
	echo "=== $t\n";
	passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . "/$t.php"), $rc);
	$fallos += $rc ? 1 : 0;
}
echo $fallos ? "\nHAY FALLAS ($fallos archivos)\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);
