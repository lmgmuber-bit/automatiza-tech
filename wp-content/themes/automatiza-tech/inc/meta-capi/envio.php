<?php
/**
 * Evento Lead por la API de Conversiones de Meta: envío desde el servidor cuando /leads guarda una agenda.
 * Solo manda si el interruptor at_capi_activo vale 1 y PixelYourSite tiene píxel y token. Nunca rompe la agenda:
 * el envío es no bloqueante y los errores van al log sin datos personales.
 */
if (!defined('ABSPATH')) { exit; }

// Marketing API vigente al 28-oct-2026 (v24.0 venció el 6-oct-2026; el SDK de PixelYourSite 11.4.4 trae 16.0, vencida).
if (!defined('AT_CAPI_GRAPH_VERSION')) {
	define('AT_CAPI_GRAPH_VERSION', 'v25.0');
}
const AT_CAPI_DIAS_PRIMERA_AGENDA = 30;

/** ['activo' => bool, 'token' => string, 'pixel' => string, 'test' => string]. El token y el píxel son los de PixelYourSite. */
function at_capi_config(): array {
	$cfg = ['activo' => (string) get_option('at_capi_activo', '0') === '1', 'token' => '', 'pixel' => '', 'test' => ''];
	if (function_exists('PixelYourSite\Facebook')) {
		try {
			$fb = \PixelYourSite\Facebook();
			$ids = (array) $fb->getPixelIDs();
			$pixel = (string) ($ids[0] ?? '');
			if ($pixel !== '') {
				$cfg['pixel'] = $pixel;
				$cfg['token'] = (string) (((array) $fb->getApiToken())[$pixel] ?? '');
				$cfg['test'] = (string) (((array) $fb->getApiTestCode())[$pixel] ?? '');
			}
		} catch (\Throwable $e) {
			error_log('[at-capi] no se pudo leer PixelYourSite');
		}
	}
	return (array) apply_filters('at_capi_config', $cfg);
}

function at_capi_clave_contado(string $correo): string {
	return 'at_capi_lead_' . hash('sha256', at_capi_norm_correo($correo));
}

function at_capi_ya_contado(string $correo): bool {
	return (bool) get_transient(at_capi_clave_contado($correo));
}

function at_capi_marcar_contado(string $correo): void {
	set_transient(at_capi_clave_contado($correo), 1, AT_CAPI_DIAS_PRIMERA_AGENDA * DAY_IN_SECONDS);
}

/** POST no bloqueante a la Graph API; el token va en el cuerpo, nunca en la URL. true si la llamada salió. */
function at_capi_enviar(array $payload, array $cfg): bool {
	$cuerpo = $payload + ['access_token' => (string) $cfg['token']];
	$r = wp_remote_post('https://graph.facebook.com/' . AT_CAPI_GRAPH_VERSION . '/' . rawurlencode((string) $cfg['pixel']) . '/events', [
		'timeout'  => 5,
		'blocking' => false,
		'headers'  => ['Content-Type' => 'application/json'],
		'body'     => wp_json_encode($cuerpo),
	]);
	return !is_wp_error($r);
}

/** Oyente de automatiza_tech_lead_guardado (la dispara POST /leads después de guardar la fila). */
function at_capi_al_guardar_lead($lead_id, $data): void {
	try {
		$data = is_array($data) ? $data : [];
		$correo = at_capi_norm_correo((string) ($data['email'] ?? ''));
		$cfg = at_capi_config();
		if (empty($cfg['activo']) || (string) $cfg['token'] === '' || (string) $cfg['pixel'] === '' || $correo === '') {
			return;
		}
		if (at_capi_ya_contado($correo)) {
			return;
		}
		$lead = ['id' => (int) $lead_id] + $data;
		if (at_capi_enviar(at_capi_payload($lead, (string) $cfg['pixel'], (string) $cfg['test'], time()), $cfg)) {
			at_capi_marcar_contado($correo);
		} else {
			error_log('[at-capi] lead=' . (int) $lead_id . ' origen=' . preg_replace('/[^a-z_]/', '', (string) ($data['source'] ?? '')) . ' http=error');
		}
	} catch (\Throwable $e) {
		error_log('[at-capi] lead=' . (int) $lead_id . ' excepción');
	}
}
add_action('automatiza_tech_lead_guardado', 'at_capi_al_guardar_lead', 10, 2);
