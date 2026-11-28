<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/cierre-correo-wp-test.php
// Estado del proyecto, Etapa 3 — Task 3: correo de cierre (prueba y cliente), reclamo atómico, D8 y WhatsApp.
// Nunca sale un correo ni una llamada de red real: pre_wp_mail y pre_http_request los capturan.
if (!defined('WP_ADMIN')) { define('WP_ADMIN', true); }
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_enviar_cierre', 'at_av_registrar_whatsapp_cierre', 'at_av_url_whatsapp_cierre', 'at_av_anotar_envio');
at_av_migrar_esquema();
wp_set_current_user(pt_admin_id());
$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-cierre-correo@example.com');
$id = (int) ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-cierre-correo@example.com'))->id;
ptc_sembrar($id);
ptc_estado($id, 'enviado');
$cod = (string) at_pt_plan($id)->codigo;
$correos = [];
$mail_ok = true;
// Se anota lo que pide el sitio ANTES de los filtros de desarrollo (que reescriben destinatario y asunto) y se corta el
// envío después de fixtures-panel.php (que también corta en PHP_INT_MAX; el registrado último manda).
add_filter('wp_mail', function ($a) use (&$correos) { $correos[] = $a; return $a; }, PHP_INT_MIN);
add_filter('pre_wp_mail', function () use (&$mail_ok) { return $mail_ok; }, PHP_INT_MAX);
// El correo solo dibuja enlaces https (cierre-puras.php): el sitio local es http, así que se prueba como en PROD.
add_filter('home_url', fn($u) => preg_replace('#^http://#', 'https://', (string) $u), 99);
$pdf_resp = '%PDF-1.4 prueba';
$pedidas = [];
add_filter('pre_http_request', function ($pre, $args, $url) use (&$pdf_resp, &$pedidas) {
	if (str_contains($url, 'presentation.pdf')) {
		$pedidas[] = $url;
		return ['headers' => [], 'body' => $pdf_resp, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
	}
	return new WP_Error('sin_red_en_pruebas', 'la prueba no sale a la red');
}, PHP_INT_MAX, 3);
$leer = fn(): array => at_av_leer(at_pt_plan($id));
$envio = fn(): array => at_av_envio_de_cierre(at_av_cierre_de_avance($leer(), 'cprueba'));
$archivo = ['formato' => 'at-cierre-v1', 'plan' => $cod, 'cierre' => ['titulo' => 'Diseño terminado', 'bloques' => ['Diseño'], 'fecha_real' => '2026-10-10',
	'antes' => ['texto' => 'Sin maqueta.', 'puntos' => ['Sin maqueta'], 'evidencias' => ['visitas']], 'despues' => ['texto' => 'Maqueta aprobada.', 'puntos' => ['Maqueta aprobada'], 'evidencias' => ['sitio']]],
	'evidencias' => [['ref' => 'visitas', 'tipo' => 'cifra', 'titulo' => 'Visitas', 'valor' => '10', 'fuente' => 'GA4', 'fecha' => '2026-10-09'], ['ref' => 'sitio', 'tipo' => 'enlace', 'titulo' => 'Sitio', 'url' => 'https://example.com/']]];
$f = at_pt_plan($id);
$v = at_av_validar_import($archivo, $cod, at_pt_payload($f), at_av_leer($f));
$av = at_av_aplicar_import(at_av_leer($f), $v, 'cprueba', '2026-10-14 10:00:00')['avance'];
at_av_guardar($id, $av);

$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'no_publicado' && $correos === [], '1) un borrador no se envía');
$r = at_av_enviar_cierre($id, 'nohay', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'sin_cierre' && $correos === [], '1) un cierre que no existe no se envía');
$av = at_av_cambiar_estado_cierre($leer(), 'cprueba', 'publicado', '2026-10-14 10:05:00');
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'render_no_listo' && $correos === [] && $pedidas === [] && $envio()['correo']['enviando'] === null, '2) publicado sin presentación lista: al cliente no, y no queda reclamo (Review Focus 2)');
$r = at_av_enviar_cierre($id, 'cprueba', 'prueba', 'Hola equipo', false);
$ultimo = end($correos);
ok($r['ok'] && count($correos) === 1 && str_starts_with($ultimo['subject'], '[Prueba] Terminamos: Diseño terminado') && $ultimo['to'] === at_cc_correo_avisos() && $ultimo['attachments'] === [], '3) la prueba va a Luis, sin PDF si no hay presentación');
ok(str_contains($ultimo['message'], 'Hola equipo') && str_contains($ultimo['message'], 'ver-estado.php?id=' . $cod), '3) la prueba lleva el mensaje y el enlace al estado');
ok($envio()['correo']['prueba_at'] !== null && $envio()['correo']['enviado_at'] === null, '3) la prueba anota prueba_at y no enviado_at');
ok($envio()['correo']['mensaje'] === 'Hola equipo', '3) la prueba guarda el mensaje de Luis para traerlo al enviar al cliente');

