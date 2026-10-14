<?php
/**
 * Estado del proyecto (Etapa 2): sección «📈 Avance» de la pestaña «🗓️ Plan de trabajo» (solo planes enviados) y sus
 * acciones admin-post (spec §5). Cada acción: manage_options + nonce del plan (at_pt_accion_plan) y vuelta a la ficha
 * con pt_msg; los detalles (errores de la importación, avisos) van con at_pt_guardar_detalles. Lo que cambia algo
 * visible para el cliente rehace «Estado del proyecto» si hay al menos un cierre publicado (R4). Publicar no envía nada (D5).
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Avisos de la sección: se suman a at_pt_mensajes_panel(). */
function at_av_mensajes(): array {
	return [
		'av_importado'            => ['ok', 'Cierre guardado como borrador. Revísalo y publícalo cuando esté listo; el cliente todavía no lo ve.'],
		'av_importado_faltan'     => ['aviso', 'Cierre guardado como borrador, pero faltan archivos por subir (detalle abajo). No se puede publicar hasta subirlos.'],
		'av_import_error'         => ['error', 'No se guardó el cierre: el archivo tiene errores (detalle abajo). No cambió nada.'],
		'av_subido'               => ['ok', 'Archivo subido.'],
		'av_subida_error'         => ['error', 'No se subió el archivo (detalle abajo).'],
		'av_publicado'            => ['ok', 'Cierre publicado. Estamos rehaciendo «Estado del proyecto»: queda listo en unos minutos. Al cliente no se le envió nada.'],
		'av_publicado_sin_render' => ['aviso', 'Cierre publicado, pero n8n no recibió el pedido de la presentación. Usa «Reintentar» en un rato.'],
		'av_no_publicable'        => ['error', 'No se publicó (detalle abajo).'],
		'av_borrador'             => ['ok', 'El cierre volvió a borrador: el cliente ya no lo ve.'],
		'av_quitado'              => ['ok', 'Cierre quitado, con sus archivos.'],
		'av_bloques'              => ['ok', 'Bloques guardados.'],
		'av_listas'               => ['ok', 'Cambios de plan, «necesitamos de ti» y alcance guardados.'],
		'av_listas_error'         => ['error', 'No se guardó (detalle abajo). No cambió nada.'],
		'av_reintento'            => ['ok', 'Pedimos de nuevo «Estado del proyecto». Queda listo en unos minutos.'],
		'av_reintento_fallo'      => ['error', 'n8n no recibió el pedido. Inténtalo de nuevo en un rato.'],
		'av_previa'               => ['ok', 'Pedimos la vista previa con borradores. Recarga en un minuto para ver el enlace.'],
		'av_sin_publicados'       => ['aviso', 'Todavía no hay cierres publicados: no hay presentación que rehacer.'],
		'av_sin_cierres'          => ['aviso', 'Importa un cierre antes de pedir la vista previa.'],
		'av_no_enviado'           => ['error', 'El avance se carga solo en planes ya enviados al cliente.'],
		'av_sin_columna'          => ['error', 'Falta preparar la base para el avance: vuelve a entrar al escritorio de WordPress e inténtalo de nuevo.'],
		'av_error_guardar'        => ['error', 'No se pudo guardar el avance en la base. Inténtalo de nuevo.'],
		'av_sin_cierre'           => ['error', 'Esa acción no se pudo hacer con ese cierre (detalle abajo, si hay).'],
	];
}

add_action('admin_post_at_av_importar', 'at_av_accion_importar');
add_action('admin_post_at_av_subir', 'at_av_accion_subir');
add_action('admin_post_at_av_publicar', 'at_av_accion_publicar');
add_action('admin_post_at_av_borrador', 'at_av_accion_borrador');
add_action('admin_post_at_av_quitar', 'at_av_accion_quitar');
add_action('admin_post_at_av_bloques', 'at_av_accion_bloques');
add_action('admin_post_at_av_listas', 'at_av_accion_listas');
add_action('admin_post_at_av_reintentar', 'at_av_accion_reintentar');
add_action('admin_post_at_av_previa', 'at_av_accion_previa');

/** Permiso, nonce, plan enviado y migración hecha; si no, vuelve a la ficha. Devuelve [fila, crm, avance]. */
function at_av_accion_inicio(): array {
	[$fila, $crm] = at_pt_accion_plan();
	$id = (int) $fila->id;
	if ((string) $fila->estado !== 'enviado') {
		at_pt_volver($crm, $id, 'av_no_enviado');
	}
	if (!at_av_columna_lista()) {
		at_pt_volver($crm, $id, 'av_sin_columna');
	}
	return [$fila, $crm, at_av_leer($fila)];
}

