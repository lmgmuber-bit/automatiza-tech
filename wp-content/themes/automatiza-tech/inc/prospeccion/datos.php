<?php
/** Rastro · Prospección: lectura, escritura, importación CSV y bajas. Toda consulta con $wpdb->prepare. */
if (!defined('ABSPATH')) { exit; }

/** Largos máximos por campo, iguales a las columnas (Calibre: wpdb corta en silencio, así que aquí se rechaza). */
function at_pro_largos(): array {
	return ['negocio' => 190, 'rubro' => 100, 'comuna' => 100, 'web' => 500, 'dominio' => 190, 'correo' => 190, 'servicio_sugerido' => 100, 'canal' => 20, 'senales' => 5000, 'notas' => 5000];
}

/** Texto en UTF-8 normalizado (NFC) para contar largos igual que la base. */
function at_pro_nfc(string $s): string {
	if (class_exists('Normalizer')) {
		$n = Normalizer::normalize($s, Normalizer::FORM_C);
		if (is_string($n)) { return $n; }
	}
	return $s;
}

function at_pro_obtener(int $id): ?array {
	global $wpdb;
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pro_tabla() . ' WHERE id = %d', $id), ARRAY_A);
	return $f ?: null;
}

function at_pro_por_token(string $token): ?array {
	global $wpdb;
	if (!at_pro_token_valido($token)) { return null; }
	$f = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . at_pro_tabla() . ' WHERE token = %s', $token), ARRAY_A);
	return $f ?: null;
}

/** ¿Existe un prospecto en baja que coincida por dominio, teléfono o correo, o el contacto está en la lista común de bajas? */
function at_pro_coincide_baja(string $dominio, string $telefono, string $correo): bool {
	global $wpdb;
	$t = at_pro_tabla();
	$cond = [];
	$args = [];
	if ($dominio !== '') { $cond[] = 'dominio = %s'; $args[] = $dominio; }
	if ($telefono !== '') { $cond[] = 'telefono = %s'; $args[] = $telefono; }
	if ($correo !== '') { $cond[] = 'correo = %s'; $args[] = $correo; }
	if ($cond) {
		$sql = "SELECT id FROM {$t} WHERE estado = 'baja' AND (" . implode(' OR ', $cond) . ') LIMIT 1';
		if ($wpdb->get_var($wpdb->prepare($sql, ...$args))) { return true; }
	}
	return function_exists('at_rt_en_bajas') && at_rt_en_bajas($correo, $telefono);
}

/**
 * Crea un prospecto. Errores: sin_negocio, demasiado_largo, sin_contacto, en_bajas, repetido, error_base.
 * Nunca corta un valor: lo que no cabe se rechaza.
 */
