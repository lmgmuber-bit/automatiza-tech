<?php
/**
 * Redacción con IA de Retomar: pedido a n8n, contexto para la IA y callback con los textos y los encargos de foto.
 * La IA nunca recibe ni devuelve montos: el contexto sale sin las líneas que los traen y una respuesta con montos se
 * pide de nuevo una sola vez antes de pasar a revisión manual.
 */
if (!defined('ABSPATH')) { exit; }

/** URL del flujo de n8n «Retomar · Redactar» (no es secreta); '' si no está configurada. */
function at_rt_red_flujo(): string {
	return defined('AT_N8N_RETOMAR_REDACTAR') && is_string(AT_N8N_RETOMAR_REDACTAR) ? AT_N8N_RETOMAR_REDACTAR : '';
}

/** Límites de la redacción: los mismos que exige la presentación (at_rt_armar_render). */
function at_rt_red_reglas(): array {
	return ['max_caracteres' => 600, 'max_lineas' => 6, 'max_recomendacion' => 300, 'sin_montos' => true];
}

function at_rt_red_lineas(string $texto): int { return count(preg_split('/\r\n|\r|\n/', $texto)); }

/** Quita las líneas que traen un monto; lo demás queda tal cual. */
function at_rt_red_sin_montos(string $texto): string {
	$limpias = array_filter(preg_split('/\r\n|\r|\n/', $texto), static fn(string $linea): bool => !at_rt_tiene_montos($linea));
	return trim(implode("\n", $limpias));
}

/** Láminas con foto generada para este seguimiento: la de la demo solo si no trae una imagen propia. */
function at_rt_red_slides(array $seg): array {
	return trim((string) ($seg['imagen_url'] ?? '')) === '' ? ['portada', 'cierre', 'demo'] : ['portada', 'cierre'];
}

/** Nota de error o de revisión que se guarda y se muestra en el panel: texto plano, sin enlaces y de hasta 1000 caracteres. */
function at_rt_red_nota($nota): string {
	$nota = is_string($nota) ? sanitize_textarea_field($nota) : '';
	return mb_substr(preg_replace('~https?://\S+~i', '[enlace omitido]', $nota), 0, 1000);
}

/** Pedido de quien lo hace (Luis o un cambio pedido desde el correo): texto plano de hasta 2000 caracteres. */
function at_rt_red_pedido(string $pedido): string { return mb_substr(sanitize_textarea_field($pedido), 0, 2000); }

/** Pedido del reintento: conserva el de Luis y agrega la indicación. Sin «$», siglas de moneda ni cifras con miles. */
function at_rt_red_pedido_sin_montos(string $original): string {
	$aviso = 'Reintento: tu respuesta anterior traía montos o precios. Redáctala de nuevo sin montos ni precios: no escribas signos de moneda, siglas de moneda ni cifras con separador de miles.';
	$base = trim(mb_substr(trim($original), 0, 1000));
	return $base === '' ? $aviso : $base . "\n" . $aviso;
}

