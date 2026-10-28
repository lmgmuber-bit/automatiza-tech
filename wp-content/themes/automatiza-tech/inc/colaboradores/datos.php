<?php
/**
 * Lo que el colaborador puede leer de un cliente: nombre, empresa e historial sin montos, adjuntos ni contratos.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** ['id', 'nombre', 'empresa'] o null si no existe. Nunca el correo ni el teléfono. */
function at_colab_cliente(int $crm_id): ?array {
	global $wpdb;
	$c = $crm_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, nombre, empresa FROM {$wpdb->prefix}crm_clientes WHERE id = %d", $crm_id)) : null;
	if (!$c) {
		return null;
	}
	return ['id' => (int) $c->id, 'nombre' => (string) $c->nombre, 'empresa' => (string) $c->empresa];
}

/**
 * Historial del cliente: eventos de crm_historial ('system') y detalles del cliente ('client'), filtrados por
 * at_colab_filtrar_eventos, con la etiqueta de origen de la línea de tiempo y el nombre de quien lo escribió.
 */
function at_colab_historial(int $crm_id): array {
	global $wpdb;
	if ($crm_id <= 0) {
		return [];
	}
	$filas = [];
	$hist = (array) $wpdb->get_results($wpdb->prepare(
		"SELECT tipo_evento, titulo, descripcion, metadata, usuario_id, created_at FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d",
		$crm_id
	), ARRAY_A);
	foreach ($hist as $h) {
		$filas[] = [
			'source' => 'system', 'tipo_evento' => (string) $h['tipo_evento'], 'title' => (string) $h['titulo'],
			'description' => (string) $h['descripcion'], 'metadata' => $h['metadata'], 'usuario_id' => (int) $h['usuario_id'],
			'fecha' => (string) $h['created_at'],
		];
	}
	// Los detalles cuelgan de la ficha operativa (wp_automatiza_tech_clients), nunca del id del CRM: así lo hace la
	// ficha del CRM (Task 16). Sin el módulo de cierre cargado no se busca nada (falla cerrada).
	$fichas = function_exists('at_cc_techs_de_crm')
		? array_values(array_filter(array_map(function ($t) { return (int) $t->id; }, at_cc_techs_de_crm($crm_id))))
		: [];
	$det = [];
	if ($fichas) {
		$marcas = implode(',', array_fill(0, count($fichas), '%d'));
		$det = (array) $wpdb->get_results($wpdb->prepare(
			"SELECT detail_type, title, description, metadata, created_by, created_at FROM {$wpdb->prefix}automatiza_clients_details WHERE client_id IN ($marcas)",
			$fichas
		), ARRAY_A);
	}
	foreach ($det as $d) {
		$filas[] = [
			'source' => 'client', 'detail_type' => (string) $d['detail_type'], 'title' => (string) $d['title'],
			'description' => (string) $d['description'], 'metadata' => $d['metadata'], 'created_by' => (int) $d['created_by'],
			'fecha' => (string) $d['created_at'],
		];
	}
	$filas = at_colab_filtrar_eventos($filas);
	foreach ($filas as &$f) {
		$f['origen_html'] = function_exists('at_tl_origen_html') ? at_tl_origen_html($f) : '';
		$uid = (int) ($f['usuario_id'] ?? $f['created_by'] ?? 0);
		$u = $uid > 0 ? get_userdata($uid) : false;
		$f['autor'] = $u ? (string) $u->display_name : '';
	}
	unset($f);
	return $filas;
}
