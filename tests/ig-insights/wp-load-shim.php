<?php
// Puente de prueba: hace que api-omnichannel.php (copiado a una carpeta temporal) cargue el
// WordPress LOCAL con un secret y un canal de prueba, y con la Graph API de Instagram simulada.
// Lo copia rutas-http-wp-test.php como wp-load.php de esa carpeta. Nunca se sube a producción.
if (getenv('IGT_SECRET') !== false && getenv('IGT_SECRET') !== '' && empty($_GET['sin_secreto'])) {
	define('AT_IG_METRICS_SECRET', getenv('IGT_SECRET'));
}
define('AT_IG_METRICS_CHANNEL_ID', (int) getenv('IGT_CHANNEL'));
require getenv('AT_WP_LOAD');
add_filter('pre_wp_mail', static fn() => true);
add_filter('pre_http_request', static function ($pre, $args, $url) {
	if (strpos($url, 'https://graph.instagram.com/') !== 0) {
		return new WP_Error('prueba_local', 'HTTP externo bloqueado en pruebas.');
	}
	$path = preg_replace('#^/v[0-9.]+/#', '', (string) wp_parse_url($url, PHP_URL_PATH));
	$json = static function ($code, $body) {
		return ['headers' => [], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'filename' => null];
	};
	if ($path === 'me') {
		return $json(200, ['id' => '9001', 'user_id' => '17841400000000000', 'username' => 'automatizatech.cl', 'followers_count' => 42, 'follows_count' => 5, 'media_count' => 1]);
	}
	if ($path === 'me/media') {
		return $json(200, ['data' => [['id' => 'm-http-1', 'caption' => 'Reel de prueba', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS',
			'timestamp' => gmdate('Y-m-d\TH:i:s', time() - 5 * HOUR_IN_SECONDS) . '+0000', 'permalink' => 'https://www.instagram.com/reel/z/', 'like_count' => 4, 'comments_count' => 1]]]);
	}
	if ($path === 'me/stories') {
		return $json(200, ['data' => []]);
	}
	// Token sin el permiso de insights: el caso real más probable al desplegar.
	return $json(400, ['error' => ['message' => '(#10) Application does not have permission for this action', 'type' => 'OAuthException', 'code' => 10]]);
}, PHP_INT_MAX, 3);