/** Hora de Chile de este momento, «Y-m-d H:i:s». */
function at_rt_ahora_chile(): string {
	return (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s');
}

/**
 * Redacción que queda guardada al pedirla: pendiente, con generación nueva y pedida_at (hora de Chile del pedido; el reloj
 * la usa para saber si quedó atascada). Un pedido nuevo reinicia los intentos; el reintento los suma.
 */
function at_rt_red_nueva(array $seg, string $pedido, bool $reintento, string $pedida_at = ''): array {
	$actual = is_array($seg['redaccion'] ?? null) ? $seg['redaccion'] : [];
	$intentos = $reintento ? max(1, (int) ($actual['intentos'] ?? 1)) + 1 : 1;
	return array_merge($actual, ['estado' => 'pendiente', 'generacion' => bin2hex(random_bytes(8)), 'intentos' => $intentos, 'pedido' => $pedido, 'nota' => '', 'pedida_at' => $pedida_at !== '' ? $pedida_at : at_rt_ahora_chile()]);
}

/**
 * Avisa a n8n con {id, generacion}; '' si respondió 2xx. Si falla, la redacción queda en «error» siempre que siga siendo
 * ese mismo pedido y siga pendiente (CAS sobre el JSON leído: un callback o un pedido nuevo no se reemplaza).
 */
function at_rt_red_avisar(int $id, string $generacion): string {
	global $wpdb;
	$error = at_pt_llamar_n8n(at_rt_red_flujo(), ['id' => $id, 'generacion' => $generacion]);
	if ($error === '') { return ''; }
	$crudo = $wpdb->get_var($wpdb->prepare("SELECT redaccion FROM {$wpdb->prefix}at_retomar WHERE id=%d", $id));
	$actual = json_decode((string) $crudo, true);
	if (is_array($actual) && ($actual['generacion'] ?? '') === $generacion && ($actual['estado'] ?? '') === 'pendiente') {
		$nuevo = wp_json_encode(array_merge($actual, ['estado' => 'error', 'nota' => 'No se pudo pedir la redacción.']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}at_retomar SET redaccion=%s, actualizado_at=%s WHERE id=%d AND BINARY redaccion=BINARY %s", $nuevo, current_time('mysql'), $id, (string) $crudo));
	}
	return $error;
}

/**
 * Pide la redacción al flujo de n8n con una generación nueva (hex16) y los intentos en 1. '' si n8n recibió el aviso,
 * 'sin_flujo' si no hay AT_N8N_RETOMAR_REDACTAR (no cambia nada) o el motivo del error (deja redaccion.estado = 'error').
 * El pedido de quien lo hace vive en redaccion.pedido; las notas no se tocan aquí. redaccion.pedida_at queda con $ahora
 * (la hora de la pasada del reloj o de la detección, «Y-m-d H:i[:s]» de Chile) o, sin ella, con la hora de Chile actual.
 */
function at_rt_pedir_redaccion(int $id, string $pedido = '', string $ahora = ''): string {
	if (at_rt_red_flujo() === '') { return 'sin_flujo'; }
	$cuando = at_rt_ahora($ahora);
	$pedida_at = $cuando !== null ? $cuando->format('Y-m-d H:i:s') : at_rt_ahora_chile();
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return 'No se pudo reservar la redacción.'; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return 'El seguimiento no existe.'; }
		$redaccion = at_rt_red_nueva(at_rt_fila($fila), at_rt_red_pedido($pedido), false, $pedida_at);
		if (!at_rt_guardar($id, ['redaccion' => $redaccion]) || $wpdb->query('COMMIT') === false) { return 'No se pudo guardar el estado de la redacción.'; }
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno/error.
	}
	return at_rt_red_avisar($id, $redaccion['generacion']);
}

/**
 * ¿Hay que volver a pedir esta redacción? (Ruling 7.) Sí si está vacía, en «error», o «pendiente» desde hace más de 6 horas
 * (sin pedida_at legible cuenta como vieja: viene de antes de esta versión), y el reloj no la volvió a pedir en las últimas
 * 24 horas (redaccion.reintento_at). «lista» no hace falta y «revision» necesita a Luis: nunca se reintentan.
 * $ahora es la hora de la pasada, «Y-m-d H:i[:s]» de Chile; ilegible, no se pide nada.
 */
function at_rt_red_atascada(array $red, string $ahora): bool {
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return false; }
	// ¿La marca es de hace más de (o justo, con $incluido) $horas horas? Sin marca legible, sí.
	$hace = static function ($marca, int $horas, bool $incluido) use ($fecha): bool {
		$cuando = is_string($marca) ? at_rt_ahora($marca) : null;
		$limite = $fecha->modify('-' . $horas . ' hours');
		return $cuando === null || $cuando < $limite || ($incluido && $cuando == $limite);
	};
	$estado = is_string($red['estado'] ?? null) ? $red['estado'] : '';
	if (!in_array($estado, ['', 'error', 'pendiente'], true)) { return false; }
	if ($estado === 'pendiente' && !$hace($red['pedida_at'] ?? null, 6, false)) { return false; }
	return $hace($red['reintento_at'] ?? null, 24, true);
}

