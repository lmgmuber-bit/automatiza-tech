<?php
/** Bloque «Respuesta del cliente» de la ficha de la propuesta y sus acciones. */
if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_at_cc_pedir_respuesta', 'at_cc_accion_pedir_respuesta');
add_action('admin_post_at_cc_registrar_aceptacion', 'at_cc_accion_registrar_aceptacion');
add_action('admin_post_at_cc_completar_cierre', 'at_cc_accion_completar_cierre');
add_action('wp_ajax_at_cc_ver_evidencia', 'at_cc_ver_evidencia');

function at_cc_url_ficha_propuesta(int $id): string {
	return add_query_arg(['page' => 'automatiza-proposals', 'edit_id' => $id, 'tab' => 'envio'], admin_url('admin.php'));
}

function at_cc_guardar_aviso(array $a): void {
	set_transient('at_cc_aviso_' . get_current_user_id(), $a, 120);
}

/** Aviso de la última acción del bloque, una sola vez. */
function at_cc_aviso_panel(int $propuesta_id): string {
	$clave = 'at_cc_aviso_' . get_current_user_id();
	$a = get_transient($clave);
	if (!is_array($a) || (int) ($a['propuesta_id'] ?? 0) !== $propuesta_id) {
		return '';
	}
	delete_transient($clave);
	$clase = ['ok' => 'notice-success', 'aviso' => 'notice-warning'][$a['tipo'] ?? ''] ?? 'notice-error';
	$html = '<div class="notice ' . $clase . ' is-dismissible"><p>' . esc_html((string) ($a['texto'] ?? '')) . '</p>';
	foreach ((array) ($a['detalles'] ?? []) as $d) {
		$html .= '<p>• ' . esc_html((string) $d) . '</p>';
	}
	return $html . '</div>';
}

/** Carpeta privada de evidencias (acceso directo bloqueado, como la de contratos). */
function at_cc_dir_evidencias(): string {
	$up = wp_upload_dir();
	$dir = trailingslashit($up['basedir']) . 'automatiza-tech-evidencias';
	if (!file_exists($dir)) {
		wp_mkdir_p($dir);
	}
	if (!file_exists($dir . '/.htaccess')) {
		@file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all\nOptions -Indexes\n");
	}
	if (!file_exists($dir . '/index.php')) {
		@file_put_contents($dir . '/index.php', "<?php\n// Silencio.\n");
	}
	return $dir;
}

function at_cc_url_evidencia(int $propuesta_id, string $archivo): string {
	return add_query_arg(['action' => 'at_cc_ver_evidencia', 'p' => $propuesta_id, 'f' => $archivo], admin_url('admin-ajax.php'));
}

/** Guarda hasta 3 imágenes válidas con nombre aleatorio. */
function at_cc_guardar_evidencias(int $propuesta_id, array $archivos): array {
	$guardadas = [];
	$errores = [];
	if (count($archivos) > 3) {
		$errores[] = 'Solo se guardan 3 imágenes por aceptación; las demás se ignoraron.';
		$archivos = array_slice($archivos, 0, 3);
	}
	if (!$archivos) {
		return ['guardadas' => [], 'errores' => $errores];
	}
	$dir = at_cc_dir_evidencias() . '/' . $propuesta_id;
	wp_mkdir_p($dir);
	foreach ($archivos as $f) {
		$error = at_cc_error_evidencia($f);
		if ($error !== '') {
			$errores[] = sanitize_file_name((string) $f['name']) . ': ' . $error;
			continue;
		}
		$info = getimagesize((string) $f['tmp_name']);
		$nombre = bin2hex(random_bytes(12)) . '.' . at_cc_mimes_evidencia()[$info['mime']];
		if (!move_uploaded_file((string) $f['tmp_name'], $dir . '/' . $nombre)) {
			$errores[] = sanitize_file_name((string) $f['name']) . ': no se pudo guardar';
			continue;
		}
		$guardadas[] = ['url' => at_cc_url_evidencia($propuesta_id, $nombre), 'nombre' => sanitize_file_name((string) $f['name']), 'tipo' => $info['mime'], 'archivo' => $nombre];
	}
	return ['guardadas' => $guardadas, 'errores' => $errores];
}

/** Borra evidencias recién guardadas cuando el registro no se aplicó (Ronda 2, hallazgo 4): sin esto
 *  quedan huérfanas en disco -con datos personales- sin ninguna fila de Seguimiento que las referencie. */
