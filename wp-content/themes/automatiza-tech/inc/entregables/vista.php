<?php
/**
 * Página pública del entregable (ver-entregable.php) y la acción del formulario de notas (cliente, sin sesión).
 */
if (!defined('ABSPATH')) {
	exit;
}

function at_en_ip_hash(string $ip): string {
	return hash('sha256', $ip . '|' . wp_salt('nonce'));
}

function at_en_token_hoy(string $codigo): string {
	return at_en_token($codigo, intdiv(time(), 86400), wp_salt('nonce'));
}

/** Vuelta a la página con el mensaje; $n (1..3) solo acompaña a los errores de una imagen concreta. */
function at_en_url_pagina_msg(object $ent, string $msg, int $n = 0): string {
	return at_en_url_pagina(home_url(), (string) $ent->codigo) . '&en_msg=' . rawurlencode($msg) . ($n > 0 ? '&en_img=' . $n : '') . '#notas';
}

function at_en_fecha(string $mysql): string {
	$t = strtotime($mysql);
	return $t ? date_i18n('j \d\e F \d\e Y, H:i', $t) : '';
}

/**
 * Script del formulario (mejora progresiva: sin JavaScript el formulario funciona igual y el servidor revisa todo):
 * avisa antes de enviar si hay más de 3 imágenes o alguna pesa más de 5 MB, y guarda el nombre y la nota en este
 * navegador (sessionStorage, solo esta pestaña) para no perderlos si el envío vuelve con un error.
 */
function at_en_script_formulario(): string {
	return <<<'JS'
<script>(function(){var f=document.getElementById("en-form");if(!f){return;}
var e=document.getElementById("en-error"),n=f.elements["nombre"],t=f.elements["texto"],i=f.elements["imagenes[]"],k="at_en_borrador_"+f.getAttribute("data-codigo"),s=null;
try{s=window.sessionStorage;}catch(x){s=null;}
function g(){if(!s){return;}try{s.setItem(k,JSON.stringify({n:n.value,t:t.value}));}catch(x){}}
if(s){try{if(f.getAttribute("data-ok")==="1"){s.removeItem(k);}else{var b=JSON.parse(s.getItem(k)||"null");if(b){if(b.n){n.value=b.n;}if(b.t&&!t.value){t.value=b.t;}}}}catch(x){}}
n.addEventListener("input",g);t.addEventListener("input",g);
f.addEventListener("submit",function(ev){var m="",l=(i&&i.files)?i.files:[];
if(l.length>3){m=f.getAttribute("data-m-muchas");}
else{for(var j=0;j<l.length;j++){if(l[j].size>5242880){m=f.getAttribute("data-m-peso").replace("%d",j+1);break;}}}
if(m){ev.preventDefault();e.textContent=m;e.hidden=false;if(e.scrollIntoView){e.scrollIntoView({block:"center"});}}});
})();</script>
JS;
}

/**
 * HTML completo de la página. $ent null o sin ninguna versión enviada = «no disponible». $msg = clave de at_en_mensajes();
 * $n_img = número de la imagen (1..3) cuando el mensaje es de una imagen concreta. El cliente ve la última versión ENVIADA.
 */
