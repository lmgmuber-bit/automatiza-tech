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

/**
 * ¿Puede el colaborador estar en esta ruta del admin? Solo «Mis clientes», su perfil, las dos acciones propias de
 * admin-post y, por ajax, el heartbeat de WordPress. Todo lo demás (escritorio, CRM, widget MAXTECH, ajax de otros
 * módulos que solo piden un nonce) queda cerrado.
 */
function at_colab_ruta_permitida(string $pagenow, array $get, array $post, bool $ajax): bool {
	$accion = (string) ($post['action'] ?? $get['action'] ?? '');
	if ($ajax) {
		return $accion === 'heartbeat';
	}
	if ($pagenow === 'admin.php') {
		return (string) ($get['page'] ?? '') === 'at-mis-clientes';
	}
	if ($pagenow === 'profile.php') {
		return true;
	}
	if ($pagenow === 'admin-post.php') {
		return in_array($accion, ['at_colab_nota', 'at_colab_responder'], true);
	}
	return false;
}

/** Nota del colaborador para el historial: ['ok', 'error' => ''|'nota_vacia'|'nota_larga', 'texto' recortado]. */
function at_colab_validar_nota(string $t): array {
	$t = trim(str_replace(["\r\n", "\r"], "\n", $t));
	if ($t === '') {
		return ['ok' => false, 'error' => 'nota_vacia', 'texto' => ''];
	}
	if (mb_strlen($t, 'UTF-8') > 6000) {
		return ['ok' => false, 'error' => 'nota_larga', 'texto' => $t];
	}
	return ['ok' => true, 'error' => '', 'texto' => $t];
}

/**
 * Tipos del historial que el colaborador sí ve (detail_type de los detalles o tipo_evento de crm_historial). Es una
 * lista de PERMITIDOS: un tipo nuevo o desconocido queda fuera hasta que se agregue aquí. Quedan fuera a propósito
 * el dinero y los contratos, las propuestas y su aceptación (traen precios o la llave pública de la propuesta), los
 * correos y WhatsApp, los tipos internos del CRM, documentos y transcripciones.
 */
function at_colab_tipos_permitidos(): array {
	return [
		'nota', 'reunion', 'llamada', 'seguimiento', 'acta_reunion', 'brief', 'mockup', 'informe',
		'proyecto_creado', 'proyecto_update', 'item_proyecto', 'inicio_proyecto', 'milestone',
		'entregable', 'entregable_nota', 'entregable_version', 'entregable_respuesta',
		'hito_cierre_publicado', 'hito_cierre_correo', 'hito_cierre_whatsapp',
	];
}

/**
 * Filas ya unidas del historial: deja solo las de tipo permitido (por detail_type o tipo_evento), borra el monto y
 * los adjuntos y las ordena por 'fecha', de la más nueva a la más antigua.
 */
function at_colab_filtrar_eventos(array $eventos): array {
	$permitidos = at_colab_tipos_permitidos();
	$quedan = [];
	foreach ($eventos as $e) {
		if (!is_array($e)) {
			continue;
		}
		$tipo = (string) ($e['detail_type'] ?? $e['tipo_evento'] ?? '');
		if (!in_array($tipo, $permitidos, true)) {
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
