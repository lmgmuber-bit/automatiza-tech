<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ver-estado-wp-test.php
// Estado del proyecto, Etapa 1: ver-estado.php mínimo («Pronto verás aquí el avance») con el enlace al plan.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_fila_ver_estado', 'at_pt_html_ver_estado');
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-ver-estado@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-ver-estado@example.com'));
ptc_sembrar((int) $plan->id);
$vista = 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html';
at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => $vista]);
$codigo = (string) $plan->codigo;

$fila = at_pt_fila_ver_estado($codigo);
$h = at_pt_html_ver_estado($fila);
ok($fila !== null && strpos($h, 'Pronto verás aquí el avance de tu proyecto') !== false, 'plan enviado: avisa que el avance viene pronto');
ok(strpos($h, 'href="' . esc_url(at_pt_url_ver_plan(home_url(), $codigo)) . '"') !== false && strpos($h, 'Ver el plan de actividades inicial') !== false, 'enlace al plan inicial por automatizatech');
ok(stripos($h, 'easypanel') === false, 'ningún enlace al renderer');
ok(stripos($h, 'noindex') !== false, 'no se indexa');
at_pt_guardar((int) $plan->id, ['estado' => 'listo']);
ok(at_pt_fila_ver_estado($codigo) === null, 'un plan que no está enviado no tiene página de estado');
ok(at_pt_fila_ver_estado('NoExiste1234') === null && at_pt_fila_ver_estado('') === null && at_pt_fila_ver_estado('ab<c') === null, 'código inventado, vacío o con caracteres raros: sin página');
$hn = at_pt_html_ver_estado(null);
ok(strpos($hn, 'no está disponible') !== false && stripos($hn, $codigo) === false, 'sin fila: mensaje de no disponible sin datos del plan');
fin();
