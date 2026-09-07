<?php
/**
 * admin/recuperar.php — cambiar la contraseña del admin cuando se olvidó.
 *
 * No pide sesión, obviamente: es la pantalla para cuando no se puede entrar. Lo que la
 * protege es que el enlace se manda SIEMPRE al correo configurado en Ajustes —nunca a uno
 * escrito acá—, que dura media hora, que sirve una sola vez y que pedirlo está limitado por
 * IP. Sin correo de recuperación cargado, la pantalla no hace nada.
 *
 * La contraseña nueva no se escribe en la configuración del servidor: queda como hash en el
 * directorio de estado, fuera de la carpeta pública, y `admin/config.php` la prefiere.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/../lib.admin-password.php';
require __DIR__ . '/../lib.mail.php';
require __DIR__ . '/../lib.mail-templates.php';

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
function rec_csrf(): string
{
    if (empty($_SESSION['csrf_reset'])) { $_SESSION['csrf_reset'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf_reset'];
}
function rec_csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals($_SESSION['csrf_reset'] ?? '', $t);
}

/** Tapa el correo al mostrarlo: confirma cuál es sin publicarlo entero. */
function rec_tapar(string $correo): string
{
    [$usuario, $dominio] = array_pad(explode('@', $correo, 2), 2, '');
    if ($dominio === '') { return '···'; }
    $visible = mb_substr($usuario, 0, 2);
    return $visible . str_repeat('·', max(3, mb_strlen($usuario) - 2)) . '@' . $dominio;
}

$destino = cb_ajuste_recuperacion();
$token = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
$mensaje = '';
$errores = [];
$paso = $token !== '' ? 'cambiar' : 'pedir';
$listo = false;

// ── Pedir el enlace ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pedir') {
    if (!rec_csrf_ok()) {
        $errores[] = 'La página caducó. Vuelve a intentarlo.';
    } else {
        // Tres intentos por hora y por IP: pedir el enlace manda un correo, y sin límite
        // esto sería una forma cómoda de llenarle el buzón a Luis.
        $limite = cb_rate_limit('admin-reset', cb_request_identity(), 3, 3600, 3600);
        if (!$limite['allowed']) {
            $errores[] = 'Ya pediste el enlace varias veces. Espera un rato antes de reintentar.';
        } elseif ($destino === '') {
            $errores[] = 'No hay un correo de recuperación configurado. Hay que cargarlo en Ajustes, '
                . 'o cambiar la contraseña desde el servidor.';
        } else {
            $nuevo = cb_admin_reset_emitir();
            $url = rtrim((string) cb_public_base_url(), '/') . '/admin/recuperar.php?t=' . rawurlencode($nuevo);
            $texto = "Pediste cambiar la contraseña del admin de CumpleClick.\n\n"
                . "Entra acá para ponerle una nueva:\n$url\n\n"
                . "El enlace dura 30 minutos y sirve una sola vez.\n\n"
                . "Si no fuiste tú, ignora este correo: la contraseña no cambia hasta que alguien "
                . "abra ese enlace y escriba una nueva.\n";
            $html = cc_mail_shell('Recuperar la contraseña',
                '<p style="margin:0 0 16px">Pediste cambiar la contraseña del admin de CumpleClick.</p>'
                . '<p style="margin:0 0 18px"><a href="' . cc_mail_h($url) . '" '
                . 'style="display:inline-block;background:#7C3AED;color:#ffffff;text-decoration:none;'
                . 'padding:13px 26px;border-radius:999px;font-weight:700;font-size:15px">Poner una contraseña nueva</a></p>'
                . '<p style="margin:0 0 16px;font-size:14px;color:#6B6280">El enlace dura 30 minutos y sirve una sola vez.</p>'
                . '<p style="margin:0;font-size:13px;color:#6B6280">Si no fuiste tú, ignora este correo: '
                . 'la contraseña no cambia hasta que alguien abra ese enlace y escriba una nueva.</p>');
            $envio = cc_mail_send([
                'to' => $destino,
                'subject' => 'CumpleClick: cambiar la contraseña del admin',
                'text' => $texto,
                'html' => $html,
            ]);
            if (!empty($envio['ok'])) {
                $mensaje = 'Listo: te mandamos el enlace a ' . rec_tapar($destino) . '. Dura 30 minutos.';
                $listo = true;
            } else {
                $errores[] = 'No se pudo mandar el correo. Revisa la configuración de envío.';
            }
        }
    }
}

