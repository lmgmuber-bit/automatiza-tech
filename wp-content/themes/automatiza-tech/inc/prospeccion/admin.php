<?php
/** Rastro · Prospección: pantalla del panel (submenú bajo CRM) y sus acciones. Nada sale a un prospecto desde aquí. */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/lista.php';

add_action('admin_menu', static function () {
	add_submenu_page('automatiza-crm', 'Prospección', '🔎 Prospección', 'manage_options', 'at-prospeccion', 'at_pro_pagina');
});

function at_pro_url_panel(array $args = []): string {
	return add_query_arg($args, admin_url('admin.php?page=at-prospeccion'));
}

/** Cambia el estado de un prospecto desde el panel. Exige administrador y el nonce de ese prospecto. */
function at_pro_procesar_estado(int $id, string $estado, string $nonce, string $canal = ''): bool {
	if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'at_pro_estado_' . $id)) { return false; }
	return at_pro_cambiar_estado($id, $estado, $canal);
}

/** Acción masiva: solo marcar contactado o descartar. Devuelve cuántos cambiaron. */
function at_pro_procesar_masivo(array $ids, string $accion, string $nonce): int {
	if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'at_pro_masivo') || !in_array($accion, ['contactado', 'descartado'], true)) { return 0; }
	$n = 0;
	foreach (array_slice(array_unique(array_map('intval', $ids)), 0, 200) as $id) {
		if ($id > 0 && at_pro_cambiar_estado($id, $accion)) { $n++; }
	}
	return $n;
}

/** Alta a mano. Devuelve el id o un WP_Error. */
function at_pro_procesar_manual(array $d, string $nonce) {
	if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'at_pro_crear')) {
		return new WP_Error('permiso', 'No autorizado.');
	}
	$campos = ['fuente' => 'manual'];
	foreach (['negocio', 'rubro', 'comuna', 'web', 'telefono', 'correo', 'senales', 'servicio_sugerido', 'notas'] as $c) {
		$campos[$c] = isset($d[$c]) ? (string) $d[$c] : '';
	}
	return at_pro_crear($campos);
}

add_action('admin_post_at_pro_estado', static function () {
	$id = (int) ($_GET['id'] ?? 0);
	$ok = at_pro_procesar_estado($id, sanitize_key((string) ($_GET['estado'] ?? '')), (string) ($_GET['_wpnonce'] ?? ''), sanitize_key((string) ($_GET['canal'] ?? '')));
	wp_safe_redirect(at_pro_url_panel(['pro' => $ok ? 'estado_ok' : 'estado_no']));
	exit;
});

add_action('admin_post_at_pro_masivo', static function () {
	$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? wp_unslash($_POST['ids']) : [];
	$n = at_pro_procesar_masivo($ids, sanitize_key((string) ($_POST['accion_masiva'] ?? '')), (string) ($_POST['at_pro_nonce_masivo'] ?? ''));
	wp_safe_redirect(at_pro_url_panel(['pro' => 'masivo', 'n' => $n]));
	exit;
});

add_action('admin_post_at_pro_crear', static function () {
	$r = at_pro_procesar_manual(wp_unslash($_POST), (string) ($_POST['_wpnonce'] ?? ''));
	wp_safe_redirect(at_pro_url_panel(is_wp_error($r) ? ['pro' => 'crear_no', 'c' => $r->get_error_code()] : ['pro' => 'crear_ok']));
	exit;
});

add_action('admin_post_at_pro_importar', static function () {
	if (!current_user_can('manage_options') || !wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'at_pro_importar')) {
		wp_safe_redirect(at_pro_url_panel(['pro' => 'importar_no']));
		exit;
	}
	$f = $_FILES['archivo'] ?? null;
	$fuente = sanitize_key((string) ($_POST['fuente'] ?? 'manual'));
	$ext = $f ? strtolower((string) pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION)) : '';
	if (!$f || (int) ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !in_array($ext, ['csv', 'txt'], true) || !is_uploaded_file((string) $f['tmp_name'])) {
		wp_safe_redirect(at_pro_url_panel(['pro' => 'importar_no']));
		exit;
	}
	$res = at_pro_importar_csv((string) $f['tmp_name'], $fuente);
	set_transient('at_pro_res_' . get_current_user_id(), $res, 300);
	wp_safe_redirect(at_pro_url_panel(['pro' => 'importado']));
	exit;
});

