<?php
// Correr: AT_WP_LOAD=<ruta> php tests/entregables/vista-wp-test.php
require __DIR__ . '/wp-bootstrap.php';
require __DIR__ . '/fixtures.php';
require __DIR__ . '/accion-helpers.php';
exigir('at_en_html_pagina', 'at_en_accion_nota', 'at_en_token_hoy');
$marca = 'en' . substr(md5(uniqid('', true)), 0, 8);
register_shutdown_function(function () use ($marca) { en_fx_limpiar($marca); });
$fx = en_fx_cliente($marca);
$id = at_en_activar($fx['detalle']);
$e = at_en_por_id($id);
$cod = (string) $e->codigo;

// Sin versiones / inexistente / código con otras mayúsculas: igual «no disponible»
$h0 = at_en_html_pagina($e);
ok(strpos($h0, 'Este enlace no está disponible.') !== false && strpos($h0, '<form') === false, 'sin versiones: no disponible');
ok(at_en_por_codigo(strtoupper($cod) === $cod ? strtolower($cod) : strtoupper($cod)) === null && strpos(at_en_html_pagina(null), 'Este enlace no está disponible.') !== false, 'otro código: no disponible');
ok(strpos(at_en_html_pagina(null), 'noindex') !== false, 'noindex en la página');

// Con versión
at_en_crear_version($id, 'https://example.com/v1', 'Primera');
at_en_crear_version($id, 'https://example.com/v2', 'Segunda');
$e = at_en_por_id($id);
$h = at_en_html_pagina($e);
ok(strpos($h, 'Propuestas ' . $marca) !== false && strpos($h, 'Empresa ' . $marca) !== false, 'título y empresa');
ok(strpos($h, 'href="https://example.com/v2"') !== false && strpos($h, 'Abrir la versión 2') !== false, 'botón a la versión vigente');
ok(strpos($h, 'href="https://example.com/v1"') !== false && strpos($h, 'Versión 1') !== false, 'versiones anteriores');
ok(strpos($h, 'name="token" value="' . at_en_token_hoy($cod) . '"') !== false && strpos($h, 'enctype="multipart/form-data"') !== false && strpos($h, 'accept="image/jpeg,image/png"') !== false, 'formulario con token y 0 a 3 imágenes');
ok(strpos($h, 'value="Cliente"') !== false, 'nombre precargado con el de la ficha');
ok(strpos($h, 'admin-post.php?action=at_en_nota&#038;codigo=' . $cod) !== false && strpos($h, 'name="codigo" value="' . $cod . '"') !== false, 'la acción del formulario también lleva el código en la URL');

