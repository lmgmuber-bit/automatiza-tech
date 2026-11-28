<?php
// Uso interno de ia-wp-test.php: corre en un proceso aparte SIN clave de OpenAI (la constante OPENAI_API_KEY queda vacía).
require __DIR__ . '/wp-bootstrap.php';
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (strpos($url, 'api.openai.com') !== false) {
		echo "LLAMO_A_OPENAI\n";
	}
	return $pre;
}, 99, 3);
echo 'CLAVE ' . (at_en_ia_clave() === '' ? 'VACIA' : 'PRESENTE') . "\n";
$r = at_en_ia_llamar([['role' => 'user', 'content' => 'hola']], 'gpt-4o-mini');
echo 'CODIGO ' . (is_wp_error($r) ? $r->get_error_code() : 'sin-error') . "\n";
echo 'MENSAJE ' . (is_wp_error($r) ? $r->get_error_message() : '') . "\n";