function at_cc_borrar_evidencias(int $propuesta_id, array $guardadas): void {
	if (!$guardadas) {
		return;
	}
	$dir = at_cc_dir_evidencias() . '/' . $propuesta_id;
	foreach ($guardadas as $g) {
		$archivo = (string) ($g['archivo'] ?? '');
		if ($archivo === '' || !preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $archivo)) {
			continue;
		}
		@unlink($dir . '/' . $archivo);
	}
}

/** Muestra una evidencia solo a un administrador con sesión. */
function at_cc_ver_evidencia(): void {
	if (!current_user_can('manage_options')) {
		wp_die('Acceso denegado.', '', ['response' => 403]);
	}
	$pid = (int) ($_GET['p'] ?? 0);
	$f = (string) wp_unslash($_GET['f'] ?? '');
	if ($pid <= 0 || !preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $f, $m)) {
		wp_die('No encontrado.', '', ['response' => 404]);
	}
	$ruta = at_cc_dir_evidencias() . '/' . $pid . '/' . $f;
	if (!is_file($ruta)) {
		wp_die('No encontrado.', '', ['response' => 404]);
	}
	nocache_headers();
	header('Content-Type: ' . array_search($m[1], at_cc_mimes_evidencia(), true));
	header('Content-Length: ' . filesize($ruta));
	header('X-Content-Type-Options: nosniff');
	if (ob_get_level()) {
		ob_end_clean();
	}
	readfile($ruta);
	exit;
}

function at_cc_accion_pedir_respuesta(): void {
	$id = (int) ($_POST['proposal_id'] ?? 0);
	check_admin_referer('at_cc_pedir_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$p = at_cc_propuesta_por_id($id);
	$candado = 'at_cc_pedido_' . $id;
	if (!$p || !at_cc_puede_pedir_respuesta((string) $p->status)) {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => 'Esta propuesta no está esperando respuesta: se pide cuando ya fue enviada.']);
	} elseif (!is_email((string) $p->client_email)) {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => 'La propuesta no tiene un correo válido del cliente.']);
	} elseif (get_transient($candado)) {
		// Ronda 2, hallazgo 5: sin este candado, un doble clic en «Pedir respuesta» manda dos correos
		// (y, con la Task 10, dos WhatsApp) con dos notas en Seguimiento. Se marca ANTES de enviar, no
		// después, para que dos clics casi simultáneos no alcancen a pasar los dos por aquí.
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'aviso', 'texto' => 'Ya se le pidió la respuesta hace un momento: espera un minuto antes de repetirlo.']);
	} else {
		set_transient($candado, 1, 60);
		$ok = at_cc_enviar_pedido_respuesta($p);
		// Ronda 2, hallazgo 1: 'pedido_respuesta' (no 'propuesta_enviada', que es un tipo público) para
		// que esta nota nunca llegue a la línea de tiempo pública del prospecto ni le tape la tarjeta
		// automática «Propuesta Creada» (at_cc_tipos_internos(), puras.php).
		at_cc_anotar_simple($p, 'pedido_respuesta', $ok ? 'Se le pidió la respuesta al cliente por correo' : 'Falló el correo para pedir la respuesta');
		$detalles = function_exists('at_cc_whatsapp_tras_pedido') ? at_cc_whatsapp_tras_pedido($p) : [];
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => $ok ? 'ok' : 'error', 'texto' => $ok ? 'Le pedimos la respuesta por correo.' : 'No salió el correo. Revisa el SMTP.', 'detalles' => $detalles]);
	}
	wp_safe_redirect(at_cc_url_ficha_propuesta($id));
	exit;
}

