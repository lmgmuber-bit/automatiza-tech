<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/imagenes-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_procesar_imagenes', 'at_en_archivos_subidos', 'at_en_ruta_imagen', 'at_en_borrar_imagenes');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
add_filter('at_en_es_subida', '__return_true'); // en CLI no hay subida HTTP real
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
at_en_crear_version($id, 'https://example.com/v1', '');
$tmp = sys_get_temp_dir() . '/en-' . $marca;
@mkdir($tmp);

/** JPG real de $w×$h con un segmento APP1 Exif que trae el texto GPS-PRUEBA-<marca>. */
function en_jpg_con_exif(string $ruta, int $w, int $h, string $marca): void {
	$im = imagecreatetruecolor($w, $h);
	imagefill($im, 0, 0, imagecolorallocate($im, 30, 58, 138));
	ob_start(); imagejpeg($im, null, 90); $jpg = ob_get_clean(); imagedestroy($im);
	$exif = "Exif\0\0" . 'GPS-PRUEBA-' . $marca;
	$app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
	file_put_contents($ruta, substr($jpg, 0, 2) . $app1 . substr($jpg, 2));
}
function en_png(string $ruta, int $w, int $h): void {
	$im = imagecreatetruecolor($w, $h); imagepng($im, $ruta); imagedestroy($im);
}
function en_archivo(string $ruta, string $nombre): array {
	return ['name' => $nombre, 'type' => 'image/jpeg', 'tmp_name' => $ruta, 'error' => 0, 'size' => filesize($ruta)];
}

// Normalización de $_FILES
$campo = ['name' => ['a.jpg', ''], 'type' => ['image/jpeg', ''], 'tmp_name' => ['/x/a', ''], 'error' => [0, UPLOAD_ERR_NO_FILE], 'size' => [10, 0]];
ok(count(at_en_archivos_subidos($campo)) === 1 && at_en_archivos_subidos($campo)[0]['name'] === 'a.jpg', 'normaliza y omite los vacíos');
ok(at_en_archivos_subidos([]) === [], 'sin imágenes: lista vacía (son opcionales)');

// JPG con EXIF grande → reescrito, sin EXIF y achicado
en_jpg_con_exif("$tmp/foto.jpg", 2600, 1300, $marca);
$r = at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg')], $id);
ok(is_array($r) && count($r) === 1 && at_en_nombre_imagen_valido($r[0]) && substr($r[0], -4) === '.jpg', 'JPG guardado con nombre interno');
$guardada = at_en_dir_imagenes($id) . $r[0];
ok(is_file($guardada) && strpos((string) file_get_contents($guardada), 'GPS-PRUEBA-' . $marca) === false, 'se borró el EXIF (GPS)');
$tam = getimagesize($guardada);
ok($tam && max($tam[0], $tam[1]) <= 2000, 'lado máximo 2.000 px');
ok(is_file(at_en_dir_base() . '.htaccess') && strpos((string) file_get_contents(at_en_dir_base() . '.htaccess'), 'Require all denied') !== false && is_file(at_en_dir_base() . 'index.php'), 'carpeta bloqueada con .htaccess e index.php');

// PNG
en_png("$tmp/p.png", 300, 200);
$rp = at_en_procesar_imagenes([['type' => 'image/png'] + en_archivo("$tmp/p.png", 'p.png')], $id);
ok(is_array($rp) && substr($rp[0], -4) === '.png', 'PNG guardado como .png');

// PHP disfrazado de JPG → rechazado y sin archivos nuevos
file_put_contents("$tmp/malo.jpg", "<?php echo 'x'; ?>");
$antes = count(glob(at_en_dir_imagenes($id) . '*'));
$m = at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg'), en_archivo("$tmp/malo.jpg", 'malo.jpg')], $id);
ok(is_wp_error($m) && $m->get_error_message() === 'La imagen 2 no es JPG ni PNG.', 'PHP como .jpg: «La imagen 2 no es JPG ni PNG.»');
ok(count(glob(at_en_dir_imagenes($id) . '*')) === $antes, 'si una falla, no queda ninguna guardada');

// Más de 3
$cuatro = array_fill(0, 4, en_archivo("$tmp/foto.jpg", 'f.jpg'));
ok(is_wp_error(at_en_procesar_imagenes($cuatro, $id)) && at_en_procesar_imagenes($cuatro, $id)->get_error_code() === 'muchas_imagenes', 'más de 3: rechazado');

// Más de 5 MB (se informa el size; no hace falta un archivo real de 5 MB)
ok(is_wp_error(at_en_procesar_imagenes([['size' => 5242881] + en_archivo("$tmp/foto.jpg", 'f.jpg')], $id)), 'más de 5 MB: rechazado');

// Lado de 12.001 px
en_png("$tmp/ancha.png", 12001, 1);
ok(is_wp_error(at_en_procesar_imagenes([['type' => 'image/png'] + en_archivo("$tmp/ancha.png", 'a.png')], $id)), '12.001 px de lado: rechazado');

// Subida que no es HTTP real
remove_filter('at_en_es_subida', '__return_true');
ok(is_wp_error(at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg')], $id)), 'sin is_uploaded_file: rechazado');
add_filter('at_en_es_subida', '__return_true');

// Pertenencia
$e = at_en_por_id($id);
ok(at_en_ruta_imagen($e, $r[0]) === '', 'imagen guardada pero sin nota: no se sirve');
at_en_agregar_nota($id, 'cliente', 'Cliente', 'Con foto', [$r[0]], '');
ok(at_en_ruta_imagen($e, $r[0]) === $guardada, 'imagen de una nota del entregable: se sirve');
ok(at_en_ruta_imagen($e, '../' . $r[0]) === '' && at_en_ruta_imagen($e, 'abcdefghijklmnopqrstuvwx.jpg') === '', 'ruta rara o ajena: no');

// Borrado
at_en_borrar_imagenes($id);
ok(!is_dir(at_en_dir_imagenes($id)) || count(glob(at_en_dir_imagenes($id) . '*')) === 0, 'borrar imágenes del entregable');
array_map('unlink', glob("$tmp/*")); @rmdir($tmp);
fin();
