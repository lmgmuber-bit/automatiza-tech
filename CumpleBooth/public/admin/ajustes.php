<?php
/**
 * admin/ajustes.php — lo que vale para todo CumpleClick, no para una fiesta.
 *
 * Dos correos que son del administrador y no del cliente: a cual le llega copia oculta de
 * todo lo que sale, y a cual se manda el enlace cuando se olvida la contraseña. Van acá y no
 * en la configuración del servidor para que Luis los cambie cuando quiera, sin tocar nada del
 * hosting.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/config.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function admin_csrf_check(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf'] ?? '', $t);
}
function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(admin_csrf_token()) . '">';
}

/** Los mismos iconos de línea del resto del admin (index.php, leads.php). */
function admin_icon(string $name): string
{
    $paths = [
        'party' => '<path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01M22 8h.01M15 2h.01M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 10"/><path d="m22 13-.82-.33c-.86-.34-1.82.2-1.98 1.11v0c-.11.7-.72 1.22-1.43 1.22H16"/><path d="M11 2 9.89 4.11a2.9 2.9 0 0 0 .5 3.4l.5.5a2.9 2.9 0 0 1 .5 3.4L9 14"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
    ];
    $d = $paths[$name] ?? '';
    if ($d === '') { return ''; }
    return '<svg class="icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Glifo oficial de WhatsApp (relleno). Va aparte de admin_icon() porque ese dibuja
 *  iconos de línea con `stroke`, y el logo de una marca no se dibuja a mano alzada. */
function icono_whatsapp(int $tam = 18): string
{
    return '<svg viewBox="0 0 24 24" width="' . $tam . '" height="' . $tam . '" fill="currentColor" aria-hidden="true" focusable="false">'
        . '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/>'
        . '</svg>';
}

// ================== SESIÓN (mismo contrato que index.php y leads.php) ==================
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (admin_csrf_check()) {
        $_SESSION = [];
        session_destroy();
        header('Location: ajustes.php');
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!admin_csrf_check()) {
        $loginError = 'Sesión expirada, intenta de nuevo.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $loginLimit = cb_rate_limit('admin-login', cb_request_identity(), 5, 900, 900);
        if (!$loginLimit['allowed']) {
            $loginError = 'Demasiados intentos. Intenta nuevamente más tarde.';
        } elseif (!defined('ADMIN_PASSWORD_HASH') || ADMIN_PASSWORD_HASH === '') {
            $loginError = 'El administrador aún no está configurado. Ejecuta scripts/bootstrap.php.';
        } elseif (password_verify($pass, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_started'] = time();
            $_SESSION['admin_seen'] = time();
            header('Location: ajustes.php');
            exit;
        } else {
            $loginError = 'Contraseña incorrecta.';
        }
    }
}

$loggedIn = !empty($_SESSION['admin_logged']);
if ($loggedIn) {
    $idle = (int) cb_config('session_idle_seconds');
    $absolute = (int) cb_config('session_absolute_seconds');
    if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > $idle || time() - (int) ($_SESSION['admin_started'] ?? 0) > $absolute) {
        $_SESSION = [];
        session_destroy();
        $loggedIn = false;
        $loginError = 'La sesión expiró. Ingresa nuevamente.';
    } else {
        $_SESSION['admin_seen'] = time();
    }
}

if (!$loggedIn) {
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Ingresar</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
</head>
<body class="login-body">
  <form class="login-card" method="post" action="ajustes.php">
    <h1>CumpleBooth</h1>
    <p class="muted">Ajustes generales</p>
    <?php if ($loginError !== ''): ?><p class="alert alert-error"><?= h($loginError) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
    <button class="btn btn-primary" type="submit">Ingresar</button>
    <p class="muted small" style="margin-top:12px;text-align:center"><a href="recuperar.php">Olvidé la contraseña</a></p>
  </form>
</body>
</html><?php
    exit;
}


// ================== DATOS ==================
require_once __DIR__ . '/../lib.ajustes.php';

$aviso = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar') {
    if (!admin_csrf_check()) {
        $errores[] = 'Sesión expirada, vuelve a intentarlo.';
    } else {
        $r = cb_guardar_ajustes([
            'bcc_email' => $_POST['bcc_email'] ?? '',
            'recovery_email' => $_POST['recovery_email'] ?? '',
        ]);
        if (!empty($r['ok'])) {
            $aviso = 'Ajustes guardados.';
        } else {
            $errores = $r['errors'];
        }
    }
}

$ajustes = cb_ajustes();
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ajustes · CumpleBooth</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div>
      <h1>Ajustes generales</h1>
      <p class="muted">Valen para todo CumpleClick: no dependen de la fiesta ni de la temática.</p>
    </div>
    <form method="post" class="inline-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="logout">
      <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
    </form>
  </header>

  <nav class="tabs">
    <a class="tab" href="index.php"><?= admin_icon('party') ?> Fiestas</a>
    <a class="tab" href="index.php?view=temas"><?= admin_icon('palette') ?> Temáticas</a>
    <a class="tab" href="leads.php"><?= admin_icon('party') ?> Solicitudes</a>
    <a class="tab" href="mensajes.php"><?= admin_icon('chat') ?> Mensajes</a>
    <a class="tab" href="comprobante.php"><?= admin_icon('copy') ?> Comprobante</a>
    <a class="tab" href="planes.php"><?= admin_icon('copy') ?> Planes</a>
    <a class="tab active" href="ajustes.php"><?= admin_icon('copy') ?> Ajustes</a>
  </nav>

  <?php if ($aviso !== ''): ?><p class="alert alert-ok"><?= h($aviso) ?></p><?php endif; ?>
  <?php foreach ($errores as $e): ?><p class="alert alert-error"><?= h($e) ?></p><?php endforeach; ?>

  <form method="post">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="guardar">

    <section class="card">
      <h2>Copia oculta de los correos</h2>
      <p class="muted">
        Cada correo que sale de CumpleClick —bienvenida, solicitudes, Términos firmados,
        comprobante— llega también a esta dirección, en copia oculta. El cliente no la ve.
        Déjala vacía para no mandar copia de nada.
      </p>
      <label class="field">Correo que recibe copia
        <input type="email" name="bcc_email" value="<?= h($ajustes['bcc_email']) ?>"
               placeholder="tucorreo@ejemplo.com" autocomplete="off">
      </label>
    </section>

    <section class="card">
      <h2>Recuperar la contraseña</h2>
      <p class="muted">
        Si olvidas la contraseña del admin, el enlace para cambiarla se manda <strong>solo a esta
        dirección</strong>, nunca a una escrita en el formulario. Sin esto cargado, la
        recuperación queda apagada y hay que cambiarla por el servidor.
      </p>
      <label class="field">Correo de recuperación
        <input type="email" name="recovery_email" value="<?= h($ajustes['recovery_email']) ?>"
               placeholder="tucorreo@ejemplo.com" autocomplete="off">
      </label>
    </section>

    <div class="party-actions">
      <button class="btn btn-primary" type="submit">Guardar ajustes</button>
    </div>
  </form>
</div>
</body>
</html>
