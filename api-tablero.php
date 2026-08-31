<?php
$origenes_permitidos = array(
    'https://automatizatech.cl',
    'https://www.automatizatech.cl',
    'http://localhost',
    'http://127.0.0.1',
);
$origen = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$es_local = preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origen);
if (in_array($origen, $origenes_permitidos, true) || $es_local) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-AT-Board-Token');
    header('Access-Control-Allow-Credentials: false');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/wp-load.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if (!defined('AT_BOARD_TOKEN') || empty(AT_BOARD_TOKEN)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'error' => 'AT_BOARD_TOKEN no definido en wp-config-secrets.php'));
    exit;
}

$auth = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
elseif (function_exists('apache_request_headers')) {
    $ah = apache_request_headers();
    if (isset($ah['Authorization'])) $auth = $ah['Authorization'];
    elseif (isset($ah['authorization'])) $auth = $ah['authorization'];
}
$token_header = isset($_SERVER['HTTP_X_AT_BOARD_TOKEN']) ? trim((string)$_SERVER['HTTP_X_AT_BOARD_TOKEN']) : '';
$token_bearer = ($auth !== '' && stripos($auth, 'Bearer ') === 0) ? substr($auth, 7) : '';
$token_recibido = $token_header !== '' ? $token_header : $token_bearer;
$auth_vacio = ($token_recibido === '');
if ($auth_vacio || hash_equals(AT_BOARD_TOKEN, $token_recibido) !== true) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'Token invalido o ausente'));
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;
$tBoard = $prefix . 'omnichannel_at_board';
$tInt = $prefix . 'omnichannel_at_internas';

$metodo = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = array();

function responder($ok, $data = null, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode(array('ok' => $ok, 'data' => $data, 'error' => $error, 'generadoEn' => gmdate('c')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function limpiar($v, $max, $def = '') {
    $v = is_string($v) ? trim($v) : $def;
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v;
}
function limpiarEstado($v) {
    $v = strtolower(trim((string)$v));
    $validos = array('done','progress','wait','blocked','backlog','todo','review');
    return in_array($v, $validos, true) ? $v : 'progress';
}
function limpiarPrio($v) {
    $v = strtoupper(trim((string)$v));
    return in_array($v, array('P0','P1','P2','P3'), true) ? $v : 'P2';
}
function limpiarPaso($v) {
    $i = intval($v);
    return ($i >= 1 && $i <= 6) ? $i : 1;
}
function limpiarTipo($v) {
    $v = strtolower(trim((string)$v));
    return in_array($v, array('dev','design','ops','research','docs'), true) ? $v : 'ops';
}
function limpiarAsig($v) {
    $v = trim((string)$v);
    return in_array($v, array('Luis','OpenCode Go','Claude','Codex'), true) ? $v : 'Luis';
}
function limpiarFecha($v) {
    $v = trim((string)$v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : gmdate('Y-m-d');
}
function limpiarServicios($v) {
    if (is_array($v)) {
        $out = array();
        foreach ($v as $s) { $s = trim((string)$s); if ($s !== '' && mb_strlen($s) <= 100) $out[] = $s; }
        return json_encode(array_slice($out, 0, 20), JSON_UNESCAPED_UNICODE);
    }
    $s = trim((string)$v);
    return $s !== '' ? $s : null;
}

if ($metodo === 'GET') {
    $tablaBoardExiste = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tBoard));
    $tablaInternasExiste = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tInt));
    if ($tablaBoardExiste !== $tBoard || $tablaInternasExiste !== $tInt) {
        responder(false, null, 'board_not_initialized', 503);
    }
    $rows = $wpdb->get_results("SELECT at_id, nombre, contacto, rubro, servicios, paso, prioridad, estado, estado_label, DATE_FORMAT(ultima,'%Y-%m-%d') AS ultima, notas FROM {$tBoard} ORDER BY paso, prioridad, at_id", ARRAY_A);
    $clientes = array();
    if (is_array($rows)) {
        foreach ($rows as $r) {
            $r['servicios'] = isset($r['servicios']) ? json_decode($r['servicios'], true) : array();
            if (!is_array($r['servicios'])) $r['servicios'] = array();
            $r['paso'] = intval($r['paso']);
            $r['estado'] = $r['estado'] === 'active' ? 'progress' : $r['estado'];
            $r['estadoLabel'] = isset($r['estado_label']) ? $r['estado_label'] : '';
            unset($r['estado_label']);
            $clientes[] = $r;
        }
    }
    $rowsI = $wpdb->get_results("SELECT at_id, titulo, asignado_a AS asignadoA, tipo, estado, prioridad, DATE_FORMAT(ultima,'%Y-%m-%d') AS ultima, notas FROM {$tInt} ORDER BY FIELD(estado,'progress','todo','review','wait','blocked','backlog','done'), prioridad, at_id", ARRAY_A);
    if (is_array($rowsI)) {
        foreach ($rowsI as &$rowI) {
            if ($rowI['estado'] === 'active') $rowI['estado'] = 'progress';
        }
        unset($rowI);
    }
    responder(true, array('version' => 'v8', 'clientes' => $clientes, 'internas' => is_array($rowsI) ? $rowsI : array()));
}

