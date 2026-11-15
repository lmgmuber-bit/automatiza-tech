<?php
/**
 * Rastro · Prospección: envío del primer correo a los prospectos, en cola y escalonado.
 *
 * Sale desde la casilla de prospección (constantes AT_PRO_SMTP_USER y AT_PRO_SMTP_PASS en wp-config de PROD; las
 * pone Luis, nunca el repo), con copia oculta a at_pro_cco(). Solo se envía cuando Luis aprieta el botón del panel.
 * De a AT_PRO_LOTE correos cada AT_PRO_INTERVALO segundos, en horario hábil de Chile y con tope diario, para no
 * quemar la reputación del dominio. Cada envío correcto deja al prospecto en «contactado» con canal «correo».
 */
if (!defined('ABSPATH')) { exit; }

const AT_PRO_LOTE = 5;           // correos por tanda
const AT_PRO_INTERVALO = 900;    // segundos entre tandas (15 min)
const AT_PRO_TOPE_DIA = 40;      // máximo de correos por día, muy bajo el límite por casilla de Hostinger
const AT_PRO_FALLOS_MAX = 3;     // fallos seguidos que pausan el envío
const AT_PRO_HORA_DESDE = 9;     // horario hábil (hora de Chile), lunes a viernes
const AT_PRO_HORA_HASTA = 18;

/** ¿Está conectada la casilla de prospección? */
function at_pro_envio_listo(): bool {
	return defined('AT_PRO_SMTP_USER') && defined('AT_PRO_SMTP_PASS') && is_email((string) AT_PRO_SMTP_USER) && (string) AT_PRO_SMTP_PASS !== '';
}

function at_pro_remitente(): string {
	return at_pro_envio_listo() ? (string) AT_PRO_SMTP_USER : '';
}

