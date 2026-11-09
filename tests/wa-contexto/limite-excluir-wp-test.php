<?php
// Al reprogramar, la cita que se mueve no cuenta para el límite de 2 reuniones activas; solo n8n con la clave puede pedirlo.
// Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/limite-excluir-wp-test.php
// El WordPress local carga su propio admin-followup-meetings.php y api-endpoints.php (espejo de PROD): la lógica se prueba en
// el módulo de esta copia y el pegamento de los dos archivos se revisa leyendo su texto.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
if (!defined('AT_REST_SECRET')) {
	define('AT_REST_SECRET', 'clave-de-prueba-wa-limite');
}

$correo = wa_correo('lim');
$n = wa_numero_libre();
$l = wa_lead($correo, wa_con_espacios($n), wa_dia(3), '10:00');
$s = wa_seguimiento($correo, wa_con_espacios($n), wa_dia(4), '11:00');

$sin = at_wa_limite_con_excluir($correo, []);
ok($sin['alcanzado'] === true && $sin['demos'] === 1 && $sin['seguimientos'] === 1, '2 activas sin excluir → alcanzado');
ok($sin['mensaje'] === 'Ya tienes 2 reuniones activas (1 demo(s) y 1 seguimiento(s)). Cancela o espera a que pase alguna para agendar otra.',
	'sin excluir, el mismo mensaje que la función de hoy');
$d = at_wa_limite_con_excluir($correo, ['tipo' => 'diagnostico', 'id' => $l]);
ok($d['alcanzado'] === false && $d['demos'] === 0 && $d['seguimientos'] === 1, '2 activas excluyendo el diagnóstico → no alcanzado');
$sg = at_wa_limite_con_excluir($correo, ['tipo' => 'seguimiento', 'id' => $s]);
ok($sg['alcanzado'] === false && $sg['seguimientos'] === 0, '2 activas excluyendo el seguimiento → no alcanzado');
ok(at_wa_limite_con_excluir($correo, ['tipo' => 'otro', 'id' => $l])['alcanzado'] === true, 'un excluir con tipo raro no excluye nada');

$pedido = function (?string $clave, array $params) {
	$r = new WP_REST_Request('POST', '/automatiza-tech/v1/leads');
	foreach ($params as $k => $v) {
		$r->set_param($k, $v);
	}
	if ($clave !== null) {
		$r->set_header('X-AT-Secret', $clave);
	}
	return $r;
};
ok(at_wa_excluir_de_request($pedido(null, ['excluir_tipo' => 'diagnostico', 'excluir_id' => $l])) === [], 'excluir sin clave → se ignora');
ok(at_wa_excluir_de_request($pedido('clave-mala', ['excluir_tipo' => 'diagnostico', 'excluir_id' => $l])) === [], 'excluir con clave equivocada → se ignora');
ok(at_wa_excluir_de_request($pedido(AT_REST_SECRET, ['excluir_tipo' => 'diagnostico', 'excluir_id' => (string) $l])) === ['tipo' => 'diagnostico', 'id' => $l], 'excluir con clave → vale');
ok(at_wa_excluir_de_request($pedido(AT_REST_SECRET, ['excluir_tipo' => 'nada', 'excluir_id' => 5])) === [], 'excluir con tipo inválido → se ignora');
ok(at_wa_excluir_de_request($pedido(AT_REST_SECRET, [])) === [], 'sin excluir → vacío');

// Pegamento (texto de los archivos de esta copia)
$tema = __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/';
$af = (string) file_get_contents($tema . 'admin-followup-meetings.php');
$ae = (string) file_get_contents($tema . 'api-endpoints.php');
ok(preg_match('/function automatiza_tech_limite_reuniones_activas\(\$email, array \$excluir = \[\]\)\s*\{\s*if \(\$excluir && function_exists\(\'at_wa_limite_con_excluir\'\)\)\s*\{\s*return at_wa_limite_con_excluir\(\(string\) \$email, \$excluir\);/', $af) === 1,
	'la función del límite delega en el módulo cuando hay que excluir');
$llamada = "automatiza_tech_limite_reuniones_activas(\$email, function_exists('at_wa_excluir_de_request') ? at_wa_excluir_de_request(\$request) : [])";
ok(substr_count($af, $llamada) === 1, 'crear seguimiento pasa el excluir del pedido');
ok(substr_count($ae, $llamada) === 1, '/leads pasa el excluir del pedido');

fin();
