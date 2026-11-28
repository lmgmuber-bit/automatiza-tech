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
// Etapa 2: con un cierre publicado y la presentación lista, la página la muestra con el plan inicial y la agenda.
at_av_migrar_esquema();
at_pt_guardar((int) $plan->id, ['estado' => 'enviado']);
$plan_payload = at_pt_payload(at_pt_plan((int) $plan->id));
$d = ['formato' => 'at-cierre-v1', 'plan' => $codigo, 'cierre' => ['titulo' => 'Diseño terminado', 'bloques' => ['Diseño'], 'fecha_real' => '2026-10-10',
	'antes' => ['texto' => 'Antes.'], 'despues' => ['texto' => 'Después.', 'evidencias' => ['sitio']]],
	'evidencias' => [['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/']]];
$v = at_av_validar_import($d, $codigo, $plan_payload, at_av_vacio());
$ap = at_av_aplicar_import(at_av_vacio(), $v, 'cabc123', '2026-10-13 10:00:00')['avance'];
$vista_estado = 'https://' . at_pt_host_renderer() . '/p/' . $codigo . '-estado/index.html';
$ap['render'] = ['estado' => 'listo', 'view_url' => $vista_estado, 'pdf_url' => '', 'nota' => '', 'at' => '2026-10-13 10:05:00'];
at_av_guardar((int) $plan->id, $ap);
ok(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)) !== '' && strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)), 'Pronto verás aquí') !== false, 'Etapa 2: con la presentación lista pero el cierre en borrador, sigue «Pronto…» (Review Focus 2)');
at_av_guardar((int) $plan->id, at_av_cambiar_estado_cierre($ap, 'cabc123', 'publicado', '2026-10-13 10:00:00'));
$h = at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo));
ok(strpos($h, '<iframe src="' . esc_url($vista_estado) . '"') !== false && strpos($h, 'Pronto verás aquí') === false, 'Etapa 2: con un cierre publicado y la presentación lista, la muestra');
ok(strpos($h, 'href="' . esc_url(at_pt_url_ver_plan(home_url(), $codigo)) . '"') !== false && strpos($h, 'Ver el plan de actividades inicial') !== false, 'Etapa 2: barra con el plan inicial');
ok(strpos($h, 'data-at-pt-abrir') !== false && strpos($h, 'id="at-pt-agenda"') !== false && strpos($h, 'plan-seguimiento') !== false, 'Etapa 2: «Agendar seguimiento» con el mismo diálogo de ver-plan.php');
ok(stripos($h, 'noindex') !== false && strpos($h, 'Borrador') === false, 'Etapa 2: noindex y nada de borradores');
$fallido = at_av_leer(at_pt_plan((int) $plan->id));
$fallido['render']['estado'] = 'error';
$fallido['render']['nota'] = 'el renderer no respondió';
at_av_guardar((int) $plan->id, $fallido);
ok(strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)), '<iframe src="' . esc_url($vista_estado) . '"') !== false, 'Etapa 2: un render fallido después no rompe la página: sigue la última lista (Review Focus 5)');
$fallido['render']['view_url'] = 'https://evil.example/p/' . $codigo . '-estado/index.html';
at_av_guardar((int) $plan->id, $fallido);
ok(strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo)), 'evil.example') === false, 'Etapa 2: nunca muestra un enlace que no sea del renderer de ese plan');
$fallido['render']['view_url'] = $vista_estado;
at_av_guardar((int) $plan->id, $fallido);
ok(strpos(at_pt_html_ver_estado(at_pt_fila_ver_estado($codigo), true), '"abrir":true') !== false, 'Etapa 2: con agendar=1 el diálogo se abre solo');
fin();
