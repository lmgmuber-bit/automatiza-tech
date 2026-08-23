<?php
/**
 * admin/invitations.php — Gestión de invitaciones por fiesta (Gate A).
 * Requiere storage_mode=db. No genera imágenes ni vídeos.
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

function admin_base_url(): string
{
    return cb_public_base_url() . '/';
}

function admin_icon(string $name): string
{
    $icons = [
        'edit' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
        'copy' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'external' => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>',
        'duplicate' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M4 16V4a2 2 0 0 1 2-2h8"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'warn' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><circle cx="12" cy="12" r="10"/><path d="M12 17h.01"/></svg>',
        'lock' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'party' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5.8 11.3 2 22l10.7-3.79"/><path d="M4 3h.01"/><path d="M22 8h.01"/><path d="M15 2h.01"/><path d="M22 20h.01"/><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 9"/><path d="m6 16 6.5-6.5"/></svg>',
        'gallery' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>',
        'video' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>',
        'link' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'refresh' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function admin_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
}

function admin_format_datetime(?string $dt): string
{
    if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts === false ? h($dt) : date('Y-m-d H:i', $ts);
}

function admin_status_label(string $status): string
{
    $map = [
        'draft' => 'Borrador',
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'published' => 'Publicada',
        'revoked' => 'Revocada',
        'archived' => 'Archivada',
    ];
    return $map[$status] ?? h($status);
}

function admin_status_class(string $status): string
{
    return match ($status) {
        'draft' => 'status-draft',
        'pending' => 'status-pending',
        'approved' => 'status-approved',
        'published' => 'status-published',
        'revoked' => 'status-rejected',
        'archived' => 'status-draft',
        default => 'status-draft',
    };
}

function admin_invitation_next_asset_version(int $invitationId, string $assetKey): int
{
    $outputs = cb_load_invitation_outputs($invitationId);
    $count = 0;
    foreach ($outputs as $o) {
        if ((string) ($o['asset_key'] ?? '') === $assetKey) {
            $count++;
        }
    }
    return $count + 1;
}

function admin_mime_to_ext(string $mime): string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3',
        default => 'bin',
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
            $returnTo = is_string($_POST['return_to'] ?? null) && strpos((string) $_POST['return_to'], 'invitations.php') === 0
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

// ================== VISTA: LOGIN ==================
if (!$loggedIn) {
    $returnTo = is_string($_GET['party'] ?? null) ? 'invitations.php?party=' . rawurlencode((string) $_GET['party']) : 'index.php';
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Ingresar</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
</style>
</head>
<body class="login-body">
  <main class="login-card">
    <div class="login-logo">
      <span class="logo-mark"><?= admin_icon('party') ?></span>
      CumpleBooth <span>Admin</span>
    </div>
    <?php if ($loginError !== ''): ?>
      <p class="alert alert-error"><?= admin_icon('warn') ?> <?= h($loginError) ?></p>
    <?php endif; ?>
    <form method="post" action="invitations.php" class="login-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
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

// ================== DATOS DE LA FIESTA ==================
$publicSlugRaw = is_string($_GET['party'] ?? null) ? (string) $_GET['party'] : '';
$publicSlug = cb_valid_public_slug($publicSlugRaw) ? $publicSlugRaw : '';
$party = $publicSlug !== '' ? cb_load_party_raw($publicSlug) : null;

$errors = [];
$okMessage = null;

if ($party === null) {
    $errors[] = 'La fiesta no existe o el identificador público no es válido.';
} elseif (cb_storage_mode() !== 'db') {
    $errors[] = 'El módulo de invitaciones requiere storage_mode=db.';
}

$partyId = $party !== null ? cb_party_db_id($publicSlug) : null;
if ($party !== null && $partyId === null) {
    $errors[] = 'No se pudo resolver el id interno de la fiesta en la base de datos.';
}

$baseUrl = admin_base_url();
$invitationsUrl = 'invitations.php?party=' . rawurlencode($publicSlug);
$themes = cb_load_themes();
$themeData = $party !== null ? ($themes['themes'][$party['theme_slug']] ?? []) : [];
$themeName = $themeData['nombre'] ?? ($party['theme_slug'] ?? '');

// ================== ACCIONES POST ==================
if ($partyId !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página e intenta de nuevo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $now = gmdate('Y-m-d H:i:s');
        $by = 'admin';

        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: index.php');
            exit;
        }

        if ($action === 'crear_invitacion') {
            $birthdayPersonName = trim((string) ($_POST['birthday_person_name'] ?? ''));
            $birthdayPersonGender = in_array((string) ($_POST['birthday_person_gender'] ?? ''), ['m', 'f'], true) ? (string) $_POST['birthday_person_gender'] : '';
            $eventDate = trim((string) ($_POST['event_date'] ?? ''));
            $eventTime = trim((string) ($_POST['event_time'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $language = in_array((string) ($_POST['language'] ?? ''), ['es', 'en', 'pt'], true) ? (string) $_POST['language'] : 'es';
            $channel = in_array((string) ($_POST['channel'] ?? ''), ['whatsapp', 'email', 'print'], true) ? (string) $_POST['channel'] : 'whatsapp';
            $promptTemplate = trim((string) ($_POST['prompt_template'] ?? ''));

            if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                $errors[] = 'La fecha debe tener formato AAAA-MM-DD.';
            }
            if ($eventTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $eventTime)) {
                $errors[] = 'La hora debe tener formato HH:MM.';
            }
            if ($promptTemplate !== '') {
                $validation = cb_validate_invitation_prompt_template($promptTemplate, $themeData);
                if (!$validation['ok']) {
                    $errors[] = $validation['error'];
                } else {
                    $promptTemplate = $validation['prompt'];
                }
            }

            if (empty($errors)) {
                $res = cb_create_invitation([
                    'party_id' => $partyId,
                    'theme_slug' => (string) ($party['theme_slug'] ?? ''),
                    'admin_label' => (string) ($party['admin_label'] ?? ''),
                    'birthday_person_name' => $birthdayPersonName,
                    'birthday_person_gender' => $birthdayPersonGender,
                    'event_date' => $eventDate,
                    'event_time' => $eventTime,
                    'address' => $address,
                    'message' => $message,
                    'language' => $language,
                    'channel' => $channel,
                    'created_by' => $by,
                    'prompt_template' => $promptTemplate,
                ]);
                if (!empty($res['ok'])) {
                    $_SESSION['cc_invitation_token'] = ['id' => (int) $res['id'], 'token' => (string) $res['token']];
                    header('Location: ' . $invitationsUrl . '&ok=creada#inv-' . (int) $res['id']);
                    exit;
                }
                $errors[] = $res['error'] ?? 'No se pudo crear la invitación.';
            }
        } elseif ($action === 'actualizar_invitacion') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            if ($id === false || $id === null || cb_invitation_owned_by_party((int) $id, $partyId) === null) {
                $errors[] = 'Invitación inválida.';
            } else {
                $update = [
                    'birthday_person_name' => trim((string) ($_POST['birthday_person_name'] ?? '')),
                    'birthday_person_gender' => in_array((string) ($_POST['birthday_person_gender'] ?? ''), ['m', 'f'], true) ? (string) $_POST['birthday_person_gender'] : '',
                    'event_date' => trim((string) ($_POST['event_date'] ?? '')),
                    'event_time' => trim((string) ($_POST['event_time'] ?? '')),
                    'address' => trim((string) ($_POST['address'] ?? '')),
                    'message' => trim((string) ($_POST['message'] ?? '')),
                    'language' => in_array((string) ($_POST['language'] ?? ''), ['es', 'en', 'pt'], true) ? (string) $_POST['language'] : 'es',
                    'channel' => in_array((string) ($_POST['channel'] ?? ''), ['whatsapp', 'email', 'print'], true) ? (string) $_POST['channel'] : 'whatsapp',
                    'expires_at' => trim((string) ($_POST['expires_at'] ?? '')),
                ];
                $status = in_array((string) ($_POST['status'] ?? ''), ['draft', 'pending', 'approved', 'published', 'revoked', 'archived'], true)
                    ? (string) $_POST['status'] : 'draft';

                if ($update['event_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $update['event_date'])) {
                    $errors[] = 'La fecha debe tener formato AAAA-MM-DD.';
                }
                if ($update['event_time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $update['event_time'])) {
                    $errors[] = 'La hora debe tener formato HH:MM.';
                }

                $promptTemplate = trim((string) ($_POST['prompt_template'] ?? ''));
                if ($promptTemplate !== '') {
                    $validation = cb_validate_invitation_prompt_template($promptTemplate, $themeData);
                    if (!$validation['ok']) {
                        $errors[] = $validation['error'];
                    } else {
                        $update['prompt_template'] = $validation['prompt'];
                    }
                }

                if (empty($errors)) {
                    // Orden atómico a propósito: primero se persisten los campos editados
                    // (nombre/fecha/hora/dirección/etc.), y RECIÉN DESPUÉS, si corresponde,
                    // se intenta la transición de estado. Publicar valida los datos ya
                    // guardados en la BD — nunca los datos viejos que el formulario está
                    // reemplazando en esta misma request. El orden inverso (que tenía este
                    // archivo antes) podía validar una invitación completa vieja y dejar
                    // publicados datos nuevos incompletos. Todo dentro de una transacción:
                    // si falla el paso de estado, el guardado de campos también se revierte.
                    if (!in_array($status, ['published', 'revoked', 'archived'], true)) {
                        $update['status'] = $status;
                    }
                    $pdo = cb_pdo();
                    $pdo->beginTransaction();
                    try {
                        $saveOk = cb_update_invitation((int) $id, $update, $by);
                        if (!$saveOk) {
                            throw new RuntimeException('No se pudo actualizar la invitación.');
                        }
                        if ($status === 'published') {
                            $pub = cb_publish_invitation((int) $id, $by);
                            if (!$pub['ok']) {
                                throw new RuntimeException($pub['error'] ?? 'No se pudo publicar.');
                            }
                        } elseif ($status === 'revoked') {
                            if (!cb_revoke_invitation((int) $id, $by)) {
                                throw new RuntimeException('No se pudo revocar la invitación.');
                            }
                        } elseif ($status === 'archived') {
                            if (!cb_update_invitation_status((int) $id, 'archived', $by)) {
                                throw new RuntimeException('No se pudo archivar la invitación.');
                            }
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errors[] = $e->getMessage();
                    }
                    if (empty($errors)) {
                        header('Location: ' . $invitationsUrl . '&ok=actualizada#inv-' . (int) $id);
                        exit;
                    }
                }
            }
        } elseif ($action === 'eliminar_invitacion') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            if ($id !== false && $id !== null && cb_invitation_owned_by_party((int) $id, $partyId) !== null && cb_delete_invitation((int) $id)) {
                header('Location: ' . $invitationsUrl . '&ok=eliminada');
                exit;
            }
            $errors[] = 'No se pudo eliminar la invitación.';
        } elseif ($action === 'duplicar_invitacion') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            if ($id !== false && $id !== null && cb_invitation_owned_by_party((int) $id, $partyId) !== null) {
                $res = cb_duplicate_invitation((int) $id, $partyId, $by);
                if (!empty($res['ok'])) {
                    $_SESSION['cc_invitation_token'] = ['id' => (int) $res['id'], 'token' => (string) $res['token']];
                    header('Location: ' . $invitationsUrl . '&ok=duplicada#inv-' . (int) $res['id']);
                    exit;
                }
                $errors[] = $res['error'] ?? 'No se pudo duplicar la invitación.';
            } else {
                $errors[] = 'Invitación inválida.';
            }
        } elseif ($action === 'regenerar_token') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            if ($id !== false && $id !== null && cb_invitation_owned_by_party((int) $id, $partyId) !== null) {
                $token = cb_regenerate_invitation_token((int) $id);
                if ($token !== null) {
                    $_SESSION['cc_invitation_token'] = ['id' => (int) $id, 'token' => $token];
                    header('Location: ' . $invitationsUrl . '&ok=token_regenerado#inv-' . (int) $id);
                    exit;
                }
            }
            $errors[] = 'No se pudo regenerar el enlace.';
        } elseif ($action === 'publicar') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            if ($id !== false && $id !== null && cb_invitation_owned_by_party((int) $id, $partyId) !== null) {
                $res = cb_publish_invitation((int) $id, $by);
                if ($res['ok']) {
                    header('Location: ' . $invitationsUrl . '&ok=publicada#inv-' . (int) $id);
                    exit;
                }
                $errors[] = $res['error'] ?? 'No se pudo publicar.';
            } else {
                $errors[] = 'Invitación inválida.';
            }
        } elseif ($action === 'revocar') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            if ($id !== false && $id !== null && cb_invitation_owned_by_party((int) $id, $partyId) !== null && cb_revoke_invitation((int) $id, $by)) {
                header('Location: ' . $invitationsUrl . '&ok=revocada#inv-' . (int) $id);
                exit;
            }
            $errors[] = 'No se pudo revocar la invitación.';
        } elseif ($action === 'subir_output_image' || $action === 'subir_output_video' || $action === 'subir_output_narracion') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            $outputType = match ($action) {
                'subir_output_image' => 'personalized_image',
                'subir_output_video' => 'personalized_video',
                default => 'personalized_narration_intro',
            };
            $assetKey = match ($outputType) {
                'personalized_image' => 'personalized-image',
                'personalized_video' => 'personalized-video',
                default => 'personalized-narration-intro',
            };
            $file = $_FILES['archivo'] ?? null;
            $uploadLimit = cb_rate_limit('invitation-upload:' . $publicSlug, cb_request_identity(), 20, 600, 600);
            if (!$uploadLimit['allowed']) {
                $errors[] = 'Demasiadas subidas seguidas, esperá unos minutos e intentá de nuevo.';
            } elseif ($id === false || $id === null || cb_invitation_owned_by_party((int) $id, $partyId) === null) {
                $errors[] = 'Invitación inválida.';
            } elseif ($file === null || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Selecciona un archivo válido.';
            } else {
                $validation = cb_validate_invitation_upload($file, $outputType);
                if (!$validation['ok']) {
                    $errors[] = $validation['error'];
                } else {
                    try {
                        $ext = admin_mime_to_ext((string) ($validation['mime'] ?? ''));
                        $version = admin_invitation_next_asset_version((int) $id, $assetKey);
                        $storageKey = cb_invitation_storage_key($publicSlug, $assetKey, $version, $ext);
                        $dstPath = cb_invitation_file_path($storageKey);
                        if (!$dstPath) {
                            throw new RuntimeException('No se pudo construir la ruta de destino.');
                        }
                        $dstDir = dirname($dstPath);
                        if (!is_dir($dstDir) && !mkdir($dstDir, 0770, true) && !is_dir($dstDir)) {
                            throw new RuntimeException('No se pudo crear el directorio de destino.');
                        }
                        if (!move_uploaded_file($file['tmp_name'], $dstPath)) {
                            throw new RuntimeException('No se pudo mover el archivo subido.');
                        }
                        @chmod($dstPath, 0660);
                        $save = cb_save_invitation_output((int) $id, [
                            'output_type' => $outputType,
                            'asset_key' => $assetKey,
                            'file_storage_key' => $storageKey,
                            'status' => 'pending',
                            'visual_source_json' => '',
                            'file_mime' => (string) ($validation['mime'] ?? 'application/octet-stream'),
                            'file_byte_size' => (int) ($validation['byte_size'] ?? 0),
                            'file_sha256' => hash_file('sha256', $dstPath),
                        ]);
                        if (empty($save['ok'])) {
                            @unlink($dstPath);
                            @rmdir($dstDir);
                            throw new RuntimeException($save['error'] ?? 'No se pudo guardar el output.');
                        }
                        header('Location: ' . $invitationsUrl . '&ok=output_subido#inv-' . (int) $id);
                        exit;
                    } catch (Throwable $e) {
                        error_log('CumpleClick subir output: ' . $e->getMessage());
                        $errors[] = 'Error al subir el archivo: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'cambiar_estado_output') {
            $outputId = filter_input(INPUT_POST, 'output_id', FILTER_VALIDATE_INT);
            $status = in_array((string) ($_POST['status'] ?? ''), ['pending', 'approved', 'rejected'], true) ? (string) $_POST['status'] : 'pending';
            // Nunca confiar en invitation_id posteado: se resuelve el dueño real
            // trazando output_id -> invitation_id -> party_id.
            $ownedOutput = $outputId !== false && $outputId !== null ? cb_invitation_output_owned_by_party((int) $outputId, $partyId) : null;
            if ($ownedOutput !== null && cb_update_invitation_output_status((int) $outputId, $status, $by)) {
                header('Location: ' . $invitationsUrl . '&ok=output_estado#inv-' . (int) $ownedOutput['invitation_id']);
                exit;
            }
            $errors[] = 'No se pudo cambiar el estado del output.';
        } elseif ($action === 'eliminar_output') {
            $outputId = filter_input(INPUT_POST, 'output_id', FILTER_VALIDATE_INT);
            $ownedOutput = $outputId !== false && $outputId !== null ? cb_invitation_output_owned_by_party((int) $outputId, $partyId) : null;
            if ($ownedOutput !== null && cb_delete_invitation_output((int) $outputId)) {
                header('Location: ' . $invitationsUrl . '&ok=output_eliminado#inv-' . (int) $ownedOutput['invitation_id']);
                exit;
            }
            $errors[] = 'No se pudo eliminar el output.';
        } elseif ($action === 'guardar_prompt') {
            $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT);
            $promptTemplate = trim((string) ($_POST['prompt_template'] ?? ''));
            if ($id === false || $id === null || cb_invitation_owned_by_party((int) $id, $partyId) === null) {
                $errors[] = 'Invitación inválida.';
            } else {
                $validation = cb_validate_invitation_prompt_template($promptTemplate, $themeData);
                if (!$validation['ok']) {
                    $errors[] = $validation['error'];
                } else {
                    if (cb_update_invitation((int) $id, ['prompt_template' => $validation['prompt']], $by)) {
                        header('Location: ' . $invitationsUrl . '&ok=prompt_guardado#inv-' . (int) $id);
                        exit;
                    }
                    $errors[] = 'No se pudo guardar el prompt.';
                }
            }
        }
    }
}

$okMessages = [
    'creada' => 'Invitación creada.',
    'actualizada' => 'Invitación actualizada.',
    'eliminada' => 'Invitación eliminada.',
    'duplicada' => 'Invitación duplicada.',
    'output_subido' => 'Archivo subido correctamente.',
    'output_estado' => 'Estado del output actualizado.',
    'output_eliminado' => 'Output eliminado.',
    'token_regenerado' => 'Enlace regenerado.',
    'publicada' => 'Invitación publicada.',
    'revocada' => 'Invitación revocada.',
    'prompt_guardado' => 'Prompt guardado.',
];
if (isset($_GET['ok'], $okMessages[$_GET['ok']])) {
    $okMessage = $okMessages[$_GET['ok']];
}

$invitations = $partyId !== null ? cb_list_invitations($partyId) : [];

$tokenFlash = null;
if (!empty($_SESSION['cc_invitation_token'])) {
    $flash = $_SESSION['cc_invitation_token'];
    if (is_array($flash) && isset($flash['id'], $flash['token'])) {
        $tokenFlash = $flash;
    }
    unset($_SESSION['cc_invitation_token']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CumpleBooth Admin · Invitaciones</title>
<style>
<?php require __DIR__ . '/_style.css.php'; ?>
.invite-wrap { max-width: 1180px; }
.invite-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap;
  padding: 8px 4px 18px; border-bottom: 4px solid var(--chip-accent, var(--primary)); margin-bottom: 22px;
}
.invite-head h1 { margin: 0 0 4px; font-size: clamp(1.4rem, 3vw, 1.9rem); }
.back-link { color: var(--primary); font-weight: 800; text-decoration: none; font-size: .88rem; }
.back-link:hover { text-decoration: underline; }
.invite-meta { color: var(--text-muted); font-size: .9rem; }

.status-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 999px; font-size: .76rem; font-weight: 700;
}
.status-draft { background: #f1f1f4; color: #6b7280; }
.status-pending { background: var(--warn-soft); color: var(--warn); }
.status-approved { background: var(--success-soft); color: var(--success); }
.status-published { background: #e0f2fe; color: #0369a1; }
.status-rejected { background: var(--danger-soft); color: var(--danger); }

.invite-grid { display: grid; grid-template-columns: 340px 1fr; gap: 22px; align-items: start; }
@media (max-width: 900px) { .invite-grid { grid-template-columns: 1fr; } }

.invite-form { display: flex; flex-direction: column; gap: 12px; }
.invite-form label { font-weight: 700; font-size: .9rem; }
.invite-form input[type="text"],
.invite-form input[type="date"],
.invite-form input[type="time"],
.invite-form select,
.invite-form textarea {
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 10px 12px; font-size: .95rem; font-family: var(--font-body);
  min-height: 44px; background: #fff; color: var(--text); width: 100%;
}
.invite-form textarea { min-height: 90px; resize: vertical; }
.invite-form small { color: var(--text-muted); font-size: .78rem; }

.invite-card {
  background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow);
  padding: 18px 20px; border-left: 5px solid var(--chip-accent, var(--primary));
}
.invite-card-header {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap;
}
.invite-title { font-family: var(--font-display); font-weight: 600; font-size: 1.2rem; margin: 0; }
.invite-dates { color: var(--text-muted); font-size: .82rem; margin-top: 6px; }
.invite-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }

.token-flash {
  background: #f3f0ff; border: 1.5px solid var(--primary); border-radius: var(--radius-sm);
  padding: 12px; margin: 14px 0; display: flex; flex-direction: column; gap: 8px;
}
.token-flash label { font-size: .8rem; font-weight: 700; color: var(--primary); }
.token-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.token-row input {
  flex: 1; min-width: 220px; min-height: 44px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 10px; font-size: .8rem; color: var(--text); background: #fff;
  font-family: ui-monospace, Consolas, monospace;
}

.outputs-section { margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--border); }
.outputs-section h4 { margin: 0 0 10px; font-size: .95rem; }
.output-list { display: flex; flex-direction: column; gap: 10px; }
.output-row {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; flex-wrap: wrap;
  padding: 10px 12px; border-radius: var(--radius-sm); background: #faf7ff;
}
.output-meta { font-size: .82rem; color: var(--text-muted); }
.output-meta code { font-size: .78rem; }
.output-actions { display: flex; gap: 6px; flex-wrap: wrap; }

.upload-output-form {
  margin-top: 12px; padding: 14px; border-radius: var(--radius-sm); background: #fff;
  border: 1.5px solid var(--border); display: flex; flex-direction: column; gap: 10px;
}
.upload-output-form h4 { margin: 0; font-size: .92rem; }

.prompt-box {
  background: #fff; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 12px; margin-top: 12px;
}
.prompt-box h4 { margin: 0 0 8px; font-size: .92rem; }
.prompt-box pre {
  background: #f8f6fb; border-radius: var(--radius-sm); padding: 10px;
  font-size: .82rem; white-space: pre-wrap; word-break: break-word; max-height: 180px; overflow: auto;
}
.prompt-box textarea {
  width: 100%; min-height: 120px; resize: vertical; border: 1.5px solid var(--border);
  border-radius: var(--radius-sm); padding: 10px; font-family: var(--font-body); font-size: .9rem;
}

.empty-invitations {
  text-align: center; color: var(--text-muted); padding: 42px 24px;
  display: flex; flex-direction: column; align-items: center; gap: 12px;
}
</style>
</head>
<body>
<div class="wrap invite-wrap">
  <header class="topbar">
    <div class="logo">
      <span class="logo-mark"><?= admin_icon('party') ?></span>
      CumpleBooth <span>Admin</span>
    </div>
    <form method="post" action="invitations.php?party=<?= rawurlencode($publicSlug) ?>" class="inline-form logout-btn">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="logout">
      <button class="btn btn-ghost" type="submit"><?= admin_icon('logout') ?> Salir</button>
    </form>
  </header>

  <?php if ($party !== null): ?>
  <div class="invite-head" style="--chip-accent: <?= h($themeData['colors']['accent'] ?? '#7C3AED') ?>">
    <div>
      <a class="back-link" href="index.php">← Volver a fiestas</a>
      <h1>Invitaciones · <?= h($party['admin_label'] ?: $party['birthday_person_name']) ?></h1>
      <div class="invite-meta">
        <?= h($party['birthday_person_name']) ?> · <?= h($themeName) ?> · plan <?= h($party['service_plan']) ?>
        <?php if (!empty($party['gallery_enabled'])): ?> · galería habilitada<?php endif; ?>
        · <?= count($invitations) ?> invitación(es)
      </div>
    </div>
    <div class="party-actions">
      <a class="btn btn-ghost" href="<?= h($baseUrl . '?p=' . rawurlencode($publicSlug)) ?>" target="_blank" rel="noopener"><?= admin_icon('external') ?> Fiesta</a>
      <?php if (!empty($party['gallery_enabled'])): ?>
        <a class="btn btn-ghost" href="<?= h($baseUrl . 'galeria.php?p=' . rawurlencode($publicSlug)) ?>" target="_blank" rel="noopener"><?= admin_icon('gallery') ?> Galería</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <main>
    <?php if ($okMessage): ?>
      <p class="alert alert-ok"><?= admin_icon('check') ?> <?= h($okMessage) ?></p>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?= admin_icon('warn') ?>
        <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($party === null || $partyId === null): ?>
      <section class="card empty-state">
        <span class="empty-icon"><?= admin_icon('warn') ?></span>
        <p><strong>No se puede gestionar invitaciones.</strong><br><?= h($errors[0] ?? 'Verifica el enlace.') ?></p>
        <a class="btn btn-ghost" href="index.php">Volver al panel</a>
      </section>
    <?php else: ?>
      <div class="invite-grid">
        <aside class="card">
          <h2><?= admin_icon('plus') ?> Nueva invitación</h2>
          <p class="muted small">Crea un registro de invitación. El enlace de descarga opaco se genera automáticamente y se mostrará una sola vez.</p>
          <form method="post" action="<?= h($invitationsUrl) ?>" enctype="multipart/form-data" class="invite-form">
            <?= admin_csrf_field() ?>
            <input type="hidden" name="action" value="crear_invitacion">

            <label for="i-name">Nombre del cumpleañero/a</label>
            <input type="text" id="i-name" name="birthday_person_name" maxlength="120" placeholder="Ej. Martina">

            <label for="i-gender">Cumpleañero o cumpleañera</label>
            <select id="i-gender" name="birthday_person_gender">
              <option value="">Sin especificar</option>
              <option value="m">Niño (cumpleañero)</option>
              <option value="f">Niña (cumpleañera)</option>
            </select>
            <p class="small muted">Elige la narración de cierre de Alice ("toca el botón para conocer al cumpleañero/a"). Sin especificar usa un audio neutro.</p>

            <label for="i-date">Fecha del evento</label>
            <input type="date" id="i-date" name="event_date" value="<?= h($party['event_date'] ?? '') ?>">

            <label for="i-time">Hora del evento</label>
            <input type="time" id="i-time" name="event_time" value="<?= h($party['event_time'] ?? '') ?>">

            <label for="i-address">Dirección / lugar</label>
            <input type="text" id="i-address" name="address" maxlength="255" placeholder="Ej. Salón Arcoíris, Av. ...">

            <label for="i-message">Mensaje personalizado</label>
            <textarea id="i-message" name="message" maxlength="1000" placeholder="Opcional: texto adicional para el invitado."></textarea>

            <label for="i-prompt">Prompt de invitación (opcional)</label>
            <textarea id="i-prompt" name="prompt_template" maxlength="20000" placeholder="Si lo dejas en blanco se usa el prompt por defecto con los placeholders [NOMBRE_DEL_CUMPLEAÑERO], [FECHA_Y_HORA], [DIRECCIÓN]."></textarea>
            <small>No incluyas nombres de franquicia o personajes protegidos.</small>

            <label for="i-lang">Idioma</label>
            <select id="i-lang" name="language">
              <option value="es" selected>Español</option>
              <option value="en">English</option>
              <option value="pt">Português</option>
            </select>

            <label for="i-channel">Canal</label>
            <select id="i-channel" name="channel">
              <option value="whatsapp" selected>WhatsApp</option>
              <option value="email">Email</option>
              <option value="print">Impresión</option>
            </select>

            <button type="submit" class="btn btn-primary">Crear invitación</button>
          </form>
        </aside>

        <section>
          <h2>Invitaciones</h2>
          <?php if (empty($invitations)): ?>
            <div class="card empty-invitations">
              <span class="empty-icon"><?= admin_icon('party') ?></span>
              <p><strong>Aún no hay invitaciones.</strong><br>Crea la primera con el formulario.</p>
            </div>
          <?php else: ?>
            <?php foreach ($invitations as $inv): ?>
              <?php
              $status = (string) $inv['status'];
              $isPublished = $status === 'published';
              $isRevoked = $status === 'revoked';
              $isArchived = $status === 'archived';
              $outputs = cb_load_invitation_outputs((int) $inv['id']);
              $approvedImage = null;
              $approvedVideo = null;
              $hasApprovedImage = false;
              foreach ($outputs as $o) {
                  $oStatus = (string) $o['status'];
                  $oType = (string) $o['output_type'];
                  if ($oStatus === 'approved') {
                      if ($oType === 'personalized_image' && !$hasApprovedImage) {
                          $hasApprovedImage = true;
                          $approvedImage = $o;
                      } elseif ($oType === 'personalized_video' && !$approvedVideo) {
                          $approvedVideo = $o;
                      }
                  }
              }
              $missingMandatory = cb_invitation_mandatory_missing($inv);
              $canPublish = empty($missingMandatory) && $hasApprovedImage && !$isPublished && !$isRevoked && !$isArchived;
              $compiled = cb_compile_invitation_prompt(
                  trim((string) ($inv['prompt_template'] ?? '')) !== '' ? (string) $inv['prompt_template'] : cb_default_invitation_prompt_template(),
                  [
                      'birthday_person_name' => (string) ($inv['birthday_person_name'] ?? ''),
                      'event_date' => (string) ($inv['event_date'] ?? ''),
                      'event_time' => (string) ($inv['event_time'] ?? ''),
                      'address' => (string) ($inv['address'] ?? ''),
                  ]
              );
              $showToken = $tokenFlash !== null && (int) $tokenFlash['id'] === (int) $inv['id'];
              $publicUrl = $showToken ? cb_invitation_public_url($tokenFlash['token']) : '';
              $downloadUrl = $showToken ? cb_invitation_download_url($tokenFlash['token']) : '';
              // Enlace reconstruible desde el ID: sirve aunque el token en claro
              // se haya perdido, sin revocar el aleatorio ni guardarlo en texto.
              $shareUrl = '';
              $shareUrlLarga = '';
              $previewBasico = '';
              $previewFull = '';
              try {
                  $shareToken = cb_invitation_share_token((int) $inv['id']);
                  // Bonita para compartir; larga como respaldo por si el hosting
                  // pierde la regla de reescritura del .htaccess.
                  $shareUrl = cb_invitation_pretty_url($shareToken, (string) ($inv['birthday_person_name'] ?? ''));
                  $shareUrlLarga = cb_invitation_public_url($shareToken);
                  // La bonita no trae query string: acá el separador es '?'.
                  $previewBasico = $shareUrl . '?hero=scroll&capitulos=1&qa='
                      . cb_invitation_preview_mac((int) $inv['id'], 'scroll', '1');
                  $previewFull = $shareUrl . '?hero=auto&capitulos=auto&qa='
                      . cb_invitation_preview_mac((int) $inv['id'], 'auto', 'auto');
              } catch (Throwable $e) {
                  $shareUrl = '';
                  $shareUrlLarga = '';
              }
              $planFiesta = cb_invitation_service_plan(
                  isset($inv['party_id']) && $inv['party_id'] !== null ? (int) $inv['party_id'] : null
              );
              ?>
              <article class="invite-card" id="inv-<?= (int) $inv['id'] ?>">
                <div class="invite-card-header">
                  <div>
                    <p class="invite-title"><?= h($inv['birthday_person_name'] ?: '(sin nombre)') ?></p>
                    <div class="invite-dates">
                      <span class="status-badge <?= h(admin_status_class($status)) ?>"><?= h(admin_status_label($status)) ?></span>
                      · <?= h($inv['event_date'] ?: '—') ?> <?= h($inv['event_time'] ?: '') ?>
                      · <?= h($inv['language'] ?: 'es') ?> · <?= h($inv['channel'] ?: 'whatsapp') ?>
                      · creada <?= admin_format_datetime($inv['created_at']) ?>
                    </div>
                  </div>
                  <div>
                    <span class="badge badge-off">#<?= (int) $inv['id'] ?></span>
                  </div>
                </div>

                <?php if ($inv['address'] !== ''): ?><p class="small muted">📍 <?= h($inv['address']) ?></p><?php endif; ?>
                <?php if ($inv['message'] !== ''): ?><p class="small muted">💬 <?= h($inv['message']) ?></p><?php endif; ?>

                <?php if ($showToken && $publicUrl !== ''): ?>
                <div class="token-flash">
                  <label>Enlace público (se muestra una sola vez; guárdalo)</label>
                  <div class="token-row">
                    <input id="public-url-<?= (int) $inv['id'] ?>" type="text" readonly value="<?= h($publicUrl) ?>">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('public-url-<?= (int) $inv['id'] ?>').value)"><?= admin_icon('copy') ?> Copiar</button>
                    <a class="btn btn-ghost btn-sm" href="<?= h($publicUrl) ?>" target="_blank" rel="noopener"><?= admin_icon('external') ?> Abrir</a>
                  </div>
                  <div class="token-row">
                    <input id="download-url-<?= (int) $inv['id'] ?>" type="text" readonly value="<?= h($downloadUrl) ?>">
                    <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('download-url-<?= (int) $inv['id'] ?>').value)"><?= admin_icon('copy') ?> Copiar descarga</button>
                    <a class="btn btn-ghost btn-sm" href="<?= h($downloadUrl) ?>"><?= admin_icon('download') ?> Descargar</a>
                  </div>
                </div>
                <?php endif; ?>

                <?php if ($shareUrl !== ''): ?>
                <div class="token-row">
                  <input id="share-url-<?= (int) $inv['id'] ?>" type="text" readonly value="<?= h($shareUrl) ?>">
                  <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('share-url-<?= (int) $inv['id'] ?>').value)"><?= admin_icon('copy') ?> Copiar enlace</button>
                </div>
                <p class="small muted">
                  Enlace de respaldo, por si el hosting pierde la regla de URL bonita:<br>
                  <code><?= h($shareUrlLarga) ?></code>
                </p>
                <p class="small muted">
                  El de arriba es el que le mandas al cliente. Se puede recuperar siempre,
                  aunque hayas perdido el que salió al crear la invitación.
                  Entrega la versión
                  <strong><?= $planFiesta === 'full' ? 'Automática (Plan Full)' : 'Scroll (Plan Básico)' ?></strong>,
                  según el plan de la fiesta. Para cambiarla, cambia el plan en Editar fiesta.
                </p>
                <div class="token-row">
                  <a class="btn btn-ghost btn-sm" href="<?= h($previewBasico) ?>" target="_blank" rel="noopener"><?= admin_icon('external') ?> Ver Básico (scroll)</a>
                  <a class="btn btn-ghost btn-sm" href="<?= h($previewFull) ?>" target="_blank" rel="noopener"><?= admin_icon('external') ?> Ver Full (automática)</a>
                </div>
                <p class="small muted">
                  Vista previa solo para ti: sirve para comparar los dos planes. No mandes
                  estos dos enlaces al cliente, llevan la variante forzada.
                </p>
                <?php endif; ?>

                <div class="invite-actions">
                  <?php if (!$isPublished && !$isRevoked && !$isArchived): ?>
                    <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="regenerar_token">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('refresh') ?> Regenerar enlace</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canPublish): ?>
                    <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="publicar">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                      <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon('external') ?> Publicar</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isPublished): ?>
                    <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="revocar">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm"><?= admin_icon('warn') ?> Revocar</button>
                    </form>
                  <?php endif; ?>
                  <?php if (!$isPublished && !$isArchived): ?>
                    <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form" data-confirm="¿Archivar esta invitación?">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="actualizar_invitacion">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                      <input type="hidden" name="status" value="archived">
                      <button type="submit" class="btn btn-ghost btn-sm">Archivar</button>
                    </form>
                  <?php endif; ?>
                  <?php if (!empty($missingMandatory)): ?>
                    <span class="small muted">Faltan: <?= h(implode(', ', $missingMandatory)) ?></span>
                  <?php endif; ?>
                </div>

                <div class="prompt-box">
                  <h4><?= admin_icon('edit') ?> Prompt compilado</h4>
                  <?php if ($compiled['ok']): ?>
                    <pre><?= h($compiled['prompt']) ?></pre>
                  <?php else: ?>
                    <p class="small muted"><?= admin_icon('warn') ?> <?= h($compiled['error']) ?></p>
                  <?php endif; ?>
                  <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="invite-form" style="margin-top:8px;">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="guardar_prompt">
                    <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                    <label>Plantilla del prompt</label>
                    <textarea name="prompt_template" maxlength="20000"><?= h(trim((string) ($inv['prompt_template'] ?? '')) !== '' ? (string) $inv['prompt_template'] : cb_default_invitation_prompt_template()) ?></textarea>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('check') ?> Guardar plantilla</button>
                  </form>
                </div>

                <div class="outputs-section">
                  <h4>Outputs (<?= count($outputs) ?>)</h4>
                  <?php if (empty($outputs)): ?>
                    <p class="small muted">No hay archivos asociados. Sube imagen y video personalizados para habilitar la descarga.</p>
                  <?php else: ?>
                    <div class="output-list">
                      <?php foreach ($outputs as $o):
                        $oStatus = (string) $o['status'];
                        $oType = (string) $o['output_type'];
                        $oPath = cb_invitation_file_path((string) $o['file_storage_key']);
                        $oExists = $oPath && is_file($oPath);
                      ?>
                        <div class="output-row">
                          <div class="output-meta">
                            <?= $oType === 'personalized_narration_intro' ? '🔊' : ($oType === 'personalized_video' ? admin_icon('video') : admin_icon('image')) ?>
                            <code><?= h($o['asset_key']) ?></code> · <?= h($oType) ?> · <span class="status-badge <?= h(admin_status_class($oStatus)) ?>"><?= h(admin_status_label($oStatus)) ?></span>
                            · <?= admin_format_bytes((int) ($o['file_byte_size'] ?? 0)) ?>
                            · <?= h($o['file_mime'] ?: '—') ?>
                            <?= !$oExists ? '· <strong class="status-rejected">archivo no encontrado</strong>' : '' ?>
                          </div>
                          <div class="output-actions">
                            <?php if ($oStatus !== 'approved'): ?>
                              <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form">
                                <?= admin_csrf_field() ?>
                                <input type="hidden" name="action" value="cambiar_estado_output">
                                <input type="hidden" name="output_id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('check') ?> Aprobar</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($oStatus !== 'rejected'): ?>
                              <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form">
                                <?= admin_csrf_field() ?>
                                <input type="hidden" name="action" value="cambiar_estado_output">
                                <input type="hidden" name="output_id" value="<?= (int) $o['id'] ?>">
                                <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="btn btn-ghost btn-sm">Rechazar</button>
                              </form>
                            <?php endif; ?>
                            <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="inline-form" data-confirm="¿Eliminar este archivo?">
                              <?= admin_csrf_field() ?>
                              <input type="hidden" name="action" value="eliminar_output">
                              <input type="hidden" name="output_id" value="<?= (int) $o['id'] ?>">
                              <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                              <button type="submit" class="btn btn-danger btn-sm"><?= admin_icon('trash') ?></button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <?php if (!$isArchived && !$isRevoked): ?>
                  <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" enctype="multipart/form-data" class="upload-output-form">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="subir_output_image">
                    <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                    <h4><?= admin_icon('image') ?> Subir imagen personalizada</h4>
                    <p class="small muted">JPG/PNG/WebP · máx. <?= number_format(cb_theme_image_max_bytes() / 1048576, 1) ?> MB · mín. 320×320px</p>
                    <input type="file" name="archivo" accept=".jpg,.jpeg,.png,.webp" required>
                    <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon('plus') ?> Subir imagen</button>
                  </form>

                  <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" enctype="multipart/form-data" class="upload-output-form">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="subir_output_video">
                    <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                    <h4><?= admin_icon('video') ?> Subir video personalizado (opcional)</h4>
                    <p class="small muted">MP4 · máx. <?= number_format(cb_theme_upload_max_bytes() / 1048576, 1) ?> MB</p>
                    <input type="file" name="archivo" accept=".mp4" required>
                    <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon('plus') ?> Subir video</button>
                  </form>

                  <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" enctype="multipart/form-data" class="upload-output-form">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="subir_output_narracion">
                    <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                    <h4><?= admin_icon('video') ?> Subir narración de inicio (voz Alice, opcional)</h4>
                    <p class="small muted">MP3 · máx. 5 MB · generar con ElevenLabs, voice_id <code>Xb7hH8MSUJpSbSDYk0k2</code>, modelo <code>eleven_multilingual_v2</code>, texto: "Tenemos el agrado de invitarte a celebrar el cumpleaños de <?= h($inv['birthday_person_name'] ?: '[NOMBRE]') ?>. Es el <?= h($inv['event_date'] ?: '[FECHA]') ?><?= !empty($inv['event_time']) ? ' a las ' . h((string) $inv['event_time']) : '' ?>." — ver docs/INVITACION-MUSICA-Y-NARRACION-ALICE.md</p>
                    <input type="file" name="archivo" accept=".mp3" required>
                    <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon('plus') ?> Subir narración</button>
                  </form>
                  <?php endif; ?>
                </div>

                <details class="prompt-box" style="margin-top: 14px;">
                  <summary>Editar invitación</summary>
                  <form method="post" action="<?= h($invitationsUrl) ?>#inv-<?= (int) $inv['id'] ?>" class="invite-form" style="margin-top: 10px;">
                    <?= admin_csrf_field() ?>
                    <input type="hidden" name="action" value="actualizar_invitacion">
                    <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">

                    <label>Nombre del cumpleañero/a</label>
                    <input type="text" name="birthday_person_name" value="<?= h($inv['birthday_person_name']) ?>" required maxlength="120">

                    <label>Cumpleañero o cumpleañera</label>
                    <select name="birthday_person_gender">
                      <option value="" <?= ($inv['birthday_person_gender'] ?? '') === '' ? 'selected' : '' ?>>Sin especificar</option>
                      <option value="m" <?= ($inv['birthday_person_gender'] ?? '') === 'm' ? 'selected' : '' ?>>Niño (cumpleañero)</option>
                      <option value="f" <?= ($inv['birthday_person_gender'] ?? '') === 'f' ? 'selected' : '' ?>>Niña (cumpleañera)</option>
                    </select>

                    <label>Fecha del evento</label>
                    <input type="date" name="event_date" value="<?= h($inv['event_date']) ?>">

                    <label>Hora del evento</label>
                    <input type="time" name="event_time" value="<?= h($inv['event_time']) ?>">

                    <label>Dirección / lugar</label>
                    <input type="text" name="address" value="<?= h($inv['address']) ?>" maxlength="255">

                    <label>Mensaje personalizado</label>
                    <textarea name="message" maxlength="1000"><?= h($inv['message']) ?></textarea>

                    <label>Plantilla del prompt</label>
                    <textarea name="prompt_template" maxlength="20000"><?= h(trim((string) ($inv['prompt_template'] ?? '')) !== '' ? (string) $inv['prompt_template'] : cb_default_invitation_prompt_template()) ?></textarea>

                    <label>Idioma</label>
                    <select name="language">
                      <?php foreach (['es' => 'Español', 'en' => 'English', 'pt' => 'Português'] as $k => $l): ?>
                        <option value="<?= h($k) ?>" <?= ($inv['language'] ?? 'es') === $k ? 'selected' : '' ?>><?= h($l) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <label>Canal</label>
                    <select name="channel">
                      <?php foreach (['whatsapp' => 'WhatsApp', 'email' => 'Email', 'print' => 'Impresión'] as $k => $l): ?>
                        <option value="<?= h($k) ?>" <?= ($inv['channel'] ?? 'whatsapp') === $k ? 'selected' : '' ?>><?= h($l) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <label>Estado</label>
                    <select name="status">
                      <?php foreach (['draft' => 'Borrador', 'pending' => 'Pendiente', 'approved' => 'Aprobada', 'published' => 'Publicada', 'revoked' => 'Revocada', 'archived' => 'Archivada'] as $k => $l): ?>
                        <option value="<?= h($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($l) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <label>Vence (AAAA-MM-DD, opcional)</label>
                    <input type="date" name="expires_at" value="<?= h(!empty($inv['expires_at']) && (string) $inv['expires_at'] !== '0000-00-00 00:00:00' ? date('Y-m-d', strtotime((string) $inv['expires_at'])) : '') ?>">

                    <div class="invite-actions">
                      <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                  </form>

                  <div class="invite-actions" style="margin-top: 12px;">
                    <form method="post" action="<?= h($invitationsUrl) ?>" class="inline-form" data-confirm="¿Duplicar esta invitación?">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="duplicar_invitacion">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm"><?= admin_icon('duplicate') ?> Duplicar</button>
                    </form>
                    <form method="post" action="<?= h($invitationsUrl) ?>" class="inline-form" data-confirm="¿Eliminar la invitación de <?= h(addslashes($inv['birthday_person_name'] ?: 'este cumpleañero')) ?>?">
                      <?= admin_csrf_field() ?>
                      <input type="hidden" name="action" value="eliminar_invitacion">
                      <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm"><?= admin_icon('trash') ?> Eliminar</button>
                    </form>
                  </div>
                </details>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </section>
      </div>
    <?php endif; ?>
  </main>
</div>

<script>
(function () {
  Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });
})();
</script>
</body>
</html>
