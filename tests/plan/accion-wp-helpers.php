<?php
// Ayudas de las pruebas del plan que ejecutan acciones admin-post (Tasks 8, 9 y 10). Se carga después de
// wp-bootstrap.php (Task 5).

/** Primer administrador del WordPress local de prueba; corta la prueba si no hay. */
function pt_admin_id(): int {
	$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
	$id = (int) ($admins[0] ?? 0);
	if ($id <= 0) {
		fwrite(STDERR, "No hay un usuario administrador en el WordPress local de prueba.\n");
		exit(2);
	}
	return $id;
}

/**
 * Corre una acción admin-post en un proceso aparte (accion-wp-test-run.php) y devuelve
 * ['redirect' => url o '', 'n8n' => [['url' => …, 'cuerpo' => array|null], …], 'salida' => stdout+stderr].
 * Después vacía la caché de objetos de este proceso para leer lo que el hijo escribió en la base.
 */
function pt_correr_accion(string $accion, int $usuario, string $accion_nonce, array $post, string $n8n = 'ok'): array {
	$archivo = tempnam(sys_get_temp_dir(), 'pt-post-');
	file_put_contents($archivo, wp_json_encode($post, JSON_UNESCAPED_UNICODE));
	$proc = proc_open([PHP_BINARY, __DIR__ . '/accion-wp-test-run.php', $accion, (string) $usuario, $accion_nonce, $archivo, $n8n], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	if (!is_resource($proc)) {
		fwrite(STDERR, "No se pudo lanzar el proceso hijo de la prueba.\n");
		exit(2);
	}
	$salida = (string) stream_get_contents($pipes[1]);
	$errores = (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	proc_close($proc);
	@unlink($archivo);
	wp_cache_flush();
	$r = ['redirect' => '', 'n8n' => [], 'salida' => $salida . $errores];
	foreach (preg_split('/\r\n|\n/', $salida) as $linea) {
		if (strpos($linea, 'REDIRECT ') === 0) {
			$r['redirect'] = substr($linea, 9);
		} elseif (strpos($linea, 'N8N ') === 0) {
			$partes = explode(' ', substr($linea, 4), 2);
			$r['n8n'][] = ['url' => $partes[0], 'cuerpo' => json_decode($partes[1] ?? '', true)];
		}
	}
	return $r;
}

/** Parámetros de la URL de una redirección (page, id, pt, pt_msg, …) más '#' con el fragmento. */
function pt_query(string $url): array {
	$q = [];
	parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $q);
	$q['#'] = (string) wp_parse_url($url, PHP_URL_FRAGMENT);
	return $q;
}
