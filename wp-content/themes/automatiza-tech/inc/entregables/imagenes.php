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
		file_put_contents($dir . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
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

/** Valida y guarda 0 a 3 imágenes; devuelve sus nombres internos o WP_Error (sin dejar archivos a medias). */
function at_en_procesar_imagenes(array $archivos, int $ent_id) {
	if (count($archivos) > AT_EN_MAX_IMAGENES) {
		return new WP_Error('muchas_imagenes', at_en_mensajes()['muchas_imagenes']);
	}
	// Primero se revisan todas; recién después se guarda (una mala no deja otras guardadas).
	foreach (array_values($archivos) as $i => $a) {
		$tmp = (string) ($a['tmp_name'] ?? '');
		$medida = ($tmp !== '' && is_file($tmp)) ? at_en_medir($tmp) : ['tipo' => '', 'ancho' => 0, 'alto' => 0];
		$es_subida = $tmp !== '' && (bool) apply_filters('at_en_es_subida', is_uploaded_file($tmp), $tmp);
		$error = $es_subida ? (int) ($a['error'] ?? 0) : UPLOAD_ERR_CANT_WRITE;
		$m = at_en_revisar_imagen(['indice' => $i + 1, 'error' => $error, 'size' => (int) ($a['size'] ?? 0)] + $medida);
		if ($m !== '') {
			return new WP_Error('imagen', $m);
		}
		$ft = wp_check_filetype_and_ext($tmp, (string) ($a['name'] ?? ''), ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png']);
		if ($ft['type'] && !in_array($ft['type'], ['image/jpeg', 'image/png'], true)) {
			return new WP_Error('imagen', 'La imagen ' . ($i + 1) . ' no es JPG ni PNG.');
		}
	}
	$dir = at_en_dir_imagenes($ent_id);
	wp_mkdir_p($dir);
	$guardadas = [];
	foreach (array_values($archivos) as $i => $a) {
		$tmp = (string) $a['tmp_name'];
		$ext = at_en_medir($tmp)['tipo'] === 'image/png' ? 'png' : 'jpg';
		$nombre = strtolower(wp_generate_password(24, false, false)) . '.' . $ext;
		$ed = wp_get_image_editor($tmp);
		$ok = !is_wp_error($ed);
		if ($ok) {
			$tam = $ed->get_size();
			if (max((int) $tam['width'], (int) $tam['height']) > AT_EN_LADO_FINAL) {
				$ok = !is_wp_error($ed->resize(AT_EN_LADO_FINAL, AT_EN_LADO_FINAL, false));
			}
			// Guardar siempre reescribe la imagen: el EXIF (con el GPS) no pasa.
			$ok = $ok && !is_wp_error($ed->save($dir . $nombre, $ext === 'png' ? 'image/png' : 'image/jpeg'));
		}
		if (!$ok || !is_file($dir . $nombre)) {
			foreach ($guardadas as $g) {
				@unlink($dir . $g);
			}
			return new WP_Error('imagen', 'La imagen ' . ($i + 1) . ' no se pudo procesar. Prueba con otra.');
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
		status_header(404);
		exit;
	}
	header('Content-Type: ' . (substr($nombre, -4) === '.png' ? 'image/png' : 'image/jpeg'));
	header('Content-Length: ' . filesize($ruta));
	header('Cache-Control: private, no-store, no-transform');
	header('X-Content-Type-Options: nosniff');
	header('X-Robots-Tag: noindex, nofollow');
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
