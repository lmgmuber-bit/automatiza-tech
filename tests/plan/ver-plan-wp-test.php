<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ver-plan-wp-test.php
// Etapa 2, Task 5: la página pública ver-plan.php (iframe del renderer, barra con los dos botones y diálogo de agenda).
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_fila_ver_plan', 'at_pt_html_ver_plan');
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-plan-ver@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-plan-ver@example.com'));
ptc_sembrar((int) $plan->id);
$vista = 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html';
at_pt_guardar((int) $plan->id, ['estado' => 'enviado', 'view_url' => $vista]);
$codigo = (string) $plan->codigo;

$fila = at_pt_fila_ver_plan($codigo);
$h = at_pt_html_ver_plan($fila, false);
ok($fila !== null && strpos($h, '<iframe src="' . esc_url($vista) . '"') !== false, 'muestra el plan del renderer en un iframe');
ok(strpos($h, 'Agendar mi llamada de seguimiento') !== false && strpos($h, 'Agendar por WhatsApp con Tech') !== false && strpos($h, 'https://wa.me/') !== false, 'barra con los dos botones de agenda');
ok(strpos($h, 'id="at-fecha"') !== false && strpos($h, 'id="at-slots"') !== false && strpos($h, 'id="at-scheduled-time"') !== false && strpos($h, 'at-agenda.js') !== false, 'diálogo con el selector de horarios de la portada');
ok(strpos($h, at_pt_token_agenda_hoy($codigo)) !== false && strpos($h, rest_url('automatiza-tech/v1/plan-seguimiento')) !== false && strpos($h, '"abrir":false') !== false, 'trae el token del plan y la ruta de la agenda; sin agendar=1 el diálogo no se abre solo');
ok(strpos(at_pt_html_ver_plan($fila, true), '"abrir":true') !== false, 'con agendar=1 el diálogo se abre solo');
ok(strpos($h, 'name="sitio_web"') !== false && stripos($h, 'aceptar') === false, 'campo trampa y sin aceptar ni rechazar');
ok(stripos($h, 'noindex') !== false, 'no se indexa');

// Ya agendada: el botón web avisa la fecha en vez de abrir el selector
global $wpdb;
$wpdb->insert($wpdb->prefix . 'automatiza_followup_meetings', ['client_name' => 'Cliente Prueba', 'client_email' => 'prueba-plan-ver@example.com', 'meeting_date' => gmdate('Y-m-d', time() + 5 * 86400), 'meeting_time' => '10:00:00', 'notes' => 'Plan de trabajo ' . $codigo . ' (prueba).', 'status' => 'scheduled']);
$GLOBALS['ptc_creado']['reuniones'][] = (int) $wpdb->insert_id;
$hy = at_pt_html_ver_plan(at_pt_fila_ver_plan($codigo), true);
ok(strpos($hy, 'Ya tienes tu llamada de seguimiento') !== false && strpos($hy, 'id="at-fecha"') === false, 'con una llamada ya agendada lo dice y no muestra el selector');

// Listo (vista de Luis antes de enviar): se ve, pero la agenda no está activa
at_pt_guardar((int) $plan->id, ['estado' => 'listo']);
$hl = at_pt_html_ver_plan(at_pt_fila_ver_plan($codigo), true);
ok(strpos($hl, '<iframe') !== false && strpos($hl, 'id="at-fecha"') === false && strpos($hl, 'cuando te enviemos el plan') !== false, 'listo: se ve el plan y la agenda espera al envío');

// Review Focus 5: borrador, código inventado o con otro largo
at_pt_guardar((int) $plan->id, ['estado' => 'borrador']);
ok(at_pt_fila_ver_plan($codigo) === null && at_pt_fila_ver_plan('NoExiste1234') === null && at_pt_fila_ver_plan('abc') === null && at_pt_fila_ver_plan('') === null, 'Review Focus 5: borrador, código inventado o corto: sin plan');
$hn = at_pt_html_ver_plan(null, false);
ok(strpos($hn, 'no está disponible') !== false && strpos($hn, '<iframe') === false && strpos($hn, $codigo) === false, 'sin plan: «no está disponible» sin mostrar nada');

// ver-plan.php solo carga WordPress y llama a la vista
$archivo = (string) file_get_contents(dirname(__DIR__, 2) . '/ver-plan.php');
ok(strpos($archivo, "require_once __DIR__ . '/wp-load.php'") !== false && strpos($archivo, 'at_pt_html_ver_plan(') !== false && strpos($archivo, 'nocache_headers()') !== false && strpos($archivo, 'X-Robots-Tag') !== false, 'ver-plan.php: carga WordPress, sin caché, noindex, y dibuja la vista');
ok(strpos($archivo, "is_string(\$_GET['id'])") !== false, 'ver-plan.php: ignora un id que no es texto (?id[]=x no lanza «Array to string conversion»)');

fin();
