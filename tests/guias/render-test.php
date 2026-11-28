<?php
// Renderiza archive-guia.php y single-guia.php con funciones de WordPress de mentira y revisa el HTML.
// Uso: php tests/guias/render-test.php  (no necesita WordPress ni base de datos)
define('ABSPATH', __DIR__);
$tema = realpath(__DIR__ . '/../../wp-content/themes/automatiza-tech');
$fallas = 0;
function ok($c, $m) { global $fallas; if ($c) { echo "ok   $m\n"; } else { $fallas++; echo "FALLA $m\n"; } }

// ---- WordPress de mentira ----
$GLOBALS['hay'] = []; $GLOBALS['i'] = -1; $GLOBALS['cabeza'] = 0;
function get_template_directory() { global $tema; return $tema; }
function get_template_directory_uri() { return 'https://automatizatech.cl/wp-content/themes/automatiza-tech'; }
function home_url($p = '/') { return 'https://automatizatech.cl' . $p; }
function esc_url($u) { return htmlspecialchars((string) $u, ENT_QUOTES); }
function esc_url_raw($u) { return (string) $u; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function wp_json_encode($d) { return json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
function rest_url($p) { return 'https://automatizatech.cl/wp-json/' . $p; }
function admin_url($p) { return 'https://automatizatech.cl/wp-admin/' . $p; }
function wp_create_nonce($a) { return 'nonce'; }
function language_attributes() { echo 'lang="es-CL"'; }
function bloginfo($k) { echo 'UTF-8'; }
function body_class($c) { echo 'class="' . esc_attr($c) . '"'; }
function wp_head() { echo "<!--wp_head-->\n"; }
function wp_footer() { echo "<!--wp_footer-->\n"; }
function remove_action() {}
function get_query_var($k) { return 1; }
function paginate_links($a) { return []; }
function have_posts() { return $GLOBALS['i'] + 1 < count($GLOBALS['hay']); }
function the_post() { $GLOBALS['i']++; }
function rewind_posts() { $GLOBALS['i'] = -1; }
function P() { return $GLOBALS['hay'][max(0, $GLOBALS['i'])]; }
function get_the_ID() { return 7; }
function get_the_title() { return P()['titulo']; }
function get_permalink() { return 'https://automatizatech.cl/guias/' . P()['slug'] . '/'; }
function has_excerpt() { return P()['extracto'] !== ''; }
function get_the_excerpt() { return P()['extracto']; }
function get_the_content() { return P()['contenido']; }
function get_post_field($c, $id) { return P()['contenido']; }
function get_post_time($f, $g = false) { return $f === 'c' ? '2026-11-20T09:00:00-03:00' : '2026-11-20'; }
function get_post_modified_time($f, $g = false) { return $f === 'c' ? '2026-11-24T09:00:00-03:00' : '2026-11-24'; }
function get_the_post_thumbnail_url($id, $s) { return ''; }
function the_content() { echo P()['contenido']; }
require_once $tema . '/inc/guias/cargar.php';
function add_action() {} function add_filter() {} function register_post_type() {} function get_option() { return '1'; }
function is_post_type_archive() { return false; } function is_singular() { return false; } function is_page_template() { return false; }
function at_seo_plantillas_propias() { return []; }

function renderiza($archivo) { global $tema; ob_start(); include $tema . '/' . $archivo; return ob_get_clean(); }
function cuenta($h, $re) { return preg_match_all($re, $h); }

// ---- Una guía de mentira ----
$GLOBALS['hay'] = [['titulo' => 'Ley 21.719 de datos personales', 'slug' => 'ley-21719', 'extracto' => 'Qué cambia para tu pyme desde el 1 de diciembre.', 'contenido' => '<p>Texto de prueba.</p><h2>Subtítulo</h2>']];
$h = renderiza('single-guia.php');
ok(cuenta($h, '/<title>/') === 1 && strpos($h, '<title>Ley 21.719 de datos personales | AutomatizaTech</title>') !== false, 'entrada: un solo <title>');
ok(cuenta($h, '/rel="canonical"/') === 1 && strpos($h, 'rel="canonical" href="https://automatizatech.cl/guias/ley-21719/"') !== false, 'entrada: un solo canonical');
ok(cuenta($h, '/name="description"/') === 1 && strpos($h, 'content="Qué cambia para tu pyme desde el 1 de diciembre."') !== false, 'entrada: meta description = extracto');
ok(strpos($h, 'og:title') !== false && strpos($h, 'og:url') !== false && strpos($h, 'og:type" content="article"') !== false, 'entrada: metas og');
ok(cuenta($h, '/<h1[ >]/') === 1, 'entrada: un solo h1');
ok(strpos($h, '<time datetime="2026-11-20T09:00:00-03:00">20 de noviembre de 2026</time>') !== false && strpos($h, '<time datetime="2026-11-24T09:00:00-03:00">24 de noviembre de 2026</time>') !== false, 'entrada: fechas visibles con time/datetime');
ok(strpos($h, 'aria-label="Migas de pan"') !== false && strpos($h, 'aria-current="page">Ley 21.719') !== false, 'entrada: migas visibles');
ok(strpos($h, 'Luis Miguel González') !== false, 'entrada: autor y biografía');
ok(strpos($h, 'data-act="openModal"') !== false && strpos($h, 'Agenda tu diagnóstico gratis') !== false, 'entrada: CTA que abre la agenda');
ok(strpos($h, 'data-act-submit="submitDemo"') !== false && cuenta($h, '/id="at-modal-box"/') === 1, 'entrada: formulario de agenda (una vez)');
ok(strpos($h, 'at-home.js') !== false && strpos($h, 'at-agenda.js') !== false && strpos($h, 'window.AT_AGENDA') !== false && strpos($h, 'window.AT_HOME') !== false, 'entrada: at-home.js, at-agenda.js y su configuración');
ok(strpos($h, '<!--wp_head-->') !== false && strpos($h, '<!--wp_footer-->') !== false, 'entrada: llama wp_head y wp_footer (GA4 con consentimiento)');
ok(strpos($h, '{{') === false, 'entrada: sin marcadores sin reemplazar');
ok(strpos($h, 'class="at-guias-enlace"') !== false && strpos($h, '/guias/" data-act="closeMenu"') !== false, 'entrada: enlace Guías en el menú (escritorio y celular)');
ok(strpos($h, 'ph-book-open') !== false, 'entrada: ícono Phosphor en el menú');
preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $h, $mm);
$tipos = array_map(function ($j) { $d = json_decode($j, true); return $d['@type'] ?? '?'; }, $mm[1]);
ok($tipos === ['Article', 'BreadcrumbList'], 'entrada: JSON-LD Article + BreadcrumbList, ambos válidos (' . implode(',', $tipos) . ')');

// ---- Listado con una guía y vacío ----
$GLOBALS['i'] = -1;
$h = renderiza('archive-guia.php');
ok(cuenta($h, '/<title>/') === 1 && cuenta($h, '/rel="canonical"/') === 1 && strpos($h, 'rel="canonical" href="https://automatizatech.cl/guias/"') !== false, 'listado: un solo título y un solo canonical');
ok(strpos($h, 'Leer la guía') !== false && strpos($h, 'Qué cambia para tu pyme') !== false, 'listado: tarjeta con extracto y botón');
preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $h, $mm);
$tipos = array_map(function ($j) { $d = json_decode($j, true); return $d['@type'] ?? '?'; }, $mm[1]);
ok($tipos === ['BreadcrumbList', 'CollectionPage'], 'listado: JSON-LD BreadcrumbList + CollectionPage');
$GLOBALS['hay'] = []; $GLOBALS['i'] = -1;
$h = renderiza('archive-guia.php');
ok(strpos($h, 'Pronto publicaremos la primera guía') !== false && strpos($h, 'Leer la guía') === false, 'listado vacío: mensaje y sin tarjetas');
ok(strpos($h, 'data-act="openModal"') !== false, 'listado vacío: CTA a la agenda');

