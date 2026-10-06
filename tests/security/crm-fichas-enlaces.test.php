<?php
/**
 * Pruebas de los enlaces de las fichas públicas del CRM (cliente y prospecto).
 *
 * Sin WordPress ni base de datos: carga los archivos reales (crm-ai-completo.php y los dos del
 * tema que arman enlaces) con reemplazos mínimos de las funciones de WP que usan. Las vistas y
 * los AJAX terminan en exit, así que cada caso corre en un subproceso.
 *
 * Correr: C:\wamp64\bin\php\php8.3.28\php.exe tests/security/crm-fichas-enlaces.test.php
 */

$RAIZ = dirname(__DIR__, 2);
define('ABSPATH', $RAIZ . '/');
define('OBJECT', 'OBJECT');
define('ARRAY_A', 'ARRAY_A');

// Claves solo para esta prueba; nunca las de PROD.
const SECRETOS = array(
    'A'     => 'clave-de-prueba-A-0123456789abcdef-no-es-real',
    'B'     => 'clave-de-prueba-B-0123456789abcdef-no-es-real',
    'corto' => 'corta',
);

// Lo que "hay en la base" en todos los casos (mismas columnas que las tablas reales).
const CLIENTE = array(
    'id' => 2, 'tipo' => 'cliente', 'estado' => 'activo', 'nombre' => 'Ana Prueba',
    'email' => 'ana@example.test', 'telefono' => '+56911111111', 'empresa' => 'Empresa Prueba',
    'rubro' => 'servicios', 'origen' => 'web', 'ai_identifier' => 'ana-prueba', 'notas' => '',
    'logo_url' => '', 'manual_url' => '', 'color_principal' => '#667eea', 'color_secundario_1' => '',
    'color_secundario_2' => '', 'tipografia' => '', 'drive_folder_id' => '',
    'fecha_contacto' => '2026-08-01 10:00:00', 'fecha_demo' => '2026-08-05 10:00:00',
    'fecha_contrato' => '2026-08-10 10:00:00', 'created_at' => '2026-08-01 10:00:00',
    'updated_at' => '2026-08-10 10:00:00',
);
const PROPUESTA = array(
    'id' => 15, 'client_name' => 'Pedro Prospecto', 'client_email' => 'pedro@example.test',
    'company_name' => 'Prospecto SpA', 'status' => 'sent', 'gamma_iframe_url' => '', 'pdf_url' => '',
    'created_at' => '2026-09-01 10:00:00', 'updated_at' => '2026-09-01 10:00:00',
);
const REUNION = array(
    'id' => 7, 'client_name' => 'Ana Prueba', 'client_email' => 'ana@example.test',
    'invitees_emails' => '', 'invitees_names' => '', 'company_name' => 'Empresa Prueba',
    'phone' => '+56911111111', 'meeting_date' => '2026-10-01', 'meeting_time' => '10:00:00',
    'meet_link' => '', 'meeting_subject' => 'Reunión de Seguimiento', 'notes' => '',
    'status' => 'scheduled', 'confirmed_at' => null, 'email_sent' => 0, 'whatsapp_sent' => 0,
    'created_at' => '2026-09-20 10:00:00', 'updated_at' => '2026-09-20 10:00:00', 'google_event_id' => '',
);

// ---------------------------------------------------------------- reemplazos de WordPress
$GLOBALS['hooks'] = array();
$GLOBALS['es_admin'] = false;