function at_pro_avisos(): void {
	$p = isset($_GET['pro']) ? sanitize_key((string) $_GET['pro']) : '';
	$mapa = [
		'estado_ok' => ['updated', 'Listo, cambié el estado.'], 'estado_no' => ['error', 'No pude cambiar el estado (permiso, enlace vencido o paso no permitido).'],
		'crear_ok' => ['updated', 'Prospecto agregado.'], 'importar_no' => ['error', 'No pude importar: sube un archivo .csv y vuelve a intentar.'],
	];
	if ($p === 'masivo') { echo '<div class="notice notice-success"><p>' . (int) ($_GET['n'] ?? 0) . ' prospecto(s) actualizados.</p></div>'; }
	elseif ($p === 'crear_no') {
		$txt = ['repetido' => 'Ya existe un prospecto con ese dominio o teléfono.', 'en_bajas' => 'Ese contacto pidió baja: no se vuelve a cargar.', 'sin_contacto' => 'Falta una web, un teléfono o un correo válido.', 'demasiado_largo' => 'Un campo es demasiado largo.', 'sin_negocio' => 'Falta el nombre del negocio.'][sanitize_key((string) ($_GET['c'] ?? ''))] ?? 'No pude agregarlo.';
		echo '<div class="notice notice-error"><p>' . esc_html($txt) . '</p></div>';
	} elseif ($p === 'importado') {
		$r = get_transient('at_pro_res_' . get_current_user_id());
		if (is_array($r)) {
			echo '<div class="notice notice-success"><p>Importación: <strong>' . (int) $r['entraron'] . '</strong> entraron, ' . (int) $r['repetidos'] . ' ya estaban, ' . (int) $r['bajas'] . ' pidieron baja (no se cargan), ' . (int) $r['rechazados'] . ' rechazados.</p>';
			if (!empty($r['errores'])) { echo '<ul style="list-style:disc;margin-left:20px">'; foreach ($r['errores'] as $e) { echo '<li>' . esc_html($e) . '</li>'; } echo '</ul>'; }
			echo '</div>';
		}
	} elseif (isset($mapa[$p])) { echo '<div class="notice notice-' . ($mapa[$p][0] === 'error' ? 'error' : 'success') . '"><p>' . esc_html($mapa[$p][1]) . '</p></div>'; }
}

function at_pro_tarjetas(): void {
	$c = at_pro_conteos();
	$et = at_pro_etiquetas_estado();
	$ic = at_pro_iconos_estado();
	echo '<div class="at-pro-tarjetas">';
	echo '<div class="at-pro-tarjeta"><span class="dashicons dashicons-groups" aria-hidden="true"></span><strong>' . (int) $c['total'] . '</strong><span>Total</span></div>';
	foreach ($c['estado'] as $e => $n) {
		echo '<div class="at-pro-tarjeta"><span class="dashicons ' . esc_attr($ic[$e]) . '" aria-hidden="true"></span><strong>' . (int) $n . '</strong><span>' . esc_html($et[$e]) . '</span></div>';
	}
	echo '</div>';
	$lista = static function (array $datos, int $max = 8): string {
		if (!$datos) { return '<em>Sin datos todavía</em>'; }
		$h = [];
		foreach (array_slice($datos, 0, $max, true) as $k => $n) { $h[] = esc_html($k) . ' <strong>' . (int) $n . '</strong>'; }
		return implode(' · ', $h);
	};
	echo '<p class="at-pro-desglose"><span class="dashicons dashicons-download" aria-hidden="true"></span> <strong>Por fuente:</strong> ' . $lista($c['fuente']) . '<br><span class="dashicons dashicons-category" aria-hidden="true"></span> <strong>Por rubro:</strong> ' . $lista($c['rubro']) . '</p>';
}

