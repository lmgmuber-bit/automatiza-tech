<?php
/** Finanzas AT: dólar observado del día (mindicador.cl) con caché por fecha. */

if (!defined('ABSPATH')) exit;

/** Segundos de espera máxima por cada consulta a mindicador. */
const AT_FIN_TC_TIMEOUT = 5;
/** Tope total (segundos) de la caminata de días hacia atrás en una misma llamada. */
const AT_FIN_TC_PRESUPUESTO = 15.0;
/** Minutos que se deja en paz a la fuente tras una falla (transient at_fin_tc_caida). */
const AT_FIN_TC_PAUSA_MIN = 10;

/** Reloj en segundos con decimales; el filtro `at_fin_tc_reloj` permite simularlo en las pruebas. */
function at_fin_tc_reloj(): float {
	return (float) apply_filters('at_fin_tc_reloj', microtime(true));
}

/** ¿La fuente falló hace poco? Mientras dure la pausa no se vuelve a llamar a la red. */
function at_fin_tc_en_pausa(): bool {
	return get_transient('at_fin_tc_caida') !== false;
}

/** Marca la fuente como caída por unos minutos. */
function at_fin_tc_marcar_caida(): void {
	set_transient('at_fin_tc_caida', 1, AT_FIN_TC_PAUSA_MIN * MINUTE_IN_SECONDS);
}

/**
 * Consulta un día en mindicador. Devuelve ['Y-m-d' (Chile) => valor] (vacío si ese día no tiene valor)
 * o null si la fuente falló (y entonces deja la pausa at_fin_tc_caida). Ignora valores menores o iguales a cero.
 * Una respuesta 200 con la serie vacía (fin de semana o feriado) no es una falla.
 */
function at_fin_tc_consultar_dia(DateTimeImmutable $dia): ?array {
	$respuesta = wp_remote_get('https://mindicador.cl/api/dolar/' . $dia->format('d-m-Y'), ['timeout' => AT_FIN_TC_TIMEOUT, 'headers' => ['Accept' => 'application/json']]);
	if (is_wp_error($respuesta) || (int) wp_remote_retrieve_response_code($respuesta) !== 200) { at_fin_tc_marcar_caida(); return null; }
	$json = json_decode((string) wp_remote_retrieve_body($respuesta), true);
	if (!is_array($json) || !isset($json['serie']) || !is_array($json['serie'])) { at_fin_tc_marcar_caida(); return null; }
	$chile = new DateTimeZone('America/Santiago');
	$serie = [];
	foreach ($json['serie'] as $punto) {
		if (!is_array($punto) || !isset($punto['fecha'], $punto['valor']) || !is_numeric($punto['valor']) || (float) $punto['valor'] <= 0) { continue; }
		try {
			$fecha = (new DateTimeImmutable((string) $punto['fecha']))->setTimezone($chile)->format('Y-m-d');
		} catch (Exception $e) {
			continue;
		}
		$serie[$fecha] = (float) $punto['valor'];
	}
	return $serie;
}

/**
 * Pesos por dólar del día `$fecha` (Y-m-d). Si ese día no tiene valor (fin de semana o feriado) usa el último anterior,
 * hasta 7 días atrás. Null si la fuente falla o no hay valor en ese rango (un fallo no se guarda en la caché).
 * Lo que ya está en la caché se usa siempre; si no, con la fuente en pausa (falló hace menos de 10 min) devuelve null sin tocar la red,
 * y la caminata de días se corta a los ~15 s en total.
 */
function at_fin_tipo_cambio(string $fecha): ?float {
	if (!at_fin_fecha_valida($fecha)) { return null; }
	$cache = get_option('at_fin_tc_cache', []);
	if (!is_array($cache)) { $cache = []; }
	if (isset($cache[$fecha]) && is_numeric($cache[$fecha]) && (float) $cache[$fecha] > 0) { return (float) $cache[$fecha]; }

	if (at_fin_tc_en_pausa()) { return null; }

	$dia = new DateTimeImmutable($fecha, new DateTimeZone('America/Santiago'));
	$serie = [];
	$valor = null;
	$inicio = at_fin_tc_reloj();
	for ($atras = 0; $atras <= 7 && $valor === null; $atras++) {
		if (at_fin_tc_reloj() - $inicio > AT_FIN_TC_PRESUPUESTO) { at_fin_tc_marcar_caida(); return null; }
		$consulta = at_fin_tc_consultar_dia($dia->modify('-' . $atras . ' days'));
		if ($consulta === null) { return null; }
		$serie += $consulta;
		$valor = at_fin_tc_de_serie($fecha, $serie);
	}
	if ($valor === null) { return null; }

	// Hoy aún sin publicar (mindicador publica durante el día): se usa el último valor, pero no se guarda en la caché bajo la fecha de hoy.
	// Ojo: el movimiento sí queda con ese último valor anterior y tc_estado=ok, y no se refresca después; solo la caché evita
	// que ese valor se dé por bueno para todo el día de hoy en los registros siguientes.
	if ($fecha === at_fin_hoy() && !isset($serie[$fecha])) { return $valor; }

	$cache[$fecha] = $valor;
	ksort($cache);
	if (count($cache) > 120) { $cache = array_slice($cache, -120, null, true); }
	update_option('at_fin_tc_cache', $cache, false);
	return $valor;
}
