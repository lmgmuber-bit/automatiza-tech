<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/datos-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_migrar_esquema', 'at_en_activar', 'at_en_crear_version', 'at_en_agregar_nota', 'at_en_de_crm');
global $wpdb;
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
at_en_migrar_esquema();
$t = at_en_tablas();
foreach ($t as $tabla) {
	ok($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabla)) === $tabla, "tabla {$tabla} creada");
}
$fx = en_fx_cliente($marca);

// Activar
ok(at_en_detalle($fx['detalle']) !== null && at_en_crm_de_detalle(at_en_detalle($fx['detalle'])) === $fx['crm'], 'detalle y su cliente del CRM');
$id = at_en_activar($fx['detalle']);
ok(is_int($id) && $id > 0, 'activar crea el entregable');
$e = at_en_por_id($id);
ok($e && at_en_codigo_valido((string) $e->codigo) && $e->estado === 'abierto' && (int) $e->version_vigente === 0 && (int) $e->crm_id === $fx['crm'], 'código, abierto, sin versiones y con su cliente');
ok(at_en_activar($fx['detalle']) === $id, 'activar dos veces devuelve el mismo');
ok(at_en_por_codigo((string) $e->codigo)->id == $id && at_en_por_codigo(strtolower((string) $e->codigo) === (string) $e->codigo ? 'X' . substr((string) $e->codigo, 1) : strtolower((string) $e->codigo)) === null, 'por código exacto (mayúsculas distintas no)');
$wpdb->insert($wpdb->prefix . 'automatiza_clients_details', ['client_id' => $fx['tech'], 'detail_type' => 'nota', 'title' => 'No es entregable ' . $marca]);
ok(is_wp_error(at_en_activar((int) $wpdb->insert_id)), 'un detalle que no es entregable no se activa');
ok(is_wp_error(at_en_activar(999999999)), 'un detalle inexistente no se activa');

// Versiones
ok(at_en_crear_version($id, 'https://example.com/v1', 'Primera') === 1, 'v1');
ok(at_en_crear_version($id, 'https://example.com/v2', '') === 2, 'v2');
ok(is_wp_error(at_en_crear_version($id, 'https://x.easypanel.host/p', '')), 'URL easypanel rechazada');
ok(is_wp_error(at_en_crear_version($id, 'https://example.com/v3', str_repeat('a', 6001))), 'mensaje de 6.001 rechazado');
ok((int) at_en_por_id($id)->version_vigente === 2 && count(at_en_versiones($id)) === 2, 'vigente = 2 y dos versiones');
$wpdb->suppress_errors(true);
$dup = $wpdb->insert($t['v'], ['entregable_id' => $id, 'numero' => 2, 'url' => 'https://example.com/dup', 'creado_at' => current_time('mysql')]);
$wpdb->suppress_errors(false);
ok($dup === false, 'índice único: no puede haber dos v2');
ok(at_en_marcar_version_enviada($id, 2, 'correo') && at_en_version($id, 2)->enviado_correo_at !== null && at_en_version($id, 2)->enviado_whatsapp_at === null, 'marca enviada por correo');
ok(!at_en_marcar_version_enviada($id, 9, 'correo') && !at_en_marcar_version_enviada($id, 2, 'fax'), 'versión inexistente o canal raro: no');

// Notas
$n1 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Me gusta la v2', ['abcdefghijklmnopqrstuvwx.jpg'], str_repeat('a', 64));
ok(is_int($n1) && at_en_nota($n1)->version_numero == 2 && at_en_nota($n1)->autor === 'cliente' && (int) at_en_nota($n1)->respondida === 0, 'nota del cliente sobre la versión vigente');
ok(json_decode((string) at_en_nota($n1)->imagenes, true) === ['abcdefghijklmnopqrstuvwx.jpg'], 'imágenes como JSON');
ok(is_wp_error(at_en_agregar_nota($id, 'otro', 'X', 'y', [], '')), 'autor desconocido rechazado');
ok(is_wp_error(at_en_agregar_nota($id, 'cliente', 'X', '', [], '')), 'nota vacía rechazada');
ok(is_wp_error(at_en_agregar_nota($id, 'cliente', 'X', 'y', ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'], '')), 'más de 3 imágenes rechazado');
ok(at_en_sin_responder($id) === 1 && at_en_sin_responder_crm($fx['crm']) === 1, 'una sin responder');
$r1 = at_en_agregar_nota($id, 'at', 'Luis', 'Ya lo corrijo', [], '');
at_en_marcar_respondida($n1);
ok(is_int($r1) && at_en_sin_responder($id) === 0 && count(at_en_notas($id)) === 2, 'respuesta de AT y nota marcada respondida');
at_en_marcar_aviso($n1, false);
ok((int) at_en_nota($n1)->aviso_ok === 0, 'nota marcada sin aviso');

// Estado
ok(at_en_cambiar_estado($id, 'cerrado') && at_en_por_id($id)->estado === 'cerrado' && at_en_por_id($id)->cerrado_at !== null, 'cerrar');
ok(at_en_cambiar_estado($id, 'abierto') && at_en_por_id($id)->estado === 'abierto', 'reabrir');
ok(!at_en_cambiar_estado($id, 'borrado'), 'estado desconocido: no');

// Lista del cliente
$lista = at_en_de_crm($fx['crm']);
$fila = array_values(array_filter($lista, function ($f) use ($fx) { return (int) $f->id === $fx['detalle']; }))[0] ?? null;
ok($fila && (int) $fila->en_id === $id && (int) $fila->en_version === 2, 'la lista trae el entregable activado');
ok(count(array_filter($lista, function ($f) { return $f->detail_type !== 'entregable'; })) === 0, 'la lista solo trae entregables');

// Historial
at_en_historial($fx['crm'], 'entregable_nota', 'Nota de prueba', 'detalle');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'entregable_nota'", $fx['crm'])) === 1, 'historial del CRM escrito');
fin();
