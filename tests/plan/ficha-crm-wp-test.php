<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/ficha-crm-wp-test.php
// Task 9: la ficha real del CRM (wp-content/mu-plugins/crm-ai-completo.php) trae la pestaña «🗓️ Plan de trabajo»
// después de «📜 Contratos y operación», con el panel del plan adentro, solo para administradores.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!isset($GLOBALS['at_crm_ai']) || !method_exists($GLOBALS['at_crm_ai'], 'render_ficha_cliente')) {
	fwrite(STDERR, "El mu-plugin crm-ai-completo.php no está cargado en ese sitio.\n");
	exit(2);
}
$m = ptc_marca();
$c = ptc_cliente($m, "prueba-plan-{$m}@example.com");
$k = ptc_contrato($c['tech'], $m, null);
$f = ptc_plan($k);
ptc_sembrar((int) $f->id);

function ficha(int $crm): string {
	$_GET = ['page' => 'automatiza-crm-ficha', 'id' => (string) $crm];
	ob_start();
	$GLOBALS['at_crm_ai']->render_ficha_cliente();
	$h = (string) ob_get_clean();
	$_GET = [];
	return $h;
}

wp_set_current_user(pt_admin_id());
$h = ficha($c['crm']);
$boton_op = strpos($h, 'data-target="tab-operacion"');
$boton_plan = strpos($h, '<button class="ficha-tab" data-target="tab-plan">🗓️ Plan de trabajo</button>');
ok($boton_plan !== false, 'la ficha tiene el botón «🗓️ Plan de trabajo»');
ok($boton_op !== false && $boton_plan !== false && $boton_op < $boton_plan, 'va después de «📜 Contratos y operación»');
$fin_op = strpos($h, '</div><!-- /tab-operacion -->');
$panel = strpos($h, '<div class="ficha-tab-content" id="tab-plan">');
ok($fin_op !== false && $panel !== false && $fin_op < $panel, 'el panel id="tab-plan" va después del de «Contratos y operación»');
ok($panel !== false && strpos($h, 'value="Construcción del sitio"', $panel) !== false && strpos($h, '</div><!-- /tab-plan -->', $panel) !== false, 'el panel trae el plan del cliente y se cierra');
ok(substr_count($h, 'id="tab-plan"') === 1, 'hay un solo panel del plan');

// Ficha de un id que no existe (p. ej. un enlace viejo): $cliente es null y la ficha se dibuja como antes, sin la
// pestaña del plan y sin error fatal (at_pt_render_pestana() pide un arreglo).
global $wpdb;
$no_existe = (int) $wpdb->get_var("SELECT COALESCE(MAX(id), 0) + 1000 FROM {$wpdb->prefix}crm_clientes");
$h = ficha($no_existe);
ok(strpos($h, 'data-target="tab-operacion"') !== false && strpos($h, 'id="tab-plan"') === false, 'ficha de un id que no existe: se dibuja como antes, sin la pestaña y sin error fatal');

// Sin manage_options (aquí, sin sesión: id 0) la pestaña no aparece aunque el resto de la ficha se dibuje.
wp_set_current_user(0);
$h = ficha($c['crm']);
ok(strpos($h, 'data-target="tab-operacion"') !== false && strpos($h, 'data-target="tab-plan"') === false && strpos($h, 'id="tab-plan"') === false, 'sin manage_options no se ve la pestaña del plan');

wp_set_current_user(pt_admin_id());
ptc_limpiar();
fin();
