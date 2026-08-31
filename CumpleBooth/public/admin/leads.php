<?php
/**
 * admin/leads.php — las solicitudes que llegan del formulario del sitio.
 *
 * Los leads se venían guardando en `cc_leads` desde la migración 006, pero no
 * había ninguna pantalla que los mostrara: entraban a la base y ahí quedaban.
 * Nadie se enteraba de una solicitud salvo mirando la tabla a mano.
 *
 * La pantalla ordena por lo que importa operativamente: primero las nuevas, y
 * dentro de cada grupo la más reciente arriba. El estado se cambia con un clic
 * porque si cambiarlo cuesta, nadie lo cambia y a la semana todo dice "nueva".
 *
 * También muestra si la confirmación al cliente salió o falló. Eso NO es un
 * detalle técnico de adorno: el envío falla en silencio a propósito (ver
 * `cb_lead_enviar_correos`), así que sin mostrarlo acá, un buzón mal
 * configurado dejaría a todos los clientes sin confirmación y no habría forma
 * de notarlo hasta que alguien reclamara.
 */
require __DIR__ . '/../lib.php';
// lib.php ya carga lib.leads.php; requerirlo de nuevo aborta por funcion redeclarada.
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

function admin_icon(string $name): string
{
    $paths = [
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'warn' => '<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'back' => '<path d="M19 12H5m7-7-7 7 7 7"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .3 1.9.6 2.8a2 2 0 0 1-.5 2.1L8.1 9.8a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.8.6a2 2 0 0 1 1.7 2z"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.4 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.8 1.1z"/>',
    ];
    $d = $paths[$name] ?? '';
    if ($d === '') { return ''; }
    /* `width`/`height` EN EL ATRIBUTO, como los del resto del admin.
       Sin ellos, un SVG sin tamaño intrínseco se estira hasta llenar a su
       contenedor: el icono de advertencia salió ocupando media pantalla. La
       clase `.icon` que traía antes no existe en `_style.css.php`, así que no
       lo estaba limitando nada. */
    return '<svg class="icon" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

// ================== SESIÓN ==================
$loginError = '';
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
            header('Location: leads.php');
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
  <main class="login-card">
    <div class="login-logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Admin</span>
    </div>
    <?php if ($loginError !== ''): ?>
      <p class="alert alert-error"><?= admin_icon('warn') ?> <?= h($loginError) ?></p>
    <?php endif; ?>
    <form method="post" action="leads.php" class="login-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <label for="password">Contraseña</label>
      <div class="input-icon">
        <?= admin_icon('lock') ?>
        <input type="password" id="password" name="password" required autofocus placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-cta btn-block">Ingresar</button>
    </form>
  </main>
</body>
</html>
    <?php
    exit;
}

// ================== DATOS ==================
const CC_LEAD_ESTADOS = [
    'new' => 'Nueva',
    'contacted' => 'Contactada',
    'quoted' => 'Cotizada',
    'won' => 'Cerrada',
    'lost' => 'Descartada',
];

$okMessage = null;
$errors = [];
$modoDb = cb_storage_mode() === 'db';

if ($modoDb && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'estado') {
    if (!admin_csrf_check()) {
        $errors[] = 'La sesión expiró. Vuelve a intentarlo.';
    } else {
        $ref = (string) ($_POST['ref'] ?? '');
        $estado = (string) ($_POST['estado'] ?? '');
        if (!array_key_exists($estado, CC_LEAD_ESTADOS)) {
            $errors[] = 'Estado no válido.';
        } else {
            try {
                $stmt = cb_pdo()->prepare('UPDATE cc_leads SET status = ?, updated_at = ? WHERE public_ref = ?');
                $stmt->execute([$estado, gmdate('Y-m-d H:i:s'), $ref]);
                $okMessage = 'Solicitud ' . $ref . ' marcada como «' . CC_LEAD_ESTADOS[$estado] . '».';
            } catch (Throwable $e) {
                $errors[] = 'No se pudo actualizar el estado.';
                error_log('CumpleClick leads estado: ' . $e->getMessage());
            }
        }
    }
}

