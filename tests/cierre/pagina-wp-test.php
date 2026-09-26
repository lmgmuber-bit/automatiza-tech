<?php
// Correr: AT_WP_LOAD=<ruta> php tests/cierre/pagina-wp-test.php
// T7 ronda 1, hallazgo 1: automatizatech.cl está detrás del CDN de Hostinger (no de Cloudflare), así
// que CF-Connecting-IP y X-Forwarded-For las puede poner el propio cliente. La IP de evidencia debe
// ser siempre REMOTE_ADDR, y el límite de intentos no debe poder saltarse cambiando esas cabeceras.
require __DIR__ . '/wp-bootstrap.php';

$marca = 'prueba-cierre-pagina-' . strtolower(wp_generate_password(6, false, false));

// ---------- at_cc_ip() ignora las cabeceras manipulables; at_cc_ip_reenviada() las recoge aparte ----------
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): sin cabeceras, usa REMOTE_ADDR');
ok(at_cc_ip_reenviada() === '', 'at_cc_ip_reenviada(): sin cabeceras, vacío');

$_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.99';
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): con CF-Connecting-IP falsa, sigue siendo REMOTE_ADDR');
ok(at_cc_ip_reenviada() === '198.51.100.99', 'at_cc_ip_reenviada(): recoge la CF-Connecting-IP, solo informativa');

$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 10.0.0.1';
ok(at_cc_ip() === '203.0.113.10', 'at_cc_ip(): con X-Forwarded-For también falso, sigue siendo REMOTE_ADDR');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
ok(at_cc_ip_reenviada() === '198.51.100.1', 'at_cc_ip_reenviada(): sin CF-Connecting-IP, toma el primer valor de X-Forwarded-For');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

// ---------- el límite por código (el principal) no se salta cambiando la cabecera ----------
$accion = 'test_' . $marca;
$codigo = 'codigo-' . $marca;
for ($i = 0; $i < 3; $i++) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.10.10.' . $i; // una cabecera distinta en cada envío
	ok(at_cc_limite_codigo_ok($accion, $codigo, 3, HOUR_IN_SECONDS) === true, 'límite por código: intento ' . ($i + 1) . ' de 3 permitido');
}
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.10.10.99'; // otra cabecera más, distinta a las tres anteriores
ok(at_cc_limite_codigo_ok($accion, $codigo, 3, HOUR_IN_SECONDS) === false, 'límite por código: el 4º intento se bloquea aunque la cabecera cambió en cada envío');
ok(at_cc_limite_codigo_ok($accion, $codigo . '-otro', 3, HOUR_IN_SECONDS) === true, 'límite por código: un código de propuesta distinto tiene su propio contador');

// ---------- el límite amplio por IP real (REMOTE_ADDR) tampoco lo evita la cabecera ----------
$accion_ip = 'test_ip_' . $marca;
$_SERVER['REMOTE_ADDR'] = '203.0.113.30';
for ($i = 0; $i < 2; $i++) {
	$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.20.20.' . $i;
	ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === true, 'límite por IP: intento ' . ($i + 1) . ' de 2 permitido');
}
$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.20.20.99';
ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === false, 'límite por IP: se bloquea aunque la cabecera cambió (la clave es REMOTE_ADDR, no la cabecera)');
$_SERVER['REMOTE_ADDR'] = '203.0.113.31'; // otra conexión real: no comparte el contador de la anterior
ok(at_cc_limite_ip_ok($accion_ip, 2, HOUR_IN_SECONDS) === true, 'límite por IP: otra IP real (REMOTE_ADDR distinto) tiene su propio contador');

// Limpieza: cabeceras de prueba y los transients que quedaron con el máximo alcanzado.
unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
delete_transient('at_cc_lim_cod_' . md5($accion . '|' . $codigo));
delete_transient('at_cc_lim_cod_' . md5($accion . '|' . $codigo . '-otro'));
delete_transient('at_cc_lim_' . md5($accion_ip . '|203.0.113.30'));
delete_transient('at_cc_lim_' . md5($accion_ip . '|203.0.113.31'));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

fin();
