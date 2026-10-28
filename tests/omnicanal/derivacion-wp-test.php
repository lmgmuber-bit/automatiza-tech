<?php
// Asistente de directos de Instagram: pruebas del Portal con el WordPress LOCAL (contexto del comentario,
// acción escalate, descarte en Instagram humano y vuelta al bot). Correo y HTTP bloqueados y capturados.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/omnicanal/derivacion-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
if (class_exists('OmnichannelController', false)) {
	echo "FALLA el controller ya estaba cargado: ajusta el arranque\n\n1 FALLAS\n";
	exit(1);
}
require_once __DIR__ . '/../../omnichannel-controller.php';
global $wpdb;
$P = $wpdb->prefix . 'omnichannel_';
$ctl = new OmnichannelController();
$SECRET = defined('OMNI_ADMIN_SECRET') ? OMNI_ADMIN_SECRET : 'omni_default_secret';
$tok = static fn($conv, $canal) => hash_hmac('sha256', $conv . ':' . $canal, $SECRET);
$N8N = 'https://n8n.prueba.invalid/webhook/ig-prueba';

// --- Captura de correo y HTTP (corren antes que los bloqueos del arranque)
$GLOBALS['correos'] = [];
$GLOBALS['http'] = [];
add_filter('pre_wp_mail', static function ($pre, $atts) { $GLOBALS['correos'][] = $atts; return $pre; }, 1, 2);
add_filter('pre_http_request', static function ($pre, $args, $url) {
	$GLOBALS['http'][] = ['url' => $url, 'body' => isset($args['body']) ? json_decode($args['body'], true) : null];
	return $pre;
}, 1, 3);
$a_n8n = static fn() => array_values(array_filter($GLOBALS['http'], static fn($h) => $h['url'] === $N8N));
$limpiar = static function () { $GLOBALS['correos'] = []; $GLOBALS['http'] = []; };

