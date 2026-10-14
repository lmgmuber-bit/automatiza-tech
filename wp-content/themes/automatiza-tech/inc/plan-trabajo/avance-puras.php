<?php
/**
 * Estado del proyecto (Etapa 2): forma del avance de un plan (columna `avance`), validación de «Importar cierre»
 * (formato at-cierre-v1), estado de los bloques y los textos del panel. Funciones puras: sin base ni WordPress
 * (tests/plan/avance-puras-test.php). Spec: Docs/superpowers/specs/2026-10-13-estado-del-proyecto-design.md §3 y §4.
 * El plan firmado (payload) solo se lee: el avance vive aparte (D3).
 */

const AT_AV_MAX_CIERRES = 30;
const AT_AV_MAX_EVIDENCIAS = 60;
const AT_AV_MAX_CAMBIOS = 30;
const AT_AV_MAX_NECESITAMOS = 30;
const AT_AV_MAX_TEXTO = 2000;
const AT_AV_MAX_PUNTO = 200;
const AT_AV_MAX_PUNTOS = 8;
const AT_AV_MAX_ALCANCE = 20;
const AT_AV_MAX_ACTIVIDADES = 30;
const AT_AV_ESTADOS_BLOQUE = ['listo', 'en_curso', 'por_venir'];
const AT_AV_TIPOS = ['captura', 'enlace', 'cifra', 'video'];

/** Registro de un render de «Estado del proyecto» (público o vista previa). */
function at_av_registro_vacio(): array {
	return ['estado' => '', 'view_url' => '', 'pdf_url' => '', 'nota' => '', 'at' => ''];
}

function at_av_vacio(): array {
	return [
		'version' => 1, 'bloques' => [], 'cierres' => [], 'evidencias' => [], 'cambios_plan' => [], 'necesitamos' => [],
		'alcance' => ['fase1' => [], 'mensual' => []], 'render' => at_av_registro_vacio(), 'render_previa' => at_av_registro_vacio(),
	];
}

/** Avance guardado (texto JSON o arreglo) con todas sus claves; lo que no tiene la forma esperada vuelve a vacío. */
function at_av_normalizar(mixed $guardado): array {
	if (is_string($guardado)) {
		$guardado = $guardado === '' ? [] : json_decode($guardado, true);
	}
	$a = at_av_vacio();
	if (!is_array($guardado)) {
		return $a;
	}
	foreach (['bloques', 'evidencias'] as $k) {
		if (is_array($guardado[$k] ?? null)) {
			$a[$k] = array_filter($guardado[$k], 'is_array');
		}
	}
	foreach (['cierres', 'cambios_plan', 'necesitamos'] as $k) {
		if (is_array($guardado[$k] ?? null)) {
			$a[$k] = array_values(array_filter($guardado[$k], 'is_array'));
		}
	}
	if (is_array($guardado['alcance'] ?? null)) {
		foreach (['fase1', 'mensual'] as $k) {
			$a['alcance'][$k] = array_values(array_filter((array) ($guardado['alcance'][$k] ?? []), 'is_string'));
		}
	}
	foreach (['render', 'render_previa'] as $k) {
		if (is_array($guardado[$k] ?? null)) {
			$a[$k] = array_merge(at_av_registro_vacio(), array_intersect_key($guardado[$k], at_av_registro_vacio()));
		}
	}
	return $a;
}

/** Texto sin caracteres de control; en una sola línea salvo $multilinea. No recorta ni quita nada más: un «<» suelto
 *  se guarda tal cual (toda salida escapa). Las etiquetas HTML las rechaza at_av_texto(). */
function at_av_limpiar(mixed $v, bool $multilinea = false): string {
	if (!is_string($v) && !is_int($v) && !is_float($v)) {
		return '';
	}
	$t = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $v);
	$t = str_replace(["\r\n", "\r"], "\n", $t);
	$t = $multilinea ? (string) preg_replace("/\n{3,}/", "\n\n", $t) : (string) preg_replace('/\s+/u', ' ', $t);
	return trim($t);
}

/** Texto de un campo con tope. Pasarse del tope o llevar una etiqueta HTML es un error: nada se recorta en silencio. */
function at_av_texto(mixed $v, int $max, string $que, array &$errores, bool $multilinea = false): string {
	$t = at_av_limpiar($v, $multilinea);
	if (preg_match('/<\/?[a-z!][^>]*>/i', $t)) {
		$errores[] = "{$que} no puede llevar etiquetas HTML.";
		return '';
	}
	if (mb_strlen($t) > $max) {
		$errores[] = "{$que} tiene " . mb_strlen($t) . " caracteres (máximo {$max}).";
		return '';
	}
	return $t;
}

function at_av_fecha_valida(mixed $f): bool {
	return is_string($f) && (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
		&& (int) $m[1] >= 2020 && (int) $m[1] <= 2100;
}

/** Enlace que puede ver el cliente: https, sin espacios ni comillas, hasta 500 caracteres y nunca del servidor de
 *  Easypanel (el SMTP de Hostinger rechaza esos correos: D8). '' si no sirve. */
function at_av_url_publica(mixed $u): string {
	$u = is_string($u) ? trim($u) : '';
	if ($u === '' || strlen($u) > 500 || !preg_match('#^https://[^\s"\'<>]+$#i', $u)) {
		return '';
	}
	$host = strtolower((string) parse_url($u, PHP_URL_HOST));
	return ($host === '' || str_contains($host, 'easypanel')) ? '' : $u;
}

/** Clave para comparar nombres de bloques y títulos: minúsculas y espacios simples. */
function at_av_clave(string $s): string {
	return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
}

/** Bloques del plan firmado, en su orden: nombre, fase (clave), fechas planificadas (primera y última actividad) y entregable. */
function at_av_bloques_plan(array $plan): array {
	$out = [];
	foreach ((array) ($plan['fases'] ?? []) as $fase) {
		if (!is_array($fase)) {
			continue;
		}
		foreach ((array) ($fase['bloques'] ?? []) as $b) {
			if (!is_array($b) || trim((string) ($b['nombre'] ?? '')) === '') {
				continue;
			}
			$desde = '';
			$hasta = '';
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				$d = (string) ($a['desde'] ?? '');
				$h = (string) ($a['hasta'] ?? '');
				if (at_av_fecha_valida($d) && ($desde === '' || $d < $desde)) {
					$desde = $d;
				}
				if (at_av_fecha_valida($h) && $h > $hasta) {
					$hasta = $h;
				}
			}
			$out[] = ['nombre' => (string) $b['nombre'], 'fase' => (string) ($fase['clave'] ?? ''), 'plan_desde' => $desde, 'plan_hasta' => $hasta, 'entregable' => (string) ($b['entregable'] ?? '')];
		}
	}
	return $out;
}

