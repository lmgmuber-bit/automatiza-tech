<?php
/**
 * Tipo de cambio USD→CLP guardado 6 h (diseño 2026-10-29-whatsapp-citas-botones). La consulta al Banco Central y a la API
 * alternativa puede tardar más de 10 s: sin caché, cada mensaje de los bots Tech la esperaba y a veces el bot respondía
 * «aprox. $0 CLP». Una consulta caída nunca se guarda como fresca: responde la última tasa conocida.
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_TIPO_CAMBIO_TRANSIENT = 'at_tipo_cambio_usd_clp';

/** La tasa guardada si tiene menos de 6 h; si no, la de $consultar (y se guarda), o la última conocida si la consulta falla. */
function at_tipo_cambio_guardado(callable $consultar): float {
	$guardada = get_transient(AT_TIPO_CAMBIO_TRANSIENT);
	if ($guardada !== false && (float) $guardada > 0) {
		return (float) $guardada;
	}
	try {
		$tasa = (float) $consultar();
	} catch (Throwable $e) {
		error_log('[tipo-cambio] la consulta falló: ' . $e->getMessage());
		$tasa = 0.0;
	}
	if ($tasa > 0) {
		set_transient(AT_TIPO_CAMBIO_TRANSIENT, $tasa, 6 * HOUR_IN_SECONDS);
		update_option('automatiza_tech_last_exchange_rate', $tasa);
		return $tasa;
	}
	return (float) get_option('automatiza_tech_last_exchange_rate', 0);
}