/**
 * Reserva bajo FOR UPDATE el reintento del reloj: si el seguimiento sigue activo, ya toca (proximo_at hoy o antes), su
 * redacción quedó atascada y no tiene una condición de parada (no se gasta IA en quien ya no puede recibir nada), anota
 * redaccion.reintento_at = $ahora y devuelve el pedido que traía, para repetirlo tal cual. null si no corresponde: otra
 * pasada simultánea ve la marca y no lo pide dos veces.
 */
function at_rt_red_reservar_reintento(int $id, string $ahora): ?string {
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return null; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return null; }
		$s = at_rt_fila($fila);
		$proximo = (string) ($s['proximo_at'] ?? '');
		if ($s['estado'] !== 'activo' || $proximo === '' || $proximo > substr($ahora, 0, 10) || !at_rt_red_atascada($s['redaccion'], $ahora)) { return null; }
		if (at_rt_motivo_parada(at_rt_contexto_parada($s)) !== '') { return null; }
		$pedido = is_string($s['redaccion']['pedido'] ?? null) ? $s['redaccion']['pedido'] : '';
		if (!at_rt_guardar($id, ['redaccion' => array_merge($s['redaccion'], ['reintento_at' => $ahora])]) || $wpdb->query('COMMIT') === false) { return null; }
		return $pedido;
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno o error.
	}
}

/**
 * Pasada del reloj (Ruling 7): vuelve a pedir la redacción de los seguimientos activos que ya tocan y quedaron atascados
 * (at_rt_red_atascada), una vez cada 24 h por seguimiento y con el mismo pedido que traían. Sin flujo configurado o con una
 * hora ilegible no hace nada. La reserva va bajo candado y el pedido a n8n (HTTP) fuera de él. Devuelve los ids pedidos.
 */
function at_rt_reintentar_redacciones(string $ahora): array {
	if (at_rt_red_flujo() === '') { return []; }
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return []; }
	$cuando = $fecha->format('Y-m-d H:i:s');
	global $wpdb;
	// La misma consulta que el envío del reloj: los seguimientos activos cuyo próximo envío ya llegó.
	$ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE estado='activo' AND proximo_at<=%s ORDER BY id", $fecha->format('Y-m-d')));
	$pedidos = [];
	foreach ($ids as $id) {
		$pedido = at_rt_red_reservar_reintento((int) $id, $cuando);
		if ($pedido === null) { continue; }
		at_rt_pedir_redaccion((int) $id, $pedido, $cuando);
		$pedidos[] = (int) $id;
	}
	return $pedidos;
}

/** Una línea de texto de la propuesta: «Título: texto» si es un objeto, el texto si es una cadena; '' si no es ninguna. */
function at_rt_red_linea_prosa($item, string $clave_titulo, string $clave_texto): string {
	if (is_array($item)) {
		$titulo = is_scalar($item[$clave_titulo] ?? null) ? trim((string) $item[$clave_titulo]) : '';
		$texto = is_scalar($item[$clave_texto] ?? null) ? trim((string) $item[$clave_texto]) : '';
		$linea = ($titulo !== '' && $texto !== '') ? $titulo . ': ' . $texto : $titulo . $texto;
	} else {
		$linea = is_scalar($item) ? trim((string) $item) : '';
	}
	// Un elemento de lista es una sola línea: así el filtro de montos lo descarta entero y no deja media frase.
	return trim((string) preg_replace('/\s*[\r\n]+\s*/', ' ', $linea));
}

/**
 * Resumen de la propuesta para la IA. El prompt de contenido de una propuesta v3 es un JSON de una sola línea con precios
 * (pricing_rows, pricing_note) y el correo al cliente: si decodifica, el resumen se arma SOLO con las claves de prosa
 * (lista blanca) y nunca con esas; si no decodifica se trata como texto (sin filas de tabla markdown). En los dos casos
 * después se descartan las líneas con montos y se corta a 1200 caracteres.
 */