function at_pro_remitente_nombre(): string {
	$n = trim(str_replace([',', '<', '>', '"', "
", "
"], '', (string) get_option('at_pro_remitente_nombre', 'Luis González · AutomatizaTech')));
	return $n !== '' ? $n : 'AutomatizaTech';
}

/** Ids de los prospectos que recibirían el correo ahora: por contactar y con correo. */
function at_pro_pendientes_envio(): array {
	global $wpdb;
	return array_map('intval', $wpdb->get_col('SELECT id FROM ' . at_pro_tabla() . " WHERE estado = 'por_contactar' AND correo <> '' ORDER BY id ASC"));
}

function at_pro_cola(): array {
	$c = get_option('at_pro_cola', []);
	return is_array($c) ? array_values(array_filter(array_map('intval', $c))) : [];
}

/** Generación del envío: cambia al encolar y al detener. Se lee sin caché porque Detener ocurre en otra petición. */
function at_pro_envio_gen(): int {
	global $wpdb;
	return (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'at_pro_envio_gen'));
}

function at_pro_nueva_gen(): void {
	update_option('at_pro_envio_gen', at_pro_envio_gen() + 1, false);
}

function at_pro_envio_estado(): array {
	$e = get_option('at_pro_envio_estado', []);
	return array_merge(['dia' => '', 'enviados_hoy' => 0, 'enviados_total' => 0, 'fallos_seguidos' => 0, 'pausado' => false, 'ultimo_error' => '', 'ultimo_envio' => ''], is_array($e) ? $e : []);
}

function at_pro_guardar_envio_estado(array $e): void {
	update_option('at_pro_envio_estado', $e, false);
}

/** Próximo momento hábil (timestamp) desde $ts: lunes a viernes entre AT_PRO_HORA_DESDE y AT_PRO_HORA_HASTA, hora de Chile. */
function at_pro_proximo_habil(int $ts): int {
	$zona = wp_timezone();
	$d = (new DateTimeImmutable('@' . $ts))->setTimezone($zona);
	for ($i = 0; $i < 8; $i++) {
		$dow = (int) $d->format('N');
		$h = (int) $d->format('G');
		if ($dow <= 5 && $h >= AT_PRO_HORA_DESDE && $h < AT_PRO_HORA_HASTA) { return $d->getTimestamp(); }
		if ($dow <= 5 && $h < AT_PRO_HORA_DESDE) { return $d->setTime(AT_PRO_HORA_DESDE, 0)->getTimestamp(); }
		$d = $d->modify('+1 day')->setTime(AT_PRO_HORA_DESDE, 0);
	}
	return $ts + DAY_IN_SECONDS;
}

function at_pro_programar_tanda(int $cuando): void {
	wp_clear_scheduled_hook('at_pro_enviar_tanda');
	wp_schedule_single_event(at_pro_proximo_habil($cuando), 'at_pro_enviar_tanda');
}

/** Pone en cola a todos los pendientes y arranca. Devuelve cuántos quedaron en cola. */
function at_pro_encolar(): int {
	if (!at_pro_envio_listo()) { return 0; }
	$ids = at_pro_pendientes_envio();
	at_pro_nueva_gen();
	update_option('at_pro_cola', $ids, false);
	$e = at_pro_envio_estado();
	$e['pausado'] = false;
	$e['fallos_seguidos'] = 0;
	$e['ultimo_error'] = '';
	at_pro_guardar_envio_estado($e);
	if ($ids) { at_pro_programar_tanda(time() + 5); }
	return count($ids);
}

function at_pro_detener_envio(): void {
	at_pro_nueva_gen();
	update_option('at_pro_cola', [], false);
	wp_clear_scheduled_hook('at_pro_enviar_tanda');
}

/**
 * Envía un correo con la casilla de prospección. $para es el destinatario real; con $prueba no lleva CCO y el asunto
 * va marcado. Devuelve true o WP_Error.
 */
function at_pro_mandar(array $p, string $para, bool $prueba = false) {
	if (!at_pro_envio_listo()) { return new WP_Error('sin_casilla', 'Falta conectar la casilla de prospección.'); }
	if (!is_email($para)) { return new WP_Error('sin_correo', 'Correo de destino no válido.'); }
	$url_form = at_pro_url_formulario($p['token']);
	$url_baja = at_pro_url_baja($p['token']);
	$asunto = ($prueba ? '[PRUEBA] ' : '') . at_pro_correo_asunto($p);
	$html = at_pro_correo_html($p, $url_form, $url_baja);
	$texto = at_pro_mensaje($p, $url_form, $url_baja);
	$de = at_pro_remitente();
	$cab = [
		'Content-Type: text/html; charset=UTF-8',
		'List-Unsubscribe: <' . $url_baja . '>, <mailto:' . $de . '?subject=baja>',
	];
	$cco = at_pro_cco();
	if (!$prueba && $cco !== '' && strcasecmp($cco, $para) !== 0) { $cab[] = 'Bcc: ' . $cco; }

	$error = '';
	$al_fallar = static function ($e) use (&$error) { $error = is_wp_error($e) ? $e->get_error_message() : 'falló el envío'; };
	// Solo para este envío: remitente y credenciales de la casilla de prospección (después del ajuste general de smtp-config.php).
	$casilla = static function ($m) use ($de, $texto) {
		$m->Username = (string) AT_PRO_SMTP_USER;
		$m->Password = (string) AT_PRO_SMTP_PASS;
		$m->From = $de;
		$m->Sender = $de;
		$m->FromName = at_pro_remitente_nombre();
		$m->AltBody = $texto;
		$m->Timeout = 30;
		$m->clearReplyTos(); // smtp-config.php agrega contacto@: las respuestas van solo a la casilla de prospección
		$m->addReplyTo($de, at_pro_remitente_nombre());
	};
	add_action('wp_mail_failed', $al_fallar);
	add_action('phpmailer_init', $casilla, 99);
	try {
		$ok = wp_mail($para, $asunto, $html, $cab);
	} finally {
		remove_action('phpmailer_init', $casilla, 99);
		remove_action('wp_mail_failed', $al_fallar);
		// wp_mail no limpia el remitente de sobre: que un correo posterior de la misma petición no salga con él.
		if (isset($GLOBALS['phpmailer']) && is_object($GLOBALS['phpmailer'])) { $GLOBALS['phpmailer']->Sender = ''; }
	}
	if (!$ok) { return new WP_Error('envio', $error !== '' ? mb_substr($error, 0, 300) : 'El servidor de correo no aceptó el mensaje.'); }
	return true;
}

/** ¿Sigue siendo correcto escribirle a este prospecto? (estado, correo y lista de bajas). */
function at_pro_se_puede_enviar(?array $p): bool {
	return $p && $p['estado'] === 'por_contactar' && is_email((string) $p['correo'])
		&& !at_pro_coincide_baja((string) ($p['dominio'] ?? ''), (string) $p['telefono'], strtolower((string) $p['correo']));
}

/** Reserva al prospecto antes de enviarle: lo pasa a contactado/correo solo si sigue por contactar (atómico). */
function at_pro_reservar(int $id): bool {
	global $wpdb;
	$ahora = current_time('mysql');
	return $wpdb->query($wpdb->prepare('UPDATE ' . at_pro_tabla() . " SET estado = 'contactado', canal = 'correo', contactado_at = COALESCE(contactado_at, %s), updated_at = %s WHERE id = %d AND estado = 'por_contactar'", $ahora, $ahora, $id)) === 1;
}

/** Deshace la reserva si el servidor de correo no aceptó el mensaje. */
function at_pro_liberar(int $id): void {
	global $wpdb;
	$wpdb->query($wpdb->prepare('UPDATE ' . at_pro_tabla() . " SET estado = 'por_contactar', canal = '', contactado_at = NULL, updated_at = %s WHERE id = %d AND estado = 'contactado' AND canal = 'correo'", current_time('mysql'), $id));
}

/** Candado atómico (add_option no pisa una fila existente). Uno vencido (más de 15 min) se recupera. */
function at_pro_tomar_candado(): bool {
	if (add_option('at_pro_envio_lock', (string) time(), '', 'no')) { return true; }
	global $wpdb;
	$t = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'at_pro_envio_lock'));
	if ($t > 0 && time() - $t > 15 * MINUTE_IN_SECONDS) {
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", 'at_pro_envio_lock', (string) $t));
		wp_cache_delete('at_pro_envio_lock', 'options');
		return (bool) add_option('at_pro_envio_lock', (string) time(), '', 'no');
	}
	return false;
}

