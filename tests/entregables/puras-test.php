<?php
// Correr: php tests/entregables/puras-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/entregables/puras.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }

// Código y enlaces
ok(at_en_codigo_valido('Ab3dE5fG7hJ9') && !at_en_codigo_valido('Ab3dE5fG7hJ') && !at_en_codigo_valido('Ab3dE5fG7hJ90') && !at_en_codigo_valido('Ab3dE5fG7h/9') && !at_en_codigo_valido(''), 'código: 12 letras o números exactos');
ok(at_en_url_pagina('https://automatizatech.cl/', 'Ab3dE5fG7hJ9') === 'https://automatizatech.cl/ver-entregable.php?id=Ab3dE5fG7hJ9', 'enlace a la página sin barra doble');
ok(at_en_url_pagina('', 'Ab3dE5fG7hJ9') === '' && at_en_url_pagina('https://x.cl', 'malo') === '', 'sin sitio o código raro: sin enlace');
ok(at_en_url_imagen('https://x.cl', 'Ab3dE5fG7hJ9', 'abcdefghijklmnopqrstuvwx.jpg') === 'https://x.cl/ver-entregable.php?id=Ab3dE5fG7hJ9&img=abcdefghijklmnopqrstuvwx.jpg', 'enlace a una imagen');
ok(at_en_url_imagen('https://x.cl', 'Ab3dE5fG7hJ9', '../wp-config.php') === '', 'imagen con ruta: sin enlace');

// Token del formulario
$sal = 'sal-de-prueba';
$t = at_en_token('Ab3dE5fG7hJ9', 20000, $sal);
ok((bool) preg_match('/^[a-f0-9]{24}$/', $t), 'token de 24 hexadecimales');
ok(at_en_token_valido($t, 'Ab3dE5fG7hJ9', 20000, $sal) && at_en_token_valido($t, 'Ab3dE5fG7hJ9', 20001, $sal), 'vale el día de emisión y el siguiente');
ok(!at_en_token_valido($t, 'Ab3dE5fG7hJ9', 20002, $sal), 'al tercer día vence');
ok(!at_en_token_valido($t, 'Zb3dE5fG7hJ9', 20000, $sal) && !at_en_token_valido('x' . substr($t, 1), 'Ab3dE5fG7hJ9', 20000, $sal) && !at_en_token_valido('', 'Ab3dE5fG7hJ9', 20000, $sal), 'otro código, token alterado o vacío: no vale');

// Nota
$n = at_en_validar_nota('  Orly  ', "Hola\r\nme gusta la v3");
ok($n['ok'] && $n['nombre'] === 'Orly' && $n['texto'] === "Hola\nme gusta la v3", 'nota válida: recorta el nombre y normaliza saltos');
ok(!at_en_validar_nota('Orly', "   ")['ok'] && at_en_validar_nota('Orly', '  ')['error'] === 'nota_vacia', 'nota vacía: error nota_vacia');
ok(at_en_validar_nota('', 'texto')['error'] === 'nombre_vacio', 'sin nombre: error nombre_vacio');
ok(at_en_validar_nota(str_repeat('a', 81), 'texto')['error'] === 'nombre_largo', 'nombre de 81: error nombre_largo');
$emoji = str_repeat('é', 2999) . '🙂';
ok(at_en_validar_nota('Orly', $emoji)['ok'], '3.000 caracteres con tildes y emoji: pasa');
ok(at_en_validar_nota('Orly', $emoji . 'x')['error'] === 'nota_larga', '3.001 caracteres: error nota_larga');
ok(at_en_validar_nota('Orly', "a\x00b\x07c\td")['texto'] === "abc\td", 'quita caracteres de control salvo tab y salto');
ok(at_en_validar_nota('Orly', '<script>alert(1)</script>')['texto'] === '<script>alert(1)</script>', 'se guarda tal cual (se escapa al mostrar)');

// Versión
ok(at_en_url_version_valida(' https://funerariasamordedios.cl/propuestas/ ') === 'https://funerariasamordedios.cl/propuestas/', 'URL de versión http(s) válida');
ok(at_en_url_version_valida('https://algo.easypanel.host/p/x') === '' && at_en_url_version_valida('javascript:alert(1)') === '' && at_en_url_version_valida('ftp://x') === '' && at_en_url_version_valida('') === '', 'easypanel, javascript, ftp o vacío: inválida');
ok(at_en_validar_mensaje(str_repeat('a', 6000))['ok'] && at_en_validar_mensaje(str_repeat('a', 6001))['error'] === 'mensaje_largo', 'mensaje hasta 6.000');
ok(at_en_validar_mensaje('')['ok'], 'mensaje vacío permitido (la plantilla igual dice qué es)');
ok(at_en_validar_mensaje('Mira https://algo.easypanel.host/p')['error'] === 'mensaje_easypanel' && at_en_validar_mensaje('Usa EasyPanel para verlo')['error'] === 'mensaje_easypanel' && !at_en_validar_mensaje('x EASYPANEL x')['ok'], 'mensaje con «easypanel» (sin importar mayúsculas): mensaje_easypanel');
ok(at_en_validar_mensaje('Mira https://automatizatech.cl/ver-presentacion.php?id=1')['ok'], 'mensaje con enlace de automatizatech.cl: sirve');
ok(isset(at_en_mensajes()['mensaje_easypanel']) && strpos(at_en_mensajes()['mensaje_easypanel'], 'ver-presentacion.php') !== false, 'mensaje de easypanel explica qué enlace usar');