function at_rt_red_resumen(string $prompt): string {
	$d = function_exists('at_cc_json_de_payload') ? at_cc_json_de_payload($prompt) : json_decode($prompt, true);
	if (is_array($d)) {
		$lineas = [];
		foreach (['challenge_title', 'challenge_text', 'solution_title', 'solution_text'] as $k) {
			if (is_scalar($d[$k] ?? null) && trim((string) $d[$k]) !== '') { $lineas[] = trim((string) $d[$k]); }
		}
		foreach (['benefits' => ['title', 'text'], 'how_it_works' => ['step_title', 'step_text'], 'next_steps' => ['title', 'text']] as $k => [$titulo, $texto]) {
			foreach (array_slice(is_array($d[$k] ?? null) ? array_values($d[$k]) : [], 0, 12) as $item) {
				$linea = at_rt_red_linea_prosa($item, $titulo, $texto);
				if ($linea !== '') { $lineas[] = $linea; }
			}
		}
		$texto = implode("\n", $lineas);
	} elseif (preg_match('/^\s*[\[{]/', $prompt)) {
		return ''; // Parece JSON pero no se pudo leer: jamás se entrega crudo (llevaría los precios).
	} else {
		$texto = implode("\n", array_filter(preg_split('/\r\n|\r|\n/', $prompt), static fn(string $linea): bool => !preg_match('/^\s*\|/', $linea)));
	}
	$limpio = preg_replace("/\n{3,}/", "\n\n", at_rt_red_sin_montos($texto));
	return rtrim(mb_substr((string) $limpio, 0, 1200));
}

/**
 * Lo que lee la IA para redactar. Todo texto libre (transcripción, resumen, notas, pedido, historial) sale sin las líneas
 * con montos: la IA no ve precios. null si el seguimiento no existe.
 */
function at_rt_contexto_redaccion(int $id): ?array {
	global $wpdb;
	$s = at_rt_obtener($id);
	if (!$s) { return null; }
	$propuesta = ['status' => '', 'flujo' => null, 'transcripcion' => '', 'resumen' => ''];
	if (!empty($s['propuesta_id'])) {
		$p = $wpdb->get_row($wpdb->prepare("SELECT status, flujo, transcript_text, gamma_prompt_text FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", (int) $s['propuesta_id']), ARRAY_A);
		if ($p) {
			$propuesta = [
				'status' => (string) $p['status'],
				'flujo' => $p['flujo'] === null ? null : (string) $p['flujo'],
				'transcripcion' => mb_substr(at_rt_red_sin_montos((string) $p['transcript_text']), 0, 12000),
				'resumen' => at_rt_red_resumen((string) $p['gamma_prompt_text']),
			];
		}
	}
	$historial = [];
	if ((int) ($s['crm_cliente_id'] ?? 0) > 0) {
		// Se leen más de 10 y se descartan los que traen montos, para entregar los 10 más recientes que sirvan.
		$titulos = $wpdb->get_col($wpdb->prepare("SELECT titulo FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d ORDER BY id DESC LIMIT 40", (int) $s['crm_cliente_id']));
		foreach ($titulos as $titulo) {
			$titulo = trim((string) preg_replace('/\s+/', ' ', (string) $titulo));
			if ($titulo === '' || at_rt_tiene_montos($titulo)) { continue; }
			$historial[] = $titulo;
			if (count($historial) >= 10) { break; }
		}
	}
	$actuales = [];
	foreach (['quedamos', 'proponemos', 'partir', 'recomendacion'] as $k) {
		$actuales[$k] = at_rt_red_sin_montos(is_string($s['textos'][$k] ?? null) ? $s['textos'][$k] : '');
	}
	$demos = array_values(array_filter((array) $s['demo_urls'], static fn($u): bool => is_string($u) && $u !== '' && !at_rt_internas([$u])));
	return [
		'id' => (int) $s['id'],
		'generacion' => (string) ($s['redaccion']['generacion'] ?? ''),
		'nombre' => (string) $s['nombre'],
		'negocio' => (string) $s['negocio'],
		'demo_urls' => $demos,
		'notas' => at_rt_red_sin_montos((string) $s['notas']),
		'pedido' => at_rt_red_sin_montos((string) ($s['redaccion']['pedido'] ?? '')),
		'textos_actuales' => $actuales,
		'propuesta' => $propuesta,
		'historial' => $historial,
		'reglas' => at_rt_red_reglas(),
		'slides_foto' => at_rt_red_slides($s),
	];
}

