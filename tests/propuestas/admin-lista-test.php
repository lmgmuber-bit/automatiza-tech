<?php
// Correr: php tests/propuestas/admin-lista-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }
$esc_like = function ($s) { return addcslashes($s, '_%\\'); };

// Estados
ok(at_pa_grupo_de_estado('draft') === 'borrador', 'draft viejo cae en Borrador');
ok(at_pa_grupo_de_estado('sent') === 'enviadas', 'sent es Enviadas');
ok(at_pa_grupo_de_estado('raro') === 'otros', 'estado desconocido va a otros');
ok(at_pa_grupo_de_estado(null) === 'otros', 'estado nulo va a otros');
ok(at_pa_estado_etiqueta('sent') === ['etiqueta' => 'Enviada', 'clase' => 'at-estado--enviadas'], 'etiqueta de sent');
ok(at_pa_estado_etiqueta('lista')['etiqueta'] === 'Lista para enviar', 'etiqueta de lista');
ok(at_pa_estado_etiqueta('raro') === ['etiqueta' => 'raro', 'clase' => 'at-estado--otros'], 'desconocido se muestra tal cual');
ok(at_pa_estado_etiqueta('')['etiqueta'] === 'Sin estado', 'vacío');
$c = at_pa_contar_grupos(['sent' => 9, 'borrador' => 7, 'pending' => 1, 'draft' => 1, 'error' => 1, '' => 2]);
ok($c['todas'] === 21 && $c['borrador'] === 8 && $c['enviadas'] === 9 && $c['otros'] === 2 && $c['lista'] === 0, 'conteo por grupo');

// Filtros
ok(at_pa_fecha_valida('2026-09-23') === '2026-09-23', 'fecha válida');
ok(at_pa_fecha_valida('2026-02-30') === '' && at_pa_fecha_valida('23-09-2026') === '', 'fechas inválidas');
$f = at_pa_normalizar_filtros([]);
ok($f['s'] === '' && $f['grupo'] === '' && $f['orderby'] === 'created_at' && $f['order'] === 'DESC' && $f['paged'] === 1 && $f['avisos'] === [], 'filtros por defecto');
$f = at_pa_normalizar_filtros(['s' => '  Orly ', 'grupo' => 'enviadas', 'orderby' => 'company_name', 'order' => 'asc', 'paged' => '3']);
ok($f['s'] === 'Orly' && $f['grupo'] === 'enviadas' && $f['orderby'] === 'company_name' && $f['order'] === 'ASC' && $f['paged'] === 3, 'filtros leídos');
ok(at_pa_normalizar_filtros(['grupo' => 'otros'])['grupo'] === 'otros', 'otros es filtrable');
ok(at_pa_normalizar_filtros(['grupo' => 'hack'])['grupo'] === '', 'grupo inválido se ignora');
ok(at_pa_normalizar_filtros(['orderby' => 'id; DROP TABLE x'])['orderby'] === 'created_at', 'orderby fuera de la lista blanca');
ok(at_pa_normalizar_filtros(['paged' => '-4'])['paged'] === 1, 'página mínima 1');
$f = at_pa_normalizar_filtros(['desde' => '2026-13-01']);
ok($f['desde'] === '' && count($f['avisos']) === 1, 'fecha inválida avisa');
$f = at_pa_normalizar_filtros(['desde' => '2026-09-30', 'hasta' => '2026-09-01']);
ok($f['desde'] === '2026-09-01' && $f['hasta'] === '2026-09-30' && count($f['avisos']) === 1, 'fechas al revés se ordenan');
ok(mb_strlen(at_pa_normalizar_filtros(['s' => str_repeat('á', 300)])['s']) === 100, 'búsqueda recortada a 100');

// WHERE
$w = at_pa_where(at_pa_normalizar_filtros([]), $esc_like);
ok($w === ['sql' => '', 'args' => []], 'sin filtros no hay WHERE');
$w = at_pa_where(at_pa_normalizar_filtros(['s' => '50%_x']), $esc_like);
ok(strpos($w['sql'], 'company_name LIKE %s OR client_name LIKE %s OR client_email LIKE %s OR phone LIKE %s OR unique_link_id LIKE %s') !== false, 'busca en 5 columnas');
ok(count($w['args']) === 5 && $w['args'][0] === '%50\%\_x%', 'LIKE escapado');
$w = at_pa_where(at_pa_normalizar_filtros(['grupo' => 'borrador', 'desde' => '2026-09-01', 'hasta' => '2026-09-30']), $esc_like);
ok($w['sql'] === 'WHERE status IN (%s,%s) AND created_at >= %s AND created_at <= %s', 'WHERE de grupo y fechas');
ok($w['args'] === ['borrador', 'draft', '2026-09-01 00:00:00', '2026-09-30 23:59:59'], 'args de grupo y fechas');
$w = at_pa_where(at_pa_normalizar_filtros(['grupo' => 'otros']), $esc_like);
ok(strpos($w['sql'], '(status IS NULL OR status NOT IN (') === 0 + strlen('WHERE ') && count($w['args']) === 8, 'otros = ni nulo ni conocido');

