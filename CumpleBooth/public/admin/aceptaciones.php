<?php
/**
 * admin/aceptaciones.php — Aceptación de Términos y firma por fiesta.
 * Genera el enlace único del cliente, muestra el estado, permite revocar o
 * eximir (demo/interno) y descarga la evidencia (comprobante HTML + firma PNG).
 * Requiere storage_mode=db. Misma sesión/CSRF que index.php.
 */
require __DIR__ . '/../lib.php';
require __DIR__ . '/../lib.acceptance.php';
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

function admin_icon(string $name): string
{
    $icons = [
        'copy' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'external' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'warn' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><circle cx="12" cy="12" r="10"/><path d="M12 17h.01"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'party' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 9"/><path d="m6 16 6.5-6.5"/></svg>',
        'palette' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2Z"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>',
        'link' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'sign' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3-1 5-3 7-6l7-10a2 2 0 0 0-3-3L4 12c-2 2-2 6-1 9Z"/><path d="M14 8l2 2"/><path d="M3 21h18"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function admin_status_class(string $status): string
{
    return match ($status) {
        'accepted' => 'badge-ok',
        'waived' => 'badge-warn',
        'pending' => 'badge-off',
        default => 'badge-off',
    };
}

// ================== LOGIN ==================
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!admin_csrf_check()) {
        $loginError = 'Sesión expirada, intenta de nuevo.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $loginLimit = cb_rate_limit('admin-login', cb_request_identity(), 5, 900, 900);
        if (!$loginLimit['allowed']) {
            $loginError = 'Demasiados intentos. Intenta nuevamente más tarde.';
        } elseif (ADMIN_PASSWORD_HASH === '') {
            $loginError = 'El administrador aún no está configurado. Ejecuta scripts/bootstrap.php.';
        } elseif (password_verify($pass, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged'] = true;
            session_regenerate_id(true);
            $_SESSION['admin_started'] = time();
            $_SESSION['admin_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            $returnTo = is_string($_POST['return_to'] ?? null) && strpos((string) $_POST['return_to'], 'aceptaciones.php') === 0
                ? (string) $_POST['return_to']
                : 'index.php';
            header('Location: ' . $returnTo);
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
    $returnTo = is_string($_GET['party'] ?? null) ? 'aceptaciones.php?party=' . rawurlencode((string) $_GET['party']) : 'index.php';
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Ingresar</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body class="login-body">
  <main class="login-card">
    <div class="login-logo">
      <span class="logo-mark"><?= admin_icon('party') ?></span>
      CumpleClick <span>Admin</span>
    </div>
    <?php if ($loginError !== ''): ?>
      <p class="alert alert-error"><?= admin_icon('warn') ?> <?= h($loginError) ?></p>
    <?php endif; ?>
    <form method="post" action="aceptaciones.php" class="login-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
      <label for="password">Contraseña</label>
      <div class="input-icon">
        <?= admin_icon('lock') ?>
        <input type="password" id="password" name="password" required autofocus placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-cta btn-block">Ingresar</button>
      <p class="muted small" style="margin-top:12px;text-align:center"><a href="recuperar.php">Olvide la contrasena</a></p>
    </form>
  </main>
</body>
</html>
    <?php
    exit;
}

// ================== DATOS DE LA FIESTA ==================
$publicSlugRaw = is_string($_GET['party'] ?? null) ? (string) $_GET['party'] : '';
$publicSlug = cb_valid_public_slug($publicSlugRaw) ? $publicSlugRaw : '';
$party = $publicSlug !== '' ? (cb_load_parties()['parties'][$publicSlug] ?? null) : null;
$errors = [];
$okMessage = null;
$formErrors = [];

if ($party === null) {
    $errors[] = 'La fiesta no existe o el identificador público no es válido.';
} elseif (cb_storage_mode() !== 'db') {
    $errors[] = 'La aceptación de Términos requiere storage_mode=db.';
}
$selfUrl = 'aceptaciones.php?party=' . rawurlencode($publicSlug);

// ================== DESCARGAS DE EVIDENCIA (GET) ==================
if ($party !== null && empty($errors) && in_array((string) ($_GET['action'] ?? ''), ['comprobante', 'firma'], true)) {
    $row = cb_load_acceptance_by_id((int) ($_GET['id'] ?? 0));
    if (!$row || (string) $row['party_public_slug'] !== $publicSlug || (string) $row['status'] !== 'accepted') {
        http_response_code(404);
        exit('Evidencia no encontrada.');
    }
    $isReceipt = $_GET['action'] === 'comprobante';
    $path = cb_acceptance_file_path((string) ($isReceipt ? $row['evidence_storage_key'] : $row['signature_storage_key']));
    $expected = (string) ($isReceipt ? $row['evidence_sha256'] : $row['signature_sha256']);
    if ($path === null || !is_file($path) || !hash_equals($expected, (string) hash_file('sha256', $path))) {
        http_response_code(409);
        exit('El archivo falta o no pasó la verificación de integridad (hash distinto al registrado).');
    }
    header('Content-Type: ' . ($isReceipt ? 'text/html; charset=utf-8' : 'image/png'));
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: ' . ($isReceipt ? 'attachment' : 'inline') . '; filename="' . ($isReceipt ? 'comprobante' : 'firma') . '-cumpleclick-' . (int) $row['id'] . ($isReceipt ? '.html' : '.png') . '"');
    readfile($path);
    exit;
}

// ================== ACCIONES (POST) ==================
if ($party !== null && empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    if (!admin_csrf_check()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'logout') {
                $_SESSION = [];
                session_destroy();
                header('Location: index.php');
                exit;
            } elseif ($action === 'generar') {
                $result = cb_create_plan_acceptance($party, [
                    'client_name' => $_POST['client_name'] ?? '',
                    'client_email' => $_POST['client_email'] ?? '',
                    'client_phone' => $_POST['client_phone'] ?? '',
                    'expires_days' => $_POST['expires_days'] ?? 14,
                    'summary' => is_array($_POST['summary'] ?? null) ? $_POST['summary'] : [],
                ], 'admin');
                if ($result['ok']) {
                    // El token se muestra una sola vez (PRG); en la BD queda solo el hash.
                    $_SESSION['acceptance_flash'] = ['id' => $result['id'], 'url' => $result['url']];
                    header('Location: ' . $selfUrl . '&ok=generado');
                    exit;
                }
                $formErrors = $result['errors'];
            } elseif ($action === 'revocar') {
                $id = (int) ($_POST['id'] ?? 0);
                $row = cb_load_acceptance_by_id($id);
                if ($row && (string) $row['party_public_slug'] === $publicSlug && cb_revoke_acceptance($id, 'admin')) {
                    header('Location: ' . $selfUrl . '&ok=revocado');
                    exit;
                }
                $errors[] = 'No se pudo revocar (solo se revocan enlaces pendientes o exenciones).';
            } elseif ($action === 'eximir') {
                $result = cb_waive_acceptance($party, (string) ($_POST['reason'] ?? ''), 'admin');
                if ($result['ok']) {
                    header('Location: ' . $selfUrl . '&ok=eximido');
                    exit;
                }
                $errors = array_merge($errors, array_values($result['errors']));
            }
        } catch (Throwable $e) {
            error_log('CumpleClick admin/aceptaciones.php: ' . $e->getMessage());
            $errors[] = 'Error interno al procesar la acción. Revisa los logs del servidor.';
        }
    }
}

$okMessages = ['generado' => 'Enlace de aceptación generado. Cópialo y envíalo al cliente: no se volverá a mostrar.', 'revocado' => 'Registro revocado.', 'eximido' => 'Fiesta eximida de aceptación (queda auditado).'];
$okMessage = isset($_GET['ok'], $okMessages[$_GET['ok']]) ? $okMessages[$_GET['ok']] : null;
$flash = $_SESSION['acceptance_flash'] ?? null;
unset($_SESSION['acceptance_flash']);

$rows = [];
$state = ['status' => 'none', 'row' => null];
$legalError = null;
if ($party !== null && empty($errors)) {
    $rows = cb_list_party_acceptances($publicSlug);
    $state = cb_party_acceptance_state($publicSlug);
    try {
        $bundle = cb_legal_bundle();
    } catch (Throwable $e) {
        $legalError = $e->getMessage();
    }
}
$summaryLabels = cb_acceptance_summary_fields();
$prefill = [
    'client_name' => (string) ($_POST['client_name'] ?? ($state['row']['client_name'] ?? '')),
    'client_email' => (string) ($_POST['client_email'] ?? ($state['row']['client_email'] ?? '')),
    'client_phone' => (string) ($_POST['client_phone'] ?? ($state['row']['client_phone'] ?? '')),
    'expires_days' => (string) ($_POST['expires_days'] ?? '14'),
];
$lastSummary = is_array($state['row']['plan_summary'] ?? null) ? $state['row']['plan_summary'] : [];
$summaryPrefill = is_array($_POST['summary'] ?? null) ? $_POST['summary'] : $lastSummary;
if ($party !== null) {
    $summaryPrefill += [
        'plan_name' => cb_acceptance_plan_labels()[(string) ($party['service_plan'] ?? 'booth')] ?? '',
        'theme_name' => cb_theme_public_name((string) ($party['tema'] ?? '')),
        'event_date' => (string) ($party['fecha'] ?? ''),
        'service_hours' => ($party['service_plan'] ?? '') === 'full' ? '2 horas' : '',
        'balance_due' => 'El día del evento, antes de iniciar el servicio',
        'at_contact' => 'TODO-LUIS: WhatsApp y correo de CumpleClick',
    ];
}
$relationships = ['madre' => 'Madre', 'padre' => 'Padre', 'tutor' => 'Tutor/a legal', 'autorizado' => 'Adulto autorizado'];
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleClick Admin · Aceptación de Términos</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
.acc-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: .75rem 1rem; }
.acc-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.acc-table th, .acc-table td { text-align:left; padding:.5rem .55rem; border-bottom:1px solid var(--border); vertical-align:top; }
.acc-table th { color: var(--text-muted); font-weight:600; font-size:.8rem; text-transform:uppercase; letter-spacing:.03em; }
.acc-actions { display:flex; flex-wrap:wrap; gap:.35rem; }
.acc-link { display:flex; gap:.5rem; align-items:center; margin-top:.5rem; }
.acc-link input { flex:1; font-family:Consolas,monospace; font-size:.85rem; }
.acc-gate { border-left:4px solid var(--warn); }
</style>
</head>
<body>
<div class="wrap">
  <header class="topbar">
    <div class="logo">
      <img src="../brand/cumpleclick-mark.svg" alt="CumpleClick" width="36" height="36" style="display:block">
      CumpleClick <span>Admin</span>
    </div>
    <form method="post" action="<?= h($selfUrl) ?>" class="inline-form logout-btn">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="logout">
      <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
    </form>
  </header>

  <nav class="tabs">
    <a class="tab" href="index.php"><?= admin_icon('party') ?> Fiestas</a>
    <a class="tab" href="index.php?view=temas"><?= admin_icon('palette') ?> Temáticas</a>
    <a class="tab active" href="<?= h($selfUrl) ?>"><?= admin_icon('sign') ?> Aceptación de Términos</a>
  </nav>

  <main>
    <a class="detail-back" href="index.php">← Volver a fiestas</a>
    <?php if ($okMessage): ?>
      <p class="alert alert-ok"><?= admin_icon('check') ?> <?= h($okMessage) ?></p>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-error"><?= admin_icon('warn') ?><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if ($legalError): ?>
      <div class="alert alert-error"><?= admin_icon('warn') ?> Documentos legales inconsistentes: <?= h($legalError) ?>. No se pueden emitir enlaces hasta corregirlo.</div>
    <?php endif; ?>

    <?php if ($party !== null && empty($errors)): ?>
      <?php if ($flash): ?>
        <section class="card">
          <h2>Enlace para el cliente (se muestra una sola vez)</h2>
          <p class="muted small">Envíalo por WhatsApp o correo a <?= h($state['row']['client_email'] ?? '') ?>. Si se pierde, genera uno nuevo (el anterior queda revocado).</p>
          <div class="acc-link">
            <input type="text" readonly value="<?= h($flash['url']) ?>" onclick="this.select()" aria-label="Enlace de aceptación">
            <button type="button" class="btn btn-icon" data-copy="<?= h($flash['url']) ?>" title="Copiar enlace"><?= admin_icon('copy') ?></button>
            <a class="btn btn-icon" href="<?= h($flash['url']) ?>" target="_blank" rel="noopener" title="Abrir"><?= admin_icon('external') ?></a>
          </div>
        </section>
      <?php endif; ?>

      <section class="card <?= in_array($state['status'], ['accepted', 'waived'], true) ? '' : 'acc-gate' ?>">
        <h2><?= h($party['admin_label'] ?: $party['nombre']) ?> <span class="badge <?= admin_status_class($state['status']) ?>"><?= h(cb_acceptance_status_label($state['status'])) ?></span></h2>
        <p class="muted small"><?= h($party['birthday_person_name']) ?> · <?= h($party['fecha'] ?: 'sin fecha') ?> · <?= h(cb_acceptance_plan_labels()[(string) ($party['service_plan'] ?? 'booth')] ?? $party['service_plan']) ?> · slug: <?= h($publicSlug) ?></p>
        <?php if (in_array($state['status'], ['accepted', 'waived'], true)): ?>
          <p><?= admin_icon('check') ?> La fiesta puede activarse. <?= $state['status'] === 'waived' ? 'Motivo de exención: <em>' . h($state['row']['waived_reason'] ?? '') . '</em>' : 'Aceptó <strong>' . h($state['row']['signer_name'] ?? '') . '</strong> (RUT ' . h($state['row']['signer_rut'] ?? '') . ') el ' . h(cb_chile_datetime((string) ($state['row']['accepted_at'] ?? ''))) . '.' ?></p>
        <?php else: ?>
          <p><?= admin_icon('warn') ?> <strong>Esta fiesta no puede activarse</strong> hasta que el cliente acepte los Términos y firme, o la eximas explícitamente (solo demos o uso interno).</p>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2>Historial</h2>
        <?php if (!$rows): ?>
          <p class="muted">Todavía no se ha generado ningún enlace para esta fiesta.</p>
        <?php else: ?>
          <div style="overflow-x:auto">
          <table class="acc-table">
            <thead><tr><th>#</th><th>Estado</th><th>Cliente / firmante</th><th>Emitido</th><th>Aceptado</th><th>Marketing</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
              <?php $rowStatus = cb_acceptance_is_expired($row) ? 'expired' : (string) $row['status']; ?>
              <tr>
                <td><?= (int) $row['id'] ?></td>
                <td><span class="badge <?= admin_status_class($rowStatus) ?>"><?= h(cb_acceptance_status_label($rowStatus)) ?></span></td>
                <td>
                  <?= h($row['client_name']) ?><?= $row['client_email'] ? ' <span class="muted small">' . h($row['client_email']) . '</span>' : '' ?>
                  <?php if ($row['signer_name']): ?><br><strong><?= h($row['signer_name']) ?></strong> · RUT <?= h($row['signer_rut']) ?> · <?= h($relationships[(string) $row['signer_relationship']] ?? $row['signer_relationship']) ?><?php endif; ?>
                  <?php if ($row['waived_reason']): ?><br><em class="muted small"><?= h($row['waived_reason']) ?></em><?php endif; ?>
                </td>
                <td><?= h(cb_chile_datetime((string) $row['created_at'])) ?><br><span class="muted small">por <?= h($row['created_by']) ?><?= $row['expires_at'] ? ' · vence ' . h(cb_chile_datetime((string) $row['expires_at'])) : '' ?><?= (int) $row['view_count'] > 0 ? ' · vistas: ' . (int) $row['view_count'] : '' ?></span></td>
                <td>
                  <?= h(cb_chile_datetime($row['accepted_at'])) ?>
                  <?php if ($row['accepted_at']): ?><br><span class="muted small">IP <?= h($row['ip_address']) ?> · v<?= h($row['legal_version']) ?><?= $row['client_mail_sent_at'] ? ' · correo cliente OK' : ' · correo cliente pendiente' ?></span><?php endif; ?>
                </td>
                <td><?= $row['status'] === 'accepted' ? (!empty($row['accepted_marketing']) ? '<span class="badge badge-ok">Sí</span>' : '<span class="badge badge-off">No</span>') : '—' ?></td>
                <td>
                  <div class="acc-actions">
                    <?php if ($row['status'] === 'accepted'): ?>
                      <a class="btn btn-ghost btn-sm" href="<?= h($selfUrl) ?>&amp;action=comprobante&amp;id=<?= (int) $row['id'] ?>"><?= admin_icon('download') ?> Comprobante</a>
                      <a class="btn btn-ghost btn-sm" href="<?= h($selfUrl) ?>&amp;action=firma&amp;id=<?= (int) $row['id'] ?>" target="_blank" rel="noopener"><?= admin_icon('sign') ?> Firma</a>
                    <?php elseif (in_array($row['status'], ['pending', 'waived'], true)): ?>
                      <form method="post" action="<?= h($selfUrl) ?>" class="inline-form" data-confirm="¿Revocar el registro #<?= (int) $row['id'] ?>?">
                        <?= admin_csrf_field() ?><input type="hidden" name="action" value="revocar"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Revocar</button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <?php if ($row['status'] === 'accepted'): ?><div class="muted small" title="SHA-256 del comprobante">hash <?= h(substr((string) $row['evidence_sha256'], 0, 16)) ?>…</div><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($state['status'] !== 'accepted' && !$legalError): ?>
      <section class="card form-card">
        <h2><?= admin_icon('link') ?> Generar enlace de aceptación</h2>
        <p class="muted small">Documentos versión <strong><?= h($bundle['version']) ?></strong> · hash <code><?= h(substr($bundle['sha256'], 0, 16)) ?>…</code>. El cliente verá este Resumen del Plan junto a los documentos; revisa que coincida con lo acordado.</p>
        <?php if ($formErrors): ?>
          <div class="alert alert-error"><?= admin_icon('warn') ?><ul><?php foreach ($formErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="<?= h($selfUrl) ?>" class="party-form">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="generar">
          <div class="acc-grid">
            <div class="field"><label for="client_name">Nombre del cliente</label><input type="text" id="client_name" name="client_name" required maxlength="160" value="<?= h($prefill['client_name']) ?>"></div>
            <div class="field"><label for="client_email">Correo del cliente</label><input type="email" id="client_email" name="client_email" required maxlength="254" value="<?= h($prefill['client_email']) ?>"></div>
            <div class="field"><label for="client_phone">Teléfono / WhatsApp</label><input type="text" id="client_phone" name="client_phone" maxlength="30" value="<?= h($prefill['client_phone']) ?>"></div>
            <div class="field"><label for="expires_days">Vigencia del enlace (días)</label><input type="number" id="expires_days" name="expires_days" min="1" max="90" value="<?= h($prefill['expires_days']) ?>"></div>
          </div>
          <h3 style="margin:1rem 0 .5rem">Resumen del Plan</h3>
          <div class="acc-grid">
            <?php foreach ($summaryLabels as $key => $label): ?>
              <div class="field">
                <label for="summary_<?= h($key) ?>"><?= h($label) ?><?= $key === 'price_total' ? ' *' : '' ?></label>
                <?php if (in_array($key, ['extras', 'replacement_values', 'notes', 'event_address'], true)): ?>
                  <textarea id="summary_<?= h($key) ?>" name="summary[<?= h($key) ?>]" rows="2" maxlength="600"><?= h($summaryPrefill[$key] ?? '') ?></textarea>
                <?php else: ?>
                  <input type="text" id="summary_<?= h($key) ?>" name="summary[<?= h($key) ?>]" maxlength="600" value="<?= h($summaryPrefill[$key] ?? '') ?>" <?= $key === 'price_total' ? 'placeholder="Ej: $49.995 (TODO-LUIS: tarifa vigente)"' : '' ?>>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-cta"><?= admin_icon('link') ?> Generar enlace</button>
          </div>
        </form>
      </section>

      <section class="card">
        <h2>Eximir de aceptación (solo demos o uso interno)</h2>
        <p class="muted small">Úsalo únicamente para fiestas demo, pruebas o eventos propios. Queda registrado con motivo, fecha y autor. Para clientes reales, genera el enlace.</p>
        <form method="post" action="<?= h($selfUrl) ?>" class="party-form" data-confirm="¿Eximir esta fiesta de la aceptación de Términos? Quedará auditado.">
          <?= admin_csrf_field() ?>
          <input type="hidden" name="action" value="eximir">
          <div class="field"><label for="reason">Motivo</label><input type="text" id="reason" name="reason" required minlength="5" maxlength="255" placeholder="Ej: fiesta demo para feria escolar"></div>
          <div class="form-actions"><button type="submit" class="btn btn-ghost">Eximir esta fiesta</button></div>
        </form>
      </section>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
<script>
(function () {
  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea'); ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'absolute'; ta.style.left = '-9999px';
    document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(ta); done();
  }
  Array.prototype.forEach.call(document.querySelectorAll('[data-copy]'), function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      var done = function () { btn.classList.add('copied'); setTimeout(function () { btn.classList.remove('copied'); }, 1200); };
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); }); } else { fallbackCopy(text, done); }
    });
  });
  Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
    form.addEventListener('submit', function (ev) { if (!window.confirm(form.getAttribute('data-confirm'))) { ev.preventDefault(); } });
  });
})();
</script>
</body>
</html>
