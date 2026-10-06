<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/panel-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/accion-helpers.php';
exigir('at_en_render_pestana', 'at_en_html_resumen_portal', 'at_en_marca_lista');
global $wpdb;
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$admin = en_admin_id();
$fx = en_fx_cliente($marca);
$sin_permiso = wp_insert_user(['user_login' => 'sub' . $marca, 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
register_shutdown_function(function () use ($sin_permiso) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($sin_permiso); });

$pestana = function () use ($fx, $admin) { wp_set_current_user($admin); ob_start(); at_en_render_pestana(['id' => $fx['crm']]); return ob_get_clean(); };
ok(strpos($pestana(), 'Activar notas del cliente') !== false && strpos($pestana(), 'Propuestas ' . $marca) !== false, 'lista con botón Activar');

// Activar: sin permiso / nonce malo / bien
$r = en_correr('at_en_activar', (int) $sin_permiso, 'at_en_activar_' . $fx['detalle'], ['detalle_id' => $fx['detalle'], 'crm_id' => $fx['crm']]);
ok(at_en_por_detalle($fx['detalle']) === null, 'sin manage_options no activa');
$r = en_correr('at_en_activar', $admin, 'otro_nonce', ['detalle_id' => $fx['detalle'], 'crm_id' => $fx['crm']]);
ok(at_en_por_detalle($fx['detalle']) === null, 'nonce malo no activa');
$r = en_correr('at_en_activar', $admin, 'at_en_activar_' . $fx['detalle'], ['detalle_id' => $fx['detalle'], 'crm_id' => $fx['crm']]);
$e = at_en_por_detalle($fx['detalle']);
$q = en_query($r['redirect']);
ok($e && ($q['en_msg'] ?? '') === 'activado' && $q['#'] === 'tab-entregables' && (int) $q['en'] === (int) $e->id, 'activar y volver a la pestaña');
$id = (int) $e->id;

// Nueva versión
$r = en_correr('at_en_version_crear', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'url' => 'https://example.com/v1', 'mensaje' => "Hola\n- portada"]);
ok(en_query($r['redirect'])['en_msg'] === 'version_creada' && (int) at_en_por_id($id)->version_vigente === 1, 'versión 1 creada');
$r = en_correr('at_en_version_crear', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'url' => 'https://x.easypanel.host/', 'mensaje' => '']);
ok(en_query($r['redirect'])['en_msg'] === 'url_invalida', 'URL easypanel: url_invalida');
$html = $pestana();
ok(strpos($html, 'srcdoc=') !== false && strpos($html, 'Enviarme una prueba') !== false && strpos($html, 'Enviar al cliente por correo') !== false && strpos($html, 'Enviar por mi WhatsApp') !== false, 'vista previa y botones de envío');
ok(strpos($html, esc_html(at_en_texto_wa_version(at_en_por_id($id), 1))) !== false, 'texto de WhatsApp para copiar');

// Prueba / envío / WhatsApp
$r = en_correr('at_en_version_prueba', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(en_query($r['redirect'])['en_msg'] === 'prueba_enviada' && strpos($r['mails'][0] ?? '', '[PRUEBA]') !== false, 'prueba a Luis');
$r = en_correr('at_en_version_enviar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(en_query($r['redirect'])['en_msg'] === 'version_enviada' && at_en_version($id, 1)->enviado_correo_at !== null, 'enviada al cliente');
$r = en_correr('at_en_version_enviar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(en_query($r['redirect'])['en_msg'] === 'ya_enviada', 'doble clic: ya_enviada');
$r = en_correr('at_en_version_whatsapp', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok(strpos($r['redirect'], 'https://wa.me/56911111111?text=') === 0 && at_en_version($id, 1)->enviado_whatsapp_at !== null, 'WhatsApp abre wa.me y marca');

// Responder
$n = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Cambia el color', [], '');
ok(strpos($pestana(), '🔴 1 nota sin responder') !== false && at_en_marca_lista($fx['crm']) !== '', 'pendiente visible en la pestaña y en la lista');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n, 'texto' => 'Listo, cambiado', 'avisar' => '1']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && at_en_sin_responder($id) === 0, 'respuesta guardada y nota respondida');
ok(count(array_filter($r['mails'], function ($m) use ($marca) { return strpos($m, $marca . '@example.com') === 0; })) === 1, 'aviso al cliente con «Avisar»');
$n2 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Otra', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n2, 'texto' => 'Ok']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && $r['mails'] === [], 'sin «Avisar»: no sale correo');
$otro = en_fx_cliente($marca . 'b');
$id2 = at_en_activar($otro['detalle']);
at_en_crear_version($id2, 'https://example.com/o', '');
$n3 = at_en_agregar_nota($id2, 'cliente', 'Otro', 'Nota ajena', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n3, 'texto' => 'x']);
ok(en_query($r['redirect'])['en_msg'] === 'nota_ajena' && at_en_sin_responder($id2) === 1, 'nota de otro entregable: rechazada');
en_fx_limpiar($marca . 'b');

// Cerrar / reabrir
$r = en_correr('at_en_estado', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'estado' => 'cerrado']);
ok(en_query($r['redirect'])['en_msg'] === 'cerrado_ok' && at_en_por_id($id)->estado === 'cerrado', 'cerrar');
$r = en_correr('at_en_estado', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'estado' => 'abierto']);
ok(at_en_por_id($id)->estado === 'abierto', 'reabrir');