// ORDER y límites
ok(at_pa_order_sql(at_pa_normalizar_filtros([])) === 'ORDER BY created_at DESC, id DESC', 'orden por defecto');
ok(at_pa_order_sql(at_pa_normalizar_filtros(['orderby' => 'status', 'order' => 'asc'])) === 'ORDER BY status ASC, id DESC', 'orden por estado');
ok(at_pa_limites(1, 20, 19) === ['per_page' => 20, 'paged' => 1, 'offset' => 0, 'total_pages' => 1], 'una página');
ok(at_pa_limites(9, 20, 45) === ['per_page' => 20, 'paged' => 3, 'offset' => 40, 'total_pages' => 3], 'página más allá del final va a la última');
ok(at_pa_limites(1, 1000, 5)['per_page'] === 200 && at_pa_limites(1, 1, 5)['per_page'] === 5, 'per_page acotado 5..200');
ok(at_pa_limites(1, 20, 0)['total_pages'] === 1, 'sin filas hay 1 página');

// Fechas y volver
ok(at_pa_fecha_corta('2026-09-23 16:03:48') === '23-sep-2026', 'fecha corta');
ok(at_pa_fecha_corta('2025-12-09 23:56:56') === '9-dic-2025', 'fecha corta sin cero');
ok(at_pa_fecha_corta('basura') === '' && at_pa_fecha_corta(null) === '', 'fecha inválida vacía');
ok(at_pa_query_volver(at_pa_normalizar_filtros([])) === [], 'sin filtros no hay volver');
ok(at_pa_query_volver(at_pa_normalizar_filtros(['s' => 'x', 'paged' => 2, 'orderby' => 'status', 'order' => 'asc'])) === ['s' => 'x', 'orderby' => 'status', 'order' => 'asc', 'paged' => 2], 'volver conserva filtros');
ok(at_pa_volver_desde_param('s=Orly&grupo=enviadas&paged=2&evil=<script>') === ['s' => 'Orly', 'grupo' => 'enviadas', 'paged' => 2], 'volver saneado');
ok(at_pa_volver_desde_param('') === [], 'volver vacío');

// Ficha
ok(array_keys(at_pa_pestanas(true)) === ['resumen', 'cliente', 'revision', 'contenido', 'seguimiento', 'envio'], 'pestañas v3');
ok(array_keys(at_pa_pestanas(false)) === ['resumen', 'cliente', 'contenido', 'seguimiento', 'envio'], 'pestañas viejas sin revisión');
$json = json_encode(['pricing_rows' => [['service' => 'Fase 1', 'price_label' => '$250.000'], ['service' => '', 'price_label' => 'x'], 'basura']]);
ok(at_pa_precios_de_payload($json) === [['service' => 'Fase 1', 'price_label' => '$250.000']], 'precios del payload');
ok(at_pa_precios_de_payload('texto viejo de Gamma') === [] && at_pa_precios_de_payload(null) === [], 'payload no JSON');
$log = json_encode([['fecha' => '2026-09-24 01:00:00', 'comentario' => 'uno'], ['fecha' => '2026-09-24 02:00:00', 'comentario' => 'dos']]);
ok(at_pa_ultimo_comentario($log) === ['texto' => 'dos', 'fecha' => '2026-09-24 02:00:00', 'total' => 2], 'último comentario');
ok(at_pa_ultimo_comentario(null) === ['texto' => '', 'fecha' => '', 'total' => 0], 'sin comentarios');
ok(at_pa_siguiente_paso('v3', 'borrador') === ['tab' => 'revision', 'texto' => 'Revisar precios y aprobar'], 'v3 borrador');
ok(at_pa_siguiente_paso('v3', 'error') === ['tab' => 'revision', 'texto' => 'Revisar y reintentar'], 'v3 error');
ok(at_pa_siguiente_paso('v3', 'generando') === ['tab' => 'revision', 'texto' => 'Ver estado o destrabar'], 'v3 generando');
ok(at_pa_siguiente_paso('v3', 'lista') === ['tab' => 'envio', 'texto' => 'Enviar al cliente'], 'v3 lista');
ok(at_pa_siguiente_paso('v3', 'sent') === null && at_pa_siguiente_paso(null, 'sent') === null, 'enviada no tiene siguiente paso');
ok(at_pa_siguiente_paso(null, 'pending') === ['tab' => 'envio', 'texto' => 'Enviar al cliente'], 'vieja pendiente');

echo $fallas ? "$fallas FALLA(S)\n" : "TODO OK\n";
exit($fallas ? 1 : 0);
