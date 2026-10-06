<?php
/**
 * Correos del módulo de entregables con el diseño AT (el del correo a Orly del 05-oct). Puras: devuelven asunto y HTML.
 */

function at_en_h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function at_en_boton(string $href, string $texto, string $fondo, string $color = '#ffffff'): string {
	return '<a href="' . at_en_h($href) . '" style="display:inline-block;background:' . $fondo . ';color:' . $color . ';padding:13px 30px;border-radius:25px;text-decoration:none;font-weight:bold;margin:6px;">' . at_en_h($texto) . '</a>';
}

/** Envoltorio común: encabezado degradado con logo, cuerpo y pie. */
function at_en_correo_marco(string $titulo, string $subtitulo, string $cuerpo, string $logo): string {
	return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
		. '<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,Helvetica,sans-serif;color:#222;">'
		. '<div style="max-width:620px;margin:32px auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">'
		. '<div style="background:linear-gradient(135deg,#1e3a8a,#06d6a0);background-color:#1e3a8a;color:#ffffff;text-align:center;padding:30px 20px 22px;">'
		. ($logo !== '' ? '<img src="' . at_en_h($logo) . '" alt="AutomatizaTech" style="max-width:140px;margin-bottom:10px;">' : '')
		. '<h1 style="margin:0;font-size:22px;">' . at_en_h($titulo) . '</h1>'
		. ($subtitulo !== '' ? '<p style="margin:8px 0 0;font-size:15px;">' . at_en_h($subtitulo) . '</p>' : '')
		. '</div><div style="padding:28px 26px;font-size:15px;line-height:1.6;">' . $cuerpo . '</div>'
		. '<div style="background:#f8f9fa;color:#6c757d;text-align:center;font-size:13px;padding:16px 10px;">© ' . date('Y') . ' AutomatizaTech · <a href="https://automatizatech.cl/" style="color:#1e3a8a;">automatizatech.cl</a></div>'
		. '</div></body></html>';
}

function at_en_firma(): string {
	return '<p style="margin-top:24px;">Saludos cordiales,</p><p style="margin-bottom:0;"><b>Luis Miguel</b><br>AutomatizaTech<br><a href="https://automatizatech.cl" style="color:#1e3a8a;">https://automatizatech.cl</a></p>';
}

function at_en_correo_version(array $v): array {
	$titulo = trim((string) ($v['titulo'] ?? ''));
	$n = (int) ($v['numero'] ?? 1);
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$url_v = at_en_url_version_valida((string) ($v['url_version'] ?? ''));
	$url_p = at_en_url_version_valida((string) ($v['url_pagina'] ?? ''));
	$cuerpo = '<p>Hola <strong>' . at_en_h($nombre !== '' ? $nombre : 'cliente') . '</strong>:</p>'
		. '<p>Te enviamos la versión ' . $n . ' de <strong>' . at_en_h($titulo) . '</strong>.</p>'
		. at_en_mensaje_html((string) ($v['mensaje'] ?? ''))
		. ($url_v !== '' ? '<p style="text-align:center;margin:22px 0 6px;">' . at_en_boton($url_v, 'Ver la versión ' . $n, '#1e3a8a') . '</p>' : '')
		. ($url_p !== '' ? '<p>¿Tienes comentarios? Déjalos aquí antes de la reunión; puedes adjuntar hasta 3 imágenes:</p><p style="text-align:center;margin:12px 0 22px;">' . at_en_boton($url_p, 'Agregar notas u observaciones', '#06d6a0', '#06261c') . '</p>' : '')
		. at_en_firma();
	$asunto = $titulo . ': versión ' . $n . ' para revisar';
	return ['asunto' => (!empty($v['prueba']) ? '[PRUEBA] ' : '') . $asunto, 'html' => at_en_correo_marco($titulo, 'Versión ' . $n, $cuerpo, (string) ($v['logo'] ?? ''))];
}

function at_en_correo_nota_luis(array $v): array {
	$cliente = trim((string) ($v['cliente'] ?? '')) ?: 'El cliente';
	$titulo = trim((string) ($v['titulo'] ?? ''));
	$img = (int) ($v['imagenes'] ?? 0);
	$cuerpo = '<p><strong>' . at_en_h($cliente) . '</strong>' . (trim((string) ($v['empresa'] ?? '')) !== '' ? ' (' . at_en_h((string) $v['empresa']) . ')' : '')
		. ' dejó una nota sobre la versión ' . (int) ($v['numero'] ?? 0) . ' de <strong>' . at_en_h($titulo) . '</strong>:</p>'
		. '<div style="background:#f8f9fa;border-left:4px solid #1e3a8a;padding:14px 18px;border-radius:8px;margin:16px 0;">' . nl2br(at_en_h((string) ($v['texto'] ?? ''))) . '</div>'
		. ($img > 0 ? '<p>' . $img . ($img === 1 ? ' imagen adjunta' : ' imágenes adjuntas') . ' (se ven en la ficha).</p>' : '')
		. '<p style="text-align:center;margin:22px 0;">' . at_en_boton((string) ($v['url_ficha'] ?? ''), 'Responder en la ficha', '#1e3a8a') . '</p>';
	return ['asunto' => '📝 ' . $cliente . ' dejó una nota en ' . $titulo, 'html' => at_en_correo_marco('Nota nueva', $titulo, $cuerpo, (string) ($v['logo'] ?? ''))];
}

function at_en_correo_respuesta(array $v): array {
	$titulo = trim((string) ($v['titulo'] ?? ''));
	$nombre = trim((string) ($v['nombre'] ?? ''));
	$cuerpo = '<p>Hola <strong>' . at_en_h($nombre !== '' ? $nombre : 'cliente') . '</strong>:</p>'
		. '<p>Luis respondió tu nota sobre la versión ' . (int) ($v['numero'] ?? 0) . ' de <strong>' . at_en_h($titulo) . '</strong>:</p>'
		. '<div style="background:#f0fdf4;border-left:4px solid #06d6a0;padding:14px 18px;border-radius:8px;margin:16px 0;">' . nl2br(at_en_h(at_en_extracto((string) ($v['texto'] ?? ''), 400))) . '</div>'
		. '<p style="text-align:center;margin:22px 0;">' . at_en_boton((string) ($v['url_pagina'] ?? ''), 'Ver la conversación', '#1e3a8a') . '</p>'
		. at_en_firma();
	return ['asunto' => 'Respuesta a tu nota sobre ' . $titulo, 'html' => at_en_correo_marco('Respuesta a tu nota', $titulo, $cuerpo, (string) ($v['logo'] ?? ''))];
}
