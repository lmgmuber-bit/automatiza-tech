<?php
// Datos de prueba de la pestaña del plan (Task 9) y de la agenda (Task 10). Todo va marcado [PRUEBA], con correos
// prueba-plan-…@example.com, y ptc_limpiar() lo borra: se registra al cerrar el proceso (también si la prueba se cae
// con un error fatal o un exit), salvo que el script defina PTC_CONSERVAR antes de cargar este archivo (los datos
// para mirar la pestaña en el navegador). Se carga después de wp-bootstrap.php.
$GLOBALS['ptc_creado'] = ['crm' => [], 'tech' => [], 'contratos' => [], 'propuestas' => [], 'planes' => [], 'reuniones' => []];

// Desde el proceso de la prueba no sale ninguna llamada HTTP (n8n, correo por API, cron) ni ningún correo: se cortan
// aquí. Los procesos hijos (accion-wp-test-run.php) tienen su propio filtro, que contesta y anota las llamadas a n8n.
add_filter('pre_http_request', function ($pre, $args, $url) {
	return new WP_Error('prueba_sin_red', 'Llamada HTTP cortada en la prueba: ' . $url);
}, PHP_INT_MAX, 3);
add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);

function ptc_marca(): string {
	return strtolower(wp_generate_password(6, false, false));
}

