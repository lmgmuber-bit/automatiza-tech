<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/correo-envio-propuesta-wp-test.php
// Pedido de Luis (26-sep, tras la Task 17): el correo con que se ENVÍA la propuesta (ficha nueva,
// acciones.php, y página clásica, clasico.php) sigue la misma regla que los correos del cierre:
// Reply-To = correo principal del cierre (at_cc_correo_avisos(), cae a admin_email si está vacío) y
// Bcc = la copia de registro de siempre + la copia oculta de «Ajustes del cierre» (si hay una y no es
// el mismo destinatario). Con las dos opciones vacías, las cabeceras quedan exactamente como antes.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

foreach (['at_pa_guardar', 'automatiza_tech_proposals_page_clasico', 'at_cc_correo_avisos', 'at_cc_cabecera_copia', 'at_cc_propuesta_por_id'] as $fn) {
	if (!function_exists($fn)) {
		fwrite(STDERR, "$fn() no está definida: revisa propuestas-admin/ y cierre-cliente/.\n");
		exit(2);
	}
}

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

$tabla = $wpdb->prefix . 'automatiza_propuestas';
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$marca = 'prueba-envio-correo-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];
const EP_BOT = 'automatizacionesbotcore@gmail.com';

function ep_crear(string $marca, string $sufijo, array &$creadas): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $sufijo . '@example.com',
		'unique_link_id' => substr(md5($marca . $sufijo . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Envío', 'phone' => '',
		'status' => 'pending', 'flujo' => null, 'gamma_prompt_text' => '{}',
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = $id;
	return $id;
}

/** El POST de «Guardar» con la casilla de correo marcada, igual que la ficha y la página clásica. */
function ep_post(int $id, string $email): void {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_GET = [];
	$_POST = [
		'automatiza_proposal_nonce' => wp_create_nonce('save_proposal'),
		'proposal_id' => (string) $id, 'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Envío',
		'phone' => '', 'client_email' => $email,
		'email_subject' => '', 'email_intro' => '', 'email_highlight' => '', 'email_closing' => '',
		'gamma_url' => '', 'n8n_url' => '', 'send_email' => '1',
	];
	$_FILES = [];
}

function ep_nueva(int $id, string $email): void {
	ep_post($id, $email);
	at_pa_guardar();
	$_POST = [];
}

function ep_clasica(int $id, string $email): void {
	ep_post($id, $email);
	ob_start();
	automatiza_tech_proposals_page_clasico();
	ob_end_clean();
	$_POST = [];
}

function ep_bcc(array $headers): array {
	$r = [];
	foreach ((array) $headers as $h) {
		if (stripos((string) $h, 'Bcc:') === 0) {
			$r[] = trim(substr((string) $h, 4));
		}
	}
	return $r;
}

function ep_reply_to(array $headers): string {
	foreach ((array) $headers as $h) {
		if (stripos((string) $h, 'Reply-To:') === 0) {
			return trim(substr((string) $h, 9));
		}
	}
	return '';
}

// Se restauran al final: la base local la comparte otro sitio de prueba.
$avisos_original = get_option('at_cc_correo_avisos', '');
$copia_original = get_option('at_cc_correo_copia', '');
wp_set_current_user((int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0));

foreach (['nueva' => 'ep_nueva', 'clásica' => 'ep_clasica'] as $pagina => $enviar) {
	$slug = $pagina === 'nueva' ? 'nueva' : 'clasica'; // sanitize_email() quita la «á»
	// (a) Opciones vacías: igual que antes (Reply-To admin_email, solo la copia de registro).
	update_option('at_cc_correo_avisos', '');
	update_option('at_cc_correo_copia', '');
	$id = ep_crear($marca, $slug . '-a', $creadas);
	$email = $marca . '-' . $slug . '-a@example.com';
	$correos = [];
	$enviar($id, $email);
	ok(count($correos) === 1, "{$pagina}, a) sale un correo (" . count($correos) . ')');
	ok(($correos[0]['to'] ?? '') === $email, "{$pagina}, a) al cliente");
	ok(ep_reply_to($correos[0]['headers'] ?? []) === (string) get_option('admin_email'), "{$pagina}, a) Reply-To es admin_email, igual que antes");
	ok(ep_bcc($correos[0]['headers'] ?? []) === [EP_BOT], "{$pagina}, a) solo la copia de registro de siempre");

	// (b) Con correo principal y copia oculta: Reply-To al principal y las dos copias.
	update_option('at_cc_correo_avisos', 'avisos-prueba@example.com');
	update_option('at_cc_correo_copia', 'copia-prueba@example.com');
	$id = ep_crear($marca, $slug . '-b', $creadas);
	$email = $marca . '-' . $slug . '-b@example.com';
	$correos = [];
	$enviar($id, $email);
	ok(count($correos) === 1, "{$pagina}, b) sale un correo (" . count($correos) . ')');
	ok(ep_reply_to($correos[0]['headers'] ?? []) === 'avisos-prueba@example.com', "{$pagina}, b) Reply-To es el correo principal del cierre");
	ok(ep_bcc($correos[0]['headers'] ?? []) === [EP_BOT, 'copia-prueba@example.com'], "{$pagina}, b) la copia de registro + la copia oculta");

	// (c) La copia oculta es el mismo cliente: no se duplica el correo.
	$id = ep_crear($marca, $slug . '-c', $creadas);
	$email = $marca . '-' . $slug . '-c@example.com';
	update_option('at_cc_correo_copia', strtoupper($email));
	$correos = [];
	$enviar($id, $email);
	ok(ep_bcc($correos[0]['headers'] ?? []) === [EP_BOT], "{$pagina}, c) copia igual al destinatario: sin Bcc repetido");
}

// Limpieza
update_option('at_cc_correo_avisos', $avisos_original);
update_option('at_cc_correo_copia', $copia_original);
if ($creadas) {
	$ids = implode(',', array_map('intval', $creadas));
	$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
	$wpdb->query("DELETE FROM {$tabla} WHERE id IN ({$ids})");
}
$_SERVER['REQUEST_METHOD'] = 'GET';

fin();
