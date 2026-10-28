<?php
/**
 * Evento Lead por la API de Conversiones de Meta: funciones puras, sin WordPress.
 * Diseño: Docs/superpowers/specs/2026-10-28-meta-capi-lead-design.md. La huella (event_id) es la misma que calcula
 * el navegador en assets/js/at-lead-id.js, para que Meta junte el Lead del píxel con el del servidor.
 */
if (!defined('ABSPATH') && PHP_SAPI !== 'cli') { exit; }

function at_capi_norm_correo(string $correo): string {
	return strtolower(preg_replace('/\s+/', '', $correo));
}

/** Solo dígitos, con código de país; un celular chileno sin código (9 y ocho dígitos) se completa con 56. */
function at_capi_norm_telefono(string $telefono): string {
	$d = preg_replace('/\D/', '', $telefono);
	if (strpos($d, '00') === 0) {
		$d = substr($d, 2);
	}
	if (preg_match('/^9\d{8}$/', $d)) {
		$d = '56' . $d;
	}
	return $d;
}

/** AAAA-MM-DD válida, o ''. Acepta que venga con hora ("2026-11-03 00:00:00"). */
function at_capi_norm_fecha(string $fecha): string {
	if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($fecha), $m)) {
		return '';
	}
	return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : '';
}

/** HH:MM de 24 h desde H:MM, HH:MM o HH:MM:SS, o ''. */
function at_capi_norm_hora(string $hora): string {
	if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($hora), $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
		return '';
	}
	return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
}

function at_capi_hash(string $valor): string {
	return $valor === '' ? '' : hash('sha256', $valor);
}

/** sha256(correo|AAAA-MM-DD|HH:MM), primeros 40 hex; '' si falta un dato. Igual que AtLeadId.eventId en el navegador. */
function at_capi_event_id(string $correo, string $fecha, string $hora): string {
	$c = at_capi_norm_correo($correo);
	$f = at_capi_norm_fecha($fecha);
	$h = at_capi_norm_hora($hora);
	if ($c === '' || $f === '' || $h === '') {
		return '';
	}
	return substr(hash('sha256', "$c|$f|$h"), 0, 40);
}

/** Instagram y WhatsApp son conversaciones (chat); la web va como «other» porque /leads no recibe el navegador. */
function at_capi_action_source(string $source): string {
	return in_array($source, ['instagram', 'whatsapp'], true) ? 'chat' : 'other';
}

/**
 * Cuerpo para POST /<pixel>/events, sin el token (lo agrega el envío). $lead trae las claves de /leads:
 * id, email, phone, source, scheduled_date, scheduled_time.
 */
function at_capi_payload(array $lead, string $pixel_id, string $test_code, int $ahora): array {
	$source = (string) ($lead['source'] ?? 'web');
	$correo = at_capi_norm_correo((string) ($lead['email'] ?? ''));
	$tel = at_capi_norm_telefono((string) ($lead['phone'] ?? ''));
	$event_id = at_capi_event_id($correo, (string) ($lead['scheduled_date'] ?? ''), (string) ($lead['scheduled_time'] ?? ''));
	if ($event_id === '') {
		// Sin fecha u hora válidas no se puede deduplicar con el píxel: huella propia por lead.
		$event_id = substr(hash('sha256', 'lead|' . (int) ($lead['id'] ?? 0)), 0, 40);
	}
	$usuario = [];
	if ($correo !== '') {
		$usuario['em'] = [at_capi_hash($correo)];
		$usuario['external_id'] = [at_capi_hash($correo)];
	}
	if ($tel !== '') {
		$usuario['ph'] = [at_capi_hash($tel)];
		if (strpos($tel, '56') === 0 && strlen($tel) === 11) {
			$usuario['country'] = [at_capi_hash('cl')];
		}
	}
	$nombres = ['instagram' => 'Instagram', 'whatsapp' => 'WhatsApp'];
	$evento = [
		'event_name'    => 'Lead',
		'event_time'    => $ahora,
		'event_id'      => $event_id,
		'action_source' => at_capi_action_source($source),
		'user_data'     => $usuario,
		'custom_data'   => ['content_name' => 'Agenda (' . ($nombres[$source] ?? 'web') . ')', 'lead_source' => $source],
	];
	if ($evento['action_source'] === 'other') {
		$evento['event_source_url'] = 'https://automatizatech.cl/';
	}
	$cuerpo = ['data' => [$evento]];
	if ($test_code !== '') {
		$cuerpo['test_event_code'] = $test_code;
	}
	return $cuerpo;
}
