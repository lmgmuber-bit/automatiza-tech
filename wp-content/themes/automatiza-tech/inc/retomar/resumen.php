<?php
/**
 * Correo semanal de Retomar y aviso «recordatorio corregido». El resumen sale una vez por semana (día y hora de
 * at_rt_config(), hora de Chile) al destino de esa configuración: una tarjeta por seguimiento que toca revisar, con los
 * botones firmados de la aprobación, y la lista de quienes salieron de Retomar desde el resumen anterior. Nada sale al
 * prospecto: él solo recibe lo que Luis apruebe, y eso lo envía el reloj en su ventana.
 * D8: el correo nunca lleva easypanel ni el host del renderer. Las tarjetas no llevan <img>, todo enlace es HTTPS del sitio
 * y «Ver la presentación completa» es la página firmada «ver» (retomar-aprobar.php#presentacion, Ruling 17), que muestra
 * la presentación candidata dentro de la página: la pública del prospecto (retomar.php) sigue con la que ya recibió.
 */
if (!defined('ABSPATH')) { exit; }

/**
 * Opción leída directo de la base y no de la copia en memoria del proceso: dos relojes simultáneos deben ver lo que el
 * otro acaba de guardar. '' si no existe.
 */
function at_rt_resumen_opcion(string $nombre): string {
	global $wpdb;
	$v = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $nombre));
	return is_string($v) ? $v : '';
}

/** Guarda las opciones del resumen comparando con la base: sin vaciar la copia en memoria, update_option podría no escribir. */
function at_rt_resumen_marcar(array $opciones): void {
	foreach ($opciones as $nombre => $valor) {
		wp_cache_delete('alloptions', 'options');
		wp_cache_delete('notoptions', 'options');
		wp_cache_delete($nombre, 'options');
		update_option($nombre, (string) $valor, false);
	}
}

/** Enlace HTTPS al panel Retomar del sitio. */
function at_rt_url_panel(): string {
	return set_url_scheme(add_query_arg('page', 'at-retomar', admin_url('admin.php')), 'https');
}

/** ¿El correo armado trae easypanel o el host del renderer en alguna parte (enlace o texto)? */
function at_rt_correo_interno(string $html): bool {
	return stripos($html, 'easypanel') !== false || stripos($html, at_pt_host_renderer()) !== false;
}

/** Nombre o negocio apto para el correo: '' si trae un enlace interno (easypanel o el host del renderer). */
function at_rt_resumen_limpio($v): string {
	$v = is_string($v) ? trim(str_replace(["\r", "\n"], ' ', $v)) : '';
	return ($v === '' || at_rt_red_interno([$v])) ? '' : $v;
}

/**
 * Correo HTML a Luis; false si wp_mail falla o lanza. WordPress cambia los emojis de un correo HTML por <img> de s.w.org
 * (wp_staticize_emoji_for_email): estos correos no llevan imágenes (Ruling 1), así que se mandan con los emojis como texto.
 */
function at_rt_correo_luis(string $destino, string $asunto, string $html): bool {
	$prioridad = has_filter('wp_mail', 'wp_staticize_emoji_for_email');
	if ($prioridad !== false) { remove_filter('wp_mail', 'wp_staticize_emoji_for_email', $prioridad); }
	try { return (bool) wp_mail($destino, $asunto, $html, ['Content-Type: text/html; charset=UTF-8']); }
	catch (Throwable $e) { error_log('Retomar: wp_mail lanzó al enviar a Luis: ' . $e->getMessage()); return false; }
	finally { if ($prioridad !== false) { add_filter('wp_mail', 'wp_staticize_emoji_for_email', $prioridad); } }
}

/**
 * Días desde que se envió la propuesta vinculada (at_rt_fecha_envio_propuesta) o, sin propuesta o con una que sigue `lista`
 * (terminada y nunca enviada: la propuesta nueva que llega con el recordatorio), desde el alta del seguimiento (creado_at),
 * hasta $hoy ('Y-m-d'). 0 si no se puede saber o la fecha es futura.
 */