function add_action($hook, $cb, $prioridad = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = $cb; }
function add_filter($hook, $cb, $prioridad = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = $cb; }
function do_action($hook, ...$args) { echo '[[ACTION ' . $hook . ']]'; }
function is_admin() { return false; }
function current_user_can($cap) { return $GLOBALS['es_admin']; }
// Devuelve otro host a propósito: los enlaces con token no deben salir de home_url().
function home_url($p = '') { return 'https://host-de-la-peticion.test' . $p; }
function admin_url($p = '') { return 'https://host-de-la-peticion.test/wp-admin/' . $p; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_textarea($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_url($s) { return (string) $s; }
function esc_js($s) { return addslashes((string) $s); }
// Como el real: un arreglo u objeto se vuelve cadena vacía.
function sanitize_text_field($s) { return (is_array($s) || is_object($s)) ? '' : trim(strip_tags((string) $s)); }
function sanitize_textarea_field($s) { return (is_array($s) || is_object($s)) ? '' : trim((string) $s); }
function sanitize_email($s) { return trim((string) $s); }
function is_email($s) { return (bool) filter_var($s, FILTER_VALIDATE_EMAIL); }
function current_time($t) { return '2026-09-24 12:00:00'; }
function get_option($k, $def = false) { return $k === 'admin_email' ? 'admin@example.test' : $def; }
function get_bloginfo($k = '') { return 'AutomatizaTech'; }
function nocache_headers() { echo '[[NOCACHE]]'; }
function wp_die($msg = '', $titulo = '', $args = array()) {
    $codigo = is_array($args) ? ($args['response'] ?? 500) : 500;
    echo '[[WP_DIE ' . $codigo . ']]' . (is_string($msg) ? $msg : '');
    exit;
}
function wp_send_json_success($d = null, $s = null) { echo '[[JSON_OK]]' . json_encode($d); exit; }
function wp_send_json_error($d = null, $s = null) { echo '[[JSON_ERROR]]' . json_encode($d); exit; }
// Imprime cada correo entre marcas y falla solo para la dirección indicada en $GLOBALS['mail_falla_a'].
function wp_mail($para, $asunto, $cuerpo, $cab = array(), $adj = array()) {
    echo '[[WP_MAIL ' . $para . ']][[SUBJECT ' . $asunto . ']][[HEADERS ' . implode(' | ', (array) $cab) . ']]' . $cuerpo . '[[/WP_MAIL]]';
    return ($GLOBALS['mail_falla_a'] ?? '') !== $para;
}
// Transients de mentira, guardados en un archivo para que dos subprocesos compartan el estado.
function get_transient($k) {
    $ruta = $GLOBALS['transients_ruta'] ?? '';
    $datos = ($ruta !== '' && is_file($ruta)) ? (json_decode((string) file_get_contents($ruta), true) ?: array()) : array();
    return array_key_exists($k, $datos) ? $datos[$k] : false;
}
function set_transient($k, $v, $ttl = 0) {
    echo '[[SET_TRANSIENT ' . $k . ' ' . $ttl . ']]';
    $ruta = $GLOBALS['transients_ruta'] ?? '';
    if ($ruta === '') { return false; }
    $datos = is_file($ruta) ? (json_decode((string) file_get_contents($ruta), true) ?: array()) : array();
    $datos[$k] = $v;
    return (bool) file_put_contents($ruta, json_encode($datos));
}
function wp_remote_post($url, $args = array()) {
    echo '[[POST]]' . ($args['body'] ?? '') . '[[/POST]]';
    return array('response' => array('code' => 200), 'body' => '{"success":true}');
}
function is_wp_error($x) { return false; }
function wp_remote_retrieve_response_code($r) { return 200; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }

class WP_List_Table {}

/** $wpdb de mentira: entrega la fila de la tabla consultada si calza el id o el correo. */
class WpdbFalso {
    public $prefix = 'wp_';
    public $users = 'wp_users';
    public $filas = array();
    public $insert_termina = false;

    function prepare($q, ...$a) {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        return preg_replace_callback('/%([dsf])/', function ($m) use (&$a) {
            $v = array_shift($a);
            return $m[1] === 's' ? "'" . addslashes((string) $v) . "'" : (string) (0 + $v);
        }, $q);
    }
    private function fila_para($q) {
        foreach ($this->filas as $tabla => $fila) {
            if (!preg_match('/\b' . preg_quote($tabla, '/') . '\b/', $q)) { continue; }
            if (preg_match('/\bid = (\d+)/', $q, $m) && (int) $m[1] !== (int) $fila['id']) { return null; }
            $correo = $fila['email'] ?? $fila['client_email'] ?? '';
            if (preg_match("/\b(?:client_)?email = '([^']*)'/", $q, $m) && $m[1] !== $correo) { return null; }
            return $fila;
        }
        return null;
    }
    function get_row($q, $salida = 'OBJECT', $y = 0) {
        $f = $this->fila_para($q);
        if ($f === null) { return null; }
        return $salida === 'ARRAY_A' ? $f : (object) $f;
    }
    function get_var($q, $x = 0, $y = 0) {
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $q, $m)) {
            if ($m[1] !== 'wp_crm_chat_historial') { return null; }
            echo '[[DB esquema chat]]';
            return $m[1];
        }
        return null;
    }
    function get_results($q, $salida = 'OBJECT') {
        return strpos($q, 'SHOW COLUMNS') !== false ? array((object) array('Field' => 'x')) : array();
    }
    function get_charset_collate() { return ''; }
    function insert($t, $datos, $f = null) {
        echo '[[DB insert ' . $t . ']]';
        if ($this->insert_termina) { exit; }
        return 1;
    }
    function update($t, $datos, $donde, $f = null, $wf = null) { echo '[[DB update ' . $t . ']]'; return 1; }
    function query($q) { return true; }
}
$GLOBALS['wpdb'] = new WpdbFalso();
$GLOBALS['wpdb']->filas = array(
    'wp_crm_clientes' => CLIENTE,
    'wp_automatiza_propuestas' => PROPUESTA,
    'wp_automatiza_followup_meetings' => REUNION,
);

require $RAIZ . '/wp-content/mu-plugins/crm-ai-completo.php';
require $RAIZ . '/wp-content/themes/automatiza-tech/inc/admin-qa-module.php';
require $RAIZ . '/wp-content/themes/automatiza-tech/inc/admin-followup-meetings.php';

/** El token que cualquiera puede calcular con la sal que está en el repo público. */
function token_viejo_cliente($id, $email) { return md5($id . 'AUTOMATIZA_CRM_V2' . $email); }
function token_viejo_prospecto($id, $email) { return md5($id . 'AUTOMATIZA_PROSPECT_V1' . $email); }

