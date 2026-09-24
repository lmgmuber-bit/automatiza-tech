# Módulo Propuestas del wp-admin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cambiar el módulo Propuestas del wp-admin a una lista completa, con buscador, filtros y paginación, y a una ficha por propuesta en 6 pestañas, responsive, sin cambiar el comportamiento de guardar, enviar ni del flujo v3.

**Architecture:** La misma página (`admin.php?page=automatiza-proposals`) cambia de ruteador:

- sin `edit_id` → lista (`WP_List_Table`);
- con `edit_id` → ficha;
- con `&clasico=1` → el módulo viejo, idéntico.

Los procesos de POST de hoy se extraen tal cual a funciones de `acciones.php`, y las funciones puras de consulta y formato van en `consultas.php`, con pruebas. Estilos y JS van en archivos propios que solo se cargan en esta página.

**Tech Stack:** PHP (PROD: PHP 8.3.33 y WordPress 7.1.2; local: WordPress 6.9.4), `WP_List_Table`, CSS y JS sin dependencias.

Spec: `Docs/superpowers/specs/2026-09-24-modulo-propuestas-admin-design.md`.

## Global Constraints

- **Nunca tocar `wp-content/themes/automatiza-tech/functions.php`:** PROD va adelantado con Finanzas AT.
- **Misma URL y parámetros:**
  - slug `automatiza-proposals`;
  - `edit_id=N` abre la ficha; los correos de n8n enlazan `admin.php?page=automatiza-proposals&edit_id=`;
  - `delete_id` + `_wpnonce` (acción `delete_proposal_<id>`) borra una.
- **Mismos nombres de campo y nonces que hoy:**
  - `proposal_id`, `automatiza_proposal_nonce` (acción `save_proposal`);
  - `client_email`, `client_name`, `company_name`, `phone`, `gamma_url`, `n8n_url`, `pdf_file`, `gamma_prompt`, `system_prompt`;
  - `send_email`, `email_subject`, `email_intro`, `email_highlight`, `email_closing`;
  - `at_precio[i][service|price_label|emphasis]`, `at_nota_precio`, `at_comentario`;
  - `at_v3_accion` = `cambios|aprobar|destrabar`, con nonce `_wpnonce` de acción `at_v3_<id>`.
