<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/panel-acciones-wp-test.php
// Task 9: acciones admin-post de la pestaña «🗓️ Plan de trabajo» (crear, guardar y recalcular, pedir cambios,
// aprobar, destrabar, reintentar). Cada una termina en redirect + exit: corre en un proceso aparte
// (accion-wp-test-run.php), que contesta las llamadas a n8n con 200 o, en modo «caido», con error de conexión.
// Cubre los casos 1 (inicio en fin de semana/feriado), 2 (plan roto no se guarda), 3 (días de Luis no se pisan),
// 4 (todas las acciones corren con un cliente sin correo y un contrato sin propuesta) y 5 (doble creación, contrato
// que no es de servicios, n8n caído) del Review Focus.
if (!defined('WP_ADMIN')) {
	define('WP_ADMIN', true);
}
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/accion-wp-helpers.php';
require __DIR__ . '/fixtures-panel.php';

if (!has_action('admin_post_at_pt_guardar') || !function_exists('at_pt_plan_desde_panel')) {
	fwrite(STDERR, "Las acciones de panel.php no están cargadas: revisa inc/plan-trabajo/cargar.php.\n");
	exit(2);
}
global $wpdb;
$admin = pt_admin_id();
wp_set_current_user($admin);
$m = ptc_marca();
// La base local es compartida: los feriados de «Ajustes del chat» vuelven a como estaban al cerrar el proceso,
// también si la prueba se cae a mitad de camino.
$horario_antes = get_option('automatiza_chat_schedule', null);
register_shutdown_function(function () use ($horario_antes) {
	if ($horario_antes === null) {
		delete_option('automatiza_chat_schedule');
	} else {
		update_option('automatiza_chat_schedule', $horario_antes);
	}
});

/** Copia del payload con un campo de una actividad cambiado. */
function con_campo(array $payload, string $nombre, string $campo, $valor): array {
	foreach ($payload['fases'] as $i => $f) {
		foreach ($f['bloques'] as $j => $b) {
			foreach ($b['actividades'] as $k => $a) {
				if ($a['nombre'] === $nombre) {
					$payload['fases'][$i]['bloques'][$j]['actividades'][$k][$campo] = $valor;
				}
			}
		}
	}
	return $payload;
}

/** Copia del payload con los días de una actividad cambiados. */
function con_dias(array $payload, string $nombre, int $dias): array {
	return con_campo($payload, $nombre, 'dias_habiles', $dias);
}

/** Lo que manda el formulario «Guardar y recalcular» (plan_json como lo arma plan-trabajo.js: ptc_plan_json). */
function post_guardar(object $fila, array $payload, string $inicio, array $extra = []): array {
	return $extra + [
		'plan_id' => (string) $fila->id, 'crm_id' => (string) $fila->crm_cliente_id, 'fecha_inicio' => $inicio,
		'plan_json' => ptc_plan_json($payload), 'proyecto' => (string) ($payload['proyecto'] ?? ''),
		'necesitamos_de_ti' => implode("\n", $payload['necesitamos_de_ti'] ?? []), 'reuniones' => 'Reunión de inicio | Revisamos el plan juntos',
		'hitos' => 'Diseño aprobado | Diseño', 'mensuales' => implode("\n", $payload['soporte']['mensuales'] ?? []),
	];
}

function accion_plan(string $accion, object $fila, array $post = [], string $n8n = 'ok', int $usuario = -1, string $nonce = ''): array {
	return pt_correr_accion($accion, $usuario >= 0 ? $usuario : $GLOBALS['admin'], $nonce !== '' ? $nonce : 'at_pt_plan_' . $fila->id,
		$post + ['plan_id' => (string) $fila->id, 'crm_id' => (string) $fila->crm_cliente_id], $n8n);
}

function msg(array $r): string {
	return (string) (pt_query($r['redirect'])['pt_msg'] ?? '');
}

function releer(object $fila): object {
	return at_pt_plan((int) $fila->id);
}

