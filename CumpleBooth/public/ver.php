<?php
/** Vista/descarga de una foto por token opaco; mantiene compatibilidad con QR legacy. */
require __DIR__ . '/lib.php';

function cb_photo_page_error(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CumpleClick</title><body style="font-family:system-ui;text-align:center;padding:3rem;background:#1a1a1a;color:#fff"><h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
    exit;
}

$token = (string) ($_GET['t'] ?? '');
$legacyFile = basename((string) ($_GET['f'] ?? ''));
$legacyParty = (string) ($_GET['p'] ?? '');
$photo = null;
$path = null;
$partySlug = '';
$downloadName = 'foto-cumpleclick.png';
$imageUrl = '';
$isDiploma = false;
$isRecuerdito = false;

if ($token !== '') {
    $photo = cb_find_photo_by_token($token);
    if ($photo === null) {
        cb_photo_page_error(404, 'Foto no encontrada');
    }
    $partySlug = (string) ($photo['party_slug'] ?? $photo['party'] ?? '');
    $path = cb_photo_absolute_path((string) ($photo['storage_key'] ?? ''));
    $downloadName = (string) ($photo['original_name'] ?? $downloadName);
    $imageUrl = cb_public_base_url() . '/ver.php?t=' . rawurlencode($token) . '&amp;download=1';
    // El diploma se sube por el mismo endpoint que la foto; se distingue por el
    // nombre con el que lo envía el kiosco para no llamarlo "foto" en la página.
    $isDiploma = strncmp($downloadName, 'diploma-', 8) === 0;
    $isRecuerdito = strncmp($downloadName, 'recuerdito-', 11) === 0;
} elseif (cb_valid_slug($legacyParty, 1, 40) && preg_match('/^[A-Za-z0-9_-]+\.png$/', $legacyFile)) {
    $partySlug = $legacyParty;
    $legacyRoot = __DIR__ . '/fotos/' . $partySlug;
    $candidate = $legacyRoot . '/' . $legacyFile;
    $rootReal = realpath($legacyRoot);
    $fileReal = realpath($candidate);
    if ($rootReal === false || $fileReal === false || strpos($fileReal, $rootReal . DIRECTORY_SEPARATOR) !== 0) {
        cb_photo_page_error(404, 'Foto no encontrada');
    }
    $path = $fileReal;
    $downloadName = $legacyFile;
    $imageUrl = cb_public_base_url() . '/ver.php?p=' . rawurlencode($partySlug) . '&amp;f=' . rawurlencode($legacyFile) . '&amp;download=1';
} else {
    cb_photo_page_error(400, 'Enlace de foto inválido');
}

if ($path === null || !is_file($path)) {
    cb_photo_page_error(404, 'Foto no encontrada');
}
if (isset($_GET['download'])) {
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ((string) $_GET['download'] === 'inline' ? 'inline' : 'attachment') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $downloadName) . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

$party = cb_load_party_raw($partySlug);
if ($party === null) {
    cb_photo_page_error(404, 'Fiesta no encontrada');
}
$themesData = cb_load_themes();
$themeData = $themesData['themes'][$party['tema'] ?? ''] ?? [];
$colors = is_array($themeData['colors'] ?? null) ? $themeData['colors'] : [];
$name = (string) ($party['nombre'] ?? 'CumpleClick');
$eventType = (string) ($party['event_type'] ?? '') === 'baby_shower' ? 'baby_shower' : 'child_birthday';
$assetLabel = $isRecuerdito ? 'recuerdito' : ($isDiploma ? 'diploma' : 'foto');
$accent = (string) ($colors['accent'] ?? '#7C3AED');
$yellow = (string) ($colors['yellow'] ?? '#FBBF24');
$dark1 = (string) ($colors['dark1'] ?? '#1a1a1a');
$dark2 = (string) ($colors['dark2'] ?? '#312e81');
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"><title>Tu <?= htmlspecialchars($assetLabel) ?> · <?= htmlspecialchars($name) ?></title>
<style>*{box-sizing:border-box}body{min-height:100vh;margin:0;padding:24px 16px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;font-family:system-ui,sans-serif;background:linear-gradient(135deg,<?= htmlspecialchars($dark1) ?>,<?= htmlspecialchars($dark2) ?>);color:#fff}.title{color:<?= htmlspecialchars($yellow) ?>;font-size:clamp(1.3rem,5vw,1.8rem);font-weight:800;text-align:center}.photo{display:block;width:100%;max-width:480px;border-radius:18px;box-shadow:0 12px 40px #0008}.button{display:inline-flex;min-height:48px;align-items:center;padding:14px 30px;border-radius:999px;background:<?= htmlspecialchars($accent) ?>;color:#fff;text-decoration:none;font-weight:800}.button:focus-visible{outline:3px solid <?= htmlspecialchars($yellow) ?>;outline-offset:4px}.footer{text-align:center;opacity:.85}</style></head>
<body><h1 class="title"><?= $eventType === 'baby_shower' ? htmlspecialchars($name) : 'Fiesta de ' . htmlspecialchars($name) ?></h1><img class="photo" src="<?= $imageUrl ?>" alt="Tu <?= htmlspecialchars($assetLabel) ?>"><a class="button" href="<?= $imageUrl ?>">Guardar <?= htmlspecialchars($assetLabel) ?></a><p class="footer"><?= $isRecuerdito ? '¡Gracias por compartir tu predicción!' : '¡Gracias por venir!' ?></p><p class="footer" style="display:flex;align-items:center;gap:8px;font-weight:800"><img src="brand/cumpleclick-mark.svg" alt="" width="24" height="24">CumpleClick</p></body></html>
