<?php
/**
 * Estado del proyecto (Etapa 2): sección «📈 Avance» de la pestaña «🗓️ Plan de trabajo» (solo planes enviados) y sus
 * acciones admin-post (spec §5). Cada acción: manage_options + nonce del plan (at_pt_accion_plan) y vuelta a la ficha
 * con pt_msg; los detalles (errores de la importación, avisos) van con at_pt_guardar_detalles. Lo que cambia algo
 * visible para el cliente rehace «Estado del proyecto» si hay al menos un cierre publicado (R4). Publicar no envía nada (D5).
 * Importar un borrador NUNCA pide la presentación: el cliente no ve un borrador, y lo demás que traiga el archivo (bloques,
 * cambios de plan, «necesitamos», alcance) se ve en la próxima presentación.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Avisos de la sección: se suman a at_pt_mensajes_panel(). */
function at_av_mensajes(): array {
	return [
		'av_importado'            => ['ok', 'Cierre guardado como borrador. El cliente no ve este cierre hasta que lo publiques.'],
		'av_importado_faltan'     => ['aviso', 'Cierre guardado como borrador, pero faltan archivos por subir (detalle abajo). No se puede publicar hasta subirlos.'],
		'av_import_error'         => ['error', 'No se guardó el cierre: el archivo tiene errores (detalle abajo). No cambió nada.'],
		'av_form_error'           => ['error', 'No se guardó el cierre: revisa el detalle abajo. Lo que escribiste sigue en el formulario; los archivos hay que adjuntarlos de nuevo.'],
		'av_subido'               => ['ok', 'Archivo subido.'],
		'av_subida_error'         => ['error', 'No se subió el archivo (detalle abajo).'],
		'av_publicado'            => ['ok', 'Cierre publicado. Estamos rehaciendo «Estado del proyecto»: queda listo en unos minutos. Al cliente no se le envió nada.'],
		'av_publicado_sin_render' => ['aviso', 'Cierre publicado, pero n8n no recibió el pedido de la presentación. Usa «Rehacer la presentación» en un rato.'],
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
		'av_correo_prueba'          => ['ok', 'Te enviamos la prueba del correo de cierre a tu correo. Al cliente no le llegó nada.'],
		'av_correo_prueba_sin_pdf'  => ['aviso', 'Te enviamos la prueba, pero sin el PDF (detalle abajo): la presentación todavía no está lista.'],
		'av_correo_cliente'         => ['ok', 'Correo de cierre enviado al cliente. Quedó en el historial del CRM.'],
		'av_correo_cliente_sin_pdf' => ['aviso', 'Correo de cierre enviado al cliente, pero sin el PDF (detalle abajo): lleva el enlace al estado.'],
		'av_correo_cliente_aviso'   => ['aviso', 'Correo de cierre enviado al cliente, pero no quedó anotado (detalle abajo). No lo reenvíes sin revisar.'],
		'av_correo_no_publicado'    => ['error', 'Primero publica el cierre: solo se envía un cierre publicado.'],
		'av_correo_render_no_listo' => ['error', 'Todavía no está lista la presentación con este cierre. Espera el «Lista» (o usa «Rehacer la presentación») y vuelve a enviar.'],
		'av_correo_sin_correo'      => ['error', 'No hay a qué correo enviarlo: la ficha del cliente no tiene correo (o falta el correo principal en «Ajustes del cierre», para la prueba).'],
		'av_correo_interno'         => ['error', 'No se envió: hay un enlace al servidor interno (detalle abajo). El correo de Hostinger lo rechazaría.'],
		'av_correo_ya_enviado'      => ['aviso', 'Este cierre ya se envió al cliente. Para mandarlo otra vez usa «Reenviar» y confírmalo.'],
		'av_correo_enviando'        => ['aviso', 'Ese correo ya se está enviando. Recarga en un minuto.'],
		'av_correo_correo_fallo'    => ['error', 'El servidor de correo no aceptó el envío (detalle abajo, si hay). Inténtalo de nuevo en un rato.'],
		'av_correo_sin_cierre'      => ['error', 'Ese cierre no existe.'],
		'av_correo_sin_plan'        => ['error', 'Ese plan no existe.'],
		'av_whatsapp_sin_telefono'  => ['error', 'La ficha del cliente no tiene un teléfono para WhatsApp.'],
	];
}

add_action('admin_post_at_av_importar', 'at_av_accion_importar');
add_action('admin_post_at_av_nuevo', 'at_av_accion_nuevo');
add_action('admin_post_at_av_subir', 'at_av_accion_subir');
add_action('admin_post_at_av_publicar', 'at_av_accion_publicar');
add_action('admin_post_at_av_borrador', 'at_av_accion_borrador');
add_action('admin_post_at_av_quitar', 'at_av_accion_quitar');
add_action('admin_post_at_av_bloques', 'at_av_accion_bloques');
add_action('admin_post_at_av_listas', 'at_av_accion_listas');
add_action('admin_post_at_av_reintentar', 'at_av_accion_reintentar');
add_action('admin_post_at_av_previa', 'at_av_accion_previa');
add_action('admin_post_at_av_correo', 'at_av_accion_correo');
add_action('admin_post_at_av_whatsapp', 'at_av_accion_whatsapp');

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

/**
 * Guarda el avance que armó una acción sin borrar lo que otro proceso escribió desde que la acción lo leyó: se relee
 * la fila, se pasan al avance a guardar las marcas de envío (correo y WhatsApp) de cada cierre que está en los dos y
 * los dos registros de render (salvo $render_propio: la acción los decidió ella), y se guarda solo si la fila sigue
 * siendo la releída (at_av_guardar_si_igual), con hasta 3 intentos. Si no se logra, vuelve a la ficha con el error.
 */