$av = $leer();
$av['render'] = array_merge(at_av_registro_vacio(), ['estado' => 'listo', 'at' => '2026-10-14 10:06:00', 'view_url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $cod . '-estado/index.html', 'pdf_url' => 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/' . $cod . '-estado/presentation.pdf']);
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', 'Gracias por la paciencia', false);
$ultimo = end($correos);
ok($r['ok'] && $ultimo['to'] === 'prueba-cierre-correo@example.com' && $ultimo['subject'] === 'Terminamos: Diseño terminado — ' . (at_pt_payload(at_pt_plan($id))['proyecto'] ?? '') && count($ultimo['attachments']) === 1, '4) al cliente: asunto exacto y PDF adjunto');
ok(!str_contains($ultimo['message'], 'easypanel') && in_array('Reply-To: ' . at_cc_correo_avisos(), (array) $ultimo['headers'], true), '4) sin enlaces internos y con Reply-To');
$e = $envio()['correo'];
ok($e['enviado_at'] !== null && $e['enviando'] === null && $e['a'] === 'prueba-cierre-correo@example.com' && $e['mensaje'] === 'Gracias por la paciencia' && $e['prueba_at'] !== null, '4) queda anotado cuándo, a quién y con qué mensaje (y la prueba anterior sigue)');
ok(count($pedidas) === 1 && str_ends_with($pedidas[0], '-estado/presentation.pdf'), '4) el PDF sale de la presentación «-estado»');
$n = count($correos);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', false);
ok(!$r['ok'] && $r['motivo'] === 'ya_enviado' && count($correos) === $n, '5) un segundo clic sin confirmar no reenvía (Review Focus 1)');
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok($r['ok'] && count($correos) === $n + 1, '5) reenviar con confirmación sí');

// Reclamo atómico: otro proceso dejó el envío «en curso» hace un momento.
$av = $leer();
foreach ($av['cierres'] as $k => $x) { if ($x['id'] === 'cprueba') { $av['cierres'][$k]['correo']['enviando'] = current_time('mysql'); } }
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$r['ok'] && $r['motivo'] === 'enviando' && count($correos) === $n + 1 && $envio()['correo']['enviando'] !== null, '6) mientras otro envío está en curso no sale otro y su reclamo no se pisa (Review Focus 1)');
// Un reclamo viejo (más de AT_AV_ENVIO_VENCE) se considera caído.
$av = $leer();
foreach ($av['cierres'] as $k => $x) { if ($x['id'] === 'cprueba') { $av['cierres'][$k]['correo']['enviando'] = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - AT_AV_ENVIO_VENCE - 5); } }
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok($r['ok'] && count($correos) === $n + 2 && $envio()['correo']['enviando'] === null, '6) un reclamo vencido se retoma y queda liberado');

// Fallo del correo: se libera el reclamo y no se anota como enviado.
$antes_at = $envio()['correo']['enviado_at'];
$mail_ok = false;
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
$mail_ok = true;
ok(!$r['ok'] && $r['motivo'] === 'correo_fallo' && $envio()['correo']['enviando'] === null && $envio()['correo']['enviado_at'] === $antes_at, '6) si el correo falla se libera el reclamo y no se anota como enviado');

// D8: una evidencia apunta al servidor interno.
$n = count($correos);
$av = $leer();
$av['evidencias']['cprueba-sitio']['url'] = 'https://n8n-propuesta-renderer.kchiba.easypanel.host/p/x/';
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'prueba', '', false);
$r2 = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$r['ok'] && $r['motivo'] === 'interno' && str_contains($r['detalle'], 'easypanel') && !$r2['ok'] && $r2['motivo'] === 'interno' && count($correos) === $n && $envio()['correo']['enviando'] === null, '7) D8: con un enlace interno no sale nada (ni prueba ni cliente) y dice cuál (Review Focus 4)');
$av['evidencias']['cprueba-sitio']['url'] = 'https://example.com/';
at_av_guardar($id, $av);

// PDF muy grande: sale sin adjunto y lo dice.
$pdf_resp = '%PDF' . str_repeat('x', AT_PT_PDF_MAX_BYTES + 10);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok($r['ok'] && $r['sin_pdf'] !== '' && end($correos)['attachments'] === [], '8) PDF de más de 15 MB: va solo el enlace y se avisa');
$pdf_resp = '%PDF-1.4 prueba';

