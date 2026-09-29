<?php
// Datos y ayudas de las pruebas WordPress del plan (base LOCAL compartida). Todo lo que se crea va
// marcado [PRUEBA] y se borra al terminar (también si la prueba se cae: register_shutdown_function).
// El repositorio es público: nada de nombres, correos ni teléfonos reales («Cliente Prueba», @example.com).
// Ninguna llamada HTTP ni correo sale del equipo: las llamadas a los flujos del plan se anotan en
// $GLOBALS['pt_http'] y responden $GLOBALS['pt_http_respuesta'] (código HTTP o WP_Error); lo demás se bloquea.

$GLOBALS['pt_creados'] = ['crm' => [], 'tech' => [], 'propuesta' => [], 'contrato' => [], 'archivo' => []];
$GLOBALS['pt_http'] = [];
$GLOBALS['pt_http_respuesta'] = 200;
$GLOBALS['pt_correos'] = [];

add_filter('pre_wp_mail', function ($nulo, $atts) {
	$GLOBALS['pt_correos'][] = $atts;
	return true;
}, 10, 2);

add_filter('pre_http_request', function ($pre, $args, $url) {
	$flujos = [];
	foreach (['AT_N8N_PLAN_BORRADOR', 'AT_N8N_PLAN_CAMBIOS', 'AT_N8N_PLAN_RENDER'] as $c) {
		if (defined($c)) {
			$flujos[] = constant($c);
		}
	}
	if (!in_array((string) $url, $flujos, true)) {
		return new WP_Error('bloqueado_en_prueba', 'Llamada externa bloqueada en la prueba del plan');
	}
	$GLOBALS['pt_http'][] = ['url' => (string) $url, 'args' => $args, 'cuerpo' => json_decode((string) ($args['body'] ?? ''), true)];
	$r = $GLOBALS['pt_http_respuesta'];
	if ($r instanceof WP_Error) {
		return $r;
	}
	return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => (int) $r, 'message' => ''], 'cookies' => [], 'filename' => null];
}, 10, 3);

/** Llamadas anotadas a un flujo: 'borrador', 'cambios' o 'render'. */
function pt_llamadas(string $cual): array {
	$url = ['borrador' => AT_N8N_PLAN_BORRADOR, 'cambios' => AT_N8N_PLAN_CAMBIOS, 'render' => AT_N8N_PLAN_RENDER][$cual];
	return array_values(array_filter($GLOBALS['pt_http'], function ($l) use ($url) { return $l['url'] === $url; }));
}

function pt_marca(): string {
	return 'prueba-plan-' . strtolower(wp_generate_password(6, false, false));
}

