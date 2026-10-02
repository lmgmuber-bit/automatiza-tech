<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/pagina-respuesta-publica-wp-test.php
// T11 ronda 1 (26-sep), hallazgos 1 y 5 (pagina.php):
// - Hallazgo 1: nombre y comentario pasaban por sanitize_text_field()/sanitize_textarea_field(), que
//   comparten _sanitize_text_fields() y borran '%' seguido de dos caracteres hexadecimales (lo
//   confunden con un byte %-encoded). Verificado en el navegador: «¿El 50%de anticipo puede ser en 2
//   cuotas?» llegaba a Luis como «¿El 50 anticipo puede ser en 2 cuotas?».
// - Hallazgo 5: un GET a admin-post.php?action=at_cc_responder&codigo=X (o …&at_cc_datos_contrato…)
//   redirigía a ver-presentacion.php?id=&respuesta=error (el código solo se leía de POST), que
//   muestra «ID de presentación no válido» en vez del error real de la propuesta correcta.
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_cc_procesar_respuesta_publica') || !function_exists('at_cc_procesar_datos_contrato_publica')) {
	fwrite(STDERR, "pagina.php no está cargado: revisa cargar.php.\n");
	exit(2);
}

$det = $wpdb->prefix . 'automatiza_propuestas_details';
$marca = 'prueba-pagina-resp-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];

function crear_propuesta_resp(string $marca, string $status, array &$creadas): object {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'      => $marca . '-' . count($creadas) . '@example.com',
		'unique_link_id'    => substr(md5($marca . $status . count($creadas) . microtime(true)), 0, 12),
		'client_name'       => 'Cliente Prueba', 'company_name' => '[PRUEBA] Sanitización', 'phone' => '',
		'status'            => $status, 'flujo' => 'v3',
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text'   => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	$creadas[] = $id;
	return at_cc_propuesta_por_id($id);
}

/** Corre la acción real en un proceso PHP aparte (termina siempre con wp_safe_redirect()+exit) y
 *  devuelve ['redirect' => <url o ''>, 'correo' => <array o null>, 'stderr' => <texto>]. El correo se
 *  manda dentro de ese mismo proceso hijo (pre_wp_mail agregado en el padre no lo vería). */
function correr_resp(string $accion, string $metodo, array $post, array $get = []): array {
	$hijo = __DIR__ . '/pagina-respuesta-publica-wp-test-run.php';
	$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
	$proc = proc_open([PHP_BINARY, $hijo, $accion, $metodo, json_encode($post), json_encode($get)], $descriptores, $pipes);
	if (!is_resource($proc)) {
		fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
		exit(2);
	}
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	$redirect = '';
	if (preg_match('/^REDIRECT:(.*)$/m', $out, $m)) {
		$redirect = trim($m[1]);
	}
	$correo = null;
	if (preg_match('/^MAIL:(.*)$/m', $out, $m)) {
		$correo = json_decode((string) base64_decode(trim($m[1])), true);
	}
	return ['redirect' => $redirect, 'correo' => $correo, 'stderr' => trim($err)];
}

function ultima_metadata_resp(int $propuesta_id): array {
	global $wpdb, $det;
	$m = $wpdb->get_var($wpdb->prepare("SELECT metadata FROM {$det} WHERE propuesta_id = %d AND detail_type = 'respuesta_cliente' ORDER BY id DESC LIMIT 1", $propuesta_id));
	return $m ? (json_decode((string) $m, true) ?: []) : [];
}

// ================= Hallazgo 1: '%XX' en nombre y comentario no se borra =================
$p1 = crear_propuesta_resp($marca, 'sent', $creadas);
$codigo1 = (string) $p1->unique_link_id;
$nombre_raro = 'Ana 50%de Prueba';
$comentario_raro = '¿El 50%de anticipo puede ser en 2 cuotas? <3M';
$post1 = [
	'action' => 'at_cc_responder', 'codigo' => $codigo1, 'salida' => 'evalua',
	'nombre' => $nombre_raro, 'comentario' => $comentario_raro,
	'_wpnonce' => wp_create_nonce('at_cc_responder_' . $codigo1),
];
$r1 = correr_resp('responder', 'POST', $post1);
$meta1 = ultima_metadata_resp((int) $p1->id);
ok(($meta1['comentario'] ?? null) === $comentario_raro, "el comentario con '%de' se guarda idéntico (no se borra el '%XX')" . (isset($meta1['comentario']) ? ': guardado "' . $meta1['comentario'] . '"' : ' | ' . $r1['stderr']));
ok(($meta1['nombre'] ?? null) === $nombre_raro, "el nombre con '%de' se guarda idéntico" . (isset($meta1['nombre']) ? ': guardado "' . $meta1['nombre'] . '"' : ''));
ok(at_cc_propuesta_por_id((int) $p1->id)->status === 'evaluando', 'de paso, la propuesta pasó a "evaluando"');
$mensaje_correo1 = (string) ($r1['correo']['message'] ?? '');
ok($r1['correo'] !== null && strpos($mensaje_correo1, '50%de anticipo') !== false, 'el correo a Luis trae el comentario con el "%de" intacto: ' . ($r1['correo'] !== null ? $mensaje_correo1 : '(sin correo) | ' . $r1['stderr']));
ok($r1['correo'] !== null && strpos($mensaje_correo1, '&lt;3M') !== false && strpos($mensaje_correo1, '<3M') === false, 'el correo a Luis escapa el "<" del comentario (esc_html), nunca lo deja como etiqueta activa');