function at_rt_resumen_dias(array $s, string $hoy): int {
	global $wpdb;
	$desde = '';
	$pid = (int) ($s['propuesta_id'] ?? 0);
	if ($pid > 0) {
		$p = $wpdb->get_row($wpdb->prepare("SELECT id, status, created_at FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $pid), ARRAY_A);
		if ($p && ($p['status'] ?? '') !== 'lista') { $desde = at_rt_fecha_envio_propuesta($p); }
	}
	if ($desde === '') { $desde = at_pt_ymd((string) ($s['creado_at'] ?? '')); }
	// Días de calendario en UTC: un cambio de hora en Chile no resta ni suma un día.
	$utc = new DateTimeZone('UTC');
	$a = $desde !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $desde, $utc) : false;
	$b = DateTimeImmutable::createFromFormat('!Y-m-d', $hoy, $utc);
	if (!$a || !$b || $a > $b) { return 0; }
	return (int) $a->diff($b)->days;
}

/**
 * Datos comunes de la tarjeta (at_rt_tarjeta_html) de un seguimiento: nombre, negocio, días, recordatorio que toca, tope y
 * canales. WhatsApp solo figura si el envío automático está encendido y el prospecto lo autorizó con un teléfono, como en
 * la página de aprobación.
 */
function at_rt_resumen_base(array $s, string $hoy): array {
	$n = at_rt_envios_realizados($s['envios']) + 1;
	$max = (int) $s['max_envios'];
	$canales = ['correo'];
	if (at_rt_wa_automatico() && in_array('whatsapp', at_rt_canales($n, $max, (string) $s['canales'], (bool) $s['wa_autorizado'], at_pt_telefono_wa((string) $s['telefono'])), true)) { $canales[] = 'whatsapp'; }
	return ['nombre' => at_rt_resumen_limpio($s['nombre']), 'negocio' => at_rt_resumen_limpio($s['negocio']), 'dias' => at_rt_resumen_dias($s, $hoy), 'n' => $n, 'max' => $max, 'canales' => $canales];
}

/** Los cuatro textos del recordatorio que muestra la tarjeta (nunca la nota de la IA). */
function at_rt_resumen_textos(array $s): array {
	$textos = [];
	foreach (['quedamos', 'proponemos', 'partir', 'recomendacion'] as $k) { $textos[$k] = is_string($s['textos'][$k] ?? null) ? $s['textos'][$k] : ''; }
	return $textos;
}

/**
 * Qué tarjeta le toca a un seguimiento en el resumen de las $ahora («Y-m-d H:i[:s]» de Chile; «Y-m-d» cuenta como la
 * medianoche), o '' si no va:
 *  - 'propuesta'   — espera_propuesta: la propuesta no usa la plantilla AT y hay que hacerle una nueva.
 *  - ''            — no está activo o su próximo envío es futuro; tiene una condición de parada o llegó al tope (Ruling 3:
 *                    nunca se emite); o ya está aprobado con la presentación vigente (huella del render `listo`) y un correo
 *                    válido, y solo espera su envío: emitir pisaría la aprobación.
 *  - 'revision'    — algo que no se arregla solo, con un texto fijo en la tarjeta (nunca la nota cruda): el correo del
 *                    prospecto no es válido (I2: aprobado o no, el reloj no podría enviarlo); la presentación está en
 *                    `error` o lleva más de 24 h esperando (I4; sin pedido_at cuenta como vieja: nunca se pidió); la
 *                    redacción quedó en revisión, o en `error` después de al menos un reintento del reloj (reintento_at),
 *                    o no hay flujo de redacción que la termine.
 *  - 'preparacion' — aprobado con la presentación rehaciéndose («Aprobado, esperando la presentación»), o la redacción o la
 *                    presentación todavía se están haciendo (el reloj reintenta lo atascado).
 *  - 'normal'      — redacción `lista` y render `listo`: se emite la aprobación. Un aprobado con huella vieja cae aquí.
 */
function at_rt_resumen_tipo(array $s, string $ahora): string {
	$hoy = substr($ahora, 0, 10);
	$estado = (string) ($s['estado'] ?? '');
	$proximo = (string) ($s['proximo_at'] ?? '');
	if ($estado !== 'espera_propuesta' && ($estado !== 'activo' || $proximo === '' || $proximo > $hoy)) { return ''; }
	$ap = is_array($s['aprobacion'] ?? null) ? $s['aprobacion'] : [];
	$render = is_array($s['render'] ?? null) ? $s['render'] : [];
	$huella = is_string($render['huella'] ?? null) ? $render['huella'] : '';
	$listo = ($render['estado'] ?? '') === 'listo' && $huella !== '';
	$aprobado = $estado === 'activo' && ($ap['estado'] ?? '') === 'aprobado';
	$correo = is_email((string) ($s['email'] ?? ''));
	if ($aprobado && $listo && $correo && is_string($ap['huella'] ?? null) && hash_equals($huella, $ap['huella'])) { return ''; }
	if (at_rt_motivo_parada(at_rt_contexto_parada($s)) !== '') { return ''; }
	if ($estado === 'espera_propuesta') { return 'propuesta'; }
	if (!$correo || ($render['estado'] ?? '') === 'error') { return 'revision'; }
	$redaccion = is_array($s['redaccion'] ?? null) ? $s['redaccion'] : [];
	$red_estado = (string) ($redaccion['estado'] ?? '');
	$esperando = !$listo && ($aprobado || $red_estado === 'lista') && at_rt_render_esperando($render, $ahora, 24);
	if ($aprobado && !$listo) { return $esperando ? 'revision' : 'preparacion'; }
	if ($red_estado === 'revision' || ($red_estado === 'error' && is_string($redaccion['reintento_at'] ?? null) && $redaccion['reintento_at'] !== '')) { return 'revision'; }
	if ($red_estado !== 'lista') { return at_rt_red_flujo() === '' ? 'revision' : 'preparacion'; }
	if ($listo) { return 'normal'; }
	return $esperando ? 'revision' : 'preparacion';
}

/**
 * Tarjeta del resumen para el seguimiento $s a la hora $ahora, o null si no va. Si toca aprobar, emite la aprobación con
 * esa hora (rota el nonce: los botones de un correo anterior responden «cambio») y la tarjeta lleva los textos y los cuatro
 * enlaces; «Ver la presentación completa» es el enlace «ver» con el ancla #presentacion (Ruling 17). Unos textos con un
 * enlace interno no se ofrecen para aprobar (D8). Si la emisión no se pudo, la tarjeta sale con el tipo que tiene ahora
 * el seguimiento: ninguna si, entretanto, Luis ya lo aprobó (la emisión nunca pisa un aprobado vigente); «En preparación»
 * o «Necesita revisión» si la emisión encontró la presentación desactualizada (el contenido cambió por fuera de Retomar:
 * quedó `pendiente` y se pidió de nuevo, o en `error`); cualquier otra emisión fallida va como «Necesita revisión».
 * $cupo: pedidos a n8n que le quedan a la pasada del reloj (null fuera del reloj), lo usa la emisión.
 */
function at_rt_resumen_tarjeta(array $s, string $ahora, ?int &$cupo = null): ?array {
	$hoy = substr($ahora, 0, 10);
	$tipo = at_rt_resumen_tipo($s, $ahora);
	if ($tipo === '') { return null; }
	$textos = at_rt_resumen_textos($s);
	if ($tipo === 'normal' && at_rt_red_interno(array_merge([(string) $s['nombre'], (string) $s['negocio']], array_values($textos)))) { $tipo = 'revision'; }
	$t = at_rt_resumen_base($s, $hoy) + ['tipo' => $tipo];
	if ($tipo !== 'normal') { return $t; }
	$enlaces = at_rt_emitir_aprobacion((int) $s['id'], $ahora, $cupo);
	if ($enlaces === null) {
		$actual = at_rt_obtener((int) $s['id']);
		$ahora_tipo = $actual ? at_rt_resumen_tipo($actual, $ahora) : 'revision';
		if ($ahora_tipo === '') { return null; }
		$t['tipo'] = $ahora_tipo === 'normal' ? 'revision' : $ahora_tipo;
		return $t;
	}
	return $t + ['textos' => $textos, 'url_presentacion' => $enlaces['ver'] . '#presentacion', 'enlaces' => $enlaces];
}

/** «Salieron de Retomar»: seguimientos de $lista en un estado de cierre cuyo cambio es posterior a $desde. */
function at_rt_resumen_salidas(array $lista, string $desde): array {
	$salieron = [];
	foreach ($lista as $s) {
		if (!in_array($s['estado'] ?? '', ['agotado', 'agendo', 'cliente', 'baja', 'rechazado'], true) || (string) ($s['actualizado_at'] ?? '') <= $desde) { continue; }
		$salieron[] = ['nombre' => at_rt_resumen_limpio($s['nombre'] ?? ''), 'negocio' => at_rt_resumen_limpio($s['negocio'] ?? ''), 'motivo' => (string) $s['estado']];
	}
	return $salieron;
}

/**
 * Correo semanal a Luis a la hora de Chile $ahora («Y-m-d H:i[:s]»). Sale si at_rt_toca_resumen() lo pide (o con
 * $forzar, «Enviarme el resumen ahora», aunque ya haya salido esta semana). Un candado de MySQL y la semana leída de la base
 * bajo ese candado evitan dos resúmenes la misma semana aunque el reloj corra varias veces. Tarjetas: seguimientos de
 * at_rt_lista() según at_rt_resumen_tipo(), primero los que hay que aprobar. «Salieron de Retomar»: cierres posteriores a
 * at_rt_resumen_desde (sin ese dato, los de los últimos 7 días).
 *  - Sin tarjetas ni salidas: no envía y marca la semana (motivo 'vacio').
 *  - wp_mail falla: no marca la semana, así la pasada siguiente lo reintenta (motivo 'correo_error').
 *  - Enviado: at_rt_resumen_semana = semana ISO de $ahora y at_rt_resumen_desde = $ahora (motivo 'enviado').
 * Otros motivos: 'no_toca', 'ocupado' (otro resumen en curso), 'fecha', 'esquema', 'destino' (correo inválido) e
 * 'interno' (D8: nunca debería pasar; no se envía ni se marca).
 * $cupo: lo que le queda a la pasada del reloj de su tope de pedidos a n8n (at_rt_tick lo pasa y lo ve gastado); null fuera
 * del reloj («Enviarme el resumen ahora»): sin tope.
 * Devuelve {enviado, tarjetas, motivo}.
 */
function at_rt_resumen(string $ahora, bool $forzar = false, ?int &$cupo = null): array {
	$r = ['enviado' => false, 'tarjetas' => 0, 'motivo' => ''];
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { $r['motivo'] = 'fecha'; return $r; }
	if (!at_rt_esquema_listo()) { $r['motivo'] = 'esquema'; return $r; }
	$ahora = $fecha->format('Y-m-d H:i:s');
	$cfg = at_rt_config();
	$toca = static fn(): bool => $forzar || at_rt_toca_resumen($ahora, $cfg['dia'], $cfg['hora'], at_rt_resumen_opcion('at_rt_resumen_semana'));
	if (!$toca()) { $r['motivo'] = 'no_toca'; return $r; }
	global $wpdb;
	$candado = $wpdb->prefix . 'at_rt_resumen';
	$tomado = (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $candado));
	if ($tomado === '0') { $r['motivo'] = 'ocupado'; return $r; }
	try {
		// Bajo el candado se vuelve a mirar la base: otro reloj pudo mandarlo mientras tanto.
		if (!$toca()) { $r['motivo'] = 'no_toca'; return $r; }
		$desde = at_rt_resumen_opcion('at_rt_resumen_desde');
		if (at_rt_ahora($desde) === null) { $desde = $fecha->modify('-7 days')->format('Y-m-d H:i:s'); }
		$lista = at_rt_lista();
		$tarjetas = [];
		foreach ($lista as $s) {
			try { $t = at_rt_resumen_tarjeta($s, $ahora, $cupo); }
			catch (Throwable $e) { error_log('Retomar: no se pudo armar la tarjeta #' . (int) $s['id'] . ' del resumen: ' . $e->getMessage()); $t = null; }
			if ($t !== null) { $tarjetas[] = $t; }
		}
		$salieron = at_rt_resumen_salidas($lista, $desde);
		$r['tarjetas'] = count($tarjetas);
		if (!$tarjetas && !$salieron) {
			at_rt_resumen_marcar(['at_rt_resumen_semana' => at_rt_semana($ahora)]);
			$r['motivo'] = 'vacio';
			return $r;
		}
		if (!is_email($cfg['destino'])) { error_log('Retomar: el destino del resumen semanal no es un correo válido.'); $r['motivo'] = 'destino'; return $r; }
		// Lo que hay que decidir va primero (usort es estable: dentro de cada tipo se conserva el orden de la lista).
		$orden = ['normal' => 0, 'revision' => 1, 'propuesta' => 2, 'preparacion' => 3];
		usort($tarjetas, static fn(array $a, array $b): int => ($orden[$a['tipo']] ?? 9) <=> ($orden[$b['tipo']] ?? 9));
		$html = at_rt_resumen_html($tarjetas, $salieron, at_rt_url_panel());
		if (at_rt_correo_interno($html)) { error_log('Retomar: el resumen semanal traía un enlace interno y no se envió.'); $r['motivo'] = 'interno'; return $r; }
		$n = count($tarjetas);
		$asunto = $n > 0 ? '🔁 Retomar · ' . $n . ($n === 1 ? ' prospecto' : ' prospectos') . ' para revisar esta semana' : '🔁 Retomar · resumen de la semana';
		if (!at_rt_correo_luis($cfg['destino'], $asunto, $html)) {
			error_log('Retomar: no se pudo enviar el resumen semanal; se reintenta en la pasada siguiente.');
			$r['motivo'] = 'correo_error';
			return $r;
		}
		at_rt_resumen_marcar(['at_rt_resumen_semana' => at_rt_semana($ahora), 'at_rt_resumen_desde' => $ahora]);
		return ['enviado' => true, 'tarjetas' => $n, 'motivo' => 'enviado'];
	} finally {
		if ($tomado === '1') { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $candado)); }
	}
}

