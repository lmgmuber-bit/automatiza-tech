<?php
/**
 * Agenda de seguimiento (fase 1): funciones sin dependencia de la base.
 *
 * - Credencial de las rutas de n8n: con la cabecera válida pasa siempre; sin ella, en modo «aviso» pasa y queda anotado,
 *   en modo «exigir» responde 401.
 * - Respuestas mínimas: solo los campos que usan los flujos.
 * - Enlaces de correo firmados: HMAC-SHA256 de «id|acción|vencimiento» con una clave del servidor.
 *
 * Ajustes (constante en wp-config o, si no existe, opción de WordPress del mismo nombre en minúsculas):
 *   AT_AGENDA_AUTH_MODO            aviso (por omisión) | exigir
 *   AT_AGENDA_ENLACES_GRACIA_HASTA AAAA-MM-DD (fin de ese día, hora de Chile) o marca de tiempo; sin definir, la gracia sigue abierta
 *   AT_AGENDA_RESPUESTA            minima (por omisión) | completa (vuelve a las respuestas de antes)
 *   AT_AGENDA_ENLACE_SECRET        clave propia de los enlaces (32 caracteres o más); sin ella se usa la sal de WordPress
 *   AT_AGENDA_ENLACE_TTL_DIAS      vigencia de los enlaces de los correos (30 por omisión)
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_AGENDA_ACCIONES = ['confirm', 'cancel', 'reschedule'];
const AT_AGENDA_PAGINA_TTL = 7200; // vigencia de los enlaces que arma una página ya abierta (2 h)

/** Ajuste por constante u opción; $defecto si no existe ninguna. */
function at_agenda_config(string $constante, $defecto = null) {
	if (defined($constante)) {
		return constant($constante);
	}
	if (function_exists('get_option')) {
		$v = get_option(strtolower($constante), null);
		if ($v !== null && $v !== false && $v !== '') {
			return $v;
		}
	}
	return $defecto;
}

/** 'aviso' o 'exigir'. Cualquier otro valor cuenta como 'aviso'. */
function at_agenda_modo_auth(): string {
	return at_agenda_config('AT_AGENDA_AUTH_MODO', 'aviso') === 'exigir' ? 'exigir' : 'aviso';
}

/**
 * permission_callback de las rutas de máquina a máquina. true deja pasar; WP_Error 401 solo en modo «exigir».
 * Reutiliza at_cc_rest_auth (cabecera X-AT-Secret contra AT_REST_SECRET). Nunca anota el valor de la cabecera.
 */
function at_agenda_permiso_maquina($request) {
	$trae = (string) $request->get_header('x_at_secret') !== '';
	$valida = false;
	if ($trae && function_exists('at_cc_rest_auth')) {
		$valida = at_cc_rest_auth($request) === true;
	}
	if ($valida) {
		return true;
	}
	if (at_agenda_modo_auth() === 'exigir') {
		return new WP_Error('rest_forbidden', 'No autorizado.', ['status' => 401]);
	}
	error_log('agenda-rutas: llamada sin credencial valida ruta=' . $request->get_route() . ' metodo=' . $request->get_method()
		. ' cabecera=' . ($trae ? 'invalida' : 'ausente'));
	return true;
}

/* ----------------------------- Respuestas mínimas ----------------------------- */

const AT_AGENDA_CAMPOS_REUNION = ['id', 'client_name', 'client_email', 'company_name', 'meeting_date', 'meeting_time', 'status', 'contrato_id', 'propuesta_id'];
const AT_AGENDA_CAMPOS_BUSQUEDA = ['id', 'meeting_date', 'meeting_time', 'status'];
const AT_AGENDA_CAMPOS_EVENTO = ['exists', 'event_id'];

function at_agenda_respuesta_completa(): bool {
	return at_agenda_config('AT_AGENDA_RESPUESTA', 'minima') === 'completa';
}

function at_agenda_solo_campos(array $datos, array $permitidos): array {
	return array_intersect_key($datos, array_flip($permitidos));
}

/** GET followup-meetings/{id} */
function at_agenda_recortar_reunion(array $datos): array {
	return at_agenda_respuesta_completa() ? $datos : at_agenda_solo_campos($datos, AT_AGENDA_CAMPOS_REUNION);
}

/** Contenido de «meeting» en GET followup-meetings/search */
function at_agenda_recortar_busqueda(array $meeting): array {
	return at_agenda_respuesta_completa() ? $meeting : at_agenda_solo_campos($meeting, AT_AGENDA_CAMPOS_BUSQUEDA);
}

