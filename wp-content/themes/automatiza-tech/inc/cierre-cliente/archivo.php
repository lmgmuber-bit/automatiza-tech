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
	// T14 ronda 1 (2ª revisión), hallazgo 3: false es un error de la base (se registra), no una carrera.
	if ($n === false) {
		error_log('at_cc: no se pudo archivar la propuesta ' . (int) $p->id . ': ' . $wpdb->last_error);
		return ['ok' => false, 'mensaje' => 'No se pudo guardar: inténtalo de nuevo.'];
	}
	if ($n !== 1) {
		return ['ok' => false, 'mensaje' => 'La propuesta cambió mientras la archivabas: recarga la ficha e inténtalo de nuevo.'];
	}
	// 'aviso_operativo' es un tipo interno (at_cc_tipos_internos()): no sale en las vistas públicas.
	$nota = at_cc_anotar_simple($p, 'aviso_operativo', 'Propuesta archivada', 'El cliente ya no puede responderla desde su enlace; queda como historial.', [
		'estado_anterior' => $desde,
		'usuario_id'      => $usuario_id,
	]);
	if ($nota <= 0) {
		// T14 ronda 1, hallazgo 1: sin esta nota, desarchivar no sabría a qué estado volver y caería en
		// 'sent' (una v3 en borrador quedaría con la barra de aceptar). Se deshace el archivo, con la
		// misma condición de estado que el UPDATE de arriba.
		$deshecho = $wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->prefix}automatiza_propuestas SET status = %s WHERE id = %d AND status = %s",
			$desde,
			(int) $p->id,
			'archivada'
		));
		if ($deshecho === 1) {
			return ['ok' => false, 'mensaje' => 'No se pudo anotar el archivo en Seguimiento, así que la propuesta no se archivó: inténtalo de nuevo.'];
		}
		$p->status = 'archivada';
		return ['ok' => false, 'mensaje' => 'La propuesta quedó archivada, pero no se pudo anotar en Seguimiento el estado en que estaba («' . at_cc_estado_en_palabras($desde) . '»): revisa la ficha antes de desarchivarla.'];
	}
	$p->status = 'archivada';
	return ['ok' => true, 'mensaje' => 'Propuesta archivada: el cliente ya no puede responderla desde su enlace. Queda como historial y puedes desarchivarla cuando quieras.'];
}

/** Estado en que estaba la propuesta antes de su último archivo: el 'estado_anterior' de la nota
 *  «Propuesta archivada» más nueva que lo traiga; 'sent' si ninguna lo trae o si lo que traen no es un
 *  estado desde el que se archiva. T14 ronda 1, hallazgo 1: no basta con la última nota, porque el botón
 *  ✏️ Editar de Seguimiento no actualiza la nota sino que inserta otra con el mismo tipo y título, sin
 *  metadata (AutomatizaTech_Client_Details::add_prospect_detail()). */
function at_cc_estado_antes_de_archivar(int $propuesta_id): string {
	global $wpdb;
	$metas = $wpdb->get_col($wpdb->prepare(
		"SELECT metadata FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'aviso_operativo' AND title = %s ORDER BY id DESC",
		$propuesta_id,
		'Propuesta archivada'
	));
	foreach ((array) $metas as $meta) {
		$d = json_decode((string) $meta, true);
		$anterior = is_array($d) ? (string) ($d['estado_anterior'] ?? '') : '';
		if (at_cc_puede_archivar($anterior)) {
			return $anterior;
		}
	}
	return 'sent';
}

/** ¿Luis puede registrar a mano la aceptación de esta propuesta? T14 ronda 1 (2ª revisión), hallazgo 1:
 *  una archivada solo si el estado en que estaba antes de archivarla también lo permitía (lo decide
 *  at_cc_transicion_respuesta_valida() en puras.php); si no, hay que desarchivarla para trabajarla. */
function at_cc_se_puede_aceptar_a_mano(object $p): bool {
	$estado = (string) $p->status;
	$antes = $estado === 'archivada' ? at_cc_estado_antes_de_archivar((int) $p->id) : '';
	return at_cc_transicion_respuesta_valida($estado, 'aceptada', true, $antes);
}

/** Rastro interno cuando el cliente intenta responder una propuesta archivada (T14 ronda 1, hallazgo 2):
 *  la respuesta no se aplica, pero Luis la ve en Seguimiento (tipo interno 'aviso_operativo', no sale en
 *  las vistas públicas) y puede registrar una aceptación a mano si corresponde. La descripción no lleva
 *  datos personales; lo que mandó el cliente (nombre, comentario, IP, teléfono, wamid) va en $metadata. */
function at_cc_anotar_intento_archivada(object $p, string $salida, string $canal, array $metadata = []): int {
	$canales = ['pagina' => 'la página de la propuesta', 'whatsapp' => 'WhatsApp'];
	return at_cc_anotar_simple(
		$p,
		'aviso_operativo',
		'Intentó responder una propuesta archivada (no se aplicó)',
		'Salida: ' . $salida . ' · por ' . ($canales[$canal] ?? $canal) . '. La propuesta está archivada: no cambió nada.',
		array_merge(['salida' => $salida, 'canal' => $canal], $metadata)
	);
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
	if ($n === false) {
		error_log('at_cc: no se pudo desarchivar la propuesta ' . (int) $p->id . ': ' . $wpdb->last_error);
		return ['ok' => false, 'mensaje' => 'No se pudo guardar: inténtalo de nuevo.'];
	}
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
