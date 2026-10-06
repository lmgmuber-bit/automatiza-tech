<?php
// Correr: php tests/entregables/plantillas-test.php   (sin WordPress)
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/plan-trabajo/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/entregables/puras.php';
require_once __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/entregables/plantillas.php';
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }
function fin(): void { global $fallas; echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n"; exit($fallas ? 1 : 0); }
$logo = 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png';
$pag = 'https://automatizatech.cl/ver-entregable.php?id=Ab3dE5fG7hJ9';

$c = at_en_correo_version(['nombre' => 'Orly <b>', 'titulo' => 'Propuestas de diseño', 'numero' => 2, 'url_version' => 'https://funerariasamordedios.cl/propuestas/', 'url_pagina' => $pag, 'mensaje' => "Cambié:\n- la portada", 'logo' => $logo, 'prueba' => false]);
ok($c['asunto'] === 'Propuestas de diseño: versión 2 para revisar', 'asunto de la versión');
ok(strpos($c['html'], 'linear-gradient(135deg,#1e3a8a,#06d6a0)') !== false && strpos($c['html'], $logo) !== false, 'diseño AT con logo');
ok(strpos($c['html'], 'Hola <strong>Orly &lt;b&gt;</strong>') !== false, 'nombre escapado');
ok(strpos($c['html'], 'Te enviamos la versión 2 de <strong>Propuestas de diseño</strong>') !== false, 'frase de la versión');
ok(strpos($c['html'], 'href="https://funerariasamordedios.cl/propuestas/"') !== false && strpos($c['html'], '>Ver la versión 2</a>') !== false, 'botón Ver la versión N');
ok(strpos($c['html'], 'href="' . $pag . '"') !== false && strpos($c['html'], '>Agregar notas u observaciones</a>') !== false, 'botón de notas a la página');
ok(strpos($c['html'], '<li>la portada</li>') !== false, 'mensaje de Luis con lista');
ok(strpos($c['html'], 'Luis Miguel') !== false && strpos($c['html'], 'easypanel') === false, 'firma y sin easypanel');
$p = at_en_correo_version(['nombre' => 'Orly', 'titulo' => 'X', 'numero' => 1, 'url_version' => 'https://x.cl', 'url_pagina' => $pag, 'mensaje' => '', 'logo' => $logo, 'prueba' => true]);
ok(strpos($p['asunto'], '[PRUEBA] ') === 0, 'prueba: asunto con [PRUEBA]');
$e = at_en_correo_version(['nombre' => 'O', 'titulo' => 'X', 'numero' => 1, 'url_version' => 'https://n8n.easypanel.host/p', 'url_pagina' => $pag, 'mensaje' => '', 'logo' => $logo, 'prueba' => false]);
ok(strpos($e['html'], 'easypanel') === false && strpos($e['html'], 'Ver la versión 1') === false, 'URL easypanel: sin botón de versión');

$l = at_en_correo_nota_luis(['cliente' => 'Orly', 'empresa' => 'Funerarias Amor de Dios', 'titulo' => 'Propuestas de diseño', 'numero' => 2, 'texto' => "Me gusta <script>x</script>\nla v3", 'imagenes' => 2, 'url_ficha' => 'https://automatizatech.cl/wp-admin/admin.php?page=automatiza-crm-ficha&id=4#tab-entregables', 'logo' => $logo]);
ok($l['asunto'] === '📝 Orly dejó una nota en Propuestas de diseño', 'asunto del aviso a Luis');
ok(strpos($l['html'], '&lt;script&gt;') !== false && strpos($l['html'], '<script>') === false && strpos($l['html'], "Me gusta &lt;script&gt;x&lt;/script&gt;<br />\nla v3") !== false, 'texto escapado con saltos');
ok(strpos($l['html'], 'sobre la versión 2') !== false && strpos($l['html'], '2 imágenes adjuntas') !== false && strpos($l['html'], '>Responder en la ficha</a>') !== false, 'versión, imágenes y botón a la ficha');

$r = at_en_correo_respuesta(['nombre' => 'Orly', 'titulo' => 'Propuestas de diseño', 'numero' => 2, 'texto' => str_repeat('a', 450), 'url_pagina' => $pag, 'logo' => $logo]);
ok($r['asunto'] === 'Respuesta a tu nota sobre Propuestas de diseño', 'asunto de la respuesta');
ok(strpos($r['html'], 'Luis respondió tu nota sobre la versión 2') !== false && strpos($r['html'], str_repeat('a', 400) . '…') !== false && strpos($r['html'], str_repeat('a', 401)) === false, 'extracto de 400');
ok(strpos($r['html'], '>Ver la conversación</a>') !== false && strpos($r['html'], 'href="' . $pag . '"') !== false, 'botón a la conversación');
fin();
