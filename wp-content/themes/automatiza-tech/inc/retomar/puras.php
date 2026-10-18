<?php
/** Reglas de Retomar sin acceso a WordPress ni efectos externos. */
require_once __DIR__ . '/../plan-trabajo/cierre-puras.php';

function at_rt_intervalo(int $dias): int { return max(7, $dias); }
function at_rt_max(int $max): int { return max(1, min(4, $max)); }

function at_rt_fecha(string $ymd): ?DateTimeImmutable {
	$fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new DateTimeZone('America/Santiago'));
	return $fecha && $fecha->format('Y-m-d') === $ymd ? $fecha : null;
}
function at_rt_es_habil(string $ymd): bool {
	$fecha = at_rt_fecha($ymd);
	return $fecha !== null && (int) $fecha->format('N') <= 5;
}
function at_rt_en_ventana(string $hm): bool {
	return preg_match('/^\d{2}:[0-5]\d$/D', $hm) === 1 && $hm >= '10:00' && $hm < '13:00';
}
function at_rt_proximo(string $ymd_envio, int $intervalo): string {
	$fecha = at_rt_fecha($ymd_envio);
	if ($fecha === null) { return ''; }
	$fecha = $fecha->modify('+' . at_rt_intervalo($intervalo) . ' days');
	while ((int) $fecha->format('N') > 5) { $fecha = $fecha->modify('+1 day'); }
	return $fecha->format('Y-m-d');
}
function at_rt_canales(int $n, int $max, string $canales, bool $wa_autorizado, string $telefono_wa): array {
	$resultado = ['correo'];
	if (($n === 1 || $n === $max) && in_array('whatsapp', array_map('trim', explode(',', $canales)), true) && $wa_autorizado && $telefono_wa !== '') {
		$resultado[] = 'whatsapp';
	}
	return $resultado;
}
function at_rt_motivo_parada(array $c): string {
	if (!empty($c['baja'])) { return 'baja'; }
	if (($c['propuesta_estado'] ?? '') === 'rechazada') { return 'rechazada'; }
	if (($c['propuesta_estado'] ?? '') === 'aceptada') { return 'aceptada'; }
	if (($c['crm_tipo'] ?? '') === 'cliente') { return 'cliente'; }
	if (!empty($c['reunion'])) { return 'agendo'; }
	if ((int) ($c['enviados'] ?? 0) >= at_rt_max((int) ($c['max'] ?? 4))) { return 'agotado'; }
	return '';
}
function at_rt_estado_por_motivo(string $motivo): string {
	return ['baja' => 'baja', 'agendo' => 'agendo', 'cliente' => 'cliente', 'aceptada' => 'cliente', 'rechazada' => 'rechazado', 'agotado' => 'agotado'][$motivo] ?? '';
}
function at_rt_token(int $id, string $salt): string { return substr(hash_hmac('sha256', 'rt|' . $id, $salt), 0, 32); }
function at_rt_token_valido(string $t, int $id, string $salt): bool { return hash_equals(at_rt_token($id, $salt), $t); }
function at_rt_asunto(int $n, string $nombre): string {
	$nombre = trim(str_replace(["\r", "\n"], '', $nombre));
	return match ($n) {
		2 => ($nombre !== '' ? $nombre . ', ' : '') . 'tu demo sigue lista para ti',
		3 => 'Podemos ajustarlo a tu presupuesto' . ($nombre !== '' ? ', ' . $nombre : ''),
		4 => 'Último recordatorio: ¿conversamos 20 minutos?',
		default => '¿Retomamos tu proyecto' . ($nombre !== '' ? ', ' . $nombre : '') . '?',
	};
}

/** Recorre valores de texto, sin convertir arrays a "Array"; D8 usa el detector canónico. */
function at_rt_internas(array $d): array {
	$textos = [];
	$recorrer = function (array $datos) use (&$recorrer, &$textos): void {
		foreach ($datos as $valor) {
			if (is_array($valor)) { $recorrer($valor); }
			elseif (is_string($valor)) { $textos[] = $valor; }
		}
	};
	$recorrer($d);
	return at_av_urls_internas($textos);
}

