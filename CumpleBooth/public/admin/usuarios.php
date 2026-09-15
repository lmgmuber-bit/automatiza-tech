<?php
/**
 * admin/usuarios.php — la tabla maestra de usuarios del backoffice (2026-09-13).
 *
 * Solo para el superadministrador. Crea usuarios (les llega un correo con contraseña temporal),
 * los habilita o deshabilita, y decide qué fiestas y qué módulos ve cada uno. No se borra: se
 * deshabilita, así el historial queda y no hay forma de borrarse a uno mismo por error.
 *
 * Si el correo no sale (SMTP caído o sin configurar), la contraseña temporal se muestra UNA
 * vez en pantalla para pasarla a mano. Después de esa vista no queda en ningún lado.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/../lib.admin-usuarios.php';
require_once __DIR__ . '/../lib.mail.php';
require_once __DIR__ . '/../lib.mail-templates.php';

$adminSecureCookie = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
session_name('cc_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $adminSecureCookie, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
require __DIR__ . '/_acceso.php';
admin_exigir_super();

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
$listo = cb_admin_usuarios_listo();
$parties = cb_load_parties()['parties'];
/** Etiqueta corta de una fiesta para las casillas y la tabla. */
function usuarios_fiesta_etiqueta(array $parties, string $slug): string
{
    $p = $parties[$slug] ?? null;
    if ($p === null) {
        return $slug;
    }
    $nombre = (string) ($p['admin_label'] ?: ($p['nombre'] ?? $slug));
    return $nombre . ' · ' . cb_theme_public_name((string) ($p['tema'] ?? ''));
}
/** Manda el correo de bienvenida o el de contraseña nueva. Devuelve lo que diga cc_mail_send. */
function usuarios_enviar_correo(array $usuario, string $temporal, bool $reinicio): array
{
    global $parties, $yo;
    $nombres = [];
    foreach ($usuario['fiestas'] as $slug) {
        $p = $parties[$slug] ?? null;
        $nombres[] = $p ? (string) ($p['admin_label'] ?: ($p['nombre'] ?? $slug)) : $slug;
    }
    $quien = $yo['id'] > 0 && $yo['nombre'] !== '' ? $yo['nombre'] : 'El administrador de CumpleClick';
    $urlLogin = rtrim((string) cb_public_base_url(), '/') . '/admin/login.php';
    $m = cb_admin_correo_bienvenida($usuario, $temporal, $urlLogin, $nombres, $quien, $reinicio);
    return cc_mail_send(['to' => $usuario['email'], 'subject' => $m['subject'], 'text' => $m['text'], 'html' => $m['html']]);
}

// ── Acciones ────────────────────────────────────────────────────────────────
$flash = $_SESSION['usuarios_flash'] ?? null;
unset($_SESSION['usuarios_flash']);
$errores = [];
$formulario = null;   // lo que se vuelve a mostrar si el guardado falla

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['action'] ?? '');
    if (!admin_csrf_check()) {
        $errores[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } elseif (!$listo) {
        $errores[] = 'Los usuarios necesitan la base de datos con la migración 023 aplicada.';
    } elseif ($accion === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php?motivo=salio');
        exit;
    } elseif ($accion === 'crear') {
        $r = cb_admin_usuario_crear($_POST, $yo['id']);
        if ($r['ok']) {
            $nuevo = cb_admin_usuario_por_id((int) $r['id']);
            $envio = usuarios_enviar_correo($nuevo, $r['temporal'], false);
            $_SESSION['usuarios_flash'] = [
                'ok' => 'Usuario creado: ' . $nuevo['nombre'] . ' (' . $nuevo['email'] . ').',
                'correo' => !empty($envio['ok']),
                'correo_error' => (string) ($envio['error'] ?? ''),
                'temporal' => !empty($envio['ok']) ? '' : $r['temporal'],
                'email' => $nuevo['email'],
            ];
            header('Location: usuarios.php');
            exit;
        }
        $errores = $r['errors'];
        $formulario = $_POST;
    } elseif ($accion === 'actualizar') {
        $id = (int) ($_POST['id'] ?? 0);
        $datos = $_POST;
        // Nadie se quita a sí mismo el rol de superadministrador: se quedaría afuera de Usuarios.
        if ($id === $yo['id'] && cb_admin_rol_limpiar($datos['rol'] ?? '') !== 'super') {
            $datos['rol'] = 'super';
        }
        $r = cb_admin_usuario_actualizar($id, $datos);
        if ($r['ok']) {
            $_SESSION['usuarios_flash'] = ['ok' => 'Accesos guardados.'];
            header('Location: usuarios.php');
            exit;
        }
        $errores = $r['errors'];
        $formulario = $_POST;
    } elseif ($accion === 'activar' || $accion === 'desactivar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($accion === 'desactivar' && $id === $yo['id']) {
            $errores[] = 'No puedes deshabilitar tu propio acceso.';
        } elseif (cb_admin_usuario_activar($id, $accion === 'activar')) {
            $_SESSION['usuarios_flash'] = ['ok' => $accion === 'activar' ? 'Acceso habilitado.' : 'Acceso deshabilitado: ya no puede entrar.'];
            header('Location: usuarios.php');
            exit;
        } else {
            $errores[] = 'Ese usuario no existe.';
        }
    } elseif ($accion === 'reiniciar') {
        $id = (int) ($_POST['id'] ?? 0);
        $r = cb_admin_usuario_reiniciar_contrasena($id);
        if ($r['ok']) {
            $usuario = cb_admin_usuario_por_id($id);
            $envio = usuarios_enviar_correo($usuario, $r['temporal'], true);
            $_SESSION['usuarios_flash'] = [
                'ok' => 'Contraseña temporal nueva para ' . $usuario['nombre'] . '. La anterior ya no sirve.',
                'correo' => !empty($envio['ok']),
                'correo_error' => (string) ($envio['error'] ?? ''),
                'temporal' => !empty($envio['ok']) ? '' : $r['temporal'],
                'email' => $usuario['email'],
            ];
            header('Location: usuarios.php');
            exit;
        }
        $errores = $r['errors'];
    }
}

