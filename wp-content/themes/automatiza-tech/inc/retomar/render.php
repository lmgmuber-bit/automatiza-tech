<?php
/** Render de Retomar: láminas públicas, encargos de foto con reutilización y callback privado del renderer. */
if (!defined('ABSPATH')) { exit; }

/** Las únicas láminas con foto generada; son las mismas cuatro que acepta el renderer. */
function at_rt_laminas_foto(): array { return ['portada', 'quedamos', 'cierre', 'demo']; }

/**
 * Encargos de foto vigentes: [{slide, prompt}] en el orden de la redacción. Rechaza lo que el renderer rechazaría
 * (lámina desconocida o repetida, prompt vacío o de más de 1200 caracteres, más de cuatro) en vez de recortarlo.
 */
function at_rt_encargos_foto(array $seg): array {
	$crudos = is_array($seg['redaccion']['image_briefs'] ?? null) ? $seg['redaccion']['image_briefs'] : [];
	if (count($crudos) > count(at_rt_laminas_foto())) { throw new InvalidArgumentException('Los encargos de foto admiten máximo 4 láminas.'); }
	$vistas = [];
	$encargos = [];
	foreach ($crudos as $b) {
		$slide = is_array($b) ? ($b['slide'] ?? null) : null;
		$prompt = is_array($b) ? ($b['prompt'] ?? null) : null;
		if (!is_string($slide) || !in_array($slide, at_rt_laminas_foto(), true)) { throw new InvalidArgumentException('Cada encargo de foto debe ser de una lámina conocida (portada, quedamos, cierre o demo).'); }
		if (isset($vistas[$slide])) { throw new InvalidArgumentException("Encargo de foto repetido para la lámina {$slide}."); }
		if (!is_string($prompt) || trim($prompt) === '' || mb_strlen($prompt) > 1200) { throw new InvalidArgumentException("Encargo de foto de {$slide}: de 1 a 1200 caracteres."); }
		$vistas[$slide] = true;
		$encargos[] = ['slide' => $slide, 'prompt' => $prompt];
	}
	return $encargos;
}

/** Hash con el que se reconoce que una foto guardada corresponde a este encargo (y no a una versión anterior). */
function at_rt_hash_encargo(string $prompt): string { return hash('sha256', $prompt); }

/**
 * URL de la foto ya hecha para este encargo, o '' si hay que pedirla: solo se reutiliza si su hash es el del encargo
 * vigente y su URL es de una foto de este seguimiento en el renderer (cualquier generación).
 */
function at_rt_foto_reutilizable(array $seg, array $encargo): string {
	$foto = $seg['fotos'][$encargo['slide']] ?? null;
	if (!is_array($foto) || !is_string($foto['url'] ?? null) || !is_string($foto['hash'] ?? null)) { return ''; }
	if (!hash_equals(at_rt_hash_encargo($encargo['prompt']), $foto['hash'])) { return ''; }
	return at_rt_url_foto_valida($foto['url'], (string) ($seg['token'] ?? '')) ? $foto['url'] : '';
}

