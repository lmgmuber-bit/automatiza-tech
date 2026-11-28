<?php
/**
 * Campo «Clientes asignados» en el perfil de un usuario. Solo lo ve y lo guarda quien tiene manage_options.
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_colab_clientes_crm(): array {
	global $wpdb;
	return (array) $wpdb->get_results("SELECT id, nombre, empresa FROM {$wpdb->prefix}crm_clientes ORDER BY nombre ASC");
}

function at_colab_campo_perfil($user): void {
	if (!current_user_can('manage_options') || !($user instanceof WP_User)) {
		return;
	}
	$asignados = at_colab_ids_asignados((int) $user->ID);
	echo '<h2>Clientes asignados</h2>';
	echo '<p class="description">Solo para el rol «Colaborador AT»: ve el historial y los entregables de estos clientes en «Mis clientes». No ve montos, contratos ni propuestas.</p>';
	wp_nonce_field('at_colab_perfil_' . (int) $user->ID, '_at_colab_perfil');
	echo '<table class="form-table" role="presentation"><tr><th scope="row">Clientes</th><td><fieldset>';
	$clientes = at_colab_clientes_crm();
	if (!$clientes) {
		echo '<p>No hay clientes en el CRM.</p>';
	}
	foreach ($clientes as $c) {
		$id = (int) $c->id;
		$etiqueta = trim((string) $c->nombre) . (trim((string) $c->empresa) !== '' ? ' · ' . trim((string) $c->empresa) : '');
		echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="at_clientes_asignados[]" value="' . $id . '"' . checked(in_array($id, $asignados, true), true, false) . '> ' . esc_html($etiqueta) . '</label>';
	}
	echo '</fieldset></td></tr></table>';
}
add_action('show_user_profile', 'at_colab_campo_perfil');
add_action('edit_user_profile', 'at_colab_campo_perfil');

/** Guarda la lista. Sin manage_options o sin el nonce del campo, no toca nada. */
function at_colab_guardar_perfil($user_id): void {
	$user_id = (int) $user_id;
	if (!current_user_can('manage_options') || $user_id <= 0) {
		return;
	}
	$nonce = isset($_POST['_at_colab_perfil']) ? (string) wp_unslash($_POST['_at_colab_perfil']) : '';
	if (!wp_verify_nonce($nonce, 'at_colab_perfil_' . $user_id)) {
		return;
	}
	$ids = at_colab_normalizar_ids(isset($_POST['at_clientes_asignados']) ? wp_unslash($_POST['at_clientes_asignados']) : []);
	$ids = array_values(array_filter($ids, 'at_colab_cliente_existe'));
	update_user_meta($user_id, AT_COLAB_META, $ids);
}
add_action('personal_options_update', 'at_colab_guardar_perfil');
add_action('edit_user_profile_update', 'at_colab_guardar_perfil');