/** Error de validación del callback: 422 y no se guarda nada. */
function at_rt_red_invalida(string $mensaje): WP_Error { return new WP_Error('retomar_redaccion', $mensaje, ['status' => 422]); }

/** Un enlace al servidor interno (easypanel o el host del renderer) nunca entra a un texto ni a un encargo. */
function at_rt_red_interno(array $textos): bool {
	if (at_rt_internas($textos)) { return true; }
	foreach ($textos as $t) {
		if (stripos((string) $t, at_pt_host_renderer()) !== false) { return true; }
	}
	return false;
}

/**
 * Valida la respuesta de la IA como lo haría la presentación (nada se recorta): devuelve {textos, image_briefs} limpios o
 * un 422. $slides son las láminas con foto permitidas para este seguimiento.
 */
function at_rt_red_validar(array $p, array $slides): array|WP_Error {
	$crudos = $p['textos'] ?? null;
	if (!is_array($crudos)) { return at_rt_red_invalida('textos debe ser un objeto con quedamos, proponemos, partir y recomendacion.'); }
	$r = at_rt_red_reglas();
	$textos = [];
	foreach (['quedamos', 'proponemos', 'partir'] as $k) {
		if (!is_string($crudos[$k] ?? null)) { return at_rt_red_invalida("{$k}: debe ser texto."); }
		$t = trim(sanitize_textarea_field($crudos[$k]));
		if ($t === '' || mb_strlen($t) > $r['max_caracteres'] || at_rt_red_lineas($t) > $r['max_lineas']) {
			return at_rt_red_invalida("{$k}: de 1 a {$r['max_caracteres']} caracteres y máximo {$r['max_lineas']} líneas.");
		}
		$textos[$k] = $t;
	}
	$recomendacion = $crudos['recomendacion'] ?? '';
	if (!is_string($recomendacion)) { return at_rt_red_invalida('recomendacion: debe ser texto.'); }
	$recomendacion = trim(sanitize_textarea_field($recomendacion));
	if (mb_strlen($recomendacion) > $r['max_recomendacion'] || at_rt_red_lineas($recomendacion) > 3) {
		return at_rt_red_invalida("recomendacion: hasta {$r['max_recomendacion']} caracteres y máximo 3 líneas.");
	}
	$textos['recomendacion'] = $recomendacion;
	$crudos = $p['image_briefs'] ?? [];
	if (!is_array($crudos) || $crudos !== array_values($crudos)) { return at_rt_red_invalida('image_briefs debe ser una lista de {slide, prompt}.'); }
	if (count($crudos) > count(at_rt_laminas_foto())) { return at_rt_red_invalida('Los encargos de foto admiten máximo 3 láminas.'); }
	$vistas = [];
	$briefs = [];
	foreach ($crudos as $b) {
		$slide = is_array($b) ? ($b['slide'] ?? null) : null;
		$prompt = is_array($b) && is_string($b['prompt'] ?? null) ? trim(sanitize_textarea_field($b['prompt'])) : '';
		if (!is_string($slide) || !in_array($slide, $slides, true)) { return at_rt_red_invalida('Cada encargo de foto debe ser de una lámina disponible para este seguimiento: ' . implode(', ', $slides) . '.'); }
		if (isset($vistas[$slide])) { return at_rt_red_invalida("Encargo de foto repetido para la lámina {$slide}."); }
		if ($prompt === '' || mb_strlen($prompt) > 1200) { return at_rt_red_invalida("Encargo de foto de {$slide}: de 1 a 1200 caracteres."); }
		$vistas[$slide] = true;
		$briefs[] = ['slide' => $slide, 'prompt' => $prompt];
	}
	if (at_rt_red_interno(array_merge(array_values($textos), array_column($briefs, 'prompt')))) {
		return at_rt_red_invalida('Los textos y los encargos no pueden llevar enlaces internos.');
	}
	return ['textos' => $textos, 'image_briefs' => $briefs];
}

