<?php
/** Contrato de servicios armado desde una propuesta aceptada (Luis lo ajusta y firma después). */
if (!defined('ABSPATH')) {
	exit;
}

/** Datos del contrato desde la propuesta, lo aceptado y quién aceptó. */
function at_cc_datos_contrato(object $p, array $filas_aceptadas, array $aceptante): array {
	$payload = at_cc_json_de_payload((string) $p->gamma_prompt_text) ?? [];
	// Task 15: documento de quien aceptó (RUT, DNI o pasaporte). Quien llame solo con 'rut' (antes de
	// la Task 15) sigue funcionando: cuenta como RUT.
	$doc = at_cc_documento_de_datos($aceptante);
	$todas = at_cc_filas_de_propuesta($p);
	$siguientes = array_values(array_filter($todas, function ($f) use ($filas_aceptadas) { return !in_array($f, $filas_aceptadas, true); }));
	$entregables = [];
	foreach ((array) ($payload['how_it_works'] ?? []) as $s) {
		if (is_array($s)) {
			$t = trim((string) ($s['step_title'] ?? ''));
			$x = trim((string) ($s['step_text'] ?? ''));
			if ($t !== '' || $x !== '') {
				$entregables[] = '- ' . ($t !== '' ? '**' . str_replace('*', '', $t) . '**' . ($x !== '' ? ': ' : '') : '') . $x;
			}
		} elseif (is_string($s) && trim($s) !== '') {
			$entregables[] = '- ' . trim($s);
		}
	}
	return [
		'empresa'           => (string) $p->company_name,
		'representante'     => trim((string) ($aceptante['nombre'] ?? '')) !== '' ? (string) $aceptante['nombre'] : (string) $p->client_name,
		'tipo_documento_representante' => $doc['tipo'] !== '' ? $doc['tipo'] : 'rut',
		'rut_representante' => $doc['numero'],
		'email'             => (string) $p->client_email,
		'telefono'          => (string) $p->phone,
		'codigo_propuesta'  => (string) $p->unique_link_id,
		'fecha_propuesta'   => at_cc_fecha_larga((string) $p->created_at),
		'fecha_aceptacion'  => at_cc_fecha_larga((string) ($aceptante['fecha'] ?? current_time('mysql'))),
		'canal_aceptacion'  => (string) ($aceptante['canal_texto'] ?? 'en la página de la propuesta'),
		'filas_aceptadas'   => $filas_aceptadas,
		'filas_siguientes'  => $siguientes,
		'alcance'           => trim((string) ($payload['solution_text'] ?? '')),
		'entregables'       => implode("\n", $entregables),
		'plazo'             => 'Se define con EL CLIENTE en la reunión de inicio y queda por escrito.',
	];
}

/** Crea el borrador del contrato de servicios; devuelve su id o WP_Error. */
function at_cc_crear_contrato_servicios(object $p, int $tech_id, array $filas, array $aceptante) {
	// Ronda 1, hallazgo 2 (T9): red de seguridad. Si por otra vía (p. ej. un guardado que reabrió el
	// envío) at_cc_ejecutar_cierre() se ejecutara dos veces para la misma propuesta, no se crea un
	// segundo contrato: se devuelve el que ya existe.
	$existente = at_cc_contrato_de_propuesta((int) $p->id);
	if ($existente) {
		return (int) $existente->id;
	}
	if (!class_exists('ContractService')) {
		$f = ABSPATH . 'contracts/contract-service.php';
		if (!file_exists($f)) {
			return new WP_Error('sin_contratos', 'El módulo de contratos no está instalado.');
		}
		require_once $f;
	}
	$ph = at_cc_marcadores_servicios(at_cc_datos_contrato($p, $filas, $aceptante));
	try {
		$c = ContractService::create_contract([
			'client_id'       => $tech_id,
			'proposal_id'     => (int) $p->id,
			'type'            => 'servicios',
			'template_id'     => 'servicios_v1',
			'placeholders'    => $ph,
			'expires_in_days' => 30,
		]);
	} catch (\Throwable $e) {
		// P. ej. FPDF no pudo escribir el PDF (cuota de disco, permisos): la fila del contrato
		// puede quedar creada en 'at_pending' sin pdf_url ni document_hash, pero el cierre
		// (cliente, bienvenida, aviso a Luis) no debe morir con un error fatal por esto.
		return new WP_Error('contrato', $e->getMessage());
	}
	return is_wp_error($c) ? $c : (int) $c->id;
}

/** Último contrato creado para una propuesta; null si no hay (o no existe la tabla). */
function at_cc_contrato_de_propuesta(int $propuesta_id): ?object {
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_contracts';
	if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t))) {
		return null;
	}
	$c = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE proposal_id = %d ORDER BY id DESC LIMIT 1", $propuesta_id));
	return $c ?: null;
}
