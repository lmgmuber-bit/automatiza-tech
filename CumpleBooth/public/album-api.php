<?php
/**
 * album-api.php — datos de un Álbum Recuerdo publicado, por token de lectura.
 *
 * Entrega solo material aprobado de un álbum en estado `published`. Si el
 * organizador exigió PIN, primero hay que canjearlo acá mismo: el PIN es el de
 * la galería y la sesión es la misma (`cc_gallery`), así que una familia que ya
 * lo escribió no lo vuelve a escribir.
 *
 * Nunca devuelve rutas de disco, ids de fiesta, el PIN, ni nada del admin.
 */
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

$secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_gallery');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

function cb_album_api_fail(int $status, string $error, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => false, 'error' => $error], $extra), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$token = (string) ($_REQUEST['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_album_api_fail(400, 'bad_link');
}

try {
    $resolved = cb_album_resolve_token($token, 'view');
} catch (Throwable $e) {
    error_log('CumpleClick album-api: ' . $e->getMessage());
    cb_album_api_fail(503, 'unavailable');
}
if ($resolved === null) {
    cb_album_api_fail(404, 'bad_link');
}

$album = $resolved['album'];
$partySlug = $resolved['party_slug'];

// Un álbum que todavía no se publica no existe para el mundo, aunque el enlace
// sea válido: el organizador puede estar curando.
if ((string) $album['status'] !== 'published') {
    cb_album_api_fail(404, 'not_published');
}

$party = cb_load_party_raw($partySlug);
if ($party === null) {
    cb_album_api_fail(404, 'bad_link');
}

// ── PIN ─────────────────────────────────────────────────────────────────────
$needsPin = !empty($album['require_pin']) && !empty($party['galeriaPinHash']);
$authenticated = !$needsPin || (int) ($_SESSION['gallery_auth'][$partySlug] ?? 0) >= time();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $needsPin && !$authenticated) {
    // Mismo límite que galeria.php: 5 intentos por minuto sobre la misma
    // identidad ya anonimizada en HMAC.
    $limit = cb_rate_limit('gallery-pin:' . $partySlug, cb_request_identity(), 5, 60, 60);
    if (!$limit['allowed']) {
        header('Retry-After: ' . max(1, (int) $limit['retry_after']));
        cb_album_api_fail(429, 'rate_limited', ['retry_after' => (int) $limit['retry_after']]);
    }
    if (cb_verify_party_pin($party, (string) ($_POST['pin'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['gallery_auth'][$partySlug] = time() + 1800;
        $authenticated = true;
    } else {
        cb_album_api_fail(403, 'bad_pin');
    }
}

if (!$authenticated) {
    // 200 a propósito: que haga falta un PIN no es un error, es el estado
    // normal de un álbum privado. El cliente pinta el formulario.
    echo json_encode([
        'ok' => false,
        'error' => 'pin_required',
        'eventName' => (string) ($party['nombre'] ?? ''),
        'theme' => cb_album_api_theme((string) ($party['tema'] ?? '')),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Datos ───────────────────────────────────────────────────────────────────
/** Paleta y assets de la temática, tal cual los declara el catálogo. */
function cb_album_api_theme(string $themeSlug): array
{
    $themes = cb_load_themes()['themes'] ?? [];
    $theme = is_array($themes[$themeSlug] ?? null) ? $themes[$themeSlug] : [];
    $assets = [];
    foreach (['banner' => 'fondo-banner.jpg', 'sala' => 'fondo-sala.jpg', 'grupo' => 'grupo-personajes.png'] as $key => $file) {
        $rel = 'themes/' . $themeSlug . '/' . $file;
        if ($themeSlug !== '' && is_file(__DIR__ . '/' . $rel)) {
            $assets[$key] = $rel;
        }
    }
    return [
        'slug' => $themeSlug,
        'name' => (string) ($theme['nombre'] ?? ''),
        'colors' => is_array($theme['colors'] ?? null) ? $theme['colors'] : new stdClass(),
        'confetti' => is_array($theme['confetti'] ?? null) ? array_values($theme['confetti']) : [],
        'assets' => $assets ?: new stdClass(),
    ];
}

$media = [];
foreach (cb_album_list_media((int) $album['id'], ['approved']) as $row) {
    $source = (string) $row['source'];
    if ($source === 'booth') {
        $photoToken = (string) ($row['photo_token'] ?? '');
        if ($photoToken === '') {
            continue; // foto de cabina sin token utilizable: se omite en silencio
        }
        $url = 'ver.php?t=' . rawurlencode($photoToken) . '&download=inline';
        $thumb = $url; // la cabina no genera miniatura; el original ya es JPEG/PNG del kiosco
        $poster = null;
    } else {
        $accessToken = (string) ($row['access_token'] ?? '');
        if ($accessToken === '') {
            continue;
        }
        $url = 'ver-media.php?t=' . rawurlencode($accessToken);
        $thumb = $url . '&v=thumb';
        $poster = !empty($row['poster_storage_key']) ? $url . '&v=poster' : null;
    }

    $media[] = [
        // El id se publica porque la revista necesita referenciar la portada;
        // no es adivinable como acceso, el token sigue siendo la única llave.
        'id' => (int) $row['id'],
        'kind' => (string) $row['media_kind'],
        'source' => $source,
        'url' => $url,
        'thumb' => $thumb,
        'poster' => $poster,
        'width' => (int) $row['width'],
        'height' => (int) $row['height'],
        'duration' => $row['duration_seconds'] !== null ? (float) $row['duration_seconds'] : null,
        'author' => $row['contributor_name'] !== null ? (string) $row['contributor_name'] : null,
        'message' => $row['contributor_message'] !== null ? (string) $row['contributor_message'] : null,
    ];
}

$eventName = (string) ($party['nombre'] ?? '');
echo json_encode([
    'ok' => true,
    'album' => [
        'title' => $album['title'] !== null && $album['title'] !== ''
            ? (string) $album['title']
            : ($eventName !== '' ? 'Los recuerdos de ' . $eventName : 'Álbum Recuerdo'),
        'subtitle' => $album['subtitle'] !== null ? (string) $album['subtitle'] : null,
        'template' => (string) $album['template_key'],
        'coverId' => $album['cover_media_id'] !== null ? (int) $album['cover_media_id'] : null,
    ],
    'event' => [
        'name' => $eventName,
        'date' => (string) ($party['fecha'] ?? ''),
    ],
    'theme' => cb_album_api_theme((string) ($party['tema'] ?? '')),
    'media' => $media,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
