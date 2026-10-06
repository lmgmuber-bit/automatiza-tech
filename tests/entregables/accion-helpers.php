<?php
function en_correr(string $accion, int $usuario, string $nonce_accion, array $post, array $files = [], array $env = []): array {
	$fp = tempnam(sys_get_temp_dir(), 'en-p-');
	$ff = tempnam(sys_get_temp_dir(), 'en-f-');
	file_put_contents($fp, wp_json_encode($post, JSON_UNESCAPED_UNICODE));
	file_put_contents($ff, wp_json_encode($files));
	$entorno = array_merge(getenv(), $env);
	$proc = proc_open([PHP_BINARY, __DIR__ . '/accion-run.php', $accion, (string) $usuario, $nonce_accion, $fp, $ff], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $entorno);
	$salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
	@unlink($fp); @unlink($ff);
	wp_cache_flush();
	$r = ['redirect' => '', 'mails' => [], 'salida' => $salida];
	foreach (preg_split('/\r?\n/', $salida) as $l) {
		if (strpos($l, 'REDIRECT ') === 0) { $r['redirect'] = substr($l, 9); }
		if (strpos($l, 'MAIL ') === 0) { $r['mails'][] = substr($l, 5); }
	}
	return $r;
}
function en_query(string $url): array {
	$q = [];
	parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $q);
	$q['#'] = (string) wp_parse_url($url, PHP_URL_FRAGMENT);
	return $q;
}
