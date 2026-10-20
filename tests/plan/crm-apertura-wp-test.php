<?php
// AT-CRM-001, Ronda 3: apertura de editores y visibilidad del estado de presentación según el estado real.
// Correr: AT_WP_LOAD=<sitio-local>/wp-load.php php tests/plan/crm-apertura-wp-test.php
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_pt_render_plan', 'at_av_render_seccion', 'at_av_guardar');
at_av_migrar_esquema();
wp_set_current_user(pt_admin_id());
$marca = ptc_marca();
$cliente = ptc_cliente($marca, 'prueba-crm-apertura@example.com');
$fila = ptc_plan(ptc_contrato($cliente['tech'], $marca, null));
$plan_id = (int) $fila->id;
ptc_sembrar($plan_id);

$dibujar = function () use ($plan_id, $cliente): string {
	ob_start();
	at_pt_render_plan(at_pt_plan($plan_id), $cliente['crm']);
	return (string) ob_get_clean();
};

// El editor se muestra de inmediato cuando corresponde trabajar en el borrador o la versión editable.
foreach (['borrador' => true, 'listo' => true, 'error' => true, 'enviado' => false, 'generando' => false, 'cambios' => false, 'aprobando' => false] as $estado => $abierto) {
	ptc_estado($plan_id, $estado);
	$html = $dibujar();
	preg_match('/<details class="at-pt-editor"[^>]*>/', $html, $editor);
	ok(!empty($editor) && (bool) preg_match('/\sopen(?:\s|>)/', $editor[0]) === $abierto, 'CRM-001 R3) editor ' . ($abierto ? 'abierto' : 'plegado') . ' para plan ' . $estado);
	preg_match('/<details class="at-pt-pedir-cambios"[^>]*>/', $html, $cambios);
	ok($abierto ? (!empty($cambios) && (bool) preg_match('/\sopen(?:\s|>)/', $cambios[0])) : empty($cambios), 'CRM-001 R3) pedir cambios ' . ($abierto ? 'abierto' : 'ausente como antes') . ' para plan ' . $estado);
}

// El estado permanece a la vista en el summary; generación y errores abren además su detalle.
ptc_estado($plan_id, 'enviado');
foreach (['' => ['Todavía no se genera', false], 'pendiente' => ['Generándose', true], 'listo' => ['Lista', false], 'error' => ['Con error', true]] as $estado => [$etiqueta, $abierto]) {
	$avance = at_av_leer(at_pt_plan($plan_id));
	$avance['render'] = array_merge(at_av_registro_vacio(), ['estado' => $estado]);
	at_av_guardar($plan_id, $avance);
	$html = $dibujar();
	preg_match('/<details class="at-pt-seccion" id="at-av-presentacion-' . $plan_id . '"[^>]*>/', $html, $presentacion);
	ok(!empty($presentacion) && (bool) preg_match('/\sopen(?:\s|>)/', $presentacion[0]) === $abierto, 'CRM-001 R3) presentación ' . ($abierto ? 'abierta' : 'plegada') . ' para estado ' . ($estado ?: 'sin generar'));
	ok(str_contains($html, '<summary><h4>Presentación «Estado del proyecto» · ' . esc_html($etiqueta) . '</h4></summary>'), 'CRM-001 R3) estado «' . $etiqueta . '» visible en el summary');
}

fin();
