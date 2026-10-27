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
