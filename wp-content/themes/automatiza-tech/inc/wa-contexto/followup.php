<?php
/**
 * La reunión de seguimiento queda ligada a su contrato o su propuesta: dos columnas en wp_automatiza_followup_meetings
 * y la validación de que el vínculo sea del mismo cliente. Nunca hace fallar la agenda: si algo no calza, sin vínculo.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Agrega contrato_id y propuesta_id (con su índice) si faltan. Idempotente; queda anotado en la opción at_wa_followup_schema. */
function at_wa_followup_migrar_columnas(): void {
	if (get_option('at_wa_followup_schema') === '1') {
		return;
	}
	global $wpdb;
	$t = $wpdb->prefix . 'automatiza_followup_meetings';
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t))) !== $t) {
		return;
	}
	$cols = (array) $wpdb->get_col("SHOW COLUMNS FROM $t", 0);
	$idx = (array) $wpdb->get_col("SHOW INDEX FROM $t", 2);
	foreach (['contrato_id' => 'idx_contrato_id', 'propuesta_id' => 'idx_propuesta_id'] as $col => $indice) {
		if (!in_array($col, $cols, true)) {
			$wpdb->query("ALTER TABLE $t ADD COLUMN $col BIGINT UNSIGNED NULL DEFAULT NULL, ADD KEY $indice ($col)");
		} elseif (!in_array($indice, $idx, true)) {
			$wpdb->query("ALTER TABLE $t ADD KEY $indice ($col)");
		}
	}
	if (at_wa_followup_tiene_columnas(true)) {
		update_option('at_wa_followup_schema', '1', false);
	}
}
add_action('init', 'at_wa_followup_migrar_columnas', 20);

/** ¿Ya existen las dos columnas? Sin ellas el vínculo no se guarda (un insert con columnas que no existen fallaría). */
function at_wa_followup_tiene_columnas(bool $revisar = false): bool {
	static $tiene = null;
	if ($tiene === null || $revisar) {
		global $wpdb;
		$cols = (array) $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}automatiza_followup_meetings", 0);
		$tiene = in_array('contrato_id', $cols, true) && in_array('propuesta_id', $cols, true);
	}
	return $tiene;
}

/**
 * Suma a los datos de la reunión el contrato_id y la propuesta_id que mandó n8n, solo si son de ese mismo cliente
 * (at_wa_vinculo_valido() por el número y el correo de la reunión). Sin vínculo pedido, los datos quedan iguales.
 */
function at_wa_followup_datos_con_vinculo(array $data, WP_REST_Request $r): array {
	$contrato = is_numeric($r->get_param('contrato_id')) ? (int) $r->get_param('contrato_id') : 0;
	$propuesta = is_numeric($r->get_param('propuesta_id')) ? (int) $r->get_param('propuesta_id') : 0;
	if (($contrato <= 0 && $propuesta <= 0) || !at_wa_followup_tiene_columnas()) {
		return $data;
	}
	$v = at_wa_vinculo_valido(max(0, $contrato), max(0, $propuesta), (string) ($data['phone'] ?? ''), (string) ($data['client_email'] ?? ''));
	$data['contrato_id'] = $v['contrato_id'] > 0 ? $v['contrato_id'] : null;
	$data['propuesta_id'] = $v['propuesta_id'] > 0 ? $v['propuesta_id'] : null;
	return $data;
}