// ── Datos para dibujar ──────────────────────────────────────────────────────
$usuarios = $listo ? cb_admin_usuarios() : [];
$editando = null;
if ($formulario === null && isset($_GET['editar'])) {
    $editando = cb_admin_usuario_por_id((int) $_GET['editar']);
}
// Valores del formulario: lo que se estaba escribiendo, el usuario que se edita, o vacío.
if ($formulario !== null) {
    $fv = [
        'id' => (int) ($formulario['id'] ?? 0), 'nombre' => (string) ($formulario['nombre'] ?? ''),
        'correo' => (string) ($formulario['correo'] ?? ''), 'rol' => cb_admin_rol_limpiar($formulario['rol'] ?? ''),
        'modulos' => cb_admin_modulos_limpiar($formulario['modulos'] ?? []),
        'fiestas' => is_array($formulario['fiestas'] ?? null) ? array_map('strval', $formulario['fiestas']) : [],
    ];
} elseif ($editando !== null) {
    $fv = ['id' => $editando['id'], 'nombre' => $editando['nombre'], 'correo' => $editando['email'], 'rol' => $editando['rol'],
        'modulos' => $editando['modulos'], 'fiestas' => $editando['fiestas']];
} else {
    $fv = ['id' => 0, 'nombre' => '', 'correo' => '', 'rol' => 'operador', 'modulos' => CB_ADMIN_MODULOS_RECOMENDADOS, 'fiestas' => []];
}
$esEdicion = $fv['id'] > 0;
// Las fiestas activas primero: son las que se delegan.
$fiestasOrden = array_keys($parties);
usort($fiestasOrden, static function ($a, $b) use ($parties) {
    $aa = !empty($parties[$a]['activa']);
    $ab = !empty($parties[$b]['activa']);
    if ($aa !== $ab) {
        return $aa ? -1 : 1;
    }
    return strcmp((string) ($parties[$b]['fecha'] ?? ''), (string) ($parties[$a]['fecha'] ?? ''));
});
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Usuarios</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
.usuarios-tabla { width: 100%; border-collapse: collapse; font-size: .92rem; }
.usuarios-tabla th, .usuarios-tabla td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #ece6f3; vertical-align: top; }
.usuarios-tabla th { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); }
.usuarios-tabla .acciones { display: flex; gap: 6px; flex-wrap: wrap; }
.usuarios-tabla .acciones form { display: inline; }
.casillas { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px 16px; }
.casillas label { display: flex; gap: 10px; align-items: flex-start; font-weight: 500; }
.casillas label input { margin-top: 4px; }
.casillas small { display: block; color: var(--text-muted); font-weight: 400; }
.temporal { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 1.25rem; letter-spacing: 1px; background: #fff; padding: 6px 12px; border-radius: 10px; display: inline-block; }
.fila-inactiva td { opacity: .6; }
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
      <?= admin_usuario_chip() ?>
      <form method="post" action="usuarios.php" class="inline-form">
        <?= admin_csrf_field() ?><input type="hidden" name="action" value="logout">
        <button class="btn btn-ghost" type="submit">Salir</button>
      </form>
    </div>
  </header>

  <?= admin_nav('usuarios') ?>

  <main>
    <?php if ($flash): ?>
      <div class="alert alert-ok">
        <div>
          <?= h($flash['ok'] ?? '') ?>
          <?php if (!empty($flash['email'])): ?>
            <?php if (!empty($flash['correo'])): ?>
              <br>Le mandamos el correo con su contraseña temporal a <strong><?= h($flash['email']) ?></strong>.
            <?php else: ?>
              <br><strong>El correo no salió</strong><?= !empty($flash['correo_error']) ? ' (' . h($flash['correo_error']) . ')' : '' ?>.
              Pásale esta contraseña temporal por otro medio; después de esta pantalla no queda guardada en ningún lado:
              <br><span class="temporal"><?= h($flash['temporal'] ?? '') ?></span>
              <br><small>Entra en <?= h(rtrim((string) cb_public_base_url(), '/') . '/admin/login.php') ?> con <?= h($flash['email']) ?>.</small>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($errores): ?>
      <div class="alert alert-error"><ul><?php foreach ($errores as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if (!$listo): ?>
      <div class="alert alert-error">Los usuarios necesitan la base de datos con la migración <code>023_admin_users</code> aplicada. Mientras tanto se entra solo con la clave maestra.</div>
    <?php endif; ?>
    <?php if ($yo['id'] === 0 && $listo): ?>
      <p class="alert alert-ok"><span>Entraste con la clave maestra. Para entrar con tu correo, créate abajo un usuario con rol <strong>Superadministrador</strong>: te llega la contraseña temporal y la cambias al entrar.</span></p>
    <?php endif; ?>

    <section class="card">
      <h2>Usuarios del panel</h2>
      <p class="muted">Quién puede entrar con correo y contraseña, y qué ve cada uno. Deshabilitar corta el acceso al instante.</p>
      <?php if (!$usuarios): ?>
        <p class="muted">Todavía no hay usuarios.</p>
      <?php else: ?>
      <div style="overflow-x:auto">
      <table class="usuarios-tabla">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Fiestas</th><th>Módulos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
          <tr class="<?= $u['activo'] ? '' : 'fila-inactiva' ?>">
            <td><strong><?= h($u['nombre']) ?></strong><?= $u['id'] === $yo['id'] ? ' <span class="badge badge-ok">tú</span>' : '' ?>
              <?php if ($u['ultimo_acceso'] !== ''): ?><br><small class="muted">Último acceso <?= h($u['ultimo_acceso']) ?> UTC</small><?php endif; ?>
              <?php if ($u['debe_cambiar']): ?><br><small class="muted">Todavía no cambió la contraseña temporal</small><?php endif; ?></td>
            <td><?= h($u['email']) ?></td>
            <td><?= h(CB_ADMIN_ROLES[$u['rol']] ?? $u['rol']) ?></td>
            <td><?php if ($u['rol'] === 'super'): ?><span class="muted">Todas</span><?php elseif (!$u['fiestas']): ?><span class="muted">Ninguna</span><?php else: ?>
              <?php foreach ($u['fiestas'] as $slug): ?><span class="badge badge-off"><?= h(usuarios_fiesta_etiqueta($parties, $slug)) ?></span> <?php endforeach; ?><?php endif; ?></td>
            <td><?php if ($u['rol'] === 'super'): ?><span class="muted">Todos</span><?php elseif (!$u['modulos']): ?><span class="muted">Ninguno</span><?php else: ?>
              <?= h(implode(', ', array_map(static fn($m) => CB_ADMIN_MODULOS[$m]['nombre'], $u['modulos']))) ?><?php endif; ?></td>
            <td><span class="badge <?= $u['activo'] ? 'badge-ok' : 'badge-off' ?>"><?= $u['activo'] ? 'Habilitado' : 'Deshabilitado' ?></span></td>
            <td class="acciones">
              <a class="btn btn-ghost" href="usuarios.php?editar=<?= (int) $u['id'] ?>#formulario">Accesos</a>
              <?php if ($u['id'] !== $yo['id']): ?>
              <form method="post" action="usuarios.php"><?= admin_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="action" value="<?= $u['activo'] ? 'desactivar' : 'activar' ?>">
                <button type="submit" class="btn <?= $u['activo'] ? 'btn-danger' : 'btn-primary' ?>"><?= $u['activo'] ? 'Deshabilitar' : 'Habilitar' ?></button></form>
              <?php endif; ?>
              <form method="post" action="usuarios.php" data-confirm="Se le manda una contraseña temporal nueva a <?= h($u['email']) ?> y la anterior deja de servir. ¿Seguir?"><?= admin_csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="action" value="reiniciar">
                <button type="submit" class="btn btn-ghost">Nueva contraseña temporal</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </section>

    <section class="card form-card" id="formulario" style="max-width:none">
      <h2><?= $esEdicion ? 'Accesos de ' . h($fv['nombre']) : 'Nuevo usuario' ?></h2>
      <p class="muted"><?= $esEdicion ? 'Cambia su nombre, correo, rol, fiestas y módulos. La contraseña no se toca acá.' : 'Le llega un correo con una contraseña temporal que tiene que cambiar la primera vez que entra.' ?></p>
      <form method="post" action="usuarios.php" class="party-form">
        <?= admin_csrf_field() ?>
        <input type="hidden" name="action" value="<?= $esEdicion ? 'actualizar' : 'crear' ?>">
        <?php if ($esEdicion): ?><input type="hidden" name="id" value="<?= (int) $fv['id'] ?>"><?php endif; ?>
        <div class="field">
          <label for="nombre">Nombre</label>
          <input type="text" id="nombre" name="nombre" value="<?= h($fv['nombre']) ?>" required maxlength="80" placeholder="Como se llama la persona">
        </div>
        <div class="field">
          <label for="correo">Correo</label>
          <input type="email" id="correo" name="correo" value="<?= h($fv['correo']) ?>" required maxlength="190" placeholder="persona@correo.cl" autocapitalize="none" spellcheck="false">
          <small class="muted">Con este correo entra. Solo tú se lo puedes cambiar.</small>
        </div>
        <div class="field">
          <label for="rol">Rol</label>
          <select id="rol" name="rol" <?= $esEdicion && $fv['id'] === $yo['id'] ? 'disabled' : '' ?>>
            <?php foreach (CB_ADMIN_ROLES as $clave => $nombreRol): ?>
              <option value="<?= h($clave) ?>" <?= $fv['rol'] === $clave ? 'selected' : '' ?>><?= h($nombreRol) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($esEdicion && $fv['id'] === $yo['id']): ?><input type="hidden" name="rol" value="super"><small class="muted">Tu propio rol no se cambia desde acá.</small>
          <?php else: ?><small class="muted">Un operador ve solo las fiestas y los módulos marcados abajo. Un superadministrador ve todo, incluida esta página.</small><?php endif; ?>
        </div>
        <div class="field">
          <label>Fiestas que puede ver</label>
          <?php if (!$fiestasOrden): ?><p class="muted">No hay fiestas creadas.</p><?php else: ?>
          <div class="casillas">
            <?php foreach ($fiestasOrden as $slug): ?>
              <label><input type="checkbox" name="fiestas[]" value="<?= h($slug) ?>" <?= in_array($slug, $fv['fiestas'], true) ? 'checked' : '' ?>>
                <span><?= h(usuarios_fiesta_etiqueta($parties, $slug)) ?><small><?= h($parties[$slug]['fecha'] ?: 'sin fecha') ?> · <?= !empty($parties[$slug]['activa']) ? 'activa' : 'inactiva' ?> · <?= h($slug) ?></small></span></label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label>Módulos habilitados</label>
          <div class="casillas">
            <?php foreach (CB_ADMIN_MODULOS as $clave => $m): ?>
              <label><input type="checkbox" name="modulos[]" value="<?= h($clave) ?>" <?= in_array($clave, $fv['modulos'], true) ? 'checked' : '' ?>>
                <span><?= h($m['nombre']) ?><small><?= h($m['detalle']) ?></small></span></label>
            <?php endforeach; ?>
          </div>
          <small class="muted">La ficha de sus fiestas (dirección, temática, lista de invitados) la ve siempre. Lo de dinero, contratos, plantillas y configuración es solo para superadministradores.</small>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-cta"><?= $esEdicion ? 'Guardar accesos' : 'Crear y mandar el correo' ?></button>
          <?php if ($esEdicion): ?><a class="btn btn-ghost" href="usuarios.php">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </section>
  </main>
</div>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
});
</script>
</body>
</html>
