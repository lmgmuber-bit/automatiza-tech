<?php
// php tests/agenda-rutas-clave/enlaces-test.php
// Firma correcta, alterada y vencida; periodo de gracia para los enlaces sin firma.
require __DIR__ . '/dobles.php';
require $tema . '/agenda-rutas-clave/puras.php';

$ahora = 1800000000;
$vence = $ahora + 3600;
$t = at_agenda_firma(12, 'cancel', $vence);
comprobar(preg_match('/^[0-9a-f]{64}$/', $t) === 1, 'la firma es HMAC-SHA256 en hexadecimal');
comprobar($t === hash_hmac('sha256', '12|cancel|' . $vence, wp_salt('auth')), 'firma = HMAC de id|accion|vencimiento (sin clave propia usa la sal de WordPress)');
comprobar(at_agenda_firma(12, 'borrar', $vence) === '', 'una accion desconocida no se firma');

echo "-- firma correcta, alterada y vencida\n";
$ok = at_agenda_evaluar_enlace(12, 'cancel', (string) $vence, $t, $ahora);
comprobar($ok['permitir'] && $ok['estado'] === 'ok', 'firma correcta pasa');
$t_mal = ($t[0] === 'a' ? 'b' : 'a') . substr($t, 1);
foreach ([
	'firma cambiada' => [12, 'cancel', (string) $vence, $t_mal],
	'otro id' => [13, 'cancel', (string) $vence, $t],
	'otra accion' => [12, 'confirm', (string) $vence, $t],
	'vencimiento alterado' => [12, 'cancel', (string) ($vence + 9999), $t],
	'sin vencimiento' => [12, 'cancel', '', $t],
	'firma corta' => [12, 'cancel', (string) $vence, substr($t, 0, 20)],
	'vencimiento no numerico' => [12, 'cancel', 'abc', $t],
] as $nombre => $a) {
	$r = at_agenda_evaluar_enlace($a[0], $a[1], $a[2], $a[3], $ahora);
	comprobar(!$r['permitir'] && $r['estado'] === 'invalida', "$nombre se rechaza");
}
$venc = at_agenda_evaluar_enlace(12, 'cancel', (string) $vence, $t, $vence + 1);
comprobar(!$venc['permitir'] && $venc['estado'] === 'vencida', 'firma buena pero vencida se rechaza');
comprobar(at_agenda_evaluar_enlace(12, 'cancel', (string) $vence, $t, $vence)['permitir'], 'en el segundo exacto del vencimiento aun sirve');

echo "-- la clave propia manda sobre la sal\n";
define('AT_AGENDA_ENLACE_SECRET', 'clave-propia-de-prueba-0123456789abcdef0123456789');
comprobar(at_agenda_firma(12, 'cancel', $vence) === hash_hmac('sha256', '12|cancel|' . $vence, AT_AGENDA_ENLACE_SECRET), 'con AT_AGENDA_ENLACE_SECRET firma con ella');
comprobar(!at_agenda_evaluar_enlace(12, 'cancel', (string) $vence, $t, $ahora)['permitir'], 'una firma hecha con la sal deja de valer al poner la clave propia');

echo "-- url firmada\n";
$u = at_agenda_url_firmada(12, 'reschedule', 600, $ahora);
comprobar(strpos($u, 'https://ejemplo.test/wp-json/automatiza-tech/v1/followup-meetings/email-action/reschedule/12?e=' . ($ahora + 600) . '&t=') === 0, 'la url lleva e y t');
parse_str(parse_url($u, PHP_URL_QUERY), $q);
comprobar(at_agenda_evaluar_enlace(12, 'reschedule', $q['e'], $q['t'], $ahora)['permitir'], 'la url generada se valida');
comprobar(!at_agenda_evaluar_enlace(12, 'reschedule', $q['e'], $q['t'], $ahora + 601)['permitir'], 'y vence cuando dice');

echo "-- gracia (enlaces sin firma)\n";
$sin = at_agenda_evaluar_enlace(12, 'cancel', null, null, $ahora);
comprobar($sin['permitir'] && $sin['estado'] === 'sin_firma_en_gracia', 'sin gracia definida, el enlace sin firma sigue sirviendo');
$GLOBALS['opciones']['at_agenda_enlaces_gracia_hasta'] = '2027-01-15';
$fin = (new DateTimeImmutable('2027-01-15 23:59:59', new DateTimeZone('America/Santiago')))->getTimestamp();
comprobar(at_agenda_evaluar_enlace(12, 'cancel', null, '', $fin)['permitir'], 'el ultimo dia de la gracia aun sirve');
$tarde = at_agenda_evaluar_enlace(12, 'cancel', null, null, $fin + 1);
comprobar(!$tarde['permitir'] && $tarde['estado'] === 'sin_firma', 'pasada la gracia el enlace sin firma se rechaza');
comprobar(at_agenda_evaluar_enlace(12, 'cancel', (string) ($fin + 3600), at_agenda_firma(12, 'cancel', $fin + 3600), $fin + 1)['permitir'], 'pasada la gracia, uno firmado sigue sirviendo');
$GLOBALS['opciones']['at_agenda_enlaces_gracia_hasta'] = (string) ($ahora + 10);
comprobar(at_agenda_gracia_vigente($ahora) && !at_agenda_gracia_vigente($ahora + 11), 'tambien acepta una marca de tiempo');
$GLOBALS['opciones']['at_agenda_enlaces_gracia_hasta'] = 'pronto';
comprobar(!at_agenda_gracia_vigente($ahora), 'una fecha ilegible cierra la gracia');
$GLOBALS['opciones']['at_agenda_enlaces_gracia_hasta'] = '2027-01-15';
comprobar(!at_agenda_evaluar_enlace(12, 'cancel', (string) $vence, $t_mal, $fin - 100)['permitir'], 'en gracia, una firma alterada tampoco pasa');

echo "-- texto de enlace vencido\n";
[$titulo, $msg] = at_agenda_texto_enlace_vencido();
comprobar($titulo !== '' && strpos($msg, 'contacto@automatizatech.cl') !== false, 'la pagina ofrece contactar');
cierre();
