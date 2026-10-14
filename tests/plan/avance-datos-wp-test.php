<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-datos-wp-test.php
// Estado del proyecto, Etapa 2 — Task 2: columna avance (migración idempotente), leer y guardar sin tocar el plan firmado.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_migrar_esquema', 'at_av_columna_lista', 'at_av_leer', 'at_av_guardar', 'at_av_guardar_render');
global $wpdb;
$t = at_pt_tabla();
$hay_columna = fn() => $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$t} LIKE %s", 'avance')) === 'avance';

// Migración: desde '1' agrega la columna (o la reconoce) y deja '2'; repetirla no hace nada.
update_option('at_plan_schema', '1');
delete_transient('at_av_migrar_intento');
at_av_migrar_esquema();
ok($hay_columna() && get_option('at_plan_schema') === '2' && at_av_columna_lista(), 'migración: columna avance y at_plan_schema = 2');
at_av_migrar_esquema();
ok(get_option('at_plan_schema') === '2', 'repetir la migración no cambia nada');
at_pt_migrar_esquema();
ok(get_option('at_plan_schema') === '2', 'la migración del plan no vuelve a correr ni baja la versión');

$m = ptc_marca();
$c = ptc_cliente($m, 'prueba-avance-datos@example.com');
$plan = ptc_plan(ptc_contrato($c['tech'], $m, null, 'servicios', 'signed', 'prueba-avance-datos@example.com'));
$id = (int) $plan->id;
ptc_sembrar($id);
ok(at_av_leer(at_pt_plan($id)) === at_av_vacio(), 'plan sin avance: forma vacía');
$a = at_av_vacio();
$a['necesitamos'] = [['texto' => 'Logo en alta', 'estado' => 'abierto', 'fecha' => '2026-10-14']];
ok(at_av_guardar($id, $a) && at_av_leer(at_pt_plan($id))['necesitamos'][0]['texto'] === 'Logo en alta', 'guardar y leer el avance');
$payload_antes = (string) at_pt_plan($id)->payload;
$estado_antes = (string) at_pt_plan($id)->estado;
ok(at_av_guardar_render($id, 'render', ['estado' => 'listo', 'view_url' => 'https://x.cl/y', 'otra' => 1]), 'guardar un registro de render');
$l = at_av_leer(at_pt_plan($id));
ok($l['render']['estado'] === 'listo' && !isset($l['render']['otra']) && $l['necesitamos'][0]['texto'] === 'Logo en alta' && $l['render_previa'] === at_av_registro_vacio(), 'guardar un render toca solo su registro');
ok((string) at_pt_plan($id)->payload === $payload_antes && (string) at_pt_plan($id)->estado === $estado_antes, 'el plan firmado y su estado no cambian (D3)');
ok(!at_av_guardar_render($id, 'otra', []) && !at_av_guardar(999999999, $a), 'clave desconocida o plan inexistente: false');
$enorme = at_av_vacio();
$enorme['cambios_plan'] = array_fill(0, 3000, ['fecha' => '2026-10-14', 'titulo' => str_repeat('x', 120), 'detalle' => str_repeat('y', 600), 'bloques' => []]);
ok(!at_av_guardar($id, $enorme) && at_av_leer(at_pt_plan($id))['cambios_plan'] === [], 'un avance de más de 1,5 MB no se guarda y no pisa el anterior');
update_option('at_plan_schema', '1');
ok(!at_av_columna_lista() && !at_av_guardar($id, $a), 'sin la migración marcada no se guarda');
update_option('at_plan_schema', '2');
fin();
