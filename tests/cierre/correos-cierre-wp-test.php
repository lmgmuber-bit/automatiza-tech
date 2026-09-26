<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/correos-cierre-wp-test.php
// Task 17 (pedida por Luis el 26-sep): correo principal del cierre (at_cc_correo_avisos) y copia
// oculta (at_cc_correo_copia) en los avisos a Luis, la bienvenida y «Pedir respuesta». Con las dos
// opciones vacías, todo se comporta exactamente como antes (aviso a admin_email, sin Bcc extra).
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

foreach (['at_cc_correo_avisos', 'at_cc_correo_copia', 'at_cc_cabecera_copia', 'at_cc_avisar_luis', 'at_cc_enviar_bienvenida', 'at_cc_enviar_pedido_respuesta', 'at_cc_asegurar_cliente'] as $fn) {
	if (!function_exists($fn)) {
		fwrite(STDERR, "$fn() no está definida: revisa ajustes.php/respuesta.php/bienvenida.php.\n");
		exit(2);
	}
}

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);

$tabla_prop = $wpdb->prefix . 'automatiza_propuestas';
$tabla_det = $wpdb->prefix . 'automatiza_propuestas_details';
$tabla_crm = $wpdb->prefix . 'crm_clientes';
$tabla_hist = $wpdb->prefix . 'crm_historial';
$tabla_tech = $wpdb->prefix . 'automatiza_tech_clients';

$marca = 'prueba-correos-cierre-' . strtolower(wp_generate_password(6, false, false));
$creadas_propuestas = [];
$creados_crm = [];

function cc_test_crear_propuesta(string $marca, string $sufijo): object {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'       => $marca . '-' . $sufijo . '@example.com',
		'unique_link_id'     => substr(md5($marca . $sufijo . microtime(true)), 0, 12),
		'client_name'        => 'Cliente Prueba',
		'company_name'       => '[PRUEBA] Correos del cierre',
		'phone'              => '',
		'status'             => 'sent',
		'flujo'              => 'v3',
		'gamma_prompt_text'  => '{}',
		'transcript_text'    => '',
		'system_prompt_text' => '',
		'created_at'         => current_time('mysql'),
	]);
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}

/** Solo las cabeceras 'Bcc: ...' de un correo capturado, sin el prefijo. */
function cc_test_bcc(array $headers): array {
	$r = [];
	foreach ($headers as $h) {
		if (stripos((string) $h, 'Bcc:') === 0) {
			$r[] = trim(substr((string) $h, 4));
		}
	}
	return $r;
}

/** La cabecera 'Reply-To: ...' de un correo capturado; '' si no trae ninguna. */
function cc_test_reply_to(array $headers): string {
	foreach ($headers as $h) {
		if (stripos((string) $h, 'Reply-To:') === 0) {
			return trim(substr((string) $h, 9));
		}
	}
	return '';
}

$r_base = ['avisos' => [], 'avisos_operativos' => [], 'contrato_id' => null];

// Se restauran al final: la base local la comparte otro sitio de prueba.
$avisos_original = get_option('at_cc_correo_avisos', '');
$copia_original = get_option('at_cc_correo_copia', '');

// ---------------------------------------------------------------------------------------------
// (a) Opciones vacías: todo se comporta exactamente como hoy.
// ---------------------------------------------------------------------------------------------
update_option('at_cc_correo_avisos', '');
update_option('at_cc_correo_copia', '');

ok(at_cc_correo_avisos() === (string) get_option('admin_email'), 'a) at_cc_correo_avisos() vacío cae al correo de administrador');
ok(at_cc_correo_copia() === '', 'a) at_cc_correo_copia() vacío es ""');
ok(at_cc_cabecera_copia('cualquiera@example.com') === [], 'a) sin copia configurada: sin cabecera Bcc');

$pa = cc_test_crear_propuesta($marca, 'a');
$creadas_propuestas[] = $pa->id;

$correos = [];
at_cc_avisar_luis($pa, 'evalua', ['canal' => 'pagina', 'comentario' => 'Duda de prueba a'], $r_base);
ok(count($correos) === 1, 'a) aviso a Luis: se manda un correo');
ok(($correos[0]['to'] ?? null) === (string) get_option('admin_email'), 'a) aviso a Luis: destinatario es admin_email, igual que antes');
ok(cc_test_bcc($correos[0]['headers']) === [], 'a) aviso a Luis: sin Bcc extra');

$correos = [];
ok(at_cc_enviar_pedido_respuesta($pa) === true, 'a) pedir respuesta: el correo sale');
ok(count($correos) === 1, 'a) pedir respuesta: un correo');
ok(cc_test_reply_to($correos[0]['headers']) === (string) get_option('admin_email'), 'a) pedir respuesta: Reply-To es admin_email, igual que antes');
ok(cc_test_bcc($correos[0]['headers']) === ['automatizacionesbotcore@gmail.com'], 'a) pedir respuesta: solo su Bcc de siempre');

$cli_a = at_cc_asegurar_cliente(['nombre' => 'Cliente Prueba A', 'email' => $marca . '-cliente-a@example.com', 'empresa' => '[PRUEBA] Correos del cierre', 'telefono' => '']);
ok(is_array($cli_a) && (int) $cli_a['crm_id'] > 0, 'a) cliente de prueba creado');
$creados_crm[] = (int) $cli_a['crm_id'];
$correos = [];
ok(at_cc_enviar_bienvenida((int) $cli_a['crm_id']) === true, 'a) bienvenida: el correo sale');
ok(count($correos) === 1, 'a) bienvenida: un correo');
ok(cc_test_reply_to($correos[0]['headers']) === (string) get_option('admin_email'), 'a) bienvenida: Reply-To es admin_email, igual que antes');
ok(cc_test_bcc($correos[0]['headers']) === ['lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl'], 'a) bienvenida: solo su Bcc de siempre');

