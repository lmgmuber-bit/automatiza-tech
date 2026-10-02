<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/whatsapp-estado-wp-test.php
// Task 19 (aprobada por Luis el 27-sep): en la primera prueba real Meta aceptó el WhatsApp automático
// (hubo wamid) pero no se lo entregó al cliente (error 131049), y el panel habría dicho «enviado».
// - El envío guarda el wamid y el teléfono en la nota 'pedido_respuesta'.
// - La ruta at/v1/propuesta-whatsapp-estado recibe los estados de Meta desde el bot: por cada 'failed'
//   de un envío conocido deja la nota interna 'whatsapp_no_entregado' y avisa a Luis por correo, una
//   sola vez por wamid.
// - El panel «Respuesta del cliente» avisa cuando el último WhatsApp automático no se entregó.
// - Aceptar por WhatsApp devuelve 'url_datos' mientras al contrato le falte la dirección.
// El flujo de n8n se simula con el filtro pre_http_request y los correos con pre_wp_mail.
// at_cc_render_panel_respuesta() usa at_pa_estado_etiqueta()/at_pa_fecha_corta(), que el tema solo carga
// si is_admin(): se simula como en panel-respuesta-wp-test.php.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;

foreach (['at_cc_enviar_whatsapp_plantilla_detalle', 'at_cc_rest_estado_whatsapp', 'at_cc_registrar_fallo_whatsapp', 'at_cc_clave_fallo_pendiente', 'at_cc_whatsapp_limitado_por_meta','at_cc_whatsapp_no_entregado_ultimo', 'at_cc_url_datos_contrato', 'at_cc_render_panel_respuesta'] as $fn) {
	if (!function_exists($fn)) {
		fwrite(STDERR, "$fn() no está definida: revisa whatsapp.php, pagina.php y panel.php.\n");
		exit(2);
	}
}
if (!defined('AT_REST_SECRET') || AT_REST_SECRET === '') {
	fwrite(STDERR, "AT_REST_SECRET no está definido en el sitio de prueba.\n");
	exit(2);
}
// La URL del flujo nunca se llama de verdad: pre_http_request corta toda salida HTTP de esta prueba.
if (!defined('AT_N8N_CC_WHATSAPP')) {
	define('AT_N8N_CC_WHATSAPP', 'https://n8n.example.com/webhook/prueba-cierre-whatsapp');
}
$opcion_antes = get_option('at_cc_wa_plantilla_activa', null);
update_option('at_cc_wa_plantilla_activa', '1');
register_shutdown_function(function () use ($opcion_antes) {
	// Las demás pruebas esperan la plantilla apagada: se deja como estaba aunque esta prueba se caiga.
	if ($opcion_antes === null) {
		delete_option('at_cc_wa_plantilla_activa');
	} else {
		update_option('at_cc_wa_plantilla_activa', $opcion_antes);
	}
});

$correos = [];
add_filter('pre_wp_mail', function ($r, $a) use (&$correos) { $correos[] = $a; return true; }, 10, 2);
$flujo = ['code' => 200, 'body' => '{}'];
$pedidos_http = [];
add_filter('pre_http_request', function ($pre, $args, $url) use (&$flujo, &$pedidos_http) {
	$pedidos_http[] = ['url' => $url, 'args' => $args];
	return ['headers' => [], 'body' => $flujo['body'], 'response' => ['code' => $flujo['code'], 'message' => ''], 'cookies' => [], 'filename' => null];
}, 10, 3);

$marca = 'prueba-wa-estado-' . strtolower(wp_generate_password(6, false, false));
$creadas = [];
function wa_estado_crear(string $marca, string $sufijo, string $phone, array &$creadas): object {
	global $wpdb;
	$ok = $wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => $marca . '-' . $sufijo . '@example.com', 'unique_link_id' => substr(md5($marca . $sufijo . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] WhatsApp ' . $sufijo, 'phone' => $phone,
		'status' => 'sent', 'flujo' => 'v3',
		'gamma_prompt_text' => wp_json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_usd' => 0, 'price_label' => '$1.000.000 al contado']]], JSON_UNESCAPED_UNICODE),
		'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	if (!$ok) { fwrite(STDERR, "No se pudo insertar la propuesta de prueba: {$wpdb->last_error}\n"); exit(2); }
	$creadas[] = (int) $wpdb->insert_id;
	return at_cc_propuesta_por_id((int) $wpdb->insert_id);
}
function wa_estado_notas(int $id, string $tipo): array {
	global $wpdb;
	return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = %s ORDER BY id", $id, $tipo));
}
function wa_estado_total_notas(string $tipo): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas_details WHERE detail_type = %s", $tipo));
}
function wa_estado_pedir(string $ruta, $cuerpo, ?string $clave) {
	$r = new WP_REST_Request('POST', $ruta);
	$r->set_header('content-type', 'application/json');
	if ($clave !== null) { $r->set_header('x-at-secret', $clave); }
	$r->set_body(is_string($cuerpo) ? $cuerpo : wp_json_encode($cuerpo));
	return rest_do_request($r);
}
function wa_estado_panel(object $p): string {
	ob_start();
	at_cc_render_panel_respuesta(at_cc_propuesta_por_id((int) $p->id));
	return (string) ob_get_clean();
}
$ruta = '/at/v1/propuesta-whatsapp-estado';
$wamid_a = 'wamid.HBgLNTY5MTExMTIyMjIVAgARGBJQUlVFQkFBQUFBQUFBQUFBAA==';
$wamid_a2 = 'wamid.HBgLNTY5MTExMTIyMjIVAgARGBJQUlVFQkFBQUFBQUFBQUEyAA==';
$wamid_b = 'wamid.HBgLNTY5MTExMTMzMzMVAgARGBJQUlVFQkJCQkJCQkJCQkJCAA/+==';
$wamid_x = 'wamid.HBgLNTY5OTk5OTk5OTkVAgARGBJERVNDT05PQ0lET1hYWFhYAA==';