if ($metodo === 'POST') {
    $accion = limpiar(isset($input['accion']) ? $input['accion'] : '', 20, 'upsert');
    $tipo = limpiar(isset($input['tipo']) ? $input['tipo'] : '', 10, 'cli');
    if (!in_array($tipo, array('cli', 'int'), true)) responder(false, null, 'tipo invalido', 400);
    $at_id = limpiar(isset($input['at_id']) ? $input['at_id'] : '', 40, '');
    if ($at_id === '') responder(false, null, 'at_id requerido', 400);

    if ($tipo === 'cli') {
        $dato = array(
            'at_id' => $at_id,
            'nombre' => limpiar(isset($input['nombre']) ? $input['nombre'] : '', 191, ''),
            'contacto' => limpiar(isset($input['contacto']) ? $input['contacto'] : '', 191, ''),
            'rubro' => limpiar(isset($input['rubro']) ? $input['rubro'] : '', 191, ''),
            'servicios' => limpiarServicios(isset($input['servicios']) ? $input['servicios'] : null),
            'paso' => limpiarPaso(isset($input['paso']) ? $input['paso'] : 1),
            'prioridad' => limpiarPrio(isset($input['prioridad']) ? $input['prioridad'] : 'P2'),
            'estado' => limpiarEstado(isset($input['estado']) ? $input['estado'] : 'progress'),
            'estado_label' => limpiar(isset($input['estadoLabel']) ? $input['estadoLabel'] : '', 80, ''),
            'ultima' => limpiarFecha(isset($input['ultima']) ? $input['ultima'] : ''),
            'notas' => limpiar(isset($input['notas']) ? $input['notas'] : '', 65500, ''),
        );
        if ($dato['nombre'] === '') responder(false, null, 'nombre requerido', 400);
        $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tBoard} WHERE at_id = %s", $at_id));
        if ($existe) {
            $wpdb->update($tBoard, $dato, array('at_id' => $at_id));
        } else {
            $wpdb->insert($tBoard, $dato);
        }
        if ($wpdb->last_error) {
            error_log('[AT Board] Error DB clientes: ' . $wpdb->last_error);
            responder(false, null, 'db_error', 500);
        }
        responder(true, array('at_id' => $at_id, 'tipo' => 'cli', 'accion' => $existe ? 'update' : 'insert'));
    } else {
        $dato = array(
            'at_id' => $at_id,
            'titulo' => limpiar(isset($input['titulo']) ? $input['titulo'] : '', 255, ''),
            'asignado_a' => limpiarAsig(isset($input['asignadoA']) ? $input['asignadoA'] : 'Luis'),
            'tipo' => limpiarTipo(isset($input['tipo_tarea']) ? $input['tipo_tarea'] : (isset($input['tipo']) ? $input['tipo'] : 'ops')),
            'estado' => limpiarEstado(isset($input['estado']) ? $input['estado'] : 'backlog'),
            'prioridad' => limpiarPrio(isset($input['prioridad']) ? $input['prioridad'] : 'P2'),
            'ultima' => limpiarFecha(isset($input['ultima']) ? $input['ultima'] : ''),
            'notas' => limpiar(isset($input['notas']) ? $input['notas'] : '', 65500, ''),
        );
        if ($dato['titulo'] === '') responder(false, null, 'titulo requerido', 400);
        $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tInt} WHERE at_id = %s", $at_id));
        if ($existe) {
            $wpdb->update($tInt, $dato, array('at_id' => $at_id));
        } else {
            $wpdb->insert($tInt, $dato);
        }
        if ($wpdb->last_error) {
            error_log('[AT Board] Error DB internas: ' . $wpdb->last_error);
            responder(false, null, 'db_error', 500);
        }
        responder(true, array('at_id' => $at_id, 'tipo' => 'int', 'accion' => $existe ? 'update' : 'insert'));
    }
}

responder(false, null, 'metodo no soportado', 405);