/** Rechaza entradas incompatibles en vez de perder contenido silenciosamente. */
function at_rt_armar_render(array $seg, string $generacion = ''): array {
	$t = is_array($seg['textos'] ?? null) ? $seg['textos'] : [];
	$token = (string) ($seg['token'] ?? '');
	if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new InvalidArgumentException('El seguimiento no tiene token válido.'); }
	if ($generacion !== '' && !preg_match('/^[a-f0-9]{16}$/D', $generacion)) { throw new InvalidArgumentException('Generación de render inválida.'); }
	global $wpdb;
	$prop = !empty($seg['propuesta_id']) ? $wpdb->get_row($wpdb->prepare("SELECT unique_link_id, flujo FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", (int) $seg['propuesta_id']), ARRAY_A) : null;
	$uid = is_array($prop) ? (string) $prop['unique_link_id'] : '';
	// Solo las propuestas hechas con la plantilla AT (flujo v3) se enlazan; la URL externa del seguimiento siempre se respeta.
	$flujo = is_array($prop) && $prop['flujo'] !== null ? (string) $prop['flujo'] : null;
	$enlace_v3 = $uid !== '' && !at_rt_necesita_propuesta($flujo) ? home_url('/ver-presentacion.php?id=' . rawurlencode($uid)) : '';
	$fotos = $uid !== '' ? at_pt_fotos_propuesta_wp($uid) : [];
	$imagen = (string) ($seg['imagen_url'] ?? '');
	$recomendacion = is_string($t['recomendacion'] ?? null) ? trim($t['recomendacion']) : '';
	$nuevos = [];
	$reutilizadas = [];
	foreach (at_rt_encargos_foto($seg) as $encargo) {
		$url = at_rt_foto_reutilizable($seg, $encargo);
		if ($url !== '') { $reutilizadas[$encargo['slide']] = $url; } else { $nuevos[] = $encargo; }
	}
	$p = [
		'document_type' => 'retomar', 'unique_id' => $token . ($generacion !== '' ? '-' . $generacion : '') . '-retomar',
		'company_name' => (string) ($seg['negocio'] ?? ''), 'client_name' => (string) ($seg['nombre'] ?? ''),
		'proyecto' => (string) ($t['proyecto'] ?? $seg['negocio'] ?? ''),
		'quedamos' => (string) ($t['quedamos'] ?? ''), 'proponemos' => (string) ($t['proponemos'] ?? ''), 'partir' => (string) ($t['partir'] ?? ''),
		'recomendacion' => $recomendacion,
		'demo_urls' => array_values((array) ($seg['demo_urls'] ?? [])), 'demo_imagen' => (string) ($seg['imagen_url'] ?? ''),
		'propuesta_url' => (string) (($seg['propuesta_url'] ?? '') ?: $enlace_v3),
		'agenda' => ['web_url' => home_url('/retomar.php?t=' . $token . '&agendar=1')],
		'fotos' => ['portada' => (string) ($fotos['cover'] ?? $imagen), 'cierre' => (string) ($fotos['cierre'] ?? $imagen)],
		'image_briefs' => $nuevos,
	];
	// images solo viaja con fotos: el renderer exige un objeto y una lista vacía en JSON ([]) no lo es.
	if ($reutilizadas) { $p['images'] = $reutilizadas; }
	foreach (['company_name' => 120, 'client_name' => 120, 'proyecto' => 180, 'quedamos' => 600, 'proponemos' => 600, 'partir' => 600] as $campo => $max) {
		if (mb_strlen($p[$campo]) > $max) { throw new InvalidArgumentException("{$campo}: máximo {$max} caracteres para la presentación."); }
		if (in_array($campo, ['quedamos', 'proponemos', 'partir'], true) && count(preg_split('/\r\n|\r|\n/', $p[$campo])) > 6) {
			throw new InvalidArgumentException("{$campo}: máximo 6 líneas para la presentación.");
		}
	}
	if (mb_strlen($recomendacion) > 300 || count(preg_split('/\r\n|\r|\n/', $recomendacion)) > 3) {
		throw new InvalidArgumentException('recomendacion: máximo 300 caracteres y 3 líneas para la presentación.');
	}
	if (count($p['demo_urls']) > 6) { throw new InvalidArgumentException('La presentación admite máximo 6 demos.'); }
	// Solo se comprueba el payload público: render.view_url es interno y nunca se incorpora aquí, y las URL de images
	// (fotos ya validadas en el host del renderer) tampoco cuentan; easypanel en demos, propuesta o fotos sigue prohibido.
	$publico = $p;
	unset($publico['images']);
	if (stripos(wp_json_encode($publico), 'easypanel') !== false || stripos(wp_json_encode($publico), at_pt_host_renderer()) !== false) {
		throw new InvalidArgumentException('La presentación contiene un enlace interno: usa enlaces públicos.');
	}
	foreach (array_merge($p['demo_urls'], [$p['demo_imagen'], $p['propuesta_url']], array_values($p['fotos'])) as $url) {
		if (!is_string($url) || ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || wp_parse_url($url, PHP_URL_USER) !== null))) {
			throw new InvalidArgumentException('Las demos, propuesta y fotos deben usar URLs HTTPS públicas válidas.');
		}
	}
	return $p;
}

