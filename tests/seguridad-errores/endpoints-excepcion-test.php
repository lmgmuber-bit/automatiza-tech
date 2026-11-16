<?php
// php tests/seguridad-errores/endpoints-excepcion-test.php
// Camino de la EXCEPCION y del fallo de base: la respuesta no debe llevar el texto interno.
require __DIR__ . '/harness.php';

const SECRETO = 'SECRETO-INTERNO-ruta-y-sql';
$texto = file_get_contents(raiz() . '/at-texto.php');
$limite = '<?php function at_rate_limit_check(){return true;} function at_rate_limit_reject(){exit;} function at_rate_limit_client_ip(){return "127.0.0.1";}';
$hmac = '<?php function at_webhook_verify_hmac(){return true;}';

function sin_secreto(string $salida): bool { return strpos($salida, SECRETO) === false; }

// ---- api-chat-proxy.php: excepcion del controlador ----
echo "api-chat-proxy.php (excepcion)\n";
$r = ejecutar_endpoint('api-chat-proxy.php', [
    'wp-load.php' => '<?php function sanitize_text_field($s){return is_string($s)?$s:"";}',
    'at-cors.php' => '<?php function at_cors_apply($a){}',
    'at-rate-limit.php' => $limite,
    'openai-controller.php' => '<?php class OpenAIController { function getApiKey(){return "k";} function isTrackingReady(){return true;}
        function chatCompletion(){ throw new Exception("' . SECRETO . '"); } }',
], '{"messages":[{"role":"user","content":"hola"}]}');
$j = json_decode($r['salida'], true);
comprobar(is_array($j), 'responde JSON', substr($r['salida'], 0, 80));
comprobar(sin_secreto($r['salida']), 'no filtra el texto de la excepcion');
comprobar(($j['error'] ?? '') === 'internal_error', 'error generico estable');
comprobar(strpos((string) ($j['reply'] ?? ''), 'Error interno') === 0, 'reply amigable');

// Error de PHP (no Exception): TypeError / Error tampoco filtran
foreach (['TypeError' => 'throw new TypeError("' . SECRETO . '");', 'Error' => 'throw new Error("' . SECRETO . '");'] as $tipo => $lanza) {
    $r = ejecutar_endpoint('api-chat-proxy.php', [
        'wp-load.php' => '<?php function sanitize_text_field($s){return is_string($s)?$s:"";}',
        'at-cors.php' => '<?php function at_cors_apply($a){}',
        'at-rate-limit.php' => $limite,
        'openai-controller.php' => '<?php class OpenAIController { function getApiKey(){return "k";} function isTrackingReady(){return true;}
            function chatCompletion(){ ' . $lanza . ' } }',
    ], '{"messages":[{"role":"user","content":"hola"}]}');
    $j = json_decode($r['salida'], true);
    comprobar(is_array($j) && ($j['error'] ?? '') === 'internal_error' && sin_secreto($r['salida']), "$tipo: respuesta generica y sin detalle", substr($r['salida'], 0, 120));
}

// ---- api-save-proposal.php: fallo de base ----
echo "api-save-proposal.php (fallo de base)\n";
$wpload = static function (string $insertRetorna): string {
    return '<?php define("ABSPATH", __DIR__ . "/"); function sanitize_email($s){return $s;} function sanitize_textarea_field($s){return $s;}
      function wp_generate_password(){return "abc123abc123";} function current_time(){return "2026-01-01 00:00:00";} function get_site_url(){return "https://x.test";}
      class FakeDb { public $prefix="wp_"; public $insert_id=7; public $last_error="' . SECRETO . '";
        function insert($t,$d){ foreach($d as $k=>$v){ if(is_array($v)){ $d[$k]="Array"; } } echo "DATO:" . json_encode($d) . "\n"; return ' . $insertRetorna . '; } }
      $wpdb = new FakeDb();';
};
$dobles = ['at-rate-limit.php' => $limite, 'at-webhook-verify.php' => $hmac, 'at-texto.php' => $texto];
$r = ejecutar_endpoint('api-save-proposal.php', $dobles + ['wp-load.php' => $wpload('false')],
    '{"client_email":"a@b.cl","transcript":"t","gamma_prompt":"g","system_prompt":"s"}');
