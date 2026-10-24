<?php
// Puente de prueba: hace que webhook-omnichannel.php y api-omnichannel.php (copiados a una carpeta
// temporal) carguen el WordPress LOCAL con un secret y un canal de prueba, y con la Graph API de
// Instagram simulada. Lo copia rutas-http-wp-test.php como wp-load.php de esa carpeta. Nunca se sube a producción.
if (getenv('IGC_SECRET') !== false && getenv('IGC_SECRET') !== '' && empty($_GET['sin_secreto'])) {
	define('AT_IG_COMMENTS_SECRET', getenv('IGC_SECRET'));
}
define('AT_IG_METRICS_CHANNEL_ID', (int) getenv('IGC_CHANNEL'));
require getenv('AT_WP_LOAD');
add_filter('pre_wp_mail', static fn() => true);
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://graph.instagram.com/') !== 0) {
		return new WP_Error('prueba_local', 'HTTP externo bloqueado en pruebas.');
	}
	$method = $args['method'] ?? 'GET';
	$path = preg_replace('#^/v[0-9.]+/#', '', (string) wp_parse_url($url, PHP_URL_PATH));
	$body = isset($args['body']) ? json_decode($args['body'], true) : null;
	$json = static function ($code, $b) {
		return ['headers' => [], 'body' => wp_json_encode($b), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
	};
	if ($method === 'GET' && $path === 'me') {
		return $json(200, ['user_id' => '17841400000000002', 'id' => '9002', 'username' => 'automatizatech.cl']);
	}
	if ($method === 'GET' && $path === 'me/media') {
		return $json(200, ['data' => [['id' => 'm-http', 'timestamp' => gmdate('Y-m-d\TH:i:s', time() - 3600) . '+0000', 'comments_count' => 1]]]);
	}
	if ($method === 'GET' && $path === 'm-http/comments') {
		return $json(200, ['data' => [['id' => 'c-http-poll', 'text' => 'Spa ✨', 'timestamp' => gmdate('Y-m-d\TH:i:s', time() - 120) . '+0000',
			'from' => ['id' => 'u-http-2', 'username' => 'cliente.poll']]]]);
	}
	if ($method === 'POST' && preg_match('#^([^/]+)/replies$#', $path, $m)) {
		return $json(200, ['id' => 'r-' . $m[1]]);
	}
	if ($method === 'POST' && $path === 'me/messages' && isset($body['recipient']['comment_id']) && !isset($body['recipient']['id'])) {
		return $json(200, ['recipient_id' => 'igsid-' . $body['recipient']['comment_id'], 'message_id' => 'mid-' . $body['recipient']['comment_id']]);
	}
	// Relectura del comentario (GET /<comment_id>): lo que Meta dice de verdad.
	$cdata = [
		'c-http-1' => ['text' => 'método', 'from' => ['id' => 'u-http-1', 'username' => 'cliente.http']],
		'c-http-2' => ['text' => 'MÉTODO', 'from' => ['id' => '17841400000000002', 'username' => 'automatizatech.cl']],
		'c-http-4' => ['text' => 'hola, buen video', 'from' => ['id' => 'u-http-4', 'username' => 'otra.persona']],
	];
	if ($method === 'GET' && isset($cdata[$path])) {
		return $json(200, ['id' => $path, 'timestamp' => gmdate('Y-m-d\TH:i:s') . '+0000', 'media' => ['id' => 'm-http']] + $cdata[$path]);
	}
	if ($method === 'GET' && strpos($path, 'igsid-') === 0) {
		return $json(200, ['name' => '', 'username' => 'cliente.http']);
	}
	return $json(400, ['error' => ['message' => 'Ruta no simulada', 'type' => 'OAuthException', 'code' => 1]]);
}, PHP_INT_MAX, 3);