// Copia oculta: el correo al cliente lleva el Bcc de los ajustes; la prueba (a nosotros) no. Sin escribir la opción en la base.
$copia = fn() => 'copia-prueba@example.com';
add_filter('pre_option_at_cc_correo_copia', $copia);
$r_cli = at_av_enviar_cierre($id, 'cprueba', 'cliente', 'Mensaje enviado', true);
$h_cli = (array) end($correos)['headers'];
$r_pru = at_av_enviar_cierre($id, 'cprueba', 'prueba', 'Mensaje de prueba', false);
$h_pru = (array) end($correos)['headers'];
remove_filter('pre_option_at_cc_correo_copia', $copia);
ok($r_cli['ok'] && in_array('Bcc: copia-prueba@example.com', $h_cli, true), '9b) el correo al cliente lleva la copia oculta de los ajustes');
ok($r_pru['ok'] && !preg_grep('/^Bcc:/i', $h_pru), '9b) la prueba no lleva copia oculta');
ok($envio()['correo']['mensaje'] === 'Mensaje enviado', '9b) una prueba posterior al envío al cliente no pisa el mensaje que de verdad se mandó');
$sin_copia = fn() => '';
add_filter('pre_option_at_cc_correo_copia', $sin_copia);
$r_sin = at_av_enviar_cierre($id, 'cprueba', 'cliente', 'Sin copia', true);
remove_filter('pre_option_at_cc_correo_copia', $sin_copia);
ok($r_sin['ok'] && !preg_grep('/^Bcc:/i', (array) end($correos)['headers']), '9b) sin copia configurada no hay Bcc');

// Fix round 1 · D8 también en el texto: el mensaje de Luis y los textos del cierre llegan al correo del cliente.
$guardado = $leer();
$n = count($correos);
$p0 = count($pedidas);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', 'Mira https://x.kchiba.easypanel.host/p/a', true);
$r2 = at_av_enviar_cierre($id, 'cprueba', 'prueba', 'Mira https://x.kchiba.easypanel.host/p/a', false);
ok(!$r['ok'] && $r['motivo'] === 'interno' && str_contains($r['detalle'], 'el mensaje') && !$r2['ok'] && $r2['motivo'] === 'interno' && count($correos) === $n && count($pedidas) === $p0 && $envio()['correo']['enviando'] === null, '10) D8: un mensaje con easypanel no sale (cliente ni prueba), sin descarga ni reclamo');
$av = $leer();
foreach ($av['cierres'] as $k => $x) { if ($x['id'] === 'cprueba') { $av['cierres'][$k]['despues']['puntos'][0] = 'Míralo en https://x.kchiba.easypanel.host/'; } }
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$r['ok'] && $r['motivo'] === 'interno' && str_contains($r['detalle'], 'Después') && count($correos) === $n && count($pedidas) === $p0 && $envio()['correo']['enviando'] === null, '10) D8: un punto del cierre con easypanel no sale y dice en qué parte');
at_av_guardar($id, $guardado);

// Reclamado el envío, todo se recalcula del avance releído: si entre la primera lectura y el reclamo alguien metió un
// enlace interno en el cierre, no sale y el reclamo se suelta (se simula en la primera consulta de home_url).
$gatillo = true;
add_filter('home_url', function ($u) use (&$gatillo, $id, $leer) {
	if ($gatillo) {
		$gatillo = false;
		$x = $leer();
		foreach ($x['cierres'] as $k => $c2) { if ($c2['id'] === 'cprueba') { $x['cierres'][$k]['despues']['puntos'][0] = 'Míralo en https://x.kchiba.easypanel.host/'; } }
		at_av_guardar($id, $x);
	}
	return $u;
}, 100);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$gatillo && !$r['ok'] && $r['motivo'] === 'interno' && count($correos) === $n && count($pedidas) === $p0 && $envio()['correo']['enviando'] === null, '10) lo que cambia entre la lectura y el reclamo se vuelve a revisar y el reclamo se suelta');
at_av_guardar($id, $guardado);

// Render viejo: el cierre se republicó después de la presentación.
$av = $leer();
foreach ($av['cierres'] as $k => $x) { if ($x['id'] === 'cprueba') { $av['cierres'][$k]['publicado_at'] = '2026-10-14 10:10:00'; } }
at_av_guardar($id, $av);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$r['ok'] && $r['motivo'] === 'render_no_listo' && count($correos) === $n && count($pedidas) === $p0 && $envio()['correo']['enviando'] === null, '11) presentación anterior a la republicación: al cliente no, sin descarga ni reclamo (Review Focus 2)');
$r = at_av_enviar_cierre($id, 'cprueba', 'prueba', '', false);
ok($r['ok'] && count($correos) === $n + 1 && end($correos)['attachments'] === [] && $r['sin_pdf'] !== '' && count($pedidas) === $p0, '11) la prueba sí sale, sin PDF');
at_av_guardar($id, $guardado);
$n = count($correos);

