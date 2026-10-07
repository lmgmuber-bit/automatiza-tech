<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/ia-wp-test.php   (nunca llama a OpenAI: todo el HTTP está simulado)
putenv('OPENAI_API_KEY=sk-prueba-no-real'); // clave falsa solo para este proceso (si el sitio ya trae otra, se usa esa: igual no sale a la red)
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_ia_clave', 'at_en_ia_texto_pagina', 'at_en_ia_contexto_mensaje', 'at_en_ia_llamar', 'at_en_ia_registrar_consumo', 'at_en_ia_sugerir_mensaje', 'at_en_ia_sugerir_respuesta', 'at_en_ajax_sugerir', 'at_en_script_ia');
global $wpdb;
$CLAVE = at_en_ia_clave();
ok($CLAVE !== '', 'preparación: hay una clave (falsa) para probar sin salir a la red');

// ---- HTTP simulado: se anota cada petición y se contesta sin red (prioridad 99: después del bloqueo general del bootstrap, que deja pasar solo lo que aquí se simula) ----
$GLOBALS['ia_pedidos'] = [];
$GLOBALS['ia_openai'] = ['code' => 200, 'body' => null];
$GLOBALS['ia_paginas'] = ['https://example.com/v1' => ['code' => 200, 'body' => '<html><head><style>.a{}</style><script>var malo=1;</script></head><body><h1>PAGINA-V1-UNICA</h1><p>Portada con tres opciones</p></body></html>'],
	'https://example.com/v2' => ['code' => 200, 'body' => '<html><body><h1>PAGINA-V2-UNICA</h1></body></html>']];
add_filter('pre_http_request', function ($pre, $args, $url) {
	$GLOBALS['ia_pedidos'][] = ['url' => $url, 'args' => $args, 'body' => (string) ($args['body'] ?? '')];
	$resp = function ($code, $body, $tipo) { return ['headers' => ['content-type' => $tipo], 'body' => $body, 'response' => ['code' => $code, 'message' => ''], 'cookies' => [], 'http_response' => null]; };
	if (strpos($url, 'api.openai.com') !== false) {
		$o = $GLOBALS['ia_openai'];
		if ($o['code'] === 0) {
			return new WP_Error('http_request_failed', 'cURL error 28: Authorization: Bearer ' . at_en_ia_clave()); // un error de red que (peor caso) trae la clave
		}
		$b = $o['body'] ?? wp_json_encode(['choices' => [['message' => ['content' => "Hola, esta versión trae la portada nueva.\nMíralo en https://x.easypanel.host/p\n\n- Portada\n- Colores"]]], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 200]]);
		return $resp($o['code'], $b, 'application/json');
	}
	if (isset($GLOBALS['ia_paginas'][$url])) {
		$p = $GLOBALS['ia_paginas'][$url];
		return $resp($p['code'], $p['body'], 'text/html; charset=UTF-8');
	}
	return $pre;
}, 99, 3);
function ia_ultimo_openai(): ?array {
	foreach (array_reverse($GLOBALS['ia_pedidos']) as $p) {
		if (strpos($p['url'], 'api.openai.com') !== false) {
			return $p + ['json' => json_decode($p['body'], true)];
		}
	}
	return null;
}
function ia_solo_openai(): int {
	return count(array_filter($GLOBALS['ia_pedidos'], function ($p) { return strpos($p['url'], 'api.openai.com') !== false; }));
}