/** GET followup-meetings/check-event */
function at_agenda_recortar_evento(array $resp): array {
	return at_agenda_respuesta_completa() ? $resp : at_agenda_solo_campos($resp, AT_AGENDA_CAMPOS_EVENTO);
}

/* ----------------------------- Enlaces firmados ----------------------------- */

function at_agenda_secreto_enlaces(): string {
	$s = defined('AT_AGENDA_ENLACE_SECRET') ? (string) AT_AGENDA_ENLACE_SECRET : '';
	if (strlen($s) >= 32) {
		return $s;
	}
	return function_exists('wp_salt') ? (string) wp_salt('auth') : '';
}

/** Firma (64 hex) de «id|acción|vencimiento»; '' si la acción no existe o falta la clave. */
function at_agenda_firma(int $id, string $accion, int $vence): string {
	$secreto = at_agenda_secreto_enlaces();
	if ($secreto === '' || !in_array($accion, AT_AGENDA_ACCIONES, true)) {
		return '';
	}
	return hash_hmac('sha256', $id . '|' . $accion . '|' . $vence, $secreto);
}

/** Pares e (vencimiento) y t (firma) para un enlace. '' si no se puede firmar. */
function at_agenda_parametros_firma(int $id, string $accion, ?int $ttl = null, ?int $ahora = null): string {
	$ahora = $ahora ?? time();
	$ttl = $ttl ?? ((int) at_agenda_config('AT_AGENDA_ENLACE_TTL_DIAS', 30)) * 86400;
	$vence = $ahora + max(60, $ttl);
	$t = at_agenda_firma($id, $accion, $vence);
	return $t === '' ? '' : 'e=' . $vence . '&t=' . $t;
}

/** Enlace de correo firmado: .../followup-meetings/email-action/{acción}/{id}?e=..&t=.. */
function at_agenda_url_firmada(int $id, string $accion, ?int $ttl = null, ?int $ahora = null): string {
	$url = home_url('/wp-json/automatiza-tech/v1/followup-meetings/email-action/' . $accion . '/' . $id);
	$p = at_agenda_parametros_firma($id, $accion, $ttl, $ahora);
	return $p === '' ? $url : $url . '?' . $p;
}

/** true mientras los enlaces sin firma siguen aceptándose. */
function at_agenda_gracia_vigente(?int $ahora = null): bool {
	$ahora = $ahora ?? time();
	$hasta = at_agenda_config('AT_AGENDA_ENLACES_GRACIA_HASTA', null);
	if ($hasta === null) {
		return true;
	}
	if (is_int($hasta) || (is_string($hasta) && ctype_digit($hasta))) {
		return $ahora <= (int) $hasta;
	}
	try {
		$fin = new DateTimeImmutable(trim((string) $hasta) . ' 23:59:59', new DateTimeZone('America/Santiago'));
	} catch (Exception $e) {
		return false; // fecha ilegible: se cierra, no se deja abierta
	}
	return $ahora <= $fin->getTimestamp();
}

/**
 * Decide si un enlace sirve. Devuelve ['permitir' => bool, 'estado' => ok|sin_firma_en_gracia|sin_firma|invalida|vencida].
 */
function at_agenda_evaluar_enlace(int $id, string $accion, $e, $t, ?int $ahora = null): array {
	$ahora = $ahora ?? time();
	$t = is_string($t) ? $t : '';
	if ($t === '') {
		return at_agenda_gracia_vigente($ahora)
			? ['permitir' => true, 'estado' => 'sin_firma_en_gracia']
			: ['permitir' => false, 'estado' => 'sin_firma'];
	}
	$vence = (is_string($e) || is_int($e)) && ctype_digit((string) $e) ? (int) $e : 0;
	$esperada = $vence > 0 ? at_agenda_firma($id, $accion, $vence) : '';
	if ($esperada === '' || !preg_match('/^[0-9a-f]{64}$/', $t) || !hash_equals($esperada, $t)) {
		return ['permitir' => false, 'estado' => 'invalida'];
	}
	return $vence < $ahora ? ['permitir' => false, 'estado' => 'vencida'] : ['permitir' => true, 'estado' => 'ok'];
}

/** Título y texto de la página cuando el enlace no sirve (los mismos para todos los motivos). */
function at_agenda_texto_enlace_vencido(): array {
	return [
		'Enlace vencido',
		'Este enlace venció o ya no es válido. Para confirmar, reagendar o cancelar tu reunión, escríbenos a '
		. '<a href="mailto:contacto@automatizatech.cl">contacto@automatizatech.cl</a> y lo resolvemos contigo.',
	];
}
