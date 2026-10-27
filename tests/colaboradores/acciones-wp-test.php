<?php
// Agregar nota y responder comentarios. Uso: bash tests/colaboradores/correr.sh acciones
require __DIR__ . '/wp-bootstrap.php';
exigir('at_colab_validar_nota', 'at_colab_accion_nota', 'at_colab_accion_responder');
global $wpdb;

/** Corre una acción con este POST y devuelve el «msg» de la redirección (en pruebas, at_colab_volver lanza). */
function col_accion(string $funcion, array $post): string {
	$_POST = $post;
	$_REQUEST = $post;
	$_FILES = [];
	try {
		$funcion();
	} catch (AtColabRedireccion $r) {
		parse_str((string) parse_url($r->url, PHP_URL_QUERY), $q);
		return (string) ($q['msg'] ?? '');
	}
	return '(sin redirección)';
}

ok(at_colab_validar_nota('   ')['error'] === 'nota_vacia', 'validar: solo espacios es nota vacía');
ok(at_colab_validar_nota(str_repeat('a', 6001))['error'] === 'nota_larga', 'validar: 6.001 caracteres es muy larga');
$v = at_colab_validar_nota("  Hola equipo  \n");
ok($v['ok'] && $v['texto'] === 'Hola equipo', 'validar: recorta los espacios');

$c = col_crear_cliente('Orly Acciones', 'Funeraria');
$ficha = col_crear_ficha($c);
$e = col_crear_entregable($c, $ficha, 'Sitio funeraria');
$otro = col_crear_cliente('Cliente Ajeno');
$ficha_otro = col_crear_ficha($otro);
$e_otro = col_crear_entregable($otro, $ficha_otro, 'Sitio ajeno');
$colab = col_crear_usuario('at_colaborador', [$c]);
wp_update_user(['ID' => $colab, 'display_name' => 'Julio Colaborador']);
wp_set_current_user($colab);

$cuenta = function (int $crm) use ($wpdb) {
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'nota'", $crm));
};

// Nota en su cliente.
$GLOBALS['col_correos'] = [];
$msg = col_accion('at_colab_accion_nota', ['crm_id' => $c, 'nota' => 'Hablé con Orly por el logo.', '_at_colab' => wp_create_nonce('at_colab_nota_' . $c)]);
$fila = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'nota' ORDER BY id DESC LIMIT 1", $c));
ok($msg === 'nota_ok', 'nota: vuelve con nota_ok');
ok($fila && (int) $fila->usuario_id === $colab && $fila->descripcion === 'Hablé con Orly por el logo.' && $fila->titulo === 'Nota agregada', 'nota: queda con su usuario_id y el texto');
ok(count($GLOBALS['col_correos']) === 1 && strpos((string) $GLOBALS['col_correos'][0]['subject'], 'Julio Colaborador') !== false, 'nota: un correo a avisos con el nombre del colaborador');

// Nota en un cliente no asignado.
$antes = $cuenta($otro);
$msg = col_accion('at_colab_accion_nota', ['crm_id' => $otro, 'nota' => 'Intrusa', '_at_colab' => wp_create_nonce('at_colab_nota_' . $otro)]);
ok($msg === 'sin_acceso' && $cuenta($otro) === $antes, 'nota en cliente no asignado: sin_acceso y ninguna fila');

// Nonce de otro cliente.
$antes = $cuenta($c);
$msg = col_accion('at_colab_accion_nota', ['crm_id' => $c, 'nota' => 'Con nonce ajeno', '_at_colab' => wp_create_nonce('at_colab_nota_' . $otro)]);
ok($msg === 'sin_acceso' && $cuenta($c) === $antes, 'nota con nonce de otro cliente: rechazada');

// Nota vacía y nota larga.
ok(col_accion('at_colab_accion_nota', ['crm_id' => $c, 'nota' => '   ', '_at_colab' => wp_create_nonce('at_colab_nota_' . $c)]) === 'nota_vacia' && $cuenta($c) === $antes, 'nota vacía: rechazada sin fila');
ok(col_accion('at_colab_accion_nota', ['crm_id' => $c, 'nota' => str_repeat('b', 6001), '_at_colab' => wp_create_nonce('at_colab_nota_' . $c)]) === 'nota_larga' && $cuenta($c) === $antes, 'nota larga: rechazada sin fila');

// Responder un comentario de su cliente.
$GLOBALS['col_correos'] = [];
$msg = col_accion('at_colab_accion_responder', ['nota_id' => $e['nota'], 'texto' => 'Listo, lo cambiamos en la v2.', 'avisar' => '1', '_at_colab' => wp_create_nonce('at_colab_resp_' . $e['nota'])]);
$resp = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_en_tablas()['n'] . " WHERE entregable_id = %d AND autor = 'at' ORDER BY id DESC LIMIT 1", $e['ent']));
ok($msg === 'respuesta_ok', 'responder: vuelve con respuesta_ok');
ok($resp && $resp->nombre === 'Julio Colaborador' && $resp->texto === 'Listo, lo cambiamos en la v2.', 'responder: la respuesta lleva el nombre del colaborador');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'entregable_respuesta'", $c)) === 1, 'responder: evento entregable_respuesta en el historial');
$correos = count($GLOBALS['col_correos']);
ok($correos === 1, 'responder: un correo de aviso al cliente');

// Doble envío.
$msg = col_accion('at_colab_accion_responder', ['nota_id' => $e['nota'], 'texto' => 'Otra vez', 'avisar' => '1', '_at_colab' => wp_create_nonce('at_colab_resp_' . $e['nota'])]);
ok($msg === 'ya_respondida' && count($GLOBALS['col_correos']) === $correos, 'responder dos veces: ya_respondida y ningún correo más');

// Responder un comentario de un entregable de otro cliente.
$msg = col_accion('at_colab_accion_responder', ['nota_id' => $e_otro['nota'], 'texto' => 'Intruso', '_at_colab' => wp_create_nonce('at_colab_resp_' . $e_otro['nota'])]);
$n_otro = at_en_nota($e_otro['nota']);
ok($msg === 'sin_acceso' && !(int) $n_otro->respondida, 'responder en cliente no asignado: sin_acceso y la nota sigue sin responder');

// Quitarle el cliente con la página abierta.
update_user_meta($colab, 'at_clientes_asignados', []);
$antes = $cuenta($c);
$msg = col_accion('at_colab_accion_nota', ['crm_id' => $c, 'nota' => 'Después de quitarlo', '_at_colab' => wp_create_nonce('at_colab_nota_' . $c)]);
ok($msg === 'sin_acceso' && $cuenta($c) === $antes, 'cliente quitado de la lista: la nota siguiente se rechaza');

fin();
