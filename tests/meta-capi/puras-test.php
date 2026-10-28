<?php
// Funciones puras del evento Lead por CAPI. Uso: php tests/meta-capi/puras-test.php
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/meta-capi/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Vector fijo, calculado aparte con Python: sha256("ana@correo.cl|2026-11-03|10:00")[:40]. El JS debe dar lo mismo.
const VECTOR = '91cafbb214ddfd5e5c52a1fb6ca25c02974bf648';

// Normalizaciones
ok(at_capi_norm_correo(' Ana@Correo.CL ') === 'ana@correo.cl', 'correo: minúsculas y sin espacios');
ok(at_capi_norm_correo('') === '', 'correo vacío');
ok(at_capi_norm_telefono('+56 9 1234 5678') === '56912345678', 'teléfono: +56 9 con espacios');
ok(at_capi_norm_telefono('912345678') === '56912345678', 'teléfono: celular chileno sin código');
ok(at_capi_norm_telefono('56912345678') === '56912345678', 'teléfono: con 56 sin +');
ok(at_capi_norm_telefono('+58 414-123-4567') === '584141234567', 'teléfono: extranjero con código');
ok(at_capi_norm_telefono('') === '' && at_capi_norm_telefono('sin número') === '', 'teléfono vacío o sin dígitos');
ok(at_capi_norm_fecha('2026-11-03') === '2026-11-03', 'fecha AAAA-MM-DD');
ok(at_capi_norm_fecha('2026-11-03 00:00:00') === '2026-11-03', 'fecha con hora se recorta');
ok(at_capi_norm_fecha('03-11-2026') === '' && at_capi_norm_fecha('null') === '' && at_capi_norm_fecha('2026-13-40') === '', 'fecha inválida da vacío');
ok(at_capi_norm_hora('10:00:00') === '10:00', 'hora con segundos');
ok(at_capi_norm_hora('10:00') === '10:00', 'hora HH:MM');
ok(at_capi_norm_hora('9:05') === '09:05', 'hora H:MM');
ok(at_capi_norm_hora('25:00') === '' && at_capi_norm_hora('') === '' && at_capi_norm_hora('10h') === '', 'hora inválida da vacío');

// Hash
ok(at_capi_hash('ana@correo.cl') === '0dabba14b16c0be9bf84cf08e788beec0087711b4298e48a4a1463bbb050fd1f', 'sha256 hex');
ok(at_capi_hash('') === '', 'hash de vacío es vacío');

// Huella del evento
ok(at_capi_event_id(' Ana@Correo.CL ', '2026-11-03', '10:00:00') === VECTOR, 'event_id: vector fijo (correo con mayúsculas y hora con segundos)');
ok(at_capi_event_id('ana@correo.cl', '2026-11-03', '10:00') === VECTOR, 'event_id: mismo vector ya normalizado');
ok(strlen(VECTOR) === 40, 'event_id de 40 caracteres');
ok(at_capi_event_id('', '2026-11-03', '10:00') === '' && at_capi_event_id('a@b.cl', '', '10:00') === '' && at_capi_event_id('a@b.cl', '2026-11-03', 'x') === '', 'event_id vacío si falta un dato');

// Origen
ok(at_capi_action_source('web') === 'other', 'web → other (Meta exige navegador para website)');
ok(at_capi_action_source('instagram') === 'chat' && at_capi_action_source('whatsapp') === 'chat', 'instagram y whatsapp → chat');
ok(at_capi_action_source('cualquier') === 'other' && at_capi_action_source('') === 'other', 'desconocido → other');

// Cuerpo del evento
$lead = ['id' => 77, 'name' => 'Ana Rojas', 'email' => ' Ana@Correo.CL ', 'phone' => '+56 9 1234 5678',
	'source' => 'web', 'scheduled_date' => '2026-11-03', 'scheduled_time' => '10:00:00'];
$p = at_capi_payload($lead, '1347922513241779', '', 1761600000);
$e = $p['data'][0] ?? [];
ok(count($p['data'] ?? []) === 1, 'payload: un solo evento');
ok(($e['event_name'] ?? '') === 'Lead' && ($e['event_time'] ?? 0) === 1761600000, 'payload: Lead con event_time');
ok(($e['event_id'] ?? '') === VECTOR, 'payload: event_id = vector');
ok(($e['action_source'] ?? '') === 'other' && ($e['event_source_url'] ?? '') !== '', 'payload web: other con event_source_url');
ok(!isset($e['user_data']['client_user_agent']), 'payload: sin client_user_agent vacío');
ok(($e['user_data']['em'] ?? null) === ['0dabba14b16c0be9bf84cf08e788beec0087711b4298e48a4a1463bbb050fd1f'], 'payload: em en hash');
ok(($e['user_data']['ph'] ?? null) === ['af312d4e1c1fb2f8fd25aa9e7fa3f70a5be6ab7f238fceabd55284015c267e4f'], 'payload: ph en hash con código de país');
ok(($e['user_data']['external_id'] ?? null) === $e['user_data']['em'], 'payload: external_id = hash del correo');
ok(($e['user_data']['country'] ?? null) === ['d192375885ec7d50df1287cb5e19b55d4a79f1cdd1597b9cc52fd59e05da6730'], 'payload: país cl en hash si el teléfono es chileno');
ok(($e['custom_data']['content_name'] ?? '') === 'Agenda (web)' && ($e['custom_data']['lead_source'] ?? '') === 'web', 'payload: content_name y lead_source');
ok(!isset($p['test_event_code']), 'payload: sin test_event_code cuando no hay');
$json = json_encode($p);
foreach (['Ana@Correo', 'ana@correo.cl', 'Ana Rojas', '12345678', '1234 5678'] as $claro) {
	ok(strpos($json, $claro) === false, "payload: «{$claro}» no va en claro");
}

$ig = at_capi_payload(['id' => 8, 'email' => 'b@c.cl', 'phone' => '+58 414 1234567', 'source' => 'instagram',
	'scheduled_date' => '2026-11-04', 'scheduled_time' => '15:00'], '1', 'TEST123', 1);
$ei = $ig['data'][0];
ok($ei['action_source'] === 'chat' && !isset($ei['event_source_url']), 'payload instagram: chat sin event_source_url');
ok(($ei['custom_data']['content_name'] ?? '') === 'Agenda (Instagram)', 'payload instagram: content_name');
ok(!isset($ei['user_data']['country']), 'payload: sin país si el teléfono no es chileno');
ok(($ig['test_event_code'] ?? '') === 'TEST123', 'payload: test_event_code cuando hay');
$wa = at_capi_payload(['id' => 9, 'email' => 'c@d.cl', 'phone' => '', 'source' => 'whatsapp', 'scheduled_date' => '2026-11-04', 'scheduled_time' => '16:00'], '1', '', 1);
ok(($wa['data'][0]['custom_data']['content_name'] ?? '') === 'Agenda (WhatsApp)' && !isset($wa['data'][0]['user_data']['ph']), 'payload whatsapp sin teléfono: sin ph');

// Respaldo: sin fecha u hora válidas, event_id por lead_id (Review Focus 6)
$sin = at_capi_payload(['id' => 77, 'email' => 'a@b.cl', 'source' => 'web', 'scheduled_date' => 'null', 'scheduled_time' => ''], '1', '', 1);
ok(($sin['data'][0]['event_id'] ?? '') === '4fffaccd056bbcf722178f98d6316a9f5783fa07', 'payload sin fecha: event_id de respaldo sha256("lead|77")[:40]');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
