<?php
// Guardar una nota de la ficha del CRM: exige permiso de editor, un cliente que exista y una fecha válida.
// Uso (WAMP encendido): AT_WP_LOAD=C:/wamp64/www/automatiza-tech/wp-load.php php tests/security/crm-guardar-nota.test.php
$wp_load = getenv('AT_WP_LOAD');
if (!$wp_load || !is_file($wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD.\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
define('WP_USE_THEMES', false);
require $wp_load;
$raiz = dirname(__DIR__, 2);
if (!function_exists('at_crm_validar_nota') && is_file($raiz . '/wp-content/mu-plugins/at-crm-validar-nota.php')) {
	require_once $raiz . '/wp-content/mu-plugins/at-crm-validar-nota.php';
}

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
if (!function_exists('at_crm_validar_nota')) {
	echo "FALLA falta la función at_crm_validar_nota()\n\n1 FALLAS\n";
	exit(1);
}

global $wpdb;
$wpdb->insert($wpdb->prefix . 'crm_clientes', ['tipo' => 'cliente', 'estado' => 'contratado', 'nombre' => 'Cliente Nota', 'email' => 'nota-' . wp_generate_password(6, false) . '@ejemplo.test', 'created_at' => current_time('mysql')]);
$cliente = (int) $wpdb->insert_id;
$sub = wp_insert_user(['user_login' => 'nota_' . wp_generate_password(6, false, false), 'user_pass' => wp_generate_password(20), 'user_email' => 'nota-sub-' . wp_generate_password(6, false) . '@ejemplo.test', 'role' => 'subscriber']);
register_shutdown_function(function () use ($cliente, $sub) {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user((int) $sub);
	$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $cliente]);
});
$admin = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);

wp_set_current_user(0);
ok(at_crm_validar_nota($cliente, '')['error'] === 'sin_permiso', 'sin sesión: sin permiso');
wp_set_current_user((int) $sub);
ok(at_crm_validar_nota($cliente, '')['error'] === 'sin_permiso', 'suscriptor: sin permiso');

wp_set_current_user($admin);
ok(at_crm_validar_nota(999999999, '')['error'] === 'cliente', 'cliente que no existe: rechazado');
ok(at_crm_validar_nota(0, '')['error'] === 'cliente', 'cliente 0: rechazado');

$v = at_crm_validar_nota($cliente, '2026-10-20T10:30');
ok($v['ok'] && $v['fecha'] === '2026-10-20 10:30:00', 'fecha del formulario (datetime-local) se normaliza');
$v = at_crm_validar_nota($cliente, '2026-10-20 10:30:15');
ok($v['ok'] && $v['fecha'] === '2026-10-20 10:30:15', 'fecha con segundos se acepta');
foreach (['basura', '2026-13-45T99:99', "2026-10-20'; DROP", '1900-01-01T00:00'] as $mala) {
	$v = at_crm_validar_nota($cliente, $mala);
	ok($v['ok'] && abs(strtotime($v['fecha']) - strtotime(current_time('mysql'))) < 120, "fecha inválida ($mala): se usa la de ahora");
}
$v = at_crm_validar_nota($cliente, '');
ok($v['ok'] && abs(strtotime($v['fecha']) - strtotime(current_time('mysql'))) < 120, 'sin fecha: la de ahora');

// El manejador del CRM usa la validación y falla cerrado si la función no está.
$fuente = (string) file_get_contents($raiz . '/wp-content/mu-plugins/crm-ai-completo.php');
$ini = strpos($fuente, 'public function ajax_guardar_nota()');
$cuerpo = $ini !== false ? substr($fuente, $ini, 1600) : '';
ok(strpos($cuerpo, 'at_crm_validar_nota(') !== false, 'ajax_guardar_nota llama a at_crm_validar_nota');
ok(strpos($cuerpo, "function_exists('at_crm_validar_nota')") !== false && strpos($cuerpo, 'wp_send_json_error') !== false, 'ajax_guardar_nota falla cerrado sin la validación');
ok(strpos($cuerpo, "'created_at' => \$v['fecha']") !== false, 'ajax_guardar_nota guarda la fecha validada');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
