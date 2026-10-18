<?php
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_armar_render', 'at_rt_pedir_render', 'at_rt_rest_vista');
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', bin2hex(random_bytes(24))); }
global $wpdb;
$ids = [];
register_shutdown_function(static function () use (&$ids, $wpdb) {
	foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
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
	$r = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar/' . $id . '/vista');
	$r->set_header('Content-Type', 'application/json');
	if ($auth) { $r->set_header('X-AT-Secret', AT_REST_SECRET); }
	$r->set_body(wp_json_encode($body));
	return rest_do_request($r);
}
$base = 'https://' . at_pt_host_renderer() . '/p/' . $s['token'] . '-retomar/';
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
ok($capturado['id'] === $ids[0] && $capturado['render']['unique_id'] === $s['token'] . '-retomar', 'n8n recibe id y payload propios');
ok(strpos(wp_json_encode($capturado['render']), 'easypanel') === false, 'n8n recibe solo láminas públicas');
at_rt_guardar($ids[0], ['textos' => ['quedamos' => str_repeat('a', 601)]]);
$capturado = null;
ok(at_rt_pedir_render($ids[0]) !== '' && $capturado === null && at_rt_obtener($ids[0])['render']['estado'] === 'error', 'error claro por límite antes de llamar n8n');
ok(is_wp_error(wp_remote_get('https://example.com/no-permitido')), 'HTTP externo sigue bloqueado por defecto');
fin();
