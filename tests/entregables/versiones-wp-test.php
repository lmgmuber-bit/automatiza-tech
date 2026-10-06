<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/versiones-wp-test.php
// Versiones editables mientras no se envían, y la página pública muestra la última versión ENVIADA (no la vigente).
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/accion-helpers.php';
exigir('at_en_editar_version', 'at_en_version_publica', 'at_en_versiones_enviadas', 'at_en_html_pagina', 'at_en_render_pestana', 'at_en_html_resumen_portal');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); en_fx_limpiar($marca . 'b'); });
$admin = en_admin_id();
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
$cod = (string) at_en_por_id($id)->codigo;
$base = ['codigo' => $cod, 'token' => at_en_token_hoy($cod), 'nombre' => 'Cliente', 'texto' => 'Hola, una nota'];
$pestana = function () use ($fx, $admin, $id) { wp_set_current_user($admin); $_GET['en'] = $id; ob_start(); at_en_render_pestana(['id' => $fx['crm']]); return ob_get_clean(); };
$item = ['id' => $fx['detalle'], 'source' => 'client', 'detail_type' => 'entregable'];

// v1 creada pero sin enviar: el cliente no ve nada
at_en_crear_version($id, 'https://example.com/v1', 'Primera');
$e = at_en_por_id($id);
ok(at_en_version_publica($e) === null && at_en_versiones_enviadas($id) === [], 'v1 sin enviar: no hay versión pública');
ok(strpos(at_en_html_pagina($e), 'Este enlace no está disponible.') !== false && strpos(at_en_html_pagina($e), '<form') === false, 'v1 sin enviar: la página dice «no disponible»');
$q = en_query(en_correr('at_en_nota', 0, '-', $base)['redirect']);
ok(($q['en_msg'] ?? '') === 'no_disponible' && count(at_en_notas($id)) === 0, 'v1 sin enviar: el formulario tampoco guarda notas');
ok(at_en_html_resumen_portal($item) === '', 'v1 sin enviar: el portal no muestra resumen');

// Se envía v1 por WhatsApp: ahora sí la ve
at_en_marcar_version_enviada($id, 1, 'whatsapp');
$e = at_en_por_id($id);
ok(at_en_version_publica($e) && (int) at_en_version_publica($e)->numero === 1, 'v1 enviada por WhatsApp: es la pública');
$h = at_en_html_pagina($e);
ok(strpos($h, 'Abrir la versión 1') !== false && strpos($h, 'href="https://example.com/v1"') !== false && strpos($h, '<form') !== false, 'v1 enviada: la página la muestra con formulario');
ok(strpos(at_en_html_resumen_portal($item), 'Versión 1') !== false, 'v1 enviada: el portal muestra «Versión 1»');

// v2 creada sin enviar: la página sigue en v1 y las notas van a v1
at_en_crear_version($id, 'https://example.com/v2', 'Segunda');
$e = at_en_por_id($id);
ok((int) $e->version_vigente === 2 && (int) at_en_version_publica($e)->numero === 1, 'v2 sin enviar: vigente 2, pública 1');
$h = at_en_html_pagina($e);
ok(strpos($h, 'Abrir la versión 1') !== false && strpos($h, 'example.com/v2') === false && strpos($h, 'Versiones anteriores') === false && strpos($h, 'Abrir la versión 2') === false, 'v2 sin enviar: la página no la menciona');
ok(strpos(at_en_html_resumen_portal($item), 'Versión 1') !== false && strpos(at_en_html_resumen_portal($item), 'Versión 2') === false, 'v2 sin enviar: el portal sigue en la versión 1');
$q = en_query(en_correr('at_en_nota', 0, '-', $base)['redirect']);
$notas = at_en_notas($id);
ok(($q['en_msg'] ?? '') === 'nota_ok' && count($notas) === 1 && (int) $notas[0]->version_numero === 1, 'la nota del cliente se guarda sobre la versión 1 (la que ve)');
ok(strpos(at_en_html_pagina(at_en_por_id($id)), 'sobre la versión 1') !== false, 'la nota dice «sobre la versión 1»');

