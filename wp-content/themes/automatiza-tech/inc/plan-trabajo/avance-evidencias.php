<?php
/**
 * Estado del proyecto (Etapa 2): archivos de las evidencias (capturas, videos y pósters) — decisión D7. Se alojan en
 * wp-content/uploads/at-estado/<id del plan>/ con nombres aleatorios; la carpeta no ejecuta código, no se lista y lleva
 * noindex y no-transform (el CDN de Hostinger achica y recodifica imágenes si no se le dice). El tipo se mira por el
 * contenido, nunca por el nombre. Las imágenes se reescriben con GD (sin EXIF ni GPS, lado ≤ 2.000 px); los videos
 * (MP4 o WebM) se guardan tal cual. El póster de un video lo sube Luis (R1: en Hostinger no hay ffmpeg).
 */
if (!defined('ABSPATH')) {
	exit;
}

const AT_AV_IMAGEN_MAX = 15728640;  // 15 MB
const AT_AV_VIDEO_MAX = 209715200;  // 200 MB
const AT_AV_LADO = 2000;

/** Carpeta y URL de las evidencias del plan (con / final). Crea la carpeta base con su .htaccess e index.php. */
function at_av_dir(int $plan_id): array {
	$up = wp_upload_dir(null, false);
	$base = trailingslashit($up['basedir']) . 'at-estado/';
	$url = trailingslashit($up['baseurl']) . 'at-estado/';
	if (!is_dir($base)) {
		wp_mkdir_p($base);
	}
	if (!is_file($base . '.htaccess')) {
		$reglas = "# Evidencias de «Estado del proyecto»: solo imágenes y videos; nunca código.\n"
			. "Options -Indexes\n"
			. "<FilesMatch \"\\.(php|phtml|phar|php[0-9]|pl|py|cgi|sh|html?|svg|js)$\">\n"
			. "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			. "</FilesMatch>\n"
			. "<IfModule mod_headers.c>\nHeader set X-Robots-Tag \"noindex, nofollow\"\nHeader set Cache-Control \"public, max-age=604800, no-transform\"\nHeader set X-Content-Type-Options \"nosniff\"\n</IfModule>\n";
		if (@file_put_contents($base . '.htaccess', $reglas) === false) {
			error_log('at_av_dir: no se pudo escribir el .htaccess de ' . $base);
		}
	}
	if (!is_file($base . 'index.php')) {
		file_put_contents($base . 'index.php', "<?php\n// Silencio.\n");
	}
	$dir = $base . (int) $plan_id . '/';
	if (!is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	return ['dir' => $dir, 'url' => $url . (int) $plan_id . '/'];
}

/**
 * Tipo real por el contenido: 'jpg', 'png', 'webp', 'mp4', 'webm' o '' (cualquier otra cosa). El video se reconoce por
 * su cabecera ANTES de llamar a getimagesize(): en un MP4 grande esa función puede pedir cientos de MB de memoria
 * (lee las cajas ISO-BMFF pensando en HEIC/AVIF). Un «ftyp» solo cuenta como MP4 con una marca de video conocida.
 */
function at_av_tipo_contenido(string $ruta): string {
	if ($ruta === '' || !is_file($ruta)) {
		return '';
	}
	$h = (string) @file_get_contents($ruta, false, null, 0, 16);
	if (strlen($h) >= 12 && substr($h, 4, 4) === 'ftyp') {
		return preg_match('/^(isom|iso[0-9a-z]|mp4[0-9a-z]|avc1|M4V |M4VH|M4VP|msnv|dash|qt  |3g[a-z0-9]{2}|f4v |xavc|mmp4|cmf[a-z0-9])/i', substr($h, 8, 4)) ? 'mp4' : '';
	}
	if (strncmp($h, "\x1A\x45\xDF\xA3", 4) === 0) {
		return 'webm';
	}
	$info = @getimagesize($ruta);
	if ($info) {
		return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(string) ($info['mime'] ?? '')] ?? '';
	}
	return '';
}

/**
 * Guarda un archivo subido como evidencia. $clase 'imagen' (captura o póster): JPEG, PNG o WebP de hasta 15 MB, se
 * reescribe con GD y queda JPEG o PNG. $clase 'video': MP4 o WebM de hasta 200 MB, tal cual. Devuelve ['url','archivo']
 * o WP_Error con un motivo legible para el panel.
 */
