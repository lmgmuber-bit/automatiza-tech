<?php
/**
 * Pestaña «📦 Entregables» de la ficha del cliente en el CRM (crm-ai-completo.php la dibuja con at_en_render_pestana()),
 * resumen en el portal del cliente, marca en la lista del CRM y acciones admin-post (manage_options + nonce at_en_<id>).
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_mensajes_panel(): array {
	return [
		'activado'        => ['ok', 'Notas activadas. Ahora crea la versión 1.'],
		'version_creada'  => ['ok', 'Versión creada. Revisa la vista previa y envíala.'],
		'prueba_enviada'  => ['ok', 'Te enviamos la prueba a tu correo.'],
		'version_enviada' => ['ok', 'Versión enviada al cliente por correo.'],
		'ya_enviada'      => ['err', 'Esa versión ya se había enviado por correo.'],
		'respuesta_ok'    => ['ok', 'Respuesta guardada.'],
		'cerrado_ok'      => ['ok', 'Entregable cerrado.'],
		'abierto_ok'      => ['ok', 'Entregable reabierto.'],
		'url_invalida'    => ['err', 'El enlace debe empezar con http(s):// y no puede ser de easypanel.'],
		'mensaje_largo'   => ['err', 'El mensaje es muy largo (máximo 6.000 caracteres).'],
		'sin_correo'      => ['err', 'El cliente no tiene un correo válido en su ficha.'],
		'sin_telefono'    => ['err', 'El cliente no tiene un teléfono válido en su ficha.'],
		'correo_fallo'    => ['err', 'No se pudo enviar el correo. Revisa el SMTP e inténtalo de nuevo.'],
		'nota_ajena'      => ['err', 'Esa nota no es de este entregable.'],
		'ya_respondida'   => ['err', 'Esa nota ya estaba respondida.'],
		'respuesta_sin_aviso' => ['err', 'Respuesta guardada, pero no se pudo avisar al cliente por correo (sin correo válido o falla del envío): avísale por WhatsApp.'],
		'version_editada' => ['ok', 'Versión actualizada. Revisa la vista previa.'],
		'edicion_enviada' => ['err', 'Esa versión ya se envió y no se puede editar. Crea una versión nueva.'],
		'sin_entregable'  => ['err', 'El entregable ya no existe.'],
		'sin_version'     => ['err', 'Esa versión no existe.'],
		'error'           => ['err', 'No se pudo completar la acción.'],
	] + array_map(function ($t) { return ['err', $t]; }, at_en_mensajes());
}

function at_en_volver(int $crm, int $ent, string $msg, int $n_img = 0): void {
	wp_safe_redirect(at_en_url_ficha($crm, $ent, $msg, $n_img));
	exit;
}

/** Vuelve con el error de una imagen (clave de la lista blanca + número de la imagen). */
function at_en_volver_error_imagen(int $crm, int $ent, WP_Error $e): void {
	$datos = $e->get_error_data();
	[$clave, $n] = at_en_redireccion_error_imagen((string) $e->get_error_code(), is_array($datos) ? (int) ($datos['n'] ?? 0) : 0);
	at_en_volver($crm, $ent, $clave, $n);
}

/** Permiso, nonce y entregable de una acción. Devuelve el entregable. */
function at_en_accion_entregable(): object {
	$id = absint($_POST['entregable_id'] ?? 0);
	check_admin_referer('at_en_' . $id);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$e = at_en_por_id($id);
	if (!$e) {
		at_en_volver(absint($_POST['crm_id'] ?? 0), 0, 'error');
	}
	return $e;
}

function at_en_form(string $accion, int $ent_id, string $campos, string $boton, string $extra = ''): string {
	return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:4px 6px 4px 0"' . $extra . '>'
		. '<input type="hidden" name="action" value="' . esc_attr($accion) . '"><input type="hidden" name="entregable_id" value="' . $ent_id . '">'
		. wp_nonce_field('at_en_' . $ent_id, '_wpnonce', true, false) . $campos
		. '<button type="submit" class="button">' . esc_html($boton) . '</button></form>';
}

