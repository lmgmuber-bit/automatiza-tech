<?php
/**
 * album-intake.php — recibe un aporte de invitado al Álbum Recuerdo.
 *
 * Un archivo por petición: así la página puede mostrar progreso real y
 * reintentar solo el que falló, sin volver a subir los que ya pasaron ni
 * chocar con post_max_size cuando alguien elige diez fotos de 12 MiB.
 *
 * Solo acepta el token de aporte y nunca devuelve nada del álbum: quien tenga
 * el QR puede subir, no leer.
 */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

function cb_intake_error(int $status, string $error, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => false, 'error' => $error], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    cb_intake_error(405, 'method_not_allowed');
}

$limits = cb_album_limits();

// Un POST que supera post_max_size llega con $_POST y $_FILES vacíos y
// CONTENT_LENGTH grande: sin este caso especial el invitado vería
// "falta el archivo" cuando en realidad su video pesa demasiado.
if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    cb_intake_error(413, 'video_too_big');
}

$token = (string) ($_POST['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_intake_error(400, 'bad_link');
}

try {
    $resolved = cb_album_resolve_token($token, 'intake');
} catch (Throwable $e) {
    error_log('CumpleClick album-intake: ' . $e->getMessage());
    cb_intake_error(503, 'unavailable');
}
if ($resolved === null) {
    // No se distingue entre inexistente, revocado y vencido: confirmar cuál es
    // le diría a quien prueba tokens que acertó uno que existió.
    cb_intake_error(404, 'bad_link');
}

$album = $resolved['album'];
$partySlug = $resolved['party_slug'];
$party = cb_load_party_raw($partySlug);
if ($party === null) {
    cb_intake_error(404, 'bad_link');
}
if (!cb_album_intake_open($album, $party)) {
    cb_intake_error(403, 'closed');
}

$albumId = (int) $album['id'];
$partyId = cb_party_db_id($partySlug);
if ($partyId === null) {
    cb_intake_error(503, 'unavailable');
}

// El límite cuenta archivos, y la identidad ya viene en HMAC desde lib.php.
$limit = cb_rate_limit(
    'album-intake:' . $albumId,
    cb_request_identity(),
    (int) $limits['intake_rate_limit'],
    (int) $limits['intake_rate_window'],
    (int) $limits['intake_rate_block']
);
if (!$limit['allowed']) {
    header('Retry-After: ' . max(1, (int) $limit['retry_after']));
    cb_intake_error(429, 'rate_limited', ['retry_after' => (int) $limit['retry_after']]);
}

// El consentimiento es obligatorio y se guarda versionado: el invitado declara
// que tiene derecho a compartir lo que sube.
if (($_POST['consent'] ?? '') !== '1') {
    cb_intake_error(400, 'consent_required');
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || !isset($file['error'])) {
    cb_intake_error(400, 'no_file');
}
switch ((int) $file['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        cb_intake_error(413, 'video_too_big');
        // no break: cb_intake_error corta la ejecución.
    case UPLOAD_ERR_NO_FILE:
        cb_intake_error(400, 'no_file');
    default:
        cb_intake_error(500, 'upload_failed');
}

$tmpPath = (string) ($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    cb_intake_error(400, 'no_file');
}
$byteSize = (int) ($file['size'] ?? 0);

$validation = cb_album_validate_upload($tmpPath, $byteSize, !empty($album['intake_videos']));
if (!$validation['ok']) {
    @unlink($tmpPath);
    $status = $validation['error'] === 'format' ? 415 : 422;
    cb_intake_error($status, $validation['error']);
}
$media = $validation['media'];

// El hash se calcula antes de mover el archivo: si ya está en el álbum se
// responde bien y no se escribe nada, así reintentar una subida cortada no
// duplica recuerdos.
$sha256 = hash_file('sha256', $tmpPath);
if ($sha256 === false) {
    @unlink($tmpPath);
    cb_intake_error(500, 'upload_failed');
}
if (cb_album_media_exists($albumId, $sha256)) {
    @unlink($tmpPath);
    echo json_encode(['ok' => true, 'duplicate' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $storageKey = cb_album_storage_key($partySlug, $media['ext']);
} catch (Throwable $e) {
    @unlink($tmpPath);
    cb_intake_error(500, 'upload_failed');
}
$storedPath = cb_album_store_file($tmpPath, $storageKey, true);
if ($storedPath === null) {
    @unlink($tmpPath);
    cb_intake_error(500, 'upload_failed');
}

// La miniatura es una mejora, no un requisito: si GD no puede con este archivo
// la revista usa el original y no se pierde el aporte.
$thumbKey = $media['kind'] === 'image'
    ? cb_album_make_thumbnail($storedPath, $partySlug, $media['ext'])
    : null;

// Póster del video: lo captura el navegador del invitado del primer fotograma,
// porque en el servidor no hay ffmpeg para extraerlo.
$posterKey = null;
if ($media['kind'] === 'video' && isset($_FILES['poster']) && (int) ($_FILES['poster']['error'] ?? 1) === UPLOAD_ERR_OK) {
    $posterTmp = (string) $_FILES['poster']['tmp_name'];
    if (is_uploaded_file($posterTmp)) {
        $posterCheck = cb_album_validate_upload($posterTmp, (int) $_FILES['poster']['size'], false);
        if ($posterCheck['ok'] && $posterCheck['media']['kind'] === 'image') {
            try {
                $candidate = cb_album_storage_key($partySlug, $posterCheck['media']['ext']);
                if (cb_album_store_file($posterTmp, $candidate, true) !== null) {
                    $posterKey = $candidate;
                }
            } catch (Throwable $e) {
                $posterKey = null;
            }
        }
        @unlink($posterTmp);
    }
}

$accessToken = cb_opaque_token(16);
$record = [
    'source' => 'guest',
    'media_kind' => $media['kind'],
    'access_token' => $accessToken,
    'storage_key' => $storageKey,
    'thumb_storage_key' => $thumbKey,
    'poster_storage_key' => $posterKey,
    // El nombre original nunca se usa para construir rutas; se guarda solo
    // para que el organizador reconozca el archivo en la curaduría.
    'original_name' => mb_substr(basename((string) ($file['name'] ?? '')), 0, 200),
    'mime' => $media['mime'],
    'byte_size' => $byteSize,
    'width' => (int) $media['width'],
    'height' => (int) $media['height'],
    'duration_seconds' => $media['kind'] === 'video' ? round((float) ($media['duration'] ?? 0), 2) : null,
    'sha256' => $sha256,
    'contributor_name' => cb_album_clean_contributor_text($_POST['name'] ?? null, 80),
    'contributor_message' => cb_album_clean_contributor_text($_POST['message'] ?? null, 280),
    'moderation_status' => 'pending',
    'consent_version' => cb_album_consent_version(),
    'uploader_hmac' => cb_hmac(cb_request_identity(), 'album-intake'),
];

try {
    $result = cb_album_record_media($albumId, $partyId, $record);
} catch (Throwable $e) {
    error_log('CumpleClick album-intake: ' . $e->getMessage());
    $result = 'error';
}

if ($result !== 'ok') {
    // Si no se pudo registrar, el archivo en disco quedaría huérfano.
    @unlink($storedPath);
    if ($thumbKey !== null) { @unlink((string) cb_album_media_path($thumbKey)); }
    if ($posterKey !== null) { @unlink((string) cb_album_media_path($posterKey)); }
    if ($result === 'quota') {
        cb_intake_error(507, 'album_full');
    }
    if ($result === 'duplicate') {
        echo json_encode(['ok' => true, 'duplicate' => true], JSON_UNESCAPED_SLASHES);
        exit;
    }
    cb_intake_error(500, 'upload_failed');
}

echo json_encode(['ok' => true, 'kind' => $media['kind']], JSON_UNESCAPED_SLASHES);