/* La migración 012 puede no estar aplicada. En vez de reventar con "columna
   desconocida", se detecta una vez y la vista se adapta: sin esas columnas
   simplemente no se muestra el estado del correo. */
$tieneColumnasCorreo = false;
$leads = [];
$conteos = [];
$filtro = (string) ($_GET['estado'] ?? '');
if (!array_key_exists($filtro, CC_LEAD_ESTADOS)) { $filtro = ''; }
$refDetalle = (string) ($_GET['ref'] ?? '');

if ($modoDb) {
    try {
        $pdo = cb_pdo();
        try {
            $pdo->query('SELECT confirmation_sent_at FROM cc_leads LIMIT 1');
            $tieneColumnasCorreo = true;
        } catch (Throwable $e) {
            $tieneColumnasCorreo = false;
        }

        $columnas = 'id, public_ref, name, organization, email, phone, event_type, event_date, commune, message, status, created_at'
            . ($tieneColumnasCorreo ? ', confirmation_sent_at, notified_at, mail_error' : '');

        $sql = "SELECT $columnas FROM cc_leads";
        $params = [];
        if ($filtro !== '') {
            $sql .= ' WHERE status = ?';
            $params[] = $filtro;
        }
        /* Las nuevas arriba SIEMPRE, aunque sean viejas: son las que hay que
           atender. Dentro de cada grupo, lo más reciente primero. */
        $sql .= " ORDER BY CASE WHEN status = 'new' THEN 0 ELSE 1 END, created_at DESC LIMIT 300";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->query('SELECT status, COUNT(*) AS total FROM cc_leads GROUP BY status');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $fila) {
            $conteos[(string) $fila['status']] = (int) $fila['total'];
        }
    } catch (Throwable $e) {
        $errors[] = 'No se pudieron leer las solicitudes.';
        error_log('CumpleClick leads lectura: ' . $e->getMessage());
    }
}

$totalTodas = array_sum($conteos);
$nuevas = $conteos['new'] ?? 0;