/** Nombre del bloque del plan que calza con $nombre (sin importar mayúsculas ni espacios); '' si no existe. */
function at_av_bloque_del_plan(array $bloques_plan, mixed $nombre): string {
	$k = at_av_clave(is_string($nombre) ? $nombre : '');
	foreach ($bloques_plan as $b) {
		if ($k !== '' && at_av_clave((string) $b['nombre']) === $k) {
			return (string) $b['nombre'];
		}
	}
	return '';
}

/** Actividades planificadas de esos bloques (para precargar «Durante»). */
function at_av_actividades_plan(array $plan, array $nombres): array {
	$claves = array_map(fn($n) => at_av_clave((string) $n), $nombres);
	$out = [];
	foreach ((array) ($plan['fases'] ?? []) as $fase) {
		foreach ((array) (is_array($fase) ? ($fase['bloques'] ?? []) : []) as $b) {
			if (!is_array($b) || !in_array(at_av_clave((string) ($b['nombre'] ?? '')), $claves, true)) {
				continue;
			}
			foreach ((array) ($b['actividades'] ?? []) as $a) {
				if (is_array($a)) {
					$out[] = ['bloque' => (string) $b['nombre'], 'nombre' => (string) ($a['nombre'] ?? ''), 'plan_desde' => (string) ($a['desde'] ?? ''),
						'plan_hasta' => (string) ($a['hasta'] ?? ''), 'real' => '', 'nota' => ''];
				}
			}
		}
	}
	return $out;
}

/** Una evidencia del archivo importado, normalizada; null (con su error) si no sirve. */
function at_av_validar_evidencia(mixed $e, int $n, array &$errores): ?array {
	if (!is_array($e)) {
		$errores[] = "La evidencia {$n} no tiene la forma esperada.";
		return null;
	}
	$antes = count($errores);
	$ref = is_string($e['ref'] ?? null) ? strtolower(trim($e['ref'])) : '';
	if (!preg_match('/^(?=[a-z0-9-]*[a-z])[a-z0-9][a-z0-9-]{0,39}$/', $ref)) {
		$errores[] = "La evidencia {$n} no tiene «ref» (minúsculas, números y guiones; hasta 40).";
		return null;
	}
	$titulo = at_av_texto($e['titulo'] ?? null, 120, "El título de la evidencia «{$ref}»", $errores);
	if ($titulo === '' && count($errores) === $antes) {
		$errores[] = "La evidencia «{$ref}» no tiene título.";
	}
	$nombre = $titulo !== '' ? $titulo : $ref;
	$tipo = is_string($e['tipo'] ?? null) ? strtolower(trim($e['tipo'])) : '';
	if (!in_array($tipo, AT_AV_TIPOS, true)) {
		$errores[] = "La evidencia «{$nombre}» tiene un tipo desconocido (captura, enlace, cifra o video).";
		return null;
	}
	$fecha = $e['fecha'] ?? '';
	if ($fecha !== '' && $fecha !== null && !at_av_fecha_valida($fecha)) {
		$errores[] = "La fecha de la evidencia «{$nombre}» no es una fecha válida (AAAA-MM-DD).";
		$fecha = '';
	}
	$ev = ['ref' => $ref, 'tipo' => $tipo, 'titulo' => $titulo, 'url' => '', 'archivo' => '', 'poster' => '', 'poster_archivo' => '',
		'valor' => '', 'unidad' => '', 'fuente' => at_av_texto($e['fuente'] ?? null, 200, "La fuente de «{$nombre}»", $errores), 'fecha' => (string) ($fecha ?? '')];
	if ($tipo === 'cifra') {
		$ev['valor'] = at_av_texto($e['valor'] ?? null, 40, "El valor de «{$nombre}»", $errores);
		$ev['unidad'] = at_av_texto($e['unidad'] ?? null, 60, "La unidad de «{$nombre}»", $errores);
		// D6: ninguna cifra sin fuente y fecha.
		if ($ev['valor'] === '') {
			$errores[] = "La cifra «{$nombre}» no tiene valor.";
		}
		if ($ev['fuente'] === '') {
			$errores[] = "La cifra «{$nombre}» no tiene fuente.";
		}
		if ($ev['fecha'] === '') {
			$errores[] = "La cifra «{$nombre}» no tiene fecha.";
		}
	} elseif ($tipo === 'enlace') {
		$ev['url'] = at_av_url_publica($e['url'] ?? null);
		if ($ev['url'] === '') {
			$errores[] = "El enlace «{$nombre}» debe ser una dirección https que no sea del servidor interno.";
		}
	} else {
		$ext = $tipo === 'video' ? 'mp4|webm' : 'jpe?g|png|webp';
		$archivo = is_string($e['archivo'] ?? null) ? trim($e['archivo']) : '';
		if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(' . $ext . ')$/i', $archivo)) {
			$errores[] = "La evidencia «{$nombre}» necesita «archivo» con un nombre de " . ($tipo === 'video' ? 'video (.mp4 o .webm).' : 'imagen (.jpg, .png o .webp).');
		}
		$ev['archivo'] = $archivo;
		if ($tipo === 'video' && ($e['poster'] ?? '') !== '' && ($e['poster'] ?? null) !== null) {
			$p = is_string($e['poster']) ? trim($e['poster']) : '';
			if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(jpe?g|png|webp)$/i', $p)) {
				$errores[] = "El póster del video «{$nombre}» debe ser una imagen (.jpg, .png o .webp).";
			} else {
				$ev['poster_archivo'] = $p;
			}
		}
	}
	return count($errores) === $antes ? $ev : null;
}

