<?php
/**
 * Aprobación de Retomar por enlace firmado. Cada recordatorio listo recibe una aprobación con nonce propio y cuatro
 * enlaces (aprobar, cambios, omitir, ver) firmados con wp_salt('auth') que vencen a los 7 días. Los antivirus del correo
 * abren todos los enlaces (GET y HEAD): esos métodos solo muestran una confirmación y nunca cambian nada; únicamente el
 * POST de esa página, con el nonce de WordPress at_rt_aprobar_<id>, ejecuta. Emitir de nuevo rota el nonce: los enlaces
 * anteriores responden «cambio». «ver» es de solo lectura y no es de un solo uso.
 */
if (!defined('ABSPATH')) { exit; }

/** Vida de un enlace de aprobación: 7 días. */
function at_rt_aprobacion_vida(): int { return 604800; }
function at_rt_aprobacion_acciones(): array { return ['aprobar', 'cambios', 'omitir', 'ver']; }

/** Enlace firmado de una acción: https://<sitio>/retomar-aprobar.php?id=&a=&k=<nonce>&v=<vence>&f=<firma>. */
function at_rt_aprobacion_url(int $id, string $accion, string $nonce, int $vence): string {
	$args = ['id' => (string) $id, 'a' => $accion, 'k' => $nonce, 'v' => (string) $vence, 'f' => at_rt_firma($id, $accion, $nonce, $vence, wp_salt('auth'))];
	return add_query_arg(array_map('rawurlencode', $args), set_url_scheme(home_url('/retomar-aprobar.php'), 'https'));
}

/**
 * Emite la aprobación del recordatorio que toca, solo si la redacción está `lista` y el render `listo` con huella (sin
 * huella, un render rehecho no podría invalidar el enlace). Guarda {estado:'por_aprobar', n, nonce, huella, emitido_at,
 * vence, usada:false} bajo FOR UPDATE y devuelve los cuatro enlaces; null si no corresponde. Emitir otra vez rota el nonce.
 */
function at_rt_emitir_aprobacion(int $id, string $ahora): ?array {
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null || $id <= 0 || wp_salt('auth') === '') { return null; }
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return null; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return null; }
		$s = at_rt_fila($fila);
		$huella = $s['render']['huella'] ?? '';
		if (($s['redaccion']['estado'] ?? '') !== 'lista' || ($s['render']['estado'] ?? '') !== 'listo' || !is_string($huella) || $huella === '') { return null; }
		$aprobacion = [
			'estado' => 'por_aprobar',
			'n' => at_rt_envios_realizados($s['envios']) + 1,
			'nonce' => bin2hex(random_bytes(8)),
			'huella' => $huella,
			'emitido_at' => $fecha->format('Y-m-d H:i:s'),
			'vence' => $fecha->getTimestamp() + at_rt_aprobacion_vida(),
			'usada' => false,
		];
		if (!at_rt_guardar($id, ['aprobacion' => $aprobacion]) || $wpdb->query('COMMIT') === false) { return null; }
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno o error.
	}
	$enlaces = [];
	foreach (at_rt_aprobacion_acciones() as $accion) { $enlaces[$accion] = at_rt_aprobacion_url($id, $accion, $aprobacion['nonce'], $aprobacion['vence']); }
	return $enlaces;
}

/** id, k (nonce), v (vence) y f (firma) del enlace si tienen la forma exacta; null si no (se responde «firma»). */
function at_rt_aprobacion_params(array $q): ?array {
	$p = [];
	foreach (['id' => '/^[1-9]\d{0,18}$/D', 'k' => '/^[a-f0-9]{16}$/D', 'v' => '/^[1-9]\d{0,18}$/D', 'f' => '/^[a-f0-9]{32}$/D'] as $k => $forma) {
		$valor = $q[$k] ?? null;
		if (is_int($valor)) { $valor = (string) $valor; }
		if (!is_string($valor) || preg_match($forma, $valor) !== 1) { return null; }
		$p[$k] = $valor;
	}
	return ['id' => (int) $p['id'], 'k' => $p['k'], 'v' => (int) $p['v'], 'f' => $p['f']];
}

