<?php
/** Validación de calendario y reloj; no requiere base de datos ni secretos. */
if (PHP_SAPI !== 'cli') { exit(2); }
$file = dirname(__DIR__, 2) . '/public/lib.fechas.php';
if (!is_file($file)) { echo "FAIL: falta el validador compartido de fechas y horas\n"; exit(1); }
require $file;
$checks = 0; $failures = 0;
foreach ([
    ['2028-02-29', true], ['2024-02-29', true], ['2026-12-31', true],
    ['2026-02-30', false], ['2025-02-29', false], ['2026-04-31', false],
    ['0000-01-01', false], ['2026-13-01', false], ['2026-00-10', false],
    ['2026-12-00', false], ['2026-1-01', false], ['2026-01-01x', false],
    ["2026-01-01\n", false], ['', false],
] as [$value, $expected]) {
    $checks++; if (cb_fecha_valida($value) !== $expected) { $failures++; echo "FAIL: fecha caso $checks\n"; }
}
foreach ([['00:00',true],['23:59',true],['16:30',true],['24:00',false],['99:99',false],
    ['23:60',false],['9:00',false],['12:1',false],['12:00:00',false],["12:00\n",false],['',false]] as [$value,$expected]) {
    $checks++; if (cb_hora_valida($value) !== $expected) { $failures++; echo "FAIL: hora caso $checks\n"; }
}
echo "fechas: $checks comprobaciones, $failures fallos\n"; exit($failures ? 1 : 0);
