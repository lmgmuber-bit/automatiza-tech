<?php
/**
 * Estado «Archivada» (Task 14, aprobada por Luis el 26-sep): propuestas viejas o reemplazadas que el
 * cliente ya no puede responder desde su enlace (at_cc_transicion_respuesta_valida() en puras.php no
 * le da ninguna transición desde 'archivada'). Quedan como historial y Luis las puede desarchivar o
 * registrar una aceptación a mano. Una reevaluación se hace con una propuesta nueva.
 *
 * at_cc_archivar_propuesta() y at_cc_desarchivar_propuesta() no dependen de la sesión ni del admin:
 * el despliegue las llama desde un script CLI (usuario_id 0) para archivar las propuestas viejas antes
 * de publicar la página con los botones. Los botones del panel están en panel.php.
 */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_at_cc_archivar', 'at_cc_accion_archivar');
add_action('admin_post_at_cc_desarchivar', 'at_cc_accion_desarchivar');

/** Estado en palabras para los avisos: la etiqueta de la lista si está cargada (admin); si no (CLI), el código. */
function at_cc_estado_en_palabras(string $status): string {
	return function_exists('at_pa_estado_etiqueta') ? (string) at_pa_estado_etiqueta($status)['etiqueta'] : $status;
}

/** Archiva la propuesta si su estado lo permite (at_cc_puede_archivar()). ['ok' => bool, 'mensaje' => string]. */
function at_cc_archivar_propuesta(object $p, int $usuario_id = 0): array {
	global $wpdb;
	$desde = (string) $p->status;
	if ($desde === 'archivada') {
		return ['ok' => false, 'mensaje' => 'La propuesta ya estaba archivada.'];
	}
	if (!at_cc_puede_archivar($desde)) {
		$motivos = [
			'aceptada'   => 'el cliente ya la aceptó',
			'contracted' => 'ya está contratada',
			'ajustando'  => 'n8n la está ajustando; espera a que termine',
			'generando'  => 'n8n está generando la versión final; espera a que termine',
		];
		$motivo = $motivos[$desde] ?? 'su estado («' . at_cc_estado_en_palabras($desde) . '») no lo permite';
		return ['ok' => false, 'mensaje' => 'Esta propuesta no se puede archivar: ' . $motivo . '.'];
	}
	// Mismo criterio que at_cc_registrar_respuesta(): el UPDATE exige el estado leído, para que una
	// respuesta del cliente que llegue en el mismo instante no quede pisada por el archivo.
	$n = $wpdb->query($wpdb->prepare(
		"UPDATE {$wpdb->prefix}automatiza_propuestas SET status = %s WHERE id = %d AND status = %s",
		'archivada',
		(int) $p->id,
		$desde
	));
	if ($n !== 1) {
		return ['ok' => false, 'mensaje' => 'La propuesta cambió mientras la archivabas: recarga la ficha e inténtalo de nuevo.'];
	}
	$p->status = 'archivada';
	// 'aviso_operativo' es un tipo interno (at_cc_tipos_internos()): no sale en las vistas públicas.
	at_cc_anotar_simple($p, 'aviso_operativo', 'Propuesta archivada', 'El cliente ya no puede responderla desde su enlace; queda como historial.', [
		'estado_anterior' => $desde,
		'usuario_id'      => $usuario_id,
	]);
	return ['ok' => true, 'mensaje' => 'Propuesta archivada: el cliente ya no puede responderla desde su enlace. Queda como historial y puedes desarchivarla cuando quieras.'];
}

/** Estado en que estaba la propuesta antes de su último archivo: el 'estado_anterior' de la última nota
 *  «Propuesta archivada»; 'sent' si no hay nota o si lo que trae no es un estado desde el que se archiva. */
function at_cc_estado_antes_de_archivar(int $propuesta_id): string {
	global $wpdb;
	$meta = $wpdb->get_var($wpdb->prepare(
		"SELECT metadata FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'aviso_operativo' AND title = %s ORDER BY id DESC LIMIT 1",
		$propuesta_id,
		'Propuesta archivada'
	));
	$d = json_decode((string) $meta, true);
	$anterior = is_array($d) ? (string) ($d['estado_anterior'] ?? '') : '';
	return at_cc_puede_archivar($anterior) ? $anterior : 'sent';
}

/** Desarchiva la propuesta: vuelve al estado que tenía antes de archivarla. ['ok' => bool, 'mensaje' => string]. */
function at_cc_desarchivar_propuesta(object $p, int $usuario_id = 0): array {
	global $wpdb;
	if ((string) $p->status !== 'archivada') {
		return ['ok' => false, 'mensaje' => 'Esta propuesta no está archivada.'];
	}
	$hacia = at_cc_estado_antes_de_archivar((int) $p->id);
	$n = $wpdb->query($wpdb->prepare(
		"UPDATE {$wpdb->prefix}automatiza_propuestas SET status = %s WHERE id = %d AND status = %s",
		$hacia,
		(int) $p->id,
		'archivada'
	));
	if ($n !== 1) {
		return ['ok' => false, 'mensaje' => 'La propuesta cambió mientras la desarchivabas: recarga la ficha e inténtalo de nuevo.'];
	}
	$p->status = $hacia;
	$estado = at_cc_estado_en_palabras($hacia);
	at_cc_anotar_simple($p, 'aviso_operativo', 'Propuesta desarchivada', 'Volvió al estado «' . $estado . '».', [
		'estado_restaurado' => $hacia,
		'usuario_id'        => $usuario_id,
	]);
	return ['ok' => true, 'mensaje' => 'Propuesta desarchivada: volvió al estado «' . $estado . '».'];
}

function at_cc_accion_archivar(): void {
	at_cc_accion_archivo('archivar');
}

function at_cc_accion_desarchivar(): void {
	at_cc_accion_archivo('desarchivar');
}

/** Botones «Archivar» / «Desarchivar» del panel (admin-post): nonce, manage_options y vuelta a la ficha con el aviso. */
function at_cc_accion_archivo(string $accion): void {
	$id = (int) ($_POST['proposal_id'] ?? 0);
	check_admin_referer('at_cc_' . $accion . '_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$p = at_cc_propuesta_por_id($id);
	if (!$p) {
		$r = ['ok' => false, 'mensaje' => 'Esa propuesta no existe.'];
	} elseif ($accion === 'archivar') {
		$r = at_cc_archivar_propuesta($p, get_current_user_id());
	} else {
		$r = at_cc_desarchivar_propuesta($p, get_current_user_id());
	}
	at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => $r['ok'] ? 'ok' : 'error', 'texto' => (string) $r['mensaje']]);
	wp_safe_redirect(at_cc_url_ficha_propuesta($id));
	exit;
}
