<?php
/**
 * Plan de trabajo: tabla propia (wp_automatiza_planes_trabajo), lecturas y escrituras.
 * Un contrato de servicios firmado tiene a lo más un plan (índice único uniq_contrato).
 * Lo contratado sale del contrato; la propuesta, si existe, solo aporta extracto de la reunión y fotos.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_pt_tabla(): string {
	global $wpdb;
	return $wpdb->prefix . 'automatiza_planes_trabajo';
}

/**
 * Crea la tabla con dbDelta. Idempotente (opción at_plan_schema = '1'). Si falla (tabla bloqueada,
 * sin privilegio en el hosting), espera 5 minutos antes de reintentar, igual que at_cc_migrar_esquema().
 */
function at_pt_migrar_esquema(): void {
	if (get_option('at_plan_schema') === '1' || get_transient('at_pt_migrar_intento')) {
		return;
	}
	global $wpdb;
	$t = at_pt_tabla();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta("CREATE TABLE {$t} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contrato_id BIGINT UNSIGNED NOT NULL,
  crm_cliente_id BIGINT UNSIGNED NULL,
  tech_id BIGINT UNSIGNED NULL,
  propuesta_id BIGINT UNSIGNED NULL,
  codigo CHAR(12) NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'generando',
  fecha_inicio DATE NULL,
  payload LONGTEXT NULL,
  comentarios TEXT NULL,
  nota TEXT NULL,
  view_url VARCHAR(500) NULL,
  pdf_url VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  enviado_at DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_contrato (contrato_id),
  UNIQUE KEY uniq_codigo (codigo),
  KEY idx_crm (crm_cliente_id)
) {$charset};");
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) !== $t) {
		set_transient('at_pt_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
		error_log('at_pt: no se pudo crear ' . $t . ': ' . $wpdb->last_error);
		return;
	}
	update_option('at_plan_schema', '1');
}
add_action('admin_init', 'at_pt_migrar_esquema');

/** Un plan por id; null si no existe. */
function at_pt_plan(int $id): ?object {
	if ($id <= 0) {
		return null;
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pt_tabla() . ' WHERE id = %d', $id));
	return $f ?: null;
}

/** Un plan por su código (12 letras o números, distingue mayúsculas); null si no existe. */
function at_pt_plan_por_codigo(string $codigo): ?object {
	if (!preg_match('/^[A-Za-z0-9]{12}$/', $codigo)) {
		return null;
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pt_tabla() . ' WHERE codigo = %s', $codigo));
	// La columna compara sin distinguir mayúsculas (collation *_ci): se exige el código exacto.
	return ($f && hash_equals((string) $f->codigo, $codigo)) ? $f : null;
}