/** Botón «✨ Sugerir …» (type=button, dentro del formulario que rellena) y el lugar de su aviso. Todo dato va escapado en data-*. */
function at_en_boton_ia(string $tipo, int $ent_id, string $campo, int $numero = 0, int $nota_id = 0): string {
	return '<button type="button" class="button at-en-ia-btn" data-tipo="' . esc_attr($tipo) . '" data-ent="' . $ent_id . '" data-campo="' . esc_attr($campo) . '"'
		. ' data-numero="' . $numero . '" data-nota="' . $nota_id . '" data-nonce="' . esc_attr(wp_create_nonce('at_en_' . $ent_id)) . '">'
		. ($tipo === 'respuesta' ? '✨ Sugerir respuesta' : '✨ Sugerir mensaje') . '</button> <span class="at-en-ia-msg" role="status" style="color:#b91c1c;margin-left:6px"></span>';
}

/** Script de los botones de IA: código fijo, una sola vez por pestaña (escucha los clics del panel entero). */
function at_en_script_ia(): string {
	return '<script>(function(){var ajax=' . wp_json_encode(admin_url('admin-ajax.php')) . ';'
		. 'document.addEventListener("click",function(ev){var b=ev.target.closest?ev.target.closest(".at-en-ia-btn"):null;if(!b){return;}ev.preventDefault();'
		. 'var f=b.form||b.closest("form"),msg=b.parentNode.querySelector(".at-en-ia-msg"),campo=f?f.querySelector("textarea[name="+b.getAttribute("data-campo")+"]"):null;'
		. 'if(!campo||b.disabled){return;}msg.textContent="";var d=new URLSearchParams();d.append("action","at_en_sugerir");d.append("_ajax_nonce",b.getAttribute("data-nonce"));'
		. 'd.append("entregable_id",b.getAttribute("data-ent"));d.append("tipo",b.getAttribute("data-tipo"));'
		. 'if(b.getAttribute("data-tipo")==="respuesta"){d.append("nota_id",b.getAttribute("data-nota"));}else{'
		. 'var u=f.querySelector("input[name=url]"),url=u?u.value.trim():"";if(url===""){msg.textContent="Pega primero el enlace de la versión.";return;}'
		. 'd.append("url",url);d.append("numero",b.getAttribute("data-numero"));}'
		. 'if(campo.value.trim()!==""&&!window.confirm("¿Reemplazar el texto actual por la sugerencia de la IA?")){return;}'
		. 'var et=b.textContent;b.disabled=true;b.textContent="Pensando…";'
		. 'fetch(ajax,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:d.toString()})'
		. '.then(function(r){return r.json();}).then(function(j){if(j&&j.success&&j.data&&typeof j.data.texto==="string"){campo.value=j.data.texto;}'
		. 'else{msg.textContent=(j&&j.data&&j.data.mensaje)?j.data.mensaje:"No se pudo sugerir: escribe el texto a mano.";}})'
		. '.catch(function(){msg.textContent="No se pudo sugerir: escribe el texto a mano.";})'
		. '.then(function(){b.disabled=false;b.textContent=et;});});})();</script>';
}