// ---------- 1) El envío guarda el wamid y el teléfono en la nota 'pedido_respuesta' ----------
$pa = wa_estado_crear($marca, 'a', '+56 9 1111 2222', $creadas);
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $wamid_a])];
$aviso = at_cc_tras_envio($pa, true);
$notas_a = wa_estado_notas((int) $pa->id, 'pedido_respuesta');
$meta_a = $notas_a ? json_decode((string) end($notas_a)->metadata, true) : [];
ok(count($notas_a) === 1 && ($meta_a['wamid'] ?? '') === $wamid_a && ($meta_a['telefono'] ?? '') === '56911112222', 'envío con «También por WhatsApp»: la nota pedido_respuesta guarda el wamid y el teléfono normalizado');
ok(strpos($aviso, 'WhatsApp enviado a Meta') !== false && strpos($aviso, 'te llega un aviso por correo') !== false && strpos($aviso, 'confirma la entrega después') === false, 'el aviso tras enviar ya no promete la entrega: dice qué pasa si Meta no la entrega');
$ultimo_http = end($pedidos_http);
$cuerpo_http = json_decode((string) ($ultimo_http['args']['body'] ?? ''), true);
ok(($ultimo_http['url'] ?? '') === AT_N8N_CC_WHATSAPP && ($ultimo_http['args']['headers']['X-AT-Secret'] ?? '') === AT_REST_SECRET && ($cuerpo_http['telefono'] ?? '') === '56911112222', 'el envío llama al flujo con la clave y el teléfono normalizado');

$pb = wa_estado_crear($marca, 'b', '+56 9 1111 3333', $creadas);
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $wamid_b])];
$detalles = at_cc_whatsapp_tras_pedido($pb);
$notas_b = wa_estado_notas((int) $pb->id, 'pedido_respuesta');
$meta_b = $notas_b ? json_decode((string) end($notas_b)->metadata, true) : [];
ok(($meta_b['wamid'] ?? '') === $wamid_b && ($meta_b['telefono'] ?? '') === '56911113333', '«Pedir respuesta»: la nota pedido_respuesta guarda el wamid (con / y + de base64) y el teléfono');
ok(strpos($detalles[0] ?? '', 'WhatsApp enviado a Meta') !== false && strpos($detalles[0] ?? '', 'aviso por correo') !== false, '«Pedir respuesta»: el detalle tampoco promete la entrega');

// Sin wamid en la respuesta del flujo: se anota igual, pero el aviso dice que no llegará aviso.
$pc = wa_estado_crear($marca, 'c', '+56 9 1111 4444', $creadas);
$flujo = ['code' => 200, 'body' => '{"ok":true}'];
$aviso_c = at_cc_tras_envio($pc, true);
$notas_c = wa_estado_notas((int) $pc->id, 'pedido_respuesta');
ok(count($notas_c) === 1 && (json_decode((string) $notas_c[0]->metadata, true)['wamid'] ?? 'x') === '' && strpos($aviso_c, 'no te llegará aviso') !== false, 'sin wamid del flujo: se anota con wamid vacío y el aviso lo dice');
// Un wamid con forma rara del flujo no se guarda.
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => "wamid.<script>"])];
ok(at_cc_enviar_whatsapp_plantilla_detalle($pc)['wamid'] === '', 'un wamid con forma rara del flujo no se guarda');