function at_av_guardar_o_volver(int $id, int $crm, array $avance): void {
	if (!at_av_guardar($id, $avance)) {
		at_pt_volver($crm, $id, 'av_error_guardar');
	}
}

function at_av_cierre_post(): string {
	$c = sanitize_key(wp_unslash($_POST['cierre_id'] ?? ''));
	return preg_match('/^c[a-z0-9]{6}$/', $c) ? $c : '';
}

/** «Importar cierre» (y «Guardar cambios» de un borrador, R2): archivo JSON o texto, más los archivos de sus evidencias. */
function at_av_accion_importar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$texto = '';
	$f = $_FILES['cierre_json'] ?? null;
	if (is_array($f) && (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($f['size'] ?? 0) <= 1048576) {
		$tmp = (string) ($f['tmp_name'] ?? '');
		if ($tmp !== '' && (is_uploaded_file($tmp) || (defined('AT_AV_PRUEBAS') && AT_AV_PRUEBAS === true && is_file($tmp)))) {
			$texto = (string) file_get_contents($tmp);
		}
	}
	if ($texto === '') {
		$texto = (string) wp_unslash($_POST['cierre_texto'] ?? '');
	}
	$datos = json_decode($texto, true);
	if (!is_array($datos)) {
		at_pt_guardar_detalles($id, ['El archivo no es un JSON válido (' . json_last_error_msg() . ').']);
		at_pt_volver($crm, $id, 'av_import_error');
	}
	$v = at_av_validar_import($datos, (string) $fila->codigo, at_pt_payload($fila), $avance);
	if (!$v['ok']) {
		at_pt_guardar_detalles($id, $v['errores']);
		at_pt_volver($crm, $id, 'av_import_error');
	}
	$ids = array_column($avance['cierres'], 'id');
	do {
		$nuevo_id = 'c' . strtolower(wp_generate_password(6, false, false));
	} while (in_array($nuevo_id, $ids, true));
	$r = at_av_aplicar_import($avance, $v, $nuevo_id, current_time('mysql'));
	$nuevo = $r['avance'];
	$avisos = $v['avisos'];
	$borrar = $r['archivos'];
	$subidos = at_en_archivos_subidos((array) ($_FILES['archivos'] ?? []));
	if ($subidos) {
		$a = at_av_asignar_archivos($nuevo, $r['cierre_id'], $subidos, $id);
		$nuevo = $a['avance'];
		$avisos = array_merge($avisos, $a['errores'], $a['avisos']);
		$borrar = array_merge($borrar, $a['archivos']);
	}
	at_av_guardar_o_volver($id, $crm, $nuevo);
	at_av_borrar_archivos($id, $borrar);
	$faltan = at_av_faltan_archivos($nuevo, $r['cierre_id']);
	if ($faltan) {
		$avisos[] = 'Faltan por subir: ' . implode(', ', $faltan) . '.';
	}
	at_pt_guardar_detalles($id, $avisos);
	// Bloques, cambios, necesitamos y alcance pueden haber cambiado y esos sí se ven (R4: solo si hay algo publicado).
	at_av_refrescar($id);
	at_pt_volver($crm, $id, $faltan ? 'av_importado_faltan' : 'av_importado');
}

/** Sube el archivo o el póster de una evidencia («subir después, uno por uno»). */
function at_av_accion_subir(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$eid = (string) wp_unslash($_POST['evidencia_id'] ?? '');
	$campo = ($_POST['campo'] ?? '') === 'poster' ? 'poster' : 'archivo';
	$f = $_FILES['archivo'] ?? null;
	if (!preg_match('/^c[a-z0-9]{6}-[a-z0-9-]{1,40}$/', $eid) || !isset($avance['evidencias'][$eid]) || !is_array($f)) {
		at_pt_volver($crm, $id, 'av_sin_cierre');
	}
	$r = at_av_subir_una($avance, $eid, $campo, $f, $id);
	if ($r['error'] !== '') {
		at_pt_guardar_detalles($id, [$r['error']]);
		at_pt_volver($crm, $id, 'av_subida_error');
	}
	at_av_guardar_o_volver($id, $crm, $r['avance']);
	at_av_borrar_archivos($id, $r['archivos']);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_subido');
}