/**
 * Una tanda del envío (WP-Cron). Si Luis aprieta «Detener» mientras corre, la generación cambia y la tanda se
 * corta antes del siguiente correo, sin volver a guardar la cola ni programar otra. Un fallo no se reintenta solo:
 * el prospecto vuelve a «por contactar» y sale de la cola (si el servidor alcanzó a entregarlo, no se duplica).
 */
function at_pro_enviar_tanda(): void {
	if (!at_pro_tomar_candado()) { return; }
	$gen = at_pro_envio_gen();
	$e = at_pro_envio_estado();
	$cola = at_pro_cola();
	$seguir = false;
	try {
		if (!$cola || $e['pausado']) { return; }
		if (!at_pro_envio_listo()) { $e['pausado'] = true; $e['ultimo_error'] = 'Falta conectar la casilla de prospección.'; return; }
		$ahora = time();
		if (at_pro_proximo_habil($ahora) > $ahora) { at_pro_programar_tanda($ahora); return; }
		$hoy = wp_date('Y-m-d', $ahora);
		if ($e['dia'] !== $hoy) { $e['dia'] = $hoy; $e['enviados_hoy'] = 0; }
		$cupo = min(AT_PRO_LOTE, AT_PRO_TOPE_DIA - (int) $e['enviados_hoy']);
		while ($cupo > 0 && $cola) {
			if (at_pro_envio_gen() !== $gen) { return; } // detenido mientras corría
			$id = array_shift($cola);
			$p = at_pro_obtener($id);
			if (!at_pro_se_puede_enviar($p) || !at_pro_reservar($id)) { continue; }
			$r = at_pro_mandar($p, (string) $p['correo']);
			if (is_wp_error($r)) {
				at_pro_liberar($id);
				$e['fallos_seguidos']++;
				$e['ultimo_error'] = $p['negocio'] . ': ' . $r->get_error_message();
				if ($e['fallos_seguidos'] >= AT_PRO_FALLOS_MAX) { $e['pausado'] = true; break; }
				continue;
			}
			$e['fallos_seguidos'] = 0;
			$e['enviados_hoy']++;
			$e['enviados_total']++;
			$e['ultimo_envio'] = current_time('mysql');
			$cupo--;
		}
		$seguir = $cola && !$e['pausado'];
	} finally {
		if (at_pro_envio_gen() === $gen) {
			update_option('at_pro_cola', array_values($cola), false);
			if ($seguir) {
				$siguiente = ((int) $e['enviados_hoy'] >= AT_PRO_TOPE_DIA) ? strtotime('tomorrow', time()) : time() + AT_PRO_INTERVALO;
				at_pro_programar_tanda((int) $siguiente);
			}
		}
		at_pro_guardar_envio_estado($e);
		delete_option('at_pro_envio_lock');
	}
}
add_action('at_pro_enviar_tanda', 'at_pro_enviar_tanda');