// ============ Crear (botón para contratos firmados sin plan) ============
// Cliente sin correo (sin portal) y contrato sin propuesta (Review Focus 4): el plan igual se crea y se trabaja.
$c = ptc_cliente($m, '');
$k = ptc_contrato($c['tech'], $m, null);
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $k, ['contrato_id' => (string) $k, 'crm_id' => (string) $c['crm']]);
$fila = at_pt_plan_de_contrato($k);
ok($fila !== null, 'Crear: el contrato (sin propuesta, de un cliente sin correo) quedó con plan');
if (!$fila) {
	fwrite(STDERR, trim($r['salida']) . "\n");
	fin();
}
$GLOBALS['ptc_creado']['planes'][] = (int) $fila->id;
$q = pt_query($r['redirect']);
ok(($q['page'] ?? '') === 'automatiza-crm-ficha' && ($q['id'] ?? '') === (string) $c['crm'] && ($q['pt'] ?? '') === (string) $fila->id && ($q['pt_msg'] ?? '') === 'creado' && $q['#'] === 'tab-plan', 'Crear: vuelve a la pestaña del plan con «creado»: ' . $r['redirect']);
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-borrador') !== false && (int) ($r['n8n'][0]['cuerpo']['id'] ?? 0) === (int) $fila->id && ($r['n8n'][0]['cuerpo']['codigo'] ?? '') === (string) $fila->codigo, 'Crear: le pide el borrador a n8n con el id y el código del plan');
ok($fila->estado === 'generando', 'Crear: el plan queda «generando»');
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $k, ['contrato_id' => (string) $k, 'crm_id' => (string) $c['crm']]);
$n = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . at_pt_tabla() . ' WHERE contrato_id = %d', $k));
ok($n === 1 && msg($r) === 'ya_existe' && !$r['n8n'], 'Crear dos veces (doble clic): un solo plan y no vuelve a llamar a n8n');

// n8n caído al crear (Review Focus 5): el plan igual existe y queda en error con un motivo legible.
$k2 = ptc_contrato($c['tech'], $m . 'x', null);
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $k2, ['contrato_id' => (string) $k2, 'crm_id' => (string) $c['crm']], 'caido');
$f2 = at_pt_plan_de_contrato($k2);
ok($f2 && $f2->estado === 'error' && trim((string) $f2->nota) !== '' && msg($r) === 'n8n_fallo', 'n8n caído al crear: plan en «error» con motivo (' . ($f2->nota ?? '') . ')');

// Contrato que no es de servicios, o de otro cliente: no se crea nada.
$ks = ptc_contrato($c['tech'], $m . 's', null, 'soporte');
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $ks, ['contrato_id' => (string) $ks, 'crm_id' => (string) $c['crm']]);
ok(at_pt_plan_de_contrato($ks) === null && msg($r) === 'no_se_pudo' && !$r['n8n'], 'contrato de soporte: no crea plan ni llama a n8n');
$otro = ptc_cliente($m . 'o', "prueba-plan-{$m}o@example.com");
$ko = ptc_contrato($otro['tech'], $m . 'o', null);
$r = pt_correr_accion('at_pt_crear', $admin, 'at_pt_crear_' . $ko, ['contrato_id' => (string) $ko, 'crm_id' => (string) $c['crm']]);
ok(at_pt_plan_de_contrato($ko) === null && msg($r) === 'no_se_pudo', 'contrato de otro cliente: no crea plan');

