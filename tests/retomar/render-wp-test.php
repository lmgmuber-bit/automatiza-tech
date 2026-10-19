<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_armar_render', 'at_rt_pedir_render', 'at_rt_rest_vista', 'at_rt_huella_render', 'at_rt_url_foto_valida');
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', bin2hex(random_bytes(24))); }
global $wpdb;
$ids = [];
$props = [];
register_shutdown_function(static function () use (&$ids, &$props, $wpdb) {
	foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
	foreach ($props as $id) { $wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $id]); }
});
foreach (['Uno', 'Otro'] as $nombre) {
	$ids[] = at_rt_crear(['nombre' => '[PRUEBA] ' . $nombre, 'negocio' => '[PRUEBA] Negocio', 'email' => 'render@example.com', 'telefono' => '+56900000000', 'demo_urls' => ['https://example.com/demo'], 'textos' => ['quedamos' => '[PRUEBA] Quedamos', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Prioridades']]);
}
$s = at_rt_obtener($ids[0]);
$p = at_rt_armar_render($s);
ok($p['document_type'] === 'retomar' && $p['unique_id'] === $s['token'] . '-retomar', 'payload identidad propia');
ok($p['agenda']['web_url'] === home_url('/retomar.php?t=' . $s['token'] . '&agendar=1'), 'agenda pública del seguimiento');
ok(strpos(wp_json_encode($p), 'easypanel') === false && strpos(wp_json_encode($p), at_rt_obtener($ids[1])['token']) === false, 'payload sin host interno ni otro seguimiento');
$s['render']['view_url'] = 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/';
ok(at_rt_armar_render($s) === $p, 'view interna guardada no contamina láminas');
if (!defined('AT_N8N_RETOMAR_RENDER')) {
	$antes = at_rt_obtener($ids[0]);
	ok(at_rt_pedir_render($ids[0]) === 'sin_flujo' && at_rt_obtener($ids[0]) === $antes, 'sin constante sin_flujo y sin cambios');
}
do_action('rest_api_init');
function rt_callback(int $id, array $body, bool $auth = true) {
	$body += ['generacion' => at_rt_obtener($id)['render']['generacion'] ?? ''];
	$r = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar/' . $id . '/vista');
	$r->set_header('Content-Type', 'application/json');
	if ($auth) { $r->set_header('X-AT-Secret', AT_REST_SECRET); }
	$r->set_body(wp_json_encode($body));
	return rest_do_request($r);
}
$generacion = str_repeat('a', 16);
at_rt_guardar($ids[0], ['render' => ['generacion' => $generacion, 'huella' => at_rt_huella_render($s), 'uid' => $s['token'] . '-' . $generacion . '-retomar', 'estado' => 'pendiente']]);
$base = 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-' . $generacion . '-retomar/';
ok(rt_callback($ids[0], ['ok' => true], false)->get_status() === 401, 'callback sin secreto 401');
ok(rt_callback($ids[0], ['ok' => true, 'view_url' => $base, 'pdf_url' => $base . 'presentation.pdf'])->get_status() === 200 && at_rt_obtener($ids[0])['render']['estado'] === 'listo' && at_rt_obtener($ids[0])['render']['view_url'] === $base, 'callback listo conserva vista propia');
foreach (['https://example.com/p/' . $s['token'] . '-retomar/', 'https://' . at_pt_host_renderer() . '/p/' . at_rt_obtener($ids[1])['token'] . '-retomar/', $base . '?iframe=1', str_replace('https:', 'http:', $base)] as $url) {
	ok(rt_callback($ids[0], ['ok' => true, 'view_url' => $url])->get_status() === 422, 'rechaza URL o UID ajeno y variantes inseguras');
}
ok(rt_callback($ids[0], ['ok' => false, 'nota' => '[PRUEBA] Falló'])->get_status() === 200 && at_rt_obtener($ids[0])['render']['estado'] === 'error', 'callback de fallo registra error');
ok(rt_callback($ids[0], ['ok' => true, 'view_url' => $base, 'pdf_url' => 'https://example.com/archivo.pdf'])->get_status() === 422, 'rechaza PDF ajeno aunque la vista sea propia');
ok(rt_callback(PHP_INT_MAX, ['ok' => false])->get_status() === 404, 'callback inexistente 404');
ok(rt_callback($ids[0], ['ok' => 'true', 'view_url' => $base])->get_status() === 422, 'ok exige booleano real');
foreach ([['quedamos' => str_repeat('á', 601)], ['partir' => implode("\n", array_fill(0, 7, 'a'))]] as $textos) {
	$invalido = $s; $invalido['textos'] = $textos;
	try { at_rt_armar_render($invalido); ok(false, 'rechaza exceso sin truncarlo'); }
	catch (InvalidArgumentException $e) { ok(true, 'rechaza exceso sin truncarlo'); }
}
$invalido = $s; $invalido['demo_urls'] = ['https://oculto.easypanel.host/demo'];
try { at_rt_armar_render($invalido); ok(false, 'rechaza URL interna en láminas'); }
catch (InvalidArgumentException $e) { ok(true, 'rechaza URL interna en láminas'); }
foreach (['nombre' => 121, 'negocio' => 121] as $campo => $max) {
	$invalido = $s; $invalido[$campo] = str_repeat('á', $max);
	try { at_rt_armar_render($invalido); ok(false, 'rechaza exceso identidad'); }
	catch (InvalidArgumentException $e) { ok(true, 'rechaza exceso identidad'); }
}
$invalido = $s; $invalido['textos']['proyecto'] = str_repeat('a', 181);
try { at_rt_armar_render($invalido); ok(false, 'rechaza exceso proyecto'); }
catch (InvalidArgumentException $e) { ok(true, 'rechaza exceso proyecto'); }
$invalido = $s; $invalido['demo_urls'] = array_fill(0, 7, 'https://example.com/demo');
try { at_rt_armar_render($invalido); ok(false, 'rechaza más de 6 demos'); }
catch (InvalidArgumentException $e) { ok(true, 'rechaza más de 6 demos'); }
if (!defined('AT_N8N_RETOMAR_RENDER')) { define('AT_N8N_RETOMAR_RENDER', 'https://example.com/prueba-render'); }
$capturado = null;
// Se conserva el bloqueo por defecto del bootstrap; SOLO esta URL obtiene respuesta simulada.
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$capturado) {
	if ($url !== AT_N8N_RETOMAR_RENDER) { return $pre; }
	$capturado = json_decode($args['body'], true);
	return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []];
}, PHP_INT_MAX, 3);
ok(at_rt_pedir_render($ids[0]) === '' && at_rt_obtener($ids[0])['render']['estado'] === 'pendiente', 'solicitud simulada marca pendiente');
ok($capturado['id'] === $ids[0] && $capturado['render']['unique_id'] === $s['token'] . '-' . ($capturado['generacion'] ?? '') . '-retomar' && preg_match('/^[a-f0-9]{16}$/D', $capturado['generacion'] ?? ''), 'n8n recibe id, generación y payload propios');
ok(strpos(wp_json_encode($capturado['render']), 'easypanel') === false, 'n8n recibe solo láminas públicas');
$pedido_a = $capturado;
at_rt_guardar($ids[0], ['textos' => ['quedamos' => '[PRUEBA] Versión B']]);
$base_a = 'https://' . at_pt_host_renderer() . '/p/' . $pedido_a['render']['unique_id'] . '/';
ok(rt_callback($ids[0], ['ok' => true, 'generacion' => $pedido_a['generacion'] ?? '', 'view_url' => $base_a])->get_status() === 409, 'callback A tras guardar B se rechaza');
ok(at_rt_pedir_render($ids[0]) === '', 'solicitud B simulada');
$pedido_b = $capturado;
ok($pedido_a['render']['unique_id'] !== $pedido_b['render']['unique_id'], 'A y B escriben carpetas independientes');
$base_b = 'https://' . at_pt_host_renderer() . '/p/' . $pedido_b['render']['unique_id'] . '/';
ok(rt_callback($ids[0], ['ok' => true, 'generacion' => $pedido_b['generacion'] ?? '', 'view_url' => $base_b])->get_status() === 200, 'callback B actual aceptado');
$render_b = at_rt_obtener($ids[0])['render'];
ok(rt_callback($ids[0], ['ok' => true, 'generacion' => $pedido_a['generacion'] ?? '', 'view_url' => $base_a])->get_status() === 409 && at_rt_obtener($ids[0])['render'] === $render_b, 'callback A tardío no reemplaza B listo');
ok(rt_callback($ids[0], ['ok' => false, 'generacion' => $pedido_a['generacion'] ?? '', 'nota' => 'fallo A'])->get_status() === 409 && at_rt_obtener($ids[0])['render'] === $render_b, 'fallo A tardío no altera B listo');
at_rt_guardar($ids[0], ['textos' => ['quedamos' => str_repeat('a', 601)]]);
$capturado = null;
ok(at_rt_pedir_render($ids[0]) !== '' && $capturado === null && at_rt_obtener($ids[0])['render']['estado'] === 'error', 'error claro por límite antes de llamar n8n');
ok(is_wp_error(wp_remote_get('https://example.com/no-permitido')), 'HTTP externo sigue bloqueado por defecto');
// ---- Fase 2: encargos de foto, fotos reutilizadas, recomendación y propuesta solo si es v3 ----
function rt_url_foto(array $seg, string $generacion, string $archivo = 'portada.jpg', ?string $host = null): string {
	return 'https://' . ($host ?? at_pt_host_renderer()) . '/p/' . $seg['token'] . '-' . $generacion . '-retomar/img/' . $archivo;
}
function rt_semilla_propuesta(array $extra = []): array {
	global $wpdb, $props;
	$uid = substr(md5(uniqid('rt', true)), 0, 12);
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', $extra + ['client_email' => 'render-prop@example.com', 'client_name' => '[PRUEBA]', 'company_name' => '[PRUEBA]', 'unique_link_id' => $uid, 'status' => 'sent', 'gamma_prompt_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
	$props[] = (int) $wpdb->insert_id;
	return [(int) $wpdb->insert_id, $uid];
}
$prompt_p = '[PRUEBA] Oficina luminosa con una mesa de trabajo ordenada, sin texto ni letreros';
$prompt_c = '[PRUEBA] Mesa de reunión al atardecer con dos tazas de café, sin texto ni etiquetas';
$ids[] = $enc = at_rt_crear(['nombre' => '[PRUEBA] Encargos', 'negocio' => '[PRUEBA] Negocio', 'email' => 'render-fotos@example.com', 'telefono' => '+56900000001', 'demo_urls' => ['https://example.com/demo'], 'textos' => ['quedamos' => '[PRUEBA] Quedamos', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Prioridades', 'recomendacion' => '[PRUEBA] Recomendamos partir por lo más urgente.']]);
at_rt_guardar($enc, ['redaccion' => ['estado' => 'lista', 'origen' => 'ia', 'image_briefs' => [['slide' => 'portada', 'prompt' => $prompt_p], ['slide' => 'cierre', 'prompt' => $prompt_c]]]]);
$f = at_rt_obtener($enc);
$p1 = at_rt_armar_render($f);
ok(array_column($p1['image_briefs'], 'prompt', 'slide') === ['portada' => $prompt_p, 'cierre' => $prompt_c], 'encargos nuevos van en image_briefs');
ok(!array_key_exists('images', $p1) && !property_exists(json_decode(wp_json_encode($p1)), 'images'), 'sin fotos guardadas no viaja images (el renderer exige un objeto, no una lista)');
ok($p1['recomendacion'] === '[PRUEBA] Recomendamos partir por lo más urgente.', 'la recomendación viaja al renderer');
ok(at_rt_armar_render($s)['recomendacion'] === '' && at_rt_armar_render($s)['image_briefs'] === [], 'sin recomendación ni encargos viajan vacíos');

$url_p = rt_url_foto($f, str_repeat('c', 16));
at_rt_guardar($enc, ['fotos' => ['portada' => ['url' => $url_p, 'hash' => hash('sha256', $prompt_p)]]]);
$f2 = at_rt_obtener($enc);
$p2 = at_rt_armar_render($f2);
ok($p2['images'] === ['portada' => $url_p] && array_column($p2['image_briefs'], 'slide') === ['cierre'], 'encargo igual al guardado va en images con su URL y no en image_briefs');
ok(is_object(json_decode(wp_json_encode($p2))->images), 'images viaja como objeto JSON');
ok(strpos(wp_json_encode($p2), at_pt_host_renderer()) !== false, 'la URL del renderer en images no dispara el chequeo D8 del payload');
ok(at_rt_huella_render($f2) === at_rt_huella_render($f), 'reutilizar fotos no cambia la huella');
$f3 = $f; $f3['fotos'] = ['portada' => ['url' => rt_url_foto($f, str_repeat('d', 16), 'otra.png'), 'hash' => hash('sha256', $prompt_p)], 'cierre' => ['url' => rt_url_foto($f, str_repeat('d', 16), 'cierre.webp'), 'hash' => hash('sha256', $prompt_c)]];
ok(at_rt_huella_render($f3) === at_rt_huella_render($f) && at_rt_armar_render($f3)['image_briefs'] === [], 'otras URL de fotos con el mismo encargo dejan la misma huella y nada que generar');
$cambia = $f2; $cambia['redaccion']['image_briefs'][0]['prompt'] = $prompt_p . ' más cálida';
$pc = at_rt_armar_render($cambia);
ok(array_column($pc['image_briefs'], 'slide') === ['portada', 'cierre'] && !isset($pc['images']), 'encargo cambiado (hash distinto) se vuelve a pedir y no se reutiliza la foto vieja');
ok(at_rt_huella_render($cambia) !== at_rt_huella_render($f2), 'cambiar un encargo cambia la huella');
$cambia = $f2; $cambia['textos']['recomendacion'] = '[PRUEBA] Otra recomendación.';
ok(at_rt_huella_render($cambia) !== at_rt_huella_render($f2), 'cambiar la recomendación cambia la huella');
$mixto = $f2; $mixto['fotos']['cierre'] = ['url' => rt_url_foto($f, str_repeat('c', 16), 'cierre.jpg'), 'hash' => hash('sha256', 'encargo viejo')];
$pm = at_rt_armar_render($mixto);
ok(array_keys($pm['images']) === ['portada'] && array_column($pm['image_briefs'], 'slide') === ['cierre'], 'foto con hash de otro encargo no se reutiliza');
foreach ([
	'foto de otro seguimiento' => ['url' => rt_url_foto(at_rt_obtener($ids[1]), str_repeat('c', 16)), 'hash' => hash('sha256', $prompt_p)],
	'foto de otro host' => ['url' => rt_url_foto($f, str_repeat('c', 16), 'portada.jpg', 'example.com'), 'hash' => hash('sha256', $prompt_p)],
	'foto sin hash' => ['url' => $url_p],
	'foto con dato corrupto' => 'no es un arreglo',
] as $caso => $foto) {
	$malo = $f; $malo['fotos'] = ['portada' => $foto];
	$pmalo = at_rt_armar_render($malo);
	ok(!isset($pmalo['images']) && array_column($pmalo['image_briefs'], 'slide') === ['portada', 'cierre'], "{$caso}: no se reutiliza y el encargo se vuelve a pedir");
}
foreach ([
	'lámina desconocida' => [['slide' => 'final', 'prompt' => 'x']],
	'lámina repetida' => [['slide' => 'portada', 'prompt' => 'x'], ['slide' => 'portada', 'prompt' => 'y']],
	'prompt vacío' => [['slide' => 'portada', 'prompt' => '  ']],
	'prompt de más de 1200' => [['slide' => 'portada', 'prompt' => str_repeat('a', 1201)]],
	'más de cuatro encargos' => [['slide' => 'portada', 'prompt' => 'a'], ['slide' => 'quedamos', 'prompt' => 'b'], ['slide' => 'cierre', 'prompt' => 'c'], ['slide' => 'demo', 'prompt' => 'd'], ['slide' => 'demo', 'prompt' => 'e']],
	'encargo que no es un objeto' => ['portada'],
] as $caso => $briefs) {
	$malo = $f; $malo['redaccion']['image_briefs'] = $briefs;
	try { at_rt_armar_render($malo); ok(false, "rechaza encargos inválidos: {$caso}"); }
	catch (InvalidArgumentException $e) { ok(true, "rechaza encargos inválidos: {$caso}"); }
}
// Un encargo de la cuarta lámina «quedamos» viaja como cualquier otro y se reutiliza por hash.
$cuatro = $f; $cuatro['redaccion']['image_briefs'] = [['slide' => 'portada', 'prompt' => $prompt_p], ['slide' => 'quedamos', 'prompt' => 'q'], ['slide' => 'cierre', 'prompt' => $prompt_c], ['slide' => 'demo', 'prompt' => 'd']];
ok(array_column(at_rt_armar_render($cuatro)['image_briefs'], 'slide') === ['portada', 'quedamos', 'cierre', 'demo'], 'cuatro encargos (con quedamos) son válidos y viajan al renderer');
foreach (['recomendación de más de 300' => str_repeat('á', 301), 'recomendación de más de 3 líneas' => "a\nb\nc\nd"] as $caso => $texto) {
	$malo = $f; $malo['textos']['recomendacion'] = $texto;
	try { at_rt_armar_render($malo); ok(false, "rechaza {$caso}"); }
	catch (InvalidArgumentException $e) { ok(true, "rechaza {$caso}"); }
}
$borde = $f; $borde['textos']['recomendacion'] = str_repeat('á', 300);
ok(at_rt_armar_render($borde)['recomendacion'] === str_repeat('á', 300), 'recomendación de 300 caracteres pasa');
$invalido = $f; $invalido['demo_urls'] = ['https://oculto.easypanel.host/demo'];
try { at_rt_armar_render($invalido); ok(false, 'con fotos en images, easypanel en demo_urls se sigue rechazando'); }
catch (InvalidArgumentException $e) { ok(true, 'con fotos en images, easypanel en demo_urls se sigue rechazando'); }
$invalido = $f2; $invalido['demo_urls'] = ['https://oculto.easypanel.host/demo'];
try { at_rt_armar_render($invalido); ok(false, 'con fotos en images, easypanel en demo_urls se sigue rechazando'); }
catch (InvalidArgumentException $e) { ok(true, 'con fotos en images, easypanel en demo_urls se sigue rechazando'); }

// La propuesta vinculada solo se enlaza si es v3; la URL externa del seguimiento siempre se respeta.
[$prop_null, $uid_null] = rt_semilla_propuesta();
$wpdb->update($wpdb->prefix . 'automatiza_propuestas', ['flujo' => null], ['id' => $prop_null]);
[$prop_old, $uid_old] = rt_semilla_propuesta(['flujo' => 'v2']);
[$prop_v3, $uid_v3] = rt_semilla_propuesta(['flujo' => 'v3']);
$https_local = static fn($url) => preg_replace('~^http:~', 'https:', $url); // el sitio local va por http; el payload exige https
add_filter('home_url', $https_local);
$con = fn(array $extra) => at_rt_armar_render(array_merge($f2, $extra))['propuesta_url'];
ok($con(['propuesta_id' => $prop_null]) === '', 'propuesta vinculada con flujo NULL: sin propuesta_url');
ok($con(['propuesta_id' => $prop_old]) === '', 'propuesta vinculada con otro flujo: sin propuesta_url');
ok($con(['propuesta_id' => $prop_v3]) === home_url('/ver-presentacion.php?id=' . rawurlencode($uid_v3)), 'propuesta v3: ver-presentacion.php?id=...');
ok($con(['propuesta_id' => 0]) === '' && $con(['propuesta_id' => PHP_INT_MAX]) === '', 'sin propuesta o inexistente: sin propuesta_url');
ok($con(['propuesta_id' => $prop_null, 'propuesta_url' => 'https://example.com/mi-propuesta']) === 'https://example.com/mi-propuesta', 'propuesta externa con propuesta vinculada no v3: se enlaza la externa');
ok($con(['propuesta_id' => $prop_v3, 'propuesta_url' => 'https://example.com/mi-propuesta']) === 'https://example.com/mi-propuesta', 'la propuesta externa del seguimiento gana sobre la v3');
ok(strpos(wp_json_encode(at_rt_armar_render(array_merge($f2, ['propuesta_id' => $prop_null]))), $uid_null) === false && strpos(wp_json_encode(at_rt_armar_render(array_merge($f2, ['propuesta_id' => $prop_old]))), $uid_old) === false, 'el código de una propuesta no v3 no aparece en el payload');
remove_filter('home_url', $https_local);

// Callback con fotos: pedido real contra el flujo simulado, luego respuestas del renderer.
// El aviso «recordatorio corregido» es el real (at_rt_aviso_listo): se cuentan los correos que manda con pre_wp_mail.
exigir('at_rt_aviso_listo');
$GLOBALS['rt_avisos'] = [];
add_filter('pre_wp_mail', static function ($v, $d) {
	if (str_starts_with((string) ($d['subject'] ?? ''), '🔁 Retomar · Recordatorio corregido: ')) { $GLOBALS['rt_avisos'][] = $d; }
	return true;
}, 100, 2);
/** Asuntos de los avisos enviados. */
function rt_avisos(): array { return array_column($GLOBALS['rt_avisos'], 'subject'); }
$aviso_cb = '🔁 Retomar · Recordatorio corregido: [PRUEBA] Callback fotos';
$ids[] = $cb = at_rt_crear(['nombre' => '[PRUEBA] Callback fotos', 'negocio' => '[PRUEBA] Negocio', 'email' => 'render-cb@example.com', 'telefono' => '+56900000002', 'estado' => 'activo', 'demo_urls' => ['https://example.com/demo'], 'textos' => ['quedamos' => '[PRUEBA] Quedamos', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Prioridades']]);
at_rt_guardar($cb, ['redaccion' => ['estado' => 'lista', 'image_briefs' => [['slide' => 'portada', 'prompt' => $prompt_p], ['slide' => 'cierre', 'prompt' => $prompt_c]]], 'aprobacion' => ['estado' => 'cambios', 'n' => 1]]);
$capturado = null;
ok(at_rt_pedir_render($cb) === '' && count($capturado['render']['image_briefs']) === 2 && !isset($capturado['render']['images']), 'el pedido a n8n lleva los dos encargos y ninguna foto');
$pedido1 = $capturado;
$render1 = at_rt_obtener($cb)['render'];
ok($render1['huella'] === at_rt_huella_render(at_rt_obtener($cb)), 'la huella guardada del pedido cubre textos y encargos');
$seg_cb = at_rt_obtener($cb);
$gen1 = $pedido1['generacion'];
$vista1 = 'https://' . at_pt_host_renderer() . '/p/' . $pedido1['render']['unique_id'] . '/';
$ok_base = ['ok' => true, 'view_url' => $vista1];
$otro = at_rt_obtener($ids[1]);
$malos = [
	'URL de otro seguimiento' => ['portada' => rt_url_foto($otro, $gen1)],
	'URL de otro host' => ['portada' => rt_url_foto($seg_cb, $gen1, 'portada.jpg', 'example.com')],
	'URL http' => ['portada' => str_replace('https:', 'http:', rt_url_foto($seg_cb, $gen1))],
	'URL de una generación anterior' => ['portada' => rt_url_foto($seg_cb, str_repeat('e', 16))],
	'URL sin generación' => ['portada' => 'https://' . at_pt_host_renderer() . '/p/' . $seg_cb['token'] . '-retomar/img/portada.jpg'],
	'extensión no permitida' => ['portada' => rt_url_foto($seg_cb, $gen1, 'portada.svg')],
	'subcarpeta' => ['portada' => rt_url_foto($seg_cb, $gen1, 'a/portada.jpg')],
	'ruta con ..' => ['portada' => rt_url_foto($seg_cb, $gen1, '../portada.jpg')],
	'parámetros en la URL' => ['portada' => rt_url_foto($seg_cb, $gen1) . '?x=1'],
	'una buena y una ajena' => ['portada' => rt_url_foto($seg_cb, $gen1), 'cierre' => rt_url_foto($otro, $gen1, 'cierre.jpg')],
	'lámina desconocida' => ['final' => rt_url_foto($seg_cb, $gen1, 'final.jpg')],
	'lámina sin encargo vigente' => ['demo' => rt_url_foto($seg_cb, $gen1, 'demo.jpg')],
	'URL que no es texto' => ['portada' => ['x']],
	'fotos que no es un objeto' => 'portada',
	'fotos como lista' => [rt_url_foto($seg_cb, $gen1)],
];
foreach ($malos as $caso => $fotos) {
	ok(rt_callback($cb, $ok_base + ['fotos' => $fotos])->get_status() === 422, "callback con fotos inválidas: {$caso} da 422");
}
$despues = at_rt_obtener($cb);
ok($despues['fotos'] === [] && $despues['render']['estado'] === 'pendiente' && $despues['render'] === $render1, 'los 422 no guardan fotos ni tocan el render');
ok($GLOBALS['rt_avisos'] === [], 'sin render listo no se avisa');
ok(rt_callback($cb, ['ok' => false, 'nota' => '[PRUEBA] Falló', 'fotos' => ['portada' => rt_url_foto($seg_cb, $gen1)]])->get_status() === 200 && at_rt_obtener($cb)['render']['estado'] === 'error' && $GLOBALS['rt_avisos'] === [], 'un fallo del render no avisa');
ok(array_keys(at_rt_obtener($cb)['fotos']) === ['portada'], 'una foto válida ya pagada se conserva aunque el render haya fallado');
at_rt_guardar($cb, ['render' => $render1, 'fotos' => []]);
ok(rt_callback($cb, $ok_base + ['fotos' => ['portada' => rt_url_foto($seg_cb, $gen1), 'cierre' => rt_url_foto($seg_cb, $gen1, 'cierre.webp')]])->get_status() === 200, 'callback con fotos del seguimiento y la generación vigente: 200');
$g1 = at_rt_obtener($cb);
ok($g1['fotos'] === ['portada' => ['url' => rt_url_foto($seg_cb, $gen1), 'hash' => hash('sha256', $prompt_p)], 'cierre' => ['url' => rt_url_foto($seg_cb, $gen1, 'cierre.webp'), 'hash' => hash('sha256', $prompt_c)]], 'cada foto se guarda con url y hash de su encargo vigente');
ok($g1['render']['estado'] === 'listo' && $g1['render']['view_url'] === $vista1, 'el render queda listo');
ok(rt_avisos() === [$aviso_cb], 'render listo tras «Pedir cambios»: se avisa una vez, con el recordatorio de este seguimiento');
$ap_cb = at_rt_obtener($cb)['aprobacion'];
ok(($ap_cb['estado'] ?? '') === 'por_aprobar' && ($ap_cb['usada'] ?? null) === false && ($ap_cb['huella'] ?? '') === $g1['render']['huella'] && str_contains($GLOBALS['rt_avisos'][0]['message'] ?? '', 'retomar-aprobar.php'), 'el aviso emite una aprobación nueva (sale de «cambios») y trae sus botones');
ok(stripos($GLOBALS['rt_avisos'][0]['message'] ?? '', 'easypanel') === false && stripos($GLOBALS['rt_avisos'][0]['message'] ?? '', at_pt_host_renderer()) === false, 'el aviso no lleva el host del renderer (D8)');
ok(rt_callback($cb, $ok_base + ['fotos' => ['portada' => rt_url_foto($seg_cb, $gen1)]])->get_status() === 200 && rt_avisos() === [$aviso_cb], 'repetir el mismo callback no vuelve a avisar');

// Segundo pedido sin cambios: las fotos viajan en images, no hay nada que generar y la huella es la misma.
$huella1 = $render1['huella'];
$capturado = null;
ok(at_rt_pedir_render($cb) === '' && array_keys($capturado['render']['images']) === ['portada', 'cierre'] && $capturado['render']['image_briefs'] === [], 'segundo pedido: las fotos viajan en images y no hay encargos');
ok($capturado['render']['images']['portada'] === rt_url_foto($seg_cb, $gen1) && at_rt_obtener($cb)['render']['huella'] === $huella1, 'segundo pedido: misma huella con las fotos reutilizadas');
$pedido2 = $capturado;
// El renderer devuelve solo la portada (la del cierre se perdió): la foto del cierre guardada no se borra.
$nueva_p = rt_url_foto($seg_cb, $pedido2['generacion']);
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $pedido2['render']['unique_id'] . '/', 'fotos' => ['portada' => $nueva_p]])->get_status() === 200, 'callback que devuelve solo una foto: 200');
$g2 = at_rt_obtener($cb)['fotos'];
ok($g2['portada']['url'] === $nueva_p && $g2['cierre'] === $g1['fotos']['cierre'], 'la lámina que falta en el callback conserva la foto guardada');
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $pedido2['render']['unique_id'] . '/'])->get_status() === 200 && at_rt_obtener($cb)['fotos'] === $g2, 'callback sin fotos no toca las guardadas');
// Cambiar un solo encargo: solo ese se vuelve a pedir y la huella cambia.
at_rt_guardar($cb, ['redaccion' => ['estado' => 'lista', 'image_briefs' => [['slide' => 'portada', 'prompt' => $prompt_p], ['slide' => 'cierre', 'prompt' => $prompt_c . ' con luz cálida']]]]);
$capturado = null;
ok(at_rt_pedir_render($cb) === '' && array_keys($capturado['render']['images']) === ['portada'] && array_column($capturado['render']['image_briefs'], 'slide') === ['cierre'], 'encargo cambiado: la portada se reutiliza y solo el cierre se vuelve a pedir');
ok(at_rt_obtener($cb)['render']['huella'] !== $huella1, 'encargo cambiado: otra huella');
$pedido3 = $capturado;
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $pedido3['render']['unique_id'] . '/', 'fotos' => ['cierre' => rt_url_foto($seg_cb, $pedido3['generacion'], 'cierre.png')]])->get_status() === 200, 'callback con la foto del encargo nuevo');
$g3 = at_rt_obtener($cb)['fotos'];
ok($g3['cierre']['hash'] === hash('sha256', $prompt_c . ' con luz cálida') && $g3['portada'] === $g2['portada'], 'el hash sigue al encargo vigente y la otra lámina queda igual');
// Sin «Pedir cambios» ni una aprobación vencida no se avisa.
$avisos_antes = rt_avisos();
at_rt_guardar($cb, ['aprobacion' => ['estado' => 'por_aprobar', 'n' => 2]]);
$capturado = null;
ok(at_rt_pedir_render($cb) === '', 'pedido con la aprobación por aprobar');
$pedido4 = $capturado;
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $pedido4['render']['unique_id'] . '/'])->get_status() === 200 && at_rt_obtener($cb)['render']['estado'] === 'listo' && rt_avisos() === $avisos_antes, 'render listo con aprobación por aprobar: no se avisa');
// Ruling 11: la aprobación venció por contenido (por_aprobar con los enlaces muertos) y la presentación nueva queda lista:
// se avisa para no perder una semana.
at_rt_guardar($cb, ['aprobacion' => ['estado' => 'por_aprobar', 'n' => 1, 'nonce' => str_repeat('c', 16), 'huella' => 'vieja', 'usada' => true, 'vencida_at' => '2026-10-19 10:30:00']]);
$capturado = null;
ok(at_rt_pedir_render($cb) === '', 'pedido de la presentación nueva tras vencer la aprobación');
$pedido5 = $capturado;
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $pedido5['render']['unique_id'] . '/'])->get_status() === 200 && rt_avisos() === array_merge($avisos_antes, [$aviso_cb]), 'aprobación vencida y presentación nueva lista: se avisa una vez');
$ap_cb = at_rt_obtener($cb)['aprobacion'];
ok(($ap_cb['estado'] ?? '') === 'por_aprobar' && ($ap_cb['usada'] ?? null) === false && ($ap_cb['nonce'] ?? '') !== str_repeat('c', 16), 'el aviso deja una aprobación nueva con enlaces vivos');
// Un aviso que no corresponde (seguimiento en pausa) no se manda y no hace fracasar el callback.
$avisos_antes = rt_avisos();
at_rt_guardar($cb, ['estado' => 'pausado', 'aprobacion' => ['estado' => 'cambios', 'n' => 1, 'usada' => true]]);
$capturado = null;
ok(at_rt_pedir_render($cb) === '', 'pedido con el seguimiento en pausa');
$pedido6 = $capturado;
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $pedido6['render']['unique_id'] . '/'])->get_status() === 200 && at_rt_obtener($cb)['render']['estado'] === 'listo' && rt_avisos() === $avisos_antes && at_rt_obtener($cb)['aprobacion']['estado'] === 'cambios', 'seguimiento en pausa: el render queda listo, no se avisa ni se emite');

