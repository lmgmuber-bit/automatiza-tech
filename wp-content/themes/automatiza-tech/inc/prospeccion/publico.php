<?php
/**
 * Rastro · Prospección: enlace personal (?r=<token>), paso a «llenó formulario» y baja pública.
 * No toca el formulario de agendar: la cookie del enlace se cruza con el lead que el sitio ya guarda.
 */
if (!defined('ABSPATH')) { exit; }

const AT_PRO_COOKIE = 'at_pro_r';
const AT_PRO_VENTANA_LEAD = 1800; // segundos: el lead debe haberse creado hace menos de 30 minutos

/** Qué hacer con un ?r= : cookie con el token y a dónde redirigir quitándolo de la URL. Puro respecto de cabeceras. */
function at_pro_procesar_ref(array $get): array {
	$vacio = ['cookie' => '', 'redirect' => ''];
	$r = isset($get['r']) && is_string($get['r']) ? $get['r'] : '';
	if (!at_pro_token_valido($r)) { return $vacio; }
	$p = at_pro_por_token($r);
	if (!$p || $p['estado'] === 'baja') { return $vacio; }
	$resto = $get;
	unset($resto['r']);
	$destino = $resto ? add_query_arg(array_map('strval', $resto), home_url('/')) : home_url('/');
	return ['cookie' => $r, 'redirect' => $destino];
}