function at_av_accion_publicar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_cambiar_estado_cierre($avance, at_av_cierre_post(), 'publicado', current_time('mysql'));
	if (is_string($r)) {
		at_pt_guardar_detalles($id, [$r]);
		at_pt_volver($crm, $id, $r === 'Ese cierre no existe.' ? 'av_sin_cierre' : 'av_no_publicable');
	}
	at_av_guardar_o_volver($id, $crm, $r);
	$motivo = at_av_refrescar($id);
	at_pt_volver($crm, $id, $motivo === '' ? 'av_publicado' : 'av_publicado_sin_render');
}

/** Vuelve a borrador. La presentación vieja todavía muestra ese cierre: se deja de mostrar ya (Review Focus 2) y, si
 *  quedan otros publicados, se rehace sin él. */
function at_av_accion_borrador(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_cambiar_estado_cierre($avance, at_av_cierre_post(), 'borrador', current_time('mysql'));
	if (is_string($r)) {
		at_pt_guardar_detalles($id, [$r]);
		at_pt_volver($crm, $id, 'av_sin_cierre');
	}
	$r['render'] = at_av_publicados($r) ? array_merge(at_av_registro_vacio(), ['estado' => 'pendiente', 'at' => current_time('mysql')]) : at_av_registro_vacio();
	at_av_guardar_o_volver($id, $crm, $r);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_borrador');
}

function at_av_accion_quitar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_quitar_cierre($avance, at_av_cierre_post());
	if (is_string($r)) {
		at_pt_guardar_detalles($id, [$r]);
		at_pt_volver($crm, $id, 'av_sin_cierre');
	}
	at_av_guardar_o_volver($id, $crm, $r['avance']);
	at_av_borrar_archivos($id, $r['archivos']);
	at_pt_volver($crm, $id, 'av_quitado');
}

function at_av_accion_bloques(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$errores = [];
	$bloques = at_av_bloques_de_post(wp_unslash($_POST['bloques'] ?? []), at_pt_payload($fila), $errores);
	if ($errores) {
		at_pt_guardar_detalles($id, $errores);
		at_pt_volver($crm, $id, 'av_listas_error');
	}
	$avance['bloques'] = $bloques;
	at_av_guardar_o_volver($id, $crm, $avance);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_bloques');
}

function at_av_accion_listas(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$errores = [];
	$cambios = at_av_cambios_de_texto((string) wp_unslash($_POST['cambios_plan'] ?? ''), at_av_bloques_plan(at_pt_payload($fila)), $avance['cambios_plan'], $errores);
	$necesitamos = at_av_necesitamos_de_texto((string) wp_unslash($_POST['necesitamos'] ?? ''), $avance['necesitamos'], current_time('Y-m-d'), $errores);
	$fase1 = at_av_lista_de_texto((string) wp_unslash($_POST['alcance_fase1'] ?? ''), 'fase1', $errores);
	$mensual = at_av_lista_de_texto((string) wp_unslash($_POST['alcance_mensual'] ?? ''), 'mensual', $errores);
	if ($errores) {
		at_pt_guardar_detalles($id, $errores);
		at_pt_volver($crm, $id, 'av_listas_error');
	}
	$avance['cambios_plan'] = $cambios;
	$avance['necesitamos'] = $necesitamos;
	$avance['alcance'] = ['fase1' => $fase1, 'mensual' => $mensual];
	at_av_guardar_o_volver($id, $crm, $avance);
	at_av_refrescar($id);
	at_pt_volver($crm, $id, 'av_listas');
}

function at_av_accion_reintentar(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	if (!at_av_publicados($avance)) {
		at_pt_volver($crm, $id, 'av_sin_publicados');
	}
	at_pt_volver($crm, $id, at_av_pedir_render($id, false) === '' ? 'av_reintento' : 'av_reintento_fallo');
}

function at_av_accion_previa(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	if (!$avance['cierres']) {
		at_pt_volver($crm, $id, 'av_sin_cierres');
	}
	at_pt_volver($crm, $id, at_av_pedir_render($id, true) === '' ? 'av_previa' : 'av_reintento_fallo');
}