/**
 * Motivo por el que el enlace $q no sirve para $accion sobre el seguimiento $s ([] si no existe), o '' si sirve. $ts es la
 * hora Unix de la decisión. Orden, para no revelar nada a quien no tiene un enlace auténtico:
 *  1. firma         — parámetros mal formados o firma que no calza (no consulta el estado: igual para un id inexistente);
 *  2. vencido       — $ts > vence (vence va firmado: solo lo sabe quien tiene el enlace real);
 *  3. no_disponible — no hay seguimiento con ese id o no tiene aprobación emitida;
 *  4. cambio        — nonce distinto del vigente (se emitió otra), vence distinto, o huella de la aprobación distinta de la
 *                     del render actual (o render sin huella: se guardó contenido nuevo);
 *  5. usado         — la aprobación ya se usó (aprobar, cambios u omitir: un solo uso entre los tres);
 *  6. no_disponible — la aprobación no está `por_aprobar` aunque no figure usada.
 * «ver» se valida hasta el paso 4: es de solo lectura y no es de un solo uso.
 */
function at_rt_aprobacion_motivo(array $s, string $accion, array $q, int $ts): string {
	$p = at_rt_aprobacion_params($q);
	if ($p === null || !at_rt_firma_valida($p['f'], $p['id'], $accion, $p['k'], $p['v'], wp_salt('auth'))) { return 'firma'; }
	if ($ts > $p['v']) { return 'vencido'; }
	$ap = is_array($s['aprobacion'] ?? null) ? $s['aprobacion'] : [];
	$nonce = is_string($ap['nonce'] ?? null) ? $ap['nonce'] : '';
	if ((int) ($s['id'] ?? 0) !== $p['id'] || $nonce === '') { return 'no_disponible'; }
	$huella = is_string($ap['huella'] ?? null) ? $ap['huella'] : '';
	$vigente = is_array($s['render'] ?? null) && is_string($s['render']['huella'] ?? null) ? $s['render']['huella'] : '';
	if (!hash_equals($nonce, $p['k']) || (int) ($ap['vence'] ?? 0) !== $p['v'] || $huella === '' || $vigente === '' || !hash_equals($vigente, $huella)) { return 'cambio'; }
	if ($accion === 'ver') { return ''; }
	if (!empty($ap['usada'])) { return 'usado'; }
	return ($ap['estado'] ?? '') === 'por_aprobar' ? '' : 'no_disponible';
}

/**
 * Por qué no se puede aprobar ni pedir cambios aunque el enlace valga: una condición de parada de la fase 1 (baja, agendo,
 * cliente, aceptada, rechazada, agotado) o 'inactivo' si se intenta aprobar un seguimiento que no está `activo` (el reloj
 * solo envía activos: aprobarlo diría una fecha falsa). '' si se puede. «Esta semana no» y «ver» nunca se bloquean.
 */
function at_rt_aprobacion_bloqueo(array $s, string $accion): string {
	if (!in_array($accion, ['aprobar', 'cambios'], true)) { return ''; }
	$parada = at_rt_motivo_parada(at_rt_contexto_parada($s));
	if ($parada !== '') { return $parada; }
	return $accion === 'aprobar' && ($s['estado'] ?? '') !== 'activo' ? 'inactivo' : '';
}

function at_rt_aprobacion_mensaje(string $motivo): string {
	return [
		'firma' => 'Este enlace no es válido. Usa los botones del último correo de Retomar.',
		'vencido' => 'Este enlace venció: los botones duran 7 días. Usa los del último correo de Retomar.',
		'no_disponible' => 'Este recordatorio ya no se puede decidir desde este enlace.',
		'cambio' => 'Este recordatorio cambió: revisa la versión nueva en el último correo de Retomar.',
		'usado' => 'Este enlace ya se usó: el recordatorio ya tiene una decisión.',
		'pedido' => 'Escribe qué cambiamos, de 1 a 1000 caracteres.',
		'baja' => 'Este prospecto pidió no recibir más mensajes: no le enviaremos nada.',
		'agendo' => 'Este prospecto ya agendó una reunión: el recordatorio no hace falta.',
		'cliente' => 'Este contacto ya es cliente: no le enviaremos el recordatorio.',
		'aceptada' => 'La propuesta ya fue aceptada: no le enviaremos el recordatorio.',
		'rechazada' => 'La propuesta fue rechazada: no le enviaremos el recordatorio.',
		'agotado' => 'Ya recibió todos sus recordatorios: no sale ninguno más.',
		'inactivo' => 'Este seguimiento no está activo (en pausa o detenido): revísalo en el panel Retomar.',
		'error' => 'No pudimos guardar tu decisión. Inténtalo de nuevo en unos minutos.',
	][$motivo] ?? 'No pudimos completar la acción.';
}
function at_rt_aprobacion_http(string $motivo): int {
	return ['firma' => 403, 'vencido' => 410, 'no_disponible' => 404, 'pedido' => 422, 'error' => 500][$motivo] ?? 409;
}

