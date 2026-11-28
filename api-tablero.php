<?php
define('AT_BOARD_API_VERSION', '8.3.0');
define('AT_BOARD_SCHEMA_VERSION', '8.3.0');
define('AT_BOARD_ATTACHMENT_MAX_BYTES', 2097152);
define('AT_BOARD_ATTACHMENT_MAX_PER_TASK', 5);
header('X-AT-Board-API-Version: ' . AT_BOARD_API_VERSION);

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

// Algunas instalaciones AT no cargan wp-config-secrets.php desde wp-config.php.
// Cargarlo solo si falta el token del tablero. El handler evita que constantes
// legacy duplicadas contaminen la respuesta JSON con warnings HTML.
$cfg = ABSPATH . 'wp-config-secrets.php';
$cfg_existe = is_file($cfg);
$cfg_legible = $cfg_existe && is_readable($cfg);
if (!defined('AT_BOARD_TOKEN') && $cfg_legible) {
        set_error_handler(function ($severity, $message) {
            if (($severity === E_WARNING || $severity === E_NOTICE)
                && strpos($message, 'already defined') !== false) {
                return true;
            }
            return false;
        });
        include $cfg;
        restore_error_handler();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if (!defined('AT_BOARD_TOKEN') || empty(AT_BOARD_TOKEN)) {
    http_response_code(500);
    echo json_encode(array(
        'ok' => false,
        'error' => 'token_not_configured',
        'diagnostic' => array(
            'api_version' => AT_BOARD_API_VERSION,
            'secrets_exists' => $cfg_existe,
            'secrets_readable' => $cfg_legible,
        ),
    ));
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
$tAttachments = $prefix . 'omnichannel_at_attachments';

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

function asegurarTablaAdjuntos($tabla) {
    global $wpdb;
    $existe = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabla));
    if ($existe === $tabla && get_option('at_board_schema_version') === AT_BOARD_SCHEMA_VERSION) {
        return true;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$tabla} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        at_id VARCHAR(40) NOT NULL,
        tipo VARCHAR(3) NOT NULL DEFAULT 'int',
        filename VARCHAR(191) NOT NULL,
        mime_type VARCHAR(50) NOT NULL,
        file_size INT UNSIGNED NOT NULL,
        contenido LONGBLOB NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_task (tipo, at_id),
        KEY idx_created (created_at)
    ) {$charset_collate};";
    dbDelta($sql);
    $existe = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabla));
    if ($existe === $tabla) {
        update_option('at_board_schema_version', AT_BOARD_SCHEMA_VERSION, false);
        return true;
    }
    error_log('[AT Board] No se pudo crear tabla de adjuntos: ' . $wpdb->last_error);
    return false;
}

function metaAdjunto($row) {
    return array(
        'id' => intval(is_array($row) ? $row['id'] : $row->id),
        'at_id' => is_array($row) ? $row['at_id'] : $row->at_id,
        'tipo' => is_array($row) ? $row['tipo'] : $row->tipo,
        'filename' => is_array($row) ? $row['filename'] : $row->filename,
        'mime_type' => is_array($row) ? $row['mime_type'] : $row->mime_type,
        'file_size' => intval(is_array($row) ? $row['file_size'] : $row->file_size),
        'created_at' => is_array($row) ? $row['created_at'] : $row->created_at,
    );
}

if (!asegurarTablaAdjuntos($tAttachments)) {
    responder(false, null, 'attachments_table_unavailable', 503);
}

$accion = '';
if (isset($_GET['accion'])) $accion = limpiar($_GET['accion'], 30, '');
elseif (isset($_POST['accion'])) $accion = limpiar($_POST['accion'], 30, '');
elseif (isset($input['accion'])) $accion = limpiar($input['accion'], 30, '');

if ($metodo === 'GET' && $accion === 'attachment') {
    $attachment_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if (!$attachment_id) responder(false, null, 'attachment_id_required', 400);
    $adjunto = $wpdb->get_row($wpdb->prepare(
        "SELECT id, filename, mime_type, file_size, contenido FROM {$tAttachments} WHERE id = %d",
        $attachment_id
    ));
    if (!$adjunto) responder(false, null, 'attachment_not_found', 404);
    $extensiones = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    if (!isset($extensiones[$adjunto->mime_type])) responder(false, null, 'attachment_invalid_mime', 415);
    header('Content-Type: ' . $adjunto->mime_type);
    header('Content-Length: ' . intval($adjunto->file_size));
    header('Content-Disposition: inline; filename="attachment-' . intval($adjunto->id) . '.' . $extensiones[$adjunto->mime_type] . '"');
    header('Cache-Control: private, no-store, max-age=0');
    echo $adjunto->contenido;
    exit;
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
    $adjuntosMeta = $wpdb->get_results("SELECT id, at_id, tipo, filename, mime_type, file_size, created_at FROM {$tAttachments} ORDER BY created_at, id", ARRAY_A);
    $adjuntosPorTarea = array('cli' => array(), 'int' => array());
    if (is_array($adjuntosMeta)) {
        foreach ($adjuntosMeta as $adjuntoMeta) {
            $tipoAdjunto = $adjuntoMeta['tipo'] === 'cli' ? 'cli' : 'int';
            $idTarea = $adjuntoMeta['at_id'];
            if (!isset($adjuntosPorTarea[$tipoAdjunto][$idTarea])) $adjuntosPorTarea[$tipoAdjunto][$idTarea] = array();
            $adjuntosPorTarea[$tipoAdjunto][$idTarea][] = metaAdjunto($adjuntoMeta);
        }
    }
    foreach ($clientes as &$cliente) {
        $cliente['adjuntos'] = isset($adjuntosPorTarea['cli'][$cliente['at_id']]) ? $adjuntosPorTarea['cli'][$cliente['at_id']] : array();
    }
    unset($cliente);
    if (is_array($rowsI)) {
        foreach ($rowsI as &$rowI) {
            if ($rowI['estado'] === 'active') $rowI['estado'] = 'progress';
            $rowI['adjuntos'] = isset($adjuntosPorTarea['int'][$rowI['at_id']]) ? $adjuntosPorTarea['int'][$rowI['at_id']] : array();
        }
        unset($rowI);
    }
    responder(true, array('version' => 'v8.3', 'clientes' => $clientes, 'internas' => is_array($rowsI) ? $rowsI : array()));
}