// ============ Reintentar desde error sin plan (Review Focus 5) ============
$r = accion_plan('at_pt_reintentar', $f2, [], 'caido');
ok(releer($f2)->estado === 'error' && msg($r) === 'n8n_fallo', 'Reintentar con n8n todavía caído: sigue en «error»');
$r = accion_plan('at_pt_reintentar', $f2);
ok(releer($f2)->estado === 'generando' && msg($r) === 'reintentando' && count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-borrador') !== false, 'Reintentar con n8n arriba: vuelve a «generando» y pide el borrador');

// ============ Guardar y recalcular ============
ptc_sembrar((int) $fila->id);
$fila = releer($fila);
$pl = at_pt_payload($fila);
ok(ptc_actividad($pl, 'Construcción del sitio')['origen'] === 'tabla' && ptc_actividad($pl, 'Capacitación')['origen'] === 'ia', 'punto de partida: Construcción viene de la tabla y Capacitación de la IA');
// Review Focus 1: inicio un sábado (10-oct-2026) y el lunes 12 feriado → el plan parte el martes 13. El feriado se
// agrega a los que ya había (se devuelven al cerrar el proceso).
$horario = is_array($horario_antes) ? $horario_antes : [];
$horario['holidays'] = trim((string) ($horario['holidays'] ?? '')) . "\n2026-10-12";
update_option('automatiza_chat_schedule', $horario);
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_dias($pl, 'Construcción del sitio', 12), '2026-10-10'));
$fila = releer($fila);
$g = at_pt_payload($fila);
$a = ptc_actividad($g, 'Construcción del sitio');
ok(msg($r) === 'guardado' && $fila->estado === 'borrador', 'Guardar: vuelve con «guardado» y el plan sigue en borrador: ' . $r['redirect']);
ok((int) ($a['dias_habiles'] ?? 0) === 12 && ($a['origen'] ?? '') === 'luis', 'Guardar: la actividad editada queda con 12 días y origen «luis»');
ok(ptc_actividad($g, 'Maqueta de la portada')['origen'] === 'tabla' && ptc_actividad($g, 'Capacitación')['origen'] === 'ia', 'Guardar: las no editadas conservan su origen');
ok((string) $fila->fecha_inicio === '2026-10-13' && ($g['fases'][0]['bloques'][0]['actividades'][0]['desde'] ?? '') === '2026-10-13', 'inicio en sábado con lunes feriado: el plan parte el martes 13-oct');
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-render') !== false && ($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'draft' && ($r['n8n'][0]['cuerpo']['aviso'] ?? null) === false, 'Guardar: pide la vista previa (render draft, sin aviso)');
// Review Focus 3 (lado del panel): volver a guardar sin cambios no reaplica la tabla ni pierde la marca de Luis.
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13'));
$a = ptc_actividad(at_pt_payload(releer($fila)), 'Construcción del sitio');
ok(msg($r) === 'guardado' && (int) ($a['dias_habiles'] ?? 0) === 12 && ($a['origen'] ?? '') === 'luis', 'volver a guardar: la actividad de Luis sigue 12/luis');

// Review Focus 2 (lado del panel): un plan roto nunca se guarda.
$antes = (string) releer($fila)->payload;
foreach ([0, 200] as $dias_malos) {
	$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_dias($g, 'Capacitación', $dias_malos), '2026-10-13'));
	ok(msg($r) === 'invalido' && (string) releer($fila)->payload === $antes && !$r['n8n'], "días {$dias_malos}: no se guarda, no llama a n8n y avisa «invalido»");
}
ok(at_pt_tomar_detalles((int) $fila->id) !== [], 'los errores de validación quedan para mostrarlos en la pestaña');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13', ['plan_json' => 'esto no es JSON']));
ok(msg($r) === 'json_invalido' && (string) releer($fila)->payload === $antes, 'plan_json roto: no se guarda nada');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13', ['plan_json' => wp_json_encode(['fases' => []])]));
ok(msg($r) === 'invalido' && (string) releer($fila)->payload === $antes, 'sin ninguna actividad: no se guarda');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g, '2026-10-13', ['plan_json' => str_replace('"clave":"implementacion"', '"clave":"otra_fase"', ptc_plan_json($g))]));
ok(msg($r) === 'invalido' && (string) releer($fila)->payload === $antes && !$r['n8n'], 'una fase desconocida: no se guarda');

