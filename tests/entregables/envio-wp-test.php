<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/envio-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_enviar_version', 'at_en_avisar_nota', 'at_en_avisar_respuesta', 'at_en_url_wa_version');
global $wpdb;
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', "Revisa:\n- la portada");
$e = at_en_por_id($id);

// Prueba a Luis: no marca ni registra
$GLOBALS['en_correos'] = [];
$r = at_en_enviar_version($id, 1, true);
$c = $GLOBALS['en_correos'][0] ?? [];
ok($r['ok'] && count($GLOBALS['en_correos']) === 1 && $c['to'] === at_cc_correo_avisos() && strpos($c['subject'], '[PRUEBA] ') === 0, 'prueba: a Luis con [PRUEBA]');
ok(at_en_version($id, 1)->enviado_correo_at === null, 'la prueba no marca la versión');

// Envío real
$GLOBALS['en_correos'] = [];
$r = at_en_enviar_version($id, 1, false);
$c = $GLOBALS['en_correos'][0] ?? [];
$cab = implode("\n", (array) ($c['headers'] ?? []));
ok($r['ok'] && $c['to'] === $marca . '@example.com' && $c['subject'] === 'Propuestas ' . $marca . ': versión 1 para revisar', 'al cliente con el asunto de la versión');
ok(strpos($cab, 'Reply-To: ' . at_cc_correo_avisos()) !== false && strpos($cab, 'Bcc: lgonzalez@automatizatech.cl') !== false && strpos($cab, 'From: Automatiza Tech <') !== false, 'cabeceras: From, Reply-To y copia a Luis');
ok(strpos($c['message'], at_en_url_pagina(home_url(), (string) $e->codigo)) !== false && strpos($c['message'], 'https://example.com/v1') !== false, 'correo con la página y la versión');
ok(at_en_version($id, 1)->enviado_correo_at !== null, 'versión marcada enviada');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'entregable_version'", $fx['crm'])) === 1, 'historial: versión enviada');
ok(at_en_enviar_version($id, 1, false)['motivo'] === 'ya_enviada', 'segundo envío de la misma versión: ya_enviada');
ok(at_en_enviar_version($id, 7, false)['motivo'] === 'sin_version', 'versión inexistente: sin_version');

// Falla de wp_mail
add_filter('pre_wp_mail', '__return_false', 0);
at_en_crear_version($id, 'https://example.com/v2', '');
$r2 = at_en_enviar_version($id, 2, false);
remove_filter('pre_wp_mail', '__return_false', 0);
ok(!$r2['ok'] && $r2['motivo'] === 'correo_fallo' && at_en_version($id, 2)->enviado_correo_at === null, 'si wp_mail falla: no se marca');

// Sin correo del cliente
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => ''], ['id' => $fx['crm']]);
ok(at_en_enviar_version($id, 2, false)['motivo'] === 'sin_correo', 'cliente sin correo: sin_correo');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => $marca . '@example.com'], ['id' => $fx['crm']]);

// Aviso de nota a Luis
$n = at_en_agregar_nota($id, 'cliente', 'Cliente', "Me gusta <b>\nla portada", ['abcdefghijklmnopqrstuvwx.jpg'], '');
$GLOBALS['en_correos'] = [];
ok(at_en_avisar_nota($n) && $GLOBALS['en_correos'][0]['to'] === at_cc_correo_avisos() && strpos($GLOBALS['en_correos'][0]['message'], 'Me gusta &lt;b&gt;') !== false, 'aviso de nota a Luis, escapado');
ok(strpos($GLOBALS['en_correos'][0]['message'], 'page=automatiza-crm-ficha') !== false && strpos($GLOBALS['en_correos'][0]['message'], '#tab-entregables') !== false, 'botón a la ficha');

// Aviso de respuesta al cliente
$resp = at_en_agregar_nota($id, 'at', 'Luis', 'Ya la cambié', [], '');
$GLOBALS['en_correos'] = [];
ok(at_en_avisar_respuesta($resp) && $GLOBALS['en_correos'][0]['to'] === $marca . '@example.com' && $GLOBALS['en_correos'][0]['subject'] === 'Respuesta a tu nota sobre Propuestas ' . $marca, 'aviso de respuesta al cliente');
ok(!at_en_avisar_respuesta($n), 'una nota del cliente no se avisa como respuesta');

// WhatsApp
$url = at_en_url_wa_version($e, 1);
ok(strpos($url, 'https://wa.me/56911111111?text=') === 0 && strpos(rawurldecode($url), 'versión 1 de Propuestas ' . $marca) !== false, 'wa.me de la versión al teléfono de la ficha');
ok(strpos(at_en_texto_wa_respuesta($e, 1), 'te respondí tu nota sobre la versión 1') !== false, 'texto de WhatsApp de la respuesta');
fin();