function correr_hooks($hook) {
    foreach ($GLOBALS['hooks'][$hook] ?? array() as $cb) { call_user_func($cb); }
}

// ---------------------------------------------------------------- casos en subproceso
if (($argv[1] ?? '') === 'caso') {
    ini_set('error_log', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-fichas-enlaces.test.log');
    $secreto = $argv[3];
    if (isset(SECRETOS[$secreto])) { define('AT_CRM_FICHA_SECRET', SECRETOS[$secreto]); }
    $p = json_decode(base64_decode($argv[4] ?? ''), true) ?: array();
    $GLOBALS['transients_ruta'] = $p['transients'] ?? '';
    $GLOBALS['mail_falla_a'] = $p['mail_falla_a'] ?? '';
    if (($p['metodo'] ?? 'GET') === 'POST') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('at_crm_reenviar' => '1');
    }
    if (empty($p['sin_funcion_avisos'])) {
        function at_cc_correo_avisos() { return 'avisos@example.test'; }
    }
    $wpdb = $GLOBALS['wpdb'];
    if (isset($p['email_en_base'])) { $wpdb->filas['wp_crm_clientes']['email'] = $p['email_en_base']; }
    if (isset($p['correo_reunion'])) { $wpdb->filas['wp_automatiza_followup_meetings']['client_email'] = $p['correo_reunion']; }

    switch ($argv[2]) {
        case 'url-cliente':
            $u = AutomatizaTech_CRM_AI::url_ficha_cliente($p['id'], $p['email']);
            echo $u === '' ? '[[VACIO]]' : $u;
            break;
        case 'url-prospecto':
            $u = AutomatizaTech_CRM_AI::get_prospect_timeline_url($p['pid']);
            echo $u === '' ? '[[VACIO]]' : $u;
            break;
        case 'vista-cliente':
            $_GET = array('crm_view' => 'timeline') + $p['get'];
            correr_hooks('template_redirect');
            echo '[[SIN_RESPUESTA]]';
            break;
        case 'vista-prospecto':
            $_GET = array('crm_view' => 'prospect_timeline') + $p['get'];
            correr_hooks('template_redirect');
            echo '[[SIN_RESPUESTA]]';
            break;
        case 'ajax-historial':
            $_POST = $p['post'];
            correr_hooks('wp_ajax_nopriv_crm_chat_history');
            break;
        case 'ajax-chat':
            $_POST = $p['post'] + array('mensaje' => 'Hola');
            $wpdb->insert_termina = true; // corta antes de la llamada a OpenAI
            correr_hooks('wp_ajax_nopriv_crm_chat_cliente');
            break;
        case 'followup-whatsapp':
            automatiza_tech_send_followup_whatsapp(REUNION['id']);
            break;
        case 'followup-reagendar':
            automatiza_tech_call_followup_reschedule_workflow((object) $wpdb->filas['wp_automatiza_followup_meetings'], '2026-10-02', '11:00');
            break;
        case 'followup-correo':
            automatiza_tech_send_followup_email(REUNION['id']);
            break;
        case 'qa-url':
            echo at_qa_url_ficha_cliente((object) CLIENTE);
            break;
        case 'aviso':
            $GLOBALS['es_admin'] = true;
            correr_hooks('admin_notices');
            break;
    }
    exit;
}

// ---------------------------------------------------------------- pruebas
$fallas = 0;
$total = 0;
function comprobar($nombre, $ok) {
    global $fallas, $total;
    $total++;
    if (!$ok) { $fallas++; }
    echo ($ok ? '  ok   ' : '  FALLA ') . $nombre . PHP_EOL;
}
function caso($nombre, $secreto = 'A', $params = array()) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' caso ' . escapeshellarg($nombre)
         . ' ' . escapeshellarg($secreto) . ' ' . escapeshellarg(base64_encode(json_encode($params)));
    return (string) shell_exec($cmd);
}
function query_de($url) {
    parse_str((string) parse_url(html_entity_decode($url), PHP_URL_QUERY), $q);
    return $q;
}
function vista_cliente_desde($url, $secreto = 'A', $extra = array()) {
    $q = query_de($url);
    return caso('vista-cliente', $secreto, array('get' => array('cid' => $q['cid'] ?? '', 'token' => $q['token'] ?? '')) + $extra);
}
function vista_prospecto_desde($url, $secreto = 'A') {
    $q = query_de($url);
    return caso('vista-prospecto', $secreto, array('get' => array('pid' => $q['pid'] ?? '', 'token' => $q['token'] ?? '')));
}
function abre_ficha_cliente($s) { return strpos($s, 'Ana Prueba') !== false && strpos($s, '[[WP_DIE') === false; }
function abre_ficha_prospecto($s) { return strpos($s, 'Pedro Prospecto') !== false && strpos($s, '[[WP_DIE') === false; }
function sin_error_fatal($s) { return stripos($s, 'Fatal error') === false && stripos($s, 'Uncaught') === false; }
function denegado($s) { return strpos($s, '[[WP_DIE 403]]') !== false && sin_error_fatal($s); }
function sin_cache($s) { return strpos($s, '[[ACTION litespeed_control_set_nocache]]') !== false && strpos($s, '[[NOCACHE]]') !== false; }
function payload_de($s) {
    return preg_match('/\[\[POST\]\](.*?)\[\[\/POST\]\]/s', $s, $m) ? (json_decode($m[1], true) ?: array()) : array();
}