function at_av_guardar_o_volver(int $id, int $crm, array $avance, bool $render_propio = false): void {
	for ($i = 0; $i < 3; $i++) {
		$f = at_pt_plan($id);
		if (!$f) {
			break;
		}
		$antes = (string) $f->avance; // tal cual salió de la base: es la condición del guardado
		$fresco = at_av_leer($f);
		$nuevo = at_av_con_lo_ajeno($avance, $fresco, $render_propio);
		if (at_av_normalizar($nuevo) === $fresco || at_av_guardar_si_igual($id, $antes, $nuevo)) {
			return; // nada que escribir (ya está así) o guardado
		}
	}
	at_pt_volver($crm, $id, 'av_error_guardar');
}

/** El avance $avance con lo que escriben otros procesos tomado del $fresco: envíos de cada cierre (por id) y, salvo $render_propio, los renders. */
function at_av_con_lo_ajeno(array $avance, array $fresco, bool $render_propio): array {
	$vigentes = [];
	foreach ($fresco['cierres'] as $c) {
		$vigentes[(string) ($c['id'] ?? '')] = $c;
	}
	foreach ($avance['cierres'] as $k => $c) {
		$v = $vigentes[(string) ($c['id'] ?? '')] ?? null;
		if ($v === null) {
			continue;
		}
		foreach (['correo', 'whatsapp'] as $campo) {
			if (array_key_exists($campo, $v)) {
				$avance['cierres'][$k][$campo] = $v[$campo];
			} else {
				unset($avance['cierres'][$k][$campo]);
			}
		}
	}
	if (!$render_propio) {
		$avance['render'] = $fresco['render'];
		$avance['render_previa'] = $fresco['render_previa'];
	}
	return $avance;
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
	$pesado = is_array($f) && (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($f['size'] ?? 0) > 1048576;
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
		at_pt_guardar_detalles($id, [$pesado ? 'El archivo pesa más de 1 MB.' : 'El archivo no es un JSON válido (' . json_last_error_msg() . ').']);
		at_pt_volver($crm, $id, 'av_import_error');
	}
	at_pt_volver($crm, $id, at_av_guardar_cierre($fila, $crm, $avance, $datos, at_en_archivos_subidos((array) ($_FILES['archivos'] ?? [])), 'av_import_error'));
}

/**
 * Valida y guarda un cierre en borrador (lo usan «Importar cierre» y «Nuevo cierre»). Con errores (los de la validación
 * más $errores_previos) vuelve a la ficha con $msg_error y no guarda nada. Si sale bien deja los avisos en los detalles
 * y devuelve el mensaje de vuelta. $subidos: archivos con su nombre ya igual al de la evidencia.
 */
function at_av_guardar_cierre(object $fila, int $crm, array $avance, array $datos, array $subidos, string $msg_error, array $errores_previos = []): string {
	$id = (int) $fila->id;
	$v = at_av_validar_import($datos, (string) $fila->codigo, at_pt_payload($fila), $avance);
	$errores = array_merge($errores_previos, $v['ok'] ? [] : $v['errores']);
	if ($errores) {
		at_pt_guardar_detalles($id, $errores);
		at_pt_volver($crm, $id, $msg_error);
	}
	// Un cierre solo puede enlazar un entregable del mismo cliente del plan (spec §9).
	$ent_id = (int) ($v['cierre']['entregable_id'] ?? 0);
	if ($ent_id > 0 && at_av_entregable_del_plan($fila, $ent_id) === '') {
		at_pt_guardar_detalles($id, ["El entregable {$ent_id} no existe o no es de este cliente."]);
		at_pt_volver($crm, $id, $msg_error);
	}
	$ids = array_column($avance['cierres'], 'id');
	do {
		$nuevo_id = 'c' . strtolower(wp_generate_password(6, false, false));
	} while (in_array($nuevo_id, $ids, true));
	$r = at_av_aplicar_import($avance, $v, $nuevo_id, current_time('mysql'));
	$nuevo = $r['avance'];
	$avisos = $v['avisos'];
	$borrar = $r['archivos'];
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
	// Importar nunca rehace la presentación del cliente (D5): lo general del archivo se ve en la próxima presentación.
	if (($v['bloques'] || $v['cambios_plan'] !== null || $v['necesitamos'] !== null || $v['alcance'] !== null) && at_av_publicados($nuevo)) {
		$avisos[] = 'El archivo también cambió bloques, cambios de plan, «necesitamos» o alcance: el cliente los verá cuando publiques un cierre o uses «Rehacer la presentación».';
	}
	at_pt_guardar_detalles($id, $avisos);
	return $faltan ? 'av_importado_faltan' : 'av_importado';
}

function at_av_clave_form(): string {
	return 'at_av_form_' . get_current_user_id();
}

/**
 * «Nuevo cierre» (y «Editar» un borrador) desde el formulario del panel: arma el archivo at-cierre-v1 con
 * at_av_cierre_de_formulario y lo guarda igual que una importación. Lo escrito queda 30 minutos para volver a mostrarlo
 * si algo falla (los archivos no: el navegador no los conserva). Al editar con otro título, el cierre se renombra antes
 * de validar para que se reemplace a sí mismo (mismo id y mismos archivos) en vez de crear otro.
 */
