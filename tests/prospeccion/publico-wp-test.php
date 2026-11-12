<?php
// Uso: AT_WP_LOAD=<wp-load.php> php tests/prospeccion/publico-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
at_pro_migrar_esquema();
$t = at_pro_tabla();
$leads = $wpdb->prefix . 'automatiza_leads';
$wpdb->query("DELETE FROM {$t}");
$wpdb->query("DELETE FROM {$leads} WHERE email LIKE '%@prueba.test'");
$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'at_demo_request' AND post_title LIKE '%@prueba.test'");

// Doble de prueba del aviso (Task 5 engancha at_pro_al_llenar a esta acción): cuenta las llamadas.
$llamadas = [];
add_action('at_pro_llenado', function ($p, $persona) use (&$llamadas) { $llamadas[] = [$p['id'], $persona]; }, 5, 2);
// La acción real (Task 5) no debe mandar nada en esta prueba.
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', fn() => ['response' => ['code' => 200], 'body' => '{}', 'headers' => [], 'cookies' => [], 'filename' => null]);

$nuevo_lead = function (string $email, string $minutos_atras = '0') use ($wpdb, $leads) {
	$cuando = (new DateTime('now', wp_timezone()))->modify("-{$minutos_atras} minutes")->format('Y-m-d H:i:s');
	$wpdb->insert($leads, ['created_at' => $cuando, 'name' => 'Persona Prueba', 'email' => $email, 'phone' => '+56955509999', 'source' => 'web', 'status' => 'active', 'token' => wp_generate_password(12, false)]);
};

$a = at_pro_crear(['negocio' => 'Café Sur', 'web' => 'cafesur.test', 'correo' => 'hola@cafesur.test']);
$ta = at_pro_obtener($a)['token'];

// ?r= deja cookie y redirige sin r
$r = at_pro_procesar_ref(['r' => $ta, 'utm_source' => 'x']);
ok($r['cookie'] === $ta && $r['redirect'] !== '' && strpos($r['redirect'], 'r=') === false && strpos($r['redirect'], 'utm_source=x') !== false, '?r= válido: cookie y redirección sin r');
$r = at_pro_procesar_ref(['r' => 'zzzzzzzzzzzzzzzz']);
ok($r['cookie'] === '' && $r['redirect'] === '', 'token inexistente: sin cookie');
$r = at_pro_procesar_ref(['r' => '<script>']);
ok($r['cookie'] === '', 'token con formato inválido: sin cookie');
$r = at_pro_procesar_ref([]);
ok($r['cookie'] === '' && $r['redirect'] === '', 'sin r no hace nada');

// marcar: caso feliz
$nuevo_lead('visita@prueba.test', '5');
ok(at_pro_marcar_formulario($ta, 'visita@prueba.test', 'agenda') === true, 'token válido + lead de hace 5 min -> marca');
$p = at_pro_obtener($a);
ok($p['estado'] === 'formulario' && $p['formulario_at'] !== null, 'estado formulario y formulario_at fijado');
ok(count($llamadas) === 1 && $llamadas[0][0] == $a && $llamadas[0][1]['email'] === 'visita@prueba.test' && $llamadas[0][1]['nombre'] === 'Persona Prueba' && $llamadas[0][1]['telefono'] === '+56955509999', 'el aviso se dispara una vez con los datos del lead');
ok(at_pro_marcar_formulario($ta, 'visita@prueba.test', 'agenda') === false && count($llamadas) === 1, 'reenviado: no marca ni avisa dos veces');