/**
 * Huella del contenido de la presentación: textos, enlaces y encargos de foto (todos, estén o no ya hechos), sin las
 * URL de las fotos. Así reutilizar una foto, o guardar otra URL del mismo encargo, no cambia la huella.
 */
function at_rt_huella_render(array $seg): string {
	$p = at_rt_armar_render($seg);
	unset($p['images']);
	$p['image_briefs'] = at_rt_encargos_foto($seg);
	return hash('sha256', wp_json_encode($p));
}

/** URL del flujo de n8n «Retomar · Render» (no es secreta); '' si no está configurada. */
function at_rt_render_flujo(): string {
	return defined('AT_N8N_RETOMAR_RENDER') && is_string(AT_N8N_RETOMAR_RENDER) ? AT_N8N_RETOMAR_RENDER : '';
}

/**
 * Lo que un render nuevo hereda del anterior cuando una ruta lo reemplaza entero: la versión publicada (Ruling 17: solo la
 * cambia un recordatorio que sale, at_rt_finalizar_entrega) y, con $reintento, la marca del último reintento del reloj
 * (render.reintento_at, para no volver a pedirla más de una vez cada 24 h). Lo demás (vista, PDF, nota) es del render nuevo.
 */
function at_rt_render_heredar(array $anterior, array $nuevo, bool $reintento = true): array {
	if (is_array($anterior['publicada'] ?? null)) { $nuevo['publicada'] = $anterior['publicada']; }
	if ($reintento && is_string($anterior['reintento_at'] ?? null)) { $nuevo['reintento_at'] = $anterior['reintento_at']; }
	return $nuevo;
}

/**
 * ¿La presentación del seguimiento $s quedó vieja porque el contenido cambió por fuera de las rutas de guardado de Retomar
 * (la propuesta vinculada, su flujo o sus fotos)? null si la huella del contenido actual calza con render.huella. Si no
 * calza, devuelve el render `pendiente` con $nota (y render.pedido_at = $pedido_at si se pasa); si el contenido ya no se
 * puede presentar (at_rt_huella_render lanza), el render en `error` con «$error_prefijo <motivo> Corrígelo en el panel
 * Retomar.». Todo lo demás del render se conserva, render.publicada incluida. Lo usan el vencimiento de una aprobación
 * (envio.php) y la emisión (aprobacion.php), siempre bajo FOR UPDATE: quien llama guarda el resultado y pide el render nuevo
 * fuera del candado.
 */
function at_rt_render_desfasado(array $s, string $nota, string $error_prefijo, string $pedido_at = ''): ?array {
	$render = is_array($s['render'] ?? null) ? $s['render'] : [];
	try { $actual = at_rt_huella_render($s); }
	catch (InvalidArgumentException $e) { return array_merge($render, ['estado' => 'error', 'nota' => $error_prefijo . ' ' . $e->getMessage() . ' Corrígelo en el panel Retomar.']); }
	if (is_string($render['huella'] ?? null) && hash_equals($render['huella'], $actual)) { return null; }
	return array_merge($render, ['estado' => 'pendiente', 'nota' => $nota], $pedido_at !== '' ? ['pedido_at' => $pedido_at] : []);
}

/**
 * Pide la presentación al flujo de n8n. $timeout: segundos de espera (15 desde el panel; el reloj pasa at_rt_timeout_reloj()).
 * Deja render.pedido_at con $ahora (la hora de la pasada del reloj, «Y-m-d H:i[:s]» de Chile) o, sin ella, con la hora de
 * Chile actual: el reloj y el resumen la usan para ver si la presentación quedó atascada (I4). El render nuevo conserva la
 * versión publicada y la marca del último reintento (at_rt_render_heredar).
 */
