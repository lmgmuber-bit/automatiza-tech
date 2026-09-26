<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/ficha-checkboxes-envio-wp-test.php
// T11 ronda 1 (26-sep), hallazgo 4 (propuestas-admin/ficha.php): con respuesta del cliente ('aceptada',
// 'evaluando' o 'rechazada') acciones.php (guarda de la Task 9) ya bloquea el reenvío por completo,
// pero at_propuesta_puede_enviarse() solo desmarca/deshabilita el checkbox "Enviar correo" cuando el
// flujo es v3; una propuesta clásica (flujo NULL o distinto de v3) seguía mostrando las dos casillas
// marcadas y habilitadas -dando a entender que sí se iba a reenviar el correo y el WhatsApp-, y el
// checkbox de WhatsApp nunca miraba el estado (solo si había teléfono).
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_pa_render_ficha')) {
	fwrite(STDERR, "at_pa_render_ficha() no existe: revisa propuestas-admin/ficha.php.\n");
	exit(2);
}

$marca = 'prueba-ficha-checkboxes-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];

function crear_propuesta_ficha_cb(string $marca, string $status, ?string $flujo, array &$creadas): object {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $status . '-' . (string) $flujo . '@example.com',
		'unique_link_id' => substr(md5($marca . $status . $flujo . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Ficha checkboxes', 'phone' => '+56 9 2222 2222',
		'status' => $status, 'flujo' => $flujo, 'gamma_prompt_text' => wp_json_encode(['pricing_rows' => []]),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	$creadas[] = $id;
	return at_cc_propuesta_por_id($id);
}

function render_ficha_cb(object $p): string {
	ob_start();
	at_pa_render_ficha($p, '');
	return (string) ob_get_clean();
}

function checkbox_html(string $html, string $name): string {
	return preg_match('/<input type="checkbox" name="' . preg_quote($name, '/') . '"[^>]*>/', $html, $m) ? $m[0] : '';
}

// ================= flujo NULL (propuesta clásica): las tres estados con respuesta del cliente ======
foreach (['aceptada', 'evaluando', 'rechazada'] as $estado) {
	$p = crear_propuesta_ficha_cb($marca, $estado, null, $creadas);
	$html = render_ficha_cb($p);
	$cb_correo = checkbox_html($html, 'send_email');
	$cb_wa = checkbox_html($html, 'at_cc_whatsapp');
	ok(strpos($cb_correo, 'disabled') !== false && strpos($cb_correo, 'checked') === false, "flujo clásico, {$estado}: send_email deshabilitado y sin marcar: {$cb_correo}");
	ok(strpos($cb_wa, 'disabled') !== false && strpos($cb_wa, 'checked') === false, "flujo clásico, {$estado}: at_cc_whatsapp deshabilitado y sin marcar (aunque hay teléfono): {$cb_wa}");
	ok(strpos($html, 'Esta propuesta ya tiene respuesta del cliente') !== false, "flujo clásico, {$estado}: aviso de «ya tiene respuesta» presente");
	ok(strpos($html, 'usa «Pedir respuesta»') !== false, "flujo clásico, {$estado}: el aviso remite a «Pedir respuesta»");
}

// ================= flujo v3: mismas tres estados (antes ya funcionaba para send_email por at_propuesta_puede_enviarse(), pero at_cc_whatsapp no) ======
foreach (['aceptada', 'evaluando', 'rechazada'] as $estado) {
	$p = crear_propuesta_ficha_cb($marca, $estado, 'v3', $creadas);
	$html = render_ficha_cb($p);
	$cb_correo = checkbox_html($html, 'send_email');
	$cb_wa = checkbox_html($html, 'at_cc_whatsapp');
	ok(strpos($cb_correo, 'disabled') !== false && strpos($cb_correo, 'checked') === false, "flujo v3, {$estado}: send_email deshabilitado y sin marcar: {$cb_correo}");
	ok(strpos($cb_wa, 'disabled') !== false && strpos($cb_wa, 'checked') === false, "flujo v3, {$estado}: at_cc_whatsapp deshabilitado y sin marcar: {$cb_wa}");
	ok(strpos($html, 'Esta propuesta ya tiene respuesta del cliente') !== false, "flujo v3, {$estado}: aviso de «ya tiene respuesta» presente");
	// v3 fuera de 'lista'/'sent' también dispara el aviso viejo ("Se habilita cuando..."); con el
	// nuevo aviso de "ya tiene respuesta" no deben mostrarse los dos a la vez.
	ok(strpos($html, 'Se habilita cuando la propuesta esté') === false, "flujo v3, {$estado}: no se muestra a la vez el aviso de «se habilita cuando esté lista»");
}

// ================= control: sin respuesta del cliente, las casillas siguen como antes =============
$p_sent = crear_propuesta_ficha_cb($marca, 'sent', null, $creadas);
$html_sent = render_ficha_cb($p_sent);
$cb_correo_sent = checkbox_html($html_sent, 'send_email');
$cb_wa_sent = checkbox_html($html_sent, 'at_cc_whatsapp');
ok(strpos($cb_correo_sent, 'checked') !== false && strpos($cb_correo_sent, 'disabled') === false, 'flujo clásico, sent (sin respuesta): send_email sigue marcado y habilitado: ' . $cb_correo_sent);
ok(strpos($cb_wa_sent, 'checked') !== false && strpos($cb_wa_sent, 'disabled') === false, 'flujo clásico, sent (sin respuesta, con teléfono): at_cc_whatsapp sigue marcado y habilitado: ' . $cb_wa_sent);
ok(strpos($html_sent, 'Esta propuesta ya tiene respuesta del cliente') === false, 'sent: no muestra el aviso de «ya tiene respuesta»');

$p_v3_lista = crear_propuesta_ficha_cb($marca, 'lista', 'v3', $creadas);
$html_v3_lista = render_ficha_cb($p_v3_lista);
$cb_correo_v3 = checkbox_html($html_v3_lista, 'send_email');
ok(strpos($cb_correo_v3, 'disabled') === false, 'flujo v3, lista (sin respuesta): send_email sigue habilitado (aunque sin "checked", como antes): ' . $cb_correo_v3);
ok(strpos($html_v3_lista, 'Esta propuesta ya tiene respuesta del cliente') === false, 'v3 lista: no muestra el aviso de «ya tiene respuesta»');

// Limpieza
if ($creadas) {
	$ids = implode(',', array_map('intval', $creadas));
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
}

fin();