/** «Antes» o «Después»: texto, hasta AT_AV_MAX_PUNTOS puntos y las refs de sus evidencias (deben existir). */
function at_av_validar_parte(mixed $p, string $que, array $refs, array &$errores): array {
	$p = is_array($p) ? $p : [];
	$out = ['texto' => at_av_texto($p['texto'] ?? null, AT_AV_MAX_TEXTO, "El texto de «{$que}»", $errores, true), 'puntos' => [], 'evidencias' => []];
	$puntos = $p['puntos'] ?? [];
	if (!is_array($puntos)) {
		$errores[] = "Los puntos de «{$que}» deben ser una lista.";
		$puntos = [];
	}
	if (count($puntos) > AT_AV_MAX_PUNTOS) {
		$errores[] = "«{$que}» trae " . count($puntos) . ' puntos (máximo ' . AT_AV_MAX_PUNTOS . ').';
	}
	foreach (array_slice(array_values($puntos), 0, AT_AV_MAX_PUNTOS) as $i => $t) {
		$x = at_av_texto($t, AT_AV_MAX_PUNTO, 'El punto ' . ($i + 1) . " de «{$que}»", $errores);
		if ($x !== '') {
			$out['puntos'][] = $x;
		}
	}
	foreach ((array) ($p['evidencias'] ?? []) as $r) {
		$r = is_string($r) ? strtolower(trim($r)) : '';
		if (!in_array($r, $refs, true)) {
			$errores[] = "«{$que}» nombra una evidencia que no está en la lista: «{$r}».";
			continue;
		}
		if (!in_array($r, $out['evidencias'], true)) {
			$out['evidencias'][] = $r;
		}
	}
	return $out;
}

/** Estado de un bloque: listo, en curso o por venir, con fechas reales válidas (fin no antes del inicio). */
function at_av_validar_estado_bloque(mixed $x, string $nombre, array &$errores): ?array {
	$x = is_array($x) ? $x : [];
	$estado = is_string($x['estado'] ?? null) ? $x['estado'] : '';
	if (!in_array($estado, AT_AV_ESTADOS_BLOQUE, true)) {
		$errores[] = "El bloque «{$nombre}» tiene un estado desconocido (listo, en_curso o por_venir).";
		return null;
	}
	$fechas = [];
	foreach (['inicio_real', 'fin_real'] as $k) {
		$f = $x[$k] ?? '';
		if ($f !== '' && $f !== null && !at_av_fecha_valida($f)) {
			$errores[] = "La fecha «{$k}» del bloque «{$nombre}» no es válida (AAAA-MM-DD).";
			return null;
		}
		$fechas[$k] = (string) ($f ?? '');
	}
	if ($fechas['inicio_real'] !== '' && $fechas['fin_real'] !== '' && $fechas['fin_real'] < $fechas['inicio_real']) {
		$errores[] = "El bloque «{$nombre}» termina antes de empezar.";
		return null;
	}
	return ['estado' => $estado] + $fechas;
}

function at_av_validar_cambios(mixed $l, array $bp, array &$errores): array {
	if (!is_array($l)) {
		$errores[] = '«cambios_plan» debe ser una lista.';
		return [];
	}
	if (count($l) > AT_AV_MAX_CAMBIOS) {
		$errores[] = 'Hay ' . count($l) . ' cambios de plan (máximo ' . AT_AV_MAX_CAMBIOS . ').';
	}
	$out = [];
	foreach (array_slice(array_values($l), 0, AT_AV_MAX_CAMBIOS) as $i => $c) {
		$c = is_array($c) ? $c : [];
		$n = $i + 1;
		if (!at_av_fecha_valida($c['fecha'] ?? null)) {
			$errores[] = "El cambio de plan {$n} no tiene una fecha válida (AAAA-MM-DD).";
			continue;
		}
		$titulo = at_av_texto($c['titulo'] ?? null, 120, "El título del cambio de plan {$n}", $errores);
		if ($titulo === '') {
			$errores[] = "El cambio de plan {$n} no tiene título.";
			continue;
		}
		$bloques = [];
		foreach ((array) ($c['bloques'] ?? []) as $nb) {
			$b = at_av_bloque_del_plan($bp, $nb);
			if ($b === '') {
				$errores[] = "El cambio «{$titulo}» nombra un bloque que no está en el plan.";
			} elseif (!in_array($b, $bloques, true)) {
				$bloques[] = $b;
			}
		}
		$out[] = ['fecha' => (string) $c['fecha'], 'titulo' => $titulo, 'detalle' => at_av_texto($c['detalle'] ?? null, 600, "El detalle de «{$titulo}»", $errores, true), 'bloques' => $bloques];
	}
	return $out;
}

function at_av_validar_necesitamos(mixed $l, array &$errores): array {
	if (!is_array($l)) {
		$errores[] = '«necesitamos» debe ser una lista.';
		return [];
	}
	if (count($l) > AT_AV_MAX_NECESITAMOS) {
		$errores[] = 'Hay ' . count($l) . ' ítems en «necesitamos» (máximo ' . AT_AV_MAX_NECESITAMOS . ').';
	}
	$out = [];
	foreach (array_slice(array_values($l), 0, AT_AV_MAX_NECESITAMOS) as $i => $x) {
		$x = is_array($x) ? $x : ['texto' => $x];
		$texto = at_av_texto($x['texto'] ?? null, 200, 'El ítem ' . ($i + 1) . ' de «necesitamos»', $errores);
		if ($texto === '') {
			continue;
		}
		$estado = ($x['estado'] ?? 'abierto') === 'resuelto' ? 'resuelto' : 'abierto';
		$fecha = at_av_fecha_valida($x['fecha'] ?? null) ? (string) $x['fecha'] : '';
		$out[] = ['texto' => $texto, 'estado' => $estado, 'fecha' => $fecha];
	}
	return $out;
}

function at_av_validar_alcance(mixed $a, array &$errores): array {
	$a = is_array($a) ? $a : [];
	$out = ['fase1' => [], 'mensual' => []];
	foreach (['fase1' => 'Fase 1', 'mensual' => 'Servicio mensual'] as $k => $que) {
		$l = $a[$k] ?? [];
		if (!is_array($l)) {
			$errores[] = "«{$que}» del alcance debe ser una lista.";
			continue;
		}
		if (count($l) > AT_AV_MAX_ALCANCE) {
			$errores[] = "«{$que}» trae " . count($l) . ' ítems (máximo ' . AT_AV_MAX_ALCANCE . ').';
		}
		foreach (array_slice(array_values($l), 0, AT_AV_MAX_ALCANCE) as $i => $t) {
			$x = at_av_texto($t, 200, "El ítem " . ($i + 1) . " de «{$que}»", $errores);
			if ($x !== '') {
				$out[$k][] = $x;
			}
		}
	}
	return $out;
}

