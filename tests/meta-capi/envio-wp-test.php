<?php
// Envío del Lead a Meta desde el servidor. Uso: AT_WP_LOAD=<ruta> php tests/meta-capi/envio-wp-test.php
// Carga el módulo de ESTA copia del repo sobre el WordPress local. Ninguna llamada sale a internet: pre_http_request
// las captura. La configuración de PixelYourSite no se toca: se reemplaza con el filtro at_capi_config.
require __DIR__ . '/../cierre/wp-bootstrap.php';
$raiz = __DIR__ . '/../..';
require $raiz . '/wp-content/themes/automatiza-tech/inc/meta-capi/cargar.php';

$marca = strtolower(wp_generate_password(8, false));
$correo = function (string $n) use ($marca) { return "prueba-capi-{$marca}-{$n}@example.com"; };
$GLOBALS['capi_llamadas'] = [];
$GLOBALS['capi_respuesta'] = ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{"events_received":1}', 'headers' => [], 'cookies' => []];
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (strpos($url, 'graph.facebook.com') === false) { return $pre; }
	$GLOBALS['capi_llamadas'][] = ['url' => $url, 'args' => $args];
	return $GLOBALS['capi_respuesta'];
}, 10, 3);
$cfg = ['activo' => true, 'token' => 'token-de-prueba-capi', 'pixel' => '123456', 'test' => ''];
add_filter('at_capi_config', function () use (&$cfg) { return $cfg; }, 99);
$limpiar = [];
register_shutdown_function(function () use (&$limpiar) { foreach ($limpiar as $c) { delete_transient('at_capi_lead_' . hash('sha256', $c)); } });
$log = wp_tempnam('capi-log');
ini_set('error_log', $log);

$guardar = function (int $id, array $datos) use (&$limpiar) {
	$limpiar[] = at_capi_norm_correo((string) ($datos['email'] ?? ''));
	$GLOBALS['capi_llamadas'] = [];
	do_action('automatiza_tech_lead_guardado', $id, $datos);
	return $GLOBALS['capi_llamadas'];
};
$web = ['name' => 'Ana Prueba', 'email' => strtoupper($correo('a')), 'phone' => '+56 9 1234 5678', 'source' => 'web',
	'scheduled_date' => '2026-11-03', 'scheduled_time' => '10:00:00', 'token' => 'no-va-a-meta'];

// 1) Agenda web nueva: una sola llamada, bien armada
$ll = $guardar(901, $web);
ok(count($ll) === 1, 'web nueva: una llamada a Meta');
$url = $ll[0]['url'] ?? '';
ok($url === 'https://graph.facebook.com/' . AT_CAPI_GRAPH_VERSION . '/123456/events', 'URL de la Graph API con la versión y el píxel');
ok(strpos($url, 'token') === false && strpos($url, 'access_token') === false, 'el token no va en la URL');
$cuerpo = json_decode((string) ($ll[0]['args']['body'] ?? ''), true) ?: [];
ok(($cuerpo['access_token'] ?? '') === 'token-de-prueba-capi', 'el token va en el cuerpo');
// Revisión final: con blocking=false WordPress igual espera curl_exec y no ve errores; se espera la respuesta, con tope.
ok(($ll[0]['args']['blocking'] ?? false) === true && ($ll[0]['args']['timeout'] ?? 99) <= 3, 'espera la respuesta de Meta, con tope de 3 s');
$e = $cuerpo['data'][0] ?? [];
ok(($e['event_name'] ?? '') === 'Lead' && ($e['action_source'] ?? '') === 'other', 'Lead con action_source other');
ok(($e['event_id'] ?? '') === at_capi_event_id($web['email'], '2026-11-03', '10:00'), 'event_id igual al del navegador');
$crudo = (string) ($ll[0]['args']['body'] ?? '');
ok(stripos($crudo, $correo('a')) === false && strpos($crudo, '12345678') === false && strpos($crudo, 'Ana Prueba') === false && strpos($crudo, 'no-va-a-meta') === false,
	'ni correo, ni teléfono, ni nombre, ni token del lead en claro');
ok(!isset($cuerpo['test_event_code']), 'sin código de prueba cuando PYS no tiene');

// 2) Segunda agenda del mismo correo (otra hora): no vuelve a contar
ok(count($guardar(902, ['scheduled_time' => '15:00:00'] + $web)) === 0, 'mismo correo en 30 días: no llama');

// 3) Instagram: chat
$ll = $guardar(903, ['email' => $correo('b'), 'phone' => '+58 414 1234567', 'source' => 'instagram', 'scheduled_date' => '2026-11-04', 'scheduled_time' => '11:00:00']);
$ei = json_decode((string) ($ll[0]['args']['body'] ?? ''), true)['data'][0] ?? [];
ok(count($ll) === 1 && ($ei['action_source'] ?? '') === 'chat' && ($ei['custom_data']['content_name'] ?? '') === 'Agenda (Instagram)', 'instagram: chat');