/**
 * Respuesta mal formada del pedido vigente (ya bajo el candado): no se guarda ni un texto ni un encargo, pero la
 * redacción pasa a «error» con el motivo (sin enlaces) para que no quede «pendiente» para siempre. Una respuesta corregida
 * con la misma generación se sigue aceptando. Hace COMMIT y devuelve el 422.
 */
function at_rt_red_rechazar(int $id, array $redaccion, WP_Error $invalida): WP_Error {
	global $wpdb;
	if (!at_rt_guardar($id, ['redaccion' => array_merge($redaccion, ['estado' => 'error', 'nota' => at_rt_red_nota($invalida->get_error_message())])])) { return new WP_Error('retomar_guardar', 'No se pudo guardar el motivo del rechazo.', ['status' => 500]); }
	if ($wpdb->query('COMMIT') === false) { return new WP_Error('retomar_guardar', 'No se pudo confirmar el motivo del rechazo.', ['status' => 500]); }
	return $invalida;
}

/**
 * Callback de n8n con la redacción. Bajo FOR UPDATE y validado por generación (409 si no es el pedido vigente, sin tocar
 * nada); un fallo de transporte (estado «error») no impide aceptar la respuesta del mismo pedido, porque puede ser un
 * falso negativo. Una respuesta ya resuelta (lista o en revisión) no se acepta otra vez.
 *  - ok:false → estado «error» con la nota sin enlaces.
 *  - Con errores de forma del pedido vigente → 422, no se guarda ningún texto y la redacción queda en «error» con el motivo.
 *  - Con montos (en los textos o en un encargo) → la primera vez se pide de nuevo (intentos 2, responde {reintento:true},
 *    siempre HTTP 200); con intentos ≥ 2 → «revision», sin guardar textos.
 *  - Válida → textos con origen «ia», estado «lista», encargos de foto guardados y presentación pedida.
 */