/**
 * Valida un archivo «Importar cierre» (at-cierre-v1, spec §4) contra el plan abierto. Todo o nada: con errores no
 * devuelve nada que guardar. Los errores van en lenguaje simple (hasta 25). Un cierre publicado con el mismo título no
 * se pisa (hay que pasarlo a borrador primero). Las listas cambios_plan, necesitamos y alcance que no vienen quedan null
 * (al guardar no se tocan).
 */
function at_av_validar_import(mixed $d, string $codigo, array $plan, array $avance): array {
	$resultado = fn(array $errores, array $avisos = [], array $cierre = [], array $evidencias = [], array $bloques = [], ?array $cambios = null, ?array $necesitamos = null, ?array $alcance = null) =>
		['ok' => !$errores, 'errores' => $errores, 'avisos' => $avisos, 'cierre' => $errores ? [] : $cierre, 'evidencias' => $errores ? [] : $evidencias,
			'bloques' => $errores ? [] : $bloques, 'cambios_plan' => $errores ? null : $cambios, 'necesitamos' => $errores ? null : $necesitamos, 'alcance' => $errores ? null : $alcance];
	if (!is_array($d) || ($d['formato'] ?? '') !== 'at-cierre-v1') {
		return $resultado(['El archivo no es un cierre de AutomatizaTech (falta «formato»: «at-cierre-v1»).']);
	}
	if (!is_string($d['plan'] ?? null) || $d['plan'] !== $codigo) {
		return $resultado(['El archivo es de otro plan («' . at_av_limpiar($d['plan'] ?? '') . '»); este plan es «' . $codigo . '».']);
	}
	$errores = [];
	$avisos = [];
	$bp = at_av_bloques_plan($plan);

	$evs = $d['evidencias'] ?? [];
	if (!is_array($evs)) {
		$errores[] = '«evidencias» debe ser una lista.';
		$evs = [];
	}
	if (count($evs) > AT_AV_MAX_EVIDENCIAS) {
		$errores[] = 'El archivo trae ' . count($evs) . ' evidencias (máximo ' . AT_AV_MAX_EVIDENCIAS . ').';
		$evs = [];
	}
	$evidencias = [];
	foreach (array_values($evs) as $i => $e) {
		$ev = at_av_validar_evidencia($e, $i + 1, $errores);
		if ($ev === null) {
			continue;
		}
		if (isset($evidencias[$ev['ref']])) {
			$errores[] = "Hay dos evidencias con la misma «ref»: «{$ev['ref']}».";
			continue;
		}
		$evidencias[$ev['ref']] = $ev;
	}
	$refs = array_keys($evidencias);

	$c = is_array($d['cierre'] ?? null) ? $d['cierre'] : null;
	if ($c === null) {
		return $resultado(array_merge(['Falta «cierre».'], $errores));
	}
	$titulo = at_av_texto($c['titulo'] ?? null, 120, 'El título del cierre', $errores);
	if ($titulo === '') {
		$errores[] = 'El cierre no tiene título.';
	}
	$bloques = [];
	foreach ((array) ($c['bloques'] ?? []) as $nb) {
		$b = at_av_bloque_del_plan($bp, $nb);
		if ($b === '') {
			$errores[] = 'El bloque «' . at_av_limpiar($nb) . '» no está en el plan.';
		} elseif (!in_array($b, $bloques, true)) {
			$bloques[] = $b;
		}
	}
	if (!$bloques && !array_filter($errores, fn($e) => str_contains($e, 'no está en el plan'))) {
		$errores[] = 'El cierre debe nombrar al menos un bloque del plan.';
	}
	$fecha_real = $c['fecha_real'] ?? '';
	if (!at_av_fecha_valida($fecha_real)) {
		$errores[] = 'La fecha real del cierre no es una fecha válida (AAAA-MM-DD).';
		$fecha_real = '';
	}
	$ent = $c['entregable_id'] ?? 0;
	$entregable = (is_int($ent) || (is_string($ent) && ctype_digit($ent))) ? (int) $ent : -1;
	if ($entregable < 0) {
		$errores[] = '«entregable_id» debe ser un número (0 si no hay).';
		$entregable = 0;
	}
	$antes = at_av_validar_parte($c['antes'] ?? null, 'Antes', $refs, $errores);
	$despues = at_av_validar_parte($c['despues'] ?? null, 'Después', $refs, $errores);
	$dur = is_array($c['durante'] ?? null) ? $c['durante'] : [];
	$durante = ['texto' => at_av_texto($dur['texto'] ?? null, AT_AV_MAX_TEXTO, 'El texto de «Durante»', $errores, true), 'actividades' => []];
	$plan_acts = at_av_actividades_plan($plan, $bloques);
	if (!array_key_exists('actividades', $dur)) {
		$durante['actividades'] = $plan_acts; // spec §3: se precargan desde el plan
	} elseif (!is_array($dur['actividades'])) {
		$errores[] = 'Las actividades de «Durante» deben ser una lista.';
	} else {
		if (count($dur['actividades']) > AT_AV_MAX_ACTIVIDADES) {
			$errores[] = '«Durante» trae ' . count($dur['actividades']) . ' actividades (máximo ' . AT_AV_MAX_ACTIVIDADES . ').';
		}
		foreach (array_slice(array_values($dur['actividades']), 0, AT_AV_MAX_ACTIVIDADES) as $i => $a) {
			if (!is_array($a)) {
				$errores[] = 'La actividad ' . ($i + 1) . ' de «Durante» no tiene la forma esperada.';
				continue;
			}
			$nombre = at_av_texto($a['nombre'] ?? null, 120, 'El nombre de la actividad ' . ($i + 1), $errores);
			if ($nombre === '') {
				$errores[] = 'La actividad ' . ($i + 1) . ' de «Durante» no tiene nombre.';
				continue;
			}
			$bloque = at_av_bloque_del_plan($bp, $a['bloque'] ?? '');
			if ($bloque === '' || !in_array($bloque, $bloques, true)) {
				$errores[] = "La actividad «{$nombre}» es de un bloque que este cierre no cubre.";
				continue;
			}
			$real = $a['real'] ?? '';
			if ($real !== '' && $real !== null && !at_av_fecha_valida($real)) {
				$errores[] = "La fecha real de «{$nombre}» no es válida (AAAA-MM-DD).";
				$real = '';
			}
			$fila = ['bloque' => $bloque, 'nombre' => $nombre, 'plan_desde' => '', 'plan_hasta' => '', 'real' => (string) ($real ?? ''),
				'nota' => at_av_texto($a['nota'] ?? null, 300, "La nota de «{$nombre}»", $errores)];
			$hallada = false;
			foreach ($plan_acts as $pa) {
				if ($pa['bloque'] === $bloque && at_av_clave($pa['nombre']) === at_av_clave($nombre)) {
					$fila['plan_desde'] = $pa['plan_desde'];
					$fila['plan_hasta'] = $pa['plan_hasta'];
					$hallada = true;
					break;
				}
			}
			if (!$hallada) {
				$avisos[] = "La actividad «{$nombre}» no está en el plan: va sin fechas planificadas.";
			}
			$durante['actividades'][] = $fila;
		}
	}
	$usadas = array_unique(array_merge($antes['evidencias'], $despues['evidencias']));
	foreach ($refs as $r) {
		if (!in_array($r, $usadas, true)) {
			$avisos[] = "La evidencia «{$evidencias[$r]['titulo']}» no está en «Antes» ni en «Después»: no se mostrará.";
		}
	}

	$reemplaza = null;
	foreach ($avance['cierres'] ?? [] as $x) {
		if ($titulo !== '' && at_av_clave((string) ($x['titulo'] ?? '')) === at_av_clave($titulo)) {
			$reemplaza = $x;
			if (($x['estado'] ?? '') === 'publicado') {
				$errores[] = "El cierre «{$titulo}» ya está publicado: pásalo a borrador antes de importarlo de nuevo.";
			}
		}
	}
	if ($reemplaza === null && count($avance['cierres'] ?? []) >= AT_AV_MAX_CIERRES) {
		$errores[] = 'El plan ya tiene ' . AT_AV_MAX_CIERRES . ' cierres (el máximo).';
	}
	$viejas = $reemplaza ? count(array_unique(array_merge((array) ($reemplaza['antes']['evidencias'] ?? []), (array) ($reemplaza['despues']['evidencias'] ?? [])))) : 0;
	if (count($avance['evidencias'] ?? []) - $viejas + count($evidencias) > AT_AV_MAX_EVIDENCIAS) {
		$errores[] = 'Con este cierre el plan pasaría las ' . AT_AV_MAX_EVIDENCIAS . ' evidencias.';
	}

	$estado_bloques = [];
	if (array_key_exists('bloques', $d)) {
		if (!is_array($d['bloques'])) {
			$errores[] = '«bloques» debe ser un objeto con el nombre de cada bloque.';
		} else {
			foreach ($d['bloques'] as $nb => $x) {
				$b = at_av_bloque_del_plan($bp, (string) $nb);
				if ($b === '') {
					$errores[] = 'El bloque «' . at_av_limpiar((string) $nb) . '» no está en el plan.';
					continue;
				}
				$eb = at_av_validar_estado_bloque($x, $b, $errores);
				if ($eb !== null) {
					$estado_bloques[$b] = $eb;
				}
			}
		}
	}
	$cambios = array_key_exists('cambios_plan', $d) ? at_av_validar_cambios($d['cambios_plan'], $bp, $errores) : null;
	$necesitamos = array_key_exists('necesitamos', $d) ? at_av_validar_necesitamos($d['necesitamos'], $errores) : null;
	$alcance = array_key_exists('alcance', $d) ? at_av_validar_alcance($d['alcance'], $errores) : null;

	if (count($errores) > 25) {
		$errores = array_merge(array_slice($errores, 0, 25), ['… y ' . (count($errores) - 25) . ' más.']);
	}
	$cierre = ['titulo' => $titulo, 'bloques' => $bloques, 'fecha_real' => (string) $fecha_real, 'entregable_id' => $entregable,
		'antes' => $antes, 'durante' => $durante, 'despues' => $despues];
	return $resultado($errores, $avisos, $cierre, $evidencias, $estado_bloques, $cambios, $necesitamos, $alcance);
}