// Errores del flujo: 502 y 400 con {"ok":false}, y un 200 que igual dice "ok": false.
$flujo = ['code' => 502, 'body' => '{"ok":false,"error":"Meta rechazó","codigo":131000}'];
$antes = count(wa_estado_notas((int) $pc->id, 'pedido_respuesta'));
$aviso_502 = at_cc_tras_envio($pc, true);
ok(strpos($aviso_502, 'falló') !== false && strpos($aviso_502, 'HTTP 502') !== false && count(wa_estado_notas((int) $pc->id, 'pedido_respuesta')) === $antes, 'flujo con 502: avisa que falló y no anota pedido_respuesta');
ok(at_cc_enviar_whatsapp_plantilla($pc) === 'n8n respondió HTTP 502', 'at_cc_enviar_whatsapp_plantilla() sigue devolviendo el motivo como texto');
$flujo = ['code' => 200, 'body' => '{"ok":false,"error":"sin plantilla"}'];
ok(strpos(at_cc_enviar_whatsapp_plantilla($pc), 'sin plantilla') !== false, 'un 200 con "ok": false cuenta como error');
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $wamid_x])];
ok(at_cc_enviar_whatsapp_plantilla($pc) === '', 'un 200 con "ok": true sigue siendo «salió» para quien usa el texto');

// ---------- 2) La ruta de estados exige la clave ----------
ok(wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_a, 'estado' => 'failed', 'codigo' => 131049]]], null)->get_status() === 401, 'ruta de estados sin clave: 401');
ok(wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_a, 'estado' => 'failed', 'codigo' => 131049]]], 'mala')->get_status() === 401, 'ruta de estados con clave mala: 401');
ok(!wa_estado_notas((int) $pa->id, 'whatsapp_no_entregado') && !$correos, 'sin clave no se crea nada ni sale correo');

// ---------- 3) Un 'failed' de un envío conocido: nota interna y correo a Luis ----------
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_a, 'estado' => 'failed', 'codigo' => 131049, 'titulo' => 'This message was not delivered to maintain healthy ecosystem engagement.', 'telefono' => '56911112222']]], AT_REST_SECRET);
ok($res->get_status() === 200 && $res->get_data() === ['ok' => true, 'avisados' => 1, 'ignorados' => 0], 'failed conocido: responde avisados 1');
$fallos_a = wa_estado_notas((int) $pa->id, 'whatsapp_no_entregado');
$meta_f = $fallos_a ? json_decode((string) $fallos_a[0]->metadata, true) : [];
ok(count($fallos_a) === 1 && ($meta_f['wamid'] ?? '') === $wamid_a && (int) ($meta_f['codigo'] ?? 0) === 131049 && strpos((string) ($meta_f['titulo'] ?? ''), 'healthy ecosystem') !== false, 'queda la nota whatsapp_no_entregado con wamid, código y título');
ok(in_array('whatsapp_no_entregado', at_cc_tipos_internos(), true), 'whatsapp_no_entregado es un tipo interno');
ok(at_cc_propuesta_por_id((int) $pa->id)->status === 'sent', 'la propuesta no cambia de estado');
$correo = $correos[0] ?? [];
ok(count($correos) === 1 && ($correo['to'] ?? '') === at_cc_correo_avisos(), 'sale un correo a Luis');
ok(strpos((string) ($correo['subject'] ?? ''), '[PRUEBA] WhatsApp a') !== false && strpos((string) ($correo['subject'] ?? ''), 'no le llegó al cliente') !== false, 'asunto: el WhatsApp de la propuesta de <empresa> no le llegó al cliente');
$msj = (string) ($correo['message'] ?? '');
ok(strpos($msj, 'mensajes de marketing') !== false && strpos($msj, '24 horas') !== false, 'el correo explica el 131049 en español: límite de marketing y no reintentar antes de 24 horas');
ok(strpos($msj, 'no cambió de estado') !== false && strpos($msj, 'Enviar por mi WhatsApp') !== false, 'el correo dice que la propuesta no cambió y ofrece «Enviar por mi WhatsApp»');
ok(strpos(html_entity_decode($msj), 'page=automatiza-proposals&edit_id=' . (int) $pa->id . '&tab=envio') !== false, 'el correo enlaza a la ficha de la propuesta');
ok(strpos($msj, 'healthy ecosystem') === false, 'con un código conocido no se muestra el texto en inglés de Meta');
$cabeceras = implode("\n", (array) ($correo['headers'] ?? []));
ok(strpos($cabeceras, 'Content-Type: text/html') !== false && strpos($cabeceras, 'From: Automatiza Tech <') !== false, 'mismas cabeceras que los demás avisos a Luis');

// ---------- 4) El mismo wamid otra vez: nada nuevo ----------
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_a, 'estado' => 'failed', 'codigo' => 131049]]], AT_REST_SECRET);
ok($res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 1] && count(wa_estado_notas((int) $pa->id, 'whatsapp_no_entregado')) === 1 && count($correos) === 1, 'mismo wamid otra vez: no repite nota ni correo');

