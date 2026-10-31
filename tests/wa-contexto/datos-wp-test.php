<?php
// Fichas de un número desde la base. Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/datos-wp-test.php
// Carga el módulo de ESTA copia del repo sobre el WordPress local. Crea sus filas con datos inventados y las borra al final.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/puras.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/datos.php';
global $wpdb;
$wpdb->show_errors(false);
$errores = [];
function sin_error(string $donde) { global $wpdb, $errores; if ($wpdb->last_error !== '') { $errores[] = $donde . ': ' . $wpdb->last_error; } $wpdb->last_error = ''; }

// 1) Cliente del CRM con contrato de servicios firmado y plan
$n1 = wa_numero_libre();
[$crm1, $tech1] = wa_cliente_crm('a', wa_con_espacios($n1));
$k1 = wa_contrato($tech1, 'servicios', 'signed', true);
$codigo1 = wa_plan($k1, 'Proyecto Prueba WA');
$c = at_wa_contexto('569' . substr($n1, 1)); sin_error('contexto 569');
ok($c['situacion'] === 'cliente', 'cliente del CRM con contrato firmado (569…)');
$op = $c['opciones'][0] ?? [];
ok(($op['tipo'] ?? '') === 'proyecto' && ($op['id'] ?? 0) === $k1 && ($op['nombre'] ?? '') === 'Proyecto Prueba WA' && ($op['plan_codigo'] ?? '') === $codigo1, 'la opción es su proyecto, con el nombre y el código del plan');
ok(($c['agenda']['correo'] ?? '') === wa_correo('a') && ($c['nombre'] ?? '') === 'Julio', 'agenda con el correo de la ficha y el primer nombre');
$c9 = at_wa_contexto($n1); sin_error('contexto 9…');
ok($c9['situacion'] === 'cliente' && ($c9['opciones'][0]['id'] ?? 0) === $k1, 'el mismo número escrito como 9… da lo mismo');
$ce = at_wa_contexto('56' . $n1, 'Hola, quiero agendar mi seguimiento (código ' . $codigo1 . ')'); sin_error('contexto con código');
ok(($ce['elegida']['id'] ?? 0) === $k1, 'el código del plan en el texto elige ese proyecto');

// 2) Prospecto: propuesta enviada cuyo número solo está en la propuesta (sin ficha del CRM)
$n2 = wa_numero_libre();
$pr2 = wa_propuesta(wa_correo('b'), wa_con_espacios($n2), 'sent', '[PRUEBA] Spa Prueba');
$c2 = at_wa_contexto('56' . $n2); sin_error('contexto prospecto');
ok($c2['situacion'] === 'prospecto' && ($c2['opciones'][0]['tipo'] ?? '') === 'propuesta' && ($c2['opciones'][0]['id'] ?? 0) === $pr2, 'prospecto con su propuesta abierta, sin ficha del CRM');
ok(($c2['opciones'][0]['nombre'] ?? '') === '[PRUEBA] Spa Prueba' && ($c2['agenda']['correo'] ?? '') === wa_correo('b'), 'la propuesta se nombra por la empresa y la agenda sale de la propuesta');

// 3) Número desconocido
$c3 = at_wa_contexto('56' . wa_numero_libre()); sin_error('contexto desconocido');
ok($c3['situacion'] === 'nuevo' && $c3['opciones'] === [] && $c3['elegida'] === null, 'número desconocido: nuevo');

// 4) Mismo número en dos fichas del CRM
$n4 = wa_numero_libre();
[$crm4a, $tech4a] = wa_cliente_crm('c', wa_con_espacios($n4));
[$crm4b, $tech4b] = wa_cliente_crm('d', '56' . $n4);
$k4 = wa_contrato($tech4a, 'servicios', 'signed', true);
$c4 = at_wa_contexto($n4); sin_error('contexto compartido');
ok($c4['situacion'] === 'cliente' && $c4['nombre'] === '' && ($c4['agenda']['correo'] ?? 'x') === '', 'número en dos fichas: sin nombre y sin agenda de primer nivel');
ok(($c4['opciones'][0]['agenda']['correo'] ?? '') === wa_correo('c'), 'número en dos fichas: la opción lleva la agenda de su ficha');

// 5) Vínculo de una reunión
$v = at_wa_vinculo_valido($k1, 0, '56' . $n1, wa_correo('a')); sin_error('vínculo propio');
ok($v === ['contrato_id' => $k1, 'propuesta_id' => 0], 'el contrato propio se acepta');
$v = at_wa_vinculo_valido($k4, $pr2, '56' . $n1, wa_correo('a')); sin_error('vínculo ajeno');
ok($v === ['contrato_id' => 0, 'propuesta_id' => 0], 'el contrato y la propuesta de otro cliente se descartan');
$v = at_wa_vinculo_valido(0, $pr2, '', wa_correo('b')); sin_error('vínculo por correo');
ok($v['propuesta_id'] === $pr2, 'la propuesta se acepta por el correo aunque no venga el número');

ok($errores === [], 'ninguna consulta dejó error en la base' . ($errores ? ': ' . implode(' | ', $errores) : ''));
fin();