function at_cc_accion_registrar_aceptacion(): void {
	$id = (int) ($_POST['proposal_id'] ?? 0);
	check_admin_referer('at_cc_aceptar_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$volver = function (array $aviso) use ($id): void {
		at_cc_guardar_aviso(array_merge(['propuesta_id' => $id], $aviso));
		wp_safe_redirect(at_cc_url_ficha_propuesta($id));
		exit;
	};
	$p = at_cc_propuesta_por_id($id);
	if (!$p) {
		$volver(['tipo' => 'error', 'texto' => 'Esa propuesta no existe.']);
	}
	$nota = trim(sanitize_textarea_field(wp_unslash($_POST['nota'] ?? '')));
	$canal = sanitize_key(wp_unslash($_POST['canal'] ?? ''));
	if ($nota === '' || !isset(at_cc_canales_manuales()[$canal])) {
		$volver(['tipo' => 'error', 'texto' => 'Falta decir por dónde aceptó y qué dijo el cliente.']);
	}
	// Ronda 2, hallazgo 3: un RUT con dígito verificador equivocado se descartaba en silencio
	// (quedaba '' sin avisar), y la aceptación se registraba igual en verde, con el contrato sin RUT.
	$rut = sanitize_text_field(wp_unslash($_POST['rut'] ?? ''));
	if ($rut !== '' && !at_cc_rut_valido($rut)) {
		$volver(['tipo' => 'error', 'texto' => 'El RUT no es válido: corrígelo o déjalo en blanco.']);
	}
	// Ronda 2, hallazgo 2: la página pública exige al menos una fila aceptada cuando la propuesta tiene
	// filas (pagina.php, at_cc_procesar_respuesta_publica()); el camino manual no lo exigía, así que
	// desmarcar la única casilla dejaba la propuesta aceptada sin servicios contratados, sin monto y
	// con todas las filas como «fases siguientes» en el contrato.
	$todas = at_cc_filas_de_propuesta($p);
	$filas = at_cc_filas_aceptadas($todas, array_map('sanitize_text_field', (array) wp_unslash($_POST['filas'] ?? [])));
	if ($todas && !$filas) {
		$volver(['tipo' => 'error', 'texto' => 'Marca qué aceptó el cliente.']);
	}
	// Ronda 2, hallazgo 4: se valida el estado ANTES de guardar evidencias (con datos personales) en
	// disco, para no guardar nada cuando el registro de todos modos no se va a aplicar. Esto no
	// reemplaza el mismo chequeo dentro de at_cc_registrar_respuesta(): dos pestañas abiertas pueden
	// cambiar el estado entre este chequeo y el UPDATE de más abajo, así que si igual se cuela una
	// evidencia huérfana, se borra después (más abajo, con at_cc_borrar_evidencias()).
	$estado = (string) $p->status;
	if ($estado === 'aceptada') {
		$volver(['tipo' => 'ok', 'texto' => 'La propuesta ya estaba aceptada: no se repitió nada.']);
	}
	if (!at_cc_transicion_respuesta_valida($estado, 'aceptada', true)) {
		$volver(['tipo' => 'error', 'texto' => 'Esta propuesta no está esperando respuesta.']);
	}
	$fecha = at_cc_fecha_declarada(sanitize_text_field(wp_unslash($_POST['fecha'] ?? '')), current_time('Y-m-d'));
	$ev = at_cc_guardar_evidencias($id, at_cc_archivos_normalizados($_FILES['evidencia'] ?? []));
	$r = at_cc_registrar_respuesta($p, 'acepta', [
		'canal'        => 'manual',
		'canal_manual' => $canal,
		'nombre'       => sanitize_text_field(wp_unslash($_POST['nombre'] ?? '')),
		'rut'          => $rut !== '' ? at_cc_rut_formato($rut) : '',
		'comentario'   => $nota,
		'filas'        => $filas,
		'fecha'        => $fecha !== '' ? $fecha : current_time('mysql'),
		'evidencias'   => $ev['guardadas'],
		'bienvenida'   => !empty($_POST['bienvenida']),
		'usuario_id'   => get_current_user_id(),
	]);
	// Ronda 2, hallazgo 4: el registro no se aplicó (la propuesta cambió mientras se enviaba el
	// formulario, o -pese al chequeo de arriba- ya estaba aceptada por la otra pestaña): las evidencias
	// que se acaban de guardar quedan huérfanas, sin ninguna fila que las referencie.
	if (!$r['ok'] || $r['mensaje'] === 'ya_aceptada') {
		at_cc_borrar_evidencias($id, $ev['guardadas']);
	}
	// Ronda 1, hallazgo 1: 'avisos_operativos' (p. ej. datos bancarios sin configurar) también se
	// muestra aquí. En el canal manual at_cc_avisar_luis() no manda nada, así que este panel es el
	// único lugar donde Luis puede enterarse de que tiene que mandarle los datos de transferencia.
	$detalles = array_merge($ev['errores'], (array) $r['avisos'], (array) ($r['avisos_operativos'] ?? []));
	if (!$r['ok']) {
		$volver(['tipo' => 'error', 'texto' => (string) $r['mensaje'], 'detalles' => $detalles]);
	}
	$texto = $r['mensaje'] === 'ya_aceptada' ? 'La propuesta ya estaba aceptada: no se repitió nada.' : 'Aceptación registrada: la propuesta quedó aceptada, el cliente pasó a la ficha única y el contrato quedó listo para tu revisión.';
	$volver(['tipo' => $detalles ? 'aviso' : 'ok', 'texto' => $texto, 'detalles' => $detalles]);
}

