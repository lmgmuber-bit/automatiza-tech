<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-datos-wp-test.php
// Estado del proyecto, Etapa 2 — Task 2: columna avance (migración idempotente), leer y guardar sin tocar el plan firmado.
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures-panel.php';
exigir('at_av_migrar_esquema', 'at_av_columna_lista', 'at_av_leer', 'at_av_guardar', 'at_av_guardar_render', 'at_av_guardar_si_igual');
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
// Etapa 3: guardado condicional (reclamar un envío sin que dos pedidos casi juntos manden dos correos).
$f_si = at_pt_plan($id);
$antes_si = (string) $f_si->avance;
$a_si = at_av_leer($f_si);
$a_si['necesitamos'] = [['texto' => 'Uno', 'estado' => 'abierto', 'fecha' => '2026-10-15']];
ok(at_av_guardar_si_igual($id, $antes_si, $a_si) === true, 'guarda si nadie cambió el avance');
ok(at_av_guardar_si_igual($id, $antes_si, at_av_vacio()) === false && count(at_av_leer(at_pt_plan($id))['necesitamos']) === 1, 'no pisa si el avance cambió entretanto');
// Fix round 1: la respuesta del renderer no pisa lo que otro proceso escribió entre la lectura y la escritura
// (un envío reclamado o anotado en cierres[*].correo). Se simula cambiando la fila justo antes del UPDATE.
$concurrente = true;
add_filter('query', function ($q) use (&$concurrente, $wpdb, $t, $id) {
	if ($concurrente && strpos($q, 'UPDATE') === 0 && strpos($q, 'SET avance') !== false) {
		$concurrente = false; // la llamada anidada de abajo ya no vuelve a entrar
		$x = at_av_leer(at_pt_plan($id));
		$x['cierres'] = [['id' => 'cx0001', 'titulo' => 'Prueba', 'estado' => 'publicado', 'correo' => ['enviado_at' => null, 'prueba_at' => null, 'mensaje' => '', 'enviando' => '2026-10-15 09:00:00', 'a' => '']]];
		$wpdb->query($wpdb->prepare("UPDATE {$t} SET avance = %s WHERE id = %d", wp_json_encode(at_av_normalizar($x), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id));
	}
	return $q;
});
ok(at_av_guardar_render($id, 'render', ['estado' => 'pendiente']) && !$concurrente, 'guardar un render con un cambio concurrente: se reintenta y guarda');
$l = at_av_leer(at_pt_plan($id));
ok($l['render']['estado'] === 'pendiente' && count($l['cierres']) === 1 && $l['cierres'][0]['correo']['enviando'] === '2026-10-15 09:00:00', 'guardar un render no borra el reclamo que otro proceso escribió entretanto');
ok(at_av_guardar_render($id, 'render', ['estado' => 'pendiente']), 'guardar el mismo render otra vez es un éxito, no un fallo');
fin();
