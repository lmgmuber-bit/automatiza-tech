<?php
// Correr: AT_WP_LOAD=<ruta> php tests/plan/datos-contexto-wp-test.php
// Task 5: datos para el renderer y contexto para la IA (con y sin propuesta, con y sin correo).
require __DIR__ . '/wp-bootstrap.php';
exigir('at_pt_datos_render', 'at_pt_contexto');
require __DIR__ . '/datos-prueba.php';
global $wpdb;
add_filter('pre_option_at_pt_duraciones', function () { return ''; });
add_filter('pre_option_at_cc_whatsapp_at', function () { return '+56 9 1234 5678'; });

$m = pt_marca();
$cli = pt_cliente($m);
$wpdb->update($wpdb->prefix . 'crm_clientes', ['rubro' => '[PRUEBA] Panadería'], ['id' => $cli['crm']]);
$prop = pt_propuesta($m, ['transcript' => str_repeat('Hablamos del sitio. ', 400)]);
$cid = pt_contrato($cli['tech'], $prop);
$pid = at_pt_crear_plan($cid);
$f = at_pt_plan((int) $pid);

// 1) Datos para el renderer, con propuesta y cliente con correo.
$d = at_pt_datos_render($f);
ok(array_keys($d) === ['codigo', 'company_name', 'client_name', 'portal_url', 'whatsapp', 'fecha_firma', 'garantia_meses'], '1) las siete claves que espera at_pt_armar_render() (con la garantía del contrato)');
ok($d['codigo'] === $f->codigo && $d['fecha_firma'] === '2026-10-09', '1) código del plan y fecha de firma del contrato');
ok($d['company_name'] === '[PRUEBA] Empresa ' . $m && $d['client_name'] === 'Cliente Prueba', '1) con propuesta: su nombre comercial (company_name) y el cliente que firma en el contrato');
ok(strpos($d['portal_url'], 'crm_view=timeline&cid=' . $cli['crm'] . '&token=') !== false, '1) enlace al portal del cliente (at_crm_url_portal)');
ok($d['whatsapp'] === '56912345678', '1) WhatsApp de AutomatizaTech (Ajustes del cierre), solo dígitos');
ok($d['garantia_meses'] === 3, '1) contrato sin el marcador garantia_meses_servicio: 3 meses');

// 2) Sin propuesta y sin correo (Review Focus 4).
$m2 = pt_marca();
$cli2 = pt_cliente($m2, false);
$cid2 = pt_contrato($cli2['tech'], null);
$f2 = at_pt_plan((int) at_pt_crear_plan($cid2));
$d2 = at_pt_datos_render($f2);
ok($d2['portal_url'] === '', '2) cliente sin correo: sin enlace al portal (la lámina «Sigue tu proyecto» va sin enlace)');
ok($d2['company_name'] === '[PRUEBA] Sitio de una página' && $d2['client_name'] === 'Cliente Prueba', '2) sin propuesta: el nombre del proyecto del contrato (nombre_proyecto)');

// 2b) Sin propuesta ni nombre de proyecto: la razón social; sin nada, el nombre del cliente. Garantía del contrato.
$ph_base = ['representante_cliente_nombre' => 'Cliente Prueba', 'alcance' => 'Sitio de una página con formulario.'];
$f2b = at_pt_plan((int) at_pt_crear_plan(pt_contrato($cli2['tech'], null, ['ph' => $ph_base + ['razon_social_cliente' => '[PRUEBA] Razón Social SpA', 'garantia_meses_servicio' => '6']])));
$d2b = at_pt_datos_render($f2b);
ok($d2b['company_name'] === '[PRUEBA] Razón Social SpA' && $d2b['garantia_meses'] === 6, '2b) sin propuesta ni nombre de proyecto: la razón social; garantía de 6 meses del contrato');
$f2c = at_pt_plan((int) at_pt_crear_plan(pt_contrato($cli2['tech'], null, ['ph' => $ph_base + ['garantia_meses_servicio' => 'a convenir']])));
$d2c = at_pt_datos_render($f2c);
ok($d2c['company_name'] === 'Cliente Prueba' && $d2c['garantia_meses'] === 3, '2b) sin propuesta, proyecto ni razón social: el nombre del cliente (nunca vacío); una garantía ilegible vale 3');
ok(at_pt_contexto($f2b)['empresa'] === '[PRUEBA] Razón Social SpA' && at_pt_contexto($f2b)['contrato']['garantia_meses'] === 6, '2b) el contexto de la IA usa la misma empresa y la misma garantía');
$f2d = at_pt_plan((int) at_pt_crear_plan(pt_contrato($cli2['tech'], null, ['ph' => $ph_base + ['garantia_meses_servicio' => '36 meses']])));
ok(at_pt_datos_render($f2d)['garantia_meses'] === 3, '2b) una garantía fuera de 0 a 24 meses (la que valida el plan) vale 3');

