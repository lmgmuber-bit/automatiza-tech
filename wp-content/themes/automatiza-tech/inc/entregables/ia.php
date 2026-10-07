<?php
/**
 * Sugerencias con IA (OpenAI) para el panel de Entregables: el mensaje que acompaña una versión y la respuesta a una nota del cliente.
 * Solo se llama a OpenAI cuando Luis pulsa el botón. La clave viene de la constante OPENAI_API_KEY (env / wp-config-secrets.php):
 * nunca se escribe, se imprime ni viaja en un error. Los prompts y la limpieza del texto son funciones puras en puras.php.
 */
if (!defined('ABSPATH')) {
	exit;
}
if (!defined('AT_EN_IA_POR_HORA')) {
	define('AT_EN_IA_POR_HORA', 30);
	define('AT_EN_IA_MODELO_TEXTO', 'gpt-4o-mini');
	define('AT_EN_IA_MODELO_FOTOS', 'gpt-4o');
}

/** Clave de OpenAI; '' si no está configurada. */
function at_en_ia_clave(): string {
	return (defined('OPENAI_API_KEY') && is_string(OPENAI_API_KEY)) ? trim(OPENAI_API_KEY) : '';
}

/** Texto de la página de una versión (8.000 caracteres como máximo); '' si el enlace no sirve, la página no responde 200 o no es texto. */
function at_en_ia_texto_pagina(string $url): string {
	$url = at_en_url_version_valida($url);
	if ($url === '') {
		return '';
	}
	// La versión «safe» rechaza destinos internos (127.0.0.1, redes privadas): el enlace lo escribe un humano y no se confía.
	$r = wp_safe_remote_get($url, ['timeout' => 15, 'redirection' => 3, 'limit_response_size' => 1048576, 'user-agent' => 'AutomatizaTech/1.0']);
	if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
		return '';
	}
	$tipo = strtolower((string) wp_remote_retrieve_header($r, 'content-type'));
	if ($tipo !== '' && strpos($tipo, 'text/') !== 0 && strpos($tipo, 'xml') === false) {
		return '';
	}
	return at_en_ia_html_a_texto((string) wp_remote_retrieve_body($r));
}

/** Contexto para redactar el mensaje de la versión $numero: título, empresa, texto de la página y, desde la v2, las notas de la versión anterior. */
function at_en_ia_contexto_mensaje(object $ent, string $url, int $numero): array {
	$cli = at_en_cliente($ent);
	$conv = [];
	if ($numero > 1) {
		foreach (at_en_notas((int) $ent->id) as $n) {
			if ((int) $n->version_numero === $numero - 1) {
				$conv[] = ['autor' => $n->autor === 'at' ? 'at' : 'cliente', 'texto' => (string) $n->texto];
			}
		}
	}
	return ['titulo' => $cli['titulo'], 'empresa' => $cli['empresa'], 'numero' => $numero, 'pagina' => at_en_ia_texto_pagina($url), 'conversacion_anterior' => $conv];
}

/** Precio por millón de tokens (entrada, salida) en US$; fuente: developers.openai.com/api/docs/pricing, 06-oct-2026. */
function at_en_ia_costo(string $modelo, int $in, int $out): float {
	$p = ['gpt-4o-mini' => [0.15, 0.60], 'gpt-4o' => [2.50, 10.00]][$modelo] ?? [0.15, 0.60];
	return round(($in * $p[0] + $out * $p[1]) / 1000000, 6);
}

/**
 * Llama a chat/completions. ['texto', 'modelo', 'tokens_in', 'tokens_out'] o WP_Error: sin_clave, ia_fallo.
 * Los errores llevan un mensaje corto y fijo: nunca la clave, la petición ni la respuesta cruda.
 */
function at_en_ia_llamar(array $messages, string $modelo, int $max_tokens = 600) {
	$clave = at_en_ia_clave();
	if ($clave === '') {
		return new WP_Error('sin_clave', 'La IA no está configurada.');
	}
	$fallo = new WP_Error('ia_fallo', 'No se pudo obtener la sugerencia de la IA.');
	$r = wp_remote_post('https://api.openai.com/v1/chat/completions', [
		'timeout'   => 30,
		'sslverify' => true,
		'headers'   => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $clave],
		'body'      => wp_json_encode(['model' => $modelo, 'messages' => $messages, 'max_tokens' => $max_tokens, 'temperature' => 0.4]),
	]);
	if (is_wp_error($r)) {
		error_log('at_en_ia: la llamada a OpenAI falló (' . $r->get_error_code() . ')');
		return $fallo;
	}
	$codigo = (int) wp_remote_retrieve_response_code($r);
	$j = json_decode((string) wp_remote_retrieve_body($r), true);
	$texto = is_array($j) ? trim((string) ($j['choices'][0]['message']['content'] ?? '')) : '';
	if ($codigo !== 200 || $texto === '') {
		error_log('at_en_ia: OpenAI respondió HTTP ' . $codigo . ($texto === '' ? ' sin texto' : ''));
		return $fallo;
	}
	return ['texto' => $texto, 'modelo' => $modelo, 'tokens_in' => (int) ($j['usage']['prompt_tokens'] ?? 0), 'tokens_out' => (int) ($j['usage']['completion_tokens'] ?? 0)];
}

