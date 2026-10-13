<?php
// Correr: php tests/plan/fechas-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Feriados de prueba: lunes 12-oct, sábado 31-oct y domingo 1-nov de 2026, martes 8-dic, viernes 25-dic y viernes 1-ene-2027.
$fer = ['2026-10-12', '2026-10-31', '2026-11-01', '2026-12-08', '2026-12-25', '2027-01-01'];

// Fecha 'Y-m-d' al inicio de la cadena
ok(at_pt_ymd('2026-09-29') === '2026-09-29' && at_pt_ymd(' 2026-09-29 14:35:00 ') === '2026-09-29', 'ymd: fecha sola y fecha con hora de MySQL');
ok(at_pt_ymd('2026-02-30') === '' && at_pt_ymd('basura') === '' && at_pt_ymd('29-09-2026') === '' && at_pt_ymd('2026-09-290') === '' && at_pt_ymd('') === '', 'ymd: fechas imposibles o mal escritas quedan vacías');

// Feriados desde el texto del ajuste de la agenda
ok(at_pt_feriados_de_texto("2026-12-08\r\n 2026-10-12 \n\nbasura\n2026-02-30\r2026-10-12\n12-10-2026") === ['2026-10-12', '2026-12-08'], 'feriados: una fecha por línea (\r\n, \n o \r), sin inválidas, sin repetir y en orden');
ok(at_pt_feriados_de_texto('') === [], 'feriados: texto vacío, lista vacía');

// Día hábil
ok(at_pt_es_habil('2026-09-29', $fer), 'martes 29-sep-2026 es hábil');
ok(!at_pt_es_habil('2026-10-03', $fer) && !at_pt_es_habil('2026-10-04', $fer), 'sábado 3 y domingo 4-oct-2026 no son hábiles');
ok(!at_pt_es_habil('2026-10-12', $fer) && at_pt_es_habil('2026-10-12', []), 'lunes 12-oct-2026: no es hábil si es feriado; sí lo es sin feriados');
ok(!at_pt_es_habil('basura', []) && !at_pt_es_habil('2026-02-30', []), 'una fecha inválida no es hábil');

// Siguiente hábil (estrictamente después)
ok(at_pt_siguiente_habil('2026-10-02', $fer) === '2026-10-05', 'viernes 2-oct -> lunes 5-oct');
ok(at_pt_siguiente_habil('2026-10-09', $fer) === '2026-10-13', 'viernes 9-oct -> martes 13-oct (salta el feriado del lunes 12)');
ok(at_pt_siguiente_habil('2026-12-24', $fer) === '2026-12-28', 'jueves 24-dic -> lunes 28-dic (salta el feriado del viernes 25)');
ok(at_pt_siguiente_habil('2026-12-31', $fer) === '2027-01-04', 'jueves 31-dic-2026 -> lunes 4-ene-2027 (cambio de año con feriado)');
ok(at_pt_siguiente_habil('2026-10-05', $fer) === '2026-10-06', 'desde un día hábil también avanza');
ok(at_pt_siguiente_habil('basura', $fer) === '', 'siguiente hábil de una fecha inválida: vacío');

// Sumar días hábiles ($desde cuenta como día 1)
ok(at_pt_sumar_habiles('2026-10-05', 1, $fer) === '2026-10-05', '1 día hábil desde el lunes 5-oct termina ese mismo día');
ok(at_pt_sumar_habiles('2026-10-05', 5, $fer) === '2026-10-09', '5 días hábiles desde el lunes 5-oct terminan el viernes 9-oct');
ok(at_pt_sumar_habiles('2026-10-05', 10, $fer) === '2026-10-19' && at_pt_sumar_habiles('2026-10-05', 10, []) === '2026-10-16', '10 días hábiles desde el 5-oct: lunes 19-oct con el feriado del 12; viernes 16-oct sin él');
ok(at_pt_sumar_habiles('2026-10-03', 1, $fer) === '2026-10-05' && at_pt_sumar_habiles('2026-10-03', 3, $fer) === '2026-10-07', 'desde un sábado cuenta desde el lunes siguiente');
ok(at_pt_sumar_habiles('2026-10-12', 1, $fer) === '2026-10-13', 'desde un feriado cuenta desde el hábil siguiente');
ok(at_pt_sumar_habiles('2026-10-05', 0, $fer) === '2026-10-05' && at_pt_sumar_habiles('2026-10-05', -3, $fer) === '2026-10-05', 'n menor que 1 vale 1');
ok(at_pt_sumar_habiles('2026-12-21', 5, $fer) === '2026-12-28', '5 días desde el lunes 21-dic: 21, 22, 23, 24 y 28 (salta el 25 y el fin de semana)');
ok(at_pt_sumar_habiles('basura', 3, $fer) === '', 'sumar desde una fecha inválida: vacío');

// Inicio por defecto: primer lunes estrictamente después de la firma (o el hábil siguiente si ese lunes es feriado)
ok(at_pt_inicio_por_defecto('2026-09-29', $fer) === '2026-10-05', 'firma martes 29-sep-2026 -> inicio lunes 5-oct-2026');
ok(at_pt_inicio_por_defecto('2026-09-29 18:40:12', $fer) === '2026-10-05', 'firma con hora (signed_at) -> el mismo lunes');
ok(at_pt_inicio_por_defecto('2026-10-05', []) === '2026-10-12', 'firma un lunes -> el lunes siguiente, no el mismo día');
ok(at_pt_inicio_por_defecto('2026-10-05', $fer) === '2026-10-13', 'firma lunes 5-oct con feriado el lunes 12 -> martes 13-oct');
ok(at_pt_inicio_por_defecto('2026-10-03', $fer) === '2026-10-05' && at_pt_inicio_por_defecto('2026-10-04', $fer) === '2026-10-05', 'firma sábado 3 o domingo 4-oct -> lunes 5-oct');
ok(at_pt_inicio_por_defecto('2026-10-11', $fer) === '2026-10-13', 'firma domingo 11-oct: el lunes 12 es feriado -> martes 13-oct');
ok(at_pt_inicio_por_defecto('2026-12-30', $fer) === '2027-01-04', 'firma miércoles 30-dic-2026 -> lunes 4-ene-2027');
ok(at_pt_inicio_por_defecto('', $fer) === '' && at_pt_inicio_por_defecto('2026-13-01', $fer) === '', 'firma inválida: vacío');

// Fecha larga
ok(at_pt_fecha_larga('2026-09-28') === '28 de septiembre de 2026', 'fecha larga: 28 de septiembre de 2026');
ok(at_pt_fecha_larga('2026-10-05 10:00:00') === '5 de octubre de 2026' && at_pt_fecha_larga('2027-01-04') === '4 de enero de 2027', 'fecha larga sin cero a la izquierda y con hora');
ok(at_pt_fecha_larga('basura') === '' && at_pt_fecha_larga('2026-02-30') === '', 'fecha larga inválida: vacío');

fin();