// ================= Hallazgo 1: tope de 1000 caracteres en el comentario =================
$p1b = crear_propuesta_resp($marca, 'sent', $creadas);
$codigo1b = (string) $p1b->unique_link_id;
$comentario_largo = str_repeat('a', 1500);
correr_resp('responder', 'POST', [
	'action' => 'at_cc_responder', 'codigo' => $codigo1b, 'salida' => 'evalua',
	'nombre' => 'Cliente Prueba', 'comentario' => $comentario_largo,
	'_wpnonce' => wp_create_nonce('at_cc_responder_' . $codigo1b),
]);
$meta1b = ultima_metadata_resp((int) $p1b->id);
ok(isset($meta1b['comentario']) && mb_strlen($meta1b['comentario']) === 1000, 'el comentario se recorta a 1000 caracteres: quedó en ' . mb_strlen((string) ($meta1b['comentario'] ?? '')));

// ================= Hallazgo 5: GET a la acción de responder redirige con el código real =================
$p2 = crear_propuesta_resp($marca, 'sent', $creadas);
$codigo2 = (string) $p2->unique_link_id;
$r2 = correr_resp('responder', 'GET', [], ['codigo' => $codigo2]);
ok($r2['redirect'] !== '' && strpos($r2['redirect'], 'id=' . $codigo2) !== false, 'GET a "responder": el redirect trae el código real, no "id=" vacío: ' . $r2['redirect']);
ok(strpos($r2['redirect'], 'respuesta=error') !== false, 'GET a "responder": el redirect sigue marcando "respuesta=error" (nunca procesa un GET)');
ok(at_cc_propuesta_por_id((int) $p2->id)->status === 'sent', 'GET a "responder": la propuesta no cambió de estado');
$n2 = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$det} WHERE propuesta_id = %d", $p2->id));
ok($n2 === 0, 'GET a "responder": no queda ninguna nota en Seguimiento');

// Mismo criterio en el manejador de datos del contrato.
$p3 = crear_propuesta_resp($marca, 'aceptada', $creadas);
$codigo3 = (string) $p3->unique_link_id;
$r3 = correr_resp('datos_contrato', 'GET', [], ['codigo' => $codigo3]);
ok($r3['redirect'] !== '' && strpos($r3['redirect'], 'id=' . $codigo3) !== false, 'GET a "datos_contrato": el redirect trae el código real: ' . $r3['redirect']);
ok(strpos($r3['redirect'], 'respuesta=error') !== false, 'GET a "datos_contrato": el redirect marca "respuesta=error"');

// Sin código alguno (ni POST ni GET): sigue sin fallar, solo con "id=" vacío.
$r4 = correr_resp('responder', 'GET', [], []);
ok($r4['redirect'] !== '' && strpos($r4['redirect'], 'id=&respuesta=error') !== false, 'GET sin código: redirect con "id=" vacío, sin advertencias PHP: ' . $r4['redirect']);

// Limpieza
delete_transient('at_cc_ctx_aviso_' . $p1->id);
$ids = implode(',', array_map('intval', $creadas));
$emails = $wpdb->get_col("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");
$crm_ids = array_filter(array_map('at_cc_crm_de_email', $emails));
$wpdb->query("DELETE FROM {$det} WHERE propuesta_id IN ({$ids})");
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id IN ({$ids})");
if ($crm_ids) {
	$lista = implode(',', array_map('intval', $crm_ids));
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id IN ({$lista})");
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN ({$lista})");
}
$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN ({$ids})");

fin();