// --- Datos de prueba: cliente 0, canal IG, canal WA, ejecutivo, configuración
$ahora = current_time('mysql');
$wpdb->insert($P . 'channels', ['client_id' => 0, 'channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG directos',
	'is_active' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
$IG = (int) $wpdb->insert_id;
$wpdb->insert($P . 'channels', ['client_id' => 0, 'channel_type' => 'whatsapp', 'channel_name' => '[PRUEBA] WA directos',
	'is_active' => 1, 'created_at' => $ahora, 'updated_at' => $ahora]);
$WA = (int) $wpdb->insert_id;
$wpdb->insert($P . 'agents', ['client_id' => 0, 'name' => '[PRUEBA] Luis', 'email' => 'prueba-luis@ejemplo.invalid',
	'role' => 'supervisor', 'status' => 'active', 'max_concurrent_chats' => 50, 'active_chats' => 0]);
$AG = (int) $wpdb->insert_id;
$wpdb->insert($P . 'agents', ['client_id' => 0, 'name' => '[PRUEBA] Ejecutiva', 'email' => 'prueba-ejecutiva@ejemplo.invalid',
	'role' => 'agent', 'status' => 'active', 'max_concurrent_chats' => 50, 'active_chats' => 0]);
$AG2 = (int) $wpdb->insert_id;
$cfg = static fn($agente) => wp_json_encode(['palabras_clave' => ['activo' => false],
	'derivacion' => ['agente_id' => $agente, 'correos' => ['prueba-a@ejemplo.invalid', 'prueba-b@ejemplo.invalid']]]);
$wpdb->insert($P . 'bot_configs', ['client_id' => 0, 'channel_id' => $IG, 'bot_name' => '[PRUEBA]', 'is_active' => 1,
	'n8n_webhook_url' => $N8N, 'custom_functions_json' => $cfg($AG)]);
$BC = (int) $wpdb->insert_id;
$wpdb->insert($P . 'bot_configs', ['client_id' => 0, 'channel_id' => $WA, 'bot_name' => '[PRUEBA]', 'is_active' => 1, 'n8n_webhook_url' => '']);
$BC2 = (int) $wpdb->insert_id;
ok($IG && $WA && $AG && $AG2 && $BC && $BC2, 'datos de prueba creados');

register_shutdown_function(static function () use ($wpdb, $P, $IG, $WA, $AG, $AG2, $BC, $BC2) {
	$conv = $wpdb->get_col("SELECT id FROM {$P}conversations WHERE channel_id IN ($IG, $WA)");
	if ($conv) {
		$ids = implode(',', array_map('intval', $conv));
		$wpdb->query("DELETE FROM {$P}messages WHERE conversation_id IN ($ids)");
		$wpdb->query("DELETE FROM {$P}takeovers WHERE conversation_id IN ($ids)");
		$wpdb->query("DELETE FROM {$P}audit_log WHERE entity_type = 'conversation' AND entity_id IN ($ids)");
	}
	$wpdb->query("DELETE FROM {$P}conversations WHERE channel_id IN ($IG, $WA)");
	$wpdb->query("DELETE FROM {$P}bot_configs WHERE id IN ($BC, $BC2)");
	$wpdb->query("DELETE FROM {$P}agents WHERE id IN ($AG, $AG2)");
	$wpdb->query("DELETE FROM {$P}channels WHERE id IN ($IG, $WA)");
});

$gmt = static fn($seg) => gmdate('Y-m-d H:i:s', time() - $seg);
$conv_nueva = static function ($canal, $tipo, $igsid, array $extra = []) use ($wpdb, $P, $gmt) {
	$wpdb->insert($P . 'conversations', array_merge(['client_id' => 0, 'channel_id' => $canal, 'external_contact_id' => $igsid,
		'contact_name' => 'Ana Prueba', 'channel_type' => $tipo, 'status' => 'bot', 'last_message_at' => $gmt(60),
		'contact_phone' => $tipo === 'whatsapp' ? '+56900000000' : ''], $extra));
	return (int) $wpdb->insert_id;
};
$fila = static fn($id) => $wpdb->get_row($wpdb->prepare("SELECT * FROM {$P}conversations WHERE id = %d", $id));
$msgs = static fn($id) => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$P}messages WHERE conversation_id = %d ORDER BY id", $id));
$activos = static fn($ag) => (int) $wpdb->get_var($wpdb->prepare("SELECT active_chats FROM {$P}agents WHERE id = %d", $ag));
$recibir = static fn($canal, $igsid, $texto) => $ctl->receive_message($canal, ['external_contact_id' => $igsid,
	'contact_name' => 'Ana Prueba', 'content' => $texto, 'type' => 'text']);

// ============ 1. Contexto de quien llega desde un comentario ============
$igsid = 'igsid-prueba-' . bin2hex(random_bytes(4));
$c1 = $conv_nueva($IG, 'instagram', $igsid);
$meta = wp_json_encode(['origen' => 'comentario_palabra_clave', 'comment_id' => 'c1', 'media_id' => 'm1', 'keyword' => 'MÉTODO'], JSON_UNESCAPED_UNICODE);
$wpdb->insert($P . 'messages', ['conversation_id' => $c1, 'channel_type' => 'instagram', 'direction' => 'inbound', 'sender_type' => 'system',
	'sender_name' => 'Sistema', 'message_type' => 'text', 'content' => '💬 Comentó «MÉTODO»', 'metadata_json' => $meta, 'created_at' => $gmt(120)]);
$wpdb->insert($P . 'messages', ['conversation_id' => $c1, 'channel_type' => 'instagram', 'direction' => 'outbound', 'sender_type' => 'bot',
	'sender_name' => 'Respuesta automática', 'message_type' => 'text', 'content' => 'Así trabajamos. Agenda tu diagnóstico gratis',
	'metadata_json' => $meta, 'created_at' => $gmt(120)]);
$limpiar();
$r = $recibir($IG, $igsid, 'quiero agendar');
$env = $a_n8n();
ok(count($env) === 1, 'contexto: el mensaje se reenvía a n8n');
$ctx = $env[0]['body']['contexto'] ?? null;
ok(($ctx['origen'] ?? '') === 'comentario_palabra_clave' && ($ctx['palabra'] ?? '') === 'MÉTODO', 'contexto: origen y palabra');
ok(($ctx['mensaje_privado'] ?? '') === 'Así trabajamos. Agenda tu diagnóstico gratis', 'contexto: el privado enviado');
ok(($env[0]['body']['message']['content'] ?? '') === 'quiero agendar', 'contexto: el mensaje de la persona sigue igual');

$igsid2 = 'igsid-prueba-' . bin2hex(random_bytes(4));
$conv_nueva($IG, 'instagram', $igsid2);
$limpiar();
$recibir($IG, $igsid2, 'hola');
$env = $a_n8n();
ok(count($env) === 1 && !array_key_exists('contexto', $env[0]['body']), 'contexto: sin comentario, no va el bloque');

// ============ 2. escalate ============
$c2 = $conv_nueva($IG, 'instagram', 'igsid-esc-' . bin2hex(random_bytes(4)));
$wpdb->insert($P . 'messages', ['conversation_id' => $c2, 'channel_type' => 'instagram', 'direction' => 'inbound', 'sender_type' => 'contact',
	'sender_name' => 'Ana Prueba', 'message_type' => 'text', 'content' => 'quiero hablar con alguien', 'created_at' => $gmt(30)]);
$antes = $fila($c2);
$limpiar();
$r = $ctl->handle_n8n_callback(['conversation_id' => $c2, 'channel_id' => $IG, 'callback_token' => 'malo', 'action' => 'escalate', 'motivo' => 'x']);
ok(isset($r['error']) && $fila($c2)->status === $antes->status && count($GLOBALS['correos']) === 0, 'escalate: token inválido se rechaza y no cambia nada');

$activos_antes = $activos($AG);
$r = $ctl->handle_n8n_callback(['conversation_id' => $c2, 'channel_id' => $IG, 'callback_token' => $tok($c2, $IG), 'action' => 'escalate',
	'motivo' => 'Quiere hablar con una persona sobre precios', 'content' => 'Le avisé a Luis, te va a responder por aquí mismo.']);
$f = $fila($c2);
ok(!empty($r['derivada']) && (int) $r['agente_id'] === $AG && (int) $r['correos'] === 2, 'escalate: responde derivada, ejecutivo y 2 correos');
ok($f->intervention_mode === 'human' && $f->status === 'assigned' && (int) $f->assigned_agent_id === $AG, 'escalate: humana y asignada al ejecutivo de la config');
ok($activos($AG) === $activos_antes + 1, 'escalate: suma un chat activo al ejecutivo');
$m = $msgs($c2);
$desp = array_values(array_filter($m, static fn($x) => $x->sender_type === 'bot' && $x->content === 'Le avisé a Luis, te va a responder por aquí mismo.'));
$nota = array_values(array_filter($m, static fn($x) => $x->sender_type === 'system' && strpos($x->content, 'El asistente derivó') !== false));
ok(count($desp) === 1, 'escalate: la despedida se guarda como mensaje del bot');
ok(count($nota) === 1 && strpos($nota[0]->content, 'precios') !== false, 'escalate: nota de sistema con el motivo');
ok($desp && $nota && (int) $desp[0]->id < (int) $nota[0]->id, 'escalate: la despedida va antes de la derivación');
$ig_envios = array_values(array_filter($GLOBALS['http'], static fn($h) => strpos($h['url'], 'graph.instagram.com') !== false || strpos($h['url'], 'graph.facebook.com') !== false));
$asuntos = array_map(static fn($c) => $c['subject'], $GLOBALS['correos']);
$para = array_map(static fn($c) => is_array($c['to']) ? implode(',', $c['to']) : $c['to'], $GLOBALS['correos']);
ok(count($GLOBALS['correos']) === 2 && $asuntos === ['Instagram: Ana Prueba quiere hablar contigo', 'Instagram: Ana Prueba quiere hablar contigo'],
	'escalate: 2 correos con el asunto exacto y ningún otro (sin el correo genérico de asignación)');
ok(in_array('prueba-a@ejemplo.invalid', $para, true) && in_array('prueba-b@ejemplo.invalid', $para, true), 'escalate: a los correos de la config');
$ct = implode("\n", (array) ($GLOBALS['correos'][0]['headers'] ?? []));
ok(stripos($ct, 'text/html') !== false, 'escalate: correo en HTML');
ok(strpos($GLOBALS['correos'][0]['message'], 'quiero hablar con alguien') !== false, 'escalate: el correo trae los últimos mensajes');
ok(count($a_n8n()) === 0, 'escalate: no llama a n8n');

$limpiar();
$r = $ctl->handle_n8n_callback(['conversation_id' => $c2, 'channel_id' => $IG, 'callback_token' => $tok($c2, $IG), 'action' => 'escalate', 'motivo' => 'otra vez']);
ok(!empty($r['ya_derivada']) && count($GLOBALS['correos']) === 0, 'escalate: la segunda vez no repite el correo');
ok($activos($AG) === $activos_antes + 1, 'escalate: la segunda vez no suma otro chat');

// Sin agente en la config: queda abierta y sin asignar
$wpdb->update($P . 'bot_configs', ['custom_functions_json' => $cfg(0)], ['id' => $BC]);
$c3 = $conv_nueva($IG, 'instagram', 'igsid-sin-' . bin2hex(random_bytes(4)));
$limpiar();
$r = $ctl->handle_n8n_callback(['conversation_id' => $c3, 'channel_id' => $IG, 'callback_token' => $tok($c3, $IG), 'action' => 'escalate', 'motivo' => 'm']);
$f = $fila($c3);
ok($f->status === 'open' && $f->intervention_mode === 'human' && $f->assigned_agent_id === null && count($GLOBALS['correos']) === 2,
	'escalate sin ejecutivo: abierta, humana, sin asignar y con los 2 correos');

// Ejecutivo lleno: cae a abierta sin asignar
$wpdb->update($P . 'bot_configs', ['custom_functions_json' => $cfg($AG2)], ['id' => $BC]);
$wpdb->update($P . 'agents', ['max_concurrent_chats' => 1, 'active_chats' => 1], ['id' => $AG2]);
$c4 = $conv_nueva($IG, 'instagram', 'igsid-lleno-' . bin2hex(random_bytes(4)));
$limpiar();
$r = $ctl->handle_n8n_callback(['conversation_id' => $c4, 'channel_id' => $IG, 'callback_token' => $tok($c4, $IG), 'action' => 'escalate', 'motivo' => 'm']);
$f = $fila($c4);
ok($f->status === 'open' && $f->intervention_mode === 'human' && $f->assigned_agent_id === null && count($GLOBALS['correos']) === 2,
	'escalate con el ejecutivo lleno: abierta sin asignar, igual avisa');
$wpdb->update($P . 'agents', ['max_concurrent_chats' => 50, 'active_chats' => 0], ['id' => $AG2]);
$wpdb->update($P . 'bot_configs', ['custom_functions_json' => $cfg($AG)], ['id' => $BC]);

// ============ 3. Descarte de respuestas en Instagram humano ============
$n_antes = count($msgs($c2));
$r = $ctl->handle_n8n_callback(['conversation_id' => $c2, 'channel_id' => $IG, 'callback_token' => $tok($c2, $IG), 'content' => 'respuesta tardía']);
ok(($r['ignorada'] ?? '') === 'conversacion_humana' && count($msgs($c2)) === $n_antes, 'descarte: texto en Instagram humano no se guarda ni se envía');

$cw = $conv_nueva($WA, 'whatsapp', '56900000000', ['status' => 'assigned', 'intervention_mode' => 'human']);
$r = $ctl->handle_n8n_callback(['conversation_id' => $cw, 'channel_id' => $WA, 'callback_token' => $tok($cw, $WA), 'content' => 'respuesta en WhatsApp']);
$mw = array_values(array_filter($msgs($cw), static fn($x) => $x->content === 'respuesta en WhatsApp'));
ok(!isset($r['ignorada']) && count($mw) === 1, 'descarte: WhatsApp humano sigue como antes (se guarda)');

// ============ 4. Vuelta al bot tras 24 h (solo derivadas por el bot) ============
$wpdb->update($P . 'conversations', ['last_message_at' => $gmt(25 * 3600)], ['id' => $c2]);
$activos_antes = $activos($AG);
$limpiar();
$recibir($IG, $fila($c2)->external_contact_id, 'hola de nuevo');
$f = $fila($c2);
ok($f->status === 'bot' && $f->intervention_mode === '' && $f->assigned_agent_id === null, 'vuelta: derivada por el bot y 25 h después vuelve al bot');
ok(count($a_n8n()) === 1, 'vuelta: el mensaje se reenvía a n8n');
ok($activos($AG) === max(0, $activos_antes - 1), 'vuelta: descuenta el chat activo del ejecutivo');
ok((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}takeovers WHERE conversation_id = %d AND status = 'active'", $c2)) === 0, 'vuelta: libera el takeover');

// derivada por el bot, 2 h
$c5 = $conv_nueva($IG, 'instagram', 'igsid-2h-' . bin2hex(random_bytes(4)));
$ctl->handle_n8n_callback(['conversation_id' => $c5, 'channel_id' => $IG, 'callback_token' => $tok($c5, $IG), 'action' => 'escalate', 'motivo' => 'm']);
$wpdb->update($P . 'conversations', ['last_message_at' => $gmt(2 * 3600)], ['id' => $c5]);
$limpiar();
$recibir($IG, $fila($c5)->external_contact_id, 'sigo aquí');
ok($fila($c5)->intervention_mode === 'human' && count($a_n8n()) === 0, 'vuelta: a las 2 h sigue humana y no va a n8n');

// derivada sin ejecutivo (abierta), 25 h
$wpdb->update($P . 'conversations', ['last_message_at' => $gmt(25 * 3600)], ['id' => $c3]);
$limpiar();
$recibir($IG, $fila($c3)->external_contact_id, 'hola');
ok($fila($c3)->status === 'bot' && count($a_n8n()) === 1, 'vuelta: la derivada sin ejecutivo también vuelve');

// tomada a mano por un ejecutivo, 25 h
$c6 = $conv_nueva($IG, 'instagram', 'igsid-mano-' . bin2hex(random_bytes(4)));
$ctl->takeover_conversation($c6, $AG2, 'Lo tomo yo');
$wpdb->update($P . 'conversations', ['last_message_at' => $gmt(25 * 3600)], ['id' => $c6]);
$limpiar();
$recibir($IG, $fila($c6)->external_contact_id, 'hola');
ok($fila($c6)->intervention_mode === 'human' && (int) $fila($c6)->assigned_agent_id === $AG2 && count($a_n8n()) === 0,
	'vuelta: la que tomó un ejecutivo a mano no vuelve sola');

// ============ 4b. Cuentas sin bot (pedido de Luis, 28-oct: su cuenta personal le manda reels a la de AT)
$igsid_sb = 'igsid-sinbot-' . bin2hex(random_bytes(4));
$cfg_sb = json_decode($cfg($AG), true);
$cfg_sb['sin_bot'] = [['igsid' => $igsid_sb, 'nota' => 'cuenta personal']];
$wpdb->update($P . 'bot_configs', ['custom_functions_json' => wp_json_encode($cfg_sb)], ['id' => $BC]);
$limpiar();
$r = $ctl->receive_message($IG, ['external_contact_id' => $igsid_sb, 'contact_name' => 'Luis Personal', 'content' => 'mira este reel', 'type' => 'text']);
$csb = (int) ($r['conversation_id'] ?? 0);
ok($csb > 0 && count($msgs($csb)) === 1, 'sin bot: el mensaje se guarda y se ve en el Portal');
ok(count($a_n8n()) === 0, 'sin bot: conversación nueva en modo bot, pero no va a n8n');
$limpiar();
$recibir($IG, 'igsid-otro-' . bin2hex(random_bytes(4)), 'hola');
ok(count($a_n8n()) === 1, 'sin bot: las demás cuentas sí van a n8n');
$wpdb->update($P . 'bot_configs', ['custom_functions_json' => $cfg($AG)], ['id' => $BC]);

// ============ 5. Contacto nuevo con foto de perfil larga (caso real del 28-oct: 523 caracteres, la columna acepta 500) ============
$foto = 'https://scontent.cdninstagram.com/v/t51.2885-19/' . str_repeat('a', 480) . '.jpg?stp=dst-jpg';
$igsid_f = 'igsid-foto-' . bin2hex(random_bytes(4));
$limpiar();
$r = $ctl->receive_message($IG, ['external_contact_id' => $igsid_f, 'contact_name' => 'Ana Foto', 'contact_avatar_url' => $foto,
	'content' => 'hola', 'type' => 'text']);
$cf = (int) ($r['conversation_id'] ?? 0);
$ff = $cf ? $fila($cf) : null;
ok($cf > 0 && $ff && $ff->external_contact_id === $igsid_f, 'foto larga: la conversación se crea igual');
ok($ff && $ff->contact_avatar_url === '', 'foto larga: queda sin foto (cortarla rompería la dirección firmada)');
$env = $a_n8n();
ok(count($env) === 1 && (int) ($env[0]['body']['conversation_id'] ?? 0) === $cf, 'foto larga: a n8n llega la conversación real, no 0');
$igsid_roto = 'igsid-' . bin2hex(random_bytes(4)) . str_repeat('x', 300);
$huerfanos = static fn() => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}messages WHERE conversation_id = 0 AND sender_id IN (%s, %s)",
	$igsid_f, mb_substr($igsid_roto, 0, 255)));
