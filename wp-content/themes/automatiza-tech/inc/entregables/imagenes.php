<?php
/**
 * Imágenes de las notas (0 a 3 por nota): validar el contenido real, reescribir (quita EXIF/GPS, lado ≤ 2.000 px),
 * guardar fuera del alcance público y servir solo por ver-entregable.php con el código del entregable.
 */
if (!defined('ABSPATH')) {
	exit;
}

/** uploads/at-entregables/ con .htaccess que bloquea todo e index.php vacío. */
function at_en_dir_base(): string {
	$up = wp_upload_dir(null, false);
	$dir = trailingslashit($up['basedir']) . 'at-entregables/';
	if (!is_dir($dir)) {
		wp_mkdir_p($dir);
	}
	if (!is_file($dir . '.htaccess')) {
		$reglas = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
		if (@file_put_contents($dir . '.htaccess', $reglas) === false) {
			error_log('at_en_dir_base: no se pudo escribir el .htaccess de ' . $dir);
		}
	}
	if (!is_file($dir . 'index.php')) {
		file_put_contents($dir . 'index.php', "<?php\n// Silencio.\n");
	}
	return $dir;
}

function at_en_dir_imagenes(int $ent_id): string {
	return at_en_dir_base() . (int) $ent_id . '/';
}

/** $_FILES['imagenes'] (formato de arreglos paralelos) → lista de archivos; omite los campos vacíos. */
function at_en_archivos_subidos(array $campo): array {
	if (!isset($campo['name']) || !is_array($campo['name'])) {
		return [];
	}
	$lista = [];
	foreach ($campo['name'] as $i => $nombre) {
		$error = (int) ($campo['error'][$i] ?? UPLOAD_ERR_NO_FILE);
		if ($error === UPLOAD_ERR_NO_FILE) {
			continue;
		}
		$lista[] = ['name' => (string) $nombre, 'type' => (string) ($campo['type'][$i] ?? ''), 'tmp_name' => (string) ($campo['tmp_name'][$i] ?? ''), 'error' => $error, 'size' => (int) ($campo['size'][$i] ?? 0)];
	}
	return $lista;
}

/** MIME real del contenido ('' si no es imagen) y medidas. */
function at_en_medir(string $ruta): array {
	$info = @getimagesize($ruta);
	if (!$info) {
		return ['tipo' => '', 'ancho' => 0, 'alto' => 0];
	}
	return ['tipo' => (string) ($info['mime'] ?? ''), 'ancho' => (int) $info[0], 'alto' => (int) $info[1]];
}

/** Abre la imagen con GD siempre: el EXIF/GPS se saca al reescribir y no depende del editor del servidor (Imagick solo lo quita al achicar). */
function at_en_abrir_editor_gd(string $ruta) {
	$solo_gd = function () {
		return ['WP_Image_Editor_GD'];
	};
	add_filter('wp_image_editors', $solo_gd, PHP_INT_MAX);
	try {
		return wp_get_image_editor($ruta);
	} finally {
		remove_filter('wp_image_editors', $solo_gd, PHP_INT_MAX);
	}
}

/** Error de una imagen: el código es una clave de at_en_mensajes() y el dato lleva el número de la imagen (1..3). */
function at_en_error_imagen(string $clave, int $n): WP_Error {
	return new WP_Error($clave, at_en_formatear_mensaje(at_en_mensajes()[$clave], $n), ['n' => $n]);
}