function at_rt_pedir_render(int $id, int $timeout = 15, string $ahora = ''): string {
	$flujo = at_rt_render_flujo();
	if ($flujo === '') { return 'sin_flujo'; }
	$cuando = at_rt_ahora($ahora);
	$pedido_at = $cuando !== null ? $cuando->format('Y-m-d H:i:s') : at_rt_ahora_chile();
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return 'No se pudo reservar la presentación.'; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return 'El seguimiento no existe.'; }
		$s = at_rt_fila($fila);
		$generacion = bin2hex(random_bytes(8));
		try {
			$huella = at_rt_huella_render($s);
			$payload = at_rt_armar_render($s, $generacion);
		} catch (InvalidArgumentException $e) {
			at_rt_guardar($id, ['render' => at_rt_render_heredar($s['render'], ['estado' => 'error', 'nota' => $e->getMessage(), 'pedido_at' => $pedido_at])]);
			$wpdb->query('COMMIT');
			return $e->getMessage();
		}
		$render = at_rt_render_heredar($s['render'], ['generacion' => $generacion, 'huella' => $huella, 'uid' => $payload['unique_id'], 'estado' => 'pendiente', 'nota' => '', 'pedido_at' => $pedido_at]);
		if (!at_rt_guardar($id, ['render' => $render]) || $wpdb->query('COMMIT') === false) { return 'No se pudo guardar el estado del render.'; }
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno/error.
	}
	$error = at_pt_llamar_n8n($flujo, ['id' => $id, 'generacion' => $generacion, 'render' => $payload], $timeout);
	if ($error !== '') {
		$actual = at_rt_obtener($id);
		if ($actual && ($actual['render']['generacion'] ?? '') === $generacion && ($actual['render']['estado'] ?? '') === 'pendiente') {
			// CAS: un callback o guardado concurrente no debe ser reemplazado por este error de transporte.
			$nuevo = at_rt_campos(['render' => array_merge($actual['render'], ['estado' => 'error', 'nota' => 'No se pudo pedir la presentación.'])]);
			$anterior = at_rt_campos(['render' => $actual['render']]);
			$wpdb->update($wpdb->prefix . 'at_retomar', $nuevo, ['id' => $id, 'render' => $anterior['render']]);
		}
	}
	return $error;
}

/**
 * ¿La presentación está esperando desde hace más de $horas horas? Pendiente (o sin pedir: estado vacío) y su pedido_at es de
 * hace más de $horas; sin pedido_at legible, sí (nunca se pidió, por ejemplo porque no había flujo). $ahora es «Y-m-d
 * H:i[:s]» (o «Y-m-d», la medianoche) de Chile; ilegible, no. El reloj la repite a las 6 h y el resumen la marca a las 24 h.
 */
function at_rt_render_esperando(array $render, string $ahora, int $horas): bool {
	$fecha = at_rt_ahora($ahora);
	return $fecha !== null && in_array((string) ($render['estado'] ?? ''), ['', 'pendiente'], true) && at_rt_marca_vieja($render['pedido_at'] ?? null, $fecha, $horas);
}

/**
 * Reserva bajo FOR UPDATE el reintento del reloj de una presentación atascada (I4): si el seguimiento sigue activo, ya toca
 * (proximo_at hoy o antes), su redacción está `lista`, la presentación lleva más de 6 h esperando y el reloj no la volvió a
 * pedir en las últimas 24 h (render.reintento_at), y no tiene una condición de parada, anota render.reintento_at = $ahora y
 * devuelve true. Otra pasada simultánea ve la marca y no la pide dos veces.
 */
function at_rt_render_reservar_reintento(int $id, string $ahora): bool {
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return false; }
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return false; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return false; }
		$s = at_rt_fila($fila);
		$proximo = (string) ($s['proximo_at'] ?? '');
		if ($s['estado'] !== 'activo' || $proximo === '' || $proximo > $fecha->format('Y-m-d') || ($s['redaccion']['estado'] ?? '') !== 'lista') { return false; }
		if (!at_rt_render_esperando($s['render'], $ahora, 6) || !at_rt_marca_vieja($s['render']['reintento_at'] ?? null, $fecha, 24, true)) { return false; }
		if (at_rt_motivo_parada(at_rt_contexto_parada($s)) !== '') { return false; }
		if (!at_rt_guardar($id, ['render' => array_merge($s['render'], ['reintento_at' => $fecha->format('Y-m-d H:i:s')])])) { return false; }
		return $wpdb->query('COMMIT') !== false;
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno o error.
	}
}

