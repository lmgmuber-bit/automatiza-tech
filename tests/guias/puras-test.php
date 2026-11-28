<?php
// Funciones puras de las guías. Uso: php tests/guias/puras-test.php  (no necesita WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/guias/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Fechas
ok(at_guias_fecha_es('2026-11-16') === '16 de noviembre de 2026', 'fecha: AAAA-MM-DD');
ok(at_guias_fecha_es('2026-12-01T10:00:00-03:00') === '1 de diciembre de 2026', 'fecha: ISO con hora y sin cero inicial');
ok(at_guias_fecha_es('2026-02-30') === '' && at_guias_fecha_es('') === '' && at_guias_fecha_es('16-11-2026') === '', 'fecha inválida da vacío');

// Resumen
ok(at_guias_resumen("  <p>Hola   <b>mundo</b></p>\n") === 'Hola mundo', 'resumen: quita etiquetas y espacios');
$largo = str_repeat('palabra ', 60);
$r = at_guias_resumen($largo, 160);
ok(mb_strlen($r) <= 160 && mb_substr($r, -1) === '…' && substr($r, -3, 1) !== ' ', 'resumen: corta en palabra y termina en …');
ok(at_guias_resumen('Ley 21.719 &amp; privacidad') === 'Ley 21.719 & privacidad', 'resumen: decodifica entidades');

// Migas
$m = at_guias_migas();
ok(count($m) === 2 && $m[1]['nombre'] === 'Guías' && $m[1]['url'] === 'https://automatizatech.cl/guias/', 'migas del listado: Inicio › Guías');
$m = at_guias_migas('Ley 21.719', 'https://automatizatech.cl/guias/ley-21719/');
ok(count($m) === 3 && $m[2]['nombre'] === 'Ley 21.719' && $m[2]['url'] === 'https://automatizatech.cl/guias/ley-21719/', 'migas de la entrada: tres niveles');

// JSON-LD Article
$d = ['titulo' => 'Guía </script> de prueba', 'descripcion' => 'Resumen á é ñ', 'publicado' => '2026-11-20T09:00:00-03:00', 'modificado' => '', 'url' => 'https://automatizatech.cl/guias/prueba/'];
$json = at_guias_json(at_guias_jsonld_articulo($d));
$a = json_decode($json, true);
ok(json_last_error() === JSON_ERROR_NONE, 'Article: JSON válido');
ok(stripos($json, '</script>') === false, 'Article: no puede cerrar el <script> (JSON_HEX_TAG)');
ok($a['@type'] === 'Article' && $a['headline'] === 'Guía </script> de prueba' && $a['description'] === 'Resumen á é ñ', 'Article: headline y description');
ok($a['datePublished'] === $d['publicado'] && $a['dateModified'] === $d['publicado'], 'Article: dateModified cae en datePublished si falta');
ok($a['author']['@type'] === 'Person' && $a['author']['name'] === 'Luis Miguel González' && $a['author']['url'] === 'https://automatizatech.cl/', 'Article: autor Person con nombre y url');
ok($a['publisher']['@type'] === 'Organization' && $a['publisher']['name'] === 'AutomatizaTech' && $a['publisher']['logo']['url'] === 'https://automatizatech.cl/wp-content/themes/automatiza-tech/assets/images/logo-automatiza-tech.png', 'Article: publisher con el logo real');
ok($a['mainEntityOfPage']['@id'] === $d['url'] && !isset($a['image']), 'Article: mainEntityOfPage y sin image si no hay');
$a2 = at_guias_jsonld_articulo(array_merge($d, ['imagen' => 'https://automatizatech.cl/x.jpg', 'modificado' => '2026-11-22T09:00:00-03:00']));
ok($a2['image'] === ['https://automatizatech.cl/x.jpg'] && $a2['dateModified'] === '2026-11-22T09:00:00-03:00', 'Article: image y dateModified propios');

// BreadcrumbList
$b = at_guias_jsonld_migas(at_guias_migas('Ley 21.719', 'https://automatizatech.cl/guias/ley-21719/'));
ok($b['@type'] === 'BreadcrumbList' && count($b['itemListElement']) === 3 && $b['itemListElement'][2]['position'] === 3 && $b['itemListElement'][0]['item'] === 'https://automatizatech.cl/', 'BreadcrumbList: posiciones y urls');

// CollectionPage
$c = at_guias_jsonld_coleccion('https://automatizatech.cl/guias/', 'Guías', 'Desc', [['titulo' => 'A', 'url' => 'https://automatizatech.cl/guias/a/']]);
ok($c['@type'] === 'CollectionPage' && $c['mainEntity']['itemListElement'][0]['name'] === 'A', 'CollectionPage: lista de guías');
ok(json_decode(at_guias_json($c), true) !== null, 'CollectionPage: JSON válido');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTodo bien\n";
exit($fallas ? 1 : 0);
