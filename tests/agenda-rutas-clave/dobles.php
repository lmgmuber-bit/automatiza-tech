<?php
// Dobles mínimos de WordPress para las pruebas de la agenda. Todo es local: sin red ni base de datos.
require_once dirname(__DIR__) . '/seguridad-errores/harness.php';

if (!defined('ABSPATH')) { define('ABSPATH', raiz() . '/'); }
if (!defined('AT_REST_SECRET') && getenv('SIN_CLAVE') !== '1') { define('AT_REST_SECRET', 'clave-falsa-de-prueba-0123456789abcdef'); }

class WP_Error { public $code; public $message; public $data; function __construct($c = '', $m = '', $d = '') { $this->code = $c; $this->message = $m; $this->data = $d; } }
class WP_REST_Request implements ArrayAccess {
	public $cab; public $params; public $ruta; public $metodo;
	function __construct(string $metodo = 'GET', string $ruta = '/automatiza-tech/v1/followup-meetings/7', array $cab = [], array $params = []) {
		$this->metodo = $metodo; $this->ruta = $ruta; $this->cab = $cab; $this->params = $params;
	}
	function get_header($k) { return $this->cab[strtolower($k)] ?? null; }
	function get_route() { return $this->ruta; }
	function get_method() { return $this->metodo; }
	function get_param($k) { return $this->params[$k] ?? null; }
	function offsetGet($k): mixed { return $this->params[$k] ?? null; }
	function offsetExists($k): bool { return isset($this->params[$k]); }
	function offsetSet($k, $v): void { $this->params[$k] = $v; }
	function offsetUnset($k): void { unset($this->params[$k]); }
}
$GLOBALS['opciones'] = [];
function get_option($k, $d = false) { return $GLOBALS['opciones'][$k] ?? $d; }
function wp_salt($t = 'auth') { return 'sal-falsa-de-prueba-' . $t . '-0123456789abcdef0123456789'; }
function home_url($p = '') { return 'https://ejemplo.test' . $p; }

/** Extrae una función de un archivo por su nombre (termina en la primera llave de cierre en columna 0). */
function extraer_funcion(string $ruta, string $nombre): string {
	$src = file_get_contents($ruta);
	preg_match('/function ' . preg_quote($nombre, '/') . '\(.*?\r?\n\}\r?\n/s', $src, $m);
	if (empty($m[0])) { echo "No se encontro $nombre\n"; exit(2); }
	return $m[0];
}

$tema = raiz() . '/wp-content/themes/automatiza-tech/inc';