$CANONICO = 'https://automatizatech.cl/';

echo "1) Generación de enlaces" . PHP_EOL;
comprobar('sin la constante no se genera enlace (falla cerrado)',
    trim(caso('url-cliente', 'ninguno', array('id' => 2, 'email' => 'ana@example.test'))) === '[[VACIO]]');
comprobar('con una clave corta no se genera enlace',
    trim(caso('url-cliente', 'corto', array('id' => 2, 'email' => 'ana@example.test'))) === '[[VACIO]]');
$url_ana = trim(caso('url-cliente', 'A', array('id' => 2, 'email' => 'ana@example.test')));
comprobar('el enlace usa el dominio canónico aunque el Host de la petición sea otro',
    strpos($url_ana, $CANONICO . '?crm_view=timeline&cid=2&token=') === 0);
comprobar('el token es de 64 caracteres hexadecimales', (bool) preg_match('/&token=[0-9a-f]{64}$/', $url_ana));
$url_pedro = trim(caso('url-prospecto', 'A', array('pid' => 15)));
comprobar('el enlace del prospecto usa pid y el dominio canónico',
    (bool) preg_match('#^' . preg_quote($CANONICO, '#') . '\?crm_view=prospect_timeline&pid=15&token=[0-9a-f]{64}$#', $url_pedro));

echo PHP_EOL . "2) Ficha de cliente" . PHP_EOL;
$s = vista_cliente_desde($url_ana);
comprobar('el enlace nuevo abre la ficha', abre_ficha_cliente($s));
comprobar('la ficha no queda en caché (LiteSpeed y navegador)', sin_cache($s));
comprobar('la ficha pide no ser indexada', strpos($s, '<meta name="robots" content="noindex') !== false);
$s_anterior = caso('vista-cliente', 'A', array('get' => array('cid' => 2, 'token' => token_viejo_cliente(2, 'ana@example.test'))));
comprobar('el enlace anterior (md5) no abre la ficha', !abre_ficha_cliente($s_anterior));
comprobar('quien abre un enlace anterior no ve datos del cliente',
    strpos($s_anterior, 'Ana Prueba') === false && strpos($s_anterior, 'ana@example.test') === false);
// La respuesta genérica de todo enlace que no se reconoce.
$s_viejo = caso('vista-cliente', 'A', array('get' => array('cid' => 2, 'token' => 'no-es-un-token')));
comprobar('un token que no es de ningún tipo da 403', denegado($s_viejo));
comprobar('quien abre un enlace inválido no ve datos del cliente',
    strpos($s_viejo, 'Ana Prueba') === false && strpos($s_viejo, 'ana@example.test') === false);
comprobar('quien abre un enlace inválido ve cómo pedir uno nuevo', strpos($s_viejo, 'wa.me/56927002984') !== false);
comprobar('la denegación tampoco queda en caché', sin_cache($s_viejo));
$s_inexistente = vista_cliente_desde(str_replace('cid=2', 'cid=99', $url_ana));
comprobar('un cliente inexistente recibe exactamente la misma respuesta', $s_inexistente === $s_viejo);
comprobar('un enlace sin token recibe la misma respuesta', caso('vista-cliente', 'A', array('get' => array('cid' => 2))) === $s_viejo);
comprobar('un token hecho con otra clave no abre', denegado(vista_cliente_desde(
    trim(caso('url-cliente', 'B', array('id' => 2, 'email' => 'ana@example.test'))))));
comprobar('el token de otro cliente no abre esta ficha', denegado(vista_cliente_desde(str_replace('cid=3', 'cid=2',
    trim(caso('url-cliente', 'A', array('id' => 3, 'email' => 'ana@example.test')))))));
comprobar('cambiar el correo del cliente invalida su enlace',
    denegado(vista_cliente_desde($url_ana, 'A', array('email_en_base' => 'ana.nueva@example.test'))));
comprobar('el correo se compara sin mayúsculas ni espacios', abre_ficha_cliente(vista_cliente_desde(
    trim(caso('url-cliente', 'A', array('id' => 2, 'email' => ' Ana@Example.TEST '))))));
$s = caso('vista-cliente', 'A', array('get' => array('cid' => 2, 'token' => array('x'))));
comprobar('un token con forma de arreglo da 403 sin error fatal', denegado($s));
comprobar('sin la constante ni un enlace válido abre (falla cerrado)', denegado(vista_cliente_desde($url_ana, 'ninguno')));
comprobar('un token de cliente no abre la ficha de prospecto con el mismo id', denegado(vista_prospecto_desde(
    str_replace(array('crm_view=timeline', 'cid='), array('crm_view=prospect_timeline', 'pid='),
        trim(caso('url-cliente', 'A', array('id' => 15, 'email' => 'pedro@example.test')))))));