/** Campos ocultos de cada formulario de la sección (acción, plan, ficha, nonce y extras). */
function at_av_ocultos(string $accion, int $plan_id, int $crm_id, array $extra = []): string {
	$h = '<input type="hidden" name="action" value="' . esc_attr($accion) . '">'
		. '<input type="hidden" name="plan_id" value="' . $plan_id . '">'
		. '<input type="hidden" name="crm_id" value="' . $crm_id . '">'
		. '<input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('at_pt_plan_' . $plan_id)) . '">';
	foreach ($extra as $k => $v) {
		$h .= '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
	}
	return $h;
}

/** Botón dentro de su propio formulario (con confirmación opcional). */
function at_av_boton(string $accion, int $plan_id, int $crm_id, string $texto, array $extra = [], string $confirmar = '', bool $deshabilitado = false): string {
	$onsubmit = $confirmar !== '' ? ' onsubmit="return confirm(' . esc_attr(wp_json_encode($confirmar, JSON_UNESCAPED_UNICODE)) . ');"' : '';
	return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="at-av-form-boton"' . $onsubmit . '>'
		. at_av_ocultos($accion, $plan_id, $crm_id, $extra)
		. '<button type="submit" class="button"' . ($deshabilitado ? ' disabled' : '') . '>' . esc_html($texto) . '</button></form>';
}

