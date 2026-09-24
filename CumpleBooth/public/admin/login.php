<?php
/**
 * admin/login.php — entrar al backoffice con correo y contraseña (2026-09-13).
 *
 * Es la entrada por defecto: cualquier página del admin sin sesión manda acá. La clave maestra
 * de siempre sigue viva en `maestro.php`, por si esto fallara.
 *
 * Límites: 5 intentos por 15 minutos por IP (compartido con la clave maestra) y 10 por hora por
 * correo. Un correo que no existe y una contraseña equivocada dan el mismo mensaje, para no
 * confirmar qué correos tienen cuenta.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/../lib.admin-usuarios.php';

$secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function login_csrf(): string
{
    if (empty($_SESSION['csrf_login'])) {
        $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_login'];
}
function login_csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf_login'] ?? '', $t);
}
/** Solo se vuelve a una página del propio admin: `volver` viene por la URL y podría ser cualquier cosa. */
function login_volver_limpio($raw): string
{
    $v = is_string($raw) ? $raw : '';
    return preg_match('/^[a-z-]+[.]php([?][^#]{0,300})?$/', $v) === 1 && $v !== 'login.php' && $v !== 'maestro.php' ? $v : 'index.php';
}

// Con sesión viva no hay nada que pedir.
if (!empty($_SESSION['admin_logged'])) {
    header('Location: ' . login_volver_limpio($_GET['volver'] ?? ''));
    exit;
}

$volver = login_volver_limpio($_GET['volver'] ?? ($_POST['volver'] ?? ''));
$error = '';
$aviso = '';
$listo = cb_admin_usuarios_listo();
switch ((string) ($_GET['motivo'] ?? '')) {
    case 'expiro':
        $aviso = 'La sesión venció. Entra de nuevo.';
        break;
    case 'deshabilitado':
        $aviso = 'Tu acceso está deshabilitado o ya no existe. Habla con quien te lo dio.';
        break;
    case 'salio':
        $aviso = 'Saliste del panel.';
        break;
}
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = cb_admin_normalizar_correo((string) ($_POST['correo'] ?? ''));
    $clave = (string) ($_POST['password'] ?? '');
    if (!login_csrf_ok()) {
        $error = 'La página caducó. Vuelve a intentarlo.';
    } elseif (!$listo) {
        $error = 'El acceso por correo todavía no está habilitado en este servidor. Entra con la clave maestra.';
    } elseif ($correo === '' || $clave === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } else {
        $porIp = cb_rate_limit('admin-login', cb_request_identity(), 5, 900, 900);
        $porCorreo = cb_rate_limit('admin-login-correo', hash('sha256', $correo), 10, 3600, 900);
        if (!$porIp['allowed'] || !$porCorreo['allowed']) {
            $error = 'Demasiados intentos. Espera un rato y vuelve a intentar.';
        } else {
            $r = cb_admin_login_verificar($correo, $clave);
            if (!empty($r['ok'])) {
                $usuario = $r['usuario'];
                session_regenerate_id(true);
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_usuario_id'] = (int) $usuario['id'];
                $_SESSION['admin_started'] = time();
                $_SESSION['admin_seen'] = time();
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                unset($_SESSION['csrf_login']);
                header('Location: ' . ($usuario['debe_cambiar'] ? 'perfil.php?cambiar=1' : $volver));
                exit;
            }
            $error = ($r['error'] ?? '') === 'deshabilitado'
                ? 'Tu acceso está deshabilitado. Habla con quien te lo dio.'
                : 'Correo o contraseña incorrectos.';
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Entrar</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body class="login-body">
  <main class="login-card">
    <div class="login-logo">
      <img src="../brand/cumpleclick-mark.svg" alt="" width="88" height="88">
      CumpleClick <span>Admin</span>
    </div>
    <?php if ($aviso !== ''): ?>
      <p class="alert alert-ok"><?= h($aviso) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <p class="alert alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php" class="login-form">
      <input type="hidden" name="csrf" value="<?= h(login_csrf()) ?>">
      <input type="hidden" name="volver" value="<?= h($volver) ?>">
      <label for="correo">Correo</label>
      <div class="input-icon">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        <input type="email" id="correo" name="correo" value="<?= h($correo) ?>" required autofocus autocomplete="username" placeholder="tu@correo.cl" inputmode="email" autocapitalize="none" spellcheck="false">
      </div>
      <label for="password">Contraseña</label>
      <div class="input-icon">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••">
      </div>
      <button type="submit" class="btn btn-cta btn-block">Entrar</button>
      <p class="muted small" style="margin-top:12px;text-align:center">¿Olvidaste tu contraseña? Pídele una nueva a quien te dio el acceso.</p>
      <p class="muted small" style="margin-top:4px;text-align:center"><a href="maestro.php">Entrar con la clave maestra</a></p>
    </form>
  </main>
</body>
</html>
