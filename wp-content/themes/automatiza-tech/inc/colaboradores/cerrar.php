<?php
/**
 * El colaborador no sale de «Mis clientes». Varias acciones ajax del admin (MAXTECH, por ejemplo) solo piden un nonce
 * que se imprime en cualquier página del admin; por eso, para este rol, el admin entero queda cerrado salvo las rutas
 * de at_colab_ruta_permitida(), y el widget MAXTECH no se dibuja.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** Colaborador sin permisos de edición: a quien se le cierra el admin. */
function at_colab_cerrado(): bool {
	return at_colab_es_colaborador() && !current_user_can('edit_posts');
}

function at_colab_cerrar_admin(): void {
	if (!at_colab_cerrado()) {
		return;
	}
	global $pagenow;
	$ajax = wp_doing_ajax();
	if (at_colab_ruta_permitida((string) $pagenow, (array) $_GET, (array) $_POST, $ajax)) {
		return;
	}
	if (defined('AT_COLAB_PRUEBAS')) {
		throw new AtColabRedireccion($ajax ? 'ajax' : at_colab_url());
	}
	if ($ajax) {
		wp_die('0', '', ['response' => 403]);
	}
	wp_safe_redirect(at_colab_url());
	exit;
}
add_action('admin_init', 'at_colab_cerrar_admin', 1);

/** Quita del pie del admin el widget MAXTECH (y con él su nonce). */
function at_colab_quitar_widget(): void {
	global $wp_filter;
	if (!isset($wp_filter['admin_footer'])) {
		return;
	}
	foreach ($wp_filter['admin_footer']->callbacks as $prioridad => $cbs) {
		foreach ($cbs as $cb) {
			$f = $cb['function'];
			if (is_array($f) && is_object($f[0]) && class_exists('ARIA_Widget_Flotante') && $f[0] instanceof ARIA_Widget_Flotante) {
				remove_action('admin_footer', $f, $prioridad);
			}
		}
	}
}
add_action('admin_init', function () {
	if (at_colab_cerrado()) {
		at_colab_quitar_widget();
	}
}, 2);
