<?php
// Revisión B de Codex, hallazgo B-02: un error de la base no se puede leer como «persona nueva».
// Uso: AT_WP_LOAD=<ruta> php tests/wa-contexto/fallos-wp-test.php
// Se reemplaza $wpdb por un intermediario que hace fallar las consultas a una tabla (como un error real de MySQL:
// devuelve null y deja last_error) y deja pasar las demás. Sin escribir nada salvo los datos de prueba.
require __DIR__ . '/../cierre/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/wa-contexto/cargar.php';
if (!defined('AT_REST_SECRET')) {
	define('AT_REST_SECRET', 'clave-de-prueba-wa-fallos');
}

require_once __DIR__ . '/fallos-wpdb.php';

$n = wa_numero_libre();
[$crm, $tech] = wa_cliente_crm('x', wa_con_espacios($n));
$k = wa_contrato($tech, 'servicios', 'signed', true);
wa_plan($k, 'Proyecto Prueba Fallo');
$pr = wa_propuesta(wa_correo('x'), '', 'sent', '[PRUEBA] Propuesta fallo');
ok(at_wa_contexto('56' . $n)['situacion'] === 'cliente', 'sin fallas, el número es de un cliente');

foreach (['crm_clientes', 'automatiza_tech_clients', 'automatiza_propuestas', 'automatiza_leads', 'automatiza_contracts', 'automatiza_planes_trabajo'] as $tabla) {
	$c = con_falla($tabla, function () use ($n) { return at_wa_contexto('56' . $n); });
	ok($c['situacion'] === 'no_disponible' && $c['opciones'] === [] && $c['nombre'] === '', "si falla la lectura de {$tabla}: no_disponible, nunca «nuevo» ni datos a medias");
}
$res = con_falla('crm_clientes', function () use ($n) {
	$r = new WP_REST_Request('GET', '/automatiza-tech/v1/wa-contexto');
	$r->set_query_params(['phone' => '56' . $n]);
	$r->set_header('X-AT-Secret', AT_REST_SECRET);
	return rest_ensure_response(rest_do_request($r));
});
ok($res->get_status() === 503 && (($res->get_data()['situacion'] ?? '') === 'no_disponible'), 'la ruta responde 503 con situacion no_disponible');
ok(strpos(wp_json_encode($res->get_data()), 'falla simulada') === false && strpos(wp_json_encode($res->get_data()), 'SELECT') === false, 'la respuesta no trae el error ni el SQL');
$v = con_falla('automatiza_contracts', function () use ($k, $pr, $n) { return at_wa_vinculo_valido($k, $pr, '56' . $n, wa_correo('x')); });
ok($v === ['contrato_id' => 0, 'propuesta_id' => 0], 'si falla la base, la reunión se guarda sin vínculo (nunca falla la agenda)');
$t = con_falla('automatiza_contracts', function () use ($k) { return at_wa_vinculo_texto(['contrato_id' => $k]); });
ok($t === '', 'si falla la base, la lista no muestra un vínculo');
fin();
