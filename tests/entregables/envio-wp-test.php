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
$cabp = implode("
", (array) ($c['headers'] ?? []));
ok(stripos($cabp, 'Bcc:') === false && stripos($cabp, 'Reply-To:') === false, 'la prueba no lleva Bcc ni Reply-To');

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
$GLOBALS['en_correos'] = [];
ok(at_en_enviar_version($id, 1, false)['motivo'] === 'ya_enviada' && count($GLOBALS['en_correos']) === 0, 'segundo envío de la misma versión: ya_enviada y ningún correo');
ok(at_en_enviar_version($id, 7, false)['motivo'] === 'sin_version', 'versión inexistente: sin_version');

// Falla de wp_mail
add_filter('pre_wp_mail', '__return_false', 0);
at_en_crear_version($id, 'https://example.com/v2', '');
$r2 = at_en_enviar_version($id, 2, false);
remove_filter('pre_wp_mail', '__return_false', 0);
ok(!$r2['ok'] && $r2['motivo'] === 'correo_fallo' && at_en_version($id, 2)->enviado_correo_at === null, 'si wp_mail falla: no se marca');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}crm_historial WHERE cliente_id = %d AND tipo_evento = 'entregable_version'", $fx['crm'])) === 1, 'correo_fallo no escribe historial');
ok(at_en_reclamar_envio_correo($id, 2) === true && at_en_reclamar_envio_correo($id, 2) === false, 'reclamar: la primera vez sí, la segunda no');
at_en_soltar_envio_correo($id, 2);
ok(at_en_version($id, 2)->enviado_correo_at === null && at_en_reclamar_envio_correo($id, 2) === true, 'soltar libera el cupo y se puede reclamar de nuevo');
at_en_soltar_envio_correo($id, 2);

// Carrera: otro clic ya reclamó la versión 3
at_en_crear_version($id, 'https://example.com/v3', '');
ok(at_en_reclamar_envio_correo($id, 3) === true, 'v3 reclamada por el otro clic');
$GLOBALS['en_correos'] = [];
$rc = at_en_enviar_version($id, 3, false);
ok(!$rc['ok'] && $rc['motivo'] === 'ya_enviada' && count($GLOBALS['en_correos']) === 0, 'carrera: ya_enviada y ningún correo');
at_en_soltar_envio_correo($id, 3);

// Sin correo del cliente
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => ''], ['id' => $fx['crm']]);
ok(at_en_enviar_version($id, 2, false)['motivo'] === 'sin_correo', 'cliente sin correo: sin_correo');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => $marca . '@example.com'], ['id' => $fx['crm']]);

// Aviso de nota a Luis
$n = at_en_agregar_nota($id, 'cliente', 'Cliente', "Me gusta <b>\nla portada", ['abcdefghijklmnopqrstuvwx.jpg'], '');
$GLOBALS['en_correos'] = [];
ok(at_en_avisar_nota($n) && $GLOBALS['en_correos'][0]['to'] === at_cc_correo_avisos() && strpos($GLOBALS['en_correos'][0]['message'], 'Me gusta &lt;b&gt;') !== false, 'aviso de nota a Luis, escapado');
ok((int) at_en_nota($n)->aviso_ok === 1, 'aviso_ok queda en 1 si el correo sale');
ok(strpos($GLOBALS['en_correos'][0]['message'], 'page=automatiza-crm-ficha') !== false && strpos($GLOBALS['en_correos'][0]['message'], '#tab-entregables') !== false, 'botón a la ficha');

// Aviso de nota con wp_mail caído
$n2 = at_en_agregar_nota($id, 'cliente', 'Cliente', 'Otra nota', [], '');
add_filter('pre_wp_mail', '__return_false', 0);
$av = at_en_avisar_nota($n2);
remove_filter('pre_wp_mail', '__return_false', 0);
ok(!$av && (int) at_en_nota($n2)->aviso_ok === 0, 'si wp_mail falla: aviso_ok queda en 0');

// Aviso de respuesta al cliente
$resp = at_en_agregar_nota($id, 'at', 'Luis', 'Ya la cambié', [], '');
$GLOBALS['en_correos'] = [];
ok(at_en_avisar_respuesta($resp) && $GLOBALS['en_correos'][0]['to'] === $marca . '@example.com' && $GLOBALS['en_correos'][0]['subject'] === 'Respuesta a tu nota sobre Propuestas ' . $marca, 'aviso de respuesta al cliente');
ok(!at_en_avisar_respuesta($n), 'una nota del cliente no se avisa como respuesta');

// Respuesta sin correo del cliente
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => ''], ['id' => $fx['crm']]);
$GLOBALS['en_correos'] = [];
ok(!at_en_avisar_respuesta($resp) && count($GLOBALS['en_correos']) === 0, 'respuesta sin correo del cliente: false y sin correo');
$wpdb->update($wpdb->prefix . 'crm_clientes', ['email' => $marca . '@example.com'], ['id' => $fx['crm']]);

// Título con saltos de línea
$wpdb->update($wpdb->prefix . 'automatiza_clients_details', ['title' => "Propuestas
  {$marca}
"], ['id' => $fx['detalle']]);
ok(at_en_cliente($e)['titulo'] === 'Propuestas ' . $marca, 'título sin saltos de línea ni espacios dobles');

// WhatsApp
$url = at_en_url_wa_version($e, 1);
ok(strpos($url, 'https://wa.me/56911111111?text=') === 0 && strpos(rawurldecode($url), 'versión 1 de Propuestas ' . $marca) !== false, 'wa.me de la versión al teléfono de la ficha');
ok(strpos(at_en_texto_wa_respuesta($e, 1), 'te respondí tu nota sobre la versión 1') !== false, 'texto de WhatsApp de la respuesta');
fin();
