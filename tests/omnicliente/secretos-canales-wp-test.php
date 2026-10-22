<?php
// Pruebas locales: los secretos de canales no salen de la API y la auditoría no los guarda en claro
// (ni los nuevos ni el historial, con tools/omni-audit-secretos.php). Nunca contra producción.
// Correr: AT_WP_LOAD=<wp-load.php local> php tests/omnicliente/secretos-canales-wp-test.php
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../retomar/wp-bootstrap.php';
require_once __DIR__ . '/../../omnichannel-controller.php';
global $wpdb;

$P = $wpdb->prefix . 'omnichannel_';
$R = static function ($p) { return $p . bin2hex(random_bytes(12)); };
$S = [
	'bot'     => $R('tok-prueba-ig-'),
	'ycloud'  => $R('ycl-prueba-'),
	'ycloud2' => $R('ycl-prueba-nueva-'),
	'agente'  => $R('acc-prueba-'),
	'hash'    => '$P$' . $R('hashprueba'),
	'reset'   => $R('rst-prueba-'),
];
$omni = new OmnichannelController();

$cli = $omni->create_client(['company_name' => '[PRUEBA] Secretos canales', 'contact_name' => 'Prueba', 'email' => 'prueba-secretos@example.com', 'plan_type' => 'enterprise']);
$CLIENT = (int) ($cli['id'] ?? 0);
$API_KEY = (string) ($cli['api_key'] ?? '');
ok($CLIENT > 0 && $API_KEY !== '', 'cliente de prueba creado');
$ig = $omni->create_channel($CLIENT, ['channel_type' => 'instagram', 'channel_name' => '[PRUEBA] IG', 'bot_token' => $S['bot']]);
$wa = $omni->create_channel($CLIENT, ['channel_type' => 'whatsapp', 'channel_name' => '[PRUEBA] WA', 'ycloud_api_key' => $S['ycloud']]);
$IG = (int) ($ig['id'] ?? 0);
$WA = (int) ($wa['id'] ?? 0);
$WEBHOOK = (string) ($wa['webhook_secret'] ?? '');
ok($IG > 0 && $WA > 0 && $WEBHOOK !== '', 'canales de prueba creados');
$wpdb->update($P . 'channels', ['credentials_json' => wp_json_encode(['clave' => $R('cred-')])], ['id' => $WA]);

$legacy_ids = [];
register_shutdown_function(static function () use ($wpdb, $P, $CLIENT, $IG, $WA, &$legacy_ids) {
	foreach ([$IG, $WA] as $cid) {
		$wpdb->delete($P . 'bot_configs', ['channel_id' => $cid]);
		$wpdb->delete($P . 'channels', ['id' => $cid]);
		$wpdb->delete($P . 'audit_log', ['entity_type' => 'channel', 'entity_id' => $cid]);
	}
	$wpdb->delete($P . 'audit_log', ['entity_type' => 'client', 'entity_id' => $CLIENT]);
	$wpdb->delete($P . 'audit_log', ['client_id' => $CLIENT]);
	foreach ($legacy_ids as $id) { $wpdb->delete($P . 'audit_log', ['id' => $id]); }
	$wpdb->delete($P . 'clients', ['id' => $CLIENT]);
});

$contiene = static function ($data, array $secretos) {
	$json = is_string($data) ? $data : wp_json_encode($data);
	foreach ($secretos as $s) { if ($s !== '' && strpos($json, $s) !== false) { return true; } }
	return false;
};
$todos = array_values($S);

// --- 1) Lo que ve el navegador
$propios = $omni->get_channels($CLIENT); // ruta de clientes (X-API-Key) y de agentes
ok(count($propios) === 2, 'get_channels devuelve los 2 canales del cliente');
ok(!$contiene($propios, array_merge($todos, [$WEBHOOK])), 'get_channels (clientes y agentes): ni token, ni API key de YCloud, ni webhook_secret');
$por_id = [];
foreach ($propios as $ch) { $por_id[(int) $ch->id] = $ch; }
ok($por_id[$IG]->has_bot_token === true && $por_id[$IG]->has_ycloud_api_key === false, 'canal IG: has_bot_token=true, has_ycloud_api_key=false');
ok($por_id[$WA]->has_ycloud_api_key === true && $por_id[$WA]->ycloud_api_key === '', 'canal WA: has_ycloud_api_key=true y la clave vacía');
ok($por_id[$WA]->credentials_json === null, 'credentials_json no viaja');
ok($por_id[$WA]->webhook_secret === '', 'webhook_secret no le llega a clientes ni agentes');

$admin = array_values(array_filter($omni->get_all_channels(), static function ($ch) use ($IG, $WA) { return in_array((int) $ch->id, [$IG, $WA], true); }));
ok(count($admin) === 2 && !$contiene($admin, $todos), 'get_all_channels (admin): sin token ni API key');
$wa_admin = null;
foreach ($admin as $ch) { if ((int) $ch->id === $WA) { $wa_admin = $ch; } }
ok($wa_admin && $wa_admin->webhook_secret === $WEBHOOK, 'el admin conserva webhook_secret para armar la URL del webhook');