if ($metodo === 'POST') {
    if ($accion === 'upload_attachment') {
        $tipoAdjunto = limpiar(isset($_POST['tipo']) ? $_POST['tipo'] : '', 3, 'int');
        $idTarea = limpiar(isset($_POST['at_id']) ? $_POST['at_id'] : '', 40, '');
        if ($tipoAdjunto !== 'int') responder(false, null, 'attachments_only_for_internal_tasks', 400);
        if ($idTarea === '') responder(false, null, 'at_id_required', 400);
        $tareaExiste = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tInt} WHERE at_id = %s", $idTarea));
        if (!$tareaExiste) responder(false, null, 'task_not_found', 404);
        $cantidad = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tAttachments} WHERE tipo = %s AND at_id = %s", 'int', $idTarea)));
        if ($cantidad >= AT_BOARD_ATTACHMENT_MAX_PER_TASK) responder(false, null, 'attachment_limit_reached', 409);
        if (!isset($_FILES['imagen']) || !is_array($_FILES['imagen'])) responder(false, null, 'image_required', 400);
        $archivo = $_FILES['imagen'];
        $uploadError = isset($archivo['error']) ? intval($archivo['error']) : UPLOAD_ERR_NO_FILE;
        if (in_array($uploadError, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
            responder(false, null, 'attachment_size_invalid', 413);
        }
        if ($uploadError !== UPLOAD_ERR_OK) responder(false, null, 'upload_failed', 400);
        $tamano = isset($archivo['size']) ? intval($archivo['size']) : 0;
        if ($tamano < 1 || $tamano > AT_BOARD_ATTACHMENT_MAX_BYTES) responder(false, null, 'attachment_size_invalid', 413);
        $temporal = isset($archivo['tmp_name']) ? $archivo['tmp_name'] : '';
        if ($temporal === '' || !is_uploaded_file($temporal)) responder(false, null, 'invalid_upload_source', 400);
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? finfo_file($finfo, $temporal) : '';
        if ($finfo) finfo_close($finfo);
        $permitidos = array(
            'image/jpeg' => array('jpg', 'jpeg'),
            'image/png' => array('png'),
            'image/webp' => array('webp'),
        );
        $nombre = sanitize_file_name(isset($archivo['name']) ? $archivo['name'] : 'imagen');
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!isset($permitidos[$mime]) || !in_array($extension, $permitidos[$mime], true)) {
            responder(false, null, 'attachment_mime_invalid', 415);
        }
        $contenido = file_get_contents($temporal);
        if ($contenido === false || strlen($contenido) !== $tamano) responder(false, null, 'attachment_read_failed', 500);
        $insertado = $wpdb->insert($tAttachments, array(
            'at_id' => $idTarea,
            'tipo' => 'int',
            'filename' => limpiar($nombre, 191, 'imagen.' . $extension),
            'mime_type' => $mime,
            'file_size' => $tamano,
            'contenido' => $contenido,
        ), array('%s', '%s', '%s', '%s', '%d', '%s'));
        if (!$insertado || $wpdb->last_error) {
            error_log('[AT Board] Error DB adjunto: ' . $wpdb->last_error);
            responder(false, null, 'db_error', 500);
        }
        $meta = $wpdb->get_row($wpdb->prepare("SELECT id, at_id, tipo, filename, mime_type, file_size, created_at FROM {$tAttachments} WHERE id = %d", $wpdb->insert_id));
        responder(true, metaAdjunto($meta));
    }

    if ($accion === 'delete_attachment') {
        $attachment_id = isset($input['attachment_id']) ? absint($input['attachment_id']) : 0;
        if (!$attachment_id) responder(false, null, 'attachment_id_required', 400);
        $existeAdjunto = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tAttachments} WHERE id = %d", $attachment_id));
        if (!$existeAdjunto) responder(false, null, 'attachment_not_found', 404);
        $borrado = $wpdb->delete($tAttachments, array('id' => $attachment_id), array('%d'));
        if ($borrado === false || $wpdb->last_error) {
            error_log('[AT Board] Error al eliminar adjunto: ' . $wpdb->last_error);
            responder(false, null, 'db_error', 500);
        }
        responder(true, array('attachment_id' => $attachment_id, 'accion' => 'delete'));
    }

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