/** Correo corto «recordatorio corregido» (HTML de correo, estilos en línea, sin <img>) con la tarjeta $t. */
function at_rt_aviso_html(array $t): string {
	$h = fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$panel = at_rt_url_correo(trim((string) ($t['url_panel'] ?? '')));
	$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Retomar</title></head><body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;font-size:14px;line-height:1.45;color:#111">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fb"><tr><td align="center" style="padding:16px 8px">'
		. '<table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:640px;background:#fff;border-radius:8px"><tr><td style="padding:20px;font-family:Arial,sans-serif;font-size:14px;line-height:1.45;color:#111">'
		. '<div style="font-weight:bold;font-size:16px;margin:0 0 12px">' . $h('🔁 Retomar · Recordatorio corregido') . '</div>'
		. '<p style="margin:0 0 14px">Rehicimos el recordatorio y su presentación: esta es la versión nueva para que la revises. Los botones de correos anteriores ya no sirven. <b>Nada sale sin tu aprobación.</b></p>'
		. at_rt_tarjeta_html($t)
		. '<div style="font-size:12px;color:#666">Si apruebas fuera de horario, el envío sale en la siguiente ventana hábil (10:00 a 13:00).';
	if ($panel !== '') { $html .= ' <a href="' . $h($panel) . '" style="color:#1e40af">Abrir el panel Retomar</a>'; }
	return $html . '</div></td></tr></table></td></tr></table></body></html>';
}