// --- 2) Editar sin tocar las claves las conserva; pegar una nueva la reemplaza
$guardado = static function ($cid, $col, $valor) use ($wpdb, $P) {
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$P}channels WHERE id = %d AND {$col} = %s", $cid, $valor)) === 1;
};
ok($omni->update_channel($WA, ['channel_name' => '[PRUEBA] WA 2', 'ycloud_api_key' => '', 'bot_token' => '']) === true, 'editar con las claves vacías responde bien');
ok($guardado($WA, 'ycloud_api_key', $S['ycloud']), 'la API key de YCloud se conserva');
ok($guardado($WA, 'channel_name', '[PRUEBA] WA 2'), 'el resto de los cambios se guarda');
$omni->update_channel($WA, ['ycloud_api_key' => $S['ycloud2']]);
ok($guardado($WA, 'ycloud_api_key', $S['ycloud2']), 'pegar una API key nueva la reemplaza');
$omni->update_channel($IG, ['bot_token' => '']);
ok($guardado($IG, 'bot_token', $S['bot']), 'el token de Instagram también se conserva');

// --- 3) La auditoría nueva no guarda secretos (todas las entidades, a cualquier profundidad)
$audit_cli = $wpdb->get_col($wpdb->prepare(
	"SELECT CONCAT(IFNULL(old_values_json,''), IFNULL(new_values_json,'')) FROM {$P}audit_log WHERE client_id = %d OR (entity_type = 'channel' AND entity_id IN (%d, %d))",
	$CLIENT, $IG, $WA
));
ok(count($audit_cli) >= 5, 'hay auditoría del cliente y de los canales (' . count($audit_cli) . ' filas)');
ok(!$contiene(implode('|', $audit_cli), array_merge($todos, [$API_KEY, $WEBHOOK])), 'la auditoría nueva no trae token, API keys ni webhook_secret');
$red = OmnichannelController::redact_audit_values([
	'name' => 'Ana', 'password_hash' => $S['hash'], 'access_token' => $S['agente'], 'reset_token' => '',
	'anidado' => ['api_key' => 'x', 'nota' => 'se queda'], 'obj' => (object) ['webhook_secret' => 'y'],
]);
ok($red['name'] === 'Ana' && $red['anidado']['nota'] === 'se queda', 'tachar no toca los demás campos');
ok($red['password_hash'] === '[oculto]' && $red['access_token'] === '[oculto]' && $red['anidado']['api_key'] === '[oculto]' && $red['obj']['webhook_secret'] === '[oculto]', 'se tachan hash, sesión y claves anidadas');
ok($red['reset_token'] === '', 'un secreto vacío sigue vacío (se ve que no había)');
ok(OmnichannelController::redact_audit_values(null) === null, 'null sigue null');

// --- 4) Historial: filas viejas guardadas en claro (antes del arreglo)
$viejas = [
	['entity_type' => 'channel', 'old_values_json' => wp_json_encode(['id' => 1, 'bot_token' => $S['bot'], 'channel_name' => 'IG']), 'new_values_json' => wp_json_encode(['bot_token' => $S['bot']])],
	['entity_type' => 'agent', 'old_values_json' => wp_json_encode(['name' => 'Ana', 'password_hash' => $S['hash'], 'access_token' => $S['agente'], 'reset_token' => $S['reset']]), 'new_values_json' => null],
	['entity_type' => 'client', 'old_values_json' => null, 'new_values_json' => wp_json_encode(['company_name' => 'X', 'api_key' => $API_KEY])],
	['entity_type' => 'channel', 'old_values_json' => '{"bot_token": "' . $S['bot'] . '", roto', 'new_values_json' => null],
	['entity_type' => 'agent', 'old_values_json' => wp_json_encode(['name' => 'Sin secretos', 'password_hash' => '']), 'new_values_json' => null],
];
foreach ($viejas as $v) {
	$wpdb->insert($P . 'audit_log', array_merge($v, ['action' => 'update', 'entity_id' => 0, 'description' => '[PRUEBA] fila vieja', 'client_id' => $CLIENT]));
	$legacy_ids[] = (int) $wpdb->insert_id;
}
$conteo = $omni->redact_audit_history(false, null, $legacy_ids);
ok($conteo['rows_seen'] === 5 && $conteo['rows_changed'] === 3, "solo conteo: ve 5 filas y tacharía 3 ({$conteo['rows_seen']}/{$conteo['rows_changed']})");
ok($conteo['unreadable'] === 1, 'la fila con JSON roto se cuenta aparte y no se toca');
ok(($conteo['by_entity']['agent'] ?? 0) === 1 && ($conteo['by_key']['access_token'] ?? 0) === 1 && ($conteo['by_key']['bot_token'] ?? 0) === 2, 'conteos por entidad y por campo');
ok(!$contiene($conteo, array_merge($todos, [$API_KEY])), 'el conteo no trae ningún valor');
$intactas = $wpdb->get_col("SELECT CONCAT(IFNULL(old_values_json,''), IFNULL(new_values_json,'')) FROM {$P}audit_log WHERE id IN (" . implode(',', $legacy_ids) . ')');
ok($contiene(implode('|', $intactas), [$S['agente']]), 'solo contar no escribe');