/** Va dentro del formulario de la ficha: sus campos usan form="…" para enviarse con los formularios de abajo. */
function at_cc_render_panel_respuesta(object $p): void {
	$estado = (string) $p->status;
	$ultima = at_cc_ultima_respuesta((int) $p->id);
	$filas = at_cc_filas_de_propuesta($p);
	$wa = at_cc_url_wa_me((string) $p->phone, at_cc_texto_whatsapp((string) $p->client_name, (string) $p->company_name, at_cc_url_respuesta(get_site_url(), (string) $p->unique_link_id, 'aceptar')));
	echo '<div class="at-cc-panel" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin:0 0 16px">';
	echo '<h3 style="margin-top:0">🤝 Respuesta del cliente</h3>';
	echo '<p>Estado: <strong>' . esc_html(at_pa_estado_etiqueta($estado)['etiqueta']) . '</strong>';
	if ($ultima) {
		echo ' · Última respuesta: ' . esc_html($ultima['titulo']) . ' (' . esc_html(at_pa_fecha_corta($ultima['fecha'])) . ')';
	}
	echo '</p>';
	if ($estado === 'aceptada') {
		at_cc_render_resumen_aceptada($p, $ultima);
		echo '</div>';
		return;
	}
	echo '<p>';
	$puede_pedir = at_cc_puede_pedir_respuesta($estado);
	if ($puede_pedir) {
		echo '<button type="submit" class="button" form="at-cc-f-pedir">📧 Pedir respuesta por correo</button> ';
	}
	// Ronda 1, hallazgo 2: el enlace de aceptar solo se dibuja en la página pública cuando la
	// propuesta está en sent/evaluando/rechazada (at_cc_render_barra() en pagina.php). Mandarlo en
	// otro estado (p. ej. 'lista') deja al cliente con un enlace que no muestra dónde aceptar.
	if (!$puede_pedir) {
		echo '<span class="description">El WhatsApp con el enlace de aceptar se habilita cuando la propuesta esté enviada.</span>';
	} elseif ($wa !== '') {
		// Ronda 1, hallazgo 1 (whatsapp.php): esc_url() borra '%0a'/'%0d' y pega el mensaje con el
		// enlace para aceptar; $wa ya es segura por construcción (at_cc_url_wa_me()), basta esc_attr().
		echo '<a class="button" target="_blank" rel="noopener" href="' . esc_attr($wa) . '">💬 Enviar por mi WhatsApp</a>';
	} else {
		echo '<span class="description">Sin teléfono: agrégalo en «Cliente y enlaces» para mandar el WhatsApp.</span>';
	}
	echo '</p>';
	if (!at_cc_transicion_respuesta_valida($estado, 'aceptada', true)) {
		echo '<p class="description">La aceptación se registra cuando la propuesta está lista o enviada.</p></div>';
		return;
	}
	$hoy = current_time('Y-m-d');
	echo '<details><summary style="cursor:pointer;font-weight:600">✍️ Registrar aceptación a mano</summary>';
	echo '<p class="description">Para cuando el cliente ya aceptó por WhatsApp, correo, llamada o en una reunión. La propuesta pasa a aceptada, el cliente pasa a la ficha única, recibe la bienvenida si la marcas y el contrato queda listo para tu revisión.</p>';
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th>¿Por dónde aceptó?</th><td><select name="canal" form="at-cc-f-aceptar" required><option value="">Elegir…</option>';
	foreach (at_cc_canales_manuales() as $k => $t) {
		echo '<option value="' . esc_attr($k) . '">' . esc_html($t) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>¿Cuándo aceptó?</th><td><input type="date" name="fecha" form="at-cc-f-aceptar" value="' . esc_attr($hoy) . '" max="' . esc_attr($hoy) . '"></td></tr>';
	if ($filas) {
		echo '<tr><th>¿Qué aceptó?</th><td>';
		foreach ($filas as $i => $f) {
			echo '<label style="display:block"><input type="checkbox" name="filas[]" value="' . (int) $i . '" form="at-cc-f-aceptar"' . ($i === 0 ? ' checked' : '') . '> '
				. esc_html(trim(($f['service'] ?? '') . ' · ' . ($f['price_label'] ?? ''))) . '</label>';
		}
		echo '</td></tr>';
	}
	echo '<tr><th>¿Quién aceptó?</th><td><input type="text" class="regular-text" name="nombre" form="at-cc-f-aceptar" value="' . esc_attr((string) $p->client_name) . '"></td></tr>';
	echo '<tr><th>RUT (si lo tienes)</th><td><input type="text" class="regular-text" name="rut" form="at-cc-f-aceptar" placeholder="12.345.678-9"></td></tr>';
	echo '<tr><th>¿Qué dijo el cliente?</th><td><textarea name="nota" form="at-cc-f-aceptar" rows="3" class="large-text" required placeholder="Ej.: Me escribió por WhatsApp: sí, partamos con la fase 1."></textarea></td></tr>';
	echo '<tr><th>Evidencia</th><td><input type="file" name="evidencia[]" form="at-cc-f-aceptar" accept="image/jpeg,image/png,image/webp" multiple>'
		. '<p class="description">Hasta 3 imágenes (JPG, PNG o WEBP, 5 MB cada una), por ejemplo la captura del WhatsApp. Se guardan en privado: solo se ven desde el panel.</p></td></tr>';
	echo '<tr><th>Bienvenida</th><td><label><input type="checkbox" name="bienvenida" value="1" form="at-cc-f-aceptar" checked> Enviarle el correo de bienvenida</label></td></tr>';
	echo '</table>';
	echo '<p><button type="submit" class="button button-primary" form="at-cc-f-aceptar" onclick="return confirm(\'Esto pasa la propuesta a aceptada, crea al cliente y le envía la bienvenida si está marcada. ¿Seguir?\');">Registrar aceptación</button></p>';
	echo '</details></div>';
}

function at_cc_render_resumen_aceptada(object $p, ?array $ultima): void {
	$crm_id = at_cc_crm_de_email((string) $p->client_email);
	echo '<p>✅ La propuesta está aceptada.</p><p>';
	if ($crm_id) {
		echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=automatiza-crm-ficha&id=' . $crm_id)) . '">Abrir la ficha del cliente</a> ';
	}
	$c = at_cc_contrato_de_propuesta((int) $p->id);
	if ($c && in_array($c->status, ['draft', 'at_pending'], true)) {
		echo '<a class="button button-primary" href="' . esc_url(home_url('/contracts/at-sign-contract.php?token=' . $c->at_review_token)) . '">Revisar, ajustar y firmar el contrato</a>';
	} elseif ($c) {
		echo '<span class="description">Contrato ' . esc_html((string) $c->contract_number) . ': ' . esc_html((string) $c->status) . '</span>';
	}
	echo '</p>';
	// Revisión final (26-sep), hallazgo 7: un cierre que quedó a medias (no se pudo pasar a cliente o
	// no se creó el contrato) se completa desde aquí; el formulario real está en
	// at_cc_render_formularios_respuesta().
	if (at_cc_cierre_incompleto($p)) {
		$faltas = [];
		if (!$crm_id || !at_cc_tech_de_crm($crm_id)) {
			$faltas[] = 'pasar al cliente a la ficha única';
		}
		if (!$c) {
			$faltas[] = 'crear el contrato de servicios';
		}
		echo '<div class="notice notice-warning inline" style="margin:8px 0;padding:8px 12px">';
		echo '<p style="margin:0 0 6px"><strong>⚠️ El cierre quedó a medias:</strong> falta ' . esc_html(implode(' y ', $faltas)) . '. El motivo está en Seguimiento («Cierre incompleto»). Si era el correo del cliente, corrígelo en «Cliente y enlaces» y guarda antes de completar.</p>';
		echo '<label style="display:block;margin:0 0 6px"><input type="checkbox" name="bienvenida" value="1" form="at-cc-f-completar" checked> Enviarle el correo de bienvenida si todavía no le llegó</label>';
		echo '<button type="submit" class="button button-primary" form="at-cc-f-completar">🔁 Completar cierre</button>';
		echo '</div>';
	}
	foreach ((array) ($ultima['evidencias'] ?? []) as $ev) {
		echo '<a href="' . esc_url((string) $ev['url']) . '" target="_blank" rel="noopener"><img src="' . esc_url((string) $ev['url']) . '" alt="Evidencia" style="max-width:140px;max-height:140px;margin:4px;border:1px solid #ddd;border-radius:6px"></a>';
	}
}

/** «Completar cierre» (revisión final 26-sep, hallazgo 7): vuelve a correr el cierre con la última
 *  aceptación registrada, sin repetir lo que ya se hizo (at_cc_completar_cierre()). */
function at_cc_accion_completar_cierre(): void {
	$id = (int) ($_POST['proposal_id'] ?? 0);
	check_admin_referer('at_cc_completar_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$p = at_cc_propuesta_por_id($id);
	$candado = 'at_cc_completar_' . $id;
	if (!$p || (string) $p->status !== 'aceptada') {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => 'Esta propuesta no está aceptada: no hay cierre que completar.']);
	} elseif (!at_cc_cierre_incompleto($p)) {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'ok', 'texto' => 'El cierre ya estaba completo: no se repitió nada.']);
	} elseif (get_transient($candado)) {
		at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'aviso', 'texto' => 'Ya se está completando el cierre: espera un minuto antes de repetirlo.']);
	} else {
		set_transient($candado, 1, 60);
		$r = at_cc_completar_cierre($p, !empty($_POST['bienvenida']));
		delete_transient($candado);
		$detalles = array_merge((array) $r['avisos'], (array) ($r['avisos_operativos'] ?? []));
		if (!$r['ok']) {
			at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => (string) $r['mensaje'], 'detalles' => $detalles]);
		} elseif (!empty($r['avisos'])) {
			at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => 'error', 'texto' => 'El cierre sigue incompleto.', 'detalles' => $detalles]);
		} else {
			at_cc_guardar_aviso(['propuesta_id' => $id, 'tipo' => $detalles ? 'aviso' : 'ok', 'texto' => 'Cierre completado: el cliente está en la ficha única y el contrato quedó listo para tu revisión.', 'detalles' => $detalles]);
		}
	}
	wp_safe_redirect(at_cc_url_ficha_propuesta($id));
	exit;
}