register_shutdown_function(static function () use ($wpdb, $P, $igsid_f, $igsid_roto) {   // por si falla: no dejar huérfanos de prueba
	$wpdb->query($wpdb->prepare("DELETE FROM {$P}messages WHERE conversation_id = 0 AND sender_id IN (%s, %s)", $igsid_f, mb_substr($igsid_roto, 0, 255)));
});
ok($huerfanos() === 0, 'foto larga: ningún mensaje queda sin conversación');

// Conversación que existía sin foto: la foto larga no debe tumbar la actualización
$wpdb->update($P . 'conversations', ['last_message_at' => $gmt(3600)], ['id' => $cf]);
$limpiar();
$r = $ctl->receive_message($IG, ['external_contact_id' => $igsid_f, 'contact_name' => 'Ana Foto', 'contact_avatar_url' => $foto,
	'content' => 'sigo', 'type' => 'text']);
ok((int) ($r['conversation_id'] ?? 0) === $cf && strtotime($fila($cf)->last_message_at . ' UTC') > time() - 120, 'foto larga en una conversación existente: se actualiza igual');

// Si la conversación no se puede crear, no se guarda un mensaje huérfano ni se avisa a n8n
$limpiar();
$r = $ctl->receive_message($IG, ['external_contact_id' => $igsid_roto, 'contact_name' => 'Ana Rota',
	'content' => 'hola', 'type' => 'text']);
ok(isset($r['error']) && count($a_n8n()) === 0 && $huerfanos() === 0, 'conversación imposible: error, sin huérfanos y sin aviso a n8n');

fin();