$respaldadas = [];
$hecho = $omni->redact_audit_history(true, static function ($row) use (&$respaldadas) { $respaldadas[] = (int) $row->id; }, $legacy_ids);
ok($hecho['rows_changed'] === 3 && count($respaldadas) === 3, 'aplicar: tacha 3 filas y respalda antes cada una');
$despues = $wpdb->get_results("SELECT id, old_values_json, new_values_json FROM {$P}audit_log WHERE id IN (" . implode(',', $legacy_ids) . ') ORDER BY id');
$sanas = array_filter($despues, static function ($r) { return json_decode((string) $r->old_values_json) !== null || $r->old_values_json === null; });
ok(!$contiene(wp_json_encode(array_values($sanas)), array_merge($todos, [$API_KEY])), 'después de aplicar, ninguna fila legible trae secretos');
$agente = json_decode($despues[1]->old_values_json, true);
ok($agente['name'] === 'Ana' && $agente['password_hash'] === '[oculto]' && $agente['reset_token'] === '[oculto]', 'los demás campos quedan como estaban');
ok($despues[3]->old_values_json === $viejas[3]['old_values_json'], 'la fila ilegible queda igual (se reporta, no se adivina)');
$otra = $omni->redact_audit_history(true, null, $legacy_ids);
ok($otra['rows_changed'] === 0, 'correrlo otra vez no cambia nada (idempotente)');

// --- 5) El script por línea de comandos
$wp_load = getenv('AT_WP_LOAD');
$script = dirname(__DIR__, 2) . '/tools/omni-audit-secretos.php';
$correr = static function (array $args) use ($script) {
	$cmd = array_merge([PHP_BINARY, $script], $args);
	$p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$out = stream_get_contents($pipes[1]);
	$err = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return ['code' => proc_close($p), 'out' => $out, 'err' => $err];
};
// La fila 0 vuelve a quedar en claro para que el script tenga algo que tachar.
$wpdb->update($P . 'audit_log', ['old_values_json' => $viejas[0]['old_values_json']], ['id' => $legacy_ids[0]]);
$ids_arg = '--ids=' . implode(',', $legacy_ids);
$ctl_arg = '--controlador=' . dirname(__DIR__, 2) . '/omnichannel-controller.php';
$r = $correr(['--wp=' . $wp_load, $ctl_arg, $ids_arg]);
$json = json_decode($r['out'], true);
ok($r['code'] === 0 && ($json['modo'] ?? '') === 'solo conteo' && ($json['rows_changed'] ?? -1) === 1, 'script sin --aplicar: solo cuenta (1 fila)');
ok(!$contiene($r['out'] . $r['err'], array_merge($todos, [$API_KEY])), 'la salida del script no trae valores');
$r = $correr(['--wp=' . $wp_load, $ctl_arg, $ids_arg, '--aplicar']);
ok($r['code'] === 2, 'script con --aplicar y sin --respaldo: se niega');
$r = $correr(['--wp=' . $wp_load, $ctl_arg, $ids_arg, '--aplicar', '--respaldo=' . dirname($wp_load) . '/respaldo-prueba.jsonl']);
ok($r['code'] === 2 && !file_exists(dirname($wp_load) . '/respaldo-prueba.jsonl'), 'script: un respaldo dentro del sitio web se rechaza');
$bk = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omni-audit-respaldo-' . bin2hex(random_bytes(4)) . '.jsonl';
$r = $correr(['--wp=' . $wp_load, $ctl_arg, $ids_arg, '--aplicar', '--respaldo=' . $bk]);
$json = json_decode($r['out'], true);
ok($r['code'] === 0 && ($json['modo'] ?? '') === 'aplicado' && ($json['rows_changed'] ?? -1) === 1, 'script con --aplicar y respaldo: tacha 1 fila');
ok(is_file($bk) && count(file($bk)) === 1 && $contiene((string) file_get_contents($bk), [$S['bot']]), 'el respaldo guarda la fila original');
$r2 = $correr(['--wp=' . $wp_load, $ctl_arg, $ids_arg, '--aplicar', '--respaldo=' . $bk]);
ok($r2['code'] === 2, 'no pisa un respaldo que ya existe');
@unlink($bk);
ok(!$contiene((string) $wpdb->get_var($wpdb->prepare("SELECT old_values_json FROM {$P}audit_log WHERE id = %d", $legacy_ids[0])), [$S['bot']]), 'la fila quedó tachada');

fin();