/** Nombres de archivo (captura, video y póster) que usan esas evidencias. */
function at_av_archivos_de(array $evidencias): array {
	$out = [];
	foreach ($evidencias as $e) {
		foreach (['url', 'poster'] as $k) {
			$u = (string) ($e[$k] ?? '');
			if ($u !== '' && in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
				$out[] = basename((string) parse_url($u, PHP_URL_PATH));
			}
		}
	}
	return $out;
}

/**
 * Guarda lo validado: crea el cierre en borrador, o reemplaza el borrador del mismo título conservando su id. Las
 * evidencias con la misma ref y el mismo nombre de archivo conservan el archivo ya subido (Review Focus 1); las demás
 * evidencias viejas salen y sus archivos vuelven en 'archivos' para borrarlos. Bloques nombrados se reemplazan;
 * cambios_plan, necesitamos y alcance solo si el archivo los trae. $nuevo_id: 'c' + 6 minúsculas o números.
 */
function at_av_aplicar_import(array $avance, array $v, string $nuevo_id, string $ahora): array {
	$id = $nuevo_id;
	$viejas = [];
	foreach ($avance['cierres'] as $i => $x) {
		if (at_av_clave((string) ($x['titulo'] ?? '')) === at_av_clave($v['cierre']['titulo'])) {
			$id = (string) $x['id'];
			foreach (array_merge((array) ($x['antes']['evidencias'] ?? []), (array) ($x['despues']['evidencias'] ?? [])) as $eid) {
				if (isset($avance['evidencias'][$eid])) {
					$viejas[$eid] = $avance['evidencias'][$eid];
					unset($avance['evidencias'][$eid]);
				}
			}
			unset($avance['cierres'][$i]);
		}
	}
	$mapa = [];
	$conservados = [];
	// Solo se guardan las evidencias que «Antes» o «Después» nombran (el validador ya avisó de las demás).
	$usadas = array_merge($v['cierre']['antes']['evidencias'], $v['cierre']['despues']['evidencias']);
	foreach ($v['evidencias'] as $ref => $ev) {
		if (!in_array($ref, $usadas, true)) {
			continue;
		}
		$eid = $id . '-' . $ref;
		$mapa[$ref] = $eid;
		unset($ev['ref']);
		$antes = $viejas[$eid] ?? null;
		if ($antes && ($antes['tipo'] ?? '') === $ev['tipo'] && in_array($ev['tipo'], ['captura', 'video'], true)) {
			if (($antes['archivo'] ?? '') === $ev['archivo'] && ($antes['url'] ?? '') !== '') {
				$ev['url'] = $antes['url'];
				$conservados[] = basename((string) parse_url($antes['url'], PHP_URL_PATH));
			}
			if ($ev['poster_archivo'] !== '' && ($antes['poster_archivo'] ?? '') === $ev['poster_archivo'] && ($antes['poster'] ?? '') !== '') {
				$ev['poster'] = $antes['poster'];
				$conservados[] = basename((string) parse_url($antes['poster'], PHP_URL_PATH));
			}
		}
		$avance['evidencias'][$eid] = $ev;
	}
	$archivos = array_values(array_diff(at_av_archivos_de($viejas), $conservados));
	$parte = fn(array $p) => ['texto' => $p['texto'], 'puntos' => $p['puntos'], 'evidencias' => array_map(fn($r) => $mapa[$r], $p['evidencias'])];
	$c = $v['cierre'];
	$avance['cierres'][] = [
		'id' => $id, 'titulo' => $c['titulo'], 'bloques' => $c['bloques'], 'fecha_real' => $c['fecha_real'], 'estado' => 'borrador',
		'publicado_at' => '', 'entregable_id' => $c['entregable_id'], 'antes' => $parte($c['antes']), 'durante' => $c['durante'],
		'despues' => $parte($c['despues']), 'correo' => ['enviado_at' => null, 'prueba_at' => null, 'mensaje' => ''],
		'whatsapp' => ['abierto_at' => null], 'importado_at' => $ahora,
	];
	$avance['cierres'] = array_values($avance['cierres']);
	foreach ($v['bloques'] as $n => $b) {
		$avance['bloques'][$n] = $b;
	}
	foreach (['cambios_plan', 'necesitamos', 'alcance'] as $k) {
		if ($v[$k] !== null) {
			$avance[$k] = $v[$k];
		}
	}
	return ['avance' => $avance, 'cierre_id' => $id, 'archivos' => $archivos];
}