add_action('template_redirect', static function () {
	if (is_admin() || !isset($_GET['r'])) { return; }
	$r = at_pro_procesar_ref(wp_unslash($_GET));
	if ($r['cookie'] === '') { return; }
	setcookie(AT_PRO_COOKIE, $r['cookie'], ['expires' => time() + 30 * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
	wp_safe_redirect($r['redirect'], 302);
	exit;
}, 1);

/** Datos de la persona si el sitio guardó un lead (agenda o formulario de contacto) con ese correo hace menos de 30 minutos. */
function at_pro_lead_reciente(string $email): ?array {
	global $wpdb;
	$email = strtolower(trim($email));
	if (!is_email($email)) { return null; }
	$limite = (new DateTime('now', wp_timezone()))->modify('-' . AT_PRO_VENTANA_LEAD . ' seconds')->format('Y-m-d H:i:s');
	$l = $wpdb->get_row($wpdb->prepare("SELECT name, email, phone FROM {$wpdb->prefix}automatiza_leads WHERE email = %s AND created_at >= %s ORDER BY id DESC LIMIT 1", $email, $limite), ARRAY_A);
	if ($l) { return ['nombre' => (string) $l['name'], 'email' => $email, 'telefono' => (string) $l['phone']]; }
	$pid = $wpdb->get_var($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_at_email' WHERE p.post_type = 'at_demo_request' AND m.meta_value = %s AND p.post_date >= %s ORDER BY p.ID DESC LIMIT 1", $email, $limite));
	if ($pid) { return ['nombre' => (string) get_post_meta((int) $pid, '_at_name', true), 'email' => $email, 'telefono' => (string) get_post_meta((int) $pid, '_at_phone', true)]; }
	return null;
}

/**
 * Pasa el prospecto del token a «llenó formulario» si ese correo acaba de agendar o escribir.
 * Una sola vez por prospecto: el cambio de estado es atómico (UPDATE ... WHERE estado = anterior).
 */
function at_pro_marcar_formulario(string $token, string $email, string $origen): bool {
	$p = at_pro_por_token($token);
	if (!$p || !at_pro_transicion_valida($p['estado'], 'formulario')) { return false; }
	$persona = at_pro_lead_reciente($email);
	if (!$persona) { return false; }
	if (!at_pro_cambiar_estado((int) $p['id'], 'formulario')) { return false; }
	$persona['origen'] = sanitize_key($origen);
	do_action('at_pro_llenado', at_pro_obtener((int) $p['id']) ?: $p, $persona);
	return true;
}

/** Límite del AJAX público: 10 llamadas por hora por IP. true = puede seguir. */
function at_pro_limite_ip(string $ip): bool {
	$k = 'at_pro_rl_' . md5($ip);
	$n = (int) get_transient($k);
	if ($n >= 10) { return false; }
	set_transient($k, $n + 1, HOUR_IN_SECONDS);
	return true;
}

/**
 * AJAX público. Sin nonce a propósito: la página puede venir de la caché del sitio y un nonce viejo rompería el paso.
 * Lo protege lo que sí importa: el token sale de la cookie HttpOnly (nunca del cuerpo), el lead con ese correo tiene que
 * existir en los últimos 30 minutos, y hay límite por IP.
 */
function at_pro_ajax_formulario(): void {
	$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
	$token = isset($_COOKIE[AT_PRO_COOKIE]) ? sanitize_text_field(wp_unslash((string) $_COOKIE[AT_PRO_COOKIE])) : '';
	if ($token !== '' && at_pro_limite_ip($ip)) {
		at_pro_marcar_formulario($token, sanitize_email(wp_unslash((string) ($_POST['email'] ?? ''))), 'agenda');
	}
	wp_send_json_success(); // misma respuesta siempre: no revela si el token existe
}
add_action('wp_ajax_nopriv_at_pro_formulario', 'at_pro_ajax_formulario');
add_action('wp_ajax_at_pro_formulario', 'at_pro_ajax_formulario');

/** Respaldo: el formulario de contacto guarda un at_demo_request; si trae la cookie, se marca igual. */
add_action('save_post_at_demo_request', static function ($post_id, $post, $update) {
	if ($update || empty($_COOKIE[AT_PRO_COOKIE])) { return; }
	$token = sanitize_text_field(wp_unslash((string) $_COOKIE[AT_PRO_COOKIE]));
	at_pro_marcar_formulario($token, (string) get_post_meta((int) $post_id, '_at_email', true), 'contact_form');
}, 20, 3);

/** El script va siempre (es chico): si dependiera de la cookie, la caché de página lo dejaría fuera. */
add_action('wp_enqueue_scripts', static function () {
	$ruta = get_template_directory() . '/assets/js/prospeccion-ref.js';
	if (!is_file($ruta)) { return; }
	wp_enqueue_script('at-prospeccion-ref', get_template_directory_uri() . '/assets/js/prospeccion-ref.js', [], (string) filemtime($ruta), true);
	wp_add_inline_script('at-prospeccion-ref', 'window.AtProRef=' . wp_json_encode(['ajax' => admin_url('admin-ajax.php')]) . ';', 'before');
});

/** Página mínima, sin el tema, sin índice de buscadores. */
function at_pro_baja_html(string $titulo, string $cuerpo): string {
	$icono = '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0e7c86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-6"/></svg>';
	return '<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html($titulo) . ' · AutomatizaTech</title>'
		. '<style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:#f4f7f9;color:#12263a;display:grid;place-items:center;min-height:100vh;padding:16px;box-sizing:border-box}main{background:#fff;border-radius:12px;padding:28px;max-width:440px;box-shadow:0 2px 14px rgba(0,0,0,.08);text-align:center}h1{font-size:1.3rem;margin:.6rem 0}p{line-height:1.5}button{background:#12263a;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-size:1rem;cursor:pointer;display:inline-flex;gap:8px;align-items:center}</style></head><body><main>'
		. $icono . '<h1>' . esc_html($titulo) . '</h1>' . $cuerpo . '</main></body></html>';
}

/**
 * Página de baja. Un GET nunca cambia nada: solo muestra el botón; la baja la da el POST con nonce.
 * Con un token inexistente responde igual que con uno válido, sin revelar si existe.
 */
function at_pro_baja_pagina(string $token, bool $es_post, string $nonce): array {
	$token = strlen($token) <= 64 ? $token : '';
	$p = at_pro_token_valido($token) ? at_pro_por_token($token) : null;
	$hecho = at_pro_baja_html('Listo, no te volveremos a escribir', '<p>Quitamos tu contacto de nuestra lista. Si fue un error, puedes escribirnos a contacto@automatizatech.cl.</p>');
	if ($es_post) {
		if ($p && wp_verify_nonce($nonce, 'at_pro_baja_' . $token)) { at_pro_dar_baja($token); return ['status' => 200, 'html' => $hecho]; }
		if (!$p) { return ['status' => 200, 'html' => $hecho]; }
		return ['status' => 200, 'html' => at_pro_baja_html('Confirma tu baja', '<p>No pudimos validar la solicitud. Vuelve a abrir el enlace del mensaje y confirma de nuevo.</p>')];
	}
	if ($p && $p['estado'] === 'baja') { return ['status' => 200, 'html' => $hecho]; }
	$form = '<p>Si no quieres recibir más mensajes de AutomatizaTech, confirma aquí.</p><form method="post" action="' . esc_url(add_query_arg('baja', rawurlencode($token), home_url('/'))) . '">'
		. '<input type="hidden" name="at_pro_nonce" value="' . esc_attr(wp_create_nonce('at_pro_baja_' . $token)) . '"><button type="submit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg> Confirmar baja</button></form>';
	return ['status' => 200, 'html' => at_pro_baja_html('Darte de baja', $form)];
}

add_action('template_redirect', static function () {
	if (is_admin() || !isset($_GET['baja'])) { return; }
	$token = is_string($_GET['baja']) ? sanitize_text_field(wp_unslash($_GET['baja'])) : '';
	$es_post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
	$r = at_pro_baja_pagina($token, $es_post, $es_post ? (string) wp_unslash($_POST['at_pro_nonce'] ?? '') : '');
	nocache_headers();
	do_action('litespeed_control_set_nocache', 'rastro baja');
	status_header($r['status']);
	header('Content-Type: text/html; charset=utf-8');
	header('X-Robots-Tag: noindex, nofollow');
	echo $r['html']; // HTML armado arriba con esc_html/esc_attr
	exit;
}, 1);
