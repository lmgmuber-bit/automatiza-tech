<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/panel-timeline-wp-test.php
// Task 8 (ajuste del controlador): render_public_prospect_timeline() (crm-ai-completo.php) no debe
// mostrarle al prospecto las notas internas 'cierre_incompleto' ni 'aviso_operativo', igual que ya
// hace render_public_timeline() con la línea de tiempo del cliente.
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

if (!function_exists('at_cc_tipos_internos')) {
	fwrite(STDERR, "at_cc_tipos_internos() no está definida: revisa puras.php.\n");
	exit(2);
}
ok(at_cc_tipos_internos() === ['cierre_incompleto', 'aviso_operativo'], 'at_cc_tipos_internos: lista de tipos que nunca son públicos');

$marca = 'prueba-panel-timeline-' . strtolower(wp_generate_password(6, false, false));
$email = $marca . '@example.com';
$inserto = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
	'client_email'       => $email,
	'unique_link_id'     => substr(md5($marca . microtime(true)), 0, 12),
	'client_name'        => 'Cliente Prueba',
	'company_name'       => '[PRUEBA] Timeline',
	'phone'              => '',
	'status'             => 'sent',
	'flujo'              => 'v3',
	'gamma_prompt_text'  => '{}',
	'transcript_text'    => '',
	'system_prompt_text' => '',
	'created_at'         => current_time('mysql'),
]);
if (!$inserto) {
	fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n");
	exit(2);
}
$propuesta_id = (int) $wpdb->insert_id;
$det = $wpdb->prefix . 'automatiza_propuestas_details';
$p = at_cc_propuesta_por_id($propuesta_id);

// Las mismas dos notas internas que deja at_cc_registrar_respuesta() cuando el cierre falla a medias
// (respuesta.php), más una nota normal que sí debe verse.
at_cc_anotar_simple($p, 'cierre_incompleto', 'Cierre incompleto de prueba', 'El contrato no se creó: motivo de prueba.');
at_cc_anotar_simple($p, 'aviso_operativo', 'Revisar datos bancarios de prueba', 'Faltan los datos bancarios.');
at_cc_anotar_simple($p, 'nota', 'Nota visible de prueba', 'Esta nota sí debe verse en la línea de tiempo pública.');

// render_public_prospect_timeline() siempre termina con exit;, así que se genera en un proceso PHP
// aparte (panel-timeline-wp-test-render.php) y aquí solo se revisa el HTML real que imprimió.
$hijo = __DIR__ . '/panel-timeline-wp-test-render.php';
$descriptores = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open([PHP_BINARY, $hijo, (string) $propuesta_id, $email], $descriptores, $pipes);
if (!is_resource($proc)) {
	fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
	exit(2);
}
$html = stream_get_contents($pipes[1]);
$errores_hijo = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);

ok($html !== '' && strpos($html, 'Nota visible de prueba') !== false, 'la vista sí generó HTML con la nota normal (confirma que la prueba corrió sobre el HTML real, no vacío): ' . trim($errores_hijo));
ok(strpos($html, 'Cierre incompleto de prueba') === false, 'la línea de tiempo pública del prospecto NO muestra una nota cierre_incompleto');
ok(strpos($html, 'Revisar datos bancarios de prueba') === false, 'la línea de tiempo pública del prospecto NO muestra una nota aviso_operativo');

// Limpieza
$wpdb->query($wpdb->prepare("DELETE FROM {$det} WHERE propuesta_id = %d", $propuesta_id));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $propuesta_id));

fin();
