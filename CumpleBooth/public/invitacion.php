<?php
/**
 * invitacion.php — página pública mínima de una invitación publicada.
 * Requiere token opaco vía GET ?t=<token>. Solo muestra outputs aprobados de
 * una invitación en estado `published` y no expirada. No expone IDs internos,
 * rutas físicas, prompts ni ninguna información administrativa.
 */
require __DIR__ . '/lib.php';

function cb_invitation_page_error(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<title>CumpleClick</title></head>'
        . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:3rem;background:#1a1a1a;color:#fff">'
        . '<h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
    exit;
}

header('X-Robots-Tag: noindex, nofollow');

$token = (string) ($_GET['t'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    cb_invitation_page_error(400, 'Enlace de invitación inválido.');
}

try {
    $invitation = cb_load_invitation_by_token_hash(cb_hash_token($token));
} catch (Throwable $e) {
    error_log('CumpleClick invitacion.php: ' . $e->getMessage());
    cb_invitation_page_error(503, 'Servicio no disponible por el momento.');
}

if (!$invitation) {
    cb_invitation_page_error(404, 'Invitación no encontrada.');
}
if ((string) $invitation['status'] !== 'published') {
    cb_invitation_page_error(404, 'Esta invitación todavía no está disponible.');
}
if (!empty($invitation['expires_at']) && strtotime((string) $invitation['expires_at']) < time()) {
    cb_invitation_page_error(410, 'Este enlace de invitación ya expiró.');
}

$imageOutputs = cb_invitation_approved_outputs((int) $invitation['id'], 'personalized_image');
if (!$imageOutputs) {
    // Publicada sin imagen aprobada no debería ocurrir (cb_publish_invitation lo exige),
    // pero se valida de nuevo acá por defensa en profundidad antes de mostrar nada.
    cb_invitation_page_error(404, 'Esta invitación todavía no está disponible.');
}
$hasVideo = (bool) cb_invitation_approved_outputs((int) $invitation['id'], 'personalized_video');

$themesData = cb_load_themes();
$themeSlug = (string) ($invitation['theme_slug'] ?? '');
$themeData = is_array($themesData['themes'][$themeSlug] ?? null) ? $themesData['themes'][$themeSlug] : [];
$colors = is_array($themeData['colors'] ?? null) ? $themeData['colors'] : [];
$accent = (string) ($colors['accent'] ?? '#7C3AED');
$yellow = (string) ($colors['yellow'] ?? '#FBBF24');
$dark1 = (string) ($colors['dark1'] ?? '#1a1a1a');
$dark2 = (string) ($colors['dark2'] ?? '#312e81');
$ink = (string) ($colors['ink'] ?? '#1a1a1a');
$bgLight1 = (string) ($colors['bgLight1'] ?? '#fff');
$bgLight2 = (string) ($colors['bgLight2'] ?? '#fff');

$birthdayName = (string) ($invitation['birthday_person_name'] ?? '');
$eventDate = (string) ($invitation['event_date'] ?? '');
$eventTime = (string) ($invitation['event_time'] ?? '');
$address = (string) ($invitation['address'] ?? '');
$message = (string) ($invitation['message'] ?? '');

$imageUrl = cb_invitation_download_url($token, 'image');
$videoUrl = $hasVideo ? cb_invitation_download_url($token, 'video') : null;

$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>¡<?= $esc($birthdayName !== '' ? $birthdayName : 'Estás invitado') ?> está de cumpleaños! · CumpleClick</title>
<style>
*{box-sizing:border-box}
body{min-height:100vh;margin:0;padding:24px 16px;display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:18px;font-family:system-ui,sans-serif;
  background:linear-gradient(135deg,<?= $esc($bgLight1) ?>,<?= $esc($bgLight2) ?>);color:<?= $esc($ink) ?>}
.title{color:<?= $esc($dark2) ?>;font-size:clamp(1.3rem,5vw,1.8rem);font-weight:800;text-align:center;margin:0}
.card{width:100%;max-width:420px;border-radius:20px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.25);
  background:<?= $esc($dark1) ?>}
.invite-img{display:block;width:100%;height:auto}
.invite-video{display:block;width:100%;height:auto;background:#000}
.details{width:100%;max-width:420px;background:#fff;border-radius:16px;padding:18px 20px;
  box-shadow:0 6px 20px rgba(0,0,0,.1);text-align:left}
.details dt{font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:<?= $esc($accent) ?>;
  margin-top:10px}
.details dt:first-child{margin-top:0}
.details dd{margin:2px 0 0;font-size:16px;font-weight:600}
.actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:center}
.button{display:inline-flex;min-height:48px;align-items:center;padding:14px 26px;border-radius:999px;
  background:<?= $esc($accent) ?>;color:#fff;text-decoration:none;font-weight:800}
.button:focus-visible{outline:3px solid <?= $esc($yellow) ?>;outline-offset:4px}
.footer{text-align:center;opacity:.75;font-size:13px}
</style></head>
<body>
<h1 class="title">¡<?= $esc($birthdayName !== '' ? $birthdayName : 'Estás invitado') ?> está de cumpleaños!</h1>
<div class="card">
<img class="invite-img" src="<?= $esc($imageUrl) ?>" alt="Invitación de cumpleaños">
<?php if ($hasVideo): ?>
<video class="invite-video" src="<?= $esc((string) $videoUrl) ?>" controls playsinline loop muted></video>
<?php endif; ?>
</div>
<dl class="details">
<?php if ($eventDate !== ''): ?><dt>Fecha</dt><dd><?= $esc($eventDate) ?></dd><?php endif; ?>
<?php if ($eventTime !== ''): ?><dt>Hora</dt><dd><?= $esc($eventTime) ?></dd><?php endif; ?>
<?php if ($address !== ''): ?><dt>Dirección</dt><dd><?= $esc($address) ?></dd><?php endif; ?>
<?php if ($message !== ''): ?><dt>Mensaje</dt><dd><?= $esc($message) ?></dd><?php endif; ?>
</dl>
<div class="actions">
<a class="button" href="<?= $esc($imageUrl) ?>" download>Descargar imagen</a>
<?php if ($hasVideo): ?><a class="button" href="<?= $esc((string) $videoUrl) ?>" download>Descargar video</a><?php endif; ?>
</div>
<p class="footer">CumpleClick by AutomatizaTech</p>
</body></html>
