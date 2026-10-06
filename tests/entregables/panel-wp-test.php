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
$nver = count(at_en_versiones($id));
$r = en_correr('at_en_version_crear', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'url' => 'https://example.com/v9', 'mensaje' => 'Míralo en https://algo.EasyPanel.host/x']);
ok(en_query($r['redirect'])['en_msg'] === 'mensaje_easypanel' && count(at_en_versiones($id)) === $nver, 'easypanel en el mensaje: mensaje_easypanel y no se crea la versión');
$_GET['en_msg'] = 'mensaje_easypanel';
ok(strpos($pestana(), 'El mensaje tiene un enlace de easypanel: Hostinger rechaza esos correos. Usa el enlace de automatizatech.cl (ver-presentacion.php).') !== false, 'la pestaña explica el problema del enlace de easypanel');
unset($_GET['en_msg']);
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
$todas = at_en_notas($id);
$ultima = end($todas);
$imgs = json_decode((string) $ultima->imagenes, true);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok' && count($imgs) === 1 && is_file(at_en_dir_imagenes($id) . $imgs[0]), 'respuesta con imagen guardada');
ok(strpos($pestana(), esc_url(at_en_url_imagen(home_url(), (string) at_en_por_id($id)->codigo, $imgs[0]))) !== false, 'la miniatura sale en la línea de tiempo');
@unlink($tmp);
// Respuesta con una imagen falsa: el error dice cuál y la respuesta no queda guardada ni marcada
file_put_contents($tmp . '.jpg', '<?php echo 1;');
$n9 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Respuesta con foto mala', [], '');
$antes9 = count(at_en_notas($id));
$files9 = ['imagenes' => ['name' => ['x.jpg'], 'type' => ['image/jpeg'], 'tmp_name' => [$tmp . '.jpg'], 'error' => [0], 'size' => [12]]];
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n9, 'texto' => 'Con imagen mala'], $files9);
$q9 = en_query($r['redirect']);
ok(($q9['en_msg'] ?? '') === 'img_tipo' && ($q9['en_img'] ?? '') === '1' && count(at_en_notas($id)) === $antes9 && (int) at_en_nota($n9)->respondida === 0, 'respuesta con imagen falsa: img_tipo en la imagen 1 y la nota sigue pendiente');
$_GET['en_msg'] = 'img_tipo';
$_GET['en_img'] = '1';
ok(strpos($pestana(), 'La imagen 1 no es JPG ni PNG.') !== false, 'la pestaña muestra «La imagen 1 no es JPG ni PNG.»');
unset($_GET['en_msg'], $_GET['en_img']);
at_en_marcar_respondida($n9);
@unlink($tmp . '.jpg');

// Ronda de correcciones: mensajes de envío, respuesta sin duplicados, aviso sin correo
$r = en_correr('at_en_version_enviar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => 99]);
ok(en_query($r['redirect'])['en_msg'] === 'sin_version' && $r['mails'] === [], 'enviar una versión que no existe: sin_version');
$_GET['en_msg'] = 'sin_version';
ok(strpos($pestana(), 'Esa versión no existe.') !== false, 'la pestaña muestra el aviso de sin_version');
$_GET['en_msg'] = 'sin_entregable';
ok(strpos($pestana(), 'El entregable ya no existe.') !== false, 'la pestaña muestra el aviso de sin_entregable');
unset($_GET['en_msg']);

$n5 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Doble clic', [], '');
$antes = count(at_en_notas($id));
$r1 = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n5, 'texto' => 'Respuesta única', 'avisar' => '1']);
$r2 = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n5, 'texto' => 'Respuesta única', 'avisar' => '1']);
ok(en_query($r1['redirect'])['en_msg'] === 'respuesta_ok' && en_query($r2['redirect'])['en_msg'] === 'ya_respondida', 'responder dos veces: la segunda es ya_respondida');
ok(count(at_en_notas($id)) === $antes + 1 && count($r1['mails']) + count($r2['mails']) === 1, 'una sola respuesta de AT y un solo correo al cliente');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => 'sin-correo'], ['id' => $fx['crm']]);
$n6 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Sin correo', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n6, 'texto' => 'Te escribo por WhatsApp', 'avisar' => '1']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_sin_aviso' && $r['mails'] === [] && (int) at_en_nota($n6)->respondida === 1, 'Avisar sin correo válido: respuesta guardada y aviso de que no se avisó');
$n7 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Sin avisar', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n7, 'texto' => 'Solo guardar']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_ok', 'sin «Avisar» y sin correo: respuesta_ok');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => $marca . '@example.com'], ['id' => $fx['crm']]);
$n8 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Respuesta con falla', [], '');
at_en_marcar_respondida($n8);
ok(at_en_reclamar_respuesta($n8) === false, 'reclamar una nota ya respondida: false');
at_en_soltar_respuesta($n8);
ok(at_en_reclamar_respuesta($n8) === true && at_en_reclamar_respuesta($n8) === false, 'soltar la reclamación permite reclamar una sola vez');

