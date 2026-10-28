<?php
// Asistente de directos de Instagram: funciones puras del Portal (sin WordPress, sin base, sin red). Datos ficticios.
// Correr: php tests/omnicanal/derivacion-puras-test.php
if (PHP_SAPI !== 'cli') { exit; }
foreach ([__DIR__ . '/../../omnichannel-derivacion.php', __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/lead-origen.php'] as $archivo) {
	if (is_file($archivo)) { require_once $archivo; }
}
$total = 0;
$fallas = 0;
function ok($valor, string $mensaje): void {
	global $total, $fallas;
	$total++;
	if (!$valor) { $fallas++; }
	echo ($valor ? 'ok ' : 'FALLA ') . $mensaje . "\n";
}
foreach (['omni_derivacion_config', 'omni_debe_volver_al_bot', 'omni_derivacion_correo', 'omni_contexto_comentario', 'automatiza_tech_lead_origen', 'omni_sin_bot'] as $f) {
	ok(function_exists($f), "interfaz $f");
}
if ($fallas) { echo "$total pruebas, $fallas fallas\n"; exit(1); }

// --- omni_derivacion_config
ok(omni_derivacion_config('{"derivacion":{"agente_id":7,"correos":["a@b.cl","a@b.cl","malo"," A@B.cl "]}}') === ['agente_id' => 7, 'correos' => ['a@b.cl']],
	'config: id y correos válidos sin repetir (sin importar mayúsculas ni espacios)');
ok(omni_derivacion_config(null) === ['agente_id' => 0, 'correos' => []], 'config: null');
ok(omni_derivacion_config('{') === ['agente_id' => 0, 'correos' => []], 'config: JSON roto');
ok(omni_derivacion_config('{"otra":1}') === ['agente_id' => 0, 'correos' => []], 'config: sin la clave derivacion');
ok(omni_derivacion_config('{"palabras_clave":{"activo":true},"derivacion":{"agente_id":"9","correos":"x@y.cl"}}') === ['agente_id' => 9, 'correos' => []],
	'config: convive con palabras_clave; correos que no son lista se ignoran');
ok(omni_derivacion_config('{"derivacion":{"agente_id":-3}}')['agente_id'] === 0, 'config: id negativo vale 0');

// --- omni_debe_volver_al_bot
$ahora = strtotime('2026-10-22 10:00:01 UTC');
ok(omni_debe_volver_al_bot('instagram', 'human', '2026-10-21 10:00:00', $ahora, true) === true, 'vuelve: Instagram, humana, derivada por el bot, 24 h + 1 s');
ok(omni_debe_volver_al_bot('instagram', 'human', '2026-10-22 08:00:00', $ahora, true) === false, 'no vuelve: 2 horas');
ok(omni_debe_volver_al_bot('instagram', 'human', '2026-10-21 10:00:01', $ahora, true) === false, 'no vuelve: justo 24 h');
ok(omni_debe_volver_al_bot('instagram', 'human', '2026-10-01 10:00:00', $ahora, false) === false, 'no vuelve: la tomó un ejecutivo a mano');
ok(omni_debe_volver_al_bot('whatsapp', 'human', '2026-10-01 10:00:00', $ahora, true) === false, 'no vuelve: WhatsApp');
ok(omni_debe_volver_al_bot('instagram', '', '2026-10-01 10:00:00', $ahora, true) === false, 'no vuelve: no está en modo humano');
ok(omni_debe_volver_al_bot('instagram', 'human', null, $ahora, true) === false, 'no vuelve: sin último mensaje');
ok(omni_debe_volver_al_bot('instagram', 'human', 'basura', $ahora, true) === false, 'no vuelve: fecha ilegible');

// --- omni_derivacion_correo
$ultimos = [['dir' => 'out', 'texto' => 'Hola, ¿en qué te ayudo?']];
for ($i = 0; $i < 8; $i++) { $ultimos[] = ['dir' => 'in', 'texto' => "m$i"]; }
$ultimos[] = ['dir' => 'in', 'texto' => 'Hola <script>x</script>'];
$c = omni_derivacion_correo(['contact_name' => 'Ana <b>', 'external_contact_id' => '178'], 'Quiere precio a medida', $ultimos, 'https://x.cl/omnicliente/?c=5');
ok($c['asunto'] === 'Instagram: Ana <b> quiere hablar contigo', 'correo: asunto exacto');
ok(strpos($c['html'], 'Ana &lt;b&gt;') !== false && strpos($c['html'], 'Ana <b>') === false, 'correo: nombre escapado');
ok(strpos($c['html'], '&lt;script&gt;') !== false && strpos($c['html'], '<script>') === false, 'correo: mensajes escapados');
ok(strpos($c['html'], 'Instagram solo permite responder dentro de las 24 horas desde el último mensaje de la persona.') !== false, 'correo: aviso de las 24 horas');
ok(strpos($c['html'], 'Quiere precio a medida') !== false, 'correo: trae el motivo');
ok(strpos($c['html'], 'https://x.cl/omnicliente/?c=5') !== false, 'correo: enlace al Portal');
ok(strpos($c['html'], 'm7') !== false && strpos($c['html'], 'Hola, ¿en qué te ayudo?') === false, 'correo: solo los últimos 6 mensajes');
$g = omni_derivacion_correo(['contact_name' => '', 'external_contact_id' => '178'], '', [], 'https://x.cl');
ok($g['asunto'] === 'Instagram: una persona quiere hablar contigo', 'correo: asunto genérico sin nombre');
$p = omni_derivacion_correo(['contact_name' => 'Usuario Instagram 123456', 'external_contact_id' => '1'], 'x', [], 'https://x.cl');
ok($p['asunto'] === 'Instagram: una persona quiere hablar contigo', 'correo: el nombre de relleno cuenta como sin nombre');

// --- omni_contexto_comentario (filas de la conversación, la más nueva primero; fechas en GMT)
$meta = '{"origen":"comentario_palabra_clave","comment_id":"c1","media_id":"m1","keyword":"MÉTODO"}';
$filas = [
	['sender_type' => 'contact', 'content' => 'quiero agendar', 'metadata_json' => null, 'created_at' => '2026-10-24 16:00:00'],
	['sender_type' => 'bot', 'content' => 'Hola, así trabajamos… agenda tu diagnóstico gratis', 'metadata_json' => $meta, 'created_at' => '2026-10-24 15:57:58'],
	['sender_type' => 'system', 'content' => '💬 Comentó «MÉTODO» en una publicación: «Método»', 'metadata_json' => $meta, 'created_at' => '2026-10-24 15:57:58'],
];
$ahora = strtotime('2026-10-24 16:00:05 UTC');
ok(omni_contexto_comentario($filas, $ahora) === ['origen' => 'comentario_palabra_clave', 'palabra' => 'MÉTODO',
	'mensaje_privado' => 'Hola, así trabajamos… agenda tu diagnóstico gratis', 'fecha' => '2026-10-24 15:57:58'], 'contexto: palabra, privado y fecha');
ok(omni_contexto_comentario($filas, strtotime('2026-11-01 16:00:00 UTC')) === null, 'contexto: más de 7 días, no va');
ok(omni_contexto_comentario([$filas[0]], $ahora) === null, 'contexto: conversación sin comentario');
ok(omni_contexto_comentario([], $ahora) === null, 'contexto: sin filas');
$solo_nota = [$filas[2]];
ok(omni_contexto_comentario($solo_nota, $ahora) === ['origen' => 'comentario_palabra_clave', 'palabra' => 'MÉTODO', 'mensaje_privado' => '',
	'fecha' => '2026-10-24 15:57:58'], 'contexto: sin el privado guardado, igual trae la palabra');
$roto = [['sender_type' => 'system', 'content' => 'x', 'metadata_json' => '{roto', 'created_at' => '2026-10-24 15:00:00']];
ok(omni_contexto_comentario($roto, $ahora) === null, 'contexto: metadata ilegible se ignora');
$largo = [['sender_type' => 'bot', 'content' => str_repeat('a', 2000), 'metadata_json' => $meta, 'created_at' => '2026-10-24 15:57:58']];
ok(mb_strlen(omni_contexto_comentario($largo, $ahora)['mensaje_privado']) === 1000, 'contexto: privado recortado a 1000');

// --- omni_sin_bot (cuentas a las que el bot nunca responde, por IGSID; ej. la cuenta personal de Luis)
$cf = '{"palabras_clave":{"activo":true},"sin_bot":[{"igsid":"111","nota":"cuenta personal"},"222"," 333 "]}';
ok(omni_sin_bot($cf, '111') === true, 'sin bot: IGSID de la lista (con nota)');
ok(omni_sin_bot($cf, '222') === true && omni_sin_bot($cf, '333') === true, 'sin bot: IGSID como texto, con o sin espacios');
ok(omni_sin_bot($cf, '444') === false, 'sin bot: otro IGSID sí va al bot');
ok(omni_sin_bot($cf, '') === false, 'sin bot: IGSID vacío no calza con nada');
ok(omni_sin_bot(null, '111') === false && omni_sin_bot('{', '111') === false && omni_sin_bot('{"sin_bot":"111"}', '111') === false,
	'sin bot: config vacía, rota o que no es lista');

// --- automatiza_tech_lead_origen (source, session_id, teléfono)
ok(automatiza_tech_lead_origen('web', 'ig:178414', '') === 'instagram', 'origen: session ig: es Instagram');
ok(automatiza_tech_lead_origen('web', 'IG:178414', '+56911112222') === 'instagram', 'origen: ig: gana al teléfono y no distingue mayúsculas');
ok(automatiza_tech_lead_origen('web', 'whatsapp_56911112222', '') === 'whatsapp', 'origen: session de WhatsApp (como antes)');
ok(automatiza_tech_lead_origen('web', '', '+56911112222') === 'whatsapp', 'origen: teléfono sin sesión (como antes)');
ok(automatiza_tech_lead_origen('web', 'web_abc', '') === 'web', 'origen: web (como antes)');
ok(automatiza_tech_lead_origen('phone', 'ig:1', '') === 'phone', 'origen: un origen explícito no se pisa');
ok(automatiza_tech_lead_origen('web', 'bigig:1', '') === 'web', 'origen: ig: solo al inicio');

echo "\n$total pruebas, $fallas fallas\n";
echo $fallas ? "HAY FALLAS\n" : "TODO OK\n";
exit($fallas ? 1 : 0);
