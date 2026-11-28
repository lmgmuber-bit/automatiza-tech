<?php
/**
 * Validación de una nota de la ficha del CRM antes de guardarla (crm-ai-completo.php::ajax_guardar_nota):
 * permiso de editor, cliente que exista y fecha con formato de fecha (si no, la de ahora).
 * Prueba: tests/security/crm-guardar-nota.test.php
 */
if (!defined('ABSPATH')) {
	exit;
}

/** ['ok' => bool, 'error' => ''|'sin_permiso'|'cliente', 'fecha' => 'Y-m-d H:i:s']. */
function at_crm_validar_nota(int $cliente_id, string $fecha): array {
	if (!current_user_can('edit_posts')) {
		return ['ok' => false, 'error' => 'sin_permiso', 'fecha' => ''];
	}
	global $wpdb;
	$existe = $cliente_id > 0 && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $cliente_id)) === 1;
	if (!$existe) {
		return ['ok' => false, 'error' => 'cliente', 'fecha' => ''];
	}
	return ['ok' => true, 'error' => '', 'fecha' => at_crm_fecha_nota($fecha)];
}

/** Fecha del formulario («2026-10-20T10:30», o con segundos y espacio) a 'Y-m-d H:i:s'; inválida o vacía: ahora. */
function at_crm_fecha_nota(string $fecha): string {
	$fecha = trim($fecha);
	if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/', $fecha, $m)
		&& (int) $m[1] >= 2000 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
		&& (int) $m[4] < 24 && (int) $m[5] < 60 && (int) ($m[6] ?? 0) < 60) {
		return sprintf('%s-%s-%s %s:%s:%02d', $m[1], $m[2], $m[3], $m[4], $m[5], (int) ($m[6] ?? 0));
	}
	return current_time('mysql');
}