// 4) Código de prueba de PYS
$cfg['test'] = 'TEST4242';
$ll = $guardar(904, ['email' => $correo('c'), 'source' => 'whatsapp', 'scheduled_date' => '2026-11-05', 'scheduled_time' => '12:00:00']);
ok((json_decode((string) ($ll[0]['args']['body'] ?? ''), true)['test_event_code'] ?? '') === 'TEST4242', 'con código de prueba, va en el cuerpo');
$cfg['test'] = '';

// 5) Interruptor apagado o sin token: nada
$cfg['activo'] = false;
ok(count($guardar(905, ['email' => $correo('d')] + $web)) === 0, 'at_capi_activo apagado: no llama');
$cfg['activo'] = true; $cfg['token'] = '';
ok(count($guardar(906, ['email' => $correo('e')] + $web)) === 0, 'sin token: no llama');
$cfg['token'] = 'token-de-prueba-capi'; $cfg['pixel'] = '';
ok(count($guardar(907, ['email' => $correo('e2')] + $web)) === 0, 'sin píxel: no llama');
$cfg['pixel'] = '123456';
ok(count($guardar(908, ['email' => ''] + $web)) === 0, 'sin correo: no llama');

// 6) Falla de red: no queda marcado, el próximo intento sí llama; el log no lleva datos personales
$GLOBALS['capi_respuesta'] = new WP_Error('http_request_failed', 'caída simulada');
$ll = $guardar(909, ['email' => $correo('f')] + $web);
ok(count($ll) === 1, 'con la red caída igual intenta una vez');
$GLOBALS['capi_respuesta'] = ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{}', 'headers' => [], 'cookies' => []];
ok(count($guardar(910, ['email' => $correo('f')] + $web)) === 1, 'tras una falla el correo no quedó marcado: vuelve a intentar');
// Meta responde con error (token inválido, evento rechazado): no se marca y el log lleva el código HTTP
$GLOBALS['capi_respuesta'] = ['response' => ['code' => 400, 'message' => 'Bad Request'], 'body' => '{"error":{"message":"Invalid OAuth access token"}}', 'headers' => [], 'cookies' => []];
ok(count($guardar(911, ['email' => $correo('g')] + $web)) === 1, 'Meta responde 400: intentó una vez');
$GLOBALS['capi_respuesta'] = ['response' => ['code' => 200, 'message' => 'OK'], 'body' => '{}', 'headers' => [], 'cookies' => []];
ok(count($guardar(912, ['email' => $correo('g')] + $web)) === 1, 'tras un 400 el correo no quedó marcado: vuelve a intentar');
ok(count($guardar(913, ['email' => $correo('g')] + $web)) === 0, 'tras un 200 el correo sí quedó marcado');
$texto_log = (string) @file_get_contents($log);
ok(strpos($texto_log, '[at-capi] lead=909') !== false, 'la falla queda en el log con el id del lead');
ok(strpos($texto_log, '[at-capi] lead=911 origen=web http=400') !== false, 'el rechazo de Meta queda en el log con su código HTTP');
ok(strpos($texto_log, 'Invalid OAuth') === false, 'el log no copia la respuesta de Meta');
ok(stripos($texto_log, $correo('f')) === false && strpos($texto_log, 'token-de-prueba') === false, 'el log no lleva correo ni token');

// 7) El interruptor nace apagado y la configuración real se lee sin romper
remove_all_filters('at_capi_config');
$real = at_capi_config();
ok(is_array($real) && array_key_exists('token', $real) && array_key_exists('pixel', $real), 'at_capi_config lee PYS sin errores');
ok(get_option('at_capi_activo', null) !== '1' || $real['activo'] === true, 'el interruptor sale de la opción at_capi_activo');

// 8) /leads dispara la acción después de guardar; /followup-meetings no
$fuente = (string) file_get_contents($raiz . '/wp-content/themes/automatiza-tech/inc/api-endpoints.php');
ok(preg_match('/\$lead_id = \(int\) \$wpdb->insert_id;\s*\n\s*do_action\(\'automatiza_tech_lead_guardado\', \$lead_id, \$data\);/', $fuente) === 1,
	'api-endpoints.php: do_action justo después del insert de /leads');
ok(substr_count($fuente, 'automatiza_tech_lead_guardado') === 1, 'la acción se dispara en un solo lugar');
// Revisión final: todo archivo que api-endpoints.php carga con require_once __DIR__ debe estar en git (si no, main queda roto)
preg_match_all("/require_once __DIR__ \. '\/([a-z0-9_-]+\.php)'/", $fuente, $req);
$en_git = (string) shell_exec('git -C ' . escapeshellarg($raiz) . ' ls-files wp-content/themes/automatiza-tech/inc');
foreach (array_unique($req[1]) as $archivo) {
	ok(strpos($en_git, 'inc/' . $archivo) !== false, "api-endpoints.php requiere $archivo y está en git");
}
$seg = (string) file_get_contents($raiz . '/wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php');
ok(strpos($seg, 'automatiza_tech_lead_guardado') === false, 'las reuniones de seguimiento no disparan el Lead');

@unlink($log);
fin();