/** Correo de prueba: el primer pendiente, enviado solo a la copia oculta de Luis. No cambia ningún estado. */
function at_pro_enviar_prueba() {
	$cco = at_pro_cco();
	if ($cco === '') { return new WP_Error('sin_cco', 'Falta la dirección de copia oculta.'); }
	$ids = at_pro_pendientes_envio();
	$p = $ids ? at_pro_obtener($ids[0]) : null;
	if (!$p) { return new WP_Error('sin_pendientes', 'No hay prospectos por contactar con correo.'); }
	return at_pro_mandar($p, $cco, true);
}

// Acciones del panel (solo administrador, con nonce).
foreach (['at_pro_envio_encolar', 'at_pro_envio_detener', 'at_pro_envio_prueba'] as $accion) {
	add_action('admin_post_' . $accion, static function () use ($accion) {
		if (!current_user_can('manage_options') || !wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), $accion)) {
			wp_safe_redirect(at_pro_url_panel(['pro' => 'envio_no']));
			exit;
		}
		if ($accion === 'at_pro_envio_encolar') {
			$n = at_pro_encolar();
			wp_safe_redirect(at_pro_url_panel(['pro' => 'envio_cola', 'n' => $n]));
		} elseif ($accion === 'at_pro_envio_detener') {
			at_pro_detener_envio();
			wp_safe_redirect(at_pro_url_panel(['pro' => 'envio_detenido']));
		} else {
			$r = at_pro_enviar_prueba();
			if (is_wp_error($r)) { set_transient('at_pro_err_' . get_current_user_id(), $r->get_error_message(), 300); }
			wp_safe_redirect(at_pro_url_panel(['pro' => is_wp_error($r) ? 'prueba_no' : 'prueba_ok']));
		}
		exit;
	});
}