/**
 * Pasada del reloj (I4): vuelve a pedir la presentación de los seguimientos activos que ya tocan y cuya presentación quedó
 * más de 6 h esperando, una vez cada 24 h por seguimiento. Sin flujo de presentación o con una hora ilegible no hace nada.
 * La reserva va bajo candado y el pedido a n8n (HTTP) fuera de él, con at_rt_timeout_reloj(). $max: cuántos pedidos como
 * máximo en esta pasada (el mismo tope del reloj que comparte con las redacciones, Ruling 12; negativo = sin tope); los
 * que no caben se piden en la pasada siguiente, en orden de id. Devuelve los ids pedidos.
 */
function at_rt_reintentar_renders(string $ahora, int $max = -1): array {
	if (at_rt_render_flujo() === '' || $max === 0) { return []; }
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return []; }
	$cuando = $fecha->format('Y-m-d H:i:s');
	global $wpdb;
	// La misma consulta que el envío del reloj: los seguimientos activos cuyo próximo envío ya llegó.
	$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado='activo' AND proximo_at<=%s ORDER BY id", $fecha->format('Y-m-d')));
	$pedidos = [];
	foreach ($ids as $id) {
		if ($max > 0 && count($pedidos) >= $max) { break; } // Sin reservar: el que no cupo sigue libre para la pasada siguiente.
		if (!at_rt_render_reservar_reintento((int) $id, $cuando)) { continue; }
		at_rt_pedir_render((int) $id, at_rt_timeout_reloj(), $cuando);
		$pedidos[] = (int) $id;
	}
	return $pedidos;
}

add_action('rest_api_init', static function () {
	register_rest_route('automatiza-tech/v1', '/retomar/(?P<id>\d+)/vista', [
		'methods' => 'POST', 'callback' => 'at_rt_rest_vista', 'permission_callback' => 'automatiza_proposals_rest_auth',
	]);
});

/** Carpeta del seguimiento en el renderer como expresión regular (sin delimitadores); '' si el token o la generación no son válidos. */
function at_rt_carpeta_render(string $token, string $generacion): string {
	if (!preg_match('/^[a-f0-9]{32}$/D', $token) || ($generacion !== '' && !preg_match('/^[a-f0-9]{16}$/D', $generacion))) { return ''; }
	$version = $generacion !== '' ? '-' . $generacion : '(?:-[a-f0-9]{16})?';
	return 'https://' . preg_quote(at_pt_host_renderer(), '~') . '/p/' . $token . $version . '-retomar/';
}

/** Solo archivos exactos del seguimiento en el host del renderer: nada de iframes arbitrarios. */
function at_rt_url_render_valida(string $url, string $token, bool $pdf = false, string $generacion = ''): bool {
	$carpeta = at_rt_carpeta_render($token, $generacion);
	$archivo = $pdf ? 'presentation\\.pdf' : '(?:index\\.html)?';
	return $carpeta !== '' && (bool) preg_match('~^' . $carpeta . $archivo . '$~D', $url);
}

/** Foto guardada por el renderer para el seguimiento: <carpeta>/img/<archivo simple>.(png|jpg|jpeg|webp), sin parámetros. */
function at_rt_url_foto_valida(string $url, string $token, string $generacion = ''): bool {
	$carpeta = at_rt_carpeta_render($token, $generacion);
	return $carpeta !== '' && (bool) preg_match('~^' . $carpeta . 'img/[A-Za-z0-9_-]+\\.(?:png|jpe?g|webp)$~D', $url);
}

