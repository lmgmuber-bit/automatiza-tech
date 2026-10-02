<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/envio-wp-test.php
// Etapa 2, Task 2: envío del plan por correo (PDF adjunto o solo enlace) y por el WhatsApp de Luis.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_enviar_plan', 'at_pt_descargar_pdf', 'at_pt_marcar_enviado_whatsapp', 'at_pt_url_whatsapp_cliente');
global $wpdb;
$m = ptc_marca();

// Correos: se anotan y no salen (fixtures-panel.php corta todo con __return_true en PHP_INT_MAX).
$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return $nulo; }, 10, 2);
// Renderer: 'ok' devuelve un PDF chico, 'caido' un error, 'grande' 16 MB, 'html' una página que no es PDF.
$renderer = 'ok';
$pedidas = [];
add_filter('pre_http_request', function ($pre, $args, $url) use (&$renderer, &$pedidas) {
	$pedidas[] = $url;
	if (strpos($url, '/presentation.pdf') === false) {
		return $pre;
	}
	if ($renderer === 'caido') {
		return new WP_Error('http_request_failed', 'caído');
	}
	$cuerpo = ['ok' => "%PDF-1.4\n% prueba\n", 'grande' => '%PDF' . str_repeat('x', AT_PT_PDF_MAX_BYTES + 10), 'html' => '<html>no</html>'][$renderer];
	return ['headers' => [], 'body' => $cuerpo, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, PHP_INT_MAX, 3); // después del corte de fixtures-panel.php (misma prioridad, registrado después): gana este

/** Plan listo con su versión final en el renderer. */
function plan_listo(string $marca, string $correo = 'prueba-plan-envio@example.com'): object {
	$c = ptc_cliente($marca . bin2hex(random_bytes(2)), $correo);
	$plan = ptc_plan(ptc_contrato($c['tech'], $marca, ptc_propuesta($marca), 'servicios', 'signed', $correo));
	ptc_sembrar((int) $plan->id);
	$codigo = (string) $plan->codigo;
	at_pt_guardar((int) $plan->id, ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $codigo . '/index.html', 'pdf_url' => 'https://' . at_pt_host_renderer() . '/p/' . $codigo . '/presentation.pdf']);
	return at_pt_plan((int) $plan->id);
}

// Descarga del PDF
$ok_pdf = at_pt_descargar_pdf('https://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9');
ok(is_string($ok_pdf) && is_file($ok_pdf) && strncmp((string) file_get_contents($ok_pdf), '%PDF', 4) === 0, 'baja el PDF del renderer a un archivo temporal');
if (is_string($ok_pdf)) { @unlink($ok_pdf); @rmdir(dirname($ok_pdf)); }
$antes = count($pedidas);
ok(is_wp_error(at_pt_descargar_pdf('https://otro-sitio.example.com/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9')) && is_wp_error(at_pt_descargar_pdf('http://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9')) && count($pedidas) === $antes, 'nunca pide un PDF fuera del renderer ni por http');
$renderer = 'grande';
$g = at_pt_descargar_pdf('https://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9');
ok(is_wp_error($g) && $g->get_error_message() === 'el PDF pesa más de 15 MB', 'Review Focus 4: más de 15 MB no se adjunta');
$renderer = 'html';
ok(is_wp_error(at_pt_descargar_pdf('https://' . at_pt_host_renderer() . '/p/Ab3dE5fG7hJ9/presentation.pdf', 'Ab3dE5fG7hJ9')), 'una respuesta que no es PDF no se adjunta');
$renderer = 'ok';

// Envío por correo de un plan listo
$p = plan_listo($m);
$correos = [];
$r = at_pt_enviar_plan((int) $p->id);
$fila = at_pt_plan((int) $p->id);
ok($r === ['ok' => true, 'motivo' => '', 'sin_pdf' => ''], 'envía el plan listo');
ok($fila->estado === 'enviado' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $fila->enviado_at) === 1, 'queda «enviado» con la fecha de envío');
ok(count($correos) === 1 && $correos[0]['to'] === 'prueba-plan-envio@example.com', 'un correo al cliente');
$c0 = $correos[0] ?? ['subject' => '', 'message' => '', 'headers' => [], 'attachments' => []];
ok(strpos($c0['subject'], 'Tu plan de trabajo — ') === 0, 'asunto «Tu plan de trabajo — {proyecto}»');
ok(strpos($c0['message'], home_url('/ver-plan.php?id=' . $p->codigo)) !== false && strpos($c0['message'], 'agendar=1') !== false && stripos($c0['message'], 'easypanel') === false, 'el correo enlaza a ver-plan.php y nunca al renderer');
ok(count((array) $c0['attachments']) === 1 && substr((string) $c0['attachments'][0], -4) === '.pdf' && !file_exists((string) $c0['attachments'][0]), 'lleva el PDF adjunto y el temporal se borra después');
$cab = implode("\n", (array) $c0['headers']);
ok(strpos($cab, 'Content-Type: text/html; charset=UTF-8') !== false && strpos($cab, 'Reply-To: ') !== false, 'HTML y Reply-To al correo del cierre');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND titulo = %s", at_pt_crm_de_plan($fila), 'Plan de trabajo enviado')) === 1, 'queda en el historial del CRM');

