<?php
/** Render de Retomar: láminas públicas y callback privado, sin generación de fotos. */
if (!defined('ABSPATH')) { exit; }

/** Rechaza entradas incompatibles en vez de perder contenido silenciosamente. */
function at_rt_armar_render(array $seg, string $generacion = ''): array {
	$t = is_array($seg['textos'] ?? null) ? $seg['textos'] : [];
	$token = (string) ($seg['token'] ?? '');
	if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { throw new InvalidArgumentException('El seguimiento no tiene token válido.'); }
	if ($generacion !== '' && !preg_match('/^[a-f0-9]{16}$/D', $generacion)) { throw new InvalidArgumentException('Generación de render inválida.'); }
	global $wpdb;
	$uid = !empty($seg['propuesta_id']) ? (string) $wpdb->get_var($wpdb->prepare("SELECT unique_link_id FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", (int) $seg['propuesta_id'])) : '';
	$fotos = $uid !== '' ? at_pt_fotos_propuesta_wp($uid) : [];
	$imagen = (string) ($seg['imagen_url'] ?? '');
	$p = [
		'document_type' => 'retomar', 'unique_id' => $token . ($generacion !== '' ? '-' . $generacion : '') . '-retomar',
		'company_name' => (string) ($seg['negocio'] ?? ''), 'client_name' => (string) ($seg['nombre'] ?? ''),
		'proyecto' => (string) ($t['proyecto'] ?? $seg['negocio'] ?? ''),
		'quedamos' => (string) ($t['quedamos'] ?? ''), 'proponemos' => (string) ($t['proponemos'] ?? ''), 'partir' => (string) ($t['partir'] ?? ''),
		'demo_urls' => array_values((array) ($seg['demo_urls'] ?? [])), 'demo_imagen' => (string) ($seg['imagen_url'] ?? ''),
		'propuesta_url' => (string) (($seg['propuesta_url'] ?? '') ?: ($uid !== '' ? home_url('/ver-presentacion.php?id=' . rawurlencode($uid)) : '')),
		'agenda' => ['web_url' => home_url('/retomar.php?t=' . $token . '&agendar=1')],
		'fotos' => ['portada' => (string) ($fotos['cover'] ?? $imagen), 'cierre' => (string) ($fotos['cierre'] ?? $imagen)],
	];
	foreach (['company_name' => 120, 'client_name' => 120, 'proyecto' => 180, 'quedamos' => 600, 'proponemos' => 600, 'partir' => 600] as $campo => $max) {
		if (mb_strlen($p[$campo]) > $max) { throw new InvalidArgumentException("{$campo}: máximo {$max} caracteres para la presentación."); }
		if (in_array($campo, ['quedamos', 'proponemos', 'partir'], true) && count(preg_split('/\r\n|\r|\n/', $p[$campo])) > 6) {
			throw new InvalidArgumentException("{$campo}: máximo 6 líneas para la presentación.");
		}
	}
	if (count($p['demo_urls']) > 6) { throw new InvalidArgumentException('La presentación admite máximo 6 demos.'); }
	// Solo se comprueba el payload: render.view_url es interno y nunca se incorpora aquí.
	if (stripos(wp_json_encode($p), 'easypanel') !== false || stripos(wp_json_encode($p), at_pt_host_renderer()) !== false) {
		throw new InvalidArgumentException('La presentación contiene un enlace interno: usa enlaces públicos.');
	}
	foreach (array_merge($p['demo_urls'], [$p['demo_imagen'], $p['propuesta_url']], array_values($p['fotos'])) as $url) {
		if (!is_string($url) || ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || wp_parse_url($url, PHP_URL_USER) !== null))) {
			throw new InvalidArgumentException('Las demos, propuesta y fotos deben usar URLs HTTPS públicas válidas.');
		}
	}
	return $p;
}

function at_rt_pedir_render(int $id): string {
	if (!defined('AT_N8N_RETOMAR_RENDER') || AT_N8N_RETOMAR_RENDER === '') { return 'sin_flujo'; }
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return 'No se pudo reservar la presentación.'; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return 'El seguimiento no existe.'; }
		$s = at_rt_fila($fila);
		$generacion = bin2hex(random_bytes(8));
		try {
			$huella = hash('sha256', wp_json_encode(at_rt_armar_render($s)));
			$payload = at_rt_armar_render($s, $generacion);
		} catch (InvalidArgumentException $e) {
			at_rt_guardar($id, ['render' => ['estado' => 'error', 'nota' => $e->getMessage()]]);
			$wpdb->query('COMMIT');
			return $e->getMessage();
		}
		$render = ['generacion' => $generacion, 'huella' => $huella, 'uid' => $payload['unique_id'], 'estado' => 'pendiente', 'nota' => ''];
		if (!at_rt_guardar($id, ['render' => $render]) || $wpdb->query('COMMIT') === false) { return 'No se pudo guardar el estado del render.'; }
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno/error.
	}
	$error = at_pt_llamar_n8n(AT_N8N_RETOMAR_RENDER, ['id' => $id, 'generacion' => $generacion, 'render' => $payload]);
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