/** Cliente del CRM con su ficha operativa enlazada. $correo '' = cliente sin correo (y sin portal). */
function ptc_cliente(string $marca, string $correo): array {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_clientes', [
		'nombre' => '[PRUEBA] Cliente Plan ' . $marca, 'email' => $correo, 'empresa' => '[PRUEBA] Empresa ' . $marca,
		'telefono' => '+56 9 1111 1111', 'tipo' => 'cliente', 'estado' => 'contratado',
	]);
	$crm = (int) $wpdb->insert_id;
	$wpdb->insert($wpdb->prefix . 'automatiza_tech_clients', [
		'name' => 'Cliente Prueba', 'email' => $correo, 'company' => '[PRUEBA] Empresa ' . $marca, 'crm_cliente_id' => $crm,
	]);
	$tech = (int) $wpdb->insert_id;
	if ($crm <= 0 || $tech <= 0) {
		fwrite(STDERR, "No se pudo crear el cliente de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['crm'][] = $crm;
	$GLOBALS['ptc_creado']['tech'][] = $tech;
	return ['crm' => $crm, 'tech' => $tech];
}

/** Propuesta mínima (solo para que el contrato tenga proposal_id). */
function ptc_propuesta(string $marca): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', [
		'client_email' => "prueba-plan-{$marca}@example.com", 'unique_link_id' => substr(md5($marca . microtime(true)), 0, 12),
		'client_name' => 'Cliente Prueba', 'company_name' => '[PRUEBA] Empresa ' . $marca, 'status' => 'aceptada', 'flujo' => 'v3',
		'gamma_prompt_text' => '{}', 'transcript_text' => '', 'system_prompt_text' => '', 'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	if ($id <= 0) {
		fwrite(STDERR, "No se pudo crear la propuesta de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['propuestas'][] = $id;
	return $id;
}

/** Contrato (por defecto de servicios y firmado) de una ficha operativa, con los marcadores que lee el plan. */
function ptc_contrato(int $tech, string $marca, ?int $propuesta, string $tipo = 'servicios', string $estado = 'signed', string $correo = ''): int {
	global $wpdb;
	$ph = [
		'servicios_contratados' => '- **Sitio web**: $1', 'alcance' => 'Sitio web.', 'entregables' => '- Sitio publicado',
		'plazo' => 'Se define en la reunión de inicio.', 'fases_siguientes' => 'La propuesta no tiene fases siguientes.',
		'nombre_proyecto' => '[PRUEBA] Sitio ' . $marca, 'razon_social_cliente' => '[PRUEBA] Razón social ' . $marca,
		'representante_cliente_nombre' => 'Cliente Prueba', 'email_cliente' => $correo, 'telefono_cliente' => '+56 9 2222 2222',
	];
	$wpdb->insert($wpdb->prefix . 'automatiza_contracts', [
		'client_id' => $tech, 'proposal_id' => $propuesta, 'contract_number' => 'PRUEBA-PT-' . $marca . '-' . count($GLOBALS['ptc_creado']['contratos']),
		'type' => $tipo, 'template_id' => $tipo === 'servicios' ? 'servicios_v1' : 'soporte_v2',
		'placeholders' => wp_json_encode($ph, JSON_UNESCAPED_UNICODE), 'status' => $estado,
		'signed_at' => $estado === 'signed' ? current_time('mysql') : null, 'sign_token' => bin2hex(random_bytes(32)),
	]);
	$id = (int) $wpdb->insert_id;
	if ($id <= 0) {
		fwrite(STDERR, "No se pudo crear el contrato de prueba: {$wpdb->last_error}\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['contratos'][] = $id;
	return $id;
}

/** Plan del contrato recién creado (estado generando, sin payload), sin llamar a n8n. */
function ptc_plan(int $contrato): object {
	$id = at_pt_crear_plan($contrato);
	if (is_wp_error($id) || (int) $id <= 0) {
		fwrite(STDERR, 'No se pudo crear el plan de prueba: ' . (is_wp_error($id) ? $id->get_error_message() : 'id 0') . "\n");
		exit(2);
	}
	$GLOBALS['ptc_creado']['planes'][] = (int) $id;
	return at_pt_plan((int) $id);
}

/** Lo que devolvería la IA: tres fases, días propuestos, 10 briefs de foto (uno por lámina). */
function ptc_plan_base(): array {
	$briefs = [];
	foreach (['cover', 'metodo', 'gantt', 'fase_1', 'fase_2', 'fase_3', 'necesitamos', 'reuniones', 'portal', 'cierre'] as $s) {
		$briefs[] = ['slide' => $s, 'prompt' => 'small business owner at a counter, natural light, no text'];
	}
	$act = function (string $nombre, string $resp, int $dias, string $servicio, string $etapa, bool $paralelo = false): array {
		return ['nombre' => $nombre, 'detalle' => '', 'responsable' => $resp, 'dias_habiles' => $dias, 'servicio' => $servicio, 'etapa' => $etapa, 'en_paralelo' => $paralelo];
	};
	return [
		'version' => 1, 'proyecto' => '[PRUEBA] Sitio del panel', 'fecha_firma' => '2026-09-28',
		'fases' => [
			['clave' => 'diseno_desarrollo', 'descripcion' => 'Diseñamos y construimos el sitio.', 'bloques' => [
				['nombre' => 'Diseño', 'entrega' => true, 'entregable' => 'Maqueta aprobada', 'actividades' => [
					$act('Maqueta de la portada', 'at', 2, 'sitio_web_tienda', 'diseno'),
					$act('Maqueta de páginas internas', 'at', 2, 'sitio_web_tienda', 'diseno'),
				]],
				['nombre' => 'Desarrollo', 'entrega' => false, 'entregable' => 'Sitio en pruebas', 'actividades' => [
					$act('Construcción del sitio', 'at', 8, 'sitio_web_tienda', 'desarrollo'),
				]],
			]],
			['clave' => 'implementacion', 'descripcion' => 'Publicamos y te capacitamos.', 'bloques' => [
				['nombre' => 'Puesta en marcha', 'entrega' => false, 'entregable' => 'Sitio publicado', 'actividades' => [
					$act('Publicación en tu dominio', 'at', 2, 'sitio_web_tienda', 'implementacion'),
					$act('Capacitación', 'ambos', 1, '', ''),
				]],
			]],
			['clave' => 'soporte', 'descripcion' => 'Acompañamiento después de la entrega.', 'bloques' => [
				['nombre' => 'Acompañamiento', 'entrega' => false, 'entregable' => '', 'actividades' => [
					$act('Ajustes de la primera semana', 'at', 5, '', 'soporte'),
				]],
			]],
		],
		'hitos' => [['nombre' => 'Diseño aprobado', 'despues_de' => 'Diseño']],
		'necesitamos_de_ti' => ['Logo y colores', 'Acceso al dominio'],
		'reuniones' => [['nombre' => 'Reunión de inicio', 'detalle' => 'Revisamos el plan juntos']],
		'soporte' => ['garantia_meses' => 3, 'mensuales' => []],
		'image_briefs' => $briefs,
	];
}

/** Deja el plan en borrador con el payload de ptc_plan_base() validado, con la tabla aplicada y con fechas. */
function ptc_sembrar(int $plan_id, string $inicio = '2026-10-05'): array {
	$v = at_pt_validar_plan(ptc_plan_base());
	if (empty($v['ok'])) {
		fwrite(STDERR, 'El plan base de prueba no valida: ' . implode(' | ', (array) $v['errores']) . "\n");
		exit(2);
	}
	$plan = at_pt_calcular_fechas(at_pt_aplicar_tabla($v['plan'], at_pt_duraciones_defecto()), $inicio, []);
	at_pt_guardar($plan_id, ['payload' => $plan, 'fecha_inicio' => $inicio, 'estado' => 'borrador', 'nota' => '']);
	return $plan;
}

/** Fuerza un estado (y su nota) sin pasar por las transiciones: solo para preparar casos. */
function ptc_estado(int $plan_id, string $estado, string $nota = ''): void {
	at_pt_guardar($plan_id, ['estado' => $estado, 'nota' => $nota]);
}

/** Primera actividad con ese nombre en el payload ([] si no está). */
function ptc_actividad(array $plan, string $nombre): array {
	foreach ((array) ($plan['fases'] ?? []) as $f) {
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				if (($a['nombre'] ?? '') === $nombre) {
					return $a;
				}
			}
		}
	}
	return [];
}

/**
 * Lo que manda plan-trabajo.js en plan_json (fases → bloques → actividades con los campos editables, en ese orden de
 * claves y con los días como número). panel-js-wp-test.php exige que serializar() dé exactamente esto.
 */
function ptc_plan_json(array $payload): string {
	$fases = [];
	foreach ((array) ($payload['fases'] ?? []) as $f) {
		$bloques = [];
		foreach ((array) ($f['bloques'] ?? []) as $b) {
			$acts = [];
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				$acts[] = [
					'nombre' => (string) $a['nombre'], 'detalle' => (string) ($a['detalle'] ?? ''), 'responsable' => (string) $a['responsable'],
					'dias_habiles' => (int) $a['dias_habiles'], 'en_paralelo' => !empty($a['en_paralelo']), 'servicio' => (string) ($a['servicio'] ?? ''),
					'etapa' => (string) ($a['etapa'] ?? ''), 'origen' => (string) ($a['origen'] ?? ''),
				];
			}
			$bloques[] = ['nombre' => (string) $b['nombre'], 'entregable' => (string) ($b['entregable'] ?? ''), 'entrega' => !empty($b['entrega']), 'actividades' => $acts];
		}
		$fases[] = ['clave' => (string) $f['clave'], 'descripcion' => (string) ($f['descripcion'] ?? ''), 'bloques' => $bloques];
	}
	return (string) wp_json_encode(['fases' => $fases], JSON_UNESCAPED_UNICODE);
}