// ---- Ruling 17 (C1) e I4: un pedido nuevo de presentación nunca borra la versión publicada y anota cuándo se pidió ----
$pub = ['view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $seg_cb['token'] . '-' . str_repeat('9', 16) . '-retomar/index.html', 'pdf_url' => '', 'huella' => 'publicada', 'generacion' => str_repeat('9', 16)];
at_rt_guardar($cb, ['aprobacion' => [], 'render' => array_merge(at_rt_obtener($cb)['render'], ['publicada' => $pub, 'reintento_at' => '2026-10-19 09:05:00'])]);
$capturado = null;
ok(at_rt_pedir_render($cb, 5, '2026-10-19 11:05:00') === '' && is_array($capturado), 'pedido de la presentación con la hora del reloj');
$rp = at_rt_obtener($cb)['render'];
ok(($rp['publicada'] ?? null) === $pub && ($rp['reintento_at'] ?? '') === '2026-10-19 09:05:00' && ($rp['pedido_at'] ?? '') === '2026-10-19 11:05:00' && $rp['estado'] === 'pendiente' && !isset($rp['view_url']), 'pedirla conserva la versión publicada y la marca del reintento, anota pedido_at y deja de mostrar la vigente vieja');
ok(rt_callback($cb, ['ok' => false, 'nota' => '[PRUEBA] Falló'])->get_status() === 200 && at_rt_obtener($cb)['render']['estado'] === 'error' && (at_rt_obtener($cb)['render']['publicada'] ?? null) === $pub && (at_rt_obtener($cb)['render']['pedido_at'] ?? '') === '2026-10-19 11:05:00', 'un callback de error conserva la versión publicada y cuándo se pidió');
$capturado = null;
ok(at_rt_pedir_render($cb) === '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string) (at_rt_obtener($cb)['render']['pedido_at'] ?? '')) === 1, 'sin hora inyectada, pedido_at es la hora de Chile');
ok(rt_callback($cb, ['ok' => true, 'view_url' => 'https://' . at_pt_host_renderer() . '/p/' . $capturado['render']['unique_id'] . '/'])->get_status() === 200 && at_rt_obtener($cb)['render']['estado'] === 'listo' && (at_rt_obtener($cb)['render']['publicada'] ?? null) === $pub, 'un callback listo conserva la versión publicada');
at_rt_guardar($cb, ['textos' => array_merge(at_rt_obtener($cb)['textos'], ['quedamos' => str_repeat('a', 601)])]);
$capturado = null;
ok(at_rt_pedir_render($cb, 15, '2026-10-19 12:05:00') !== '' && $capturado === null && at_rt_obtener($cb)['render']['estado'] === 'error' && (at_rt_obtener($cb)['render']['publicada'] ?? null) === $pub && (at_rt_obtener($cb)['render']['pedido_at'] ?? '') === '2026-10-19 12:05:00', 'contenido que no se puede presentar: error con la versión publicada intacta');
ok(at_rt_render_flujo() === AT_N8N_RETOMAR_RENDER, 'at_rt_render_flujo devuelve la URL del flujo de presentación');
fin();