function at_en_render_pestana(array $cliente): void {
	$crm = (int) ($cliente['id'] ?? 0);
	$msg = sanitize_key(wp_unslash($_GET['en_msg'] ?? ''));
	$abierto = absint($_GET['en'] ?? 0);
	$mp = at_en_mensajes_panel();
	echo '<h3>📦 Entregables</h3>';
	if ($msg !== '' && isset($mp[$msg])) {
		$texto = at_en_formatear_mensaje($mp[$msg][1], absint($_GET['en_img'] ?? 0));
		// Si el correo no salió, el motivo que dio el SMTP (guardado un minuto) ayuda a saber por qué.
		$motivo = ($abierto > 0 && in_array($msg, ['correo_fallo', 'respuesta_sin_aviso'], true)) ? at_en_motivo_fallo($abierto) : '';
		echo '<div class="notice notice-' . ($mp[$msg][0] === 'ok' ? 'success' : 'error') . ' inline"><p>' . esc_html($texto) . ($motivo !== '' ? ' Motivo: ' . esc_html($motivo) : '') . '</p></div>';
	}
	$lista = at_en_de_crm($crm);
	if (!$lista) {
		echo '<p>Este cliente no tiene entregables. Créalos en «Contratos y operación» con el tipo «Entregable».</p>';
		return;
	}
	foreach ($lista as $d) {
		$en_id = (int) ($d->en_id ?? 0);
		echo '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:10px 0;background:#fff">';
		echo '<strong>' . esc_html((string) $d->title) . '</strong>';
		if ($en_id <= 0) {
			echo ' <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">'
				. '<input type="hidden" name="action" value="at_en_activar"><input type="hidden" name="detalle_id" value="' . (int) $d->id . '"><input type="hidden" name="crm_id" value="' . $crm . '">'
				. wp_nonce_field('at_en_activar_' . (int) $d->id, '_wpnonce', true, false) . '<button class="button">Activar notas del cliente</button></form></div>';
			continue;
		}
		$e = at_en_por_id($en_id);
		$pend = at_en_sin_responder($en_id);
		echo ' · ' . esc_html($e->estado === 'abierto' ? 'Abierto' : 'Cerrado') . ' · versión ' . (int) $e->version_vigente
			. ($pend > 0 ? ' · <span style="color:#b91c1c;font-weight:bold">🔴 ' . $pend . ($pend === 1 ? ' nota sin responder' : ' notas sin responder') . '</span>' : '');
		$url = at_en_url_pagina(home_url(), (string) $e->codigo);
		echo '<p>Enlace del cliente: <input type="text" readonly value="' . esc_attr($url) . '" style="width:60%" onclick="this.select()"></p>';
		echo '<details' . ($abierto === $en_id ? ' open' : '') . '><summary>Línea de tiempo y acciones</summary>';
		at_en_render_detalle($e, $crm);
		echo '</details></div>';
		$hay_activos = true;
	}
	if (!empty($hay_activos)) {
		echo at_en_script_ia();
	}
}

