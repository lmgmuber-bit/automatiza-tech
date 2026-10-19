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
/** Canales de un envío aprobado. Con la aprobación semanal el WhatsApp sale en TODO recordatorio aprobado si el canal
 *  está elegido, el prospecto lo autorizó y hay teléfono; $n y $max quedan por compatibilidad de firma (ya no cuentan). */
function at_rt_canales(int $n, int $max, string $canales, bool $wa_autorizado, string $telefono_wa): array {
	$resultado = ['correo'];
	if (in_array('whatsapp', array_map('trim', explode(',', $canales)), true) && $wa_autorizado && trim($telefono_wa) !== '') {
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
/** Mensaje para compartir a mano (copiar o abrir el WhatsApp de Luis) mientras Meta aprueba la plantilla
 *  retomar_propuesta. Mismo texto que la plantilla, con los dos enlaces escritos. */
function at_rt_texto_manual(string $nombre, string $negocio, string $url_ver, string $url_baja): string {
	$nombre = trim($nombre);
	$negocio = trim($negocio);
	return 'Hola ' . ($nombre !== '' ? $nombre . ' ' : '') . '👋 Te saluda el equipo de AutomatizaTech. Hace unas semanas conversamos sobre '
		. ($negocio !== '' ? $negocio : 'tu proyecto') . ' y quedó pendiente. Te dejamos un resumen de lo que vimos y tu demo. '
		. 'Si algo no calza con lo que necesitas o con tu presupuesto, lo ajustamos y podemos partir por lo más importante. '
		. '¿Agendamos 20 minutos para conversarlo?' . "\n\n" . 'Ver y agendar: ' . $url_ver . "\n\n"
		. 'Si prefieres que no te escribamos más: ' . $url_baja;
}
/** wa.me al teléfono del prospecto con el mensaje; '' sin teléfono. $telefono_wa ya normalizado (solo dígitos). */
function at_rt_url_wa_manual(string $telefono_wa, string $texto): string {
	return $telefono_wa === '' ? '' : 'https://wa.me/' . $telefono_wa . '?text=' . rawurlencode($texto);
}

/* ---------- Fase 2: detección, aprobación semanal y resumen (sin WordPress) ---------- */

/** true si el texto trae un monto: «$», CLP/USD/UF como palabra o cifras con separador de miles (1.500, 1,500).
 *  Si el texto no es UTF-8 válido se revisa por bytes, así un texto roto nunca se da por limpio. */
function at_rt_tiene_montos(string $t): bool {
	$r = preg_match('/\$|\b(CLP|USD|UF)\b|\d{1,3}(?:[.,]\d{3})+/iu', $t);
	if ($r === false) { $r = preg_match('/\$|\b(CLP|USD|UF)\b|\d{1,3}(?:[.,]\d{3})+/i', $t); }
	return $r === 1;
}

/** Motivo por el que una propuesta NO entra a Retomar, o '' si es candidata. Orden de revisión:
 *  estado, reciente, cliente, baja, existe.
 *  $p = ['status' => string, 'enviada' => 'Y-m-d']; $c = ['crm_tipo' => string, 'baja' => bool, 'existe' => bool];
 *  $hoy 'Y-m-d' (Chile). Una fecha de envío ausente o inválida cuenta como «reciente»: sin prueba de antigüedad no se toca. */
function at_rt_es_candidata(array $p, array $c, string $hoy, int $dias_min = 14): string {
	if (!in_array($p['status'] ?? '', ['sent', 'evaluando'], true)) { return 'estado'; }
	$fecha_hoy = at_rt_fecha($hoy);
	$enviada = at_rt_fecha(is_string($p['enviada'] ?? null) ? $p['enviada'] : '');
	if ($fecha_hoy === null || $enviada === null || $enviada->format('Y-m-d') > $fecha_hoy->modify('-' . max(0, $dias_min) . ' days')->format('Y-m-d')) { return 'reciente'; }
	if (($c['crm_tipo'] ?? '') === 'cliente') { return 'cliente'; }
	if (!empty($c['baja'])) { return 'baja'; }
	if (!empty($c['existe'])) { return 'existe'; }
	return '';
}

/** Solo las propuestas hechas con la plantilla AT (flujo 'v3') se pueden enlazar en un recordatorio. */
function at_rt_necesita_propuesta(?string $flujo): bool { return $flujo !== 'v3'; }

/** Fecha y hora de Chile desde «Y-m-d H:i[:s]» (admite «T» en vez del espacio; solo «Y-m-d» es la medianoche); null si no se entiende. */
function at_rt_ahora(string $ahora): ?DateTimeImmutable {
	$zona = new DateTimeZone('America/Santiago');
	foreach (['!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d\TH:i:s', '!Y-m-d\TH:i', '!Y-m-d'] as $formato) {
		$fecha = DateTimeImmutable::createFromFormat($formato, trim($ahora), $zona);
		$errores = DateTimeImmutable::getLastErrors();
		if ($fecha instanceof DateTimeImmutable && (!$errores || ($errores['warning_count'] === 0 && $errores['error_count'] === 0))) { return $fecha; }
	}
	return null;
}

/** Semana ISO-8601 en hora de Chile, p. ej. «2026-W43» (el 2027-01-01 es «2026-W53»). '' si la fecha no se entiende. */
function at_rt_semana(string $ahora): string {
	$fecha = at_rt_ahora($ahora);
	return $fecha === null ? '' : $fecha->format('o-\WW');
}

/** ¿Toca enviar el resumen semanal? Verdadero si la semana de $ahora no es $ultima_semana y ya llegó el día
 *  ($dia 1..7, ISO «N») y la hora ($hora 'H:i'). Pasado el día, se recupera un envío perdido (lunes perdido → martes).
 *  Un día fuera de 1..7 se ajusta al borde y una hora inválida usa 09:00. */
function at_rt_toca_resumen(string $ahora, int $dia, string $hora, string $ultima_semana): bool {
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null || $fecha->format('o-\WW') === $ultima_semana) { return false; }
	$dia = max(1, min(7, $dia));
	if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $hora) !== 1) { $hora = '09:00'; }
	$n = (int) $fecha->format('N');
	return $n > $dia || ($n === $dia && $fecha->format('H:i') >= $hora);
}