/** Cliente del CRM y su ficha operativa. Sin correo: el CRM queda sin correo (sin portal). $enlazar=false: ficha sin crm_cliente_id. */
function pt_cliente(string $marca, bool $con_correo = true, bool $enlazar = true): array {
	global $wpdb;
	$correo = $con_correo ? $marca . '@example.com' : '';
	$wpdb->insert($wpdb->prefix . 'crm_clientes', ['nombre' => '[PRUEBA] Cliente ' . $marca, 'email' => $correo, 'empresa' => '[PRUEBA] Empresa ' . $marca, 'tipo' => 'cliente', 'estado' => 'contratado', 'origen' => 'prueba_plan']);
	$crm = (int) $wpdb->insert_id;
	if (!$crm) {
		fwrite(STDERR, "No se pudo crear el cliente de prueba en el CRM: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['crm'][] = $crm;
	$tech = pt_ficha($enlazar ? $crm : 0, $marca, $con_correo ? $correo : 'sin-correo-' . $marca . '@example.com');
	return ['crm' => $crm, 'tech' => $tech];
}

/** Ficha operativa (automatiza_tech_clients) enlazada a $crm (0 = sin enlazar). */
function pt_ficha(int $crm, string $marca, string $correo = ''): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', [
		'name'           => 'Cliente Prueba',
		'email'          => $correo !== '' ? $correo : 'ficha-' . $marca . '@example.com',
		'company'        => '[PRUEBA] Empresa ' . $marca,
		'crm_cliente_id' => $crm > 0 ? $crm : null,
	]);
	$tech = (int) $wpdb->insert_id;
	if (!$tech) {
		fwrite(STDERR, "No se pudo crear la ficha operativa de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['tech'][] = $tech;
	return $tech;
}

/** Propuesta v3 aceptada. Su unique_link_id es substr(md5($marca), 0, 12). */
function pt_propuesta(string $marca, array $o = []): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email'       => $marca . '@example.com',
		'unique_link_id'     => substr(md5($marca), 0, 12),
		'client_name'        => 'Cliente Prueba',
		'company_name'       => '[PRUEBA] Empresa ' . $marca,
		'phone'              => '',
		'status'             => 'aceptada',
		'flujo'              => 'v3',
		'gamma_prompt_text'  => wp_json_encode(['solution_text' => 'Sitio de una página con formulario de contacto.', 'how_it_works' => [['step_title' => 'Diseño', 'step_text' => 'Maqueta de la portada'], 'Publicación en tu dominio']], JSON_UNESCAPED_UNICODE),
		'transcript_text'    => $o['transcript'] ?? 'Reunión de prueba: quieren un sitio de una página.',
		'system_prompt_text' => '',
		'created_at'         => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) {
		fwrite(STDERR, "No se pudo crear la propuesta de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['propuesta'][] = $id;
	return $id;
}

/**
 * Contrato directo en la tabla (sin PDF ni correos). Por defecto: servicios, firmado el viernes
 * 2026-10-09 a las 15:00. $o: type, status, signed_at, ph (marcadores, reemplaza los de defecto), ph_mas (se suman a
 * los de defecto).
 */
function pt_contrato(int $tech, ?int $propuesta, array $o = []): int {
	global $wpdb;
	$tipo = $o['type'] ?? 'servicios';
	$estado = $o['status'] ?? 'signed';
	$ph = ($o['ph_mas'] ?? []) + ($o['ph'] ?? [
		'nombre_proyecto'              => '[PRUEBA] Sitio de una página',
		'razon_social_cliente'         => '[PRUEBA] Razón Social SpA',
		'representante_cliente_nombre' => 'Cliente Prueba',
		'email_cliente'                => 'prueba-plan-contrato@example.com',
		'servicios_contratados'        => '- **Sitio de una página**: $1',
		'alcance'                      => 'Sitio de una página con formulario.',
		'entregables'                  => "- Sitio publicado\n- Formulario de contacto",
		'plazo'                        => 'Se define con EL CLIENTE en la reunión de inicio y queda por escrito.',
		'fases_siguientes'             => 'La propuesta no tiene fases siguientes.',
	]);
	$wpdb->insert($wpdb->prefix . 'automatiza_contracts', [
		'client_id'       => $tech,
		'proposal_id'     => $propuesta,
		'contract_number' => strtoupper('PRUEBA-PLAN-' . substr(md5(uniqid('', true)), 0, 10)),
		'type'            => $tipo,
		'template_id'     => $tipo === 'servicios' ? 'servicios_v1' : 'soporte_v2',
		'placeholders'    => wp_json_encode($ph, JSON_UNESCAPED_UNICODE),
		'status'          => $estado,
		'sign_token'      => bin2hex(random_bytes(32)),
		'at_review_token' => bin2hex(random_bytes(32)),
		'signed_at'       => $estado === 'signed' ? ($o['signed_at'] ?? '2026-10-09 15:00:00') : null,
		'expires_at'      => date('Y-m-d H:i:s', strtotime('+10 days')),
		'created_at'      => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if (!$id) {
		fwrite(STDERR, "No se pudo crear el contrato de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['pt_creados']['contrato'][] = $id;
	return $id;
}

function pt_contrato_fila(int $id): ?object {
	global $wpdb;
	return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", $id)) ?: null;
}

/** Archivo que la prueba generó (PDF firmado, imagen de firma): se borra al terminar. */
function pt_archivo(string $ruta): void {
	$GLOBALS['pt_creados']['archivo'][] = $ruta;
}

/** Un id de plan que no existe. */
function pt_plan_inexistente(): int {
	global $wpdb;
	return (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) + 1000 FROM ' . at_pt_tabla());
}

/** Un id de contrato que no existe. */
function pt_contrato_inexistente(): int {
	global $wpdb;
	return (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) + 1000 FROM {$wpdb->prefix}automatiza_contracts");
}

function pt_limpiar(): void {
	global $wpdb;
	$c = $GLOBALS['pt_creados'];
	$en = function (array $ids): string { return implode(',', array_map('intval', $ids ?: [0])); };
	if (function_exists('at_pt_tabla') && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like(at_pt_tabla()))) === at_pt_tabla()) {
		$wpdb->query('DELETE FROM ' . at_pt_tabla() . ' WHERE contrato_id IN (' . $en($c['contrato']) . ')');
		// Restos de corridas anteriores que se cayeron: planes cuyo contrato ya no existe (solo en esta base local).
		$wpdb->query('DELETE p FROM ' . at_pt_tabla() . " p LEFT JOIN {$wpdb->prefix}automatiza_contracts k ON k.id = p.contrato_id WHERE k.id IS NULL");
	}
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_contracts WHERE id IN (" . $en($c['contrato']) . ')');
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_propuestas WHERE id IN (" . $en($c['propuesta']) . ')');
	$wpdb->query("DELETE FROM {$wpdb->prefix}automatiza_tech_clients WHERE id IN (" . $en($c['tech']) . ')');
	$wpdb->query("DELETE FROM {$wpdb->prefix}crm_clientes WHERE id IN (" . $en($c['crm']) . ')');
	foreach ($c['archivo'] as $f) {
		if (is_file($f)) {
			@unlink($f);
		}
	}
	$GLOBALS['pt_creados'] = ['crm' => [], 'tech' => [], 'propuesta' => [], 'contrato' => [], 'archivo' => []];
}
register_shutdown_function('pt_limpiar');

/** Petición REST a automatiza-tech/v1. $clave: true = AT_REST_SECRET, false = sin cabecera, texto = esa clave. */
function pt_pedir(string $metodo, string $ruta, $cuerpo = null, $clave = true, array $query = []): WP_REST_Response {
	$r = new WP_REST_Request($metodo, '/automatiza-tech/v1' . $ruta);
	if ($clave === true) {
		$r->set_header('x-at-secret', AT_REST_SECRET);
	} elseif (is_string($clave)) {
		$r->set_header('x-at-secret', $clave);
	}
	if ($query) {
		$r->set_query_params($query);
	}
	if ($cuerpo !== null) {
		$r->set_header('content-type', 'application/json');
		$r->set_body(is_string($cuerpo) ? $cuerpo : wp_json_encode($cuerpo));
	}
	return rest_do_request($r);
}