// Imágenes (reglas puras)
ok(at_en_nombre_imagen_valido('abcdefghijklmnopqrstuvwx.jpg') && at_en_nombre_imagen_valido('0123456789abcdefghijklmn.png'), 'nombre interno válido');
ok(!at_en_nombre_imagen_valido('../abcdefghijklmnopqrstuvw.jpg') && !at_en_nombre_imagen_valido('abcdefghijklmnopqrstuvwx.php') && !at_en_nombre_imagen_valido('ABCDEFGHIJKLMNOPQRSTUVWX.jpg'), 'ruta, otra extensión o mayúsculas: inválido');
$base = ['indice' => 1, 'error' => 0, 'size' => 1000, 'tipo' => 'image/jpeg', 'ancho' => 800, 'alto' => 600];
ok(at_en_revisar_imagen($base) === '', 'JPG chico: sirve');
ok(at_en_revisar_imagen(['tipo' => 'image/png'] + $base) === '', 'PNG: sirve');
ok(at_en_revisar_imagen(['tipo' => 'image/gif'] + $base) === 'La imagen 1 no es JPG ni PNG.', 'GIF: no');
ok(at_en_revisar_imagen(['tipo' => ''] + $base) === 'La imagen 1 no es JPG ni PNG.', 'sin tipo real (no es imagen): no');
ok(at_en_revisar_imagen(['size' => 5242881, 'indice' => 2] + $base) === 'La imagen 2 pesa más de 5 MB.', 'más de 5 MB: no');
ok(at_en_revisar_imagen(['ancho' => 12001] + $base) === 'La imagen 1 es demasiado grande (más de 12.000 px de lado).', 'lado de 12.001 px: no');
ok(at_en_revisar_imagen(['error' => 1] + $base) === 'La imagen 1 no se pudo subir. Inténtalo de nuevo.', 'error de subida: no');

// Errores de imagen con clave y número (para no perder la razón en la redirección)
ok(at_en_clave_error_imagen($base) === '', 'clave de error: imagen que sirve = vacía');
ok(at_en_clave_error_imagen(['tipo' => 'image/gif'] + $base) === 'img_tipo' && at_en_clave_error_imagen(['tipo' => ''] + $base) === 'img_tipo', 'clave: img_tipo');
ok(at_en_clave_error_imagen(['size' => 5242881] + $base) === 'img_peso', 'clave: img_peso');
ok(at_en_clave_error_imagen(['ancho' => 12001] + $base) === 'img_lado' && at_en_clave_error_imagen(['ancho' => 8000, 'alto' => 6000] + $base) === 'img_mp', 'clave: img_lado e img_mp');
ok(at_en_clave_error_imagen(['error' => 1] + $base) === 'img_subida' && at_en_clave_error_imagen(['ancho' => 0, 'alto' => 0] + $base) === 'img_tipo', 'clave: img_subida y sin medidas = img_tipo');
$mm = at_en_mensajes();
foreach (['img_tipo', 'img_peso', 'img_lado', 'img_mp', 'img_subida', 'img_proceso'] as $k) {
	ok(isset($mm[$k]) && strpos($mm[$k], '%d') !== false, "mensaje «{$k}» con el número de la imagen");
}
ok(at_en_formatear_mensaje($mm['img_tipo'], 2) === 'La imagen 2 no es JPG ni PNG.' && at_en_formatear_mensaje($mm['img_proceso'], 3) === 'La imagen 3 no se pudo procesar. Prueba con otra.', 'mensaje con número: «La imagen 2 no es JPG ni PNG.»');
ok(at_en_formatear_mensaje($mm['img_peso'], 0) === 'La imagen 1 pesa más de 5 MB.' && at_en_formatear_mensaje($mm['imagen'], 2) === 'Una de las imágenes no sirve.', 'sin número válido: 1; sin %d: tal cual');
ok(at_en_redireccion_error_imagen('img_tipo', 2) === ['img_tipo', 2] && at_en_redireccion_error_imagen('muchas_imagenes', 0) === ['muchas_imagenes', 0], 'redirección: clave válida con su número');
ok(at_en_redireccion_error_imagen('img_tipo', 9) === ['imagen', 0] && at_en_redireccion_error_imagen('img_tipo', 0) === ['imagen', 0] && at_en_redireccion_error_imagen('<script>', 1) === ['imagen', 0] && at_en_redireccion_error_imagen('nota_ok', 1) === ['imagen', 0], 'redirección: clave o número raros caen en el mensaje genérico');