/** «lunes 26 de octubre de 2026». */
function at_rt_dia_largo(string $ymd): string {
	$fecha = at_rt_fecha($ymd);
	if ($fecha === null) { return $ymd; }
	return ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'][(int) $fecha->format('N') - 1] . ' ' . at_pt_fecha_larga($ymd);
}

/**
 * Ejecuta la decisión de un enlace (solo la llama el POST de la página). Todo bajo START TRANSACTION + FOR UPDATE: un
 * doble clic o dos dispositivos se serializan y el segundo recibe «usado».
 *  - aprobar: aprobacion.estado = 'aprobado', usada, programado = proximo_at = at_rt_programar($ahora); antes revisa las
 *    paradas y que el seguimiento esté activo. No envía nada: el reloj envía lo aprobado en su ventana.
 *  - cambios: pedido de 1 a 1000 caracteres; aprobacion.estado = 'cambios', usada, el pedido se agrega a las notas (que se
 *    recortan a sus últimos 4000 caracteres: MySQL sin modo estricto truncaría en silencio) y, después del COMMIT y fuera
 *    del candado, se pide la redacción a la IA con ese pedido.
 *  - omitir: aprobacion.estado = 'omitido', usada; historial «🔁 Esta semana no»; proximo_at no se toca.
 * Devuelve {ok, motivo ('' si ok), mensaje, programado? (solo aprobar)}.
 */
function at_rt_resolver_aprobacion(int $id, string $accion, array $q, string $ahora, string $pedido = ''): array {
	$fallo = static fn(string $motivo): array => ['ok' => false, 'motivo' => $motivo, 'mensaje' => at_rt_aprobacion_mensaje($motivo)];
	if (!in_array($accion, ['aprobar', 'cambios', 'omitir'], true)) { return $fallo(in_array($accion, at_rt_aprobacion_acciones(), true) ? 'no_disponible' : 'firma'); }
	$fecha = at_rt_ahora($ahora);
	if ($fecha === null) { return $fallo('error'); }
	$cuando = $fecha->format('Y-m-d H:i:s');
	$texto = '';
	$programado = '';
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return $fallo('error'); }
	try {
		$fila = $id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A) : null;
		$s = $fila ? at_rt_fila($fila) : [];
		$motivo = at_rt_aprobacion_motivo($s, $accion, $q, $fecha->getTimestamp());
		if ($motivo === '') { $motivo = at_rt_aprobacion_bloqueo($s, $accion); }
		if ($motivo === '' && $accion === 'cambios') {
			$crudo = trim($pedido);
			$texto = sanitize_textarea_field($crudo);
			if ($crudo === '' || mb_strlen($crudo) > 1000 || $texto === '') { $motivo = 'pedido'; }
		}
		if ($motivo !== '') { return $fallo($motivo); }
		$ap = $s['aprobacion'];
		$ap['usada'] = true;
		$ap['resuelto_at'] = $cuando;
		$campos = [];
		if ($accion === 'aprobar') {
			$programado = at_rt_programar($cuando);
			$ap['estado'] = 'aprobado';
			$ap['programado'] = $programado;
			$campos['proximo_at'] = $programado;
		} elseif ($accion === 'cambios') {
			$ap['estado'] = 'cambios';
			$linea = '[' . $fecha->format('Y-m-d H:i') . '] Cambios pedidos desde el correo: ' . $texto;
			$notas = trim($s['notas']) === '' ? $linea : rtrim($s['notas']) . "\n\n" . $linea;
			$campos['notas'] = mb_strlen($notas) > 4000 ? mb_substr($notas, -4000) : $notas;
		} else {
			$ap['estado'] = 'omitido';
		}
		$campos['aprobacion'] = $ap;
		if (!at_rt_guardar($id, $campos) || $wpdb->query('COMMIT') === false) { return $fallo('error'); }
	} finally {
		$wpdb->query('ROLLBACK'); // Sin efecto después de COMMIT; libera el candado ante cualquier retorno o error.
	}
	if ($accion === 'aprobar') {
		return ['ok' => true, 'motivo' => '', 'mensaje' => 'Aprobado. Sale el ' . at_rt_dia_largo($programado) . ' entre 10:00 y 13:00.', 'programado' => $programado];
	}
	if ($accion === 'omitir') {
		at_rt_historia($s, 'retomar_omitido', '🔁 Esta semana no');
		return ['ok' => true, 'motivo' => '', 'mensaje' => 'Listo: esta semana no le enviamos nada. Vuelve a aparecer en el próximo correo semanal.'];
	}
	// La redacción llama a n8n por HTTP: va después del COMMIT y fuera del candado.
	$error = at_rt_pedir_redaccion($id, $texto);
	if ($error === 'sin_flujo') {
		at_rt_aprobacion_revision($id, $texto);
		return ['ok' => true, 'motivo' => '', 'mensaje' => 'Anotamos tu pedido en las notas, pero la redacción con IA no está configurada: corrige los textos en el panel Retomar.'];
	}
	if ($error !== '') {
		return ['ok' => true, 'motivo' => '', 'mensaje' => 'Anotamos tu pedido en las notas, pero no pudimos pedirle la redacción a la IA ahora. Revísalo en el panel Retomar.'];
	}
	return ['ok' => true, 'motivo' => '', 'mensaje' => 'Listo, pedimos los cambios. Rehacemos el recordatorio y la presentación con tu pedido y te avisamos por correo cuando la versión nueva esté lista para aprobar.'];
}