// Reenvío de un plan ya enviado: sale otra vez y sigue enviado
$correos = [];
$r2 = at_pt_enviar_plan((int) $p->id);
ok($r2['ok'] === true && count($correos) === 1 && at_pt_plan((int) $p->id)->estado === 'enviado', 'un plan enviado se puede reenviar');

// Review Focus 4: renderer caído -> el correo sale igual, sin adjunto, y dice por qué
$p2 = plan_listo($m . 'b');
$renderer = 'caido';
$correos = [];
$r3 = at_pt_enviar_plan((int) $p2->id);
ok($r3['ok'] === true && $r3['sin_pdf'] === 'no se pudo descargar el PDF' && count($correos) === 1 && (array) $correos[0]['attachments'] === [], 'renderer caído: correo sin adjunto y con el motivo');
ok(strpos($correos[0]['message'] ?? '', 'adjunto') === false && strpos($correos[0]['message'] ?? '', 'ver-plan.php') !== false, 'sin PDF el correo no dice «adjunto» y lleva el enlace');
$renderer = 'ok';

// No se envía: borrador, sin vista, sin correo, correo que falla
$p3 = plan_listo($m . 'c');
ptc_estado((int) $p3->id, 'borrador');
$correos = [];
ok(at_pt_enviar_plan((int) $p3->id) === ['ok' => false, 'motivo' => 'no_listo', 'sin_pdf' => ''] && $correos === [], 'un borrador no se envía');
ptc_estado((int) $p3->id, 'listo');
at_pt_guardar((int) $p3->id, ['view_url' => '']);
ok(at_pt_enviar_plan((int) $p3->id)['motivo'] === 'no_listo', 'sin versión final no se envía');
$p4 = plan_listo($m . 'd', '');
$correos = [];
ok(at_pt_enviar_plan((int) $p4->id) === ['ok' => false, 'motivo' => 'sin_correo', 'sin_pdf' => ''] && $correos === [] && at_pt_plan((int) $p4->id)->estado === 'listo', 'Review Focus 3: sin correo no sale y sigue listo');
ok(at_pt_enviar_plan(999999999)['motivo'] === 'sin_plan', 'plan que no existe');
$p5 = plan_listo($m . 'e');
$falla = function () { return false; };
add_filter('pre_wp_mail', $falla, PHP_INT_MAX - 1);
remove_all_filters('pre_wp_mail', PHP_INT_MAX);
$r5 = at_pt_enviar_plan((int) $p5->id);
ok($r5['ok'] === false && $r5['motivo'] === 'correo_fallo' && at_pt_plan((int) $p5->id)->estado === 'listo', 'si el correo falla, el plan sigue listo');
remove_filter('pre_wp_mail', $falla, PHP_INT_MAX - 1);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);

// WhatsApp de Luis
$p6 = plan_listo($m . 'f');
$u = at_pt_url_whatsapp_cliente($p6);
ok(strpos($u, 'https://wa.me/56911111111?text=') === 0 && strpos(rawurldecode($u), home_url('/ver-plan.php?id=' . $p6->codigo)) !== false, 'wa.me al teléfono del cliente con el enlace del plan');
$w = at_pt_marcar_enviado_whatsapp((int) $p6->id);
ok($w['ok'] === true && $w['url'] === $u && at_pt_plan((int) $p6->id)->estado === 'enviado', 'abrir el WhatsApp deja el plan enviado');
ok(at_pt_marcar_enviado_whatsapp((int) $p6->id)['ok'] === true, 'se puede volver a abrir con el plan enviado');
$p7 = plan_listo($m . 'g');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['telefono' => ''], ['id' => at_pt_crm_de_plan($p7)]);
$ph = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT placeholders FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", (int) $p7->contrato_id)), true);
$ph['telefono_cliente'] = '';
$wpdb->update($wpdb->prefix . 'automatiza_contracts', ['placeholders' => wp_json_encode($ph)], ['id' => (int) $p7->contrato_id]);
ok(at_pt_marcar_enviado_whatsapp((int) $p7->id) === ['ok' => false, 'motivo' => 'sin_telefono', 'url' => ''] && at_pt_plan((int) $p7->id)->estado === 'listo', 'sin teléfono no se abre y sigue listo');
ptc_estado((int) $p7->id, 'borrador');
ok(at_pt_marcar_enviado_whatsapp((int) $p7->id)['motivo'] === 'no_listo', 'un borrador no se manda por WhatsApp');

// El render pedido para un plan del sitio trae la agenda web
ok(at_pt_datos_render($p6)['sitio'] === home_url(), 'at_pt_datos_render() trae el sitio para la agenda web');

fin();