add_action('rest_api_init', static function () {
	register_rest_route('automatiza-tech/v1', '/retomar/(?P<id>\d+)/vista', [
		'methods' => 'POST', 'callback' => 'at_rt_rest_vista', 'permission_callback' => 'automatiza_proposals_rest_auth',
	]);
});

/** Solo archivos exactos del seguimiento en el host del renderer: nada de iframes arbitrarios. */
function at_rt_url_render_valida(string $url, string $token, bool $pdf = false, string $generacion = ''): bool {
	if (!preg_match('/^[a-f0-9]{32}$/D', $token) || ($generacion !== '' && !preg_match('/^[a-f0-9]{16}$/D', $generacion))) { return false; }
	$version = $generacion !== '' ? '-' . $generacion : '(?:-[a-f0-9]{16})?';
	$archivo = $pdf ? 'presentation\\.pdf' : '(?:index\\.html)?';
	return (bool) preg_match('~^https://' . preg_quote(at_pt_host_renderer(), '~') . '/p/' . $token . $version . '-retomar/' . $archivo . '$~D', $url);
}

function at_rt_rest_vista(WP_REST_Request $r) {
	$p = $r->get_json_params();
	if (!is_array($p) || !isset($p['ok']) || !is_bool($p['ok'])) { return new WP_Error('retomar_callback', 'ok debe ser booleano.', ['status' => 422]); }
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('retomar_guardar', 'No se pudo reservar el render.', ['status' => 500]); }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", (int) $r['id']), ARRAY_A);
		if (!$fila) { return new WP_Error('retomar_no_existe', 'No existe el seguimiento.', ['status' => 404]); }
		$s = at_rt_fila($fila);
		$generacion = $p['generacion'] ?? '';
		try { $huella = hash('sha256', wp_json_encode(at_rt_armar_render($s))); }
		catch (InvalidArgumentException $e) { $huella = ''; }
		if (!is_string($generacion) || !preg_match('/^[a-f0-9]{16}$/D', $generacion) || !hash_equals((string) ($s['render']['generacion'] ?? ''), $generacion) || $huella === '' || !hash_equals((string) ($s['render']['huella'] ?? ''), $huella)) {
			return new WP_Error('retomar_generacion', 'La respuesta pertenece a una presentación anterior.', ['status' => 409]);
		}
		$view = $p['view_url'] ?? ''; $pdf = $p['pdf_url'] ?? '';
		if (!is_string($view) || !is_string($pdf) || ($view !== '' && !at_rt_url_render_valida($view, $s['token'], false, $generacion)) || ($pdf !== '' && !at_rt_url_render_valida($pdf, $s['token'], true, $generacion)) || ($p['ok'] && $view === '')) {
			return new WP_Error('retomar_url', 'El render debe pertenecer a este seguimiento y al renderer HTTPS autorizado.', ['status' => 422]);
		}
		$nota = is_string($p['nota'] ?? '') ? sanitize_textarea_field($p['nota'] ?? '') : '';
		// La nota se muestra en el panel; no conservar URLs internas en mensajes reutilizables.
		$nota = preg_replace('~https?://\S+~i', '[enlace omitido]', $nota);
		$render = array_merge($s['render'], ['estado' => $p['ok'] ? 'listo' : 'error', 'nota' => mb_substr($nota, 0, 1000)]);
		if ($p['ok']) { $render['view_url'] = $view; $render['pdf_url'] = $pdf; }
		if (!at_rt_guardar($s['id'], ['render' => $render])) { return new WP_Error('retomar_guardar', 'No se pudo guardar el render.', ['status' => 500]); }
		if ($wpdb->query('COMMIT') === false) { return new WP_Error('retomar_guardar', 'No se pudo confirmar el render.', ['status' => 500]); }
		return new WP_REST_Response(['ok' => true, 'estado' => $render['estado']], 200);
	} finally { $wpdb->query('ROLLBACK'); }
}