// Nota del cliente por la acción (sin sesión)
$base = ['codigo' => $cod, 'token' => at_en_token_hoy($cod), 'nombre' => 'Cliente', 'texto' => "Me gusta <script>alert(1)</script>\ny la v2"];
$r = en_correr('at_en_nota', 0, '-', $base);
$q = en_query($r['redirect']);
ok(($q['en_msg'] ?? '') === 'nota_ok' && ($q['id'] ?? '') === $cod && $q['#'] === 'notas', 'nota guardada: vuelve con nota_ok#notas');
ok(count(at_en_notas($id)) === 1 && at_en_notas($id)[0]->version_numero == 2, 'nota guardada sobre la v2');
ok(count($r['mails']) === 1 && strpos($r['mails'][0], 'dejó una nota') !== false, 'aviso a Luis enviado');
$h2 = at_en_html_pagina(at_en_por_id($id));
ok(strpos($h2, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && strpos($h2, '<script>alert(1)') === false, 'la nota se ve escapada');
ok(strpos($h2, 'sobre la versión 2') !== false, 'la nota dice sobre qué versión');

// Sin imágenes está bien; con imagen real también
$tmp = sys_get_temp_dir() . '/en-v-' . $marca . '.png';
$im = imagecreatetruecolor(50, 40); imagepng($im, $tmp); imagedestroy($im);
$files = ['imagenes' => ['name' => ['a.png'], 'type' => ['image/png'], 'tmp_name' => [$tmp], 'error' => [0], 'size' => [filesize($tmp)]]];
$r = en_correr('at_en_nota', 0, '-', ['texto' => 'Con foto'] + $base, $files);
$ult = array_values(at_en_notas($id))[1] ?? null;
ok(en_query($r['redirect'])['en_msg'] === 'nota_ok' && $ult && count(json_decode((string) $ult->imagenes, true)) === 1, 'nota con 1 imagen');
$img = json_decode((string) $ult->imagenes, true)[0];
ok(strpos(at_en_html_pagina(at_en_por_id($id)), 'ver-entregable.php?id=' . $cod . '&amp;img=' . $img) !== false, 'miniatura servida por la página');

// Errores
ok(en_query(en_correr('at_en_nota', 0, '-', ['token' => 'f00'] + $base)['redirect'])['en_msg'] === 'sesion_vencida', 'token malo: sesion_vencida');
ok(en_query(en_correr('at_en_nota', 0, '-', ['texto' => ''] + $base)['redirect'])['en_msg'] === 'nota_vacia', 'nota vacía');
file_put_contents($tmp . '.jpg', '<?php echo 1;');
$malo = ['imagenes' => ['name' => ['x.jpg'], 'type' => ['image/jpeg'], 'tmp_name' => [$tmp . '.jpg'], 'error' => [0], 'size' => [12]]];
$rm = en_correr('at_en_nota', 0, '-', $base, $malo);
ok(en_query($rm['redirect'])['en_msg'] === 'imagen' && count(at_en_notas($id)) === 2, 'imagen falsa: no se guarda la nota');
ok(strpos(at_en_html_pagina(at_en_por_id($id), 'imagen'), 'Una de las imágenes no sirve.') !== false, 'mensaje de imagen en la página');
$qx = en_query(en_correr('at_en_nota', 0, '-', ['codigo' => 'NoExiste1234'] + $base)['redirect']);
ok(($qx['en_msg'] ?? '') === 'no_disponible', 'código inexistente');

// POST que superó post_max_size: PHP vacía $_POST y $_FILES; el código viaja en la URL de la acción
$rg = en_correr('at_en_nota', 0, '-', [], [], ['EN_CONTENT_LENGTH' => '99999999', 'EN_GET_CODIGO' => $cod]);
$qg = en_query($rg['redirect']);
ok(($qg['en_msg'] ?? '') === 'envio_grande' && ($qg['id'] ?? '') === $cod && $qg['#'] === 'notas' && count(at_en_notas($id)) === 2, 'POST demasiado grande: envio_grande y no se guarda nada');
ok(strpos(at_en_html_pagina(at_en_por_id($id), 'envio_grande'), 'Las imágenes pesan demasiado en total.') !== false, 'mensaje de envio_grande en la página');
$qg2 = en_query(en_correr('at_en_nota', 0, '-', [], [], ['EN_CONTENT_LENGTH' => '99999999', 'EN_GET_CODIGO' => 'NoExiste1234'])['redirect']);
ok(($qg2['en_msg'] ?? '') === 'no_disponible', 'POST grande con código inexistente: no_disponible');

// Límite de 10 por hora (por IP)
$ip = ['EN_IP' => '10.9.8.' . rand(1, 250)];
$msgs = [];
for ($i = 0; $i < 11; $i++) {
	$msgs[] = en_query(en_correr('at_en_nota', 0, '-', ['texto' => "n$i"] + $base, [], $ip)['redirect'])['en_msg'] ?? '';
}
ok(count(array_filter($msgs, function ($m) { return $m === 'nota_ok'; })) === 10 && end($msgs) === 'muchos_intentos', 'la 11.ª nota en una hora: muchos_intentos');

// Cerrado
at_en_cambiar_estado($id, 'cerrado');
$hc = at_en_html_pagina(at_en_por_id($id));
ok(strpos($hc, 'Este entregable ya está cerrado.') !== false && strpos($hc, '<form') === false && strpos($hc, 'Me gusta') !== false, 'cerrado: muestra historial sin formulario');
ok(en_query(en_correr('at_en_nota', 0, '-', $base)['redirect'])['en_msg'] === 'cerrado', 'POST a un cerrado: cerrado');
@unlink($tmp); @unlink($tmp . '.jpg');
fin();