function at_pro_pagina(): void {
	if (!current_user_can('manage_options')) { wp_die('No tienes permiso para ver esta página.'); }
	at_pro_migrar_esquema();
	$f = at_pro_filtros();
	$conteos = at_pro_conteos();
	$et = at_pro_etiquetas_estado();
	$ic = at_pro_iconos_estado();
	echo '<div class="wrap at-pro"><h1><span class="dashicons dashicons-search" aria-hidden="true"></span> Prospección <small class="description">Rastro</small></h1>';
	echo '<style>.at-pro-tarjetas{display:flex;flex-wrap:wrap;gap:10px;margin:14px 0}.at-pro-tarjeta{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:10px 16px;min-width:110px;text-align:center}.at-pro-tarjeta strong{display:block;font-size:24px;line-height:1.2}.at-pro-desglose{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:8px 12px}.at-pro-acciones{display:flex;flex-wrap:wrap;gap:4px}.at-pro-acciones .dashicons{vertical-align:text-bottom;font-size:16px;width:16px;height:16px}.at-pro-estado .dashicons{vertical-align:text-bottom}.at-pro-estado--formulario{color:#1a7f37;font-weight:600}.at-pro-estado--baja,.at-pro-estado--descartado{color:#8c8f94}.at-pro-caja{background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:8px 14px;margin:10px 0}.at-pro-caja summary{cursor:pointer;font-weight:600}.at-pro-caja label{display:block;margin:6px 0 2px}.at-pro-caja .regular-text{width:100%;max-width:420px}</style>';
	at_pro_avisos();
	echo '<p class="description">Aquí vive lo que Rastro encuentra. <strong>Nada sale desde este panel</strong>: copias el correo con diseño, lo mandas tú desde tu correo y marcas «contactado». El primer contacto va solo por correo; WhatsApp aparece cuando el prospecto ya respondió.</p>';
	at_pro_tarjetas();

	// Importar y agregar
	echo '<details class="at-pro-caja"><summary><span class="dashicons dashicons-upload" aria-hidden="true"></span> Importar un CSV (Lead Scout o Sellbird)</summary>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="at_pro_importar">' . wp_nonce_field('at_pro_importar', '_wpnonce', true, false);
	echo '<p class="description">Reconozco las columnas nombre/negocio, web, teléfono, correo, rubro, comuna y señales; el separador puede ser coma, punto y coma o tabulador. Salto los repetidos (por web o teléfono) y nunca vuelvo a cargar a quien pidió baja.</p>';
	echo '<label for="at-pro-archivo">Archivo CSV</label><input type="file" id="at-pro-archivo" name="archivo" accept=".csv,.txt" required> ';
	echo '<label for="at-pro-fuente">Fuente</label><select id="at-pro-fuente" name="fuente">';
	foreach (['lead_scout' => 'Lead Scout', 'sellbird' => 'Sellbird', 'instagram' => 'Instagram', 'manual' => 'Manual'] as $k => $v) { echo '<option value="' . esc_attr($k) . '">' . esc_html($v) . '</option>'; }
	echo '</select> <button type="submit" class="button button-primary"><span class="dashicons dashicons-upload" aria-hidden="true"></span> Importar</button></form></details>';

	echo '<details class="at-pro-caja"><summary><span class="dashicons dashicons-plus-alt" aria-hidden="true"></span> Agregar un prospecto a mano</summary>';
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="at_pro_crear">' . wp_nonce_field('at_pro_crear', '_wpnonce', true, false);
	foreach (['negocio' => ['Negocio', 190, true], 'rubro' => ['Rubro', 100, false], 'comuna' => ['Comuna', 100, false], 'web' => ['Web', 500, false], 'telefono' => ['Teléfono', 30, false], 'correo' => ['Correo del negocio', 190, false], 'senales' => ['Lo que vimos (señal real)', 1000, false], 'servicio_sugerido' => ['Servicio de AT que le calza', 100, false]] as $k => [$lbl, $max, $req]) {
		echo '<label for="at-pro-n-' . esc_attr($k) . '">' . esc_html($lbl) . '</label><input type="text" class="regular-text" id="at-pro-n-' . esc_attr($k) . '" name="' . esc_attr($k) . '" maxlength="' . (int) $max . '"' . ($req ? ' required' : '') . '>';
	}
	echo '<p class="description">Solo datos que el negocio publica (nombre comercial, web, teléfono y correo de contacto). Ni datos de personas particulares.</p>';
	echo '<p><button type="submit" class="button button-primary"><span class="dashicons dashicons-plus" aria-hidden="true"></span> Agregar</button></p></form></details>';

	// Vistas por estado
	$sin_estado = $f;
	$sin_estado['estado'] = '';
	$base_args = array_filter(['fuente' => $f['fuente'], 'rubro' => $f['rubro'], 's' => $f['s']]);
	echo '<ul class="subsubsub" style="float:none;margin:8px 0">';
	$partes = ['<li><a href="' . esc_url(at_pro_url_panel($base_args)) . '"' . ($f['estado'] === '' ? ' class="current"' : '') . '><span class="dashicons dashicons-list-view" aria-hidden="true"></span> Todos <span class="count">(' . (int) $conteos['total'] . ')</span></a></li>'];
	foreach ($et as $k => $v) {
		$partes[] = '<li><a href="' . esc_url(at_pro_url_panel($base_args + ['estado' => $k])) . '"' . ($f['estado'] === $k ? ' class="current"' : '') . '><span class="dashicons ' . esc_attr($ic[$k]) . '" aria-hidden="true"></span> ' . esc_html($v) . ' <span class="count">(' . (int) $conteos['estado'][$k] . ')</span></a></li>';
	}
	echo implode(' | ', $partes) . '</ul>';

	// Filtros y búsqueda (GET)
	echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="at-pro-filtros"><input type="hidden" name="page" value="at-prospeccion">';
	if ($f['estado'] !== '') { echo '<input type="hidden" name="estado" value="' . esc_attr($f['estado']) . '">'; }
	echo '<label class="screen-reader-text" for="at-pro-f-fuente">Fuente</label><select name="fuente" id="at-pro-f-fuente"><option value="">Todas las fuentes</option>';
	foreach (at_pro_fuentes() as $k) { echo '<option value="' . esc_attr($k) . '"' . selected($f['fuente'], $k, false) . '>' . esc_html($k) . '</option>'; }
	echo '</select> <label class="screen-reader-text" for="at-pro-f-rubro">Rubro</label><select name="rubro" id="at-pro-f-rubro"><option value="">Todos los rubros</option>';
	foreach (array_keys($conteos['rubro']) as $r) { echo '<option value="' . esc_attr($r) . '"' . selected($f['rubro'], $r, false) . '>' . esc_html($r) . '</option>'; }
	echo '</select> <label class="screen-reader-text" for="at-pro-f-s">Buscar</label><input type="search" id="at-pro-f-s" name="s" value="' . esc_attr($f['s']) . '" placeholder="Negocio, web, teléfono, correo…"> <button type="submit" class="button"><span class="dashicons dashicons-search" aria-hidden="true"></span> Filtrar</button></form>';

	$tabla = new AT_Prospectos_Lista($f);
	$tabla->prepare_items();
	echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="at_pro_masivo">' . wp_nonce_field('at_pro_masivo', 'at_pro_nonce_masivo', false, false);
	$tabla->display();
	echo '</form>';
	echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".at-pro-copiar");if(!b)return;var t=b.getAttribute("data-mensaje")||"";var ok=function(){var o=b.innerHTML;b.textContent="Copiado";setTimeout(function(){b.innerHTML=o},1600)};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(ok)}else{var a=document.createElement("textarea");a.value=t;document.body.appendChild(a);a.select();document.execCommand("copy");a.remove();ok()}});</script>';

	echo <<<'ATPRO'