function at_pro_crear(array $campos) {
	global $wpdb;
	$t = at_pro_tabla();
	$limites = at_pro_largos();
	$v = [];
	foreach (['negocio', 'rubro', 'comuna', 'web', 'servicio_sugerido', 'canal'] as $c) {
		$v[$c] = at_pro_nfc(trim(sanitize_text_field((string) ($campos[$c] ?? ''))));
	}
	foreach (['senales', 'notas'] as $c) {
		$v[$c] = at_pro_nfc(trim(sanitize_textarea_field((string) ($campos[$c] ?? ''))));
	}
	$correo = strtolower(trim((string) ($campos['correo'] ?? '')));
	$v['correo'] = is_email($correo) ? $correo : '';
	$v['dominio'] = at_pro_dominio($v['web']);
	foreach ($limites as $c => $max) {
		if (mb_strlen($v[$c] ?? '') > $max) {
			return new WP_Error('demasiado_largo', "El campo {$c} supera {$max} caracteres.");
		}
	}
	if ($v['negocio'] === '') { return new WP_Error('sin_negocio', 'Falta el nombre del negocio.'); }
	$tel = at_pro_telefono((string) ($campos['telefono'] ?? ''));
	if ($v['dominio'] === '' && $tel === '' && $v['correo'] === '') {
		return new WP_Error('sin_contacto', 'Sin web, teléfono ni correo válidos.');
	}
	if (at_pro_coincide_baja($v['dominio'], $tel, $v['correo'])) {
		return new WP_Error('en_bajas', 'Pidió baja: no se vuelve a cargar.');
	}
	$cond = [];
	$args = [];
	if ($v['dominio'] !== '') { $cond[] = 'dominio = %s'; $args[] = $v['dominio']; }
	if ($tel !== '') { $cond[] = 'telefono = %s'; $args[] = $tel; }
	if ($cond && $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE " . implode(' OR ', $cond) . ' LIMIT 1', ...$args))) {
		return new WP_Error('repetido', 'Ya existe por dominio o teléfono.');
	}
	$fuente = (string) ($campos['fuente'] ?? 'manual');
	if (!in_array($fuente, at_pro_fuentes(), true)) { $fuente = 'manual'; }
	$ahora = current_time('mysql');
	$fila = [
		'negocio' => $v['negocio'], 'rubro' => $v['rubro'], 'comuna' => $v['comuna'], 'web' => $v['web'],
		'dominio' => $v['dominio'] !== '' ? $v['dominio'] : null, 'telefono' => $tel, 'correo' => $v['correo'],
		'fuente' => $fuente, 'senales' => $v['senales'], 'servicio_sugerido' => $v['servicio_sugerido'],
		'estado' => 'por_contactar', 'canal' => $v['canal'], 'notas' => $v['notas'],
		'created_at' => $ahora, 'updated_at' => $ahora,
	];
	for ($i = 0; $i < 5; $i++) {
		$fila['token'] = at_pro_token();
		$wpdb->suppress_errors(true);
		$ok = $wpdb->insert($t, $fila);
		$wpdb->suppress_errors(false);
		if ($ok) { return (int) $wpdb->insert_id; }
		if (stripos((string) $wpdb->last_error, 'token') === false) { break; }
	}
	return new WP_Error('error_base', 'No se pudo guardar.');
}

/** Cambia el estado respetando las transiciones; fija la marca de tiempo la primera vez. `baja` pasa por at_pro_dar_baja. */
function at_pro_cambiar_estado(int $id, string $estado, string $canal = ''): bool {
	global $wpdb;
	$p = at_pro_obtener($id);
	if (!$p || !at_pro_transicion_valida($p['estado'], $estado)) { return false; }
	if ($estado === 'baja') { return at_pro_dar_baja($p['token']); }
	$t = at_pro_tabla();
	$ahora = current_time('mysql');
	$sets = ['estado = %s', 'updated_at = %s'];
	$args = [$estado, $ahora];
	$marca = ['contactado' => 'contactado_at', 'respondio' => 'respondio_at', 'formulario' => 'formulario_at'][$estado] ?? '';
	if ($marca !== '') { $sets[] = "{$marca} = COALESCE({$marca}, %s)"; $args[] = $ahora; }
	if ($canal !== '') {
		$canal = at_pro_nfc(sanitize_key($canal));
		if (mb_strlen($canal) > at_pro_largos()['canal']) { return false; }
		$sets[] = 'canal = %s'; $args[] = $canal;
	}
	$args[] = $id;
	$args[] = $p['estado'];
	$n = $wpdb->query($wpdb->prepare("UPDATE {$t} SET " . implode(', ', $sets) . ' WHERE id = %d AND estado = %s', ...$args));
	return $n === 1;
}

/** Baja por el enlace personal: queda en `baja` y su contacto en la lista común de bajas (no se vuelve a escribir). */
function at_pro_dar_baja(string $token): bool {
	global $wpdb;
	$p = at_pro_por_token($token);
	if (!$p) { return false; }
	$ahora = current_time('mysql');
	$wpdb->query($wpdb->prepare('UPDATE ' . at_pro_tabla() . " SET estado = 'baja', baja_at = COALESCE(baja_at, %s), updated_at = %s WHERE id = %d", $ahora, $ahora, (int) $p['id']));
	if ((string) $wpdb->last_error !== '') { return false; }
	$correo = strtolower((string) $p['correo']);
	$tel = (string) $p['telefono'];
	if ($correo !== '' || $tel !== '') {
		$bajas = $wpdb->prefix . 'at_retomar_bajas';
		$existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$bajas} WHERE email = %s AND telefono = %s AND revertido_at IS NULL LIMIT 1", $correo, $tel));
		if (!$existe) {
			$wpdb->insert($bajas, ['email' => $correo, 'telefono' => $tel, 'origen' => 'prospeccion', 'creado_at' => $ahora]);
		}
	}
	return true;
}

