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
// Mismo token que usan los formularios del admin; si la sesión todavía no tiene uno se crea
// acá igual que en admin/index.php, para poder pedir el enlace de aportes desde esta pantalla.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

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
$tokenAlbum = '';
$avisoAlbum = '';

// Emisión del token de aportes del Álbum, solo por POST y con el CSRF del admin: es una
// acción con consecuencia (revoca el token anterior), no algo que pase por mirar la página.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'album-token') {
    $enviado = (string) ($_POST['csrf'] ?? '');
    if ($enviado === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $enviado)) {
        carteles_responder(403, ['ok' => false, 'error' => 'csrf_invalido']);
    }
    try {
        $partyId = cb_party_db_id($slug);
        $albumPost = $partyId !== null ? cb_album_find_by_party($partyId) : null;
        if (!$albumPost) {
            carteles_responder(409, ['ok' => false, 'error' => 'sin_album']);
        }
        // El vencimiento se cuenta desde la fecha del evento y no desde hoy: un cartel
        // impreso una semana antes tiene que seguir sirviendo el día de la fiesta.
        $limites = cb_album_limits();
        $fecha = (string) ($party['fecha'] ?? '');
        $desde = $fecha !== '' ? strtotime($fecha) : false;
        if ($desde === false) { $desde = time(); }
        $expira = gmdate('Y-m-d H:i:s', $desde + ((int) $limites['default_open_days']) * 86400);
        $tokenAlbum = cb_album_issue_token((int) $albumPost['id'], 'intake', $expira, 'admin-carteles');
        if ((string) ($albumPost['status'] ?? '') === 'draft') {
            cb_album_update((int) $albumPost['id'], ['status' => 'collecting']);
        }
        $avisoAlbum = 'Enlace de aportes nuevo: el anterior quedó revocado y los carteles del Álbum impresos antes '
            . 'ya no sirven. Imprime este cartel ahora: al recargar la página el enlace no se puede volver a mostrar.';
    } catch (Throwable $e) {
        error_log('CumpleClick carteles token album: ' . $e->getMessage());
        carteles_responder(500, ['ok' => false, 'error' => 'no_se_pudo_emitir']);
    }
}

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

// 2) Álbum Recuerdo: el QR lleva el token de APORTE, que se emite de a uno y en base solo
// queda su huella. Por eso no se arma solo al abrir la pantalla —cada visita revocaría el
// anterior y dejaría muertos los carteles ya impresos—: hay que pedirlo explícitamente por
// POST, y recién ahí este cartel aparece con su enlace.
$album = null;
$albumEstado = ['existe' => false, 'abierto' => false, 'motivo' => 'sin_album'];
if (cb_storage_mode() === 'db' && function_exists('cb_album_find_by_party')) {
    try {
        $partyIdAlbum = cb_party_db_id($slug);
        $album = $partyIdAlbum !== null ? cb_album_find_by_party($partyIdAlbum) : null;
        if ($album) {
            $abierto = cb_album_intake_open($album, $party);
            // Del token activo solo se puede saber CUÁNDO se emitió: en base queda su huella,
            // no el enlace. Alcanza para avisar que generar otro deja muerto al anterior.
            $vivo = function_exists('cb_album_active_token_info')
                ? cb_album_active_token_info((int) $album['id'], 'intake') : null;
            $albumEstado = [
                'existe' => true,
                'abierto' => $abierto,
                'motivo' => $abierto ? '' : (empty($party['activa']) ? 'fiesta_inactiva' : 'aportes_cerrados'),
                'enlaceVivo' => $vivo ? [
                    'creado' => (string) ($vivo['created_at'] ?? ''),
                    'vence' => (string) ($vivo['expires_at'] ?? ''),
                ] : null,
            ];
        }
    } catch (Throwable $e) {
        error_log('CumpleClick carteles album: ' . $e->getMessage());
    }
}

// Si en esta misma petición se pidió el token, el cartel sale con su enlace ya listo.
if ($tokenAlbum !== '') {
    $carteles[] = [
        'id' => 'album',
        'titulo' => 'Suma tus fotos',
        // El texto sigue a la configuración del álbum: prometer videos en un cartel impreso
        // cuando la recepción solo acepta fotos deja al invitado con un error en la mano.
        'bajada' => 'Escanea y sube tus ' . (!empty($album['intake_videos']) ? 'fotos y videos' : 'fotos')
            . ' del cumpleaños de ' . $nombreNino . ' al Álbum Recuerdo.',
        'url' => cb_album_intake_url($tokenAlbum),
        'pie' => 'Se suben desde tu celular, sin instalar nada',
        'necesitaPin' => false,
    ];
}

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

// 5) Cartel de la marca: no lleva un enlace de la fiesta sino el de nuestras redes. Va con
// la temática igual que los demás, para que en la mesa se vea como parte de la decoración
// y no como publicidad pegada aparte.
$redes = '';
foreach (['instagram_url', 'web_url'] as $campo) {
    $valor = trim((string) ($marca[$campo] ?? ''));
    if ($valor === '' && $campo === 'instagram_url' && !empty($marca['instagram'])) {
        $valor = 'https://instagram.com/' . ltrim((string) $marca['instagram'], '@');
    }
    if ($valor === '' && $campo === 'web_url' && !empty($marca['web'])) {
        $valor = 'https://' . ltrim((string) $marca['web'], '/');
    }
    if ($valor !== '') { $redes = $valor; break; }
}
if ($redes !== '') {
    $carteles[] = [
        'id' => 'marca',
        'titulo' => '¿Lo quieres en tu fiesta?',
        'bajada' => 'Cabina de fotos, álbum de recuerdos y juego 3D para cumpleaños. '
            . 'Escanea y mira todo lo que hacemos.',
        'url' => $redes,
        'pie' => trim(($marca['instagram'] ?? '') . '  ·  ' . ($marca['correo'] ?? ''), ' ·'),
        'necesitaPin' => false,
    ];
}

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
    'album' => $albumEstado,
    'avisoAlbum' => $avisoAlbum,
    // El token del formulario del admin, para poder pedir el enlace de aportes desde acá.
    'csrf' => (string) ($_SESSION['csrf'] ?? ''),
]);
