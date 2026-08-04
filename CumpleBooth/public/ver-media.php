<?php
/**
 * ver-media.php — sirve un archivo del Álbum Recuerdo por token opaco.
 *
 * Solo entrega lo que subió un invitado o el organizador. Las fotos de cabina
 * siguen sirviéndose por ver.php con el token de cc_photos: no se les emite un
 * segundo token para el mismo archivo.
 *
 * Cierra por defecto: sin sesión de admin únicamente se entrega material
 * aprobado de un álbum publicado. Mientras el organizador cura, nada de lo
 * pendiente es alcanzable desde fuera aunque alguien adivine un token.
 */
require __DIR__ . '/lib.php';

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');

function cb_media_error(int $code): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $code === 404 ? 'No encontrado' : 'No disponible';
    exit;
}

$token = (string) ($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_media_error(404);
}

// Variante: original (por defecto), miniatura o póster de video.
$variant = (string) ($_GET['v'] ?? 'full');
if (!in_array($variant, ['full', 'thumb', 'poster'], true)) {
    cb_media_error(404);
}

try {
    $media = cb_album_find_media_by_token($token);
} catch (Throwable $e) {
    error_log('CumpleClick ver-media: ' . $e->getMessage());
    cb_media_error(503);
}
if ($media === null) {
    cb_media_error(404);
}

// La sesión de admin se lee sin crear una nueva: si no hay cookie no se
// arranca sesión, para no repartir cookies a invitados que solo miran fotos.
$isAdmin = false;
if (!empty($_COOKIE['cc_admin'])) {
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_name('cc_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    $isAdmin = !empty($_SESSION['admin_logged'])
        && time() - (int) ($_SESSION['admin_seen'] ?? 0) <= $idle
        && time() - (int) ($_SESSION['admin_started'] ?? 0) <= $absolute;
    session_write_close();
}

if (!$isAdmin) {
    if ((string) $media['moderation_status'] !== 'approved') {
        cb_media_error(404);
    }
    if ((string) $media['album_status'] !== 'published') {
        cb_media_error(404);
    }
}

$keyByVariant = [
    'full' => (string) ($media['storage_key'] ?? ''),
    'thumb' => (string) ($media['thumb_storage_key'] ?? ''),
    'poster' => (string) ($media['poster_storage_key'] ?? ''),
];
$storageKey = $keyByVariant[$variant];
// Si la miniatura no se pudo generar se cae al original en vez de dar 404:
// la revista prefiere una imagen pesada a un hueco.
if ($storageKey === '' && $variant !== 'full') {
    $storageKey = $keyByVariant['full'];
}
if ($storageKey === '') {
    cb_media_error(404);
}

$path = cb_album_media_path($storageKey);
if ($path === null || !is_file($path)) {
    cb_media_error(404);
}

// El Content-Type sale de la extensión canónica de la storage key, que la
// generó el servidor tras oler los bytes; nunca del mime declarado al subir.
$ext = strtolower((string) pathinfo($storageKey, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'mp4' => 'video/mp4'];
if (!isset($types[$ext])) {
    cb_media_error(404);
}

$size = (int) filesize($path);
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . $size);
// Siempre inline y con nombre neutro: el archivo se muestra en la revista y el
// nombre que puso el invitado no llega jamás a una cabecera.
header('Content-Disposition: inline; filename="recuerdo.' . $ext . '"');
header('Cache-Control: private, max-age=3600');
readfile($path);
