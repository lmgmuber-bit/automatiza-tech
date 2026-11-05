<?php
// Correr: php tests/seo-tecnico/ganchos-test.php   (sin WordPress: imita los ganchos que usa cargar.php)
define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');

$ganchos = []; $quitados = [];
function add_action($h, $f, $p = 10, $n = 1) { global $ganchos; $ganchos[$h][] = $f; }
function add_filter($h, $f, $p = 10, $n = 1) { global $ganchos; $ganchos[$h][] = $f; }
function remove_action($h, $f, $p = 10) { global $quitados; $quitados[] = "{$h}:{$f}:{$p}"; return true; }
function correr($h, ...$a) { global $ganchos; foreach ($ganchos[$h] ?? [] as $f) { $a[0] = $f(...$a); } return $a[0] ?? null; }

// Estado simulado de la página pedida.
$plantilla = ''; $autor = false; $categoria = 0; $singular = 0; $tipo = 'page';
$publicadas = ['post' => ['hola-mundo' => 1], 'page' => ['pagina-ejemplo' => 2, 'validar-factura' => 3, 'asistentes' => 9]];
function is_page_template($lista) { global $plantilla; return in_array($plantilla, (array) $lista, true); }
function get_page_by_path($slug, $o, $tipo) { global $publicadas; $id = $publicadas[$tipo][$slug] ?? 0; return $id ? (object) ['ID' => $id] : null; }
function get_option($k) { return $k === 'default_category' ? 1 : null; }
function is_author() { global $autor; return $autor; }
function is_category($id) { global $categoria; return $categoria === $id; }
function is_singular() { global $singular; return $singular > 0; }
function get_queried_object_id() { global $singular; return $singular; }
function get_post_type() { global $tipo; return $tipo; }
function home_url($p = '') { return 'https://automatizatech.cl' . $p; }

require __DIR__ . '/../../wp-content/themes/automatiza-tech/inc/seo-tecnico/cargar.php';

$fallas = 0;
function ok($cond, $msg) { global $fallas; if ($cond) { echo "ok   $msg\n"; } else { $fallas++; echo "FALLA $msg\n"; } }

// <title> y canonical
$plantilla = 'template-landing-asistentes-ia.php'; correr('template_redirect');
ok($quitados === ['wp_head:_wp_render_title_tag:1', 'wp_head:rel_canonical:10'], 'landing: WordPress no imprime su <title> ni su canonical');
$quitados = []; $plantilla = 'template-home-premium.php'; correr('template_redirect');
ok(count($quitados) === 2, 'home: igual');
$quitados = []; $plantilla = ''; correr('template_redirect');
ok($quitados === [], 'página común (header.php): WordPress sigue a cargo del <title> y el canonical');

// Sitemap
ok(correr('wp_sitemaps_add_provider', 'P', 'users') === false, 'sin sitemap de usuarios');
ok(correr('wp_sitemaps_add_provider', 'P', 'posts') === 'P', 'sitemap de entradas y páginas sigue');
ok(correr('wp_sitemaps_posts_query_args', ['post_type' => 'page'], 'page')['post__not_in'] === [2, 3], 'páginas: fuera Página de ejemplo y validar-factura');
ok(correr('wp_sitemaps_posts_query_args', ['post_type' => 'post'], 'post')['post__not_in'] === [1], 'entradas: fuera Hola mundo');
ok(!isset(correr('wp_sitemaps_posts_query_args', [], 'attachment')['post__not_in']), 'otros tipos: sin cambios');
ok(correr('wp_sitemaps_taxonomies_query_args', ['taxonomy' => 'category'], 'category')['exclude'] === [1], 'categorías: fuera Sin categoría');
ok(!isset(correr('wp_sitemaps_taxonomies_query_args', [], 'post_tag')['exclude']), 'etiquetas: sin cambios');

// noindex
$base = ['max-image-preview' => 'large'];
$singular = 2; $tipo = 'page';
ok(correr('wp_robots', $base) === ['max-image-preview' => 'large', 'noindex' => true, 'follow' => true], 'Página de ejemplo: noindex, follow');
$singular = 9;
ok(correr('wp_robots', $base) === $base, 'landing: indexable como antes');
$singular = 0; $autor = true;
ok(isset(correr('wp_robots', $base)['noindex']), 'archivo de autor: noindex');
$autor = false; $categoria = 1;
ok(isset(correr('wp_robots', $base)['noindex']), 'Sin categoría: noindex');

// robots.txt sobre la salida del núcleo (grupo * + Sitemap de WP_Sitemaps::add_robots)
$nucleo = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: https://automatizatech.cl/wp-sitemap.xml\n";
$r = correr('robots_txt', $nucleo, '1');
ok(substr_count($r, 'Sitemap:') === 1, 'robots.txt: un solo Sitemap');
ok(strpos($r, "\nLLMS:") === false, 'robots.txt: sin LLMS:');
ok(strpos($r, 'User-agent: OAI-SearchBot') !== false && strpos($r, 'User-agent: Claude-SearchBot') !== false, 'robots.txt: grupo de búsqueda con IA');
ok(correr('robots_txt', "User-agent: *\nDisallow: /\n", '0') === "User-agent: *\nDisallow: /\n", 'sitio no público: robots.txt intacto');
echo "\n--- robots.txt resultante ---\n{$r}";

echo $fallas ? "\n{$fallas} FALLAS\n" : "\nTodo bien\n";
exit($fallas ? 1 : 0);
