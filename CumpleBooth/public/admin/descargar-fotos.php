<?php
/**
 * admin/descargar-fotos.php — todas las fotos de una fiesta en un ZIP (pedido de Luis, 21-oct-2026).
 *
 * Trae dos carpetas: `cabina/` (lo que salió del kiosco y sigue en la galería; lo de la papelera no) y
 * `invitados/` (las fotos que mandaron por el Álbum, aprobadas o pendientes; lo oculto o eliminado no, porque
 * alguien decidió sacarlo). Los videos no van: pesan hasta 60 MB cada uno y se bajan de a uno desde el Álbum.
 *
 * Cada carpeta respeta su permiso: un operador con solo «Fotos del kiosco» recibe solo `cabina/`, y uno con solo
 * «Álbum» recibe solo `invitados/`. El portero (`_acceso.php`) ya exige que la fiesta sea suya.
 */
require __DIR__ . '/../lib.php';
require_once __DIR__ . '/../lib.zip.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/_acceso.php';   // el portero: sesión, usuario y permisos

function descargar_fotos_error(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    echo $mensaje;
    exit;
}

$slugRaw = is_string($_GET['party'] ?? null) ? (string) $_GET['party'] : '';
$slug = cb_valid_public_slug($slugRaw) ? $slugRaw : '';
$party = $slug !== '' ? cb_load_party_raw($slug) : null;
if ($party === null || cb_storage_mode() !== 'db') {
    descargar_fotos_error(404, 'Esa fiesta no existe.');
}
$conCabina = admin_puede('fotos', $slug);
$conInvitados = admin_puede('album', $slug);

$entradas = [];
if ($conCabina) {
    // Más antigua primero: el número del nombre sigue el orden de la fiesta.
    $fotos = array_reverse(cb_photos_admin($slug));
    $n = 0;
    foreach ($fotos as $f) {
        $ruta = cb_photo_absolute_path((string) $f['storage_key']);
        if ($ruta === null) {
            continue;
        }
        $ts = strtotime((string) $f['created_at']) ?: time();
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
        $entradas[] = ['nombre' => sprintf('cabina/%03d-%s.%s', ++$n, date('Y-m-d_Hi', $ts), $ext), 'ruta' => $ruta, 'fecha' => $ts];
    }
}
if ($conInvitados) {
    require_once __DIR__ . '/../lib.album.php';
    $partyId = cb_party_db_id($slug);
    $album = $partyId !== null ? cb_album_find_by_party($partyId) : null;
    if ($album !== null) {
        $n = 0;
        foreach (cb_album_list_media((int) $album['id'], ['approved', 'pending'], null, 'image') as $m) {
            if ((string) $m['source'] === 'booth') {
                continue; // es una foto de la cabina enlazada al álbum: ya va en cabina/
            }
            $ruta = cb_album_media_path((string) $m['storage_key']);
            if ($ruta === null) {
                continue;
            }
            $quien = cb_zip_slug((string) ($m['contributor_name'] ?? ''), 'invitado');
            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            $ts = strtotime((string) $m['created_at']) ?: time();
            $entradas[] = ['nombre' => sprintf('invitados/%03d-%s.%s', ++$n, $quien, $ext), 'ruta' => $ruta, 'fecha' => $ts];
        }
    }
}
if (!$entradas) {
    descargar_fotos_error(404, 'Esta fiesta todavía no tiene fotos para descargar.');
}

try {
    $zip = cb_zip_preparar($entradas);
} catch (RuntimeException $e) {
    descargar_fotos_error(413, $e->getMessage());
}
if (!$zip['entradas']) {
    descargar_fotos_error(404, 'Las fotos de esta fiesta no están en el servidor.');
}

$fecha = (string) ($party['fecha'] ?? '');
$archivo = 'fotos-' . cb_zip_slug((string) ($party['nombre'] ?? ''), 'fiesta')
    . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? '-' . $fecha : '') . '.zip';

// Sin compresión ni búfer: el ZIP sale a medida que se lee cada foto, y el tamaño exacto va en la cabecera
// para que el navegador muestre el avance. no-transform: el CDN no debe tocar el archivo.
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_clean();
}
@set_time_limit(0);
ignore_user_abort(false);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . $zip['total']);
header('Cache-Control: private, no-store, no-transform');
header('X-Accel-Buffering: no');
$salida = fopen('php://output', 'wb');
try {
    cb_zip_emitir($zip, $salida);
} catch (Throwable $e) {
    // Las cabeceras ya salieron: lo único honesto es cortar la descarga (el navegador la marca como fallida).
    error_log('CumpleClick admin/descargar-fotos.php: ' . $e->getMessage());
}
fclose($salida);
