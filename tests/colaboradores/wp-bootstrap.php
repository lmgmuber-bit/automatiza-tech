<?php
// Carga el WordPress local y, desde este worktree, el módulo de colaboradores (y lo que falte del de entregables
// y de la línea de tiempo). Uso: AT_WP_LOAD=<ruta a wp-load.php> php tests/colaboradores/<prueba>-wp-test.php
$at_colab_wp_load = getenv('AT_WP_LOAD');
if (!$at_colab_wp_load || !is_file($at_colab_wp_load)) {
	fwrite(STDERR, "Falta AT_WP_LOAD: la ruta al wp-load.php del sitio local de prueba.\n");
	exit(2);
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
define('WP_USE_THEMES', false);
define('AT_COLAB_PRUEBAS', true);
require $at_colab_wp_load;

$at_colab_raiz = dirname(__DIR__, 2);
if (!function_exists('at_en_tablas')) {
	require_once $at_colab_raiz . '/wp-content/themes/automatiza-tech/inc/entregables/cargar.php';
}
at_en_migrar_esquema();
if (!function_exists('at_tl_origen_html')) {
	require_once $at_colab_raiz . '/wp-content/mu-plugins/at-timeline-origen.php';
}
require_once $at_colab_raiz . '/wp-content/themes/automatiza-tech/inc/colaboradores/cargar.php';
at_colab_registrar_rol();

// Nunca sale a la red ni manda correos reales.
add_filter('pre_http_request', function ($pre, $args, $url) {
	return new WP_Error('prueba', 'HTTP bloqueado en la prueba: ' . $url);
}, 1, 3);
$GLOBALS['col_correos'] = [];
add_filter('pre_wp_mail', function ($nulo, $atts) {
	if ($nulo !== null) {
		return $nulo;
	}
	$GLOBALS['col_correos'][] = $atts;
	return true;
}, 1, 2);

$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
function exigir(string ...$funciones): void {
	foreach ($funciones as $f) {
		if (!function_exists($f)) {
			echo "FALLA falta la función {$f}()\n\n1 FALLAS\n";
			exit(1);
		}
	}
}

// Todo lo que crea una prueba se borra al terminar, pase lo que pase.
$GLOBALS['col_basura'] = ['usuarios' => [], 'clientes' => []];
register_shutdown_function(function () {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ($GLOBALS['col_basura']['usuarios'] as $u) {
		wp_delete_user($u);
	}
	foreach ($GLOBALS['col_basura']['clientes'] as $c) {
		$wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $c]);
		$wpdb->delete($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $c]);
		if (function_exists('at_en_de_crm')) {
			$t = at_en_tablas();
			foreach (at_en_de_crm($c) as $e) {
				$wpdb->delete($t['n'] ?? '', ['entregable_id' => $e->id]);
				$wpdb->delete($t['v'], ['entregable_id' => $e->id]);
				$wpdb->delete($t['e'], ['id' => $e->id]);
			}
		}
		$wpdb->delete($wpdb->prefix . 'crm_clientes', ['id' => $c]);
	}
});

function col_admin_id(): int {
	$a = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
	$id = (int) ($a[0] ?? 0);
	if ($id <= 0) {
		fwrite(STDERR, "No hay administrador en el WordPress local de prueba.\n");
		exit(2);
	}
	return $id;
}

/** Cliente de prueba en el CRM. */
function col_crear_cliente(string $nombre, string $empresa = 'Empresa Prueba'): int {
	global $wpdb;
	$wpdb->insert($wpdb->prefix . 'crm_clientes', [
		'tipo' => 'cliente', 'estado' => 'contratado', 'nombre' => $nombre, 'empresa' => $empresa,
		'email' => 'cliente-colab-' . wp_generate_password(8, false) . '@ejemplo.test', 'telefono' => '+56900000001',
		'created_at' => current_time('mysql'),
	]);
	$id = (int) $wpdb->insert_id;
	$GLOBALS['col_basura']['clientes'][] = $id;
	return $id;
}

/** Usuario de prueba con un rol y, opcionalmente, clientes asignados. */
function col_crear_usuario(string $rol, array $clientes = []): int {
	$id = wp_insert_user([
		'user_login' => 'colab_' . wp_generate_password(8, false, false), 'user_pass' => wp_generate_password(24),
		'user_email' => 'colab-' . wp_generate_password(8, false) . '@ejemplo.test', 'display_name' => 'Colaborador Prueba',
		'role' => $rol,
	]);
	if (is_wp_error($id)) {
		fwrite(STDERR, 'No se pudo crear el usuario de prueba: ' . $id->get_error_message() . "\n");
		exit(2);
	}
	$GLOBALS['col_basura']['usuarios'][] = $id;
	if ($clientes) {
		update_user_meta($id, 'at_clientes_asignados', $clientes);
	}
	return (int) $id;
}