// 3) Contexto para la IA.
$x = at_pt_contexto($f);
ok(array_keys($x) === ['ok', 'plan_id', 'codigo', 'estado', 'proyecto', 'empresa', 'cliente', 'fecha_firma', 'contrato', 'propuesta', 'tabla', 'slides_foto', 'plan_actual', 'comentarios', 'crm_cliente_id', 'rubro'], '3) las claves del contrato de interfaces, en orden (más crm_cliente_id para los correos y rubro para las fotos)');
ok($x['ok'] === true && $x['plan_id'] === $pid && $x['estado'] === 'generando' && $x['proyecto'] === '[PRUEBA] Sitio de una página' && $x['empresa'] === '[PRUEBA] Empresa ' . $m && $x['fecha_firma'] === '2026-10-09' && $x['crm_cliente_id'] === $cli['crm'], '3) plan, estado, proyecto, empresa (nombre comercial de la propuesta), fecha de firma y cliente del CRM');
ok($x['rubro'] === '[PRUEBA] Panadería', '3) el rubro del cliente del CRM (las fotos nuevas se describen por rubro)');
ok(array_keys($x['contrato']) === ['servicios_contratados', 'alcance', 'entregables', 'fases_siguientes', 'plazo', 'garantia_meses'] && $x['contrato']['alcance'] === 'Sitio de una página con formulario.' && strpos($x['contrato']['servicios_contratados'], 'Sitio de una página') !== false && $x['contrato']['garantia_meses'] === 3, '3) lo contratado sale del contrato, con los meses de garantía (3 si el contrato no los dice)');
ok(is_array($x['propuesta']) && $x['propuesta']['company_name'] === '[PRUEBA] Empresa ' . $m && $x['propuesta']['solution_text'] === 'Sitio de una página con formulario de contacto.' && $x['propuesta']['how_it_works'] === ['Diseño: Maqueta de la portada', 'Publicación en tu dominio'], '3) de la propuesta: empresa, solución y pasos');
ok(mb_strlen($x['propuesta']['extracto_reunion']) === 3000 && strpos($x['propuesta']['extracto_reunion'], 'Hablamos del sitio.') === 0, '3) extracto de la reunión: los primeros 3000 caracteres');
ok($x['tabla'] === at_pt_duraciones_defecto() && $x['slides_foto'] === at_pt_slides_foto(), '3) tabla de tiempos y láminas con foto');
ok($x['plan_actual'] === null && $x['comentarios'] === '', '3) sin plan todavía: plan_actual null y sin comentarios');
at_pt_guardar($pid, ['payload' => ['version' => 1, 'fases' => []], 'comentarios' => 'Acorta el diseño']);
$x = at_pt_contexto(at_pt_plan($pid));
ok($x['plan_actual'] === ['version' => 1, 'fases' => []] && $x['comentarios'] === 'Acorta el diseño', '3) con plan: plan_actual y el último pedido de cambios');

// 4) Contexto sin propuesta (Review Focus 4).
$x2 = at_pt_contexto($f2);
ok(array_key_exists('propuesta', $x2) && $x2['propuesta'] === null && $x2['cliente'] === 'Cliente Prueba' && $x2['contrato']['alcance'] !== '' && $x2['rubro'] === '', '4) contrato sin propuesta y cliente sin rubro: propuesta null, rubro vacío y lo contratado igual llega');

fin();