/** Día de envío al prospecto para algo aprobado a las $ahora: hoy si es hábil y falta para las 13:00; si no, el
 *  siguiente día hábil. '' si la fecha no se entiende. (La hora exacta la sigue decidiendo la ventana 10:00–13:00.) */
function at_rt_programar(string $ahora): string {
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return ''; }
	$dia = $fecha->setTime(0, 0);
	if ($fecha->format('H:i') >= '13:00' || (int) $dia->format('N') > 5) { $dia = $dia->modify('+1 day'); }
	while ((int) $dia->format('N') > 5) { $dia = $dia->modify('+1 day'); }
	return $dia->format('Y-m-d');
}

/** Firma (32 hex) de un enlace de aprobación. $accion: aprobar|cambios|omitir|ver. */
function at_rt_firma(int $id, string $accion, string $nonce, int $vence, string $salt): string {
	return substr(hash_hmac('sha256', "rta|$id|$accion|$nonce|$vence", $salt), 0, 32);
}
function at_rt_firma_valida(string $f, int $id, string $accion, string $nonce, int $vence, string $salt): bool {
	if ($salt === '' || !in_array($accion, ['aprobar', 'cambios', 'omitir', 'ver'], true)) { return false; }
	return hash_equals(at_rt_firma($id, $accion, $nonce, $vence, $salt), $f);
}

/** Enlace http(s) apto para el cuerpo del resumen: sin controles, comillas ni credenciales y sin D8 (easypanel).
 *  Devuelve '' si no sirve; nunca javascript:, mailto: ni «//host». */
