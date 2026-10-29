<?php
// Ruta wa-contexto, solo para n8n. Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/rest-wp-test.php
// Carga el módulo de ESTA copia del repo (cargar.php) sobre el WordPress local.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
if (!defined('AT_REST_SECRET')) {
	define('AT_REST_SECRET', 'clave-de-prueba-wa-contexto');
}

function wa_pedir(string $tel, ?string $clave, string $texto = ''): WP_REST_Response {
	$r = new WP_REST_Request('GET', '/automatiza-tech/v1/wa-contexto');
	$r->set_query_params(array_filter(['phone' => $tel, 'texto' => $texto]));
	if ($clave !== null) {
		$r->set_header('X-AT-Secret', $clave);
	}
	return rest_ensure_response(rest_do_request($r));
}

/** Busca datos que no deben salir de la ruta fuera de las claves 'agenda'. */
function wa_fugas($v, string $digitos, string $ruta = ''): array {
	$f = [];
	if (is_array($v)) {
		foreach ($v as $k => $x) {
			if ($k === 'agenda' || $k === 'portales') { // portales: el enlace firmado a la ficha de este mismo número (diseño 2026-10-29)
				continue;
			}
			if (in_array((string) $k, ['url', 'monto', 'telefono', 'phone'], true)) {
				$f[] = "$ruta.$k (clave prohibida)";
			}
			$f = array_merge($f, wa_fugas($x, $digitos, "$ruta.$k"));
		}
	} elseif (is_string($v) && (strpos($v, '@') !== false || strpos($v, $digitos) !== false)) {
		$f[] = "$ruta (valor)";
	}
	return $f;
}

$n = wa_numero_libre();
[$crm, $tech] = wa_cliente_crm('r', wa_con_espacios($n));
$k = wa_contrato($tech, 'servicios', 'signed', true);
wa_propuesta(wa_correo('r'), '', 'sent', '[PRUEBA] Propuesta abierta');

ok(wa_pedir('56' . $n, null)->get_status() === 401, 'sin la clave: 401');
ok(wa_pedir('56' . $n, 'una-clave-equivocada')->get_status() === 401, 'con una clave equivocada: 401');
$res = wa_pedir('56' . $n, AT_REST_SECRET);
$d = (array) $res->get_data();
ok($res->get_status() === 200 && ($d['situacion'] ?? '') === 'cliente', 'con la clave: 200 y la situación');
ok(array_keys($d) === ['situacion', 'nombre', 'opciones', 'diagnostico_proximo', 'elegida', 'agenda', 'portales'], 'la respuesta trae exactamente las claves del diseño');
ok(count($d['opciones'] ?? []) === 2 && array_keys($d['opciones'][0]) === ['tipo', 'id', 'nombre', 'plan_codigo', 'agenda'], 'cada opción trae tipo, id, nombre, código del plan y agenda');
$fugas = wa_fugas(json_decode(wp_json_encode($d), true), $n);
ok($fugas === [], 'fuera de la agenda no salen correos, el número, enlaces ni montos' . ($fugas ? ': ' . implode(', ', $fugas) : ''));
ok(wa_pedir('1234', AT_REST_SECRET)->get_data()['situacion'] === 'nuevo', 'un número corto no busca y responde nuevo');
fin();