/** Línea de tiempo y acciones de un entregable. */
function at_en_render_detalle(object $e, int $crm): void {
	$id = (int) $e->id;
	$eventos = [];
	foreach (at_en_versiones($id) as $v) {
		$canales = array_filter([$v->enviado_correo_at ? 'correo' : '', $v->enviado_whatsapp_at ? 'WhatsApp' : '']);
		$eventos[] = [(string) $v->creado_at, 0, '<div style="background:#eef2ff;padding:8px;border-radius:6px;margin:6px 0"><strong>v' . (int) $v->numero . ' ' . ($canales ? 'enviada (' . esc_html(implode(' + ', $canales)) . ')' : 'creada, sin enviar') . '</strong> · ' . esc_html(at_en_fecha((string) $v->creado_at))
			. ' · <a href="' . esc_url((string) $v->url) . '" target="_blank" rel="noopener">abrir</a>'
			. ((string) $v->mensaje !== '' ? '<details><summary>Mensaje</summary>' . at_en_mensaje_html((string) $v->mensaje) . '</details>' : '') . '</div>'];
	}
	foreach (at_en_notas($id) as $n) {
		$imgs = json_decode((string) $n->imagenes, true);
		$mini = '';
		foreach (is_array($imgs) ? $imgs : [] as $img) {
			$u = at_en_url_imagen(home_url(), (string) $e->codigo, (string) $img);
			$mini .= $u !== '' ? '<a href="' . esc_url($u) . '" target="_blank" rel="noopener"><img src="' . esc_url($u) . '" alt="" style="max-width:90px;max-height:90px;margin:4px;border-radius:4px"></a>' : '';
		}
		$es_cli = $n->autor === 'cliente';
		$html = '<div style="background:' . ($es_cli ? '#fff7ed' : '#ecfdf5') . ';padding:8px;border-radius:6px;margin:6px 0 6px ' . ($es_cli ? '0' : '40px') . '">'
			. '<small>' . esc_html(($es_cli ? '' : 'AT · ') . $n->nombre) . ' · sobre la versión ' . (int) $n->version_numero . ' · ' . esc_html(at_en_fecha((string) $n->creado_at))
			. ($es_cli && !(int) $n->aviso_ok ? ' · <span style="color:#b45309">sin aviso por correo</span>' : '') . '</small><br>'
			. nl2br(esc_html((string) $n->texto)) . ($mini !== '' ? '<div>' . $mini . '</div>' : '');
		if ($es_cli && !(int) $n->respondida) {
			$html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" style="margin-top:6px">'
				. '<input type="hidden" name="action" value="at_en_responder"><input type="hidden" name="entregable_id" value="' . $id . '"><input type="hidden" name="nota_id" value="' . (int) $n->id . '">'
				. wp_nonce_field('at_en_' . $id, '_wpnonce', true, false)
				. '<textarea name="texto" rows="3" style="width:100%" maxlength="3000" required placeholder="Tu respuesta"></textarea>'
				. '<input type="file" name="imagenes[]" accept="image/jpeg,image/png" multiple> '
				. at_en_boton_ia('respuesta', $id, 'texto', 0, (int) $n->id)
				. '<label><input type="checkbox" name="avisar" value="1" checked> Avisar al cliente por correo</label> '
				. '<button class="button button-primary">Responder</button></form>';
		}
		$eventos[] = [(string) $n->creado_at, 1, $html . '</div>'];
	}
	usort($eventos, function ($a, $b) { return strcmp($a[0], $b[0]) ?: $a[1] <=> $b[1]; });
	foreach ($eventos as $ev) {
		echo $ev[2];
	}
	// Última respuesta de AT: texto de WhatsApp para avisar.
	$ult_at = null;
	foreach (at_en_notas($id) as $n) {
		if ($n->autor === 'at') {
			$ult_at = $n;
		}
	}
	if ($ult_at) {
		$t = at_en_texto_wa_respuesta($e, (int) $ult_at->version_numero);
		$wa = at_en_url_wa_respuesta($e, (int) $ult_at->version_numero);
		echo '<p><strong>Avisar tu última respuesta por WhatsApp:</strong><br><textarea readonly rows="2" style="width:100%" onclick="this.select()">' . esc_textarea($t) . '</textarea>'
			. ($wa !== '' ? '<a class="button" href="' . esc_url($wa) . '" target="_blank" rel="noopener">Abrir mi WhatsApp</a>' : '') . '</p>';
	}
	// Versión vigente sin enviar por correo: vista previa y envío.
	$vig = (int) $e->version_vigente > 0 ? at_en_version($id, (int) $e->version_vigente) : null;
	if ($vig) {
		$cli = at_en_cliente($e);
		$c = at_en_correo_version(['nombre' => $cli['nombre'], 'titulo' => $cli['titulo'], 'numero' => (int) $vig->numero, 'url_version' => (string) $vig->url, 'url_pagina' => at_en_url_pagina(home_url(), (string) $e->codigo), 'mensaje' => (string) $vig->mensaje, 'logo' => at_en_logo(), 'prueba' => false]);
		echo '<h4>Versión ' . (int) $vig->numero . ': vista previa del correo</h4><p>Asunto: <strong>' . esc_html($c['asunto']) . '</strong></p>'
			. '<iframe srcdoc="' . esc_attr($c['html']) . '" style="width:100%;height:520px;border:1px solid #e2e8f0;border-radius:6px;background:#fff" sandbox></iframe>';
		$num = '<input type="hidden" name="numero" value="' . (int) $vig->numero . '">';
		echo '<p>' . at_en_form('at_en_version_prueba', $id, $num, 'Enviarme una prueba')
			. ($vig->enviado_correo_at === null ? at_en_form('at_en_version_enviar', $id, $num, 'Enviar al cliente por correo', ' onsubmit="return confirm(\'¿Enviar la versión al cliente?\')"') : '<em>Correo enviado.</em> ')
			. at_en_form('at_en_version_whatsapp', $id, $num, 'Enviar por mi WhatsApp', ' target="_blank"') . '</p>';
		echo '<p>Texto del WhatsApp para copiar:<br><textarea readonly rows="3" style="width:100%" onclick="this.select()">' . esc_textarea(at_en_texto_wa_version($e, (int) $vig->numero)) . '</textarea></p>';
		// Mientras no se envíe (ni por correo ni por WhatsApp) se puede corregir; enviada ya no: se crea una versión nueva.
		if ($vig->enviado_correo_at === null && $vig->enviado_whatsapp_at === null) {
			echo '<h4>Editar esta versión</h4><p><em>El cliente todavía no la ve: la verá cuando la envíes.</em></p>'
				. '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
				. '<input type="hidden" name="action" value="at_en_version_editar"><input type="hidden" name="entregable_id" value="' . $id . '">' . $num . wp_nonce_field('at_en_' . $id, '_wpnonce', true, false)
				. '<p><label>Enlace de la versión<br><input type="url" name="url" required style="width:100%" value="' . esc_attr((string) $vig->url) . '"></label></p>'
				. '<p><label>Tu mensaje<br><textarea name="mensaje" rows="6" maxlength="6000" style="width:100%">' . esc_textarea((string) $vig->mensaje) . '</textarea></label><br>' . at_en_boton_ia('mensaje', $id, 'mensaje', (int) $vig->numero) . '</p>'
				. '<button class="button">Guardar cambios de la versión ' . (int) $vig->numero . '</button></form>';
		}
	}
	// Nueva versión.
	echo '<h4>Nueva versión (' . ((int) $e->version_vigente + 1) . ')</h4><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
		. '<input type="hidden" name="action" value="at_en_version_crear"><input type="hidden" name="entregable_id" value="' . $id . '">' . wp_nonce_field('at_en_' . $id, '_wpnonce', true, false)
		. '<p><label>Enlace de la versión<br><input type="url" name="url" required style="width:100%" placeholder="https://"></label></p>'
		. '<p><label>Tu mensaje (párrafos con línea en blanco; listas con «- »)<br><textarea name="mensaje" rows="6" maxlength="6000" style="width:100%"></textarea></label><br>' . at_en_boton_ia('mensaje', $id, 'mensaje', (int) $e->version_vigente + 1) . '</p>'
		. '<button class="button button-primary">Crear versión y ver vista previa</button></form>';
	echo '<p>' . ($e->estado === 'abierto'
		? at_en_form('at_en_estado', $id, '<input type="hidden" name="estado" value="cerrado">', 'Cerrar el entregable')
		: at_en_form('at_en_estado', $id, '<input type="hidden" name="estado" value="abierto">', 'Reabrir el entregable')) . '</p>';
}

