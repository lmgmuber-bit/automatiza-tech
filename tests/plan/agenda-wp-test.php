<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/agenda-wp-test.php
// Task 10: «Agendar llamada de seguimiento» (decisión 9). at_pt_datos_agenda() y la precarga por GET
// (?pt_plan=<id>) del formulario de inc/admin-followup-meetings.php. Abrir el formulario nunca crea una reunión.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!function_exists('at_pt_datos_agenda') || !function_exists('automatiza_tech_followup_page')) {
	fwrite(STDERR, "Falta at_pt_datos_agenda (panel.php) o el módulo de reuniones de seguimiento.\n");
	exit(2);
}
global $wpdb;
wp_set_current_user(pt_admin_id());
$m = ptc_marca();
$tabla_reuniones = $wpdb->prefix . 'automatiza_followup_meetings';

/** HTML de «Reuniones de seguimiento» con ese GET (y ese POST, si viene). */
function seguimiento(array $get, array $post = []): string {
	$_GET = $get + ['page' => 'automatiza-followup'];
	$_POST = $post;
	$_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
	ob_start();
	automatiza_tech_followup_page();
	$h = (string) ob_get_clean();
	$_GET = [];
	$_POST = [];
	$_SERVER['REQUEST_METHOD'] = 'GET';
	return $h;
}

/** Valor del atributo value del input con ese id ('' si no tiene; null si no está). */
function valor_input(string $h, string $id): ?string {
	if (!preg_match('/<input[^>]*id="' . preg_quote($id, '/') . '"[^>]*value="([^"]*)"/s', $h, $mm)) {
		return null;
	}
	return html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8');
}

function valor_notas(string $h): ?string {
	if (!preg_match('/<textarea name="notes" id="notes"[^>]*>(.*?)<\/textarea>/s', $h, $mm)) {
		return null;
	}
	return html_entity_decode($mm[1], ENT_QUOTES, 'UTF-8');
}

// ---------- at_pt_datos_agenda ----------
$correo = "prueba-plan-{$m}@example.com";
$c = ptc_cliente($m, $correo);
$k = ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', $correo);
$f = ptc_plan($k);
ptc_sembrar((int) $f->id);
$f = at_pt_plan((int) $f->id);
$d = at_pt_datos_agenda((int) $f->id);
ok(array_keys($d) === ['client_name', 'client_email', 'company_name', 'phone', 'meeting_subject', 'notes'], 'devuelve las seis claves del formulario, en orden');
ok($d['client_name'] === '[PRUEBA] Cliente Plan ' . $m && $d['client_email'] === $correo && $d['company_name'] === '[PRUEBA] Empresa ' . $m && $d['phone'] === '+56 9 1111 1111', 'nombre, correo, empresa y teléfono salen de la ficha del CRM');
ok($d['meeting_subject'] === 'Seguimiento del plan de trabajo — [PRUEBA] Sitio del panel', 'asunto fijo «Seguimiento del plan de trabajo — <proyecto>»: ' . $d['meeting_subject']);
ok(strpos($d['notes'], (string) $f->codigo) !== false && strpos($d['notes'], 'PRUEBA-PT-' . $m) !== false, 'las notas internas llevan el código del plan y el número del contrato');
ok(at_pt_datos_agenda(0) === [] && at_pt_datos_agenda(999999999) === [], 'un plan que no existe devuelve []');
// Plan creado cuando la ficha operativa todavía no estaba enlazada al CRM (crm_cliente_id vacío en el plan): los
// datos salen igual del cliente del CRM enlazado hoy (at_pt_crm_de_plan, Task 9), no solo de los marcadores.
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => null], ['id' => (int) $f->id]);
ok((at_pt_datos_agenda((int) $f->id)['client_name'] ?? '') === $d['client_name'], 'plan guardado sin cliente del CRM: los datos salen del cliente enlazado hoy a su ficha');
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => $c['crm']], ['id' => (int) $f->id]);