/** Sección «📈 Avance» (solo con el plan enviado; la llama at_pt_render_plan). */
function at_av_render_seccion(object $fila, int $crm_id): void {
	$id = (int) $fila->id;
	$codigo = (string) $fila->codigo;
	$accion = esc_url(admin_url('admin-post.php'));
	echo '<section class="at-av" id="at-av"><h3>📈 Avance del proyecto · «Estado del proyecto»</h3>';
	if (!at_av_columna_lista()) {
		echo '<div class="notice notice-warning inline"><p>Falta preparar la base para el avance: vuelve a entrar al escritorio de WordPress (la migración corre sola) y recarga esta pestaña.</p></div></section>';
		return;
	}
	$av = at_av_leer($fila);
	$plan = at_pt_payload($fila);
	$publicados = at_av_publicados($av);
	$publica = at_pt_url_ver_estado(home_url(), $codigo);

	// Presentación para el cliente y vista previa.
	$etiquetas = ['' => 'Todavía no se genera', 'pendiente' => 'Generándose', 'listo' => 'Lista', 'error' => 'Con error'];
	$reg = $av['render'];
	echo '<div class="at-av-render"><p><strong>Presentación para el cliente:</strong> ' . esc_html($etiquetas[$reg['estado']] ?? $reg['estado'])
		. ($reg['at'] !== '' ? ' · ' . esc_html($reg['at']) : '') . '</p>';
	if ($reg['nota'] !== '') {
		echo '<p class="description">' . esc_html($reg['nota']) . '</p>';
	}
	echo '<p>El cliente la ve en <a href="' . esc_url($publica) . '" target="_blank" rel="noopener">' . esc_html($publica) . '</a>'
		. ($publicados ? '' : ' (mientras no publiques un cierre, ve «Pronto verás aquí el avance de tu proyecto»)') . '.</p><p>';
	if ($reg['view_url'] !== '') {
		echo '<a class="button" href="' . esc_url($reg['view_url']) . '" target="_blank" rel="noopener">👁️ Ver la presentación publicada</a> ';
		if ($reg['pdf_url'] !== '') {
			echo '<a class="button" href="' . esc_url($reg['pdf_url']) . '" target="_blank" rel="noopener">📄 PDF</a> ';
		}
	}
	if ($publicados) {
		echo at_av_boton('at_av_reintentar', $id, $crm_id, '🔄 Rehacer la presentación');
	}
	if ($av['cierres']) {
		echo at_av_boton('at_av_previa', $id, $crm_id, '👁️ Pedir vista previa con borradores');
	}
	echo '</p>';
	$pr = $av['render_previa'];
	if ($pr['estado'] === 'listo' && $pr['view_url'] !== '') {
		echo '<p><a href="' . esc_url($pr['view_url']) . '" target="_blank" rel="noopener">Abrir la vista previa con borradores</a> <span class="description">(solo para ti; el cliente no la ve)</span></p>';
	} elseif (in_array($pr['estado'], ['pendiente', 'error'], true)) {
		echo '<p class="description">Vista previa: ' . esc_html($pr['estado'] === 'pendiente' ? 'generándose…' : 'con error. ' . $pr['nota']) . '</p>';
	}
	echo '</div>';

	// Cierres.
	echo '<h4>Cierres de hito</h4>';
	if (!$av['cierres']) {
		echo '<p class="description">Todavía no hay cierres. Impórtalos abajo con «Importar cierre».</p>';
	} else {
		echo '<table class="widefat striped at-av-cierres"><thead><tr><th>Cierre</th><th>Bloques</th><th>Listo el</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
		foreach ($av['cierres'] as $c) {
			$cid = (string) $c['id'];
			$pub = ($c['estado'] ?? '') === 'publicado';
			$faltan = at_av_faltan_archivos($av, $cid);
			echo '<tr><td><strong>' . esc_html((string) $c['titulo']) . '</strong></td><td>' . esc_html(implode(', ', (array) $c['bloques'])) . '</td><td>'
				. esc_html(at_pt_panel_fecha((string) $c['fecha_real'], true)) . '</td><td>'
				. ($pub ? '<span class="at-av-chip is-pub">Publicado</span>' : '<span class="at-av-chip">Borrador</span>')
				. ($faltan ? '<br><small>Faltan archivos: ' . esc_html(implode(', ', $faltan)) . '</small>' : '') . '</td><td>';
			if ($pub) {
				echo at_av_boton('at_av_borrador', $id, $crm_id, '↩️ Volver a borrador', ['cierre_id' => $cid], 'El cliente dejará de ver este cierre. ¿Volver a borrador?');
			} else {
				echo at_av_boton('at_av_publicar', $id, $crm_id, '✅ Publicar', ['cierre_id' => $cid], 'El cliente verá este cierre en «Estado del proyecto». No se le envía nada. ¿Publicar?', (bool) $faltan);
				echo at_av_boton('at_av_quitar', $id, $crm_id, '🗑️ Quitar', ['cierre_id' => $cid], '¿Quitar este cierre y sus archivos?');
			}
			echo '</td></tr><tr class="at-av-detalle"><td colspan="5"><details><summary>Archivos y edición</summary>';
			foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
				$e = $av['evidencias'][$eid] ?? null;
				if (!is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
					continue;
				}
				$campos = $e['tipo'] === 'video' ? ['archivo' => 'Video', 'poster' => 'Póster (opcional)'] : ['archivo' => 'Imagen'];
				foreach ($campos as $campo => $nombre) {
					$url = (string) ($campo === 'poster' ? ($e['poster'] ?? '') : ($e['url'] ?? ''));
					echo '<form method="post" action="' . $accion . '" enctype="multipart/form-data" class="at-av-subir">'
						. at_av_ocultos('at_av_subir', $id, $crm_id, ['evidencia_id' => $eid, 'campo' => $campo])
						. '<span>' . esc_html((string) $e['titulo']) . ' · ' . esc_html($nombre) . ': '
						. ($url !== '' ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">subido</a>' : '<strong>falta</strong>')
						. ($campo === 'archivo' && ($e['archivo'] ?? '') !== '' ? ' <code>' . esc_html((string) $e['archivo']) . '</code>' : '') . '</span> '
						. '<input type="file" name="archivo" required accept="' . ($campo === 'archivo' && $e['tipo'] === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp') . '"> '
						. '<button type="submit" class="button">Subir</button></form>';
				}
			}
			if ($pub) {
				echo '<p class="description">Para editar un cierre publicado, primero vuelve a borrador.</p>';
			} else {
				$json = (string) wp_json_encode(at_av_exportar_cierre($av, $cid, $codigo), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				echo '<form method="post" action="' . $accion . '" class="at-av-editar">' . at_av_ocultos('at_av_importar', $id, $crm_id)
					. '<label>Editar el cierre (formato at-cierre-v1)<textarea name="cierre_texto" rows="14" class="large-text code">' . esc_textarea($json) . '</textarea></label>'
					. '<p class="description">Se valida igual que «Importar cierre». Las evidencias que mantienen su nombre de archivo conservan el que ya subiste.</p>'
					. '<p><button type="submit" class="button">💾 Guardar cambios del cierre</button></p></form>';
			}
			echo '</details></td></tr>';
		}
		echo '</tbody></table>';
	}

	// Importar cierre.
	echo '<h4>Importar cierre</h4><form method="post" action="' . $accion . '" enctype="multipart/form-data" class="at-av-importar">'
		. at_av_ocultos('at_av_importar', $id, $crm_id)
		. '<p><label>Archivo del cierre (.json, formato at-cierre-v1) <input type="file" name="cierre_json" accept=".json,application/json"></label></p>'
		. '<p><label>…o pega su contenido<textarea name="cierre_texto" rows="5" class="large-text code"></textarea></label></p>'
		. '<p><label>Archivos de las evidencias (capturas, videos y pósters, con el nombre que dice el archivo) <input type="file" name="archivos[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label></p>'
		. '<p class="description">Se guarda como borrador: el cliente no ve nada hasta que lo publiques. Formato y ejemplo: Docs/METODO_AT/ESTADO-DEL-PROYECTO.md.</p>'
		. '<p><button type="submit" class="button button-primary">📥 Guardar como borrador</button></p></form>';

	// Bloques.
	$opciones = ['por_venir' => 'Por venir', 'en_curso' => 'En curso', 'listo' => 'Listo'];
	echo '<h4>Bloques del plan</h4><form method="post" action="' . $accion . '" class="at-av-bloques">' . at_av_ocultos('at_av_bloques', $id, $crm_id)
		. '<table class="widefat striped"><thead><tr><th>Bloque</th><th>Planificado</th><th>Estado</th><th>Inicio real</th><th>Fin real</th></tr></thead><tbody>';
	foreach (at_av_estado_bloques($plan, $av) as $i => $b) {
		$g = is_array($av['bloques'][$b['nombre']] ?? null) ? $av['bloques'][$b['nombre']] : ['estado' => 'por_venir', 'inicio_real' => '', 'fin_real' => ''];
		$sel = '';
		foreach ($opciones as $k => $t) {
			$sel .= '<option value="' . $k . '"' . selected((string) ($g['estado'] ?? 'por_venir'), $k, false) . '>' . $t . '</option>';
		}
		$cubierto = $b['estado'] === 'listo' && ($g['estado'] ?? '') !== 'listo' ? '<br><small>Listo por un cierre publicado</small>' : '';
		echo '<tr><td>' . esc_html($b['nombre']) . '<input type="hidden" name="bloques[' . $i . '][nombre]" value="' . esc_attr($b['nombre']) . '"></td>'
			. '<td>' . esc_html(at_pt_panel_fecha($b['plan_desde']) . ' – ' . at_pt_panel_fecha($b['plan_hasta'])) . '</td>'
			. '<td><select name="bloques[' . $i . '][estado]">' . $sel . '</select>' . $cubierto . '</td>'
			. '<td><input type="date" name="bloques[' . $i . '][inicio_real]" value="' . esc_attr((string) ($g['inicio_real'] ?? '')) . '"></td>'
			. '<td><input type="date" name="bloques[' . $i . '][fin_real]" value="' . esc_attr((string) ($g['fin_real'] ?? '')) . '"></td></tr>';
	}
	echo '</tbody></table><p><button type="submit" class="button">💾 Guardar bloques</button></p></form>';

	// Cambios de plan, necesitamos y alcance.
	$bp = at_av_bloques_plan($plan);
	$fase1_def = implode("\n", array_values(array_unique(array_filter(array_column($bp, 'entregable'), 'strlen'))));
	$mensual_def = implode("\n", array_map('strval', (array) ($plan['soporte']['mensuales'] ?? [])));
	echo '<h4>Cambios de plan, «necesitamos de ti» y alcance</h4><form method="post" action="' . $accion . '" class="at-av-listas">' . at_av_ocultos('at_av_listas', $id, $crm_id)
		. '<label class="at-pt-campo">Cambios de plan acordados (uno por línea: «2026-10-13 | título | detalle»)<textarea name="cambios_plan" rows="3" class="large-text">' . esc_textarea(at_av_texto_de_cambios($av['cambios_plan'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Lo que necesitamos del cliente (uno por línea; «✓ » al inicio = resuelto)<textarea name="necesitamos" rows="3" class="large-text">' . esc_textarea(at_av_texto_de_necesitamos($av['necesitamos'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Qué incluye la Fase 1 (uno por línea; vacío = los entregables del plan)<textarea name="alcance_fase1" rows="3" class="large-text" placeholder="' . esc_attr($fase1_def) . '">' . esc_textarea(implode("\n", $av['alcance']['fase1'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Qué incluye el servicio mensual (uno por línea; vacío = los mensuales del plan)<textarea name="alcance_mensual" rows="2" class="large-text" placeholder="' . esc_attr($mensual_def) . '">' . esc_textarea(implode("\n", $av['alcance']['mensual'])) . '</textarea></label>'
		. '<p><button type="submit" class="button">💾 Guardar</button></p></form>';
	echo '</section>';
}
