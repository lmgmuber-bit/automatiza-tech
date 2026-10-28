<?php
/**
 * Página «Mis clientes»: lista de los clientes asignados y, de cada uno, su historial y sus entregables.
 * No imprime el nonce del CRM ni enlaza a la ficha del CRM.
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_COLAB_PAGINA = 'at-mis-clientes';

function at_colab_url(int $crm_id = 0, string $msg = ''): string {
	$args = ['page' => AT_COLAB_PAGINA];
	if ($crm_id > 0) {
		$args['cliente'] = $crm_id;
	}
	if ($msg !== '') {
		$args['msg'] = $msg;
	}
	return add_query_arg($args, admin_url('admin.php'));
}

function at_colab_mensajes(): array {
	$propios = [
		'nota_ok'       => ['ok', 'Nota agregada al historial.'],
		'respuesta_ok'  => ['ok', 'Respuesta enviada.'],
		'respuesta_sin_aviso' => ['err', 'La respuesta quedó guardada, pero no se pudo avisar al cliente por correo.'],
		'sin_acceso'    => ['err', 'No tienes acceso a este cliente.'],
		'nota_vacia'    => ['err', 'Escribe la nota antes de guardarla.'],
		'nota_larga'    => ['err', 'La nota es muy larga (máximo 6.000 caracteres).'],
		'ya_respondida' => ['err', 'Ese comentario ya fue respondido.'],
	];
	$entregables = function_exists('at_en_mensajes') ? array_map(function ($t) { return ['err', $t]; }, at_en_mensajes()) : [];
	return $propios + $entregables;
}

function at_colab_aviso(string $msg): string {
	$m = at_colab_mensajes()[$msg] ?? null;
	if (!$m) {
		return '';
	}
	return '<div class="notice notice-' . ($m[0] === 'ok' ? 'success' : 'error') . '"><p>' . esc_html($m[1]) . '</p></div>';
}

function at_colab_html_lista(int $user_id): string {
	$ids = user_can($user_id, 'manage_options') ? [] : at_colab_ids_asignados($user_id);
	$filas = '';
	foreach ($ids as $id) {
		$c = at_colab_cliente($id);
		if (!$c) {
			continue; // asignado, pero ya no existe en el CRM
		}
		$filas .= '<li style="margin:6px 0"><a href="' . esc_url(at_colab_url($c['id'])) . '"><strong>' . esc_html($c['nombre']) . '</strong></a>'
			. ($c['empresa'] !== '' ? ' · ' . esc_html($c['empresa']) : '') . '</li>';
	}
	if ($filas === '') {
		return '<p>' . (user_can($user_id, 'manage_options')
			? 'Como administrador, abre un cliente con <code>&amp;cliente=ID</code> para ver lo que vería un colaborador.'
			: 'Todavía no tienes clientes asignados.') . '</p>';
	}
	return '<ul>' . $filas . '</ul>';
}

function at_colab_html_historial(int $crm_id): string {
	$h = at_colab_historial($crm_id);
	if (!$h) {
		return '<p>Todavía no hay movimientos.</p>';
	}
	$html = '';
	foreach ($h as $f) {
		$t = strtotime((string) $f['fecha']);
		$html .= '<div style="border-left:3px solid #1e3a8a;background:#fff;padding:8px 12px;margin:8px 0">'
			. '<small>' . esc_html($t ? date_i18n('j \d\e F \d\e Y, H:i', $t) : '') . ($f['autor'] !== '' ? ' · ' . esc_html($f['autor']) : '') . ' ' . $f['origen_html'] . '</small>'
			. '<div><strong>' . esc_html((string) $f['title']) . '</strong></div>'
			. ((string) $f['description'] !== '' ? '<div style="white-space:pre-wrap;color:#444">' . esc_html((string) $f['description']) . '</div>' : '')
			. '</div>';
	}
	return $html;
}

function at_colab_html_form_nota(int $crm_id): string {
	return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:10px 0 18px">'
		. '<input type="hidden" name="action" value="at_colab_nota"><input type="hidden" name="crm_id" value="' . $crm_id . '">'
		. wp_nonce_field('at_colab_nota_' . $crm_id, '_at_colab', true, false)
		. '<p><label for="at-colab-nota"><strong>Agregar una nota al historial</strong></label><br>'
		. '<span class="description">El cliente también ve estas notas en su portal: escribe como si te leyera.</span><br>'
		. '<textarea id="at-colab-nota" name="nota" rows="3" maxlength="6000" style="width:100%;max-width:680px" required></textarea></p>'
		. '<p><button type="submit" class="button button-primary">Guardar nota</button></p></form>';
}

function at_colab_html_entregables(int $crm_id): string {
	// at_en_de_crm devuelve los detalles 'entregable' del cliente; solo los activados (en_id) tienen versiones y notas.
	$ents = [];
	foreach (function_exists('at_en_de_crm') ? at_en_de_crm($crm_id) : [] as $d) {
		$e = !empty($d->en_id) ? at_en_por_id((int) $d->en_id) : null;
		if ($e && (int) $e->crm_id === $crm_id) {
			$ents[] = [$e, trim((string) $d->title) !== '' ? (string) $d->title : 'Entregable'];
		}
	}
	if (!$ents) {
		return '<p>Este cliente no tiene entregables.</p>';
	}
	$html = '';
	foreach ($ents as [$e, $titulo]) {
		$html .= '<div style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:10px 14px;margin:10px 0"><h3 style="margin:4px 0 8px">' . esc_html($titulo) . '</h3>';
		$versiones = at_en_versiones_enviadas((int) $e->id);
		foreach ($versiones as $v) {
			$html .= '<div style="background:#eef2ff;padding:8px;border-radius:6px;margin:6px 0"><strong>Versión ' . (int) $v->numero . '</strong> · '
				. esc_html(at_en_fecha((string) $v->creado_at)) . ' · <a href="' . esc_url((string) $v->url) . '" target="_blank" rel="noopener">' . esc_html((string) $v->url) . '</a>'
				. ((string) $v->mensaje !== '' ? '<details><summary>Mensaje</summary>' . at_en_mensaje_html((string) $v->mensaje) . '</details>' : '') . '</div>';
		}
		if (!$versiones) {
			$html .= '<p>Todavía no se envía ninguna versión.</p>';
		}
		foreach (at_en_notas((int) $e->id) as $n) {
			$es_cli = $n->autor === 'cliente';
			$imgs = json_decode((string) $n->imagenes, true);
			$mini = '';
			foreach (is_array($imgs) ? $imgs : [] as $img) {
				$u = at_en_url_imagen(home_url(), (string) $e->codigo, (string) $img);
				$mini .= $u !== '' ? '<a href="' . esc_url($u) . '" target="_blank" rel="noopener"><img src="' . esc_url($u) . '" alt="" style="max-width:90px;max-height:90px;margin:4px;border-radius:4px"></a>' : '';
			}
			$html .= '<div style="background:' . ($es_cli ? '#fff7ed' : '#ecfdf5') . ';padding:8px;border-radius:6px;margin:6px 0 6px ' . ($es_cli ? '0' : '40px') . '">'
				. '<small>' . esc_html(($es_cli ? 'Cliente · ' : 'Equipo · ') . $n->nombre) . ' · sobre la versión ' . (int) $n->version_numero . ' · ' . esc_html(at_en_fecha((string) $n->creado_at)) . '</small><br>'
				. nl2br(esc_html((string) $n->texto)) . ($mini !== '' ? '<div>' . $mini . '</div>' : '');
			if ($es_cli && !(int) $n->respondida) {
				$html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" style="margin-top:6px">'
					. '<input type="hidden" name="action" value="at_colab_responder"><input type="hidden" name="nota_id" value="' . (int) $n->id . '">'
					. wp_nonce_field('at_colab_resp_' . (int) $n->id, '_at_colab', true, false)
					. '<textarea name="texto" rows="2" style="width:100%;max-width:640px" required></textarea><br>'
					. '<input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple> '
					. '<label><input type="checkbox" name="avisar" value="1" checked> Avisar al cliente por correo</label> '
					. '<button type="submit" class="button">Responder</button></form>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	return $html;
}

/** La vista entera para un usuario: lista (crm_id 0) o un cliente. Se prueba sin navegador. */
function at_colab_html_pagina(int $user_id, int $crm_id, string $msg = ''): string {
	$html = '<div class="wrap"><h1>Mis clientes</h1>' . at_colab_aviso($msg);
	if ($crm_id <= 0) {
		return $html . at_colab_html_lista($user_id) . '</div>';
	}
	if (!at_colab_puede($crm_id, $user_id)) {
		return $html . '<p>No tienes acceso a este cliente.</p><p><a href="' . esc_url(at_colab_url()) . '">← Volver a mis clientes</a></p></div>';
	}
	$c = at_colab_cliente($crm_id);
	return $html . '<p><a href="' . esc_url(at_colab_url()) . '">← Volver a mis clientes</a></p>'
		. '<h2>' . esc_html($c['nombre']) . ($c['empresa'] !== '' ? ' · ' . esc_html($c['empresa']) : '') . '</h2>'
		. '<h2 style="font-size:1.1em">Entregables</h2>' . at_colab_html_entregables($crm_id)
		. '<h2 style="font-size:1.1em">Historial</h2>' . at_colab_html_form_nota($crm_id) . at_colab_html_historial($crm_id)
		. '</div>';
}

function at_colab_render_pagina(): void {
	if (!current_user_can('at_colaborador')) {
		wp_die('No tienes acceso a esta página.');
	}
	$crm_id = isset($_GET['cliente']) ? absint($_GET['cliente']) : 0;
	$msg = isset($_GET['msg']) ? sanitize_key((string) wp_unslash($_GET['msg'])) : '';
	echo at_colab_html_pagina(get_current_user_id(), $crm_id, $msg); // phpcs:ignore -- HTML ya escapado pieza por pieza
}

add_action('admin_menu', function () {
	add_menu_page('Mis clientes', 'Mis clientes', 'at_colaborador', AT_COLAB_PAGINA, 'at_colab_render_pagina', 'dashicons-groups', 3);
});

/** El colaborador entra directo a «Mis clientes»; los demás siguen a donde iban. */
function at_colab_redirigir_login($redirect_to, $requested, $user) {
	if ($user instanceof WP_User && in_array(AT_COLAB_ROL, (array) $user->roles, true)) {
		return admin_url('admin.php?page=' . AT_COLAB_PAGINA);
	}
	return $redirect_to;
}
add_filter('login_redirect', 'at_colab_redirigir_login', 10, 3);