/** Caja del panel con el estado del envío y sus botones. */
function at_pro_caja_envio(): void {
	$listo = at_pro_envio_listo();
	$pend = count(at_pro_pendientes_envio());
	$cola = at_pro_cola();
	$e = at_pro_envio_estado();
	$prox = wp_next_scheduled('at_pro_enviar_tanda');
	$form = static function (string $accion, string $html, string $clase = 'button', string $confirmar = '', bool $activo = true): string {
		return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:0 6px 6px 0"' . ($confirmar !== '' ? ' onsubmit="return confirm(' . esc_attr(wp_json_encode($confirmar)) . ')"' : '') . '>'
			. '<input type="hidden" name="action" value="' . esc_attr($accion) . '">' . wp_nonce_field($accion, '_wpnonce', false, false)
			. '<button type="submit" class="' . esc_attr($clase) . '"' . ($activo ? '' : ' disabled') . '>' . $html . '</button></form>';
	};
	echo '<div class="at-pro-caja"><h2 style="margin:6px 0"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> Envío del primer correo</h2>';
	if (!$listo) {
		echo '<p class="notice notice-warning inline" style="padding:8px">Falta conectar la casilla de prospección: agrega <code>AT_PRO_SMTP_USER</code> y <code>AT_PRO_SMTP_PASS</code> en el wp-config de PROD. Mientras tanto no sale nada.</p>';
	}
	echo '<p>Sale desde <strong>' . esc_html($listo ? at_pro_remitente_nombre() . ' <' . at_pro_remitente() . '>' : 'la casilla de prospección') . '</strong>'
		. (at_pro_cco() !== '' ? ', con copia oculta a <code>' . esc_html(at_pro_cco()) . '</code>' : '') . '. '
		. 'De a ' . AT_PRO_LOTE . ' cada ' . (AT_PRO_INTERVALO / 60) . ' minutos, de lunes a viernes de ' . AT_PRO_HORA_DESDE . ' a ' . AT_PRO_HORA_HASTA . ' h, máximo ' . AT_PRO_TOPE_DIA . ' al día. '
		. 'Cada correo enviado deja al prospecto en «contactado» por correo. Quien pidió baja no recibe nada.</p>';
	echo '<p><strong>Por contactar con correo:</strong> ' . (int) $pend . ' · <strong>En cola:</strong> ' . count($cola)
		. ' · <strong>Enviados hoy:</strong> ' . (int) ($e['dia'] === wp_date('Y-m-d') ? $e['enviados_hoy'] : 0) . ' · <strong>Total enviados:</strong> ' . (int) $e['enviados_total']
		. ($e['ultimo_envio'] !== '' ? ' · <strong>Último:</strong> ' . esc_html(mysql2date('d-m-Y H:i', $e['ultimo_envio'])) : '')
		. ($cola && $prox ? ' · <strong>Próxima tanda:</strong> ' . esc_html(wp_date('d-m-Y H:i', $prox)) : '') . '</p>';
	if ($e['pausado'] && $cola) {
		echo '<p class="notice notice-error inline" style="padding:8px"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Envío en pausa tras ' . (int) $e['fallos_seguidos'] . ' fallos seguidos. Último error: ' . esc_html($e['ultimo_error']) . '. Revisa la casilla y vuelve a apretar «Enviar a todos».</p>';
	} elseif ($e['ultimo_error'] !== '') {
		echo '<p class="description">Último error: ' . esc_html($e['ultimo_error']) . '</p>';
	}
	echo $form('at_pro_envio_prueba', '<span class="dashicons dashicons-visibility" aria-hidden="true"></span> Enviarme una prueba', 'button', '', $listo && $pend > 0 && at_pro_cco() !== '');
	if ($cola && !$e['pausado']) {
		echo $form('at_pro_envio_detener', '<span class="dashicons dashicons-controls-pause" aria-hidden="true"></span> Detener envío', 'button', '¿Detener el envío? Los que ya salieron quedan como contactados.');
	} else {
		echo $form('at_pro_envio_encolar', '<span class="dashicons dashicons-email-alt2" aria-hidden="true"></span> Enviar correo a todos los por contactar (' . (int) $pend . ')', 'button button-primary',
			'Se enviarán ' . $pend . ' correos desde ' . at_pro_remitente() . ', de a ' . AT_PRO_LOTE . ' cada ' . (AT_PRO_INTERVALO / 60) . ' minutos en horario hábil. ¿Seguir?', $listo && $pend > 0);
	}
	echo '</div>';
}