// sin lead / lead viejo
$b = at_pro_crear(['negocio' => 'Taller', 'web' => 'taller.test']);
$tb = at_pro_obtener($b)['token'];
ok(at_pro_marcar_formulario($tb, 'nadie@prueba.test', 'agenda') === false && at_pro_obtener($b)['estado'] === 'por_contactar', 'sin lead con ese correo: no marca');
$nuevo_lead('viejo@prueba.test', '120');
ok(at_pro_marcar_formulario($tb, 'viejo@prueba.test', 'agenda') === false && at_pro_obtener($b)['estado'] === 'por_contactar', 'lead de hace 2 horas: no marca');
ok(at_pro_marcar_formulario($tb, 'no-es-correo', 'agenda') === false, 'correo inválido: no marca');
ok(at_pro_marcar_formulario('zzzzzzzzzzzzzzzz', 'viejo@prueba.test', 'agenda') === false, 'token inexistente: no marca');
ok(at_pro_marcar_formulario('', 'viejo@prueba.test', 'agenda') === false, 'token vacío: no marca');

// descartado y baja
$nuevo_lead('otro@prueba.test', '1');
at_pro_cambiar_estado($b, 'descartado');
ok(at_pro_marcar_formulario($tb, 'otro@prueba.test', 'agenda') === false && at_pro_obtener($b)['estado'] === 'descartado', 'prospecto descartado: no marca');
$c = at_pro_crear(['negocio' => 'Se fue', 'web' => 'sefue.test']);
$tc = at_pro_obtener($c)['token'];
at_pro_dar_baja($tc);
ok(at_pro_marcar_formulario($tc, 'otro@prueba.test', 'agenda') === false && at_pro_obtener($c)['estado'] === 'baja', 'prospecto en baja: no marca');
$r = at_pro_procesar_ref(['r' => $tc]);
ok($r['cookie'] === '', 'token de baja: ?r= no deja cookie');

// Revisión foco 3: token válido sin haber agendado
$d = at_pro_crear(['negocio' => 'Sin agendar', 'web' => 'sinagendar.test']);
ok(at_pro_marcar_formulario(at_pro_obtener($d)['token'], 'cualquiera@prueba.test', 'agenda') === false, 'token válido sin lead: no marca');

// respaldo contact_form: gancho de at_demo_request con la cookie
$e = at_pro_crear(['negocio' => 'Con formulario', 'web' => 'conformulario.test']);
$te = at_pro_obtener($e)['token'];
$_COOKIE['at_pro_r'] = $te;
$antes = count($llamadas);
wp_insert_post(['post_type' => 'at_demo_request', 'post_title' => 'Visita — gancho@prueba.test', 'post_status' => 'private', 'meta_input' => ['_at_name' => 'Visita Gancho', '_at_email' => 'gancho@prueba.test', '_at_phone' => '+56955508888', '_at_source' => 'contact_form', '_at_date' => current_time('mysql')]]);
ok(at_pro_obtener($e)['estado'] === 'formulario' && count($llamadas) === $antes + 1, 'gancho at_demo_request con cookie marca igual que el AJAX');
ok($llamadas[count($llamadas) - 1][1]['nombre'] === 'Visita Gancho' && $llamadas[count($llamadas) - 1][1]['telefono'] === '+56955508888', 'el gancho toma nombre y teléfono del contacto');
$f = at_pro_crear(['negocio' => 'Sin cookie', 'web' => 'sincookie.test']);
unset($_COOKIE['at_pro_r']);
wp_insert_post(['post_type' => 'at_demo_request', 'post_title' => 'Otra — otra@prueba.test', 'post_status' => 'private', 'meta_input' => ['_at_name' => 'Otra', '_at_email' => 'otra@prueba.test', '_at_source' => 'contact_form']]);
ok(at_pro_obtener($f)['estado'] === 'por_contactar', 'gancho sin cookie no marca a nadie');

// límite por IP del AJAX
$ip = '203.0.113.' . wp_rand(1, 250);
$ok = 0;
for ($i = 0; $i < 12; $i++) { if (at_pro_limite_ip($ip)) { $ok++; } }
ok($ok === 10, 'el AJAX público permite 10 por hora por IP: ' . $ok);
delete_transient('at_pro_rl_' . md5($ip));

$wpdb->query("DELETE FROM {$t}");
$wpdb->query("DELETE FROM {$leads} WHERE email LIKE '%@prueba.test'");
$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'at_demo_request' AND post_title LIKE '%@prueba.test'");
fin();