// Mensaje de Luis → HTML
$h =at_en_mensaje_html("Hola Orly:\n\nCambié dos cosas:\n- la portada\n- los colores\n\nMira https://x.cl/p?a=1&b=2 y dime.\n<script>alert(1)</script>");
ok(strpos($h, '<p>Hola Orly:</p>') !== false, 'párrafo por bloque');
ok(strpos($h, '<ul style="padding-left:20px;margin:0 0 14px;"><li>la portada</li><li>los colores</li></ul>') !== false, 'líneas con «- » forman lista');
ok(strpos($h, '<a href="https://x.cl/p?a=1&amp;b=2" style="color:#1e3a8a;">https://x.cl/p?a=1&amp;b=2</a>') !== false, 'URL a enlace escapado');
ok(strpos($h, '<script>') === false && strpos($h, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, '<script> queda como texto');
ok(at_en_mensaje_html('') === '', 'mensaje vacío: nada');

// Extracto
ok(at_en_extracto(str_repeat('a', 500), 400) === str_repeat('a', 400) . '…' && at_en_extracto('corto') === 'corto', 'extracto de 400 con «…»');

// WhatsApp
$w = at_en_texto_whatsapp_version('Orly', 2, 'Propuestas de diseño', 'https://x.cl/ver-entregable.php?id=Ab3dE5fG7hJ9');
ok($w === 'Hola Orly, te escribimos del equipo de AutomatizaTech. Te enviamos la versión 2 de Propuestas de diseño. Puedes verla y dejarnos tus notas aquí: https://x.cl/ver-entregable.php?id=Ab3dE5fG7hJ9', 'WhatsApp de versión');
ok(strpos(at_en_texto_whatsapp_version('', 1, 'X', 'u'), 'Hola, te escribimos del equipo') === 0, 'sin nombre: «Hola,»');
ok(at_en_texto_whatsapp_respuesta('Orly', 2, 'Propuestas de diseño', 'https://x.cl/v') === 'Hola Orly, te respondimos tu nota sobre la versión 2 de Propuestas de diseño: https://x.cl/v', 'WhatsApp de respuesta');
ok(at_en_url_wa('+56 9 1111 1111', 'Hola Orly') === 'https://wa.me/56911111111?text=Hola%20Orly', 'wa.me con el teléfono del cliente');
ok(at_en_url_wa('', 'Hola') === '', 'sin teléfono: sin enlace');

// Mensajes
$m = at_en_mensajes();
foreach (['no_disponible', 'cerrado', 'nota_ok', 'nota_vacia', 'nota_larga', 'nombre_vacio', 'nombre_largo', 'sesion_vencida', 'muchos_intentos', 'imagen', 'muchas_imagenes', 'no_guardo', 'envio_grande'] as $k) {
	ok(isset($m[$k]) && $m[$k] !== '', "mensaje «{$k}»");
}

// Ronda de correcciones 1
ok(!at_en_codigo_valido("Ab3dE5fG7hJ9\n"), 'código con salto de línea final: inválido');
ok(!at_en_nombre_imagen_valido("abcdefghijklmnopqrstuvwx.jpg\n"), 'nombre de imagen con salto final: inválido');
ok(at_en_url_pagina('https://x.cl', "Ab3dE5fG7hJ9\n") === '' && at_en_url_imagen('https://x.cl', 'Ab3dE5fG7hJ9', "abcdefghijklmnopqrstuvwx.jpg\n") === '', 'enlaces con salto final: sin enlace');
ok(!at_en_token_valido($t . "\n", 'Ab3dE5fG7hJ9', 20000, $sal), 'token con salto final: no vale');
ok(at_en_validar_nota("Orly\r\nBcc: a@b.c", 'hola')['nombre'] === 'Orly Bcc: a@b.c', 'nombre con CRLF: una sola línea');
ok(at_en_validar_nota("Orly\rX", 'hola')['nombre'] === 'Orly X', 'nombre con CR: una sola línea');
ok(at_en_revisar_imagen(['ancho' => 8000, 'alto' => 6000] + $base) === 'La imagen 1 es demasiado grande (más de 25 megapíxeles).', '8000x6000: más de 25 MP');
ok(at_en_revisar_imagen(['ancho' => 6000, 'alto' => 5000] + $base) === 'La imagen 1 es demasiado grande (más de 25 megapíxeles).', '6000x5000 (30 MP): no');
ok(at_en_revisar_imagen(['ancho' => 4000, 'alto' => 3000] + $base) === '', '4000x3000: sirve');
ok(at_en_revisar_imagen(['ancho' => 0, 'alto' => 0] + $base) === 'La imagen 1 no es JPG ni PNG.', 'sin dimensiones: no es imagen');
$inv = at_en_validar_nota('Orly', "caf\xe9 rico");
ok($inv['ok'] && $inv['texto'] !== '' && mb_check_encoding($inv['texto'], 'UTF-8') && strpos($inv['texto'], 'rico') !== false, 'UTF-8 inválido: se descartan los bytes malos y se conserva el resto');
fin();