function at_av_accion_nuevo(): void {
	[$fila, $crm, $avance] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$p = wp_unslash($_POST['nc'] ?? []);
	$p = is_array($p) ? $p : [];
	$editar = at_av_cierre_post();
	set_transient(at_av_clave_form(), ['plan_id' => $id, 'p' => $p, 'editar' => $editar], 30 * MINUTE_IN_SECONDS);
	$base = null;
	if ($editar !== '') {
		$k = array_search($editar, array_column($avance['cierres'], 'id'), true);
		if ($k === false) {
			at_pt_guardar_detalles($id, ['Ese cierre ya no existe.']);
			at_pt_volver($crm, $id, 'av_form_error');
		}
		if (($avance['cierres'][$k]['estado'] ?? '') === 'publicado') {
			at_pt_guardar_detalles($id, ['Ese cierre está publicado: vuelve a borrador para editarlo.']);
			at_pt_volver($crm, $id, 'av_form_error');
		}
		$base = at_av_exportar_cierre($avance, $editar, (string) $fila->codigo);
		$titulo = trim(is_string($p['titulo'] ?? null) ? $p['titulo'] : '');
		if ($titulo !== '' && at_av_clave($titulo) !== at_av_clave((string) $avance['cierres'][$k]['titulo'])) {
			foreach ($avance['cierres'] as $j => $c) {
				if ($j !== $k && at_av_clave((string) ($c['titulo'] ?? '')) === at_av_clave($titulo)) {
					at_pt_guardar_detalles($id, ["Ya hay otro cierre llamado «{$c['titulo']}»."]);
					at_pt_volver($crm, $id, 'av_form_error');
				}
			}
			$avance['cierres'][$k]['titulo'] = $titulo;
		}
	}
	// Archivos de cada fila: nc_archivo[fila] y nc_poster[fila].
	$originales = [];
	$archivos = [];
	foreach (['archivo' => 'nc_archivo', 'poster' => 'nc_poster'] as $campo => $input) {
		$f = $_FILES[$input] ?? null;
		if (!is_array($f) || !is_array($f['name'] ?? null)) {
			continue;
		}
		foreach ($f['name'] as $i => $nombre) {
			$error = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
			if ($error === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			$originales[$i][$campo] = (string) $nombre;
			$archivos[$i][$campo] = ['name' => (string) $nombre, 'type' => (string) ($f['type'][$i] ?? ''), 'tmp_name' => (string) ($f['tmp_name'][$i] ?? ''), 'error' => $error, 'size' => (int) ($f['size'][$i] ?? 0)];
		}
	}
	$r = at_av_cierre_de_formulario($p, (string) $fila->codigo, $originales, $base);
	$subidos = [];
	foreach ($r['nombres'] as $i => $ns) {
		foreach ($ns as $campo => $nuevo) {
			$subidos[] = array_merge($archivos[$i][$campo], ['name' => $nuevo]);
		}
	}
	$msg = at_av_guardar_cierre($fila, $crm, $avance, $r['datos'], $subidos, 'av_form_error', $r['errores']);
	delete_transient(at_av_clave_form());
	at_pt_volver($crm, $id, $msg);
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
	// Un cierre publicado lo está viendo el cliente: subir ahora reemplazaría y borraría su archivo en el acto.
	foreach ($avance['cierres'] as $c) {
		$refs = array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? []));
		if (($c['estado'] ?? '') === 'publicado' && in_array($eid, $refs, true)) {
			at_pt_guardar_detalles($id, ['Ese cierre está publicado: vuelve a borrador para cambiar sus archivos.']);
			at_pt_volver($crm, $id, 'av_subida_error');
		}
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
	// Solo con el cierre ya guardado como publicado: un intento fallido no deja nada en el historial del CRM.
	$pub = at_av_cierre_de_avance($r, at_av_cierre_post());
	at_pt_historial($fila, 'hito_cierre_publicado', 'Cierre de hito publicado', 'Publicamos el cierre «' . (string) ($pub['titulo'] ?? '') . '» en el estado del proyecto. No se le envió nada al cliente.');
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
	// Si quedan publicados, la presentación queda «pendiente» y sin enlace viejo. Un pedido que de verdad está en curso
	// conserva su hora y su cola, para que el pedido de abajo se serialice con él (at_av_pedir_render).
	$en_curso = ($avance['render']['estado'] ?? '') === 'pendiente' ? ['pedido_at' => $avance['render']['pedido_at'], 'repetir' => $avance['render']['repetir']] : [];
	$r['render'] = at_av_publicados($r) ? array_merge(at_av_registro_vacio(), ['estado' => 'pendiente', 'at' => current_time('mysql')], $en_curso) : at_av_registro_vacio();
	at_av_guardar_o_volver($id, $crm, $r, true);
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

/**
 * «Enviarme una prueba» / «Enviar al cliente» / «Reenviar»: el envío lo hace at_av_enviar_cierre(). Todo detalle que
 * traiga (el enlace interno, el error del servidor, el PDF que faltó, un envío que no quedó anotado) va al aviso de la
 * ficha, que es lo que lee quien hace el clic; el mensaje dice qué pasó.
 */
function at_av_accion_correo(): void {
	[$fila, $crm] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$destino = ($_POST['destino'] ?? '') === 'cliente' ? 'cliente' : 'prueba';
	$mensaje = mb_substr(sanitize_textarea_field(wp_unslash($_POST['mensaje'] ?? '')), 0, 1500);
	$r = at_av_enviar_cierre($id, at_av_cierre_post(), $destino, $mensaje, ($_POST['reenviar'] ?? '') === '1');
	if (!$r['ok']) {
		if ($r['detalle'] !== '') {
			$prefijo = ['interno' => 'Enlace o texto interno: ', 'correo_fallo' => 'Error: '][$r['motivo']] ?? '';
			at_pt_guardar_detalles($id, [$prefijo . $r['detalle']]);
		}
		at_pt_volver($crm, $id, 'av_correo_' . $r['motivo']);
	}
	$lineas = [];
	if ($destino === 'cliente' && $r['sin_pdf'] !== '') {
		$lineas[] = 'Motivo: ' . $r['sin_pdf'] . '.';
	}
	if ($r['detalle'] !== '') {
		$lineas[] = $r['detalle']; // salió, pero no quedó anotado: que nadie lo reenvíe sin mirar
	}
	if ($destino !== 'cliente' && $r['sin_pdf'] !== '') {
		$lineas[] = 'Motivo: ' . $r['sin_pdf'] . '.'; // la prueba salió sin el PDF: que quien la revisa sepa por qué
	}
	if ($lineas) {
		at_pt_guardar_detalles($id, $lineas);
	}
	if ($r['detalle'] !== '') {
		at_pt_volver($crm, $id, 'av_correo_cliente_aviso');
	}
	if ($destino !== 'cliente' && $r['sin_pdf'] !== '') {
		at_pt_volver($crm, $id, 'av_correo_prueba_sin_pdf');
	}
	if ($destino === 'cliente' && $r['sin_pdf'] !== '') {
		at_pt_volver($crm, $id, 'av_correo_cliente_sin_pdf');
	}
	at_pt_volver($crm, $id, $destino === 'cliente' ? 'av_correo_cliente' : 'av_correo_prueba');
}

/** «Enviar por mi WhatsApp»: anota que se abrió y manda a wa.me (el formulario abre otra pestaña). */
function at_av_accion_whatsapp(): void {
	[$fila, $crm] = at_av_accion_inicio();
	$id = (int) $fila->id;
	$r = at_av_registrar_whatsapp_cierre($id, at_av_cierre_post());
	if (!$r['ok']) {
		at_pt_volver($crm, $id, $r['motivo'] === 'sin_telefono' ? 'av_whatsapp_sin_telefono' : 'av_correo_' . $r['motivo']);
	}
	if ($r['motivo'] === 'no_anotado') {
		at_pt_guardar_detalles($id, ['Abrimos el WhatsApp, pero no quedó anotado en el cierre. Revisa el historial antes de volver a enviarlo.']);
	}
	wp_redirect($r['url']); // wa.me no es del sitio: wp_safe_redirect lo cambiaría por el escritorio
	exit;
}

/** Fecha y hora de un envío («2026-10-12 10:30:00» → «12 oct 2026 10:30»). */
function at_av_fecha_envio(string $mysql): string {
	$dia = at_pt_panel_fecha(substr($mysql, 0, 10), true);
	return $dia === '' ? $mysql : $dia . (preg_match('/^\d{4}-\d{2}-\d{2} (\d{2}:\d{2})/', $mysql, $m) ? ' ' . $m[1] : '');
}

/** El video es de nuestro propio sitio (el de la carpeta de uploads o cualquier URL del sitio): solo esos se ofrecen para descargar. */
function at_av_url_propia(string $url): bool {
	$up = wp_upload_dir(null, false);
	foreach ([home_url('/'), trailingslashit((string) $up['baseurl'])] as $base) {
		if ($url !== '' && str_starts_with($url, $base)) {
			return true;
		}
	}
	return false;
}

/**
 * Bloque «📣 Avisar al cliente» de un cierre publicado: estado de los envíos, correo (prueba o al cliente, con mensaje
 * opcional), WhatsApp de Luis y descarga de los videos propios. Nada sale sin un clic. '' si el cierre no se puede enviar.
 */
function at_av_render_envio(object $fila, int $crm_id, array $av, string $cid): string {
	$p = at_av_preparar_envio_de($fila, $av, $cid);
	if (is_string($p)) {
		return '';
	}
	[, , $c, $r, $d, $url_estado] = $p;
	$id = (int) $fila->id;
	$env = at_av_envio_de_cierre($c);
	$co = $env['correo'];
	$cuando = fn(?string $v, string $verbo): string => $v === null ? 'aún no' : $verbo . ' el ' . at_av_fecha_envio($v);
	$enviado = $co['enviado_at'] !== null;
	$para = (string) ($d['client_email'] ?? '');

	$h = '<div class="at-av-envio"><strong>📣 Avisar al cliente</strong> <span class="description">No sale nada hasta que hagas clic.</span><ul>'
		. '<li>Correo al cliente: ' . esc_html($cuando($co['enviado_at'], 'enviado')) . ($enviado && $co['a'] !== '' ? ' a ' . esc_html($co['a']) : '') . '</li>'
		. '<li>Prueba: ' . esc_html($cuando($co['prueba_at'], 'enviada')) . '</li>'
		. '<li>WhatsApp: ' . esc_html($cuando($env['whatsapp']['abierto_at'], 'abierto')) . '</li></ul>';

	// Correo: la prueba va a nuestro correo; al cliente pide confirmación con su dirección (o la fecha del envío anterior).
	$confirmar = $enviado
		? 'Este cierre ya se envió el ' . at_av_fecha_envio((string) $co['enviado_at']) . ($co['a'] !== '' ? ' a ' . $co['a'] : '') . '. ¿Enviarlo otra vez al cliente?'
		: 'Se enviará el correo de cierre a ' . $para . '. ¿Enviar?';
	$h .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="at-av-correo">'
		. at_av_ocultos('at_av_correo', $id, $crm_id, ['cierre_id' => $cid])
		. '<textarea name="mensaje" rows="3" maxlength="1500" placeholder="Mensaje opcional para el cliente (va arriba del resumen)">' . esc_textarea($co['mensaje']) . '</textarea>'
		. ($enviado ? '<input type="hidden" name="reenviar" value="1">' : '')
		. '<p><button type="submit" name="destino" value="prueba" class="button">📧 Enviarme una prueba</button> ';
	if ($para === '') {
		$h .= '<button type="submit" name="destino" value="cliente" class="button button-primary" disabled>' . ($enviado ? '📨 Reenviar al cliente' : '📨 Enviar al cliente') . '</button> '
			. '<span class="description">El cliente no tiene correo en su ficha ni en el contrato.</span>';
	} else {
		$h .= '<button type="submit" name="destino" value="cliente" class="button button-primary" onclick="return confirm(' . esc_attr(wp_json_encode($confirmar, JSON_UNESCAPED_UNICODE)) . ');">'
			. ($enviado ? '📨 Reenviar al cliente' : '📨 Enviar al cliente') . '</button>';
	}
	$h .= '</p></form>';

	// WhatsApp: abre el WhatsApp de Luis (otra pestaña) con el texto ya escrito; el mismo texto queda abajo para copiarlo.
	if (at_av_url_whatsapp_cierre($fila, $cid) !== '') {
		$h .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" target="_blank" class="at-av-whatsapp">'
			. at_av_ocultos('at_av_whatsapp', $id, $crm_id, ['cierre_id' => $cid])
			. '<p><button type="submit" class="button">💬 Enviar por mi WhatsApp</button>'
			. (at_av_render_listo_para($av, $c) ? '' : ' <span class="description">Ojo: la presentación del cliente todavía no incluye este cierre; espera el «Lista» antes de mandarlo.</span>')
			. '</p>'
			. '<textarea readonly rows="3">' . esc_textarea(at_av_texto_whatsapp_cierre((string) ($d['client_name'] ?? ''), $r, $url_estado)) . '</textarea></form>';
	} else {
		$h .= '<p class="description">La ficha no tiene teléfono para WhatsApp.</p>';
	}

	// Videos: solo los de nuestro sitio se ofrecen para bajar (para mandarlos por WhatsApp).
	foreach ((array) ($r['videos'] ?? []) as $v) {
		if (at_av_url_propia((string) $v['url'])) {
			$h .= '<a class="button at-av-form-boton" href="' . esc_url((string) $v['url']) . '" download>⬇️ Descargar el video «' . esc_html((string) $v['titulo']) . '»</a> ';
		}
	}
	return $h . '</div>';
}

/** «ℹ️ ¿Para qué sirve?» bajo el título de una parte de la sección: se abre y se cierra sin JS. $html es texto fijo del código. */
function at_av_ayuda(string $html): string {
	return '<details class="at-av-ayuda"><summary>ℹ️ ¿Para qué sirve?</summary><p>' . $html . '</p></details>';
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

	// Cierres.
	echo '<details class="at-pt-seccion" id="at-av-cierres-' . $id . '" open><summary><h4>Cierres de hito</h4></summary><div class="at-pt-seccion-contenido">' . at_av_ayuda('Cada cierre cuenta un hito terminado: cómo estaba, qué hicimos y cómo quedó, con sus pruebas. Nace en <strong>borrador</strong> (solo lo ves tú). Con «✅ Publicar» entra a la presentación «Estado del proyecto» del cliente; no se le envía nada: el aviso (correo o WhatsApp) lo mandas tú, aparte, desde el cierre publicado. «↩️ Volver a borrador» lo saca de la presentación al tiro. Solo un borrador se edita o se quita.');
	if (!$av['cierres']) {
		echo '<p class="description">Todavía no hay cierres. Créalo abajo con «Nuevo cierre» (o impórtalo, si te entregaron el archivo .json).</p>';
	} else {
		echo '<div class="at-pt-tabla-scroll" role="region" aria-label="Cierres de hito" tabindex="0"><table class="widefat striped at-av-cierres"><thead><tr><th>Cierre</th><th>Bloques</th><th>Listo el</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
		foreach ($av['cierres'] as $c) {
			$cid = (string) $c['id'];
			$pub = ($c['estado'] ?? '') === 'publicado';
			$faltan = at_av_faltan_archivos($av, $cid);
			echo '<tr id="at-av-' . esc_attr($cid) . '"><td data-label="Cierre"><strong>' . esc_html((string) $c['titulo']) . '</strong></td><td data-label="Bloques">' . esc_html(implode(', ', (array) $c['bloques'])) . '</td><td data-label="Listo el">'
				. esc_html(at_pt_panel_fecha((string) $c['fecha_real'], true)) . '</td><td data-label="Estado">'
				. ($pub ? '<span class="at-av-chip is-pub">Publicado</span>' : '<span class="at-av-chip">Borrador</span>')
				. ($faltan ? '<br><small>Faltan archivos: ' . esc_html(implode(', ', $faltan)) . '</small>' : '') . '</td><td data-label="Acciones">';
			if ($pub) {
				echo at_av_boton('at_av_borrador', $id, $crm_id, '↩️ Volver a borrador', ['cierre_id' => $cid], 'El cliente dejará de ver este cierre. ¿Volver a borrador?');
			} else {
				$editar = add_query_arg(['page' => 'automatiza-crm-ficha', 'id' => $crm_id, 'pt' => $id, 'av_editar' => $cid], admin_url('admin.php')) . '#at-av-nuevo';
				echo '<a class="button at-av-form-boton" href="' . esc_url($editar) . '">✏️ Editar</a>';
				echo at_av_boton('at_av_publicar', $id, $crm_id, '✅ Publicar', ['cierre_id' => $cid], 'El cliente verá este cierre en «Estado del proyecto». No se le envía nada. ¿Publicar?', (bool) $faltan);
				echo at_av_boton('at_av_quitar', $id, $crm_id, '🗑️ Quitar', ['cierre_id' => $cid], '¿Quitar este cierre y sus archivos?');
			}
			echo '</td></tr><tr class="at-av-detalle"><td colspan="5">' . ($pub ? at_av_render_envio($fila, $crm_id, $av, $cid) : '') . '<details><summary>Archivos y edición avanzada (JSON)</summary>';
			foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
				$e = $av['evidencias'][$eid] ?? null;
				// Publicado: sin formularios de subida (el cliente lo está viendo; primero se vuelve a borrador).
				if ($pub || !is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
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
		echo '</tbody></table></div>';
	}
	echo '</div></details>';

	$formulario_guardado = get_transient(at_av_clave_form());
	$abrir_formulario = !empty($_GET['av_editar']) || (is_array($formulario_guardado) && (int) ($formulario_guardado['plan_id'] ?? 0) === $id);
	echo '<details class="at-pt-seccion" id="at-av-formulario-' . $id . '"' . ($abrir_formulario ? ' open' : '') . '><summary><h4>Nuevo/editar cierre e importar</h4></summary><div class="at-pt-seccion-contenido">';
	echo at_av_render_formulario($fila, $crm_id, $av, $plan);

	// Importar cierre (archivo .json que entrega una sesión de Claude o Codex).
	echo '<details class="at-av-importar-caja"><summary><strong>📥 Importar cierre</strong> desde un archivo .json (si te lo entregaron armado)</summary><form method="post" action="' . $accion . '" enctype="multipart/form-data" class="at-av-importar">'
		. at_av_ocultos('at_av_importar', $id, $crm_id)
		. '<p><label>Archivo del cierre (.json, formato at-cierre-v1) <input type="file" name="cierre_json" accept=".json,application/json"></label></p>'
		. '<p><label>…o pega su contenido<textarea name="cierre_texto" rows="5" class="large-text code"></textarea></label></p>'
		. '<p><label>Archivos de las evidencias (capturas, videos y pósters, con el nombre que dice el archivo) <input type="file" name="archivos[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"></label></p>'
		. '<p class="description">Se guarda como borrador: el cliente no ve nada hasta que lo publiques. Formato y ejemplo: Docs/METODO_AT/ESTADO-DEL-PROYECTO.md.</p>'
		. '<p><button type="submit" class="button">📥 Importar como borrador</button></p></form></details>';
	echo '</div></details>';

	// Bloques.
	$opciones = ['por_venir' => 'Por venir', 'en_curso' => 'En curso', 'listo' => 'Listo'];
	echo '<details class="at-pt-seccion" id="at-av-bloques-' . $id . '"><summary><h4>Bloques del plan</h4></summary><div class="at-pt-seccion-contenido">' . at_av_ayuda('Aquí marcas en qué va cada etapa del plan firmado. Es lo que el cliente ve en la barra de avance de «Estado del proyecto». <strong>Por venir</strong> = aún no empieza; <strong>En curso</strong> = estamos trabajando en ella; <strong>Listo</strong> = terminada. <strong>Inicio y fin real</strong> son las fechas en que de verdad empezó y terminó (pueden ser distintas de lo planificado, que no cambia). Un bloque con un cierre publicado se muestra «listo» solo, aunque aquí diga otra cosa. Si ya hay un cierre publicado, guardar rehace la presentación del cliente en unos minutos; si no, lo verá en la primera.') . '<form method="post" action="' . $accion . '" class="at-av-bloques">' . at_av_ocultos('at_av_bloques', $id, $crm_id)
		. '<div class="at-pt-tabla-scroll" role="region" aria-label="Bloques del plan" tabindex="0"><table class="widefat striped at-av-tabla-bloques"><thead><tr><th>Bloque</th><th>Planificado</th><th>Estado</th><th>Inicio real</th><th>Fin real</th></tr></thead><tbody>';
	foreach (at_av_estado_bloques($plan, $av) as $i => $b) {
		$g = is_array($av['bloques'][$b['nombre']] ?? null) ? $av['bloques'][$b['nombre']] : ['estado' => 'por_venir', 'inicio_real' => '', 'fin_real' => ''];
		$sel = '';
		foreach ($opciones as $k => $t) {
			$sel .= '<option value="' . $k . '"' . selected((string) ($g['estado'] ?? 'por_venir'), $k, false) . '>' . $t . '</option>';
		}
		$cubierto = $b['estado'] === 'listo' && ($g['estado'] ?? '') !== 'listo' ? '<br><small>Listo por un cierre publicado</small>' : '';
		echo '<tr><td data-label="Bloque">' . esc_html($b['nombre']) . '<input type="hidden" name="bloques[' . $i . '][nombre]" value="' . esc_attr($b['nombre']) . '"></td>'
			. '<td data-label="Planificado">' . esc_html(at_pt_panel_fecha($b['plan_desde']) . ' – ' . at_pt_panel_fecha($b['plan_hasta'])) . '</td>'
			. '<td data-label="Estado"><select aria-label="Estado de ' . esc_attr($b['nombre']) . '" name="bloques[' . $i . '][estado]">' . $sel . '</select>' . $cubierto . '</td>'
			. '<td data-label="Inicio real"><input aria-label="Inicio real de ' . esc_attr($b['nombre']) . '" type="date" name="bloques[' . $i . '][inicio_real]" value="' . esc_attr((string) ($g['inicio_real'] ?? '')) . '"></td>'
			. '<td data-label="Fin real"><input aria-label="Fin real de ' . esc_attr($b['nombre']) . '" type="date" name="bloques[' . $i . '][fin_real]" value="' . esc_attr((string) ($g['fin_real'] ?? '')) . '"></td></tr>';
	}
	echo '</tbody></table></div><p><button type="submit" class="button">💾 Guardar bloques</button></p></form></div></details>';

	// Cambios de plan, necesitamos y alcance.
	$bp = at_av_bloques_plan($plan);
	$fase1_def = implode("\n", array_values(array_unique(array_filter(array_column($bp, 'entregable'), 'strlen'))));
	$mensual_def = implode("\n", array_map('strval', (array) ($plan['soporte']['mensuales'] ?? [])));
	echo '<details class="at-pt-seccion" id="at-av-listas-' . $id . '"><summary><h4>Cambios de plan, «necesitamos de ti» y alcance</h4></summary><div class="at-pt-seccion-contenido">' . at_av_ayuda('<strong>Cambios de plan acordados:</strong> lo que cambió respecto del plan firmado, con su fecha (p. ej., «Google Ads pasa al servicio mensual»). El plan firmado no se toca: el cliente ve estos cambios aparte, en «Estado del proyecto». <strong>Lo que necesitamos del cliente:</strong> los pendientes que dependen de él (logo, accesos, una fecha); un «✓ » al inicio lo marca resuelto. <strong>Qué incluye la Fase 1 y el servicio mensual:</strong> el alcance que ve el cliente; vacío = lo que dice el plan. Si ya hay un cierre publicado, guardar rehace la presentación del cliente.') . '<form method="post" action="' . $accion . '" class="at-av-listas">' . at_av_ocultos('at_av_listas', $id, $crm_id)
		. '<label class="at-pt-campo">Cambios de plan acordados (uno por línea: «2026-10-13 | título | detalle»)<textarea name="cambios_plan" rows="3" class="large-text">' . esc_textarea(at_av_texto_de_cambios($av['cambios_plan'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Lo que necesitamos del cliente (uno por línea; «✓ » al inicio = resuelto)<textarea name="necesitamos" rows="3" class="large-text">' . esc_textarea(at_av_texto_de_necesitamos($av['necesitamos'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Qué incluye la Fase 1 (uno por línea; vacío = los entregables del plan)<textarea name="alcance_fase1" rows="3" class="large-text" placeholder="' . esc_attr($fase1_def) . '">' . esc_textarea(implode("\n", $av['alcance']['fase1'])) . '</textarea></label>'
		. '<label class="at-pt-campo">Qué incluye el servicio mensual (uno por línea; vacío = los mensuales del plan)<textarea name="alcance_mensual" rows="2" class="large-text" placeholder="' . esc_attr($mensual_def) . '">' . esc_textarea(implode("\n", $av['alcance']['mensual'])) . '</textarea></label>'
		. '<p><button type="submit" class="button">💾 Guardar</button></p></form></div></details>';

	// Presentación para el cliente y vista previa, al final de la operación del plan.
	$etiquetas = ['' => 'Todavía no se genera', 'pendiente' => 'Generándose', 'listo' => 'Lista', 'error' => 'Con error'];
	$reg = $av['render'];
	echo '<details class="at-pt-seccion" id="at-av-presentacion-' . $id . '"><summary><h4>Presentación «Estado del proyecto»</h4></summary><div class="at-pt-seccion-contenido"><div class="at-av-render"><p><strong>Presentación para el cliente:</strong> ' . esc_html($etiquetas[$reg['estado']] ?? $reg['estado'])
		. ($reg['at'] !== '' ? ' · ' . esc_html($reg['at']) : '') . '</p>';
	if ($reg['nota'] !== '') {
		echo '<p class="description">' . esc_html($reg['nota']) . '</p>';
	}
	echo '<p>El cliente la ve en <a href="' . esc_url($publica) . '" target="_blank" rel="noopener">' . esc_html($publica) . '</a>'
		. ($publicados ? '' : ' (mientras no publiques un cierre, ve «Pronto verás aquí el avance de tu proyecto»)') . '.</p><div class="at-pt-botones">';
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
	echo '</div>';
	$pr = $av['render_previa'];
	if ($pr['estado'] === 'listo' && $pr['view_url'] !== '') {
		echo '<p><a href="' . esc_url($pr['view_url']) . '" target="_blank" rel="noopener">Abrir la vista previa con borradores</a> <span class="description">(solo para ti; el cliente no la ve)</span></p>';
	} elseif (in_array($pr['estado'], ['pendiente', 'error'], true)) {
		echo '<p class="description">Vista previa: ' . esc_html($pr['estado'] === 'pendiente' ? 'generándose…' : 'con error. ' . $pr['nota']) . '</p>';
	}
	echo '</div></div></details>';
	echo '</section>';
}

/**
 * Formulario «Nuevo cierre» (o «Editar» un borrador con ?av_editar=<id>). Si la última vez no se pudo guardar, vuelve
 * con lo que se había escrito. Cada fila de evidencia muestra solo los campos de su tipo (CSS con :has, sin JS); las
 * filas de más quedan ocultas hasta «＋ Agregar otra evidencia» (plan-trabajo.js).
 */
function at_av_render_formulario(object $fila, int $crm_id, array $av, array $plan): string {
	$id = (int) $fila->id;
	$p = [];
	$editar = '';
	$guardado = get_transient(at_av_clave_form());
	if (is_array($guardado) && (int) ($guardado['plan_id'] ?? 0) === $id) {
		delete_transient(at_av_clave_form());
		$p = is_array($guardado['p'] ?? null) ? $guardado['p'] : [];
		$editar = (string) ($guardado['editar'] ?? '');
	} else {
		$pedido = sanitize_key(wp_unslash($_GET['av_editar'] ?? ''));
		foreach ($av['cierres'] as $c) {
			if (($c['id'] ?? '') === $pedido && ($c['estado'] ?? '') !== 'publicado') {
				$editar = $pedido;
				$p = at_av_formulario_de_cierre(at_av_exportar_cierre($av, $pedido, (string) $fila->codigo));
			}
		}
	}
	$v = fn(string $k): string => is_string($p[$k] ?? null) ? $p[$k] : '';
	$marcados = array_map(fn($b) => at_av_clave((string) $b), (array) ($p['bloques'] ?? []));
	$h = '<h4 id="at-av-nuevo">' . ($editar !== '' ? '✏️ Editar el cierre «' . esc_html($v('titulo')) . '»' : '➕ Nuevo cierre') . '</h4>'
		. at_av_ayuda('Aquí armas un cierre sin escribir JSON. Marca el bloque del plan que terminó, cuenta el antes, el durante y el después, y agrega las pruebas: cada evidencia va en «Antes» o en «Después» y se elige su tipo (imagen, cifra medida con fuente y fecha, video con portada opcional o enlace). Los archivos se adjuntan con el nombre que tengan. Se guarda como <strong>borrador</strong>: el cliente no lo ve hasta que lo publiques.')
		. '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="at-av-nc">'
		. at_av_ocultos('at_av_nuevo', $id, $crm_id, $editar !== '' ? ['cierre_id' => $editar] : [])
		. '<div class="at-av-nc-fila">'
		. '<label class="at-av-nc-titulo">Título del hito<input type="text" name="nc[titulo]" maxlength="120" required value="' . esc_attr($v('titulo')) . '" placeholder="Ej.: Fotos del personal antiguo retiradas"></label>'
		. '<label>Terminado el<input type="date" name="nc[fecha_real]" required value="' . esc_attr($v('fecha_real')) . '"></label></div>'
		. '<fieldset class="at-av-nc-bloques"><legend>¿Qué bloque del plan cierra? (las actividades del plan de esos bloques se agregan solas)</legend>';
	foreach (at_av_estado_bloques($plan, $av) as $b) {
		$h .= '<label><input type="checkbox" name="nc[bloques][]" value="' . esc_attr($b['nombre']) . '"' . (in_array(at_av_clave($b['nombre']), $marcados, true) ? ' checked' : '') . '> ' . esc_html($b['nombre']) . '</label>';
	}
	$caja = fn(string $k, string $etiqueta, int $filas, string $ayuda) => '<label>' . $etiqueta . '<textarea name="nc[' . $k . ']" rows="' . $filas . '" class="large-text" placeholder="' . esc_attr($ayuda) . '">' . esc_textarea($v($k)) . '</textarea></label>';
	$h .= '</fieldset><div class="at-av-nc-partes">'
		. '<div><h5>Antes</h5>' . $caja('antes_texto', 'Cómo estaba', 3, 'Ej.: El sitio mostraba fotos de personas que ya no trabajan en la empresa.') . $caja('antes_puntos', 'Puntos clave (uno por línea, opcional)', 3, '') . '</div>'
		. '<div><h5>Durante</h5>' . $caja('durante_texto', 'Qué hicimos', 3, 'Ej.: Retiramos las fotos y las reemplazamos por imágenes del servicio.') . '</div>'
		. '<div><h5>Después</h5>' . $caja('despues_texto', 'Cómo quedó', 3, 'Ej.: El sitio ya no muestra al personal antiguo.') . $caja('despues_puntos', 'Puntos clave (uno por línea, opcional)', 3, '') . '</div></div>'
		. '<h5>Evidencias</h5><p class="description">Pruebas del antes y el después: una cifra medida (con su fuente y fecha), una imagen, un video o un enlace.</p>';
	$filas = array_values(is_array($p['ev'] ?? null) ? array_filter($p['ev'], 'is_array') : []);
	$visibles = max(2, count($filas) + 1);
	$tipos = ['captura' => 'Imagen o captura', 'cifra' => 'Cifra medida', 'video' => 'Video', 'enlace' => 'Enlace'];
	for ($i = 0; $i < AT_AV_FORM_FILAS; $i++) {
		$f = $filas[$i] ?? [];
		$c = fn(string $k): string => is_string($f[$k] ?? null) ? $f[$k] : '';
		$n = 'nc[ev][' . $i . ']';
		$sel = '';
		foreach ($tipos as $k => $t) {
			$sel .= '<option value="' . $k . '"' . selected($c('tipo') !== '' ? $c('tipo') : 'captura', $k, false) . '>' . $t . '</option>';
		}
		$actual = $c('archivo_actual') !== '' ? ' <small>(ya está «' . esc_html($c('archivo_actual')) . '»: adjunta otro solo para cambiarlo)</small>' : '';
		$actual_p = $c('poster_actual') !== '' ? ' <small>(ya está «' . esc_html($c('poster_actual')) . '»)</small>' : '';
		$h .= '<fieldset class="at-av-ev"' . ($i >= $visibles ? ' hidden' : '') . '><legend>Evidencia ' . ($i + 1) . '</legend>'
			. '<input type="hidden" name="' . $n . '[ref]" value="' . esc_attr($c('ref')) . '">'
			. '<input type="hidden" name="' . $n . '[archivo_actual]" value="' . esc_attr($c('archivo_actual')) . '">'
			. '<input type="hidden" name="' . $n . '[poster_actual]" value="' . esc_attr($c('poster_actual')) . '">'
			. '<label>Tipo<select class="at-av-ev-tipo" name="' . $n . '[tipo]">' . $sel . '</select></label>'
			. '<label>Va en<select name="' . $n . '[donde]"><option value="despues">Después</option><option value="antes"' . selected($c('donde'), 'antes', false) . '>Antes</option></select></label>'
			. '<label class="at-av-ev-largo">Título<input type="text" name="' . $n . '[titulo]" maxlength="120" value="' . esc_attr($c('titulo')) . '" placeholder="Ej.: Velocidad en celular"></label>'
			. '<label class="at-av-c t-cifra">Valor<input type="text" name="' . $n . '[valor]" maxlength="40" value="' . esc_attr($c('valor')) . '" placeholder="92"></label>'
			. '<label class="at-av-c t-cifra">Unidad<input type="text" name="' . $n . '[unidad]" maxlength="60" value="' . esc_attr($c('unidad')) . '" placeholder="puntos de 100"></label>'
			. '<label class="at-av-c t-cifra">Fuente<input type="text" name="' . $n . '[fuente]" maxlength="200" value="' . esc_attr($c('fuente')) . '" placeholder="PageSpeed Insights"></label>'
			. '<label class="at-av-c t-cifra">Fecha de la medición<input type="date" name="' . $n . '[fecha]" value="' . esc_attr($c('fecha')) . '"></label>'
			. '<label class="at-av-c t-captura t-video at-av-ev-largo"><span class="t-captura">Imagen (JPG, PNG o WebP)</span><span class="t-video">Video (MP4 o WebM, hasta 200 MB)</span>'
			. '<input type="file" name="nc_archivo[' . $i . ']" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">' . $actual . '</label>'
			. '<label class="at-av-c t-video at-av-ev-largo">Portada del video (opcional, JPG o PNG)<input type="file" name="nc_poster[' . $i . ']" accept="image/jpeg,image/png,image/webp">' . $actual_p . '</label>'
			. '<label class="at-av-c t-enlace at-av-ev-largo">Dirección (https://…)<input type="url" name="' . $n . '[url]" maxlength="500" value="' . esc_attr($c('url')) . '" placeholder="https://"></label>'
			. '</fieldset>';
	}
	$volver = add_query_arg(['page' => 'automatiza-crm-ficha', 'id' => $crm_id, 'pt' => $id], admin_url('admin.php')) . '#at-av';
	return $h . '<p><button type="button" class="button at-av-nc-mas">＋ Agregar otra evidencia</button></p>'
		. '<p><button type="submit" class="button button-primary">💾 Guardar como borrador</button>'
		. ($editar !== '' ? ' <a class="button" href="' . esc_url($volver) . '">Cancelar</a>' : '') . '</p></form>';
}
