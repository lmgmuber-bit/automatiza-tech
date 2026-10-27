<?php
/**
 * Funciones puras del colaborador AT (sin base de datos).
 */
if (!defined('ABSPATH') && !defined('AT_COLAB_PRUEBAS')) {
	exit;
}

/** Lista de ids de clientes del CRM: enteros positivos, sin repetir y ordenados. Cualquier otra cosa da []. */
function at_colab_normalizar_ids($crudo): array {
	if (!is_array($crudo)) {
		return [];
	}
	$ids = [];
	foreach ($crudo as $v) {
		if (is_int($v) || is_float($v) || (is_string($v) && ctype_digit($v))) {
			$n = (int) $v;
			if ($n > 0) {
				$ids[$n] = $n;
			}
		}
	}
	sort($ids);
	return array_values($ids);
}

/** Tipos de detalle que el colaborador nunca ve: dinero, propuestas y contratos. */
function at_colab_tipos_excluidos(): array {
	return ['propuesta_enviada', 'cotizacion', 'boleta', 'factura', 'pago', 'contrato', 'contratacion'];
}

/**
 * Filas ya unidas del historial: quita las de tipo excluido (por detail_type o tipo_evento), borra el monto y los
 * adjuntos de las demás y las ordena por 'fecha', de la más nueva a la más antigua.
 */
function at_colab_filtrar_eventos(array $eventos): array {
	$excluidos = at_colab_tipos_excluidos();
	$quedan = [];
	foreach ($eventos as $e) {
		if (!is_array($e)) {
			continue;
		}
		$tipo = (string) ($e['detail_type'] ?? $e['tipo_evento'] ?? '');
		if (in_array($tipo, $excluidos, true)) {
			continue;
		}
		unset($e['amount'], $e['attachment_url'], $e['attachment_name']);
		$quedan[] = $e;
	}
	usort($quedan, function ($a, $b) {
		return strcmp((string) ($b['fecha'] ?? ''), (string) ($a['fecha'] ?? ''));
	});
	return $quedan;
}
