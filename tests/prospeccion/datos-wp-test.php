<?php
// Uso: AT_WP_LOAD=<wp-load.php> php tests/prospeccion/datos-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
at_pro_migrar_esquema();
$t = at_pro_tabla();
$bajas = $wpdb->prefix . 'at_retomar_bajas';
$limpiar = function () use ($wpdb, $t, $bajas) {
	$wpdb->query("DELETE FROM {$t}");
	$wpdb->query("DELETE FROM {$bajas} WHERE origen = 'prospeccion' OR email LIKE '%@prueba.test'");
};
$limpiar();

// crear: campos, dominio normalizado, duplicados
$a = at_pro_crear(['negocio' => 'Uno', 'web' => 'https://www.uno.test/x', 'telefono' => '+56 9 4440 0001', 'correo' => 'Hola@Uno.test', 'fuente' => 'manual']);
ok(is_int($a) && $a > 0, 'crear devuelve id');
$p = at_pro_obtener($a);
ok($p['dominio'] === 'uno.test' && $p['telefono'] === '56944400001' && $p['correo'] === 'hola@uno.test', 'se guardan dominio, teléfono y correo normalizados');
ok($p['estado'] === 'por_contactar' && at_pro_token_valido($p['token']), 'estado inicial y token válido');
ok(at_pro_por_token($p['token'])['id'] == $a && at_pro_por_token('0000000000000000') === null && at_pro_por_token('ZZZ') === null, 'por_token');
$r = at_pro_crear(['negocio' => 'Uno otra vez', 'web' => 'uno.test']);
ok(is_wp_error($r) && $r->get_error_code() === 'repetido', 'mismo dominio -> repetido');
$r = at_pro_crear(['negocio' => 'Dos', 'telefono' => '944400001']);
ok(is_wp_error($r) && $r->get_error_code() === 'repetido', 'mismo teléfono con otro formato -> repetido');
$r = at_pro_crear(['negocio' => 'Vacío']);
ok(is_wp_error($r) && $r->get_error_code() === 'sin_contacto', 'sin web, teléfono ni correo -> sin_contacto');
$b1 = at_pro_crear(['negocio' => 'Solo correo A', 'correo' => 'a@solo.test']);
$b2 = at_pro_crear(['negocio' => 'Solo correo B', 'correo' => 'b@solo.test']);
ok(is_int($b1) && is_int($b2) && at_pro_obtener($b1)['dominio'] === null, 'dos filas sin dominio conviven (dominio NULL, no vacío)');
// Calibre H1: nunca truncar en silencio
$r = at_pro_crear(['negocio' => str_repeat('x', 191), 'web' => 'largo1.test']);
ok(is_wp_error($r) && $r->get_error_code() === 'demasiado_largo', 'negocio de 191 se rechaza, no se corta');
$r = at_pro_crear(['negocio' => str_repeat('x', 190), 'web' => 'largo2.test']);
ok(is_int($r), 'negocio de 190 entra');
$r = at_pro_crear(['negocio' => 'Web larga', 'web' => 'https://larga.test/' . str_repeat('a', 490)]);
ok(is_wp_error($r) && $r->get_error_code() === 'demasiado_largo', 'web de más de 500 se rechaza');
$r = at_pro_crear(['negocio' => 'Tel malo', 'telefono' => '56 9 1111 2222 56 9 3333 4444 5555']);
ok(is_wp_error($r), 'dos teléfonos pegados en un campo no entran como uno solo');

// bajas: lista común por correo y baja propia por dominio/teléfono
$wpdb->insert($bajas, ['email' => 'bajado@prueba.test', 'telefono' => '', 'origen' => 'otro', 'creado_at' => current_time('mysql')]);
$r = at_pro_crear(['negocio' => 'Bajado', 'correo' => 'Bajado@Prueba.test', 'web' => 'bajado.test']);
ok(is_wp_error($r) && $r->get_error_code() === 'en_bajas', 'correo en la lista común de bajas -> en_bajas');

// estados
$id = at_pro_crear(['negocio' => 'Estados', 'web' => 'estados.test']);
ok(at_pro_cambiar_estado($id, 'contactado', 'whatsapp') === true, 'por_contactar -> contactado');
$p1 = at_pro_obtener($id);
ok($p1['estado'] === 'contactado' && $p1['canal'] === 'whatsapp' && $p1['contactado_at'] !== null, 'contactado_at fijado y canal guardado');
$wpdb->update($t, ['contactado_at' => '2026-01-02 03:04:05'], ['id' => $id]);
at_pro_cambiar_estado($id, 'respondio');
at_pro_cambiar_estado($id, 'descartado');
at_pro_cambiar_estado($id, 'contactado', 'correo');
ok(at_pro_obtener($id)['contactado_at'] === '2026-01-02 03:04:05', 'contactado_at no se pisa la segunda vez');
ok(at_pro_cambiar_estado($id, 'inventado') === false, 'estado inexistente -> false');
ok(at_pro_cambiar_estado(999999, 'contactado') === false, 'id inexistente -> false');