function at_rt_url_resumen($url): string {
	$url = is_string($url) ? trim($url) : '';
	if ($url === '' || preg_match('/[\x00-\x20\x7f<>"\x27\\\\]/', $url) || at_rt_internas([$url])) { return ''; }
	$partes = parse_url($url);
	if (!is_array($partes) || !in_array(strtolower($partes['scheme'] ?? ''), ['http', 'https'], true) || empty($partes['host']) || isset($partes['user']) || isset($partes['pass'])) { return ''; }
	return $url;
}

/** Tarjeta (HTML de correo, solo estilos en línea y tablas) de un prospecto del resumen semanal.
 *  $t = ['tipo' => normal|preparacion|propuesta|revision, 'nombre', 'negocio', 'dias' => int (desde la propuesta),
 *  'n' => int (recordatorio que toca), 'max' => int, 'canales' => string[] (correo|whatsapp), 'textos' => ['quedamos',
 *  'proponemos', 'partir', 'recomendacion'?], 'url_presentacion', 'enlaces' => ['aprobar', 'cambios', 'omitir', 'ver'],
 *  'url_panel'? (solo para propuesta y revisión)].
 *  Solo 'normal' lleva textos y botones; los otros tres tipos muestran un aviso sin botones (un tipo desconocido se
 *  trata como 'revision', nunca como aprobable). Sin <img>: la miniatura es un bloque HTML (D8). Todo se escapa. */
