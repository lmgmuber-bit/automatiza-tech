<?php
/**
 * Flujo v3 de propuestas: estados, transiciones y reglas que no deben depender de n8n.
 *
 * Todo menos automatiza_proposals_migrate_v3() es puro (sin WordPress) para poder
 * probarlo con `php tests/propuestas/flow-test.php`.
 *
 * @package AutomatizaTech
 */

/** Transiciones permitidas del flujo v3 (ver Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md). */
function at_propuesta_transicion_valida(string $desde, string $hacia): bool {
	$permitidas = [
		'borrador'  => ['ajustando', 'generando'],
		'ajustando' => ['borrador', 'error'],
		'generando' => ['lista', 'error'],
		'lista'     => ['ajustando', 'sent'],
		'error'     => ['borrador', 'ajustando', 'generando'],
	];
	return in_array($hacia, $permitidas[$desde] ?? [], true);
}

/** Las propuestas v3 solo salen al cliente desde 'lista'; las viejas, como siempre. */
function at_propuesta_puede_enviarse(?string $flujo, string $status): bool {
	if ($flujo !== 'v3') {
		return true;
	}
	return in_array($status, ['lista', 'sent'], true);
}

/** El envío al cliente ('sent') solo lo hace el panel; la API REST no puede pedirlo. */
function at_propuesta_estado_permitido_por_api(string $hacia): bool {
	return $hacia !== 'sent';
}

/** Precios que Luis escribe en el panel. Sin filas válidas, se conservan las anteriores. */
function at_propuesta_aplicar_precios(array $payload, array $filas, string $nota): array {
	$limpias = [];
	foreach ($filas as $f) {
		$servicio = trim((string) ($f['service'] ?? ''));
		$precio = trim((string) ($f['price_label'] ?? ''));
		if ($servicio === '' || $precio === '') {
			continue;
		}
		$fila = ['service' => $servicio, 'price_usd' => 0, 'price_label' => $precio];
		if (!empty($f['emphasis'])) {
			$fila['emphasis'] = true;
		}
		$limpias[] = $fila;
	}
	if ($limpias) {
		$payload['pricing_rows'] = $limpias;
	}
	$payload['pricing_note'] = trim($nota);
	return $payload;
}

/** Una fila enviada con servicio pero sin precio se perdería en silencio al aplicar precios. */
function at_propuesta_filas_con_precio_vacio(array $filas): bool {
	foreach ($filas as $f) {
		$servicio = trim((string) ($f['service'] ?? ''));
		$precio = trim((string) ($f['price_label'] ?? ''));
		if ($servicio !== '' && $precio === '') {
			return true;
		}
	}
	return false;
}

/** No se puede aprobar sin precios reales: sin filas, o con alguna en blanco o "Por confirmar". */
function at_propuesta_precios_pendientes(array $payload): bool {
	$filas = $payload['pricing_rows'] ?? [];
	if (!is_array($filas) || !$filas) {
		return true;
	}
	foreach ($filas as $f) {
		$precio = strtolower(trim((string) ($f['price_label'] ?? '')));
		if ($precio === '' || $precio === 'por confirmar') {
			return true;
		}
	}
	return false;
}

/** Un payload que llega de n8n nunca cambia precios ni el unique_id de lo guardado. */
function at_propuesta_conservar_precios(array $guardado, array $entrante): array {
	$entrante['pricing_rows'] = $guardado['pricing_rows'] ?? [];
	if (array_key_exists('pricing_note', $guardado)) {
		$entrante['pricing_note'] = $guardado['pricing_note'];
	} else {
		unset($entrante['pricing_note']);
	}
	$entrante['unique_id'] = $guardado['unique_id'] ?? ($entrante['unique_id'] ?? '');
	return $entrante;
}

/** Agrega un comentario al historial (JSON). Un historial ilegible se reinicia. */
function at_propuesta_agregar_comentario(?string $log_json, string $comentario, string $fecha): string {
	$log = json_decode((string) $log_json, true);
	if (!is_array($log)) {
		$log = [];
	}
	$log[] = ['fecha' => $fecha, 'comentario' => $comentario];
	return json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Fotos que pedirá el flujo Final y su costo de lista (Soul 2: US$0,0032 c/u, 2026-09-20). */
function at_propuesta_costo_fotos(array $payload): array {
	$n = 0;
	foreach ($payload['image_briefs'] ?? [] as $b) {
		if (!empty($b['slide']) && !empty($b['prompt'])) {
			$n++;
		}
	}
	return ['fotos' => $n, 'usd_lista' => round($n * 0.0032, 4)];
}

/** Mismos campos obligatorios que renderer/src/schema.js. */
function at_propuesta_errores_payload(array $p): array {
	$errores = [];
	foreach (['unique_id', 'client_name', 'company_name', 'challenge_title', 'challenge_text', 'solution_title', 'solution_text'] as $k) {
		if (!isset($p[$k]) || !is_string($p[$k]) || trim($p[$k]) === '') {
			$errores[] = "falta o está vacío: $k";
		}
	}
	foreach (['benefits', 'how_it_works', 'pricing_rows', 'next_steps'] as $k) {
		if (empty($p[$k]) || !is_array($p[$k])) {
			$errores[] = "falta o está vacía la lista: $k";
		}
	}
	return $errores;
}

/** Columnas del flujo v3. Idempotente: se puede correr en cada carga. */
function automatiza_proposals_migrate_v3() {
	if (get_option('at_propuestas_schema') === '3') {
		return;
	}
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_propuestas';
	$cols = $wpdb->get_col("SHOW COLUMNS FROM {$t}");
	if (!$cols) {
		return;
	}
	if (!in_array('flujo', $cols, true)) {
		$wpdb->query("ALTER TABLE {$t} ADD COLUMN flujo varchar(10) DEFAULT NULL");
	}
	if (!in_array('feedback_log', $cols, true)) {
		$wpdb->query("ALTER TABLE {$t} ADD COLUMN feedback_log longtext DEFAULT NULL");
	}
	if (!in_array('status_note', $cols, true)) {
		$wpdb->query("ALTER TABLE {$t} ADD COLUMN status_note text DEFAULT NULL");
	}
	update_option('at_propuestas_schema', '3');
}

if (function_exists('add_action')) {
	add_action('init', 'automatiza_proposals_migrate_v3');
}
