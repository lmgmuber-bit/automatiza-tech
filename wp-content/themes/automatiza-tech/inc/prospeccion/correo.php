<?php
/** Rastro · Prospección: correo con el diseño corporativo AT para el primer contacto. Funciones puras: el sistema no envía nada, Luis lo copia y lo manda. */
if (!defined('ABSPATH')) { exit; }

/** Dónde encontramos al negocio, según el campo `fuente` del prospecto. */
function at_pro_fuente_texto(array $p): string {
	$f = (string) ($p['fuente'] ?? 'manual');
	if ($f === 'lead_scout' || $f === 'sellbird') { return 'Google Maps'; }
	if ($f === 'instagram') { return 'Instagram'; }
	return 'tu sitio web';
}

/** Asunto del correo. */
function at_pro_correo_asunto(array $p): string {
	$n = trim((string) ($p['negocio'] ?? ''));
	return 'Propuesta comercial para ' . ($n !== '' ? $n : 'tu negocio') . ' · AutomatizaTech';
}

/** Primer párrafo en texto plano (sin escapar): «Soy Luis… vi que {señal}.» */
function at_pro_correo_presentacion(array $p): string {
	$s = trim((string) ($p['senales'] ?? ''));
	return 'Soy Luis, de AutomatizaTech SpA (automatizatech.cl). Encontré tu negocio en ' . at_pro_fuente_texto($p)
		. ($s !== '' ? ' y vi que ' . $s . '.' : ' y quise escribirte.');
}

/** HTML del correo: tablas y estilos en línea, 600 px máximo, sin <style> ni clases. Todo dato del prospecto va escapado. */
function at_pro_correo_html(array $p, string $url_form, string $url_baja): string {
	$n = trim((string) ($p['negocio'] ?? ''));
	$logo = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
	$ff = "font-family:Poppins,'Segoe UI',Arial,sans-serif;";
	$saludo = $n !== '' ? 'Hola, equipo de <strong>' . esc_html($n) . '</strong>:' : 'Hola:';
	$form = esc_url($url_form);
	$baja = esc_url($url_baja);
	$priv = 'https://automatizatech.cl/privacidad/';
	$h = '<!DOCTYPE html><html lang="es-CL"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . esc_html(at_pro_correo_asunto($p)) . '</title></head>';
	$h .= '<body style="margin:0;padding:0;background-color:#f4f4f4;' . $ff . '">';
	$h .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f4f4;"><tr><td align="center" style="padding:20px 10px;">';
	$h .= '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:8px;">';
	// encabezado
	$h .= '<tr><td align="center" style="background-color:#1e40af;padding:30px;border-radius:8px 8px 0 0;">'
		. '<img src="' . esc_url($logo) . '" alt="AutomatizaTech" width="200" style="width:200px;max-width:100%;height:auto;display:block;border:0;margin:0 auto;">'
		. '<h1 style="margin:20px 0 0;font-size:22px;line-height:1.3;font-weight:600;color:#ffffff;' . $ff . '">Una propuesta para tu negocio</h1></td></tr>';
	// cuerpo
	$h .= '<tr><td style="padding:36px 30px 20px;color:#333333;font-size:16px;line-height:1.6;' . $ff . '">';
	$h .= '<p style="margin:0 0 16px;">' . $saludo . '</p>';
	$h .= '<p style="margin:0 0 16px;">' . esc_html(at_pro_correo_presentacion($p)) . '</p>';
	$h .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0;"><tr><td style="background-color:#f9fafb;border-left:4px solid #06d6a0;padding:16px 20px;border-radius:6px;color:#333333;font-size:16px;line-height:1.6;' . $ff . '">'
		. 'Ayudamos a negocios como el tuyo a ordenar su atención y sus reservas con automatizaciones simples.</td></tr></table>';
	$h .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:24px auto 8px;"><tr><td align="center" bgcolor="#06d6a0" style="background-color:#06d6a0;border-radius:4px;">'
		. '<a href="' . $form . '" style="display:inline-block;padding:14px 26px;color:#ffffff;background-color:#06d6a0;text-decoration:none;font-weight:bold;font-size:16px;border-radius:4px;' . $ff . '">Agendar diagnóstico sin costo</a></td></tr></table>';
	$h .= '<p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#6b7280;text-align:center;">Si el botón no carga, copia este enlace en tu navegador:<br><a href="' . $form . '" style="color:#1e40af;word-break:break-all;">' . esc_html($url_form) . '</a></p>';
	$h .= '</td></tr>';
	// letra chica
	$h .= '<tr><td style="padding:10px 30px 24px;color:#6b7280;font-size:12px;line-height:1.5;' . $ff . '">'
		. 'Este es un mensaje comercial (publicidad). Si no quieres que te volvamos a escribir, responde «NO» a este correo o entra aquí: <a href="' . $baja . '" style="color:#6b7280;text-decoration:underline;word-break:break-all;">' . esc_html($url_baja) . '</a>. '
		. 'Así tratamos tus datos: <a href="' . esc_url($priv) . '" style="color:#6b7280;text-decoration:underline;">' . esc_html($priv) . '</a></td></tr>';
	// pie
	$h .= '<tr><td align="center" style="background-color:#1f2937;padding:20px;border-radius:0 0 8px 8px;color:#9ca3af;font-size:12px;line-height:1.5;' . $ff . '">'
		. 'AutomatizaTech SpA · Providencia, Santiago · <a href="mailto:contacto@automatizatech.cl" style="color:#9ca3af;text-decoration:underline;">contacto@automatizatech.cl</a></td></tr>';
	$h .= '</table></td></tr></table></body></html>';
	return $h;
}
