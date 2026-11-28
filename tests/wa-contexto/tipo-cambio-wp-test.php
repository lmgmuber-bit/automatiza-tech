<?php
// El tipo de cambio USD→CLP se guarda 6 h: los bots no esperan al Banco Central en cada mensaje.
// Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/tipo-cambio-wp-test.php
// Deja la caché y la última tasa como estaban al terminar.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/tipo-cambio-cache.php';

$transient_antes = get_transient(AT_TIPO_CAMBIO_TRANSIENT);
$ultima_antes = get_option('automatiza_tech_last_exchange_rate', null);
register_shutdown_function(function () use ($transient_antes, $ultima_antes) {
	$transient_antes === false ? delete_transient(AT_TIPO_CAMBIO_TRANSIENT) : set_transient(AT_TIPO_CAMBIO_TRANSIENT, $transient_antes, 6 * HOUR_IN_SECONDS);
	$ultima_antes === null ? delete_option('automatiza_tech_last_exchange_rate') : update_option('automatiza_tech_last_exchange_rate', $ultima_antes);
});

$consultas = 0;
$banco = function ($tasa) use (&$consultas) {
	return function () use ($tasa, &$consultas) { $consultas++; return $tasa; };
};

// Con la tasa guardada, no se consulta al banco
set_transient(AT_TIPO_CAMBIO_TRANSIENT, 912.5, 6 * HOUR_IN_SECONDS);
$consultas = 0;
ok(at_tipo_cambio_guardado($banco(950.0)) === 912.5 && $consultas === 0, 'con transient no consulta');

// Sin la tasa guardada, se consulta una vez y se guarda
delete_transient(AT_TIPO_CAMBIO_TRANSIENT);
$consultas = 0;
ok(at_tipo_cambio_guardado($banco(948.25)) === 948.25 && $consultas === 1, 'sin transient consulta una vez');
ok((float) get_transient(AT_TIPO_CAMBIO_TRANSIENT) === 948.25, 'sin transient guarda la tasa');
ok((float) get_option('automatiza_tech_last_exchange_rate') === 948.25, 'y queda como última tasa conocida');
$consultas = 0;
ok(at_tipo_cambio_guardado($banco(1.0)) === 948.25 && $consultas === 0, 'la segunda vez sale de la caché');

// Consulta caída → última tasa conocida, sin guardarla como fresca
delete_transient(AT_TIPO_CAMBIO_TRANSIENT);
update_option('automatiza_tech_last_exchange_rate', 930.0);
ok(at_tipo_cambio_guardado($banco(false)) === 930.0, 'consulta caída → última tasa conocida');
ok(get_transient(AT_TIPO_CAMBIO_TRANSIENT) === false, 'una consulta caída no se guarda como tasa fresca');
ok(at_tipo_cambio_guardado(function () { throw new RuntimeException('banco caído'); }) === 930.0, 'una excepción del banco también cae a la última tasa');

// Pegamento: la ruta /exchange-rate usa la caché y responde las mismas claves de hoy
$ae = (string) file_get_contents(__DIR__ . '/../../wp-content/themes/automatiza-tech/inc/api-endpoints.php');
ok(substr_count($ae, "require_once __DIR__ . '/tipo-cambio-cache.php';") === 1, 'api-endpoints.php carga la caché');
ok(substr_count($ae, "at_tipo_cambio_guardado(function () use (\$updater) { return \$updater->get_current_exchange_rate(); })") === 1, 'la ruta del tipo de cambio pasa por la caché');
foreach (["'currency_from' => 'USD'", "'currency_to' => 'CLP'", "'rate' => (float) \$rate", "'formatted_rate' =>"] as $clave) {
	ok(strpos($ae, $clave) !== false, "la respuesta sigue trayendo {$clave}");
}

fin();