/** Resumen para la línea de tiempo del portal del cliente (sin texto de notas ni imágenes). */
function at_en_html_resumen_portal(array $item): string {
	if (($item['source'] ?? '') !== 'client' || ($item['detail_type'] ?? '') !== 'entregable') {
		return '';
	}
	$e = at_en_por_detalle((int) ($item['id'] ?? 0));
	$pub = $e ? at_en_version_publica($e) : null; // la que ve el cliente: la última enviada
	if (!$e || !$pub) {
		return '';
	}
	$n = count(at_en_notas((int) $e->id));
	return '<div style="margin-top:10px;padding:10px;background:#eef2ff;border-radius:6px">'
		. 'Versión ' . (int) $pub->numero . ' · ' . $n . ($n === 1 ? ' nota' : ' notas')
		. ' · <a href="' . esc_url(at_en_url_pagina(home_url(), (string) $e->codigo)) . '" target="_blank" rel="noopener" style="font-weight:bold">Ver el detalle y las notas →</a></div>';
}

/** Marca para la lista de clientes del CRM; '' sin pendientes. */
function at_en_marca_lista(int $crm_id): string {
	$n = at_en_sin_responder_crm($crm_id);
	return $n > 0 ? ' <span title="Notas de entregables sin responder" style="color:#b91c1c;font-weight:bold">🔴 ' . $n . '</span>' : '';
}