/** Importa un CSV (UTF-8 o Windows-1252, con o sin BOM; separador ; , o tabulador). No guarda copia del archivo. */
function at_pro_importar_csv(string $ruta, string $fuente): array {
	$res = ['entraron' => 0, 'repetidos' => 0, 'bajas' => 0, 'rechazados' => 0, 'errores' => []];
	if (!is_file($ruta) || !is_readable($ruta)) { $res['errores'][] = 'No se pudo leer el archivo.'; return $res; }
	if (filesize($ruta) > 5 * 1024 * 1024) { $res['errores'][] = 'El archivo supera 5 MB.'; return $res; }
	$txt = (string) file_get_contents($ruta);
	$txt = (string) preg_replace('/^\xEF\xBB\xBF/', '', $txt);
	if (!mb_check_encoding($txt, 'UTF-8')) { $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252'); }
	$primera = (string) strtok($txt, "\n");
	$sep = ',';
	$mejor = -1;
	foreach ([';', ',', "\t"] as $s) {
		$n = substr_count($primera, $s);
		if ($n > $mejor) { $mejor = $n; $sep = $s; }
	}
	$h = fopen('php://memory', 'r+');
	fwrite($h, $txt);
	rewind($h);
	$cab = fgetcsv($h, 0, $sep, '"', '\\');
	if (!$cab) { fclose($h); $res['errores'][] = 'El archivo está vacío.'; return $res; }
	$mapa = [];
	foreach ($cab as $i => $c) {
		$campo = at_pro_columna_csv((string) $c);
		if ($campo !== '' && !in_array($campo, $mapa, true)) { $mapa[$i] = $campo; }
	}
	if (!in_array('negocio', $mapa, true)) {
		fclose($h);
		$res['errores'][] = 'No encontré la columna del nombre del negocio (nombre, negocio, empresa).';
		return $res;
	}
	$linea = 1;
	$filas = 0;
	while (($r = fgetcsv($h, 0, $sep, '"', '\\')) !== false) {
		$linea++;
		if ($r === [null] || !array_filter($r, fn($x) => trim((string) $x) !== '')) { continue; }
		if (++$filas > 5000) { $res['errores'][] = 'Se leyeron solo las primeras 5000 filas.'; break; }
		$campos = ['fuente' => $fuente];
		foreach ($mapa as $i => $campo) { $campos[$campo] = (string) ($r[$i] ?? ''); }
		$id = at_pro_crear($campos);
		if (!is_wp_error($id)) { $res['entraron']++; continue; }
		$codigo = $id->get_error_code();
		if ($codigo === 'repetido') { $res['repetidos']++; }
		elseif ($codigo === 'en_bajas') { $res['bajas']++; }
		else {
			$res['rechazados']++;
			if (count($res['errores']) < 20) { $res['errores'][] = "Fila {$linea}: " . $id->get_error_message(); }
		}
	}
	fclose($h);
	return $res;
}

/** Números de la tarjeta superior: por estado (con ceros), por fuente y por rubro. */
function at_pro_conteos(): array {
	global $wpdb;
	$t = at_pro_tabla();
	$estado = array_fill_keys(at_pro_estados(), 0);
	foreach ($wpdb->get_results("SELECT estado, COUNT(*) n FROM {$t} GROUP BY estado", ARRAY_A) as $f) {
		if (isset($estado[$f['estado']])) { $estado[$f['estado']] = (int) $f['n']; }
	}
	$fuente = [];
	foreach ($wpdb->get_results("SELECT fuente, COUNT(*) n FROM {$t} GROUP BY fuente ORDER BY n DESC", ARRAY_A) as $f) { $fuente[$f['fuente']] = (int) $f['n']; }
	$rubro = [];
	foreach ($wpdb->get_results("SELECT rubro, COUNT(*) n FROM {$t} WHERE rubro <> '' GROUP BY rubro ORDER BY n DESC", ARRAY_A) as $f) { $rubro[$f['rubro']] = (int) $f['n']; }
	return ['estado' => $estado, 'fuente' => $fuente, 'rubro' => $rubro, 'total' => array_sum($estado)];
}