/** Valida y guarda 0 a 3 imágenes; devuelve sus nombres internos o WP_Error (sin dejar archivos a medias). */
function at_en_procesar_imagenes(array $archivos, int $ent_id) {
	if (count($archivos) > AT_EN_MAX_IMAGENES) {
		return new WP_Error('muchas_imagenes', at_en_mensajes()['muchas_imagenes']);
	}
	// Primero se revisan todas; recién después se guarda (una mala no deja otras guardadas).
	foreach (array_values($archivos) as $i => $a) {
		$tmp = (string) ($a['tmp_name'] ?? '');
		// Lo primero: que sea una subida HTTP real. Si no, no se toca la ruta (ni se mide).
		// AT_EN_PRUEBAS solo se define en tests/entregables (no hay subida HTTP en CLI); en PROD no existe.
		$es_subida = $tmp !== '' && (is_uploaded_file($tmp) || (defined('AT_EN_PRUEBAS') && AT_EN_PRUEBAS === true && is_file($tmp)));
		$medida = ['tipo' => '', 'ancho' => 0, 'alto' => 0];
		$size = (int) ($a['size'] ?? 0);
		if ($es_subida && is_file($tmp)) {
			$medida = at_en_medir($tmp);
			$size = max($size, (int) @filesize($tmp)); // no se confía solo en el tamaño informado
		}
		$error = $es_subida ? (int) ($a['error'] ?? 0) : UPLOAD_ERR_CANT_WRITE;
		$clave = at_en_clave_error_imagen(['indice' => $i + 1, 'error' => $error, 'size' => $size] + $medida);
		if ($clave !== '') {
			return at_en_error_imagen($clave, $i + 1);
		}
		$ft = wp_check_filetype_and_ext($tmp, (string) ($a['name'] ?? ''), ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png']);
		if ($ft['type'] && !in_array($ft['type'], ['image/jpeg', 'image/png'], true)) {
			return at_en_error_imagen('img_tipo', $i + 1);
		}
	}
	$dir = at_en_dir_imagenes($ent_id);
	wp_mkdir_p($dir);
	$guardadas = [];
	foreach (array_values($archivos) as $i => $a) {
		$tmp = (string) $a['tmp_name'];
		$ext = at_en_medir($tmp)['tipo'] === 'image/png' ? 'png' : 'jpg';
		$nombre = strtolower(wp_generate_password(24, false, false)) . '.' . $ext;
		$ed = at_en_abrir_editor_gd($tmp);
		$ok = !is_wp_error($ed);
		if ($ok) {
			// Fotos de celular en vertical: se giran según el EXIF antes de que este se pierda.
			if (method_exists($ed, 'maybe_exif_rotate')) {
				$ed->maybe_exif_rotate();
			}
			$tam = $ed->get_size();
			if (max((int) $tam['width'], (int) $tam['height']) > AT_EN_LADO_FINAL) {
				$ok = !is_wp_error($ed->resize(AT_EN_LADO_FINAL, AT_EN_LADO_FINAL, false));
			}
			// Guardar siempre reescribe la imagen: el EXIF (con el GPS) no pasa.
			$ok = $ok && !is_wp_error($ed->save($dir . $nombre, $ext === 'png' ? 'image/png' : 'image/jpeg'));
		}
		unset($ed); // libera la imagen decodificada antes de la siguiente
		if (!$ok || !is_file($dir . $nombre)) {
			@unlink($dir . $nombre);
			foreach ($guardadas as $g) {
				@unlink($dir . $g);
			}
			return at_en_error_imagen('img_proceso', $i + 1);
		}
		$guardadas[] = $nombre;
	}
	return $guardadas;
}

/** Ruta en disco de una imagen solo si figura en una nota de ese entregable; '' si no. */
function at_en_ruta_imagen(object $ent, string $nombre): string {
	if (!at_en_nombre_imagen_valido($nombre)) {
		return '';
	}
	foreach (at_en_notas((int) $ent->id) as $n) {
		$imgs = json_decode((string) $n->imagenes, true);
		if (is_array($imgs) && in_array($nombre, $imgs, true)) {
			$ruta = at_en_dir_imagenes((int) $ent->id) . $nombre;
			return is_file($ruta) ? $ruta : '';
		}
	}
	return '';
}

/** Entrega la imagen (o 404) y termina. */
function at_en_servir_imagen(object $ent, string $nombre): void {
	$ruta = at_en_ruta_imagen($ent, $nombre);
	if ($ruta === '') {
		nocache_headers();
		status_header(404);
		exit;
	}
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: ' . (substr($nombre, -4) === '.png' ? 'image/png' : 'image/jpeg'));
	header('Content-Length: ' . filesize($ruta));
	header('Cache-Control: private, no-store, no-transform');
	header('X-Content-Type-Options: nosniff');
	header('X-Robots-Tag: noindex, nofollow');
	header("Content-Security-Policy: default-src 'none'; sandbox");
	readfile($ruta);
	exit;
}

function at_en_borrar_imagenes(int $ent_id): void {
	$dir = at_en_dir_imagenes($ent_id);
	foreach ((array) glob($dir . '*') as $f) {
		if (is_file($f)) {
			@unlink($f);
		}
	}
	@rmdir($dir);
}