// ---------------------------------------------------------------------------------------------
// (b) Principal y copia configurados, y distintos entre sí.
// ---------------------------------------------------------------------------------------------
update_option('at_cc_correo_avisos', 'avisos-prueba@example.com');
update_option('at_cc_correo_copia', 'copia-prueba@example.com');

ok(at_cc_correo_avisos() === 'avisos-prueba@example.com', 'b) at_cc_correo_avisos() usa la opción configurada');
ok(at_cc_correo_copia() === 'copia-prueba@example.com', 'b) at_cc_correo_copia() usa la opción configurada');
ok(at_cc_cabecera_copia('avisos-prueba@example.com') === ['Bcc: copia-prueba@example.com'], 'b) cabecera de copia cuando difiere del destinatario');

$pb = cc_test_crear_propuesta($marca, 'b');
$creadas_propuestas[] = $pb->id;

$correos = [];
at_cc_avisar_luis($pb, 'evalua', ['canal' => 'pagina', 'comentario' => 'Duda de prueba b'], $r_base);
ok(count($correos) === 1 && ($correos[0]['to'] ?? null) === 'avisos-prueba@example.com', 'b) aviso a Luis: destinatario es el correo principal configurado');
ok(cc_test_bcc($correos[0]['headers']) === ['copia-prueba@example.com'], 'b) aviso a Luis: lleva la copia oculta');

$correos = [];
ok(at_cc_enviar_pedido_respuesta($pb) === true, 'b) pedir respuesta: el correo sale');
ok(count($correos) === 1, 'b) pedir respuesta: un correo');
ok(cc_test_reply_to($correos[0]['headers']) === 'avisos-prueba@example.com', 'b) pedir respuesta: Reply-To es el correo principal configurado');
ok(cc_test_bcc($correos[0]['headers']) === ['automatizacionesbotcore@gmail.com', 'copia-prueba@example.com'], 'b) pedir respuesta: su Bcc de siempre + la copia oculta');

$cli_b = at_cc_asegurar_cliente(['nombre' => 'Cliente Prueba B', 'email' => $marca . '-cliente-b@example.com', 'empresa' => '[PRUEBA] Correos del cierre', 'telefono' => '']);
$creados_crm[] = (int) $cli_b['crm_id'];
$correos = [];
ok(at_cc_enviar_bienvenida((int) $cli_b['crm_id']) === true, 'b) bienvenida: el correo sale');
ok(count($correos) === 1, 'b) bienvenida: un correo');
ok(cc_test_reply_to($correos[0]['headers']) === 'avisos-prueba@example.com', 'b) bienvenida: Reply-To es el correo principal configurado');
ok(cc_test_bcc($correos[0]['headers']) === ['lgonzalez@automatizatech.cl, adriana.perez@automatizatech.cl', 'copia-prueba@example.com'], 'b) bienvenida: su Bcc de siempre + la copia oculta');

// ---------------------------------------------------------------------------------------------
// (c) Copia igual al principal (sin distinguir mayúsculas): el aviso no lleva Bcc de copia.
// ---------------------------------------------------------------------------------------------
update_option('at_cc_correo_avisos', 'avisos-prueba@example.com');
update_option('at_cc_correo_copia', 'AVISOS-PRUEBA@example.com');

ok(at_cc_cabecera_copia('avisos-prueba@example.com') === [], 'c) copia igual al principal (sin distinguir mayúsculas): sin cabecera Bcc');

$pc = cc_test_crear_propuesta($marca, 'c');
$creadas_propuestas[] = $pc->id;
$correos = [];
at_cc_avisar_luis($pc, 'evalua', ['canal' => 'pagina', 'comentario' => 'Duda de prueba c'], $r_base);
ok(count($correos) === 1 && ($correos[0]['to'] ?? null) === 'avisos-prueba@example.com', 'c) aviso a Luis: destinatario correcto');
ok(cc_test_bcc($correos[0]['headers']) === [], 'c) copia igual al principal: el aviso no lleva Bcc de copia');

// ---------------------------------------------------------------------------------------------
// (d) Valor inválido en el correo principal: cae a admin_email.
// ---------------------------------------------------------------------------------------------
update_option('at_cc_correo_avisos', 'no-es-un-correo');
update_option('at_cc_correo_copia', '');

ok(at_cc_correo_avisos() === (string) get_option('admin_email'), 'd) valor inválido en el principal: at_cc_correo_avisos() cae a admin_email');

$pd = cc_test_crear_propuesta($marca, 'd');
$creadas_propuestas[] = $pd->id;
$correos = [];
at_cc_avisar_luis($pd, 'evalua', ['canal' => 'pagina', 'comentario' => 'Duda de prueba d'], $r_base);
ok(count($correos) === 1 && ($correos[0]['to'] ?? null) === (string) get_option('admin_email'), 'd) aviso a Luis con principal inválido: destinatario cae a admin_email');

// ---------------------------------------------------------------------------------------------
// Limpieza: opciones y datos de prueba.
// ---------------------------------------------------------------------------------------------
update_option('at_cc_correo_avisos', $avisos_original);
update_option('at_cc_correo_copia', $copia_original);

foreach ($creadas_propuestas as $id) {
	$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_det} WHERE propuesta_id = %d", $id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_prop} WHERE id = %d", $id));
}
if ($creados_crm) {
	$in = implode(',', array_fill(0, count($creados_crm), '%d'));
	$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_hist} WHERE cliente_id IN ({$in})", ...$creados_crm));
	$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_tech} WHERE crm_cliente_id IN ({$in})", ...$creados_crm));
	$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_crm} WHERE id IN ({$in})", ...$creados_crm));
}

fin();