/** El plan de un contrato (hay a lo más uno); null si no tiene. */
function at_pt_plan_de_contrato(int $contrato_id): ?object {
	if ($contrato_id <= 0) {
		return null;
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d LIMIT 1', $contrato_id));
	return $f ?: null;
}

/**
 * Planes de un cliente del CRM, del más nuevo al más viejo. Manda el enlace actual de la ficha
 * operativa (si la ficha se enlazó después de crear el plan, el plan igual aparece); si la ficha
 * no está enlazada, el cliente que quedó anotado en el plan.
 */
function at_pt_planes_de_crm(int $crm_id): array {
	if ($crm_id <= 0) {
		return [];
	}
	at_pt_migrar_esquema();
	global $wpdb;
	return (array) $wpdb->get_results($wpdb->prepare(
		'SELECT p.* FROM ' . at_pt_tabla() . " p LEFT JOIN {$wpdb->prefix}automatiza_tech_clients t ON t.id = p.tech_id
		 WHERE COALESCE(t.crm_cliente_id, p.crm_cliente_id) = %d ORDER BY p.id DESC",
		$crm_id
	));
}

/** El contenido del plan como arreglo; [] si está vacío o no es un objeto JSON. */
function at_pt_payload(object $fila): array {
	$d = json_decode((string) ($fila->payload ?? ''), true);
	return is_array($d) ? $d : [];
}

/** Fila del contrato (tabla de contracts/contract-service.php) sin cargar ContractService; null si no existe. */
function at_pt_db_contrato(int $id): ?object {
	if ($id <= 0) {
		return null;
	}
	global $wpdb;
	$c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_contracts WHERE id = %d", $id));
	return $c ?: null;
}

/**
 * Crea el plan de un contrato de servicios firmado, en «generando». Idempotente: si el contrato ya
 * tiene plan, devuelve su id. Devuelve el id o WP_Error con un motivo legible.
 */
function at_pt_crear_plan(int $contrato_id): int|WP_Error {
	at_pt_migrar_esquema();
	$existente = at_pt_plan_de_contrato($contrato_id);
	if ($existente) {
		return (int) $existente->id;
	}
	$c = at_pt_db_contrato($contrato_id);
	if (!$c) {
		return new WP_Error('at_pt_sin_contrato', 'El contrato no existe.');
	}
	if ((string) $c->type !== 'servicios') {
		return new WP_Error('at_pt_no_servicios', 'El plan de trabajo es solo para contratos de servicios.');
	}
	if ((string) $c->status !== 'signed') {
		return new WP_Error('at_pt_sin_firma', 'El contrato todavía no está firmado por el cliente.');
	}
	global $wpdb;
	$tech_id = (int) $c->client_id;
	$crm_id = 0;
	if ($tech_id > 0) {
		$crm_id = (int) $wpdb->get_var($wpdb->prepare("SELECT crm_cliente_id FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", $tech_id));
	}
	$propuesta_id = (int) ($c->proposal_id ?? 0);
	for ($intento = 0; $intento < 5; $intento++) {
		$codigo = wp_generate_password(12, false);
		// El renderer publica en /p/<código>/, la misma carpeta que usan las propuestas: nunca repetir uno.
		$usado = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}automatiza_propuestas WHERE unique_link_id = %s", $codigo));
		if ($usado > 0 || !preg_match('/^[A-Za-z0-9]{12}$/', $codigo)) {
			continue;
		}
		$ok = $wpdb->insert(at_pt_tabla(), [
			'contrato_id'    => $contrato_id,
			'crm_cliente_id' => $crm_id > 0 ? $crm_id : null,
			'tech_id'        => $tech_id > 0 ? $tech_id : null,
			'propuesta_id'   => $propuesta_id > 0 ? $propuesta_id : null,
			'codigo'         => $codigo,
			'estado'         => 'generando',
		]);
		if ($ok) {
			return (int) $wpdb->insert_id;
		}
		// Otro proceso pudo crear el plan de este contrato al mismo tiempo (índice único uniq_contrato).
		$existente = at_pt_plan_de_contrato($contrato_id);
		if ($existente) {
			return (int) $existente->id;
		}
	}
	return new WP_Error('at_pt_insert', 'No se pudo crear el plan: ' . $wpdb->last_error);
}

/** Todos los estados que conoce el plan (los de at_pt_transiciones(), de origen y de destino). */
function at_pt_db_estados(): array {
	$e = [];
	foreach (at_pt_transiciones() as $de => $destinos) {
		$e[(string) $de] = true;
		foreach ((array) $destinos as $a) {
			$e[(string) $a] = true;
		}
	}
	return array_keys($e);
}