// ---------- 5) Wamid desconocido y estados que no son 'failed': se ignoran ----------
$total_fallos = wa_estado_total_notas('whatsapp_no_entregado');
$res = wa_estado_pedir($ruta, ['estados' => [
	['wamid' => $wamid_x, 'estado' => 'failed', 'codigo' => 131049],
	['wamid' => $wamid_b, 'estado' => 'sent'],
	['wamid' => $wamid_b, 'estado' => 'delivered'],
	['wamid' => $wamid_b, 'estado' => 'read'],
]], AT_REST_SECRET);
ok($res->get_status() === 200 && $res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 4], 'wamid desconocido y sent/delivered/read: todos ignorados');
ok(wa_estado_total_notas('whatsapp_no_entregado') === $total_fallos && count($correos) === 1, 'sin nota ni correo por lo ignorado');
// Revisión: el LIKE de la base no distingue mayúsculas (utf8mb4_unicode_520_ci) y el wamid es base64,
// donde sí importan. Un wamid igual al de B salvo en la caja, o con '_' (comodín de LIKE, válido en un
// wamid) en lugar de una letra, pasa el prefiltro o casi; lo que lo frena es la comparación exacta.
$wamid_b_caja = strtolower($wamid_b);
$wamid_b_comodin = substr_replace($wamid_b, '_', strpos($wamid_b, 'UlVFQ') + 4, 1);
$desconocidos = [$wamid_x, $wamid_b_caja, $wamid_b_comodin];
foreach (['otra caja' => $wamid_b_caja, 'comodín _' => $wamid_b_comodin] as $nombre => $w) {
	$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $w, 'estado' => 'failed', 'codigo' => 131049]]], AT_REST_SECRET);
	ok(at_cc_wamid_valido($w) && $res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 1], "wamid de B con {$nombre}: no se confunde con el de B");
}
ok(!wa_estado_notas((int) $pb->id, 'whatsapp_no_entregado') && count($correos) === 1, 'un wamid parecido al de B no le deja nota ni manda correo por B');

// ---------- 6) Cuerpos raros: 200 y nada indebido ----------
$raros = [
	'sin estados'            => [],
	'estados no arreglo'     => ['estados' => 'failed'],
	'estados número'         => ['estados' => 131049],
	'estados con basura'     => ['estados' => ['texto', null, 5, ['wamid' => ['x'], 'estado' => ['failed']], ['wamid' => 12345678901, 'estado' => 'failed']]],
	'wamid de 5 KB'          => ['estados' => [['wamid' => 'wamid.' . str_repeat('A', 5120), 'estado' => 'failed', 'codigo' => 131049]]],
	'wamid con salto'        => ['estados' => [['wamid' => $wamid_b . "\n", 'estado' => 'failed']]],
	'wamid con % (inválido)' => ['estados' => [['wamid' => 'wamid.HBgLNTY5MTExMTMzMzMVAgARGBJQUlVF%', 'estado' => 'failed']]],
];
// Un wamid con forma inválida ni siquiera llega a buscarse en la base (ni queda en espera). Se parte sin
// esas esperas por si una corrida anterior (p. ej. con una mutación) las dejó.
$invalidos = [$wamid_b . "\n", 'wamid.' . str_repeat('A', 5120)];
foreach ($invalidos as $w) {
	delete_transient(at_cc_clave_fallo_pendiente($w));
	$desconocidos[] = $w;
}
$consultas_wamid = [];
$espia = function ($q) use (&$consultas_wamid) {
	if (strpos($q, 'pedido_respuesta') !== false && stripos($q, 'LIKE') !== false) {
		$consultas_wamid[] = $q;
	}
	return $q;
};
add_filter('query', $espia);
foreach ($raros as $nombre => $cuerpo) {
	$res = wa_estado_pedir($ruta, $cuerpo, AT_REST_SECRET);
	ok($res->get_status() === 200 && ($res->get_data()['ok'] ?? null) === true && ($res->get_data()['avisados'] ?? -1) === 0, "cuerpo raro ({$nombre}): 200 sin avisos");
}
remove_filter('query', $espia);
ok(!$consultas_wamid, 'cuerpos raros con wamid inválido: no se busca ninguna nota en la base (' . count($consultas_wamid) . ' consultas)');
ok(get_transient(at_cc_clave_fallo_pendiente($invalidos[0])) === false && get_transient(at_cc_clave_fallo_pendiente($invalidos[1])) === false, 'un wamid inválido no queda en espera');
// 50 estados: solo se miran los primeros 20. Los 20 primeros son desconocidos y el wamid de B (que sí
// existe) va del 21 en adelante, así que no se toca.
$cincuenta = [];
for ($i = 0; $i < 50; $i++) {
	$cincuenta[] = ['wamid' => $i < 20 ? 'wamid.DESCONOCIDO' . str_pad((string) $i, 10, '0', STR_PAD_LEFT) : $wamid_b, 'estado' => 'failed', 'codigo' => 131049];
	if ($i < 20) {
		$desconocidos[] = $cincuenta[$i]['wamid'];
	}
}
$res = wa_estado_pedir($ruta, ['estados' => $cincuenta], AT_REST_SECRET);
ok($res->get_status() === 200 && $res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 50], '50 estados: solo se revisan 20, el resto se ignora');
ok(!wa_estado_notas((int) $pb->id, 'whatsapp_no_entregado') && wa_estado_total_notas('whatsapp_no_entregado') === $total_fallos && count($correos) === 1, 'los cuerpos raros no crean notas ni correos');
// Un JSON roto tampoco da 500 (WordPress responde 400 antes de llegar a la función).
$roto = wa_estado_pedir($ruta, '{"estados": [', AT_REST_SECRET)->get_status();
ok($roto >= 400 && $roto < 500, "JSON roto: {$roto}, nunca 500");