// ---- Enlace Guías en las 8 páginas con menú de AT ----
$paginas = ['template-home-premium.php' => 'home-premium'];
foreach (glob($tema . '/template-landing-*.php') as $t) { $paginas[basename($t)] = str_replace(['template-', '.php'], '', basename($t)); }
ok(count($paginas) === 8, 'páginas con menú de AT: ' . count($paginas));
foreach ($paginas as $plantilla => $carpeta) {
	$php = file_get_contents($tema . '/' . $plantilla);
	$body = file_get_contents($tema . '/assets/' . $carpeta . '/body.html');
	ok(strpos($body, '{{GUIAS_PIE}}') !== false && strpos($php, "'{{GUIAS_PIE}}'") !== false && strpos($body, '{{MENU_SERVICIOS}}') !== false && strpos($body, '{{MENU_SERVICIOS_MOVIL}}') !== false, "$plantilla: menú y pie con enlace Guías");
}
$sv = at_menu_servicios_html('') . at_menu_servicios_movil_html('') . at_guias_pie_html();
ok(substr_count($sv, '/guias/') === 3, 'menú escritorio, celular y pie apuntan a /guias/');

echo $fallas ? "\n$fallas FALLAS\n" : "\nTodo bien\n";
exit($fallas ? 1 : 0);
