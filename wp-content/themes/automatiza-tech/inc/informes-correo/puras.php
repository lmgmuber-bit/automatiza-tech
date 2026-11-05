<?php
/**
 * Informes semanales del equipo AT por el SMTP del servidor: validación sin WordPress (se prueba con php puro).
 * Solo se envía a direcciones de la lista configurada en la base (opción at_informes_destinos): el repo es público
 * y las direcciones no van en el código.
 */

const AT_INF_MAX_ASUNTO = 200;
const AT_INF_MAX_HTML = 300000;
const AT_INF_MAX_TEXTO = 100000;
const AT_INF_MAX_PDF = 3145728; // 3 MB ya decodificado

function at_inf_correo_normal($valor): string {
	return is_string($valor) ? strtolower(trim($valor)) : '';
}

/** Nombre de archivo seguro: sin rutas, solo letras, números, punto, guion y guion bajo, y terminado en .pdf. */
function at_inf_nombre_pdf($valor): string {
	$base = is_string($valor) ? basename(str_replace('\\', '/', $valor)) : '';
	$base = preg_replace('/\.pdf$/i', '', $base);
	$base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);
	$base = trim(preg_replace('/-{2,}/', '-', $base), '-.');
	if ($base === '') { $base = 'Informe-AT'; }
	return substr($base, 0, 100) . '.pdf';
}

/**
 * Revisa el pedido y devuelve ['ok' => bool, 'errores' => [...], 'datos' => [...]].
 * $destinos: direcciones permitidas. $responder: la única dirección aceptada para Reply-To ('' = ninguna).
 */
function at_inf_validar(array $c, array $destinos, string $responder): array {
	$errores = [];
	$permitidos = array_values(array_filter(array_map('at_inf_correo_normal', $destinos)));
	$para = at_inf_correo_normal($c['para'] ?? '');
	if ($para === '' || !in_array($para, $permitidos, true)) { $errores[] = 'para: el destinatario no está en la lista permitida'; }

	$cc = $c['cc'] ?? [];
	$cc = is_array($cc) ? $cc : [$cc];
	$cc = array_values(array_filter(array_map('at_inf_correo_normal', $cc)));
	foreach ($cc as $copia) {
		if (!in_array($copia, $permitidos, true)) { $errores[] = 'cc: una copia no está en la lista permitida'; break; }
	}

	$asunto = is_string($c['asunto'] ?? null) ? trim($c['asunto']) : '';
	if ($asunto === '' || preg_match('/[\r\n]/', $asunto) || mb_strlen($asunto) > AT_INF_MAX_ASUNTO) {
		$errores[] = 'asunto: obligatorio, de una línea y hasta ' . AT_INF_MAX_ASUNTO . ' caracteres';
	}
	$html = is_string($c['html'] ?? null) ? $c['html'] : '';
	if ($html === '' || strlen($html) > AT_INF_MAX_HTML) { $errores[] = 'html: obligatorio y hasta 300 KB'; }
	$texto = is_string($c['texto'] ?? null) ? $c['texto'] : '';
	if (strlen($texto) > AT_INF_MAX_TEXTO) { $errores[] = 'texto: hasta 100 KB'; }

	$pdf = null;
	if (isset($c['pdf_base64']) && $c['pdf_base64'] !== '') {
		$bytes = is_string($c['pdf_base64']) ? base64_decode(preg_replace('/\s+/', '', $c['pdf_base64']), true) : false;
		if ($bytes === false || strncmp($bytes, '%PDF-', 5) !== 0) { $errores[] = 'pdf_base64: no es un PDF en base64'; }
		elseif (strlen($bytes) > AT_INF_MAX_PDF) { $errores[] = 'pdf_base64: el PDF pasa de 3 MB'; }
		else { $pdf = $bytes; }
	}

	$responderA = at_inf_correo_normal($c['responder_a'] ?? '');
	if ($responderA !== '' && $responderA !== at_inf_correo_normal($responder)) { $errores[] = 'responder_a: dirección no permitida'; }

	return ['ok' => !$errores, 'errores' => $errores, 'datos' => [
		'para' => $para, 'cc' => $cc, 'asunto' => $asunto, 'html' => $html, 'texto' => $texto,
		'pdf' => $pdf, 'pdf_nombre' => at_inf_nombre_pdf($c['pdf_nombre'] ?? ''), 'responder_a' => $responderA,
	]];
}