// Textos de las láminas y detalle de una actividad (todo editable sin IA, decisión 11).
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_campo($g, 'Maqueta de la portada', 'detalle', '[PRUEBA] Detalle editado'), '2026-10-13', [
	'proyecto' => '[PRUEBA] Proyecto editado', 'necesitamos_de_ti' => "Logo\n\nTextos de la empresa\n", 'reuniones' => "Inicio | Revisamos todo\nEntrega", 'garantia_meses' => '6',
	'mensuales' => "Mantención del sitio\n\nCampañas en Google Ads\n",
]));
$g2 = at_pt_payload(releer($fila));
ok(msg($r) === 'guardado' && ($g2['proyecto'] ?? '') === '[PRUEBA] Proyecto editado' && ($g2['necesitamos_de_ti'] ?? []) === ['Logo', 'Textos de la empresa'], 'Guardar: nombre del proyecto y «qué necesitamos» (sin líneas vacías)');
ok(($g2['reuniones'][0]['nombre'] ?? '') === 'Inicio' && ($g2['reuniones'][0]['detalle'] ?? '') === 'Revisamos todo' && ($g2['reuniones'][1]['nombre'] ?? '') === 'Entrega', 'Guardar: reuniones «Nombre | detalle»');
ok(($g2['soporte']['mensuales'] ?? null) === ['Mantención del sitio', 'Campañas en Google Ads'] && isset($g2['image_briefs']) && count($g2['image_briefs']) === 10, 'Guardar: servicios mensuales (sin líneas vacías), y los briefs de foto intactos');
ok(isset($g['soporte']['garantia_meses']) && (int) $g['soporte']['garantia_meses'] !== 6 && ($g2['soporte']['garantia_meses'] ?? null) === $g['soporte']['garantia_meses'], 'Guardar: un POST con garantia_meses=6 no cambia la garantía (viene del contrato, D8)');
$maqueta = ptc_actividad($g2, 'Maqueta de la portada');
ok(($maqueta['detalle'] ?? '') === '[PRUEBA] Detalle editado' && ($maqueta['origen'] ?? '') === 'tabla', 'Guardar: el detalle editado queda guardado y no cambia el origen de sus días');

// n8n caído al pedir la vista previa: lo editado se guarda igual, el plan no pasa a error y queda el motivo en la nota.
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, con_dias($g2, 'Capacitación', 2), '2026-10-13'), 'caido');
$fila = releer($fila);
ok(msg($r) === 'guardado_sin_vista' && $fila->estado === 'borrador' && (int) ptc_actividad(at_pt_payload($fila), 'Capacitación')['dias_habiles'] === 2 && strpos((string) $fila->nota, 'No se pudo pedir la vista previa') !== false, 'n8n caído al guardar: se guarda, sigue en borrador, avisa «guardado_sin_vista» y anota el motivo');
$g3 = at_pt_payload($fila);
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g3, '2026-10-13'));
ok(msg($r) === 'guardado' && trim((string) releer($fila)->nota) === '', 'guardar con n8n arriba borra la nota de la vista previa que falló');
// Guardar desde «listo»: vuelve a borrador (la versión final hay que aprobarla de nuevo).
ptc_estado((int) $fila->id, 'listo');
$r = accion_plan('at_pt_guardar', releer($fila), post_guardar(releer($fila), $g3, '2026-10-13'));
$fila = releer($fila);
ok(msg($r) === 'guardado' && $fila->estado === 'borrador', 'Guardar desde «listo»: vuelve a borrador');

// ============ Pedir cambios ============
$r = accion_plan('at_pt_cambios', $fila, ['comentarios' => '   ']);
ok(msg($r) === 'sin_comentarios' && releer($fila)->estado === 'borrador' && !$r['n8n'], 'Pedir cambios sin comentarios: no hace nada');
$r = accion_plan('at_pt_cambios', $fila, ['comentarios' => 'Separa la capacitación en dos sesiones.']);
$fila = releer($fila);
ok(msg($r) === 'cambios_pedidos' && $fila->estado === 'cambios' && $fila->comentarios === 'Separa la capacitación en dos sesiones.', 'Pedir cambios: guarda los comentarios y pasa a «cambios»');
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-cambios') !== false && (int) ($r['n8n'][0]['cuerpo']['id'] ?? 0) === (int) $fila->id && ($r['n8n'][0]['cuerpo']['codigo'] ?? '') === (string) $fila->codigo, 'Pedir cambios: llama al flujo 2 Cambios con el id y el código');
$r = accion_plan('at_pt_guardar', $fila, post_guardar($fila, $g3, '2026-10-13'));
ok(msg($r) === 'no_editable', 'mientras la IA aplica cambios, Guardar no se puede');
$r = accion_plan('at_pt_aprobar', $fila);
ok(msg($r) === 'transicion' && releer($fila)->estado === 'cambios', 'mientras la IA aplica cambios, Aprobar no se puede');