/** 'AAAA-MM-DD' que existe en el calendario. */
function at_pt_db_fecha_valida(string $f): bool {
	return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** Enlace http(s) de hasta 500 caracteres; '' si no sirve (nunca se recorta un enlace). */
function at_pt_db_url($u): string {
	$u = trim((string) $u);
	if ($u === '' || strlen($u) > 500 || !preg_match('#^https?://#i', $u)) {
		return '';
	}
	return (string) esc_url_raw($u, ['http', 'https']);
}

/**
 * Guarda columnas del plan. Permitidas: estado, fecha_inicio, payload (arreglo → JSON), comentarios,
 * nota, view_url, pdf_url, enviado_at; las demás (id, contrato_id, codigo, …) se ignoran. Devuelve false
 * sin tocar nada si el plan no existe, si no viene ninguna permitida o si un valor no sirve (estado
 * desconocido, fecha inexistente, payload que no es arreglo): nunca se guarda un plan roto.
 */
function at_pt_guardar(int $id, array $campos): bool {
	$datos = [];
	foreach ($campos as $k => $v) {
		switch ($k) {
			case 'estado':
				if (!in_array((string) $v, at_pt_db_estados(), true)) {
					return false;
				}
				$datos['estado'] = (string) $v;
				break;
			case 'fecha_inicio':
				if ($v === null || $v === '') {
					$datos['fecha_inicio'] = null;
				} elseif (at_pt_db_fecha_valida((string) $v)) {
					$datos['fecha_inicio'] = (string) $v;
				} else {
					return false;
				}
				break;
			case 'payload':
				if ($v === null || $v === []) {
					$datos['payload'] = null;
					break;
				}
				if (!is_array($v)) {
					return false;
				}
				$json = wp_json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				if (!is_string($json)) {
					return false;
				}
				$datos['payload'] = $json;
				break;
			case 'comentarios':
				$datos['comentarios'] = mb_substr((string) $v, 0, 4000);
				break;
			case 'nota':
				$datos['nota'] = mb_substr((string) $v, 0, 1000);
				break;
			case 'view_url':
			case 'pdf_url':
				$datos[$k] = at_pt_db_url($v);
				break;
			case 'enviado_at':
				if ($v === null || $v === '') {
					$datos['enviado_at'] = null;
				} elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $v)) {
					$datos['enviado_at'] = (string) $v;
				} else {
					return false;
				}
				break;
		}
	}
	if (!$datos || !at_pt_plan($id)) {
		return false;
	}
	global $wpdb;
	return $wpdb->update(at_pt_tabla(), $datos, ['id' => $id]) !== false;
}

/**
 * Cambia el estado si la transición es válida (at_pt_transicion_valida). La nota describe el estado
 * nuevo: cambiar sin nota la deja vacía. Compara y cambia: si otro proceso movió el estado entre la
 * lectura y la escritura, no se pisa y devuelve false.
 */
function at_pt_cambiar_estado(int $id, string $a, string $nota = ''): bool {
	$f = at_pt_plan($id);
	if (!$f || !at_pt_transicion_valida((string) $f->estado, $a)) {
		return false;
	}
	global $wpdb;
	$r = $wpdb->update(at_pt_tabla(), ['estado' => $a, 'nota' => mb_substr($nota, 0, 1000)], ['id' => $id, 'estado' => (string) $f->estado]);
	if ($r === false) {
		return false;
	}
	$ahora = at_pt_plan($id);
	return $ahora !== null && $ahora->estado === $a;
}

/** Feriados de «Ajustes del chat» (automatiza_chat_schedule['holidays'], una fecha por línea). */
function at_pt_feriados(): array {
	$o = get_option('automatiza_chat_schedule');
	return at_pt_feriados_de_texto(is_array($o) ? (string) ($o['holidays'] ?? '') : '');
}

/** Tabla de tiempos de referencia (opción at_pt_duraciones, JSON o arreglo), normalizada; la de defecto si está vacía o ilegible. */
function at_pt_duraciones(): array {
	$raw = get_option('at_pt_duraciones', '');
	$t = is_array($raw) ? $raw : json_decode((string) $raw, true);
	$t = is_array($t) ? at_pt_normalizar_duraciones($t) : [];
	return $t ?: at_pt_duraciones_defecto();
}

/** Contratos de servicios firmados de todas las fichas operativas del cliente que aún no tienen plan, del más nuevo al más viejo. */
function at_pt_contratos_sin_plan(int $crm_id): array {
	if ($crm_id <= 0) {
		return [];
	}
	at_pt_migrar_esquema();
	global $wpdb;
	$k = $wpdb->prefix . 'automatiza_contracts';
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($k))) !== $k) {
		return [];
	}
	return (array) $wpdb->get_results($wpdb->prepare(
		"SELECT c.* FROM {$k} c
		 JOIN {$wpdb->prefix}automatiza_tech_clients t ON t.id = c.client_id
		 LEFT JOIN " . at_pt_tabla() . " p ON p.contrato_id = c.id
		 WHERE t.crm_cliente_id = %d AND c.type = 'servicios' AND c.status = 'signed' AND p.id IS NULL
		 ORDER BY c.id DESC",
		$crm_id
	));
}

