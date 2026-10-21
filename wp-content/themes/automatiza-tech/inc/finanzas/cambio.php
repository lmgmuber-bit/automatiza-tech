<?php
/** Finanzas AT: dólar observado del día (mindicador.cl) con caché por fecha. */

if (!defined('ABSPATH')) exit;

/**
 * Consulta un día en mindicador. Devuelve ['Y-m-d' (Chile) => valor] (vacío si ese día no tiene valor)
 * o null si la fuente falló. Ignora valores menores o iguales a cero.
 */
function at_fin_tc_consultar_dia(DateTimeImmutable $dia): ?array {
	$respuesta = wp_remote_get('https://mindicador.cl/api/dolar/' . $dia->format('d-m-Y'), ['timeout' => 10, 'headers' => ['Accept' => 'application/json']]);
	if (is_wp_error($respuesta) || (int) wp_remote_retrieve_response_code($respuesta) !== 200) { return null; }
	$json = json_decode((string) wp_remote_retrieve_body($respuesta), true);
	if (!is_array($json) || !isset($json['serie']) || !is_array($json['serie'])) { return null; }
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
 */
function at_fin_tipo_cambio(string $fecha): ?float {
	if (!at_fin_fecha_valida($fecha)) { return null; }
	$cache = get_option('at_fin_tc_cache', []);
	if (!is_array($cache)) { $cache = []; }
	if (isset($cache[$fecha]) && is_numeric($cache[$fecha]) && (float) $cache[$fecha] > 0) { return (float) $cache[$fecha]; }

	$dia = new DateTimeImmutable($fecha, new DateTimeZone('America/Santiago'));
	$serie = [];
	$valor = null;
	for ($atras = 0; $atras <= 7 && $valor === null; $atras++) {
		$consulta = at_fin_tc_consultar_dia($dia->modify('-' . $atras . ' days'));
		if ($consulta === null) { return null; }
		$serie += $consulta;
		$valor = at_fin_tc_de_serie($fecha, $serie);
	}
	if ($valor === null) { return null; }

	// Hoy aún sin publicar (mindicador publica durante el día): se usa el último valor, pero no se guarda bajo la fecha de hoy
	// para que mañana, o en cuanto se publique, el movimiento no quede fijado a un dólar de otro día.
	if ($fecha === at_fin_hoy() && !isset($serie[$fecha])) { return $valor; }

	$cache[$fecha] = $valor;
	ksort($cache);
	if (count($cache) > 120) { $cache = array_slice($cache, -120, null, true); }
	update_option('at_fin_tc_cache', $cache, false);
	return $valor;
}