// ---------- 7) La nota nueva no aparece en la línea de tiempo pública del prospecto ----------
at_cc_anotar_simple($pa, 'nota', 'Nota visible de prueba', 'Esta nota sí debe verse en la línea de tiempo pública.');
$proc = proc_open([PHP_BINARY, __DIR__ . '/panel-timeline-wp-test-render.php', (string) $pa->id, (string) $pa->client_email], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
$html_pub = is_resource($proc) ? (string) stream_get_contents($tubos[1]) : '';
$err_pub = is_resource($proc) ? (string) stream_get_contents($tubos[2]) : '';
if (is_resource($proc)) { fclose($tubos[1]); fclose($tubos[2]); proc_close($proc); }
ok($html_pub !== '' && strpos($html_pub, 'Nota visible de prueba') !== false, 'la vista pública generó HTML con la nota normal: ' . trim($err_pub));
ok(strpos($html_pub, 'no le llegó al cliente') === false && strpos($html_pub, 'Meta limita') === false && strpos($html_pub, $wamid_a) === false, 'la línea de tiempo pública NO muestra la nota whatsapp_no_entregado');

// ---------- 8) Aviso en el panel «Respuesta del cliente» ----------
$h_a = wa_estado_panel($pa);
ok(strpos($h_a, 'no le llegó al cliente') !== false && strpos($h_a, 'mensajes de marketing') !== false && strpos($h_a, 'at-cc-wa-no-entregado') !== false, 'panel: avisa que el último WhatsApp automático no llegó, con el motivo');
ok(preg_match('#at-cc-wa-no-entregado.*?wa\.me/56911112222.*?Enviar por mi WhatsApp#s', $h_a) === 1, 'panel: el aviso trae el botón «Enviar por mi WhatsApp»');
ok(strpos(wa_estado_panel($pb), 'at-cc-wa-no-entregado') === false, 'panel: sin fallo avisado, sin aviso');
ok(strpos(wa_estado_panel($pc), 'at-cc-wa-no-entregado') === false, 'panel: envío sin wamid, sin aviso');
// B falla con un código raro y un título largo: se guarda recortado y el motivo usa el título de Meta.
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_b, 'estado' => 'FAILED', 'codigo' => '1e400', 'titulo' => str_repeat('Título largo ', 500)]]], AT_REST_SECRET);
$fallos_b = wa_estado_notas((int) $pb->id, 'whatsapp_no_entregado');
$meta_fb = $fallos_b ? json_decode((string) $fallos_b[0]->metadata, true) : [];
ok($res->get_data()['avisados'] === 1 && (int) ($meta_fb['codigo'] ?? -1) === 0 && mb_strlen((string) ($meta_fb['titulo'] ?? '')) <= 200, 'código absurdo queda en 0 y el título se recorta a 200');
ok(strpos(wa_estado_panel($pb), 'Meta dijo: «Título largo') !== false, 'panel: con un código desconocido muestra el título de Meta');
// Un envío nuevo a A deja atrás el aviso del anterior; y si ese envío nuevo falla, vuelve a aparecer.
// El fallo de A es un 131049 y, desde la revisión, frena el automático 24 horas: se da por viejo.
$wpdb->update($wpdb->prefix . 'automatiza_propuestas_details', ['created_at' => wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS - HOUR_IN_SECONDS)], ['propuesta_id' => (int) $pa->id, 'detail_type' => 'whatsapp_no_entregado']);
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $wamid_a2])];
at_cc_whatsapp_tras_pedido($pa);
ok(strpos(wa_estado_panel($pa), 'at-cc-wa-no-entregado') === false, 'panel: tras un envío nuevo, el aviso del anterior desaparece');
wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_a, 'estado' => 'failed', 'codigo' => 131049]]], AT_REST_SECRET);
ok(strpos(wa_estado_panel($pa), 'at-cc-wa-no-entregado') === false, 'panel: un fallo tardío del envío viejo no se confunde con el último');
wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_a2, 'estado' => 'failed', 'codigo' => 131026]]], AT_REST_SECRET);
$h_a2 = wa_estado_panel($pa);
ok(strpos($h_a2, 'at-cc-wa-no-entregado') !== false && strpos($h_a2, 'no tiene WhatsApp') !== false, 'panel: si el envío nuevo falla, vuelve el aviso con su motivo (131026)');
// Aceptada: el aviso ya no importa.
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'aceptada'], ['id' => (int) $pb->id]);
ok(strpos(wa_estado_panel($pb), 'at-cc-wa-no-entregado') === false, 'panel: en una propuesta aceptada no se muestra el aviso (el panel corta antes)');
// Archivada: el cliente ya no puede responder (Task 14). Esta es la que defiende la guarda $puede_pedir:
// A tiene un fallo avisado y, sin la guarda, mostraría el aviso con «Enviar por mi WhatsApp».
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'archivada'], ['id' => (int) $pa->id]);
ok(strpos(wa_estado_panel($pa), 'at-cc-wa-no-entregado') === false, 'panel: en una propuesta archivada no se muestra el aviso aunque el último WhatsApp no llegó');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'sent'], ['id' => (int) $pa->id]);
ok(strpos(wa_estado_panel($pa), 'at-cc-wa-no-entregado') !== false, 'panel: de vuelta en enviada, el aviso vuelve');