/**
 * Meses de garantía del contrato (marcador garantia_meses_servicio, texto que Luis puede editar al revisar):
 * el primer número, si está entre 0 y 24; si no está o no se entiende, 3 (el valor por defecto del cierre).
 */
function at_pt_db_garantia(array $ph): int {
	if (preg_match('/\d+/', (string) ($ph['garantia_meses_servicio'] ?? ''), $m)) {
		$n = (int) $m[0];
		if ($n >= 0 && $n <= 24) {
			return $n;
		}
	}
	return 3;
}

/**
 * Todo lo que rodea a un plan: contrato y sus marcadores, ficha operativa, propuesta (o null), cliente
 * del CRM, garantía y los nombres ya resueltos. Empresa (lo que se muestra como company_name): el nombre
 * comercial de la propuesta si existe; si no, nombre_proyecto o razon_social_cliente del contrato; si no, el
 * nombre del cliente; nunca vacío. Cliente: quien firma por el cliente en el contrato, si no la propuesta o la
 * ficha.
 */
function at_pt_db_partes(object $fila): array {
	global $wpdb;
	$c = at_pt_db_contrato((int) $fila->contrato_id);
	$ph = $c ? json_decode((string) $c->placeholders, true) : null;
	$ph = is_array($ph) ? $ph : [];
	$tech = null;
	if ((int) $fila->tech_id > 0) {
		$tech = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_tech_clients WHERE id = %d", (int) $fila->tech_id)) ?: null;
	}
	$prop = null;
	if ((int) $fila->propuesta_id > 0) {
		$prop = $wpdb->get_row($wpdb->prepare(
			"SELECT id, unique_link_id, client_name, company_name, gamma_prompt_text, transcript_text FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d",
			(int) $fila->propuesta_id
		)) ?: null;
	}
	$primero = function (...$valores): string {
		foreach ($valores as $v) {
			$v = trim((string) $v);
			if ($v !== '') {
				return $v;
			}
		}
		return '';
	};
	$cliente = $primero($ph['representante_cliente_nombre'] ?? '', $ph['aceptante_nombre'] ?? '', $prop->client_name ?? '', $tech->name ?? '', $ph['razon_social_cliente'] ?? '');
	$cliente = $cliente !== '' ? $cliente : 'Cliente';
	// D9: el nombre comercial de la propuesta; si no, el del contrato (proyecto o razón social); si no, el cliente.
	$empresa = $primero($prop->company_name ?? '', $ph['nombre_proyecto'] ?? '', $ph['razon_social_cliente'] ?? '', $cliente);
	$firma = $c ? substr((string) $c->signed_at, 0, 10) : '';
	$crm_id = (int) ($tech->crm_cliente_id ?? 0);
	return [
		'contrato'       => $c,
		'ph'             => $ph,
		'tech'           => $tech,
		'propuesta'      => $prop,
		'crm_id'         => $crm_id > 0 ? $crm_id : (int) $fila->crm_cliente_id,
		'empresa'        => $empresa,
		'cliente'        => $cliente,
		'proyecto'       => $primero($ph['nombre_proyecto'] ?? '', $empresa),
		'fecha_firma'    => at_pt_db_fecha_valida($firma) ? $firma : current_time('Y-m-d'),
		'garantia_meses' => at_pt_db_garantia($ph),
	];
}

/**
 * Datos del cliente para at_pt_armar_render(): código, empresa, cliente, portal ('' sin correo), WhatsApp de AT,
 * fecha de firma, meses de garantía del contrato (el contrato manda sobre lo que diga el plan: D8) y sitio (home_url,
 * para el enlace de la agenda web).
 */