function at_en_accion_activar(): void {
	$det = absint($_POST['detalle_id'] ?? 0);
	$crm = absint($_POST['crm_id'] ?? 0);
	check_admin_referer('at_en_activar_' . $det);
	if (!current_user_can('manage_options')) {
		wp_die('Sin permiso.', '', ['response' => 403]);
	}
	$r = at_en_activar($det);
	at_en_volver($crm, is_wp_error($r) ? 0 : (int) $r, is_wp_error($r) ? 'error' : 'activado');
}

function at_en_accion_version_crear(): void {
	$e = at_en_accion_entregable();
	$r = at_en_crear_version((int) $e->id, (string) wp_unslash($_POST['url'] ?? ''), (string) wp_unslash($_POST['mensaje'] ?? ''));
	if (is_wp_error($r)) {
		at_en_volver((int) $e->crm_id, (int) $e->id, in_array($r->get_error_code(), ['url_invalida', 'mensaje_largo', 'mensaje_easypanel'], true) ? $r->get_error_code() : 'error');
	}
	at_en_volver((int) $e->crm_id, (int) $e->id, 'version_creada');
}

function at_en_accion_version_editar(): void {
	$e = at_en_accion_entregable();
	$r = at_en_editar_version((int) $e->id, absint($_POST['numero'] ?? 0), (string) wp_unslash($_POST['url'] ?? ''), (string) wp_unslash($_POST['mensaje'] ?? ''));
	if (is_wp_error($r)) {
		$claves = ['url_invalida' => 'url_invalida', 'mensaje_largo' => 'mensaje_largo', 'mensaje_easypanel' => 'mensaje_easypanel', 'sin_version' => 'sin_version', 'ya_enviada' => 'edicion_enviada'];
		at_en_volver((int) $e->crm_id, (int) $e->id, $claves[$r->get_error_code()] ?? 'error');
	}
	at_en_volver((int) $e->crm_id, (int) $e->id, 'version_editada');
}

function at_en_accion_version_prueba(): void {
	$e = at_en_accion_entregable();
	$r = at_en_enviar_version((int) $e->id, absint($_POST['numero'] ?? 0), true);
	at_en_volver((int) $e->crm_id, (int) $e->id, $r['ok'] ? 'prueba_enviada' : $r['motivo']);
}

function at_en_accion_version_enviar(): void {
	$e = at_en_accion_entregable();
	$r = at_en_enviar_version((int) $e->id, absint($_POST['numero'] ?? 0), false);
	at_en_volver((int) $e->crm_id, (int) $e->id, $r['ok'] ? 'version_enviada' : $r['motivo']);
}

function at_en_accion_version_whatsapp(): void {
	$e = at_en_accion_entregable();
	$n = absint($_POST['numero'] ?? 0);
	$url = at_en_version((int) $e->id, $n) ? at_en_url_wa_version($e, $n) : '';
	if ($url === '') {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'sin_telefono');
	}
	at_en_marcar_version_enviada((int) $e->id, $n, 'whatsapp');
	at_en_historial((int) $e->crm_id, 'entregable_version', at_en_cliente($e)['titulo'] . ': versión ' . $n . ' enviada por WhatsApp', 'Se abrió el WhatsApp de Luis con el enlace del entregable ' . (int) $e->id . '.');
	wp_redirect($url); // wa.me no es del sitio: wp_safe_redirect lo cambiaría por el escritorio
	exit;
}