// baja
$id2 = at_pro_crear(['negocio' => 'Se va', 'web' => 'seva.test', 'correo' => 'seva@prueba.test', 'telefono' => '+56 9 3330 0001']);
$tk = at_pro_obtener($id2)['token'];
ok(at_pro_dar_baja($tk) === true, 'dar_baja con token');
ok(at_pro_obtener($id2)['estado'] === 'baja' && at_pro_obtener($id2)['baja_at'] !== null, 'estado baja y baja_at');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$bajas} WHERE email = %s AND origen = 'prospeccion'", 'seva@prueba.test')) === 1, 'fila en la lista común de bajas');
at_pro_dar_baja($tk);
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$bajas} WHERE email = %s", 'seva@prueba.test')) === 1, 'baja repetida no duplica la fila común');
ok(at_pro_cambiar_estado($id2, 'contactado') === false, 'baja -> contactado devuelve false');
ok(at_pro_dar_baja('0000000000000000') === false, 'token inexistente -> false');
$r = at_pro_crear(['negocio' => 'Vuelve', 'web' => 'https://www.seva.test']);
ok(is_wp_error($r) && $r->get_error_code() === 'en_bajas', 'mismo dominio de un baja -> en_bajas (no repetido)');
$r = at_pro_crear(['negocio' => 'Vuelve por correo', 'correo' => 'SEVA@prueba.test']);
ok(is_wp_error($r) && $r->get_error_code() === 'en_bajas', 'mismo correo de un baja -> en_bajas');
$r = at_pro_crear(['negocio' => 'Vuelve por teléfono', 'telefono' => '933300001']);
ok(is_wp_error($r) && $r->get_error_code() === 'en_bajas', 'mismo teléfono de un baja -> en_bajas');

// importación
$limpiar();
$prev = at_pro_crear(['negocio' => 'Baja Test', 'web' => 'bajatest.test']);
at_pro_dar_baja(at_pro_obtener($prev)['token']);
$res = at_pro_importar_csv(__DIR__ . '/fixtures/ejemplo.csv', 'lead_scout');
ok($res['entraron'] === 3 && $res['repetidos'] === 1 && $res['rechazados'] === 1 && $res['bajas'] === 1, 'importar ejemplo.csv (BOM, ;): ' . json_encode($res));
$uno = $wpdb->get_row("SELECT * FROM {$t} WHERE dominio = 'cafeuno.test'", ARRAY_A);
ok($uno && $uno['negocio'] === 'Café Uno' && $uno['fuente'] === 'lead_scout' && $uno['comuna'] === 'Providencia' && $uno['senales'] === 'sin reservas en línea', 'campos mapeados con tildes');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE estado='baja'") === 1, 'la baja sigue siendo una sola fila');
$res = at_pro_importar_csv(__DIR__ . '/fixtures/ejemplo.csv', 'lead_scout');
ok($res['entraron'] === 0 && $res['repetidos'] === 4, 'reimportar: todo repetido, nada entra: ' . json_encode($res));
// separador coma + Windows-1252
$tmp = tempnam(sys_get_temp_dir(), 'pro');
file_put_contents($tmp, "name,phone,website\n" . mb_convert_encoding('Peluquería Ñandú', 'Windows-1252', 'UTF-8') . ",+56 9 5550 0099,https://nandu.test\n");
$res = at_pro_importar_csv($tmp, 'sellbird');
ok($res['entraron'] === 1, 'separador coma y Windows-1252: ' . json_encode($res));
ok($wpdb->get_var("SELECT negocio FROM {$t} WHERE dominio='nandu.test'") === 'Peluquería Ñandú', 'Windows-1252 convertido a UTF-8');
file_put_contents($tmp, "foo,bar\n1,2\n");
$res = at_pro_importar_csv($tmp, 'sellbird');
ok($res['entraron'] === 0 && count($res['errores']) >= 1, 'CSV sin columna de nombre -> error informado');
$res = at_pro_importar_csv($tmp . '.noexiste', 'sellbird');
ok($res['entraron'] === 0 && count($res['errores']) >= 1, 'archivo inexistente -> error informado');
unlink($tmp);
at_pro_importar_csv(__DIR__ . '/fixtures/ejemplo.csv', 'inventada');
ok($wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE fuente='inventada'") === '0', 'fuente fuera de lista no se guarda');

// conteos
$c = at_pro_conteos();
ok(isset($c['estado']['por_contactar'], $c['estado']['baja'], $c['estado']['formulario']) && $c['estado']['baja'] === 1, 'conteos por estado (con ceros)');
ok(($c['fuente']['lead_scout'] ?? 0) >= 3 && isset($c['rubro']['Cafetería']), 'conteos por fuente y rubro');
ok($c['total'] === array_sum($c['estado']), 'total = suma de estados');
$limpiar();
fin();