/** Anota el consumo en ai_usage_log (la tabla de PROD tiene muchas columnas, algunas heredadas: solo se llenan las que existen). */
function at_en_ia_registrar_consumo(array $r, string $tipo): void {
	global $wpdb;
	static $columnas = null;
	$tabla = $wpdb->prefix . 'ai_usage_log';
	if ($columnas === null) {
		$columnas = [];
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tabla))) === $tabla) {
			$columnas = array_flip((array) $wpdb->get_col("SHOW COLUMNS FROM {$tabla}", 0));
		}
	}
	if (!$columnas) {
		return;
	}
	$in = (int) ($r['tokens_in'] ?? 0);
	$out = (int) ($r['tokens_out'] ?? 0);
	$modelo = (string) ($r['modelo'] ?? '');
	$fila = [
		'user_id' => get_current_user_id(), 'client_identifier' => 'entregables', 'model_used' => $modelo, 'model' => $modelo,
		'prompt_tokens' => $in, 'completion_tokens' => $out, 'total_tokens' => $in + $out,
		'cost_estimated' => at_en_ia_costo($modelo, $in, $out), 'costo_usd' => at_en_ia_costo($modelo, $in, $out),
		'tokens_total' => $in + $out, 'tokens_input' => $in, 'tokens_output' => $out, 'request_endpoint' => 'chat/completions',
		'request_type' => $tipo, 'created_at' => current_time('mysql'),
	];
	$wpdb->insert($tabla, array_intersect_key($fila, $columnas));
}

/** Sugiere el cuerpo del correo de la versión $numero leyendo la página de $url. ['texto'] o WP_Error. */
function at_en_ia_sugerir_mensaje(int $ent_id, string $url, int $numero) {
	$ent = at_en_por_id($ent_id);
	if (!$ent) {
		return new WP_Error('sin_entregable', 'El entregable no existe.');
	}
	$url = at_en_url_version_valida($url);
	if ($url === '' || $numero < 1) {
		return new WP_Error('url_invalida', 'El enlace de la versión no sirve.');
	}
	$r = at_en_ia_llamar(at_en_ia_prompt_mensaje(at_en_ia_contexto_mensaje($ent, $url, $numero)), AT_EN_IA_MODELO_TEXTO, 600);
	if (is_wp_error($r)) {
		return $r;
	}
	at_en_ia_registrar_consumo($r, 'entregables_mensaje');
	$texto = at_en_ia_quitar_easypanel($r['texto']);
	return $texto === '' ? new WP_Error('ia_fallo', 'No se pudo obtener la sugerencia de la IA.') : ['texto' => $texto];
}