/** URL apta para correo y cabeceras: HTTPS absoluta sin controles, credenciales ni D8. */
function at_rt_url_correo(string $url): string {
	if ($url === '' || preg_match('/[\x00-\x20\x7f<>"\x27\\\\]|%0[ad]/i', $url) || at_rt_internas([$url])) { return ''; }
	$partes = parse_url($url);
	if (!is_array($partes) || strtolower($partes['scheme'] ?? '') !== 'https' || empty($partes['host']) || isset($partes['user']) || isset($partes['pass']) || filter_var($url, FILTER_VALIDATE_URL) === false) { return ''; }
	return $url;
}

/** Valores de salida compartidos por HTML y alternativa de texto. */
function at_rt_correo_campos(array $d): array {
	$salida = [];
	foreach (['nombre', 'negocio', 'quedamos'] as $k) {
		$v = is_string($d[$k] ?? null) ? trim($d[$k]) : '';
		$salida[$k] = at_rt_internas([$v]) ? '' : $v;
	}
	foreach (['url_ver', 'url_agendar', 'url_baja', 'logo'] as $k) {
		$salida[$k] = at_rt_url_correo(is_string($d[$k] ?? null) ? $d[$k] : '');
	}
	return $salida;
}
function at_rt_correo_html(array $d): string {
	$v = at_rt_correo_campos($d);
	$h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head><body style="margin:0;background:#f4f6fb;font-family:Arial,sans-serif;color:#222"><div style="max-width:600px;margin:24px auto;background:#fff;border-radius:10px;overflow:hidden"><div style="padding:24px;background:#1e40af;color:#fff;text-align:center">';
	if ($v['logo'] !== '') { $html .= '<img src="' . $h($v['logo']) . '" alt="AutomatizaTech" style="max-height:64px">'; }
	$html .= '<h1 style="font-size:22px">Retomemos tu proyecto</h1></div><div style="padding:26px;line-height:1.6"><p>Hola' . ($v['nombre'] !== '' ? ' ' . $h($v['nombre']) : '') . ', te saluda el equipo de AutomatizaTech.</p>';
	if ($v['negocio'] !== '') { $html .= '<p>Queremos retomar lo que conversamos sobre ' . $h($v['negocio']) . '.</p>'; }
	if ($v['quedamos'] !== '') { $html .= '<p>' . nl2br($h($v['quedamos']), false) . '</p>'; }
	$html .= '<p>Podemos ajustarnos a lo que necesitas y a tu presupuesto, partiendo por lo prioritario. ¿Agendamos 20 minutos para conversarlo con Luis?</p>';
	foreach (['url_ver' => 'Ver la presentación', 'url_agendar' => 'Agendar una reunión'] as $k => $etiqueta) {
		if ($v[$k] !== '') { $html .= '<p style="text-align:center"><a href="' . $h($v[$k]) . '" style="display:inline-block;padding:12px 24px;background:#1e40af;color:#fff;border-radius:24px;text-decoration:none">' . $etiqueta . '</a></p>'; }
	}
	$html .= '</div><div style="padding:18px;text-align:center;color:#64748b;font-size:12px">AutomatizaTech';
	if ($v['url_baja'] !== '') { $html .= '<p><a href="' . $h($v['url_baja']) . '" style="color:#64748b">No me interesa, no volver a escribirme</a></p>'; }
	return $html . '</div></div></body></html>';
}
function at_rt_correo_texto(array $d): string {
	$v = at_rt_correo_campos($d);
	$texto = 'Hola' . ($v['nombre'] !== '' ? ' ' . $v['nombre'] : '') . ", te saluda el equipo de AutomatizaTech.\n\n";
	if ($v['negocio'] !== '') { $texto .= 'Queremos retomar lo que conversamos sobre ' . $v['negocio'] . ".\n\n"; }
	if ($v['quedamos'] !== '') { $texto .= $v['quedamos'] . "\n\n"; }
	$texto .= "Podemos ajustarnos a lo que necesitas y a tu presupuesto, partiendo por lo prioritario. ¿Agendamos 20 minutos para conversarlo con Luis?\n\n";
	foreach (['url_ver' => 'Ver la presentación', 'url_agendar' => 'Agendar una reunión', 'url_baja' => 'No me interesa, no volver a escribirme'] as $k => $etiqueta) {
		if ($v[$k] !== '') { $texto .= $etiqueta . ': ' . $v[$k] . "\n"; }
	}
	return $texto;
}
function at_rt_cabeceras_baja(string $url_baja): array {
	$url = at_rt_url_correo($url_baja);
	return $url !== '' ? ['List-Unsubscribe: <' . $url . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'] : [];
}
