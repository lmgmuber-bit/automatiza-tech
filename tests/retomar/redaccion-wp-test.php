<?php
// Redacción con IA de Retomar: pedido a n8n, contexto para la IA y callback con textos y encargos de foto.
require __DIR__ . '/wp-bootstrap.php';
exigir('at_rt_pedir_redaccion', 'at_rt_contexto_redaccion', 'at_rt_guardar_redaccion');
if (!defined('AT_REST_SECRET')) { define('AT_REST_SECRET', bin2hex(random_bytes(24))); }
at_rt_migrar_esquema();
global $wpdb;
$ids = [];
$props = [];
$crm = [];
$marca = 'rt-red-' . strtolower(wp_generate_password(8, false, false));
register_shutdown_function(static function () use (&$ids, &$props, &$crm, $wpdb) {
	foreach ($ids as $id) { $wpdb->delete($wpdb->prefix . 'at_retomar', ['id' => $id]); }
	foreach ($props as $id) { $wpdb->delete($wpdb->prefix . 'automatiza_propuestas', ['id' => $id]); }
	foreach ($crm as $id) { $wpdb->delete($wpdb->prefix . 'crm_historial', ['cliente_id' => $id]); }
});

function rt_red_seg(array $extra = []): int {
	global $ids, $marca;
	$id = at_rt_crear($extra + ['nombre' => '[PRUEBA] Persona', 'negocio' => '[PRUEBA] Negocio', 'email' => $marca . '-' . count($ids) . '@example.com', 'telefono' => '+56900000000', 'demo_urls' => ['https://example.com/demo'], 'textos' => ['proyecto' => '[PRUEBA] Proyecto', 'quedamos' => '[PRUEBA] Quedamos antes', 'proponemos' => '[PRUEBA] Proponemos antes', 'partir' => '[PRUEBA] Partir antes']]);
	$ids[] = $id;
	return $id;
}
function rt_red_peticion(string $metodo, int $id, string $ruta, ?array $body = null, bool $auth = true) {
	$r = new WP_REST_Request($metodo, '/automatiza-tech/v1/retomar/' . $id . '/' . $ruta);
	if ($auth) { $r->set_header('X-AT-Secret', AT_REST_SECRET); }
	if ($body !== null) { $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($body)); }
	return rest_do_request($r);
}
function rt_red_callback(int $id, array $body, bool $auth = true) {
	$body += ['generacion' => at_rt_obtener($id)['redaccion']['generacion'] ?? ''];
	return rt_red_peticion('POST', $id, 'redaccion', $body, $auth);
}
function rt_red_bien(array $extra = []): array {
	return $extra + [
		'ok' => true,
		'textos' => ['quedamos' => "Quedamos en retomar el proyecto.\nLa agenda era lo urgente.", 'proponemos' => 'Proponemos partir por lo más simple y medir el resultado.', 'partir' => 'Podemos partir esta semana con una reunión corta.', 'recomendacion' => 'Recomendamos partir por la agenda automática.'],
		'image_briefs' => [['slide' => 'portada', 'prompt' => '[PRUEBA] Oficina luminosa con una mesa ordenada, sin texto ni letreros'], ['slide' => 'cierre', 'prompt' => '[PRUEBA] Mesa de reunión al atardecer, sin texto ni etiquetas']],
		'nota' => '[PRUEBA] Redactado con IA',
	];
}
do_action('rest_api_init');

// ---- Pedido a n8n ----
$a = rt_red_seg();
if (!defined('AT_N8N_RETOMAR_REDACTAR')) {
	$antes = at_rt_obtener($a);
	ok(at_rt_pedir_redaccion($a, 'x') === 'sin_flujo' && at_rt_obtener($a) === $antes, 'sin constante: sin_flujo y sin cambios');
	define('AT_N8N_RETOMAR_REDACTAR', 'https://example.com/prueba-redactar');
}
if (!defined('AT_N8N_RETOMAR_RENDER')) { define('AT_N8N_RETOMAR_RENDER', 'https://example.com/prueba-render'); }
$simular = false;
$simular_render = false;
$capturado = null;
$capturado_render = null;
// Se conserva el bloqueo de HTTP del bootstrap: solo estas dos URL obtienen respuesta simulada, y solo cuando se pide.
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$simular, &$simular_render, &$capturado, &$capturado_render) {
	if ($simular && $url === AT_N8N_RETOMAR_REDACTAR) { $capturado = json_decode($args['body'], true); return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []]; }
	if ($simular_render && $url === AT_N8N_RETOMAR_RENDER) { $capturado_render = json_decode($args['body'], true); return ['response' => ['code' => 200], 'body' => '{}', 'headers' => []]; }
	return $pre;
}, PHP_INT_MAX, 3);

$error = at_rt_pedir_redaccion($a, '[PRUEBA] Que suene cercano');
$r = at_rt_obtener($a)['redaccion'];
ok($error !== '' && $error !== 'sin_flujo' && $r['estado'] === 'error', 'con flujo y HTTP bloqueado: devuelve el error y deja estado error');
ok(preg_match('/^[a-f0-9]{16}$/D', $r['generacion'] ?? '') === 1 && $r['intentos'] === 1 && $r['pedido'] === '[PRUEBA] Que suene cercano', 'el pedido guarda generación, intentos 1 y el pedido de quien lo pide');
ok(at_rt_obtener($a)['notas'] === '', 'el pedido vive en redaccion y no se agrega a las notas');

