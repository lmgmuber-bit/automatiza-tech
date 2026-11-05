<?php
// Intermediario de $wpdb que hace fallar las consultas a una tabla (lo usan las pruebas de fallos de wa-contexto).
if (!class_exists('WaWpdbQueFalla')) {
class WaWpdbQueFalla {
		public $last_error = '';
		private $real;
		private $tabla;
		public function __construct($real, string $tabla) { $this->real = $real; $this->tabla = $tabla; }
		public function __get($n) { return $this->real->$n; }
		public function __call($m, $a) {
			if (in_array($m, ['get_var', 'get_col', 'get_row', 'get_results', 'query'], true) && strpos((string) ($a[0] ?? ''), $this->real->prefix . $this->tabla) !== false) {
				$this->last_error = 'falla simulada';
				return null;
			}
			$this->real->last_error = '';
			$r = $this->real->$m(...$a);
			$this->last_error = (string) $this->real->last_error;
			return $r;
		}
	}
	function con_falla(string $tabla, callable $f) {
		$real = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = new WaWpdbQueFalla($real, $tabla);
		try { return $f(); } finally { $GLOBALS['wpdb'] = $real; }
	}
}