function at_rt_tarjeta_html(array $t): string {
	$h = fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$limpio = function ($v): string {
		$v = is_string($v) ? trim($v) : '';
		return ($v === '' || at_rt_internas([$v])) ? '' : $v;
	};
	$tipo = in_array($t['tipo'] ?? '', ['normal', 'preparacion', 'propuesta'], true) ? $t['tipo'] : 'revision';
	$nombre = $limpio($t['nombre'] ?? '');
	$negocio = $limpio($t['negocio'] ?? '');
	$titulo = $nombre !== '' ? $nombre : ($negocio !== '' ? $negocio : 'Prospecto sin nombre');
	$n = max(1, (int) ($t['n'] ?? 1));
	$max = max(1, (int) ($t['max'] ?? 4));
	$dias = max(0, (int) ($t['dias'] ?? 0));
	$linea = [];
	if ($nombre !== '' && $negocio !== '') { $linea[] = $negocio; }
	$linea[] = 'propuesta enviada ' . ($dias === 0 ? 'hoy' : ($dias === 1 ? 'hace 1 día' : 'hace ' . $dias . ' días'));
	$canales = is_array($t['canales'] ?? null) ? $t['canales'] : [];
	if (in_array('correo', $canales, true)) { $linea[] = 'correo ✓'; }
	if (in_array('whatsapp', $canales, true)) { $linea[] = 'WhatsApp ✓'; }
	$enlaces = is_array($t['enlaces'] ?? null) ? $t['enlaces'] : [];
	$panel = at_rt_url_resumen($t['url_panel'] ?? '');
	$enlace = fn(string $url, string $etiqueta): string => '<a href="' . $h($url) . '" style="color:#1e40af">' . $etiqueta . '</a>';

	$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border:1px solid #d6dde6;border-radius:10px;border-collapse:separate;margin:0 0 14px"><tr><td style="padding:12px;font-family:Arial,sans-serif;font-size:14px;line-height:1.45;color:#111">';
	$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="font-size:15px;font-weight:bold;color:#111">' . $h($titulo) . '</td><td align="right" style="font-size:12px;color:#0b7a75;white-space:nowrap">Recordatorio ' . $n . ' de ' . $max . '</td></tr></table>';
	$html .= '<div style="font-size:12px;color:#666;margin:2px 0 8px">' . implode(' · ', array_map($h, $linea)) . '</div>';

	if ($tipo !== 'normal') {
		[$etiqueta, $aviso] = [
			'propuesta' => ['Necesita propuesta nueva', 'La propuesta que le enviamos es antigua y no la enlazamos para no mostrarle montos viejos. Hay que hacerle una propuesta nueva y vincularla desde el panel; mientras tanto no sale ningún recordatorio.'],
			'preparacion' => ['En preparación', 'Estamos redactando el recordatorio y armando su presentación. Te lo mostramos listo en el próximo aviso.'],
			'revision' => ['Necesita revisión', 'El recordatorio no quedó listo para enviarse. Revisa y corrige sus textos en el panel.'],
		][$tipo];
		$html .= '<div style="margin:0 0 4px"><span style="display:inline-block;background:#fef3c7;color:#92400e;border-radius:6px;padding:4px 10px;font-size:12px;font-weight:bold">' . $etiqueta . '</span></div>';
		$html .= '<div style="font-size:12.5px;color:#333">' . $h($aviso) . '</div>';
		if ($panel !== '' && $tipo !== 'preparacion') { $html .= '<div style="margin-top:8px;font-size:12.5px">' . $enlace($panel, 'Abrir en el panel') . '</div>'; }
		return $html . '</td></tr></table>';
	}

	$textos = is_array($t['textos'] ?? null) ? $t['textos'] : [];
	$partes = '';
	foreach (['quedamos' => 'Lo que conversamos', 'proponemos' => 'Lo que proponemos', 'partir' => 'Para partir', 'recomendacion' => 'Recomendación de ajuste'] as $clave => $rotulo) {
		$v = $limpio($textos[$clave] ?? '');
		if ($v !== '') { $partes .= '<div style="margin:0 0 6px"><b>' . $rotulo . ':</b> ' . nl2br($h($v), false) . '</div>'; }
	}
	$miniatura = $negocio !== '' ? $negocio : $titulo;
	if (preg_match('/^.{29,}$/us', $miniatura) === 1 && preg_match('/^.{27}/us', $miniatura, $corte) === 1) { $miniatura = $corte[0] . '…'; }
	$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td width="130" valign="top" style="width:130px;padding:0 10px 0 0">'
		. '<div style="width:120px;height:68px;border-radius:6px;background:#0f2740;background-image:linear-gradient(135deg,#0f2740,#1d4a6e);color:#fff;font-size:10px;line-height:1.3;padding:6px;box-sizing:border-box;overflow:hidden">RETOMEMOS LA CONVERSACIÓN<br><b style="font-size:12px">' . $h($miniatura) . '</b></div>'
		. '</td><td valign="top" style="font-size:12.5px;color:#333">' . $partes . '</td></tr></table>';

	$vistas = [];
	$url = at_rt_url_resumen($t['url_presentacion'] ?? '');
	if ($url !== '') { $vistas[] = $enlace($url, 'Ver la presentación completa'); }
	$url = at_rt_url_resumen($enlaces['ver'] ?? '');
	if ($url !== '') { $vistas[] = $enlace($url, 'Ver el correo tal como le llega'); }
	if ($vistas) { $html .= '<div style="margin:8px 0 10px;font-size:12.5px">👀 ' . implode(' · ', $vistas) . '</div>'; }

	$botones = '';
	foreach (['aprobar' => ['✅ Aprobar y enviar', 'background:#16a34a;color:#fff;font-weight:bold'], 'cambios' => ['✏️ Pedir cambios', 'background:#f59e0b;color:#111;font-weight:bold'], 'omitir' => ['⏭️ Esta semana no', 'background:#e5e7eb;color:#111']] as $accion => [$rotulo, $estilo]) {
		$url = at_rt_url_resumen($enlaces[$accion] ?? '');
		if ($url !== '') { $botones .= '<a href="' . $h($url) . '" style="display:inline-block;' . $estilo . ';border-radius:6px;padding:7px 12px;text-decoration:none;margin:0 8px 8px 0">' . $rotulo . '</a>'; }
	}
	if ($botones !== '') { $html .= '<div>' . $botones . '</div>'; }
	return $html . '</td></tr></table>';
}

