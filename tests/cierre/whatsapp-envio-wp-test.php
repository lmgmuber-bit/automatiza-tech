<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/whatsapp-envio-wp-test.php
// Task 9: el correo de la propuesta pide aceptarla (at_cc_bloque_aceptar_html) y, tras el envío,
// at_cc_tras_envio()/at_cc_whatsapp_tras_pedido() ofrecen o mandan el WhatsApp.
// Ajuste del controlador (obligatorio): estas notas NUNCA deben quedar con el tipo 'propuesta_enviada',
// porque render_public_prospect_timeline() (crm-ai-completo.php) solo agrega la tarjeta automática
// «Propuesta Creada» con el PDF cuando la propuesta no tiene ninguna nota de ese tipo. El correo se
// anota como 'email' (público, existe en get_detail_types()) y el WhatsApp como 'pedido_respuesta'
// (tipo interno, at_cc_tipos_internos()).
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

foreach (['at_cc_tras_envio', 'at_cc_whatsapp_tras_pedido', 'at_cc_whatsapp_plantilla_activa', 'at_cc_boton_wa_me'] as $fn) {
	if (!function_exists($fn)) {
		fwrite(STDERR, "$fn() no está definida: revisa whatsapp.php.\n");
		exit(2);
	}
}

// Sin opción ni constante configuradas (el controlador las agrega en la Task 10): la plantilla nunca
// está activa en este entorno de prueba.
ok(at_cc_whatsapp_plantilla_activa() === false, 'at_cc_whatsapp_plantilla_activa: falsa sin la opción ni la constante de n8n');

function at_cc_test_crear_propuesta(string $marca, string $phone = ''): object {
	global $wpdb;
	$email = $marca . '@example.com';
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'       => $email,
		'unique_link_id'     => substr(md5($marca . microtime(true)), 0, 12),
		'client_name'        => 'Cliente Prueba',
		'company_name'       => '[PRUEBA] WhatsApp envío',
		'phone'              => $phone,
		'status'             => 'sent',
		'flujo'              => 'v3',
		'gamma_prompt_text'  => '{}',
		'gamma_iframe_url'   => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $marca . '/index.html',
		'transcript_text'    => '',
		'system_prompt_text' => '',
		'created_at'         => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id($id);
}

function at_cc_test_tipos_de($id): array {
	global $wpdb;
	return $wpdb->get_col($wpdb->prepare(
		"SELECT detail_type FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d",
		$id
	));
}

function at_cc_test_borrar($id): void {
	global $wpdb;
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
}

// --- 1) Sin pedir WhatsApp: solo la nota 'email', nunca 'propuesta_enviada' ---
$marca1 = 'prueba-wa-envio-1-' . strtolower(wp_generate_password(6, false, false));
$p1 = at_cc_test_crear_propuesta($marca1, '');
$aviso1 = at_cc_tras_envio($p1, false);
ok($aviso1 === '', "at_cc_tras_envio sin pedir WhatsApp no devuelve aviso: '$aviso1'");
$tipos1 = at_cc_test_tipos_de($p1->id);
ok(in_array('email', $tipos1, true), 'at_cc_tras_envio anota el correo con el tipo público «email»');
ok(!in_array('propuesta_enviada', $tipos1, true), 'at_cc_tras_envio NUNCA usa el tipo «propuesta_enviada» (taparía la tarjeta automática)');

// --- 2) Pidiendo WhatsApp sin teléfono: aviso de advertencia, sin nota de WhatsApp ---
$marca2 = 'prueba-wa-envio-2-' . strtolower(wp_generate_password(6, false, false));
$p2 = at_cc_test_crear_propuesta($marca2, '');
$aviso2 = at_cc_tras_envio($p2, true);
ok(strpos($aviso2, 'No se mandó WhatsApp') !== false, 'sin teléfono: avisa que no se mandó WhatsApp');
$tipos2 = at_cc_test_tipos_de($p2->id);
ok(in_array('email', $tipos2, true), 'sin teléfono: igual queda la nota «email»');
ok(!in_array('pedido_respuesta', $tipos2, true), 'sin teléfono: no se anota ningún intento de WhatsApp');

// --- 3) Pidiendo WhatsApp con teléfono, plantilla inactiva: ofrece el botón wa.me, sin nota nueva ---
$marca3 = 'prueba-wa-envio-3-' . strtolower(wp_generate_password(6, false, false));
$p3 = at_cc_test_crear_propuesta($marca3, '+56 9 2700 2984');
$aviso3 = at_cc_tras_envio($p3, true);
ok(strpos($aviso3, 'Falta el WhatsApp') !== false && strpos($aviso3, 'wa.me/56927002984') !== false, 'con teléfono y sin plantilla: ofrece el botón de wa.me con el mensaje ya escrito');
$tipos3 = at_cc_test_tipos_de($p3->id);
ok(!in_array('pedido_respuesta', $tipos3, true), 'ofrecer el botón (no enviar) no anota «pedido_respuesta»');
ok(!in_array('propuesta_enviada', $tipos3, true), 'tampoco usa «propuesta_enviada» en este camino');

// --- 4) at_cc_whatsapp_tras_pedido (usado por «Pedir respuesta») ---
$detalles_sin_tel = at_cc_whatsapp_tras_pedido($p1);
ok($detalles_sin_tel === ['Sin teléfono: no se mandó WhatsApp.'], 'at_cc_whatsapp_tras_pedido sin teléfono');
$detalles_con_tel = at_cc_whatsapp_tras_pedido($p3);
ok(strpos($detalles_con_tel[0] ?? '', 'Enviar por mi WhatsApp') !== false, 'at_cc_whatsapp_tras_pedido con teléfono y sin plantilla remite al botón manual');
ok(!in_array('pedido_respuesta', at_cc_test_tipos_de($p3->id), true), 'at_cc_whatsapp_tras_pedido sin plantilla no anota nada nuevo');

// --- 5) La vista pública del prospecto sigue mostrando «Propuesta Creada» después de at_cc_tras_envio()
// (reutiliza el patrón de proceso hijo de panel-timeline-wp-test.php: render_public_prospect_timeline()
// siempre termina con exit;, así que se genera en un proceso PHP aparte). ---
$hijo = __DIR__ . '/panel-timeline-wp-test-render.php';
$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open([PHP_BINARY, $hijo, (string) $p1->id, $p1->client_email], $descriptores, $pipes);
if (!is_resource($proc)) {
	fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
	exit(2);
} else {
	$html = stream_get_contents($pipes[1]);
	$errores_hijo = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	ok(strpos($html, 'Propuesta Creada') !== false, 'la vista pública del prospecto sigue mostrando «Propuesta Creada» aunque ya exista la nota «email»: ' . trim($errores_hijo));
}

at_cc_test_borrar($p1->id);
at_cc_test_borrar($p2->id);
at_cc_test_borrar($p3->id);

fin();
