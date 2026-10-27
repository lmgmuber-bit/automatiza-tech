<?php
// El colaborador no sale de «Mis clientes»: páginas del admin, admin-post y admin-ajax cerrados; sin el widget MAXTECH.
// Uso: bash tests/colaboradores/correr.sh cerrar
require __DIR__ . '/wp-bootstrap.php';
exigir('at_colab_ruta_permitida', 'at_colab_cerrar_admin', 'at_colab_quitar_widget');

// Tabla de rutas (pura).
ok(at_colab_ruta_permitida('admin.php', ['page' => 'at-mis-clientes'], [], false), 'ruta: Mis clientes');
ok(at_colab_ruta_permitida('profile.php', [], [], false), 'ruta: su perfil');
ok(at_colab_ruta_permitida('admin-post.php', [], ['action' => 'at_colab_nota'], false), 'ruta: admin-post de la nota');
ok(at_colab_ruta_permitida('admin-post.php', [], ['action' => 'at_colab_responder'], false), 'ruta: admin-post de la respuesta');
ok(!at_colab_ruta_permitida('admin-post.php', [], ['action' => 'at_en_responder'], false), 'ruta: otra acción de admin-post, no');
ok(!at_colab_ruta_permitida('admin.php', ['page' => 'automatiza-crm-ficha'], [], false), 'ruta: la ficha del CRM, no');
ok(!at_colab_ruta_permitida('index.php', [], [], false), 'ruta: el escritorio, no');
ok(!at_colab_ruta_permitida('edit.php', [], [], false), 'ruta: entradas, no');
ok(at_colab_ruta_permitida('admin-ajax.php', ['action' => 'heartbeat'], [], true), 'ajax: heartbeat sí');
ok(!at_colab_ruta_permitida('admin-ajax.php', ['action' => 'maxtech_buscar_cliente'], [], true), 'ajax: maxtech_buscar_cliente no');
ok(!at_colab_ruta_permitida('admin-ajax.php', [], ['action' => 'aria_chat'], true), 'ajax: aria_chat por POST no');
ok(!at_colab_ruta_permitida('admin-ajax.php', [], [], true), 'ajax: sin acción no');

$admin = col_admin_id();
$colab = col_crear_usuario('at_colaborador', []);

/** Corre la puerta del admin con esta ruta; devuelve 'bloqueado:…' o 'pasa'. */
function col_puerta(string $pagenow, array $get, array $post, bool $ajax): string {
	$GLOBALS['pagenow'] = $pagenow;
	$_GET = $get;
	$_POST = $post;
	$_REQUEST = array_merge($get, $post);
	$f = function () use ($ajax) { return $ajax; };
	add_filter('wp_doing_ajax', $f, 99);
	try {
		at_colab_cerrar_admin();
		$r = 'pasa';
	} catch (AtColabRedireccion $e) {
		$r = 'bloqueado:' . $e->url;
	}
	remove_filter('wp_doing_ajax', $f, 99);
	return $r;
}

wp_set_current_user($colab);
ok(col_puerta('admin.php', ['page' => 'at-mis-clientes'], [], false) === 'pasa', 'colaborador: entra a Mis clientes');
ok(strpos(col_puerta('index.php', [], [], false), 'page=at-mis-clientes') !== false, 'colaborador: el escritorio lo manda a Mis clientes');
ok(strpos(col_puerta('admin-ajax.php', ['action' => 'maxtech_buscar_cliente', 'busqueda' => 'a'], [], true), 'bloqueado:') === 0, 'colaborador: admin-ajax de MAXTECH bloqueado');
ok(strpos(col_puerta('admin-ajax.php', [], ['action' => 'aria_chat'], true), 'bloqueado:') === 0, 'colaborador: aria_chat bloqueado');
ok(col_puerta('admin-ajax.php', [], ['action' => 'heartbeat'], true) === 'pasa', 'colaborador: heartbeat pasa');
ok(strpos(col_puerta('admin-post.php', [], ['action' => 'at_en_responder'], false), 'bloqueado:') === 0, 'colaborador: admin-post del panel de entregables bloqueado');

wp_set_current_user($admin);
ok(col_puerta('index.php', [], [], false) === 'pasa', 'administrador: nada cambia');
ok(col_puerta('admin-ajax.php', ['action' => 'maxtech_buscar_cliente'], [], true) === 'pasa', 'administrador: su ajax sigue');

// Widget MAXTECH: se dibuja para el administrador y no para el colaborador.
if (class_exists('ARIA_Widget_Flotante')) {
	ob_start();
	do_action('admin_footer');
	$admin_footer = ob_get_clean();
	ok(strpos($admin_footer, 'toggleAriaPanel') !== false, 'administrador: ve el widget MAXTECH');
	wp_set_current_user($colab);
	at_colab_quitar_widget();
	ob_start();
	do_action('admin_footer');
	$colab_footer = ob_get_clean();
	ok(strpos($colab_footer, 'toggleAriaPanel') === false && strpos($colab_footer, 'aria_nonce') === false, 'colaborador: sin widget MAXTECH ni su nonce');
} else {
	ok(false, 'el WordPress local debería cargar el widget MAXTECH (mu-plugin aria-widget-flotante.php)');
}

fin();