$ultima = trim(substr($r['salida'], (int) strrpos($r['salida'], "\n", -2)));
$j = json_decode($ultima, true);
comprobar(sin_secreto($r['salida']), 'no filtra last_error');
comprobar(($j['error'] ?? '') === 'internal_error' && !isset($j['db_error']), 'error generico, sin db_error', $ultima);

// Entradas que no son texto: no se guardan como "Array"
$r = ejecutar_endpoint('api-save-proposal.php', $dobles + ['wp-load.php' => $wpload('true')],
    '{"client_email":["a"],"transcript":{"x":1},"gamma_prompt":["p1","p2"],"system_prompt":{"k":"v"}}');
comprobar(strpos($r['salida'], 'DATO:') !== false, 'llego al insert', substr($r['salida'], 0, 100));
comprobar(strpos($r['salida'], 'Array') === false, 'ningun campo queda como "Array"', substr($r['salida'], 0, 200));
$r = ejecutar_endpoint('api-save-proposal.php', $dobles + ['wp-load.php' => $wpload('true')], '{}');
comprobar(strpos($r['salida'], 'No data received') !== false || strpos($r['salida'], 'DATO:') === false, 'cuerpo vacio no inserta');

// ---- api-save-presentation.php: fallo de base ----
echo "api-save-presentation.php (fallo de base)\n";
$r = ejecutar_endpoint('api-save-presentation.php', ['at-rate-limit.php' => $limite, 'at-webhook-verify.php' => $hmac,
    'wp-load.php' => '<?php function sanitize_text_field($s){return $s;} function esc_url_raw($s){return $s;}
      class FakeDb { public $prefix="wp_"; public $last_error="' . SECRETO . '";
        function prepare($q,...$a){return $q;} function get_var(){return 1;} function update(){return false;} }
      $wpdb = new FakeDb();'],
    '{"unique_id":"abc","view_url":"https://x.test/v"}');
$j = json_decode(trim($r['salida']), true);
comprobar(sin_secreto($r['salida']), 'no filtra last_error');
comprobar(($j['error'] ?? '') === 'internal_error' && !isset($j['db_error']), 'error generico, sin db_error', trim($r['salida']));

// ---- openai-controller.php: error de red (sin salir: proxy a un puerto cerrado) ----
echo "openai-controller.php (error de red)\n";
$d = carpeta_temporal();
file_put_contents($d . '/driver.php', '<?php
function current_time(){return "2026-01-01 00:00:00";}
class FakeDb { public $prefix="wp_"; public $last_error="";
  function suppress_errors($b){return false;} function get_var(){return 1;} function prepare($q,...$a){return $q;} function get_charset_collate(){return "";} function insert(){return 1;} }
$wpdb = new FakeDb();
require ' . var_export(raiz() . '/openai-controller.php', true) . ';
$c = new OpenAIController("sk-prueba-local");
echo json_encode($c->chatCompletion(1, [["role"=>"user","content"=>"hola"]], "gpt-4o"));');
$proxy = 'http://127.0.0.1:1';
$p = proc_open([PHP_BINARY, '-d', 'display_errors=0', $d . '/driver.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $d,
    array_merge(getenv() ?: [], ['https_proxy' => $proxy, 'HTTPS_PROXY' => $proxy, 'all_proxy' => $proxy, 'ALL_PROXY' => $proxy, 'no_proxy' => '', 'NO_PROXY' => '']));
$salida = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
borrar_carpeta($d);
$j = json_decode($salida, true);
comprobar(is_array($j) && isset($j['error']), 'devuelve error', substr($salida, 0, 120));
comprobar(!preg_match('/127\.0\.0\.1|cURL|connect|proxy/i', $salida), 'no filtra el detalle de curl', $salida);
comprobar(($j['error'] ?? '') === 'upstream_unavailable', 'error generico estable', $salida);

// ---- openai-controller.php: respuesta de error de OpenAI (revision del codigo) ----
echo "openai-controller.php (respuesta no 200)\n";
$src = file_get_contents(raiz() . '/openai-controller.php');
comprobar(strpos($src, "'details'") === false, 'ya no devuelve el cuerpo de error de OpenAI');
comprobar(strpos($src, "'cURL Error: '") === false, 'ya no devuelve curl_error');
cierre();