function at_pt_datos_render(object $fila): array {
	$x = at_pt_db_partes($fila);
	$portal = ($x['crm_id'] > 0 && function_exists('at_crm_url_portal')) ? (string) at_crm_url_portal($x['crm_id']) : '';
	$whatsapp = (string) preg_replace('/\D+/', '', function_exists('at_cc_whatsapp_at') ? at_cc_whatsapp_at() : '');
	return [
		'codigo'         => (string) $fila->codigo,
		'company_name'   => $x['empresa'],
		'client_name'    => $x['cliente'],
		'portal_url'     => $portal,
		'whatsapp'       => $whatsapp !== '' ? $whatsapp : '56927002984',
		'fecha_firma'    => $x['fecha_firma'],
		'garantia_meses' => $x['garantia_meses'],
		'sitio'          => home_url(),
	];
}

/**
 * Lo que recibe la IA (GET /plan/{id}/contexto). Lo contratado sale del contrato (ya revisado por
 * Luis); la propuesta, si existe, aporta solución, pasos y los primeros 3000 caracteres de la reunión;
 * el rubro sale de la ficha del CRM (las fotos nuevas se describen por rubro, también sin propuesta).
 */
function at_pt_contexto(object $fila): array {
	global $wpdb;
	$x = at_pt_db_partes($fila);
	$rubro = '';
	if ($x['crm_id'] > 0) {
		$rubro = trim((string) $wpdb->get_var($wpdb->prepare("SELECT rubro FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $x['crm_id'])));
	}
	$ph = $x['ph'];
	$campo = function (string $k) use ($ph): string {
		return mb_substr(trim((string) ($ph[$k] ?? '')), 0, 4000);
	};
	$propuesta = null;
	if ($x['propuesta']) {
		$p = $x['propuesta'];
		$d = function_exists('at_cc_json_de_payload') ? at_cc_json_de_payload((string) $p->gamma_prompt_text) : json_decode((string) $p->gamma_prompt_text, true);
		$d = is_array($d) ? $d : [];
		$pasos = [];
		foreach ((array) ($d['how_it_works'] ?? []) as $s) {
			if (is_array($s)) {
				$t = trim((string) ($s['step_title'] ?? ''));
				$tx = trim((string) ($s['step_text'] ?? ''));
				$linea = ($t !== '' && $tx !== '') ? $t . ': ' . $tx : $t . $tx;
			} else {
				$linea = is_scalar($s) ? trim((string) $s) : '';
			}
			if ($linea !== '') {
				$pasos[] = mb_substr($linea, 0, 500);
			}
			if (count($pasos) >= 12) {
				break;
			}
		}
		$propuesta = [
			'company_name'     => (string) $p->company_name,
			'solution_text'    => mb_substr(trim((string) ($d['solution_text'] ?? '')), 0, 4000),
			'how_it_works'     => $pasos,
			'extracto_reunion' => mb_substr((string) $p->transcript_text, 0, 3000),
		];
	}
	$plan = at_pt_payload($fila);
	return [
		'ok'          => true,
		'plan_id'     => (int) $fila->id,
		'codigo'      => (string) $fila->codigo,
		'estado'      => (string) $fila->estado,
		'proyecto'    => $x['proyecto'],
		'empresa'     => $x['empresa'],
		'cliente'     => $x['cliente'],
		'fecha_firma' => $x['fecha_firma'],
		'contrato'    => [
			'servicios_contratados' => $campo('servicios_contratados'),
			'alcance'               => $campo('alcance'),
			'entregables'           => $campo('entregables'),
			'fases_siguientes'      => $campo('fases_siguientes'),
			'plazo'                 => $campo('plazo'),
			// D7: meses de garantía del contrato (garantia_meses_servicio; 3 si no está). La IA los copia en soporte.
			'garantia_meses'        => $x['garantia_meses'],
		],
		'propuesta'   => $propuesta,
		'tabla'       => at_pt_duraciones(),
		'slides_foto' => at_pt_slides_foto(),
		'plan_actual' => $plan ?: null,
		'comentarios' => (string) ($fila->comentarios ?? ''),
		// Para el botón de los correos a Luis (pestaña del plan en la ficha del CRM); 0 si la ficha no está enlazada.
		'crm_cliente_id' => (int) $x['crm_id'],
		// Rubro de la ficha del CRM (crm_clientes.rubro); '' si no hay ficha enlazada o no lo tiene.
		'rubro'          => mb_substr($rubro, 0, 100),
	];
}