/** Correo semanal a Luis (HTML completo, estilos en línea, sin <img>).
 *  $tarjetas: arreglos de at_rt_tarjeta_html() (se les completa 'url_panel') o tarjetas ya dibujadas (string).
 *  $salieron: prospectos que terminaron su ciclo, [['nombre' => string, 'negocio' => string, 'motivo' => string], ...];
 *  'motivo' es el estado de cierre (agotado|agendo|cliente|aceptada|baja|rechazado|rechazada), que se muestra en
 *  español, o un texto libre, que se muestra tal cual.
 *  $url_panel: enlace «Abrir el panel Retomar» (si no es una URL http(s) válida, se omite). */
function at_rt_resumen_html(array $tarjetas, array $salieron, string $url_panel): string {
	$h = fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$limpio = function ($v): string {
		$v = is_string($v) ? trim($v) : '';
		return ($v === '' || at_rt_internas([$v])) ? '' : $v;
	};
	$panel = at_rt_url_resumen($url_panel);
	$cuerpo = '';
	$cuenta = 0;
	foreach ($tarjetas as $t) {
		if (is_array($t)) {
			if ($panel !== '' && empty($t['url_panel'])) { $t['url_panel'] = $panel; }
			$cuerpo .= at_rt_tarjeta_html($t);
			$cuenta++;
		} elseif (is_string($t) && $t !== '') {
			$cuerpo .= $t;
			$cuenta++;
		}
	}
	$motivos = ['agotado' => 'completó sus recordatorios sin responder', 'agendo' => 'agendó una reunión', 'cliente' => 'ya es cliente', 'aceptada' => 'aceptó la propuesta', 'baja' => 'pidió no recibir más mensajes', 'rechazado' => 'rechazó la propuesta', 'rechazada' => 'rechazó la propuesta'];
	$filas = '';
	foreach ($salieron as $s) {
		if (!is_array($s)) { continue; }
		$nombre = $limpio($s['nombre'] ?? '');
		$negocio = $limpio($s['negocio'] ?? '');
		$motivo = $limpio($s['motivo'] ?? '');
		$quien = $nombre !== '' ? $nombre : ($negocio !== '' ? $negocio : 'Un prospecto');
		$filas .= '<li style="margin:0 0 4px"><b>' . $h($quien) . '</b>' . ($nombre !== '' && $negocio !== '' ? ' (' . $h($negocio) . ')' : '') . ($motivo !== '' ? ' · ' . $h($motivos[$motivo] ?? $motivo) : '') . '</li>';
	}

	$titulo = $cuenta > 0 ? '🔁 Retomar · ' . $cuenta . ($cuenta === 1 ? ' prospecto' : ' prospectos') . ' para revisar esta semana' : '🔁 Retomar · resumen de la semana';
	$intro = $cuenta > 0 ? 'Estos prospectos tienen la propuesta enviada y no han respondido. Revisa cada recordatorio y decide.' : 'Esta semana no hay recordatorios por revisar.';
	$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Retomar</title></head><body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;font-size:14px;line-height:1.45;color:#111">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fb"><tr><td align="center" style="padding:16px 8px">'
		. '<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:640px;background:#fff;border-radius:8px"><tr><td style="padding:20px;font-family:Arial,sans-serif;font-size:14px;line-height:1.45;color:#111">'
		. '<div style="font-weight:bold;font-size:16px;margin:0 0 12px">' . $h($titulo) . '</div>'
		. '<p style="margin:0 0 14px">' . $h($intro) . ' <b>Nada sale sin tu aprobación.</b></p>'
		. $cuerpo;
	if ($filas !== '') { $html .= '<div style="margin:6px 0 14px"><div style="font-weight:bold;margin:0 0 6px">Salieron de Retomar</div><ul style="margin:0;padding:0 0 0 20px;font-size:13px;color:#333">' . $filas . '</ul></div>'; }
	$html .= '<div style="font-size:12px;color:#666">Si apruebas fuera de horario, el envío sale en la siguiente ventana hábil (10:00 a 13:00).';
	if ($panel !== '') { $html .= ' <a href="' . $h($panel) . '" style="color:#1e40af">Abrir el panel Retomar</a>'; }
	return $html . '</div></td></tr></table></td></tr></table></body></html>';
}
