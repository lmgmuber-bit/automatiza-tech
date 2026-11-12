<?php
// Uso: AT_WP_LOAD=<wp-load.php> php tests/prospeccion/baja-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
global $wpdb;
at_pro_migrar_esquema();
$t = at_pro_tabla();
$bajas = $wpdb->prefix . 'at_retomar_bajas';
$wpdb->query("DELETE FROM {$t}");
$wpdb->query("DELETE FROM {$bajas} WHERE email LIKE '%@prueba.test'");

$a = at_pro_crear(['negocio' => 'Se va', 'web' => 'seva.test', 'correo' => 'seva@prueba.test', 'telefono' => '+56 9 5550 3003']);
$tk = at_pro_obtener($a)['token'];

// GET: muestra el botón y NO cambia nada
$r = at_pro_baja_pagina($tk, false, '');
ok($r['status'] === 200 && strpos($r['html'], 'Confirmar baja') !== false && strpos($r['html'], 'noindex') !== false, 'GET: página con «Confirmar baja» y noindex');
ok(at_pro_obtener($a)['estado'] === 'por_contactar', 'GET no cambia el estado (un antivirus que abre el enlace no da de baja)');
ok(strpos($r['html'], 'Se va') === false, 'la página no revela el nombre del negocio');

// POST sin nonce o con nonce de otro token: no cambia
$r = at_pro_baja_pagina($tk, true, 'malo');
ok(at_pro_obtener($a)['estado'] === 'por_contactar', 'POST sin nonce válido no da de baja');
$otro = at_pro_crear(['negocio' => 'Otro', 'web' => 'otro.test']);
$r = at_pro_baja_pagina($tk, true, wp_create_nonce('at_pro_baja_' . at_pro_obtener($otro)['token']));
ok(at_pro_obtener($a)['estado'] === 'por_contactar', 'nonce de otro token no sirve');

// POST con nonce: baja
$n = wp_create_nonce('at_pro_baja_' . $tk);
$r = at_pro_baja_pagina($tk, true, $n);
ok($r['status'] === 200 && strpos($r['html'], 'no te volveremos a escribir') !== false, 'POST con nonce: confirma en pantalla');
ok(at_pro_obtener($a)['estado'] === 'baja' && at_pro_obtener($a)['baja_at'] !== null, 'estado baja');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$bajas} WHERE email = 'seva@prueba.test' AND origen = 'prospeccion'") === 1, 'queda en la lista común de bajas');
$r = at_pro_baja_pagina($tk, true, $n);
ok(strpos($r['html'], 'no te volveremos a escribir') !== false && (int) $wpdb->get_var("SELECT COUNT(*) FROM {$bajas} WHERE email = 'seva@prueba.test'") === 1, 'confirmar dos veces: mismo mensaje y sin duplicar');
$r = at_pro_baja_pagina($tk, false, '');
ok(strpos($r['html'], 'no te volveremos a escribir') !== false, 'GET de un token ya dado de baja: ya está confirmado');

// token inválido: 200 y mensaje genérico, sin revelar
foreach (['zzzzzzzzzzzzzzzz', '', '<script>alert(1)</script>', str_repeat('a', 300)] as $malo) {
	$g = at_pro_baja_pagina($malo, false, '');
	$p = at_pro_baja_pagina($malo, true, wp_create_nonce('at_pro_baja_' . $malo));
	ok($g['status'] === 200 && $p['status'] === 200 && strpos($p['html'], 'no te volveremos a escribir') !== false && strpos($p['html'], '<script>alert') === false, 'token inválido «' . substr($malo, 0, 12) . '»: 200 y mensaje genérico, sin eco');
}
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE estado = 'baja'") === 1, 'ningún token inválido dio de baja a nadie');

$wpdb->query("DELETE FROM {$t}");
$wpdb->query("DELETE FROM {$bajas} WHERE email LIKE '%@prueba.test'");
fin();