// ---------- 9) url_datos al aceptar por WhatsApp ----------
function wa_estado_responder(array $cuerpo) {
	return wa_estado_pedir('/at/v1/propuesta-respuesta', $cuerpo, AT_REST_SECRET)->get_data();
}
$pd = wa_estado_crear($marca, 'd', '+56 9 1111 5555', $creadas);
$esperada = add_query_arg('respuesta', 'completar', at_cc_url_respuesta(get_site_url(), (string) $pd->unique_link_id));
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd->unique_link_id, 'telefono' => '56911115555', 'wamid' => 'wamid.ACEPTAD0000001']);
ok(($r['ok'] ?? false) === true && ($r['estado'] ?? '') === 'aceptada' && at_cc_contrato_de_propuesta((int) $pd->id) !== null, 'acepta por WhatsApp: queda aceptada con contrato');
ok(($r['url_datos'] ?? '') === $esperada && strpos($esperada, 'ver-presentacion.php?id=' . $pd->unique_link_id . '&respuesta=completar') !== false, 'acepta por WhatsApp sin dirección: devuelve url_datos a «Datos para tu contrato»');
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd->unique_link_id, 'telefono' => '56911115555', 'wamid' => 'wamid.ACEPTAD0000002']);
ok(($r['motivo'] ?? '') === 'ya_aceptada' && ($r['url_datos'] ?? '') === $esperada, 'segundo «Acepto» mientras falte la dirección: vuelve a mandar url_datos');
$c = at_cc_contrato_de_propuesta((int) $pd->id);
$ph = json_decode((string) $c->placeholders, true) ?: [];
$wpdb->update($wpdb->prefix . 'automatiza_contracts', ['placeholders' => wp_json_encode(array_merge($ph, ['domicilio_cliente' => 'Calle Falsa 123, Santiago']))], ['id' => (int) $c->id]);
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd->unique_link_id, 'telefono' => '56911115555', 'wamid' => 'wamid.ACEPTAD0000003']);
ok(($r['ok'] ?? false) === true && !array_key_exists('url_datos', $r), 'con dirección en el contrato: sin url_datos');
$wpdb->update($wpdb->prefix . 'automatiza_contracts', ['placeholders' => wp_json_encode(array_merge($ph, ['revision_at' => '2026-09-27 10:00:00']))], ['id' => (int) $c->id]);
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd->unique_link_id, 'telefono' => '56911115555', 'wamid' => 'wamid.ACEPTAD0000004']);
ok(($r['ok'] ?? false) === true && !array_key_exists('url_datos', $r), 'contrato ya revisado por Luis: sin url_datos');
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_contracts WHERE proposal_id = %d", (int) $pd->id));
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd->unique_link_id, 'telefono' => '56911115555', 'wamid' => 'wamid.ACEPTAD0000005']);
ok(($r['ok'] ?? false) === true && !array_key_exists('url_datos', $r), 'sin contrato: sin url_datos');
$pe = wa_estado_crear($marca, 'e', '+56 9 1111 6666', $creadas);
$r = wa_estado_responder(['salida' => 'evalua', 'codigo' => $pe->unique_link_id, 'telefono' => '56911116666', 'wamid' => 'wamid.EVALUA00000001']);
ok(($r['estado'] ?? '') === 'evaluando' && !array_key_exists('url_datos', $r), '«La sigo evaluando»: sin url_datos');
// Revisión: la regla es salida 'acepta' Y aceptada. Con un contrato draft sin dirección (quedó de una
// aceptación anterior), «La sigo evaluando», «No, gracias» o un «Acepto» que no se aplica no mandan
// url_datos: el enlace abre «Datos para tu contrato» y el cliente no aceptó.
$pd2 = wa_estado_crear($marca, 'd2', '+56 9 1111 0000', $creadas);
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd2->unique_link_id, 'telefono' => '56911110000', 'wamid' => 'wamid.ACEPTAD2000001']);
ok(isset($r['url_datos']), 'contrato draft sin dirección para las pruebas siguientes');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'sent'], ['id' => (int) $pd2->id]);
ok(at_cc_url_datos_contrato(at_cc_propuesta_por_id((int) $pd2->id)) !== '', 'de vuelta en enviada, el contrato sigue admitiendo url_datos (lo que frena es la regla)');
$r = wa_estado_responder(['salida' => 'evalua', 'codigo' => $pd2->unique_link_id, 'telefono' => '56911110000', 'wamid' => 'wamid.EVALUA20000001']);
ok(($r['ok'] ?? false) === true && ($r['estado'] ?? '') === 'evaluando' && !array_key_exists('url_datos', $r), 'con contrato sin dirección, «La sigo evaluando»: sin url_datos');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'sent'], ['id' => (int) $pd2->id]);
$r = wa_estado_responder(['salida' => 'rechaza', 'codigo' => $pd2->unique_link_id, 'telefono' => '56911110000', 'wamid' => 'wamid.RECHAZ20000001']);
ok(($r['ok'] ?? false) === true && ($r['estado'] ?? '') === 'rechazada' && !array_key_exists('url_datos', $r), 'con contrato sin dirección, «No, gracias»: sin url_datos');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['status' => 'archivada'], ['id' => (int) $pd2->id]);
$r = wa_estado_responder(['salida' => 'acepta', 'codigo' => $pd2->unique_link_id, 'telefono' => '56911110000', 'wamid' => 'wamid.ACEPTAD2000002']);
ok(($r['ok'] ?? true) === false && !array_key_exists('url_datos', $r), 'con contrato sin dirección, «Acepto» en una archivada (no se aplica): sin url_datos');

