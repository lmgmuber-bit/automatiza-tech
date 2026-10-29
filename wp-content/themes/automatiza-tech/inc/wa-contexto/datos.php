<?php
/**
 * Fichas de un número de WhatsApp desde la base: el CRM, las fichas operativas, las propuestas y las citas.
 * Todo se lleva a la ficha única del CRM; lo que no tiene ficha en el CRM se agrupa por correo.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Expresión SQL con los últimos 9 dígitos de una columna de teléfono, sin espacios ni signos. */
function at_wa_sql_ultimos9(string $columna): string {
	return "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$columna}, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', ''), 9)";
}

function at_wa_correo(string $c): string {
	return strtolower(trim($c));
}

/** Marcadores «%s, %s, …» para un IN con n valores. */
function at_wa_marcas(int $n, string $marca = '%s'): string {
	return implode(', ', array_fill(0, max(1, $n), $marca));
}

/** Ficha de un cliente del CRM: su nombre, su agenda, sus contratos, sus propuestas y sus citas. */
function at_wa_ficha_de_crm(int $crm_id): array {
	global $wpdb;
	$crm = $wpdb->get_row($wpdb->prepare("SELECT id, nombre, email, empresa FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id));
	if (!$crm) {
		return [];
	}
	$techs = function_exists('at_cc_techs_de_crm') ? at_cc_techs_de_crm($crm_id) : [];
	$correos = [at_wa_correo((string) $crm->email)];
	foreach ($techs as $t) {
		$correos[] = at_wa_correo((string) $t->email);
	}
	$primera = $techs[0] ?? null;
	$agenda = [
		'nombre_completo' => trim((string) $crm->nombre) !== '' ? trim((string) $crm->nombre) : trim((string) ($primera->name ?? '')),
		'correo'          => trim((string) $crm->email) !== '' ? trim((string) $crm->email) : trim((string) ($primera->email ?? '')),
		'empresa'         => trim((string) $crm->empresa) !== '' ? trim((string) $crm->empresa) : trim((string) ($primera->company ?? '')),
	];
	return at_wa_armar_ficha('crm:' . $crm_id, $agenda['nombre_completo'], $agenda, array_map(function ($t) { return (int) $t->id; }, $techs), $correos);
}

/** Ficha de quien no tiene ficha en el CRM, por su correo: fichas operativas sueltas, propuestas y citas con ese correo. */
function at_wa_ficha_de_correo(string $correo): array {
	global $wpdb;
	$correo = at_wa_correo($correo);
	if ($correo === '') {
		return [];
	}
	$p = $wpdb->prefix;
	$techs = (array) $wpdb->get_results($wpdb->prepare("SELECT id, name, email, company FROM {$p}automatiza_tech_clients WHERE LOWER(TRIM(email)) = %s AND crm_cliente_id IS NULL ORDER BY id DESC LIMIT 50", $correo));
	$prop = $wpdb->get_row($wpdb->prepare("SELECT client_name, client_email, company_name FROM {$p}automatiza_propuestas WHERE LOWER(TRIM(client_email)) = %s ORDER BY id DESC LIMIT 1", $correo));
	$cita = $prop ? null : $wpdb->get_row($wpdb->prepare("SELECT name, email FROM {$p}automatiza_leads WHERE LOWER(TRIM(email)) = %s ORDER BY id DESC LIMIT 1", $correo));
	$t0 = $techs[0] ?? null;
	$nombre = trim((string) ($prop->client_name ?? ($t0->name ?? ($cita->name ?? ''))));
	$agenda = [
		'nombre_completo' => $nombre,
		'correo'          => trim((string) ($prop->client_email ?? ($t0->email ?? ($cita->email ?? $correo)))),
		'empresa'         => trim((string) ($prop->company_name ?? ($t0->company ?? ''))),
	];
	return at_wa_armar_ficha('correo:' . $correo, $nombre, $agenda, array_map(function ($t) { return (int) $t->id; }, $techs), [$correo]);
}

/** Contratos (de las fichas operativas), propuestas y citas (por los correos) en la forma que usa at_wa_clasificar(). */
function at_wa_armar_ficha(string $clave, string $nombre, array $agenda, array $tech_ids, array $correos): array {
	global $wpdb;
	$p = $wpdb->prefix;
	$correos = array_values(array_unique(array_filter($correos)));
	$contratos = [];
	if ($tech_ids) {
		$filas = (array) $wpdb->get_results($wpdb->prepare(
			"SELECT id, type, status, signed_at, placeholders, contract_number FROM {$p}automatiza_contracts WHERE client_id IN (" . at_wa_marcas(count($tech_ids), '%d') . ") ORDER BY id DESC LIMIT 1000",
			...$tech_ids
		));
		foreach ($filas as $k) {
			$plan = function_exists('at_pt_plan_de_contrato') ? at_pt_plan_de_contrato((int) $k->id) : null;
			$payload = $plan ? (json_decode((string) $plan->payload, true) ?: []) : [];
			$contratos[] = [
				'id'          => (int) $k->id,
				'tipo'        => (string) $k->type,
				'estado'      => (string) $k->status,
				'firmado'     => !empty($k->signed_at),
				'nombre'      => at_wa_nombre_contrato((string) $k->type, json_decode((string) $k->placeholders, true) ?: [], (string) ($payload['proyecto'] ?? ''), (string) $k->contract_number),
				'plan_codigo' => $plan ? (string) $plan->codigo : '',
			];
		}
	}
	$propuestas = [];
	$diagnosticos = [];
	if ($correos) {
		$filas = (array) $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$p}automatiza_propuestas WHERE LOWER(TRIM(client_email)) IN (" . at_wa_marcas(count($correos)) . ") ORDER BY id DESC LIMIT 1000",
			...$correos
		));
		foreach ($filas as $pr) {
			$servicio = '';
			if (function_exists('at_cc_filas_de_propuesta')) {
				$servicio = (string) ((at_cc_filas_de_propuesta($pr)[0]['service'] ?? ''));
			}
			$propuestas[] = ['id' => (int) $pr->id, 'estado' => (string) $pr->status, 'nombre' => at_wa_nombre_propuesta($servicio, (string) $pr->company_name, (string) $pr->created_at)];
		}
		$asistencia = at_wa_leads_tiene_asistencia() ? 'attendance_status' : "''";
		$filas = (array) $wpdb->get_results($wpdb->prepare(
			"SELECT scheduled_date, scheduled_time, status, {$asistencia} AS asistencia FROM {$p}automatiza_leads WHERE LOWER(TRIM(email)) IN (" . at_wa_marcas(count($correos)) . ") ORDER BY id DESC LIMIT 1000",
			...$correos
		));
		foreach ($filas as $l) {
			if (empty($l->scheduled_date)) {
				continue;
			}
			$diagnosticos[] = ['cuando' => (string) $l->scheduled_date . ' ' . substr((string) $l->scheduled_time, 0, 5), 'estado' => (string) $l->status, 'asistencia' => (string) $l->asistencia];
		}
	}
	return ['clave' => $clave, 'nombre' => $nombre, 'agenda' => $agenda, 'contratos' => $contratos, 'propuestas' => $propuestas, 'diagnosticos' => $diagnosticos];
}

