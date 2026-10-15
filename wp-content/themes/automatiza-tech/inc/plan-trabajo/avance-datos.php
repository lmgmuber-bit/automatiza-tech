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

/** Guarda el avance solo si la columna sigue valiendo $json_antes (lo leído antes): para reclamar un envío sin que dos
 *  pedidos casi juntos manden dos correos. false si cambió, si no hay migración o si el JSON es muy grande. */
function at_av_guardar_si_igual(int $plan_id, string $json_antes, array $avance): bool {
	if (!at_av_columna_lista()) {
		return false;
	}
	$json = wp_json_encode(at_av_normalizar($avance), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if (!is_string($json) || strlen($json) > 1572864) {
		return false;
	}
	global $wpdb;
	$t = at_pt_tabla();
	$n = $json_antes === ''
		? $wpdb->query($wpdb->prepare("UPDATE {$t} SET avance = %s WHERE id = %d AND (avance IS NULL OR avance = '')", $json, $plan_id))
		: $wpdb->query($wpdb->prepare("UPDATE {$t} SET avance = %s WHERE id = %d AND BINARY avance = %s", $json, $plan_id, $json_antes)); // BINARY: bytes exactos, sin que mayúsculas ni tildes cuenten como iguales
	return (int) $n === 1;
}

/**
 * Cambia solo el registro de un render ('render' o 'render_previa') con la decisión de $decidir, sobre el avance recién
 * leído: $decidir recibe el registro actual y devuelve los campos a cambiar (se mezclan sobre el registro) o null si no
 * hay nada que cambiar. Se guarda con at_av_guardar_si_igual: si entretanto cambió otra cosa (un envío reclamado o
 * anotado, otro render), se relee y se decide de nuevo, hasta 3 veces; así la devolución del renderer nunca borra lo
 * que otro proceso escribió y lo que se decide siempre es sobre el registro vigente. $decidir puede correr varias
 * veces: que no tenga efectos fuera de lo que devuelve. false solo si las 3 veces fallan (o el plan o la clave no existen).
 */
function at_av_cambiar_render(int $plan_id, string $clave, callable $decidir): bool {
	if (!in_array($clave, ['render', 'render_previa'], true)) {
		return false;
	}
	for ($i = 0; $i < 3; $i++) {
		$f = at_pt_plan($plan_id);
		if (!$f) {
			return false;
		}
		$antes = (string) $f->avance; // tal cual salió de la base: es la condición del guardado
		$a = at_av_leer($f);
		$campos = $decidir($a[$clave]);
		if (!is_array($campos)) {
			return true;
		}
		$nuevo = array_merge($a[$clave], array_intersect_key($campos, at_av_registro_vacio()));
		if ($nuevo === $a[$clave] && at_av_columna_lista()) {
			return true; // ya está así: guardar lo mismo no cambia la fila y no es un fallo
		}
		$a[$clave] = $nuevo;
		if (at_av_guardar_si_igual($plan_id, $antes, $a)) {
			return true;
		}
	}
	return false;
}

/** Cambia solo el registro de un render con estos campos (ver at_av_cambiar_render). */
function at_av_guardar_render(int $plan_id, string $clave, array $campos): bool {
	return at_av_cambiar_render($plan_id, $clave, fn(array $actual): array => $campos);
}