/**
 * Sin flujo de redacción configurado, un «Pedir cambios» no puede rehacer los textos: la redacción pasa a «revision» para
 * que la tarjeta semanal diga «Necesita revisión» en vez de volver a ofrecer el mismo texto para aprobar. Solo si sigue
 * `lista` (nadie la movió entretanto).
 */
function at_rt_aprobacion_revision(int $id, string $pedido): void {
	global $wpdb;
	if ($wpdb->query('START TRANSACTION') === false) { return; }
	try {
		$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}at_retomar WHERE id=%d FOR UPDATE", $id), ARRAY_A);
		if (!$fila) { return; }
		$s = at_rt_fila($fila);
		if (($s['redaccion']['estado'] ?? '') !== 'lista') { return; }
		$redaccion = array_merge($s['redaccion'], ['estado' => 'revision', 'pedido' => at_rt_red_pedido($pedido), 'nota' => 'Pediste cambios desde el correo, pero la redacción con IA no está configurada: corrige los textos en el panel.']);
		if (at_rt_guardar($id, ['redaccion' => $redaccion])) { $wpdb->query('COMMIT'); }
	} finally {
		$wpdb->query('ROLLBACK');
	}
}

/**
 * Página de los botones del correo semanal (retomar-aprobar.php). GET, HEAD y cualquier método que no sea POST solo
 * muestran: la confirmación de aprobar, cambios u omitir (con un formulario POST y el nonce de WordPress
 * at_rt_aprobar_<id>), o, con a=ver, el correo tal como le llega al prospecto. Solo el POST de aprobar, cambios u omitir
 * ejecuta (at_rt_resolver_aprobacion). La página de «ver» no trae enlaces para decidir: quien solo tiene «ver» no
 * obtiene los otros. Todo dato del prospecto sale escapado.
 */
