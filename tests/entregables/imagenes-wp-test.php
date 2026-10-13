<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/imagenes-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
exigir('at_en_procesar_imagenes', 'at_en_archivos_subidos', 'at_en_ruta_imagen', 'at_en_borrar_imagenes');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
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

// Control: la fuente trae de verdad el marcador de GPS (si no, «se borró el EXIF» no probaría nada)
en_jpg_con_exif("$tmp/fuente.jpg", 800, 600, $marca);
ok(strpos((string) file_get_contents("$tmp/fuente.jpg"), 'GPS-PRUEBA-' . $marca) !== false, 'control: la foto original trae el GPS');

// JPG con EXIF grande → reescrito, sin EXIF y achicado
en_jpg_con_exif("$tmp/foto.jpg", 2600, 1300, $marca);
$r = at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg')], $id);
ok(is_array($r) && count($r) === 1 && at_en_nombre_imagen_valido($r[0]) && substr($r[0], -4) === '.jpg', 'JPG guardado con nombre interno');
$guardada = at_en_dir_imagenes($id) . $r[0];
ok(is_file($guardada) && strpos((string) file_get_contents($guardada), 'GPS-PRUEBA-' . $marca) === false, 'se borró el EXIF (GPS)');
$tam = getimagesize($guardada);
ok($tam && max($tam[0], $tam[1]) <= 2000, 'lado máximo 2.000 px');
ok(is_file(at_en_dir_base() . '.htaccess') && strpos((string) file_get_contents(at_en_dir_base() . '.htaccess'), 'Require all denied') !== false && is_file(at_en_dir_base() . 'index.php'), 'carpeta bloqueada con .htaccess e index.php');

// Siempre GD, aunque otro filtro pida Imagick (que solo borra el EXIF al achicar: una foto chica conservaría el GPS)
$vistos = null;
$pide_imagick = function ($lista) use (&$vistos) {
	// Se engancha al final del mismo gancho: ve lo que queda después de que el módulo fuerza GD.
	static $enganchado = false;
	if (!$enganchado) {
		$enganchado = true;
		add_filter('wp_image_editors', function ($l) use (&$vistos) { $vistos = $l; return $l; }, PHP_INT_MAX);
	}
	return ['WP_Image_Editor_Imagick'];
};
add_filter('wp_image_editors', $pide_imagick, 99);
$rg = at_en_procesar_imagenes([en_archivo("$tmp/fuente.jpg", 'fuente.jpg')], $id);
remove_filter('wp_image_editors', $pide_imagick, 99);
ok($vistos === ['WP_Image_Editor_GD'], 'el editor que se usa es GD aunque otro filtro pida Imagick');
ok(is_array($rg) && strpos((string) file_get_contents(at_en_dir_imagenes($id) . $rg[0]), 'GPS-PRUEBA-' . $marca) === false, 'foto chica (sin achicar): también sin GPS');
$vistos = 'no tocar';
ok(apply_filters('wp_image_editors', ['x']) === ['x'], 'el filtro de GD no queda enganchado después');

// PNG
en_png("$tmp/p.png", 300, 200);
$rp = at_en_procesar_imagenes([['type' => 'image/png'] + en_archivo("$tmp/p.png", 'p.png')], $id);
ok(is_array($rp) && substr($rp[0], -4) === '.png', 'PNG guardado como .png');

// PHP disfrazado de JPG → rechazado y sin archivos nuevos
file_put_contents("$tmp/malo.jpg", "<?php echo 'x'; ?>");
$antes = count(glob(at_en_dir_imagenes($id) . '*'));
$m = at_en_procesar_imagenes([en_archivo("$tmp/foto.jpg", 'foto.jpg'), en_archivo("$tmp/malo.jpg", 'malo.jpg')], $id);
ok(is_wp_error($m) && $m->get_error_message() === 'La imagen 2 no es JPG ni PNG.', 'PHP como .jpg: «La imagen 2 no es JPG ni PNG.»');
ok($m->get_error_code() === 'img_tipo' && ($m->get_error_data()['n'] ?? 0) === 2, 'el error lleva la clave img_tipo y el número de la imagen (2)');
ok(count(glob(at_en_dir_imagenes($id) . '*')) === $antes, 'si una falla, no queda ninguna guardada');

// Imagen que se puede medir pero no decodificar (cortada): error limpio y sin restos
file_put_contents("$tmp/cortada.jpg", substr((string) file_get_contents("$tmp/foto.jpg"), 0, 400));
$antes2 = count(glob(at_en_dir_imagenes($id) . '*'));
$c = at_en_procesar_imagenes([en_archivo("$tmp/fuente.jpg", 'f.jpg'), en_archivo("$tmp/cortada.jpg", 'c.jpg')], $id);
ok(is_wp_error($c) && $c->get_error_message() === 'La imagen 2 no se pudo procesar. Prueba con otra.' && $c->get_error_code() === 'img_proceso' && ($c->get_error_data()['n'] ?? 0) === 2, 'imagen ilegible: «no se pudo procesar» (img_proceso, imagen 2)');
ok(count(glob(at_en_dir_imagenes($id) . '*')) === $antes2, 'imagen ilegible: no queda ninguna guardada (ni la buena ni a medias)');

// Más de 3
$cuatro = array_fill(0, 4, en_archivo("$tmp/foto.jpg", 'f.jpg'));
ok(is_wp_error(at_en_procesar_imagenes($cuatro, $id)) && at_en_procesar_imagenes($cuatro, $id)->get_error_code() === 'muchas_imagenes', 'más de 3: rechazado');

// Más de 5 MB (se informa el size; no hace falta un archivo real de 5 MB)
$pe = at_en_procesar_imagenes([['size' => 5242881] + en_archivo("$tmp/foto.jpg", 'f.jpg')], $id);
ok(is_wp_error($pe) && $pe->get_error_code() === 'img_peso' && ($pe->get_error_data()['n'] ?? 0) === 1, 'más de 5 MB: img_peso, imagen 1');

// Lado de 12.001 px
en_png("$tmp/ancha.png", 12001, 1);
$pl = at_en_procesar_imagenes([['type' => 'image/png'] + en_archivo("$tmp/ancha.png", 'a.png')], $id);
ok(is_wp_error($pl) && $pl->get_error_code() === 'img_lado', '12.001 px de lado: img_lado');

// Subida que no es real: con la constante de pruebas cuenta un archivo local, pero una ruta que no existe nunca
$ps = at_en_procesar_imagenes([['tmp_name' => "$tmp/no-existe.jpg"] + en_archivo("$tmp/foto.jpg", 'foto.jpg')], $id);
ok(is_wp_error($ps) && $ps->get_error_code() === 'img_subida', 'ruta que no es un archivo subido: img_subida');
// (El caso «un archivo local que NO viene de una subida HTTP» sin la constante de pruebas se prueba en vista-wp-test.php con EN_SIN_PRUEBAS.)
ok(defined('AT_EN_PRUEBAS') && AT_EN_PRUEBAS === true && !has_filter('at_en_es_subida'), 'ya no hay filtro at_en_es_subida: solo la constante de pruebas');

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