// ---- Datos de prueba ----
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
$marca2 = 'en' . substr(md5(uniqid('', true)), 0, 8);
$consumo_desde = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}ai_usage_log");
register_shutdown_function(function () use ($marca, $marca2, $consumo_desde) {
	global $wpdb;
	en_fx_limpiar($marca);
	en_fx_limpiar($marca2);
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ai_usage_log WHERE id > %d AND client_identifier = 'entregables'", $consumo_desde));
});
$admin = en_admin_id();
wp_set_current_user($admin);
$sub = wp_insert_user(['user_login' => 'sub' . $marca, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
register_shutdown_function(function () use ($sub) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($sub); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', 'Mensaje v1');
$nota1 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Cambia el color del logo NOTA-CLIENTE-V1', [], '');
at_en_agregar_nota($id, 'at', 'Luis', 'Listo, lo cambio RESP-AT-V1', [], '', 1);
at_en_crear_version($id, 'https://example.com/v2', '');
$fx2 = en_fx_cliente($marca2);
$id2 = at_en_activar($fx2['detalle']);
at_en_crear_version($id2, 'https://example.com/v1', '');
$nota_ajena = at_en_agregar_nota($id2, 'cliente', 'Otro', 'Nota del otro entregable', [], '');

// ---- Clave: nunca en los resultados ----
$sin_url = at_en_ia_texto_pagina('javascript:alert(1)');
ok($sin_url === '' && count($GLOBALS['ia_pedidos']) === 0, 'página: un enlace no válido no hace ninguna petición');
$t = at_en_ia_texto_pagina('https://example.com/v1');
ok(strpos($t, 'PAGINA-V1-UNICA Portada con tres opciones') === 0 && strpos($t, 'malo') === false, 'página: texto sin script ni estilos');
$ped = $GLOBALS['ia_pedidos'][0];
ok(($ped['args']['timeout'] ?? 0) == 15 && ($ped['args']['redirection'] ?? 0) == 3 && ($ped['args']['limit_response_size'] ?? 0) == 1048576 && ($ped['args']['user-agent'] ?? '') === 'AutomatizaTech/1.0', 'página: timeout 15, 3 redirecciones, 1 MB y user-agent');
$GLOBALS['ia_paginas']['https://example.com/mala'] = ['code' => 404, 'body' => 'no'];
ok(at_en_ia_texto_pagina('https://example.com/mala') === '', 'página que no responde 200: texto vacío');
$GLOBALS['ia_paginas']['https://example.com/larga'] = ['code' => 200, 'body' => '<p>' . str_repeat('x ', 9000) . '</p>'];
ok(mb_strlen(at_en_ia_texto_pagina('https://example.com/larga'), 'UTF-8') === 8000, 'página: cortada a 8.000 caracteres');

// ---- Contexto ----
$ent = at_en_por_id($id);
$c2 = at_en_ia_contexto_mensaje($ent, 'https://example.com/v2', 2);
ok($c2['numero'] === 2 && strpos($c2['pagina'], 'PAGINA-V2-UNICA') !== false && $c2['titulo'] === 'Propuestas ' . $marca && $c2['empresa'] === 'Empresa ' . $marca, 'contexto v2: título, empresa y página');
ok(count($c2['conversacion_anterior']) === 2 && $c2['conversacion_anterior'][0] === ['autor' => 'cliente', 'texto' => 'Cambia el color del logo NOTA-CLIENTE-V1'] && $c2['conversacion_anterior'][1]['autor'] === 'at', 'contexto v2: conversación de la versión 1');
ok(at_en_ia_contexto_mensaje($ent, 'https://example.com/v1', 1)['conversacion_anterior'] === [], 'contexto v1: sin conversación anterior');

// ---- Sugerir mensaje ----
$GLOBALS['ia_pedidos'] = [];
$r = at_en_ia_sugerir_mensaje($id, 'https://example.com/v2', 2);
$oa = ia_ultimo_openai();
ok(!is_wp_error($r) && strpos($r['texto'], 'easypanel') === false && strpos($r['texto'], "- Portada\n- Colores") !== false && strpos($r['texto'], 'Hola, esta versión') === 0, 'mensaje: devuelve el texto sin la línea de easypanel');
ok($oa && $oa['url'] === 'https://api.openai.com/v1/chat/completions' && $oa['json']['model'] === 'gpt-4o-mini' && $oa['json']['max_tokens'] === 600 && $oa['json']['temperature'] == 0.4, 'mensaje: gpt-4o-mini a chat/completions');
ok(($oa['args']['headers']['Authorization'] ?? '') === 'Bearer ' . $CLAVE && ($oa['args']['sslverify'] ?? false) === true && ($oa['args']['timeout'] ?? 0) == 30, 'mensaje: Authorization Bearer, sslverify y timeout 30');
$cuerpo_user = $oa['json']['messages'][1]['content'];
ok(strpos($cuerpo_user, 'PAGINA-V2-UNICA') !== false && strpos($cuerpo_user, 'NOTA-CLIENTE-V1') !== false && strpos($cuerpo_user, 'RESP-AT-V1') !== false, 'mensaje v2: el cuerpo lleva el texto de la página y la nota anterior del cliente');
ok(strpos($oa['body'], $CLAVE) === false, 'la clave no viaja en el cuerpo de la petición (solo en la cabecera)');
$GLOBALS['ia_pedidos'] = [];
at_en_ia_sugerir_mensaje($id, 'https://example.com/v1', 1);
$u1 = ia_ultimo_openai()['json']['messages'][1]['content'];
ok(strpos($u1, 'PAGINA-V1-UNICA') !== false && strpos($u1, 'NOTA-CLIENTE-V1') === false, 'mensaje v1: sin conversación anterior');
$e = at_en_ia_sugerir_mensaje($id, 'https://x.easypanel.host/p', 1);
ok(is_wp_error($e) && $e->get_error_code() === 'url_invalida', 'mensaje: un enlace de easypanel no se manda a la IA');

// ---- Sugerir respuesta (sin y con fotos) ----
$GLOBALS['ia_pedidos'] = [];
$r = at_en_ia_sugerir_respuesta($nota1);
$oa = ia_ultimo_openai();
ok(!is_wp_error($r) && $oa['json']['model'] === 'gpt-4o-mini' && is_string($oa['json']['messages'][1]['content']) && strpos($oa['json']['messages'][1]['content'], 'NOTA-CLIENTE-V1') !== false, 'respuesta sin fotos: gpt-4o-mini y la nota en el prompt');
// Una foto real (JPG por GD) guardada como lo hace el módulo
$dir = at_en_dir_imagenes($id);
wp_mkdir_p($dir);
$im = imagecreatetruecolor(40, 30);
imagefill($im, 0, 0, imagecolorallocate($im, 200, 20, 20));
$nombre_foto = strtolower(wp_generate_password(24, false, false)) . '.jpg';
imagejpeg($im, $dir . $nombre_foto, 85);
imagedestroy($im);
$nota_foto = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Mira la foto NOTA-CON-FOTO', [$nombre_foto], '');
$GLOBALS['ia_pedidos'] = [];
$r = at_en_ia_sugerir_respuesta($nota_foto);
$oa = ia_ultimo_openai();
$partes = $oa['json']['messages'][1]['content'] ?? null;
ok(!is_wp_error($r) && $oa['json']['model'] === 'gpt-4o', 'respuesta con fotos: gpt-4o');
ok(is_array($partes) && $partes[0]['type'] === 'text' && $partes[1]['type'] === 'image_url' && strpos($partes[1]['image_url']['url'], 'data:image/jpeg;base64,') === 0 && $partes[1]['image_url']['detail'] === 'low', 'respuesta con fotos: image_url base64 con detail low');
ok(base64_decode(substr($partes[1]['image_url']['url'], strlen('data:image/jpeg;base64,'))) === file_get_contents($dir . $nombre_foto), 'respuesta con fotos: el base64 es la foto guardada');
$nota_at = at_en_agregar_nota($id, 'at', 'Luis', 'Una respuesta', [], '');
$e = at_en_ia_sugerir_respuesta($nota_at);
ok(is_wp_error($e) && $e->get_error_code() === 'nota_invalida', 'respuesta: solo notas del cliente');
// Un nombre de imagen malicioso en la nota nunca sale del directorio
$wpdb->update(at_en_tablas()['n'], ['imagenes' => wp_json_encode(['../../wp-config.php', 'nope.jpg'])], ['id' => $nota_foto]);
$GLOBALS['ia_pedidos'] = [];
at_en_ia_sugerir_respuesta($nota_foto);
ok(ia_ultimo_openai()['json']['model'] === 'gpt-4o-mini' && is_string(ia_ultimo_openai()['json']['messages'][1]['content']), 'respuesta: nombres de imagen no válidos se ignoran (no se lee nada fuera de la carpeta)');

// ---- Fallos: mensaje corto y la clave nunca aparece ----
$revisar = function ($r, $etiqueta) use ($CLAVE) {
	$volcado = is_wp_error($r) ? wp_json_encode([$r->get_error_code(), $r->get_error_message(), $r->get_error_data()]) : wp_json_encode($r);
	ok(is_wp_error($r) && $r->get_error_code() === 'ia_fallo' && strpos((string) $volcado, $CLAVE) === false && strlen($r->get_error_message()) < 80, $etiqueta);
};
$m = [['role' => 'user', 'content' => 'hola']];
$GLOBALS['ia_openai'] = ['code' => 500, 'body' => '{"error":{"message":"Incorrect API key provided: ' . $CLAVE . '"}}'];
$revisar(at_en_ia_llamar($m, 'gpt-4o-mini'), 'OpenAI 500 (con la clave en su respuesta): ia_fallo sin la clave');
$GLOBALS['ia_openai'] = ['code' => 200, 'body' => 'esto no es json'];
$revisar(at_en_ia_llamar($m, 'gpt-4o-mini'), 'JSON inválido: ia_fallo');
$GLOBALS['ia_openai'] = ['code' => 200, 'body' => wp_json_encode(['choices' => [['message' => ['content' => '   ']]]])];
$revisar(at_en_ia_llamar($m, 'gpt-4o-mini'), 'contenido vacío: ia_fallo');
$GLOBALS['ia_openai'] = ['code' => 0, 'body' => null];
$revisar(at_en_ia_llamar($m, 'gpt-4o-mini'), 'falla de red (el error trae la clave): ia_fallo sin la clave');
$GLOBALS['ia_openai'] = ['code' => 200, 'body' => null];
$ok = at_en_ia_llamar($m, 'gpt-4o-mini');
ok(!is_wp_error($ok) && $ok['modelo'] === 'gpt-4o-mini' && $ok['tokens_in'] === 1000 && $ok['tokens_out'] === 200 && is_string($ok['texto']), 'llamada buena: texto, modelo y tokens');
ok(strpos(wp_json_encode($ok), $CLAVE) === false, 'llamada buena: la clave no está en el resultado');

// ---- Sin clave (proceso aparte con la constante vacía) ----
$proc = proc_open([PHP_BINARY, __DIR__ . '/ia-sin-clave-run.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['OPENAI_API_KEY' => '']));
$salida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
if (strpos($salida, 'CLAVE VACIA') !== false) {
	ok(strpos($salida, 'CODIGO sin_clave') !== false && strpos($salida, 'MENSAJE La IA no está configurada.') !== false && strpos($salida, 'LLAMO_A_OPENAI') === false, 'sin clave: WP_Error sin_clave y no se llama a OpenAI');
} else {
	ok(false, 'sin clave: el sitio de prueba trae una clave propia en wp-config-secrets.php; no se puede probar el caso vacío aquí');
}

// ---- Consumo ----
$antes = (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}ai_usage_log");
at_en_ia_registrar_consumo(['modelo' => 'gpt-4o-mini', 'tokens_in' => 1000000, 'tokens_out' => 1000000], 'entregables_mensaje');
at_en_ia_registrar_consumo(['modelo' => 'gpt-4o', 'tokens_in' => 1000000, 'tokens_out' => 1000000], 'entregables_respuesta');
$filas = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ai_usage_log WHERE id > %d ORDER BY id", $antes));
ok(count($filas) === 2, 'consumo: dos filas nuevas en ai_usage_log');
$f0 = $filas[0]; $f1 = $filas[1];
ok($f0->client_identifier === 'entregables' && $f0->request_type === 'entregables_mensaje' && $f0->model_used === 'gpt-4o-mini' && (int) $f0->prompt_tokens === 1000000 && (int) $f0->total_tokens === 2000000 && $f0->request_endpoint === 'chat/completions' && (int) $f0->user_id === $admin, 'consumo: columnas del mensaje');
ok(abs((float) $f0->cost_estimated - 0.75) < 0.0001 && abs((float) $f1->cost_estimated - 12.5) < 0.0001 && $f1->request_type === 'entregables_respuesta', 'consumo: costo gpt-4o-mini 0,15+0,60 y gpt-4o 2,50+10,00 por millón');
ok(!property_exists($f0, 'model') || $f0->model === 'gpt-4o-mini', 'consumo: la columna model (la que tiene PROD) también lleva el modelo');
ok($f0->created_at !== null && strlen((string) $f0->created_at) === 19, 'consumo: created_at con fecha');

// ---- AJAX ----
class En_Ajax_Fin extends Exception { public $respuesta; }
add_filter('wp_doing_ajax', '__return_true');
add_filter('wp_die_ajax_handler', function () { return function ($msg) { $x = new En_Ajax_Fin('fin'); $x->respuesta = $msg; throw $x; }; });
function en_ajax(array $post, int $usuario, ?string $nonce_accion): array {
	wp_set_current_user($usuario);
	$_POST = wp_slash($post + ['action' => 'at_en_sugerir']);
	if ($nonce_accion !== null) {
		$_POST['_ajax_nonce'] = wp_create_nonce($nonce_accion);
	}
	$_REQUEST = $_POST;
	ob_start();
	$fin = null;
	try {
		do_action('wp_ajax_at_en_sugerir');
	} catch (En_Ajax_Fin $x) {
		$fin = $x->respuesta;
	}
	$salida = ob_get_clean();
	return ['json' => json_decode($salida, true), 'salida' => $salida, 'fin' => $fin];
}
ok(!has_action('wp_ajax_nopriv_at_en_sugerir') && has_action('wp_ajax_at_en_sugerir'), 'AJAX: solo con sesión (sin wp_ajax_nopriv)');
$ok_post = ['entregable_id' => $id, 'tipo' => 'mensaje', 'url' => 'https://example.com/v2', 'numero' => 2];
delete_transient('at_en_ia_' . $admin);
delete_transient('at_en_ia_' . $sub);
$n0 = ia_solo_openai();
$a = en_ajax($ok_post, $admin, 'otro_nonce');
ok($a['json'] === null && (string) $a['fin'] === '-1' && ia_solo_openai() === $n0, 'AJAX: nonce malo → rechazado y sin llamada a OpenAI');
$a = en_ajax($ok_post, $admin, null);
ok($a['json'] === null && (string) $a['fin'] === '-1' && ia_solo_openai() === $n0, 'AJAX: sin nonce → rechazado');
$a = en_ajax($ok_post, (int) $sub, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === false && ($a['json']['data']['mensaje'] ?? '') === 'Sin permiso.' && ia_solo_openai() === $n0, 'AJAX: sin manage_options → «Sin permiso.» y sin llamada');
$a = en_ajax(['entregable_id' => 999999999] + $ok_post, $admin, 'at_en_999999999');
ok(($a['json']['success'] ?? null) === false && ia_solo_openai() === $n0, 'AJAX: entregable inexistente → error');
$a = en_ajax(['entregable_id' => $id, 'tipo' => 'respuesta', 'nota_id' => $nota_ajena], $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === false && ($a['json']['data']['mensaje'] ?? '') === 'Esa nota no es de este entregable.' && ia_solo_openai() === $n0, 'AJAX: nota de otro entregable → rechazada');
$a = en_ajax(['entregable_id' => $id, 'tipo' => 'otra'], $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === false, 'AJAX: tipo desconocido → error');
$a = en_ajax($ok_post, $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === true && strpos($a['json']['data']['texto'] ?? '', 'Hola, esta versión') === 0 && strpos($a['salida'], 'easypanel') === false && strpos($a['salida'], $CLAVE) === false, 'AJAX mensaje: éxito con texto, sin easypanel ni clave');
$a = en_ajax(['entregable_id' => $id, 'tipo' => 'respuesta', 'nota_id' => $nota1], $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === true && is_string($a['json']['data']['texto'] ?? null), 'AJAX respuesta: éxito');
// Error de OpenAI: mensaje en español, sin la clave, y no cuenta para el límite
$antes_n = at_en_ia_usadas($admin);
$GLOBALS['ia_openai'] = ['code' => 500, 'body' => 'x'];
$a = en_ajax($ok_post, $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === false && ($a['json']['data']['mensaje'] ?? '') === 'No se pudo sugerir: escribe el texto a mano.' && strpos($a['salida'], $CLAVE) === false, 'AJAX: OpenAI cae → «No se pudo sugerir: escribe el texto a mano.»');
ok(at_en_ia_usadas($admin) === $antes_n, 'AJAX: una sugerencia fallida no cuenta para el límite');
$GLOBALS['ia_openai'] = ['code' => 200, 'body' => null];
// Límite: 30 por hora (ya van 2 buenas)
$n_ok = 0;
for ($i = 0; $i < 28; $i++) {
	$a = en_ajax($ok_post, $admin, 'at_en_' . $id);
	$n_ok += (($a['json']['success'] ?? null) === true) ? 1 : 0;
}
ok($n_ok === 28 && at_en_ia_usadas($admin) === 30, 'AJAX: las primeras 30 sugerencias pasan');
$n_antes = ia_solo_openai();
$a = en_ajax($ok_post, $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === false && ($a['json']['data']['mensaje'] ?? '') === 'Llegaste al límite de sugerencias por hora.' && ia_solo_openai() === $n_antes, 'AJAX: la 31 se rechaza con el aviso y sin llamar a OpenAI');
$t = get_transient('at_en_ia_' . $admin);
ok(is_array($t) && (int) $t['n'] === 30 && $t['hasta'] > time() && $t['hasta'] <= time() + HOUR_IN_SECONDS, 'AJAX: el contador vive una hora en el transient at_en_ia_<usuario>');
$filas_consumo = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ai_usage_log WHERE id > %d AND client_identifier = 'entregables' AND request_type IN ('entregables_mensaje','entregables_respuesta')", $consumo_desde));
ok($filas_consumo >= 30, 'AJAX: cada sugerencia lograda dejó su fila de consumo');
delete_transient('at_en_ia_' . $admin);
$a = en_ajax($ok_post, $admin, 'at_en_' . $id);
ok(($a['json']['success'] ?? null) === true, 'AJAX: con el contador en cero vuelve a pasar');
delete_transient('at_en_ia_' . $admin);
remove_all_filters('wp_doing_ajax');
remove_all_filters('wp_die_ajax_handler');

// ---- Panel: botones y script ----
wp_set_current_user($admin);
$html = (function () use ($fx) { ob_start(); at_en_render_pestana(['id' => $fx['crm']]); return ob_get_clean(); })();
ok(substr_count($html, '✨ Sugerir mensaje') === 2, 'panel: «✨ Sugerir mensaje» en Nueva versión y en Editar esta versión');
ok(substr_count($html, '✨ Sugerir respuesta') === 2, 'panel: «✨ Sugerir respuesta» bajo cada nota del cliente sin responder (2)');
ok(substr_count($html, 'at_en_sugerir') === 1 && substr_count($html, '<script>(function(){var ajax=') === 1, 'panel: el script está una sola vez');
ok(substr_count($html, 'type="button" class="button at-en-ia-btn"') === 4, 'panel: los 4 botones son type=button');
ok(strpos($html, 'data-numero="3"') !== false && strpos($html, 'data-numero="2"') !== false, 'panel: Nueva versión usa vigente+1 (3) y Editar usa la vigente (2)');
ok(strpos($html, 'data-nota="' . $nota1 . '"') !== false && strpos($html, 'data-nonce="' . wp_create_nonce('at_en_' . $id) . '"') !== false, 'panel: el botón de respuesta lleva el id de su nota y el nonce del entregable');
ok(strpos($html, $CLAVE) === false && stripos($html, 'sk-') === false && stripos($html, 'Bearer') === false, 'panel: ninguna clave en el HTML');
ok(strpos($html, 'innerHTML') === false && strpos($html, 'Pega primero el enlace de la versión.') !== false && strpos($html, 'Pensando…') !== false, 'panel: el script no usa innerHTML y trae sus avisos');
// Sin entregables activos no hay script
$fx3 = en_fx_cliente($marca . 'z');
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca . 'z'); });
$h3 = (function () use ($fx3) { ob_start(); at_en_render_pestana(['id' => $fx3['crm']]); return ob_get_clean(); })();
ok(strpos($h3, 'at_en_sugerir') === false && strpos($h3, 'Sugerir') === false, 'panel: sin entregables activados no hay botones ni script');
fin();