function at_wa_leads_tiene_asistencia(): bool {
	static $tiene = null;
	if ($tiene === null) {
		global $wpdb;
		$tiene = (bool) $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}automatiza_leads LIKE 'attendance_status'");
	}
	return $tiene;
}

/**
 * Las fichas de un número: se busca por los últimos 9 dígitos en el CRM, las fichas operativas, las propuestas y las
 * citas, y cada coincidencia se lleva a su ficha del CRM (directo, por la ficha operativa o por el correo).
 */
function at_wa_fichas_por_telefono(string $tel): array {
	$u9 = at_wa_ultimos9($tel);
	if ($u9 === '') {
		return [];
	}
	global $wpdb;
	$p = $wpdb->prefix;
	$crm_ids = [];
	$correos = [];
	$sumar = function (int $crm, string $correo) use (&$crm_ids, &$correos) {
		if ($crm <= 0 && function_exists('at_cc_crm_de_email')) {
			$crm = at_cc_crm_de_email($correo);
		}
		if ($crm > 0) {
			$crm_ids[$crm] = true;
		} elseif (at_wa_correo($correo) !== '') {
			$correos[at_wa_correo($correo)] = true;
		}
	};
	foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT id FROM {$p}crm_clientes WHERE " . at_wa_sql_ultimos9('telefono') . " = %s LIMIT 1000", $u9)) as $id) {
		$sumar((int) $id, '');
	}
	foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT crm_cliente_id, email FROM {$p}automatiza_tech_clients WHERE " . at_wa_sql_ultimos9('phone') . " = %s LIMIT 1000", $u9)) as $t) {
		$sumar((int) $t->crm_cliente_id, (string) $t->email);
	}
	foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT client_email FROM {$p}automatiza_propuestas WHERE " . at_wa_sql_ultimos9('phone') . " = %s LIMIT 1000", $u9)) as $c) {
		$sumar(0, (string) $c);
	}
	foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT email FROM {$p}automatiza_leads WHERE " . at_wa_sql_ultimos9('phone') . " = %s LIMIT 1000", $u9)) as $c) {
		$sumar(0, (string) $c);
	}
	$fichas = [];
	foreach (array_keys($crm_ids) as $id) {
		$f = at_wa_ficha_de_crm((int) $id);
		if ($f) {
			$fichas[] = $f;
		}
	}
	foreach (array_keys($correos) as $c) {
		$f = at_wa_ficha_de_correo((string) $c);
		if ($f) {
			$fichas[] = $f;
		}
	}
	return $fichas;
}