/** Fotos de una nota como data URLs (máx. 3), leídas de la carpeta privada solo con nombres válidos. */
function at_en_ia_fotos_nota(object $nota): array {
	$nombres = json_decode((string) $nota->imagenes, true);
	$dir = at_en_dir_imagenes((int) $nota->entregable_id);
	$salida = [];
	foreach (is_array($nombres) ? $nombres : [] as $nombre) {
		if (count($salida) >= AT_EN_MAX_IMAGENES) {
			break;
		}
		$ruta = at_en_nombre_imagen_valido((string) $nombre) ? $dir . $nombre : '';
		if ($ruta === '' || !is_file($ruta) || filesize($ruta) > 4 * 1048576) {
			continue;
		}
		$bin = file_get_contents($ruta);
		if ($bin !== false) {
			$salida[] = 'data:' . (substr((string) $nombre, -4) === '.png' ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode($bin);
		}
	}
	return $salida;
}

/** Sugiere la respuesta de Luis a una nota del cliente. Con fotos usa gpt-4o (ve imágenes); sin fotos, gpt-4o-mini. ['texto'] o WP_Error. */
function at_en_ia_sugerir_respuesta(int $nota_id) {
	$nota = at_en_nota($nota_id);
	if (!$nota || $nota->autor !== 'cliente') {
		return new WP_Error('nota_invalida', 'La nota no existe o no es del cliente.');
	}
	$ent = at_en_por_id((int) $nota->entregable_id);
	if (!$ent) {
		return new WP_Error('sin_entregable', 'El entregable no existe.');
	}
	$conv = [];
	foreach (at_en_notas((int) $ent->id) as $n) {
		if ((int) $n->id < (int) $nota->id) {
			$conv[] = ['autor' => $n->autor === 'at' ? 'at' : 'cliente', 'texto' => (string) $n->texto];
		}
	}
	$fotos = at_en_ia_fotos_nota($nota);
	$msgs = at_en_ia_prompt_respuesta(['titulo' => at_en_cliente($ent)['titulo'], 'numero' => (int) $nota->version_numero, 'nota' => (string) $nota->texto, 'conversacion' => $conv, 'imagenes' => $fotos]);
	$r = at_en_ia_llamar($msgs, $fotos ? AT_EN_IA_MODELO_FOTOS : AT_EN_IA_MODELO_TEXTO, 400);
	if (is_wp_error($r)) {
		return $r;
	}
	at_en_ia_registrar_consumo($r, 'entregables_respuesta');
	$texto = at_en_ia_quitar_easypanel($r['texto']);
	return $texto === '' ? new WP_Error('ia_fallo', 'No se pudo obtener la sugerencia de la IA.') : ['texto' => $texto];
}

/** Sugerencias usadas en la hora en curso por un usuario. */
function at_en_ia_usadas(int $user_id): int {
	$d = get_transient('at_en_ia_' . $user_id);
	return (is_array($d) && (int) ($d['hasta'] ?? 0) > time()) ? (int) ($d['n'] ?? 0) : 0;
}

/** Cuenta una sugerencia lograda; la ventana de una hora arranca con la primera. */
function at_en_ia_contar(int $user_id): void {
	$d = get_transient('at_en_ia_' . $user_id);
	$hasta = (is_array($d) && (int) ($d['hasta'] ?? 0) > time()) ? (int) $d['hasta'] : time() + HOUR_IN_SECONDS;
	set_transient('at_en_ia_' . $user_id, ['n' => at_en_ia_usadas($user_id) + 1, 'hasta' => $hasta], max(1, $hasta - time()));
}

/** AJAX del panel: solo usuarios con sesión (no hay wp_ajax_nopriv_). */
function at_en_ajax_sugerir(): void {
	$id = absint($_POST['entregable_id'] ?? 0);
	check_ajax_referer('at_en_' . $id, '_ajax_nonce');
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['mensaje' => 'Sin permiso.'], 403);
	}
	$e = at_en_por_id($id);
	if (!$e) {
		wp_send_json_error(['mensaje' => 'El entregable ya no existe.'], 404);
	}
	$tipo = sanitize_key(wp_unslash($_POST['tipo'] ?? ''));
	$nota_id = absint($_POST['nota_id'] ?? 0);
	if ($tipo === 'respuesta') {
		$nota = at_en_nota($nota_id);
		if (!$nota || (int) $nota->entregable_id !== (int) $e->id) {
			wp_send_json_error(['mensaje' => 'Esa nota no es de este entregable.'], 400);
		}
	} elseif ($tipo !== 'mensaje') {
		wp_send_json_error(['mensaje' => 'Petición inválida.'], 400);
	}
	$uid = get_current_user_id();
	if (at_en_ia_usadas($uid) >= AT_EN_IA_POR_HORA) {
		wp_send_json_error(['mensaje' => 'Llegaste al límite de sugerencias por hora.'], 429);
	}
	$r = $tipo === 'respuesta'
		? at_en_ia_sugerir_respuesta($nota_id)
		: at_en_ia_sugerir_mensaje((int) $e->id, (string) wp_unslash($_POST['url'] ?? ''), absint($_POST['numero'] ?? 0));
	if (is_wp_error($r)) {
		$mensajes = ['sin_clave' => 'La IA no está configurada.', 'url_invalida' => 'El enlace de la versión no sirve.'];
		wp_send_json_error(['mensaje' => $mensajes[$r->get_error_code()] ?? 'No se pudo sugerir: escribe el texto a mano.'], $r->get_error_code() === 'sin_clave' ? 503 : 502);
	}
	at_en_ia_contar($uid);
	wp_send_json_success(['texto' => $r['texto']]);
}
add_action('wp_ajax_at_en_sugerir', 'at_en_ajax_sugerir');
