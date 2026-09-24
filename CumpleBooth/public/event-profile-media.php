<?php
/**
 * Multimedia pública del Perfil del protagonista.
 *
 * El token opaco identifica una invitación publicada. El helper de dominio
 * vuelve a comprobar que el medio pertenece al perfil activo del mismo evento
 * y que está marcado como público. Los storage keys nunca se exponen.
 */
require __DIR__ . '/lib.php';

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('Accept-Ranges: bytes');

function cb_event_profile_media_error(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    if ($status === 405) {
        header('Allow: GET, HEAD');
    }
    echo $status === 503 ? 'No disponible' : 'No encontrado';
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    cb_event_profile_media_error(405);
}

$token = (string) ($_GET['t'] ?? '');
$mediaToken = strtolower(trim((string) ($_GET['mt'] ?? '')));
if (!cb_invitation_public_token_is_valid($token) || !preg_match('/^[a-f0-9]{32}$/', $mediaToken)) {
    cb_event_profile_media_error(404);
}

if (!function_exists('cb_event_profile_find_public_media_for_invitation')) {
    error_log('CumpleClick event-profile-media: falta helper de dominio');
    cb_event_profile_media_error(503);
}

try {
    $limit = cb_rate_limit('event-profile-media', cb_request_identity(), 300, 600, 600);
    if (!$limit['allowed']) {
        header('Retry-After: ' . max(1, (int) $limit['retry_after']));
        cb_event_profile_media_error(429);
    }
    $media = cb_event_profile_find_public_media_for_invitation($token, $mediaToken);
} catch (Throwable $e) {
    error_log('CumpleClick event-profile-media: ' . $e->getMessage());
    cb_event_profile_media_error(503);
}

if (!is_array($media)) {
    cb_event_profile_media_error(404);
}

// El helper entrega una ruta ya resuelta y validada dentro del storage privado.
// Nunca se construye una ruta desde parámetros HTTP en este endpoint.
$path = (string) ($media['absolute_path'] ?? $media['file_path'] ?? '');
if ($path === '' && function_exists('cb_event_profile_media_path')) {
    $path = (string) (cb_event_profile_media_path((string) ($media['storage_key'] ?? '')) ?? '');
}
if ($path === '' || !is_file($path) || !is_readable($path)) {
    cb_event_profile_media_error(404);
}

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'video/mp4' => 'mp4',
];
$declaredMime = strtolower((string) ($media['mime_type'] ?? $media['file_mime'] ?? $media['mime'] ?? ''));
$finfo = new finfo(FILEINFO_MIME_TYPE);
$sniffedMime = strtolower((string) $finfo->file($path));
if (!isset($allowedTypes[$sniffedMime])) {
    cb_event_profile_media_error(404);
}
if ($declaredMime !== '' && $declaredMime !== $sniffedMime) {
    error_log('CumpleClick event-profile-media: MIME no coincide para medio autorizado');
    cb_event_profile_media_error(404);
}

$size = (int) filesize($path);
if ($size < 1) {
    cb_event_profile_media_error(404);
}

$start = 0;
$end = $size - 1;
$status = 200;
$range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
if ($range !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) || ($matches[1] === '' && $matches[2] === '')) {
        header('Content-Range: bytes */' . $size);
        cb_event_profile_media_error(416);
    }

    if ($matches[1] === '') {
        $suffix = (int) $matches[2];
        if ($suffix < 1) {
            header('Content-Range: bytes */' . $size);
            cb_event_profile_media_error(416);
        }
        $start = max(0, $size - $suffix);
    } else {
        $start = (int) $matches[1];
        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }
    }

    if ($start >= $size || $end < $start) {
        header('Content-Range: bytes */' . $size);
        cb_event_profile_media_error(416);
    }
    $end = min($end, $size - 1);
    $status = 206;
}

$length = $end - $start + 1;
http_response_code($status);
header('Content-Type: ' . $sniffedMime);
header('Content-Disposition: inline; filename="perfil.' . $allowedTypes[$sniffedMime] . '"');
header('Content-Length: ' . $length);
header('Cache-Control: private, max-age=3600, no-transform');
if ($status === 206) {
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
if ($method === 'HEAD') {
    exit;
}

$handle = fopen($path, 'rb');
if ($handle === false || fseek($handle, $start) !== 0) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    cb_event_profile_media_error(503);
}

$remaining = $length;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(8192, $remaining));
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    if (connection_status() !== CONNECTION_NORMAL) {
        break;
    }
}
fclose($handle);
exit;