// ---------- 10) Revisión de la Task 19 ----------
// Candado: si otra llamada está avisando el mismo wamid, esta no repite nota ni correo.
$pf = wa_estado_crear($marca, 'f', '+56 9 1111 7777', $creadas);
$wamid_f = 'wamid.HBgLNTY5MTExMTc3NzcVAgARGBJGRkZGRkZGRkZGRkZGRkZGAA==';
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $wamid_f])];
at_cc_whatsapp_tras_pedido($pf);
$candado_f = 'at_cc_wa_fallo_' . md5($wamid_f);
add_option($candado_f, '1', '', false);
$n_correos = count($correos);
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_f, 'estado' => 'failed', 'codigo' => 131049]]], AT_REST_SECRET);
ok($res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 1] && !wa_estado_notas((int) $pf->id, 'whatsapp_no_entregado') && count($correos) === $n_correos, 'candado tomado por otra llamada con el mismo wamid: esta no avisa');
delete_option($candado_f);
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_f, 'estado' => 'failed', 'codigo' => 131049]]], AT_REST_SECRET);
ok($res->get_data() === ['ok' => true, 'avisados' => 1, 'ignorados' => 0] && count(wa_estado_notas((int) $pf->id, 'whatsapp_no_entregado')) === 1 && count($correos) === $n_correos + 1, 'libre el candado: avisa una vez');
ok(get_option($candado_f, 'no-existe') === 'no-existe', 'el candado no queda guardado después de avisar');

// 131049 hace menos de 24 horas: ni «Pedir respuesta» ni el envío mandan otra plantilla.
$http_antes = count($pedidos_http);
$notas_f = count(wa_estado_notas((int) $pf->id, 'pedido_respuesta'));
$det_f = at_cc_whatsapp_tras_pedido($pf);
ok(count($pedidos_http) === $http_antes && count(wa_estado_notas((int) $pf->id, 'pedido_respuesta')) === $notas_f, '131049 reciente: «Pedir respuesta» no llama al flujo ni anota otro envío');
ok(strpos($det_f[0] ?? '', 'No se mandó el WhatsApp automático') !== false && strpos($det_f[0] ?? '', 'Enviar por mi WhatsApp') !== false, '131049 reciente: el detalle lo explica y ofrece «Enviar por mi WhatsApp»');
$aviso_f = at_cc_tras_envio($pf, true);
ok(count($pedidos_http) === $http_antes && strpos($aviso_f, 'No se mandó el WhatsApp automático') !== false && strpos($aviso_f, 'wa.me/56911117777') !== false, '131049 reciente: al enviar la propuesta tampoco sale la plantilla, y trae el botón');
$wpdb->update($wpdb->prefix . 'automatiza_propuestas_details', ['created_at' => wp_date('Y-m-d H:i:s', time() - DAY_IN_SECONDS - HOUR_IN_SECONDS)], ['propuesta_id' => (int) $pf->id, 'detail_type' => 'whatsapp_no_entregado']);
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => 'wamid.HBgLNTY5MTExMTc3NzcVAgARGBJGRkZGRkZGRkZGRkZGRkYyAA=='])];
at_cc_whatsapp_tras_pedido($pf);
ok(count($pedidos_http) === $http_antes + 1, 'pasadas 24 horas del 131049: vuelve a mandar la plantilla');

