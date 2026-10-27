<?php
// Funciones puras del módulo de colaboradores. Uso: php tests/colaboradores/puras-test.php
define('AT_COLAB_PRUEBAS', true);
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/colaboradores/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

ok(at_colab_normalizar_ids(['3', '3', 0, -1, 'x', 7]) === [3, 7], 'normalizar: enteros positivos, únicos y ordenados');
ok(at_colab_normalizar_ids('3') === [], 'normalizar: un texto suelto no es una lista');
ok(at_colab_normalizar_ids(null) === [], 'normalizar: null da lista vacía');
ok(at_colab_normalizar_ids([12, '5', 5.0]) === [5, 12], 'normalizar: mezcla de tipos');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
