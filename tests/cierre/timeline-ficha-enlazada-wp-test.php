<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/timeline-ficha-enlazada-wp-test.php
//
// Task 16: render_public_timeline() y el timeline de la ficha del admin (crm-ai-completo.php) leen
// wp_automatiza_clients_details por los ids de la o las fichas operativas enlazadas al cliente
// (_ids_ficha_operativa(), sobre at_cc_techs_de_crm()), nunca por el id del cliente en el CRM: quien
// escribe esa tabla siempre guarda el id de la ficha operativa. Esta prueba comprueba que una fila con
// el id de la ficha de un cliente aparece en su propio portal; que una fila cuyo client_id coincide con
// el id del CRM de ese cliente, pero está enlazada a otro, no se cuela en su portal; y que sin fichas
// enlazadas no se muestra ninguna fila de clientes ni hay errores de PHP (falla cerrada).
//
// render_public_timeline() termina con exit, así que la vista se genera en un proceso hijo (este mismo
// archivo con --vista <cid> <token>) y el padre revisa el HTML que imprime. La ficha del admin
// (render_ficha_cliente(), ~línea 1736) arma una página completa con muchos otros datos y controles;
// en vez de generar su HTML aquí, esta prueba ejercita por reflexión el método privado que las dos
// vistas comparten (_ids_ficha_operativa()), que es lo que decide qué ids se consultan.
if (($argv[1] ?? '') === '--vista') {
	require __DIR__ . '/wp-bootstrap.php';
	$_GET = ['crm_view' => 'timeline', 'cid' => (string) ($argv[2] ?? ''), 'token' => (string) ($argv[3] ?? '')];
	$GLOBALS['at_crm_ai']->render_public_timeline();
	exit(0);
}

require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