// El 'failed' llega antes que la nota del envío: queda en espera y se avisa al anotar el envío.
$pg = wa_estado_crear($marca, 'g', '+56 9 1111 8888', $creadas);
$wamid_g = 'wamid.HBgLNTY5MTExMTg4ODgVAgARGBJHR0dHR0dHR0dHR0dHR0dHAA==';
$desconocidos[] = $wamid_g;
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_g, 'estado' => 'failed', 'codigo' => 131026]]], AT_REST_SECRET);
ok($res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 1] && !wa_estado_notas((int) $pg->id, 'whatsapp_no_entregado') && is_array(get_transient(at_cc_clave_fallo_pendiente($wamid_g))), 'failed antes que la nota: cuenta como ignorado y queda en espera');
$n_correos = count($correos);
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $wamid_g])];
$aviso_g = at_cc_tras_envio($pg, true);
$fallos_g = wa_estado_notas((int) $pg->id, 'whatsapp_no_entregado');
ok(count($fallos_g) === 1 && (int) (json_decode((string) $fallos_g[0]->metadata, true)['codigo'] ?? 0) === 131026 && count($correos) === $n_correos + 1 && get_transient(at_cc_clave_fallo_pendiente($wamid_g)) === false, 'al anotar el envío, el fallo en espera se avisa (nota y correo) y sale de la espera');
ok(strpos($aviso_g, 'no se lo entregó al cliente') !== false && strpos($aviso_g, 'wa.me/56911118888') !== false, 'el aviso tras enviar dice que Meta no lo entregó y ofrece el botón');
ok(strpos(wa_estado_panel($pg), 'at-cc-wa-no-entregado') !== false, 'panel: muestra el aviso del fallo que llegó antes');
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_g, 'estado' => 'failed', 'codigo' => 131026]]], AT_REST_SECRET);
ok($res->get_data() === ['ok' => true, 'avisados' => 0, 'ignorados' => 1] && count($correos) === $n_correos + 1, 'el mismo failed otra vez: ya avisado, no se repite');
// Un fallo con otro código (131026) no frena el automático: solo el 131049 pide esperar 24 horas.
$http_antes = count($pedidos_http);
$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => 'wamid.HBgLNTY5MTExMTg4ODgVAgARGBJHR0dHR0dHR0dHR0dHR0cyAA=='])];
at_cc_whatsapp_tras_pedido($pg);
ok(count($pedidos_http) === $http_antes + 1, 'un fallo con otro código (131026) no frena el automático');

// Códigos fuera de rango (negativo o gigante) se guardan como 0.
$ph = wa_estado_crear($marca, 'h', '+56 9 1111 9999', $creadas);
$wamid_h1 = 'wamid.HBgLNTY5MTExMTk5OTkVAgARGBJISEhISEhISEhISEhISEgxAA==';
$wamid_h2 = 'wamid.HBgLNTY5MTExMTk5OTkVAgARGBJISEhISEhISEhISEhISEgyAA==';
foreach ([$wamid_h1, $wamid_h2] as $w) {
	$flujo = ['code' => 200, 'body' => wp_json_encode(['ok' => true, 'wamid' => $w])];
	at_cc_whatsapp_tras_pedido($ph);
}
$res = wa_estado_pedir($ruta, ['estados' => [['wamid' => $wamid_h1, 'estado' => 'failed', 'codigo' => -5], ['wamid' => $wamid_h2, 'estado' => 'failed', 'codigo' => 99999999999]]], AT_REST_SECRET);
$codigos_h = array_map(function ($n) { return json_decode((string) $n->metadata, true)['codigo'] ?? null; }, wa_estado_notas((int) $ph->id, 'whatsapp_no_entregado'));
ok($res->get_data()['avisados'] === 2 && $codigos_h === [0, 0], 'código negativo o gigante: se guarda como 0 (' . wp_json_encode($codigos_h) . ')');

// ---------- Limpieza ----------
foreach ($desconocidos as $w) {
	delete_transient(at_cc_clave_fallo_pendiente($w));
}
foreach ($creadas as $id) {
	$email = (string) $wpdb->get_var($wpdb->prepare("SELECT client_email FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
	$crm = $email !== '' ? at_cc_crm_de_email($email) : null;
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_contracts WHERE proposal_id = %d", $id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d", $id));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_clients_details WHERE propuesta_origin_id = %d", $id));
	if ($crm) {
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d", $crm));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE crm_cliente_id = %d", $crm));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm));
	}
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $id));
}
fin();