// Sin correo en el CRM ni en el contrato, sin teléfono en el CRM y todavía sin payload (Review Focus 4).
$c2 = ptc_cliente($m . 'n', '');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['telefono' => ''], ['id' => $c2['crm']]);
$k2 = ptc_contrato($c2['tech'], $m . 'n', null);
$f2 = ptc_plan($k2);
$d2 = at_pt_datos_agenda((int) $f2->id);
ok($d2['client_email'] === '', 'sin correo: el campo queda vacío para que Luis lo escriba');
ok($d2['phone'] === '+56 9 2222 2222', 'sin teléfono en el CRM: se usa el del contrato');
ok($d2['meeting_subject'] === 'Seguimiento del plan de trabajo — [PRUEBA] Sitio ' . $m . 'n', 'sin payload: el asunto usa el nombre del proyecto del contrato');

// ---------- La página de seguimiento con ?pt_plan ----------
$antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}");
$h = seguimiento(['pt_plan' => (string) $f->id]);
ok(valor_input($h, 'client_name') === $d['client_name'] && valor_input($h, 'client_email') === $d['client_email'], 'precarga nombre y correo');
ok(valor_input($h, 'company_name') === $d['company_name'] && valor_input($h, 'phone') === $d['phone'], 'precarga empresa y teléfono');
ok(valor_input($h, 'meeting_subject') === $d['meeting_subject'], 'precarga el asunto fijo del seguimiento del plan');
ok(valor_notas($h) === $d['notes'], 'precarga las notas internas con el código del plan');
ok(valor_input($h, 'meeting_date') === '' && strpos($h, 'Datos precargados desde el plan de trabajo') !== false, 'la fecha la elige Luis y la página avisa de dónde vienen los datos');
ok(strpos($h, 'Programar Nueva Reunión') !== false && strpos($h, 'Editar Reunión #') === false, 'es una reunión nueva: no pasa a modo edición');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}") === $antes, 'abrir el formulario no crea ninguna reunión');

$h = seguimiento(['pt_plan' => (string) $f2->id]);
ok(valor_input($h, 'client_email') === '' && strpos($h, 'no tiene correo') !== false, 'cliente sin correo: el campo queda vacío y la página lo avisa');
$h = seguimiento(['pt_plan' => '999999999']);
ok(strpos($h, 'No se encontró ese plan de trabajo') !== false && valor_input($h, 'client_name') === '', 'plan inexistente: formulario vacío con aviso');
$h = seguimiento([]);
ok(valor_input($h, 'meeting_subject') === 'Reunión de Seguimiento - AutomatizaTech' && strpos($h, 'Datos precargados') === false, 'sin pt_plan: el formulario de siempre');

// edit_id manda sobre pt_plan.
$wpdb->insert($tabla_reuniones, ['client_name' => '[PRUEBA] Reunión existente ' . $m, 'client_email' => $correo, 'meeting_date' => '2026-12-01', 'meeting_time' => '10:00:00', 'status' => 'scheduled']);
$reunion = (int) $wpdb->insert_id;
$GLOBALS['ptc_creado']['reuniones'][] = $reunion;
$h = seguimiento(['edit_id' => (string) $reunion, 'pt_plan' => (string) $f->id]);
ok(valor_input($h, 'client_name') === '[PRUEBA] Reunión existente ' . $m && strpos($h, 'Datos precargados') === false, 'con edit_id se edita esa reunión y no se precarga el plan');

// Tras un POST (formulario devuelto con error) no se vuelve a precargar: evita agendar dos veces.
$antes = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}");
$h = seguimiento(['pt_plan' => (string) $f->id], [
	'followup_nonce' => wp_create_nonce('save_followup_meeting'), 'client_name' => '', 'client_email' => '', 'invitees_emails' => '',
	'company_name' => '', 'phone' => '', 'meeting_date' => '', 'meeting_time' => '', 'meet_link' => '', 'meeting_subject' => '', 'notes' => '',
]);
ok(strpos($h, 'completa todos los campos obligatorios') !== false, 'el POST sí se procesó (con su error de campos obligatorios)');
ok(valor_input($h, 'client_name') === '' && strpos($h, 'Datos precargados') === false, 'tras un POST el formulario no se precarga');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabla_reuniones}") === $antes, 'el POST con error no creó reuniones');

ptc_limpiar();
fin();
