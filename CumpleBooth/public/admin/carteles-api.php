<?php
/**
 * Datos de los carteles con QR de una fiesta.
 *
 * Cada fiesta tiene sus propios enlaces (galería, álbum, juego 3D, invitación) y su
 * temática, así que los carteles que se imprimen para una no sirven para otra. Esta API
 * arma la lista de los que corresponden a ESA fiesta: si no tiene galería con PIN, no
 * aparece el cartel de la galería; si su temática no tiene mundo 3D, no aparece el del juego.
 *
 * Va detrás de la sesión del admin: los enlaces incluyen el PIN de la galería, que es lo
 * único que separa las fotos de una fiesta de cualquiera que pase por la URL.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function carteles_responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['admin_logged'])) {
    carteles_responder(401, ['ok' => false, 'error' => 'no_autenticado']);
}
$idle = (int) cb_config('session_idle_seconds');
$absolute = (int) cb_config('session_absolute_seconds');
if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $idle || time() - (int) ($_SESSION['admin_started'] ?? 0) > $absolute) {
    carteles_responder(401, ['ok' => false, 'error' => 'sesion_expirada']);
}
$_SESSION['admin_seen'] = time();

$slug = isset($_GET['p']) && is_string($_GET['p']) ? $_GET['p'] : '';
if (!cb_valid_public_slug($slug)) {
    carteles_responder(400, ['ok' => false, 'error' => 'slug_invalido']);
}
$party = cb_load_party_raw($slug);
if (!$party) {
    carteles_responder(404, ['ok' => false, 'error' => 'no_existe']);
}

$base = rtrim((string) cb_public_base_url(), '/');
$temaSlug = (string) ($party['tema'] ?? $party['theme_slug'] ?? '');
$temas = cb_load_themes()['themes'] ?? [];
$tema = $temas[$temaSlug] ?? [];
$nombreNino = (string) ($party['nombre'] ?? $party['birthday_person_name'] ?? '');

// Solo las temáticas con mundo 3D ofrecen el juego (las mismas que el botón del kiosco).
$JUEGOS_3D = ['hielo' => 'Reino de Hielo en 3D', 'heroes' => 'Misión 3D', 'spidey' => 'Aventura Arácnida en 3D'];

$carteles = [];

// 1) Galería: el cartel que más se usa, para que los papás se lleven las fotos.
if (!empty($party['galeriaHabilitada'])) {
    $carteles[] = [
        'id' => 'galeria',
        'titulo' => 'Llévate las fotos',
        'bajada' => 'Escanea y mira todas las fotos de la fiesta. Descárgalas cuando quieras.',
        'url' => $base . '/galeria.php?p=' . rawurlencode($slug),
        'pie' => 'Te va a pedir un PIN',
        'necesitaPin' => true,
    ];
}

// El cartel para SUBIR fotos al Álbum Recuerdo no se arma aquí: su QR lleva un token de
// aporte que se emite de a uno (cb_album_issue_token) y vive en admin/album.php, que ya
// tiene su propio cartel imprimible. Duplicarlo acá emitiría un token nuevo por visita.

// 3) Juego 3D de la temática.
if (isset($JUEGOS_3D[$temaSlug])) {
    $carteles[] = [
        'id' => 'juego',
        'titulo' => $JUEGOS_3D[$temaSlug],
        'bajada' => 'Entra al mundo de la fiesta y juega con ' . $nombreNino . ' y sus invitados.',
        'url' => $base . '/juego/?p=' . rawurlencode($slug),
        'pie' => 'Se juega en el celular, la tablet o el computador',
        'necesitaPin' => false,
    ];
}

// 4) Invitación, si la fiesta ya tiene una emitida.
if (function_exists('cb_list_invitations') && function_exists('cb_invitation_share_token')
    && function_exists('cb_invitation_pretty_url') && cb_storage_mode() === 'db') {
    try {
        $partyId = cb_party_db_id($slug);
        $invs = $partyId !== null ? cb_list_invitations($partyId) : [];
        if ($invs) {
            $inv = $invs[0];
            $carteles[] = [
                'id' => 'invitacion',
                'titulo' => 'La invitación',
                'bajada' => 'Toda la información de la fiesta de ' . $nombreNino . ', en tu celular.',
                'url' => cb_invitation_pretty_url(
                    cb_invitation_share_token((int) $inv['id']),
                    (string) ($inv['birthday_person_name'] ?? $nombreNino)
                ),
                'pie' => '',
                'necesitaPin' => false,
            ];
        }
    } catch (Throwable $e) {
        error_log('CumpleClick carteles invitacion: ' . $e->getMessage());
    }
}

/**
 * Datos de contacto de la marca, los mismos que cierran el Álbum Recuerdo.
 *
 * Se leen acá y no con `cb_album_marca()` porque esa función vive dentro de
 * album-api.php, que al incluirlo ejecutaría su propio endpoint.
 */
function carteles_marca(): ?array
{
    $ruta = dirname(__DIR__) . '/data/marca.json';
    $crudo = is_file($ruta) ? json_decode((string) @file_get_contents($ruta), true) : null;
    if (!is_array($crudo)) {
        return null;
    }
    $limpio = [];
    foreach (['nombre', 'web', 'instagram', 'whatsapp', 'correo'] as $campo) {
        $valor = trim((string) ($crudo[$campo] ?? ''));
        if ($valor !== '') {
            $limpio[$campo] = $valor;
        }
    }
    return $limpio === [] ? null : $limpio;
}

$marca = carteles_marca();

carteles_responder(200, [
    'ok' => true,
    'fiesta' => [
        'slug' => $slug,
        'nombre' => $nombreNino,
        'fecha' => (string) ($party['fecha'] ?? ''),
        'tema' => $temaSlug,
        'temaNombre' => cb_theme_public_name($temaSlug),
    ],
    'tema' => [
        // Los colores de la temática, para que el cartel se vea de la fiesta y no genérico.
        'colors' => $tema['colors'] ?? null,
        // Ruta convencional de la temática; se publica solo si el archivo está en disco, para
        // no dejar una imagen rota en una hoja que se va a imprimir.
        'banner' => is_file(dirname(__DIR__) . '/themes/' . $temaSlug . '/fondo-banner.jpg')
            ? 'themes/' . $temaSlug . '/fondo-banner.jpg' : null,
    ],
    'carteles' => $carteles,
    'marca' => $marca,
]);