<style>.at-pro-modal{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:16px}.at-pro-modal__caja{background:#fff;border-radius:8px;width:100%;max-width:680px;max-height:94vh;display:flex;flex-direction:column;overflow:hidden}.at-pro-modal__cab{padding:12px 16px;border-bottom:1px solid #dcdcde}.at-pro-modal__cab strong{display:block;word-break:break-word}.at-pro-modal__marco{flex:1;border:0;width:100%;min-height:60vh;background:#f4f4f4}.at-pro-modal__pie{padding:10px 16px;border-top:1px solid #dcdcde;display:flex;flex-wrap:wrap;gap:6px;align-items:center}.at-pro-modal .dashicons{vertical-align:text-bottom}</style>
<div id="at-pro-aviso" class="screen-reader-text" role="status" aria-live="polite"></div>
<script>
(function(){
var aviso=document.getElementById("at-pro-aviso");
function decir(t){if(aviso){aviso.textContent="";setTimeout(function(){aviso.textContent=t},30)}}
function marcar(b,t){var o=b.innerHTML;b.textContent=t;decir(t);setTimeout(function(){b.innerHTML=o},1600)}
function textoSimple(txt,html){
 var d=document.createElement("div");d.contentEditable="true";d.setAttribute("aria-hidden","true");
 d.style.cssText="position:fixed;left:-9999px;top:0;opacity:0";d.innerHTML=html;document.body.appendChild(d);
 var r=document.createRange();r.selectNodeContents(d);var s=window.getSelection();s.removeAllRanges();s.addRange(r);
 var ok=false;try{ok=document.execCommand("copy")}catch(e){}
 s.removeAllRanges();d.remove();return ok}
function copiarCorreo(o,b){
 var ok=function(){marcar(b,"Copiado")};var fallo=function(){marcar(b,"No se pudo copiar")};
 if(navigator.clipboard&&window.ClipboardItem&&navigator.clipboard.write){
  navigator.clipboard.write([new ClipboardItem({"text/html":new Blob([o.html],{type:"text/html"}),"text/plain":new Blob([o.texto],{type:"text/plain"})})]).then(ok,function(){textoSimple(o.texto,o.html)?ok():fallo()});
 }else{textoSimple(o.texto,o.html)?ok():fallo()}}
function copiarTexto(t,b){
 var ok=function(){marcar(b,"Copiado")};
 if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(ok)}
 else{var a=document.createElement("textarea");a.value=t;document.body.appendChild(a);a.select();document.execCommand("copy");a.remove();ok()}}
function datos(b){return{asunto:b.getAttribute("data-asunto")||"",html:b.getAttribute("data-html")||"",texto:b.getAttribute("data-texto")||""}}
var previo=null;
function cerrar(){var m=document.getElementById("at-pro-modal");if(m){m.remove()}if(previo){previo.focus();previo=null}}
function abrir(o,origen){
 cerrar();previo=origen;
 var m=document.createElement("div");m.id="at-pro-modal";m.className="at-pro-modal";
 var c=document.createElement("div");c.className="at-pro-modal__caja";c.setAttribute("role","dialog");c.setAttribute("aria-modal","true");c.setAttribute("aria-labelledby","at-pro-modal-t");
 var h=document.createElement("div");h.className="at-pro-modal__cab";
 var e=document.createElement("span");e.className="description";e.textContent="Asunto";
 var t=document.createElement("strong");t.id="at-pro-modal-t";t.textContent=o.asunto;h.appendChild(e);h.appendChild(t);
 var f=document.createElement("iframe");f.className="at-pro-modal__marco";f.setAttribute("sandbox","");f.setAttribute("title","Vista previa del correo");f.srcdoc=o.html;
 var p=document.createElement("div");p.className="at-pro-modal__pie";
 function boton(txt,ico,fn){var x=document.createElement("button");x.type="button";x.className="button";x.innerHTML='<span class="dashicons dashicons-'+ico+'" aria-hidden="true"></span> '+txt;x.addEventListener("click",function(){fn(x)});p.appendChild(x);return x}
 var primero=boton("Copiar correo con diseño","email-alt2",function(x){copiarCorreo(o,x)});
 boton("Copiar asunto","clipboard",function(x){copiarTexto(o.asunto,x)});
 boton("Cerrar","no-alt",cerrar);
 c.appendChild(h);c.appendChild(f);c.appendChild(p);m.appendChild(c);
 m.addEventListener("click",function(ev){if(ev.target===m){cerrar()}});
 m.addEventListener("keydown",function(ev){if(ev.key==="Escape"){cerrar()}});
 document.body.appendChild(m);primero.focus();}
document.addEventListener("click",function(e){
 var v=e.target.closest(".at-pro-ver-correo");
 if(v){var w=v.parentNode.querySelector(".at-pro-copiar-correo");if(w){abrir(datos(w),v)}return}
 var b=e.target.closest(".at-pro-copiar-correo");
 if(b){copiarCorreo(datos(b),b)}
});
})();
</script>
ATPRO;
	echo '</div>';
}