echo PHP_EOL . "3) Ficha de prospecto" . PHP_EOL;
$s = vista_prospecto_desde($url_pedro);
comprobar('el enlace nuevo abre la ficha del prospecto', abre_ficha_prospecto($s));
comprobar('la ficha del prospecto no queda en caché', sin_cache($s));
$s = caso('vista-prospecto', 'A', array('get' => array('pid' => 15, 'token' => token_viejo_prospecto(15, 'pedro@example.test'))));
comprobar('el enlace anterior del prospecto no abre la ficha', !abre_ficha_prospecto($s));
comprobar('quien abre el enlace anterior del prospecto no ve sus datos',
    strpos($s, 'Pedro Prospecto') === false && strpos($s, 'pedro@example.test') === false);

echo PHP_EOL . "4) Chat de la ficha (AJAX público)" . PHP_EOL;
$token_ana = query_de($url_ana)['token'];
// Los dos AJAX envuelven todo en try/catch: un error también sale como JSON_ERROR, por eso se exige el texto.
function acceso_denegado($s) { return strpos($s, '[[JSON_ERROR]]"Acceso denegado') !== false; }
$s = caso('ajax-historial', 'A', array('post' => array('cid' => 2, 'token' => token_viejo_cliente(2, 'ana@example.test'))));
comprobar('historial con el token viejo: acceso denegado', acceso_denegado($s));
comprobar('historial con el token viejo no toca el esquema del chat', strpos($s, '[[DB esquema chat]]') === false);
comprobar('historial con el token nuevo responde',
    strpos(caso('ajax-historial', 'A', array('post' => array('cid' => 2, 'token' => $token_ana))), '[[JSON_OK]]') !== false);
$s = caso('ajax-chat', 'A', array('post' => array('cid' => 2, 'token' => token_viejo_cliente(2, 'ana@example.test'))));
comprobar('chat con el token viejo: denegado y sin guardar nada',
    acceso_denegado($s) && strpos($s, '[[DB insert') === false);
comprobar('chat con el token nuevo llega a guardar el mensaje',
    strpos(caso('ajax-chat', 'A', array('post' => array('cid' => 2, 'token' => $token_ana))), '[[DB insert wp_crm_chat_historial]]') !== false);

echo PHP_EOL . "5) Enlaces que arma el tema" . PHP_EOL;
$pl = payload_de(caso('followup-whatsapp'));
comprobar('WhatsApp de seguimiento: ficha_url en el dominio canónico', strpos($pl['ficha_url'] ?? '', $CANONICO) === 0);
comprobar('WhatsApp de seguimiento: ficha_url abre la ficha', abre_ficha_cliente(vista_cliente_desde($pl['ficha_url'] ?? '')));
$pl = payload_de(caso('followup-whatsapp', 'A', array('correo_reunion' => 'pedro@example.test')));
comprobar('WhatsApp de seguimiento a prospecto: el enlace abre su ficha',
    abre_ficha_prospecto(vista_prospecto_desde($pl['prospect_timeline_url'] ?? '')));
$pl = payload_de(caso('followup-reagendar'));
comprobar('reagendamiento: ficha_url abre la ficha', abre_ficha_cliente(vista_cliente_desde($pl['ficha_url'] ?? '')));
$s = caso('followup-correo');
preg_match('/href="([^"]*crm_view=timeline[^"]*)"/', $s, $m);
comprobar('correo de seguimiento: el enlace del portal abre la ficha', abre_ficha_cliente(vista_cliente_desde($m[1] ?? '')));
comprobar('módulo QA: el enlace de ficha abre la ficha', abre_ficha_cliente(vista_cliente_desde(trim(caso('qa-url')))));

echo PHP_EOL . "6) Aviso al administrador" . PHP_EOL;
comprobar('sin la constante, el admin ve el aviso', strpos(caso('aviso', 'ninguno'), 'AT_CRM_FICHA_SECRET') !== false);
comprobar('con la constante, no hay aviso', strpos(caso('aviso', 'A'), 'AT_CRM_FICHA_SECRET') === false);