/** Genera el HTML real del portal de un cliente en un proceso hijo (ver cabecera). */
function at_cc_test_ver_portal(int $crm_id): array {
	$url = $GLOBALS['at_crm_ai']->url_portal($crm_id);
	parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
	$proc = proc_open([PHP_BINARY, __FILE__, '--vista', (string) ($q['cid'] ?? ''), (string) ($q['token'] ?? '')], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
	$html = is_resource($proc) ? (string) stream_get_contents($tubos[1]) : '';
	$errores = is_resource($proc) ? (string) stream_get_contents($tubos[2]) : '';
	if (is_resource($proc)) {
		fclose($tubos[1]);
		fclose($tubos[2]);
		proc_close($proc);
	}
	return [$html, $errores];
}

$marca = 'prueba-timeline-enlace-' . strtolower(wp_generate_password(6, false, false));
$tabla_details = $wpdb->prefix . 'automatiza_clients_details';
$tabla_tech = $wpdb->prefix . 'automatiza_tech_clients';
$tabla_crm = $wpdb->prefix . 'crm_clientes';

// ---------- cliente A, con un id de CRM explícito y alto ----------
// A necesita un id de CRM alto y libre en las dos tablas para poder, más abajo, insertar una ficha
// operativa impostora con ESE MISMO id (algo que nunca ocurre con ids normales, pero que antes de esta
// tarea bastaba para que el portal de un cliente mostrara las filas de otro).
$id_alto = 990000;
while (
	(int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tabla_crm} WHERE id = %d", $id_alto)) > 0
	|| (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tabla_tech} WHERE id = %d", $id_alto)) > 0
) {
	$id_alto++;
}
$crmA = $id_alto;
$emailA = $marca . '-a@example.com';
$ok_crm_a = $wpdb->insert($tabla_crm, [
	'id' => $crmA, 'nombre' => 'Prueba Enlace A', 'email' => $emailA, 'tipo' => 'cliente',
	'estado' => 'contratado', 'fecha_contrato' => current_time('mysql'), 'fecha_contacto' => current_time('mysql'),
	'origen' => 'prueba',
]);
ok($ok_crm_a !== false, 'cliente A creado en el CRM con un id explícito y alto');

$ra = at_cc_asegurar_cliente(['crm_id' => $crmA, 'nombre' => 'Prueba Enlace A', 'email' => $emailA, 'empresa' => '[PRUEBA] Enlace', 'telefono' => '+56 9 2222 4444', 'valor' => 1000, 'servicios' => 'Fase 1']);
ok(is_array($ra) && (int) $ra['crm_id'] === $crmA && $ra['tech_id'] > 0, 'A queda con su propia ficha operativa enlazada');
$techA = (int) $ra['tech_id'];

// ---------- una fila con el tech_id de A sí aparece en el portal de A ----------
$wpdb->insert($tabla_details, [
	'client_id' => $techA, 'detail_type' => 'nota', 'title' => 'Nota de A',
	'description' => 'MARCA-A-SI-DEBE-VERSE', 'created_at' => current_time('mysql'),
]);
[$html_a1, $err_a1] = at_cc_test_ver_portal($crmA);
ok($html_a1 !== '' && strpos($html_a1, 'MARCA-A-SI-DEBE-VERSE') !== false, 'una fila con el tech_id de A aparece en el portal de A: ' . trim($err_a1));

// ---------- cruce: ficha operativa con id explícito == crm_id de A, enlazada a otro cliente B ----------
$emailB = $marca . '-b@example.com';
$rb = at_cc_asegurar_cliente(['nombre' => 'Prueba Enlace B', 'email' => $emailB, 'empresa' => '[PRUEBA] Enlace', 'telefono' => '+56 9 2222 5555']);
ok(is_array($rb) && $rb['crm_id'] > 0 && $rb['tech_id'] > 0, 'cliente B creado con su propia ficha operativa enlazada');
$crmB = (int) $rb['crm_id'];

$ok_impostora = $wpdb->insert($tabla_tech, [
	'id' => $crmA, 'crm_cliente_id' => $crmB, 'name' => 'Ficha impostora', 'email' => $emailB,
	'contracted_at' => current_time('mysql'), 'contract_status' => 'active',
]);
ok($ok_impostora !== false, 'ficha operativa impostora creada con id == crm_id de A, enlazada a B');
$wpdb->insert($tabla_details, [
	'client_id' => $crmA, 'detail_type' => 'nota', 'title' => 'Nota cruzada',
	'description' => 'MARCA-CRUCE-NUNCA-EN-A', 'created_at' => current_time('mysql'),
]);

[$html_a2, $err_a2] = at_cc_test_ver_portal($crmA);
ok($html_a2 !== '' && strpos($html_a2, 'MARCA-A-SI-DEBE-VERSE') !== false, 'tras el cruce, la nota propia de A sigue viéndose en su portal: ' . trim($err_a2));
ok(strpos($html_a2, 'MARCA-CRUCE-NUNCA-EN-A') === false, 'una fila cuyo client_id coincide con el id del CRM de A, pero enlazada a otro cliente, NO aparece en el portal de A');

// ---------- la ficha del admin sigue la misma regla: mismo helper, mismos ids ----------
$metodo = new ReflectionMethod($GLOBALS['at_crm_ai'], '_ids_ficha_operativa');
$metodo->setAccessible(true);
ok($metodo->invoke($GLOBALS['at_crm_ai'], $crmA) === [$techA], 'el helper de la ficha del admin: A trae solo su propia ficha operativa, no la impostora enlazada a B');

// ---------- sin fichas enlazadas: ninguna fila de clientes y sin errores de PHP ----------
$emailC = $marca . '-c@example.com';
$wpdb->insert($tabla_crm, [
	'nombre' => 'Prueba Enlace C', 'email' => $emailC, 'tipo' => 'cliente', 'estado' => 'contratado',
	'fecha_contrato' => current_time('mysql'), 'fecha_contacto' => current_time('mysql'), 'origen' => 'prueba',
]);
$crmC = (int) $wpdb->insert_id;
ok($metodo->invoke($GLOBALS['at_crm_ai'], $crmC) === [], 'C no tiene ninguna ficha operativa enlazada');
// Fila "vieja" guardada por error con el id del CRM de C (lo que la falla habría mostrado): con la
// falla cerrada, nunca se consulta ni se muestra.
$wpdb->insert($tabla_details, [
	'client_id' => $crmC, 'detail_type' => 'nota', 'title' => 'Nota huérfana',
	'description' => 'MARCA-C-NUNCA-SIN-FICHA', 'created_at' => current_time('mysql'),
]);
[$html_c, $err_c] = at_cc_test_ver_portal($crmC);
ok(strpos($err_c, 'Fatal error') === false, 'sin fichas enlazadas: la vista no termina en un error fatal de PHP: ' . trim($err_c));
ok($html_c !== '', 'sin fichas enlazadas: la vista igual generó HTML (no se cae en blanco)');
ok(strpos($html_c, 'MARCA-C-NUNCA-SIN-FICHA') === false, 'sin fichas enlazadas: la fila guardada con el id del CRM de C no aparece (falla cerrada, nunca se vuelve a filtrar por ese id)');

// Limpieza
$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_details} WHERE client_id IN (%d, %d)", $techA, $crmA));
$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_tech} WHERE id = %d OR crm_cliente_id IN (%d, %d)", $crmA, $crmA, $crmB));
$wpdb->query($wpdb->prepare("DELETE FROM {$tabla_crm} WHERE id IN (%d, %d, %d)", $crmA, $crmB, $crmC));

fin();