/** Fila del CRM como la recibe at_pt_render_pestana() (ARRAY_A, igual que la ficha). */
function ptc_cliente_fila(int $crm): array {
	global $wpdb;
	return (array) $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm), ARRAY_A);
}

/** Borra todo lo creado por estas ayudas (y los planes que se hayan creado para sus contratos). */
function ptc_limpiar(): void {
	global $wpdb;
	$c = $GLOBALS['ptc_creado'];
	foreach ($c['planes'] as $id) {
		$wpdb->delete(at_pt_tabla(), ['id' => (int) $id]);
	}
	foreach ($c['contratos'] as $id) {
		$wpdb->delete(at_pt_tabla(), ['contrato_id' => (int) $id]);
		$wpdb->delete($wpdb->prefix . 'automatiza_contracts', ['id' => (int) $id]);
	}
	foreach ($c['propuestas'] as $id) {
		$wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => (int) $id]);
	}
	foreach ($c['reuniones'] as $id) {
		$wpdb->delete($wpdb->prefix . 'automatiza_followup_meetings', ['id' => (int) $id]);
	}
	foreach ($c['tech'] as $id) {
		$wpdb->delete($wpdb->prefix . 'automatiza_tech_clients', ['id' => (int) $id]);
	}
	foreach ($c['crm'] as $id) {
		$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => (int) $id]);
		$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => (int) $id]);
	}
	// Ya borrado: la llamada del cierre del proceso (o una segunda llamada) no repite nada.
	$GLOBALS['ptc_creado'] = ['crm' => [], 'tech' => [], 'contratos' => [], 'propuestas' => [], 'planes' => [], 'reuniones' => []];
}

if (!defined('PTC_CONSERVAR')) {
	register_shutdown_function('ptc_limpiar');
}