// Línea de tiempo en la pestaña
$html = $pestana();
ok(strpos($html, 'v1 enviada') !== false && strpos($html, 'Cambia el color') !== false && strpos($html, 'Listo, cambiado') !== false, 'línea de tiempo con versiones, notas y respuestas');
ok(strpos($html, at_en_url_pagina(home_url(), (string) at_en_por_id($id)->codigo)) !== false, 'enlace para copiar');

// Resumen del portal
$item = ['id' => $fx['detalle'], 'source' => 'client', 'detail_type' => 'entregable'];
$res = at_en_html_resumen_portal($item);
ok(strpos($res, 'Versión 1') !== false && strpos($res, '4 notas') !== false && strpos($res, 'Ver el detalle y las notas →') !== false, 'resumen con versión, notas y botón');
ok(strpos($res, 'Cambia el color') === false, 'el portal no muestra el texto de las notas');
ok(at_en_html_resumen_portal(['id' => $fx['detalle'], 'source' => 'prospect', 'detail_type' => 'entregable']) === '' && at_en_html_resumen_portal(['id' => 999999999, 'source' => 'client', 'detail_type' => 'entregable']) === '', 'otro origen o sin activar: nada');

// Endurecimiento: respuesta vacía, nonce malo, sin permiso, imagen adjunta y texto escapado
$n4 = at_en_agregar_nota($id, 'cliente', 'Cliente', '<script>alert(1)</script> Nota con etiqueta', [], '');
$antes = count(at_en_notas($id));
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n4, 'texto' => '   ']);
ok(en_query($r['redirect'])['en_msg'] === 'nota_vacia' && count(at_en_notas($id)) === $antes && at_en_sin_responder($id) === 1, 'respuesta vacía: rechazada y la nota sigue pendiente');
$r = en_correr('at_en_responder', $admin, 'otro_nonce', ['entregable_id' => $id, 'nota_id' => $n4, 'texto' => 'Hola']);
ok(count(at_en_notas($id)) === $antes, 'responder con nonce malo no guarda');
$r = en_correr('at_en_responder', (int) $sin_permiso, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n4, 'texto' => 'Hola']);
ok(count(at_en_notas($id)) === $antes, 'responder sin manage_options no guarda');
$r = en_correr('at_en_version_enviar', (int) $sin_permiso, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 1]);
ok($r['mails'] === [], 'enviar sin manage_options no manda correo');
$html = $pestana();
ok(strpos($html, '<script>alert(1)</script>') === false && strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'la nota con etiquetas se muestra escapada en el panel');
$tmp = sys_get_temp_dir() . '/en-p-' . $marca . '.png';
$im = imagecreatetruecolor(50, 40); imagepng($im, $tmp); imagedestroy($im);
$files = ['imagenes' => ['name' => ['a.png'], 'type' => ['image/png'], 'tmp_name' => [$tmp], 'error' => [0], 'size' => [filesize($tmp)]]];
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n4, 'texto' => 'Con imagen'], $files);
$ultima = end(at_en_notas($id));
$imgs = json_decode((string) $ultima->imagenes, true);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && count($imgs) === 1 && is_file(at_en_dir_imagenes($id) . $imgs[0]), 'respuesta con imagen guardada');
ok(strpos($pestana(), esc_url(at_en_url_imagen(home_url(), (string) at_en_por_id($id)->codigo, $imgs[0]))) !== false, 'la miniatura sale en la línea de tiempo');
@unlink($tmp);
fin();