/** Evidencias de captura o video de ese cierre sin su archivo subido: [id => título]. El póster es opcional. */
function at_av_faltan_archivos(array $avance, string $cierre_id): array {
	$out = [];
	foreach ($avance['cierres'] as $c) {
		if (($c['id'] ?? '') !== $cierre_id) {
			continue;
		}
		foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
			$e = $avance['evidencias'][$eid] ?? null;
			if (is_array($e) && in_array($e['tipo'] ?? '', ['captura', 'video'], true) && ($e['url'] ?? '') === '') {
				$out[$eid] = (string) $e['titulo'];
			}
		}
	}
	return $out;
}

/** Publica o vuelve a borrador un cierre. Devuelve el avance nuevo o el motivo (texto) si no se puede. */
function at_av_cambiar_estado_cierre(array $avance, string $id, string $estado, string $ahora): array|string {
	foreach ($avance['cierres'] as $i => $c) {
		if (($c['id'] ?? '') !== $id) {
			continue;
		}
		if ($estado === 'publicado') {
			$faltan = at_av_faltan_archivos($avance, $id);
			if ($faltan) {
				return 'Falta subir el archivo de: ' . implode(', ', $faltan) . '.';
			}
			$avance['cierres'][$i]['estado'] = 'publicado';
			$avance['cierres'][$i]['publicado_at'] = $ahora;
		} elseif ($estado === 'borrador') {
			$avance['cierres'][$i]['estado'] = 'borrador';
			$avance['cierres'][$i]['publicado_at'] = '';
		} else {
			return 'Estado de cierre desconocido.';
		}
		return $avance;
	}
	return 'Ese cierre no existe.';
}

/** Quita un cierre en borrador con sus evidencias. ['avance', 'archivos' (para borrar)] o el motivo. */
function at_av_quitar_cierre(array $avance, string $id): array|string {
	foreach ($avance['cierres'] as $i => $c) {
		if (($c['id'] ?? '') !== $id) {
			continue;
		}
		if (($c['estado'] ?? '') !== 'borrador') {
			return 'Solo se quita un cierre en borrador.';
		}
		$quitadas = [];
		foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
			if (isset($avance['evidencias'][$eid])) {
				$quitadas[$eid] = $avance['evidencias'][$eid];
				unset($avance['evidencias'][$eid]);
			}
		}
		unset($avance['cierres'][$i]);
		$avance['cierres'] = array_values($avance['cierres']);
		return ['avance' => $avance, 'archivos' => at_av_archivos_de($quitadas)];
	}
	return 'Ese cierre no existe.';
}

/** Cierres publicados, del más antiguo al más nuevo (por fecha real). */
function at_av_publicados(array $avance): array {
	$l = array_values(array_filter($avance['cierres'], fn($c) => ($c['estado'] ?? '') === 'publicado'));
	usort($l, fn($a, $b) => strcmp((string) ($a['fecha_real'] ?? ''), (string) ($b['fecha_real'] ?? '')));
	return $l;
}

/** Bloques del plan con su estado para el cliente: el que dejó Luis o la importación, o «listo» si un cierre PUBLICADO
 *  los cubre (fin real = fecha del cierre si no traía una). Un borrador no marca nada. */
function at_av_estado_bloques(array $plan, array $avance): array {
	$publicados = at_av_publicados($avance);
	$out = [];
	foreach (at_av_bloques_plan($plan) as $b) {
		$x = is_array($avance['bloques'][$b['nombre']] ?? null) ? $avance['bloques'][$b['nombre']] : [];
		$fila = $b + [
			'estado' => in_array($x['estado'] ?? '', AT_AV_ESTADOS_BLOQUE, true) ? (string) $x['estado'] : 'por_venir',
			'inicio_real' => (string) ($x['inicio_real'] ?? ''), 'fin_real' => (string) ($x['fin_real'] ?? ''),
		];
		foreach ($publicados as $c) {
			if (in_array($b['nombre'], (array) ($c['bloques'] ?? []), true)) {
				$fila['estado'] = 'listo';
				if ($fila['fin_real'] === '') {
					$fila['fin_real'] = (string) $c['fecha_real'];
				}
			}
		}
		$out[] = $fila;
	}
	return $out;
}