echo PHP_EOL . "7) Enlaces anteriores al cambio de firma: reenvío del enlace nuevo" . PHP_EOL;
function correos_de($s) {
    preg_match_all('/\[\[WP_MAIL ([^\]]*)\]\]\[\[SUBJECT (.*?)\]\]\[\[HEADERS (.*?)\]\](.*?)\[\[\/WP_MAIL\]\]/s', $s, $m, PREG_SET_ORDER);
    $out = array();
    foreach ($m as $c) { $out[] = array('para' => $c[1], 'asunto' => $c[2], 'cab' => $c[3], 'cuerpo' => $c[4]); }
    return $out;
}
function sin_correos($s) { return preg_replace('/\[\[WP_MAIL.*?\[\[\/WP_MAIL\]\]/s', '', $s); }
function hay_boton($s) { return strpos($s, 'Enviarme el enlace nuevo') !== false; }
function sin_enlace_nuevo($s) { return !preg_match('/token=[0-9a-f]{64}/', $s); }
function ruta_transients() { return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-fichas-transients-' . uniqid('', true) . '.json'; }
function vista_anterior($tipo, $token, $metodo = 'GET', $extra = array(), $secreto = 'A') {
    $get = $tipo === 'cliente' ? array('cid' => 2, 'token' => $token) : array('pid' => 15, 'token' => $token);
    return caso($tipo === 'cliente' ? 'vista-cliente' : 'vista-prospecto', $secreto, array('get' => $get, 'metodo' => $metodo) + $extra);
}
$viejo_c = token_viejo_cliente(2, 'ana@example.test');
$viejo_p = token_viejo_prospecto(15, 'pedro@example.test');

// Cliente: GET
$s = vista_anterior('cliente', $viejo_c);
comprobar('cliente, enlace anterior (GET): ofrece «Enviarme el enlace nuevo»', hay_boton($s) && strpos($s, '[[WP_DIE 200]]') !== false);
comprobar('cliente, GET: explica por qué', strpos($s, 'Actualizamos los enlaces de acceso a las fichas por seguridad') !== false);
comprobar('cliente, GET: formulario POST con at_crm_reenviar y el mismo enlace',
    strpos($s, 'method="post"') !== false && strpos($s, 'name="at_crm_reenviar" value="1"') !== false
    && strpos($s, $CANONICO . '?crm_view=timeline&cid=2&token=' . $viejo_c) !== false);
comprobar('cliente, GET: no muestra el correo, ni un enlace firmado, ni la ficha',
    strpos($s, 'ana@') === false && strpos($s, 'Ana Prueba') === false && sin_enlace_nuevo($s));
comprobar('cliente, GET: no envía correo ni marca límite', count(correos_de($s)) === 0 && strpos($s, '[[SET_TRANSIENT') === false);
comprobar('cliente, GET: no queda en caché', sin_cache($s));

// Cliente: POST
$tr = ruta_transients();
$s = vista_anterior('cliente', $viejo_c, 'POST', array('transients' => $tr));
$cs = correos_de($s);
comprobar('cliente, POST: salen exactamente dos correos', count($cs) === 2);
$vacio = array('para' => '', 'asunto' => '', 'cab' => '', 'cuerpo' => '');
$al_cliente = $cs[0] ?? $vacio;
$a_luis = $cs[1] ?? $vacio;
comprobar('cliente, POST: el primero va al correo registrado', $al_cliente['para'] === 'ana@example.test');
comprobar('cliente, POST: asunto del correo al cliente', $al_cliente['asunto'] === 'Tu nuevo enlace de acceso — AutomatizaTech');
comprobar('cliente, POST: el correo es HTML y sale de AutomatizaTech',
    strpos($al_cliente['cab'], 'Content-Type: text/html; charset=UTF-8') !== false
    && strpos($al_cliente['cab'], 'From: AutomatizaTech <contacto@automatizatech.cl>') !== false);
comprobar('cliente, POST: el correo lleva diseño AT, saludo, botón, nota y pie',
    strpos($al_cliente['cuerpo'], 'linear-gradient(135deg,#1e3a8a,#06d6a0);background-color:#1e3a8a') !== false
    && strpos($al_cliente['cuerpo'], 'logo-automatiza-tech.png') !== false
    && strpos($al_cliente['cuerpo'], 'Hola Ana') !== false
    && strpos($al_cliente['cuerpo'], 'Abrir mi ficha') !== false
    && strpos($al_cliente['cuerpo'], 'Si no pediste este enlace, ignora este correo.') !== false
    && strpos($al_cliente['cuerpo'], '© AutomatizaTech · automatizatech.cl') !== false);
preg_match('/href="([^"]*crm_view=timeline[^"]*)"/', $al_cliente['cuerpo'], $mh);
$enlace_nuevo = $mh[1] ?? '';
comprobar('cliente, POST: el enlace del correo es firmado y abre la ficha',
    (bool) preg_match('/&token=[0-9a-f]{64}$/', html_entity_decode($enlace_nuevo)) && abre_ficha_cliente(vista_cliente_desde($enlace_nuevo)));
comprobar('cliente, POST: el segundo correo va al buzón de avisos y nombra al cliente',
    $a_luis['para'] === 'avisos@example.test' && strpos($a_luis['asunto'], 'Ana Prueba pidió su enlace nuevo a la ficha') !== false
    && strpos($a_luis['cuerpo'], 'Ana Prueba') !== false);
comprobar('cliente, POST: el aviso no lleva enlace ni token', strpos($a_luis['cuerpo'], 'crm_view') === false
    && strpos($a_luis['cuerpo'], 'token') === false && !preg_match('/[0-9a-f]{32}/', $a_luis['cuerpo']));
comprobar('cliente, POST: dice «Listo» y la página no muestra ni el correo ni el enlace',
    strpos($s, 'Listo. Te enviamos el enlace nuevo a tu correo registrado. Revisa también la carpeta de spam.') !== false
    && strpos(sin_correos($s), 'ana@') === false && sin_enlace_nuevo(sin_correos($s)));
comprobar('cliente, POST: marca el límite de 600 s', strpos($s, '[[SET_TRANSIENT at_crm_reenvio_cliente_2 600]]') !== false);
comprobar('cliente, POST: no queda en caché', sin_cache($s));

// Segundo POST dentro de los 600 s
$s2 = vista_anterior('cliente', $viejo_c, 'POST', array('transients' => $tr));
comprobar('cliente, segundo POST: no envía correo', count(correos_de($s2)) === 0);
comprobar('cliente, segundo POST: dice «Ya te enviamos»',
    strpos($s2, 'Ya te enviamos el enlace nuevo hace unos minutos. Revisa tu correo, también la carpeta de spam.') !== false);
@unlink($tr);

// El aviso usa el correo del sitio si no existe at_cc_correo_avisos()
$tr = ruta_transients();
$cs = correos_de(vista_anterior('cliente', $viejo_c, 'POST', array('transients' => $tr, 'sin_funcion_avisos' => true)));
comprobar('sin at_cc_correo_avisos() el aviso va al correo del administrador', ($cs[1]['para'] ?? '') === 'admin@example.test');
@unlink($tr);

// Falla el envío al cliente
$tr = ruta_transients();
$s = vista_anterior('cliente', $viejo_c, 'POST', array('transients' => $tr, 'mail_falla_a' => 'ana@example.test'));
comprobar('cliente, correo que falla: igual marca el límite (sin avisos repetidos)', strpos($s, '[[SET_TRANSIENT') !== false);
comprobar('cliente, correo que falla: el aviso a Luis dice que no se pudo enviar', strpos($s, 'No se pudo enviar') !== false && strpos($s, 'Se envió al correo registrado') === false);
comprobar('cliente, correo que falla: dice «No pudimos enviar» con el WhatsApp',
    strpos($s, 'No pudimos enviar el correo') !== false && strpos($s, 'wa.me/56927002984') !== false && strpos($s, 'Listo.') === false);
@unlink($tr);

// Tokens que no son el anterior de este cliente
$s = vista_anterior('cliente', md5('cualquier-cosa'));
comprobar('cliente, 32 hex al azar: respuesta genérica sin botón', denegado($s) && !hay_boton($s) && count(correos_de($s)) === 0);
$tr = ruta_transients();
$s = vista_anterior('cliente', md5('cualquier-cosa'), 'POST', array('transients' => $tr));
comprobar('cliente, 32 hex al azar con POST: sin correo y sin límite', denegado($s) && count(correos_de($s)) === 0 && strpos($s, '[[SET_TRANSIENT') === false);
@unlink($tr);
$s = vista_anterior('cliente', token_viejo_cliente(2, 'otra@example.test'));
comprobar('cliente, token anterior hecho con otro correo: respuesta genérica', denegado($s) && !hay_boton($s));
$s = vista_anterior('cliente', strtoupper($viejo_c));
comprobar('cliente, token anterior en mayúsculas: respuesta genérica', denegado($s) && !hay_boton($s));
$s = vista_anterior('cliente', $viejo_c . "\n");
comprobar('cliente, token con salto de línea al final: respuesta genérica', denegado($s) && !hay_boton($s));
$s = vista_anterior('cliente', token_viejo_prospecto(2, 'ana@example.test'));
comprobar('cliente, token anterior de prospecto: respuesta genérica', denegado($s) && !hay_boton($s));
$s = vista_anterior('cliente', $viejo_c, 'GET', array(), 'ninguno');
comprobar('sin la clave del servidor, el enlace anterior da la respuesta genérica', denegado($s) && !hay_boton($s));
$tr = ruta_transients();
$s = vista_anterior('cliente', $viejo_c, 'POST', array('transients' => $tr), 'ninguno');
comprobar('sin la clave, un POST tampoco envía nada', denegado($s) && count(correos_de($s)) === 0);
@unlink($tr);
$tr = ruta_transients();
// El correo en la base ya no es el de la firma: el token anterior deja de calzar y no hay reenvío.
$s = vista_anterior('cliente', $viejo_c, 'POST', array('email_en_base' => 'ana.nueva@example.test', 'transients' => $tr));
comprobar('si el correo de la base cambió, el token anterior no calza: nada se envía', denegado($s) && count(correos_de($s)) === 0);
@unlink($tr);

// Correo registrado que no es un correo: el token anterior calza, pero no hay a dónde enviar
$tr = ruta_transients();
$s = vista_anterior('cliente', token_viejo_cliente(2, 'no-es-un-correo'), 'POST', array('email_en_base' => 'no-es-un-correo', 'transients' => $tr));
comprobar('correo registrado inválido: respuesta genérica, sin correo y sin límite',
    denegado($s) && count(correos_de($s)) === 0 && strpos($s, '[[SET_TRANSIENT') === false);
@unlink($tr);

// Prospecto
$s = vista_anterior('prospecto', $viejo_p);
comprobar('prospecto, enlace anterior (GET): ofrece «Enviarme el enlace nuevo»', hay_boton($s) && strpos($s, '[[WP_DIE 200]]') !== false);
comprobar('prospecto, GET: formulario hacia pid y no muestra correo ni ficha',
    strpos($s, $CANONICO . '?crm_view=prospect_timeline&pid=15&token=' . $viejo_p) !== false
    && strpos($s, 'pedro@') === false && strpos($s, 'Pedro Prospecto') === false && sin_enlace_nuevo($s)
    && count(correos_de($s)) === 0);
$tr = ruta_transients();
$s = vista_anterior('prospecto', $viejo_p, 'POST', array('transients' => $tr));
$cs = correos_de($s);
comprobar('prospecto, POST: dos correos, el primero al correo registrado', count($cs) === 2 && ($cs[0]['para'] ?? '') === 'pedro@example.test');
preg_match('/href="([^"]*crm_view=prospect_timeline[^"]*)"/', $cs[0]['cuerpo'] ?? '', $mh);
comprobar('prospecto, POST: el enlace del correo abre su ficha', abre_ficha_prospecto(vista_prospecto_desde($mh[1] ?? '')));
comprobar('prospecto, POST: aviso sin enlace y límite propio del prospecto',
    ($cs[1]['para'] ?? '') === 'avisos@example.test' && strpos($cs[1]['cuerpo'] ?? '', 'crm_view') === false
    && strpos($s, '[[SET_TRANSIENT at_crm_reenvio_prospecto_15 600]]') !== false && strpos($s, 'Listo.') !== false);
$s2 = vista_anterior('prospecto', $viejo_p, 'POST', array('transients' => $tr));
comprobar('prospecto, segundo POST: «Ya te enviamos» y sin correo', count(correos_de($s2)) === 0 && strpos($s2, 'Ya te enviamos') !== false);
@unlink($tr);
$s = vista_anterior('prospecto', md5('cualquier-cosa'));
comprobar('prospecto, 32 hex al azar: respuesta genérica sin botón', denegado($s) && !hay_boton($s));
$s = vista_anterior('prospecto', token_viejo_prospecto(15, 'otro@example.test'));
comprobar('prospecto, token anterior con otro correo: respuesta genérica', denegado($s) && !hay_boton($s));
$s = vista_anterior('prospecto', $viejo_p, 'GET', array(), 'ninguno');
comprobar('prospecto, sin la clave: respuesta genérica', denegado($s) && !hay_boton($s));

// El chat sigue rechazando los tokens anteriores
$s = caso('ajax-historial', 'A', array('post' => array('cid' => 2, 'token' => $viejo_c)));
comprobar('historial del chat: el token anterior sigue rechazado', acceso_denegado($s));
$s = caso('ajax-chat', 'A', array('post' => array('cid' => 2, 'token' => $viejo_c)));
comprobar('chat: el token anterior sigue rechazado y no guarda nada', acceso_denegado($s) && strpos($s, '[[DB insert') === false);

echo PHP_EOL . "8) Regresión: nadie vuelve a calcular el token con las sales públicas" . PHP_EOL;
$con_sal = array();
$sales = array('AUTOMATIZA_CRM_V2', 'AUTOMATIZA_PROSPECT_V1');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($RAIZ . '/wp-content', FilesystemIterator::SKIP_DOTS));
foreach (array_merge(iterator_to_array($it, false), glob($RAIZ . '/*.php')) as $f) {
    $ruta = (string) $f;
    if (substr($ruta, -4) !== '.php' || strpos($ruta, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) !== false) { continue; }
    $t = file_get_contents($ruta);
    // Única excepción: el cuerpo de _token_anterior_valido() en el mu-plugin del CRM.
    if (basename($ruta) === 'crm-ai-completo.php' && strpos($ruta, 'mu-plugins') !== false) {
        $t = preg_replace('/private static function _token_anterior_valido\b.*?\r?\n    \}\r?\n/s', '', $t, 1);
    }
    foreach ($sales as $sal) {
        if (strpos($t, $sal) !== false) { $con_sal[] = substr($ruta, strlen($RAIZ) + 1); break; }
    }
}
comprobar('ningún PHP del sitio usa las sales anteriores fuera de _token_anterior_valido' . ($con_sal ? ' (quedan: ' . implode(', ', $con_sal) . ')' : ''), !$con_sal);
$mu = (string) file_get_contents($RAIZ . '/wp-content/mu-plugins/crm-ai-completo.php');
comprobar('_token_anterior_valido existe y lleva las dos sales',
    preg_match('/private static function _token_anterior_valido\b.*?\r?\n    \}\r?\n/s', $mu, $m_fn) === 1
    && strpos($m_fn[0], $sales[0]) !== false && strpos($m_fn[0], $sales[1]) !== false);

echo PHP_EOL . ($fallas === 0 ? "TODO OK: $total comprobaciones" : "FALLARON $fallas de $total") . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