function at_av_guardar_archivo(array $a, int $plan_id, string $clase) {
	$tmp = (string) ($a['tmp_name'] ?? '');
	$nombre = sanitize_file_name((string) ($a['name'] ?? 'archivo'));
	// Solo una subida HTTP real. AT_AV_PRUEBAS existe únicamente en tests/plan (en CLI no hay subidas HTTP).
	$es_subida = $tmp !== '' && (is_uploaded_file($tmp) || (defined('AT_AV_PRUEBAS') && AT_AV_PRUEBAS === true && is_file($tmp)));
	if (!$es_subida || (int) ($a['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
		return new WP_Error('at_av_subida', "«{$nombre}» no llegó completo; vuelve a subirlo.");
	}
	$tipo = at_av_tipo_contenido($tmp);
	$peso = (int) @filesize($tmp);
	$d = at_av_dir($plan_id);
	$aleatorio = strtolower(wp_generate_password(20, false, false));
	if ($clase === 'video') {
		if (!in_array($tipo, ['mp4', 'webm'], true)) {
			return new WP_Error('at_av_tipo', "«{$nombre}» no es un video MP4 o WebM.");
		}
		if ($peso > AT_AV_VIDEO_MAX) {
			return new WP_Error('at_av_peso', "«{$nombre}» pesa más de 200 MB.");
		}
		$archivo = $aleatorio . '.' . $tipo;
		$ok = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $d['dir'] . $archivo) : @copy($tmp, $d['dir'] . $archivo);
		if (!$ok || !is_file($d['dir'] . $archivo)) {
			return new WP_Error('at_av_guardar', "No se pudo guardar «{$nombre}».");
		}
		return ['url' => $d['url'] . $archivo, 'archivo' => $archivo];
	}
	if (!in_array($tipo, ['jpg', 'png', 'webp'], true)) {
		return new WP_Error('at_av_tipo', "«{$nombre}» no es una imagen JPEG, PNG o WebP.");
	}
	if ($peso > AT_AV_IMAGEN_MAX) {
		return new WP_Error('at_av_peso', "«{$nombre}» pesa más de 15 MB.");
	}
	// Medidas antes de decodificar: un PNG liviano de 9000×9000 ocuparía ~324 MB en GD (fatal en Hostinger).
	// Mismos topes que el módulo de entregables (inc/entregables/puras.php).
	$medida = @getimagesize($tmp);
	$max_lado = defined('AT_EN_MAX_LADO_ENTRADA') ? (int) AT_EN_MAX_LADO_ENTRADA : 12000;
	$max_px = defined('AT_EN_MAX_PIXELES') ? (int) AT_EN_MAX_PIXELES : 25000000;
	if (!$medida || (int) $medida[0] > $max_lado || (int) $medida[1] > $max_lado || (int) $medida[0] * (int) $medida[1] > $max_px) {
		return new WP_Error('at_av_medidas', "«{$nombre}» es demasiado grande (máximo 12.000 px por lado y 25 millones de píxeles).");
	}
	$ext = $tipo === 'png' ? 'png' : 'jpg';
	$archivo = $aleatorio . '.' . $ext;
	$ed = at_en_abrir_editor_gd($tmp);
	if (is_wp_error($ed)) {
		return new WP_Error('at_av_imagen', "No se pudo abrir «{$nombre}» como imagen.");
	}
	if (method_exists($ed, 'maybe_exif_rotate')) {
		$ed->maybe_exif_rotate(); // fotos de celular en vertical: se giran antes de perder el EXIF
	}
	$tam = $ed->get_size();
	$ok = true;
	if (max((int) $tam['width'], (int) $tam['height']) > AT_AV_LADO) {
		$ok = !is_wp_error($ed->resize(AT_AV_LADO, AT_AV_LADO, false));
	}
	// Guardar siempre reescribe la imagen: el EXIF (con el GPS) no pasa.
	$ok = $ok && !is_wp_error($ed->save($d['dir'] . $archivo, $ext === 'png' ? 'image/png' : 'image/jpeg'));
	unset($ed);
	if (!$ok || !is_file($d['dir'] . $archivo)) {
		@unlink($d['dir'] . $archivo);
		return new WP_Error('at_av_imagen', "No se pudo procesar «{$nombre}».");
	}
	return ['url' => $d['url'] . $archivo, 'archivo' => $archivo];
}

/** Nombre de archivo de una URL de evidencia ('' si no es de las nuestras). */
function at_av_nombre_de_url(string $u): string {
	$n = basename((string) parse_url($u, PHP_URL_PATH));
	return preg_match('/^[a-z0-9]{20}\.(jpg|png|mp4|webm)$/', $n) ? $n : '';
}

/**
 * Pone en las evidencias de captura y video de ese cierre los archivos subidos cuyo nombre original calza (sin
 * importar mayúsculas) con su «archivo» o su «poster». Un archivo que no calza se avisa y no se guarda.
 */
function at_av_asignar_archivos(array $avance, string $cierre_id, array $subidos, int $plan_id): array {
	$res = ['avance' => $avance, 'errores' => [], 'avisos' => [], 'archivos' => []];
	$ids = [];
	foreach ($avance['cierres'] as $c) {
		if (($c['id'] ?? '') === $cierre_id) {
			$ids = array_merge((array) ($c['antes']['evidencias'] ?? []), (array) ($c['despues']['evidencias'] ?? []));
		}
	}
	$por_nombre = [];
	foreach ($subidos as $s) {
		$por_nombre[strtolower((string) ($s['name'] ?? ''))] = $s;
	}
	$usados = [];
	$guardados = []; // nombre en minúsculas => resultado de guardar (dos evidencias pueden pedir el mismo archivo)
	foreach (array_unique($ids) as $eid) {
		$e = $avance['evidencias'][$eid] ?? null;
		if (!is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
			continue;
		}
		foreach (['archivo' => 'url', 'poster_archivo' => 'poster'] as $campo => $destino) {
			$n = strtolower((string) ($e[$campo] ?? ''));
			if ($n === '' || !isset($por_nombre[$n])) {
				continue;
			}
			if ($campo === 'poster_archivo' && $e['tipo'] !== 'video') {
				continue; // solo un video lleva póster (igual que at_av_subir_una)
			}
			$usados[$n] = true;
			$clase = ($campo === 'archivo' && $e['tipo'] === 'video') ? 'video' : 'imagen';
			$clave = $clase . '|' . $n;
			if (!isset($guardados[$clave])) {
				$guardados[$clave] = at_av_guardar_archivo($por_nombre[$n], $plan_id, $clase);
				if (is_wp_error($guardados[$clave])) {
					$res['errores'][] = $guardados[$clave]->get_error_message();
				}
			}
			$r = $guardados[$clave];
			if (is_wp_error($r)) {
				continue;
			}
			$viejo = at_av_nombre_de_url((string) ($e[$destino] ?? ''));
			if ($viejo !== '') {
				$res['archivos'][] = $viejo;
			}
			$res['avance']['evidencias'][$eid][$destino] = $r['url'];
		}
	}
	foreach ($por_nombre as $n => $s) {
		if (!isset($usados[$n])) {
			$res['avisos'][] = '«' . sanitize_file_name((string) $s['name']) . '» no corresponde a ninguna evidencia de este cierre: no se guardó.';
		}
	}
	return $res;
}

/** Sube el archivo (o el póster) de una evidencia. ['avance', 'error' ('' si salió bien), 'archivos' (viejos)]. */
function at_av_subir_una(array $avance, string $evidencia_id, string $campo, array $subido, int $plan_id): array {
	$e = $avance['evidencias'][$evidencia_id] ?? null;
	$no = fn(string $m) => ['avance' => $avance, 'error' => $m, 'archivos' => []];
	if (!is_array($e) || !in_array($e['tipo'] ?? '', ['captura', 'video'], true)) {
		return $no('Esa evidencia no lleva archivo.');
	}
	if ($campo === 'poster' && $e['tipo'] !== 'video') {
		return $no('Solo un video lleva póster.');
	}
	$destino = $campo === 'poster' ? 'poster' : 'url';
	$r = at_av_guardar_archivo($subido, $plan_id, ($destino === 'url' && $e['tipo'] === 'video') ? 'video' : 'imagen');
	if (is_wp_error($r)) {
		return $no($r->get_error_message());
	}
	$viejo = at_av_nombre_de_url((string) ($e[$destino] ?? ''));
	$avance['evidencias'][$evidencia_id][$destino] = $r['url'];
	if ($destino === 'poster' && ($e['poster_archivo'] ?? '') === '') {
		// Para que «Editar» (que exporta el cierre) no pierda el póster al volver a importar.
		$n = sanitize_file_name((string) ($subido['name'] ?? ''));
		$avance['evidencias'][$evidencia_id]['poster_archivo'] = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\.(jpe?g|png|webp)$/i', $n) ? $n : '';
	}
	return ['avance' => $avance, 'error' => '', 'archivos' => $viejo !== '' ? [$viejo] : []];
}

/** Borra archivos de la carpeta del plan: solo nombres propios (20 letras o números y extensión conocida), nunca rutas. */
function at_av_borrar_archivos(int $plan_id, array $nombres): void {
	$dir = at_av_dir($plan_id)['dir'];
	foreach ($nombres as $n) {
		$n = (string) $n;
		if (preg_match('/^[a-z0-9]{20}\.(jpg|png|mp4|webm)$/', $n) && is_file($dir . $n)) {
			@unlink($dir . $n);
		}
	}
}

/** Ancho y alto de cada captura con archivo propio, leídos del archivo (getimagesize lee solo la cabecera). La
 *  presentación los usa para dibujar entera, con marco de teléfono, una captura de celular (más alta que ancha). No se
 *  guardan: sirven también para las capturas subidas antes de este cambio. */
function at_av_con_medidas(int $plan_id, array $avance): array {
	$dir = '';
	foreach ((array) ($avance['evidencias'] ?? []) as $id => $e) {
		$n = (string) ($e['archivo'] ?? '');
		if (($e['tipo'] ?? '') !== 'captura' || !preg_match('/^[a-z0-9]{20}\.(jpg|png)$/', $n)) {
			continue;
		}
		$dir = $dir !== '' ? $dir : at_av_dir($plan_id)['dir'];
		$m = is_file($dir . $n) ? @getimagesize($dir . $n) : false;
		if ($m && (int) $m[0] > 0 && (int) $m[1] > 0) {
			$avance['evidencias'][$id]['ancho'] = (int) $m[0];
			$avance['evidencias'][$id]['alto'] = (int) $m[1];
		}
	}
	return $avance;
}
