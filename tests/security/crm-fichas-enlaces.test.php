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
function get_option($k, $def = false) { return $def; }
function get_bloginfo($k = '') { return 'AutomatizaTech'; }
function nocache_headers() { echo '[[NOCACHE]]'; }
function wp_die($msg = '', $titulo = '', $args = array()) {
    $codigo = is_array($args) ? ($args['response'] ?? 500) : 500;
    echo '[[WP_DIE ' . $codigo . ']]' . (is_string($msg) ? $msg : '');
    exit;
}
function wp_send_json_success($d = null, $s = null) { echo '[[JSON_OK]]' . json_encode($d); exit; }
function wp_send_json_error($d = null, $s = null) { echo '[[JSON_ERROR]]' . json_encode($d); exit; }
function wp_mail($para, $asunto, $cuerpo, $cab = array(), $adj = array()) {
    echo '[[WP_MAIL ' . $para . ']]' . $cuerpo;
    return true;
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
$s_viejo = caso('vista-cliente', 'A', array('get' => array('cid' => 2, 'token' => token_viejo_cliente(2, 'ana@example.test'))));
comprobar('el enlace viejo (md5 con la sal pública) ya no abre: 403', denegado($s_viejo));
comprobar('quien abre un enlace viejo no ve datos del cliente',
    strpos($s_viejo, 'Ana Prueba') === false && strpos($s_viejo, 'ana@example.test') === false);
comprobar('quien abre un enlace viejo ve cómo pedir uno nuevo', strpos($s_viejo, 'wa.me/56927002984') !== false);
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
comprobar('el enlace viejo del prospecto ya no abre: 403', denegado($s));
comprobar('quien abre el enlace viejo del prospecto no ve sus datos', strpos($s, 'Pedro Prospecto') === false);

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

echo PHP_EOL . "7) Regresión: nadie vuelve a calcular el token con las sales públicas" . PHP_EOL;
$con_sal = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($RAIZ . '/wp-content', FilesystemIterator::SKIP_DOTS));
foreach (array_merge(iterator_to_array($it, false), glob($RAIZ . '/*.php')) as $f) {
    $ruta = (string) $f;
    if (substr($ruta, -4) !== '.php' || strpos($ruta, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) !== false) { continue; }
    $t = file_get_contents($ruta);
    if (strpos($t, 'AUTOMATIZA_CRM_V2') !== false || strpos($t, 'AUTOMATIZA_PROSPECT_V1') !== false) {
        $con_sal[] = substr($ruta, strlen($RAIZ) + 1);
    }
}
comprobar('ningún PHP del sitio usa las sales públicas' . ($con_sal ? ' (quedan: ' . implode(', ', $con_sal) . ')' : ''), !$con_sal);

echo PHP_EOL . ($fallas === 0 ? "TODO OK: $total comprobaciones" : "FALLARON $fallas de $total") . PHP_EOL;
exit($fallas === 0 ? 0 : 1);
