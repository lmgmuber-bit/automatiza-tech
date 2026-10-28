<?php
/**
 * Pruebas del aviso de cookies (mu-plugin at-consentimiento.php), sin WordPress:
 * stubs mínimos de add_filter/add_action para probar la regla del Pixel y lo que se dibuja.
 * Uso: php tests/consentimiento/consentimiento-test.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['filtros'] = [];
$GLOBALS['acciones'] = [];
$GLOBALS['es_admin'] = false;
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['filtros'][$h][] = $cb; return true; }
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['acciones'][$h][] = $cb; return true; }
function apply_filters($h, $v) { foreach ($GLOBALS['filtros'][$h] ?? [] as $cb) { $v = $cb($v); } return $v; }
function __return_true() { return true; }
function is_admin() { return $GLOBALS['es_admin']; }
function wp_doing_ajax() { return false; }
function is_feed() { return false; }
function esc_url($u) { return $u; }
function home_url($p = '') { return 'https://automatizatech.cl' . $p; }

require __DIR__ . '/../../wp-content/mu-plugins/at-consentimiento.php';

$fallas = 0;
function ok($c, $m) { global $fallas; echo ($c ? 'ok   ' : 'FALLA ') . $m . "\n"; if (!$c) { $fallas++; } }
function dibujo() { ob_start(); foreach ($GLOBALS['acciones']['wp_footer'] ?? [] as $cb) { $cb(); } return ob_get_clean(); }

// --- Regla del Pixel: sin «Aceptar» no se activa ninguno
$_COOKIE = [];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'sin decisión, PixelYourSite queda desactivado');
$_COOKIE = ['at_consentimiento' => 'no'];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'con «Rechazar», sigue desactivado');
$_COOKIE = ['at_consentimiento' => 'si'];
ok(apply_filters('pys_disable_by_gdpr', false) === false, 'con «Aceptar», se activa');
$_COOKIE = ['at_consentimiento' => 'SI'];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'un valor distinto de «si» no cuenta como permiso');
$_COOKIE = ['at_consentimiento' => ['si']];
ok(apply_filters('pys_disable_by_gdpr', false) === true, 'una cookie con forma rara no cuenta como permiso');
$_COOKIE = ['at_consentimiento' => 'si'];
ok(apply_filters('pys_disable_by_gdpr', true) === true, 'si otro filtro ya lo desactivó, no lo reactivamos');
ok(apply_filters('pys_gdpr_ajax_enabled', false) === true, 'pide a PixelYourSite consultar el permiso por AJAX (la caché de página no lo congela)');

// --- Lo que se dibuja
$_COOKIE = [];
$h = dibujo();
ok(strpos($h, 'id="at-consent"') !== false && strpos($h, 'hidden') !== false, 'el aviso se dibuja oculto: lo muestra el JS según la cookie (la página puede venir de caché)');
ok(strpos($h, 'data-at-consent="si"') !== false && strpos($h, 'data-at-consent="no"') !== false, 'trae «Aceptar» y «Rechazar»');
ok(strpos($h, '/cookies/') !== false && strpos($h, '/privacidad/') !== false, 'enlaza la Política de Cookies y la de Privacidad');
ok(strpos($h, 'window.atLead') !== false && strpos($h, "fbq('track', 'Lead'") !== false, 'expone window.atLead, que envía «Lead» a Meta');
ok(strpos($h, "leer() !== 'si'") !== false, 'atLead no envía nada sin permiso');
ok(strpos($h, 'at_cookie_consent') !== false, 'respeta la decisión guardada por el aviso anterior (localStorage)');
ok(strpos($h, 'role="dialog"') !== false && strpos($h, 'aria-labelledby') !== false, 'accesible: rol de diálogo con título');
$GLOBALS['es_admin'] = true;
ok(dibujo() === '', 'en wp-admin no se dibuja');
$GLOBALS['es_admin'] = false;

echo $fallas ? "\n$fallas FALLAS\n" : "\nTODO OK\n";
exit($fallas ? 1 : 0);
