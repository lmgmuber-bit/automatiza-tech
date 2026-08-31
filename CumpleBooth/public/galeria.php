<?php
/** Galería privada con PIN hasheado, sesión corta y rate limit persistente. */
require __DIR__ . '/lib.php';

$secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_gallery');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function gallery_h($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function gallery_csrf(): string
{
    if (empty($_SESSION['gallery_csrf'])) { $_SESSION['gallery_csrf'] = bin2hex(random_bytes(16)); }
    return (string) $_SESSION['gallery_csrf'];
}
function gallery_csrf_valid(): bool
{
    $sent = (string) ($_POST['csrf'] ?? '');
    return $sent !== '' && hash_equals((string) ($_SESSION['gallery_csrf'] ?? ''), $sent);
}
function gallery_message(int $status, string $title, string $message): void
{
    http_response_code($status);
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . gallery_h($title) . '</title><body style="font-family:system-ui;text-align:center;padding:3rem;background:#1a1a1a;color:#fff"><h1>' . gallery_h($title) . '</h1><p>' . gallery_h($message) . '</p></body></html>';
    exit;
}

$slug = (string) ($_GET['p'] ?? '');
if (!cb_valid_public_slug($slug)) { gallery_message(400, 'Galería no disponible', 'El enlace no es válido.'); }
$party = cb_load_party_raw($slug);
if ($party === null) { gallery_message(404, 'Galería no disponible', 'No encontramos esta fiesta.'); }
$pinEnabled = !empty($party['galeriaPinHash']) || !empty($party['galeriaPin']);
if (!$pinEnabled) { gallery_message(404, 'Galería no disponible', 'El organizador aún no habilitó la galería.'); }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!gallery_csrf_valid()) {
        $error = 'La sesión expiró. Recarga la página.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        unset($_SESSION['gallery_auth'][$slug]);
        session_regenerate_id(true);
        header('Location: galeria.php?p=' . rawurlencode($slug));
        exit;
    } elseif (($_POST['action'] ?? '') === 'login') {
        $limit = cb_rate_limit('gallery-pin:' . $slug, cb_request_identity(), 5, 60, 60);
        if (!$limit['allowed']) {
            header('Retry-After: ' . max(1, (int) $limit['retry_after']));
            $error = 'Demasiados intentos. Espera un minuto.';
        } elseif (cb_verify_party_pin($party, (string) ($_POST['pin'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['gallery_auth'][$slug] = time() + 1800;
            header('Location: galeria.php?p=' . rawurlencode($slug));
            exit;
        } else {
            $error = 'PIN incorrecto.';
        }
    }
}
$authenticated = (int) ($_SESSION['gallery_auth'][$slug] ?? 0) >= time();
if (!$authenticated) { unset($_SESSION['gallery_auth'][$slug]); }

$photos = [];
if ($authenticated) {
    foreach (cb_list_party_photos($slug) as $photo) {
        $path = cb_photo_absolute_path((string) ($photo['storage_key'] ?? ''));
        if ($path && is_file($path)) {
            $token = (string) ($photo['access_token'] ?? $photo['token'] ?? '');
            $photos[] = ['name' => (string) ($photo['original_name'] ?? 'foto.png'), 'path' => $path, 'view' => 'ver.php?t=' . rawurlencode($token)];
        }
    }
    // Solo lectura para instalaciones anteriores; no vuelve a exponer /fotos directamente.
    $legacyDir = __DIR__ . '/fotos/' . $slug;
    foreach (is_dir($legacyDir) ? (glob($legacyDir . '/*.png') ?: []) : [] as $path) {
        $name = basename($path);
        $photos[] = ['name' => $name, 'path' => $path, 'view' => 'ver.php?p=' . rawurlencode($slug) . '&f=' . rawurlencode($name)];
    }
}

if ($authenticated && isset($_GET['zip']) && class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'cczip_');
    $zip = new ZipArchive();
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) { gallery_message(500, 'Error', 'No se pudo preparar el archivo.'); }
    $used = [];
    foreach ($photos as $index => $photo) {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '-', basename($photo['name']));
        if (isset($used[$name])) { $name = ($index + 1) . '-' . $name; }
        $used[$name] = true;
        $zip->addFile($photo['path'], $name);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="fotos-' . $slug . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$themes = cb_load_themes();
$theme = $themes['themes'][$party['tema'] ?? ''] ?? [];
$colors = $theme['colors'] ?? [];
$accent = (string) ($colors['accent'] ?? '#7C3AED');
$dark1 = (string) ($colors['dark1'] ?? '#1a1a1a');
$dark2 = (string) ($colors['dark2'] ?? '#312e81');
$yellow = (string) ($colors['yellow'] ?? '#FBBF24');
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Galería · <?= gallery_h($party['nombre'] ?? '') ?></title>
<style>*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px 16px;font-family:system-ui,sans-serif;background:linear-gradient(135deg,<?= gallery_h($dark1) ?>,<?= gallery_h($dark2) ?>);color:#fff}.wrap{width:min(1080px,100%);margin:auto;text-align:center}h1{color:<?= gallery_h($yellow) ?>}.card{width:min(420px,100%);margin:2rem auto;padding:24px;border-radius:20px;background:#ffffff14}.pin{width:100%;min-height:52px;border:2px solid #fff5;border-radius:12px;text-align:center;font-size:1.5rem;letter-spacing:.5em}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 24px;border:0;border-radius:999px;background:<?= gallery_h($accent) ?>;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.btn:focus-visible,.pin:focus-visible{outline:3px solid <?= gallery_h($yellow) ?>;outline-offset:3px}.error{color:#fecaca}.toolbar{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin:1rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.photo{padding:10px;border-radius:18px;background:#fff;color:#222}.photo img{display:block;width:100%;aspect-ratio:9/16;object-fit:cover;border-radius:12px}.photo a{margin-top:10px}.muted{opacity:.8}</style></head><body><main class="wrap"><h1>Galería de la fiesta de <?= gallery_h($party['nombre'] ?? '') ?></h1>
<?php if (!$authenticated): ?><section class="card"><p>Ingresa el PIN de 4 dígitos entregado por el organizador.</p><?php if ($error): ?><p class="error"><?= gallery_h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= gallery_h(gallery_csrf()) ?>"><input type="hidden" name="action" value="login"><input class="pin" type="password" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" required autocomplete="one-time-code"><p><button class="btn" type="submit">Ver fotos</button></p></form></section>
<?php else: ?><div class="toolbar"><?php if ($photos && class_exists('ZipArchive')): ?><a class="btn" href="galeria.php?p=<?= rawurlencode($slug) ?>&amp;zip=1">Descargar todas</a><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= gallery_h(gallery_csrf()) ?>"><input type="hidden" name="action" value="logout"><button class="btn" type="submit">Cerrar galería</button></form></div><?php if (!$photos): ?><p class="muted">Todavía no hay fotos.</p><?php else: ?><div class="grid"><?php foreach ($photos as $photo): ?><article class="photo"><img src="<?= gallery_h($photo['view']) ?>&amp;download=inline" alt="Foto de la fiesta" loading="lazy"><a class="btn" href="<?= gallery_h($photo['view']) ?>&amp;download=1">Descargar</a></article><?php endforeach; ?></div><?php endif; ?><?php endif; ?><p style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:2.5rem;font-weight:800;opacity:.9"><img src="brand/cumpleclick-mark.svg" alt="" width="24" height="24">CumpleClick</p></main></body></html>
