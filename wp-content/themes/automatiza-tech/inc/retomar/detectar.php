<?php
/**
 * Detección automática de Retomar: busca las propuestas enviadas que quedaron sin respuesta y da de alta su seguimiento.
 * Regla (at_rt_es_candidata), aplicada a la propuesta más reciente de cada correo: `sent` o `evaluando`, enviada hace 14 días
 * o más, sin ficha CRM `cliente` con ese correo, sin baja y sin seguimiento previo con ese correo. No envía nada al
 * prospecto: el seguimiento queda esperando la redacción (activo) o una propuesta con la plantilla AT (espera_propuesta),
 * y sale solo cuando Luis lo aprueba.
 */
if (!defined('ABSPATH')) { exit; }

/**
 * Día ('Y-m-d' estricto) en que se envió la propuesta; '' si no hay ninguna fecha válida (la regla lo trata como reciente).
 * Fuente: el historial de Seguimiento de la propuesta (`automatiza_propuestas_details`). Cuenta como envío:
 *  - el envío desde el panel, que at_cc_tras_envio() (cierre-cliente/whatsapp.php) anota como tipo `email` con el título
 *    «Propuesta enviada por correo, con el botón para aceptarla» y completed_date = hoy (nunca como `propuesta_enviada`);
 *  - las notas manuales de tipo `propuesta_enviada`.
 * Se usa el evento más reciente, con la misma prioridad de fecha que la línea de tiempo (completed_date, luego
 * scheduled_date, luego el día en que se anotó). Sin ninguno, el día de `created_at` de la propuesta (en una v3, el día del
 * borrador de la reunión: solo sirve si nunca se registró el envío). Cualquier otro correo del historial no cuenta.
 * $p es la fila de `automatiza_propuestas` (basta `id` y `created_at`).
 */
function at_rt_fecha_envio_propuesta(array $p): string {
	global $wpdb;
	$id = (int) ($p['id'] ?? 0);
	$ultimo = '';
	if ($id > 0) {
		$eventos = $wpdb->get_results($wpdb->prepare(
			"SELECT completed_date, scheduled_date, created_at FROM {$wpdb->prefix}automatiza_propuestas_details WHERE propuesta_id = %d AND (detail_type = 'propuesta_enviada' OR (detail_type = 'email' AND title LIKE %s))",
			$id,
			$wpdb->esc_like('Propuesta enviada por correo') . '%'
		), ARRAY_A);
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
 * $hoy ('Y-m-d') y $activado ('Y-m-d H:i:s') salen de la fecha de la pasada, nunca del reloj del servidor. Con $pedir en
 * false (la pasada ya usó su cupo de pedidos) el seguimiento activo queda con la redacción vacía: la pide el reintento
 * del reloj (at_rt_reintentar_redacciones) en una pasada siguiente.
 */
function at_rt_detectar_propuesta(array $p, string $hoy, string $activado, bool $pedir = true): ?array {
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
	// pedida_at con la hora de la pasada: así el reintento del reloj (Ruling 7) mide el atasco con el mismo reloj. La
	// detección es parte del reloj: espera a n8n lo mismo que sus otros pedidos (Ruling 12).
	if (!$espera && $pedir) { at_rt_pedir_redaccion($id, '', $activado, at_rt_timeout_reloj()); }
	return ['id' => $id, 'estado' => $estado];
}

/**
 * Pasada de detección a la hora de Chile $ahora ('Y-m-d H:i[:s]'). Devuelve los seguimientos que creó:
 * `creados` = activos (ya se pidió su redacción) y `espera` = en espera_propuesta (la propuesta no usa la plantilla AT);
 * son listas distintas. Por cada correo solo se evalúa su propuesta más reciente (mayor id), sea cual sea su estado: si la
 * más nueva ya está aceptada, en borrador, lista o reciente, una más vieja que siga en `sent` no dispara nada, porque la
 * persona ya tiene otra propuesta en juego. Sin esquema listo o con una fecha ilegible no hace nada. Un candado de MySQL
 * evita que dos pasadas simultáneas den de alta dos veces la misma propuesta; si otra está en curso, esta no hace nada y la
 * siguiente repite la búsqueda. $max_pedidos (Ruling 12; negativo = sin tope): se pide la redacción de los primeros
 * $max_pedidos activos creados; los demás se crean igual con la redacción vacía y los toma el reintento del reloj. Así
 * quien llama sabe que hizo min(count(creados), $max_pedidos) pedidos.
 */
function at_rt_detectar(string $ahora, int $max_pedidos = -1): array {
	global $wpdb;
	$resultado = ['creados' => [], 'espera' => []];
	if (!at_rt_esquema_listo()) { return $resultado; }
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return $resultado; }
	$candado = $wpdb->prefix . 'at_rt_detectar';
	$tomado = (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $candado));
	if ($tomado === '0') { return $resultado; }
	try {
		$propuestas = $wpdb->get_results("SELECT id, client_email, client_name, company_name, phone, status, flujo, created_at FROM {$wpdb->prefix}automatiza_propuestas WHERE client_email <> '' ORDER BY id DESC", ARRAY_A);
		$vistos = [];
		foreach ((array) $propuestas as $propuesta) {
			$correo = strtolower(trim((string) $propuesta['client_email']));
			if (isset($vistos[$correo])) { continue; }
			$vistos[$correo] = true;
			$pedir = $max_pedidos < 0 || count($resultado['creados']) < $max_pedidos;
			$nuevo = at_rt_detectar_propuesta($propuesta, $fecha->format('Y-m-d'), $fecha->format('Y-m-d H:i:s'), $pedir);
			if ($nuevo === null) { continue; }
			$resultado[$nuevo['estado'] === 'activo' ? 'creados' : 'espera'][] = $nuevo['id'];
		}
	} finally {
		if ($tomado === '1') { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $candado)); }
	}
	return $resultado;
}