/** Un cierre como archivo at-cierre-v1 (para «Editar», R2): vuelve a validar igual. [] si no existe. */
function at_av_exportar_cierre(array $avance, string $id, string $codigo): array {
	foreach ($avance['cierres'] as $c) {
		if (($c['id'] ?? '') !== $id) {
			continue;
		}
		$pref = $id . '-';
		$ref = fn(string $eid) => str_starts_with($eid, $pref) ? substr($eid, strlen($pref)) : $eid;
		$evidencias = [];
		foreach (array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? [])) as $eid) {
			$e = $avance['evidencias'][$eid] ?? null;
			if (!is_array($e) || isset($evidencias[$eid])) {
				continue;
			}
			$x = ['ref' => $ref($eid), 'tipo' => $e['tipo'], 'titulo' => $e['titulo']];
			if ($e['tipo'] === 'cifra') {
				$x += ['valor' => $e['valor'], 'unidad' => $e['unidad'], 'fuente' => $e['fuente'], 'fecha' => $e['fecha']];
			} elseif ($e['tipo'] === 'enlace') {
				$x['url'] = $e['url'];
			} else {
				$x['archivo'] = $e['archivo'];
				if (($e['poster_archivo'] ?? '') !== '') {
					$x['poster'] = $e['poster_archivo'];
				}
			}
			if ($e['tipo'] !== 'cifra' && ($e['fuente'] ?? '') !== '') {
				$x['fuente'] = $e['fuente'];
			}
			if ($e['tipo'] !== 'cifra' && ($e['fecha'] ?? '') !== '') {
				$x['fecha'] = $e['fecha'];
			}
			$evidencias[$eid] = $x;
		}
		$parte = fn(array $p) => ['texto' => (string) ($p['texto'] ?? ''), 'puntos' => (array) ($p['puntos'] ?? []), 'evidencias' => array_map($ref, (array) ($p['evidencias'] ?? []))];
		$actividades = array_map(fn($a) => ['bloque' => $a['bloque'], 'nombre' => $a['nombre'], 'real' => $a['real'], 'nota' => $a['nota']], (array) ($c['durante']['actividades'] ?? []));
		return [
			'formato' => 'at-cierre-v1', 'plan' => $codigo,
			'cierre' => ['titulo' => $c['titulo'], 'bloques' => $c['bloques'], 'fecha_real' => $c['fecha_real'], 'entregable_id' => (int) ($c['entregable_id'] ?? 0),
				'antes' => $parte($c['antes'] ?? []), 'durante' => ['texto' => (string) ($c['durante']['texto'] ?? ''), 'actividades' => $actividades], 'despues' => $parte($c['despues'] ?? [])],
			'evidencias' => array_values($evidencias),
		];
	}
	return [];
}

/* ---------- Textos del panel (sección «Avance», Task 7) ---------- */

/** «Cambios de plan»: una línea por cambio, «AAAA-MM-DD | título | detalle». Las líneas vacías se ignoran. El cuadro no
 *  trae los bloques: un cambio que ya estaba (misma fecha y mismo título) conserva los suyos. */
function at_av_cambios_de_texto(string $t, array $bloques_plan, array $anteriores, array &$errores): array {
	$bloques_de = [];
	foreach ($anteriores as $a) {
		$bloques_de[(string) ($a['fecha'] ?? '') . '|' . at_av_clave((string) ($a['titulo'] ?? ''))] = (array) ($a['bloques'] ?? []);
	}
	$lista = [];
	foreach (preg_split('/\r\n|\n|\r/', $t) as $i => $linea) {
		if (trim($linea) === '') {
			continue;
		}
		$p = array_map('trim', explode('|', $linea, 3));
		if (!at_av_fecha_valida($p[0])) {
			$errores[] = 'Cambios de plan, línea ' . ($i + 1) . ': empieza con la fecha AAAA-MM-DD («2026-10-13 | título | detalle»).';
			continue;
		}
		$titulo = $p[1] ?? '';
		if ($titulo === '') {
			$errores[] = 'Cambios de plan, línea ' . ($i + 1) . ': falta el título después de la fecha.';
			continue;
		}
		$lista[] = ['fecha' => $p[0], 'titulo' => $titulo, 'detalle' => $p[2] ?? '', 'bloques' => $bloques_de[$p[0] . '|' . at_av_clave($titulo)] ?? []];
	}
	return at_av_validar_cambios($lista, $bloques_plan, $errores);
}

function at_av_texto_de_cambios(array $l): string {
	return implode("\n", array_map(fn($c) => trim($c['fecha'] . ' | ' . $c['titulo'] . ' | ' . str_replace("\n", ' ', (string) $c['detalle'])), $l));
}

/** «Necesitamos de ti»: una línea por ítem; «✓ » al inicio = resuelto. Un ítem que ya estaba (mismo texto) conserva su fecha. */
function at_av_necesitamos_de_texto(string $t, array $anteriores, string $hoy, array &$errores): array {
	$fechas = [];
	foreach ($anteriores as $a) {
		$fechas[at_av_clave((string) ($a['texto'] ?? ''))] = (string) ($a['fecha'] ?? '');
	}
	$lista = [];
	foreach (preg_split('/\r\n|\n|\r/', $t) as $linea) {
		$linea = trim($linea);
		if ($linea === '') {
			continue;
		}
		$resuelto = str_starts_with($linea, '✓');
		$texto = trim($resuelto ? mb_substr($linea, 1) : $linea);
		$lista[] = ['texto' => $texto, 'estado' => $resuelto ? 'resuelto' : 'abierto', 'fecha' => ($fechas[at_av_clave($texto)] ?? '') ?: $hoy];
	}
	return at_av_validar_necesitamos($lista, $errores);
}

function at_av_texto_de_necesitamos(array $l): string {
	return implode("\n", array_map(fn($x) => (($x['estado'] ?? '') === 'resuelto' ? '✓ ' : '') . $x['texto'], $l));
}