$simular = true;
ok(at_rt_pedir_redaccion($a, '[PRUEBA] Que suene cercano') === '' && at_rt_obtener($a)['redaccion']['estado'] === 'pendiente', 'con el flujo simulado: estado pendiente');
$r2 = at_rt_obtener($a)['redaccion'];
ok(array_keys($capturado) === ['id', 'generacion'] && $capturado['id'] === $a && $capturado['generacion'] === $r2['generacion'] && $r2['generacion'] !== $r['generacion'], 'n8n recibe solo {id, generacion} y cada pedido tiene su generación');
$simular = false;
ok(at_rt_pedir_redaccion(PHP_INT_MAX) !== '' && at_rt_pedir_redaccion(PHP_INT_MAX) !== 'sin_flujo', 'seguimiento inexistente: error claro');

// ---- Contexto para la IA ----
$cliente_crm = 987650000 + random_int(1, 9999);
$crm[] = $cliente_crm;
$titulos = ['Primer contacto', 'Reunión de descubrimiento', 'Cotización de $50.000 enviada', 'Propuesta enviada', 'Llamada', 'Correo', 'Otro correo', 'WhatsApp', 'Reunión', 'Nota', 'Seguimiento', 'Último contacto'];
foreach ($titulos as $i => $t) {
	$wpdb->insert($wpdb->prefix . 'crm_historial', ['cliente_id' => $cliente_crm, 'tipo_evento' => 'nota', 'titulo' => $t, 'descripcion' => '', 'usuario_id' => 0, 'created_at' => gmdate('Y-m-d H:i:s', 1700000000 + $i * 60)]);
}
$transcripcion = "Hablamos de la agenda y de los recordatorios.\nEl valor del plan es $120.000 al mes.\nQuedó pendiente el diseño." . str_repeat("\nLínea de relleno sobre la reunión.", 700);
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '-prop@example.com', 'client_name' => '[PRUEBA]', 'company_name' => '[PRUEBA]', 'unique_link_id' => substr(md5($marca), 0, 12), 'status' => 'sent', 'flujo' => 'v3', 'transcript_text' => $transcripcion, 'gamma_prompt_text' => "Presentación de la agenda automática para el negocio.\n| Ítem | Precio |\n| Agenda | 150.000 |\nTotal: USD 300\nIncluye recordatorios y reportes semanales.", 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$props[] = $prop = (int) $wpdb->insert_id;
$c = rt_red_seg(['crm_cliente_id' => $cliente_crm, 'propuesta_id' => $prop, 'notas' => "Quiere algo simple\nMencionó un presupuesto de CLP 500", 'imagen_url' => '']);
at_rt_guardar($c, ['textos' => ['proyecto' => '[PRUEBA] Proyecto', 'quedamos' => '[PRUEBA] Quedamos', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Partir', 'recomendacion' => '[PRUEBA] Recomendación'], 'redaccion' => ['estado' => 'pendiente', 'generacion' => str_repeat('b', 16), 'intentos' => 1, 'pedido' => "Habla de la agenda\nOfrece 20% sobre UF 5"]]);
$ctx = at_rt_contexto_redaccion($c);
ok($ctx !== null && at_rt_contexto_redaccion(PHP_INT_MAX) === null, 'contexto de un seguimiento y null si no existe');
ok(array_keys($ctx) === ['id', 'generacion', 'nombre', 'negocio', 'demo_urls', 'notas', 'pedido', 'textos_actuales', 'propuesta', 'historial', 'reglas', 'slides_foto'], 'el contexto trae exactamente los campos acordados');
ok($ctx['id'] === $c && $ctx['generacion'] === str_repeat('b', 16) && $ctx['nombre'] === '[PRUEBA] Persona' && $ctx['negocio'] === '[PRUEBA] Negocio' && $ctx['demo_urls'] === ['https://example.com/demo'], 'identidad, generación y demos');
ok($ctx['reglas'] === ['max_caracteres' => 600, 'max_lineas' => 6, 'max_recomendacion' => 300, 'sin_montos' => true], 'reglas de redacción');
ok($ctx['textos_actuales'] === ['quedamos' => '[PRUEBA] Quedamos', 'proponemos' => '[PRUEBA] Proponemos', 'partir' => '[PRUEBA] Partir', 'recomendacion' => '[PRUEBA] Recomendación'], 'textos actuales sin el proyecto');
ok($ctx['slides_foto'] === ['portada', 'quedamos', 'cierre', 'demo'], 'sin imagen del seguimiento: también se encarga la foto de la demo (y siempre la de «quedamos»)');
at_rt_guardar($c, ['imagen_url' => 'https://example.com/captura.png']);
ok(at_rt_contexto_redaccion($c)['slides_foto'] === ['portada', 'quedamos', 'cierre'], 'con imagen del seguimiento: portada, quedamos y cierre');
ok(array_keys($ctx['propuesta']) === ['status', 'flujo', 'transcripcion', 'resumen'] && $ctx['propuesta']['status'] === 'sent' && $ctx['propuesta']['flujo'] === 'v3', 'estado y flujo de la propuesta');
ok(mb_strlen($ctx['propuesta']['transcripcion']) <= 12000 && mb_strlen($ctx['propuesta']['transcripcion']) > 10000 && str_contains($ctx['propuesta']['transcripcion'], 'Hablamos de la agenda'), 'transcripción de hasta 12000 caracteres');
ok(!str_contains($ctx['propuesta']['transcripcion'], '120.000') && str_contains($ctx['propuesta']['transcripcion'], 'Quedó pendiente el diseño.'), 'la transcripción pierde solo las líneas con montos');
ok(str_contains($ctx['propuesta']['resumen'], 'agenda automática') && str_contains($ctx['propuesta']['resumen'], 'recordatorios y reportes') && !str_contains($ctx['propuesta']['resumen'], '|') && !str_contains($ctx['propuesta']['resumen'], 'USD') && mb_strlen($ctx['propuesta']['resumen']) <= 1200, 'el resumen sale de la propuesta sin tablas de precios ni montos');
// Una propuesta v3 guarda su contenido como JSON de una sola línea con precios y correo: el resumen sale solo de la prosa.
$json_v3 = static function (array $filas, array $extra = []): string {
	return wp_json_encode($extra + [
		'unique_id' => 'x', 'client_name' => '[PRUEBA]', 'company_name' => '[PRUEBA]',
		'challenge_title' => 'El desafío de la agenda', 'challenge_text' => "Hoy las reservas se anotan a mano.\nSe pierden clientes por no responder a tiempo.",
		'solution_title' => 'Una agenda que responde sola', 'solution_text' => 'Un asistente que confirma las horas y envía recordatorios.',
		'benefits' => [['title' => 'Menos ausencias', 'text' => 'Los recordatorios bajan las inasistencias.'], ['title' => 'Más tiempo', 'text' => 'Ahorra más de $50.000 al mes en horas perdidas.']],
		'how_it_works' => [['step_title' => 'Conectamos la agenda', 'step_text' => "Se enlaza con el calendario del negocio.\nSin cambiar nada."]],
		'pricing_rows' => $filas, 'pricing_note' => 'Precios en dólares más IVA, pago inicial del 50 por ciento',
		'next_steps' => ['Agendar una llamada de seguimiento', 'Aprobar el alcance'],
		'correo_cliente' => ['asunto' => 'Tu propuesta', 'saludo' => 'Hola, aquí va correo-secreto-X'],
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
$filas_sin_marca = [['service' => 'Servicio-Precio-X', 'price_usd' => 0, 'price_label' => '300 dólares + IVA'], ['service' => 'Servicio-Precio-Y', 'price_usd' => 0, 'price_label' => '1500 pesos al mes'], ['service' => 'Servicio-Precio-Z', 'price_usd' => 0, 'price_label' => '300']];
$filas_con_marca = [['service' => 'Servicio-Precio-X', 'price_usd' => 0, 'price_label' => 'USD 300 + IVA']];
$beneficios_limpios = [['title' => 'Menos ausencias', 'text' => 'Los recordatorios bajan las inasistencias.']];
foreach (['precios sin marca (dólares, pesos, solo cifra)' => [$filas_sin_marca, []], 'precio con marca (USD)' => [$filas_con_marca, []], 'nada en la prosa ni en los precios trae marca de monto' => [$filas_sin_marca, ['benefits' => $beneficios_limpios]]] as $caso => [$filas, $extra]) {
	$res_json = at_rt_red_resumen($json_v3($filas, $extra));
	ok(str_contains($res_json, 'Hoy las reservas se anotan a mano.') && str_contains($res_json, 'Una agenda que responde sola') && str_contains($res_json, 'Menos ausencias: Los recordatorios') && str_contains($res_json, 'Conectamos la agenda: Se enlaza con el calendario del negocio. Sin cambiar nada.') && str_contains($res_json, 'Agendar una llamada de seguimiento'), "resumen de una propuesta JSON ({$caso}): trae la prosa (desafío, solución, beneficios, pasos y próximos pasos)");
	ok(!preg_match('/Servicio-Precio|dólares|pesos|300|1500|IVA|correo-secreto|pricing|correo_cliente|[{}"]/u', $res_json) && !str_contains($res_json, 'Más tiempo') && !at_rt_tiene_montos($res_json) && mb_strlen($res_json) <= 1200, "resumen de una propuesta JSON ({$caso}): sin precios, correo ni JSON crudo, y el elemento con monto se descarta entero");
}
$res_cerco = at_rt_red_resumen("```json\n" . $json_v3($filas_sin_marca) . "\n```");
ok(str_contains($res_cerco, 'Una agenda que responde sola') && !str_contains($res_cerco, 'Servicio-Precio') && !str_contains($res_cerco, '```'), 'JSON entre cercos de código: también se lee y se filtra');
ok(at_rt_red_resumen(substr($json_v3($filas_sin_marca), 0, -40)) === '', 'JSON truncado (no se puede leer): resumen vacío, nunca el JSON crudo');
ok(at_rt_red_resumen('{"pricing_rows":[{"price_label":"300 dólares"}]}') === '' && at_rt_red_resumen('[]') === '', 'JSON sin claves de prosa: resumen vacío');
$largo = at_rt_red_resumen($json_v3($filas_sin_marca, ['solution_text' => str_repeat('Una frase larga sobre la agenda. ', 80)]));
ok(mb_strlen($largo) <= 1200 && $largo !== '', 'el resumen de una propuesta JSON se corta a 1200 caracteres');
$wpdb->insert($wpdb->prefix . 'automatiza_propuestas', ['client_email' => $marca . '-json@example.com', 'client_name' => '[PRUEBA]', 'company_name' => '[PRUEBA]', 'unique_link_id' => substr(md5($marca . 'json'), 0, 12), 'status' => 'sent', 'flujo' => 'v3', 'transcript_text' => 'Hablamos de la agenda.', 'gamma_prompt_text' => $json_v3($filas_sin_marca), 'system_prompt_text' => '', 'created_at' => current_time('mysql')]);
$props[] = $prop_json = (int) $wpdb->insert_id;
$ctx_json = at_rt_contexto_redaccion(rt_red_seg(['propuesta_id' => $prop_json]));
$ctx_texto = wp_json_encode($ctx_json, JSON_UNESCAPED_UNICODE);
ok(str_contains($ctx_json['propuesta']['resumen'], 'Una agenda que responde sola') && !preg_match('/Servicio-Precio|dólares|pesos|IVA|correo-secreto|pricing/u', $ctx_texto), 'contexto de una propuesta v3 real (JSON): la IA recibe la prosa y ningún precio ni correo');
ok(count($ctx['historial']) === 10 && $ctx['historial'][0] === 'Último contacto' && !in_array('Cotización de $50.000 enviada', $ctx['historial'], true), 'historial: los 10 títulos más recientes y sin montos');
ok($ctx['notas'] === 'Quiere algo simple' && $ctx['pedido'] === 'Habla de la agenda', 'notas y pedido pierden las líneas con montos');
$con_montos_ctx = array_filter([$ctx['propuesta']['transcripcion'], $ctx['propuesta']['resumen'], $ctx['notas'], $ctx['pedido'], implode("\n", $ctx['historial']), implode("\n", $ctx['textos_actuales'])], 'at_rt_tiene_montos');
ok($con_montos_ctx === [], 'ningún campo del contexto trae montos');
$sin_prop = at_rt_contexto_redaccion($a);
ok($sin_prop['propuesta'] === ['status' => '', 'flujo' => null, 'transcripcion' => '', 'resumen' => ''] && $sin_prop['historial'] === [], 'sin propuesta ni ficha CRM: campos vacíos');

$res = rt_red_peticion('GET', $c, 'contexto', null, false);
ok($res->get_status() === 401, 'contexto sin la cabecera X-AT-Secret: 401');
$res = rt_red_peticion('GET', $c, 'contexto');
ok($res->get_status() === 200 && $res->get_data()['id'] === $c && $res->get_data()['reglas']['max_caracteres'] === 600, 'contexto con la cabecera: 200 y los datos');
ok(rt_red_peticion('GET', PHP_INT_MAX, 'contexto')->get_status() === 404, 'contexto de un seguimiento inexistente: 404');
$res = rt_red_peticion('POST', $c, 'contexto', []);
ok($res->get_status() === 404 || $res->get_status() === 405, 'el contexto solo se lee (GET)');

// ---- Callback ----
$d = rt_red_seg(['imagen_url' => '']);
ok(rt_red_callback($d, rt_red_bien(), false)->get_status() === 401, 'callback sin la cabecera X-AT-Secret: 401');
ok(rt_red_callback(PHP_INT_MAX, rt_red_bien(['generacion' => str_repeat('a', 16)]))->get_status() === 404, 'callback de un seguimiento inexistente: 404');
$simular = true;
at_rt_pedir_redaccion($d, '');
$simular = false;
$base_d = at_rt_obtener($d);
$gen = $base_d['redaccion']['generacion'];
// 409: no cambia nada, ni el estado. 422 del pedido vigente: no cambia ningún texto, render ni nota de Luis; solo la redacción pasa a error con el motivo.
$identico = static function () use ($d, &$base_d): bool { $n = at_rt_obtener($d); return $n['textos'] === $base_d['textos'] && $n['redaccion'] === $base_d['redaccion'] && $n['render'] === $base_d['render'] && $n['notas'] === $base_d['notas']; };
$sin_cambios = static function () use ($d, &$base_d): bool { $n = at_rt_obtener($d); return $n['textos'] === $base_d['textos'] && $n['render'] === $base_d['render'] && $n['notas'] === $base_d['notas'] && array_diff_key($n['redaccion'], ['estado' => 1, 'nota' => 1]) === array_diff_key($base_d['redaccion'], ['estado' => 1, 'nota' => 1]); };
foreach (['generación de otro pedido' => str_repeat('a', 16), 'generación que no es hex16' => 'zz', 'generación vacía' => ''] as $caso => $g) {
	ok(rt_red_callback($d, rt_red_bien(['generacion' => $g]))->get_status() === 409 && $identico(), "callback con {$caso}: 409 y nada cambia");
}
$sin_gen = new WP_REST_Request('POST', '/automatiza-tech/v1/retomar/' . $d . '/redaccion');
$sin_gen->set_header('X-AT-Secret', AT_REST_SECRET); $sin_gen->set_header('Content-Type', 'application/json'); $sin_gen->set_body(wp_json_encode(rt_red_bien()));
ok(rest_do_request($sin_gen)->get_status() === 409 && $identico(), 'callback sin generación: 409');
ok(rt_red_callback($d, ['ok' => 'x', 'textos' => 'mal', 'generacion' => str_repeat('a', 16)])->get_status() === 409 && $identico() && at_rt_obtener($d)['redaccion']['estado'] === 'pendiente', 'respuesta mal formada de OTRO pedido: 409 y no marca error');

$invalidos = [
	'texto de 601 caracteres' => ['textos' => ['quedamos' => str_repeat('a', 601)]],
	'texto de 601 caracteres con tildes' => ['textos' => ['proponemos' => str_repeat('á', 601)]],
	'texto de 7 líneas' => ['textos' => ['partir' => implode("\n", array_fill(0, 7, 'línea'))]],
	'texto vacío' => ['textos' => ['quedamos' => '   ']],
	'texto que no es cadena' => ['textos' => ['quedamos' => ['x']]],
	'recomendación de 301 caracteres' => ['textos' => ['recomendacion' => str_repeat('a', 301)]],
	'recomendación de 4 líneas' => ['textos' => ['recomendacion' => "a\nb\nc\nd"]],
	'enlace interno en un texto' => ['textos' => ['quedamos' => 'Mira https://oculto.easypanel.host/demo para verlo']],
	'host del renderer en un texto' => ['textos' => ['proponemos' => 'Mira https://' . at_pt_host_renderer() . '/p/x']],
	'textos que no es objeto' => ['textos' => 'hola'],
	'sin textos' => ['textos' => null],
	'lámina desconocida' => ['image_briefs' => [['slide' => 'final', 'prompt' => 'x']]],
	'lámina repetida' => ['image_briefs' => [['slide' => 'portada', 'prompt' => 'x'], ['slide' => 'portada', 'prompt' => 'y']]],
	'encargo que no es objeto' => ['image_briefs' => ['portada']],
	'encargo sin prompt' => ['image_briefs' => [['slide' => 'portada']]],
	'prompt de 1201 caracteres' => ['image_briefs' => [['slide' => 'portada', 'prompt' => str_repeat('a', 1201)]]],
	'más de cuatro encargos' => ['image_briefs' => [['slide' => 'portada', 'prompt' => 'a'], ['slide' => 'quedamos', 'prompt' => 'b'], ['slide' => 'cierre', 'prompt' => 'c'], ['slide' => 'demo', 'prompt' => 'd'], ['slide' => 'demo', 'prompt' => 'e']]],
	'enlace interno en un encargo' => ['image_briefs' => [['slide' => 'portada', 'prompt' => 'Foto como https://oculto.easypanel.host/x']]],
	'image_briefs que no es lista' => ['image_briefs' => 'portada'],
	'ok que no es booleano' => ['ok' => 'true'],
];
foreach ($invalidos as $caso => $cambio) {
	$cuerpo = rt_red_bien();
	foreach ($cambio as $k => $v) {
		if ($k === 'textos' && is_array($v)) { $cuerpo['textos'] = $v + $cuerpo['textos']; }
		elseif ($v === null) { unset($cuerpo[$k]); }
		else { $cuerpo[$k] = $v; }
	}
	$res422 = rt_red_callback($d, $cuerpo);
	$rd = at_rt_obtener($d)['redaccion'];
	ok($res422->get_status() === 422 && $sin_cambios() && $rd['estado'] === 'error' && $rd['nota'] !== '' && !str_contains($rd['nota'], 'http') && $rd['generacion'] === $gen, "callback inválido ({$caso}): 422, no se guarda ningún texto y la redacción queda en error con el motivo");
}
// Una respuesta mal formada del pedido vigente marca error con el motivo (no queda «pendiente» para siempre); la corregida con la misma generación se acepta.
$q = rt_red_seg();
$simular = true; at_rt_pedir_redaccion($q, ''); $simular = false;
$gq = at_rt_obtener($q)['redaccion']['generacion'];
ok(at_rt_obtener($q)['redaccion']['estado'] === 'pendiente', 'antes de responder la redacción está pendiente');
$res = rt_red_callback($q, rt_red_bien(['textos' => ['quedamos' => str_repeat('a', 601)] + rt_red_bien()['textos']]));
$rq = at_rt_obtener($q)['redaccion'];
ok($res->get_status() === 422 && $rq['estado'] === 'error' && $rq['generacion'] === $gq && stripos($rq['nota'], 'quedamos') !== false && at_rt_obtener($q)['textos']['quedamos'] === '[PRUEBA] Quedamos antes', 'respuesta mal formada: 422, estado error con el motivo en la nota y textos intactos');
ok(rt_red_callback($q, rt_red_bien())->get_status() === 200 && at_rt_obtener($q)['redaccion']['estado'] === 'lista' && at_rt_obtener($q)['redaccion']['nota'] === '[PRUEBA] Redactado con IA', 'la respuesta corregida con la misma generación se acepta aunque el estado fuera error');
$antes_q = at_rt_obtener($q);
ok(rt_red_callback($q, ['ok' => 'x'])->get_status() === 409 && rt_red_callback($q, ['ok' => true, 'textos' => 'mal'])->get_status() === 409, 'ya resuelta: ni una respuesta mal formada vuelve a tocar nada (409)');
$despues_q = at_rt_obtener($q);
ok($despues_q['textos'] === $antes_q['textos'] && $despues_q['redaccion'] === $antes_q['redaccion'] && $despues_q['render'] === $antes_q['render'], 'ya resuelta: textos, redacción y render intactos');
// La lámina «quedamos» se acepta siempre que el seguimiento la pida, con o sin imagen propia; sin pedirla, se rechaza.
$_q = ['image_briefs' => [['slide' => 'quedamos', 'prompt' => '[PRUEBA] Escena tranquila del rubro, sin texto']]] + rt_red_bien();
ok(!is_wp_error(at_rt_red_validar($_q, ['portada', 'quedamos', 'cierre'])) && at_rt_red_validar($_q, ['portada', 'quedamos', 'cierre'])['image_briefs'][0]['slide'] === 'quedamos', 'lámina quedamos con foto pedida: se acepta');
ok(is_wp_error(at_rt_red_validar($_q, ['portada', 'cierre'])), 'lámina quedamos sin que se haya pedido: se rechaza');
// La lámina «demo» solo se acepta cuando el seguimiento no trae imagen propia.
$demo = ['image_briefs' => [['slide' => 'demo', 'prompt' => '[PRUEBA] Pantalla de una agenda, sin texto']]];
at_rt_guardar($d, ['imagen_url' => 'https://example.com/captura.png']);
$base_d = at_rt_obtener($d);
ok(rt_red_callback($d, rt_red_bien($demo))->get_status() === 422 && $sin_cambios(), 'lámina demo con imagen del seguimiento: 422');
at_rt_guardar($d, ['imagen_url' => '']);
$base_d = at_rt_obtener($d);

// Montos: la primera vez se vuelve a pedir una sola vez; la segunda queda para revisión manual.
$con_montos = rt_red_bien(['textos' => ['proponemos' => 'Lo dejamos en $150.000 al mes.'] + rt_red_bien()['textos']]);
$gen1 = $gen;
$res = rt_red_callback($d, $con_montos);
$m1 = at_rt_obtener($d);
ok($res->get_status() === 200 && $res->get_data()['reintento'] === true, 'montos la primera vez: respuesta {reintento:true}');
ok($m1['redaccion']['intentos'] === 2 && stripos($m1['redaccion']['pedido'], 'sin montos') !== false && !at_rt_tiene_montos($m1['redaccion']['pedido']), 'el reintento sube intentos a 2 y el pedido dice «sin montos» sin traer montos');
ok(preg_match('/^[a-f0-9]{16}$/D', $m1['redaccion']['generacion']) === 1 && $m1['redaccion']['generacion'] !== $gen1 && $m1['redaccion']['estado'] === 'error', 'el reintento tiene generación nueva y, con HTTP bloqueado, deja estado error');
ok($m1['textos'] === $base_d['textos'] && $m1['render'] === $base_d['render'], 'con montos no se guardan los textos ni se pide render');
ok(rt_red_callback($d, rt_red_bien(['generacion' => $gen1]))->get_status() === 409, 'la respuesta del primer pedido ya no vale después del reintento');
$res = rt_red_callback($d, $con_montos);
$m2 = at_rt_obtener($d);
ok($res->get_status() === 200 && ($res->get_data()['reintento'] ?? false) !== true && $m2['redaccion']['estado'] === 'revision', 'montos la segunda vez (con la generación nueva aunque el estado fuera error): estado revision');
ok($m2['textos'] === $base_d['textos'] && $m2['redaccion']['intentos'] === 2 && $m2['redaccion']['nota'] !== '' && !at_rt_tiene_montos($m2['redaccion']['nota']) && $m2['render'] === $base_d['render'], 'en revision los textos no se guardan, hay nota y no se pide render');
ok(rt_red_callback($d, rt_red_bien())->get_status() === 409, 'una respuesta ya resuelta (revision) no se vuelve a aceptar');
// El reintento sale de verdad hacia n8n cuando el flujo responde.
$simular = true;
at_rt_pedir_redaccion($d, '[PRUEBA] Pedido de Luis');
$gen_s = at_rt_obtener($d)['redaccion']['generacion'];
$capturado = null;
$res = rt_red_callback($d, rt_red_bien(['image_briefs' => [['slide' => 'portada', 'prompt' => 'Foto con precio $9.990 en un cartel']]]));
$m3 = at_rt_obtener($d)['redaccion'];
ok($res->get_data()['reintento'] === true && $m3['estado'] === 'pendiente' && $capturado['id'] === $d && $capturado['generacion'] === $m3['generacion'] && $m3['generacion'] !== $gen_s && $m3['intentos'] === 2, 'montos en un encargo de foto también cuentan; el reintento viaja a n8n con su generación');
ok(str_starts_with($m3['pedido'], '[PRUEBA] Pedido de Luis') && stripos($m3['pedido'], 'sin montos') !== false, 'el reintento conserva el pedido de Luis y agrega la indicación');
$simular = false;

// Respuesta válida: textos, origen ia, estado lista, encargos y render pedido.
$e = rt_red_seg(['imagen_url' => '', 'notas' => '[PRUEBA] nota de Luis']);
$simular = true;
at_rt_pedir_redaccion($e, '');
$simular = false;
$base_e = at_rt_obtener($e);
$simular_render = true;
$res = rt_red_callback($e, rt_red_bien());
$v = at_rt_obtener($e);
ok($res->get_status() === 200 && $res->get_data()['ok'] === true && $res->get_data()['estado'] === 'lista', 'respuesta válida: 200');
ok($v['textos']['quedamos'] === "Quedamos en retomar el proyecto.\nLa agenda era lo urgente." && $v['textos']['proponemos'] === 'Proponemos partir por lo más simple y medir el resultado.' && $v['textos']['partir'] === 'Podemos partir esta semana con una reunión corta.' && $v['textos']['recomendacion'] === 'Recomendamos partir por la agenda automática.', 'los cuatro textos quedan guardados');
ok($v['textos']['proyecto'] === '[PRUEBA] Proyecto', 'guardar los textos de la IA conserva el resto de las claves');
ok($v['redaccion']['origen'] === 'ia' && $v['redaccion']['estado'] === 'lista' && $v['redaccion']['generacion'] === $base_e['redaccion']['generacion'], 'origen ia, estado lista y la misma generación');
ok($v['redaccion']['image_briefs'] === rt_red_bien()['image_briefs'] && $v['redaccion']['nota'] === '[PRUEBA] Redactado con IA', 'encargos de foto y nota guardados');
ok(in_array($v['render']['estado'], ['pendiente', 'error'], true), 'se pide la presentación: render pendiente o error');
ok($v['render']['estado'] === 'pendiente' && $capturado_render['id'] === $e && array_column($capturado_render['render']['image_briefs'], 'prompt', 'slide') === array_column(rt_red_bien()['image_briefs'], 'prompt', 'slide') && $capturado_render['render']['recomendacion'] === 'Recomendamos partir por la agenda automática.', 'el render viaja con los encargos y la recomendación de la IA');
ok($v['notas'] === '[PRUEBA] nota de Luis', 'las notas de Luis no se tocan');
$render_antes = $v['render'];
ok(rt_red_callback($e, rt_red_bien(['textos' => ['quedamos' => 'Otro intento tardío'] + rt_red_bien()['textos']]))->get_status() === 409 && at_rt_obtener($e)['textos']['quedamos'] === $v['textos']['quedamos'] && at_rt_obtener($e)['render'] === $render_antes, 'repetir un callback ya aceptado: 409 y no pisa lo guardado');
$simular_render = false;

// Texto con etiquetas y espacios: se guarda limpio, y sin recomendación ni encargos también es válido.
$g = rt_red_seg();
$simular = true; at_rt_pedir_redaccion($g, ''); $simular = false;
$min = ['ok' => true, 'textos' => ['quedamos' => "  Quedamos así<script>alert(1)</script>  ", 'proponemos' => 'Proponemos esto.', 'partir' => 'Partimos así.']];
ok(rt_red_callback($g, $min)->get_status() === 200, 'sin recomendación ni encargos: 200');
$vg = at_rt_obtener($g);
ok($vg['textos']['quedamos'] === 'Quedamos así' && $vg['textos']['recomendacion'] === '' && $vg['redaccion']['image_briefs'] === [] && $vg['redaccion']['nota'] === '', 'texto sin etiquetas ni espacios sobrantes; recomendación vacía; sin encargos');
// Los bordes exactos pasan.
$h = rt_red_seg();
$simular = true; at_rt_pedir_redaccion($h, ''); $simular = false;
$borde = ['ok' => true, 'textos' => ['quedamos' => str_repeat('á', 600), 'proponemos' => implode("\n", array_fill(0, 6, 'línea')), 'partir' => 'a', 'recomendacion' => implode("\n", array_fill(0, 3, str_repeat('b', 98)))], 'image_briefs' => [['slide' => 'portada', 'prompt' => str_repeat('p', 1200)], ['slide' => 'cierre', 'prompt' => 'c'], ['slide' => 'demo', 'prompt' => 'd']]];
ok(rt_red_callback($h, $borde)->get_status() === 200 && at_rt_obtener($h)['redaccion']['estado'] === 'lista', '600 caracteres, 6 líneas, recomendación de 3 líneas, prompt de 1200 y tres láminas: pasan');

// ok:false: nota limpia, sin enlaces; una respuesta buena con la misma generación se acepta aunque el estado sea error.
$i = rt_red_seg();
$simular = true; at_rt_pedir_redaccion($i, ''); $simular = false;
$antes_textos = at_rt_obtener($i)['textos'];
$res = rt_red_callback($i, ['ok' => false, 'nota' => "[PRUEBA] Falló en https://oculto.easypanel.host/x y en http://example.com/y?a=1\n<b>fin</b>"]);
$vi = at_rt_obtener($i);
ok($res->get_status() === 200 && $vi['redaccion']['estado'] === 'error' && substr_count($vi['redaccion']['nota'], '[enlace omitido]') === 2 && stripos($vi['redaccion']['nota'], 'easypanel') === false && !str_contains($vi['redaccion']['nota'], '<b>') && $vi['textos'] === $antes_textos, 'ok:false: estado error, nota sin enlaces ni etiquetas y textos intactos');
ok(rt_red_callback($i, rt_red_bien())->get_status() === 200 && at_rt_obtener($i)['redaccion']['estado'] === 'lista' && at_rt_obtener($i)['textos']['proponemos'] === rt_red_bien()['textos']['proponemos'], 'un fallo de transporte puede ser un falso negativo: la respuesta buena con la misma generación se acepta');
$j = rt_red_seg();
$simular = true; at_rt_pedir_redaccion($j, ''); $simular = false;
rt_red_callback($j, ['ok' => false, 'nota' => str_repeat('n', 1500)]);
ok(mb_strlen(at_rt_obtener($j)['redaccion']['nota']) === 1000, 'la nota se corta a 1000 caracteres');

// Un pedido nuevo invalida el anterior y reinicia los intentos.
$k = rt_red_seg();
$simular = true;
at_rt_pedir_redaccion($k, 'primero');
$gen_k1 = at_rt_obtener($k)['redaccion']['generacion'];
rt_red_callback($k, $con_montos);
ok(at_rt_obtener($k)['redaccion']['intentos'] === 2, 'montos: segundo intento');
at_rt_pedir_redaccion($k, 'segundo');
$rk = at_rt_obtener($k)['redaccion'];
$simular = false;
ok($rk['intentos'] === 1 && $rk['pedido'] === 'segundo' && $rk['generacion'] !== $gen_k1 && $rk['estado'] === 'pendiente', 'un pedido nuevo reinicia los intentos, cambia la generación y cambia el pedido');
ok(rt_red_callback($k, rt_red_bien(['generacion' => $gen_k1]))->get_status() === 409, 'la respuesta del pedido anterior ya no vale');

// ---- Ruling 7: cada pedido guarda pedida_at (hora de Chile) para que el reloj sepa si la redacción quedó atascada ----
$chile = static fn(): int => (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->getTimestamp();
$q = rt_red_seg();
$timeouts = [];
// Antes que el bloqueo del bootstrap: anota el timeout de cada pedido al flujo de redacción, sin responder.
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$timeouts) { if ($url === AT_N8N_RETOMAR_REDACTAR) { $timeouts[] = $args['timeout'] ?? null; } return $pre; }, 1, 3);
$simular = true; at_rt_pedir_redaccion($q, '[PRUEBA] Pedido'); $simular = false;
ok($timeouts === [15], 'Ruling 12: un pedido del panel o de la aprobación mantiene su timeout de 15 s');
$pa = (string) (at_rt_obtener($q)['redaccion']['pedida_at'] ?? '');
ok(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $pa) === 1 && at_rt_ahora($pa) !== null && abs(at_rt_ahora($pa)->getTimestamp() - $chile()) <= 120, 'el pedido guarda pedida_at con la hora de Chile');
$simular = true; at_rt_pedir_redaccion($q, '[PRUEBA] Pedido', '2026-10-19 08:00:00', at_rt_timeout_reloj()); $simular = false;
ok($timeouts === [15, 5] && at_rt_timeout_reloj() === 5, 'el reloj pide con su timeout de 5 s');
$simular = true; at_rt_pedir_redaccion($q, '[PRUEBA] Pedido', '2026-10-19 09:05:00'); $simular = false;
ok((at_rt_obtener($q)['redaccion']['pedida_at'] ?? '') === '2026-10-19 09:05:00', 'el reloj pasa su hora y queda como pedida_at');
at_rt_pedir_redaccion($q, '', '2026-10-19 10:05:00'); // HTTP bloqueado: el pedido falla
$rq = at_rt_obtener($q)['redaccion'];
ok($rq['estado'] === 'error' && ($rq['pedida_at'] ?? '') === '2026-10-19 10:05:00', 'un pedido que falla también deja su pedida_at');
// El reintento por montos también es un pedido: renueva pedida_at.
at_rt_guardar($q, ['redaccion' => ['estado' => 'pendiente', 'generacion' => str_repeat('f', 16), 'intentos' => 1, 'pedido' => '', 'pedida_at' => '2026-01-01 00:00:00']]);
$simular = true; rt_red_callback($q, $con_montos); $simular = false;
$rq = at_rt_obtener($q)['redaccion'];
ok(($rq['intentos'] ?? 0) === 2 && ($rq['pedida_at'] ?? '') !== '2026-01-01 00:00:00' && at_rt_ahora((string) ($rq['pedida_at'] ?? '')) !== null && abs(at_rt_ahora((string) $rq['pedida_at'])->getTimestamp() - $chile()) <= 120, 'el reintento por montos renueva pedida_at');
exigir('at_rt_red_atascada');
// ¿Quedó atascada? Vacía, en error, o pendiente hace más de 6 h; y el reloj no la volvió a pedir en las últimas 24 h.
$casos = [
	'vacía' => [[], true],
	'error' => [['estado' => 'error', 'pedida_at' => '2026-10-19 09:00:00'], true],
	'error sin pedida_at' => [['estado' => 'error'], true],
	'error con reintento del reloj hace 2 h' => [['estado' => 'error', 'pedida_at' => '2026-10-19 08:00:00', 'reintento_at' => '2026-10-19 08:00:00'], false],
	'error con reintento del reloj hace 24 h' => [['estado' => 'error', 'pedida_at' => '2026-10-18 10:00:00', 'reintento_at' => '2026-10-18 10:00:00'], true],
	'pendiente hace 7 h' => [['estado' => 'pendiente', 'pedida_at' => '2026-10-19 03:00:00'], true],
	'pendiente hace 6 h justas' => [['estado' => 'pendiente', 'pedida_at' => '2026-10-19 04:00:00'], false],
	'pendiente hace 5 h' => [['estado' => 'pendiente', 'pedida_at' => '2026-10-19 05:00:00'], false],
	'pendiente sin pedida_at (anterior a esta versión)' => [['estado' => 'pendiente'], true],
	'pendiente hace 7 h, reintentada por el reloj hace 7 h' => [['estado' => 'pendiente', 'pedida_at' => '2026-10-19 03:00:00', 'reintento_at' => '2026-10-19 03:00:00'], false],
	'lista' => [['estado' => 'lista', 'pedida_at' => '2026-10-01 00:00:00'], false],
	'revisión (necesita a Luis)' => [['estado' => 'revision', 'pedida_at' => '2026-10-01 00:00:00'], false],
];
foreach ($casos as $caso => [$red, $esperado]) {
	ok(at_rt_red_atascada($red, '2026-10-19 10:00:00') === $esperado, 'redacción atascada: ' . $caso . ($esperado ? ' → se vuelve a pedir' : ' → no'));
}
ok(at_rt_red_atascada([], 'no es una fecha') === false, 'con una hora ilegible no se pide nada');
fin();
