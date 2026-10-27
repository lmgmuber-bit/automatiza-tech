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

$ex = at_colab_tipos_excluidos();
sort($ex);
ok($ex === ['boleta', 'contratacion', 'contrato', 'cotizacion', 'factura', 'pago', 'propuesta_enviada'], 'tipos excluidos: exactamente los siete');

$eventos = [
	['source' => 'client', 'detail_type' => 'pago', 'title' => 'Pago', 'description' => '', 'amount' => 150000, 'fecha' => '2026-10-02 10:00:00'],
	['source' => 'system', 'tipo_evento' => 'nota', 'title' => 'Nota vieja', 'description' => 'a', 'amount' => 5, 'attachment_url' => 'https://x/y.pdf', 'attachment_name' => 'y.pdf', 'fecha' => '2026-10-01 10:00:00'],
	['source' => 'client', 'detail_type' => 'reunion', 'title' => 'Reunión', 'description' => 'b', 'fecha' => '2026-10-03 10:00:00'],
	['source' => 'client', 'detail_type' => 'contrato', 'title' => 'Contrato', 'description' => '', 'fecha' => '2026-10-04 10:00:00'],
];
$f = at_colab_filtrar_eventos($eventos);
ok(count($f) === 2, 'filtrar: quedan la nota y la reunión');
ok($f[0]['title'] === 'Reunión' && $f[1]['title'] === 'Nota vieja', 'filtrar: orden por fecha descendente');
$claves = array_merge(...array_map('array_keys', $f));
ok(!in_array('amount', $claves, true) && !in_array('attachment_url', $claves, true) && !in_array('attachment_name', $claves, true), 'filtrar: sin monto ni adjuntos');
ok(at_colab_filtrar_eventos([]) === [], 'filtrar: lista vacía');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