/** Lista del alcance (Fase 1 o mensual): una por línea. */
function at_av_lista_de_texto(string $t, string $que, array &$errores): array {
	$l = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $t)), 'strlen'));
	return at_av_validar_alcance([$que === 'mensual' ? 'mensual' : 'fase1' => $l], $errores)[$que === 'mensual' ? 'mensual' : 'fase1'];
}

/** Tabla de bloques del panel (bloques[i][nombre|estado|inicio_real|fin_real]) → [nombre del plan => estado]. */
function at_av_bloques_de_post(mixed $post, array $plan, array &$errores): array {
	$bp = at_av_bloques_plan($plan);
	$out = [];
	foreach ((array) $post as $fila) {
		$fila = is_array($fila) ? $fila : [];
		$nombre = at_av_bloque_del_plan($bp, $fila['nombre'] ?? '');
		if ($nombre === '') {
			$errores[] = 'El bloque «' . at_av_limpiar($fila['nombre'] ?? '') . '» no está en el plan.';
			continue;
		}
		$eb = at_av_validar_estado_bloque($fila, $nombre, $errores);
		if ($eb !== null) {
			$out[$nombre] = $eb;
		}
	}
	return $out;
}

/* ---------- Cuerpo para el renderer (Task 5) ---------- */

/** Evidencias de un cierre para el renderer. No salen las de captura o video sin archivo, ni un enlace que no pase
 *  at_av_url_publica (D8). */
function at_av_evidencias_render(array $avance, array $ids): array {
	$out = [];
	foreach ($ids as $id) {
		$e = $avance['evidencias'][$id] ?? null;
		if (!is_array($e) || !in_array($e['tipo'] ?? '', AT_AV_TIPOS, true)) {
			continue;
		}
		$url = $e['tipo'] === 'cifra' ? '' : at_av_url_publica($e['url'] ?? '');
		if ($e['tipo'] !== 'cifra' && $url === '') {
			continue;
		}
		$out[] = ['tipo' => (string) $e['tipo'], 'titulo' => (string) ($e['titulo'] ?? ''), 'url' => $url, 'poster' => at_av_url_publica($e['poster'] ?? ''),
			'valor' => (string) ($e['valor'] ?? ''), 'unidad' => (string) ($e['unidad'] ?? ''), 'fuente' => (string) ($e['fuente'] ?? ''), 'fecha' => (string) ($e['fecha'] ?? '')];
	}
	return $out;
}

/**
 * Cuerpo de «Estado del proyecto» para el renderer (document_type 'estado', spec §7). Solo cierres publicados; con
 * $previa también los borradores (marcados) y la ruta <código>-previa-<token> (R3). $datos: lo de at_pt_datos_render()
 * más 'previa_token' y 'entregables' (id => URL pública). Sin fotos nuevas (D9): image_briefs vacío e images {} (n8n
 * pone las del plan). Todos los enlaces son de WordPress (D8).
 */
function at_av_armar_render_estado(array $plan, array $avance, array $datos, bool $previa, string $hoy): array {
	$base = at_pt_armar_render($plan, $datos, true);
	$codigo = (string) ($datos['codigo'] ?? '');
	$cierres = [];
	foreach ($avance['cierres'] as $c) {
		$publicado = ($c['estado'] ?? '') === 'publicado';
		if (!$publicado && !$previa) {
			continue;
		}
		$ent = (int) ($c['entregable_id'] ?? 0);
		$parte = fn(array $p) => ['texto' => (string) ($p['texto'] ?? ''), 'puntos' => array_values((array) ($p['puntos'] ?? [])),
			'evidencias' => at_av_evidencias_render($avance, (array) ($p['evidencias'] ?? []))];
		$cierres[] = [
			'id' => (string) $c['id'], 'titulo' => (string) $c['titulo'], 'bloques' => array_values((array) ($c['bloques'] ?? [])),
			'fecha_real' => (string) ($c['fecha_real'] ?? ''), 'borrador' => !$publicado,
			'entregable_url' => $ent > 0 ? at_av_url_publica($datos['entregables'][$ent] ?? '') : '',
			'antes' => $parte((array) ($c['antes'] ?? [])),
			'durante' => ['texto' => (string) ($c['durante']['texto'] ?? ''), 'actividades' => array_values((array) ($c['durante']['actividades'] ?? []))],
			'despues' => $parte((array) ($c['despues'] ?? [])),
		];
	}
	usort($cierres, fn($a, $b) => strcmp($a['fecha_real'], $b['fecha_real']));
	$bloques_plan = at_av_bloques_plan($plan);
	$fase1 = $avance['alcance']['fase1'] ?: array_values(array_unique(array_filter(array_column($bloques_plan, 'entregable'), 'strlen')));
	$mensual = $avance['alcance']['mensual'] ?: array_values(array_filter(array_map('strval', (array) ($plan['soporte']['mensuales'] ?? [])), 'strlen'));
	$crono = is_array($plan['cronograma'] ?? null) ? $plan['cronograma'] : [];
	return [
		'document_type' => 'estado',
		'unique_id'     => $previa ? $codigo . '-previa-' . (string) ($datos['previa_token'] ?? '') : $codigo . '-estado',
		'draft'         => $previa,
		'company_name'  => $base['company_name'],
		'client_name'   => $base['client_name'],
		'proyecto'      => $base['proyecto'],
		'actualizado'   => $hoy,
		'plan_url'      => at_pt_url_ver_plan((string) ($datos['sitio'] ?? ''), $codigo),
		'agenda'        => $base['agenda'],
		'cronograma'    => ['inicio' => (string) ($crono['inicio'] ?? ''), 'fin' => (string) ($crono['fin'] ?? ''), 'semanas' => (int) ($crono['semanas'] ?? 0)],
		'bloques'       => at_av_estado_bloques($plan, $avance),
		'cierres'       => $cierres,
		'cambios_plan'  => array_values($avance['cambios_plan']),
		'necesitamos'   => array_values(array_map(fn($x) => (string) $x['texto'], array_filter($avance['necesitamos'], fn($x) => ($x['estado'] ?? '') !== 'resuelto'))),
		'alcance'       => ['fase1' => array_slice($fase1, 0, AT_AV_MAX_ALCANCE), 'mensual' => array_slice($mensual, 0, AT_AV_MAX_ALCANCE)],
		'image_briefs'  => [],
		'images'        => new stdClass(),
	];
}