/** Formularios reales del bloque; van después del formulario de la ficha (no se pueden anidar). */
function at_cc_render_formularios_respuesta(object $p): void {
	$url = esc_url(admin_url('admin-post.php'));
	$id = (int) $p->id;
	echo '<form id="at-cc-f-pedir" method="post" action="' . $url . '" hidden>'
		. '<input type="hidden" name="action" value="at_cc_pedir_respuesta"><input type="hidden" name="proposal_id" value="' . $id . '">'
		. wp_nonce_field('at_cc_pedir_' . $id, '_wpnonce', true, false) . '</form>';
	echo '<form id="at-cc-f-aceptar" method="post" action="' . $url . '" enctype="multipart/form-data" hidden>'
		. '<input type="hidden" name="action" value="at_cc_registrar_aceptacion"><input type="hidden" name="proposal_id" value="' . $id . '">'
		. wp_nonce_field('at_cc_aceptar_' . $id, '_wpnonce', true, false) . '</form>';
	if ((string) $p->status === 'aceptada' && at_cc_cierre_incompleto($p)) {
		echo '<form id="at-cc-f-completar" method="post" action="' . $url . '" hidden>'
			. '<input type="hidden" name="action" value="at_cc_completar_cierre"><input type="hidden" name="proposal_id" value="' . $id . '">'
			. wp_nonce_field('at_cc_completar_' . $id, '_wpnonce', true, false) . '</form>';
	}
	// Ronda 2, hallazgo 5: estos formularios están fuera de .at-pa-form (no se pueden anidar), así
	// que la guardia contra doble envío de esa otra ficha (F4, assets/js/propuestas-admin.js) nunca los
	// alcanza: cada uno solo escucha su propio 'submit'. Sus botones viven en at_cc_render_panel_respuesta()
	// y apuntan aquí con form="…", así que se deshabilitan por ese atributo, no por closest('form').
	// Revisión final (26-sep), hallazgo 2: solo los BOTONES. El navegador arma los datos del envío
	// después del evento submit y omite los controles deshabilitados: deshabilitar también los campos
	// con form="…" (canal, nota, filas, RUT, evidencia…) hacía que nunca llegaran al servidor.
	echo "<script>(function(){function candado(id){var f=document.getElementById(id);if(!f){return;}var enviando=false;f.addEventListener('submit',function(e){if(enviando){e.preventDefault();return;}enviando=true;document.querySelectorAll('button[form=\"'+id+'\"], #'+id+' button').forEach(function(b){b.disabled=true;});});}candado('at-cc-f-pedir');candado('at-cc-f-aceptar');candado('at-cc-f-completar');})();</script>";
}
