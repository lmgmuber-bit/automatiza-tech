<?php
// WordPress mínimo en memoria para probar OmniMetaAdsController sin base de datos.
// Solo las funciones que usa el controlador; las opciones viven en un arreglo y el HTTP
// pasa por el filtro pre_http_request, igual que en WordPress. Nunca se sube a producción.
if (PHP_SAPI !== 'cli') { exit; }
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['stub_options'] = [];
$GLOBALS['stub_filters'] = [];

class WP_Error {
	private $msg;
	public function __construct($code = '', $msg = '') { $this->msg = $msg; }
	public function get_error_message() { return $this->msg; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['stub_options']) ? $GLOBALS['stub_options'][$k] : $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['stub_options'][$k] = $v; return true; }
function add_option($k, $v = '', $dep = '', $autoload = null) {
	if (array_key_exists($k, $GLOBALS['stub_options'])) { return false; }
	$GLOBALS['stub_options'][$k] = $v;
	return true;
}
function delete_option($k) { unset($GLOBALS['stub_options'][$k]); return true; }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function esc_url_raw($u) { return preg_match('#^https?://#i', (string) $u) ? (string) $u : ''; }
function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
function add_filter($tag, $cb, $prio = 10, $args = 1) { $GLOBALS['stub_filters'][$tag][] = $cb; }
function wp_remote_get($url, $args = []) {
	$args += ['method' => 'GET'];
	$pre = false;
	foreach ($GLOBALS['stub_filters']['pre_http_request'] ?? [] as $cb) { $pre = $cb($pre, $args, $url); }
	return $pre === false ? new WP_Error('sin_red', 'HTTP externo bloqueado en pruebas.') : $pre;
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }

$GLOBALS['fallas'] = 0;
function ok($cond, $msg) { if ($cond) { echo "ok   $msg\n"; } else { $GLOBALS['fallas']++; echo "FALLA $msg\n"; } }
function fin() { echo $GLOBALS['fallas'] ? "\n{$GLOBALS['fallas']} FALLAS\n" : "\nTODO OK\n"; exit($GLOBALS['fallas'] ? 1 : 0); }