function at_en_accion_responder(): void {
	$e = at_en_accion_entregable();
	$nota = at_en_nota(absint($_POST['nota_id'] ?? 0));
	if (!$nota || (int) $nota->entregable_id !== (int) $e->id || $nota->autor !== 'cliente') {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'nota_ajena');
	}
	$nombre = 'Luis'; // firma fija: lo que ve el cliente no depende del nombre del usuario de WordPress
	$texto = (string) wp_unslash($_POST['texto'] ?? '');
	// Se valida el texto antes de reescribir imágenes: así una respuesta rechazada no deja archivos sueltos.
	$v = at_en_validar_nota($nombre, $texto);
	if (!$v['ok']) {
		at_en_volver((int) $e->crm_id, (int) $e->id, $v['error']);
	}
	// Se reclama la nota antes de guardar nada: dos clics seguidos no crean dos respuestas ni dos correos.
	if (!at_en_reclamar_respuesta((int) $nota->id)) {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'ya_respondida');
	}
	$archivos = at_en_archivos_subidos(isset($_FILES['imagenes']) && is_array($_FILES['imagenes']) ? $_FILES['imagenes'] : []);
	$imagenes = $archivos ? at_en_procesar_imagenes($archivos, (int) $e->id) : [];
	if (is_wp_error($imagenes)) {
		at_en_soltar_respuesta((int) $nota->id);
		at_en_volver_error_imagen((int) $e->crm_id, (int) $e->id, $imagenes);
	}
	// La respuesta queda sobre la misma versión de la nota que contesta (no sobre la vigente, que puede ser otra).
	$id = at_en_agregar_nota((int) $e->id, 'at', $nombre, $texto, $imagenes, '', (int) $nota->version_numero);
	if (is_wp_error($id)) {
		foreach ($imagenes as $img) {
			if (at_en_nombre_imagen_valido((string) $img)) {
				@unlink(at_en_dir_imagenes((int) $e->id) . $img);
			}
		}
		at_en_soltar_respuesta((int) $nota->id);
		at_en_volver((int) $e->crm_id, (int) $e->id, $id->get_error_code());
	}
	at_en_historial((int) $e->crm_id, 'entregable_respuesta', at_en_cliente($e)['titulo'] . ': respuesta de AT', at_en_extracto($v['texto'], 300));
	if (!empty($_POST['avisar']) && !at_en_avisar_respuesta((int) $id)) {
		at_en_volver((int) $e->crm_id, (int) $e->id, 'respuesta_sin_aviso');
	}
	at_en_volver((int) $e->crm_id, (int) $e->id, 'respuesta_ok');
}

function at_en_accion_estado(): void {
	$e = at_en_accion_entregable();
	$estado = sanitize_key(wp_unslash($_POST['estado'] ?? ''));
	$ok = at_en_cambiar_estado((int) $e->id, $estado);
	at_en_volver((int) $e->crm_id, (int) $e->id, !$ok ? 'error' : ($estado === 'cerrado' ? 'cerrado_ok' : 'abierto_ok'));
}

add_action('admin_post_at_en_activar', 'at_en_accion_activar');
add_action('admin_post_at_en_version_crear', 'at_en_accion_version_crear');
add_action('admin_post_at_en_version_editar', 'at_en_accion_version_editar');
add_action('admin_post_at_en_version_prueba', 'at_en_accion_version_prueba');
add_action('admin_post_at_en_version_enviar', 'at_en_accion_version_enviar');
add_action('admin_post_at_en_version_whatsapp', 'at_en_accion_version_whatsapp');
add_action('admin_post_at_en_responder', 'at_en_accion_responder');
add_action('admin_post_at_en_estado', 'at_en_accion_estado');

/** La ficha del CRM solo abre por hash la pestaña del plan; esta abre la de Entregables (#tab-entregables) al volver de una acción. */
function at_en_abrir_pestana_por_hash(): void {
	if (sanitize_key(wp_unslash($_GET['page'] ?? '')) !== 'automatiza-crm-ficha' || !current_user_can('manage_options')) {
		return;
	}
	echo '<script>document.addEventListener("DOMContentLoaded",function(){if(window.location.hash!=="#tab-entregables"){return;}var b=document.querySelector(\'.ficha-tab[data-target="tab-entregables"]\');if(b){b.click();}});</script>';
}
add_action('admin_footer', 'at_en_abrir_pestana_por_hash');