function cc_lead_fecha(?string $iso): string
{
    if ($iso === null || trim($iso) === '') { return ''; }
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $iso, new DateTimeZone('UTC'))
        ?: DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new DateTimeZone('UTC'));
    if (!$d) { return $iso; }
    return $d->setTimezone(new DateTimeZone('America/Santiago'))->format('d-m-Y H:i');
}
function cc_lead_solo_fecha(?string $iso): string
{
    if ($iso === null || trim($iso) === '') { return ''; }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    return $d ? $d->format('d-m-Y') : (string) $iso;
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitudes · CumpleClick Admin</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>

/* ---- Solicitudes ---- */
.lead-card .icon, .lead-correo .icon, .lead-datos .icon { width:15px; height:15px; flex:0 0 auto; }
.lead-datos span { white-space:nowrap; }
.lead-filtros { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 18px; }
.lead-chip { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border-radius:999px;
  border:1px solid #D9CDEB; background:#fff; color:#4C2882; font-weight:600; font-size:14px; text-decoration:none; }
.lead-chip.is-active { background:#4C2882; border-color:#4C2882; color:#fff; }
.lead-chip b { font-weight:800; }

.lead-lista { display:grid; gap:12px; }
.lead-card { border:1px solid #E6DCF2; border-radius:14px; padding:16px 18px; background:#fff; }
.lead-card.is-new { border-left:4px solid #D6307F; }
.lead-head { display:flex; flex-wrap:wrap; align-items:baseline; gap:10px; margin:0 0 8px; }
.lead-nombre { font-size:17px; font-weight:700; color:#2C2140; margin:0; }
.lead-ref { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; color:#7A6E92; }
.lead-cuando { margin-left:auto; font-size:13px; color:#7A6E92; }
.lead-datos { display:flex; flex-wrap:wrap; gap:6px 18px; margin:0 0 10px; font-size:14px; color:#4A4160; }
.lead-datos span { display:inline-flex; align-items:center; gap:6px; }
.lead-mensaje { margin:0 0 12px; padding:11px 13px; background:#FBF8FF; border-radius:10px;
  font-size:14px; line-height:1.55; color:#3D3355; white-space:pre-wrap; }
.lead-acciones { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
.lead-estado-form { display:flex; gap:6px; align-items:center; margin-left:auto; }
.lead-estado-form select { padding:7px 10px; font-size:14px; }
.lead-badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.lead-badge--new { background:#FCE4F0; color:#B01A63; }
.lead-badge--otro { background:#EDE7F6; color:#5B4A86; }
.lead-correo { font-size:12.5px; color:#7A6E92; margin:8px 0 0; display:flex; align-items:center; gap:6px; }
.lead-correo.is-error { color:#B4341C; }
.lead-vacio { text-align:center; padding:44px 20px; color:#7A6E92; }
@media (max-width: 640px) {
  .lead-cuando { margin-left:0; width:100%; }
  .lead-estado-form { margin-left:0; width:100%; }
  .lead-estado-form select { flex:1; }
}
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Solicitudes</span>
    </div>
    <a class="btn btn-ghost" href="index.php"><?= admin_icon('back') ?> Volver a fiestas</a>
  </header>

  <main>
    <?php if ($okMessage !== null): ?>
      <p class="alert alert-ok"><?= admin_icon('check') ?> <?= h($okMessage) ?></p>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-error"><?= admin_icon('warn') ?><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (!$modoDb): ?>
      <p class="alert alert-error"><?= admin_icon('warn') ?> Las solicitudes del formulario requieren <code>storage_mode=db</code>.</p>
    <?php else: ?>

      <?php if (!$tieneColumnasCorreo): ?>
        <p class="alert alert-error"><?= admin_icon('warn') ?>
          Falta aplicar la migración <code>012_lead_mail_tracking</code>. Las solicitudes se ven igual,
          pero no se puede saber si la confirmación al cliente salió o falló.
        </p>
      <?php endif; ?>

      <div class="lead-filtros">
        <a class="lead-chip <?= $filtro === '' ? 'is-active' : '' ?>" href="leads.php">Todas <b><?= (int) $totalTodas ?></b></a>
        <?php foreach (CC_LEAD_ESTADOS as $clave => $etiqueta): ?>
          <a class="lead-chip <?= $filtro === $clave ? 'is-active' : '' ?>" href="leads.php?estado=<?= h($clave) ?>">
            <?= h($etiqueta) ?> <b><?= (int) ($conteos[$clave] ?? 0) ?></b>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (!$leads): ?>
        <div class="card lead-vacio">
          <?= admin_icon('inbox') ?>
          <p style="margin:10px 0 0;font-size:15px;">
            <?= $filtro === '' ? 'Todavía no hay solicitudes del formulario.' : 'No hay solicitudes en este estado.' ?>
          </p>
        </div>
      <?php else: ?>
        <div class="lead-lista">
          <?php foreach ($leads as $lead):
              $esNueva = ($lead['status'] ?? '') === 'new';
              $ref = (string) ($lead['public_ref'] ?? '');
              $wa = preg_replace('/[^0-9]/', '', (string) ($lead['phone'] ?? ''));
              $fechaEvento = cc_lead_solo_fecha($lead['event_date'] ?? null);
          ?>
          <article class="lead-card <?= $esNueva ? 'is-new' : '' ?>" id="<?= h($ref) ?>">
            <div class="lead-head">
              <h2 class="lead-nombre"><?= h((string) ($lead['name'] ?? '')) ?></h2>
              <span class="lead-badge <?= $esNueva ? 'lead-badge--new' : 'lead-badge--otro' ?>">
                <?= h(CC_LEAD_ESTADOS[$lead['status'] ?? ''] ?? (string) ($lead['status'] ?? '')) ?>
              </span>
              <span class="lead-ref"><?= h($ref) ?></span>
              <span class="lead-cuando"><?= h(cc_lead_fecha($lead['created_at'] ?? null)) ?></span>
            </div>

            <p class="lead-datos">
              <span><?= admin_icon('party') ?><?= h((string) ($lead['event_type'] ?? '')) ?></span>
              <?php if ($fechaEvento !== ''): ?><span>📅 <?= h($fechaEvento) ?></span><?php endif; ?>
              <span>📍 <?= h((string) ($lead['commune'] ?? '')) ?></span>
              <?php if (trim((string) ($lead['organization'] ?? '')) !== ''): ?>
                <span>🏢 <?= h((string) $lead['organization']) ?></span>
              <?php endif; ?>
            </p>

            <p class="lead-mensaje"><?= h((string) ($lead['message'] ?? '')) ?></p>

            <div class="lead-acciones">
              <?php if ($wa !== ''): ?>
                <a class="btn btn-cta btn-sm" href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener">
                  <?= admin_icon('phone') ?> <?= h((string) ($lead['phone'] ?? '')) ?>
                </a>
              <?php endif; ?>
              <a class="btn btn-ghost btn-sm" href="mailto:<?= h((string) ($lead['email'] ?? '')) ?>">
                <?= admin_icon('mail') ?> <?= h((string) ($lead['email'] ?? '')) ?>
              </a>

              <form method="post" action="leads.php<?= $filtro !== '' ? '?estado=' . h($filtro) : '' ?>" class="lead-estado-form">
                <?= admin_csrf_field() ?>
                <input type="hidden" name="action" value="estado">
                <input type="hidden" name="ref" value="<?= h($ref) ?>">
                <label class="sr-only" for="estado-<?= h($ref) ?>">Estado de la solicitud</label>
                <select id="estado-<?= h($ref) ?>" name="estado">
                  <?php foreach (CC_LEAD_ESTADOS as $clave => $etiqueta): ?>
                    <option value="<?= h($clave) ?>" <?= ($lead['status'] ?? '') === $clave ? 'selected' : '' ?>><?= h($etiqueta) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-ghost btn-sm">Guardar</button>
              </form>
            </div>

            <?php if ($tieneColumnasCorreo):
                $error = trim((string) ($lead['mail_error'] ?? ''));
                $enviado = trim((string) ($lead['confirmation_sent_at'] ?? ''));
            ?>
              <?php if ($error !== ''): ?>
                <p class="lead-correo is-error"><?= admin_icon('warn') ?> No se pudo enviar el correo: <?= h($error) ?></p>
              <?php elseif ($enviado !== ''): ?>
                <p class="lead-correo"><?= admin_icon('check') ?> Confirmación enviada el <?= h(cc_lead_fecha($enviado)) ?></p>
              <?php else: ?>
                <p class="lead-correo">Sin registro de envío (solicitud anterior al correo automático).</p>
              <?php endif; ?>
            <?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<?php if ($refDetalle !== ''): ?>
<script>
  /* Se llega acá desde el enlace del correo de aviso, que trae `?ref=`. Se
     resalta y se lleva a la vista la solicitud correspondiente, en vez de
     dejar al lector buscándola en una lista de trescientas. */
  (function () {
    var destino = document.getElementById(<?= json_encode($refDetalle, JSON_UNESCAPED_SLASHES) ?>);
    if (!destino) { return; }
    destino.scrollIntoView({ block: 'center' });
    destino.style.outline = '3px solid #D6307F';
    destino.style.outlineOffset = '3px';
  })();
</script>
<?php endif; ?>
</body>
</html>