/** Lo que responde la ruta wa-contexto: la situación, las opciones, la elegida por el código del plan y la agenda. */
function at_wa_contexto(string $tel, string $texto = ''): array {
	$fichas = at_wa_fichas_por_telefono($tel);
	$r = at_wa_clasificar($fichas, current_time('Y-m-d H:i'));
	$r['elegida'] = at_wa_elegida($r['opciones'], at_wa_codigo_de_texto($texto));
	$r['agenda'] = count($fichas) === 1 ? $fichas[0]['agenda'] : ['nombre_completo' => '', 'correo' => '', 'empresa' => ''];
	return $r;
}

/**
 * El vínculo de una reunión solo vale si el contrato o la propuesta son de una ficha de ese número o de ese correo.
 * Devuelve ['contrato_id' => int, 'propuesta_id' => int]; lo que no calza vuelve como 0.
 */
function at_wa_vinculo_valido(int $contrato_id, int $propuesta_id, string $tel, string $correo): array {
	$res = ['contrato_id' => 0, 'propuesta_id' => 0];
	if ($contrato_id <= 0 && $propuesta_id <= 0) {
		return $res;
	}
	$fichas = at_wa_fichas_por_telefono($tel);
	$crm = function_exists('at_cc_crm_de_email') ? at_cc_crm_de_email($correo) : 0;
	$extra = $crm > 0 ? at_wa_ficha_de_crm($crm) : at_wa_ficha_de_correo($correo);
	if ($extra) {
		$fichas[] = $extra;
	}
	foreach ($fichas as $f) {
		foreach ($f['contratos'] as $k) {
			if ($contrato_id > 0 && (int) $k['id'] === $contrato_id) {
				$res['contrato_id'] = $contrato_id;
			}
		}
		foreach ($f['propuestas'] as $pr) {
			if ($propuesta_id > 0 && (int) $pr['id'] === $propuesta_id) {
				$res['propuesta_id'] = $propuesta_id;
			}
		}
	}
	return $res;
}
