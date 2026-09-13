<?php
/**
 * admin/perfil.php — mi perfil: nombre y contraseña (2026-09-13).
 *
 * Es la única página a la que puede ir alguien con contraseña temporal: el portero
 * (`_acceso.php`) lo manda acá hasta que la cambie. El correo es de solo lectura; se lo cambia
 * solo un superadministrador desde Usuarios. Quien entró con la clave maestra no tiene perfil:
 * esa clave se cambia con `recuperar.php`.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/../lib.admin-usuarios.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/_acceso.php';

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
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

$yo = admin_usuario_actual();
$parties = cb_load_parties()['parties'];
$errores = [];
$ok = $_SESSION['perfil_flash'] ?? '';
unset($_SESSION['perfil_flash']);
$pendiente = $yo['debe_cambiar'] || ($_GET['cambiar'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['action'] ?? '');
    if (!admin_csrf_check()) {
        $errores[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } elseif ($accion === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?motivo=salio');
        exit;
    } elseif ($yo['id'] === 0) {
        $errores[] = 'La clave maestra no tiene perfil: se cambia desde recuperar.php.';
    } elseif ($accion === 'nombre') {
        $r = cb_admin_usuario_renombrar($yo['id'], (string) ($_POST['nombre'] ?? ''));
        if ($r['ok']) {
            $_SESSION['perfil_flash'] = 'Nombre guardado.';
            header('Location: perfil.php');
            exit;
        }
        $errores = $r['errors'];
    } elseif ($accion === 'clave') {
        $r = cb_admin_usuario_cambiar_contrasena($yo['id'], (string) ($_POST['actual'] ?? ''),
            (string) ($_POST['nueva'] ?? ''), (string) ($_POST['repetida'] ?? ''));
        if ($r['ok']) {
            // Sesión nueva: si alguien tenía la temporal, ya no le sirve para nada.
            session_regenerate_id(true);
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            $_SESSION['perfil_flash'] = 'Contraseña cambiada. Ya puedes usar el panel.';
            header('Location: ' . ($yo['debe_cambiar'] ? 'index.php' : 'perfil.php'));
            exit;
        }
        $errores = $r['errors'];
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Mi perfil</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Admin</span>
    </div>
    <div class="inline-form logout-btn">
      <form method="post" action="perfil.php" class="inline-form">
        <?= admin_csrf_field() ?><input type="hidden" name="action" value="logout">
        <button class="btn btn-ghost" type="submit">Salir</button>
      </form>
    </div>
  </header>

  <?php if (!$yo['debe_cambiar']): ?><?= admin_nav('') ?><?php endif; ?>

  <main>
    <?php if ($ok !== ''): ?><p class="alert alert-ok"><?= h($ok) ?></p><?php endif; ?>
    <?php if ($errores): ?>
      <div class="alert alert-error"><ul><?php foreach ($errores as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($yo['id'] === 0): ?>
      <section class="card form-card">
        <h2>Clave maestra</h2>
        <p class="muted">Entraste con la clave maestra del superadministrador. No tiene nombre ni correo: para cambiarla usa <a href="recuperar.php">recuperar.php</a>. Si quieres entrar con tu correo, créate un usuario superadministrador en <a href="usuarios.php">Usuarios</a>.</p>
        <p><a class="btn btn-ghost" href="index.php">Volver a las fiestas</a></p>
      </section>
    <?php else: ?>
      <?php if ($pendiente): ?>
        <p class="alert alert-error">Tu contraseña es temporal: elige una tuya para seguir. Tiene que tener al menos <?= CB_ADMIN_CLAVE_MINIMA ?> caracteres.</p>
      <?php endif; ?>

      <section class="card form-card">
        <h2>Cambiar contraseña</h2>
        <form method="post" action="perfil.php" class="party-form">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="clave">
          <div class="field">
            <label for="actual"><?= $pendiente ? 'Contraseña temporal (la del correo)' : 'Contraseña actual' ?></label>
            <input type="password" id="actual" name="actual" required autocomplete="current-password" <?= $pendiente ? 'autofocus' : '' ?>>
          </div>
          <div class="field">
            <label for="nueva">Contraseña nueva</label>
            <input type="password" id="nueva" name="nueva" required minlength="<?= CB_ADMIN_CLAVE_MINIMA ?>" autocomplete="new-password">
            <small class="muted">Al menos <?= CB_ADMIN_CLAVE_MINIMA ?> caracteres. Una frase corta que recuerdes sirve mejor que una palabra rara.</small>
          </div>
          <div class="field">
            <label for="repetida">Repite la contraseña nueva</label>
            <input type="password" id="repetida" name="repetida" required minlength="<?= CB_ADMIN_CLAVE_MINIMA ?>" autocomplete="new-password">
          </div>
          <div class="form-actions"><button type="submit" class="btn btn-cta">Guardar contraseña</button></div>
        </form>
      </section>

      <?php if (!$pendiente): ?>
      <section class="card form-card">
        <h2>Mis datos</h2>
        <form method="post" action="perfil.php" class="party-form">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="nombre">
          <div class="field">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" value="<?= h($yo['nombre']) ?>" required maxlength="80">
          </div>
          <div class="field">
            <label for="correo">Correo</label>
            <input type="email" id="correo" value="<?= h($yo['email']) ?>" readonly>
            <small class="muted">Con este correo entras. Solo un superadministrador te lo puede cambiar.</small>
          </div>
          <div class="field">
            <label>Rol</label>
            <p style="margin:0"><?= h(CB_ADMIN_ROLES[$yo['rol']] ?? $yo['rol']) ?></p>
          </div>
          <?php if ($yo['rol'] !== 'super'): ?>
          <div class="field">
            <label>Mis fiestas</label>
            <p style="margin:0"><?php if (!$yo['fiestas']): ?><span class="muted">Ninguna todavía.</span><?php else: ?>
              <?php foreach ($yo['fiestas'] as $slug): $p = $parties[$slug] ?? null; ?><span class="badge badge-off"><?= h($p ? ($p['admin_label'] ?: ($p['nombre'] ?? $slug)) : $slug) ?></span> <?php endforeach; ?><?php endif; ?></p>
          </div>
          <div class="field">
            <label>Lo que puedo hacer</label>
            <p style="margin:0"><?= $yo['modulos'] ? h(implode(', ', array_map(static fn($m) => CB_ADMIN_MODULOS[$m]['nombre'], $yo['modulos']))) : '<span class="muted">Solo ver la ficha de mis fiestas.</span>' ?></p>
          </div>
          <?php endif; ?>
          <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar nombre</button></div>
        </form>
      </section>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
