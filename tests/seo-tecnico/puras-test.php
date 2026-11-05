<?php
// Correr: php tests/seo-tecnico/puras-test.php   (sin WordPress)
require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/seo-tecnico/puras.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// Plantillas con <head> propio: deben existir y escribir su <title> (el home y las landings).
$tema = __DIR__ . '/../../wp-content/themes/automatiza-tech/';
foreach (at_seo_plantillas_propias() as $p) {
	ok(is_file($tema . $p), "existe {$p}");
	ok(substr_count((string) @file_get_contents($tema . $p), '<title>') === 1, "{$p} escribe un solo <title>");
}
ok(count(at_seo_plantillas_propias()) === 9, 'nueve plantillas con head propio');
ok(strpos((string) file_get_contents($tema . 'template-legal.php'), 'name="robots"') === false, 'legal: sin un segundo meta robots (WordPress ya imprime uno)');
ok(strpos((string) file_get_contents($tema . 'functions.php'), '<meta name="description"') === false && strpos((string) file_get_contents($tema . 'functions.php'), 'automatiza_tech_seo_optimizations') === false, 'functions.php: sin las metas del home antiguo');
ok(strpos((string) file_get_contents($tema . 'inc/home-premium-backend.php'), "'robots_txt'") === false, 'home-premium-backend: ya no agrega su propio robots.txt');

// Sitemap
ok(at_seo_excluidos() === ['post' => ['hola-mundo'], 'page' => ['pagina-ejemplo', 'validar-factura']], 'excluidos: relleno de WordPress y validar-factura');
ok(at_seo_proveedor_apagado('users'), 'sitemap de usuarios apagado');
ok(!at_seo_proveedor_apagado('posts') && !at_seo_proveedor_apagado('taxonomies'), 'sitemaps de páginas y categorías siguen');

// robots.txt
$llms = 'https://automatizatech.cl/llms.txt';
ok(at_seo_robots_extra(false, true, $llms) === '', 'sitio no público: no se agrega nada');
$r = at_seo_robots_extra(true, false, $llms);
ok(strpos($r, 'Sitemap:') === false, 'no repite Sitemap (lo escribe el núcleo)');
ok(preg_match('/^LLMS:/m', $r) === 0, 'sin la línea LLMS: no estándar');
ok(strpos($r, "# Índice para asistentes de IA: {$llms}") !== false, 'llms.txt queda como comentario');
foreach (['OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User'] as $bot) {
	ok(strpos($r, "User-agent: {$bot}\n") !== false, "grupo de búsqueda incluye {$bot}");
}
ok(strpos($r, "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php") !== false, 'el grupo de búsqueda conserva el bloqueo de wp-admin');
ok(strpos($r, 'GPTBot') === false && strpos($r, "Disallow: /\n") === false, 'sin decisión de bloqueo: el entrenamiento queda como antes');
$b = at_seo_robots_extra(true, true, $llms);
ok(preg_match("/User-agent: GPTBot\nUser-agent: ClaudeBot\nUser-agent: Google-Extended\nDisallow: \/\n/", $b) === 1, 'con bloqueo: GPTBot, ClaudeBot y Google-Extended con Disallow: /');
ok(preg_match("/User-agent: (OAI-SearchBot|Claude-SearchBot)\n(User-agent: .*\n)*Disallow: \/\n/", $b) === 0, 'con bloqueo: los de búsqueda no quedan bloqueados');
foreach (explode("\n", $b) as $l) {
	if ($l === '' || $l[0] === '#') { continue; }
	ok(preg_match('/^(User-agent|Disallow|Allow): \S+$/', $l) === 1, "directiva estándar: «{$l}»");
}

echo $fallas ? "\n{$fallas} FALLAS\n" : "\nTodo bien\n";
exit($fallas ? 1 : 0);
