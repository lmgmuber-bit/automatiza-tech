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
