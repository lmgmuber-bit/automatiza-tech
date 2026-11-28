<?php
// php tests/agenda-rutas-clave/permiso-test.php
// Modo aviso contra exigir; cabecera valida, invalida y ausente; el valor nunca se registra.
require __DIR__ . '/dobles.php';
eval(extraer_funcion($tema . '/cierre-cliente/whatsapp.php', 'at_cc_rest_auth')); // la comparacion real, sin duplicarla
require $tema . '/agenda-rutas-clave/puras.php';

$log = sys_get_temp_dir() . '/at-agenda-permiso.log';
ini_set('error_log', $log);
function leer_log(string $log): string { return (string) @file_get_contents($log); }

if (getenv('SIN_CLAVE') === '1') {
	// Sin AT_REST_SECRET configurado una cabecera nunca vale, ni en modo aviso ni en exigir.
	@unlink($log);
	$r = new WP_REST_Request('GET', '/automatiza-tech/v1/followup-meetings/7', ['x_at_secret' => 'lo-que-sea']);
	comprobar(at_agenda_permiso_maquina($r) === true, 'sin clave en el servidor, aviso deja pasar');
	comprobar(strpos(leer_log($log), 'cabecera=invalida') !== false, 'sin clave en el servidor, la cabecera cuenta como invalida');
	$GLOBALS['opciones']['at_agenda_auth_modo'] = 'exigir';
	comprobar(at_agenda_permiso_maquina($r) instanceof WP_Error, 'sin clave en el servidor, exigir rechaza');
	@unlink($log);
	cierre();
}

$buena = ['x_at_secret' => AT_REST_SECRET];
$mala = ['x_at_secret' => 'valor-incorrecto-que-no-debe-aparecer'];
$ruta = '/automatiza-tech/v1/followup-meetings/12/cancel';

echo "-- modo aviso (por omision)\n";
comprobar(at_agenda_modo_auth() === 'aviso', 'por omision es aviso');
@unlink($log);
comprobar(at_agenda_permiso_maquina(new WP_REST_Request('POST', $ruta, $buena)) === true, 'cabecera valida pasa');
comprobar(leer_log($log) === '', 'cabecera valida no deja aviso');
comprobar(at_agenda_permiso_maquina(new WP_REST_Request('POST', $ruta, $mala)) === true, 'cabecera invalida pasa en aviso');
comprobar(at_agenda_permiso_maquina(new WP_REST_Request('POST', $ruta, [])) === true, 'sin cabecera pasa en aviso');
$l = leer_log($log);
comprobar(strpos($l, 'ruta=' . $ruta) !== false && strpos($l, 'metodo=POST') !== false, 'el aviso lleva ruta y metodo');
comprobar(strpos($l, 'cabecera=invalida') !== false && strpos($l, 'cabecera=ausente') !== false, 'el aviso distingue invalida de ausente');
comprobar(strpos($l, 'valor-incorrecto-que-no-debe-aparecer') === false, 'no registra el valor que mando el cliente');
comprobar(strpos($l, AT_REST_SECRET) === false, 'no registra la clave del servidor');

echo "-- modo exigir\n";
$GLOBALS['opciones']['at_agenda_auth_modo'] = 'exigir';
comprobar(at_agenda_modo_auth() === 'exigir', 'la opcion activa exigir');
@unlink($log);
comprobar(at_agenda_permiso_maquina(new WP_REST_Request('POST', $ruta, $buena)) === true, 'cabecera valida pasa');
$r1 = at_agenda_permiso_maquina(new WP_REST_Request('POST', $ruta, $mala));
$r2 = at_agenda_permiso_maquina(new WP_REST_Request('POST', $ruta, []));
comprobar($r1 instanceof WP_Error && $r1->data['status'] === 401, 'cabecera invalida responde 401');
comprobar($r2 instanceof WP_Error && $r2->data['status'] === 401, 'sin cabecera responde 401');
comprobar($r1->message === 'No autorizado.' && $r1->code === 'rest_forbidden', '401 sin detalle');
comprobar(strpos(leer_log($log), AT_REST_SECRET) === false && strpos(leer_log($log), 'valor-incorrecto') === false, 'exigir tampoco registra valores');

echo "-- valores raros del modo\n";
$GLOBALS['opciones']['at_agenda_auth_modo'] = 'EXIGIR ya';
comprobar(at_agenda_modo_auth() === 'aviso', 'un modo desconocido cuenta como aviso');

echo "-- sin clave en el servidor (proceso aparte)\n";
$p = proc_open([PHP_BINARY, __FILE__], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, array_merge(getenv() ?: [], ['SIN_CLAVE' => '1']));
$salida = stream_get_contents($pipes[1]);
stream_get_contents($pipes[2]);
$rc = proc_close($p);
comprobar($rc === 0, 'el proceso sin clave pasa sus comprobaciones', $salida);
@unlink($log);
cierre();