// ── Cambiar la contraseña ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cambiar') {
    $token = (string) ($_POST['token'] ?? '');
    $paso = 'cambiar';
    if (!rec_csrf_ok()) {
        $errores[] = 'La página caducó. Vuelve a abrir el enlace del correo.';
    } elseif (!cb_admin_reset_valido($token)) {
        $errores[] = 'El enlace ya no sirve: venció o ya se usó. Pide uno nuevo.';
        $paso = 'pedir';
    } else {
        $r = cb_admin_password_cambiar((string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
        if (!empty($r['ok'])) {
            // Se cierra cualquier sesión abierta: si alguien entró con la clave vieja, sale.
            $_SESSION = [];
            session_regenerate_id(true);
            $mensaje = 'Contraseña cambiada. Ya puedes entrar con la nueva.';
            $listo = true;
            $paso = 'fin';
            if ($destino !== '') {
                // Aviso de que cambio: si no fue Luis, se entera al toque.
                cc_mail_send([
                    'to' => $destino,
                    'subject' => 'CumpleClick: la contraseña del admin fue cambiada',
                    'text' => "La contraseña del admin de CumpleClick se cambió recién.\n\n"
                        . "Si fuiste tú, todo bien. Si no, entra ahora y cámbiala de nuevo.\n",
                    'html' => cc_mail_shell('Contraseña cambiada',
                        '<p style="margin:0 0 16px">La contraseña del admin de CumpleClick <strong>se cambió recién</strong>.</p>'
                        . '<p style="margin:0">Si fuiste tú, todo bien. Si no, entra ahora y cámbiala de nuevo.</p>'),
                ]);
            }
        } else {
            $errores = $r['errors'];
        }
    }
} elseif ($token !== '' && !cb_admin_reset_valido($token)) {
    $errores[] = 'El enlace ya no sirve: venció o ya se usó. Pide uno nuevo.';
    $paso = 'pedir';
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Recuperar la contraseña · CumpleBooth</title>
<style><?php require __DIR__ . '/_style.css.php'; ?></style>
</head>
<body class="login-body">
  <div class="login-card">
    <h1>CumpleBooth</h1>
    <p class="muted">Recuperar la contraseña</p>

    <?php foreach ($errores as $e): ?><p class="alert alert-error"><?= h($e) ?></p><?php endforeach; ?>
    <?php if ($mensaje !== ''): ?><p class="alert alert-ok"><?= h($mensaje) ?></p><?php endif; ?>

    <?php if ($paso === 'cambiar'): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(rec_csrf()) ?>">
        <input type="hidden" name="action" value="cambiar">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label for="password">Contraseña nueva</label>
        <input type="password" id="password" name="password" required autofocus autocomplete="new-password" minlength="10">
        <label for="password2">Repítela</label>
        <input type="password" id="password2" name="password2" required autocomplete="new-password" minlength="10">
        <p class="muted small">Al menos 10 caracteres. Esta clave abre el backoffice completo.</p>
        <button class="btn btn-primary" type="submit">Guardar la contraseña</button>
      </form>

    <?php elseif ($paso === 'fin'): ?>
      <a class="btn btn-primary" href="index.php">Ir a entrar</a>

    <?php else: ?>
      <?php if ($destino === ''): ?>
        <p class="muted">
          No hay un correo de recuperación configurado, así que no se puede mandar el enlace.
          Se carga en <strong>Ajustes</strong>, entrando al admin, o se cambia la contraseña
          desde el servidor.
        </p>
      <?php elseif (!$listo): ?>
        <p class="muted">
          Te mandamos un enlace a <strong><?= h(rec_tapar($destino)) ?></strong> para poner una
          contraseña nueva. Dura 30 minutos y sirve una sola vez.
        </p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(rec_csrf()) ?>">
          <input type="hidden" name="action" value="pedir">
          <button class="btn btn-primary" type="submit">Mandarme el enlace</button>
        </form>
      <?php endif; ?>
      <p style="margin-top:14px"><a href="index.php">Volver a entrar</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
