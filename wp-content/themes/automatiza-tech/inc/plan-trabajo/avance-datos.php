<?php
/**
 * Estado del proyecto (Etapa 2): columna `avance` (JSON) de wp_automatiza_planes_trabajo. Migración idempotente
 * (at_plan_schema '1' → '2'), lectura y escritura. Sin la columna el módulo sigue funcionando: at_av_columna_lista()
 * da false, el panel avisa y la ruta de render responde 409. El payload del plan firmado nunca se toca (D3).
 */
if (!defined('ABSPATH')) {
	exit;
}

/** La migración del avance está hecha (columna avance creada). */
function at_av_columna_lista(): bool {
	return get_option('at_plan_schema') === '2';
}

/**
 * Agrega `avance LONGTEXT NULL` y deja at_plan_schema en '2'. Necesita la tabla creada (opción '1'). Si la columna ya
 * existe (instalación nueva: la crea dbDelta), solo marca la opción. Si falla, espera 5 minutos antes de reintentar,
 * igual que at_pt_migrar_esquema().
 */
function at_av_migrar_esquema(): void {
	if (get_option('at_plan_schema') !== '1' || get_transient('at_av_migrar_intento')) {
		return;
	}
	global $wpdb;
	$t = at_pt_tabla();
	$existe = fn(): bool => $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", 'avance')) === 'avance';
	if (!$existe()) {
		$wpdb->query("ALTER TABLE {$t} ADD COLUMN avance LONGTEXT NULL");
		if (!$existe()) {
			set_transient('at_av_migrar_intento', 1, 5 * MINUTE_IN_SECONDS);
			error_log('at_av: no se pudo agregar la columna avance a ' . $t . ': ' . $wpdb->last_error);
			return;
		}
	}
	update_option('at_plan_schema', '2');
}
add_action('admin_init', 'at_av_migrar_esquema', 11);

/** Avance del plan con todas sus claves (forma vacía si no hay, o si la columna todavía no existe). */
function at_av_leer(object $fila): array {
	return at_av_normalizar($fila->avance ?? null);
}

/** Guarda el avance completo (normalizado). false sin la migración, si el plan no existe o si el JSON pasa de 1,5 MB. */
function at_av_guardar(int $plan_id, array $avance): bool {
	if (!at_av_columna_lista() || !at_pt_plan($plan_id)) {
		return false;
	}
	$json = wp_json_encode(at_av_normalizar($avance), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if (!is_string($json) || strlen($json) > 1572864) {
		return false;
	}
	global $wpdb;
	return $wpdb->update(at_pt_tabla(), ['avance' => $json], ['id' => $plan_id]) !== false;
}

/** Cambia solo el registro de un render ('render' o 'render_previa'), sobre el avance recién leído. */
function at_av_guardar_render(int $plan_id, string $clave, array $campos): bool {
	if (!in_array($clave, ['render', 'render_previa'], true)) {
		return false;
	}
	$f = at_pt_plan($plan_id);
	if (!$f) {
		return false;
	}
	$a = at_av_leer($f);
	$a[$clave] = array_merge($a[$clave], array_intersect_key($campos, at_av_registro_vacio()));
	return at_av_guardar($plan_id, $a);
}
