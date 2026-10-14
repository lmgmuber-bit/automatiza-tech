<?php
// Correr: AT_WP_LOAD=<SCR>/wp-local-plan/wp-load.php php tests/plan/avance-evidencias-wp-test.php
// Estado del proyecto, Etapa 2 — Task 3: evidencias (Review Focus 3). Tipo por contenido, EXIF/GPS fuera, carpeta sin
// ejecución de código, nombres aleatorios, asignación por nombre de archivo y borrado seguro.
define('AT_AV_PRUEBAS', true);
require __DIR__ . '/wp-bootstrap.php';
exigir('at_av_dir', 'at_av_tipo_contenido', 'at_av_guardar_archivo', 'at_av_asignar_archivos', 'at_av_subir_una', 'at_av_borrar_archivos');
$plan_id = 990001; // carpeta de prueba: se borra al final
$tmp = sys_get_temp_dir() . '/at-av-prueba-' . getmypid() . '/';
wp_mkdir_p($tmp);
register_shutdown_function(function () use ($tmp, $plan_id) {
	foreach (glob($tmp . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($tmp);
	$d = at_av_dir($plan_id)['dir'];
	foreach (glob($d . '*') ?: [] as $f) { @unlink($f); }
	@rmdir($d);
});
/** Archivo temporal con ese contenido; devuelve la entrada como la deja at_en_archivos_subidos(). */
function subido(string $tmp, string $nombre, string $contenido): array {
	$ruta = $tmp . md5($nombre . microtime(true)) . '-' . $nombre;
	file_put_contents($ruta, $contenido);
	return ['name' => $nombre, 'type' => 'application/octet-stream', 'tmp_name' => $ruta, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contenido)];
}
function png(int $w = 40, int $h = 30): string {
	$im = imagecreatetruecolor($w, $h);
	imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
	ob_start(); imagepng($im); imagedestroy($im);
	return (string) ob_get_clean();
}
/** PNG de 1 bit armado a mano (liviano y sin decodificar con GD: GD pediría ~324 MB para un 9000×9000 de verdad). */
function png_grande(int $w, int $h): string {
	$chunk = function (string $tipo, string $datos): string {
		return pack('N', strlen($datos)) . $tipo . $datos . pack('N', crc32($tipo . $datos));
	};
	$fila = str_repeat("\0", 1 + (int) ceil($w / 8));
	$raw = '';
	$z = deflate_init(ZLIB_ENCODING_DEFLATE);
	for ($i = 0; $i < $h; $i++) {
		$raw .= deflate_add($z, $fila, ZLIB_NO_FLUSH);
	}
	$raw .= deflate_add($z, '', ZLIB_FINISH);
	return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 1, 0, 0, 0, 0)) . $chunk('IDAT', $raw) . $chunk('IEND', '');
}
/** JPEG con un segmento EXIF falso que trae un texto de GPS reconocible. */
function jpeg_con_exif(): string {
	$im = imagecreatetruecolor(60, 40);
	ob_start(); imagejpeg($im, null, 90); imagedestroy($im);
	$j = (string) ob_get_clean();
	$exif = "Exif\0\0" . 'GPSLatitude-33.4489-GPSLongitude-70.6693';
	return substr($j, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($j, 2);
}
$mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\0", 64);

// Carpeta: nombres aleatorios, nunca código, noindex y no-transform (D7).
$d = at_av_dir($plan_id);
$base = dirname(rtrim($d['dir'], '/')) . '/';
$ht = (string) @file_get_contents($base . '.htaccess');
ok(is_dir($d['dir']) && str_ends_with($d['url'], '/at-estado/' . $plan_id . '/'), 'carpeta del plan en uploads/at-estado/<id>/');
ok(str_contains($ht, 'Require all denied') && str_contains($ht, '.(php|phtml|phar') && str_contains($ht, 'noindex') && str_contains($ht, 'no-transform') && str_contains($ht, 'Options -Indexes'), '.htaccess: no ejecuta código, no lista, noindex y no-transform');
ok(is_file($base . 'index.php'), 'index.php vacío en la carpeta base');

// Tipo por contenido.
ok(at_av_tipo_contenido(subido($tmp, 'a.png', png())['tmp_name']) === 'png' && at_av_tipo_contenido(subido($tmp, 'a.mp4', $mp4)['tmp_name']) === 'mp4', 'PNG y MP4 se reconocen por su contenido');
ok(at_av_tipo_contenido(subido($tmp, 'w.webm', "\x1A\x45\xDF\xA3" . str_repeat("\0", 40))['tmp_name']) === 'webm', 'WebM por su firma');
ok(at_av_tipo_contenido(subido($tmp, 'foto.jpg', '<?php echo 1; ?>')['tmp_name']) === '', 'código PHP con nombre .jpg: no es nada');

// Imágenes: se reescriben (sin EXIF ni GPS) con nombre aleatorio.
$r = at_av_guardar_archivo(subido($tmp, 'Foto Celular.JPG', jpeg_con_exif()), $plan_id, 'imagen');
$guardado = is_array($r) ? (string) file_get_contents($d['dir'] . $r['archivo']) : '';
ok(is_array($r) && preg_match('/^[a-z0-9]{20}\.jpg$/', $r['archivo']) && $r['url'] === $d['url'] . $r['archivo'], 'foto guardada con nombre aleatorio .jpg');
ok($guardado !== '' && !str_contains($guardado, 'GPSLatitude') && !str_contains($guardado, 'Exif'), 'la foto guardada ya no trae EXIF ni GPS');
$r = at_av_guardar_archivo(subido($tmp, 'captura.png', png()), $plan_id, 'imagen');
ok(is_array($r) && str_ends_with($r['archivo'], '.png'), 'un PNG queda PNG');
foreach ([['foto.jpg', '<?php echo 1; ?>'], ['dibujo.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'], ['texto.webp', 'hola']] as [$n, $c]) {
	$e = at_av_guardar_archivo(subido($tmp, $n, $c), $plan_id, 'imagen');
	ok(is_wp_error($e) && str_contains($e->get_error_message(), 'no es una imagen'), "rechaza «{$n}» disfrazado");
}
$grande = subido($tmp, 'grande.png', png() . str_repeat("\0", 15728641));
$e = at_av_guardar_archivo($grande, $plan_id, 'imagen');
ok(is_wp_error($e) && str_contains($e->get_error_message(), '15 MB'), 'imagen de más de 15 MB: rechazada');
// Tope de medidas antes de decodificar: un PNG liviano de 9000×9000 decodificaría ~324 MB.
$gigante = subido($tmp, 'enorme.png', png_grande(9000, 9000));
ok(filesize($gigante['tmp_name']) < AT_AV_IMAGEN_MAX, 'el PNG de 9000×9000 pesa poco (el peso solo no lo detiene)');
$antes = count(glob($d['dir'] . '*'));
$e = at_av_guardar_archivo($gigante, $plan_id, 'imagen');
ok(is_wp_error($e) && str_contains($e->get_error_message(), 'demasiado grande') && str_contains($e->get_error_message(), '12.000 px') && count(glob($d['dir'] . '*')) === $antes, 'imagen de 9000×9000 (más de 25 millones de píxeles): rechazada sin decodificar ni guardar');
// Lado mayor a 2000 px: queda en 2000 px conservando la proporción.
$r = at_av_guardar_archivo(subido($tmp, 'ancha.png', png(3000, 1000)), $plan_id, 'imagen');
$medida = is_array($r) ? getimagesize($d['dir'] . $r['archivo']) : false;
ok($medida && $medida[0] === 2000 && $medida[1] === 667, 'un PNG de 3000×1000 queda de 2000×667');
// Videos: MP4 o WebM tal cual.
$r = at_av_guardar_archivo(subido($tmp, 'reel.mp4', $mp4), $plan_id, 'video');
ok(is_array($r) && preg_match('/^[a-z0-9]{20}\.mp4$/', $r['archivo']) && file_get_contents($d['dir'] . $r['archivo']) === $mp4, 'video MP4 guardado sin cambios');
// Video de más de 200 MB: archivo disperso (casi no ocupa disco) con cabecera MP4.
$enorme = $tmp . 'enorme.mp4';
$fh = fopen($enorme, 'wb');
fwrite($fh, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom");
fseek($fh, 209715200);
fwrite($fh, 'x');
fclose($fh);
$e = at_av_guardar_archivo(['name' => 'enorme.mp4', 'type' => 'video/mp4', 'tmp_name' => $enorme, 'error' => UPLOAD_ERR_OK, 'size' => filesize($enorme)], $plan_id, 'video');
@unlink($enorme);
ok(is_wp_error($e) && str_contains($e->get_error_message(), '200 MB'), 'video de más de 200 MB: rechazado');
$e = at_av_guardar_archivo(subido($tmp, 'reel.mp4', 'esto no es un video'), $plan_id, 'video');
ok(is_wp_error($e) && str_contains($e->get_error_message(), 'no es un video'), 'un «.mp4» que es texto: rechazado');
$e = at_av_guardar_archivo(['name' => 'x.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], $plan_id, 'imagen');
ok(is_wp_error($e) && str_contains($e->get_error_message(), 'no llegó completo'), 'subida cortada: motivo legible');

// Asignar por nombre de archivo (sin importar mayúsculas); el póster es aparte.
$av = at_av_vacio();
$av['cierres'][] = ['id' => 'cabc123', 'titulo' => 'Sitio', 'estado' => 'borrador', 'antes' => ['evidencias' => []], 'despues' => ['evidencias' => ['cabc123-portada', 'cabc123-reel']]];
$av['evidencias']['cabc123-portada'] = ['tipo' => 'captura', 'titulo' => 'Portada', 'url' => '', 'archivo' => 'portada.png', 'poster' => '', 'poster_archivo' => ''];
$av['cierres'][0]['despues']['evidencias'][] = 'cabc123-reel2';
$av['cierres'][0]['despues']['evidencias'][] = 'cabc123-foto';
$av['evidencias']['cabc123-reel2'] = ['tipo' => 'video', 'titulo' => 'Mismo reel', 'url' => '', 'archivo' => 'REEL.mp4', 'poster' => '', 'poster_archivo' => ''];
$av['evidencias']['cabc123-foto'] = ['tipo' => 'captura', 'titulo' => 'Foto', 'url' => '', 'archivo' => 'foto.png', 'poster' => '', 'poster_archivo' => 'poster.jpg'];
$av['evidencias']['cabc123-reel'] = ['tipo' => 'video', 'titulo' => 'Reel', 'url' => '', 'archivo' => 'reel.mp4', 'poster' => '', 'poster_archivo' => 'poster.jpg'];
$res = at_av_asignar_archivos($av, 'cabc123', [subido($tmp, 'Portada.PNG', png()), subido($tmp, 'reel.mp4', $mp4), subido($tmp, 'poster.jpg', jpeg_con_exif()), subido($tmp, 'sobra.png', png()), subido($tmp, 'foto.png', png())], $plan_id);
$ev = $res['avance']['evidencias'];
ok($res['errores'] === [] && $ev['cabc123-reel2']['url'] !== '' && $ev['cabc123-reel2']['url'] === $ev['cabc123-reel']['url'], 'dos evidencias con el mismo archivo subido reutilizan el primer resultado');
ok($ev['cabc123-foto']['url'] !== '' && $ev['cabc123-foto']['poster'] === '', 'una captura nunca recibe póster, aunque nombre uno');
ok($res['errores'] === [] && $ev['cabc123-portada']['url'] !== '' && $ev['cabc123-reel']['url'] !== '' && $ev['cabc123-reel']['poster'] !== '', 'captura, video y póster quedan con su URL');
ok(count($res['avisos']) === 1 && str_contains($res['avisos'][0], 'sobra.png') && at_av_faltan_archivos($res['avance'], 'cabc123') === [], 'un archivo que no calza se avisa; ya no falta nada');
$viejo = basename($ev['cabc123-portada']['url']);
$una = at_av_subir_una($res['avance'], 'cabc123-portada', 'archivo', subido($tmp, 'otra.png', png(50, 50)), $plan_id);
ok($una['error'] === '' && $una['avance']['evidencias']['cabc123-portada']['url'] !== $ev['cabc123-portada']['url'] && $una['archivos'] === [$viejo], 'subir uno reemplaza el archivo y devuelve el viejo para borrarlo');
$mala = at_av_subir_una($res['avance'], 'cabc123-portada', 'poster', subido($tmp, 'p.png', png()), $plan_id);
ok($mala['error'] === 'Solo un video lleva póster.', 'una captura no lleva póster');

// Borrado: solo nombres propios del plan, nunca rutas.
file_put_contents($d['dir'] . 'zzzzzzzzzzzzzzzzzzzz.png', 'x');
file_put_contents($tmp . 'fuera.png', 'x');
$senuelo = $base . 'fuera.png'; // el destino real de «../fuera.png» desde la carpeta del plan
file_put_contents($senuelo, 'x');
at_av_borrar_archivos($plan_id, ['zzzzzzzzzzzzzzzzzzzz.png', '../../fuera.png', '../fuera.png', '.htaccess', $viejo]);
$sobrevive = is_file($senuelo);
@unlink($senuelo);
ok(!is_file($d['dir'] . 'zzzzzzzzzzzzzzzzzzzz.png') && !is_file($d['dir'] . $viejo) && is_file($tmp . 'fuera.png') && $sobrevive && is_file($base . '.htaccess'), 'borra solo archivos propios de la carpeta del plan');
fin();
