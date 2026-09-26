<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/timeline-cliente-interno-wp-test.php
//
// T10b ronda 1 (revisión), hallazgo 1: render_public_timeline() (crm-ai-completo.php) lee
// wp_automatiza_clients_details por el id del CRM (cid de la URL) sin filtrar por tipo, así que un
// Seguimiento interno (p. ej. 'mensaje_whatsapp', que llega ahí migrado desde el prospecto) quedaba
// visible en la línea de tiempo pública de cualquier cliente cuyo id de CRM coincidiera con el
// client_id guardado en esa fila. Esta prueba no reproduce el cruce completo entre wp_crm_clientes.id
// y wp_automatiza_tech_clients.id (ver Docs/.../task-10b-report.md, sección «Ronda 1», bug marcado
// aparte): solo comprueba que, viendo el propio portal de un cliente, un detalle con un tipo interno
// nunca aparece en el HTML mientras uno normal sí.
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

$tabla_details = $wpdb->prefix . 'automatiza_clients_details';
// Mismo client_id (el del CRM) para las dos filas: si el filtro faltara, las dos aparecerían en la
// vista de este mismo cliente; con el filtro, solo la nota normal debe verse.
$wpdb->insert($tabla_details, [
	'client_id'   => $crm_id,
	'detail_type' => 'mensaje_whatsapp',
	'title'       => 'Mensaje por WhatsApp sobre la propuesta',
	'description' => 'MENSAJE-PRIVADO-no-debe-verse-nunca',
	'created_at'  => current_time('mysql'),
]);
$wpdb->insert($tabla_details, [
	'client_id'   => $crm_id,
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

$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_details} WHERE client_id = %d", $crm_id));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm_id));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));

fin();