function at_rt_pagina_aprobacion(array $get, string $metodo, array $post): array {
	$cabeceras = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer', 'Content-Type' => 'text/html; charset=UTF-8', 'X-Frame-Options' => 'DENY', 'Content-Security-Policy' => "frame-ancestors 'none'"];
	$h = static fn($v): string => esc_html((string) $v);
	$pagina = static function (int $http, string $contenido) use ($cabeceras): array {
		$html = '<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Retomar · AutomatizaTech</title>'
			. '<style>body{margin:0;background:#f1f5f9;color:#142c46;font:17px/1.6 system-ui,sans-serif}main{max-width:760px;margin:32px auto;padding:24px;background:#fff;border-radius:16px}h1{line-height:1.25;font-size:26px}a{color:#076b73}button,.boton{display:inline-block;background:#087b80;color:#fff;border:0;border-radius:8px;padding:12px 20px;font:inherit;cursor:pointer;text-decoration:none}label{display:block;margin:16px 0 6px;font-weight:600}textarea{display:block;width:100%;box-sizing:border-box;font:inherit;padding:10px;border:1px solid #cbd5e1;border-radius:8px}.aviso{background:#fef3c7;color:#92400e;border-radius:8px;padding:10px 14px}.nota{color:#475569;font-size:15px}iframe{width:100%;height:70vh;border:1px solid #d6dde6;border-radius:8px;background:#fff}@media(max-width:600px){main{margin:0;border-radius:0;padding:18px}h1{font-size:23px}}</style>'
			. '</head><body><main>' . $contenido . '</main></body></html>';
		return ['http' => $http, 'html' => $html, 'cabeceras' => $cabeceras];
	};
	$error = static fn(string $motivo, string $titulo = 'No se pudo completar', string $extra = ''): array => $pagina(at_rt_aprobacion_http($motivo), '<h1>' . esc_html($titulo) . '</h1><p role="status">' . esc_html(at_rt_aprobacion_mensaje($motivo) . $extra) . '</p>');

	$accion = is_string($get['a'] ?? null) && in_array($get['a'], at_rt_aprobacion_acciones(), true) ? $get['a'] : '';
	$p = at_rt_aprobacion_params($get);
	$s = $p !== null ? at_rt_obtener($p['id']) : null;
	$motivo = at_rt_aprobacion_motivo($s ?? [], $accion, $get, time());
	if ($motivo !== '') {
		$titulos = ['cambio' => 'Este recordatorio cambió', 'usado' => 'Este enlace ya se usó', 'vencido' => 'Este enlace venció', 'firma' => 'Enlace no válido'];
		$extra = '';
		if ($motivo === 'usado' && ($s['aprobacion']['estado'] ?? '') === 'aprobado' && is_string($s['aprobacion']['programado'] ?? null)) {
			$extra = ' Ya está aprobado: sale el ' . at_rt_dia_largo($s['aprobacion']['programado']) . ' entre 10:00 y 13:00.';
		}
		return $error($motivo, $titulos[$motivo] ?? 'No se pudo completar', $extra);
	}
	$id = (int) $s['id'];
	$ap = $s['aprobacion'];
	$quien = $s['nombre'] !== '' ? $s['nombre'] : ($s['negocio'] !== '' ? $s['negocio'] : 'el prospecto');
	$quien_html = $h($quien) . ($s['nombre'] !== '' && $s['negocio'] !== '' ? ' (' . $h($s['negocio']) . ')' : '');
	$n = max(1, (int) ($ap['n'] ?? 1));
	$recordatorio = 'recordatorio ' . $n . ' de ' . (int) $s['max_envios'];

	if ($accion === 'ver') {
		$datos = at_rt_datos_correo($s);
		$correo = at_rt_correo_html($datos);
		// srcdoc con doble escapado (htmlspecialchars, no esc_attr, que no re-escapa entidades) y sandbox sin permisos: dentro
		// no corren scripts ni se envían formularios (tampoco el de la baja si se sigue un enlace del correo).
		return $pagina(200, '<h1>Así le llega el correo a ' . $h($quien) . '</h1>'
			. '<p class="nota">Para: ' . $h($s['email']) . '<br>Asunto: ' . $h(at_rt_asunto($n, $s['nombre'])) . '<br>Es el ' . $h($recordatorio) . '. Esta vista no envía nada.</p>'
			. '<iframe title="Correo al prospecto" sandbox="" srcdoc="' . htmlspecialchars($correo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></iframe>'
			. '<p class="nota">«Ver resumen y demo» lleva a <a href="' . esc_url($datos['url_ver']) . '" target="_blank" rel="noopener">la página del prospecto</a>, con la presentación, la agenda y la baja.</p>');
	}

	$bloqueo = at_rt_aprobacion_bloqueo($s, $accion);
	if ($bloqueo !== '') { return $error($bloqueo, 'No se puede ' . ($accion === 'aprobar' ? 'aprobar' : 'pedir cambios')); }

	$ahora = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y-m-d H:i:s');
	$tel = at_pt_telefono_wa((string) $s['telefono']);
	$http = 200;
	$aviso = '';
	$previo = '';
	if (strtoupper($metodo) === 'POST') {
		$nonce = is_string($post['_wpnonce'] ?? null) ? $post['_wpnonce'] : '';
		if (!wp_verify_nonce($nonce, 'at_rt_aprobar_' . $id)) {
			return $pagina(403, '<h1>Vuelve a confirmar</h1><p role="status">La confirmación venció. <a href="' . esc_url(at_rt_aprobacion_url($id, $accion, $p['k'], $p['v'])) . '">Abre de nuevo el botón</a> y confirma.</p>');
		}
		$res = at_rt_resolver_aprobacion($id, $accion, $get, $ahora, is_string($post['pedido'] ?? null) ? $post['pedido'] : '');
		if ($res['ok']) {
			if ($accion === 'aprobar') {
				$s = at_rt_obtener($id) ?? $s;
				$wa = at_rt_wa_automatico() && in_array('whatsapp', at_rt_canales($n, (int) $s['max_envios'], (string) $s['canales'], (bool) $s['wa_autorizado'], $tel), true);
				$contenido = '<h1>✅ Recordatorio aprobado</h1><p role="status">Sale el <strong>' . $h(at_rt_dia_largo($res['programado'])) . ' entre 10:00 y 13:00</strong> ' . ($wa ? 'por correo y WhatsApp' : 'por correo') . ' a ' . $quien_html . '.</p>';
				if (!at_rt_wa_automatico() && !empty($s['wa_autorizado']) && $tel !== '') {
					$texto = at_rt_panel_texto_manual($s);
					if (!at_rt_internas(['texto' => $texto])) {
						$contenido .= '<p class="nota">El WhatsApp automático está en pausa. Si quieres avisarle también por WhatsApp, compártelo a mano desde tu teléfono:</p><p><a class="boton" href="' . esc_url(at_rt_url_wa_manual($tel, $texto)) . '" target="_blank" rel="noopener">💬 Compartir a mano</a></p>';
					}
				}
				return $pagina(200, $contenido);
			}
			return $pagina(200, '<h1>' . ($accion === 'cambios' ? '✏️ Cambios pedidos' : '⏭️ Esta semana no') . '</h1><p role="status">' . $h($res['mensaje']) . '</p>');
		}
		if ($res['motivo'] !== 'pedido') {
			return $error($res['motivo']);
		}
		$http = at_rt_aprobacion_http('pedido');
		$previo = is_string($post['pedido'] ?? null) ? $post['pedido'] : ''; // no perder lo escrito
		$aviso = '<p class="aviso" role="alert">' . $h($res['mensaje']) . '</p>';
	}

	$formulario = static fn(string $campos, string $boton): string => '<form method="post"><input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('at_rt_aprobar_' . $id)) . '">' . $campos . '<p><button type="submit">' . $boton . '</button></p></form>';
	$ver = '<p class="nota"><a href="' . esc_url(at_rt_aprobacion_url($id, 'ver', $p['k'], $p['v'])) . '">Ver el correo tal como le llega</a></p>';
	if ($accion === 'aprobar') {
		$wa = at_rt_wa_automatico() && in_array('whatsapp', at_rt_canales($n, (int) $s['max_envios'], (string) $s['canales'], (bool) $s['wa_autorizado'], $tel), true);
		return $pagina($http, '<h1>¿Enviar el recordatorio?</h1><p>¿Enviar el ' . $h($recordatorio) . ' a ' . $quien_html . ', al correo ' . $h($s['email']) . '?</p>'
			. '<p class="nota">Si lo apruebas ahora, sale el ' . $h(at_rt_dia_largo(at_rt_programar($ahora))) . ' entre 10:00 y 13:00 ' . ($wa ? 'por correo y WhatsApp' : 'por correo') . '. Justo antes de enviarlo revisamos que no se haya dado de baja ni agendado.</p>'
			. $aviso . $ver . $formulario('', '✅ Aprobar y enviar'));
	}
	if ($accion === 'cambios') {
		return $pagina($http, '<h1>Pedir cambios</h1><p>' . ucfirst($h($recordatorio)) . ' para ' . $quien_html . '. Rehacemos el recordatorio y la presentación con tu pedido y te avisamos por correo cuando la versión nueva esté lista para aprobar.</p>'
			. $aviso . $ver . $formulario('<label for="rt-pedido">¿Qué cambiamos?</label><textarea id="rt-pedido" name="pedido" rows="6" maxlength="1000" required>' . esc_textarea($previo) . '</textarea>', '✏️ Pedir cambios'));
	}
	return $pagina($http, '<h1>¿Esta semana no?</h1><p>Esta semana no le enviamos nada a ' . $quien_html . '. Vuelve a aparecer en el próximo correo semanal.</p>'
		. $aviso . $ver . $formulario('', '⏭️ Esta semana no'));
}