// Texto del aviso sin correo y motivo real de una falla de envío
$_GET['en_msg'] = 'respuesta_sin_aviso';
$ps = $pestana();
ok(strpos($ps, 'Respuesta guardada, pero no se pudo avisar al cliente por correo (sin correo válido o falla del envío): avísale por WhatsApp.') !== false, 'respuesta_sin_aviso: texto que cubre sin correo y falla del envío');
unset($_GET['en_msg']);
// wp_mail falla (con wp_mail_failed): el motivo se guarda 60 s y se ve, escapado, junto al aviso
$n10 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Aviso que falla', [], '');
$r = en_correr('at_en_responder', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'nota_id' => $n10, 'texto' => 'Respuesta con aviso roto', 'avisar' => '1'], [], ['EN_MAIL_FALLA' => '1']);
ok(en_query($r['redirect'])['en_msg'] === 'respuesta_sin_aviso', 'el aviso al cliente falla: respuesta_sin_aviso');
wp_set_current_user($admin);
ok(at_en_motivo_fallo($id) === 'SMTP simulado: no se pudo autenticar x', 'el motivo de wp_mail_failed quedó guardado (sin etiquetas)');
$_GET['en_msg'] = 'respuesta_sin_aviso';
$_GET['en'] = $id;
ok(strpos($pestana(), 'Motivo: SMTP simulado: no se pudo autenticar x') !== false, 'la pestaña muestra el motivo guardado');
// Correo de la versión que falla: correo_fallo + motivo
$vn = at_en_crear_version($id, 'https://example.com/vf', '');
delete_transient('at_en_fallo_' . $admin . '_' . $id);
$r = en_correr('at_en_version_enviar', $admin, 'at_en_' . $id, ['entregable_id' => $id, 'numero' => $vn], [], ['EN_MAIL_FALLA' => '1']);
ok(en_query($r['redirect'])['en_msg'] === 'correo_fallo' && at_en_version($id, $vn)->enviado_correo_at === null && at_en_motivo_fallo($id) !== '', 'enviar la versión con el SMTP caído: correo_fallo, no se marca y queda el motivo');
delete_transient('at_en_fallo_' . $admin . '_' . $id);
ok(at_en_motivo_fallo($id) === '', 'sin motivo guardado: vacío');
at_en_guardar_motivo_fallo($id, '<script>alert(1)</script> falla');
$_GET['en_msg'] = 'correo_fallo';
$pf = $pestana();
ok(strpos($pf, '<script>alert(1)</script>') === false && strpos($pf, 'Motivo: falla') !== false, 'el motivo se muestra como texto, sin etiquetas ni scripts');
unset($_GET['en_msg'], $_GET['en']);
delete_transient('at_en_fallo_' . $admin . '_' . $id);
// El motivo es de quien envió: otro usuario no lo ve
at_en_guardar_motivo_fallo($id, 'solo para quien envió');
wp_set_current_user((int) $sin_permiso);
ok(at_en_motivo_fallo($id) === '', 'el motivo no lo ve otro usuario');
wp_set_current_user($admin);
delete_transient('at_en_fallo_' . $admin . '_' . $id);
fin();
