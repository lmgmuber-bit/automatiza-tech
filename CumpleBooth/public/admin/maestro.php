<?php
/**
 * admin/maestro.php — la pantalla de solo contraseña de siempre, en su propia dirección
 * (2026-09-13). Entra como superadministrador sin fila en la tabla de usuarios (id 0).
 *
 * Existe para que Luis pueda entrar aunque el acceso por correo (`login.php`) fallara, o antes
 * de que la migración 023 esté aplicada. La contraseña es la misma de antes y `recuperar.php`
 * sigue sirviendo para cambiarla.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/config.php';

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
function maestro_csrf(): string
{
    if (empty($_SESSION['csrf_login'])) {
        $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_login'];
}
function maestro_csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf_login'] ?? '', $t);
}

if (!empty($_SESSION['admin_logged'])) {
    header('Location: index.php');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!maestro_csrf_ok()) {
        $loginError = 'Sesión expirada, intenta de nuevo.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $loginLimit = cb_rate_limit('admin-login', cb_request_identity(), 5, 900, 900);
        if (!$loginLimit['allowed']) {
            $loginError = 'Demasiados intentos. Intenta nuevamente más tarde.';
        } elseif (ADMIN_PASSWORD_HASH === '') {
            $loginError = 'El administrador aún no está configurado. Ejecuta scripts/bootstrap.php.';
        } elseif (password_verify($pass, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_usuario_id'] = 0;
            $_SESSION['admin_started'] = time();
            $_SESSION['admin_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            unset($_SESSION['csrf_login']);
            header('Location: index.php');
            exit;
        } else {
            $loginError = 'Contraseña incorrecta.';
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Clave maestra</title>
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
    <p class="muted small" style="text-align:center;margin:0">Entrada con la clave maestra del superadministrador.</p>
    <?php if ($loginError !== ''): ?>
      <p class="alert alert-error"><?= h($loginError) ?></p>
    <?php endif; ?>
    <form method="post" action="maestro.php" class="login-form">
      <input type="hidden" name="csrf" value="<?= h(maestro_csrf()) ?>">
      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required autofocus autocomplete="current-password" placeholder="••••••••">
      <button type="submit" class="btn btn-cta btn-block">Ingresar</button>
      <p class="muted small" style="margin-top:12px;text-align:center"><a href="recuperar.php">Olvidé la contraseña</a> · <a href="login.php">Entrar con mi correo</a></p>
    </form>
  </main>
</body>
</html>
