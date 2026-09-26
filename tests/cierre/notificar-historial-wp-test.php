<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/notificar-historial-wp-test.php
//
// T16 ronda 1 (revisión), hallazgo 2: el botón «📧 Enviar ahora» del historial de un cliente llama a
// crm_enviar_notificacion_historial(), que solo comprobaba el nonce: cualquier usuario logueado con ese
// nonce podía dispararla, y no comprobaba que el historial_id recibido perteneciera al cliente_id
// recibido (el botón ahora también puede aparecer, por error, sobre filas de otra tabla con un id que por
// casualidad exista en wp_crm_historial pero sea de otro cliente). Esta prueba llama al manejador AJAX
// real, sin generar la ficha del admin completa (miles de líneas con notas, contratos y otros
// controles): comprueba que ahora exige manage_options y que rechaza un historial_id que no es del
// cliente_id recibido, sin mandar el correo ni marcar esa fila como notificada.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;

$correos = [];
add_filter('pre_wp_mail', function ($nulo, $atts) use (&$correos) { $correos[] = $atts; return true; }, 10, 2);
$marca = 'prueba-notif-hist-' . strtolower(wp_generate_password(6, false, false));
$tabla_historial = $wpdb->prefix . 'crm_historial';
$tabla_crm = $wpdb->prefix . 'crm_clientes';
$tabla_tech = $wpdb->prefix . 'automatiza_tech_clients';

$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$admin_id = (int) ($admins[0] ?? 0);
if ($admin_id <= 0) {
	fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
	exit(2);
}
$editor_id = wp_insert_user(['user_login' => $marca . '-editor', 'user_email' => $marca . '-editor@example.com', 'user_pass' => wp_generate_password(24), 'role' => 'editor']);
if (is_wp_error($editor_id)) {
	fwrite(STDERR, 'No se pudo crear el usuario editor de prueba: ' . $editor_id->get_error_message() . "\n");
	exit(2);
}

/** Corre un handler AJAX real: wp_send_json() termina en wp_die(), que se vuelve una excepción. */
function nh_ajax(callable $fn): string {
	$lanzar = function () { return function () { throw new RuntimeException('wp_die'); }; };
	add_filter('wp_doing_ajax', '__return_true');
	add_filter('wp_die_ajax_handler', $lanzar);
	ob_start();
	try {
		$fn();
	} catch (RuntimeException $e) {
		// Fin normal del handler.
	}
	$out = (string) ob_get_clean();
	remove_filter('wp_die_ajax_handler', $lanzar);
	remove_filter('wp_doing_ajax', '__return_true');
	return $out;
}

$emailX = $marca . '-x@example.com';
$emailY = $marca . '-y@example.com';
$rx = at_cc_asegurar_cliente(['nombre' => 'Cliente X', 'email' => $emailX, 'empresa' => '[PRUEBA] Empresa', 'telefono' => '+56 9 2222 7777']);
$ry = at_cc_asegurar_cliente(['nombre' => 'Cliente Y', 'email' => $emailY, 'empresa' => '[PRUEBA] Empresa', 'telefono' => '+56 9 2222 8888']);
ok(is_array($rx) && $rx['crm_id'] > 0 && is_array($ry) && $ry['crm_id'] > 0, 'X e Y creados como clientes en el CRM');
$crmX = (int) $rx['crm_id'];
$crmY = (int) $ry['crm_id'];

$wpdb->insert($tabla_historial, [
	'cliente_id' => $crmX, 'tipo_evento' => 'proyecto_update', 'titulo' => 'Actualización de Proyecto: Prueba',
	'descripcion' => 'MARCA-NOTIF-X', 'created_at' => current_time('mysql'),
]);
$histX = (int) $wpdb->insert_id;

// ---------- sin manage_options: un editor no puede notificar, aunque el nonce sea válido ----------
wp_set_current_user((int) $editor_id);
$_POST = ['historial_id' => (string) $histX, 'cliente_id' => (string) $crmX, 'nonce' => wp_create_nonce('crm_nonce')];
$_REQUEST = $_POST;
$correos = [];
$out_editor = nh_ajax(function () { $GLOBALS['at_crm_ai']->crm_enviar_notificacion_historial(); });
$json_editor = json_decode($out_editor, true);
ok(is_array($json_editor) && $json_editor['success'] === false && ($json_editor['data'] ?? '') === 'No tienes permisos.', 'un editor sin manage_options no puede notificar: ' . substr($out_editor, 0, 150));
ok(count($correos) === 0, 'un editor sin permisos: no se manda ningún correo');
$meta_tras_editor = $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$tabla_historial} WHERE id = %d", $histX));
ok(empty($meta_tras_editor) || strpos((string) $meta_tras_editor, 'notificado') === false, 'un editor sin permisos: el historial de X sigue sin marca de notificado');

// ---------- cruce: historial de X con el cliente_id de Y ----------
wp_set_current_user($admin_id);
$_POST = ['historial_id' => (string) $histX, 'cliente_id' => (string) $crmY, 'nonce' => wp_create_nonce('crm_nonce')];
$_REQUEST = $_POST;
$correos = [];
$out_cruce = nh_ajax(function () { $GLOBALS['at_crm_ai']->crm_enviar_notificacion_historial(); });
$json_cruce = json_decode($out_cruce, true);
ok(is_array($json_cruce) && $json_cruce['success'] === false && ($json_cruce['data'] ?? '') === 'El historial no pertenece a este cliente.', 'admin con historial_id de X y cliente_id de Y: rechazado: ' . substr($out_cruce, 0, 150));
ok(count($correos) === 0, 'cruce X/Y: no se le manda nada al cliente Y');
$meta_tras_cruce = $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$tabla_historial} WHERE id = %d", $histX));
ok(empty($meta_tras_cruce) || strpos((string) $meta_tras_cruce, 'notificado') === false, 'cruce X/Y: el historial de X no queda marcado como notificado por el intento cruzado');

// ---------- camino feliz: admin, historial_id y cliente_id del mismo cliente ----------
$_POST = ['historial_id' => (string) $histX, 'cliente_id' => (string) $crmX, 'nonce' => wp_create_nonce('crm_nonce')];
$_REQUEST = $_POST;
$correos = [];
$out_ok = nh_ajax(function () { $GLOBALS['at_crm_ai']->crm_enviar_notificacion_historial(); });
$json_ok = json_decode($out_ok, true);
ok(is_array($json_ok) && $json_ok['success'] === true, 'admin, mismo cliente: notifica correctamente: ' . substr($out_ok, 0, 150));
ok(count($correos) === 1 && $correos[0]['to'] === $emailX, 'admin, mismo cliente: el correo llega al cliente X');
$meta_final = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$tabla_historial} WHERE id = %d", $histX)), true);
ok(!empty($meta_final['notificado']), 'admin, mismo cliente: el historial de X queda marcado como notificado');

wp_set_current_user(0);
$_POST = [];
$_REQUEST = [];

// Limpieza
$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_historial} WHERE cliente_id IN (%d, %d)", $crmX, $crmY));
$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_tech} WHERE crm_cliente_id IN (%d, %d)", $crmX, $crmY));
$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_crm} WHERE id IN (%d, %d)", $crmX, $crmY));
wp_delete_user((int) $editor_id);

fin();
