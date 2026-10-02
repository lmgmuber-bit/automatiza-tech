<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-envio-wp-test.php
// Etapa 2, Task 3: botones de envío en la pestaña del plan y sus acciones admin-post (en un proceso aparte).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_accion_enviar', 'at_pt_accion_enviar_whatsapp');
global $wpdb;
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();

/** Plan listo (con versión final) de un cliente nuevo. */
function plan_listo_panel(string $marca, string $correo = 'prueba-plan-panel@example.com'): array {
	$c = ptc_cliente($marca, $correo);
	$plan = ptc_plan(ptc_contrato($c['tech'], $marca, ptc_propuesta($marca), 'servicios', 'signed', $correo));
	ptc_sembrar((int) $plan->id);
	at_pt_guardar((int) $plan->id, ['estado' => 'listo', 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/index.html', 'pdf_url' => 'https://' . at_pt_host_renderer() . '/p/' . $plan->codigo . '/presentation.pdf']);
	return ['plan' => at_pt_plan((int) $plan->id), 'crm' => $c['crm']];
}
function pestana(object $fila, int $crm): string {
	ob_start();
	at_pt_render_plan($fila, $crm);
	return (string) ob_get_clean();
}

// Render
$a = plan_listo_panel($m . 'a');
$h = pestana($a['plan'], $a['crm']);
ok(strpos($h, 'value="at_pt_enviar"') !== false && strpos($h, '📧 Enviar al cliente') !== false && strpos($h, 'confirm(') !== false, 'listo: botón «Enviar al cliente» con confirmación');
ok(strpos($h, 'value="at_pt_enviar_whatsapp"') !== false && strpos($h, 'target="_blank"') !== false && strpos($h, '💬 Enviar por mi WhatsApp') !== false, 'listo: «Enviar por mi WhatsApp» abre otra pestaña');
ok(strpos($h, 'prueba-plan-panel@example.com') !== false, 'dice a qué correo se envía');
ok(strpos($h, 'llega en la etapa 2') === false, 'ya no dice que el envío llega en la etapa 2');
ptc_estado((int) $a['plan']->id, 'borrador');
$hb = pestana(at_pt_plan((int) $a['plan']->id), $a['crm']);
ok(strpos($hb, 'value="at_pt_enviar"') === false && strpos($hb, 'value="at_pt_enviar_whatsapp"') === false, 'borrador: sin botones de envío');
ptc_estado((int) $a['plan']->id, 'enviado');
at_pt_guardar((int) $a['plan']->id, ['enviado_at' => '2026-10-01 10:00:00']);
$he = pestana(at_pt_plan((int) $a['plan']->id), $a['crm']);
ok(strpos($he, 'value="at_pt_enviar"') !== false && strpos($he, 'Reenviar') !== false && strpos($he, '2026-10-01 10:00:00') !== false, 'enviado: dice cuándo y permite reenviar');
$sin = plan_listo_panel($m . 'b', '');
$hs = pestana($sin['plan'], $sin['crm']);
ok(strpos($hs, 'no tiene correo') !== false && preg_match('/<button[^>]*disabled[^>]*>📧 Enviar al cliente/u', $hs) === 1, 'Review Focus 3: sin correo el botón queda deshabilitado y lo explica');

// Acciones (proceso aparte; el hijo corta los correos y las llamadas HTTP)
$b = plan_listo_panel($m . 'c');
$id = (int) $b['plan']->id;
$r = pt_correr_accion('at_pt_enviar', $admin, 'at_pt_plan_' . $id, ['plan_id' => $id, 'crm_id' => $b['crm']]);
$q = pt_query($r['redirect']);
ok(in_array($q['pt_msg'] ?? '', ['enviado', 'enviado_sin_pdf'], true) && at_pt_plan($id)->estado === 'enviado', 'la acción envía y vuelve con el aviso');
$w = pt_correr_accion('at_pt_enviar_whatsapp', $admin, 'at_pt_plan_' . $id, ['plan_id' => $id, 'crm_id' => $b['crm']]);
ok(strpos($w['redirect'], 'https://wa.me/56911111111?text=') === 0, 'WhatsApp: redirige al wa.me del cliente');
ptc_estado($id, 'borrador');
$r2 = pt_correr_accion('at_pt_enviar', $admin, 'at_pt_plan_' . $id, ['plan_id' => $id, 'crm_id' => $b['crm']]);
ok((pt_query($r2['redirect'])['pt_msg'] ?? '') === 'no_listo' && at_pt_plan($id)->estado === 'borrador', 'un borrador no se envía');
$r3 = pt_correr_accion('at_pt_enviar', $admin, 'at_pt_plan_0', ['plan_id' => $id, 'crm_id' => $b['crm']]);
ok($r3['redirect'] === '' && at_pt_plan($id)->estado === 'borrador', 'con un nonce que no es del plan no hace nada');
foreach (['enviado', 'enviado_sin_pdf', 'no_listo', 'sin_correo', 'correo_fallo', 'sin_telefono'] as $k) {
	ok(isset(at_pt_mensajes_panel()[$k]), "aviso del panel para «{$k}»");
}

fin();
