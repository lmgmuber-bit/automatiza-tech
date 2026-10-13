<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/timeline-cliente-interno-wp-test.php
//
// render_public_timeline() (crm-ai-completo.php) arma la línea de tiempo pública del cliente. Esta
// prueba comprueba que un Seguimiento con un tipo interno (p. ej. 'mensaje_whatsapp') nunca aparece
// ahí, mientras uno con un tipo normal sí.
//
// render_public_timeline() termina con exit, así que la vista se genera en un proceso hijo
// (este mismo archivo con --vista <cid> <token>) y el padre revisa el HTML que imprime.
if (($argv[1] ?? '') === '--vista') {
	require __DIR__ . '/wp-bootstrap.php';
	$_GET = ['crm_view' => 'timeline', 'cid' => (string) ($argv[2] ?? ''), 'token' => (string) ($argv[3] ?? '')];
	$GLOBALS['at_crm_ai']->render_public_timeline();
	exit(0);
}

require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

$marca = 'prueba-timeline-interno-' . strtolower(wp_generate_password(6, false, false));
$email = $marca . '@example.com';
$r = at_cc_asegurar_cliente(['nombre' => 'Prueba Timeline', 'email' => $email, 'empresa' => '[PRUEBA] Timeline', 'telefono' => '+56 9 2222 3333', 'valor' => 1000, 'servicios' => 'Fase 1']);
ok(is_array($r) && $r['crm_id'] > 0, 'cliente de prueba creado en el CRM');
$crm_id = (int) $r['crm_id'];
$tech_id = (int) $r['tech_id'];

$tabla_details = $wpdb->prefix . 'automatiza_clients_details';
// Task 16: client_id es el de la ficha operativa enlazada (tech_id), no el del CRM: quien escribe
// esta tabla siempre guarda ese id.
$wpdb->insert($tabla_details, [
	'client_id'   => $tech_id,
	'detail_type' => 'mensaje_whatsapp',
	'title'       => 'Mensaje por WhatsApp sobre la propuesta',
	'description' => 'MENSAJE-PRIVADO-no-debe-verse-nunca',
	'created_at'  => current_time('mysql'),
]);
$wpdb->insert($tabla_details, [
	'client_id'   => $tech_id,
	'detail_type' => 'nota',
	'title'       => 'Nota visible de prueba',
	'description' => 'Esta nota sí debe verse en la línea de tiempo pública.',
	'created_at'  => current_time('mysql'),
]);

$url = $GLOBALS['at_crm_ai']->url_portal($crm_id);
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
ok(!empty($q['cid']) && !empty($q['token']), 'el cliente tiene enlace a su portal');

$proc = proc_open([PHP_BINARY, __FILE__, '--vista', (string) ($q['cid'] ?? ''), (string) ($q['token'] ?? '')], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
$html = is_resource($proc) ? (string) stream_get_contents($tubos[1]) : '';
$errores_hijo = is_resource($proc) ? (string) stream_get_contents($tubos[2]) : '';
if (is_resource($proc)) {
	fclose($tubos[1]);
	fclose($tubos[2]);
	proc_close($proc);
}

ok($html !== '' && strpos($html, 'Nota visible de prueba') !== false, 'la vista sí generó HTML con la nota normal (confirma que la prueba corrió sobre el HTML real, no vacío): ' . trim($errores_hijo));
ok(strpos($html, 'MENSAJE-PRIVADO-no-debe-verse-nunca') === false, 'la línea de tiempo pública del cliente NO muestra el texto de un detalle mensaje_whatsapp');
ok(strpos($html, 'Mensaje por WhatsApp sobre la propuesta') === false, 'la línea de tiempo pública del cliente NO muestra el título de un detalle mensaje_whatsapp');

$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_details} WHERE client_id = %d", $tech_id));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm_id));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));

fin();