// Respuesta de AT: queda sobre la versión de la nota que contesta (1), no sobre la vigente (2)
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => (int) $notas[0]->id, 'texto' => 'Gracias, lo reviso']);
$todas = at_en_notas($id);
$resp = end($todas);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && $resp->autor === 'at' && (int) $resp->version_numero === 1, 'la respuesta de AT queda sobre la versión 1');
ok($resp->nombre === 'Luis', 'la respuesta se firma «Luis» aunque el usuario se llame distinto');

// Edición de v2 mientras no se envía
$ok = at_en_editar_version($id, 2, 'https://example.com/v2b', "Segunda corregida\n- punto");
ok($ok === true && at_en_version($id, 2)->url === 'https://example.com/v2b' && strpos((string) at_en_version($id, 2)->mensaje, 'corregida') !== false, 'editar v2 sin enviar: guarda enlace y mensaje');
ok(at_en_editar_version($id, 2, 'https://example.com/v2b', "Segunda corregida\n- punto") === true, 'editar con los mismos datos: sigue siendo true');
$er = at_en_editar_version($id, 2, 'https://x.easypanel.host/p', '');
ok(is_wp_error($er) && $er->get_error_code() === 'url_invalida', 'editar con enlace easypanel: url_invalida');
$er = at_en_editar_version($id, 2, 'https://example.com/v2c', 'Mira https://algo.easypanel.host/x');
ok(is_wp_error($er) && $er->get_error_code() === 'mensaje_easypanel' && at_en_version($id, 2)->url === 'https://example.com/v2b', 'editar con easypanel en el mensaje: mensaje_easypanel y no cambia');
$er = at_en_editar_version($id, 2, 'https://example.com/v2c', str_repeat('a', 6001));
ok(is_wp_error($er) && $er->get_error_code() === 'mensaje_largo', 'editar con mensaje de 6.001: mensaje_largo');
$er = at_en_editar_version($id, 9, 'https://example.com/v9', '');
ok(is_wp_error($er) && $er->get_error_code() === 'sin_version', 'editar una versión que no existe: sin_version');
$er = at_en_editar_version($id, 1, 'https://example.com/otra', '');
ok(is_wp_error($er) && $er->get_error_code() === 'ya_enviada' && at_en_version($id, 1)->url === 'https://example.com/v1', 'editar v1 (enviada por WhatsApp): ya_enviada y no cambia');