function at_en_html_pagina(?object $ent, string $msg = '', int $n_img = 0): string {
	$m = at_en_mensajes();
	$h = function ($s) { return esc_html((string) $s); };
	$cab = '<!DOCTYPE html><html lang="es-CL"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Entregable — AutomatizaTech</title><style>'
		. 'body{margin:0;background:#f0f2f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937}.caja{max-width:760px;margin:0 auto;padding:16px}'
		. '.cab{background:linear-gradient(135deg,#1e3a8a,#06d6a0);color:#fff;border-radius:12px;padding:22px;text-align:center}.cab img{max-width:120px}'
		. '.tarjeta{background:#fff;border-radius:12px;padding:18px;margin-top:14px;box-shadow:0 1px 4px rgba(0,0,0,.06)}'
		. '.btn{display:inline-block;background:#1e3a8a;color:#fff;text-decoration:none;font-weight:bold;padding:12px 24px;border-radius:25px;border:0;font-size:16px;cursor:pointer}'
		. '.nota{border-radius:12px;padding:12px 14px;margin:10px 0;max-width:88%}.cli{background:#eef2ff;margin-right:auto}.at{background:#ecfdf5;margin-left:auto}'
		. '.meta{font-size:12px;color:#6b7280;margin-bottom:4px}.imgs img{max-width:120px;max-height:120px;border-radius:8px;margin:6px 6px 0 0;border:1px solid #e5e7eb}'
		. 'label{display:block;font-weight:bold;margin:12px 0 4px}input[type=text],textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:16px}textarea{min-height:120px}'
		. '.aviso{padding:12px;border-radius:8px;margin-top:12px}.ok{background:#ecfdf5;color:#065f46}.err{background:#fef2f2;color:#991b1b}'
		. '</style></head><body><div class="caja"><div class="cab"><img src="' . esc_url(at_en_logo()) . '" alt="AutomatizaTech">';
	$pie = '<p style="text-align:center;color:#6b7280;font-size:13px;margin:22px 0">© ' . date('Y') . ' AutomatizaTech · <a href="https://automatizatech.cl/">automatizatech.cl</a></p></div></body></html>';
	$pub = $ent ? at_en_version_publica($ent) : null;
	if (!$ent || !$pub) {
		return $cab . '<h1 style="font-size:20px">' . $h($m['no_disponible']) . '</h1></div>' . $pie;
	}
	$cli = at_en_cliente($ent);
	$versiones = at_en_versiones_enviadas((int) $ent->id); // de la más nueva a la más antigua; las sin enviar no se ven
	$html = $cab . '<h1 style="margin:10px 0 4px;font-size:22px">' . $h($cli['titulo']) . '</h1><div>' . $h($cli['empresa']) . '</div></div>';
	$html .= '<div class="tarjeta"><p style="margin-top:0">Versión vigente: <strong>' . (int) $pub->numero . '</strong> · ' . $h(at_en_fecha((string) $pub->creado_at)) . '</p>'
		. '<p><a class="btn" href="' . esc_url((string) $pub->url) . '" target="_blank" rel="noopener">Abrir la versión ' . (int) $pub->numero . '</a></p>';
	$anteriores = array_filter($versiones, function ($v) use ($pub) { return (int) $v->numero !== (int) $pub->numero; });
	if ($anteriores) {
		$html .= '<p style="margin-bottom:4px"><strong>Versiones anteriores</strong></p><ul>';
		foreach ($anteriores as $v) {
			$html .= '<li><a href="' . esc_url((string) $v->url) . '" target="_blank" rel="noopener">Versión ' . (int) $v->numero . '</a> · ' . $h(at_en_fecha((string) $v->creado_at)) . '</li>';
		}
		$html .= '</ul>';
	}
	$html .= '</div><div class="tarjeta" id="notas"><h2 style="margin-top:0;font-size:18px">Notas y observaciones</h2>';
	$notas = at_en_notas((int) $ent->id);
	if (!$notas) {
		$html .= '<p style="color:#6b7280">Todavía no hay notas.</p>';
	}
	foreach ($notas as $n) {
		$imgs = json_decode((string) $n->imagenes, true);
		$html .= '<div class="nota ' . ($n->autor === 'at' ? 'at' : 'cli') . '"><div class="meta">' . $h($n->autor === 'at' ? 'AutomatizaTech · ' . $n->nombre : $n->nombre) . ' · sobre la versión ' . (int) $n->version_numero . ' · ' . $h(at_en_fecha((string) $n->creado_at)) . '</div>'
			. nl2br(esc_html((string) $n->texto));
		if (is_array($imgs) && $imgs) {
			$html .= '<div class="imgs">';
			foreach ($imgs as $img) {
				$u = at_en_url_imagen(home_url(), (string) $ent->codigo, (string) $img);
				if ($u !== '') {
					// $u sale de at_en_url_imagen (código y nombre validados): esc_attr deja &amp; (esc_url lo dejaría como &#038;).
					$html .= '<a href="' . esc_attr($u) . '" target="_blank" rel="noopener"><img src="' . esc_attr($u) . '" alt="Imagen adjunta" loading="lazy"></a>';
				}
			}
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	if ($msg !== '' && isset($m[$msg])) {
		$html .= '<div class="aviso ' . ($msg === 'nota_ok' ? 'ok' : 'err') . '">' . $h(at_en_formatear_mensaje($m[$msg], $n_img)) . '</div>';
	}
	if ($ent->estado !== 'abierto') {
		$html .= '<div class="aviso err">' . $h($m['cerrado']) . '</div></div>';
		return $html . $pie;
	}
	// El código también viaja en la URL de la acción: si el envío supera post_max_size PHP vacía $_POST y solo queda la URL.
	$accion_url = admin_url('admin-post.php') . '?action=at_en_nota&codigo=' . rawurlencode((string) $ent->codigo);
	$html .= '<form id="en-form" method="post" action="' . esc_url($accion_url) . '" enctype="multipart/form-data" data-codigo="' . esc_attr((string) $ent->codigo) . '"'
		. ($msg === 'nota_ok' ? ' data-ok="1"' : '')
		. ' data-m-muchas="' . esc_attr($m['muchas_imagenes']) . '" data-m-peso="' . esc_attr($m['img_peso']) . '">'
		. '<input type="hidden" name="action" value="at_en_nota"><input type="hidden" name="codigo" value="' . esc_attr((string) $ent->codigo) . '">'
		. '<input type="hidden" name="token" value="' . esc_attr(at_en_token_hoy((string) $ent->codigo)) . '">'
		. '<label for="en-nombre">Tu nombre</label><input type="text" id="en-nombre" name="nombre" maxlength="80" required value="' . esc_attr($cli['nombre']) . '">'
		. '<label for="en-texto">Tu nota u observación</label><textarea id="en-texto" name="texto" maxlength="3000" required></textarea>'
		. '<label for="en-img">Imágenes (opcional, hasta 3; JPG o PNG de hasta 5 MB)</label><input type="file" id="en-img" name="imagenes[]" accept="image/jpeg,image/png" multiple>'
		. '<div class="aviso err" id="en-error" role="alert" hidden></div>'
		. '<p><button class="btn" type="submit">Enviar nota</button></p></form>' . at_en_script_formulario() . '</div>';
	return $html . $pie;
}

/** Acción del formulario (cliente sin sesión o Luis con sesión). Siempre vuelve a la página con en_msg. */
function at_en_accion_nota(): void {
	// Con un envío que superó post_max_size PHP vacía $_POST: el código se lee también de la URL de la acción.
	$bruto = isset($_POST['codigo']) ? $_POST['codigo'] : (isset($_GET['codigo']) ? $_GET['codigo'] : '');
	$codigo = is_string($bruto) ? preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($bruto)) : '';
	$ent = at_en_por_codigo((string) $codigo);
	$pub = $ent ? at_en_version_publica($ent) : null;
	if (!$ent || !$pub) {
		wp_safe_redirect(home_url('/ver-entregable.php?id=' . rawurlencode((string) $codigo) . '&en_msg=no_disponible'));
		exit;
	}
	$volver = function (string $msg, int $n = 0) use ($ent) {
		wp_safe_redirect(at_en_url_pagina_msg($ent, $msg, $n));
		exit;
	};
	if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
		$volver('envio_grande');
	}
	if ($ent->estado !== 'abierto') {
		$volver('cerrado');
	}
	if (!at_en_token_valido((string) wp_unslash($_POST['token'] ?? ''), (string) $ent->codigo, intdiv(time(), 86400), wp_salt('nonce'))) {
		$volver('sesion_vencida');
	}
	$ip = at_en_ip_hash((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	$clave = 'at_en_lim_' . (int) $ent->id . '_' . substr($ip, 0, 20);
	$intentos = (int) get_transient($clave);
	if ($intentos >= AT_EN_NOTAS_POR_HORA) {
		$volver('muchos_intentos');
	}
	$v = at_en_validar_nota((string) wp_unslash($_POST['nombre'] ?? ''), (string) wp_unslash($_POST['texto'] ?? ''));
	if (!$v['ok']) {
		$volver($v['error']);
	}
	$archivos = at_en_archivos_subidos(isset($_FILES['imagenes']) && is_array($_FILES['imagenes']) ? $_FILES['imagenes'] : []);
	$imagenes = $archivos ? at_en_procesar_imagenes($archivos, (int) $ent->id) : [];
	if (is_wp_error($imagenes)) {
		$datos = $imagenes->get_error_data();
		[$clave, $n] = at_en_redireccion_error_imagen((string) $imagenes->get_error_code(), is_array($datos) ? (int) ($datos['n'] ?? 0) : 0);
		$volver($clave, $n);
	}
	// La nota va sobre la versión que el cliente está viendo (la última enviada), no sobre una creada y aún sin enviar.
	$id = at_en_agregar_nota((int) $ent->id, 'cliente', $v['nombre'], $v['texto'], $imagenes, $ip, (int) $pub->numero);
	if (is_wp_error($id)) {
		$volver('no_guardo');
	}
	set_transient($clave, $intentos + 1, HOUR_IN_SECONDS);
	$cli = at_en_cliente($ent);
	at_en_historial((int) $ent->crm_id, 'entregable_nota', $cli['titulo'] . ': nota del cliente sobre la versión ' . (int) $pub->numero, at_en_extracto($v['texto'], 300) . ($imagenes ? ' (' . count($imagenes) . ' imagen/es)' : ''));
	at_en_avisar_nota((int) $id);
	$volver('nota_ok');
}
add_action('admin_post_nopriv_at_en_nota', 'at_en_accion_nota');
add_action('admin_post_at_en_nota', 'at_en_accion_nota');