function at_rt_rest_vista(WP_REST_Request $r) {
	$p = $r->get_json_params();
	if (!is_array($p) || !isset($p['ok']) || !is_bool($p['ok'])) { return new WP_Error('retomar_callback', 'ok debe ser booleano.', ['status' => 422]); }
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('retomar_guardar', 'No se pudo reservar el render.', ['status' => 500]); }
	$avisar = false;
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", (int) $r['id']), ARRAY_A);
		if (!$fila) { return new WP_Error('retomar_no_existe', 'No existe el seguimiento.', ['status' => 404]); }
		$s = at_rt_fila($fila);
		$generacion = $p['generacion'] ?? '';
		try { $huella = at_rt_huella_render($s); }
		catch (InvalidArgumentException $e) { $huella = ''; }
		if (!is_string($generacion) || !preg_match('/^[a-f0-9]{16}$/D', $generacion) || !hash_equals((string) ($s['render']['generacion'] ?? ''), $generacion) || $huella === '' || !hash_equals((string) ($s['render']['huella'] ?? ''), $huella)) {
			return new WP_Error('retomar_generacion', 'La respuesta pertenece a una presentación anterior.', ['status' => 409]);
		}
		$view = $p['view_url'] ?? ''; $pdf = $p['pdf_url'] ?? '';
		if (!is_string($view) || !is_string($pdf) || ($view !== '' && !at_rt_url_render_valida($view, $s['token'], false, $generacion)) || ($pdf !== '' && !at_rt_url_render_valida($pdf, $s['token'], true, $generacion)) || ($p['ok'] && $view === '')) {
			return new WP_Error('retomar_url', 'El render debe pertenecer a este seguimiento y al renderer HTTPS autorizado.', ['status' => 422]);
		}
		// Fotos que el renderer dejó guardadas: lámina => URL. Cada una debe ser de este seguimiento y de esta generación,
		// y de una lámina con encargo vigente (su hash es el del encargo). Lo que no viene no se borra: una foto hecha
		// antes sigue valiendo mientras su encargo no cambie. Con una sola foto inválida no se guarda nada.
		$fotos_recibidas = $p['fotos'] ?? [];
		if (!is_array($fotos_recibidas)) { return new WP_Error('retomar_fotos', 'fotos debe ser un objeto lámina => URL.', ['status' => 422]); }
		$hashes = [];
		foreach (at_rt_encargos_foto($s) as $encargo) { $hashes[$encargo['slide']] = at_rt_hash_encargo($encargo['prompt']); }
		$fotos = $s['fotos'];
		foreach ($fotos_recibidas as $slide => $url) {
			if (!is_string($slide) || !isset($hashes[$slide]) || !is_string($url) || !at_rt_url_foto_valida($url, $s['token'], $generacion)) {
				return new WP_Error('retomar_fotos', 'Cada foto debe ser de una lámina con encargo y estar guardada en el renderer para este seguimiento.', ['status' => 422]);
			}
			$fotos[$slide] = ['url' => $url, 'hash' => $hashes[$slide]];
		}
		$nota = is_string($p['nota'] ?? '') ? sanitize_textarea_field($p['nota'] ?? '') : '';
		// La nota se muestra en el panel; no conservar URLs internas en mensajes reutilizables.
		$nota = preg_replace('~https?://\S+~i', '[enlace omitido]', $nota);
		$render = array_merge($s['render'], ['estado' => $p['ok'] ? 'listo' : 'error', 'nota' => mb_substr($nota, 0, 1000)]);
		if ($p['ok']) { $render['view_url'] = $view; $render['pdf_url'] = $pdf; }
		$campos = ['render' => $render];
		if ($fotos_recibidas) { $campos['fotos'] = $fotos; }
		if (!at_rt_guardar($s['id'], $campos)) { return new WP_Error('retomar_guardar', 'No se pudo guardar el render.', ['status' => 500]); }
		if ($wpdb->query('COMMIT') === false) { return new WP_Error('retomar_guardar', 'No se pudo confirmar el render.', ['status' => 500]); }
		// Avisa una sola vez, al pasar a listo, cuando Luis había pedido cambios o cuando la aprobación venció por contenido
		// (por_aprobar con los enlaces muertos: at_rt_aprobacion_muerta en envio.php), para no esperar al correo de la semana
		// siguiente (Ruling 11). Un callback repetido no vuelve a avisar.
		$ap = is_array($s['aprobacion'] ?? null) ? $s['aprobacion'] : [];
		$vencida = ($ap['estado'] ?? '') === 'por_aprobar' && ($ap['usada'] ?? false) === true;
		$avisar = $render['estado'] === 'listo' && ($s['render']['estado'] ?? '') !== 'listo' && (($ap['estado'] ?? '') === 'cambios' || $vencida);
	} finally { $wpdb->query('ROLLBACK'); }
	if ($avisar) {
		// El render ya quedó guardado: un fallo del aviso no debe hacer fracasar el callback.
		try { at_rt_aviso_listo((int) $s['id']); }
		catch (Throwable $e) { error_log('Retomar: el aviso de render listo falló: ' . $e->getMessage()); }
	}
	return new WP_REST_Response(['ok' => true, 'estado' => $render['estado']], 200);
}