// Panel: formulario «Editar esta versión» solo mientras la vigente no se envía
$html = $pestana();
ok(strpos($html, 'Editar esta versión') !== false && strpos($html, 'value="https://example.com/v2b"') !== false && strpos($html, 'name="action" value="at_en_version_editar"') !== false, 'panel: formulario de edición con la v2 cargada');
$r = en_correr('at_en_version_editar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 2, 'url' => 'https://example.com/v2d', 'mensaje' => 'Tercera redacción']);
$q = en_query($r['redirect']);
ok(($q['en_msg'] ?? '') === 'version_editada' && $q['#'] === 'tab-entregables' && at_en_version($id, 2)->url === 'https://example.com/v2d', 'acción de edición: version_editada y guarda');
$r = en_correr('at_en_version_editar', $admin, 'otro_nonce', ['entregable_id' => $id, 'numero' => 2, 'url' => 'https://example.com/hack', 'mensaje' => '']);
ok(at_en_version($id, 2)->url === 'https://example.com/v2d', 'editar con nonce malo: no cambia');
$sin_permiso = wp_insert_user(['user_login' => 'sub' . $marca, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
register_shutdown_function(function () use ($sin_permiso) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($sin_permiso); });
$r = en_correr('at_en_version_editar', (int) $sin_permiso, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 2, 'url' => 'https://example.com/hack', 'mensaje' => '']);
ok(at_en_version($id, 2)->url === 'https://example.com/v2d', 'editar sin manage_options: no cambia');
$r = en_correr('at_en_version_editar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 2, 'url' => 'https://example.com/v2e', 'mensaje' => 'Ver https://x.EasyPanel.host/a']);
ok(en_query($r['redirect'])['en_msg'] === 'mensaje_easypanel' && at_en_version($id, 2)->url === 'https://example.com/v2d', 'acción de edición con easypanel: mensaje_easypanel');
$r = en_correr('at_en_version_editar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1, 'url' => 'https://example.com/v1b', 'mensaje' => '']);
ok(en_query($r['redirect'])['en_msg'] === 'edicion_enviada' && at_en_version($id, 1)->url === 'https://example.com/v1', 'acción de edición de una enviada: edicion_enviada');

// Se envía v2 por correo: ya no se edita y la página pasa a v2
ok(at_en_enviar_version($id, 2, false)['ok'], 'v2 enviada por correo');
$e = at_en_por_id($id);
ok(at_en_editar_version($id, 2, 'https://example.com/tarde', '')->get_error_code() === 'ya_enviada' && at_en_version($id, 2)->url === 'https://example.com/v2d', 'v2 enviada: editar da ya_enviada');
$html = $pestana();
ok(strpos($html, 'Editar esta versión') === false, 'panel: sin formulario de edición cuando la vigente ya se envió');
$h = at_en_html_pagina($e);
ok((int) at_en_version_publica($e)->numero === 2 && strpos($h, 'Abrir la versión 2') !== false && strpos($h, 'href="https://example.com/v2d"') !== false, 'v2 enviada: la página la muestra como vigente');
ok(strpos($h, 'Versiones anteriores') !== false && strpos($h, 'href="https://example.com/v1"') !== false, 'v2 enviada: v1 pasa a anteriores');
ok(strpos(at_en_html_resumen_portal($item), 'Versión 2') !== false, 'v2 enviada: el portal pasa a «Versión 2»');
$q = en_query(en_correr('at_en_nota', 0, '-', ['texto' => 'Sobre la nueva'] + $base)['redirect']);
$todas = at_en_notas($id);
ok(($q['en_msg'] ?? '') === 'nota_ok' && (int) end($todas)->version_numero === 2, 'nota nueva: queda sobre la versión 2');

// Una versión intermedia sin enviar no aparece en «anteriores»
at_en_crear_version($id, 'https://example.com/v3', '');   // v3 sin enviar
at_en_crear_version($id, 'https://example.com/v4', '');   // v4 enviada, v3 nunca
at_en_marcar_version_enviada($id, 4, 'whatsapp');
$h = at_en_html_pagina(at_en_por_id($id));
ok(strpos($h, 'Abrir la versión 4') !== false && strpos($h, 'example.com/v3') === false && strpos($h, 'Versión 2') !== false && strpos($h, 'Versión 1') !== false, 'versiones sin enviar no aparecen en la lista de anteriores');

// Un entregable con solo versiones sin enviar: el portal no muestra resumen
$fx2 = en_fx_cliente($marca . 'b');
$id2 = at_en_activar($fx2['detalle']);
at_en_crear_version($id2, 'https://example.com/z1', '');
ok(at_en_html_resumen_portal(['id' => $fx2['detalle'], 'source' => 'client', 'detail_type' => 'entregable']) === '', 'solo sin enviar: sin resumen en el portal');

// Cabecera de privacidad de la página pública (se revisa en el código: ver-entregable.php sale por exit)
$src = (string) file_get_contents(__DIR__ . '/../../ver-entregable.php');
ok(strpos($src, "header('Referrer-Policy: no-referrer');") !== false, 'ver-entregable.php manda Referrer-Policy: no-referrer');
ok(strpos($src, 'at_en_version_publica(') !== false && strpos($src, 'version_vigente < 1') === false, 'ver-entregable.php decide con la versión pública, no con la vigente');
fin();