// ============ Destrabar y volver al borrador ============
$r = accion_plan('at_pt_destrabar', $fila);
$fila = releer($fila);
ok(msg($r) === 'destrabado' && $fila->estado === 'error' && $fila->nota === 'Destrabado por Luis', 'Destrabar: «cambios» pasa a «error» con «Destrabado por Luis»');
$r = accion_plan('at_pt_reintentar', $fila);
ok(msg($r) === 'vuelto_borrador' && releer($fila)->estado === 'borrador' && !$r['n8n'], 'Reintentar con plan: vuelve a borrador sin llamar a n8n');
$r = accion_plan('at_pt_destrabar', $fila);
ok(msg($r) === 'transicion' && releer($fila)->estado === 'borrador', 'Destrabar un borrador: no corresponde');

// n8n caído al pedir cambios.
$r = accion_plan('at_pt_cambios', $fila, ['comentarios' => 'Otro cambio.'], 'caido');
$fila = releer($fila);
ok(msg($r) === 'n8n_fallo' && $fila->estado === 'error' && strpos((string) $fila->nota, 'No se pudo pedir los cambios') !== false, 'Pedir cambios con n8n caído: «error» con el motivo (' . $fila->nota . ')');
accion_plan('at_pt_reintentar', $fila);

// ============ Aprobar ============
$r = accion_plan('at_pt_aprobar', $fila);
$fila = releer($fila);
ok(msg($r) === 'aprobando' && $fila->estado === 'aprobando', 'Aprobar: pasa a «aprobando»');
ok(count($r['n8n']) === 1 && strpos($r['n8n'][0]['url'], 'plan-v1-render') !== false && ($r['n8n'][0]['cuerpo']['modo'] ?? '') === 'final' && ($r['n8n'][0]['cuerpo']['aviso'] ?? null) === true, 'Aprobar: pide el render final con aviso');
$r = accion_plan('at_pt_destrabar', $fila);
ok(releer($fila)->estado === 'error', 'Destrabar desde «aprobando»: queda en «error»');
accion_plan('at_pt_reintentar', $fila);
$r = accion_plan('at_pt_aprobar', $fila, [], 'caido');
$fila = releer($fila);
ok(msg($r) === 'n8n_fallo' && $fila->estado === 'error' && trim((string) $fila->nota) !== '', 'Aprobar con n8n caído: «error» con el motivo');
$r = accion_plan('at_pt_aprobar', releer($f2));
ok(msg($r) === 'transicion' && releer($f2)->estado === 'generando', 'Aprobar un plan que todavía se genera: no corresponde');

// ============ Seguridad y vuelta a la ficha ============
accion_plan('at_pt_reintentar', $fila);
$r = accion_plan('at_pt_aprobar', $fila, [], 'ok', 0);
ok($r['redirect'] === '' && strpos($r['salida'], 'Sin permiso') !== false && releer($fila)->estado === 'borrador', 'sin manage_options: se corta y no cambia nada');
$r = accion_plan('at_pt_aprobar', $fila, [], 'ok', $admin, 'at_pt_plan_' . $f2->id);
ok($r['redirect'] === '' && releer($fila)->estado === 'borrador' && !$r['n8n'], 'con el nonce de otro plan: no cambia nada');
$r = pt_correr_accion('at_pt_aprobar', $admin, 'at_pt_plan_999999999', ['plan_id' => '999999999', 'crm_id' => (string) $c['crm']]);
ok(msg($r) === 'sin_plan', 'un plan que no existe: vuelve con «sin_plan»');
// El plan quedó con un crm_cliente_id viejo: la vuelta usa el cliente enlazado hoy a su ficha operativa.
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => (int) $c['crm'] + 1000000], ['id' => (int) $fila->id]);
$r = accion_plan('at_pt_destrabar', releer($fila));
ok((pt_query($r['redirect'])['id'] ?? '') === (string) $c['crm'], 'plan con el cliente del CRM guardado viejo: vuelve a la ficha del cliente enlazado hoy');
$wpdb->update(at_pt_tabla(), ['crm_cliente_id' => (int) $c['crm']], ['id' => (int) $fila->id]);

ptc_limpiar();
fin();