/**
 * Aviso «recordatorio corregido» del seguimiento $id: lo llama el callback del render cuando la presentación nueva queda
 * lista después de «Pedir cambios» o de una aprobación vencida por contenido (render.php, Ruling 11). Emite una aprobación
 * nueva (`por_aprobar`, que saca el estado de `cambios`) y manda a Luis un correo corto con esa tarjeta. Nunca emite si el
 * seguimiento no está activo, tiene una condición de parada o llegó al tope (Ruling 3), ni si sus textos traen un enlace
 * interno (D8). false si no corresponde o si wp_mail falla (queda en error_log).
 */
function at_rt_aviso_listo(int $id): bool {
	$ahora = at_rt_ahora_chile();
	$s = at_rt_obtener($id);
	if (!$s || $s['estado'] !== 'activo' || at_rt_motivo_parada(at_rt_contexto_parada($s)) !== '') { return false; }
	$textos = at_rt_resumen_textos($s);
	if (at_rt_red_interno(array_merge([(string) $s['nombre'], (string) $s['negocio']], array_values($textos)))) {
		error_log('Retomar: el recordatorio corregido #' . $id . ' trae un enlace interno; no se avisa.');
		return false;
	}
	$destino = at_rt_config()['destino'];
	if (!is_email($destino)) { error_log('Retomar: el destino del aviso no es un correo válido.'); return false; }
	$enlaces = at_rt_emitir_aprobacion($id, $ahora);
	if ($enlaces === null) { return false; }
	$t = at_rt_resumen_base($s, substr($ahora, 0, 10)) + ['tipo' => 'normal', 'textos' => $textos, 'url_presentacion' => $enlaces['ver'] . '#presentacion', 'enlaces' => $enlaces, 'url_panel' => at_rt_url_panel()];
	$html = at_rt_aviso_html($t);
	if (at_rt_correo_interno($html)) { error_log('Retomar: el aviso del recordatorio corregido #' . $id . ' traía un enlace interno y no se envió.'); return false; }
	$quien = $t['nombre'] !== '' ? $t['nombre'] : ($t['negocio'] !== '' ? $t['negocio'] : 'prospecto sin nombre');
	if (!at_rt_correo_luis($destino, '🔁 Retomar · Recordatorio corregido: ' . $quien, $html)) {
		error_log('Retomar: no se pudo enviar el aviso del recordatorio corregido #' . $id . '.');
		return false;
	}
	return true;
}
