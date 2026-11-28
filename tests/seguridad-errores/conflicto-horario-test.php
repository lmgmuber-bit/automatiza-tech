<?php
// php tests/seguridad-errores/conflicto-horario-test.php
// POST followup-meetings con la hora ocupada: la respuesta no debe traer datos de otro cliente.
require __DIR__ . '/harness.php';
define('ABSPATH', raiz() . '/');
class WP_Error { public $code; public $message; public $data; function __construct($c = '', $m = '', $d = '') { $this->code = $c; $this->message = $m; $this->data = $d; } }
function is_email($e) { return strpos((string) $e, '@') !== false; }
function at_texto($v, $o = '') { return is_string($v) ? $v : $o; }
function automatiza_tech_check_slot_availability() {
    return ['available' => false, 'conflict_type' => 'demo', 'conflict_id' => 42, 'conflict_details' => 'DEMO con Persona Ajena (ajena@otro.test) - 01/01/2027 10:00 hrs'];
}
class Req { function __construct(private array $p) {} function get_param($k) { return $this->p[$k] ?? null; } }
class FakeDb { public $prefix = 'wp_'; }
$wpdb = new FakeDb();

$src = file_get_contents(raiz() . '/wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php');
preg_match('/function automatiza_tech_create_followup_meeting_api\(\$request\) \{.*?\r?\n\}\r?\n/s', $src, $m);
comprobar(!empty($m[0]), 'se extrajo la funcion');
eval($m[0]);

$logs = [];
ini_set('error_log', sys_get_temp_dir() . '/at-seg-conflicto.log');
@unlink(sys_get_temp_dir() . '/at-seg-conflicto.log');
$r = automatiza_tech_create_followup_meeting_api(new Req(['name' => 'Ana', 'email' => 'ana@x.test', 'date' => '2027-01-01', 'time' => '10:00']));
$todo = json_encode($r);
comprobar($r instanceof WP_Error && $r->code === 'slot_taken', 'responde slot_taken');
comprobar(strpos($todo, 'Persona Ajena') === false && strpos($todo, 'ajena@otro.test') === false, 'sin nombre ni correo de otro cliente', $todo);
comprobar(!isset($r->data['conflict_details']), 'sin conflict_details');
$reg = (string) @file_get_contents(sys_get_temp_dir() . '/at-seg-conflicto.log');
comprobar(strpos($reg, 'tipo=demo id=42') !== false, 'el error_log anota solo el tipo y el id del conflicto');
comprobar(strpos($reg, 'Persona Ajena') === false && strpos($reg, 'ajena@otro.test') === false, 'el error_log no lleva nombre ni correo de otra persona', $reg);
@unlink(sys_get_temp_dir() . '/at-seg-conflicto.log');
cierre();