// Un fallo inesperado al armar o enviar suelta el reclamo y dice qué pasó.
$boom = true;
add_filter('wp_mail', function ($a) use (&$boom) { if ($boom) { throw new RuntimeException('boom'); } return $a; }, PHP_INT_MIN + 1);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
$boom = false;
ok(!$r['ok'] && $r['motivo'] === 'correo_fallo' && str_starts_with($r['detalle'], 'error interno al armar o enviar el correo: RuntimeException: boom') && $envio()['correo']['enviando'] === null, '12) una excepción suelta el reclamo y queda dicha en el detalle');

// Una excepción al recalcular lo que usa el correo, ya reclamado el envío, también suelta el reclamo y se dice (se tira solo mientras el reclamo existe).
$tira = true;
$n_antes = count($correos);
$filtro_tira = function ($u) use (&$tira, $leer) {
	if ($tira && at_av_envio_de_cierre((array) at_av_cierre_de_avance($leer(), 'cprueba'))['correo']['enviando'] !== null) {
		throw new LogicException('boom2');
	}
	return $u;
};
add_filter('home_url', $filtro_tira, 101);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
$tira = false;
remove_filter('home_url', $filtro_tira, 101);
ok(!$r['ok'] && $r['motivo'] === 'correo_fallo' && str_contains($r['detalle'], 'LogicException: boom2') && $envio()['correo']['enviando'] === null && count($correos) === $n_antes, '12b) una excepción tras reclamar, al recalcular el correo, suelta el reclamo, no envía y dice qué pasó');

// Sin correo del cliente.
global $wpdb;
$sin_ph = function (array $claves) use ($id, $wpdb) { // la ficha manda; si falta, el contrato: se quitan los dos para probar «sin dato»
	$ct = $wpdb->prefix . 'automatiza_contracts';
	$cid = (int) at_pt_plan($id)->contrato_id;
	$ph = json_decode((string) $wpdb->get_var($wpdb->prepare("SELECT placeholders FROM {$ct} WHERE id = %d", $cid)), true) ?: [];
	foreach ($claves as $k) { unset($ph[$k]); }
	$wpdb->update($ct, ['placeholders' => wp_json_encode($ph)], ['id' => $cid]);
};
$sin_ph(['email_cliente', 'telefono_cliente']);
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => ''], ['id' => $c['crm']]);
$n = count($correos);
$r = at_av_enviar_cierre($id, 'cprueba', 'cliente', '', true);
ok(!$r['ok'] && $r['motivo'] === 'sin_correo' && count($correos) === $n && $envio()['correo']['enviando'] === null, '8) la ficha sin correo: no sale y no queda reclamo');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => 'prueba-cierre-correo@example.com'], ['id' => $c['crm']]);

// anotar_envio mezcla sin pisar lo que ya había.
$antes = $envio();
ok(at_av_anotar_envio($id, 'cprueba', ['mensaje' => 'Otro texto']) && $envio()['correo']['mensaje'] === 'Otro texto' && $envio()['correo']['a'] === $antes['correo']['a'], '8) anotar un campo no borra los demás');
ok(at_av_anotar_envio($id, 'cprueba', ['mensaje' => 'Otro texto']), '8) anotar lo mismo que ya hay es un éxito, no un fallo');
ok(!at_av_anotar_envio($id, 'nohay', ['mensaje' => 'x']), '8) un cierre inexistente no se anota');

// WhatsApp (la ficha de prueba tiene teléfono: +56 9 1111 1111).
$url = at_av_url_whatsapp_cierre(at_pt_plan($id), 'cprueba');
$texto = rawurldecode($url);
ok(str_starts_with($url, 'https://wa.me/56911111111?text=') && str_contains($texto, 'terminamos Diseño terminado') && str_contains($texto, 'ver-estado.php?id='), '9) WhatsApp: wa.me con el teléfono de la ficha y el texto del cierre');
ok(at_av_url_whatsapp_cierre(at_pt_plan($id), 'nohay') === '', '9) WhatsApp: sin cierre, vacío');
$r = at_av_registrar_whatsapp_cierre($id, 'cprueba');
ok($r['ok'] && $r['url'] === $url && $envio()['whatsapp']['abierto_at'] !== null && $envio()['correo']['a'] === 'prueba-cierre-correo@example.com', '9) registrar WhatsApp anota abierto_at sin tocar el correo');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['telefono' => ''], ['id' => $c['crm']]);
$r = at_av_registrar_whatsapp_cierre($id, 'cprueba');
ok(!$r['ok'] && $r['motivo'] === 'sin_telefono' && $r['url'] === '', '9) sin teléfono en la ficha: no se registra nada');
$av = $leer();
$av['cierres'][0]['estado'] = 'borrador';
at_av_guardar($id, $av);
$r = at_av_registrar_whatsapp_cierre($id, 'cprueba');
ok(!$r['ok'] && $r['motivo'] === 'no_publicado', '9) un borrador no se registra por WhatsApp');
fin();
