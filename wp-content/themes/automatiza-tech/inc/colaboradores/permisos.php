<?php
/**
 * Rol «Colaborador AT» y la única puerta de permisos del módulo: at_colab_puede().
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_COLAB_ROL = 'at_colaborador';
const AT_COLAB_META = 'at_clientes_asignados';

/** Crea el rol si falta y le da al administrador la capacidad para ver «Mis clientes». Sin migración. */
function at_colab_registrar_rol(): void {
	if (!get_role(AT_COLAB_ROL)) {
		add_role(AT_COLAB_ROL, 'Colaborador AT', ['read' => true, 'at_colaborador' => true]);
	}
	$admin = get_role('administrator');
	if ($admin && !$admin->has_cap('at_colaborador')) {
		$admin->add_cap('at_colaborador');
	}
}
add_action('init', 'at_colab_registrar_rol');

function at_colab_ids_asignados(int $user_id): array {
	return at_colab_normalizar_ids(get_user_meta($user_id, AT_COLAB_META, true));
}

/** true si el usuario (0 = el actual) tiene hoy el rol de colaborador. */
function at_colab_es_colaborador(int $user_id = 0): bool {
	$u = $user_id > 0 ? get_userdata($user_id) : wp_get_current_user();
	return $u && $u->exists() && in_array(AT_COLAB_ROL, (array) $u->roles, true);
}

function at_colab_cliente_existe(int $crm_id): bool {
	global $wpdb;
	return $crm_id > 0 && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id)) === 1;
}

/**
 * ¿Puede el usuario (0 = el actual) ver y anotar en este cliente? Administrador: si el cliente existe. Colaborador
 * (con el rol vigente): solo si el cliente existe y está en su lista. Cualquier otro: no.
 */
function at_colab_puede(int $crm_id, int $user_id = 0): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	if ($user_id <= 0 || !at_colab_cliente_existe($crm_id)) {
		return false;
	}
	if (user_can($user_id, 'manage_options')) {
		return true;
	}
	return at_colab_es_colaborador($user_id) && in_array($crm_id, at_colab_ids_asignados($user_id), true);
}