function at_rt_guardar_redaccion(int $id, array $p): array|WP_Error {
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('retomar_guardar', 'No se pudo reservar la redacción.', ['status' => 500]); }
	$respuesta = null;
	$despues = '';
	$generacion_nueva = '';
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return new WP_Error('retomar_no_existe', 'No existe el seguimiento.', ['status' => 404]); }
		$s = at_rt_fila($fila);
		$r = $s['redaccion'];
		$generacion = $p['generacion'] ?? '';
		if (!is_string($generacion) || !preg_match('/^[a-f0-9]{16}$/D', $generacion) || !hash_equals((string) ($r['generacion'] ?? ''), $generacion) || in_array($r['estado'] ?? '', ['lista', 'revision'], true)) {
			return new WP_Error('retomar_generacion', 'La respuesta pertenece a una redacción anterior o ya resuelta.', ['status' => 409]);
		}
		// Solo una respuesta mal formada del pedido VIGENTE marca error (las de otro pedido ya salieron con 409 sin tocar nada).
		$invalida = isset($p['ok']) && is_bool($p['ok']) ? null : at_rt_red_invalida('ok debe ser booleano.');
		if ($invalida === null && $p['ok']) {
			$v = at_rt_red_validar($p, at_rt_red_slides($s));
			if (is_wp_error($v)) { $invalida = $v; }
		}
		if ($invalida !== null) { return at_rt_red_rechazar($id, $r, $invalida); }
		if (!$p['ok']) {
			$campos = ['redaccion' => array_merge($r, ['estado' => 'error', 'nota' => at_rt_red_nota($p['nota'] ?? '')])];
			$respuesta = ['ok' => true, 'estado' => 'error'];
		} else {
			$montos = false;
			foreach (array_merge(array_values($v['textos']), array_column($v['image_briefs'], 'prompt')) as $texto) {
				if (at_rt_tiene_montos($texto)) { $montos = true; break; }
			}
			if (!$montos) {
				$campos = [
					'textos' => array_merge($s['textos'], $v['textos']),
					'redaccion' => array_merge($r, ['estado' => 'lista', 'origen' => 'ia', 'image_briefs' => $v['image_briefs'], 'nota' => at_rt_red_nota($p['nota'] ?? '')]),
				];
				$respuesta = ['ok' => true, 'estado' => 'lista'];
				$despues = 'render';
			} elseif ((int) ($r['intentos'] ?? 1) < 2 && at_rt_red_flujo() !== '') {
				$nueva = at_rt_red_nueva($s, at_rt_red_pedido_sin_montos((string) ($r['pedido'] ?? '')), true);
				$campos = ['redaccion' => $nueva];
				$respuesta = ['ok' => true, 'reintento' => true];
				$despues = 'reintento';
				$generacion_nueva = $nueva['generacion'];
			} else {
				$campos = ['redaccion' => array_merge($r, ['estado' => 'revision', 'nota' => 'La IA devolvió montos o precios en dos intentos. Escribe los textos a mano.'])];
				$respuesta = ['ok' => true, 'estado' => 'revision'];
			}
		}
		if (!at_rt_guardar($id, $campos)) { return new WP_Error('retomar_guardar', 'No se pudo guardar la redacción.', ['status' => 500]); }
		if ($wpdb->query('COMMIT') === false) { return new WP_Error('retomar_guardar', 'No se pudo confirmar la redacción.', ['status' => 500]); }
	} finally { $wpdb->query('ROLLBACK'); }
	// Lo que sigue ocurre ya confirmado y fuera del candado: n8n o el renderer pueden tardar o fallar sin deshacer lo guardado.
	if ($despues === 'reintento') {
		$respuesta['pedido'] = at_rt_red_avisar($id, $generacion_nueva) === '' ? 'enviado' : 'error';
	} elseif ($despues === 'render') {
		try { $respuesta['render'] = at_rt_pedir_render($id) === '' ? 'pedido' : 'error'; }
		catch (Throwable $e) { error_log('Retomar: no se pudo pedir la presentación tras la redacción: ' . $e->getMessage()); $respuesta['render'] = 'error'; }
	}
	return $respuesta;
}

add_action('rest_api_init', static function (): void {
	register_rest_route('automatiza-tech/v1', '/retomar/(?P<id>\d+)/contexto', [
		'methods' => 'GET', 'permission_callback' => 'at_rt_auth', 'callback' => 'at_rt_rest_contexto',
	]);
	register_rest_route('automatiza-tech/v1', '/retomar/(?P<id>\d+)/redaccion', [
		'methods' => 'POST', 'permission_callback' => 'at_rt_auth', 'callback' => 'at_rt_rest_redaccion',
	]);
});

function at_rt_rest_contexto(WP_REST_Request $r): WP_REST_Response|WP_Error {
	$contexto = at_rt_contexto_redaccion((int) $r['id']);
	return $contexto === null ? new WP_Error('retomar_no_existe', 'No existe el seguimiento.', ['status' => 404]) : new WP_REST_Response($contexto, 200);
}

function at_rt_rest_redaccion(WP_REST_Request $r): WP_REST_Response|WP_Error {
	$p = $r->get_json_params();
	if (!is_array($p)) { return new WP_Error('retomar_callback', 'El cuerpo debe ser un objeto JSON.', ['status' => 422]); }
	$res = at_rt_guardar_redaccion((int) $r['id'], $p);
	return is_wp_error($res) ? $res : new WP_REST_Response($res, 200);
}