- **Cero cambios de base:** sin migraciones ni columnas nuevas. Tabla `{$wpdb->prefix}automatiza_propuestas`.
- **Seguridad:**
  - Todo SQL con `$wpdb->prepare`; LIKE con `$wpdb->esc_like`; ORDER BY solo por lista blanca.
  - Toda salida escapada (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`).
  - `manage_options` para ver y actuar.
- **Fines de línea:** `inc/admin-proposals.php` es CRLF en la copia de trabajo y debe seguir así. Los archivos nuevos pueden ser LF.
- **Textos de la interfaz en español de Chile.** Nombres de funciones nuevas con prefijo `at_pa_`.
- **No desplegar, no conectarse a servidores, no `git stash`, `clean` ni `push` desde los subagentes.** Los despliegues los hace el controlador con autorización de Luis.
- **Commits:** `git commit -m "<asunto>" -m "Co-Authored-By: <modelo que corre> <noreply@anthropic.com>"`. Se agregan archivos por ruta; nunca `git add -A`.

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php` | Crear | Funciones puras: estados, filtros, WHERE, ORDER, límites, fechas, volver, pestañas, resumen |
| `tests/propuestas/admin-lista-test.php` | Crear | Pruebas de `consultas.php` sin WordPress |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/clasico.php` | Crear | La página vieja, intacta, renombrada `automatiza_tech_proposals_page_clasico()` |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php` | Crear | Despachador y procesos extraídos tal cual (borrar una, borrar varias, v3, guardar) |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/lista.php` | Crear | `AT_Propuestas_Lista extends WP_List_Table` + `at_pa_render_lista()` |
| `wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php` | Crear | `at_pa_render_ficha()` con 6 pestañas |
| `wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css` | Crear | Estilos del módulo |
| `wp-content/themes/automatiza-tech/assets/js/propuestas-admin.js` | Crear | Pestañas, «Siguiente paso», Enter en precios v3, Copiar transcripción |
| `wp-content/themes/automatiza-tech/inc/admin-proposals.php` | Modificar | Solo cargador: constantes, `at_v3_llamar_n8n()`, menú, opciones de pantalla, assets, ruteador |

---

### Task 1: Funciones puras de consulta y formato (`consultas.php`)

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php`
- Test: `tests/propuestas/admin-lista-test.php`

**Interfaces:**
- Consumes: nada. PHP puro, sin WordPress.
- Produces (las usan las tareas 3 y 4):
  - `at_pa_grupos_estado(): array` → `['borrador'=>['etiqueta'=>'Borrador','estados'=>['borrador','draft']], 'ajustando'=>…, 'generando'=>…, 'lista'=>…, 'enviadas'=>['etiqueta'=>'Enviadas','estados'=>['sent']], 'pendiente'=>…, 'error'=>…]`
  - `at_pa_grupo_de_estado(?string $status): string` → clave del grupo o `'otros'`
  - `at_pa_estado_etiqueta(?string $status): array{etiqueta:string, clase:string}`
  - `at_pa_contar_grupos(array $conteos_por_status): array` → claves `todas`, cada grupo y `otros`
  - `at_pa_fecha_valida(string $v): string`
  - `at_pa_normalizar_filtros(array $get): array{s,grupo,desde,hasta,orderby,order,paged,avisos}`
  - `at_pa_where(array $f, callable $esc_like): array{sql:string, args:array}`
  - `at_pa_order_sql(array $f): string`
  - `at_pa_limites(int $paged, int $per_page, int $total): array{per_page,paged,offset,total_pages}`
  - `at_pa_fecha_corta(?string $mysql): string`
  - `at_pa_query_volver(array $f): array`
  - `at_pa_volver_desde_param(string $volver): array`
  - `at_pa_pestanas(bool $es_v3): array`
  - `at_pa_precios_de_payload(?string $json): array`
  - `at_pa_ultimo_comentario(?string $feedback_log): array{texto,fecha,total}`
  - `at_pa_siguiente_paso(?string $flujo, ?string $status): ?array{tab,texto}`

- [ ] **Step 1: Escribir la prueba que falla** — crear `tests/propuestas/admin-lista-test.php`:

```php
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
```

- [ ] **Step 2: Correr y ver que falla**

Run: `php tests/propuestas/admin-lista-test.php`
Expected: error fatal porque `consultas.php` no existe.

- [ ] **Step 3: Implementar** — crear `wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php`:

```php
<?php
/**
 * Módulo Propuestas del wp-admin: funciones puras de consulta y formato.
 * Sin WordPress, para probarlas con: php tests/propuestas/admin-lista-test.php
 * Spec: Docs/superpowers/specs/2026-09-24-modulo-propuestas-admin-design.md
 */

/** Grupos de estado de la lista, en el orden en que se muestran. */
function at_pa_grupos_estado(): array {
	return [
		'borrador'  => ['etiqueta' => 'Borrador', 'estados' => ['borrador', 'draft']],
		'ajustando' => ['etiqueta' => 'Ajustando', 'estados' => ['ajustando']],
		'generando' => ['etiqueta' => 'Generando', 'estados' => ['generando']],
		'lista'     => ['etiqueta' => 'Lista para enviar', 'estados' => ['lista']],
		'enviadas'  => ['etiqueta' => 'Enviadas', 'estados' => ['sent']],
		'pendiente' => ['etiqueta' => 'Pendiente', 'estados' => ['pending']],
		'error'     => ['etiqueta' => 'Error', 'estados' => ['error']],
	];
}

/** Clave del grupo al que pertenece un estado; 'otros' si no se conoce. */
function at_pa_grupo_de_estado(?string $status): string {
	foreach (at_pa_grupos_estado() as $clave => $g) {
		if (in_array((string) $status, $g['estados'], true)) {
			return $clave;
		}
	}
	return 'otros';
}

/** Etiqueta en español (singular) y clase CSS de un estado. */
function at_pa_estado_etiqueta(?string $status): array {
	$singular = [
		'borrador' => 'Borrador', 'draft' => 'Borrador', 'ajustando' => 'Ajustando', 'generando' => 'Generando',
		'lista' => 'Lista para enviar', 'sent' => 'Enviada', 'pending' => 'Pendiente', 'error' => 'Error',
	];
	$s = (string) $status;
	if (isset($singular[$s])) {
		return ['etiqueta' => $singular[$s], 'clase' => 'at-estado--' . at_pa_grupo_de_estado($s)];
	}
	return ['etiqueta' => $s === '' ? 'Sin estado' : $s, 'clase' => 'at-estado--otros'];
}

/** De [status => cantidad] a [todas, cada grupo, otros]. */
function at_pa_contar_grupos(array $conteos): array {
	$r = ['todas' => 0];
	foreach (at_pa_grupos_estado() as $k => $_) {
		$r[$k] = 0;
	}
	$r['otros'] = 0;
	foreach ($conteos as $status => $n) {
		$r['todas'] += (int) $n;
		$r[at_pa_grupo_de_estado((string) $status)] += (int) $n;
	}
	return $r;
}

/** 'AAAA-MM-DD' si es una fecha real; si no, ''. */
function at_pa_fecha_valida(string $v): string {
	$v = trim($v);
	if ($v === '') {
		return '';
	}
	$d = DateTime::createFromFormat('!Y-m-d', $v);
	return ($d && $d->format('Y-m-d') === $v) ? $v : '';
}

/** Filtros de la lista a partir de $_GET (ya sin barras). Nada de lo que venga sale sin validar. */
function at_pa_normalizar_filtros(array $get): array {
	$avisos = [];
	$s = trim((string) ($get['s'] ?? ''));
	$s = mb_substr($s, 0, 100);
	$grupo = (string) ($get['grupo'] ?? '');
	if ($grupo !== '' && $grupo !== 'otros' && !isset(at_pa_grupos_estado()[$grupo])) {
		$grupo = '';
	}
	$fechas = [];
	foreach (['desde' => 'Desde', 'hasta' => 'Hasta'] as $k => $nombre) {
		$crudo = trim((string) ($get[$k] ?? ''));
		$fechas[$k] = at_pa_fecha_valida($crudo);
		if ($crudo !== '' && $fechas[$k] === '') {
			$avisos[] = "La fecha «{$nombre}» no es válida y se ignoró.";
		}
	}
	if ($fechas['desde'] !== '' && $fechas['hasta'] !== '' && $fechas['desde'] > $fechas['hasta']) {
		[$fechas['desde'], $fechas['hasta']] = [$fechas['hasta'], $fechas['desde']];
		$avisos[] = 'Las fechas venían al revés; se ordenaron.';
	}
	$orderby = in_array(($get['orderby'] ?? ''), ['created_at', 'company_name', 'status'], true) ? $get['orderby'] : 'created_at';
	$order = strtolower((string) ($get['order'] ?? '')) === 'asc' ? 'ASC' : 'DESC';
	$paged = max(1, (int) ($get['paged'] ?? 1));
	return [
		's' => $s, 'grupo' => $grupo, 'desde' => $fechas['desde'], 'hasta' => $fechas['hasta'],
		'orderby' => $orderby, 'order' => $order, 'paged' => $paged, 'avisos' => $avisos,
	];
}

/** WHERE con placeholders de $wpdb->prepare y sus argumentos. $esc_like = [$wpdb, 'esc_like']. */
function at_pa_where(array $f, callable $esc_like): array {
	$partes = [];
	$args = [];
	if ($f['s'] !== '') {
		$like = '%' . $esc_like($f['s']) . '%';
		$cols = ['company_name', 'client_name', 'client_email', 'phone', 'unique_link_id'];
		$partes[] = '(' . implode(' OR ', array_map(function ($c) { return "$c LIKE %s"; }, $cols)) . ')';
		foreach ($cols as $_) {
			$args[] = $like;
		}
	}
	if ($f['grupo'] === 'otros') {
		$conocidos = [];
		foreach (at_pa_grupos_estado() as $g) {
			$conocidos = array_merge($conocidos, $g['estados']);
		}
		$partes[] = '(status IS NULL OR status NOT IN (' . implode(',', array_fill(0, count($conocidos), '%s')) . '))';
		$args = array_merge($args, $conocidos);
	} elseif ($f['grupo'] !== '') {
		$estados = at_pa_grupos_estado()[$f['grupo']]['estados'];
		$partes[] = 'status IN (' . implode(',', array_fill(0, count($estados), '%s')) . ')';
		$args = array_merge($args, $estados);
	}
	if ($f['desde'] !== '') {
		$partes[] = 'created_at >= %s';
		$args[] = $f['desde'] . ' 00:00:00';
	}
	if ($f['hasta'] !== '') {
		$partes[] = 'created_at <= %s';
		$args[] = $f['hasta'] . ' 23:59:59';
	}
	return ['sql' => $partes ? 'WHERE ' . implode(' AND ', $partes) : '', 'args' => $args];
}

/** ORDER BY de columnas permitidas (normalizar_filtros ya las validó) con desempate estable. */
function at_pa_order_sql(array $f): string {
	return 'ORDER BY ' . $f['orderby'] . ' ' . $f['order'] . ', id DESC';
}

/** Paginación acotada: 5..200 por página y la página dentro del rango. */
function at_pa_limites(int $paged, int $per_page, int $total): array {
	$per_page = min(200, max(5, $per_page));
	$total_pages = max(1, (int) ceil($total / $per_page));
	$paged = min(max(1, $paged), $total_pages);
	return ['per_page' => $per_page, 'paged' => $paged, 'offset' => ($paged - 1) * $per_page, 'total_pages' => $total_pages];
}

/** '2026-09-23 16:03:48' → '23-sep-2026'; inválida → ''. */
function at_pa_fecha_corta(?string $mysql): string {
	$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
	$d = DateTime::createFromFormat('Y-m-d H:i:s', (string) $mysql);
	if (!$d) {
		return '';
	}
	return $d->format('j') . '-' . $meses[(int) $d->format('n') - 1] . '-' . $d->format('Y');
}

/** Parámetros de la lista que vale la pena conservar al ir a la ficha y volver. */
function at_pa_query_volver(array $f): array {
	$q = [];
	foreach (['s', 'grupo', 'desde', 'hasta'] as $k) {
		if ($f[$k] !== '') {
			$q[$k] = $f[$k];
		}
	}
	if ($f['orderby'] !== 'created_at' || $f['order'] !== 'DESC') {
		$q['orderby'] = $f['orderby'];
		$q['order'] = strtolower($f['order']);
	}
	if ($f['paged'] > 1) {
		$q['paged'] = $f['paged'];
	}
	return $q;
}

/** El parámetro ?volver= (query string) vuelto a validar: nunca se confía en él. */
function at_pa_volver_desde_param(string $volver): array {
	if ($volver === '') {
		return [];
	}
	parse_str($volver, $crudo);
	return at_pa_query_volver(at_pa_normalizar_filtros(is_array($crudo) ? $crudo : []));
}

/** Pestañas de la ficha. «Revisión y precios» solo existe en v3. */
function at_pa_pestanas(bool $es_v3): array {
	$p = ['resumen' => 'Resumen', 'cliente' => 'Cliente y enlaces'];
	if ($es_v3) {
		$p['revision'] = 'Revisión y precios';
	}
	return $p + ['contenido' => 'Contenido', 'seguimiento' => 'Seguimiento', 'envio' => 'Envío'];
}

/** Filas de precio con servicio del payload v3; [] si el contenido no es JSON (propuestas viejas). */
function at_pa_precios_de_payload(?string $json): array {
	$p = json_decode((string) $json, true);
	if (!is_array($p) || !isset($p['pricing_rows']) || !is_array($p['pricing_rows'])) {
		return [];
	}
	$r = [];
	foreach ($p['pricing_rows'] as $f) {
		if (is_array($f) && trim((string) ($f['service'] ?? '')) !== '') {
			$r[] = ['service' => (string) $f['service'], 'price_label' => (string) ($f['price_label'] ?? '')];
		}
	}
	return $r;
}

/** Último comentario del historial v3 y cuántos van. */
function at_pa_ultimo_comentario(?string $feedback_log): array {
	$log = json_decode((string) $feedback_log, true);
	if (!is_array($log) || !$log) {
		return ['texto' => '', 'fecha' => '', 'total' => 0];
	}
	$u = end($log);
	return ['texto' => (string) ($u['comentario'] ?? ''), 'fecha' => (string) ($u['fecha'] ?? ''), 'total' => count($log)];
}

/** A qué pestaña lleva el botón «Siguiente paso» del Resumen; null si no hay nada que hacer. */
function at_pa_siguiente_paso(?string $flujo, ?string $status): ?array {
	$s = (string) $status;
	if ($s === 'sent') {
		return null;
	}
	if ($flujo === 'v3') {
		switch ($s) {
			case 'borrador':
				return ['tab' => 'revision', 'texto' => 'Revisar precios y aprobar'];
			case 'error':
				return ['tab' => 'revision', 'texto' => 'Revisar y reintentar'];
			case 'ajustando':
			case 'generando':
				return ['tab' => 'revision', 'texto' => 'Ver estado o destrabar'];
			case 'lista':
				return ['tab' => 'envio', 'texto' => 'Enviar al cliente'];
			default:
				return null;
		}
	}
	return ['tab' => 'envio', 'texto' => 'Enviar al cliente'];
}
```

- [ ] **Step 4: Correr y ver que pasa**

Run: `php tests/propuestas/admin-lista-test.php && php tests/propuestas/flow-test.php && php -l wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php`
Expected: `TODO OK` en las dos pruebas y `No syntax errors detected`. Si una aserción de esta tarea falla, se corrige el código, no la prueba. Solo se corrige la prueba si contradice la spec, y se explica en el reporte.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/propuestas-admin/consultas.php tests/propuestas/admin-lista-test.php
git commit -m "feat(propuestas-admin): funciones puras de búsqueda, filtros, estados y ficha con pruebas" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 2: Extraer los procesos y dejar el módulo viejo como clásico, sin cambiar el comportamiento

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/propuestas-admin/clasico.php`
- Create: `wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php`
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php` (líneas 54-992: sale la función `automatiza_tech_proposals_page` y entran los `require_once` y un ruteador que, por ahora, siempre llama al clásico)

**Interfaces:**
- Consumes: `at_propuesta_*` de `inc/proposals-flow.php` y `at_v3_llamar_n8n()`, `AT_N8N_V3_CAMBIOS` y `AT_N8N_V3_FINAL` de `inc/admin-proposals.php`.
- Produces:
  - `automatiza_tech_proposals_page_clasico(): void`, el módulo viejo intacto;
  - `at_pa_procesar_acciones(): string`, que devuelve el HTML del aviso (`''` si no hubo acción);
  - `at_pa_borrar_una(): string`;
  - `at_pa_borrar_varias(array $ids): string`;
  - `at_pa_accion_v3(): string`;
  - `at_pa_guardar(): string`.

- [ ] **Step 1: Crear `clasico.php` con la función vieja idéntica.** Copiar las líneas 54-992 de `inc/admin-proposals.php` tal como están en `d32ae3a`, desde el docblock «Renderizar página de administración» hasta la llave de cierre. Solo cambia el nombre de la función en la línea de la firma:

```php
<?php
/**
 * Módulo Propuestas ANTERIOR, intacto, accesible con &clasico=1 como red de seguridad
 * mientras se asienta el módulo nuevo. Se borra en un PR aparte (spec 2026-09-24, Despliegue).
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderizar página de administración
 */
function automatiza_tech_proposals_page_clasico() {
    // ... cuerpo idéntico a las líneas 58-991 de admin-proposals.php en d32ae3a ...
}
```

Verificación de que es idéntico. Tiene que dar exactamente una línea distinta, la de la firma:

```bash
git show d32ae3a:wp-content/themes/automatiza-tech/inc/admin-proposals.php | sed -n '57,992p' | tr -d '\r' > /tmp/viejo.txt
sed -n '/^function automatiza_tech_proposals_page_clasico/,$p' wp-content/themes/automatiza-tech/inc/propuestas-admin/clasico.php | tr -d '\r' > /tmp/nuevo.txt
diff /tmp/viejo.txt /tmp/nuevo.txt
```
Expected: solo `< function automatiza_tech_proposals_page() {` / `> function automatiza_tech_proposals_page_clasico() {`.

- [ ] **Step 2: Crear `acciones.php` con los procesos extraídos.** Cada función es el cuerpo de hoy, copiado; solo cambian las líneas que aquí se indican. En `d32ae3a`, `inc/admin-proposals.php` tiene: borrar una en 79-88, v3 en 90-136 y guardar en 138-372. No se toca el texto de ningún mensaje ni la lógica.

```php
<?php
/**
 * Módulo Propuestas: los procesos de POST/GET de siempre, extraídos sin cambiar lógica ni nombres de campo.
 * Se despachan por acción explícita: una acción desconocida no cae en el guardado.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Ejecuta la acción que venga en la petición y devuelve el aviso HTML ('' si no hubo acción). */
function at_pa_procesar_acciones(): string {
    if (!current_user_can('manage_options')) {
        return '';
    }
    if (isset($_GET['delete_id'], $_GET['_wpnonce'])) {
        return at_pa_borrar_una();
    }
    // Borrado masivo de la lista (WP_List_Table, formulario GET): action o action2 = borrar.
    foreach (['action', 'action2'] as $k) {
        $v = sanitize_key(wp_unslash($_GET[$k] ?? ''));
        if ($v === 'borrar' && !empty($_GET['proposal_ids'])) {
            return at_pa_borrar_varias(array_map('intval', (array) $_GET['proposal_ids']));
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return '';
    }
    if (isset($_POST['at_v3_accion'], $_POST['proposal_id'])) {
        return at_pa_accion_v3();
    }
    if (isset($_POST['proposal_id'], $_POST['automatiza_proposal_nonce'])) {
        return at_pa_guardar();
    }
    return '';
}

/** Borrar una propuesta (enlace con nonce delete_proposal_<id>). Líneas 79-88 de d32ae3a. */
function at_pa_borrar_una(): string {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';
    // ... pegar aquí el bloque interior del if de las líneas 81-87, sin cambios ...
    return $message;
}

/** Borrar varias desde la lista (nonce de WP_List_Table: bulk-propuestas). */
function at_pa_borrar_varias(array $ids): string {
    global $wpdb;
    check_admin_referer('bulk-propuestas');
    $ids = array_values(array_filter($ids, function ($i) { return $i > 0; }));
    if (!$ids) {
        return '';
    }
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($ids_placeholder)", ...$ids));
    return '<div class="notice notice-success is-dismissible"><p>🗑️ ' . count($ids) . ' propuesta(s) eliminada(s) correctamente.</p></div>';
}

/** Botones v3 (cambios, aprobar, destrabar). Cuerpo de las líneas 92-135 de d32ae3a. */
function at_pa_accion_v3(): string {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';
    // ... pegar aquí las líneas 92-135 sin cambios (desde «$id = (int) $_POST['proposal_id'];» ...) ...
    return $message;
}

/** Guardado normal (y envío del correo si corresponde). Cuerpo de las líneas 140-371 de d32ae3a. */
function at_pa_guardar(): string {
    global $wpdb;
    $table_name = $wpdb->prefix . 'automatiza_propuestas';
    $message = '';
    // ... pegar aquí las líneas 140-371 con UN solo cambio: las líneas 142-143
    //     echo '<div class="notice notice-error"><p>Error de seguridad. Intente nuevamente.</p></div>';
    //     return;
    // pasan a:
    //     return '<div class="notice notice-error"><p>Error de seguridad. Intente nuevamente.</p></div>';
    return $message;
}
```

Las líneas `// ... pegar aquí ...` son instrucciones para esta tarea y **no deben quedar en el archivo**. El archivo final tiene el código pegado, sin esos comentarios.

- [ ] **Step 3: Verificar que la extracción es fiel.** Para cada bloque, comparar sin fines de línea el original de `d32ae3a` con el cuerpo pegado. Las únicas diferencias permitidas son la sangría y el `echo ...; return;` → `return '...';` de `at_pa_guardar`.

```bash
V=/tmp/v.txt; N=/tmp/n.txt
git show d32ae3a:wp-content/themes/automatiza-tech/inc/admin-proposals.php | tr -d '\r' > /tmp/orig.php
sed -n '92,135p' /tmp/orig.php | sed 's/^[[:space:]]*//' > $V
sed -n '/^function at_pa_accion_v3/,/^}/p' wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php | tr -d '\r' | sed 's/^[[:space:]]*//' > $N
diff $V $N
```
Expected: solo difieren las líneas de firma y encabezado de la función y `global`/`$table_name`/`$message`/`return $message;`. Repetir con `140,371` para `at_pa_guardar` y con `81,87` para `at_pa_borrar_una`. Pegar los tres `diff` en el reporte.

- [ ] **Step 4: Ruteador que por ahora siempre usa el clásico.** En `inc/admin-proposals.php` reemplazar las líneas 54-992 (docblock y función `automatiza_tech_proposals_page` completa) por lo siguiente. Mantener CRLF.

```php
require_once __DIR__ . '/propuestas-admin/consultas.php';
require_once __DIR__ . '/propuestas-admin/acciones.php';
require_once __DIR__ . '/propuestas-admin/clasico.php';

/**
 * Página Propuestas. &clasico=1 abre el módulo anterior intacto (red de seguridad).
 * Tarea 2 del plan: todavía siempre usa el clásico; las tareas 3 y 4 conectan la lista y la ficha.
 */
function automatiza_tech_proposals_page() {
    automatiza_tech_proposals_page_clasico();
}
```

- [ ] **Step 5: Verificar**

```bash
php -l wp-content/themes/automatiza-tech/inc/admin-proposals.php
php -l wp-content/themes/automatiza-tech/inc/propuestas-admin/clasico.php
php -l wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php
php tests/propuestas/flow-test.php && php tests/propuestas/admin-lista-test.php
grep -c $'\r' wp-content/themes/automatiza-tech/inc/admin-proposals.php   # debe ser igual al número de líneas
wc -l wp-content/themes/automatiza-tech/inc/admin-proposals.php
```
Expected: sin errores de sintaxis, las dos pruebas en `TODO OK` y `admin-proposals.php` con CRLF en todas sus líneas.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/propuestas-admin/clasico.php wp-content/themes/automatiza-tech/inc/propuestas-admin/acciones.php wp-content/themes/automatiza-tech/inc/admin-proposals.php
git commit -m "refactor(propuestas-admin): módulo viejo como clásico y procesos extraídos sin cambios de lógica" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 3: La lista (`WP_List_Table`), sus estilos y la conexión en el ruteador

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/propuestas-admin/lista.php`
- Create: `wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css` (parte de lista y estados; la tarea 4 agrega la ficha)
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php`: menú con opciones de pantalla, encolado de CSS y ruteador

**Interfaces:**
- Consumes: todas las `at_pa_*` de la tarea 1, `at_pa_procesar_acciones()` de la tarea 2 y `automatiza_tech_proposals_page_clasico()`.
- Produces:
  - `class AT_Propuestas_Lista extends WP_List_Table` con `__construct(array $filtros)`;
  - `at_pa_render_lista(string $message): void`;
  - la opción de pantalla `propuestas_por_pagina`;
  - las clases CSS `at-estado`, `at-estado--<grupo>` y `at-marca-v3`, que la tarea 4 reutiliza.

- [ ] **Step 1: Crear `lista.php`:**

```php
<?php
/**
 * Lista de propuestas (WP_List_Table): buscador, vistas por estado, fechas, orden, paginación y borrado masivo.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AT_Propuestas_Lista extends WP_List_Table {
    /** @var array Filtros normalizados (at_pa_normalizar_filtros). */
    private $f;
    /** @var array Conteo por grupo (at_pa_contar_grupos). */
    private $conteos = [];

    public function __construct(array $filtros) {
        parent::__construct(['singular' => 'propuesta', 'plural' => 'propuestas', 'ajax' => false]);
        $this->f = $filtros;
    }

    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox">',
            'empresa'  => 'Empresa / cliente',
            'contacto' => 'Contacto',
            'estado'   => 'Estado',
            'creada'   => 'Creada',
        ];
    }

    protected function get_sortable_columns() {
        return ['empresa' => ['company_name', false], 'estado' => ['status', false], 'creada' => ['created_at', true]];
    }

    protected function get_primary_column_name() {
        return 'empresa';
    }

    protected function get_bulk_actions() {
        return ['borrar' => 'Borrar'];
    }

    public function prepare_items() {
        global $wpdb;
        $t = $wpdb->prefix . 'automatiza_propuestas';
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'empresa'];

        $w = at_pa_where($this->f, [$wpdb, 'esc_like']);
        $sql_total = "SELECT COUNT(*) FROM $t {$w['sql']}";
        $total = (int) ($w['args'] ? $wpdb->get_var($wpdb->prepare($sql_total, ...$w['args'])) : $wpdb->get_var($sql_total));
        $lim = at_pa_limites($this->f['paged'], $this->get_items_per_page('propuestas_por_pagina', 20), $total);

        $sql = "SELECT id, unique_link_id, client_name, company_name, client_email, phone, status, flujo, created_at
                FROM $t {$w['sql']} " . at_pa_order_sql($this->f) . ' LIMIT %d OFFSET %d';
        $this->items = $wpdb->get_results($wpdb->prepare($sql, ...array_merge($w['args'], [$lim['per_page'], $lim['offset']])));
        $this->set_pagination_args(['total_items' => $total, 'per_page' => $lim['per_page'], 'total_pages' => $lim['total_pages']]);

        $filas = $wpdb->get_results("SELECT status, COUNT(*) AS n FROM $t GROUP BY status", ARRAY_A);
        $this->conteos = at_pa_contar_grupos(array_column($filas ?: [], 'n', 'status'));
    }

    protected function get_views() {
        $base = admin_url('admin.php?page=automatiza-proposals');
        $q = at_pa_query_volver(array_merge($this->f, ['grupo' => '', 'paged' => 1]));
        $actual = $this->f['grupo'];
        $vista = function ($clave, $etiqueta, $n) use ($base, $q, $actual) {
            $args = $clave === 'todas' ? $q : $q + ['grupo' => $clave];
            $es = ($clave === 'todas' && $actual === '') || $clave === $actual;
            return sprintf('<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg($args, $base)), $es ? ' class="current" aria-current="page"' : '', esc_html($etiqueta), (int) $n);
        };
        $views = ['todas' => $vista('todas', 'Todas', $this->conteos['todas'] ?? 0)];
        foreach (at_pa_grupos_estado() + ['otros' => ['etiqueta' => 'Otros']] as $k => $g) {
            if (!empty($this->conteos[$k])) {
                $views[$k] = $vista($k, $g['etiqueta'], $this->conteos[$k]);
            }
        }
        return $views;
    }

    private function url_ficha($item) {
        $q = ['page' => 'automatiza-proposals', 'edit_id' => (int) $item->id];
        $volver = http_build_query(at_pa_query_volver($this->f));
        if ($volver !== '') {
            $q['volver'] = $volver;
        }
        return add_query_arg($q, admin_url('admin.php'));
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="proposal_ids[]" value="%d">', (int) $item->id);
    }

    protected function column_empresa($item) {
        $empresa = (string) $item->company_name !== '' ? (string) $item->company_name : '(sin empresa)';
        $acciones = ['abrir' => sprintf('<a href="%s">Abrir ficha</a>', esc_url($this->url_ficha($item)))];
        if (!empty($item->unique_link_id)) {
            $acciones['ver'] = sprintf('<a href="%s" target="_blank" rel="noopener">Ver presentación</a>',
                esc_url(get_site_url() . '/ver-presentacion.php?id=' . rawurlencode($item->unique_link_id)));
        }
        $acciones['borrar'] = sprintf('<a href="%s" class="at-borrar" onclick="return confirm(\'¿Borrar esta propuesta? No se puede deshacer.\');">Borrar</a>',
            esc_url(wp_nonce_url(admin_url('admin.php?page=automatiza-proposals&delete_id=' . (int) $item->id), 'delete_proposal_' . (int) $item->id)));
        return sprintf('<strong><a class="row-title" href="%s">%s</a></strong><div class="at-cliente">%s</div>%s',
            esc_url($this->url_ficha($item)), esc_html($empresa), esc_html((string) $item->client_name), $this->row_actions($acciones));
    }

    protected function column_contacto($item) {
        $partes = [];
        if (!empty($item->client_email)) {
            $partes[] = sprintf('<a href="mailto:%s">%s</a>', esc_attr($item->client_email), esc_html($item->client_email));
        }
        if (!empty($item->phone)) {
            $partes[] = esc_html($item->phone);
        }
        return $partes ? implode('<br>', $partes) : '—';
    }

    protected function column_estado($item) {
        $e = at_pa_estado_etiqueta($item->status);
        $v3 = $item->flujo === 'v3' ? ' <span class="at-marca-v3">v3</span>' : '';
        return sprintf('<span class="at-estado %s">%s</span>%s', esc_attr($e['clase']), esc_html($e['etiqueta']), $v3);
    }

    protected function column_creada($item) {
        return esc_html(at_pa_fecha_corta($item->created_at));
    }

    public function no_items() {
        printf('No hay propuestas con esos filtros. <a href="%s">Limpiar filtros</a>', esc_url(admin_url('admin.php?page=automatiza-proposals')));
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        printf('<div class="alignleft actions at-fechas"><label>Desde <input type="date" name="desde" value="%s"></label> <label>Hasta <input type="date" name="hasta" value="%s"></label> ',
            esc_attr($this->f['desde']), esc_attr($this->f['hasta']));
        submit_button('Filtrar', '', 'filtrar', false);
        echo '</div>';
    }
}

/** Pantalla de lista completa. $message ya viene armado por at_pa_procesar_acciones (partes dinámicas escapadas). */
function at_pa_render_lista(string $message): void {
    $f = at_pa_normalizar_filtros(wp_unslash($_GET));
    // Que la paginación y el orden no repitan una acción ya hecha (borrado) al armar sus enlaces.
    $_SERVER['REQUEST_URI'] = remove_query_arg(['action', 'action2', 'proposal_ids', '_wpnonce', '_wp_http_referer', 'delete_id', 'filtrar'], $_SERVER['REQUEST_URI']);
    $tabla = new AT_Propuestas_Lista($f);
    $tabla->prepare_items();
    echo '<div class="wrap at-pa">';
    echo '<h1 class="wp-heading-inline">Propuestas</h1>';
    if ($f['s'] !== '') {
        printf('<span class="subtitle">Resultados para «%s»</span>', esc_html($f['s']));
    }
    echo '<hr class="wp-header-end">';
    echo $message;
    foreach ($f['avisos'] as $a) {
        printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($a));
    }
    $tabla->views();
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="automatiza-proposals">';
    if ($f['grupo'] !== '') {
        printf('<input type="hidden" name="grupo" value="%s">', esc_attr($f['grupo']));
    }
    $tabla->search_box('Buscar', 'at-propuestas');
    $tabla->display();
    echo '</form></div>';
}
```

- [ ] **Step 2: Crear `assets/css/propuestas-admin.css`:**

```css
/* Módulo Propuestas del wp-admin. Se encola solo en toplevel_page_automatiza-proposals (no en &clasico=1). */
.at-pa { --at-navy: #0d1b2a; --at-teal: #00d9c0; }
.at-estado { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; line-height: 1.6; background: #e2e8f0; color: #334155; white-space: nowrap; }
.at-estado--borrador { background: #fef3c7; color: #92400e; }
.at-estado--ajustando, .at-estado--generando { background: #dbeafe; color: #1e40af; }
.at-estado--lista { background: #ccfbf1; color: #115e59; }
.at-estado--enviadas { background: #dcfce7; color: #166534; }
.at-estado--pendiente { background: #f1f5f9; color: #475569; }
.at-estado--error { background: #fee2e2; color: #991b1b; }
.at-marca-v3 { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 4px; font-size: 11px; font-weight: 700; line-height: 1.6; background: var(--at-navy); color: #fff; }
.at-pa .at-cliente { color: #64748b; }
.at-pa .row-actions .at-borrar { color: #b32d2e; }
.at-pa .at-fechas label { margin-right: 6px; }
.at-pa .at-fechas input[type="date"] { max-width: 150px; }
@media (max-width: 782px) {
  .at-pa .at-fechas { float: none; display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
  .at-pa .at-fechas input[type="date"] { max-width: 100%; }
}
```

- [ ] **Step 3: En `inc/admin-proposals.php` agregar las opciones de pantalla y el encolado, y conectar la lista.** Mantener CRLF. Reemplazar `automatiza_tech_proposals_menu()` (líneas 41-52) por:

```php
function automatiza_tech_proposals_menu() {
    $hook = add_menu_page(
        'Aprobar Propuestas',
        'Propuestas',
        'manage_options',
        'automatiza-proposals',
        'automatiza_tech_proposals_page',
        'dashicons-format-aside',
        26
    );
    add_action('load-' . $hook, 'at_pa_opciones_pantalla');
}
add_action('admin_menu', 'automatiza_tech_proposals_menu');

/** «Opciones de pantalla» → Propuestas por página (solo en la lista nueva). */
function at_pa_opciones_pantalla() {
    if (isset($_GET['edit_id']) || isset($_GET['clasico'])) {
        return;
    }
    add_screen_option('per_page', ['label' => 'Propuestas por página', 'default' => 20, 'option' => 'propuestas_por_pagina']);
}
add_filter('set_screen_option_propuestas_por_pagina', function ($status, $option, $value) {
    return min(200, max(5, (int) $value));
}, 10, 3);

/** CSS y JS del módulo nuevo, solo en su página y nunca en el clásico. */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_automatiza-proposals' || isset($_GET['clasico'])) {
        return;
    }
    $dir = get_template_directory() . '/assets';
    $url = get_template_directory_uri() . '/assets';
    wp_enqueue_style('at-propuestas-admin', $url . '/css/propuestas-admin.css', [], (string) filemtime($dir . '/css/propuestas-admin.css'));
    if (file_exists($dir . '/js/propuestas-admin.js')) {
        wp_enqueue_script('at-propuestas-admin', $url . '/js/propuestas-admin.js', [], (string) filemtime($dir . '/js/propuestas-admin.js'), true);
    }
});
```

En el bloque de `require_once` de la tarea 2 agregar `require_once __DIR__ . '/propuestas-admin/lista.php';`. Reemplazar el ruteador por:

```php
function automatiza_tech_proposals_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ver las propuestas.');
    }
    // El clásico procesa sus propios formularios (no tienen action: vuelven a esta misma URL con clasico=1).
    // Tarea 3: la ficha todavía es la del clásico.
    if (isset($_GET['clasico']) || isset($_GET['edit_id'])) {
        automatiza_tech_proposals_page_clasico();
        return;
    }
    at_pa_render_lista(at_pa_procesar_acciones());
}
```

- [ ] **Step 4: Verificar**

```bash
php -l wp-content/themes/automatiza-tech/inc/propuestas-admin/lista.php
php -l wp-content/themes/automatiza-tech/inc/admin-proposals.php
php tests/propuestas/flow-test.php && php tests/propuestas/admin-lista-test.php
grep -c $'\r' wp-content/themes/automatiza-tech/inc/admin-proposals.php; wc -l < wp-content/themes/automatiza-tech/inc/admin-proposals.php
```
Expected: sintaxis OK, pruebas en `TODO OK` y CRLF en todas las líneas de `admin-proposals.php`. El render real con WordPress lo verifica el controlador en la tarea 5.

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/propuestas-admin/lista.php wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css wp-content/themes/automatiza-tech/inc/admin-proposals.php
git commit -m "feat(propuestas-admin): lista completa con buscador, estados, fechas, orden y paginación" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 4: La ficha en 6 pestañas, su JS y la conexión en el ruteador

**Files:**
- Create: `wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php`
- Create: `wp-content/themes/automatiza-tech/assets/js/propuestas-admin.js`
- Modify: `wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css` (agregar al final)
- Modify: `wp-content/themes/automatiza-tech/inc/admin-proposals.php` (require de la ficha y ruteador final)

**Interfaces:**
- Consumes:
  - de la tarea 1: `at_pa_pestanas`, `at_pa_estado_etiqueta`, `at_pa_fecha_corta`, `at_pa_volver_desde_param`, `at_pa_precios_de_payload`, `at_pa_ultimo_comentario`, `at_pa_siguiente_paso`;
  - de la tarea 2: `at_pa_procesar_acciones`;
  - de la tarea 3: `at_pa_render_lista`;
  - de `proposals-flow.php`: `at_propuesta_costo_fotos` y `at_propuesta_puede_enviarse`;
  - de otros módulos: `automatiza_render_prospect_details`.
- Produces: `at_pa_render_ficha(object $p, string $message): void`.

- [ ] **Step 1: Crear `ficha.php`.** Los campos y la lógica de cada bloque salen del clásico, en las líneas indicadas de `d32ae3a`. Mismos `name`, `id`, `required`, `readonly`, `confirm()` y reglas de la casilla. Solo cambian el envoltorio y las clases: sin `style=` salvo el botón oculto por defecto.

```php
<?php
/**
 * Ficha de una propuesta: un solo formulario con 6 pestañas (Revisión solo en v3) y barra de guardado fija.
 * Los nombres de campo y los procesos son los mismos del módulo anterior (acciones.php).
 */
if (!defined('ABSPATH')) {
    exit;
}

function at_pa_render_ficha($p, string $message): void {
    $es_v3 = ($p->flujo ?? '') === 'v3';
    $pestanas = at_pa_pestanas($es_v3);
    $tab = sanitize_key(wp_unslash($_POST['at_tab'] ?? ($_GET['tab'] ?? 'resumen')));
    if (!isset($pestanas[$tab])) {
        $tab = 'resumen';
    }
    $url_lista = add_query_arg(at_pa_volver_desde_param(wp_unslash((string) ($_GET['volver'] ?? ''))) + ['page' => 'automatiza-proposals'], admin_url('admin.php'));
    $estado = at_pa_estado_etiqueta($p->status);
    $link_pres = get_site_url() . '/ver-presentacion.php?id=' . rawurlencode((string) $p->unique_link_id);
    $link_demo = get_site_url() . '/ver-demo.php?id=' . rawurlencode((string) $p->unique_link_id);
    $precios = at_pa_precios_de_payload($p->gamma_prompt_text);
    $ultimo = at_pa_ultimo_comentario($p->feedback_log ?? null);
    $paso = at_pa_siguiente_paso($p->flujo ?? null, $p->status);
    $sub = array_filter([(string) $p->client_name, 'id ' . (int) $p->id, 'creada ' . at_pa_fecha_corta($p->created_at)]);
    ?>
    <div class="wrap at-pa at-pa-ficha">
      <p class="at-pa-volver"><a href="<?php echo esc_url($url_lista); ?>">← Volver a la lista</a></p>
      <div class="at-pa-cabecera">
        <div>
          <h1><?php echo esc_html((string) $p->company_name !== '' ? $p->company_name : '(sin empresa)'); ?></h1>
          <p class="at-pa-sub"><?php echo esc_html(implode(' · ', $sub)); ?></p>
        </div>
        <div><span class="at-estado <?php echo esc_attr($estado['clase']); ?>"><?php echo esc_html($estado['etiqueta']); ?></span><?php echo $es_v3 ? ' <span class="at-marca-v3">v3</span>' : ''; ?></div>
      </div>
      <?php echo $message; ?>

      <form method="POST" enctype="multipart/form-data" class="at-pa-form" data-tab-inicial="<?php echo esc_attr($tab); ?>">
        <button type="submit" style="display:none" tabindex="-1" aria-hidden="true"></button>
        <?php wp_nonce_field('save_proposal', 'automatiza_proposal_nonce'); ?>
        <input type="hidden" name="proposal_id" value="<?php echo (int) $p->id; ?>">
        <input type="hidden" name="at_tab" value="<?php echo esc_attr($tab); ?>" class="at-pa-tab-actual">

        <nav class="at-pa-tabs" role="tablist" aria-label="Secciones de la propuesta">
          <?php foreach ($pestanas as $k => $titulo): ?>
            <button type="button" role="tab" class="at-pa-tab" data-tab="<?php echo esc_attr($k); ?>" aria-selected="<?php echo $k === $tab ? 'true' : 'false'; ?>"><?php echo esc_html($titulo); ?></button>
          <?php endforeach; ?>
        </nav>
        <select class="at-pa-tabs-movil" aria-label="Sección">
          <?php foreach ($pestanas as $k => $titulo): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($k, $tab); ?>><?php echo esc_html($titulo); ?></option>
          <?php endforeach; ?>
        </select>

        <section class="at-pa-panel" data-panel="resumen" role="tabpanel">
          <div class="at-pa-resumen">
            <div class="at-pa-caja"><h3>Contacto</h3>
              <?php echo $p->client_email ? '<a href="mailto:' . esc_attr($p->client_email) . '">' . esc_html($p->client_email) . '</a>' : '—'; ?>
              <?php echo $p->phone ? '<br>' . esc_html($p->phone) : ''; ?>
            </div>
            <div class="at-pa-caja"><h3>Enlaces</h3>
              <div class="at-pa-enlaces">
                <a class="button" href="<?php echo esc_url($link_pres); ?>" target="_blank" rel="noopener">📊 Presentación</a>
                <a class="button" href="<?php echo esc_url($link_demo); ?>" target="_blank" rel="noopener">🤖 Demo chatbot</a>
                <?php if (!empty($p->pdf_path)): ?><a class="button" href="<?php echo esc_url($p->pdf_path); ?>" target="_blank" rel="noopener">📄 PDF</a><?php endif; ?>
              </div>
            </div>
            <div class="at-pa-caja"><h3>Precios</h3>
              <?php if ($precios): ?><ul class="at-pa-lista-precios"><?php foreach ($precios as $r): ?><li><?php echo esc_html($r['service'] . ' · ' . $r['price_label']); ?></li><?php endforeach; ?></ul>
              <?php else: ?>—<?php endif; ?>
            </div>
            <div class="at-pa-caja"><h3>Último movimiento</h3>
              <?php if (!empty($p->status_note)): ?><p><?php echo esc_html($p->status_note); ?></p><?php endif; ?>
              <?php if ($ultimo['total']): ?><p><?php echo esc_html('Último comentario (' . $ultimo['total'] . '): ' . $ultimo['texto']); ?></p><?php endif; ?>
              <?php if (empty($p->status_note) && !$ultimo['total']): ?>—<?php endif; ?>
            </div>
          </div>
          <?php if ($paso): ?>
            <p class="at-pa-botones"><button type="button" class="button button-primary at-pa-ir" data-ir="<?php echo esc_attr($paso['tab']); ?>"><?php echo esc_html($paso['texto']); ?> →</button></p>
          <?php endif; ?>
        </section>

        <section class="at-pa-panel" data-panel="cliente" role="tabpanel">
          <!-- Pegar aquí la <table class="form-table"> de las líneas 762-813 de d32ae3a, sin cambios,
               salvo la línea 809, que pasa a:
               <p class="description">PDF actual: <a href="<?php echo esc_url($p->pdf_path); ?>" target="_blank" rel="noopener">Ver archivo</a></p>
               y reemplazar $edit_proposal por $p en todo el bloque. -->
        </section>

        <?php if ($es_v3):
            $pl = json_decode((string) $p->gamma_prompt_text, true) ?: [];
            $costo = at_propuesta_costo_fotos($pl);
            $filas = $pl['pricing_rows'] ?? [];
            for ($i = count($filas); $i < 6; $i++) { $filas[] = ['service' => '', 'price_label' => '']; }
            $log = json_decode((string) $p->feedback_log, true) ?: [];
        ?>
        <section class="at-pa-panel v3-section" data-panel="revision" role="tabpanel">
          <p>Estado: <strong><?php echo esc_html($p->status); ?></strong>
             <?php if ($p->status_note): ?> — <?php echo esc_html($p->status_note); ?><?php endif; ?>
             · <a href="<?php echo esc_url($p->gamma_iframe_url); ?>" target="_blank" rel="noopener">Ver vista previa</a></p>
          <?php wp_nonce_field('at_v3_' . $p->id); ?>
          <table class="widefat at-pa-precios">
            <thead><tr><th>Servicio</th><th>Precio (texto tal cual)</th><th>Destacar</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $i => $f): ?>
              <tr>
                <td data-titulo="Servicio"><input type="text" name="at_precio[<?php echo $i; ?>][service]" value="<?php echo esc_attr($f['service'] ?? ''); ?>"></td>
                <td data-titulo="Precio"><input type="text" name="at_precio[<?php echo $i; ?>][price_label]" value="<?php echo esc_attr($f['price_label'] ?? ''); ?>"></td>
                <td data-titulo="Destacar"><input type="checkbox" name="at_precio[<?php echo $i; ?>][emphasis]" value="1" <?php checked(!empty($f['emphasis'])); ?>></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <p><label>Nota de precios<br><textarea name="at_nota_precio" rows="2" class="large-text"><?php echo esc_textarea($pl['pricing_note'] ?? ''); ?></textarea></label></p>
          <p><label>Comentarios para ajustar (qué cambiar en textos, láminas o chatbot)<br><textarea name="at_comentario" rows="4" class="large-text"></textarea></label></p>
          <?php if ($log): ?><details><summary>Historial de comentarios (<?php echo count($log); ?>)</summary><ul>
            <?php foreach ($log as $c): ?><li><?php echo esc_html(($c['fecha'] ?? '') . ' — ' . ($c['comentario'] ?? '')); ?></li><?php endforeach; ?>
          </ul></details><?php endif; ?>
          <p class="at-pa-botones">
            <!-- Pegar aquí, sin cambios, los botones de las líneas 899-907 de d32ae3a (cambios, aprobar con su confirm, destrabar condicional),
                 reemplazando $edit_proposal por $p. -->
          </p>
        </section>
        <?php endif; ?>

        <section class="at-pa-panel" data-panel="contenido" role="tabpanel">
          <h2>Transcripción de la reunión</h2>
          <?php if (trim((string) $p->transcript_text) !== ''): ?>
            <details><summary>Ver transcripción</summary>
              <textarea id="at-transcripcion" class="large-text" rows="14" readonly><?php echo esc_textarea($p->transcript_text); ?></textarea>
              <p><button type="button" class="button at-pa-copiar" data-copiar="#at-transcripcion">Copiar</button></p>
            </details>
          <?php else: ?><p>Esta propuesta no tiene transcripción guardada.</p><?php endif; ?>
          <!-- Pegar aquí los dos bloques de prompt de las líneas 821-856 de d32ae3a (Gamma y chatbot), sin cambios en <details>, <label> y <textarea>
               (mismos name, id, readonly y avisos), quitando solo los atributos style= y reemplazando $edit_proposal por $p.
               El <div> que envuelve el código usa class="at-pa-codigo". -->
        </section>

        <section class="at-pa-panel" data-panel="seguimiento" role="tabpanel">
          <?php if (function_exists('automatiza_render_prospect_details')) { automatiza_render_prospect_details($p->id); } else { echo '<p>El módulo de seguimiento no está disponible.</p>'; } ?>
        </section>

        <section class="at-pa-panel" data-panel="envio" role="tabpanel">
          <!-- Pegar aquí la casilla send_email (líneas 924-936) y la personalización del correo (líneas 939-976) de d32ae3a:
               misma lógica de $puede / $send_email_attr, mismos name, id y placeholders, quitando solo los atributos style=
               y reemplazando $edit_proposal por $p. El aviso «Se habilita cuando…» usa class="at-pa-aviso". -->
        </section>

        <div class="at-pa-guardar"><button type="submit" class="button button-primary button-large">💾 Guardar</button></div>
      </form>
    </div>
    <?php
}
```

Los comentarios `<!-- Pegar aquí ... -->` son instrucciones para esta tarea y **no deben quedar en el archivo**. El archivo final lleva el marcado pegado.

- [ ] **Step 2: Crear `assets/js/propuestas-admin.js`:**

```js
/* Módulo Propuestas (wp-admin): pestañas de la ficha, «Siguiente paso», Enter en precios v3 y Copiar transcripción. */
(function () {
  'use strict';
  var form = document.querySelector('.at-pa-form');
  if (!form) { return; }
  form.classList.add('at-pa-js');
  var campo = form.querySelector('.at-pa-tab-actual');
  var tabs = form.querySelectorAll('.at-pa-tab');
  var sel = form.querySelector('.at-pa-tabs-movil');

  function mostrar(clave) {
    if (!form.querySelector('.at-pa-panel[data-panel="' + clave + '"]')) { clave = 'resumen'; }
    form.querySelectorAll('.at-pa-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== clave; });
    tabs.forEach(function (t) { t.setAttribute('aria-selected', t.getAttribute('data-tab') === clave ? 'true' : 'false'); });
    if (sel) { sel.value = clave; }
    if (campo) { campo.value = clave; }
  }

  tabs.forEach(function (t) { t.addEventListener('click', function () { mostrar(t.getAttribute('data-tab')); }); });
  if (sel) { sel.addEventListener('change', function () { mostrar(sel.value); }); }
  form.querySelectorAll('.at-pa-ir').forEach(function (b) {
    b.addEventListener('click', function () { mostrar(b.getAttribute('data-ir')); window.scrollTo(0, 0); });
  });
  // Un campo obligatorio vacío en una pestaña oculta bloquearía el envío sin mostrar nada: se abre su pestaña.
  form.addEventListener('invalid', function (e) {
    var panel = e.target.closest('.at-pa-panel');
    if (panel && panel.hidden) { mostrar(panel.getAttribute('data-panel')); }
  }, true);
  // Enter en los campos de la revisión v3 no envía: el guardado normal ignora esos precios (revisión 14b, M3).
  form.querySelectorAll('.v3-section input').forEach(function (el) {
    el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
  });
  form.querySelectorAll('.at-pa-copiar').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = form.querySelector(b.getAttribute('data-copiar'));
      if (!t) { return; }
      var listo = function () { b.textContent = 'Copiado ✓'; };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(t.value).then(listo, function () { t.select(); document.execCommand('copy'); listo(); });
      } else { t.select(); document.execCommand('copy'); listo(); }
    });
  });
  mostrar(form.getAttribute('data-tab-inicial') || 'resumen');
})();
```

- [ ] **Step 3: Agregar al final de `assets/css/propuestas-admin.css`:**

```css
/* ---------- Ficha ---------- */
.at-pa-volver { margin: 8px 0 0; }
.at-pa-cabecera { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 8px 16px; margin: 4px 0 12px; }
.at-pa-cabecera h1 { margin: 0; padding: 0; font-size: 23px; line-height: 1.3; overflow-wrap: anywhere; }
.at-pa-sub { margin: 4px 0 0; color: #64748b; }
.at-pa-tabs { display: flex; flex-wrap: wrap; gap: 2px; border-bottom: 1px solid #c3c4c7; margin: 0 0 16px; }
.at-pa-tab { background: none; border: 0; border-bottom: 3px solid transparent; padding: 10px 14px; font-size: 14px; cursor: pointer; color: #1d2327; }
.at-pa-tab[aria-selected="true"] { border-bottom-color: var(--at-teal); font-weight: 600; color: var(--at-navy); }
.at-pa-tab:focus-visible { outline: 2px solid var(--at-teal); outline-offset: -2px; }
.at-pa-tabs-movil { display: none; width: 100%; max-width: 100%; margin: 0 0 16px; }
.at-pa-panel { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 16px; min-width: 0; }
.at-pa-panel[hidden] { display: none; }
.at-pa-panel > h2 { margin-top: 0; }
.at-pa-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.at-pa-caja { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; background: #f8fafc; min-width: 0; overflow-wrap: anywhere; }
.at-pa-caja h3 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
.at-pa-caja p { margin: 0 0 6px; }
.at-pa-lista-precios { margin: 0; padding-left: 18px; list-style: disc; }
.at-pa-enlaces, .at-pa-botones { display: flex; flex-wrap: wrap; gap: 8px; }
.at-pa-botones .button, .at-pa-enlaces .button { white-space: normal; height: auto; min-height: 30px; line-height: 1.4; padding-top: 4px; padding-bottom: 4px; }
.at-pa .form-table input.regular-text, .at-pa .form-table input.large-text, .at-pa textarea.large-text { width: 100%; max-width: 100%; box-sizing: border-box; }
.at-pa-precios { max-width: 820px; }
.at-pa-precios input[type="text"] { width: 100%; box-sizing: border-box; }
.at-pa-codigo { max-height: 300px; overflow: auto; white-space: pre-wrap; font-family: monospace; font-size: 12px; line-height: 1.5; background: #fff; border: 1px solid #e2e8f0; padding: 12px; margin-top: 8px; }
.at-pa-aviso { color: #b45309; }
.at-pa-guardar { position: sticky; bottom: 0; z-index: 10; background: #f0f0f1; border-top: 1px solid #c3c4c7; padding: 10px 0; margin-top: 8px; text-align: right; }
@media (max-width: 782px) {
  .at-pa-tabs { display: none; }
  .at-pa-js .at-pa-tabs-movil { display: block; }
  .at-pa-panel { padding: 12px; }
  .at-pa-precios thead { display: none; }
  .at-pa-precios, .at-pa-precios tbody, .at-pa-precios tr, .at-pa-precios td { display: block; width: 100%; box-sizing: border-box; }
  .at-pa-precios tr { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-bottom: 8px; }
  .at-pa-precios td { padding: 4px 0; }
  .at-pa-precios td::before { content: attr(data-titulo); display: block; font-weight: 600; font-size: 12px; color: #64748b; }
  .at-pa-botones .button, .at-pa-guardar .button { width: 100%; text-align: center; }
}
```

- [ ] **Step 4: Ruteador final en `inc/admin-proposals.php`.** Mantener CRLF. Agregar `require_once __DIR__ . '/propuestas-admin/ficha.php';` y reemplazar el ruteador por:

```php
function automatiza_tech_proposals_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ver las propuestas.');
    }
    // El clásico procesa sus propios formularios (no tienen action: vuelven a esta misma URL con clasico=1).
    if (isset($_GET['clasico'])) {
        automatiza_tech_proposals_page_clasico();
        return;
    }
    $message = at_pa_procesar_acciones();
    $edit_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
    if ($edit_id > 0) {
        global $wpdb;
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}automatiza_propuestas WHERE id = %d", $edit_id));
        if ($p) {
            at_pa_render_ficha($p, $message);
            return;
        }
        $message .= '<div class="notice notice-error"><p>Esa propuesta no existe (id ' . (int) $edit_id . '). <a href="'
            . esc_url(admin_url('admin.php?page=automatiza-proposals')) . '">Ver la lista</a></p></div>';
    }
    at_pa_render_lista($message);
}
```

- [ ] **Step 5: Verificar** que no queden instrucciones ni estilos en línea y que la sintaxis esté bien:

```bash
php -l wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php
php -l wp-content/themes/automatiza-tech/inc/admin-proposals.php
grep -n "Pegar aquí\|pegar aquí" wp-content/themes/automatiza-tech/inc/propuestas-admin/*.php   # debe salir vacío
grep -c 'style=' wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php               # debe ser 1 (el botón oculto por defecto)
grep -c 'edit_proposal' wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php         # debe ser 0
node --check wp-content/themes/automatiza-tech/assets/js/propuestas-admin.js
php tests/propuestas/flow-test.php && php tests/propuestas/admin-lista-test.php
```

Comprobar además que la ficha envía los mismos nombres de campo que el clásico. Hay que listarlos con `grep -o 'name="[^"]*"'` sobre la ficha y sobre las líneas 757-982 del clásico. El conjunto de la ficha tiene que contener todos los del clásico, más `at_tab`. Pegar los dos conjuntos en el reporte.

- [ ] **Step 6: Commit**

```bash
git add wp-content/themes/automatiza-tech/inc/propuestas-admin/ficha.php wp-content/themes/automatiza-tech/assets/js/propuestas-admin.js wp-content/themes/automatiza-tech/assets/css/propuestas-admin.css wp-content/themes/automatiza-tech/inc/admin-proposals.php
git commit -m "feat(propuestas-admin): ficha en 6 pestañas con transcripción, guardado fijo y vista de celular" -m "Co-Authored-By: <modelo> <noreply@anthropic.com>"
```

---

### Task 5: Verificación integrada en un WordPress local (la hace el controlador)

No se delega: levanta un servidor en la máquina de Luis y usa el navegador.

**Files:** ninguno del repositorio. Todo va en el scratchpad de la sesión.

- [ ] **Step 1: Base de datos local.**
  - Si `http://localhost/automatiza-tech/` no responde, pedirle a Luis que encienda WAMP. Es su herramienta, y basta con MySQL.
  - Comprobar que existe la base `automatiza_tech_local` y leer cuántas propuestas tiene.
  - Si tiene menos de 25, insertar filas de prueba **solo en la base local**: 25 filas con `company_name` «[PRUEBA LOCAL] Empresa N», fechas repartidas entre 2025-10-01 y 2026-09-24, y estados variados (`sent`, `borrador` v3, `pending`, `draft`, `error` v3, `lista` v3), una de ellas con `transcript_text`. Anotar los id para borrarlas al final.
- [ ] **Step 2: Sitio de prueba armado con uniones (sin copiar el repo).** Crear `<scratchpad>/wp-local/`:
  - con uniones (`mklink /J`) a `wp-admin` y `wp-includes`;
  - con copias de los `*.php` de la raíz y de `wp-config.php`, desde `C:\wamp64\www\automatiza-tech`;
  - con `wp-content/` que contenga `themes/automatiza-tech` como unión a la carpeta del tema de este worktree, y `plugins` como unión a los plugins de la copia principal.
- [ ] **Step 3: Servidor con sesión de administrador solo local.** Armar `<scratchpad>/wp-local/router.php`:
  - define `WP_HOME` y `WP_SITEURL` = `http://localhost:8089`;
  - pre-registra en `$GLOBALS['wp_filter']['determine_current_user'][99]` una función que devuelve el id del primer administrador local;
  - hace `return false` para los archivos estáticos que existan y, para lo demás, incluye el `.php` pedido.

  Se levanta con `preview_start` y una entrada `wp-local` en `.claude/launch.json` (`php -S localhost:8089 -t <wp-local> <wp-local>/router.php`). Este router vive en el scratchpad, nunca en el repo, y no se despliega.
- [ ] **Step 4: Recorrido con el navegador**, con capturas:
  - **Lista:** aparecen todas las filas en páginas de 20; buscar por empresa, por correo y por teléfono; vistas por estado con sus conteos; «Otros»; filtro de fechas (y una fecha inválida que muestra el aviso); ordenar por empresa y por fecha; borrado masivo de 2 filas de prueba; borrado individual con confirmación.
  - **Ficha:** abrir desde la lista y volver con los filtros intactos; recorrer las 6 pestañas; en una v3, «Siguiente paso» lleva a Revisión; guardar desde Cliente vuelve a Cliente; un campo obligatorio vacío abre su pestaña; Copiar transcripción.
  - **Equivalencia:** en la misma propuesta v3 y en una vieja, capturar con `new FormData(form)` los nombres enviados por Guardar, Pedir cambios (sin enviarlo a n8n: interceptar el `submit` y cancelar) y Aprobar, en la ficha nueva y en `&clasico=1`. Deben ser el mismo conjunto, más `at_tab`.
  - **Celular (375×812):** `document.documentElement.scrollWidth <= 375` en la lista y en cada pestaña; la tabla de precios pasa a tarjetas; ningún botón se sale de su cuadro.
  - **Clásico:** `&clasico=1` se ve y guarda como antes.
  - **Seguridad:** `edit_id=999999` muestra «Esa propuesta no existe».
- [ ] **Step 5: Limpiar:**
  - borrar las filas de prueba insertadas en la base local;
  - `preview_stop`;
  - quitar las uniones con `rmdir`, nunca con `rm -rf` sobre una unión.

  Si algo falla, abrir una ronda de corrección con la tarea que corresponda.

---

### Task 6: Despliegue a PROD, documentación y PR (lo hace el controlador, con autorización de Luis)

- [ ] **Step 1:** Cotejar el md5 de `inc/admin-proposals.php` en PROD contra el de `claude/propuestas-v3`, que es el desplegado el 24-sep a las 02:18 (`7327db63e8d7…`). Si difiere, parar y avisar.
- [ ] **Step 2:** Pedir la autorización de Luis con la lista exacta de archivos:
  - Nuevos, en este orden: los 5 de `inc/propuestas-admin/`, `assets/css/propuestas-admin.css` y `assets/js/propuestas-admin.js`.
  - Al final, `inc/admin-proposals.php`, porque es el único que los requiere.
- [ ] **Step 3:** Con la autorización, hacer el despliegue:
  - respaldar en `~/respaldos/propuestas-admin-antes-<fecha>.tar.gz`;
  - subir cada archivo a un temporal, correr `php -l` y mover;
  - comparar md5 local contra remoto.
- [ ] **Step 4:** Verificar desde afuera:
  - `/wp-admin/admin.php?page=automatiza-proposals` responde 302 al login, no 500;
  - portada 200, `ver-presentacion.php?id=uBn21AF16EcM` 200;
  - la URL pública del CSS y del JS responde 200.
- [ ] **Step 5:** Luis revisa con su sesión:
  - la lista muestra las 19 propuestas y el buscador encuentra «Jeffer»;
  - la ficha de Orly (id 43) abre;
  - una v3 muestra Revisión;
  - el celular se ve sin desbordes;
  - `&clasico=1` sigue funcionando.
- [ ] **Step 6:** Documentar:
  - sección «Panel de propuestas» en `Docs/METODO_AT/PROPUESTAS-FLUJO-V3.md`: cómo buscar, pestañas y `&clasico=1`;
  - actualizar la memoria `project_propuesta_renderer`;
  - commit, push y PR hacia `main` con la dependencia del PR #41 y la lista de despliegue.
- [ ] **Step 7:** Anotar en el ledger que `&clasico=1` y `clasico.php` se retiran en un PR aparte después de unas dos semanas sin problemas.
