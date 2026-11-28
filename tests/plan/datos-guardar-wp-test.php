<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-guardar-wp-test.php
// Task 5: guardar columnas del plan y cambiar su estado.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_guardar', 'at_pt_cambiar_estado');
require __DIR__ . '/datos-prueba.php';

$m = pt_marca();
$cli = pt_cliente($m);
$cid = pt_contrato($cli['tech'], null);
$pid = at_pt_crear_plan($cid);
if (!is_int($pid)) {
	echo "FALLA no se pudo crear el plan de prueba\n\n1 FALLAS\n";
	exit(1);
}
$no_existe = pt_plan_inexistente();

// 1) Guardar contenido y textos.
$plan = ['version' => 1, 'proyecto' => '[PRUEBA] Proyecto «ñandú»', 'fases' => [['clave' => 'soporte', 'titulo' => 'Soporte y mejora continua', 'bloques' => []]]];
ok(at_pt_guardar($pid, ['payload' => $plan, 'fecha_inicio' => '2026-10-05', 'comentarios' => 'Más corto el diseño', 'nota' => 'Nota']), '1) guarda contenido, fecha, comentarios y nota');
$f = at_pt_plan($pid);
ok(at_pt_payload($f) === $plan && strpos((string) $f->payload, '«ñandú»') !== false, '1) el contenido vuelve igual y queda como JSON legible (sin \\u)');
ok($f->fecha_inicio === '2026-10-05' && $f->comentarios === 'Más corto el diseño' && $f->nota === 'Nota', '1) fecha, comentarios y nota guardados');

// 2) Columnas que no se editan.
$codigo = (string) $f->codigo;
ok(at_pt_guardar($pid, ['codigo' => 'XXXXXXXXXXXX', 'contrato_id' => 1, 'nota' => 'otra']) && at_pt_plan($pid)->codigo === $codigo && (int) at_pt_plan($pid)->contrato_id === $cid, '2) código y contrato no se editan: se ignoran');
ok(!at_pt_guardar($pid, ['codigo' => 'XXXXXXXXXXXX']), '2) sin ninguna columna permitida no guarda nada');

// 3) Valores que no sirven: no se guarda nada (nunca un plan roto).
ok(!at_pt_guardar($pid, ['estado' => 'inventado', 'nota' => 'no']) && at_pt_plan($pid)->estado === 'generando' && at_pt_plan($pid)->nota === 'otra', '3) un estado desconocido no se guarda (ni la nota que venía con él)');
ok(!at_pt_guardar($pid, ['fecha_inicio' => '2026-02-30']) && at_pt_plan($pid)->fecha_inicio === '2026-10-05', '3) una fecha que no existe no se guarda');
ok(!at_pt_guardar($pid, ['payload' => 'no es un arreglo']) && at_pt_payload(at_pt_plan($pid)) === $plan, '3) un contenido que no es arreglo no se guarda');
ok(!at_pt_guardar($no_existe, ['nota' => 'x']), '3) un plan que no existe no se guarda');

// 4) Límites y formatos.
ok(at_pt_guardar($pid, ['nota' => str_repeat('á', 1500)]) && mb_strlen((string) at_pt_plan($pid)->nota) === 1000, '4) la nota se recorta a 1000 caracteres');
ok(at_pt_guardar($pid, ['view_url' => 'javascript:alert(1)', 'pdf_url' => 'https://render.ejemplo.test/p/abc123def456/presentation.pdf']) && at_pt_plan($pid)->view_url === '' && at_pt_plan($pid)->pdf_url === 'https://render.ejemplo.test/p/abc123def456/presentation.pdf', '4) solo se guardan enlaces http(s)');
ok(at_pt_guardar($pid, ['enviado_at' => '2026-10-05 10:00:00']) && at_pt_plan($pid)->enviado_at === '2026-10-05 10:00:00' && !at_pt_guardar($pid, ['enviado_at' => 'ayer']), '4) enviado_at: solo fecha y hora válidas');
ok(at_pt_guardar($pid, ['payload' => null, 'fecha_inicio' => '']) && at_pt_plan($pid)->payload === null && at_pt_plan($pid)->fecha_inicio === null, '4) contenido y fecha se pueden vaciar');

// 5) Cambiar el estado según at_pt_transiciones().
ok(at_pt_cambiar_estado($pid, 'borrador', 'Avisos: ninguno') && at_pt_plan($pid)->estado === 'borrador' && at_pt_plan($pid)->nota === 'Avisos: ninguno', '5) generando → borrador, con su nota');
ok(!at_pt_cambiar_estado($pid, 'enviado') && at_pt_plan($pid)->estado === 'borrador', '5) borrador → enviado no vale: no cambia');
ok(!at_pt_cambiar_estado($pid, 'inventado') && at_pt_plan($pid)->estado === 'borrador', '5) estado desconocido: no cambia');
ok(at_pt_cambiar_estado($pid, 'error', 'Destrabado por Luis') && at_pt_plan($pid)->estado === 'error' && at_pt_plan($pid)->nota === 'Destrabado por Luis', '5) borrador → error con la nota');
ok(at_pt_cambiar_estado($pid, 'borrador') && at_pt_plan($pid)->nota === '', '5) error → borrador sin nota: la nota se limpia');
ok(!at_pt_cambiar_estado($no_existe, 'borrador'), '5) un plan que no existe: false');

fin();
