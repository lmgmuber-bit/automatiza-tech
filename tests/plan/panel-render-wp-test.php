<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-render-wp-test.php
// Task 9: lo que dibuja la pestaña «🗓️ Plan de trabajo» (at_pt_render_pestana) y sus assets.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!function_exists('at_pt_render_pestana') || !function_exists('at_pt_url_ficha')) {
	fwrite(STDERR, "panel.php no está cargado: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
global $wpdb;
wp_set_current_user(pt_admin_id());
$m = ptc_marca();

function pestana(int $crm, array $get = []): string {
	$_GET = $get;
	ob_start();
	at_pt_render_pestana(ptc_cliente_fila($crm));
	$h = (string) ob_get_clean();
	$_GET = [];
	return $h;
}

// ---------- URL de vuelta ----------
$u = at_pt_url_ficha(7, 12, 'guardado');
$q = pt_query($u);
ok(($q['page'] ?? '') === 'automatiza-crm-ficha' && ($q['id'] ?? '') === '7' && ($q['pt'] ?? '') === '12' && ($q['pt_msg'] ?? '') === 'guardado' && $q['#'] === 'tab-plan', 'at_pt_url_ficha arma la ficha con pt, pt_msg y #tab-plan: ' . $u);
$q = pt_query(at_pt_url_ficha(7));
ok(!isset($q['pt']) && !isset($q['pt_msg']) && $q['#'] === 'tab-plan', 'sin plan ni mensaje, la URL no los lleva');

// ---------- Cliente sin contratos ----------
$c0 = ptc_cliente($m . 'a', "prueba-plan-{$m}a@example.com");
$h = pestana($c0['crm']);
ok(strpos($h, 'no tiene contratos de servicios firmados') !== false && strpos($h, 'at_pt_crear') === false, 'sin contratos: lo dice y no ofrece crear');

// ---------- Contrato firmado sin plan (p. ej. firmado antes de este cambio) ----------
$c1 = ptc_cliente($m . 'b', "prueba-plan-{$m}b@example.com");
$k1 = ptc_contrato($c1['tech'], $m . 'b', null);
$ks = ptc_contrato($c1['tech'], $m . 'b', null, 'soporte');
$h = pestana($c1['crm']);
ok(strpos($h, 'name="action" value="at_pt_crear"') !== false && strpos($h, 'name="contrato_id" value="' . $k1 . '"') !== false && strpos($h, 'Crear plan de trabajo') !== false, 'contrato de servicios sin plan: botón «Crear plan de trabajo» con su contrato');
ok(strpos($h, 'name="contrato_id" value="' . $ks . '"') === false, 'un contrato de soporte no ofrece plan');

// ---------- Plan en borrador, con propuesta y con correo ----------
$c2 = ptc_cliente($m . 'c', "prueba-plan-{$m}c@example.com");
$p2 = ptc_propuesta($m . 'c');
$k2 = ptc_contrato($c2['tech'], $m . 'c', $p2);
$f2 = ptc_plan($k2);
ptc_sembrar((int) $f2->id);
$h = pestana($c2['crm'], ['pt' => (string) $f2->id]);
ok(strpos($h, 'at-pt-estado--borrador') !== false && strpos($h, '>Borrador<') !== false, 'estado «Borrador» con su color');
ok(strpos($h, 'value="Construcción del sitio"') !== false && strpos($h, 'value="Reunión de inicio"') !== false, 'la tabla trae las actividades, incluido el arranque');
ok(strpos($h, 'at-pt-origen--tabla') !== false && strpos($h, 'at-pt-origen--ia') !== false && strpos($h, '>Tabla de tiempos<') !== false && strpos($h, '>IA · revisar<') !== false, 'se ve el origen de cada duración con las etiquetas de at_pt_origenes() («Tabla de tiempos», «IA · revisar»)');
ok(strpos($h, 'class="at-pt-a-detalle"') !== false && strpos($h, 'data-detalle=') === false, 'el detalle de cada actividad (sale en la lámina de la fase) se edita en su propio campo');
ok(strpos($h, 'name="mensuales"') !== false, 'los servicios mensuales (lámina de reuniones y soporte) se pueden editar');
$pl9 = at_pt_payload(at_pt_plan((int) $f2->id));
$pl9['soporte']['garantia_meses'] = 9;
at_pt_guardar((int) $f2->id, ['payload' => $pl9]);
$h9 = pestana($c2['crm'], ['pt' => (string) $f2->id]);
ok(strpos($h9, 'name="garantia_meses"') === false && strpos($h9, '<input type="number" class="at-pt-garantia" value="3" readonly ') !== false && strpos($h9, 'Viene del contrato') !== false, 'la garantía es de solo lectura, sin name (no se envía), con la leyenda «Viene del contrato» y el valor del contrato (3, sin marcador) aunque el plan diga 9 (D8)');
ok(strpos($h, 'class="at-pt-plan-json"') !== false && strpos($h, 'value="at_pt_guardar"') !== false && strpos($h, 'Guardar y recalcular') !== false, '«Guardar y recalcular» con el campo oculto plan_json');
ok(strpos($h, 'value="at_pt_cambios"') !== false && strpos($h, 'name="comentarios"') !== false, '«Pedir cambios» con sus comentarios');
$costo = at_pt_costo_fotos(at_pt_payload(at_pt_plan((int) $f2->id))['image_briefs'] ?? [], true);
ok((int) $costo['fotos'] === 8, 'con propuesta cuentan 8 fotos nuevas (portada y cierre se reutilizan): ' . $costo['fotos']);
$etiqueta = '(8 fotos + revisión ≈ US$' . number_format((float) $costo['usd_lista'], 4, ',', '.') . ')';
ok(strpos($h, 'Aprobar y generar versión final ' . $etiqueta) !== false, 'el botón Aprobar muestra ' . $etiqueta);
ok(preg_match('/<form[^>]*class="at-pt-form-aprobar at-pt-requiere-guardado"[^>]*onsubmit="return confirm\(&quot;Se generarán 8 fotos nuevas/u', $h) === 1, 'Aprobar pide confirm() con la cantidad de fotos y el costo');
ok(strpos($h, 'page=automatiza-followup') !== false && strpos($h, 'pt_plan=' . $f2->id) !== false && strpos($h, 'Agendar llamada de seguimiento') !== false, '«Agendar llamada de seguimiento» abre el formulario de seguimiento con pt_plan');
ok(strpos($h, 'Destrabar') === false && strpos($h, 'no tiene correo') === false && strpos($h, 'no tiene propuesta') === false, 'en borrador, con correo y con propuesta: sin Destrabar ni avisos de falta');
ok(strpos($h, '<fieldset>') !== false, 'en borrador la tabla se puede editar');

// ---------- Sin propuesta y sin correo (Review Focus 4) ----------
$c3 = ptc_cliente($m . 'd', '');
$k3 = ptc_contrato($c3['tech'], $m . 'd', null);
$f3 = ptc_plan($k3);
ptc_sembrar((int) $f3->id);
$h = pestana($c3['crm']);
ok(strpos($h, '(10 fotos + revisión ≈ US$0,0580)') !== false, 'sin propuesta: portada y cierre también son fotos nuevas (10 fotos + revisión ≈ US$0,0580)');
ok(strpos($h, 'saldrá sin enlace al portal') !== false, 'sin correo: avisa que «Sigue tu proyecto» sale sin enlace, y la pestaña se dibuja igual');
ok(strpos($h, 'no tiene propuesta') !== false, 'sin propuesta: lo avisa');

// ---------- Estados ocupados y de error ----------
ptc_estado((int) $f3->id, 'aprobando');
$h = pestana($c3['crm']);
ok(strpos($h, 'value="at_pt_destrabar"') !== false && strpos($h, 'value="at_pt_aprobar"') === false && strpos($h, '<fieldset disabled>') !== false, 'aprobando: ofrece Destrabar, no Aprobar, y la tabla queda de solo lectura');
ptc_estado((int) $f3->id, 'error', 'n8n no respondió (prueba)');
$h = pestana($c3['crm']);
ok(strpos($h, 'Motivo: n8n no respondió (prueba)') !== false && strpos($h, 'Volver al borrador') !== false && strpos($h, 'value="at_pt_reintentar"') !== false, 'error con plan: muestra el motivo y «Volver al borrador»');
$k4 = ptc_contrato($c3['tech'], $m . 'e', null);
$f4 = ptc_plan($k4);
ptc_estado((int) $f4->id, 'error', 'n8n caído al firmar (prueba)');
$h = pestana($c3['crm'], ['pt' => (string) $f4->id]);
ok(strpos($h, 'Reintentar borrador') !== false && strpos($h, 'Motivo: n8n caído al firmar (prueba)') !== false, 'error sin plan: «Reintentar borrador» con el motivo (Review Focus 5)');

// ---------- Varios planes: selector ----------
ok(strpos($h, 'class="at-pt-selector"') !== false && strpos($h, 'pt=' . $f4->id . '#tab-plan" aria-current="page"') !== false && strpos($h, 'pt=' . $f3->id . '#tab-plan"') !== false && strpos($h, 'pt=' . $f3->id . '#tab-plan" aria-current') === false, 'dos planes: el selector lleva a los dos y marca el que se está viendo');

// ---------- Escapado de lo que viene de la IA ----------
$pl = at_pt_payload(at_pt_plan((int) $f2->id));
$pl['fases'][0]['bloques'][0]['actividades'][0]['nombre'] = '<script>alert(1)</script> [PRUEBA] "comillas" & más';
at_pt_guardar((int) $f2->id, ['payload' => $pl]);
$h = pestana($c2['crm']);
ok(strpos($h, '<script>alert(1)') === false && strpos($h, '&lt;script&gt;alert(1)&lt;/script&gt; [PRUEBA] &quot;comillas&quot; &amp; más') !== false, 'los textos del plan salen escapados');

// ---------- Aviso de la última acción (pt_msg) ----------
$h = pestana($c2['crm'], ['pt' => (string) $f2->id, 'pt_msg' => 'guardado']);
ok(strpos($h, 'Guardado y recalculado.') !== false, 'pt_msg=guardado muestra su aviso');
at_pt_guardar_detalles((int) $f2->id, ['Días fuera de rango en «Construcción del sitio»']);
$h = pestana($c2['crm'], ['pt' => (string) $f2->id, 'pt_msg' => 'invalido']);
ok(strpos($h, 'No se guardó: el plan tiene errores.') !== false && strpos($h, '• Días fuera de rango en «Construcción del sitio»') !== false, 'pt_msg=invalido muestra los errores guardados una sola vez');
$h = pestana($c2['crm'], ['pt' => (string) $f2->id, 'pt_msg' => 'invalido']);
ok(strpos($h, '• Días fuera de rango') === false, 'los errores no se repiten al recargar');
$h = pestana($c2['crm'], ['pt_msg' => '<script>']);
ok(strpos($h, 'at-pt-aviso') === false, 'un pt_msg desconocido no dibuja ningún aviso');

// ---------- Assets: solo en la ficha del CRM ----------
$_GET = ['page' => 'automatiza-proposals'];
at_pt_encolar_assets();
ok(!wp_style_is('at-plan-trabajo', 'enqueued') && !wp_script_is('at-plan-trabajo', 'enqueued'), 'en otra página no se encola nada');
$_GET = ['page' => 'automatiza-crm-ficha', 'id' => (string) $c2['crm']];
at_pt_encolar_assets();
ok(wp_style_is('at-plan-trabajo', 'enqueued') && wp_script_is('at-plan-trabajo', 'enqueued'), 'en la ficha del cliente se encolan plan-trabajo.css y plan-trabajo.js');
$_GET = [];

ptc_limpiar();
fin();
