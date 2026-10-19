<?php
/**
 * Detección automática de Retomar: busca las propuestas enviadas que quedaron sin respuesta y da de alta su seguimiento.
 * Regla (at_rt_es_candidata): propuesta `sent` o `evaluando`, enviada hace 14 días o más, sin ficha CRM `cliente` con ese
 * correo, sin baja y sin seguimiento previo con ese correo. No envía nada al prospecto: el seguimiento queda esperando la
 * redacción (activo) o una propuesta con la plantilla AT (espera_propuesta), y sale solo cuando Luis lo aprueba.
 */
if (!defined('ABSPATH')) { exit; }

/**
 * Día ('Y-m-d' estricto) en que se envió la propuesta; '' si no hay ninguna fecha válida (la regla lo trata como reciente).
 * Fuente: el historial de Seguimiento de la propuesta (`automatiza_propuestas_details`) guarda cada envío como un evento
 * público de tipo `propuesta_enviada`; se usa el más reciente, con la misma prioridad de fecha que la línea de tiempo
 * (completed_date, luego scheduled_date, luego el día en que se anotó). Sin ese evento, el día de `created_at` de la propuesta.
 * $p es la fila de `automatiza_propuestas` (basta `id` y `created_at`).
 */
function at_rt_fecha_envio_propuesta(array $p): string {
	global $wpdb;
	$id = (int) ($p['id'] ?? 0);
	$ultimo = '';
	if ($id > 0) {
		$eventos = $wpdb->get_results($wpdb->prepare("SELECT completed_date, scheduled_date, created_at FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND detail_type = 'propuesta_enviada'", $id), ARRAY_A);
		foreach ((array) $eventos as $evento) {
			foreach (['completed_date', 'scheduled_date', 'created_at'] as $campo) {
				$dia = at_pt_ymd((string) ($evento[$campo] ?? ''));
				if ($dia === '') { continue; }
				if ($dia > $ultimo) { $ultimo = $dia; }
				break;
			}
		}
	}
	return $ultimo !== '' ? $ultimo : at_pt_ymd((string) ($p['created_at'] ?? ''));
}

/** Fichas del CRM con ese correo: si alguna es cliente, y la primera de prospecto (id y teléfono). */
function at_rt_detectar_fichas(string $email): array {
	global $wpdb;
	$resultado = ['cliente' => false, 'id' => 0, 'telefono' => ''];
	$fichas = $wpdb->get_results($wpdb->prepare("SELECT id, tipo, telefono FROM {$wpdb->prefix}crm_clientes WHERE LOWER(TRIM(email)) = %s ORDER BY id", $email), ARRAY_A);
	foreach ((array) $fichas as $ficha) {
		if ($ficha['tipo'] === 'cliente') { $resultado['cliente'] = true; }
		if ($ficha['tipo'] === 'prospecto' && $resultado['id'] === 0) { $resultado['id'] = (int) $ficha['id']; $resultado['telefono'] = (string) $ficha['telefono']; }
	}
	return $resultado;
}

/**
 * Evalúa una propuesta y, si es candidata, crea su seguimiento. Devuelve ['id' => int, 'estado' => string] o null si no entra.
 * $hoy ('Y-m-d') y $activado ('Y-m-d H:i:s') salen de la fecha de la pasada, nunca del reloj del servidor.
 */
function at_rt_detectar_propuesta(array $p, string $hoy, string $activado): ?array {
	global $wpdb;
	$email = strtolower(trim((string) ($p['client_email'] ?? '')));
	if (!is_email($email)) { return null; }
	$fichas = at_rt_detectar_fichas($email);
	$telefono = at_pt_telefono_wa((string) ($p['phone'] ?? ''));
	if ($telefono === '') { $telefono = at_pt_telefono_wa($fichas['telefono']); }
	$existe = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}at_retomar WHERE LOWER(TRIM(email)) = %s LIMIT 1", $email));
	$motivo = at_rt_es_candidata(
		['status' => (string) ($p['status'] ?? ''), 'enviada' => at_rt_fecha_envio_propuesta($p)],
		['crm_tipo' => $fichas['cliente'] ? 'cliente' : '', 'baja' => at_rt_en_bajas($email, $telefono), 'existe' => $existe],
		$hoy
	);
	if ($motivo !== '') { return null; }
	$espera = at_rt_necesita_propuesta(isset($p['flujo']) ? (string) $p['flujo'] : null);
	$estado = $espera ? 'espera_propuesta' : 'activo';
	$id = at_rt_crear([
		'crm_cliente_id' => $fichas['id'],
		'propuesta_id' => (int) $p['id'],
		'nombre' => (string) ($p['client_name'] ?? ''),
		'negocio' => (string) ($p['company_name'] ?? ''),
		'email' => $email,
		'telefono' => $telefono,
		'wa_autorizado' => 0,
		'canales' => 'correo,whatsapp',
		'estado' => $estado,
		'render' => ['estado' => 'pendiente', 'nota' => 'Esperando redacción'],
		'redaccion' => [],
		'activado_at' => $activado,
		'proximo_at' => $hoy,
	]);
	if ($id <= 0) { return null; }
	at_rt_historia(['id' => $id, 'crm_cliente_id' => $fichas['id']], 'retomar_detectado', '🔁 Entró a Retomar');
	if (!$espera) { at_rt_pedir_redaccion($id); }
	return ['id' => $id, 'estado' => $estado];
}

/**
 * Pasada de detección a la hora de Chile $ahora ('Y-m-d H:i[:s]'). Devuelve los seguimientos que creó:
 * `creados` = activos (ya se pidió su redacción) y `espera` = en espera_propuesta (la propuesta no usa la plantilla AT);
 * son listas distintas. Sin esquema listo o con una fecha ilegible no hace nada. Un candado de MySQL evita que dos pasadas
 * simultáneas den de alta dos veces la misma propuesta; si otra está en curso, esta no hace nada y la siguiente repite la búsqueda.
 */
function at_rt_detectar(string $ahora): array {
	global $wpdb;
	$resultado = ['creados' => [], 'espera' => []];
	if (!at_rt_esquema_listo()) { return $resultado; }
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return $resultado; }
	$candado = $wpdb->prefix . 'at_rt_detectar';
	$tomado = (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $candado));
	if ($tomado === '0') { return $resultado; }
	try {
		$propuestas = $wpdb->get_results("SELECT id, client_email, client_name, company_name, phone, status, flujo, created_at FROM {$wpdb->prefix}automatiza_propuestas WHERE status IN ('sent','evaluando') ORDER BY id", ARRAY_A);
		foreach ((array) $propuestas as $propuesta) {
			$nuevo = at_rt_detectar_propuesta($propuesta, $fecha->format('Y-m-d'), $fecha->format('Y-m-d H:i:s'));
			if ($nuevo === null) { continue; }
			$resultado[$nuevo['estado'] === 'activo' ? 'creados' : 'espera'][] = $nuevo['id'];
		}
	} finally {
		if ($tomado === '1') { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $candado)); }
	}
	return $resultado;
}
