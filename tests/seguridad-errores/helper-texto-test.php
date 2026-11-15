<?php
// php tests/seguridad-errores/helper-texto-test.php
require __DIR__ . '/harness.php';
define('ABSPATH', raiz() . '/');
require raiz() . '/at-texto.php';

echo "at_texto()\n";
comprobar(at_texto(['a', 'b']) === '', 'arreglo -> omision vacia');
comprobar(at_texto(['a'], 'sin dato') === 'sin dato', 'arreglo -> omision indicada');
comprobar(at_texto(new stdClass()) === '', 'objeto -> omision vacia');
comprobar(at_texto(new stdClass(), 'x') === 'x', 'objeto -> omision indicada');
comprobar(at_texto(null) === '', 'null -> omision vacia');
comprobar(at_texto(null, 'n') === 'n', 'null -> omision indicada');
comprobar(at_texto(42) === '42', 'int -> texto');
comprobar(at_texto(1.5) === '1.5', 'float -> texto');
comprobar(at_texto('hola') === 'hola', 'string igual');
comprobar(at_texto('') === '', 'string vacio igual');
comprobar(at_texto(['x']) !== 'Array', 'nunca devuelve "Array"');

// Sin ABSPATH el archivo no hace nada (guarda de acceso directo)
$o = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(raiz() . '/at-texto.php'));
comprobar(trim((string) $o) === '', 'ejecutado directo sale vacio');
cierre();
